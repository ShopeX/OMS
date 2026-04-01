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

class openapi_data_original_sales{
    public function getList($filter,$offset=0,$limit=100){
        $sqlstr = '1';
        $shopObj = app::get('ome')->model('shop');
        
        //按店铺
        if ($filter['shop_bn']) {
            $shop_bn = explode('#',$filter['shop_bn']);
            $shop_id = $shopObj->getlist('shop_id',array('shop_bn|in'=>$shop_bn));
            foreach ($shop_id as $value) {
                $shop_ids[]=$value['shop_id'];
            }
            $shopIds = "'".implode("','",$shop_ids)."'";
            $sqlstr.=" AND shop_id in(".$shopIds.")";
        }
        
        //按订单号
        if ($filter['order_bn']) {
            $orderMdl = app::get('ome')->model('orders');
            
            $orderBns = explode(',', $filter['order_bn']);
            $orderList = $orderMdl->getList('order_id', array('order_bn'=>$orderBns));
            $order_ids = array_column($orderList, 'order_id');
            if($order_ids){
                $sqlstr .= " AND order_id IN(". implode(',', $order_ids) .")";
            }
            
            unset($orderBns, $orderList, $order_ids);
        }

        if($filter['sale_time']) {
            $sqlstr .= " AND sale_time >=".$filter['sale_time'][0].' AND sale_time <'.$filter['sale_time'][1];
        }

        if($filter['up_time']) {
            $sqlstr .= ' AND up_time >="'.date('Y-m-d H:i:s', $filter['up_time'][0]).'" AND up_time <"'.date('Y-m-d H:i:s', $filter['up_time'][1]).'"';
        }
        
        $formatFilter=kernel::single('openapi_format_abstract');
        $countList = kernel::database()->selectrow("select count(sale_id) as _count from sdb_ome_sales where ".$sqlstr);
        
        if(intval($countList['_count']) >0){
            $branchObj = app::get('ome')->model('branch');
            $orderObj = app::get('ome')->model('orders');
            $memberObj = app::get('ome')->model('members');
            $deliveryObj = app::get('ome')->model('delivery');
            $opObj = app::get('desktop')->model('users');
            $agent_obj = app::get('ome')->model('order_selling_agent');
            $orderItemObj = app::get('ome')->model('order_items');
            $orderObjects = app::get('ome')->model('order_objects');
            $shopInfos = array();
            $shop_arr = $shopObj->getList('shop_id,shop_bn,name', array(), 0, -1);
            foreach ($shop_arr as $k => $shop){
                $shopInfos[$shop['shop_id']] = $shop;
            }

            $branchInfos = array();
            $branch_arr = $branchObj->getList('branch_id,name,branch_bn', array(), 0, -1);
            foreach ($branch_arr as $k => $branch){
                $branchInfos[$branch['branch_id']]['name'] = $branch['name'];
                $branchInfos[$branch['branch_id']]['branch_bn'] = $branch['branch_bn'];
            }

            $saleLists = kernel::database()->select("select * from sdb_ome_sales where ".$sqlstr." limit ".$offset.",".$limit."");

            $saleIds = array();
            $orderIds = array();
            $memberIds = array();
            $deliveryIds = array();
            $opIds = array();
            foreach ($saleLists as $k => $sale)
            {
                $saleIds[] = $sale['sale_id'];

                if(intval($sale['order_id'])>0 && !in_array($sale['order_id'],$orderIds)){
                    $orderIds[] =  $sale['order_id'];
                }

                if(intval($sale['member_id'])>0 && !in_array($sale['member_id'],$memberIds)){
                    $memberIds[] = $sale['member_id'];
                }

                if(intval($sale['delivery_id'])>0 && !in_array($sale['delivery_id'],$deliveryIds)){
                    $deliveryIds[] = $sale['delivery_id'];
                }

                if(intval($sale['order_check_id'])>0 && !in_array($sale['order_check_id'],$opIds)){
                    $opIds[] = $sale['order_check_id'];
                }
            }
            
            //订单信息
            $order_arr = $orderObj->getList('order_id,order_bn,mark_text,tax_company,relate_order_bn,order_type,order_source',array('order_id'=>$orderIds),0,-1);
            foreach ($order_arr as $k => $order)
            {
                $orderInfos[$order['order_id']] = $order;
                if ($order['mark_text']) {
                    $orderInfos[$order['order_id']]['order_memo'] = $this->get_mark_text($order['mark_text']);
                }
                $orderInfos[$order['order_id']]['tax_title'] = $order['tax_company'];
                $orderInfos[$order['order_id']]['relate_order_bn'] = $order['relate_order_bn'];
            }
    
            //一次处理所有订单明细数据
            $itemList = $objectList = [];
    
            $objects = $orderObjects->getList('obj_id,order_id,obj_type,bn,goods_id,name', ['order_id' => $orderIds]);
            foreach ($objects as $object) {
                $obj_key              = $object['order_id'] . '_' . $object['bn'] . '_' . $object['obj_type'];
                $objectList[$obj_key] = $object;
            }
    
            $items = $orderItemObj->getList('item_id,obj_id,order_id,product_id,item_type,bn,name', ['order_id' => $orderIds]);
            foreach ($items as $item) {
                $item_key            = $item['order_id'] . '_' . $item['product_id'] . '_' . $item['item_type'];
                $itemList[$item_key] = $item;
            }
            unset($items, $objects);
            
            //会员信息
            $member_arr = $memberObj->getList('member_id,name',array('member_id'=>$memberIds),0,-1);
            foreach ($member_arr as $k => $member){
                $memberInfos[$member['member_id']] = $member['name'];
            }

            //发货单信息
            $delivery_arr = $deliveryObj->getList('delivery_id,delivery_bn,ship_name,ship_area,ship_province,ship_city,ship_district,ship_addr,ship_zip,ship_tel,ship_mobile,ship_email,logi_id,logi_name,logi_no,weight,delivery_cost_actual',array('delivery_id'=>$deliveryIds),0,-1);
            foreach ($delivery_arr as $k => $delivery){
                $deliveryInfos[$delivery['delivery_id']] = $delivery;
            }
            $logi = [];
            if($delivery_arr) {
                $logi = app::get('ome')->model('dly_corp')->getList('corp_id,type', array('corp_id' => array_column($delivery_arr, 'logi_id')));
                $logi = array_column($logi, 'type', 'corp_id');
            }
            //操作人信息
            $op_arr = $opObj->getList('user_id,name',array('user_id'=>$opIds),0,-1);
            foreach ($op_arr as $k => $op){
                $opInfos[$op['user_id']] = $op['name'];
            }
    
            //代销人信息
            $newAgentList = [];
            $agentList    = $agent_obj->getList('*', array_column($saleLists, 'selling_agent_id'));
            foreach ($agentList as $k => $info) {
                $newAgentList[$info['selling_agent_id']] = $agent_obj->plain_to_sdf($info);
            }
    
            $saleInfos = array();
            foreach ($saleLists as $k => $sale)
            {
                //下面有取发货单运单号重置
//                $sql = 'select ODB.logi_no from sdb_ome_delivery_bill as ODB left join sdb_ome_delivery_order as ODO on ODB.delivery_id = ODO.delivery_id left join sdb_ome_sales as OS on OS.order_id= ODO.order_id where OS.sale_id= '.$sale['sale_id'];
//                $delivery_bill = kernel::database()->select($sql);
//                if($delivery_bill){
//                    foreach ($delivery_bill as $value){
//                        $sale['logi_no'] .= '|'.$value['logi_no'];
//                    }
//                }
                
                $saleInfos[$sale['sale_id']] = $sale;
                $saleInfos[$sale['sale_id']]['order_bn'] = $orderInfos[$sale['order_id']]['order_bn'];
                $saleInfos[$sale['sale_id']]['order_type'] = $orderInfos[$sale['order_id']]['order_type'];
                $saleInfos[$sale['sale_id']]['shop_bn'] = $shopInfos[$sale['shop_id']]['shop_bn'];
                $saleInfos[$sale['sale_id']]['shop_name'] = $shopInfos[$sale['shop_id']]['name'];
                $saleInfos[$sale['sale_id']]['branch_name'] = $branchInfos[$sale['branch_id']]['name'];
                $saleInfos[$sale['sale_id']]['branch_bn'] = $branchInfos[$sale['branch_id']]['branch_bn'];
                $saleInfos[$sale['sale_id']]['member_name'] = $memberInfos[$sale['member_id']];
                $saleInfos[$sale['sale_id']]['delivery_bn'] = $deliveryInfos[$sale['delivery_id']]['delivery_bn'];
                $saleInfos[$sale['sale_id']]['ship_name'] = $deliveryInfos[$sale['delivery_id']]['ship_name'];
                $saleInfos[$sale['sale_id']]['ship_area'] = $deliveryInfos[$sale['delivery_id']]['ship_province'].'-'.$deliveryInfos[$sale['delivery_id']]['ship_city'].'-'.$deliveryInfos[$sale['delivery_id']]['ship_district'];
                $saleInfos[$sale['sale_id']]['ship_addr'] = $deliveryInfos[$sale['delivery_id']]['ship_addr'];
                $saleInfos[$sale['sale_id']]['ship_zip'] = $deliveryInfos[$sale['delivery_id']]['ship_zip'];
                $saleInfos[$sale['sale_id']]['ship_tel'] = $deliveryInfos[$sale['delivery_id']]['ship_tel'];
                $saleInfos[$sale['sale_id']]['ship_mobile'] = $deliveryInfos[$sale['delivery_id']]['ship_mobile'];
                $saleInfos[$sale['sale_id']]['ship_email'] = $deliveryInfos[$sale['delivery_id']]['ship_email'];
                $saleInfos[$sale['sale_id']]['order_check_name'] = $opInfos[$sale['order_check_id']];
                
                //新增物流公司、物流单号、包裹重量、订单备注、物流费 
                $saleInfos[$sale['sale_id']]['logi_name'] = $formatFilter->charFilter($deliveryInfos[$sale['delivery_id']]['logi_name']);
                $saleInfos[$sale['sale_id']]['logi_no'] = $formatFilter->charFilter($deliveryInfos[$sale['delivery_id']]['logi_no']);
                $saleInfos[$sale['sale_id']]['logi_code'] = $logi[$deliveryInfos[$sale['delivery_id']]['logi_id']] ? : '';
                $saleInfos[$sale['sale_id']]['weight'] = $formatFilter->charFilter($deliveryInfos[$sale['delivery_id']]['weight']);
                $saleInfos[$sale['sale_id']]['delivery_cost_actual'] = $formatFilter->charFilter($deliveryInfos[$sale['delivery_id']]['delivery_cost_actual']);
                $saleInfos[$sale['sale_id']]['order_memo'] = $formatFilter->charFilter($orderInfos[$sale['order_id']]['order_memo']);
                $saleInfos[$sale['sale_id']]['tax_title'] = $formatFilter->charFilter($orderInfos[$sale['order_id']]['tax_title']);
                
                //新增如果为换货订单号原订单号标识
                $saleInfos[$sale['sale_id']]['relate_order_bn'] = $orderInfos[$sale['order_id']]['relate_order_bn'];
                
                $saleInfos[$sale['sale_id']]['agent_info'] = $newAgentList[$sale['selling_agent_id']]['member_info'] ?? [];//代销人信息
                $saleInfos[$sale['sale_id']]['settlement_amount'] = $sale['settlement_amount'];
                $saleInfos[$sale['sale_id']]['platform_amount']   = $sale['platform_amount'];
                $saleInfos[$sale['sale_id']]['order_source']      = $sale['order_source'] ?: $orderInfos[$sale['order_id']]['order_source'];
                //items
                $saleInfos[$sale['sale_id']]['sale_items'] = array();
                
                //other
                $pkg_sales_ma_bn_arr[$sale['sale_id']] = array();
                $lkb_sales_ma_bn_arr[$sale['sale_id']] = array();
                $pko_sales_ma_bn_arr[$sale['sale_id']] = array();
            }

            if(count($saleIds) == 1){
                $_where_sql = " sale_id =".$saleIds[0]."";
            }else{
                $_where_sql = " sale_id in('".implode("','", $saleIds)."')";
            }
            
            //销售单明细
            $salesMaterialObj = app::get('material')->model('sales_material');
            
            $material_sales_type = array('product'=>'普通', 'pkg'=>'组合', 'gift'=>'赠品', 'lkb'=>'福袋', 'pko'=>'多选一');
            
            //items
            $sale_items = kernel::database()->select("select * from sdb_ome_sales_items where ".$_where_sql."");
    
            //获取销售物料
            $salesMaterialBns    = array_column($sale_items, 'sales_material_bn');
            $salesMaterialBnList = $salesMaterialObj->getList('sm_id,sales_material_bn,sales_material_name,sales_material_type', ['sales_material_bn' => $salesMaterialBns]);
            $salesMaterialBnList = array_column($salesMaterialBnList, null, 'sales_material_bn');
            
            $productIds = $objIds = array();
            foreach ($sale_items as $k =>$sale_item)
            {
                $sale_id = $sale_item['sale_id'];
                $sales_material_bn = $sale_item['sales_material_bn'];
                $obj_type = $sale_item['obj_type'];
                $product_id = intval($sale_item['product_id']);
                
                $productIds[$product_id] = $product_id;
                
                if(isset($saleInfos[$sale_item['sale_id']])){
                    $temp_order_id    = $saleInfos[$sale_item['sale_id']]['order_id'];
                    
                    // 判断是商品类型获取数据
                    if ($sale_item['product_id']){ //sunjing改后平铺明细走这里 用obj_type判断销售物料类型
                        if($obj_type == 'pkg'){
                            $getItem = $pkg_sales_ma_bn_arr[$sale_id][$sales_material_bn];
                            if(empty($getItem)){
                                //查询order_object层数据信息
                                $obj_key = $temp_order_id . '_' . $sales_material_bn . '_pkg';
                                if (isset($objectList[$obj_key]) && is_array($objectList[$obj_key])) {
                                    $getItem = [
                                        'obj_id'   => $objectList[$obj_key]['obj_id'],
                                        'goods_id' => $objectList[$obj_key]['goods_id'],
                                        'obj_type' => $objectList[$obj_key]['obj_type'],
                                    ];
                                }
                                
                                //销售物料名称
                                $getItem['sales_material_name'] = $salesMaterialBnList[$sale_item['sales_material_bn']]['sales_material_name'] ?? '';
                                
                                $pkg_sales_ma_bn_arr[$sale_id][$sales_material_bn] = $getItem;
                            }
                            
                            $sale_item['item_type'] = $obj_type;
                            $sale_item['type_name'] = $material_sales_type[$obj_type]; //物料类型名称
                            $sale_item['obj_id'] = $getItem['obj_id'] ?? '';
                            $sale_item['goods_id'] = $getItem['goods_id'] ?? '';
                            $sale_item['sm_id'] = $getItem['goods_id'] ?? '';
                            $sale_item['sales_material_name'] = $getItem['sales_material_name'] ?? '';
                        }elseif($obj_type == "lkb"){ //福袋
                            $obj_key = $temp_order_id . '_' . $sale_item['sales_material_bn'] . '_lkb';
                            if (isset($objectList[$obj_key]) && is_array($objectList[$obj_key])) {
                                $getItem = [
                                    'obj_id'   => $objectList[$obj_key]['obj_id'],
                                    'goods_id' => $objectList[$obj_key]['goods_id'],
                                    'obj_type' => $objectList[$obj_key]['obj_type'],
                                ];
                            }
                            
                            $sale_item['item_type'] = $getItem['obj_type'] ?? '';
                            $sale_item['type_name'] = $material_sales_type[$sale_item['item_type']];
                            $sale_item['obj_id'] = $getItem['obj_id'] ?? '';
                            $sale_item['goods_id'] = $getItem['goods_id'] ?? '';
                            $sale_item['sm_id'] = $getItem['goods_id'] ?? '';
                            
                            $sale_item['sales_material_name'] = $salesMaterialBnList[$sale_item['sales_material_bn']]['sales_material_name'] ?? '';
    
                            $lkb_sales_ma_bn_arr[$sale_item['sale_id']][] = $sale_item["sales_material_bn"];
                        }elseif($obj_type == "pko"){ //多选一
                            $obj_key = $temp_order_id . '_' . $sale_item['sales_material_bn'] . '_pko';
                            if (isset($objectList[$obj_key]) && is_array($objectList[$obj_key])) {
                                $getItem = [
                                    'obj_id'   => $objectList[$obj_key]['obj_id'],
                                    'goods_id' => $objectList[$obj_key]['goods_id'],
                                    'obj_type' => $objectList[$obj_key]['obj_type'],
                                ];
                            }
                            
                            $sale_item['item_type'] = $getItem['obj_type'] ?? '';
                            $sale_item['type_name'] = $material_sales_type[$sale_item['item_type']];
                            $sale_item['obj_id'] = $getItem['obj_id'] ?? '';
                            $sale_item['goods_id'] = $getItem['goods_id'] ?? '';
                            $sale_item['sm_id'] = $getItem['goods_id'] ?? '';
                            
                            $sale_item['sales_material_name'] = $salesMaterialBnList[$sale_item['sales_material_bn']]['sales_material_name'] ?? '';
                            $pko_sales_ma_bn_arr[$sale_item['sale_id']][] = $sale_item["sales_material_bn"];
                        }else{ //普通商品 赠品
                            $item_product_key = $temp_order_id . '_' . $sale_item['product_id'] . '_product';
                            $item_gift_key    = $temp_order_id . '_' . $sale_item['product_id'] . '_gift';
                            $itemInfo         = $itemList[$item_product_key] ?? $itemList[$item_gift_key];
                            $getItem          = [
                                'item_id'   => $itemInfo['item_id'] ?? '',
                                'obj_id'    => $itemInfo['obj_id'] ?? '',
                                'item_type' => $itemInfo['item_type'] ?? '',
                            ];
                            
                            $sale_item['item_type']    = $getItem['item_type'];
                            $sale_item['type_name']    = $material_sales_type[$getItem['item_type']];
                            $sale_item['obj_id']       = $getItem['obj_id'];
                        }
                    }else{ //旧数据$sale_item['product_id']为0时就能是促销物料
                        $obj_key = $temp_order_id . '_' . $sale_item['bn'] . '_pkg';
                        if (isset($objectList[$obj_key]) && is_array($objectList[$obj_key])) {
                            $getItem = [
                                'obj_id'   => $objectList[$obj_key]['obj_id'],
                                'goods_id' => $objectList[$obj_key]['goods_id'],
                                'obj_type' => $objectList[$obj_key]['obj_type'],
                            ];
                        }
                        
                        $sale_item['item_type']    = $getItem['obj_type'] ?? '';
                        $sale_item['type_name']    = $material_sales_type[$sale_item['item_type']];
                        $sale_item['obj_id']       = $getItem['obj_id'] ?? '';
                        $sale_item['goods_id']     = $getItem['goods_id'] ?? '';
                        
                        $sale_item['sm_id']                  = $getItem['goods_id'] ?? '';
                        $sale_item['sales_material_bn']      = $sale_item['bn'];
                        $sale_item['sales_material_name']    = $sale_item['name'];
                    }
                    $addon                        = $sale_item['addon'] ? json_decode($sale_item['addon'], true) : [];
                    $sale_item['shop_goods_id']   = isset($addon['shop_goods_id']) ? $addon['shop_goods_id'] : '';
                    $sale_item['shop_product_id'] = isset($addon['shop_product_id']) ? $addon['shop_product_id'] : '';
                    $saleInfos[$sale_item['sale_id']]['sale_items'] = array_merge($saleInfos[$sale_item['sale_id']]['sale_items'], array($sale_item));
                }
            }
            
            //基础物料信息
            $basicMaterialList = array();
            if($productIds){
                $basicMaterialList = $this->_getBasicMaterial($productIds);
            }
            
            $objectData = array_column($objectList,null,'obj_id');
            //[格式化]销售单明细中促销类型销售物料
            foreach ($saleInfos as $sale_id => $saleRow)
            {
                $dataList          = $saleRow['sale_items'];
                $sale_item_list    = array();
                
                //销毁
                unset($saleInfos[$sale_id]['sale_items']);
                
                foreach ($dataList as $iKey => $item)
                {
                    $product_id = intval($item['product_id']);
                    
                    if($item['item_type'] == 'pkg' || $item['item_type'] == 'lkb' || $item['item_type'] == 'pko'){
                        //基础物料信息
                        if($product_id){
                            $item['brand_code'] = $basicMaterialList[$product_id]['brand_code']; //物料品牌
                            $item['cat_name'] = $basicMaterialList[$product_id]['cat_name']; //物料分类
                            $item['goods_type'] = $basicMaterialList[$product_id]['type']; //物料属性
                            
                            $item['barcode'] = $basicMaterialList[$product_id]['barcode']; //条形码
                            $item['spec_name'] = $basicMaterialList[$product_id]['specifications']; //物料规格
                            $item['retail_price'] = $basicMaterialList[$product_id]['retail_price']; //物料销售价
                        }
                        
                        $sale_item_list[] = $item;
                    }else{
                        //关联销售物料信息
                        $getItem = $objectData[$item['obj_id']] ?? [];
                        $item['sm_id']                  = $getItem['goods_id'] ?? '';
                        $item['sales_material_bn']      = $getItem['bn'] ?? '';
                        $item['sales_material_name']    = $getItem['name'] ?? '';
                        
                        //基础物料信息
                        $item['brand_code'] = $basicMaterialList[$product_id]['brand_code']; //物料品牌
                        $item['cat_name'] = $basicMaterialList[$product_id]['cat_name']; //物料分类
                        $item['goods_type'] = $basicMaterialList[$product_id]['type']; //物料属性
                        
                        $item['barcode'] = $basicMaterialList[$product_id]['barcode']; //条形码
                        $item['spec_name'] = $basicMaterialList[$product_id]['specifications']; //物料规格
                        $item['retail_price'] = $basicMaterialList[$product_id]['retail_price']; //物料销售价
                        
                        $sale_item_list[] = $item;
                    }
                }

                //合并数据
                $saleInfos[$sale_id]['sale_items']    = $sale_item_list;
            }
            
            //销毁
            unset($dataList, $temp_item_data, $sale_item_list, $getItem, $getList, $material_sales_type,$itemList,$objectList,$objectData);
            return array(
                'lists' => $saleInfos,
                'count' => $countList['_count'],
            );
        }else{
            return array(
                'lists' => array(),
                'count' => 0,
            );
        }
    }
    
