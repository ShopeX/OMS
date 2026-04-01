<?php
/**
 * 补寄申请业务处理
 *
 * @category
 * @package
 * @author
 * @version $Id: Z
 */
class erpapi_shop_response_process_reshipping
{
    /**
     * 补寄申请业务处理
     *
     * @param array $sdf 格式化后的补寄申请数据（已经过参数验证）
     * @return array
     */
    public function add($sdf)
    {
        try {
            // 获取店铺ID
            $shop_id = $sdf['shop_id'];
            if (empty($shop_id)) {
                return array('rsp' => 'fail', 'msg' => '店铺ID不能为空');
            }

            // 查找关联订单
            $order_id = $this->_getOrderId($sdf, $shop_id);
            if (empty($order_id)) {
                return array('rsp' => 'fail', 'msg' => '未找到关联订单，订单号：' . ($sdf['alipay_no'] ?: $sdf['biz_order_id']));
            }

            // 状态值映射
            $status = $this->_mapStatus($sdf['status']);
            if ($status === false) {
                return array('rsp' => 'fail', 'msg' => '未知的状态值：' . $sdf['status']);
            }

            $reshippingData = $this->_formatReshippingData($sdf, $order_id);
            // 保存或更新补寄申请单
            $reshippingModel = app::get('ome')->model('return_reshipping');
            $existingReshipping = $reshippingModel->dump(array(
                'reshipping_bn' => $sdf['dispute_id'],
                'shop_id' => $shop_id,
            ));

            if (!$existingReshipping) {
                $existingReshipping = $reshippingData;
                $existingReshipping['status'] = '0';
                $rsTran = kernel::database()->beginTransaction();
                $reshipping_id = $reshippingModel->insert($existingReshipping);
                if (!$reshipping_id) {
                    kernel::database()->rollBack();
                    return array('rsp' => 'fail', 'msg' => '保存补寄申请单失败');
                }
                // 处理商品信息
                if (!empty($sdf['bought_sku']) || !empty($sdf['new_bought_sku'])) {
                    $this->_saveItems($reshipping_id, $sdf);
                }
                kernel::database()->commit($rsTran);
                $memo = '创建补寄申请单';
                // 记录操作日志
                $operationLog = app::get('ome')->model('operation_log');
                $operationLog->write_log('return_reshipping@ome', $reshipping_id, $memo);
                
                // 如果状态为0，直接返回结果
                if ($status == '0') {
                    return array('rsp' => 'succ', 'msg' => $memo, 'data' => array('reshipping_id' => $reshipping_id, 'reshipping_bn' => $sdf['dispute_id']));
                }
            }
            $reshippingData['status'] = $status;
            $reshippingData['bought_sku'] = $sdf['bought_sku'];
            $reshippingData['new_bought_sku'] = $sdf['new_bought_sku'];
            return $this->_reshippingUpdateStatus($reshippingData, $existingReshipping);

        } catch (Exception $e) {
            return array('rsp' => 'fail', 'msg' => '处理补寄申请失败：' . $e->getMessage());
        }
    }

    /**
     * 查找关联订单ID
     *
     * @param array $sdf
     * @param int $shop_id
     * @return int|false
     */
    private function _getOrderId($sdf, $shop_id)
    {
        $orderModel = app::get('ome')->model('orders');
        
        // 优先使用alipay_no（主订单号）
        if (!empty($sdf['alipay_no'])) {
            $order = $orderModel->dump(array(
                'order_bn' => $sdf['alipay_no'],
                'shop_id' => $shop_id,
            ), 'order_id');
            
            if ($order && $order['order_id']) {
                return $order['order_id'];
            }
        }

        // 使用biz_order_id（子订单号）查找
        if (!empty($sdf['biz_order_id'])) {
            // 先通过order_objects表查找
            $orderObjectsModel = app::get('ome')->model('order_objects');
            $obj = $orderObjectsModel->dump(array(
                'oid' => $sdf['biz_order_id'],
                'shop_id' => $shop_id,
            ), 'order_id');
            
            if ($obj && $obj['order_id']) {
                return $obj['order_id'];
            }
        }

        return false;
    }

    /**
     * 状态值映射
     *
     * @param string $platformStatus 平台状态字符串
     * @return string|false 数据库状态值
     */
    private function _mapStatus($platformStatus)
    {
        if (empty($platformStatus)) {
            return '0'; // 默认补寄待处理
        }

        $status_mapping = array(
            'WAIT_SELLER_AGREE'                    => '0', // 补寄待处理
            'WAIT_SELLER_SEND_GOODS'               => '1', // 等待卖家发货
            'WAIT_BUYER_CONFIRM_REDO_SEND_GOODS'   => '2', // 等待买家收货
            'SUCCESS'                              => '3', // 补寄成功
            'SELLER_REFUSE_BUYER'                  => '4', // 卖家拒绝补寄
            'CLOSED'                               => '5', // 补寄关闭
            'EXCHANGE_TRANSFORM_TO_REFUND'         => '6', // 转退款
        );

        return isset($status_mapping[$platformStatus]) ? $status_mapping[$platformStatus] : false;
    }

