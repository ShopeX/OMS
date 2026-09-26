<?php

/**
 * SAP 库存差异生成供应计划库存调整单（按批次合并一单）
 */
class goldentra_stock_adjustment
{
    /**
     * @param array $ctx stock_id, branch_id, branch_bn, unique_code, diff_qty, snapshot_date, sync_batch_no, bn
     * @return array bill_bn, action, message
     */
    public function createFromDiff($ctx)
    {
        $result = $this->createFromDiffBatch(array($ctx));
        if (empty($result)) {
            return array('bill_bn' => '', 'action' => 'none', 'message' => '库存一致');
        }
        return $result[0];
    }

    /**
     * 按 sync_batch_no + branch + 方向 合并为一单
     * 单个唯一码缺基础物料只记该行失败，不影响同组其它行开单
     *
     * @param array $rows
     * @return array[]
     */
    public function createFromDiffBatch(array $rows)
    {
        $groups = array();
        foreach ($rows as $ctx) {
            $diff = (int) $ctx['diff_qty'];
            if ($diff === 0) {
                continue;
            }
            $direction = $diff > 0 ? 'more' : 'less';
            $key = $ctx['sync_batch_no'] . '|' . $ctx['branch_bn'] . '|' . $direction;
            if (!isset($groups[$key])) {
                $groups[$key] = array(
                    'direction' => $direction,
                    'ctx' => $ctx,
                    'lines' => array(),
                );
            }
            $groups[$key]['lines'][] = $ctx;
        }

        $results = array();
        foreach ($groups as $group) {
            foreach ($this->createGroupAdjust($group['ctx'], $group['lines'], $group['direction']) as $result) {
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * @param array[] $results createFromDiffBatch 返回值
     * @return array<int,string> stock_id => message（仅成功开单行）
     */
    public function buildStockMessageMap(array $results)
    {
        $msgByStockId = array();
        foreach ($results as $result) {
            if (!empty($result['status']) && $result['status'] === 'fail') {
                continue;
            }
            if (empty($result['stock_ids'])) {
                continue;
            }
            $msg = !empty($result['message']) ? $result['message'] : '已生成批次调整单';
            foreach ($result['stock_ids'] as $stock_id) {
                $msgByStockId[(int) $stock_id] = $msg;
            }
        }
        return $msgByStockId;
    }

    /**
     * @param array[] $results createFromDiffBatch 返回值
     * @return array<int,string> stock_id => 该唯一码自己的失败原因
     */
    public function buildStockFailMap(array $results)
    {
        $msgByStockId = array();
        foreach ($results as $result) {
            if (empty($result['status']) || $result['status'] !== 'fail') {
                continue;
            }
            if (empty($result['stock_ids'])) {
                continue;
            }
            $msg = !empty($result['message']) ? $result['message'] : '处理失败';
            foreach ($result['stock_ids'] as $stock_id) {
                $sid = (int) $stock_id;
                if ($sid <= 0) {
                    continue;
                }
                $msgByStockId[$sid] = $msg;
            }
        }
        return $msgByStockId;
    }

    /**
     * @return array[]
     */
    private function createGroupAdjust($head, array $lines, $direction)
    {
        $items = array();
        $snMap = array();
        $codes = array();
        $okLines = array();
        $results = array();

        foreach ($lines as $line) {
            $diff = (int) $line['diff_qty'];
            if ($diff === 0) {
                continue;
            }
            $bn = isset($line['bn']) ? trim((string) $line['bn']) : '';
            if ($bn === '') {
                $results[] = $this->failResult($line, '基础物料编码为空');
                continue;
            }
            $bm = app::get('material')->model('basic_material')->dump(
                array('material_bn' => $bn),
                'bm_id,material_bn'
            );
            if (empty($bm['bm_id'])) {
                $results[] = $this->failResult($line, '基础物料不存在: ' . $bn);
                continue;
            }
            $bmId = $bm['bm_id'];
            if (!isset($items[$bmId])) {
                $items[$bmId] = 0;
                $snMap[$bmId] = array();
            }
            $items[$bmId] += $diff;
            $snMap[$bmId][] = $line['unique_code'];
            $codes[] = $line['unique_code'];
            $okLines[] = $line;
        }

        if (empty($items)) {
            return $results;
        }

        $adjust_type = $direction === 'more' ? 'more' : 'less';
        $origin_id = $this->batchOriginId($head['sync_batch_no'], $head['branch_bn'], $adjust_type);
        $source = !empty($head['adjust_source']) ? $head['adjust_source'] : 'SAP库存同步';
        $memo = sprintf(
            '%s 批次:%s 仓:%s 共%d条唯一码',
            $source,
            $head['sync_batch_no'],
            $head['branch_bn'],
            count($codes)
        );
        $data = array(
            'adjust_mode' => 'zl',
            'is_check' => '0',
            'iso_status' => 'confirm',
            'branch_id' => $head['branch_id'],
            'negative_branch_id' => array($head['branch_id']),
            'memo' => $memo,
            'source' => $source,
            'adjust_type' => 'cy',
            'adjust_bill_type' => $head['branch_bn'] . '_' . $adjust_type,
            'origin_id' => $origin_id,
            'origin_bn' => $head['sync_batch_no'],
            'business_bn' => $head['sync_batch_no'],
            'adjust_channel' => 'branchadjust',
            'items' => $items,
            'sn' => $snMap,
        );

        list($rs, $rsData) = kernel::single('console_adjust')->dealSave($data);
        if (!$rs) {
            $msg = '库存调整单生成失败: ' . (isset($rsData['msg']) ? $rsData['msg'] : '');
            foreach ($okLines as $line) {
                $results[] = $this->failResult($line, $msg);
            }
            return $results;
        }

        $bill_bn = isset($rsData['adjust_bn']) ? $rsData['adjust_bn'] : '';
        $totalDiff = array_sum($items);
        $results[] = array(
            'status' => 'succ',
            'bill_bn' => $bill_bn,
            'action' => $totalDiff > 0 ? 'stock_in' : 'stock_out',
            'branch_bn' => $head['branch_bn'],
            'stock_ids' => array_column($okLines, 'stock_id'),
            'message' => sprintf(
                '已生成库存调整单[%s] 仓:%s 明细%d条 diff合计:%s',
                $bill_bn,
                $head['branch_bn'],
                count($codes),
                $totalDiff
            ),
        );
        return $results;
    }

    private function failResult(array $line, $message)
    {
        return array(
            'status' => 'fail',
            'bill_bn' => '',
            'action' => 'fail',
            'branch_bn' => isset($line['branch_bn']) ? $line['branch_bn'] : '',
            'stock_ids' => array((int) $line['stock_id']),
            'unique_code' => isset($line['unique_code']) ? $line['unique_code'] : '',
            'message' => $message,
        );
    }

    private function batchOriginId($sync_batch_no, $branch_bn, $adjust_type)
    {
        return abs(crc32($sync_batch_no . '|' . $branch_bn . '|' . $adjust_type)) % 2147483647;
    }
}
