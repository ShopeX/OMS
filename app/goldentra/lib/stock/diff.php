<?php

/**
 * 电商仓库存差异落库
 */
class goldentra_stock_diff
{
    const MSG_EC_INCREMENT_SKIP = '电商仓无需处理';
    const MSG_EC_FULL_DIFF = '电商仓差异已记入差异表，无需调整';

    /**
     * 差异类型：唯一码维度
     *
     * @param mixed $sap_qty
     * @param mixed $oms_qty
     * @return string qty_mismatch|oms_only|sap_only
     */
    public function classifyDiffType($sap_qty, $oms_qty)
    {
        $sap = (float) $sap_qty;
        $oms = (float) $oms_qty;
        if ($sap <= 0 && $oms > 0) {
            return 'oms_only';
        }
        if ($sap > 0 && $oms <= 0) {
            return 'sap_only';
        }
        return 'qty_mismatch';
    }

    /**
     * 清空某全量批次已生成的电商仓差异（重跑比对时）
     *
     * @param string $sync_batch_no
     */
    public function deleteByBatch($sync_batch_no)
    {
        $sync_batch_no = trim((string) $sync_batch_no);
        if ($sync_batch_no === '') {
            return;
        }
        app::get('goldentra')->model('stock_diff')->delete(array(
            'sync_batch_no' => $sync_batch_no,
        ));
    }

    /**
     * 批量写入差异
     *
     * @param array $rows
     * @return int
     */
    public function insertRows(array $rows)
    {
        if (!$rows) {
            return 0;
        }
        $mdl = app::get('goldentra')->model('stock_diff');
        $n = 0;
        foreach ($rows as $row) {
            if ($mdl->insert($row)) {
                $n++;
            }
        }
        return $n;
    }
}
