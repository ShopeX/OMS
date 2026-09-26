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


class ome_order_edit{
    /**
     * 获取订单编辑时每种objtype的显示内容定义
     * @access public
     * @param int $reship_id 退货单ID
     */
    public function get_config_list(){
        $conf_list = array(
            'goods' => kernel::single("ome_order_edit_goods")->get_config(),
            'pkg' => kernel::single("ome_order_edit_pkg")->get_config(),
            'gift' => kernel::single("ome_order_edit_gift")->get_config(),
            'giftpackage' => kernel::single("ome_order_edit_giftpackage")->get_config(),
            'lkb' => kernel::single("ome_order_edit_lkb")->get_config(),
            'pko' => kernel::single("ome_order_edit_pko")->get_config(),
        );
        return $conf_list;
    }
    
    /**
     * 处理订单编辑时提交的数据
     * @access public
     * @param array $objtype 如：array('goods','pkg');
     * @param array $data 订单编辑的数据 //POST
     */
    public function process_order_objtype($objtype,$data){
        $obj    = array();
        $new    = array();
        $total  = 0;
        $is_order_change = false;
        $is_goods_modify = false;
        
        if ($objtype && is_array($objtype))
        foreach ($objtype as $key =>$type){
            if ($service = kernel::service('ome.service.order.edit.'.$type)){
                if (method_exists($service,'process')){
                    $rs = $service->process($data);
                }
                
                if ($rs){
                    $obj = array_merge($obj,$rs['oobj'] ? $rs['oobj'] : array());
                    $new = array_merge($new,$rs['nobj'] ? $rs['nobj'] : array());
                    $total += is_numeric($rs['total']) ? $rs['total'] : 0;
                    $total_pmt_goods += is_numeric($rs['total_pmt_goods']) ? $rs['total_pmt_goods'] : 0;                    
                    if ($is_order_change == false) $is_order_change = $rs['is_order_change'] == true ? true : false;
                    if ($is_goods_modify == false) $is_goods_modify = $rs['is_goods_modify'] == true ? true : false;
                }
            }
        }
        $rs = array(
            'obj'   => $obj,
            'new'   => $new,
            'total' => $total,
            'is_order_change' => $is_order_change,
            'is_goods_modify' => $is_goods_modify,
            'total_pmt_goods' => $total_pmt_goods,
        );
        return $rs;
    }

    /**
     * 校验订单编辑时提交的数据
     * @access public
     * @param array $objtype 如：array('goods','pkg');
     * @param array $data 订单编辑的数据 //POST
     */
    public function valid_order_objtype($objtype,$data){
        $flag = true;
        $msg  = '';
        if ($objtype && is_array($objtype))
        foreach ($objtype as $key =>$type){
            if ($service = kernel::service('ome.service.order.edit.'.$type)){
                if (method_exists($service,'valid')){
                    $rs = $service->valid($data);
                    if ($rs !== true && $rs['flag'] == false){
                        $rs = array(
                            'flag' => $rs['flag'],
                            'msg' => $rs['msg'],
                        );
                        return $rs;
                    }
                }
            }
        }
        return true;
    }
    