    /**
     * 返回备注
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_mark_text($mark_text)
    {
        $mark = unserialize($mark_text);
        $memo = array();
        if (is_array($mark) || !empty($mark)){
           $memo = array_pop($mark);
        }
        return $memo['op_content'];
    }

    /**
     * 返回所有备注内容
     * @param string $mark_text 序列化的备注数据
     * @return string 所有op_content内容，用||连接
     * @access  public
     */
    function getAllMarkText($mark_text)
    {
        if (empty($mark_text)) {
            return '';
        }
        
        $mark = @unserialize($mark_text);
        if (!is_array($mark) || empty($mark)) {
            return '';
        }
        
        $opContents = array();
        foreach ($mark as $item) {
            if (is_array($item) && isset($item['op_content']) && !empty($item['op_content'])) {
                $opContents[] = $item['op_content'];
            }
        }
        
        return implode('||', $opContents);
    }

    public function SalesAmount($start_time,$end_time,$offset=0,$limit=100,$shop_bn = false){
        if(empty($start_time) || empty($end_time)){
            return false;
        }
        $shopObj = app::get('ome')->model('shop');
        $shop_arr = $shopObj->getList('shop_id,shop_bn', array(), 0, -1);
        foreach ($shop_arr as $k => $_shop){
            $shopInfos["'".$_shop['shop_id']."'"] = $_shop;
        }
        $str_shop_id = null;
        if(!empty($shop_bn)){
            foreach ($shop_bn as $k => $_shop_bn){
                $shop_info = $shopObj->getList('shop_id,shop_bn,name', array('shop_bn'=>$_shop_bn));
                if(!empty($shop_info)){
                    $all_shop_id[] = $shop_info[0]['shop_id'];
                }
            }
            if(!empty($all_shop_id)){
                foreach($all_shop_id as $v){
                    if(trim($v)){
                        $shop_id[] = "'".trim($v)."'";
                    }
                }
                $str_shop_id = implode(',',$shop_id);
            }else{
                #传了店铺编码参数，但是店铺编码有误的
                return array('lists' => array());
            }
        }
        if(empty($str_shop_id)){
            $sql = "select count(sale_id) as _count from sdb_ome_sales where sale_time >=".$start_time." and sale_time <".$end_time; 
            $countList = kernel::database()->selectrow( $sql);
        }else{
            $sql = "select count(sale_id) as _count from sdb_ome_sales where sale_time >=".$start_time." and sale_time <".$end_time.' and shop_id in('.$str_shop_id .')';
            $countList = kernel::database()->selectrow($sql);
        }
        if(intval($countList['_count']) >0){
            if(empty($str_shop_id)){
                $saleLists = kernel::database()->select("select sale_id from sdb_ome_sales where sale_time >=".$start_time." and sale_time <".$end_time." order by sale_time asc limit ".$offset.",".$limit."");
            }else{
                $saleLists = kernel::database()->select("select sale_id from sdb_ome_sales where sale_time >=".$start_time." and sale_time <".$end_time.' and shop_id in('.$str_shop_id .')'." order by sale_time asc limit ".$offset.",".$limit."");
            }
            $saleIds = array();
            foreach ($saleLists as $k => $sale){
                $saleIds[] = $sale['sale_id'];
            }
            
            if(count($saleIds) == 1){
                $_where_sql = " sales.sale_id =".$saleIds[0]."";
            }else{
                $_where_sql = " sales.sale_id in(".implode(',', $saleIds).")";
            }
           /*  $sql = "select
                        sales.shop_id,sum(items.sales_amount) sales_amount
                    from sdb_ome_sales sales
                    left join sdb_ome_sales_items items
                    on  sales.sale_id=items.sale_id
                    where ".$_where_sql." group by sales.shop_id order by null"; */
            
            $sql = "select
                        shop_id,sum(sale_amount) sales_amount,sum(cost_freight) cost_freight,sum(discount) discount,sum(additional_costs) additional_costs
                    from sdb_ome_sales sales
                    where ".$_where_sql." group by sales.shop_id order by null";
            $sales_info = kernel::database()->select($sql);
            foreach($sales_info as $k=>$info){
                $sales_info[$k]['shop_bn'] = $shopInfos["'".$info['shop_id']."'"]['shop_bn'];
                unset($sales_info[$k]['shop_id']);
            }
            return array('lists' => $sales_info);
        }else{
            return array('lists' => array());
        }
    }

