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


class ome_order_edit_gift{
    
    protected  $config = array(
        'app'       => 'ome',
        'html'      => 'admin/order/edit/gift_edit.html',
        'js_count'  => 'total_gift()',
        'is_add'    => true,
        'add_title' => '',
        'add_id'    => '',
    );
    
    /**
     * 获取赠品类型页面配置
     * @return Araay conf
     */
    public function get_config(){
        return $this->config;
    }
    /**
     * 处理订单编辑时提交的数据
     * @access public
     * @param array $data 订单编辑的数据
     */
    public function process($data)
    {
        if (!$data['gift']) return false;

        $obj = $data['gift']['obj'];
        $num = $data['gift']['num'];
        $price = $data['gift']['price'];
        $order_id = $data['order_id'];
        $shop_id = $data['shop_id'];

        $item_num = $data['gift']['item_num'];

        $oOrderItm = app::get('ome')->model("order_items");
        $oOrderObj = app::get('ome')->model("order_objects");

        $salesMLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        $new_num = $data['gift']['newnum'];
        $new_price = $data['gift']['newprice'];
        $new_obj_pmt_price = $data['gift']['new_obj_pmt_price'];

        $tmp_obj = array();
        $new_obj = array();
        $total = 0;
        if ($obj && is_array($obj))
            $branchBatchList = [];
            // $object_id 数组
            foreach ($obj as $k => $v) { 
                $tmp_obj[$k] = array('obj_id' => $k);
                $tmp_amount = 0;
                // n = object_id
                foreach ($v as $n) {
                    $objInfo = $oOrderObj->dump($n);
                    $itemInfos = $oOrderItm->getList('*', array('obj_id' => $n));

                    if (!$objInfo) continue;

                    $tmp_items = array();
                    // 存在数量则进行校验
                    if (isset($num[$n])) {
                        if ($data['do_action'] != 2) {
                            if ($num[$n] < 1 || $num[$n] > 499999) {
                                trigger_error('数量必须大于1且小于499999', E_USER_ERROR);
                            }
                        }

                        // orderItem 循环
                        foreach ($itemInfos as $itemKey => $itemInfo) {
                            $quantity = 0;
                            // orderItem数据库中的nums做为quantity
                            $itemQuantity = $itemInfo['nums'];
                            $objQuantity = $objInfo['quantity'];
                            // 数量计算: 单个item数量 * 新数量
                            $quantity = intval($itemQuantity / $objQuantity) * intval($num[$n]);
                            
                            // 原子订单数量不等于输入数量 或价格不等于
                            if ($objInfo['quantity'] != $num[$n]) {
//                                $t_n = $num[$n] - $objInfo['quantity']; 
//                                $t_n = $quantity - $objInfo['quantity'];
                                $t_n = $quantity - $itemQuantity; // 需要冻结的基础物料冻结数量
                                // 原子订单已删除,则进行冻结
                                if ($objInfo['delete'] == 'true') {

                                    $freezeData = [];
                                    $freezeData['bm_id'] = $itemInfo['product_id'];
                                    $freezeData['sm_id'] = $objInfo['goods_id'];
                                    $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                                    $freezeData['bill_type'] = 0;
                                    $freezeData['obj_id'] = $order_id;
                                    $freezeData['shop_id'] = $shop_id;
                                    $freezeData['branch_id'] = 0;
                                    $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
//                                    $freezeData['num'] = $num[$n]; 
                                    $freezeData['num'] = $quantity; 
//                                    $freezeData['num'] = $itemQuantity;  // $num[$n] 是销售物料的数量， $itemQuantity为基础物料数量
                                    $freezeData['obj_bn'] = $data['order_bn'];
                                    $branchBatchList['+'][] = $freezeData;
                                } else {
                                    if ($t_n < 0) {

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
                                    } elseif ($t_n > 0) {

                                        $freezeData = [];
                                        $freezeData['bm_id'] = $itemInfo['product_id'];
                                        $freezeData['sm_id'] = $objInfo['goods_id'];
                                        $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                                        $freezeData['bill_type'] = 0;
                                        $freezeData['obj_id'] = $order_id;
                                        $freezeData['shop_id'] = $shop_id;
                                        $freezeData['branch_id'] = 0;
                                        $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                                        $freezeData['num'] = abs($t_n); // todo 基础物料冻结数量
                                        $freezeData['obj_bn'] = $data['order_bn'];
                                        $branchBatchList['+'][] = $freezeData;
                                    }
                                }
                                $is_order_change = true;
                                $is_goods_modify = true;
                            } else if ($objInfo['delete'] == 'true') {
                                // 如果原来已删除,则直接按原数量冻结

                                $freezeData = [];
                                $freezeData['bm_id'] = $itemInfo['product_id'];
                                $freezeData['sm_id'] = $objInfo['goods_id'];
                                $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                                $freezeData['bill_type'] = 0;
                                $freezeData['obj_id'] = $order_id;
                                $freezeData['shop_id'] = $shop_id;
                                $freezeData['branch_id'] = 0;
                                $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
//                            $freezeData['num'] = $num[$n];
                                $freezeData['num'] = $quantity;
                                $freezeData['obj_bn'] = $data['order_bn'];
                                $branchBatchList['+'][] = $freezeData;

                                $is_order_change = true;
                                $is_goods_modify = true;
                            }

                            $tmp_item = array(
                                'item_id' => $itemInfo['item_id'],
                                'product_id' => $itemInfo['product_id'],
//                            'quantity' => $num[$n],  // 替换为 $itemQuantity
//                                'quantity' => $itemQuantity,
                                'quantity' => $quantity,
                                'price' => $price[$n] == 0 ? 0 : $price[$n],
                                'delete' => 'false',
                            );
                            $tmp_item['amount'] = $tmp_item['quantity'] * $tmp_item['price'];
                            $tmp_item['sale_price'] = $tmp_item['amount'];

                            $tmp_amount += $tmp_item['amount'];
                            $total += $tmp_amount;

                            $tmp_obj[$k]['amount'] += $tmp_item['amount'];

                            $tmp_obj[$k]['price'] += $tmp_item['price'];
                            
                            $tmp_obj[$k]['sale_price'] += $tmp_item['sale_price'];

                            $tmp_obj[$k]['goods_id'] = $objInfo['goods_id'];

                            $tmp_items[] = $tmp_item;
                        }

                        $tmp_obj[$k]['quantity'] = intval($num[$n]);
                        $tmp_obj[$k]['pmt_price'] = 0.00;
                        $tmp_obj[$k]['delete'] = 'false';
                        $tmp_obj[$k]['divide_order_fee'] = sprintf('%.2f', $objInfo['divide_order_fee'] * intval($num[$n]) / $objInfo['quantity']);
                        $tmp_obj[$k]['settlement_amount'] = sprintf('%.2f', $objInfo['settlement_amount'] * intval($num[$n]) / $objInfo['quantity']);
                        $tmp_obj[$k]['actually_amount'] = sprintf('%.2f', $objInfo['actually_amount'] * intval($num[$n]) / $objInfo['quantity']);
                        $tmp_obj[$k]['platform_pay_amount'] = sprintf('%.2f', $tmp_obj[$k]['divide_order_fee'] - $tmp_obj[$k]['actually_amount']);
                        $tmp_obj[$k]['platform_amount'] = sprintf('%.2f', $tmp_obj[$k]['settlement_amount'] - $tmp_obj[$k]['divide_order_fee']);
                        $tmp_obj[$k]['part_mjz_discount'] = sprintf('%.2f', $tmp_obj[$k]['sale_price'] - $tmp_obj[$k]['divide_order_fee']);
                        $tmp_obj[$k]['oid'] = $objInfo['oid'];

                    } else {
                        // 不存在 则进行删除
                        foreach ($itemInfos as $k1 => $itemInfo) {
                            $itemInfo['quantity'] = $itemInfo['nums'];
                            // 如果原先未删除,则进行解冻
                            if ($objInfo['delete'] == 'false') {

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
                            $tmp_item = array(
                                'item_id' => $itemInfo['item_id'],
                                'delete' => 'true',
                            );

                            $tmp_items[] = $tmp_item;
                        }

                        $tmp_obj[$k]['delete'] = 'true';
                    }

                    $tmp_obj[$k]['items'] = $tmp_items;

                }
                $tmp_obj[$k]['amount'] = $tmp_amount;
            }

            $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
            $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);

        //新增赠品处理
        //新增商品
        if ($new_num) {
            $add_obj = array();
            $batchList = $branchBatchList = [];
            foreach ($new_num as $key => $n) {
                if ($data['do_action'] != 2) {
                    if ($n < 1 || $n > 499999) {
                        trigger_error('数量必须大于1且小于499999', E_USER_ERROR);
                    }
                }

                $tmp_price = $new_price[$key];
                $pmt_price = $new_obj_pmt_price[$key];
                $amount = $n * $tmp_price;
                $obj_number = $n;

                $salesMInfo = $salesMLib->getSalesMById($shop_id, $key);
                if ($salesMInfo) {
                    $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                    if ($basicMInfos) {
                        if ($salesMInfo['sales_material_type'] == 3) {
                            if ( isset($tmp['items'])){
                                unset($tmp['items']);
                            }
                            foreach ($basicMInfos as $kk => $basicMInfo) {
                                $tmp['items'][] = array(
                                    'order_id' => $order_id,
                                    'product_id' => $basicMInfo['bm_id'],
                                    'bn' => $basicMInfo['material_bn'],
                                    'name' => $basicMInfo['material_name'],
                                    'price' => $tmp_price,
                                    'pmt_price' => $pmt_price, //订单行明细优惠小计
                                    'amount' => $amount,
                                    'sale_price' => $amount,
                                    'quantity' => $basicMInfo['number'] * $obj_number,
                                    'sendnum' => 0,
                                    'item_type' => 'gift',
                                );

                                $weight += $basicMInfo['weight'] * $basicMInfo['number'] * $obj_number;

                                //冻结
//                                $basicMStockLib->freeze($tmp['items'][0]['product_id'], $tmp['items'][0]['quantity']);

                                $freezeData = [];
//                                $freezeData['bm_id'] = $tmp['items'][0]['product_id'];
                                $freezeData['bm_id'] = $basicMInfo['bm_id'];
                                $freezeData['sm_id'] = $salesMInfo['sm_id'];
                                $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                                $freezeData['bill_type'] = 0;
                                $freezeData['obj_id'] = $order_id;
                                $freezeData['shop_id'] = $shop_id;
                                $freezeData['branch_id'] = 0;
                                $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
//                                $freezeData['num'] = $tmp['items'][0]['quantity'];
                                $freezeData['num'] = $basicMInfo['number'] * $obj_number;
                                $freezeData['obj_bn'] = $data['order_bn'];
                                $branchBatchList[] = $freezeData;
                            }

                            $tmp['order_id'] = $order_id;
                            $tmp['obj_type'] = 'gift';
                            $tmp['obj_alias'] = '商品';
                            $tmp['goods_id'] = $salesMInfo['sm_id'];
                            $tmp['bn'] = $salesMInfo['sales_material_bn'];
                            $tmp['name'] = $salesMInfo['sales_material_name'];
                            $tmp['price'] = $tmp_price;
                            $tmp['quantity'] = $obj_number;
                            $tmp['amount'] = $amount;
                            $tmp['weight'] = $weight;
                            $tmp['pmt_price'] = $pmt_price;
                            $tmp['sale_price'] = $amount;

                            $new_obj[] = $tmp;
                            $total += $tmp['amount'];
                            $total_pmt_goods += $pmt_price;
                            
                        }
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }
            }
            $is_order_change = true;
            $is_goods_modify = true;

            $basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        }

        $rs = array(
            'oobj' => $tmp_obj,
            'nobj' => $new_obj,
            'total' => $total,
            'is_order_change' => $is_order_change,
            'is_goods_modify' => $is_goods_modify,
        );
        return $rs;
    }


    /**
     * 处理订单编辑时提交的数据
     * @access public
     * @param array $data 订单编辑的数据
     */
    public function processOld($data){
        if (!$data['gift']) return false;
      
        $obj        = $data['gift']['obj'];
        $num        = $data['gift']['num'];
        $price      = $data['gift']['price'];
        $order_id   = $data['order_id'];
        $shop_id   = $data['shop_id'];

        $oOrderItm  = app::get('ome')->model("order_items");
        $oOrderObj  = app::get('ome')->model("order_objects");

        $salesMLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        $new_num    = $data['gift']['newnum'];
        $new_price  = $data['gift']['newprice'];
        $new_obj_pmt_price  = $data['gift']['new_obj_pmt_price'];
        
        $branchBatchList = [];
        $tmp_obj = array();
        $new_obj = array();
        $total = 0;
        if ($obj && is_array($obj))
        foreach ($obj as $k => $v){
            $tmp_obj[$k] = array('obj_id'=>$k);
            $tmp_amount  = 0;
            foreach ($v as $n){
                $objInfo = $oOrderObj->dump($n);
                $itemInfo = $oOrderItm->dump(array('obj_id'=>$n));
                if (!$objInfo) continue;

                if (isset($num[$n])){
                    if ($data['do_action'] != 2){
                        if ($num[$n] < 1 || $num[$n] > 499999){
                            trigger_error('数量必须大于1且小于499999', E_USER_ERROR);
                        }
                    }

                    if ($objInfo['quantity'] != $num[$n] || $objInfo['price'] != $price[$n]){
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
                        'product_id'   => $itemInfo['product_id'],
                        'quantity'  => $num[$n],
                        'price'     => $price[$n]==0?0:$price[$n],
                        'delete'    => 'false',
                    );
                    $tmp_item['amount'] = $tmp_item['quantity'] * $tmp_item['price'];
                    $tmp_item['sale_price'] = $tmp_item['amount'];

                    $tmp_amount += $tmp_item['amount'];
                    $total += $tmp_amount;

                    $tmp_obj[$k]['amount'] = $tmp_item['amount'];
                    $tmp_obj[$k]['pmt_price'] = 0.00;
                    $tmp_obj[$k]['price'] = $tmp_item['price'];
                    $tmp_obj[$k]['quantity'] = $tmp_item['quantity'];
                    $tmp_obj[$k]['sale_price'] = $tmp_item['sale_price'];
                    $tmp_obj[$k]['delete'] = 'false';
                    $tmp_obj[$k]['goods_id'] = $objInfo['goods_id'];
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
                $tmp_obj[$k]['items'][$n] = $tmp_item;
            }
            $tmp_obj[$k]['amount'] = $tmp_amount;
        }

        $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
        $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
        
        //新增赠品处理
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
                        if($salesMInfo['sales_material_type'] == 3){
                            foreach($basicMInfos as $kk => $basicMInfo){
                                $tmp['items'][0] = array(
                                    'order_id'   => $order_id,
                                    'product_id' => $basicMInfo['bm_id'],
                                    'bn' => $basicMInfo['material_bn'],
                                    'name' => $basicMInfo['material_name'],
                                    'price' => $tmp_price,
                                    'amount' => $amount,
                                    'sale_price'=> $amount,
                                    'quantity' => $basicMInfo['number']*$obj_number,
                                    'sendnum' => 0,
                                    'item_type' => 'gift'
                                );

                                $weight += $basicMInfo['weight']*$basicMInfo['number']*$obj_number;
                            }

                            $tmp['order_id']    = $order_id;
                            $tmp['obj_type']    = 'gift';
                            $tmp['obj_alias']   = '商品';
                            $tmp['goods_id']    = $salesMInfo['sm_id'];
                            $tmp['bn']          = $salesMInfo['sales_material_bn'];
                            $tmp['name']        = $salesMInfo['sales_material_name'];
                            $tmp['price']       = $tmp_price;
                            $tmp['quantity']    = $obj_number;
                            $tmp['amount']      = $amount;
                            $tmp['weight']      = $weight;
                            $tmp['pmt_price']   = $pmt_price;
                            $tmp['sale_price']  = $amount;

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
        );
        return $rs;
    }
    
    /**
     * 判断这次提交的数据在处理完成后，是否还存在有正常的数据。
     * @param array $data 订单编辑的数据  //POST
     */
    public function is_null($data){
        if (!$data['gift']) return true;
        $obj    = $data['gift']['obj'];
        $num    = $data['gift']['num'];
        $price  = $data['gift']['price'];
        if (empty($num) || empty($price)) return true;
        return false;
    }
    
    /**
     * 校验订单编辑时提交的数据
     * @param array $data 订单编辑的数据  //POST
     */
    public function valid($data){
        if (!$data['gift']) return true;
        $obj    = $data['gift']['obj'];
        $num    = $data['gift']['num'];
        $price  = $data['gift']['price'];
        
        if ($obj && is_array($obj))
        foreach ($obj as $k => $v){
            foreach ($v as $n){
                if (isset($num[$n])){
                    if ($num[$n] < 1  || $num[$n] > 499999){
                        if ($num[$n] < 1 || $num[$n] > 499999){
                            $rs = array(
                                'flag' => false,
                                'msg'  => "赠品数量必须大于1且小于499999",
                            );
                            return $rs;
                        }
                    }
                }
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
        
        if (!$data['gift']) return false;
        
        $oOrderObj  = app::get('ome')->model("order_objects");
        
        $obj        = $data['gift']['obj'];
        $num        = $data['gift']['num'];
        $price      = $data['gift']['price'];
        $new_num    = $data['gift']['newnum'];
        $order_id   = $data['order_id'];
        
        if ($obj && is_array($obj)){
            foreach ($obj as $k => $v){
                foreach ($v as $n){
                    
                    //如果obj_id不存在，跳过
                    $objInfo = $oOrderObj->dump($n);
                    if (!$objInfo) continue;
                    
                    if (isset($num[$n])){
                        if ($objInfo['quantity'] != $num[$n]){
                            return true;//数量有变化
                        }else if ($objInfo['delete'] == 'true'){
                            return true;//恢复删除的赠品
                        }
                    }else {
                        if ($objInfo['delete'] == 'false'){
                            return true;//删除了赠品
                        }
                    }
                }
            }
        }
        
        //有新增的赠品
        if ($new_num){
            return true;
        }
        
        return false;
    }
}