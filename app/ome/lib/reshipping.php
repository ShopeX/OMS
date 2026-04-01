<?php
/**
 * 补寄申请业务逻辑处理
 */
class ome_reshipping extends ome_abstract
{
    /**
     * 同意补寄申请
     * 
     * @param int $reshipping_id 补寄申请单ID
     * @param array $items_detail 商品明细信息（actual_product_id, actual_product_bn, actual_nums）
     * @return array
     */
    public function agree($reshipping_id, $items_detail = array())
    {
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $operationLogModel = app::get('ome')->model('operation_log');
        
        // 获取补寄申请单信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            return array('rsp' => 'fail', 'msg' => '补寄申请单不存在');
        }
        
        // 检查状态
        if ($reshipping['status'] != '0') {
            return array('rsp' => 'fail', 'msg' => '补寄申请单状态不正确，无法同意');
        }
        
        // 先做校验并保存商品明细
        $validateResult = $this->_validateAndSaveItemsDetail($reshipping_id, $reshipping, $items_detail);
        if ($validateResult['rsp'] == 'fail') {
            return $validateResult;
        }
        
        // 校验通过后，调用矩阵同意接口
        $apiResult = kernel::single('erpapi_router_request')->set('shop', $reshipping['shop_id'])->reshipping_agree(array(
            'dispute_id' => $reshipping['reshipping_bn'],
        ));
        
        if ($apiResult['rsp'] == 'fail') {
            // 清除写入的明细
            $itemsDetailModel = app::get('ome')->model('return_reshipping_items_detail');
            $itemsDetailModel->delete(array('reshipping_id' => $reshipping_id));
            return array('rsp' => 'fail', 'msg' => '调用同意接口失败：' . (isset($apiResult['msg']) ? $apiResult['msg'] : '未知错误'));
        }
        
