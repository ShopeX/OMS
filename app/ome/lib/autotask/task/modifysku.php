<?PHP
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * 修改SKU校验队列任务
 *
 * @author wangbiao@shopex.cn
 * @version 2026.10.12
 */
class ome_autotask_task_modifysku
{
    function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 异步执行淘宝改SKU后续：物料校验、库存检查、打回未发货发货单
     *
     * @param array $params
     * @param string $error_msg
     * @return bool
     */
    public function process($params, &$error_msg = '')
    {
        set_time_limit(180);
        
        // log_id
        $log_id = isset($params['log_id']) ? $params['log_id'] : 0;
        
        // check
        if (!$log_id || empty($params['log_text'])) {
            $error_msg = '缺少log_id或log_text';
            return false;
        }
        
        // log_text
        $payload = json_decode($params['log_text'], true);
        if (empty($payload) || !is_array($payload) || empty($payload['order_id'])) {
            $error_msg = 'log_text解析失败';
            return false;
        }
        
        $orderMdl = app::get('ome')->model('orders');
        $opLogMdl = app::get('ome')->model('operation_log');
        $batchLogMdl = $this->app->model('batch_log');
        
        // update
        $batchLogMdl->update(array('status'=>'2'), array('log_id'=>$log_id));
        
        $order_id = intval($payload['order_id']);
        $old_sales_material_bn = isset($payload['old_sales_material_bn']) ? $payload['old_sales_material_bn'] : ''; // 原SKU
        $new_sales_material_bn = isset($payload['new_sales_material_bn']) ? $payload['new_sales_material_bn'] : ''; // 新SKU
        
        // 订单信息
        $orderInfo = $orderMdl->dump(array('order_id'=>$order_id), 'order_id,order_bn,shop_id,ship_status,pay_status,pause');
        if (!$orderInfo) {
            $error_msg = '订单不存在';
            
            // update
            $batchLogMdl->update(array('status' => '1', 'fail_number' => 1), array('log_id' => $log_id));
            
            return false;
        }
        
        // 双保险：已发货则空跑退出
        if ((string) $orderInfo['ship_status'] !== '0') {
            $error_msg = '订单已发货，跳过改SKU后续';
            
            // log
            $log_msg = '改SKU队列，校验失败：订单已发货，无法修改SKU';
            $opLogMdl->write_log('order_modify@ome', $order_id, $log_msg);
            
            // update
            $batchLogMdl->update(array('status'=>'1'), array('log_id'=>$log_id));
            
            return true;
        }

        // 目标销售物料 / 基础物料
        $salesMaterialMdl = app::get('material')->model('sales_material');
        $salesBasicMdl = app::get('material')->model('sales_basic_material');
        $branchProductMdl = app::get('ome')->model('branch_product');
        $shopMdl = app::get('ome')->model('shop');
        
        // 新销售物料
        $sales_material_info = $salesMaterialMdl->dump(array('sales_material_bn'=>$new_sales_material_bn), 'sm_id');
        if (!$sales_material_info) {
            $error_msg = '没有找到对应销售物料';
            
            // 暂停订单
            $this->_pauseOrderLocal($order_id);
            
            // log
            $log_msg = '改SKU队列，校验失败：没有找到对应销售物料 ' . $new_sales_material_bn;
            $opLogMdl->write_log('order_modify@ome', $order_id, $log_msg);
            
            // update
            $batchLogMdl->update(array('status' => '1', 'fail_number' => 1), array('log_id' => $log_id));
            
            return false;
        }
        
        // 关联的基础物料列表
        $sales_basic_material_info = $salesBasicMdl->getList('*', array('sm_id'=>$sales_material_info['sm_id']));
        if (!$sales_basic_material_info) {
            $error_msg = '没有找到对应基础物料';
            
            // 暂停订单
            $this->_pauseOrderLocal($order_id);
            
            // log
            $log_msg = '改SKU队列，校验失败：没有找到对应基础物料 ' . $new_sales_material_bn;
            $opLogMdl->write_log('order_modify@ome', $order_id, $log_msg);
            
            // update
            $batchLogMdl->update(array('status' => '1', 'fail_number' => 1), array('log_id' => $log_id));
            
            return false;
        }

        // 库存校验：不足则本地暂停，不阻断撤单（撤单仍执行，避免仓继续作业）
        $stockOk = $this->_checkStockEnough($orderInfo, $old_sales_material_bn, $sales_basic_material_info, $shopMdl, $branchProductMdl);
        if (!$stockOk) {
            // 暂停订单
            $this->_pauseOrderLocal($order_id);
            
            //  log
            $log_msg = '改SKU队列，校验失败：目标SKU库存不足，已本地暂停 ' . $new_sales_material_bn;
            $opLogMdl->write_log('order_modify@ome', $order_id, $log_msg);
        }

        // 打回未发货发货单（幂等：已 back 的 rebackDelivery 会跳过）
        $deliveryMdl = app::get('ome')->model('delivery');
        $delivery_ids = $deliveryMdl->getDeliverIdByOrderId($order_id, true);
        if ($delivery_ids) {
            foreach ($delivery_ids as $delivery_id)
            {
                // 获取发货单信息
                $delivery_info = $deliveryMdl->dump(array('delivery_id' => $delivery_id));
                if (!$delivery_info) {
                    continue;
                }
                
                // check
                if (in_array($delivery_info['status'], array('ready', 'progress')) && ($delivery_info['is_bind'] == 'true' || $delivery_info['parent_id'] == 0)) {
                    // 撤销发货单
                    $result = $deliveryMdl->rebackDelivery($delivery_id, 'sku换货取消发货单');
                    if (!$result) {
                        $error_msg = '撤销发货单失败';
                        
                        // update
                        $batchLogMdl->update(array('status' => '1', 'fail_number' => 1), array('log_id' => $log_id));
                        
                        // log
                        $log_msg = '改SKU队列，校验失败：撤销发货单失败，发货单号：' . $delivery_info['delivery_bn'];
                        $opLogMdl->write_log('order_modify@ome', $order_id, $log_msg);
                        
                        return false;
                    }
                    
                    // 撤销合并发货单
                    if ($delivery_info['is_bind'] == 'true') {
                        $child_ids = $deliveryMdl->getItemsByParentId($delivery_id, 'array');
                        foreach ($child_ids as $id) {
                            $deliveryMdl->rebackDelivery($id, '合并发货单叫回');
                        }
                    }
                }
            }
        }
        
        // update
        $batchLogMdl->update(array('status' => '1'), array('log_id' => $log_id));
        
        // log
        $log_msg = '改SKU队列，校验成功：原SKU  ' . $old_sales_material_bn . ' → 新SKU ' . $new_sales_material_bn;
        $opLogMdl->write_log('order_modify@ome', $order_id, $log_msg);
        
        return true;
    }