    /**
     * 格式化补寄申请单数据（不包含状态）
     *
     * @param array $sdf
     * @param int $order_id
     * @return array
     */
    private function _formatReshippingData($sdf, $order_id)
    {
        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->dump(array('order_id' => $order_id), 'order_bn,platform_order_bn');

        $data = array(
            'reshipping_bn' => $sdf['dispute_id'],
            'shop_id' => $sdf['shop_id'],
            'shop_type' => $sdf['shop_type'],
            'order_id' => $order_id,
            'order_bn' => $order['order_bn'] ?: '',
            'platform_order_bn' => $order['platform_order_bn'] ?: $sdf['alipay_no'],
            'source' => 'matrix',
            'buyer_address' => $sdf['buyer_address'] ?: '',
            'buyer_province' => $sdf['buyer_province'] ?: '',
            'buyer_city' => $sdf['buyer_city'] ?: '',
            'buyer_district' => $sdf['buyer_district'] ?: '',
            'buyer_town' => $sdf['buyer_town'] ?: '',
            'buyer_phone' => $sdf['buyer_phone'] ?: '',
            'buyer_name' => $sdf['buyer_name'] ?: '',
            'buyer_open_uid' => $sdf['buyer_open_uid'] ?: '',
            'oaid' => $sdf['oaid'] ?: '',
            'real_receiver_open_id' => $sdf['real_receiver_open_id'] ?: '',
            'real_receiver_display_nick' => $sdf['real_receiver_display_nick'] ?: '',
            'reason' => $sdf['reason'] ?: '',
            'desc' => $sdf['desc'] ?: '',
            'refund_phase' => $sdf['refund_phase'] ?: '',
            'cs_status' => $sdf['cs_status'] ?: '',
            'advance_status' => $sdf['advance_status'] ?: '',
            'operation_contraint' => $sdf['operation_contraint'] ?: '',
            'logi_name' => $sdf['logi_name'] ?: '',
            'logi_no' => $sdf['logi_no'] ?: '',
            'refuse_reason' => $sdf['refuse_reason'] ?: '',
            'refuse_reason_id' => $sdf['refuse_reason_id'] ?: '',
            'extend_field' => $sdf['extend_field'] ?: '',
        );

        // 时间信息
        if ($sdf['created']) {
            $data['outer_create_time'] = kernel::single('ome_func')->date2time($sdf['created']);
        }
        if ($sdf['modified']) {
            $data['outer_modified_time'] = kernel::single('ome_func')->date2time($sdf['modified']);
        }
        if ($sdf['time_out']) {
            $data['time_out'] = kernel::single('ome_func')->date2time($sdf['time_out']);
        }

        return $data;
    }