    /**
     * 判断是否有商品数据提交
     * @param array $objtype 如：array('goods','pkg');
     * @param array $data 订单编辑的数据 //POST
     */
    public function is_null($objtype,$data){
        $flag = true;
        $dt = array();
        if ($objtype && is_array($objtype))
        foreach ($objtype as $key =>$type){
            if ($service = kernel::service('ome.service.order.edit.'.$type)){
                if (method_exists($service,'is_null')){
                    $rs = $service->is_null($data);
                    if ($rs == false) return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * 判断订单上商品明细是否被修改
     *
     * @param Array $objtype
     * @param Array $data
     * @return bool
     */
    public function is_edit_order_item($objtype, $data){
        
        if ($objtype && is_array($objtype)){
            foreach ($objtype as $key => $type){
                if ($service = kernel::service('ome.service.order.edit.'.$type)){
                    if (method_exists($service,'is_edit_product')){
                        
                        //发现有商品被修改或者金额变化直接返回true
                        $result = $service->is_edit_product($data);
                        if($result){
                            return true;
                        }
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * 批量替换订单明细中的基础物料
     *
     * @param int $order_id
     * @param array $params
     * @param string $err_msg
     * @return bool
     */
    public function replaceOrderItems($order_id, $params, &$err_msg=null)
    {
        $orderMdl = app::get('ome')->model('orders');
        $objectObj = app::get('ome')->model('order_objects');
        $itemObj = app::get('ome')->model('order_items');
        $smModel = app::get('material')->model('sales_material');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        // 是否替换所有SET下的此赠品
        $is_replace_all_gift = false;
        if(isset($params['is_replace_all_gift']) && $params['is_replace_all_gift']){
            $is_replace_all_gift = $params['is_replace_all_gift'];
        }
        
        // 新添加的赠品
        $addObjectGoods = [];
        if(isset($params['addObjectGoods']) && $params['addObjectGoods']){
            $addObjectGoods = $params['addObjectGoods'];
        }
        
        // 获取新的数据结构
        $replaceItemProducts = $params['replaceItemProducts'] ?? []; // 替换的子商品
        $deleteItemProducts = $params['deleteItemProducts'] ?? []; // 删除的子商品
        if (!empty($deleteItemProducts)) {
            $err_msg = '基础物料不允许删除，只支持替换';
            return false;
        }
        
        //防止订单编辑与生成发货单并发导致错误  更新订单修改标识
        $orderMdl->update(array('is_modify'=>'true'), array('order_id'=>$order_id));
        
        //订单信息
        $order = $orderMdl->dump($order_id, "*", array("order_objects" => array("*", array("order_items" => array('*')))));
        
        //check
        if(empty($order)){
            $err_msg = sprintf('订单ID[%s]没有找到订单信息', $order_id);
            return false;
        }
        
        // 删除库存冻结
        list($rs, $rsData) = $basicMStockFreezeLib->deleteOrderBranchFreeze([$order_id]);
        if(!$rs) {
            $err_msg = $order['order_bn'].'错误：'.$rsData['msg'];
            return false;
        }
        
        // 库存处理数组
        $needUpdateFreezeItem = [];
        $need_i = 0;
        
        // 订单商品被修改
        $is_edit_goods = false;
        
        // 1. 处理删除子商品操作
        if(!empty($deleteItemProducts)) {
            $objIds = [];
            foreach($deleteItemProducts as $product_bn => $deleteInfo)
            {
                $orderItemList = [];
                
                // find items
                if($is_replace_all_gift){
                    // 获取所有符合条件的订单item明细
                    $orderItemList = $this->findOrderItemListByProductBn($order_id, $deleteInfo['delete_product_bn'], $deleteInfo['delete_item_type']);
                }else{
                    // 只获取一条符合条件的订单item明细
                    $orderItemInfo = $this->findOrderItemByProductBn($order_id, $deleteInfo['delete_product_bn'], $deleteInfo['delete_item_type']);
                    if($orderItemInfo){
                        $orderItemList[0] = $orderItemInfo;
                    }
                }
                
                // check
                if(empty($orderItemList)){
                    continue;
                }
                
                // exec
                foreach ($orderItemList as $itemkey => $itemInfo)
                {
                    // item_id
                    $item_id = $itemInfo['item_id'];
                    
                    // obj_id
                    $obj_id = $itemInfo['obj_id'];
                    $objIds[$obj_id] = $obj_id;
                    
                    // order_objects
                    $orderObjectInfo = $order['order_objects'][$obj_id];
                    
                    // 检测发货数量
                    if($itemInfo['nums'] == $itemInfo['sendnum']){
                        $err_msg = $order['order_bn'].'货品明细已发货,不允许删除';
                        return false;
                    }elseif($itemInfo['sendnum'] > 0){
                        $err_msg = $order['order_bn'].'货品明细部分发货,不允许删除';
                        return false;
                    }elseif($itemInfo['nums'] == $itemInfo['split_num']){
                        $err_msg = $order['order_bn'].'货品明细已拆分,不允许删除';
                        return false;
                    }elseif($itemInfo['split_num'] > 0){
                        $err_msg = $order['order_bn'].'货品明细部分拆分,不允许删除';
                        return false;
                    }
                    
                    // update
                    $result = $itemObj->update(array('delete'=>'true'), array('item_id'=>$item_id, 'order_id'=>$order_id));
                    if(!$result) {
                        $err_msg = sprintf('订单号[%s]，删除子商品[%s]失败', $order['order_bn'], $product_bn);
                        return false;
                    }
                    
                    // stock freeze
                    $itemInfo['operate_type'] = 'delete';
                    $itemInfo['goods_id'] = ($orderObjectInfo['goods_id'] ? $orderObjectInfo['goods_id'] : 0);
                    
                    $need_i++;
                    $needUpdateFreezeItem[$need_i] = $itemInfo;
                    
                    // flag
                    $is_edit_goods = true;
                }
            }
            
            // 检测订单子商品是否全是删除状态
            foreach ($objIds as $objKey => $obj_id)
            {
                $tempItemInfo = $itemObj->dump(['obj_id'=>$obj_id, 'order_id'=>$order_id, 'delete'=>'false'], 'item_id');
                if(empty($tempItemInfo)){
                    // 更新订单子单为删除状态
                    $objectObj->update(['delete'=>'true'], ['obj_id'=>$obj_id, 'order_id'=>$order_id]);
                }
            }
        }
        
        // 2. 处理替换子商品操作
        if(!empty($replaceItemProducts)) {
            foreach($replaceItemProducts as $product_bn => $replaceInfo)
            {
                $orderItemList = [];
                
                // find items
                if($is_replace_all_gift){
                    // 获取所有符合条件的订单item明细
                    $orderItemList = $this->findOrderItemListByProductBn($order_id, $replaceInfo['original_product_bn'], $replaceInfo['original_item_type']);
                }else{
                    // 只获取一条符合条件的订单item明细
                    $orderItemInfo = $this->findOrderItemByProductBn($order_id, $replaceInfo['original_product_bn'], $replaceInfo['original_item_type']);
                    if($orderItemInfo){
                        $orderItemList[0] = $orderItemInfo;
                    }
                }
                
                // check
                if(empty($orderItemList)){
                    //$err_msg = sprintf('订单号[%s]，未找到要替换的原子商品[%s]', $order['order_bn'], $replaceInfo['original_product_bn']);
                    //return false;
                    
                    continue;
                }
                
                // exec
                foreach ($orderItemList as $itemkey => $oldItem)
                {
                    // item_id
                    $item_id = $oldItem['item_id'];
                    
                    // obj_id
                    $obj_id = $oldItem['obj_id'];
                    
                    // order_objects
                    $orderObjectInfo = $order['order_objects'][$obj_id];
                    
                    // 检测发货数量
                    if($oldItem['nums'] == $oldItem['sendnum']){
                        $err_msg = $order['order_bn'].'货品明细已发货,不允许替换';
                        return false;
                    }elseif($oldItem['sendnum'] > 0){
                        $err_msg = $order['order_bn'].'货品明细部分发货,不允许替换';
                        return false;
                    }elseif($oldItem['nums'] == $oldItem['split_num']){
                        $err_msg = $order['order_bn'].'货品明细已拆分,不允许替换';
                        return false;
                    }elseif($oldItem['split_num'] > 0){
                        $err_msg = $order['order_bn'].'货品明细部分拆分,不允许替换';
                        return false;
                    }
                    
                    // 获取新的基础物料信息
                    $bmInfo = $basicMaterialObj->dump(array('bm_id'=>$replaceInfo['replace_product_id']));
                    if(empty($bmInfo)) {
                        $err_msg = sprintf('替换的基础物料[%s]不存在', $replaceInfo['replace_product_bn']);
                        return false;
                    }
                    
                    // 1. 将原子商品标记为删除状态
                    $result = $itemObj->update(array('delete'=>'true'), array('item_id'=>$item_id, 'order_id'=>$order_id));
                    if(!$result) {
                        $err_msg = sprintf('订单号[%s]，标记原子商品[%s]为删除状态失败', $order['order_bn'], $replaceInfo['original_product_bn']);
                        return false;
                    }
                    
                    // stock freeze
                    $oldItem['operate_type'] = 'delete';
                    $oldItem['goods_id'] = ($orderObjectInfo['goods_id'] ? $orderObjectInfo['goods_id'] : 0);
                    
                    $need_i++;
                    $needUpdateFreezeItem[$need_i] = $oldItem;
                    
                    // 2. 插入新的替换子商品明细
                    $newItemData = array(
                        'order_id' => $order_id,
                        'obj_id' => $oldItem['obj_id'], // 使用相同的订单对象ID
                        'shop_goods_id' => $oldItem['shop_goods_id'],
                        'product_id' => $bmInfo['bm_id'],
                        'shop_product_id' => $oldItem['shop_product_id'],
                        'item_type' => $oldItem['item_type'], // 保持原有的商品类型
                        'bn' => $bmInfo['material_bn'],
                        'name' => $bmInfo['material_name'],
                        // 替换后沿用被替换基础物料的金额数据，避免金额被重置为0
                        'price' => $oldItem['price'],
                        'nums' => $replaceInfo['replace_product_nums'], // 使用替换数量
                        'cost' => $oldItem['cost'],
                        'amount' => $oldItem['amount'],
                        'pmt_price' => $oldItem['pmt_price'],
                        'sale_price' => $oldItem['sale_price'],
                        'divide_order_fee' => $oldItem['divide_order_fee'],
                        'part_mjz_discount' => $oldItem['part_mjz_discount'],
                        'platform_amount' => isset($oldItem['platform_amount']) ? $oldItem['platform_amount'] : 0,
                        'settlement_amount' => isset($oldItem['settlement_amount']) ? $oldItem['settlement_amount'] : 0,
                        'actually_amount' => isset($oldItem['actually_amount']) ? $oldItem['actually_amount'] : 0,
                        'platform_pay_amount' => isset($oldItem['platform_pay_amount']) ? $oldItem['platform_pay_amount'] : 0,
                        'protect_price' => isset($oldItem['protect_price']) ? $oldItem['protect_price'] : 0,
                        'weight' => 0,
                    );
                    
                    $newItemId = $itemObj->insert($newItemData);
                    if(!$newItemId) {
                        $err_msg = sprintf('订单号[%s]，插入替换子商品[%s]失败', $order['order_bn'], $product_bn);
                        return false;
                    }
                    
                    // stock freeze
                    $newItemData['operate_type'] = 'add';
                    $newItemData['goods_id'] = ($orderObjectInfo['goods_id'] ? $orderObjectInfo['goods_id'] : 0);
                    
                    $need_i++;
                    $needUpdateFreezeItem[$need_i] = $newItemData;
                    
                    // flag
                    $is_edit_goods = true;
                }
            }
        }
        
        // 3. 处理新增赠品操作
        $addGiftList = [];
        if(!empty($addObjectGoods)) {
            foreach($addObjectGoods as $goods_bn => $addInfo)
            {
                $quantity = $addInfo['quantity'];
                
                // 获取销售物料信息
                $smInfo = $smModel->dump(array('sales_material_bn'=>$goods_bn), '*');
                if(empty($smInfo)) {
                    $err_msg = sprintf('销售物料[%s]不存在', $goods_bn);
                    return false;
                }
                
                // 新添加的赠品
                //@todo：需要进入审批流程，审批之后，才会真正添加赠品到订单里；
                $addGiftList[] = [
                    'obj_type' => 'gift',
                    'bn' => $smInfo['sales_material_bn'],
                    'quantity' => $quantity,
                    'name' => $smInfo['sales_material_name'],
                    'price' => 0,
                    'sale_price' => 0,
                    'divide_order_fee' => 0,
                    'amount' => 0,
                    'spec_info' => '',
                ];
                
                continue;
            }
        }
        
        // 处理库存冻结
        if($needUpdateFreezeItem) {
            uasort($needUpdateFreezeItem, [kernel::single('console_iostockorder'), 'cmp_productid']);
            
            $branchBatchList = [];
            foreach($needUpdateFreezeItem as $item)
            {
                if($item['operate_type'] == 'add') {
                    $freezeData = [];
                    $freezeData['bm_id'] = $item['product_id'];
                    $freezeData['sm_id'] = $item['goods_id'];
                    $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                    $freezeData['bill_type'] = 0;
                    $freezeData['obj_id'] = $order_id;
                    $freezeData['shop_id'] = $order['shop_id'];
                    $freezeData['branch_id'] = 0;
                    $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                    $freezeData['num'] = abs($item['nums']);
                    $freezeData['obj_bn'] = $order['order_bn'];
                    
                    $branchBatchList['+'][] = $freezeData;
                } else {
                    //删除商品,减少预占
                    $branchBatchList['-'][] = [
                        'bm_id'     =>  $item['product_id'],
                        'sm_id'     =>  $item['goods_id'],
                        'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                        'bill_type' =>  0,
                        'obj_id'    =>  $order_id,
                        'branch_id' =>  '',
                        'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                        'num'       =>  abs($item['nums']),
                    ];
                }
            }
            
            $err = '';
            $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
            $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
        }
        
        // [审批赠品]调用service进行审批订单添加的赠品
        if($addGiftList){
            try {
                foreach(kernel::servicelist('ome.service.order.change.after') as $object)
                {
                    if(method_exists($object, 'apply_approval')){
                        $object->apply_approval($order_id, $addGiftList);
                    }
                }
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
                $err_msg = sprintf('订单[%s]审批赠品失败：%s', $order['order_bn'], $error_msg);
                
                return false;
            }
            
            // logs
            $log_id = $operLogObj->write_log('order_modify@ome', $order_id, '添加赠品操作成功。');
        }else{
            // 记录操作日志
            if($is_edit_goods){
                // OMS订单批量编辑，调用service处理相关业务
                foreach(kernel::servicelist('ome.service.order.change.after') as $object)
                {
                    if(method_exists($object, 'apply_approval')){
                        $approvalData = [
                            'active' => 'replaceOrderItems',
                        ];
                        $object->apply_approval($order_id, $approvalData);
                    }
                }
                
                $log_id = $operLogObj->write_log('order_modify@ome', $order_id, '批量替换基础物料成功。');
            }else{
                // 未修改商品
                $log_id = 0;
                $operLogObj->write_log('order_modify@ome', $order_id, '批量替换基础物料：没有找到可替换的基础物料。');
            }
        }
        
        //订单快照
        if($log_id){
            $orderMdl->write_log_detail($log_id, $order);
            
            //修改明细打标
            $boolExtendStatus = ome_order_bool_extendstatus::__GOODS_PRICE;
            app::get('ome')->model('order_extend')->updateBoolExtendStatus($order_id, $boolExtendStatus);
        }
        
        return true;
    }
    
    /**
     * 根据商品编码查找子商品ID
     */
    private function findItemIdByProductBn($order_id, $product_bn, $item_type)
    {
        $itemObj = app::get('ome')->model('order_items');
        
        // filter
        $filter = array('order_id'=>$order_id, 'bn'=>$product_bn, 'delete'=>'false');
        
        // 指定订单子明细的类型
        if($item_type){
            $filter['item_type'] = $item_type;
        }
        
        $item = $itemObj->dump($filter, 'item_id');
        
        return $item ? $item['item_id'] : null;
    }
    
    /**
     * 根据商品编码查找子商品ID
     */
    public function findOrderItemByProductBn($order_id, $product_bn, $item_type=null)
    {
        $itemObj = app::get('ome')->model('order_items');
        
        // filter
        $filter = array('order_id'=>$order_id, 'bn'=>$product_bn, 'delete'=>'false');
        
        // 指定订单子明细的类型
        if($item_type){
            $filter['item_type'] = $item_type;
        }
        
        $itemInfo = $itemObj->dump($filter, '*');
        
        // nums
        if($itemInfo['quantity'] && !isset($itemInfo['nums'])){
            $itemInfo['nums'] = $itemInfo['quantity'];
        }
        
        return $itemInfo;
    }
    
    /**
     * 根据商品编码查找所有子商品ID
     */
    public function findOrderItemListByProductBn($order_id, $product_bn, $item_type=null)
    {
        $itemObj = app::get('ome')->model('order_items');
        
        // filter
        $filter = array('order_id'=>$order_id, 'bn'=>$product_bn, 'delete'=>'false');
        
        // 指定订单子明细的类型
        if($item_type){
            $filter['item_type'] = $item_type;
        }
        
        $itemList = $itemObj->getList('*', $filter);
        
        return $itemList;
    }
}