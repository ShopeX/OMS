<?php
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
 * 系统自动审单
 *
 * @Time: 2015-03-09
 * @version 0.1
 */

class ome_autotask_task_preselectbranch
{

    /**
     * @description 执行批量自动审单
     * @access public
     * @param void
     * @return void
     */
    public function process($params, &$error_msg='') 
    {
        if( (!$params['log_id']) || (!$params['log_text']) ){
            $error_msg = '缺少数据';
            return false;
        }
        $params['log_text'] = unserialize($params['log_text']);
        if(count($params['log_text']) != 1) {
            $error_msg = '订单数不对';
            return false;
        }
        
        set_time_limit(240);
        
        $order_id   = intval(current($params['log_text']));
        $groups    = array();
        
        //[获取所有可操作的订单组]合并识别号_合并索引号[order_combine_hash、order_combine_idx]
        $row = app::get('ome')->model('orders')->db_dump(array('order_id'=>$order_id), '*');
        
        //[批量日志]处理中
        $deliBatchLog = app::get('ome')->model('batch_log');
        $rs = $deliBatchLog->update(array('status'=>'2'),array('log_id'=>$params['log_id'],'status'=>'0'));
        if(is_bool($rs)) {
            $error_msg = '队列已经跑过';
            return false;
        }

        //只处理未确认订单 && 失败订单不处理
        if(!$row ||
            !in_array($row['process_status'], array('unconfirmed','confirmed')) ||
            $row['is_fail'] == 'true' || 
            //$row['op_id'] ||
            //$row['group_id'] ||
            !$row['order_combine_hash'] ||
            !$row['order_combine_idx']
            )
        {
            //[批量日志]已处理
            $fail    = 1;
            $deliBatchLog->update(array('status'=>'1','fail_number'=>$fail),array('log_id'=>$params['log_id']));
            $error_msg = '订单状态不对' . var_export($row, 1);
            return false;
        }
        
        //是否允许审核
        if($row['is_not_combine'] > 0){
            //[批量日志]已处理
            $fail = 1;
            $deliBatchLog->update(array('status'=>'1','fail_number'=>$fail), array('log_id'=>$params['log_id']));
            
            return array(false, '订单已被标记：不允许审核!' . var_export($row, 1));
        }
        
        $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$row['shop_id']], 'delivery_mode');
        if($shop['delivery_mode'] == 'jingxiao') {
            $fail    = 1;
            $deliBatchLog->update(array('status'=>'1','fail_number'=>$fail),array('log_id'=>$params['log_id']));
            $error_msg = '经销店铺订单不增加仓库预占' . var_export($row, 1);
            return false;
        }
        $groups[]['orders'][]    = $order_id;
        
        //订单预处理
        // $preProcessLib = new ome_preprocess_entrance();
        // $preProcessLib->process($groups, $msg);
        
        //开始选仓预占
        $combineObj = new omeauto_auto_combine();
        $branchPlugObj = new omeauto_auto_plugin_branch();
        $splitStoreObj = new omeauto_split_storemax();
        $orderFreeze = app::get('material')->model('basic_material_stock_freeze')->getList('*', 
                        array('obj_type'=>1, 'obj_id'=>$order_id));
        $orderFreeze = array_column($orderFreeze, null, 'bm_id');

        $itemObjects = $combineObj->getItemObject($groups);
        $item = current($itemObjects);
        if(!is_object($item)) {
            $error_msg = '不是对象';
            return false;
        }
        $orders = $item->getOrders();
        foreach ($orders as $orderKey => $orderVal) {
            $storeCode = [];
            $storeCodeItems = [];
            foreach ($orderVal['objects'] as $objectKey => $objVal){
                #去掉已经预占仓库的明细
                foreach($objVal['items'] as $itemKey => $itemVal) {
                    if($orderFreeze[$itemVal['product_id']]['bill_type'] == material_basic_material_stock_freeze::__ORDER_YOU) {
                        unset($objVal['items'][$itemKey]);
                        continue;
                    }
                }
                if(empty($objVal['items'])) {
                    unset($orders[$orderKey]['objects'][$objectKey]);
                    continue;
                }
                #筛选出含有预选仓的明细
                if ($objVal['store_code']) {
                    $storeCode[] = $objVal['store_code'];
                    $storeCodeItems[$objVal['store_code']] = $storeCodeItems[$objVal['store_code']] ? array_merge($storeCodeItems[$objVal['store_code']], $objVal['items']) : $objVal['items'];
                    unset($orders[$orderKey]['objects'][$objectKey]);
                    continue;
                }
            }

        }
        
        $itemBranchStore = [];
        if($orders[$orderKey]['objects']) {
            #通过库存就全获取仓库
            $item->updateOrderInfo($orders);
            $branchPlugObj->process($item);
            $splitStoreObj->splitOrder($item,[]);
            $branch_id = 0;
            if(is_array($item->getBranchId())) {
                $branch_id = current($item->getBranchId());
            }
            if($branch_id) {
                $orders = $item->getOrders();
                foreach ($orders as $orderKey => $orderVal) {
                    foreach ($orderVal['objects'] as $objectKey => $objVal){
                        foreach($objVal['items'] as $itemKey => $itemVal) {
                            $itemBranchStore[$itemVal['product_id']]['bn'] = $itemVal['bn'];
                            $itemBranchStore[$itemVal['product_id']]['branch'][$branch_id] += $itemVal['nums'];
                        }
                    }
                }
            }
        }
        if($storeCode) {
            #通过指定仓获取仓库
            $appointBranch = kernel::single('ome_branch_type')->isAppointBranch($orderVal);
            $arrBranch     = kernel::single('ome_branch_type')->getBranchIdByStoreCode($storeCode, $appointBranch);
            foreach($storeCodeItems as $sc => $items) {
                $branch_id = $arrBranch[$sc];
                if($branch_id) {
                    $storeManage      = kernel::single('ome_store_manage');
                    $storeManage->loadBranch(array('branch_id'=>$branch_id));
                    foreach($items as $item) {
                        $usable_store   = $storeManage->processBranchStore(array(
                                'node_type' =>  'getAvailableStore',
                                'params'    =>  array(
                                    'branch_id' =>  $branch_id,
                                    'product_id'=>  $item['product_id'],
                                ),
                        ), $err_msg);
                        if($usable_store >= $item['nums']) {
                            $itemBranchStore[$item['product_id']]['bn'] = $item['bn'];
                            $itemBranchStore[$item['product_id']]['branch'][$branch_id] += $item['nums'];
                        }
                    }
                }
            }
        }

        kernel::single('material_basic_material_stock_freeze')->addOrderBranchFreeze($orderFreeze, $itemBranchStore);
        //[批量日志]已处理
        $deliBatchLog->update(array('status'=>'1','succ_number' => 1),array('log_id'=>$params['log_id']));
        
        return true;
    }

}