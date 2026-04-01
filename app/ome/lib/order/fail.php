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
class ome_order_fail
{
    //批量修复失败订单
    public function batchModifyOrder(&$cursor_id,$params){
        //danny_freeze_stock_log
        define('FRST_TRIGGER_OBJECT_TYPE','订单：失败订单恢复批量修改');
        define('FRST_TRIGGER_ACTION_TYPE','ome_order_fail：batchModifyOrder');
        $oldPbn = $params['sdfdata']['oldPbn'];
        $pbn = $params['sdfdata']['pbn'];
        $opinfo = $params['opinfo'];
        foreach($params['sdfdata']['orderId'] as $val){
            $this->addFailOrderLog($val,$opinfo);//失败订单操作日志记录添加
            $this->modifyOrderItemsByBn($val,$oldPbn,$pbn, $params['sdfdata']['modifyType']);
        }
        return false;
    }

    public function modifyOrder($order_id){
        $orderObj = app::get('ome')->model('orders');
        $order = $orderObj->dump($order_id,'*',array('order_objects'=>array('*',array('order_items'=>array('*')))));
        $is_delete = false;

        if ($order['is_fail'] == 'true'){
            foreach($order['order_objects'] as $obj=>$items){
                if($items['goods_id'] <= 0 || empty($items['bn'])){
                    $is_delete = true;
                    break;
                }
                
                //不存在order_items 保持订单为失败订单
                if(!isset($items['order_items'])){
                    $is_delete = true;
                    break;
                }

                foreach($items['order_items'] as $key=>$item){
                    if($item['product_id']<=0 || !isset($item['product_id'])){
                        $is_delete = true;
                        break;
                    }
                }
            }

            //只要有个内容失败，这个订单还是失败订单，不变
            if($is_delete){
                $data = array('edit_status'=>'true');
                $orderObj->update($data,array('order_id' =>$order_id));
                return true;
            }
        }
        
        //修正订单
        $orderData = array();
        $orderData['is_modify'] = 'true';
        $orderData['is_fail'] = 'false';
        $orderData['archive'] = 0;
        $orderObj->update($orderData,array('order_id' =>$order_id));
        $affect_row = $orderObj->db->affect_row();
        if(is_numeric($affect_row) && $affect_row > 0){
            $order = array_merge($order, $orderData);
            $objRetrial = kernel::single('ome_order_retrial');
            list($rs, $msg) = $objRetrial->checkMonitorAbnormal($order);
            if($rs) {
                $objRetrial->monitorAbnormal($order['order_id'], $msg);
            }
            return true;
        }else{
            return false;
        }
    }