    /**
     * 销售发货明细
     *
     * @return array
     * @author CP
     * @version 4.3.9 2021-08-14T10:54:17+08:00
     **/
    public function getDeliveryList($filter, $offset = 0, $limit = 100)
    {
        $saleDelivMdl = app::get('sales')->model('delivery_order');
        $saleDlivItemMdl = app::get('sales')->model('delivery_order_item');
        $branchMdl = app::get('ome')->model('branch');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');

        $count = $saleDelivMdl->count($filter);

        if (!$count) {
            return ['lists' => [], 'count' => '0'];
        }

        $saleDelivList = $saleDelivMdl->getList('*', $filter, $offset, $limit);
        $dlyIds = array_column($saleDelivList, 'delivery_id');
        $itemTmpList = $saleDlivItemMdl->getList('*', [
            'delivery_id' => $dlyIds
        ]);

        // 查询店铺
        $shopList = app::get('ome')->model('shop')->getList('shop_id,name,delivery_mode', [
            'shop_id'=>array_column($saleDelivList, 'shop_id')
        ]);
        $shopList = array_column($shopList, null, 'shop_id');
        $useLifeLog = app::get('console')->model('useful_life_log')->getList('original_id,product_id,num,bn,normal_defective,product_time,expire_time,purchase_code,produce_code', array('sourcetb'=>'delivery', 'original_id'=>$dlyIds));
            $useLifeLog_arr = array();
            foreach ($useLifeLog as $k => $useLife){
                if($useLifeLog_arr[$useLife['original_id']][$useLife['product_id']][$useLife['normal_defective']][$useLife['purchase_code']]) {
                    $useLifeLog_arr[$useLife['original_id']][$useLife['product_id']][$useLife['normal_defective']][$useLife['purchase_code']]['num'] += $useLife['num'];
                    continue;
                }
                $useLife['product_time'] = $useLife['product_time'] ? date('Y-m-d H:i:s',$useLife['product_time']) : '';
                $useLife['expire_time'] = $useLife['expire_time'] ? date('Y-m-d H:i:s',$useLife['expire_time']) : '';
                $useLifeLog_arr[$useLife['original_id']][$useLife['product_id']][$useLife['normal_defective']][$useLife['purchase_code']] = $useLife;
            }
        //获取开票税率
        $productIds = array_column($itemTmpList, 'product_id');
        $materialList = $basicMaterialObj->getList('bm_id,tax_rate', array('bm_id'=>$productIds));
        $materialList = array_column($materialList, null, 'bm_id');
        $materialExtList = $basicMaterialExtObj->getList('bm_id,retail_price', array('bm_id'=>$productIds));
        $materialExtList = array_column($materialExtList, null, 'bm_id');
        //获取订单支付方式
        $orderIds = array_column($itemTmpList, 'order_id');
        $orders = app::get('ome')->model('orders')->getList('order_id,platform_order_bn,payment,relate_order_bn,order_type,mark_text',['order_id'=>$orderIds]);
        $orders = array_column($orders, null, 'order_id');
        $sales_arr = app::get('ome')->model('sales')->getList('sale_id,order_id,sale_bn',array('order_id'=>$orderIds),0,-1);
        $sales_arr = array_column($sales_arr, null, 'order_id');
        //子单号
        $orderObjId = app::get('ome')->model('order_objects')->getList('obj_id,oid',['order_id'=>$orderIds]);
        $orderObjId = array_column($orderObjId, null, 'obj_id');
        $orderPmt = [];
        foreach(app::get('ome')->model('order_pmt')->getList('order_id,pmt_describe',array('order_id'=>$orderIds)) as $v) {
            $orderPmt[$v['order_id']][] = $v['pmt_describe'];
        }

        //items
        $obj = kernel::single('openapi_api_function_v1_sales');
        $doiPropsRows = app::get('sales')->model('delivery_order_item_props')->getList('*', ['item_detail_id'=>array_column($itemTmpList, 'id')]);
        $doiPropsItems = [];
        foreach ($doiPropsRows as $k => $v) {
            $doiPropsItems[$v['item_detail_id']][] = $v;
        }
        $saleDelivItemList = [];
        foreach ($itemTmpList as $key => $value)
        {
            $product_id = $value['product_id'];
            
            //开票税率
            $cost_tax = 0;
            $materialInfo = $materialList[$product_id];
            if($materialInfo['tax_rate'] > 0){
                $cost_tax = $materialInfo['tax_rate'] / 100;
            }
            
            //data
            $saleDelivItemList[$value['delivery_id']][] = [
                'item_id' => $value['id'],
                'shop_bn' => $value['shop_bn'],
                'shop_type' => $value['shop_type'],
                'branch_bn' => $value['branch_bn'],
                'order_bn' => $value['order_bn'],
                'platform_order_bn' => $orders[$value['order_id']]['platform_order_bn'],
                'relate_order_bn' => $orders[$value['order_id']]['relate_order_bn'],
                'oid' => $orderObjId[$value['order_obj_id']]['oid'],
                'order_type' => app::get('ome')->model('orders')->schema['columns']['order_type']['type'][$orders[$value['order_id']]['order_type']],
                'sale_bn' => $sales_arr[$value['order_id']]['sale_bn'],
                'pmt_title' => is_array($orderPmt[$value['order_id']]) ? implode(',', $orderPmt[$value['order_id']]) : '',
                'delivery_bn' => $value['delivery_bn'],
                'obj_type' => $value['obj_type'],
                'sales_material_bn' => $value['sales_material_bn'],
                'bn' => $value['bn'],
                'name' => $obj->charFilter($value['name']),
                'retail_price' => $materialExtList[$product_id] ? $materialExtList[$product_id]['retail_price'] : 0,
                'price' => $value['price'],
                'nums' => $value['nums'],
                'pmt_price' => $value['pmt_price'],
                'sale_price' => $value['sale_price'],
                'apportion_pmt' => $value['apportion_pmt'],
                'sales_amount' => $value['sales_amount'],
                'platform_amount' => $value['platform_amount'],
                'settlement_amount' => $value['settlement_amount'],
                'actually_amount' => $value['actually_amount'],
                'platform_pay_amount' => $value['platform_pay_amount'],
                'delivery_time' => date('Y-m-d H:i:s', $value['delivery_time']),
                'order_create_time' => date('Y-m-d H:i:s', $value['order_create_time']),
                'order_pay_time' => date('Y-m-d H:i:s', $value['order_pay_time']),
                'sale_time' => date('Y-m-d H:i:s', $value['sale_time']),
                's_type' => $value['s_type'],
                'order_item_id'=>$value['order_item_id'],
                'cost_tax' => $cost_tax,
                'pay_method' => (string) $orders[$value['order_id']]['payment'],
                'mark_text' => $this->getAllMarkText($orders[$value['order_id']]['mark_text']),
                'batchs' => $this->_getBatchs($useLifeLog_arr, $value),
                'props' => $this->_getProps($doiPropsItems[$value['id']]),
            ];
        }
        unset($itemTmpList);

        $branchList = $branchMdl->getList('branch_id,branch_bn', ['branch_id' => array_column($saleDelivList, 'branch_id')]);
        $branchList = array_column($branchList, null, 'branch_id');


        $lists = [];
        foreach ($saleDelivList as $l) {
            $items =  $saleDelivItemList[$l['delivery_id']];
            $branch = $branchList[$l['branch_id']];
            $lists[] = [
                'delivery_bn' => $l['delivery_bn'],
                'shop_type' => $l['shop_type'],
                'shop_name' => (string)$shopList[$l['shop_id']]['name'],
                'delivery_time' => date('Y-m-d H:i:s',$l['delivery_time']),
                'sale_time' => date('Y-m-d H:i:s', $l['sale_time']),
                'logi_name' => (string)$l['logi_name'],
                'logi_no' => (string)$l['logi_no'],
                'ship_name' => (string)$l['ship_name'],
                'ship_mobile' => (string)$l['ship_mobile'],
                'ship_email' => (string)$l['ship_email'],
                'ship_province' => (string)$l['ship_province'],
                'ship_city' => (string)$l['ship_city'],
                'ship_district' => (string)$l['ship_district'],
                'ship_addr' => (string)$l['ship_addr'],
                'ship_zip' => (string)$l['ship_zip'],
                'branch_bn' => $branch['branch_bn'],
                'delivery_mode' => (string)$shopList[$l['shop_id']]['delivery_mode'],
                'items' => (array) $items,
            ];
        }
        unset($saleDelivList);

        return ['lists' => $lists, 'count' => $count];
    }

