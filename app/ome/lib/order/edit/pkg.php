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


class ome_order_edit_pkg{
    
    protected  $config = array(
        'app'       => 'ome',
        'html'      => 'admin/order/edit/pkg_edit.html',
        'js_count'  => 'total_pkg()',
        'is_add'    => true,
        'add_title' => '添加促销类销售物料',
        'add_id'    => 'add_pkg_product',
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
        if (!$data['pkg']) return false;
        
        $obj        = $data['pkg']['obj'];
        $onum       = $data['pkg']['num'];
        $oprice     = $data['pkg']['price'];
        $obj_pmt_price    = $data['pkg']['obj_pmt_price'];
        $item_pmt_price   = $data['pkg']['item_pmt_price'];
        $num        = $data['pkg']['inum'];
        $price      = $data['pkg']['iprice'];
        $order_id   = $data['order_id'];
        $newPkgONum = $data['pkg']['pkgonum'];//新增PKG
        $newPkgOPr  = $data['pkg']['pkgopr'];
        $new_obj_pmt_price  = $data['pkg']['new_obj_pmt_price'];
        $new_item_pmt_price  = $data['pkg']['new_item_pmt_price'];        
        $newPkgINum = $data['pkg']['pkgnum'];
        $newPkgIPr  = $data['pkg']['pkgpr'];
        $shop_id   = $data['shop_id'];
        
        $oOrderItm  = app::get('ome')->model("order_items");
        $oOrderObj  = app::get('ome')->model("order_objects");

        $salesMLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');

        $tmp_obj = array();
        $new_obj = array();
        
        $is_order_change = false;
        $is_goods_modify = false;
        $total = $total_pmt_goods = 0;
        $branchBatchList = [];
        //捆绑商品信息
        if ($obj && is_array($obj))
        foreach ($obj as $k =>$v){
            $tmp_obj[$k] = array('obj_id'=>$k,'pmt_price'=>$obj_pmt_price[$k]);
            $obj_ = $oOrderObj->dump($k);
           
            if (!$obj_) continue;

            // 检查是否有oid限制
            $hasOidLimit = !empty($obj_['oid']) && $obj_['oid'] != '-1';
            
            // oid限制：验证数量只能改小
            if ($hasOidLimit && isset($onum[$k]) && $onum[$k] > $obj_['quantity']) {
                trigger_error('oid限制：数量不能大于原数量', E_USER_ERROR);
            }
            
            // oid限制：禁止修改单价和优惠价格
            if ($hasOidLimit) {
                $oprice[$k] = $obj_['price'];
                $obj_pmt_price[$k] = $obj_['pmt_price'];
            }
            
            if ($obj_['quantity'] != $onum[$k] || ($obj_['price'] != $oprice[$k] && !$hasOidLimit) || ($obj_['pmt_price'] != $obj_pmt_price[$k] && !$hasOidLimit)){
                $is_order_change = true;
                $is_goods_modify = true;
            }

            if (isset($onum[$k])){
                $tmp_amount = intval($onum[$k])*$oprice[$k];
                $total += $tmp_amount;
                $total_pmt_goods += $obj_pmt_price[$k];

                $oldbasicMInfos = $salesMLib->getBasicMBySalesMId($obj_['goods_id']);

                $oldpmt_price_rate = $salesMLib->calpmtpriceByRate($obj_pmt_price[$k], $oldbasicMInfos);

                if ($obj_['amount'] != $tmp_amount){
                    $is_order_change = true;
                    $is_goods_modify = true;
                }
                $tmp_obj[$k]['obj_id']           = $k;
                $tmp_obj[$k]['goods_id']         = $obj_['goods_id'];
                $tmp_obj[$k]['amount']           = $tmp_amount;
                $tmp_obj[$k]['quantity']         = intval($onum[$k]);
                $tmp_obj[$k]['price']            = $oprice[$k];
                $tmp_obj[$k]['amount']  = $tmp_obj[$k]['quantity'] * $tmp_obj[$k]['price'];
                $tmp_obj[$k]['delete'] = 'false';
                $obj_num_change = intval($onum[$k])-$obj_['quantity'];

                $tmp_item_sale_price = $tmp_item_pmt_price = $tmp_item_amount = 0;
                foreach ($v as $n){
                    $quantity = 0;
                    $oi = $oOrderItm->dump($n);
                    $pmt_price = $oldpmt_price_rate[$oi['bn']] ? $oldpmt_price_rate[$oi['bn']]['rate_price'] : '0';

                    $tmp_item_pmt_price += $pmt_price;
                    //$total_pmt_goods += $pmt_price;
                    $quantity = intval($oi['quantity'] / $obj_['quantity'])*intval($onum[$k]);
                    $item_price = $price[$n]==0 ? $oi['price'] : $price[$n];
                    $sale_price = $quantity * $item_price - $pmt_price;
                    $tmp_item_sale_price += $sale_price;
                    $tmp_item_amount += $quantity * $item_price;

                    if ($oi['quantity'] != $quantity || $oi['price'] != $item_price || $oi['pmt_price'] != $pmt_price){
                        $t_n = $quantity - $oi['quantity'];
                        if ($oi['delete'] == 'true'){

                            $freezeData = [];
                            $freezeData['bm_id'] = $oi['product_id'];
                            $freezeData['sm_id'] = $obj_['goods_id'];
                            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                            $freezeData['bill_type'] = 0;
                            $freezeData['obj_id'] = $order_id;
                            $freezeData['shop_id'] = $shop_id;
                            $freezeData['branch_id'] = 0;
                            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                            $freezeData['num'] = $quantity;
                            $freezeData['obj_bn'] = $data['order_bn'];

                            $branchBatchList['+'][] = $freezeData;
                        }else {
                            if ($t_n < 0){

                                $branchBatchList['-'][] = [
                                    'bm_id'     =>  $oi['product_id'],
                                    'sm_id'     =>  $obj_['goods_id'],
                                    'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                                    'bill_type' =>  0,
                                    'obj_id'    =>  $order_id,
                                    'branch_id' =>  '',
                                    'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                                    'num'       =>  abs($t_n),
                                ];
                            }elseif ($t_n > 0){

                                $freezeData = [];
                                $freezeData['bm_id'] = $oi['product_id'];
                                $freezeData['sm_id'] = $obj_['goods_id'];
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
                    }elseif ($oi['delete'] == 'true'){

                        $freezeData = [];
                        $freezeData['bm_id'] = $oi['product_id'];
                        $freezeData['sm_id'] = $obj_['goods_id'];
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

                    $tmp_obj[$k]['items'][$n]['item_id']    = $n;
                    $tmp_obj[$k]['items'][$n]['product_id'] = $oi['product_id'];
                    $tmp_obj[$k]['items'][$n]['quantity']   = $quantity;
                    $tmp_obj[$k]['items'][$n]['price']      = $price[$n]==0?$oi['price']:$price[$n];
                    $tmp_obj[$k]['items'][$n]['delete']     = 'false';
                    $tmp_obj[$k]['items'][$n]['pmt_price']  = $pmt_price;
                    $tmp_obj[$k]['items'][$n]['sale_price']  = $sale_price;

                }

                // oid限制：按比例计算销售价和实付
                if ($hasOidLimit && $obj_['quantity'] > 0) {
                    $ratio = intval($onum[$k]) / $obj_['quantity'];
                    $originalSalePrice = $obj_['sale_price'];
                    $originalDivideOrderFee = $obj_['divide_order_fee'];
                    
                    $tmp_obj[$k]['sale_price'] = sprintf('%.2f', $originalSalePrice * $ratio);
                    $tmp_obj[$k]['divide_order_fee'] = sprintf('%.2f', $originalDivideOrderFee * $ratio);
                    
                    // 重新计算商品优惠 = 单价 * 数量 - 销售价
                    $obj_pmt_price[$k] = sprintf('%.2f', $tmp_obj[$k]['amount'] - floatval($tmp_obj[$k]['sale_price']));
                    $total_pmt_goods -= $obj_pmt_price[$k]; // 先减去旧的
                    $total_pmt_goods += floatval($obj_pmt_price[$k]); // 再加上新的
                } else {
                    //$tmp_obj[$k]['sale_price']  = $tmp_obj[$k]['amount'] - $obj_pmt_price[$k] - $tmp_item_pmt_price;
                    $tmp_obj[$k]['sale_price']  = $tmp_obj[$k]['amount'] - $obj_pmt_price[$k];
                    $tmp_obj[$k]['divide_order_fee'] = sprintf('%.2f', $obj_['divide_order_fee'] * $onum[$k] / $obj_['quantity']);
                }
                
                $tmp_obj[$k]['settlement_amount'] = sprintf('%.2f', $obj_['settlement_amount'] * $onum[$k] / $obj_['quantity']);
                $tmp_obj[$k]['actually_amount'] = sprintf('%.2f', $obj_['actually_amount'] * $onum[$k] / $obj_['quantity']);
                $tmp_obj[$k]['platform_pay_amount'] = sprintf('%.2f', $tmp_obj[$k]['divide_order_fee'] - $tmp_obj[$k]['actually_amount']);
                $tmp_obj[$k]['platform_amount'] = sprintf('%.2f', $tmp_obj[$k]['settlement_amount'] - $tmp_obj[$k]['divide_order_fee']);
                // 均摊优惠 = 销售价 - 实付
                $tmp_obj[$k]['part_mjz_discount'] = sprintf('%.2f', floatval($tmp_obj[$k]['sale_price']) - floatval($tmp_obj[$k]['divide_order_fee']));
                $tmp_obj[$k]['oid'] = $obj_['oid'];
                if ($obj_['sale_price'] != $tmp_obj[$k]['sale_price']){
                    $is_order_change = true;
                    $is_goods_modify = true;
                }
            }else {
                foreach ($v as $n){
                    $oi = $oOrderItm->dump($n);
                    if ($oi['delete'] == 'false'){

                        $branchBatchList['-'][] = [
                            'bm_id'     =>  $oi['product_id'],
                            'sm_id'     =>  $obj_['goods_id'],
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
                    $tmp_obj[$k]['items'][$n]['item_id'] = $n;
                    $tmp_obj[$k]['items'][$n]['delete']  = 'true';
                }

                $tmp_obj[$k]['delete'] = 'true';
            }
        }

        $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
        $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
        
        //新增捆绑商品
        if ($newPkgONum){
            $branchBatchList = [];
            $is_order_change = true;
            $is_goods_modify = true;
            foreach ($newPkgONum as $key => $n){
                if ($data['do_action'] != 2){
                    if ($n < 1 || $n > 499999){
                        trigger_error('数量必须大于1且小于499999', E_USER_ERROR);
                    }
                }

                $tmp_price = $newPkgOPr[$key];
                $pmt_price = $new_obj_pmt_price[$key];
                $amount = $n * $tmp_price;
                $obj_number = $n;

                $salesMInfo = $salesMLib->getSalesMById($shop_id,$key);
                if($salesMInfo){
                    $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                    if($basicMInfos){
                        if($salesMInfo['sales_material_type'] == 2){
                            $obj_sale_price = $amount - $pmt_price;
                            //item层关联基础物料平摊销售价
                            $salesMLib->calProSaleMPriceByRate($obj_sale_price, $basicMInfos);

                            $pmt_price_rate = $salesMLib->calpmtpriceByRate($pmt_price, $basicMInfos);
                            $tmp = array();
                            $weight = 0;
                            foreach($basicMInfos as $kk => $basicMInfo){
                                $tmp['items'][] = array(
                                    'order_id'   => $order_id,
                                    'product_id' => $basicMInfo['bm_id'],
                                    'bn' => $basicMInfo['material_bn'],
                                    'name' => $basicMInfo['material_name'],
                                    'price' => $basicMInfo['rate_price'] ? bcdiv($basicMInfo['rate_price'], $basicMInfo['number']*$obj_number, 2) : 0.00,
                                    'amount' => $basicMInfo['rate_price'] ? $basicMInfo['rate_price'] : 0.00,
                                    'sale_price'=> $basicMInfo['rate_price'] ? $basicMInfo['rate_price'] : 0.00,
                                    'pmt_price'=>$pmt_price_rate[$basicMInfo['material_bn']] ? $pmt_price_rate[$basicMInfo['material_bn']]['rate_price'] : 0.00,
                                    'quantity' => $basicMInfo['number']*$obj_number,
                                    'sendnum' => 0,
                                    'item_type' => 'pkg'
                                );

                                $weight += $basicMInfo['weight']*$basicMInfo['number']*$obj_number;

                                $freezeData = [];
                                $freezeData['bm_id'] = $basicMInfo['bm_id'];
                                $freezeData['sm_id'] = $salesMInfo['sm_id'];
                                $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                                $freezeData['bill_type'] = 0;
                                $freezeData['obj_id'] = $order_id;
                                $freezeData['shop_id'] = $shop_id;
                                $freezeData['branch_id'] = 0;
                                $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                                $freezeData['num'] = $basicMInfo['number']*$obj_number;
                                $freezeData['obj_bn'] = $data['order_bn'];
                                $branchBatchList[] = $freezeData;
                            }

                            $tmp['order_id']    = $order_id;
                            $tmp['obj_type']    = 'pkg';
                            $tmp['obj_alias']   = '促销类';
                            $tmp['goods_id']    = $salesMInfo['sm_id'];
                            $tmp['bn']          = $salesMInfo['sales_material_bn'];
                            $tmp['name']        = $salesMInfo['sales_material_name'];
                            $tmp['price']       = $tmp_price;
                            $tmp['quantity']    = $obj_number;
                            $tmp['amount']      = $amount;
                            $tmp['weight']      = $weight;
                            $tmp['pmt_price']   = $pmt_price;
                            $tmp['sale_price']  = $obj_sale_price;

                            $new_obj[] = $tmp;
                            $total += $tmp['amount'];
                            $total_pmt_goods += $pmt_price;
                        }
                    }else{
                        continue;
                    }
                }else{
                    continue;
                }
            }
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
        if (!$data['pkg']) return true;
        $obj        = $data['pkg']['obj'];
        $onum       = $data['pkg']['num'];
        $oprice     = $data['pkg']['price'];
        $num        = $data['pkg']['inum'];
        $price      = $data['pkg']['iprice'];
        $newPkgONum = $data['pkg']['pkgonum'];//新增PKG
        $newPkgOPr  = $data['pkg']['pkgopr'];
        $newPkgINum = $data['pkg']['pkgnum'];
        $newPkgIPr  = $data['pkg']['pkgpr'];
        if ((empty($onum) && empty($newPkgONum)) || (empty($oprice) && empty($newPkgOPr))) return true;
        return false;
    }
    
    /**
     * 校验订单编辑时提交的数据
     * @param array $data 订单编辑的数据  //POST
     */
    public function valid($data){
        if (!$data['pkg']) return true;
        
        $obj        = $data['pkg']['obj'];
        $onum       = $data['pkg']['num'];
        $oprice     = $data['pkg']['price'];
        $obj_pmt_price     = $data['pkg']['obj_pmt_price'];
        $item_pmt_price    = $data['pkg']['item_pmt_price'];
        $new_obj_pmt_price    = $data['pkg']['new_obj_pmt_price'];
        $new_item_pmt_price   = $data['pkg']['new_item_pmt_price'];
        $num        = $data['pkg']['inum'];
        $price      = $data['pkg']['iprice'];
        $newPkgONum = $data['pkg']['pkgonum'];//新增PKG
        $newPkgOPr  = $data['pkg']['pkgopr'];
        $newPkgINum = $data['pkg']['pkgnum'];
        $newPkgIPr  = $data['pkg']['pkgpr'];
        
        //捆绑商品信息
        if ($obj && is_array($obj))
        foreach ($obj as $k =>$v)
        {
            //过滤已经删除的商品
            if (!isset($onum[$k])){
                continue;
            }
            
            if ($onum[$k] < 1  || $onum[$k] > 99999){
                $rs = array(
                    'flag' => false,
                    'msg' => "捆绑商品：数量必须大于1且小于99999;",
                );
                return $rs;
            }
            
            foreach ($v as $item_id)
            {
                if ($item_pmt_price[$item_id] < 0){
                    $rs = array(
                        'flag' => false,
                        'msg' => '捆绑商品：优惠价格必须大于等于0,并且不能大于销售金额;',
                        'error_info' => array('name'=>'pkg[item_pmt_price]['.$item_id.']'),
                    );
                    return $rs;
                }
            }
            
            if ($obj_pmt_price[$k] < 0){
                $rs = array(
                    'flag' => false,
                    'msg' => "捆绑商品：优惠金额必须大于等于0",
                    'error_info' => array('name'=>'pkg[obj_pmt_price]['.$k.']'),
                );
                return $rs;
            }
            
            $amount = $onum[$k] * $oprice[$k];
            if ($obj_pmt_price[$k] > $amount){
                $rs = array(
                        'flag' => false,
                        'msg' => '捆绑商品：优惠价格必须大于等于0,并且不能大于销售金额;',
                        'error_info' => array('name'=>'goods[obj_pmt_price]['.$k.']'),
                );
                return $rs;
            }
        }
    
        //新增捆绑商品
        if ($newPkgONum){
        foreach ($newPkgONum as $key => $n)
        {
                if ($n < 1 || $n > 99999){
                    $rs = array(
                        'flag' => false,
                        'msg'  => "捆绑商品：数量必须大于1且小于99999!",
                    );
                    return $rs;
                }
                foreach ($newPkgINum[$key] as $k => $v){
                    if ($v < 1 || $v > 99999){
                        $rs = array(
                            'flag' => false,
                            'msg'  => "捆绑商品：数量必须大于1且小于99999。",
                        );
                        return $rs;
                    }
                }
                
                //检查输入价格
                $tempPrice = bcmul($newPkgOPr[$key], 1, 2);
                if($tempPrice != $newPkgOPr[$key]){
                    $rs = array(
                        'flag' => false,
                        'msg' => '捆绑商品：单价最多只能输入2位小数;',
                    );
                    return $rs;
                }
                
                $tempPrice = bcmul($new_obj_pmt_price[$key], 1, 2);
                if($tempPrice != $new_obj_pmt_price[$key]){
                    $rs = array(
                        'flag' => false,
                        'msg' => '捆绑商品：优惠价格最多只能输入2位小数!',
                    );
                    return $rs;
                }
                
                //检查金额
                $amount = $n * $newPkgOPr[$key];
                if ($new_obj_pmt_price[$key] < 0  || $new_obj_pmt_price[$key] > $amount){
                    $rs = array(
                        'flag' => false,
                        'msg' => '捆绑商品：优惠价格必须大于等于0,并且不能大于销售金额!',
                    );
                    return $rs;
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
        if (!$data['pkg']) return false;
        
        $oOrderItm  = app::get('ome')->model("order_items");
        $oOrderObj  = app::get('ome')->model("order_objects");
        $salesMLib = kernel::single('material_sales_material');
        $obj        = $data['pkg']['obj'];
        $onum       = $data['pkg']['num'];
        $oprice     = $data['pkg']['price'];
        $obj_pmt_price    = $data['pkg']['obj_pmt_price'];
        $item_pmt_price   = $data['pkg']['item_pmt_price'];
        
        $num        = $data['pkg']['inum'];
        $price      = $data['pkg']['iprice'];
        $newPkgONum = $data['pkg']['pkgonum'];//新增PKG
        $order_id   = $data['order_id'];
        
        $tmp_obj = array();
        $total = $total_pmt_goods = 0;
        
        //捆绑商品信息
        if ($obj && is_array($obj)){
            foreach ($obj as $k =>$v){
                
                $tmp_obj[$k] = array('obj_id'=>$k,'pmt_price'=>$obj_pmt_price[$k]);


                
                //如果obj_id不存在，跳过
                $obj_ = $oOrderObj->dump($k);
                if (!$obj_) continue;
                
                $oldbasicMInfos = $salesMLib->getBasicMBySalesMId($obj_['goods_id']);

                $oldpmt_price_rate = $salesMLib->calpmtpriceByRate($obj_pmt_price[$k], $oldbasicMInfos);


                //捆绑商品数量或金额有变化
                if ($obj_['quantity'] != $onum[$k] || $obj_['price'] != $oprice[$k] || $obj_['pmt_price'] != $obj_pmt_price[$k]){
                    return true;
                }
                
                if (isset($onum[$k])){
                    $tmp_amount = intval($onum[$k])*$oprice[$k];
                    
                    //优惠金额变化,直接返回true
                    if ($obj_['amount'] != $tmp_amount){
                        return true;
                    }
                    
                    $tmp_obj[$k]['obj_id']           = $k;
                    $tmp_obj[$k]['amount']           = $tmp_amount;
                    $tmp_obj[$k]['quantity']         = intval($onum[$k]);
                    $tmp_obj[$k]['price']            = $oprice[$k];
                    $tmp_obj[$k]['amount']  = $tmp_obj[$k]['quantity'] * $tmp_obj[$k]['price'];
                    
                    $tmp_item_sale_price = $tmp_item_pmt_price = $tmp_item_amount = 0;
                    foreach ($v as $n){
                        $quantity = 0;
                        $oi = $oOrderItm->dump($n);
                        $pmt_price = $oldpmt_price_rate[$oi['bn']] ? $oldpmt_price_rate[$oi['bn']]['rate_price'] : '0';
                        
                        //$tmp_item_pmt_price += $pmt_price;
                        //$total_pmt_goods += $pmt_price;
                        
                        $quantity = intval($oi['quantity'] / $obj_['quantity'])*intval($onum[$k]);
                        $item_price = $price[$n]==0 ? $oi['price'] : $price[$n];
                        $sale_price = $quantity * $item_price - $pmt_price;
                        $tmp_item_sale_price += $sale_price;
                        $tmp_item_amount += $quantity * $item_price;
                        
                        if ($oi['quantity'] != $quantity || $oi['price'] != $item_price || $oi['pmt_price'] != $pmt_price){
                            return true;//数量有变化
                        }elseif ($oi['delete'] == 'true'){
                            return true;//恢复删除的商品
                        }
                        
                        $tmp_obj[$k]['items'][$n]['item_id']    = $n;
                        $tmp_obj[$k]['items'][$n]['quantity']   = $quantity;
                        $tmp_obj[$k]['items'][$n]['price']      = $price[$n]==0?$oi['price']:$price[$n];
                        $tmp_obj[$k]['items'][$n]['delete']     = 'false';
                        $tmp_obj[$k]['items'][$n]['pmt_price']  = $pmt_price;
                        $tmp_obj[$k]['items'][$n]['sale_price']  = $sale_price;
                    }
                    
                    //$tmp_obj[$k]['sale_price']  = $tmp_obj[$k]['amount'] - $obj_pmt_price[$k] - $tmp_item_pmt_price;
                    $tmp_obj[$k]['sale_price']  = $tmp_obj[$k]['amount'] - $obj_pmt_price[$k];
                    if ($obj_['sale_price'] != $tmp_obj[$k]['sale_price']){
                        return true;//金额有变化
                    }
                }else {
                    foreach ($v as $n){
                        $oi = $oOrderItm->dump($n);
                        if ($oi['delete'] == 'false'){
                            return true;//删除了商品
                        }
                    }
                }
            }
        }
        
        //新增捆绑商品
        if ($newPkgONum){
            return true;
        }
        
        return false;
    }
}