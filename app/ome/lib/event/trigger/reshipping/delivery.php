<?php
/**
 * 补寄发货Event Trigger
 * 监听发货完成事件，自动提交补寄发货接口
 */
class ome_event_trigger_reshipping_delivery
{
    /**
     * 发货完成后的处理
     * 在发货完成事件中调用此方法
     * 
     * @param array $deliveryData 发货单数据
     * @return array
     */
    public function afterDelivery($deliveryId)
    {
        $deliveryModel = app::get('ome')->model('delivery');
        $orderModel = app::get('ome')->model('orders');
        $reshippingModel = app::get('ome')->model('return_reshipping');
        // 获取发货单信息
        $delivery = $deliveryModel->db_dump(array('delivery_id' => $deliveryId), 'delivery_id,delivery_bn,logi_no,logi_id,logi_name');
        if (empty($delivery)) {
            return array('rsp' => 'fail', 'msg' => '发货单不存在');
        }
        // 通过 delivery_bn 获取 delivery_id，并从 delivery_order 表获取 order_id
        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $delivery_order = $deliveryOrderModel->db_dump(array('delivery_id' => $delivery['delivery_id']), 'order_id');
        if (empty($delivery_order) || empty($delivery_order['order_id'])) {
            return array('rsp' => 'fail', 'msg' => '未找到对应订单ID');
        }
        $delivery['order_id'] = $delivery_order['order_id'];
        // 获取订单信息
        $order = $orderModel->db_dump($delivery['order_id'], 'order_id,order_bn,order_type');
        if (empty($order)) {
            return array('rsp' => 'fail', 'msg' => '订单不存在');
        }
        
        // 判断是否为补寄订单（order_type=bufa）
        if ($order['order_type'] != 'bufa') {
            return array('rsp' => 'succ', 'msg' => '不是补寄订单，无需处理');
        }
        
        // 通过订单号查找补寄申请单（订单号使用补寄申请单号）
        $reshipping = $reshippingModel->db_dump(array('reshipping_bn' => $order['order_bn']), 'reshipping_id,reshipping_bn,shop_id,status,logi_no');
        if (empty($reshipping)) {
            return array('rsp' => 'fail', 'msg' => '补寄申请单不存在');
        }
        
        // 检查状态（只有等待卖家发货状态才能发货）
        if (!in_array($reshipping['status'], array('1', '2'))) {
            return array('rsp' => 'succ', 'msg' => '补寄申请单状态不正确，无需处理');
        }
        
        // 获取物流公司信息
        $logiModel = app::get('ome')->model('dly_corp');
        $logi = $logiModel->db_dump(array('corp_id' => $delivery['logi_id']), 'corp_id,type,name');
        
        // 调用补寄发货接口
        $apiParams = array(
            'dispute_id' => $reshipping['reshipping_bn'],
            'logistics_no' => $delivery['logi_no'],
            'logistics_type' => '200', // 200表示快递
            'company_name' => $logi ? $logi['name'] : $delivery['logi_name'],
            'company_code' => $logi ? $logi['type'] : '',
            'order_id' => $order['order_id'],
            'order_bn' => $order['order_bn'],
        );
        if(empty($reshipping['logi_no'])) {
            // 更新补寄申请单字段
            $updateData = array(
                'status' => '2', // 等待买家收货
                'logi_name' => $apiParams['company_name'],
                'logi_no' => $apiParams['logistics_no'],
                'logi_id' => $delivery['logi_id'],
            );
            $rs = $reshippingModel->update($updateData, array('reshipping_id' => $reshipping['reshipping_id'], 'status' => ['1', '2']));
            if(!is_bool($rs)) {
                // 记录操作日志
                $operationLogModel = app::get('ome')->model('operation_log');
                $memo = '补寄发货，物流单号：' . $apiParams['logistics_no'];
                $operationLogModel->write_log('return_reshipping@ome', $reshipping['reshipping_id'], $memo);
            }
        }
        
        $apiResult = kernel::single('erpapi_router_request')->set('shop', $reshipping['shop_id'])->reshipping_consigngoods($apiParams);

        if ($apiResult['rsp'] == 'fail') {
            return array('rsp' => 'fail', 'msg' => $memo);
        }
        
        return array('rsp' => 'succ', 'msg' => '补寄发货成功');
    }
}