        // 更新补寄申请单状态
        $updateData = array(
            'status' => '1', // 等待卖家发货
            'is_confirm_goods' => '1', // 商品已确认
            'up_time' => date('Y-m-d H:i:s'),
        );
        $rs = $reshippingModel->update($updateData, array('reshipping_id' => $reshipping_id, 'status' => '0'));
        if(is_bool($rs)) {
            return array('rsp' => 'fail', 'msg' => '更新补寄申请单状态失败');
        }
        // 记录操作日志
        $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, '同意补寄申请');
        // 生成补发订单
        $orderResult = $this->createReissueOrder($reshipping_id);
        if ($orderResult['rsp'] == 'fail') {
            // 生成补发订单失败时，记录日志但不报错，继续执行
            $errorMsg = '生成补发订单失败：' . (isset($orderResult['msg']) ? $orderResult['msg'] : '未知错误');
            $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, $errorMsg);
        }
        
        return array('rsp' => 'succ', 'msg' => '同意补寄申请成功');
    }
    
    /**
     * 拒绝补寄申请
     * 
     * @param int $reshipping_id 补寄申请单ID
     * @param array $refuseData 拒绝数据（refuse_reason_id, refuse_reason, leave_message_pics, leave_message）
     * @return array
     */
    public function refuse($reshipping_id, $refuseData)
    {
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $operationLogModel = app::get('ome')->model('operation_log');
        
        // 获取补寄申请单信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            return array('rsp' => 'fail', 'msg' => '补寄申请单不存在');
        }
        
        // 检查状态
        if ($reshipping['status'] != '0') {
            return array('rsp' => 'fail', 'msg' => '补寄申请单状态不正确，无法拒绝');
        }
        
        // 验证必填字段
        if (empty($refuseData['refuse_reason_id'])) {
            return array('rsp' => 'fail', 'msg' => '拒绝原因不能为空');
        }
        if (empty($refuseData['leave_message'])) {
            return array('rsp' => 'fail', 'msg' => '拒绝留言不能为空');
        }
        if (empty($refuseData['leave_message_pics'])) {
            return array('rsp' => 'fail', 'msg' => '凭证图片不能为空');
        }
        
        // 调用矩阵拒绝接口
        $apiResult = kernel::single('erpapi_router_request')->set('shop', $reshipping['shop_id'])->reshipping_refuse(array(
            'dispute_id' => $reshipping['reshipping_bn'],
            'seller_refuse_reason_id' => $refuseData['refuse_reason_id'],
            'leave_message_pics' => $refuseData['leave_message_pics'], // 单个 base64 字符串
            'leave_message' => $refuseData['leave_message'],
        ));
        
        if ($apiResult['rsp'] == 'fail') {
            return array('rsp' => 'fail', 'msg' => '调用拒绝接口失败：' . (isset($apiResult['msg']) ? $apiResult['msg'] : '未知错误'));
        }
        // 更新补寄申请单
        $updateData = array(
            'status' => '4', // 卖家拒绝补寄
            'refuse_reason_id' => $refuseData['refuse_reason_id'],
            'refuse_reason' => $refuseData['refuse_reason'],
            'up_time' => date('Y-m-d H:i:s'),
        );
        $rs = $reshippingModel->update($updateData, array('reshipping_id' => $reshipping_id, 'status' => '0'));
        if(is_bool($rs)) {
            return array('rsp' => 'fail', 'msg' => '更新补寄申请单状态失败');
        }
        
        // 记录操作日志
        $memo = '拒绝补寄申请，原因：' . $refuseData['refuse_reason'];
        $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, $memo);
        
        return array('rsp' => 'succ', 'msg' => '拒绝补寄申请成功');
    }
    
    /**
     * 商品信息确认（淘宝平台同意场景）
     * 
     * @param int $reshipping_id 补寄申请单ID
     * @param array $items_detail 商品明细信息
     * @return array
     */
    public function confirmGoods($reshipping_id, $items_detail = array())
    {
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $operationLogModel = app::get('ome')->model('operation_log');
        
        // 获取补寄申请单信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            return array('rsp' => 'fail', 'msg' => '补寄申请单不存在');
        }
        
        // 检查is_confirm_goods状态
        if ($reshipping['is_confirm_goods'] == '1') {
            return array('rsp' => 'fail', 'msg' => '商品信息已确认');
        }
        
        // 验证并保存商品明细
        $validateResult = $this->_validateAndSaveItemsDetail($reshipping_id, $reshipping, $items_detail);
        if ($validateResult['rsp'] == 'fail') {
            return $validateResult;
        }
        
        // 更新补寄申请单
        $updateData = array(
            'is_confirm_goods' => '1', // 商品已确认
            'up_time' => date('Y-m-d H:i:s'),
        );
        $rs = $reshippingModel->update($updateData, array('reshipping_id' => $reshipping_id, 'status' => '1', 'is_confirm_goods' => '0'));
        if(is_bool($rs)) {
            app::get('ome')->model('return_reshipping_items_detail')->delete(array('reshipping_id' => $reshipping_id));
            return array('rsp' => 'fail', 'msg' => '更新补寄申请单状态失败');
        }
        // 记录操作日志
        $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, '商品信息确认成功');
        
        // 生成补发订单（失败时只记录日志，不报错）
        $orderResult = $this->createReissueOrder($reshipping_id);
        if ($orderResult['rsp'] == 'fail') {
            $errorMsg = '生成补发订单失败：' . (isset($orderResult['msg']) ? $orderResult['msg'] : '未知错误');
            $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, $errorMsg);
        }
        
        return array('rsp' => 'succ', 'msg' => '商品信息确认成功');
    }
    
    /**
     * 验证并保存商品明细（共同逻辑）
     * 
     * @param int $reshipping_id 补寄申请单ID
     * @param array $reshipping 补寄申请单信息
     * @param array $items_detail 商品明细信息
     * @return array
     */
    private function _validateAndSaveItemsDetail($reshipping_id, $reshipping, $items_detail)
    {
        $reshippingItemsModel = app::get('ome')->model('return_reshipping_items');
        $reshippingItemsDetailModel = app::get('ome')->model('return_reshipping_items_detail');
        $salesMaterialModel = app::get('material')->model('sales_material');
        
        // 获取商品明细
        $items = $reshippingItemsModel->getList('*', array('reshipping_id' => $reshipping_id));
        
        // 用于保存校验阶段查询到的sm_id，避免重复查询
        $itemSmIdMap = array();
        
        // 校验所有商品项的销售物料编码
        foreach ($items as $item) {
            // 只从sales_material_bn获取销售物料编码，如果不存在则报错
            if (!isset($items_detail[$item['item_id']]['sales_material_bn']) || 
                empty(trim($items_detail[$item['item_id']]['sales_material_bn']))) {
                return array('rsp' => 'fail', 'msg' => '商品项ID ' . $item['item_id'] . ' 的销售物料编码不能为空');
            }
            
            $sales_material_bn = trim($items_detail[$item['item_id']]['sales_material_bn']);
            
            // 根据sales_material_bn查询销售物料表获取sm_id
            $salesMaterialList = $salesMaterialModel->getList(
                'sm_id', 
                array('sales_material_bn' => $sales_material_bn, 'shop_id' => array($reshipping['shop_id'], '_ALL_'), 'sales_material_type' => ['1', '2', '3']), 
                0, 
                1
            );
            
            // 如果查询不到sm_id，报错
            if (!$salesMaterialList || !isset($salesMaterialList[0]['sm_id'])) {
                return array('rsp' => 'fail', 'msg' => '销售物料编码 ' . $sales_material_bn . ' 不存在或不属于该店铺');
            }
            
            // 保存查询到的sm_id，供后续使用
            $itemSmIdMap[$item['item_id']] = $salesMaterialList[0]['sm_id'];
        }
        
        // 校验通过后，保存商品明细到items_detail表
        foreach ($items as $item) {
            $sales_material_bn = trim($items_detail[$item['item_id']]['sales_material_bn']);
            
            // 直接使用校验阶段保存的sm_id，不再查询
            $sm_id = $itemSmIdMap[$item['item_id']];
            
            $detailData = array(
                'reshipping_id' => $reshipping_id,
                'item_id' => $item['item_id'],
                'sm_id' => $sm_id,
                'sales_material_bn' => $sales_material_bn,
                'nums' => isset($items_detail[$item['item_id']]['nums']) ? $items_detail[$item['item_id']]['nums'] : $item['nums'],
                'price' => $item['price'],
            );
            $reshippingItemsDetailModel->insert($detailData);
        }
        
        return array('rsp' => 'succ', 'msg' => '');
    }
    
    /**
     * 创建补发订单
     * 
     * @param int $reshipping_id 补寄申请单ID
     * @return array
     */
    public function createReissueOrder($reshipping_id)
    {
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $reshippingItemsDetailModel = app::get('ome')->model('return_reshipping_items_detail');
        $orderModel = app::get('ome')->model('orders');
        $salesMaterialModel = app::get('material')->model('sales_material');
        
        $result = array('rsp' => 'fail', 'msg' => '');
        
        // 获取补寄申请单信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            $result['msg'] = '补寄申请单不存在';
            return $result;
        }
        
        // 检查是否已经创建过补发订单
        if ($reshipping['reissue_order_id']) {
            $result['msg'] = '补发订单已存在';
            return $result;
        }
        
        // 获取原订单信息
        $originalOrder = $orderModel->dump($reshipping['order_id']);
        if (empty($originalOrder)) {
            $result['msg'] = '原订单不存在';
            return $result;
        }
        
        // 获取商品明细（已确认的商品）
        $itemsDetail = $reshippingItemsDetailModel->getList('*', array('reshipping_id' => $reshipping_id));
        if (empty($itemsDetail)) {
            $result['msg'] = '补寄商品明细不存在';
            return $result;
        }
        
        // 构建补发订单数据（复制原订单信息）
        $bufaOrderSdf = array(
            'order_bn'=>$reshipping['reshipping_bn'],
            'member_id'=>$originalOrder['member_id'],
            'currency'=>'CNY',
            'title'=>'',
            'createtime'=>time(),
            'last_modified'=>time(),
            'confirm'=>'N',
            'status'=>'active',
            'pay_status'=>'1',
            'ship_status'=>'0',
            'is_delivery'=>'Y',
            'shop_id'=>$originalOrder['shop_id'],
            'itemnum'=>0,
            'relate_order_bn'=>$originalOrder['order_bn'],
            'shipping'=>array(
                'shipping_id'=>$originalOrder['shipping']['shipping_id'],
                'is_cod'=>'false',
                'shipping_name'=>$originalOrder['shipping']['shipping_name'],
                'cost_shipping'=>0,
                'is_protect'=>$originalOrder['shipping']['is_protect'],
                'cost_protect'=>0,
            ),
             'mark_type' => 'b1',
             'source' => 'local',
             'order_type' => 'bufa',
             'createway' => 'after',
             'is_tax' => $originalOrder["is_tax"],
             'tax_title' => $originalOrder["tax_title"],
             'shop_type' => $originalOrder["shop_type"],
             
         );
         
         //平台订单号
         if($originalOrder['platform_order_bn']){
             $bufaOrderSdf['platform_order_bn'] = $originalOrder['platform_order_bn'];
         }
        
        // 重置订单信息
        $bufaOrderSdf['pay_bn'] = $reshipping['reshipping_bn']; // 支付单号，使用补寄申请单号
        $bufaOrderSdf['createtime'] = time();
        $bufaOrderSdf['paytime'] = time();
        $bufaOrderSdf['modifytime'] = time();
        $bufaOrderSdf['is_modify'] = 'false';
        $bufaOrderSdf['logi_no'] = '';
        $bufaOrderSdf['source_status'] = '';
        $bufaOrderSdf['payed'] = 0.00;
        $bufaOrderSdf['total_amount'] = 0.00;
        $bufaOrderSdf['final_amount'] = 0.00;
        $bufaOrderSdf['cost_item'] = 0.00;
        $bufaOrderSdf['custom_mark'] = '';
        $bufaOrderSdf['mark_text'] = '';
        
        // 收货人信息（使用补寄申请单的购买人信息）
        $consignee = $this->_formatReshippingConsignee($reshipping);
        $bufaOrderSdf['consignee'] = $consignee;
        // 构建订单明细
        // $orderObjs 对应销售物料，$order_items 对应销售物料下的基础物料
        $order_objects = array();
        $salesBasicMaterialModel = app::get('material')->model('sales_basic_material');
        $basicMaterialModel = app::get('material')->model('basic_material');
        
        foreach ($itemsDetail as $itemDetail) {
            // 获取销售物料信息
            $salesMaterial = $salesMaterialModel->db_dump($itemDetail['sm_id'], 'sm_id,sales_material_name,sales_material_bn,sales_material_type');
            if (empty($salesMaterial)) {
                continue;
            }
            
            // 创建销售物料对应的 order_objects
            $orderObjs = array(
                'obj_type' => $salesMaterial['sales_material_type'] == '2' ? 'pkg' : ($salesMaterial['sales_material_type'] == '3' ? 'gift' : 'goods'),
                'obj_alias' => $salesMaterial['sales_material_name'],
                'goods_id' => $salesMaterial['sm_id'],
                'name' => $salesMaterial['sales_material_name'],
                'bn' => $itemDetail['sales_material_bn'],
                'quantity' => $itemDetail['nums'],
                'price' => 0.00,
                'pmt_price' => 0.00,
                'sale_price' => 0.00,
                'order_items' => array(),
            );
            
            // 获取销售物料关联的基础物料
            $salesBasicMaterials = $salesBasicMaterialModel->getList('*', array('sm_id' => $salesMaterial['sm_id']), 0, -1);
            
            // 如果没有关联基础物料，跳过该销售物料
            if (empty($salesBasicMaterials)) {
                continue;
            }
            
            // 为每个基础物料创建 order_items
            foreach ($salesBasicMaterials as $salesBasicMaterial) {
                // 获取基础物料信息
                $basicMaterial = $basicMaterialModel->db_dump($salesBasicMaterial['bm_id'], 'bm_id,material_name,material_bn,type');
                if (empty($basicMaterial)) {
                    continue;
                }
                
                $order_item = array(
                    'product_id'        => $basicMaterial['bm_id'] ? $basicMaterial['bm_id'] : 0,
                    'bn'                => $basicMaterial['material_bn'],
                    'name'              => $basicMaterial['material_name'],
                    'quantity'          => $salesBasicMaterial['number'] * $itemDetail['nums'],
                    'addon'             => '',
                    'item_type'         => $salesMaterial['sales_material_type'] == '2' ? 'pkg' : 'product',
                    'price'             => 0.00,
                    'pmt_price'         => 0.00,
                    'sale_price'        => 0.00,
                    'delete'            => 'false',
                );
                
                $orderObjs['order_items'][] = $order_item;
            }
            
            if (empty($orderObjs['order_items'])) {
                continue;
            }
            
            $order_objects[] = $orderObjs;
        }
        
        if (empty($order_objects)) {
            $result['msg'] = '没有有效的补寄商品';
            return $result;
        }
        
        $bufaOrderSdf['order_objects'] = $order_objects;
        
        // 创建补发订单
        $error_msg = '';
        $orderResult = $orderModel->create_order($bufaOrderSdf, $error_msg);
        if (!$orderResult) {
            $result['msg'] = '创建补发订单失败：' . $error_msg;
            return $result;
        }
        
        // 更新为已支付（0元订单创建时是未支付）
        $orderModel->update(array('pay_status' => '1'), array('order_id' => $bufaOrderSdf['order_id']));
        
        // 保存收件人敏感数据
        $this->_saveReshippingReceiverEncrypt($bufaOrderSdf['order_id'], $reshipping);
        
        // 计算最晚发货时间（同意时间+24小时）
        $agreeTime = strtotime($reshipping['up_time'] ? $reshipping['up_time'] : date('Y-m-d H:i:s'));
        $latestDeliveryTime = $agreeTime + 24 * 3600; // 24小时后
        
        // 更新ome_order_extend表的最晚发货时间
        $orderExtendModel = app::get('ome')->model('order_extend');
        $extendData = array(
            'order_id' => $bufaOrderSdf['order_id'],
            'latest_delivery_time' => $latestDeliveryTime,
        );
        $orderExtendModel->save($extendData);
        
        // 更新补寄申请单，关联补发订单
        $reshippingModel->update(array(
            'reissue_order_id' => $bufaOrderSdf['order_id'],
            'reissue_order_bn' => $bufaOrderSdf['order_bn'],
        ), array('reshipping_id' => $reshipping_id));
        
        $result['rsp'] = 'succ';
        $result['order_id'] = $bufaOrderSdf['order_id'];
        $result['order_bn'] = $bufaOrderSdf['order_bn'];
        $result['msg'] = '补发订单创建成功';
        
        return $result;
    }
    
    /**
     * 处理转退款
     * 当补寄申请单状态更新为6（转退款）时调用
     * 
     * @param int $reshipping_id 补寄申请单ID
     * @return array
     */
    public function handleTransformToRefund($reshipping_id)
    {
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $orderModel = app::get('ome')->model('orders');
        $deliveryModel = app::get('ome')->model('delivery');
        $operationLogModel = app::get('ome')->model('operation_log');
        
        $result = array('rsp' => 'fail', 'msg' => '');
        
        // 获取补寄申请单信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            $result['msg'] = '补寄申请单不存在';
            return $result;
        }
        
        // 检查状态
        if ($reshipping['status'] != '6') {
            $result['msg'] = '补寄申请单状态不是转退款';
            return $result;
        }
        
        // 查询补寄申请单关联的补发订单
        if (empty($reshipping['reissue_order_id'])) {
            // 没有补发订单，直接返回成功
            $result['rsp'] = 'succ';
            $result['msg'] = '没有补发订单，无需处理';
            return $result;
        }
        
        $reissue_order_id = $reshipping['reissue_order_id'];
        
        // 获取补发订单信息
        $reissueOrder = $orderModel->db_dump($reissue_order_id, 'order_id,ship_status,order_bn');
        if (empty($reissueOrder)) {
            $result['msg'] = '补发订单不存在';
            return $result;
        }
        
        // 打标签：补发转退款
        $error_msg = '';
        $labelResult = kernel::single('ome_bill_label')->markBillLabel(
            $reissue_order_id,
            '',
            'SOMS_BUFA_REFUND',
            'order',
            $error_msg
        );
        if (!$labelResult) {
            // 打标签失败，记录日志但不中断流程
            $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, '打标签失败：' . ($error_msg ? $error_msg : '未知错误'));
        }
        
        // 判断是否已发货
        $isShipped = in_array($reissueOrder['ship_status'], array('1', '2')); // 1=已发货，2=部分发货
        
        if ($isShipped) {
            // 已发货，不取消订单
            $memo = '转退款处理完成，补发订单已发货，不取消订单';
            $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, $memo);
            
            $result['rsp'] = 'succ';
            $result['msg'] = '转退款处理成功，补发订单已发货，未取消';
            return $result;
        }
        
        // 未发货，取消订单
        $cancelMemo = '补寄转退款，取消补发订单';
        $cancelResult = $orderModel->cancel($reissue_order_id, $cancelMemo, false, 'async', false);
        
        if ($cancelResult['rsp'] == 'success') {
            $memo = '转退款处理完成，补发订单已取消';
            $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, $memo);
            
            $result['rsp'] = 'succ';
            $result['msg'] = '转退款处理成功，补发订单已取消';
        } else {
            // 取消失败，记录错误日志但不中断流程
            $errorMsg = '取消补发订单失败：' . (isset($cancelResult['msg']) ? $cancelResult['msg'] : '未知错误');
            $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, $errorMsg);
            
            $result['rsp'] = 'succ';
            $result['msg'] = '转退款处理完成，但取消补发订单失败';
        }
        
        return $result;
    }
    
    /**
     * 撤销发货单
     * 
     * @param int $delivery_id 发货单ID
     * @return array
     */
    private function _cancelDelivery($delivery_id)
    {
        $deliveryModel = app::get('ome')->model('delivery');
        $delivery = $deliveryModel->db_dump($delivery_id, 'delivery_id,delivery_bn,status,branch_id');
        
        if (empty($delivery)) {
            return array('rsp' => 'fail', 'msg' => '发货单不存在');
        }
        
        // 调用发货单撤销接口
        $branchModel = app::get('ome')->model('branch');
        $branch = $branchModel->db_dump($delivery['branch_id'], 'wms_id');
        $wms_id = $branch ? $branch['wms_id'] : 0;
        
        if ($wms_id) {
            $data = array(
                'delivery_bn' => $delivery['delivery_bn'],
                'memo' => '补寄转退款，撤销发货单',
            );
            $cancelResult = kernel::single('ome_event_trigger_delivery')->cancel('wms', $wms_id, $data, true);
            
            if ($cancelResult['rsp'] != 'succ') {
                return array('rsp' => 'fail', 'msg' => isset($cancelResult['msg']) ? $cancelResult['msg'] : '撤销发货单失败');
            }
        }
        
        return array('rsp' => 'succ', 'msg' => '撤销发货单成功');
    }
    
    /**
     * 拦截发货单
     * 
     * @param int $delivery_id 发货单ID
     * @return array
     */
    private function _interceptDelivery($delivery_id)
    {
        $deliveryModel = app::get('ome')->model('delivery');
        $delivery = $deliveryModel->db_dump($delivery_id, 'delivery_id,delivery_bn,status,branch_id');
        
        if (empty($delivery)) {
            return array('rsp' => 'fail', 'msg' => '发货单不存在');
        }
        
        // 调用发货单拦截接口
        $branchModel = app::get('ome')->model('branch');
        $branch = $branchModel->db_dump($delivery['branch_id'], 'wms_id');
        $wms_id = $branch ? $branch['wms_id'] : 0;
        
        if ($wms_id) {
            $data = array(
                'delivery_bn' => $delivery['delivery_bn'],
            );
            $interceptResult = kernel::single('ome_event_trigger_delivery')->cut('wms', $wms_id, $data);
            
            if ($interceptResult['rsp'] != 'succ') {
                return array('rsp' => 'fail', 'msg' => isset($interceptResult['msg']) ? $interceptResult['msg'] : '拦截发货单失败');
            }
        }
        
        return array('rsp' => 'succ', 'msg' => '拦截发货单成功');
    }
    
    /**
     * 处理上传的文件
     * 
     * @param array $file 文件信息
     * @param array $allowedTypes 允许的文件类型
     * @param object $storager 存储对象
     * @return string|false 文件URL或false（失败时返回false，错误信息通过异常抛出）
     * @throws Exception 文件验证失败时抛出异常
     */
    public function processUploadedFile($file, $allowedTypes, $storager)
    {
        // 检查文件是否上传
        if ($file['error'] != UPLOAD_ERR_OK || empty($file['name'])) {
            return false;
        }
        
        // 检查文件大小（500K限制）
        if ($file['size'] > 512000) {
            throw new Exception('上传文件不能超过500K！');
        }
        
        // 检查文件类型
        $imgext = strtolower($this->fileext($file['name']));
        if (!in_array($imgext, $allowedTypes)) {
            $text = implode(",", $allowedTypes);
            throw new Exception("您只能上传以下类型文件{$text}！");
        }
        
        // 上传文件
        $msg = '';
        $id = $storager->save_upload($file, "file", "", $msg);
        if (!$id) {
            throw new Exception('图片上传失败：' . ($msg ? $msg : '未知错误'));
        }
        
        // 获取文件URL
        $picUrl = $storager->getUrl($id, "file");
        return $picUrl ? $picUrl : false;
    }
    
    /**
     * 获取文件扩展名
     * 
     * @param string $filename 文件名
     * @return string 扩展名
     */
    private function fileext($filename)
    {
        return substr(strrchr($filename, '.'), 1);
    }
    
    /**
     * 格式化补寄申请单的收货人信息
     * 参考 dealExchangeEncrypt 方法处理密文 oaid
     * 
     * @param array $reshipping 补寄申请单信息
     * @return array 收货人信息
     */
    private function _formatReshippingConsignee($reshipping)
    {
        $subfix = '';
        // 处理 oaid 密文
        if ($reshipping['oaid']) {
            $subfix = '>>' . $reshipping['oaid'] . kernel::single('ome_security_hash')->get_code();
        }
        
        // 构建地址区域
        $ship_area = '';
        if ($reshipping['buyer_province']) {
            $ship_area = $reshipping['buyer_province'];
            if ($reshipping['buyer_city']) {
                $ship_area .= '/' . $reshipping['buyer_city'];
            }
            if ($reshipping['buyer_district']) {
                $ship_area .= '/' . $reshipping['buyer_district'];
            }
            if ($reshipping['buyer_town']) {
                $ship_area .= '/' . $reshipping['buyer_town'];
            }
        }
        
        // 验证地址区域
        if ($ship_area) {
            kernel::single('ome_func')->region_validate($ship_area);
        }
        
        // 构建收货人信息
        $consignee = array(
            'name' => strpos($reshipping['buyer_name'], '*') !== false ? $reshipping['buyer_name'] . $subfix : $reshipping['buyer_name'],
            'addr' => strpos($reshipping['buyer_address'], '*') !== false ? $reshipping['buyer_address'] . $subfix : $reshipping['buyer_address'],
            'zip' => '',
            'telephone' => '',
            'mobile' => strpos($reshipping['buyer_phone'], '*') !== false ? $reshipping['buyer_phone'] . $subfix : $reshipping['buyer_phone'],
            'email' => '',
            'area' => $ship_area,
        );
        return $consignee;
    }
    
    /**
     * 保存补寄申请单的收件人敏感数据
     * 参考 dealExchangeEncrypt 方法处理密文 oaid
     * 
     * @param int $order_id 补发订单ID
     * @param array $reshipping 补寄申请单信息
     * @return bool
     */
    private function _saveReshippingReceiverEncrypt($order_id, $reshipping)
    {
        $receiverObj = app::get('ome')->model('order_receiver');
        
        // 构建收件人敏感数据
        $receiverData = array(
            'order_id' => $order_id,
        );
        
        // 如果有 oaid，保存到 receiver 表
        if ($reshipping['oaid']) {
            $receiverData['encrypt_source_data'] = json_encode(array('oaid' => $reshipping['oaid']));
        }
        
        // 保存收件人敏感数据
        $receiverObj->save($receiverData);
        
        return true;
    }
}

