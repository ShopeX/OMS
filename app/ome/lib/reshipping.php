<?php
/**
 * 补寄申请业务逻辑处理
 */
class ome_reshipping extends ome_abstract
{
    /**
     * 兜底：用基础物料货号(bn)解析 sales_material_type=1 的销售物料
     * basic_material.material_bn → bm_id → sales_basic_material.sm_id → sales_material(type=1) → sales_material_bn
     *
     * @param string $basicBn
     * @param string $shopId
     * @return array sm_id(int), sales_material_bn(string)
     */
    private function _resolveSalesMaterialType1ByBasicBn($basicBn, $shopId = '')
    {
        $basicBn = trim((string) $basicBn);
        if ($basicBn === '') {
            return array('sm_id' => 0, 'sales_material_bn' => '');
        }

        $db = kernel::database();
        $shopClause = '';
        if ($shopId !== '') {
            $shopClause = ' AND (shop_id=' . $db->quote($shopId) . ' OR shop_id=' . $db->quote('_ALL_') . ')';
        }

        $bm = app::get('material')->model('basic_material')->db_dump(array('material_bn' => $basicBn), 'bm_id');
        if (empty($bm['bm_id'])) {
            return array('sm_id' => 0, 'sales_material_bn' => '');
        }
        $bmId = (int) $bm['bm_id'];

        $sbmRows = $db->select('SELECT sm_id FROM sdb_material_sales_basic_material WHERE bm_id=' . $bmId);
        if (empty($sbmRows)) {
            return array('sm_id' => 0, 'sales_material_bn' => '');
        }

        $seen = array();
        foreach ($sbmRows as $sbm) {
            $smIdTry = isset($sbm['sm_id']) ? (int) $sbm['sm_id'] : 0;
            if ($smIdTry <= 0 || isset($seen[$smIdTry])) {
                continue;
            }
            $seen[$smIdTry] = true;

            $sqlSm = 'SELECT sm_id, sales_material_bn FROM sdb_material_sales_material'
                . ' WHERE sm_id=' . $smIdTry . ' AND sales_material_type=1' . $shopClause;
            $rowSm = $db->selectrow($sqlSm);
            if (!empty($rowSm['sm_id']) && isset($rowSm['sales_material_bn']) && trim((string) $rowSm['sales_material_bn']) !== '') {
                return array(
                    'sm_id' => (int) $rowSm['sm_id'],
                    'sales_material_bn' => trim((string) $rowSm['sales_material_bn']),
                );
            }
        }

        return array('sm_id' => 0, 'sales_material_bn' => '');
    }

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

        $isLocalSource = isset($reshipping['source']) && $reshipping['source'] === 'local';