    /**
     * 更新补寄申请单状态
     *
     * @param array $reshippingData 格式化后的补寄申请数据
     * @param array $existingReshipping 已存在的补寄申请数据
     * @return array
     */
    private function _reshippingUpdateStatus($reshippingData, $existingReshipping)
    {
        $status = $reshippingData['status'];
        if($status != 0){
            if($status == $existingReshipping['status']){
                return ['rsp' => 'fail', 'msg' => '状态未发生变化'];
            }
        }
        $outerModifiedTime = $reshippingData['outer_modified_time'];
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $reshipping_id = $existingReshipping['reshipping_id'];
        $operateLog = app::get('ome')->model('operation_log');
        switch($status) {
            case '0':
                if($existingReshipping['status'] > 0){
                    return ['rsp' => 'fail', 'msg' => '已存在补寄申请单不能更改，状态为：' . $existingReshipping['status']];
                }
                if($reshippingData['outer_modified_time'] <= $existingReshipping['outer_modified_time']){
                    return ['rsp' => 'fail', 'msg' => '更新时间未变化'];
                }
                $rsTran = kernel::database()->beginTransaction();
                $rs = $reshippingModel->update($reshippingData, array('reshipping_id' => $reshipping_id, 'status' => $status));
                // 防并发：检查更新影响行数
                if(is_bool($rs) || (is_numeric($rs) && $rs <= 0)) {
                    kernel::database()->rollBack();
                    if(is_numeric($rs) && $rs == 0) {
                        return ['rsp' => 'fail', 'msg' => '补寄申请单状态已被其他进程更新，更新失败'];
                    }
                    return ['rsp' => 'fail', 'msg' => '更新补寄申请单失败'];
                }
                $this->_saveItems($reshipping_id, $reshippingData);
                kernel::database()->commit($rsTran);
                $memo = '变更补寄单信息成功';
                $operateLog->write_log('return_reshipping@ome', $reshipping_id, $memo);
                return ['rsp' => 'succ', 'msg' => $memo];
                
            case '1':
                if($existingReshipping['status'] != 0){
                    return ['rsp' => 'fail', 'msg' => '已存在补寄申请单不能更改，状态为：' . $existingReshipping['status']];
                }
                // 等待卖家发货
                $upData = array(
                    'status' => $status,
                    'outer_modified_time' => $outerModifiedTime,
                );
                $rs = $reshippingModel->update($upData, array('reshipping_id' => $reshipping_id, 'status|noequal' => $status));
                // 防并发：检查更新影响行数
                if(is_bool($rs) || (is_numeric($rs) && $rs <= 0)) {
                    if(is_numeric($rs) && $rs == 0) {
                        return ['rsp' => 'fail', 'msg' => '补寄申请单状态已被其他进程更新，更新失败'];
                    }
                    return ['rsp' => 'fail', 'msg' => '更新补寄申请单状态失败'];
                }
                $memo = '状态更新为：等待卖家发货';
                $operateLog->write_log('return_reshipping@ome', $reshipping_id, $memo);
                return ['rsp' => 'succ', 'msg' => $memo];
                
            case '2':
                if($existingReshipping['status'] != 1){
                    return ['rsp' => 'fail', 'msg' => '已存在补寄申请单不能更改，状态为：' . $existingReshipping['status']];
                }
                // 等待买家收货
                $upData = array(
                    'status' => $status,
                    'outer_modified_time' => $outerModifiedTime,
                );
                $rs = $reshippingModel->update($upData, array('reshipping_id' => $reshipping_id, 'status|noequal' => $status));
                // 防并发：检查更新影响行数
                if(is_bool($rs) || (is_numeric($rs) && $rs <= 0)) {
                    if(is_numeric($rs) && $rs == 0) {
                        return ['rsp' => 'fail', 'msg' => '补寄申请单状态已被其他进程更新，更新失败'];
                    }
                    return ['rsp' => 'fail', 'msg' => '更新补寄申请单状态失败'];
                }
                $memo = '状态更新为：等待买家收货';
                $operateLog->write_log('return_reshipping@ome', $reshipping_id, $memo);
                return ['rsp' => 'succ', 'msg' => $memo];
                
            case '3':
                if(!in_array($existingReshipping['status'], [1, 2])){
                    return ['rsp' => 'fail', 'msg' => '已存在补寄申请单不能更改，状态为：' . $existingReshipping['status']];
                }
                // 补寄成功
                $upData = array(
                    'status' => $status,
                    'outer_modified_time' => $outerModifiedTime,
                );
                $rs = $reshippingModel->update($upData, array('reshipping_id' => $reshipping_id, 'status|noequal' => $status));
                // 防并发：检查更新影响行数
                if(is_bool($rs) || (is_numeric($rs) && $rs <= 0)) {
                    if(is_numeric($rs) && $rs == 0) {
                        return ['rsp' => 'fail', 'msg' => '补寄申请单状态已被其他进程更新，更新失败'];
                    }
                    return ['rsp' => 'fail', 'msg' => '更新补寄申请单状态失败'];
                }
                $memo = '状态更新为：补寄成功';
                $operateLog->write_log('return_reshipping@ome', $reshipping_id, $memo);
                return ['rsp' => 'succ', 'msg' => $memo];
                
            case '4':
                if(!in_array($existingReshipping['status'], [0])){
                    return ['rsp' => 'fail', 'msg' => '已存在补寄申请单不能更改，状态为：' . $existingReshipping['status']];
                }
                // 卖家拒绝补寄
                $upData = array(
                    'status' => $status,
                    'outer_modified_time' => $outerModifiedTime,
                );
                if($reshippingData['refuse_reason']){
                    $upData['refuse_reason'] = $reshippingData['refuse_reason'];
                }
                if($reshippingData['refuse_reason_id']){
                    $upData['refuse_reason_id'] = $reshippingData['refuse_reason_id'];
                }
                $rs = $reshippingModel->update($upData, array('reshipping_id' => $reshipping_id, 'status|noequal' => $status));
                // 防并发：检查更新影响行数
                if(is_bool($rs) || (is_numeric($rs) && $rs <= 0)) {
                    if(is_numeric($rs) && $rs == 0) {
                        return ['rsp' => 'fail', 'msg' => '补寄申请单状态已被其他进程更新，更新失败'];
                    }
                    return ['rsp' => 'fail', 'msg' => '更新补寄申请单状态失败'];
                }
                $memo = '状态更新为：卖家拒绝补寄';
                $operateLog->write_log('return_reshipping@ome', $reshipping_id, $memo);
                return ['rsp' => 'succ', 'msg' => $memo];
                
            case '5':
                if(!in_array($existingReshipping['status'], [0,4])){
                    return ['rsp' => 'fail', 'msg' => '已存在补寄申请单不能更改，状态为：' . $existingReshipping['status']];
                }
                // 补寄关闭
                $upData = array(
                    'status' => $status,
                    'outer_modified_time' => $outerModifiedTime,
                );
                $rs = $reshippingModel->update($upData, array('reshipping_id' => $reshipping_id, 'status|noequal' => $status));
                // 防并发：检查更新影响行数
                if(is_bool($rs) || (is_numeric($rs) && $rs <= 0)) {
                    if(is_numeric($rs) && $rs == 0) {
                        return ['rsp' => 'fail', 'msg' => '补寄申请单状态已被其他进程更新，更新失败'];
                    }
                    return ['rsp' => 'fail', 'msg' => '更新补寄申请单状态失败'];
                }
                $memo = '状态更新为：补寄关闭';
                $operateLog->write_log('return_reshipping@ome', $reshipping_id, $memo);
                return ['rsp' => 'succ', 'msg' => $memo];
                
            case '6':
                if(!in_array($existingReshipping['status'], [0,1,4])){
                    return ['rsp' => 'fail', 'msg' => '已存在补寄申请单不能更改，状态为：' . $existingReshipping['status']];
                }
                // 转退款
                $upData = array(
                    'status' => $status,
                    'outer_modified_time' => $outerModifiedTime,
                );
                $rs = $reshippingModel->update($upData, array('reshipping_id' => $reshipping_id, 'status|noequal' => $status));
                // 防并发：检查更新影响行数
                if(is_bool($rs) || (is_numeric($rs) && $rs <= 0)) {
                    if(is_numeric($rs) && $rs == 0) {
                        return ['rsp' => 'fail', 'msg' => '补寄申请单状态已被其他进程更新，更新失败'];
                    }
                    return ['rsp' => 'fail', 'msg' => '更新补寄申请单状态失败'];
                }
                $reshippingLib = kernel::single('ome_reshipping');
                // 处理转退款逻辑
                $transformResult = $reshippingLib->handleTransformToRefund($reshipping_id);
                if ($transformResult['rsp'] == 'fail') {
                    // 记录错误日志，但不影响主流程
                    $operateLog->write_log('return_reshipping@ome', $reshipping_id, '转退款处理失败：' . $transformResult['msg']);
                    return ['rsp' => 'fail', 'msg' => '状态更新为：转退款（处理失败）'];
                } else {
                    $memo = '状态更新为：转退款';
                    $operateLog->write_log('return_reshipping@ome', $reshipping_id, $memo);
                    return ['rsp' => 'succ', 'msg' => $memo];
                }
                
            default:
                return ['rsp' => 'fail', 'msg' => '未知状态：' . $status];
        }
    }

