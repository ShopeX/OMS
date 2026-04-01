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


class ome_order_edit_goods{

    protected  $config = array(
        'app'       => 'ome',
        'html'      => 'admin/order/edit/goods_edit.html',
        'js_count'  => 'total_goods()',
        'is_add'    => true,
        'add_title' => '添加销售物料',
        'add_id'    => 'add_product',
    );

    /**
     * 获取商品类型页面配置
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
        if (!$data['goods']) return false;

        $obj        = $data['goods']['obj'];
        $num        = $data['goods']['num'];
        $price      = $data['goods']['price'];
        $obj_pmt_price  = $data['goods']['obj_pmt_price'];

        $new_num    = $data['goods']['newnum'];
        $new_price  = $data['goods']['newprice'];
        $new_obj_pmt_price  = $data['goods']['new_obj_pmt_price'];
        $order_id   = $data['order_id'];
        $shop_id   = $data['shop_id'];

        $oOrderItm  = app::get('ome')->model("order_items");
        $oOrderObj  = app::get('ome')->model("order_objects");

        $salesMLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');

        $tmp_obj = array();
        $new_obj = array();

        $is_order_change = false;
        $is_goods_modify = false;

        $total = 0;
        $total_pmt_goods = 0;

        $branchBatchList = [];
        if ($obj && is_array($obj))
        foreach ($obj as $k => $v){
            $tmp_obj[$k] = array('obj_id'=>$k);
            foreach ($v as $n){
                //如果obj_id不存在，跳过
                $objInfo = $oOrderObj->dump($n);
                $itemInfo = $oOrderItm->dump(array('obj_id'=>$n));
                if (!$objInfo) continue;

                if (isset($num[$n])){
                    // 检查是否有oid限制
                    $hasOidLimit = !empty($objInfo['oid']) && $objInfo['oid'] != '-1';
                    
                    if ($data['do_action'] != 2){
                        if ($num[$n] < 1 || $num[$n] > 499999){
                            trigger_error('数量必须大于1且小于499999', E_USER_ERROR);
                        }
                        
                        // oid限制：验证数量只能改小
                        if ($hasOidLimit && $num[$n] > $objInfo['quantity']) {
                            trigger_error('oid限制：数量不能大于原数量', E_USER_ERROR);
                        }
                    }

                    // oid限制：禁止修改单价和优惠价格
                    if ($hasOidLimit) {
                        // 使用原单价和原优惠价格
                        $tmp_price = $objInfo['price'];
                        $pmt_price = $objInfo['pmt_price'];
                    } else {
                        $tmp_price = $price[$n]==0?0:$price[$n];
                        $pmt_price = $obj_pmt_price[$n]==0?0:$obj_pmt_price[$n];
                        
                        //优惠金额变化，那订单和商品就标记为变化
                        if ($objInfo['pmt_price'] != $obj_pmt_price[$n]){
                            $is_order_change = true;
                            $is_goods_modify = true;
                        }
                    }

                    if ($objInfo['quantity'] != $num[$n] || ($objInfo['price'] != $price[$n] && !$hasOidLimit)){
                        $t_n = $num[$n] - $objInfo['quantity'];
                        if ($objInfo['delete'] == 'true'){

                            $freezeData = [];
                            $freezeData['bm_id'] = $itemInfo['product_id'];
                            $freezeData['sm_id'] = $objInfo['goods_id'];
                            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                            $freezeData['bill_type'] = 0;
                            $freezeData['obj_id'] = $order_id;
                            $freezeData['shop_id'] = $shop_id;
                            $freezeData['branch_id'] = 0;
                            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                            $freezeData['num'] = $num[$n];
                            $freezeData['obj_bn'] = $data['order_bn'];
                            $branchBatchList['+'][] = $freezeData;
                        }else {
                            if ($t_n < 0){

                                $branchBatchList['-'][] = [
                                    'bm_id'     =>  $itemInfo['product_id'],
                                    'sm_id'     =>  $objInfo['goods_id'],
                                    'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                                    'bill_type' =>  0,
                                    'obj_id'    =>  $order_id,
                                    'branch_id' =>  '',
                                    'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                                    'num'       =>  abs($t_n),
                                ];
                            }elseif ($t_n > 0){

                                $freezeData = [];
                                $freezeData['bm_id'] = $itemInfo['product_id'];
                                $freezeData['sm_id'] = $objInfo['goods_id'];
                                $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                                $freezeData['bill_type'] = 0;
                                $freezeData['obj_id'] = $order_id;
                                $freezeData['shop_id'] = $shop_id;
                                $freezeData['branch_id'] = 0;
                                $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                                $freezeData['num'] = abs($t_n);
                                $freezeData['obj_bn'] = $data['order_bn'];
                                $branchBatchList['+'][] = $freezeData;
                            }
                        }
                        $is_order_change = true;
                        $is_goods_modify = true;
                    }else if ($objInfo['delete'] == 'true'){

                        $freezeData = [];
                        $freezeData['bm_id'] = $itemInfo['product_id'];
                        $freezeData['sm_id'] = $objInfo['goods_id'];
                        $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                        $freezeData['bill_type'] = 0;
                        $freezeData['obj_id'] = $order_id;
                        $freezeData['shop_id'] = $shop_id;
                        $freezeData['branch_id'] = 0;
                        $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                        $freezeData['num'] = $num[$n];
                        $freezeData['obj_bn'] = $data['order_bn'];
                        $branchBatchList['+'][] = $freezeData;

                        $is_order_change = true;
                        $is_goods_modify = true;
                    }

                    $tmp_item = array (
                        'item_id'   => $itemInfo['item_id'],
                        'product_id' => $itemInfo['product_id'],
                        'quantity'  => $num[$n],
                        'price'     => $tmp_price,
                        'pmt_price'     => $pmt_price,
                        'delete'    => 'false',
                    );
                    $tmp_item['amount'] = $tmp_item['quantity'] * $tmp_item['price'];
                    
                    // oid限制：按比例计算销售价和实付
                    if ($hasOidLimit && $objInfo['quantity'] > 0) {
                        $ratio = $num[$n] / $objInfo['quantity'];
                        $originalSalePrice = $objInfo['sale_price'];
                        $originalDivideOrderFee = $objInfo['divide_order_fee'];
                        
                        $tmp_item['sale_price'] = sprintf('%.2f', $originalSalePrice * $ratio);
                        $tmp_obj[$k]['divide_order_fee'] = sprintf('%.2f', $originalDivideOrderFee * $ratio);
                        
                        // 重新计算商品优惠 = 单价 * 数量 - 销售价
                        $pmt_price = sprintf('%.2f', $tmp_item['amount'] - floatval($tmp_item['sale_price']));
                        $tmp_item['pmt_price'] = $pmt_price;
                    } else {
                        $tmp_item['sale_price'] = $tmp_item['amount'] - $pmt_price;
                        $tmp_obj[$k]['divide_order_fee'] = sprintf('%.2f', $objInfo['divide_order_fee'] * $num[$n] / $objInfo['quantity']);
                    }

                    $total += $tmp_item['amount'];
                    $total_pmt_goods += floatval($pmt_price);

                    $tmp_obj[$k]['amount'] = $tmp_item['amount'];
                    $tmp_obj[$k]['pmt_price'] = $pmt_price;
                    $tmp_obj[$k]['price'] = $tmp_item['price'];
                    $tmp_obj[$k]['quantity'] = $tmp_item['quantity'];
                    $tmp_obj[$k]['sale_price'] = $tmp_item['sale_price'];
                    $tmp_obj[$k]['delete'] = 'false';
                    $tmp_obj[$k]['obj_type'] = 'goods';
                    $tmp_obj[$k]['goods_id'] = $objInfo['goods_id'];
                    $tmp_obj[$k]['settlement_amount'] = sprintf('%.2f', $objInfo['settlement_amount'] * $num[$n] / $objInfo['quantity']);
                    $tmp_obj[$k]['actually_amount'] = sprintf('%.2f', $objInfo['actually_amount'] * $num[$n] / $objInfo['quantity']);
                    $tmp_obj[$k]['platform_pay_amount'] = sprintf('%.2f', $tmp_obj[$k]['divide_order_fee'] - $tmp_obj[$k]['actually_amount']);
                    $tmp_obj[$k]['platform_amount'] = sprintf('%.2f', $tmp_obj[$k]['settlement_amount'] - $tmp_obj[$k]['divide_order_fee']);
                    // 均摊优惠 = 销售价 - 实付
                    $tmp_obj[$k]['part_mjz_discount'] = sprintf('%.2f', floatval($tmp_obj[$k]['sale_price']) - floatval($tmp_obj[$k]['divide_order_fee']));
                    $tmp_obj[$k]['oid'] = $objInfo['oid'];

                }else {
                    if ($objInfo['delete'] == 'false'){

                        $branchBatchList['-'][] = [
                            'bm_id'     =>  $itemInfo['product_id'],
                            'sm_id'     =>  $objInfo['goods_id'],
                            'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                            'bill_type' =>  0,
                            'obj_id'    =>  $order_id,
                            'branch_id' =>  '',
                            'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                            'num'       =>  $itemInfo['quantity'],
                        ];
                        $is_order_change = true;
                        $is_goods_modify = true;
                    }
                    $tmp_item = array (
                        'item_id' => $itemInfo['item_id'],
                        'delete'  => 'true',
                    );

                    $tmp_obj[$k]['delete'] = 'true';
                }
                $tmp_obj[$k]['items'][$itemInfo['item_id']] = $tmp_item;
            }
        }

        $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
        $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);

        //新增商品
        if ($new_num){
            $branchBatchList = [];
            $add_obj = array();
            foreach ($new_num as $key => $n){
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
                if($salesMInfo){
                    $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                    if($basicMInfos){
                        if($salesMInfo['sales_material_type'] == 1){
                            $weight = 0;
                            foreach($basicMInfos as $kk => $basicMInfo){
                                $tmp['items'][0] = array(
                                    'order_id'   => $order_id,
                                    'product_id' => $basicMInfo['bm_id'],
                                    'bn' => $basicMInfo['material_bn'],
                                    'name' => $basicMInfo['material_name'],
                                    'price' => $tmp_price,
                                    'amount' => $amount,
                                    'pmt_price' => $pmt_price, //订单行明细优惠小计
                                    'sale_price'=> $amount - $pmt_price,
                                    'quantity' => $basicMInfo['number']*$obj_number,
                                    'sendnum' => 0,
                                    'item_type' => 'product'
                                );

                                //weight
                                $weight += $basicMInfo['weight']*$basicMInfo['number']*$obj_number;
                            }

                            $tmp['order_id']    = $order_id;
                            $tmp['obj_type']    = 'goods';
                            $tmp['obj_alias']   = '商品';
                            $tmp['goods_id']    = $salesMInfo['sm_id'];
                            $tmp['bn']          = $salesMInfo['sales_material_bn'];
                            $tmp['name']        = $salesMInfo['sales_material_name'];
                            $tmp['price']       = $tmp_price;
                            $tmp['quantity']    = $obj_number;
                            $tmp['amount']      = $amount;
                            $tmp['weight']      = $weight;
                            $tmp['pmt_price']   = $pmt_price;
                            $tmp['sale_price']  = $amount - $pmt_price;

                            $new_obj[] = $tmp;
                            $total += $tmp['amount'];
                            $total_pmt_goods += $pmt_price;

                            $freezeData = [];
                            $freezeData['bm_id'] = $tmp['items'][0]['product_id'];
                            $freezeData['sm_id'] = $salesMInfo['sm_id'];
                            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                            $freezeData['bill_type'] = 0;
                            $freezeData['obj_id'] = $order_id;
                            $freezeData['shop_id'] = $shop_id;
                            $freezeData['branch_id'] = 0;
                            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                            $freezeData['num'] = $tmp['items'][0]['quantity'];
                            $freezeData['obj_bn'] = $data['order_bn'];
                            $branchBatchList[] = $freezeData;
                        }
                    }else{
                        continue;
                    }
                }else{
                    continue;
                }
            }
            $is_order_change = true;
            $is_goods_modify = true;

            $basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        }

        $rs = array(
            'oobj'  => $tmp_obj,
            'nobj'  => $new_obj,
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
        if (!$data['goods']) return true;
        $obj        = $data['goods']['obj'];
        $num        = $data['goods']['num'];
        $price      = $data['goods']['price'];
        $new_num    = $data['goods']['newnum'];
        $new_price  = $data['goods']['newprice'];
        if ((empty($num) && empty($new_num)) || (empty($price) && empty($new_price))) return true;
        return false;
    }

    /**
     * 校验订单编辑时提交的数据
     * @param array $data 订单编辑的数据  //POST
     */
    public function valid($data){
        if (!$data['goods']) return true;

        $obj        = $data['goods']['obj'];
        $num        = $data['goods']['num'];
        $price      = $data['goods']['price'];
        $obj_pmt_price  = $data['goods']['obj_pmt_price'];

        $new_num    = $data['goods']['newnum'];
        $new_price  = $data['goods']['newprice'];
        $new_obj_pmt_price  = $data['goods']['new_obj_pmt_price'];

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
                        'msg' => '普通商品：优惠价格必须大于等于0,并且不能大于销售金额;',
                        'error_info' => array('name'=>'goods[obj_pmt_price]['.$n.']'),
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
            $tempPrice = bcmul(trim($new_price[$key]), 1, 2);
            if($tempPrice != $new_price[$key]){
                $rs = array(
                    'flag' => false,
                    'msg' => '普通商品：单价最多只能输入2位小数;',
                );
                return $rs;
            }
            
            $tempPrice = bcmul(trim($new_obj_pmt_price[$key]), 1, 2);
            if($tempPrice != $new_obj_pmt_price[$key]){
                $rs = array(
                    'flag' => false,
                    'msg' => '普通商品：优惠价格最多只能输入2位小数!',
                );
                return $rs;
            }
            
            //检查金额
            $amount = $n * $new_price[$key];
            if ($new_obj_pmt_price[$key] < 0  || $new_obj_pmt_price[$key] > $amount){
                $rs = array(
                    'flag' => false,
                    'msg' => '普通商品：优惠价格必须大于等于0,并且不能大于销售金额。',
                    'error_info' => array('name'=>'goods[new_obj_pmt_price]['.$key.']'),
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
        
        if (!$data['goods']) return false;
        
        $oOrderObj  = app::get('ome')->model("order_objects");
        
        $obj        = $data['goods']['obj'];
        $num        = $data['goods']['num'];
        $price      = $data['goods']['price'];
        $obj_pmt_price  = $data['goods']['obj_pmt_price'];
        $new_num    = $data['goods']['newnum'];
        $order_id   = $data['order_id'];
        
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