    //单个修复订单
    public function modifyOrderItems($order_id,$oldPbn,$pbn){
        $orderObj = app::get('ome')->model('orders');
        $orderItemMdl = app::get('ome')->model('order_items');
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        $shopObj = app::get('ome')->model('shop');
        
        //obj_type
        $objTypeList = $orderItemMdl->_obj_alias;
        
        //开启事务
        $orderObj->db->beginTransaction();

        $itemObj = app::get('ome')->model('order_items');
        $Oorder_objects = app::get('ome')->model('order_objects');

        // [拆单]修复_淘宝平台_订单进入ERP的原始数据
        $modify_order_oid    = array();
        
        //先更新订单为不可编辑
        $data = array('edit_status'=>'false');
        $ret = $orderObj->update($data,array('order_id' =>$order_id, 'edit_status|noequal' => 'false', 'process_status'=>'unconfirmed'));
        if(is_bool($ret)) {//防止并发
            $orderObj->db->rollBack();
            return false;
        }
        $this->addFailOrderLog($order_id);//失败订单操作日志记录添加

        $orderInfo = $orderObj->getList('order_id,order_bn,status,pay_status,ship_status,shop_id,shop_type',array('order_id'=>$order_id));
        
        //shop_bn
        $shopInfo = $shopObj->dump(array('shop_id'=>$orderInfo[0]['shop_id']), 'shop_bn');
        $shop_bn = $shopInfo['shop_bn'];
        
        //material
        $lucky_falg = false;
        if($pbn){
            $salesMLib = kernel::single('material_sales_material');
            
            foreach($pbn as $obj_id=>$bn){
                //获取对应的obj层数据
                $objInfo = $Oorder_objects->getList('*',array('obj_id'=>$obj_id), 0 , 1);
                //销售物料没对上，进行修复
                $salesMInfo = $salesMLib->getSalesMByBn($orderInfo[0]['shop_id'],$bn);
                if($salesMInfo){
                    $quantity = $objInfo[0]['quantity'] ? $objInfo[0]['quantity'] : 1;
                    if($salesMInfo['sales_material_type'] == 5){ //多选一
                        $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'],$quantity,$orderInfo[0]['shop_id']);
                    }elseif($salesMInfo['sales_material_type'] == 7){
                        //福袋组合
                        $luckybagParams = $salesMInfo;
                        $luckybagParams['sale_material_nums'] = $quantity;
                        $luckybagParams['shop_bn'] = $shop_bn;
                        
                        $fdResult = $fudaiLib->process($luckybagParams);
                        if($fdResult['rsp'] == 'succ'){
                            $basicMInfos = $fdResult['data'];
                        }
                        
                        //obj_type
                        $objInfo[0]['obj_type'] = 'lkb';
                        
                        //unset
                        unset($luckybagParams, $fdResult);
                    }else{
                        $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                    }
                    
                    if($basicMInfos){
                        //如果是促销类销售物料
                        if($salesMInfo['sales_material_type'] == 2){
                            $obj_type = 'pkg';
                            //item层关联基础物料平摊销售价
                            $salesMLib->calProSaleMPriceByRate($objInfo[0]['sale_price'], $basicMInfos);
                            $price_rate = $salesMLib->calProPriceByRate($objInfo[0]['price'], $basicMInfos);
                            $pmt_price_rate = $salesMLib->calpmtpriceByRate($objInfo[0]['pmt_price'], $basicMInfos);
                            
                            //组织item数据
                            foreach($basicMInfos as $k => $basicMInfo){
                                //$basicMInfo['retail_price'] ? $basicMInfo['retail_price'] : 0.00
                                //$price = $price_rate[$basicMInfo['material_bn']] ? bcdiv($price_rate[$basicMInfo['material_bn']]['rate_price'], $price_rate[$basicMInfo['material_bn']]['number'], 2) : 0.00;
                                $pmt_price  = $pmt_price_rate[$basicMInfo['material_bn']] ? $pmt_price_rate[$basicMInfo['material_bn']]['rate_price'] : 0.00;
                                $sale_price = $basicMInfo['rate_price'];
                                $amount     = bcadd($pmt_price, $sale_price,2);
                                $price = bcdiv($amount, $basicMInfo['number']*$quantity, 2);
                                $sendnum = $orderInfo[0]['ship_status'] == '0' ? 0 : $basicMInfo['number']*$quantity;

                                $order_items[] = array(
                                    'shop_goods_id'   => $objInfo[0]['shop_goods_id'] ? $objInfo[0]['shop_goods_id'] : 0,
                                    'product_id'      => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                                    'shop_product_id' => 0,
                                    'bn'              => $basicMInfo['material_bn'],
                                    'name'            => $basicMInfo['material_name'],
                                    'cost'            => $basicMInfo['cost'] ? $basicMInfo['cost'] : 0.00,
                                    'price'           => $price,
                                    'pmt_price'       => $pmt_price,
                                    'sale_price'      => $sale_price,
                                    'amount'          => $amount,
                                    'weight'          => $basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00,
                                    'quantity'        => $basicMInfo['number']*$quantity,
                                    'addon'           => '',
                                    'item_type'       => 'pkg',
                                    'delete'          => ($objInfo[0]['delete'] == 'true') ? 'true' : 'false',
                                    'sendnum'         => $sendnum,
                                );

                                
                            }
                        }elseif($salesMInfo['sales_material_type'] == 5){ //多选一
                            $subtotal = $objInfo[0]['amount'] ? (float)$objInfo[0]['amount'] : bcmul((float)$objInfo[0]['price'], $quantity,3);
                            
                            $obj_sale_price = (isset($objInfo[0]['sale_price']) && is_numeric($objInfo[0]['sale_price']) && -1 != bccomp($objInfo[0]['sale_price'], 0, 3) ) ? $objInfo[0]['sale_price'] : bcsub($subtotal, (float)$objInfo[0]['pmt_price'],3);
                            $obj_type = 'pko';
                            //组织item数据
                            foreach($basicMInfos as $k => $basicMInfo){
                                $sendnum = $orderInfo[0]['ship_status'] == '0' ? 0 : $basicMInfo['number'];
                                $order_items[] = array(
                                        'shop_goods_id' => $objInfo[0]['shop_goods_id'] ? $objInfo[0]['shop_goods_id'] : 0,
                                        'product_id' => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                                        'shop_product_id' => 0,
                                        'bn' => $basicMInfo['material_bn'],
                                        'name' => $basicMInfo['material_name'],
                                        'cost' => (float)$objInfo[0]['cost'] ? $objInfo[0]['cost'] : $basicMInfo['cost'],
                                        'price' => (float)$objInfo[0]['price'],
                                        'pmt_price' => 0.00,
                                        'sale_price' => bcmul($obj_sale_price/$quantity, $basicMInfo['number'], 3),
                                        'amount' => bcmul($obj_sale_price/$quantity, $basicMInfo['number'], 3),
                                        'weight' => $basicMInfo['weight'] ? $basicMInfo['weight']*$basicMInfo['number']: 0.00,
                                        'quantity' => $basicMInfo['number'],
                                        'addon' => '',
                                        'item_type' => 'pko',
                                        'delete' => ($objInfo[0]['delete'] == 'true') ? 'true' : 'false',
                                        'sendnum' => $sendnum,
                                );
                            }
                        }elseif($salesMInfo['sales_material_type'] == 7){
                            //福袋组合
                            $obj_type = 'lkb';
                            $item_type = 'lkb';
                            
                            foreach($basicMInfos as $k => $basicMInfo)
                            {
                                $sendnum = $orderInfo[0]['ship_status'] == '0' ? 0 : $basicMInfo['number'];
                                $sale_price = $basicMInfo['price'] * $basicMInfo['number'];
                                
                                //福袋组合ID
                                $luckybag_id = ($basicMInfo['combine_id'] ? $basicMInfo['combine_id'] : 0);
                                
                                //items
                                $order_items[] = array(
                                    'shop_goods_id' => $objInfo[0]['shop_goods_id'] ? $objInfo[0]['shop_goods_id'] : 0,
                                    'product_id' => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                                    'shop_product_id' => 0,
                                    'bn' => $basicMInfo['material_bn'],
                                    'name' => $basicMInfo['material_name'],
                                    'cost' => $basicMInfo['cost'] ? $basicMInfo['cost'] : 0.00,
                                    'price' => $basicMInfo['price'] ? $basicMInfo['price'] : 0.00,
                                    'pmt_price' => 0.00,
                                    'sale_price' => $sale_price ? $sale_price : 0.00,
                                    'amount' => $sale_price ? $sale_price : 0.00,
                                    'weight' => $basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00,
                                    'quantity' => $basicMInfo['number'],
                                    'addon' => '',
                                    'item_type' => $item_type,
                                    'delete' => ($objInfo[0]['delete'] == 'true') ? 'true' : 'false',
                                    'sendnum' => $sendnum,
                                    'luckybag_id' => $luckybag_id, //福袋组合ID
                                );
                            }
                            
                            $lucky_falg = true;
                        }else{
                            $obj_type = ($salesMInfo['sales_material_type'] == 1) ? 'goods' : 'gift';
                            $item_type = ($obj_type == 'goods') ? 'product' : 'gift';

                            if($obj_type == 'gift'){
                                //直接取object层的,不用做处理
                            }

                            //普通销售物料
                            foreach($basicMInfos as $k => $basicMInfo){
                                
                                $addon = '';
                                if ($objInfo[0]['product_attr']) {
                                    $addon = serialize(array('product_attr'=>$objInfo[0]['product_attr']));
                                }
                                $subtotal = $objInfo[0]['amount'] ? (float)$objInfo[0]['amount'] : bcmul((float)$objInfo[0]['price'], $quantity,3);
                                $sendnum = $orderInfo[0]['ship_status'] == '0' ? 0 : $objInfo[0]['sendnum']*$basicMInfo['number'];

                                //普通销售物料item层数据以object为主,原本就是1:1的两层结构,item合并object,item层数据真实
                                $order_items[] = array(
                                    'shop_goods_id'   => $objInfo[0]['shop_goods_id'] ? $objInfo[0]['shop_goods_id'] : 0,
                                    'product_id'      => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                                    'shop_product_id' => $objInfo[0]['shop_product_id'] ? $objInfo[0]['shop_product_id'] : 0,
                                    'bn'              => $basicMInfo['material_bn'],
                                    'name'            => $basicMInfo['material_name'],
                                    'cost'            => (float)$objInfo[0]['cost'] ? $objInfo[0]['cost'] : $basicMInfo['cost'],
                                    'price'           => (float)$objInfo[0]['price'],
                                    'pmt_price'       => (float)$objInfo[0]['pmt_price'],
                                    'sale_price'      => (isset($objInfo[0]['sale_price']) && is_numeric($objInfo[0]['sale_price']) && -1 != bccomp($objInfo[0]['sale_price'], 0, 3) ) ? $objInfo[0]['sale_price'] : bcsub($subtotal, (float)$objInfo[0]['pmt_price'],3),
                                    'amount'          => $subtotal,
                                    'weight'          => (float)$objInfo[0]['weight'] ? $objInfo[0]['weight'] : ($basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00),
                                    'quantity'        => $basicMInfo['number']*$quantity,
                                    'addon'           => $addon,
                                    'item_type'       => $item_type,
                                    'delete'          => ($objInfo[0]['delete'] == 'true') ? 'true' : 'false',
                                    'sendnum'         => $sendnum,
                                );
                            }
                        }
                    }else{
                        //找不到对应的基础物料
                        continue;
                    }
                    
                    //修复_淘宝平台_原始属性值
                    if($orderInfo[0]['shop_type'] == 'taobao')
                    {
                        $modify_order_oid[]    = array(
                                                            'order_id'=>$order_id,
                                                            'order_bn'=>$orderInfo[0]['order_bn'],
                                                            'obj_id'=>$objInfo[0]['obj_id'],
                                                            'old_bn'=>$oldPbn[$obj_id],
                                                            'new_bn'=>$salesMInfo['sales_material_bn'],
                                                        );
                    }
                    
                }else{
                    //找不到对应的销售物料
                    continue;
                }

                //原来两层结构都有问题失败的
                if($objInfo[0]['goods_id'] <= 0 || empty($objInfo[0]['bn'])){
                    $newOrder['order_objects'][] = array_merge($objInfo[0], array(
                        'obj_id' => $objInfo[0]['obj_id'],
                        'part_mjz_discount' => $objInfo[0]['part_mjz_discount'],
                        'divide_order_fee' => $objInfo[0]['divide_order_fee'],
                        'settlement_amount' => $objInfo[0]['settlement_amount'],
                        'actually_amount' => $objInfo[0]['actually_amount'],
                        'platform_pay_amount' => $objInfo[0]['platform_pay_amount'],
                        'platform_amount' => $objInfo[0]['platform_amount'],
                        'goods_id'      => $salesMInfo['sm_id'] ? $salesMInfo['sm_id'] : 0,
                        'bn'            => $bn ? $bn : null,
                        'obj_type'      => $obj_type,
                        'obj_alias'     => $objTypeList[$obj_type] ? $objTypeList[$obj_type] : '',
                        'order_items'   => $order_items,
                    ));

                    $obj_shop_freeze[$objInfo[0]['obj_id']] = $objInfo[0]['quantity'];
                    $obj_status[$objInfo[0]['obj_id']] = $objInfo[0]['delete'];
                }else{
                    //原来绑定基础物料item有问题失败的
                    $newOrder['order_objects'][] = array_merge($objInfo[0], array(
                        'obj_id' => $objInfo[0]['obj_id'],
                        'goods_id' => $objInfo[0]['goods_id'],
                        'part_mjz_discount' => $objInfo[0]['part_mjz_discount'],
                        'divide_order_fee' => $objInfo[0]['divide_order_fee'],
                        'settlement_amount' => $objInfo[0]['settlement_amount'],
                        'actually_amount' => $objInfo[0]['actually_amount'],
                        'platform_pay_amount' => $objInfo[0]['platform_pay_amount'],
                        'platform_amount' => $objInfo[0]['platform_amount'],
                        'order_items'   => $order_items,
                    ));
                }
                
                //注销上一轮的order_items
                unset($order_items);
            }
        }
        
        $is_sales_material_setmapping = false;
        if($newOrder){
            $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
            $needFreezeItem = [];
            foreach($newOrder['order_objects'] as $ok => $obj){
                
                // check setmapping
                // @todo：只要销售物料是PKG组合类型，都需要按SET商品价格计算；
                if(isset($obj['obj_type']) && $obj['obj_type'] === 'pkg'){
                    $is_sales_material_setmapping = true;
                }
                
                foreach($obj['order_items'] as $ik =>$item){
                    $num = intval($item['quantity'])-intval($item['sendnum']);
                    if($item['product_id'] > 0 && $item['delete'] == 'false' && $num > 0){
                        $item['goods_id'] = $obj['goods_id'];
                        $needFreezeItem[] = $item;
                    }

                    if($item['product_id'] > 0){
                        $basic_material_arr[] = $item['product_id'];
                    }
                }
            }
            if($needFreezeItem) {
                $branchBatchList = [];
                uasort($needFreezeItem, [kernel::single('console_iostockorder'), 'cmp_productid']);
                foreach($needFreezeItem as $item) {
                    $num = intval($item['quantity'])-intval($item['sendnum']);

                    $freezeData = [];
                    $freezeData['bm_id'] = $item['product_id'];
                    $freezeData['sm_id'] = $item['goods_id'];
                    $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                    $freezeData['bill_type'] = 0;
                    $freezeData['obj_id'] = $order_id;
                    $freezeData['shop_id'] = $orderInfo[0]['shop_id'];
                    $freezeData['branch_id'] = 0;
                    $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                    $freezeData['num'] = $num;
                    $freezeData['obj_bn'] = $orderInfo[0]['order_bn'];
                    //订单级货品冻结
                    $branchBatchList[] = $freezeData;
                }

                //订单级货品冻结
                $err = '';
                $resFreeze = $basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
                if($resFreeze == false){
                    //冻结预占流水添加失败,事务回滚
                    $orderObj->db->rollBack();
                    return false;
                }
            }
            //判断基础物料门店是否供货，供货的标记订单为全渠道订单
            if(app::get('o2o')->is_installed()){
                if($basic_material_arr){
                    
                    $basicMaterialLib    = kernel::single('material_basic_material');
                    $is_omnichannel      = $basicMaterialLib->isOmnichannelOrder($basic_material_arr);
                    if($is_omnichannel){
                        $newOrder['omnichannel'] = 1;
                    }
                }
            }else{
                unset($basic_material_arr);
            }
            
            $newOrder['order_id'] = $order_id;
            
            // 计算订单items分摊金额
            $newOrder = kernel::single('ome_order')->divide_objects_to_items($newOrder);
            
            $orderObj->save($newOrder);
        }
        
        //修正为正常订单
        if($this->modifyOrder($order_id)){
            //事务确认
            $orderObj->db->commit();
            
            //福袋日志记录
            if($lucky_falg){
                //订单详细信息
                $orderDetailInfo = $orderObj->dump($order_id, '*', array('order_objects'=>array('*',array('order_items'=>array('*')))));
                
                $luckyBagLib = kernel::single('ome_order_luckybag');
                $luckyBagLib->saveLuckyBagUseLogs($orderDetailInfo);
            }
            
            //订单打标识
            $error_msg = '';
            kernel::single('ome_preprocess_label')->process($order_id, $error_msg);
            
            // 订单预约信息
            kernel::single('ome_order_reservation')->createReservation($orderInfo[0]);
            
            //[SAP创建]调用service进行SAP创建
            foreach(kernel::servicelist('ome.service.order.create.after') as $object)
            {
                if(method_exists($object, 'after_create')){
                    $object->after_create($orderInfo[0]);
                }
            }
            
            return true;
        }else{
            //修复失败 事务回滚
            $orderObj->db->rollBack();
            return false;
        }
    }

