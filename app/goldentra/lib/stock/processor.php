<?php

class goldentra_stock_processor
{
    const BATCH_SIZE = 1000;

    public function processPending($limit = self::BATCH_SIZE, $sync_batch_no = '')
    {
        $mcLib = kernel::single('goldentra_stock_machinecenter');
        if (!$mcLib->isInstalled()) {
            throw new Exception('机器中心未安装，无法比对库存');
        }

        $filter = array(
            'process_status|in' => array('pending', 'fail'),
            'stock_type' => 'increment',
        );
        $sync_batch_no = trim((string) $sync_batch_no);
        if ($sync_batch_no !== '') {
            $filter['sync_batch_no'] = $sync_batch_no;
        }

        $mdl = app::get('goldentra')->model('stock');
        $list = $mdl->getList('*', $filter, 0, $limit, 'stock_id ASC');
        $processed = 0;
        $diffRows = array();
        $diffStockIds = array();

        foreach ($list as $row) {
            $mdl->update(
                array('process_status' => 'running', 'up_time' => time()),
                array('stock_id' => $row['stock_id'])
            );

            try {
                $ctx = $this->compareRow($row);
                if ($ctx === null) {
                    $processed++;
                    continue;
                }
                $diffRows[] = $ctx;
                $diffStockIds[] = $row['stock_id'];
                $processed++;
            } catch (Exception $e) {
                kernel::single('goldentra_stock')->markFail($row['stock_id'], $e->getMessage());
            }
        }

        if ($diffRows) {
            $this->applyBatchAdjustments($diffRows, $diffStockIds);
        }

        return $processed;
    }

    /**
     * @return array|null 有差异时返回调整上下文
     */
    public function compareRow($row)
    {
        $stockLib = kernel::single('goldentra_stock');
        $mcLib = kernel::single('goldentra_stock_machinecenter');

        $latest = $stockLib->getLatestProcessedSnapshot(
            isset($row['plant']) ? $row['plant'] : '',
            $row['branch_bn'],
            $row['unique_code']
        );
        if ($latest !== '' && $row['snapshot_date'] < $latest) {
            $stockLib->markSkip(
                $row['stock_id'],
                sprintf('快照过期，当前 %s < 已生效 %s', $row['snapshot_date'], $latest)
            );
            return null;
        }

        $plant = isset($row['plant']) ? $row['plant'] : '';
        $branch = $stockLib->resolveBranch($plant, $row['branch_bn']);
        if (empty($branch['branch_id'])) {
            $stockLib->markFail(
                $row['stock_id'],
                $stockLib->formatBranchNotFoundMsg($plant, $row['branch_bn'])
            );
            return null;
        }

        // 电商仓增量：落库后标记无需处理，不出调整单
        if (kernel::single('goldentra_ecwarehouse')->isEcByBranchId($branch['branch_id'])) {
            $stockLib->markSkip($row['stock_id'], goldentra_stock_diff::MSG_EC_INCREMENT_SKIP);
            return null;
        }

        $material = app::get('goldentra')->model('material')->dump(
            array('unique_code' => $row['unique_code']),
            'material_id,bn,match_status'
        );
        if (empty($material) || $material['match_status'] != '1' || empty($material['bn'])) {
            $stockLib->markFail($row['stock_id'], '主档未匹配: ' . $row['unique_code']);
            return null;
        }

        $serial = $mcLib->loadSn($branch['branch_id'], $row['unique_code']);
        $oms_qty = $mcLib->calcOmsQty($serial);
        $diff_qty = bcsub((string) $row['qty'], (string) $oms_qty, 3);

        if (bccomp($diff_qty, '0', 3) === 0) {
            $stockLib->markSucc($row['stock_id'], '库存一致', $oms_qty, $diff_qty);
            return null;
        }

        if ($mcLib->isSoldOut($serial) && bccomp($diff_qty, '0', 3) < 0) {
            $stockLib->markFail(
                $row['stock_id'],
                '唯一码已出库，禁止调整',
                $oms_qty,
                $diff_qty
            );
            return null;
        }

        $stockLib->markSucc($row['stock_id'], '待生成批次调整单', $oms_qty, $diff_qty);

        return array(
            'stock_id' => $row['stock_id'],
            'branch_id' => $branch['branch_id'],
            'branch_bn' => $branch['branch_bn'],
            'plant' => $plant,
            'location_bn' => $row['branch_bn'],
            'unique_code' => $row['unique_code'],
            'diff_qty' => $diff_qty,
            'snapshot_date' => $row['snapshot_date'],
            'sync_batch_no' => $row['sync_batch_no'],
            'bn' => $material['bn'],
        );
    }

    public function processOne($row)
    {
        $ctx = $this->compareRow($row);
        if ($ctx === null) {
            return;
        }
        $this->applyBatchAdjustments(array($ctx), array($row['stock_id']));
    }

    private function applyBatchAdjustments(array $diffRows, array $stockIds)
    {
        $stockLib = kernel::single('goldentra_stock');
        $adjustment = kernel::single('goldentra_stock_adjustment');
        try {
            $results = $adjustment->createFromDiffBatch($diffRows);
        } catch (Exception $e) {
            foreach ($stockIds as $stock_id) {
                $row = app::get('goldentra')->model('stock')->dump($stock_id, 'oms_qty,diff_qty');
                $stockLib->markFail(
                    $stock_id,
                    '库存调整异常: ' . $e->getMessage(),
                    $row['oms_qty'],
                    $row['diff_qty']
                );
            }
            return;
        }

        $msgByStockId = $adjustment->buildStockMessageMap($results);
        $failByStockId = $adjustment->buildStockFailMap($results);
        foreach ($stockIds as $stock_id) {
            $row = app::get('goldentra')->model('stock')->dump($stock_id, 'oms_qty,diff_qty');
            if (isset($failByStockId[$stock_id])) {
                $stockLib->markFail($stock_id, $failByStockId[$stock_id], $row['oms_qty'], $row['diff_qty']);
                continue;
            }
            $msg = isset($msgByStockId[$stock_id])
                ? $msgByStockId[$stock_id]
                : '已生成批次调整单';
            $stockLib->markSucc($stock_id, $msg, $row['oms_qty'], $row['diff_qty']);
        }
    }
}
