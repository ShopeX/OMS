<?php

class monitor_notice_refund_apply
{ 
    public function erpapi_create($refundApplyId) {
        $refundApplyModel = app::get('ome')->model('refund_apply');
        $refundApply = $refundApplyModel->db_dump($refundApplyId, 'order_id, refund_apply_bn, money,product_data');
        if (!$refundApply) return [false, ['msg' => '退款申请单不存在']];

        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->db_dump($refundApply['order_id'], 'order_id, order_bn, ship_status');
        if (!$order) return [false, ['msg' => '订单不存在']];
        $notifyData = [
            'order_bn' => $order['order_bn'],
            'refund_apply_bn' => $refundApply['refund_apply_bn'],
            'refund_fee' => $refundApply['money']
        ];
        if ($order['ship_status'] == '1') {
            kernel::single('monitor_event_notify')->addNotify('order_ship_refund_apply', $notifyData);
            return [true, ['msg' => '订单已发货退款发起申请']];
        }

        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $deliverys = $deliveryOrderModel->getList('delivery_id', array('order_id' => $order['order_id']));

        if (empty($deliverys)) {
            kernel::single('monitor_event_notify')->addNotify('order_unship_refund_apply', $notifyData);
            return [false, ['msg' => '没有发货单']];
        }
        $arrProduct = unserialize($refundApply['product_data']);
        $order_item_id = is_array($arrProduct) ? array_column($arrProduct, 'order_item_id') : [];
        $deliveryModel = app::get('ome')->model('delivery');
        $deliveryList = $deliveryModel->getList('delivery_id,delivery_bn, sync_msg, status', array('delivery_id' => array_column($deliverys, 'delivery_id'), 'parent_id'=>0));
        $didItems = app::get('ome')->model('delivery_items_detail')->getList('delivery_id,order_id,order_item_id', ['delivery_id'=>array_column($deliveryList, 'delivery_id')]);

        foreach ($deliveryList as $delivery) {
            $needBack = false;
            if($order_item_id) {
                foreach ($didItems as $didItem) {
                    if ($didItem['delivery_id'] == $delivery['delivery_id'] && in_array($didItem['order_item_id'], $order_item_id)) {
                        $needBack = true;
                        break;
                    }
                }
            } else {
                $needBack = true;
            }
            if(!$needBack) {
                continue;
            }
            if (in_array($delivery['status'], ['ready','progress'])) {
                $failureMsg = $delivery['sync_msg'] ?: '未知原因';
                kernel::single('monitor_event_notify')->addNotify('order_refund_apply_reback_fail', array_merge($notifyData, [
                    'msg' => $failureMsg,
                    'delivery_bn' => $delivery['delivery_bn']
                ]));
                return [false, ['msg' => '发货单撤销失败']];
            }
            if (in_array($delivery['status'], ['succ'])) {
                kernel::single('monitor_event_notify')->addNotify('order_part_ship_refund_apply', array_merge($notifyData, [
                    'delivery_bn' => $delivery['delivery_bn']
                ]));
                return [false, ['msg' => '发货单部分发货']];
            }
        }

        kernel::single('monitor_event_notify')->addNotify('order_refund_apply_reback_succ', $notifyData);
        return [true, ['msg' => '发货单撤销成功']];
    }

    public function erpapi_refund($refundApplyId) {
        $refundApplyModel = app::get('ome')->model('refund_apply');
        $refundApply = $refundApplyModel->db_dump($refundApplyId, 'order_id, refund_apply_bn, money, product_data');
        if (!$refundApply) return [false, ['msg' => '退款申请单不存在']];

        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->db_dump($refundApply['order_id'], 'order_id, order_bn, ship_status');
        if (!$order) return [false, ['msg' => '订单不存在']];

        $notifyData = [
            'order_bn' => $order['order_bn'],
            'refund_apply_bn' => $refundApply['refund_apply_bn'],
            'refund_fee' => $refundApply['money']
        ];

        // 获取订单关联的发货单
        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $deliverys = $deliveryOrderModel->getList('delivery_id', array('order_id' => $order['order_id']));

        if (empty($deliverys)) {
            return [true, ['msg' => '没有发货单']];
        }

        // 解析退款申请的商品数据，获取 order_item_id
        $arrProduct = unserialize($refundApply['product_data']);
        $order_item_id = is_array($arrProduct) ? array_column($arrProduct, 'order_item_id') : [];

        // 获取发货单详细信息
        $deliveryModel = app::get('ome')->model('delivery');
        $deliveryList = $deliveryModel->getList('delivery_id, delivery_bn, sync_msg, status', array(
            'delivery_id' => array_column($deliverys, 'delivery_id'), 
            'parent_id' => 0
        ));

        // 获取发货单明细信息
        $didItems = app::get('ome')->model('delivery_items_detail')->getList('delivery_id, order_id, order_item_id', [
            'delivery_id' => array_column($deliveryList, 'delivery_id')
        ]);

        // 仅检查 ready 和 progress 状态的发货单
        foreach ($deliveryList as $delivery) {
            if (!in_array($delivery['status'], ['ready', 'progress', 'succ'])) {
                continue;
            }

            $needBack = false;
            
            // 优先使用 order_item_id 匹配
            if ($order_item_id) {
                foreach ($didItems as $didItem) {
                    if ($didItem['delivery_id'] == $delivery['delivery_id'] && in_array($didItem['order_item_id'], $order_item_id)) {
                        $needBack = true;
                        break;
                    }
                }
            } else {
                // 如果没有 order_item_id，则匹配所有发货单
                $needBack = true;
            }

            if ($needBack) {
                $failureMsg = $delivery['sync_msg'] ?: ($delivery['status'] == 'succ' ? '发货单已发货' : '未知原因');
                kernel::single('monitor_event_notify')->addNotify('order_refund_apply_reback_fail', array_merge($notifyData, [
                    'msg' => $failureMsg,
                    'delivery_bn' => $delivery['delivery_bn']
                ]));
                return [false, ['msg' => '发货单撤销失败']];
            }
        }
        return [true, ['msg' => '发货单撤销成功']];
    }
}