    public function modifyOrderItemsByBn($order_id,$oldPbn,$pbn, $modifyType = 'bn')
    {
        $res = $this->_modifyOrderItemsByBn($order_id, $oldPbn, $pbn, $modifyType);
        return $res;
    }

    private function _modifyOrderItemsByBn($order_id,$oldPbn,$pbn, $modifyType = 'bn'){
        //防止并发修复订单
        $_inner_key = sprintf("fix_order_%s", md5($order_id));
        $aData = cachecore::fetch($_inner_key);
        if ($aData === false) {
            cachecore::store($_inner_key, 'fixed', 5);
        }else{//选中的失败订单已在修复中，请不要重复修复！！！如没有完成修复，请稍后重试！！！
            return false;
        }
        
        $orderObj = app::get('ome')->model('orders');
        $orderItemMdl = app::get('ome')->model('order_items');
        $shopObj = app::get('ome')->model('shop');
        
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        //obj_type
        $objTypeList = $orderItemMdl->_obj_alias;
        
        //开启事务
        $orderObj->db->beginTransaction();

        $itemObj = app::get('ome')->model('order_items');
        $Oorder_objects = app::get('ome')->model('order_objects');

        // [拆单]修复_淘宝平台_订单进入ERP的原始数据
        $modify_order_oid    = array();
        
        $data = array('edit_status'=>'false');
        $ret = $orderObj->update($data,array('order_id' =>$order_id, 'edit_status|noequal' => 'false', 'process_status'=>'unconfirmed'));
        if(is_bool($ret)) {//防止并发
            $orderObj->db->rollBack();
            return false;
        }
        
        $orderInfo = $orderObj->getList('order_id,order_bn,ship_status,shop_id,shop_type,is_fail',array('order_id'=>$order_id));
        
        // shop info
        $shopInfo = $shopObj->dump(array('shop_id'=>$orderInfo[0]['shop_id']), 'shop_bn');
        $shop_bn = $shopInfo['shop_bn'];
        
        $lucky_falg = false;
        if($oldPbn && $pbn && ($orderInfo[0]['is_fail'] == 'true')){
            $excludeObjIds = array();
            $salesMLib = kernel::single('material_sales_material');

            foreach($pbn as $key=>$bn){
                if (!$oldPbn[$key] || !$bn){
                    continue;
                }

                //获取对应的obj层数据
                $objectFilter = array('order_id'=>$order_id, 'goods_id'=>0);

                if ($modifyType == 'bn') {
                    $objectFilter['bn'] = $oldPbn[$key];
                } else {
                    $objectFilter['shop_goods_id'] = $oldPbn[$key];
                }

                // 过滤已处理子订单
                if (!empty($excludeObjIds)) {
                    $objectFilter['obj_id|notin'] = $excludeObjIds;
                }

                $objInfo = $Oorder_objects->getList('*', $objectFilter, 0 , 1);
                if(!$objInfo){
                    continue;
                }
                
                //销售物料没对上，进行修复
                $salesMInfo = $salesMLib->getSalesMByBn($orderInfo[0]['shop_id'],$bn);
                if($salesMInfo){
                    $quantity = $objInfo[0]['quantity'] ? $objInfo[0]['quantity'] : 1;
                    $order_items = array();
                    
                    // sales_material_type
                    if($salesMInfo['sales_material_type'] == 5){ //多选一
                        $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'],$quantity,$orderInfo[0]['shop_id']);
                    }elseif($salesMInfo['sales_material_type'] == 7){
                        //福袋组合
                        $luckybagParams = $salesMInfo;
                        $luckybagParams['sale_material_nums'] = $quantity;
                        $luckybagParams['shop_bn'] = $shop_bn;
                        $fdResult = $fudaiLib->process($luckybagParams);
                        if($fdResult['rsp'] == 'succ'){
                            $basicMInfos = $fdResult['data'];
                        }
                        
                        //obj_type
                        $objInfo[0]['obj_type'] = 'lkb';
                        
                        //unset
                        unset($luckybagParams, $fdResult);
                    }else{
                        $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                    }
                    
                    if($basicMInfos){
                        //如果是促销类销售物料
                        if($salesMInfo['sales_material_type'] == 2){
                            $obj_type = 'pkg';
                            //item层关联基础物料平摊销售价
                            $salesMLib->calProSaleMPriceByRate($objInfo[0]['sale_price'], $basicMInfos);
                            //组织item数据
                            foreach($basicMInfos as $k => $basicMInfo){
                                //$basicMInfo['retail_price'] ? $basicMInfo['retail_price'] : 0.00
                                $sendnum = $orderInfo[0]['ship_status'] == '0' ? 0 : $basicMInfo['number']*$quantity;
                                $order_items[] = array(
                                    'shop_goods_id'   => $objInfo[0]['shop_goods_id'] ? $objInfo[0]['shop_goods_id'] : 0,
                                    'product_id'      => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                                    'shop_product_id' => 0,
                                    'bn'              => $basicMInfo['material_bn'],
                                    'name'            => $basicMInfo['material_name'],
                                    'cost'            => $basicMInfo['cost'] ? $basicMInfo['cost'] : 0.00,
                                    'price'           => $basicMInfo['rate_price'] ? bcdiv($basicMInfo['rate_price'], $basicMInfo['number']*$quantity, 2) : 0.00,
                                    'pmt_price'       => 0.00,
                                    'sale_price'      => $basicMInfo['rate_price'] ? $basicMInfo['rate_price'] : 0.00,
                                    'amount'          => $basicMInfo['rate_price'] ? $basicMInfo['rate_price'] : 0.00,
                                    'weight'          => $basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00,
                                    'quantity'        => $basicMInfo['number']*$quantity,
                                    'addon'           => '',
                                    'item_type'       => 'pkg',
                                    'delete'          => ($objInfo[0]['delete'] == 'true') ? 'true' : 'false',
                                    'sendnum'         => $sendnum,
                                );
                            }
                        }elseif($salesMInfo['sales_material_type'] == 5){ //多选一
                            $subtotal = $objInfo[0]['amount'] ? (float)$objInfo[0]['amount'] : bcmul((float)$objInfo[0]['price'], $quantity,3);
                            
                            $obj_sale_price = (isset($objInfo[0]['sale_price']) && is_numeric($objInfo[0]['sale_price']) && -1 != bccomp($objInfo[0]['sale_price'], 0, 3) ) ? $objInfo[0]['sale_price'] : bcsub($subtotal, (float)$objInfo[0]['pmt_price'],3);
                            $obj_type = 'pko';
                            //组织item数据
                            foreach($basicMInfos as $k => $basicMInfo){
                                $sendnum = $orderInfo[0]['ship_status'] == '0' ? 0 : $basicMInfo['number'];
                                $order_items[] = array(
                                        'shop_goods_id' => $objInfo[0]['shop_goods_id'] ? $objInfo[0]['shop_goods_id'] : 0,
                                        'product_id' => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                                        'shop_product_id' => 0,
                                        'bn' => $basicMInfo['material_bn'],
                                        'name' => $basicMInfo['material_name'],
                                        'cost' => (float)$objInfo[0]['cost'] ? $objInfo[0]['cost'] : $basicMInfo['cost'],
                                        'price' => (float)$objInfo[0]['price'],
                                        'pmt_price' => 0.00,
                                        'sale_price' => bcmul($obj_sale_price/$quantity, $basicMInfo['number'], 3),
                                        'amount' => bcmul($obj_sale_price/$quantity, $basicMInfo['number'], 3),
                                        'weight' => $basicMInfo['weight'] ? $basicMInfo['weight']*$basicMInfo['number']: 0.00,
                                        'quantity' => $basicMInfo['number'],
                                        'addon' => '',
                                        'item_type' => 'pko',
                                        'delete' => ($objInfo[0]['delete'] == 'true') ? 'true' : 'false',
                                        'sendnum' => $sendnum,
                                );
                            }
                        }elseif($salesMInfo['sales_material_type'] == 7){
                            //福袋组合
                            $obj_type = 'lkb';
                            $item_type = 'lkb';
                            
                            foreach($basicMInfos as $k => $basicMInfo)
                            {
                                $sendnum = $orderInfo[0]['ship_status'] == '0' ? 0 : $basicMInfo['number'];
                                $sale_price = $basicMInfo['price'] * $basicMInfo['number'];
                                
                                //福袋组合ID
                                $luckybag_id = ($basicMInfo['combine_id'] ? $basicMInfo['combine_id'] : 0);
                                
                                //items
                                $order_items[] = array(
                                    'shop_goods_id' => $objInfo[0]['shop_goods_id'] ? $objInfo[0]['shop_goods_id'] : 0,
                                    'product_id' => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                                    'shop_product_id' => 0,
                                    'bn' => $basicMInfo['material_bn'],
                                    'name' => $basicMInfo['material_name'],
                                    'cost' => $basicMInfo['cost'] ? $basicMInfo['cost'] : 0.00,
                                    'price' => $basicMInfo['price'] ? $basicMInfo['price'] : 0.00,
                                    'pmt_price' => 0.00,
                                    'sale_price' => $sale_price ? $sale_price : 0.00,
                                    'amount' => $sale_price ? $sale_price : 0.00,
                                    'weight' => $basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00,
                                    'quantity' => $basicMInfo['number'],
                                    'addon' => '',
                                    'item_type' => $item_type,
                                    'delete' => ($objInfo[0]['delete'] == 'true') ? 'true' : 'false',
                                    'sendnum' => $sendnum,
                                    'luckybag_id' => $luckybag_id, //福袋组合ID
                                );
                            }
                            
                            $lucky_falg = true;
                        }else{
                            $obj_type = ($salesMInfo['sales_material_type'] == 1) ? 'goods' : 'gift';
                            $item_type = ($obj_type == 'goods') ? 'product' : 'gift';

                            if($obj_type == 'gift'){
                                //直接取object层的,不用做处理
                            }
                            //普通销售物料
                            foreach($basicMInfos as $k => $basicMInfo){
                                
                                $addon = '';
                                if ($objInfo[0]['product_attr']) {
                                    $addon = serialize(array('product_attr'=>$objInfo[0]['product_attr']));
                                }
                                $subtotal = $objInfo[0]['amount'] ? (float)$objInfo[0]['amount'] : bcmul((float)$objInfo[0]['price'], $quantity,3);
                                $sendnum = $orderInfo[0]['ship_status'] == '0' ? 0 : $objInfo[0]['sendnum']*$basicMInfo['number'];

                                //普通销售物料item层数据以object为主,原本就是1:1的两层结构,item合并object,item层数据真实
                                $order_items[] = array(
                                    'shop_goods_id'   => $objInfo[0]['shop_goods_id'] ? $objInfo[0]['shop_goods_id'] : 0,
                                    'product_id'      => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                                    'shop_product_id' => $objInfo[0]['shop_product_id'] ? $objInfo[0]['shop_product_id'] : 0,
                                    'bn'              => $basicMInfo['material_bn'],
                                    'name'            => $basicMInfo['material_name'],
                                    'cost'            => (float)$objInfo[0]['cost'] ? $objInfo[0]['cost'] : $basicMInfo['cost'],
                                    'price'           => (float)$objInfo[0]['price'],
                                    'pmt_price'       => (float)$objInfo[0]['pmt_price'],
                                    'sale_price'      => (isset($objInfo[0]['sale_price']) && is_numeric($objInfo[0]['sale_price']) && -1 != bccomp($objInfo[0]['sale_price'], 0, 3) ) ? $objInfo[0]['sale_price'] : bcsub($subtotal, (float)$objInfo[0]['pmt_price'],3),
                                    'amount'          => $subtotal,
                                    'weight'          => (float)$objInfo[0]['weight'] ? $objInfo[0]['weight'] : ($basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00),
                                    'quantity'        => $basicMInfo['number']*$quantity,
                                    'addon'           => $addon,
                                    'item_type'       => $item_type,
                                    'delete'          => ($objInfo[0]['delete'] == 'true') ? 'true' : 'false',
                                    'sendnum'         => $sendnum,
                                );
                            }
                        }
                    }else{
                        //找不到对应的基础物料
                        continue;
                    }
                    
                    //修复_淘宝平台_原始属性值
                    if($orderInfo[0]['shop_type'] == 'taobao')
                    {
                        $modify_order_oid[]    = array(
                                                            'order_id'=>$order_id,
                                                            'order_bn'=>$orderInfo[0]['order_bn'],
                                                            'obj_id'=>$objInfo[0]['obj_id'],
                                                            'old_bn'=>$oldPbn[$key],
                                                            'new_bn'=>$salesMInfo['sales_material_bn'],
                                                        );
                    }
                    
                }else{
                    //找不到对应的销售物料
                    continue;
                }

                //原来两层结构都有问题失败的
                if($objInfo[0]['goods_id'] <= 0 || empty($objInfo[0]['bn'])){
                    $newOrder['order_objects'][] = array_merge($objInfo[0], array(
                        'obj_id' => $objInfo[0]['obj_id'],
                        'part_mjz_discount' => $objInfo[0]['part_mjz_discount'],
                        'divide_order_fee' => $objInfo[0]['divide_order_fee'],
                        'settlement_amount' => $objInfo[0]['settlement_amount'],
                        'actually_amount' => $objInfo[0]['actually_amount'],
                        'platform_pay_amount' => $objInfo[0]['platform_pay_amount'],
                        'platform_amount' => $objInfo[0]['platform_amount'],
                        'goods_id'      => $salesMInfo['sm_id'] ? $salesMInfo['sm_id'] : 0,
                        'bn'            => $bn ? $bn : null,
                        'obj_type'      => $obj_type,
                        'obj_alias'     => $objTypeList[$obj_type] ? $objTypeList[$obj_type] : '',
                        'order_items'   => $order_items,
                    ));

                    $obj_shop_freeze[$objInfo[0]['obj_id']] = $objInfo[0]['quantity'];
                    $obj_status[$objInfo[0]['obj_id']] = $objInfo[0]['delete'];
                }else{
                    //原来绑定基础物料item有问题失败的
                    $newOrder['order_objects'][] = array_merge($objInfo[0], array(
                        'obj_id' => $objInfo[0]['obj_id'],
                        'goods_id' => $objInfo[0]['goods_id'],
                        'part_mjz_discount' => $objInfo[0]['part_mjz_discount'],
                        'divide_order_fee' => $objInfo[0]['divide_order_fee'],
                        'settlement_amount' => $objInfo[0]['settlement_amount'],
                        'actually_amount' => $objInfo[0]['actually_amount'],
                        'platform_pay_amount' => $objInfo[0]['platform_pay_amount'],
                        'platform_amount' => $objInfo[0]['platform_amount'],
                        'order_items'   => $order_items,
                    ));
                }

                //注销上一轮的order_items
                unset($order_items);

                // 补充已处理objid, 防止重复处理
                $excludeObjIds[] = $objInfo[0]['obj_id'];
            }
        }
        
        $is_sales_material_setmapping = false;
        if($newOrder){
            $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
            $needFreezeItem = [];
            foreach($newOrder['order_objects'] as $ok => $obj){
                
                // check setmapping
                // @todo：只要销售物料是PKG组合类型，都需要按SET商品价格计算；
                if(isset($obj['obj_type']) && $obj['obj_type'] === 'pkg'){
                    $is_sales_material_setmapping = true;
                }
                
                foreach($obj['order_items'] as $ik =>$item){
                    
                    $num = intval($item['quantity'])-intval($item['sendnum']);
                    
                    if($item['product_id'] > 0 && $item['delete'] == 'false' && $num > 0){
                        $item['goods_id'] = $obj['goods_id'];
                        $needFreezeItem[] = $item;
                    }

                    if($item['product_id'] > 0){
                        $basic_material_arr[] = $item['product_id'];
                    }
                }
            }
            if($needFreezeItem) {
                $branchBatchList = [];
                uasort($needFreezeItem, [kernel::single('console_iostockorder'), 'cmp_productid']);
                foreach($needFreezeItem as $item) {
                    $num = intval($item['quantity'])-intval($item['sendnum']);

                    $freezeData = [];
                    $freezeData['bm_id'] = $item['product_id'];
                    $freezeData['sm_id'] = $item['goods_id'];
                    $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                    $freezeData['bill_type'] = 0;
                    $freezeData['obj_id'] = $order_id;
                    $freezeData['shop_id'] = $orderInfo[0]['shop_id'];
                    $freezeData['branch_id'] = 0;
                    $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                    $freezeData['num'] = $num;
                    $freezeData['obj_bn'] = $orderInfo[0]['order_bn'];
                    //订单级货品冻结
                    $branchBatchList[] = $freezeData;
                }

                //订单级货品冻结
                $err = '';
                $resFreeze = $basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
                if($resFreeze == false){
                    //冻结预占流水添加失败,事务回滚
                    $orderObj->db->rollBack();
                    return false;
                }
            }

            //判断基础物料门店是否供货，供货的标记订单为全渠道订单
            if(app::get('o2o')->is_installed()){
                if($basic_material_arr){
                    
                    $basicMaterialLib    = kernel::single('material_basic_material');
                    $is_omnichannel      = $basicMaterialLib->isOmnichannelOrder($basic_material_arr);
                    if($is_omnichannel){
                        $newOrder['omnichannel'] = 1;
                    }
                }
            }else{
                unset($basic_material_arr);
            }
            
            $newOrder['order_id'] = $order_id;
            
            // 计算订单items分摊金额
            $newOrder = kernel::single('ome_order')->divide_objects_to_items($newOrder);
            
            $orderObj->save($newOrder);
        }
        
        //修复_淘宝平台_原始属性值
        if($modify_order_oid)
        {
            $this->modifyOrderOid($modify_order_oid);
        }

        //修正为正常订单
        if($this->modifyOrder($order_id)){
            //事务确认
            $orderObj->db->commit();
            
            //福袋日志记录
            if($lucky_falg){
                //订单详细信息
                $orderDetailInfo = $orderObj->dump($order_id, '*', array('order_objects'=>array('*',array('order_items'=>array('*')))));
                
                $luckyBagLib = kernel::single('ome_order_luckybag');
                $luckyBagLib->saveLuckyBagUseLogs($orderDetailInfo);
            }
            
            //订单打标识
            $error_msg = '';
            kernel::single('ome_preprocess_label')->process($order_id, $error_msg);
            
            //[SAP创建]调用service进行SAP创建
            foreach(kernel::servicelist('ome.service.order.create.after') as $object)
            {
                if(method_exists($object, 'after_create')){
                    $object->after_create($orderInfo[0]);
                }
            }
            
            return true;
        }else{
            //修复失败 事务回滚
            $orderObj->db->rollBack();
            return false;
        }
    }