    protected function _getBatchs(&$useLifeLog_arr, $sale_item)
    {
        $product_id = intval($sale_item['product_id']);
        $original_id = intval($sale_item['delivery_id']);
        $batchs = array();
        if($sale_item['nums'] > 0) {
            if($useLifeLog_arr[$original_id][$product_id]) {
                $num = $sale_item['nums'];
                foreach($useLifeLog_arr[$original_id][$product_id] as $nd => $ndv) {
                    foreach ($ndv as $ulk => $useLife) {
                        if($num < 1) {
                            break;
                        }
                        if($useLife['num'] >= $num) {
                            $tmpNum = $num;
                        } else {
                            $tmpNum = $useLife['num'];
                        }
                        $num -= $tmpNum;
                        $useLifeLog_arr[$original_id][$product_id][$nd][$ulk]['num'] -= $tmpNum;
                        if($useLifeLog_arr[$original_id][$product_id][$nd][$ulk]['num'] < 1) {
                            unset($useLifeLog_arr[$original_id][$product_id][$nd][$ulk]);
                        }
                        if(empty($useLifeLog_arr[$original_id][$product_id][$nd])) {
                            unset($useLifeLog_arr[$original_id][$product_id][$nd]);
                        }
                        $useLife['num'] = $tmpNum;
                        $batchs[] = array(
                            'bn' => $useLife['bn'],
                            'nums' => $useLife['num'],
                            'batch_code' => $useLife['purchase_code'],
                            'product_date' => $useLife['product_time'],
                            'expire_date' => $useLife['expire_time'],
                            'produce_code' => $useLife['produce_code'],
                            'inventory_type' => $useLife['normal_defective'] == 'normal' ? 'ZP' : 'CC',
                        );
                    }
                }
            }
        }
        return $batchs;
    }
    
