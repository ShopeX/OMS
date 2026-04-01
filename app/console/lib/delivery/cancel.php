<?php
/**
 * 发货单取消处理类
 */
class console_delivery_cancel {
    
    /**
     * 发货单强制取消后的处理：如果拆分数量为0且支付状态为全额退款，则更新为已删除并更新主表金额
     * @param int $delivery_id 发货单ID
     * @return array [bool, array]
     */
    public function afterForce($delivery_id)
    {
        try {
            // 初始化模型
            $deliveryItemsDetailModel = app::get('ome')->model('delivery_items_detail');
            $orderItemsModel = app::get('ome')->model('order_items');
            $orderObjectsModel = app::get('ome')->model('order_objects');
            
            // 1. 获取发货单关联的订单明细信息
            $deliveryItemsDetails = $deliveryItemsDetailModel->getList('order_item_id,order_id,order_obj_id', [
                'delivery_id' => $delivery_id
            ]);
            
            if (empty($deliveryItemsDetails)) {
                return [true, ['msg' => '发货单没有关联的订单明细']];
            }
            
            // 2. 获取符合条件的订单明细（拆分数量为0且未删除）
            $orderItemIds = array_column($deliveryItemsDetails, 'order_item_id');
            $orderItems = $orderItemsModel->getList('item_id,obj_id,order_id,split_num,`delete`', [
                'item_id' => $orderItemIds,
                'split_num' => 0,
                'delete' => 'false'
            ]);
            
            if (empty($orderItems)) {
                return [true, ['msg' => '没有符合条件的订单明细需要处理']];
            }
            
            // 3. 获取订单子单支付状态映射
            $objIds = array_column($orderItems, 'obj_id');
            $orderObjects = $orderObjectsModel->getList('obj_id,pay_status', ['obj_id' => $objIds]);
            
            $objPayStatusMap = [];
            foreach ($orderObjects as $obj) {
                $objPayStatusMap[$obj['obj_id']] = $obj['pay_status'];
            }
            
            // 4. 分析需要删除的订单明细和子单
            $itemsToDeleteByOrder = [];
            $objectsToDeleteByOrder = [];
            $totalDeletedCount = 0;
            
            // 按订单ID分组处理
            $orderItemsGrouped = [];
            foreach ($orderItems as $item) {
                $orderItemsGrouped[$item['order_id']][] = $item;
            }
            
            foreach ($orderItemsGrouped as $order_id => $items) {
                $itemsToDelete = [];
                $objectsToDelete = [];
                
                // 按子单分组检查
                $itemsByObject = [];
                foreach ($items as $item) {
                    $itemsByObject[$item['obj_id']][] = $item;
                }
                
                foreach ($itemsByObject as $obj_id => $objItems) {
                    // 检查支付状态是否为全额退款(5)且所有明细拆分数量都为0
                    if (isset($objPayStatusMap[$obj_id]) && $objPayStatusMap[$obj_id] == '5') {
                        $allSplitNumZero = true;
                        foreach ($objItems as $item) {
                            if ($item['split_num'] != 0) {
                                $allSplitNumZero = false;
                                break;
                            }
                        }
                        
                        // 只有该子单下所有明细的拆分数量都为0时，才删除
                        if ($allSplitNumZero) {
                            foreach ($objItems as $item) {
                                $itemsToDelete[] = $item['item_id'];
                            }
                            $objectsToDelete[] = $obj_id;
                        }
                    }
                }
                
                if (!empty($itemsToDelete)) {
                    $itemsToDeleteByOrder[$order_id] = $itemsToDelete;
                    $objectsToDeleteByOrder[$order_id] = array_unique($objectsToDelete);
                    $totalDeletedCount += count($itemsToDelete);
                }
            }
            
            if (empty($itemsToDeleteByOrder)) {
                return [true, ['msg' => '没有需要删除的订单明细']];
            }
            
            // 5. 执行删除操作
            // 更新订单明细为已删除
            $allItemsToDelete = array_merge(...array_values($itemsToDeleteByOrder));
            $updateResult = $orderItemsModel->update(['delete' => 'true'], ['item_id' => $allItemsToDelete]);
            
            if (!$updateResult) {
                return [false, ['msg' => '更新订单明细删除状态失败']];
            }
            
            // 更新订单子单为已删除
            $allObjectsToDelete = array_merge(...array_values($objectsToDeleteByOrder));
            if (!empty($allObjectsToDelete)) {
                $updateObjectResult = $orderObjectsModel->update(['delete' => 'true'], ['obj_id' => $allObjectsToDelete]);
                
                if (!$updateObjectResult) {
                    return [false, ['msg' => '更新订单子单删除状态失败']];
                }
            }
            
            // 6. 重新计算每个订单的金额
            $recalculateResults = [];
            foreach ($objectsToDeleteByOrder as $order_id => $objectsToDelete) {
                if (empty($objectsToDelete)) {
                    continue;
                }
                
                $orderObjectsData = $orderObjectsModel->getList('obj_id,amount,divide_order_fee,pmt_price,part_mjz_discount,actually_amount,settlement_amount', [
                    'obj_id' => $objectsToDelete
                ]);
                
                $recalculateResult = kernel::single('ome_order_object_amount')->recalculateOrderAmount($order_id, $orderObjectsData);
                if (!$recalculateResult[0]) {
                    $recalculateResults[] = "订单ID {$order_id}: " . $recalculateResult[1]['msg'];
                }
            }
            
            if (!empty($recalculateResults)) {
                return [false, ['msg' => '部分订单金额重新计算失败：' . implode('; ', $recalculateResults)]];
            }
            
            return [true, ['msg' => '订单明细和子单处理成功']];
            
        } catch (Exception $e) {
            // 记录错误日志
            return [false, ['msg' => '处理订单明细失败：' . $e->getMessage()]];
        }
    }
}
