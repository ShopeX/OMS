<?php

/**
 * 退款申请单打标入口（基于 ome_bill_label）
 *
 * 用法示例：
 * kernel::single('ome_bill_label_refundapply')->mark($applyId, 'SOMS_XXX');
 */
class ome_bill_label_refundapply
{
    /**
     * 给退款申请单打标签
     *
     * @param int    $applyId
     * @param string $labelCode
     * @param int    $labelValue
     * @param string $extendInfo
     * @param string|null $errorMsg
     * @return bool
     */
    public function mark($applyId, $labelCode, $labelValue = 0, $extendInfo = '', &$errorMsg = null)
    {
        $applyId = (int) $applyId;
        $labelCode = trim((string) $labelCode);
        if ($applyId <= 0) {
            $errorMsg = '退款申请单ID为空';
            return false;
        }
        if ($labelCode === '') {
            $errorMsg = 'label_code为空';
            return false;
        }

        return kernel::single('ome_bill_label')->markBillLabel(
            $applyId,
            '',
            $labelCode,
            'refund_apply',
            $errorMsg,
            (int) $labelValue,
            (string) $extendInfo
        );
    }

    /**
     * 删除退款申请单上的标签（按 label_id）
     *
     * @param int   $applyId
     * @param int|int[] $labelId
     * @param string|null $errorMsg
     * @return bool
     */
    public function unmarkByLabelId($applyId, $labelId, &$errorMsg = null)
    {
        $applyId = (int) $applyId;
        if ($applyId <= 0) {
            $errorMsg = '退款申请单ID为空';
            return false;
        }
        if (empty($labelId)) {
            $errorMsg = 'label_id为空';
            return false;
        }

        return kernel::single('ome_bill_label')->delLabelFromBillId($applyId, $labelId, 'refund_apply', $errorMsg);
    }

    /**
     * 取退款申请单的所有标签
     *
     * @param int $applyId
     * @return array
     */
    public function getLabels($applyId)
    {
        $applyId = (int) $applyId;
        if ($applyId <= 0) {
            return array();
        }
        return kernel::single('ome_bill_label')->getLabelFromOrder($applyId, 'refund_apply');
    }

    /**
     * 判断是否存在某个 label_code
     *
     * @param int    $applyId
     * @param string $labelCode
     * @return bool
     */
    public function hasLabelCode($applyId, $labelCode)
    {
        $applyId = (int) $applyId;
        $labelCode = trim((string) $labelCode);
        if ($applyId <= 0 || $labelCode === '') {
            return false;
        }

        $billLabelMdl = app::get('ome')->model('bill_label');
        $row = $billLabelMdl->dump(
            array('bill_type' => 'refund_apply', 'bill_id' => $applyId, 'label_code' => $labelCode),
            'id'
        );
        return !empty($row);
    }
}

