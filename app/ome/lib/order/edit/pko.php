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


class ome_order_edit_pko{
    
    protected  $config = array(
        'app'       => 'ome',
        'html'      => 'admin/order/edit/pko_edit.html',
        'js_count'  => 'total_pko()',
        'is_add'    => true,
        'add_title' => '添加多选一类销售物料',
        'add_id'    => 'add_pko_product',
    );
    
    /**
     * 获取捆绑商品类型页面配置
     * @return Araay conf
     */
    public function get_config(){
        return $this->config;
    }
    
    /**
     * 处理订单编辑时提交的数据
     * @access public
     * @param array $data 订单编辑的数据  //POST
     */
    public function process($data){
        if(!$data['pko']){
            return false;
        }
        
        $obj = $data['pko']['obj'];
        $num = $data['pko']['num'];
        
        $price = $data['pko']['price'];
        $obj_pmt_price = $data['pko']['obj_pmt_price'];
        
        $new_num = $data['pko']['newnum'];
        $new_price = $data['pko']['newprice'];
        $new_obj_pmt_price = $data['pko']['new_obj_pmt_price'];
        $order_id = $data['order_id'];
        $shop_id = $data['shop_id'];
        
        $oOrderItm = app::get('ome')->model("order_items");
        $oOrderObj = app::get('ome')->model("order_objects");
        
        $salesMLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        $tmp_obj = array();
        $new_obj = array();
        
        $is_order_change = false;
        $is_goods_modify = false;
        $total = $total_pmt_goods = 0;
        
        if ($obj && is_array($obj)){
            $branchBatchList = [];
            foreach ($obj as $k => $v){
                $tmp_obj[$k] = array('obj_id'=>$k,'pmt_price'=>$obj_pmt_price[$k]);
                //如果obj_id不存在，跳过
                $objInfo = $oOrderObj->dump($k);
                if(!$objInfo){
                    continue;
                }
                //如果items信息不存在，跳过
                $itemInfo = $oOrderItm->getList("*",array('obj_id'=>$k));
                if(!$itemInfo){
                    continue;
                }
                // 检查是否有oid限制
                $hasOidLimit = !empty($objInfo['oid']) && $objInfo['oid'] != '-1';
                
                // oid限制：验证数量只能改小
                if ($hasOidLimit && isset($num[$k]) && $num[$k] > $objInfo['quantity']) {
                    trigger_error('oid限制：数量不能大于原数量', E_USER_ERROR);
                }
                
                // oid限制：禁止修改单价和优惠价格
                if ($hasOidLimit) {
                    $price[$k] = $objInfo['price'];
                    $obj_pmt_price[$k] = $objInfo['pmt_price'];
                }
                
                if ($objInfo['quantity'] != $num[$k] || ($objInfo['price'] != $price[$k] && !$hasOidLimit) || ($objInfo['pmt_price'] != $obj_pmt_price[$k] && !$hasOidLimit)){
                    $is_order_change = true;
                    $is_goods_modify = true;
                }
                if(isset($num[$k])){
                    $tmp_amount = intval($num[$k])*$price[$k];
                    $total += $tmp_amount;
                    $total_pmt_goods += $obj_pmt_price[$k];
                    if ($objInfo['amount'] != $tmp_amount){
                        $is_order_change = true;
                        $is_goods_modify = true;
                    }
                $tmp_obj[$k]['obj_id'] = $k;
                $tmp_obj[$k]['goods_id'] = $objInfo['goods_id'];
                $tmp_obj[$k]['amount'] = $tmp_amount;
                $tmp_obj[$k]['quantity'] = intval($num[$k]);
                $tmp_obj[$k]['price'] = $price[$k];
                $tmp_obj[$k]['amount'] = $tmp_obj[$k]['quantity'] * $tmp_obj[$k]['price'];
                $tmp_obj[$k]['delete'] = 'false';
                
                // oid限制：按比例计算销售价和实付
                if ($hasOidLimit && $objInfo['quantity'] > 0) {
                    $ratio = intval($num[$k]) / $objInfo['quantity'];
                    $originalSalePrice = $objInfo['sale_price'];
                    $originalDivideOrderFee = $objInfo['divide_order_fee'];
                    
                    $tmp_obj[$k]['sale_price'] = sprintf('%.2f', $originalSalePrice * $ratio);
                    $tmp_obj[$k]['divide_order_fee'] = sprintf('%.2f', $originalDivideOrderFee * $ratio);
                    
                    // 重新计算商品优惠 = 单价 * 数量 - 销售价
                    $obj_pmt_price[$k] = sprintf('%.2f', $tmp_obj[$k]['amount'] - floatval($tmp_obj[$k]['sale_price']));
                    $total_pmt_goods -= $obj_pmt_price[$k]; // 先减去旧的
                    $total_pmt_goods += floatval($obj_pmt_price[$k]); // 再加上新的
                } else {
                    $tmp_obj[$k]['sale_price']  = $tmp_obj[$k]['amount'] - $obj_pmt_price[$k];
                    $tmp_obj[$k]['divide_order_fee'] = sprintf('%.2f', $objInfo['divide_order_fee'] * intval($num[$k]) / $objInfo['quantity']);
                }
                
                $tmp_obj[$k]['pmt_price'] = $obj_pmt_price[$k];
                $tmp_obj[$k]['settlement_amount'] = sprintf('%.2f', $objInfo['settlement_amount'] * intval($num[$k]) / $objInfo['quantity']);
                $tmp_obj[$k]['actually_amount'] = sprintf('%.2f', $objInfo['actually_amount'] * intval($num[$k]) / $objInfo['quantity']);
                $tmp_obj[$k]['platform_pay_amount'] = sprintf('%.2f', $tmp_obj[$k]['divide_order_fee'] - $tmp_obj[$k]['actually_amount']);
                $tmp_obj[$k]['platform_amount'] = sprintf('%.2f', $tmp_obj[$k]['settlement_amount'] - $tmp_obj[$k]['divide_order_fee']);
                // 均摊优惠 = 销售价 - 实付
                $tmp_obj[$k]['part_mjz_discount'] = sprintf('%.2f', floatval($tmp_obj[$k]['sale_price']) - floatval($tmp_obj[$k]['divide_order_fee']));
                $tmp_obj[$k]['oid'] = $objInfo['oid'];
                    $obj_num_change = intval($num[$k])-$objInfo['quantity']; //填写的数量 - 数据库数量  
                    $total_change_number = abs($obj_num_change);
                    $tmp_item_sale_price = $tmp_item_pmt_price = $tmp_item_amount = 0;
                    foreach($itemInfo as $n){
                        $quantity = $n["nums"] - $n["sendnum"]; //默认
                        if($is_order_change && $is_goods_modify){
                            if ($n['delete'] == 'true'){ //恢复了

                                $freezeData = [];
                                $freezeData['bm_id'] = $n['product_id'];
                                $freezeData['sm_id'] = $objInfo['goods_id'];
                                $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                                $freezeData['bill_type'] = 0;
                                $freezeData['obj_id'] = $order_id;
                                $freezeData['shop_id'] = $shop_id;
                                $freezeData['branch_id'] = 0;
                                $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                                $freezeData['num'] = $quantity;
                                $freezeData['obj_bn'] = $data['order_bn'];
                                $branchBatchList['+'][] = $freezeData;
                            }else{
                                if($total_change_number > 0){
                                    if($obj_num_change < 0){ //减少
                                        if($quantity >= $total_change_number){ //当前items够减

                                            $branchBatchList['-'][] = [
                                                'bm_id'     =>  $n['product_id'],
                                                'sm_id'     =>  $objInfo['goods_id'],
                                                'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                                                'bill_type' =>  0,
                                                'obj_id'    =>  $order_id,
                                                'branch_id' =>  '',
                                                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                                                'num'       =>  $total_change_number,
                                            ];
                                            $quantity = $quantity - $total_change_number;
                                            $total_change_number = 0;
                                        }else{ //当前items不够减

                                            $branchBatchList['-'][] = [
                                                'bm_id'     =>  $n['product_id'],
                                                'sm_id'     =>  $objInfo['goods_id'],
                                                'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                                                'bill_type' =>  0,
                                                'obj_id'    =>  $order_id,
                                                'branch_id' =>  '',
                                                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                                                'num'       =>  $quantity,
                                            ];
                                            $quantity = 0;
                                            $total_change_number = $total_change_number - $quantity;
                                        }
                                    }elseif($obj_num_change > 0){ //增加 一般就指定第一个item数据加上即可

                                        $freezeData = [];
                                        $freezeData['bm_id'] = $n['product_id'];
                                        $freezeData['sm_id'] = $objInfo['goods_id'];
                                        $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                                        $freezeData['bill_type'] = 0;
                                        $freezeData['obj_id'] = $order_id;
                                        $freezeData['shop_id'] = $shop_id;
                                        $freezeData['branch_id'] = 0;
                                        $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                                        $freezeData['num'] = $total_change_number;
                                        $freezeData['obj_bn'] = $data['order_bn'];
                                        $branchBatchList['+'][] = $freezeData;

                                        $quantity = $quantity + $total_change_number;
                                        $total_change_number = 0;
                                    }
                                }
                            }
                        }else if ($objInfo['delete'] == 'true'){ //恢复了

                            $freezeData = [];
                            $freezeData['bm_id'] = $n['product_id'];
                            $freezeData['sm_id'] = $objInfo['goods_id'];
                            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                            $freezeData['bill_type'] = 0;
                            $freezeData['obj_id'] = $order_id;
                            $freezeData['shop_id'] = $shop_id;
                            $freezeData['branch_id'] = 0;
                            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                            $freezeData['num'] = $quantity;
                            $freezeData['obj_bn'] = $data['order_bn'];
                            $branchBatchList['+'][] = $freezeData;

                            $is_order_change = true;
                            $is_goods_modify = true;
                        }
                        //item明细数据
                        $tmp_obj[$k]['items'][$n["item_id"]]['item_id'] = $n["item_id"];
                        $tmp_obj[$k]['items'][$n["item_id"]]['product_id'] = $n["product_id"];
                        $tmp_obj[$k]['items'][$n["item_id"]]['quantity'] = $quantity;
                        $tmp_obj[$k]['items'][$n["item_id"]]['price'] = $price[$k]; //直接拿object层单价
                        $tmp_obj[$k]['items'][$n["item_id"]]['delete'] = 'false';
                        $tmp_obj[$k]['items'][$n["item_id"]]['sale_price'] = $price[$k]*$quantity;
                    }
                }else{ //删除走这里
                    foreach($itemInfo as $n){
                        $oi = $oOrderItm->dump($n["item_id"]);
                        if ($oi['delete'] == 'false'){

                            $branchBatchList['-'][] = [
                                'bm_id'     =>  $oi['product_id'],
                                'sm_id'     =>  $objInfo['goods_id'],
                                'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                                'bill_type' =>  0,
                                'obj_id'    =>  $order_id,
                                'branch_id' =>  '',
                                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                                'num'       =>  $oi['quantity'],
                            ];

                            $is_order_change = true;
                            $is_goods_modify = true;
                        }
                        $tmp_obj[$k]['items'][$n["item_id"]]['item_id'] = $n["item_id"];
                        $tmp_obj[$k]['items'][$n["item_id"]]['delete'] = 'true';
                    }
                    $tmp_obj[$k]['delete'] = 'true';
                }
            }

            $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
            $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
        }
        
        //新增商品
        if($new_num){
            $branchBatchList = [];
            $is_order_change = true;
            $is_goods_modify = true;
            foreach($new_num as $key => $n){
                if ($data['do_action'] != 2){
                    if ($n < 1 || $n > 499999){
                        trigger_error('数量必须大于1且小于499999', E_USER_ERROR);
                    }
                }
                $tmp_price = $new_price[$key];
                $pmt_price = $new_obj_pmt_price[$key];
                $amount = $n * $tmp_price;
                $obj_number = $n;
                $salesMInfo = $salesMLib->getSalesMById($shop_id,$key);
                if(empty($salesMInfo)){
                    continue;
                }
                if($salesMInfo['sales_material_type'] == 5){ //多选一
                    $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo["sm_id"],$n,$shop_id);
                    if(empty($basicMInfos)){
                        continue;
                    }
                    $obj_sale_price = $amount - $pmt_price;
                    //item层关联基础物料平摊销售价
                    $tmp = array();
                    $weight = 0;
                    foreach($basicMInfos as $kk => $basicMInfo){
                        $tmp['items'][] = array(
                                'order_id' => $order_id,
                                'product_id' => $basicMInfo['bm_id'],
                                'bn' => $basicMInfo['material_bn'],
                                'name' => $basicMInfo['material_name'],
                                'price' => $tmp_price,
                                'amount' => $basicMInfo['number'] * $tmp_price,
                                'sale_price' => $basicMInfo['number'] * $tmp_price,
                                'quantity' => $basicMInfo['number'],
                                'sendnum' => 0,
                                'item_type' => 'pko'
                        );
                        
                        $weight += $basicMInfo['weight']*$basicMInfo['number'];

                        $freezeData = [];
                        $freezeData['bm_id'] = $basicMInfo['bm_id'];
                        $freezeData['sm_id'] = $salesMInfo['sm_id'];
                        $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                        $freezeData['bill_type'] = 0;
                        $freezeData['obj_id'] = $order_id;
                        $freezeData['shop_id'] = $shop_id;
                        $freezeData['branch_id'] = 0;
                        $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                        $freezeData['num'] = $basicMInfo['number'];
                        $freezeData['obj_bn'] = $data['order_bn'];
                        $branchBatchList[] = $freezeData;
                    }
                    
                    $tmp['order_id'] = $order_id;
                    $tmp['obj_type'] = 'pko';
                    $tmp['obj_alias'] = '多选一类';
                    $tmp['goods_id'] = $salesMInfo['sm_id'];
                    $tmp['bn'] = $salesMInfo['sales_material_bn'];
                    $tmp['name'] = $salesMInfo['sales_material_name'];
                    $tmp['price'] = $tmp_price;
                    $tmp['quantity'] = $obj_number;
                    $tmp['amount'] = $amount;
                    $tmp['weight'] = $weight;
                    $tmp['pmt_price'] = $pmt_price;
                    $tmp['sale_price'] = $obj_sale_price;
                    $new_obj[] = $tmp;
                    $total += $tmp['amount'];
                    $total_pmt_goods += $pmt_price;
                }
            }

            $basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        }
        $rs = array(
            'oobj' => $tmp_obj,
            'nobj' => $new_obj,
            'total' => $total,
            'is_order_change' => $is_order_change,
            'is_goods_modify' => $is_goods_modify,
            'total_pmt_goods' => $total_pmt_goods,
        );
        return $rs;
    }
    
    /**
     * 判断这次提交的数据在处理完成后，是否还存在有正常的数据。
     * @param array $data 订单编辑的数据  //POST
     */
    public function is_null($data){
        if(!$data['pko']){
            return true;
        }
        $obj = $data['pko']['obj'];
        $num = $data['pko']['num'];
        $price = $data['pko']['price'];
        $new_num = $data['pko']['newnum'];
        $new_price = $data['pko']['newprice'];
        if ((empty($num) && empty($new_num)) || (empty($price) && empty($new_price))){
            return true;
        }
        return false;
    }
    
    /**
     * 校验订单编辑时提交的数据
     * @param array $data 订单编辑的数据  //POST
     */
    public function valid($data){
        if(!$data['pko']){
            return true;
        }
        $obj = $data['pko']['obj'];
        $num = $data['pko']['num'];
        $price = $data['pko']['price'];
        $obj_pmt_price = $data['pko']['obj_pmt_price'];
        $new_num = $data['pko']['newnum'];
        $new_price = $data['pko']['newprice'];
        $new_obj_pmt_price = $data['pko']['new_obj_pmt_price'];
        
        if ($obj && is_array($obj))
        foreach ($obj as $k => $v)
        {
                foreach ($v as $n)
                {
                    //过滤已经删除的商品
                    if (!isset($num[$n])){
                        continue;
                    }
                    
                    if ($num[$n] < 1  || $num[$n] > 499999){
                        $rs = array(
                            'flag'  => false,
                            'msg'   => "商品数量必须大于1且小于499999;",
                        );
                        return $rs;
                    }
                    
                    $amount = $num[$n] * $price[$n];
                    if ($obj_pmt_price[$n] < 0  || $obj_pmt_price[$n] > $amount){
                        $rs = array(
                                'flag' => false,
                                'msg' => '优惠价格必须大于等于0,并且不能大于销售金额;',
                                'error_info' => array('name'=>'pko[obj_pmt_price]['.$n.']'),
                        );
                        return $rs;
                    }
                }
        }
        
        //普通商品
        if ($new_num)
        foreach ($new_num as $key => $n)
        {
                if ($n < 1 || $n > 499999){
                    $rs = array(
                            'flag'  => false,
                            'msg'   => "商品数量必须大于1且小于499999!",
                    );
                    return $rs;
                }
                
                //检查输入价格
                $tempPrice = bcmul($new_price[$key], 1, 2);
                if($tempPrice != $new_price[$key]){
                    $rs = array(
                        'flag' => false,
                        'msg' => '多选一：商品单价最多只能输入2位小数;',
                    );
                    return $rs;
                }
                
                $tempPrice = bcmul($new_obj_pmt_price[$key], 1, 2);
                if($tempPrice != $new_obj_pmt_price[$key]){
                    $rs = array(
                        'flag' => false,
                        'msg' => '多选一：商品优惠价格最多只能输入2位小数!',
                    );
                    return $rs;
                }
                
                $amount = $n * $new_price[$key];
                if ($new_obj_pmt_price[$key] < 0  || $new_obj_pmt_price[$key] > $amount){
                    $rs = array(
                            'flag' => false,
                            'msg' => '多选一：优惠价格必须大于等于0,并且不能大于销售金额。',
                            'error_info' => array('name'=>'pko[new_obj_pmt_price]['.$key.']'),
                    );
                    return $rs;
                }
                
        }
        
        return true;
    }
    
    /**
     * 判断订单上商品明细是否被修改
     *
     * @param array $data 订单编辑的数据POST
     * @return bool
     */
    public function is_edit_product($data){
        if(!$data['pko']){
            return false;
        }
        $oOrderObj  = app::get('ome')->model("order_objects");
        $obj = $data['pko']['obj'];
        $num = $data['pko']['num'];
        $price = $data['pko']['price'];
        $obj_pmt_price = $data['pko']['obj_pmt_price'];
        $new_num = $data['pko']['newnum'];
        $order_id = $data['order_id'];
        if ($obj && is_array($obj)){
            foreach ($obj as $k => $v){
                foreach ($v as $n){
                    //如果obj_id不存在，跳过
                    $objInfo = $oOrderObj->dump($n);
                    if (!$objInfo) continue;
                    
                    if (isset($num[$n])){
                        //优惠金额变化,直接返回true
                        if ($objInfo['pmt_price'] != $obj_pmt_price[$n]){
                            return true;
                        }
                        
                        if ($objInfo['quantity'] != $num[$n] || $objInfo['price'] != $price[$n]){
                            return true;//数量有变化
                        }else if ($objInfo['delete'] == 'true'){
                            return true;//恢复删除的商品
                        }
                    }else {
                        if ($objInfo['delete'] == 'false'){
                            return true;//删除了商品
                        }
                    }
                }
            }
        }
        //有新增的商品
        if ($new_num){
            return true;
        }
        return false;
    }
    
}