    /**
     * 本地暂停，避免调用会通知仓的 pauseOrder
     *
     * @param int $order_id
     */
    protected function _pauseOrderLocal($order_id)
    {
        app::get('ome')->model('orders')->update(
            array('pause' => 'true'),
            array('order_id' => $order_id, 'status|noequal' => 'dead')
        );
    }

    /**
     * 按店铺仓关系汇总可用库存是否足够覆盖原 SKU 购买数量
     *
     * @param array $orderInfo
     * @param string $old_sales_material_bn
     * @param array $sales_basic_material_info
     * @param object $shopMdl
     * @param object $branchProductMdl
     * @return bool
     */
    protected function _checkStockEnough($orderInfo, $old_sales_material_bn, $sales_basic_material_info, $shopMdl, $branchProductMdl)
    {
        $shop_info = $shopMdl->dump(array('shop_id' => $orderInfo['shop_id']), 'shop_bn');
        if (!$shop_info || empty($shop_info['shop_bn'])) {
            return true;
        }

        $branches = app::get('ome')->getConf('shop.branch.relationship');
        $shop_branch = isset($branches[$shop_info['shop_bn']]) ? $branches[$shop_info['shop_bn']] : null;
        if (!$shop_branch || !is_array($shop_branch)) {
            return true;
        }

        $orderObjectsMdl = app::get('ome')->model('order_objects');
        $order_objects_info = $orderObjectsMdl->dump(
            array(
                'bn' => $old_sales_material_bn,
                'order_id' => $orderInfo['order_id'],
                'shop_goods_id|noequal' => '-1',
            ),
            'quantity'
        );
        $needQty = $order_objects_info ? floatval($order_objects_info['quantity']) : 0;
        if ($needQty <= 0) {
            return true;
        }

        $bm_id = array_column($sales_basic_material_info, 'bm_id');
        $shop_branch_ids = array_keys($shop_branch);
        $branch_product = $branchProductMdl->getList(
            'product_id,store_freeze,store',
            array('product_id' => $bm_id, 'branch_id' => $shop_branch_ids)
        );
        if (!$branch_product) {
            return false;
        }

        $sum_store = array();
        foreach ($branch_product as $bp) {
            if (!isset($sum_store[$bp['product_id']])) {
                $sum_store[$bp['product_id']] = 0;
            }
            $sum_store[$bp['product_id']] += $bp['store'] - $bp['store_freeze'];
        }

        $salesBasic = array_column($sales_basic_material_info, null, 'bm_id');
        foreach ($sum_store as $bmId => $num) {
            $number = isset($salesBasic[$bmId]['number']) ? $salesBasic[$bmId]['number'] : 1;
            if ($number <= 0) {
                $number = 1;
            }
            $sum_store[$bmId] = bcdiv($num, $number, 1);
        }

        if (empty($sum_store)) {
            return false;
        }

        return min($sum_store) >= $needQty;
    }
}
