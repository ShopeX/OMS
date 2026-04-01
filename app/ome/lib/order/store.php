<?php

/**
 * 订单库存相关处理类
 * @Author: system
 * @Version: 2024/12/19
 * @Describe: 订单库存检查、缺货通知等功能
 */
class ome_order_store
{
    /**
     * 缺货通知方法
     * 对比订单商品数量与库存，发送缺货通知
     * 
     * @param array $order 订单信息
     * @param int $branch_id 仓库ID（必传）
     * @return array [true/false, ['msg' => '消息内容']]
     */
    public function lackNotify($order, $branch_id)
    {
        if (empty($order) || empty($order['objects']) || empty($branch_id)) {
            return [false, ['msg' => '参数错误：订单信息或仓库ID为空']];
        }

        // 获取订单商品信息
        $orderObjects = $order['objects'];
        $lackProducts = array(); // 缺货商品列表

        // 收集所有需要查询的商品ID
        $productIds = array();
        $orderItems = array(); // 存储订单商品信息
        
        foreach ($orderObjects as $object) {
            if (empty($object['items'])) {
                continue;
            }
            
            foreach ($object['items'] as $item) {
                $product_id = $item['product_id'];
                $order_nums = intval($item['nums']); // 订单商品数量
                
                if ($order_nums <= 0) {
                    continue;
                }
                
                $productIds[] = $product_id;
                $orderItems[$product_id] = array(
                    'product_id' => $product_id,
                    'bn' => $item['bn'],
                    'name' => $item['name'],
                    'nums' => $order_nums
                );
            }
        }
        
        if (empty($productIds)) {
            return [true, ['msg' => '订单中无有效商品']];
        }
        
        // 批量查询仓库商品库存信息
        $branchProductMdl = app::get('ome')->model('branch_product');
        $branchProductList = $branchProductMdl->getList('*', array(
            'branch_id' => $branch_id,
            'product_id' => $productIds
        ));
        
        // 将查询结果转换为以product_id为键的数组
        $branchProductMap = array();
        foreach ($branchProductList as $branchProduct) {
            $branchProductMap[$branchProduct['product_id']] = $branchProduct;
        }
        
        // 检查每个商品的库存情况
        foreach ($orderItems as $product_id => $item) {
            $order_nums = $item['nums'];
            
            if (isset($branchProductMap[$product_id])) {
                $branchProductInfo = $branchProductMap[$product_id];
                // 计算可用库存 = 库存 - 冻结库存
                $available_store = intval($branchProductInfo['store']) - intval($branchProductInfo['store_freeze']);
                
                // 如果可用库存小于订单数量，则为缺货
                if ($available_store < $order_nums) {
                    $lackProducts[] = array(
                        'product_id' => $product_id,
                        'product_bn' => $item['bn'],
                        'product_name' => $item['name'],
                        'order_nums' => $order_nums,
                        'available_store' => $available_store,
                        'lack_nums' => $order_nums - $available_store
                    );
                }
            } else {
                // 仓库中没有该商品，视为缺货
                $lackProducts[] = array(
                    'product_id' => $product_id,
                    'product_bn' => $item['bn'],
                    'product_name' => $item['name'],
                    'order_nums' => $order_nums,
                    'available_store' => 0,
                    'lack_nums' => $order_nums
                );
            }
        }
        
        // 如果有缺货商品，发送通知
        if (!empty($lackProducts)) {
            return $this->sendLackNotify($order, $lackProducts, $branch_id);
        }
        
        return [true, ['msg' => '库存充足，无需发送缺货通知']];
    }
    
    /**
     * 发送缺货通知
     * 
     * @param array $order 订单信息
     * @param array $lackProducts 缺货商品列表
     * @param int $branch_id 仓库ID
     * @return array [true/false, ['msg' => '消息内容']]
     */
    private function sendLackNotify($order, $lackProducts, $branch_id)
    {
        try {
            // 获取仓库信息
            $branchMdl = app::get('ome')->model('branch');
            $branchInfo = $branchMdl->db_dump(array('branch_id' => $branch_id),'name');
            $branch_name = $branchInfo ? $branchInfo['name'] : '未知仓库';
            
            // 构建缺货商品信息
            $lackProductInfo = '';
            foreach ($lackProducts as $product) {
                $lackProductInfo .= sprintf(
                    "\r\n<br/>商品编码：%s，商品名称：%s，订单数量：%d，可用库存：%d，缺货数量：%d",
                    $product['product_bn'],
                    $product['product_name'],
                    $product['order_nums'],
                    $product['available_store'],
                    $product['lack_nums']
                );
            }
            
            // 准备通知参数
            $params = array(
                'order_bn' => $order['order_bn'],
                'branch_name' => $branch_name,
                'lack_products' => $lackProductInfo,
                'lack_count' => count($lackProducts),
                'org_id' => $order['org_id'] ?? null
            );
            
            // 发送监控通知
            $monitorNotifyLib = kernel::single('monitor_event_notify');
            $result = $monitorNotifyLib->addNotify('order_lack_notify', $params, false);
            
            if ($result) {
                return [true, ['msg' => '缺货通知发送成功，共' . count($lackProducts) . '个商品缺货']];
            } else {
                return [false, ['msg' => '缺货通知发送失败']];
            }
            
        } catch (Exception $e) {
            // 记录错误日志
            return [false, ['msg' => '缺货通知发送异常：' . $e->getMessage()]];
        }
    }
    
    /**
     * 批量检查订单缺货情况
     * 
     * @param array $orders 订单列表
     * @param int $branch_id 仓库ID（必传）
     * @return array [true/false, ['msg' => '消息内容', 'results' => 检查结果]]
     */
    public function batchLackNotify($orders, $branch_id)
    {
        if (empty($orders)) {
            return [false, ['msg' => '订单列表为空']];
        }
        
        $results = array();
        $successCount = 0;
        $failCount = 0;
        
        foreach ($orders as $order) {
            $result = $this->lackNotify($order, $branch_id);
            $results[] = array(
                'order_id' => $order['order_id'],
                'order_bn' => $order['order_bn'],
                'result' => $result
            );
            
            if ($result[0]) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        $msg = "批量检查完成，成功：{$successCount}个，失败：{$failCount}个";
        return [true, ['msg' => $msg, 'results' => $results]];
    }
}