        if (!$isLocalSource) {
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

        $isLocalSource = isset($reshipping['source']) && $reshipping['source'] === 'local';

        if ($isLocalSource) {
            if (empty($refuseData['leave_message'])) {
                return array('rsp' => 'fail', 'msg' => '拒绝留言不能为空');
            }
            if (empty($refuseData['refuse_reason_id'])) {
                $refuseData['refuse_reason_id'] = 'local';
            }
            if (empty($refuseData['refuse_reason'])) {
                $refuseData['refuse_reason'] = $refuseData['leave_message'];
            }
        } else {
            // 验证必填字段（平台单需同步矩阵）
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
        $itemSalesBnMap = array();
        $shopId = isset($reshipping['shop_id']) ? trim((string) $reshipping['shop_id']) : '';
        
        // 校验所有商品项的销售物料编码
        foreach ($items as $item) {
            // sales_material_bn / sm_id 取不到时：不报错不中断，留空继续保存
            $sales_material_bn = '';
            if (isset($items_detail[$item['item_id']]['sales_material_bn'])) {
                $sales_material_bn = trim((string) $items_detail[$item['item_id']]['sales_material_bn']);
            }
            $itemSalesBnMap[$item['item_id']] = $sales_material_bn;

            $smId = 0;
            if ($sales_material_bn !== '') {
                // 根据sales_material_bn查询销售物料表获取sm_id
                $salesMaterialList = $salesMaterialModel->getList(
                    'sm_id',
                    array(
                        'sales_material_bn' => $sales_material_bn,
                        'shop_id' => array($reshipping['shop_id'], '_ALL_'),
                        'sales_material_type' => array('1', '2', '3'),
                    ),
                    0,
                    1
                );
                if ($salesMaterialList && isset($salesMaterialList[0]['sm_id'])) {
                    $smId = (int) $salesMaterialList[0]['sm_id'];
                }
            }

            // 如果仍然取不到：用当前行 bn（基础物料号）兜底解析一次；仍失败则报错，不能同意通过
            if ($smId <= 0 || $sales_material_bn === '') {
                $basicBn = isset($item['bn']) ? trim((string) $item['bn']) : '';
                $fallback = $this->_resolveSalesMaterialType1ByBasicBn($basicBn, $shopId);
                if (!empty($fallback['sm_id']) && !empty($fallback['sales_material_bn'])) {
                    $smId = (int) $fallback['sm_id'];
                    $sales_material_bn = (string) $fallback['sales_material_bn'];
                    $itemSalesBnMap[$item['item_id']] = $sales_material_bn;
                } else {
                    return array('rsp' => 'fail', 'msg' => '无法解析销售物料（item_id=' . $item['item_id'] . '，bn=' . $basicBn . '），请维护销售物料映射后重试');
                }
            }

            // 保存查询到的sm_id（一定>0），供后续使用
            $itemSmIdMap[$item['item_id']] = $smId;
        }

        // 校验确认数量：允许单行等于0，但至少需有一条大于0
        $hasPositiveQty = false;
        foreach ($items as $item) {
            $detailNums = isset($items_detail[$item['item_id']]['nums'])
                ? (int) $items_detail[$item['item_id']]['nums']
                : (int) $item['nums'];
            if ($detailNums < 0) {
                return array('rsp' => 'fail', 'msg' => '确认数量不能小于0');
            }
            if ($detailNums > 0) {
                $hasPositiveQty = true;
            }
        }
        if (!$hasPositiveQty) {
            return array('rsp' => 'fail', 'msg' => '至少需要一条确认数量大于0的商品');
        }

        // 覆盖包裹中心预写的明细，避免重复插入
        $reshippingItemsDetailModel->delete(array('reshipping_id' => $reshipping_id));
        
        // 校验通过后，保存商品明细到items_detail表（金额与申请行 return_reshipping_items 一致，数量变更时按比例缩放）
        foreach ($items as $item) {
            $sales_material_bn = isset($itemSalesBnMap[$item['item_id']]) ? $itemSalesBnMap[$item['item_id']] : '';
            
            // 直接使用校验阶段保存的sm_id，不再查询
            $sm_id = isset($itemSmIdMap[$item['item_id']]) ? (int) $itemSmIdMap[$item['item_id']] : 0;
            
            $detailNums = isset($items_detail[$item['item_id']]['nums'])
                ? (int) $items_detail[$item['item_id']]['nums']
                : (int) $item['nums'];
            $origNums = (int) $item['nums'];
            $ratio = ($origNums > 0) ? ($detailNums / $origNums) : 1.0;
            
            $detailData = array(
                'reshipping_id' => $reshipping_id,
                'item_id' => $item['item_id'],
                'sm_id' => $sm_id,
                'sales_material_bn' => $sales_material_bn,
                'nums' => $detailNums,
                'price' => $item['price'],
                'pmt_price' => round((float) $item['pmt_price'] * $ratio, 3),
                'sale_price' => round((float) $item['sale_price'] * $ratio, 3),
                'amount' => round((float) $item['amount'] * $ratio, 3),
                'divide_order_fee' => round((float) $item['divide_order_fee'] * $ratio, 3),
                'part_mjz_discount' => round((float) $item['part_mjz_discount'] * $ratio, 3),
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
        $reshippingItemsModel = app::get('ome')->model('return_reshipping_items');
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
        
        // 获取商品明细（已确认的商品），补发订单仅生成数量大于0的行
        $itemsDetail = $reshippingItemsDetailModel->getList('*', array('reshipping_id' => $reshipping_id));
        if (empty($itemsDetail)) {
            $result['msg'] = '补寄商品明细不存在';
            return $result;
        }
        $itemsDetail = array_values(array_filter($itemsDetail, function ($row) {
            return isset($row['nums']) && (int) $row['nums'] > 0;
        }));
        if (empty($itemsDetail)) {
            $result['msg'] = '没有数量大于0的补寄商品';
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
        $merchantMark = isset($reshipping['merchant_mark']) ? trim($reshipping['merchant_mark']) : '';
        if ($merchantMark !== '') {
            $opInfo = kernel::single('ome_func')->getDesktopUser();
            $bufaOrderSdf['mark_text'] = serialize(array(
                array(
                    'op_name'    => $opInfo['op_name'],
                    'op_time'    => date('Y-m-d H:i', time()),
                    'op_content' => $merchantMark,
                ),
            ));
        } else {
            $bufaOrderSdf['mark_text'] = '';
        }
        $bufaOrderSdf['discount'] = 0.00;
        $bufaOrderSdf['pmt_goods'] = 0.00;
        $bufaOrderSdf['pmt_order'] = 0.00;
        
        // 收货人：物流中心包裹中心补寄单沿用原发货单快照；其余走原 _formatReshippingConsignee 逻辑
        $returnReshippingLib = kernel::single('ome_return_reshipping');
        $isDeliveryBillReshipping = $returnReshippingLib->isDeliveryBillLocalReshipping($reshipping);
        $applyItemsByItemId = array();
        $originalOrderId = (int) $reshipping['order_id'];
        if ($isDeliveryBillReshipping) {
            $applyItems = $reshippingItemsModel->getList('*', array('reshipping_id' => $reshipping_id));
            foreach ($applyItems as $applyRow) {
                if (!empty($applyRow['item_id'])) {
                    $applyItemsByItemId[(int) $applyRow['item_id']] = $applyRow;
                }
            }
        }
        if ($isDeliveryBillReshipping) {
            $consignee = $this->_buildBufaConsigneeFromDeliveryBill($reshipping, $originalOrder);
        } else {
            $consignee = $this->_formatReshippingConsignee($reshipping);
        }
        $bufaOrderSdf['consignee'] = $consignee;
        // 构建订单明细
        // $orderObjs 对应销售物料，$order_items 对应销售物料下的基础物料
        $order_objects = array();
        $salesBasicMaterialModel = app::get('material')->model('sales_basic_material');
        $basicMaterialModel = app::get('material')->model('basic_material');
        $sumPmtGoods = 0.0;
        $sumPmtOrder = 0.0;
        $sumCostItem = 0.0;
        $sumDivideOrderFee = 0.0;
        $buOidReshipNums = array();
        
        foreach ($itemsDetail as $itemDetail) {
            // 获取销售物料信息
            $salesMaterial = $salesMaterialModel->db_dump($itemDetail['sm_id'], 'sm_id,sales_material_name,sales_material_bn,sales_material_type');
            if (empty($salesMaterial)) {
                continue;
            }
            
            // 创建销售物料对应的 order_objects（金额与 return_reshipping_items_detail 对齐，缺省为 0）
            $dPrice = isset($itemDetail['price']) ? (float) $itemDetail['price'] : 0.0;
            $dPmtPrice = isset($itemDetail['pmt_price']) ? (float) $itemDetail['pmt_price'] : 0.0;
            $dSalePrice = isset($itemDetail['sale_price']) ? (float) $itemDetail['sale_price'] : 0.0;
            $dAmount = isset($itemDetail['amount']) ? (float) $itemDetail['amount'] : 0.0;
            $dDivideFee = isset($itemDetail['divide_order_fee']) ? (float) $itemDetail['divide_order_fee'] : 0.0;
            $dPartMjz = isset($itemDetail['part_mjz_discount']) ? (float) $itemDetail['part_mjz_discount'] : 0.0;
            $orderObjs = array(
                'obj_type' => $salesMaterial['sales_material_type'] == '2' ? 'pkg' : ($salesMaterial['sales_material_type'] == '3' ? 'gift' : 'goods'),
                'obj_alias' => $salesMaterial['sales_material_name'],
                'goods_id' => $salesMaterial['sm_id'],
                'name' => $salesMaterial['sales_material_name'],
                'bn' => $itemDetail['sales_material_bn'],
                'quantity' => $itemDetail['nums'],
                'price' => $dPrice,
                'pmt_price' => $dPmtPrice,
                'sale_price' => $dSalePrice,
                'amount' => $dAmount,
                'divide_order_fee' => $dDivideFee,
                'part_mjz_discount' => $dPartMjz,
                'order_items' => array(),
            );
            $detailItemId = isset($itemDetail['item_id']) ? (int) $itemDetail['item_id'] : 0;
            if ($isDeliveryBillReshipping && $detailItemId > 0 && isset($applyItemsByItemId[$detailItemId])) {
                $platformOid = $returnReshippingLib->resolvePlatformOidForApplyItem(
                    $applyItemsByItemId[$detailItemId],
                    $originalOrderId
                );
                if ($platformOid !== '') {
                    $orderObjs['oid'] = $platformOid;
                    $detailNumsForOid = isset($itemDetail['nums']) ? (int) $itemDetail['nums'] : 0;
                    if ($detailNumsForOid > 0) {
                        if (!isset($buOidReshipNums[$platformOid])) {
                            $buOidReshipNums[$platformOid] = 0;
                        }
                        $buOidReshipNums[$platformOid] += $detailNumsForOid;
                    }
                }
            }
            
            // 获取销售物料关联的基础物料
            $salesBasicMaterials = $salesBasicMaterialModel->getList('*', array('sm_id' => $salesMaterial['sm_id']), 0, -1);
            
            // 如果没有关联基础物料，跳过该销售物料
            if (empty($salesBasicMaterials)) {
                continue;
            }
            
            // 先收集有效的基础物料行，再按 number 占比分摊 itemDetail 金额（多子行时避免重复整行金额）
            $bmLines = array();
            foreach ($salesBasicMaterials as $salesBasicMaterial) {
                $basicMaterial = $basicMaterialModel->db_dump($salesBasicMaterial['bm_id'], 'bm_id,material_name,material_bn,type');
                if (empty($basicMaterial)) {
                    continue;
                }
                $bmLines[] = array(
                    'salesBasicMaterial' => $salesBasicMaterial,
                    'basicMaterial'      => $basicMaterial,
                );
            }
            if (empty($bmLines)) {
                continue;
            }
            $totalBmNumber = 0.0;
            foreach ($bmLines as $bl) {
                $totalBmNumber += (float) $bl['salesBasicMaterial']['number'];
            }
            $equalRatio = null;
            if ($totalBmNumber <= 0) {
                $cnt = count($bmLines);
                $equalRatio = $cnt > 0 ? (1.0 / $cnt) : 1.0;
            }
            
            foreach ($bmLines as $bl) {
                $salesBasicMaterial = $bl['salesBasicMaterial'];
                $basicMaterial = $bl['basicMaterial'];
                $ratio = $equalRatio !== null
                    ? $equalRatio
                    : ((float) $salesBasicMaterial['number'] / $totalBmNumber);
                
                $order_item = array(
                    'product_id'        => $basicMaterial['bm_id'] ? $basicMaterial['bm_id'] : 0,
                    'bn'                => $basicMaterial['material_bn'],
                    'name'              => $basicMaterial['material_name'],
                    'quantity'          => $salesBasicMaterial['number'] * $itemDetail['nums'],
                    'addon'             => '',
                    'item_type'         => $salesMaterial['sales_material_type'] == '2' ? 'pkg' : 'product',
                    'price'             => round($dPrice * $ratio, 4),
                    'pmt_price'         => round($dPmtPrice * $ratio, 3),
                    'sale_price'        => round($dSalePrice * $ratio, 3),
                    'amount'            => round($dAmount * $ratio, 3),
                    'divide_order_fee'  => round($dDivideFee * $ratio, 3),
                    'part_mjz_discount' => round($dPartMjz * $ratio, 3),
                    'delete'            => 'false',
                );
                
                $orderObjs['order_items'][] = $order_item;
            }
            
            $order_objects[] = $orderObjs;
            $sumPmtGoods += $dPmtPrice;
            $sumPmtOrder += $dPartMjz;
            $lineNums = isset($itemDetail['nums']) ? (float) $itemDetail['nums'] : 0.0;
            $sumCostItem += $dPrice * $lineNums;
            $sumDivideOrderFee += $dDivideFee;
        }
        
        if (empty($order_objects)) {
            $result['msg'] = '没有有效的补寄商品';
            return $result;
        }
        
        $bufaOrderSdf['order_objects'] = $order_objects;
        $bufaOrderSdf['pmt_goods'] = round($sumPmtGoods, 3);
        $bufaOrderSdf['pmt_order'] = round($sumPmtOrder, 3);
        $bufaOrderSdf['cost_item'] = round($sumCostItem, 3);
        $amtPay = round($sumDivideOrderFee, 3);
        $bufaOrderSdf['total_amount'] = $amtPay;
        $bufaOrderSdf['final_amount'] = $amtPay;
        $bufaOrderSdf['payed'] = $amtPay;
        
        // 创建补发订单
        $error_msg = '';
        $orderResult = $orderModel->create_order($bufaOrderSdf, $error_msg);
        if (!$orderResult) {
            $result['msg'] = '创建补发订单失败：' . $error_msg;
            return $result;
        }

        // OMS创建订单之后，调用service处理相关业务（如 company_code 写入订单扩展属性）
        foreach (kernel::servicelist('ome.service.order.create.after') as $object) {
            if (method_exists($object, 'after_create')) {
                $object->after_create($bufaOrderSdf);
            }
        }
        
        // 更新为已支付（0元订单创建时是未支付）
        $orderModel->update(array('pay_status' => '1'), array('order_id' => $bufaOrderSdf['order_id']));
        
        // 保存收件人敏感数据
        $this->_saveReshippingReceiverEncrypt($bufaOrderSdf['order_id'], $reshipping);

        // 包裹中心补发：打「补寄」订单标记
        if ($isDeliveryBillReshipping) {
            $labelError = '';
            if (!kernel::single('ome_bill_label')->markBillLabel(
                $bufaOrderSdf['order_id'],
                '',
                'SOMS_BUFA_PKG',
                'order',
                $labelError
            )) {
                app::get('ome')->model('operation_log')->write_log(
                    'order_modify@ome',
                    $bufaOrderSdf['order_id'],
                    '包裹中心补发：打「补寄」标记失败：' . ($labelError ?: '未知错误')
                );
            }
        }

        // 包裹中心补发：按创建订单使用的 oid 复制原单优惠方案
        if ($isDeliveryBillReshipping && !empty($buOidReshipNums)) {
            $promoCopied = $returnReshippingLib->copyOriginalOrderPromotionsByOids(
                $originalOrderId,
                (int) $bufaOrderSdf['order_id'],
                $buOidReshipNums
            );
            if ($promoCopied > 0) {
                app::get('ome')->model('operation_log')->write_log(
                    'order_modify@ome',
                    $bufaOrderSdf['order_id'],
                    '包裹中心补发：已从原订单复制 ' . $promoCopied . ' 条优惠方案（按子单 oid）'
                );
            }
        }
        
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
     * 物流中心包裹中心补寄单：补发订单收货人沿用原发货单/原订单快照
     *
     * @param array $reshipping
     * @param array $originalOrder orders::dump 结果
     * @return array
     */
    private function _buildBufaConsigneeFromDeliveryBill($reshipping, $originalOrder)
    {
        $deliveryId = 0;
        if (!empty($reshipping['extend_field'])) {
            $extend = is_array($reshipping['extend_field'])
                ? $reshipping['extend_field']
                : json_decode($reshipping['extend_field'], true);
            if (!empty($extend['delivery_id'])) {
                $deliveryId = (int) $extend['delivery_id'];
            }
        }
        if ($deliveryId > 0) {
            $rows = app::get('ome')->model('delivery')->getList(
                'ship_name,ship_mobile,ship_tel,ship_addr,ship_area',
                array('delivery_id' => $deliveryId),
                0,
                1
            );
            if (!empty($rows[0])) {
                $d = $rows[0];
                return array(
                    'name'      => $d['ship_name'],
                    'addr'      => $d['ship_addr'],
                    'zip'       => '',
                    'telephone' => $d['ship_tel'],
                    'mobile'    => $d['ship_mobile'],
                    'email'     => '',
                    'area'      => $d['ship_area'],
                );
            }
        }

        if (!empty($originalOrder['consignee']) && is_array($originalOrder['consignee'])) {
            $c = $originalOrder['consignee'];
            $area = isset($c['area']) ? $c['area'] : '';
            if (is_array($area)) {
                $parts = array();
                foreach (array('province', 'city', 'county', 'district', 'town') as $k) {
                    if (!empty($area[$k])) {
                        $parts[] = $area[$k];
                    }
                }
                $area = implode('/', $parts);
            }
            return array(
                'name'      => isset($c['name']) ? $c['name'] : '',
                'addr'      => isset($c['addr']) ? $c['addr'] : '',
                'zip'       => isset($c['zip']) ? $c['zip'] : '',
                'telephone' => isset($c['telephone']) ? $c['telephone'] : '',
                'mobile'    => isset($c['mobile']) ? $c['mobile'] : '',
                'email'     => isset($c['email']) ? $c['email'] : '',
                'area'      => $area,
            );
        }

        return $this->_formatReshippingConsignee($reshipping);
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

        $receiverData = array(
            'order_id' => $order_id,
        );

        $encryptJson = '';
        if (kernel::single('ome_return_reshipping')->isDeliveryBillLocalReshipping($reshipping)) {
            $originalOrderId = !empty($reshipping['order_id']) ? (int) $reshipping['order_id'] : 0;
            if ($originalOrderId > 0) {
                $originalReceiver = $receiverObj->db_dump(array('order_id' => $originalOrderId), 'encrypt_source_data');
                if (!empty($originalReceiver['encrypt_source_data'])) {
                    $encryptJson = $originalReceiver['encrypt_source_data'];
                }
            }
        } elseif (!empty($reshipping['oaid'])) {
            $encryptJson = json_encode(array('oaid' => $reshipping['oaid']));
        }
        if ($encryptJson !== '') {
            $receiverData['encrypt_source_data'] = $encryptJson;
        }

        $receiverObj->save($receiverData);

        return true;
    }
    
    /**
     * 补寄确认明细展示：历史数据未写入金额时，按申请行 return_reshipping_items（订单行快照）与 item_id、数量比例补全
     *
     * @param array $items        return_reshipping_items 列表
     * @param array $itemsDetail  return_reshipping_items_detail 列表
     * @return array
     */
    public function enrichItemsDetailFromItems(array $items, array $itemsDetail)
    {
        $itemsByItemId = array();
        foreach ($items as $it) {
            if (!empty($it['item_id'])) {
                $itemsByItemId[$it['item_id']] = $it;
            }
        }
        foreach ($itemsDetail as $k => $d) {
            $iid = isset($d['item_id']) ? (int) $d['item_id'] : 0;
            if ($iid <= 0 || !isset($itemsByItemId[$iid])) {
                continue;
            }
            $app = $itemsByItemId[$iid];
            $hasDetailMoney = ((float) $d['amount'] > 0 || (float) $d['sale_price'] > 0 || (float) $d['divide_order_fee'] > 0);
            if ($hasDetailMoney) {
                continue;
            }
            if ((float) $app['amount'] <= 0 && (float) $app['sale_price'] <= 0 && (float) $app['divide_order_fee'] <= 0) {
                continue;
            }
            $origNums = (int) $app['nums'];
            $detailNums = (int) $d['nums'];
            $ratio = ($origNums > 0) ? ($detailNums / $origNums) : 1.0;
            $itemsDetail[$k]['pmt_price'] = round((float) $app['pmt_price'] * $ratio, 3);
            $itemsDetail[$k]['sale_price'] = round((float) $app['sale_price'] * $ratio, 3);
            $itemsDetail[$k]['amount'] = round((float) $app['amount'] * $ratio, 3);
            $itemsDetail[$k]['divide_order_fee'] = round((float) $app['divide_order_fee'] * $ratio, 3);
            $itemsDetail[$k]['part_mjz_discount'] = round((float) $app['part_mjz_discount'] * $ratio, 3);
            if ((float) $d['price'] == 0 && (float) $app['price'] > 0) {
                $itemsDetail[$k]['price'] = $app['price'];
            }
        }
        return $itemsDetail;
    }
}

