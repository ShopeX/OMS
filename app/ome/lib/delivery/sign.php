<?php
/**
 * 订单/平台确认收货后的发货单签收处理
 */
class ome_delivery_sign
{
    /**
     * 按订单将已发货的发货单签收，并同步门店签收
     *
     * @param int $orderId
     * @param int|string $endTime
     * @param string $memo
     * @return bool
     */
    public function signOrderDeliveries($orderId, $endTime, $memo = '')
    {
        $orderId = intval($orderId);
        $endTime = $this->normalizeSignTime($endTime);
        if ($orderId <= 0 || $endTime <= 0) {
            return false;
        }

        $deliveryOrderObj = app::get('ome')->model('delivery_order');
        $deliveryObj      = app::get('ome')->model('delivery');
        $deliveryIds      = array_column(
            (array) $deliveryOrderObj->getList('delivery_id', array('order_id' => $orderId)),
            'delivery_id'
        );
        if (!$deliveryIds) {
            return true;
        }

        $deliveryList = $deliveryObj->getList(
            'delivery_id,delivery_bn,branch_id,logi_status,sign_time',
            array('delivery_id' => $deliveryIds, 'status' => 'succ')
        );
        if (!$deliveryList) {
            return true;
        }

        foreach ((array) $deliveryList as $delivery) {
            // logi_status=3 已签收，无需回写 OME，也无需同步下游
            if (intval($delivery['logi_status']) === 3) {
                continue;
            }

            $result = $this->signByReceive($delivery['delivery_bn'], $endTime, $memo);
            if (!is_array($result) || ($result['rsp'] ?? '') !== 'succ') {
                continue;
            }

            $this->syncSignToDownstream($delivery['delivery_id'], $endTime);
        }

        return true;
    }

    /**
     * 调用 OME 发货单签收回写
     *
     * @param string $deliveryBn
     * @param int|string $signTime
     * @param string $memo
     * @return array
     */
    public function signByReceive($deliveryBn, $signTime, $memo = '')
    {
        $deliveryBn = trim((string) $deliveryBn);
        $signTime   = $this->normalizeSignTime($signTime);
        if ($deliveryBn === '') {
            return array('rsp' => 'fail', 'msg' => '发货单号不能为空');
        }
        if ($signTime <= 0) {
            $signTime = time();
        }

        $data = array(
            'status'      => 'sign',
            'delivery_bn' => $deliveryBn,
            'sign_time'   => date('Y-m-d H:i:s', $signTime),
            'memo'        => $memo ?: '平台确认收货同步签收',
        );

        return kernel::single('ome_event_receive_delivery')->update($data);
    }

    /**
     * 同步签收状态给门店（WAP）
     *
     * @param int $deliveryId
     * @param int|string $signTime
     * @return array
     */
    public function syncSignToDownstream($deliveryId, $signTime = 0)
    {
        return ome_delivery_notice::syncSignDownstream(intval($deliveryId), $signTime);
    }

    /**
     * @param int|string $signTime
     * @return int
     */
    private function normalizeSignTime($signTime)
    {
        if ($signTime === '' || $signTime === null) {
            return 0;
        }

        return is_numeric($signTime) ? intval($signTime) : intval(strtotime($signTime));
    }
}