    protected function _getProps($doiPropsItems)
    {
        $propsItems = [];
        if($doiPropsItems) {
            foreach ($doiPropsItems as $doiPropsItem) {
                $propsItems[$doiPropsItem['props_col']] = $doiPropsItem['props_value'];
            }
        }
        return $propsItems;
    }
    /**
     * 获取基础物料信息
     * 
     * @param array $productIds
     * @return array
     */
    public function _getBasicMaterial($productIds)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $codebaseMdl = app::get('material')->model('codebase');
        
        $codeBaseLib = kernel::single('material_codebase');
        
        //主信息
        $mainList = $basicMaterialObj->getList('bm_id,type,cat_id,cat_path,tax_rate', array('bm_id'=>$productIds));
        $mainList = array_column($mainList, null, 'bm_id');
        
        //扩展信息
        $extList = $basicMaterialExtObj->getList('bm_id,brand_id,retail_price,specifications', array('bm_id'=>$productIds));
        $extList = array_column($extList, null, 'bm_id');
        
        //条形码
        $codType = $codeBaseLib->getBarcodeType();
        $barcodeList = $codebaseMdl->getList('*', array('bm_id'=>$productIds, 'type'=>$codType));
        $barcodeList = array_column($barcodeList, null, 'bm_id');
        