    /**
     * 保存商品信息
     *
     * @param int $reshipping_id
     * @param array $sdf
     * @return void
     */
    private function _saveItems($reshipping_id, $sdf)
    {
        $itemsModel = app::get('ome')->model('return_reshipping_items');
        
        // 使用new_bought_sku优先，如果没有则使用bought_sku
        $sku = !empty($sdf['new_bought_sku']) ? $sdf['new_bought_sku'] : $sdf['bought_sku'];
        
        if (empty($sku)) {
            return;
        }

        // 查找销售物料信息
        $salesMaterialModel = app::get('material')->model('sales_material');
        $salesMaterial = $salesMaterialModel->db_dump(array('sales_material_bn' => $sku), 'sm_id,sales_material_bn');

        // 查找是否已存在该商品记录
        $existingItem = $itemsModel->db_dump(array(
            'reshipping_id' => $reshipping_id,
        ), 'item_id');

        $itemData = array(
            'reshipping_id' => $reshipping_id,
            'oid' => $sdf['biz_order_id'] ?: '',
            'sku_uuid' => '',
            'goods_id' => $salesMaterial ? $salesMaterial['sm_id'] : 0,
            'bn' => $sku,
            'nums' => isset($sdf['num']) ? intval($sdf['num']) : 1,
            'price' => isset($sdf['price']) ? floatval($sdf['price']) : 0,
        );

        if ($existingItem && $existingItem['item_id']) {
            // 更新已存在的商品信息
            $itemsModel->update($itemData, array('item_id' => $existingItem['item_id']));
        } else {
            // 插入新的商品信息
            $itemsModel->insert($itemData);
        }
    }
}

