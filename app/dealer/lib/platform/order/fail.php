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
 * 修正失败的经销商订单Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2024.07.18
 */
class dealer_platform_order_fail extends dealer_abstract
{
    //批量修复失败订单
    public function batchModifyOrder(&$cursor_id, $params, &$error_msg=null)
    {
        $orderIds = $params['sdfdata']['orderIds'];
        $oldProductBns = $params['sdfdata']['oldProductBns'];
        $newProductBns = $params['sdfdata']['newProductBns'];
        $opinfo = $params['opinfo'];
        
        //check
        if(empty($orderIds)){
            $error_msg = '无效的任务,没有订单ID';
            return false;
        }
        
        if(empty($oldProductBns) || empty($newProductBns)){
            $error_msg = '无效的任务,没有可操作的货号';
            return false;
        }
        
        //按订单进行逐一修复
        foreach($orderIds as $plat_order_id)
        {
            //修正订单
            $result = $this->modifyOrderItems($plat_order_id, $oldProductBns, $newProductBns, $opinfo);
        }
        
        return false;
    }
    
    /**
     * 指定单个订单进行修复
     *
     * @param $plat_order_id
     * @param $oldProductBns  失败的货号
     * @param $newProductBns 替换的新货号
     * @param $opinfo 操作人
     * @return array
     */
    public function modifyOrderItems($plat_order_id, $oldProductBns, $newProductBns, $opinfo=null)
    {
        //防止并发修复订单
        $_inner_key = sprintf("repair_platform_order_%s", md5($plat_order_id));
        $aData = cachecore::fetch($_inner_key);
        if ($aData === false) {
            cachecore::store($_inner_key, 'fixed', 5);
        }else{
            $error_msg = '订单已在修复中,请不要重复修复!';
            return $this->error($error_msg);
        }
        
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $jxObjectMdl = app::get('dealer')->model('platform_order_objects');
        $jxItemMdl = app::get('dealer')->model('platform_order_items');
        $logMdl = app::get('ome')->model('operation_log');
        
        $orderLib = kernel::single('dealer_platform_orders');
        $deMaterialLib = kernel::single('dealer_material');
        $basicMStockLib = kernel::single('material_basic_material_stock');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //check
        if(empty($oldProductBns) || empty($newProductBns)){
            $error_msg = '修改提交的失败商品与替换商品不一致；';
            return $this->error($error_msg);
        }
        
        //format
        $dataList = array();
        foreach ($oldProductBns as $productKey => $product_bn)
        {
            if(empty($newProductBns[$productKey])){
                $error_msg = '货号：'. $product_bn .'没有填写调整货号；';
                return $this->error($error_msg);
            }
            
            $dataList['old'][$productKey] = $product_bn;
            $dataList['new'][$productKey] = $product_bn;
        }
        
        //开启事务
        $jxOrderMdl->db->exec('begin');
        
        //先更新订单为不可编辑
        $data = array('edit_status'=>'false', 'last_modified'=>time());
        $ret = $jxOrderMdl->update($data, array('plat_order_id'=>$plat_order_id, 'edit_status|noequal'=>'false'));
        
        //防止并发
        if(is_bool($ret)) {
            $jxOrderMdl->db->rollBack();
            
            $error_msg = '订单无法进行编辑，请稍候再试';
            return $this->error($error_msg);
        }
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $orderLib->getOrderDetail($filter);
        if(empty($orderInfo)){
            $error_msg = '平台订单未找到,请检查';
            return $this->error($error_msg);
        }
        
        //setting
        $objectList = array_column($orderInfo['order_objects'], null, 'plat_obj_id');
        $shop_id = $orderInfo['shop_id'];
        $is_fail_order = false;
        $failGoods = array();
        $productList = array();
        
        //添加失败订单操作日志记录
        $this->addFailOrderLog($plat_order_id, $opinfo);
        
        //repair
        foreach ($dataList['new'] as $productKey => $product_bn)
        {
            //失败的货号
            $old_product_bn = $dataList['old'][$productKey];
            
            //获取对应的obj层数据
            $objVal = $jxObjectMdl->dump(array('plat_order_id'=>$plat_order_id, 'bn'=>$old_product_bn, 'goods_id'=>0, 'is_delete'=>'false'), '*');
            if(empty($objVal)){
                $is_fail_order = true;
                $failGoods[] = '货号：'. $product_bn .'对应订单明细未找到,请检查';
                break;
            }
            
            $plat_obj_id = $objVal['plat_obj_id'];
            
            //check
            if($objectList[$plat_obj_id]['order_items']){
                $is_fail_order = true;
                $failGoods[] = '货号：'. $product_bn .'已经有items明细，无法修复';
                break;
            }
            
            //检查货品是否存在销售物料中
            $salesMInfo = $deMaterialLib->getSaleMaterialInfo($shop_id, $product_bn);
            if(empty($salesMInfo)){
                $is_fail_order = true;
                $failGoods[] = '货号：'. $product_bn .'对应销售物料不存在';
                break;
            }
            
            //check
            $smIds = array($salesMInfo['sm_id']);
            $bmList = $deMaterialLib->getBasicMatBySmIds($smIds);
            
            //check
            if(empty($bmList)){
                $is_fail_order = true;
                $failGoods[] = '货号：'. $product_bn .'对应基础物料不存在';
                break;
            }
            
            $obj_quantity = $objVal['quantity'];
            $obj_sale_price = $objVal['sale_price'];
            $obj_amount = $objVal['amount'];
            
            //组织item数据
            $obj_type = 'goods';
            switch ($salesMInfo['sales_material_type'])
            {
                case "2":
                    $obj_type = 'pkg';
                    
                    //根据促销总价格计算每个物料的贡献金额值
                    $deMaterialLib->calProSaleMatPriceByRate($obj_sale_price, $bmList);
                    
                    //根据优惠价格计算每个物料的贡献金额值
                    $pmt_price_rate = $deMaterialLib->getPmtPriceByRate($objVal['pmt_price'], $bmList);
                    break;
                case "3":
                    $obj_type = 'gift';
                    break;
            }
            
            //items
            foreach ($bmList as $k => $basicMInfo)
            {
                $product_id = $basicMInfo['bm_id'];
                $material_bn = $basicMInfo['material_bn'];
                
                //type
                if ($obj_type == 'pkg') {
                    $cost = $basicMInfo['cost'];
                    $pmt_price = $pmt_price_rate[$material_bn] ? ($pmt_price_rate[$material_bn]['rate_price'] > 0 ? $pmt_price_rate[$material_bn]['rate_price'] : 0) : 0.00;
                    
                    $sale_price = $basicMInfo['rate_price'] > 0 ? $basicMInfo['rate_price'] : 0;
                    $amount = bcadd((float)$pmt_price, (float)$sale_price, 2);
                    $price = bcdiv($amount, $basicMInfo['number'] * $obj_quantity, 2);
                    $weight = $basicMInfo['weight'];
                    $shop_product_id = 0;
                    $divide_order_fee = 0;
                    $part_mjz_discount = 0;
                    $item_type = 'pkg';
                    $item_nums = $basicMInfo['number'] * $obj_quantity;
                } else {
                    $cost = (float)$objVal['cost'] ? $objVal['cost'] : $basicMInfo['cost'];
                    $price = (float)$objVal['price'];
                    $pmt_price = (float)$objVal['pmt_price'];
                    $sale_price = $objVal['sale_price'];
                    $amount = $obj_amount;
                    $weight = (float)$objVal['weight'] ? $objVal['weight'] : ($basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00);
                    $shop_product_id = $objVal['shop_product_id'] ? $objVal['shop_product_id'] : 0;
                    $item_type = $obj_type == 'goods' ? 'product' : $obj_type;
                    $divide_order_fee = $objVal['divide_order_fee'];
                    $part_mjz_discount = $objVal['part_mjz_discount'];
                    $item_nums = $basicMInfo['number'] * $obj_quantity;
                }
                
                //insert
                $itemSdf = array(
                    'plat_order_id' => $plat_order_id,
                    'plat_obj_id' => $plat_obj_id,
                    'shop_goods_id' => $objVal['shop_goods_id'] ? $objVal['shop_goods_id'] : 0,
                    'product_id' => $product_id,
                    'shop_product_id' => $shop_product_id,
                    'bn' => $material_bn,
                    'name' => $basicMInfo['material_name'],
                    'cost' => $cost ? $cost : 0.00,
                    'price' => $price ? $price : 0.00,
                    'pmt_price' => $pmt_price,
                    'sale_price' => $sale_price ? $sale_price : 0.00,
                    'amount' => $amount ? $amount : 0.00,
                    'weight' => $weight ? $weight : 0.00,
                    'nums' => $item_nums, //购买数量
                    'addon' => '',
                    'item_type' => $item_type,
                    'delete' => ($objVal['status'] == 'close') ? 'true' : 'false',
                    'divide_order_fee' => $divide_order_fee,
                    'part_mjz_discount' => $part_mjz_discount,
                    'product_attr' => $objVal['product_attr'] ? $objVal['product_attr'] : "",
                );
                $jxItemMdl->insert($itemSdf);
                
                //products
                $productList[$product_id] = array(
                    'product_id' => $product_id,
                    'product_bn' => $material_bn,
                    'betc_id' => 0, //贸易公司ID
                    'is_shopyjdf_type' => '0', //发货方式
                );
            }
            
            //update
            $jxObjectMdl->update(array('goods_id'=>$salesMInfo['sm_id']), array('plat_obj_id'=>$plat_obj_id));
            
            //succ
            //$succGoods[] = $objVal['bn'];
        }
        
        //修复失败
        if($is_fail_order){
            //修复失败事务回滚
            $jxOrderMdl->db->rollBack();
            
            //logs
            $error_msg = implode(',', $failGoods);
            $logMdl->write_log('order_modify@dealer', $plat_order_id, $error_msg);
            
            return $this->error($error_msg);
        }
        
        //修正为正常订单
        $isModify = $this->modifyOrder($plat_order_id);
        if(!$isModify){
            //修复失败事务回滚
            $jxOrderMdl->db->rollBack();
            
            $error_msg = '修正为正常订单失败';
            return $this->error($error_msg);
        }
        
        //事务确认
        $jxOrderMdl->db->commit();
        
        //重新读取订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $orderLib->getOrderDetail($filter);
        
        //通过基础物料获取发货方式：自发、代发，所属贸易公司ID；
        $businessInfo = array('shop_id'=>$orderInfo['shop_id']);
        $productList = $orderLib->getProductDespatchType($productList, $businessInfo);
        
        //objects
        $needFreezeItems = array();
        foreach($orderInfo['order_objects'] as $objKey => $object)
        {
            //check
            if(empty($object['order_items'])){
                continue;
            }
            
            if($object['is_delete'] == 'true'){
                continue;
            }
            
            //items
            $shopyjdf_types = array();
            foreach($object['order_items'] as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                $betc_id = 0; //贸易公司ID
                $is_shopyjdf_type = '1'; //发货方式(默认自发)
                
                //check
                if($itemVal['is_delete'] == 'true'){
                    continue;
                }
                
                //已经转换过的则跳过
                if($itemVal['is_shopyjdf_type'] != '0'){
                    continue;
                }
                
                //发货方式
                if(isset($productList[$product_id])){
                    $betc_id = intval($productList[$product_id]['betc_id']);
                    $is_shopyjdf_type = $productList[$product_id]['is_shopyjdf_type'];
                }
                
                $itemVal['betc_id'] = $betc_id;
                $itemVal['is_shopyjdf_type'] = $is_shopyjdf_type;
                
                //汇总发货方式
                $shopyjdf_types[$is_shopyjdf_type] = $is_shopyjdf_type;
                
                //update
                $jxItemMdl->update(array('betc_id'=>$betc_id, 'is_shopyjdf_type'=>$is_shopyjdf_type, 'last_modified'=>time()), array('plat_item_id'=>$itemVal['plat_item_id']));
                
                //freeze
                if($is_shopyjdf_type == '2'){
                    $itemVal['store_code'] = '';
                    $itemVal['branch_id'] = 0;
                    
                    $needFreezeItems[] = $itemVal;
                }
            }
            
            //check
            if(empty($shopyjdf_types)){
                continue;
            }
            
            //object层发货方式
            if(count($shopyjdf_types) > 1){
                $obj_is_shopyjdf_type = '3'; //部分代发货，即有自发货，也有代发货；
            }else{
                $obj_is_shopyjdf_type = current($shopyjdf_types);
            }
            
            //转换状态
            if($obj_is_shopyjdf_type == '0'){
                $is_shopyjdf_step = '0';
            }else{
                $is_shopyjdf_step = '2';
            }
            
            //update
            $jxObjectMdl->update(array('is_shopyjdf_step'=>$is_shopyjdf_step, 'is_shopyjdf_type'=>$obj_is_shopyjdf_type, 'last_modified'=>time()), array('plat_obj_id'=>$object['plat_obj_id']));
        }
        
        //订单转换状态
        $dispose_status = 'zifa';
        $convert_status = '';
        if(count($shopyjdf_types) > 1){
            $dispose_status = 'part_daifa'; //部分代发货
        }else{
            $is_shopyjdf_type = current($shopyjdf_types);
            if($is_shopyjdf_type == '2'){
                $dispose_status = 'all_daifa'; //全部代发货
            }elseif($is_shopyjdf_type == '1'){
                $dispose_status = 'zifa'; //全部自发货
                $convert_status = 'needless'; //无需转单
            }
        }
        
        //update order
        $updateSdf = array('is_fail'=>'false', 'dispose_status'=>$dispose_status, 'last_modified'=>time());
        
        //转单状态
        if($convert_status){
            $updateSdf['convert_status'] = $convert_status;
        }
        
        $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
        
        //添加订单预占
        if($needFreezeItems) {
            //库存预占类型
            $freeze_obj_type = material_basic_material_stock_freeze::__ORDER;
            $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
            $bill_type = material_basic_material_stock_freeze::__DEALER_ORDER;
            
            //对数组排序
            uasort($needFreezeItems, [kernel::single('console_iostockorder'), 'cmp_productid']);
            
            //items
            foreach($needFreezeItems as $item)
            {
                $nums = intval($item['nums']);
                $product_id = $item['product_id'];
                
                //增加基础物料冻结数
                $basicMStockLib->freeze($product_id, $nums);
                
                //增加预占库存流水
                $freezeData = array(
                    'bm_id' => $product_id,
                    'obj_type' => $freeze_obj_type,
                    'bill_type' => $bill_type,
                    'obj_id' => $orderInfo['plat_order_id'],
                    'shop_id' => $orderInfo['shop_id'],
                    'branch_id' => $item['branch_id'],
                    'bmsq_id' => $bmsq_id,
                    'num' => $nums,
                    'log_type' => '',
                    'store_code' => $item['store_code'],
                    'obj_bn' => $orderInfo['plat_order_bn'], //平台订单号
                );
                $basicMStockFreezeLib->freeze($freezeData);
            }
            
            //[延迟自动审单]设置hold单，防止避免发货前退款；并且放入队列任务里,延迟自动审单；
            $orderLib->setOrderHoldTime($plat_order_id);
        }
        
        //logs
        $logMdl->write_log('order_modify@dealer', $orderInfo['plat_order_id'], '失败订单修复成功');
        
        return $this->succ('订单号：'. $orderInfo['plat_order_bn'] .'修复成功');
    }
    