    /**
     * 失败订单操作日志记录添加
     *
     * @return void
     * @author 
     **/
    function addFailOrderLog($order_id,$opinfo=NULL)
    {
        $oLog = app::get('ome')->model('operation_log');

        $log_id = $oLog->write_log('order_edit@ome',$order_id,"失败订单恢复",'',$opinfo);

        $orderObj = app::get('ome')->model('orders');
        $opObj = app::get('ome')->model('order_pmt');
        $membersObj = app::get('ome')->model('members');
        $paymentsObj = app::get('ome')->model('payments');
        $orders = $orderObj->dump(array('order_id'=>$order_id),"*",array("order_objects"=>array("*",array("order_items"=>array('*')))));

        //优惠方案
        $orders['pmt'] = $opObj->getList('*',array('order_id'=>$order_id));//订单优惠方案
        //会员信息
        $orders['mem_info'] = $membersObj->getRow($orders['member_id']);
        //支付单
        $orders['payments'] = $paymentsObj->getList('*',array('order_id'=>$order_id));

        $orderObj->write_log_detail($log_id,$orders);
    }

    public function getFailOrderByBn($smBns=array())
    {
        $orderObj = app::get('ome')->model('orders');
        
        // check
        if(empty($smBns)){
            return [];
        }
        
        $sql = "SELECT I.order_id FROM sdb_ome_order_objects as I LEFT JOIN sdb_ome_orders AS O ON I.order_id=O.order_id ";
        $sql .= " WHERE O.org_id>0 AND O.is_fail='true' AND O.edit_status='true' AND O.archive='1' ";
        $sql .= " AND I.goods_id=0 AND I.bn IN('". implode("','", $smBns). "') GROUP BY order_id LIMIT 0,1000";
        
        $orderList = $orderObj->db->select($sql);
        
        return $orderList;
    }
    
