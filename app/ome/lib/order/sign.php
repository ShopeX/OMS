<?php
/**
 * 订单签收相关逻辑
 */
class ome_order_sign
{
    /**
     * 手工签收：更新买家确认收货时间并触发签收后服务
     *
     * @param int    $order_id
     * @param int    $end_time  Unix 时间戳，为空则取当前时间
     * @param string $err_msg
     * @return bool
     */
    public function manualSign($order_id, $end_time = null, &$err_msg = '')
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            $err_msg = '订单ID无效';
            return false;
        }

        $orderObj  = null;
        $orderInfo = $this->_loadOrder($order_id, $orderObj);
        if (empty($orderInfo)) {
            $err_msg = '订单不存在';
            return false;
        }

        $ship_status = (string) $orderInfo['ship_status'];
        if (!in_array($ship_status, array('1', '3'), true)) {
            $err_msg = '订单未发货，不能手工签收';
            return false;
        }

        if (!empty($orderInfo['end_time'])) {
            $err_msg = '订单已有买家确认收货时间';
            return false;
        }

        $end_time = $end_time ? (int) $end_time : time();
        if ($end_time <= 0) {
            $err_msg = '签收时间无效';
            return false;
        }

        $orderObj->update(array('end_time' => $end_time), array('order_id' => $order_id));

        $this->_afterSign($order_id, $end_time);

        $memo = sprintf('手工签收,买家确认收货时间:%s', date('Y-m-d H:i:s', $end_time));
        app::get('ome')->model('operation_log')->write_log('order_modify@ome', $order_id, $memo);

        return true;
    }

    /**
     * @param int   $order_id
     * @param mixed $orderObj
     * @return array|false
     */
    protected function _loadOrder($order_id, &$orderObj)
    {
        $fields = 'order_id,order_bn,ship_status,end_time';

        $orderObj  = app::get('ome')->model('orders');
        $orderInfo = $orderObj->dump($order_id, $fields);
        if ($orderInfo) {
            return $orderInfo;
        }

        $orderObj  = app::get('archive')->model('orders');
        $orderInfo = $orderObj->dump($order_id, $fields);

        return $orderInfo ?: false;
    }

    /**
     * 签收后：触发扩展服务
     *
     * @param int $order_id
     * @param int $end_time
     */
    protected function _afterSign($order_id, $end_time)
    {
        foreach (kernel::servicelist('ome.service.order.sign.after') as $service) {
            if (method_exists($service, 'after_sign')) {
                $service->after_sign($order_id, array('end_time' => $end_time));
            }
        }
    }
}