        //品牌
        $brandList = array();
        $brandIds = array_unique(array_column($extList, 'brand_id'));
        if($brandIds){
            $brandMdl = app::get('ome')->model('brand');
            $brandList = $brandMdl->getList('brand_id,brand_code,brand_name', array('brand_id'=>$brandIds));
            $brandList = array_column($brandList, null, 'brand_id');
        }
        
        //商品分类
        $catList = array();
        $catIds = array_unique(array_column($mainList, 'cat_id'));
        if($catIds){
            $catList = app::get('material')->model('basic_material_cat')->getList('cat_id,cat_path,cat_name,cat_code', array('cat_id'=>$catIds));
            $catList = array_column($catList, null, 'cat_id');
        }
        
        //list
        $basicMaterialList = array();
        foreach((array)$mainList as $key => $val)
        {
            $bm_id = $val['bm_id'];
            $extInfo = $extList[$bm_id];
            
            //merge
            $val = array_merge($val, $extInfo);
            
            $brand_id = $val['brand_id'];
            $cat_id = $val['cat_id'];
            
            //other
            $val['barcode'] = $barcodeList[$bm_id]['code'];
            $val['brand_code'] = $brandList[$brand_id]['brand_code'];
            $val['cat_name'] = $catList[$cat_id]['cat_name'];
            
            $basicMaterialList[$bm_id] = $val;
        }
        
