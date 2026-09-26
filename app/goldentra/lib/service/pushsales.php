<?php

/**
 * 发货完成后：SD003 发货上传；确认收货+凭证由定时任务 goldentrapushconfirm 处理
 * 售后生成后：仅 SD003 退货发货上传（不做定时确认收货+凭证）
 */
class goldentra_service_pushsales
{
    /**
     * ome.service.delivery.after.consign
     *
     * @param int $delivery_id
     * @return array
     */
    public function after_consign($delivery_id)
    {
        $delivery_id = intval($delivery_id);
        if ($delivery_id <= 0) {
            return array(false, '发货单ID无效');
        }

        try {
            $salesId = kernel::single('goldentra_push_sales')->createFromDelivery($delivery_id);
            if ($salesId) {
                kernel::single('goldentra_push')->dispatch(goldentra_push::MODEL_SALES, $salesId);
                return array(true, 'SD003发货已落库#' . $salesId . '（确认收货+凭证待签收后由定时任务处理）');
            }
            return array(true, 'SD003销售未生成(无销售单或明细)');
        } catch (Exception $e) {
            return array(false, 'SAP销售推送异常:' . $e->getMessage());
        }
    }

    /**
     * sales.service.aftersale.after.generate
     *
     * @param int $aftersale_id
     * @return array
     */
    public function after_generate($aftersale_id)
    {
        $aftersale_id = intval($aftersale_id);
        if ($aftersale_id <= 0) {
            return array(false, '售后单ID无效');
        }

        try {
            $salesId = kernel::single('goldentra_push_sales')->createFromAftersale($aftersale_id);
            if (!$salesId) {
                return array(true, '非退货/退款售后或未生成SD003，跳过');
            }
            kernel::single('goldentra_push')->dispatch(goldentra_push::MODEL_SALES, $salesId);
            return array(true, 'SD003退货发货已落库#' . $salesId);
        } catch (Exception $e) {
            return array(false, 'SAP售后推送异常:' . $e->getMessage());
        }
    }
}
