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
 * 福袋组合(订单编辑时处理的实现方法)
 */
class ome_order_edit_luckybag
{
    protected $config = array(
        'app'       => 'ome',
        'html'      => 'admin/order/edit/lkb_edit.html',
        'js_count'  => 'total_lkb()',
        'is_add'    => true,
        'add_title' => '添加福袋类销售物料',
        'add_id'    => 'add_lkb_product',
    );
    
    /**
     * 获取捆绑商品类型页面配置
     * @return Araay conf
     */
    public function get_config()
    {
        return $this->config;
    }
    
    /**
     * 处理订单编辑时提交的数据
     * @access public
     * @param array $data 订单编辑的数据  //POST
     */
    public function process($data)
    {
        //check
        if (!$data['lkb']) return false;
        
        //model
        $oOrderItm  = app::get('ome')->model("order_items");
        $oOrderObj  = app::get('ome')->model("order_objects");
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicExtMdl = app::get('material')->model('basic_material_ext');
        
        $salesMLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        $combineLib = kernel::single('material_fukubukuro_combine');
        
        $obj = $data['lkb']['obj'];
        $onum = $data['lkb']['num'];
        $oprice = $data['lkb']['price'];
        $obj_pmt_price = $data['lkb']['obj_pmt_price'];
        $new_obj_pmt_price  = $data['lkb']['new_obj_pmt_price'];
        $newLkbONum = $data['lkb']['lkbonum'];//新增Lkb福袋
        $shop_id = $data['shop_id'];
        $order_id = $data['order_id'];
        
        $tmp_obj = array();
        $new_obj = array();
        
        $is_order_change = false;
        $is_goods_modify = false;
        $total = $total_pmt_goods = 0;
        $err = '';
        
        //福袋商品信息
        $branchBatchList = [];
        if ($obj && is_array($obj)){
            foreach ($obj as $k =>$v){
                $tmp_obj[$k] = array('obj_id'=>$k,'pmt_price'=>$obj_pmt_price[$k]);
                $obj_ = $oOrderObj->dump($k);
                if (!$obj_) continue;
                
                if ($obj_['quantity'] != $onum[$k] || $obj_['pmt_price'] != $obj_pmt_price[$k]){
                    $is_order_change = true;
                    $is_goods_modify = true;
                }
                
                if (isset($onum[$k])){
                    $tmp_amount = intval($onum[$k])*$oprice[$k];
                    $total += $tmp_amount;
                    $total_pmt_goods += $obj_pmt_price[$k];
                    if ($obj_['amount'] != $tmp_amount){
                        $is_order_change = true;
                        $is_goods_modify = true;
                    }
                    //object层
                    $tmp_obj[$k]['obj_id'] = $k;
                    $tmp_obj[$k]['goods_id'] = $obj_['goods_id'];
                    $tmp_obj[$k]['amount'] = $tmp_amount;
                    $tmp_obj[$k]['quantity'] = intval($onum[$k]);
                    $tmp_obj[$k]['price'] = $oprice[$k];
                    $tmp_obj[$k]['amount']  = $tmp_obj[$k]['quantity'] * $tmp_obj[$k]['price'];
                    $tmp_obj[$k]['delete'] = 'false';
                    foreach ($v as $n){
                        $oi = $oOrderItm->dump($n);
                        $quantity = intval($oi['quantity'] / $obj_['quantity'])*intval($onum[$k]);
                        $sale_price = $quantity * $oi['price'];
                        if ($oi['quantity'] != $quantity){
                            $t_n = $quantity - $oi['quantity'];
                            if ($oi['delete'] == 'true'){ //原删除状态的福袋点击恢复了
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
                        }elseif ($oi['delete'] == 'true'){ //原删除状态的福袋点击恢复了
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
                        //items层
                        $tmp_obj[$k]['items'][$n]['item_id'] = $n;
                        $tmp_obj[$k]['items'][$n]['product_id'] = $oi['product_id'];
                        $tmp_obj[$k]['items'][$n]['quantity'] = $quantity;
                        $tmp_obj[$k]['items'][$n]['price'] = $oi['price'];
                        $tmp_obj[$k]['items'][$n]['delete'] = 'false';
                        $tmp_obj[$k]['items'][$n]['pmt_price'] = 0;
                        $tmp_obj[$k]['items'][$n]['sale_price'] = $sale_price;
                    }
                    
                    $tmp_obj[$k]['sale_price']  = $tmp_obj[$k]['amount'] - $obj_pmt_price[$k];
                    $tmp_obj[$k]['divide_order_fee'] = sprintf('%.2f', $obj_['divide_order_fee'] * intval($onum[$k]) / $obj_['quantity']);
                    $tmp_obj[$k]['settlement_amount'] = sprintf('%.2f', $obj_['settlement_amount'] * intval($onum[$k]) / $obj_['quantity']);
                    $tmp_obj[$k]['actually_amount'] = sprintf('%.2f', $obj_['actually_amount'] * intval($onum[$k]) / $obj_['quantity']);
                    $tmp_obj[$k]['platform_pay_amount'] = sprintf('%.2f', $tmp_obj[$k]['divide_order_fee'] - $tmp_obj[$k]['actually_amount']);
                    $tmp_obj[$k]['platform_amount'] = sprintf('%.2f', $tmp_obj[$k]['settlement_amount'] - $tmp_obj[$k]['divide_order_fee']);
                    $tmp_obj[$k]['part_mjz_discount'] = sprintf('%.2f', $tmp_obj[$k]['sale_price'] - $tmp_obj[$k]['divide_order_fee']);
                    $tmp_obj[$k]['oid'] = $obj_['oid'];
                    if ($obj_['sale_price'] != $tmp_obj[$k]['sale_price']){
                        $is_order_change = true;
                        $is_goods_modify = true;
                    }
                }else {
                    foreach ($v as $n){
                        $oi = $oOrderItm->dump($n);
                        if ($oi['delete'] == 'false'){ //原没有删除的 现在点击了删除
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
        }

        $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
        $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
        
        //新增捆绑商品
        if ($newLkbONum){
            $newLkbOPr = $data['lkb']['lkbopr'];
            $newLkbINum = $data['lkb']['lkbnum'];
            $newLkbIPr = $data['lkb']['lkbipr'];
            $is_order_change = true;
            $is_goods_modify = true;
            $branchBatchList = [];
            foreach ($newLkbONum as $key => $n)
            {
                if ($data['do_action'] != 2){
                    if ($n < 1 || $n > 499999){
                        trigger_error('数量必须大于1且小于499999', E_USER_ERROR);
                    }
                }
                
                $pmt_price = $new_obj_pmt_price[$key];
                $salesMInfo = $salesMLib->getSalesMById($shop_id,$key);
                
                //check
                if(empty($salesMInfo)){
                    continue;
                }
                
                if($salesMInfo['sales_material_type'] != 7){
                    continue;
                }
    
                if(!isset($newLkbIPr[$key])){
                    continue;
                }
                
                //items
                $tmp = array();
                $amount = 0;
                $weight = 0;
                foreach($newLkbIPr[$key] as $key_bm_id => $var_bm_price)
                {
                    $rs_ba = $basicMaterialObj->dump(array("bm_id"=>$key_bm_id),"bm_id,material_name,material_bn");
                    
                    $bm_ids = array_column($rs_ba, 'bm_id');
                    
                    $rs_ba_ext = $basicExtMdl->dump(array("bm_id"=>$bm_ids),"weight");
                    
                    $item_quantity = intval($newLkbINum[$key][$key_bm_id]);
                    $item_amount = $var_bm_price * $item_quantity;
                    $item_sale_price = $var_bm_price * $item_quantity;
                    $item_pmt_price = $item_amount - $item_sale_price;
                    $item_pmt_price = ($item_pmt_price > 0 ? $item_pmt_price : 0);
                    
                    //order_item
                    $tmp['items'][] = array(
                        'order_id' => $order_id,
                        'product_id' => $key_bm_id,
                        'bn' => $rs_ba['material_bn'],
                        'name' => $rs_ba['material_name'],
                        'price' => $var_bm_price,
                        'pmt_price' => $item_pmt_price, //订单行明细优惠小计
                        'amount' => $item_amount,
                        'sale_price'=> $item_sale_price,
                        'quantity' => $item_quantity,
                        'sendnum' => 0,
                        'item_type' => 'lkb',
                        'luckybag_id' => 0, //福袋组合ID
                    );
                    
                    $amount += $var_bm_price*$newLkbINum[$key][$key_bm_id];
                    $weight += $rs_ba_ext['weight']*$newLkbINum[$key][$key_bm_id];
                                        
                    $freezeData = [];
                    $freezeData['bm_id'] = $key_bm_id;
                    $freezeData['bm_id'] = $salesMInfo['sm_id'];
                    $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                    $freezeData['bill_type'] = 0;
                    $freezeData['obj_id'] = $order_id;
                    $freezeData['shop_id'] = $shop_id;
                    $freezeData['branch_id'] = 0;
                    $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                    $freezeData['num'] = $newLkbINum[$key][$key_bm_id];
                    $freezeData['obj_bn'] = $data['order_bn'];

                    $branchBatchList[] = $freezeData;
                }
                
                //order_object
                $tmp['order_id'] = $order_id;
                $tmp['obj_type'] = 'lkb';
                $tmp['obj_alias'] = '福袋类';
                $tmp['goods_id'] = $salesMInfo['sm_id'];
                $tmp['bn'] = $salesMInfo['sales_material_bn'];
                $tmp['name'] = $salesMInfo['sales_material_name'];
                $tmp['price'] = $newLkbOPr[$key];
                $tmp['quantity'] = $n;
                $tmp['amount'] = $amount;
                $tmp['weight'] = $weight;
                $tmp['sale_price'] = $amount - $pmt_price;
                $tmp['pmt_price'] = $pmt_price;
                
                $new_obj[] = $tmp;
                $total += $tmp['amount'];
                $total_pmt_goods += $pmt_price;
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
    public function is_null($data)
    {
        if (!$data['lkb']) return true;
        $onum = $data['lkb']['num'];
        
        //新增lkb福袋
        $newLkbONum = $data['lkb']['lkbonum'];
        
        if (empty($onum) && empty($newLkbONum)) return true;
        
        return false;
    }
    
    /**
     * 校验订单编辑时提交的数据
     * @param array $data 订单编辑的数据  //POST
     */
    public function valid($data)
    {
        if (!$data['lkb']) return true;
        
        $obj = $data['lkb']['obj'];
        $onum = $data['lkb']['num'];
        
        //新增lkb福袋
        $newLkbONum = $data['lkb']['lkbonum'];
        $newLkbINum = $data['lkb']['lkbnum'];
        
        //福袋商品信息
        if ($obj && is_array($obj)){
            foreach ($obj as $k =>$v)
            {
                if (isset($onum[$k])){
                    if ($onum[$k] < 1  || $onum[$k] > 99999){
                        $rs = array(
                            'flag' => false,
                            'msg' => "福袋商品数量必须大于1且小于99999",
                        );
                        return $rs;
                    }
                }
            }
        }
        
        //新增福袋商品
        if ($newLkbONum){
            foreach ($newLkbONum as $key => $n)
            {
                if ($n < 1 || $n > 99999){
                    $rs = array(
                        'flag' => false,
                        'msg'  => "福袋商品数量必须大于1且小于99999",
                    );
                    return $rs;
                }
                
                foreach ($newLkbINum[$key] as $k => $v)
                {
                    if ($v < 1 || $v > 99999){
                        $rs = array(
                            'flag' => false,
                            'msg'  => "福袋商品数量必须大于1且小于99999",
                        );
                        return $rs;
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
    public function is_edit_product($data)
    {
        if (!$data['lkb']) return false;
        
        $oOrderItm = app::get('ome')->model("order_items");
        $oOrderObj = app::get('ome')->model("order_objects");
        
        $obj = $data['lkb']['obj'];
        $onum = $data['lkb']['num'];
        $oprice = $data['lkb']['price'];
        $obj_pmt_price = $data['lkb']['obj_pmt_price'];
        
        //新增Lkb福袋
        $newLkbONum = $data['lkb']['lkbonum'];
        
        //福袋商品信息
        if ($obj && is_array($obj)){
            foreach ($obj as $k =>$v)
            {
                //如果obj_id不存在，跳过
                $obj_ = $oOrderObj->dump(array('obj_id'=>$k));
                if (!$obj_){
                    continue;
                }
                
                //福袋商品数量有变化
                if ($obj_['quantity'] != $onum[$k]){
                    return true;
                }
                
                if (isset($onum[$k])){
                    $tmp_amount = intval($onum[$k])*$oprice[$k];
                    foreach ($v as $n){
                        $oi = $oOrderItm->dump($n);
                        if ($oi['delete'] == 'true'){
                            return true;//恢复删除的商品
                        }
                    }
                    
                    $sale_price = $tmp_amount - $obj_pmt_price[$k];
                    if ($obj_['sale_price'] != $sale_price){
                        return true;//金额有变化
                    }
                }else{
                    foreach ($v as $n){
                        $oi = $oOrderItm->dump($n);
                        if ($oi['delete'] == 'false'){
                            return true;//删除了商品
                        }
                    }
                }
            }
        }
        
        //新增福袋商品
        if ($newLkbONum){
            return true;
        }
        
        return false;
    }
}