    /**
     * 修正为正常订单
     *
     * @param $plat_order_id
     * @return bool
     */
    public function modifyOrder($plat_order_id)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        
        $orderLib = kernel::single('dealer_platform_orders');
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $order = $orderLib->getOrderDetail($filter);
        
        $is_fail = false;
        if ($order['is_fail'] == 'true'){
            foreach($order['order_objects'] as $obj=>$items)
            {
                if($items['goods_id'] <= 0 || empty($items['bn'])){
                    $is_fail = true;
                    break;
                }
                
                //不存在order_items 保持订单为失败订单
                if(!isset($items['order_items'])){
                    $is_fail = true;
                    break;
                }
                
                foreach($items['order_items'] as $key=>$item)
                {
                    if($item['product_id']<=0 || !isset($item['product_id'])){
                        $is_fail = true;
                        break;
                    }
                }
            }
            
            //只要有个内容失败，这个订单还是失败订单，不变
            if($is_fail){
                $data = array('edit_status'=>'true');
                $jxOrderMdl->update($data, array('plat_order_id'=>$plat_order_id));
                
                return false;
            }
        }
        
        //修正订单
        $orderData = array();
        $orderData['is_modify'] = 'true';
        $orderData['is_fail'] = 'false';
        
        $jxOrderMdl->update($orderData, array('plat_order_id'=>$plat_order_id));
        $affect_row = $jxOrderMdl->db->affect_row();
        if(is_numeric($affect_row) && $affect_row > 0){
            return true;
        }else{
            return false;
        }
    }
    
    /**
     * 失败订单操作日志记录添加
     *
     * @return void
     * @author
     **/
    public function addFailOrderLog($plat_order_id, $opinfo=NULL)
    {
        $orderObj = app::get('ome')->model('orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        $orderLib = kernel::single('dealer_platform_orders');
        
        //logs
        $log_msg = '失败订单修复';
        $log_id = $logMdl->write_log('order_modify@dealer', $plat_order_id, $log_msg, null, $opinfo);
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $orderLib->getOrderDetail($filter);
        $orderInfo['order_id'] = $orderInfo['plat_order_id'];
        
        $orderObj->write_log_detail($log_id, $orderInfo);
        
        return true;
    }
}
