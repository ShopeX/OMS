<?php

class monitor_notice_refund_apply
{ 
    protected function buildRefundShopParams($order)
    {
        $notifyLib = kernel::single('monitor_event_notify');
        $shopParams = $notifyLib->buildOrderShopParams($order);

        if (!empty($shopParams['shop_type']) && !empty($shopParams['shop_name'])) {
            return $shopParams;
        }

        $result = array(
            'shop_type' => !empty($shopParams['shop_type']) ? $shopParams['shop_type'] : '',
            'shop_name' => !empty($shopParams['shop_name']) ? $shopParams['shop_name'] : '',
        );

        if (empty($order['shop_id'])) {
            return $result;
        }

        $shopInfo = app::get('ome')->model('shop')->getShopInfo($order['shop_id']);
        if (empty($result['shop_name']) && !empty($shopInfo['name'])) {
            $result['shop_name'] = trim((string) $shopInfo['name']);
        }

        if (empty($result['shop_type'])) {
            $shopType = !empty($shopInfo['shop_type']) ? (string) $shopInfo['shop_type'] : (isset($order['shop_type']) ? (string) $order['shop_type'] : '');
            if ($shopType !== '' && class_exists('ome_shop_type')) {
                $typeLabel = trim((string) ome_shop_type::shop_name($shopType));
                $result['shop_type'] = $typeLabel !== '' ? $typeLabel : $shopType;
            } else {
                $result['shop_type'] = $shopType;
            }
        }

        return $result;
    }

    public function erpapi_create($refundApplyId) {
        $refundApplyModel = app::get('ome')->model('refund_apply');
        $refundApply = $refundApplyModel->db_dump($refundApplyId, 'order_id, refund_apply_bn, money,product_data');
        if (!$refundApply) return [false, ['msg' => '退款申请单不存在']];

        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->db_dump($refundApply['order_id'], 'order_id, order_bn, ship_status, shop_id, shop_type');
        if (!$order) return [false, ['msg' => '订单不存在']];

        $notifyData = array_merge(array(
            'order_bn' => $order['order_bn'],
            'refund_apply_bn' => $refundApply['refund_apply_bn'],
            'refund_fee' => $refundApply['money'],
        ), $this->buildRefundShopParams($order));
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
        $order = $orderModel->db_dump($refundApply['order_id'], 'order_id, order_bn, ship_status, shop_id, shop_type');
        if (!$order) return [false, ['msg' => '订单不存在']];

        $notifyData = array_merge(array(
            'order_bn' => $order['order_bn'],
            'refund_apply_bn' => $refundApply['refund_apply_bn'],
            'refund_fee' => $refundApply['money'],
        ), $this->buildRefundShopParams($order));
        // 已发货、部分退货后仅退款完成，发送 order_ship_refund_finish
        if (in_array((string) $order['ship_status'], array('1', '3'), true)) {
            kernel::single('monitor_event_notify')->addNotify('order_ship_refund_finish', $notifyData);
        }

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