        return $basicMaterialList;
    }

    /**
     * 获取JIT销售单
     *
     *
     * @return array
     **/
    public function getGxList($filter, $offset = 0, $limit = 100)
    {
        $jitSaleMdl = app::get('billcenter')->model('sales');
        $count = $jitSaleMdl->count($filter);

        $jitSaleList = $jitSaleMdl->getList('*', $filter, $offset, $limit);
        if (!$jitSaleList){
            return [
                'lists' => [],
                'count' => $count,
            ];
        }

        $jitSaleList = array_column($jitSaleList, null, 'id');
        $logiCode = array_unique(array_column($jitSaleList, 'logi_code'));
        $carrier = app::get('console')->model('carrier')->getList('carrier_code,carrier_name', ['carrier_code'=>$logiCode]);
        $carrier = array_column($carrier, 'carrier_name', 'carrier_code');
        foreach ($jitSaleList as $k => $v) {
            $jitSaleList[$k]['logi_name'] = $carrier[$v['logi_code']] ?? '';
        }
        $items = app::get('billcenter')->model('sales_items')->getList('*', [
            'sale_id' => array_column($jitSaleList, 'id'),
        ]);
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $bcExt = $basicMaterialExtObj->getList('bm_id,retail_price', ['bm_id'=>array_unique(array_column($items, 'bm_id'))]);
        $bcExt = array_column($bcExt, null, 'bm_id');
        //items
        foreach ($items as $k => $item) {
            $item['retail_price'] = $bcExt[$item['bm_id']] ? $bcExt[$item['bm_id']]['retail_price'] : 0;
            $jitSaleList[$item['sale_id']]['items'][] = $item;
        }

        return [
            'lists' => array_values($jitSaleList),
            'count' => $count,
        ];
    }
}