    public function getFailOrderByName($shopGoodsIds=array()){
        $orderObj = app::get('ome')->model('orders');
        
        // check
        if(empty($shopGoodsIds)){
            return [];
        }
        
        $sql = "SELECT I.order_id FROM sdb_ome_order_objects as I LEFT JOIN sdb_ome_orders AS O ON I.order_id=O.order_id ";
        $sql .= " WHERE O.org_id>0 AND O.is_fail='true' AND O.edit_status='true' AND O.archive='1' ";
        $sql .= " AND I.goods_id=0 AND I.shop_goods_id IN('". implode("','", $shopGoodsIds). "') GROUP BY order_id LIMIT 0,1000";
        
        $orderList = $orderObj->db->select($sql);
        
        return $orderList;
    }
    
    /**
     * 修复[淘宝平台]原始属性值
     * PS:拆单开启后,订单部分回写会使用
     *
     * @param  Array    $modify_order_oid
     * @return void
     **/
    function modifyOrderOid($modify_order_oid)
    {
        if(empty($modify_order_oid))
        {
            return false;
        }
        
        $orderDlyObj    = app::get('ome')->model('order_delivery');
        foreach ($modify_order_oid as $item_id => $item)
        {
            $getData    = $bn_data = array();
    
            //获取淘宝平台的原始数据
            $getData    = $orderDlyObj->dump(array('order_bn'=>$item['order_bn']), 'id, bn');
            
            if(empty($getData['bn']))
            {
                continue;
            }
            
            $bn_data   = unserialize($getData['bn']);
            foreach ($bn_data as $key => $val)
            {
                if($val == $item['old_bn'])
                {
                    $bn_data[$key]    = $item['new_bn'];
                }
            }
            $getData['bn']    = serialize($bn_data);
            
            $orderDlyObj->save($getData);
            
            unset($getData, $bn_data);
        }
        
        return true;
    }
}
