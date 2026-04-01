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
 * 订单业务逻辑处理
 * @access public
 * @copyright www.shopex.cn 2010.12.16
 * @author ome
 */
class ome_order_order{

    /**
     * 余单撤消
     * @access public
     * @param string $order_id 订单号
     * @param int $reback_price 实际退款金额
     * @param int $revock_price 余单撤消的商品总金额
     * @return 处理成功或者失败
     */
    public function order_revoke($order_id, $reback_price='', $revock_price='')
    {
        $flag   = true;//[拆单]发货单打回成功标志

        $oOrder = app::get('ome')->model("orders");
        $oOrder_items = app::get('ome')->model("order_items");
        
        $oShop = app::get('ome')->model('shop');
        $oOperation_log = app::get('ome')->model('operation_log');
        
        //[拆单]获取订单关联详情_保存快照
        $order_detail   = $oOrder->dump($order_id, "*", array("order_objects"=>array("*",array("order_items"=>array('*')))));
        $order_detail['pmt_order'] = floatval($order_detail['pmt_order']);
        $order_detail['payinfo']['cost_payment'] = floatval($order_detail['payinfo']['cost_payment']);
        
        $order_update = array();
        $order_update['process_status'] = 'remain_cancel';//确认状态：余单撤消
        $order_update['ship_status'] = '1';//发货状态：已发货
        
        //余单撤消的"商品总额"
        $cancel_item_price = $this->get_cancel_item_price($order_detail);
        
        //余单撤消的商品金额
        // if($order_detail['pmt_order']){
            $revock_price = $this->get_cancel_diff_money($order_detail); //有订单优惠,计算商品实付金额
        // }else{
        //     $revock_price = kernel::single('ome_order_func')->order_items_diff_money($order_id);
        // }
        
        //余单撤消的"商品优惠金额"
        $cancel_pmt_price = $this->get_cancel_pmt_price($order_detail);
        
        //余单撤消的"订单优惠分摊"
        $pmt_order_money = $this->get_cancel_pmt_order_price($order_detail);
        
        //订单是否有组合商品部分发货
        /* if (!$this->getPgkDeliveryStatus($order_detail)) {
            return ['result'=>false,'msg'=>'该订单存在组合商品部分发货无法撤销余单'];
        } */
        
        //商品总额 = 原商品总额 - 余单撤消的商品价格
        $order_update['cost_item'] = $order_detail['cost_item'] - $cancel_item_price;
        
        //折扣 = 原折扣 + (撤消的实付金额 - 退款金额)
        //$order_update['discount'] = $order_detail['discount'] + ($revock_price - $reback_price);
        
        //商品促销优惠 = 商品促销优惠 - 余单撤消的商品优惠金额
        $order_update['pmt_goods'] = $order_detail['pmt_goods'] - $cancel_pmt_price;
        
        //需平摊删除商品对应订单优惠(订单促销优惠 = 订单促销优惠 - 余单撤消的商品优惠分摊)
        $order_update['pmt_order'] = $order_detail['pmt_order']  - $pmt_order_money;
        
        
        //配送费用 + 保价费用 + 税金 + 支付费用
        $other_amount = $order_detail['shipping']['cost_shipping'] + $order_detail['shipping']['cost_protect'] + $order_detail['cost_tax'];
        if($order_detail['payinfo']['cost_payment']){
            $other_amount += $order_detail['payinfo']['cost_payment']; //支付费用
        }
        
        //重新计算订单总额
        $order_update['total_amount'] = $order_update['cost_item'] + $order_update['discount'] + $other_amount - $order_update['pmt_order'] - $order_update['pmt_goods'];
        
        
        //订单归档
        $order_update['archive'] = 1;
        
        $filter = array('order_id'=>$order_id);
        
        //打回订单未发货的发货单
        $flag   = $oOrder->rebackDeliveryByOrderId($order_id, true, '');
        
        #打回发货单失败
        if($flag == false)
        {
            $msg    = '余单撤销失败';
            $oOperation_log->write_log('order_modify@ome', $order_id, $msg);//操作日志
            
            return false;
        }
        //更新订单
        $oOrder->update($order_update, $filter);
        
        //店铺信息
        $shop_detail = $oShop->dump(array('shop_id'=>$order_detail['shop_id']), 'node_type');
        $node_type = $shop_detail['node_type'];
        
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');

        //获取撤消的商品明细
        $cancelitems = array();
        $cancel_sql = "SELECT product_id,bn,nums,sendnum,(nums-sendnum) as cancel_num,((nums-sendnum)*price) as c_total_money,obj_id FROM `sdb_ome_order_items` WHERE order_id='".$order_id."' AND `sendnum`<`nums` AND `delete`='false' ";
        $cancel_items = kernel::database()->select($cancel_sql);
        if ($cancel_items){
            $objects = app::get('ome')->model('order_objects')->getList('obj_id,goods_id', ['obj_id'=>array_column($cancel_items, 'obj_id')]);
            $objects = array_column($objects, null, 'obj_id');

            uasort($cancel_items, [kernel::single('console_iostockorder'), 'cmp_productid']);
            $branchBatchList = [];
            foreach ($cancel_items as $c_key=>$c_items){
                //减少冻结库存
                $branchBatchList[] = [
                    'bm_id'     =>  $c_items['product_id'],
                    'sm_id'     =>  $objects[$c_items['obj_id']]['goods_id'],
                    'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                    'bill_type' =>  0,
                    'obj_id'    =>  $order_id,
                    'branch_id' =>  '',
                    'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                    'num'       =>  $c_items['cancel_num'],
                ];

                $cancelitems[] = '货号:'.$c_items['bn'].'(购买数:'.$c_items['nums'].',已发货:'.$c_items['sendnum'].',本次撤销:'.$c_items['cancel_num'].'个)';
            }
            // 减少冻结库存
            $basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        }
        $cancelitems = implode(';',$cancelitems);
        $msg = '余单撤销(撤销商品总金额为:'.$revock_price.';明细为:'.$cancelitems;
        
        //余单撤消后,清除订单级预占店铺冻结流水
        // unfreezeBatch已经清除
        // $basicMStockFreezeLib->delOrderFreeze($order_id);
        
        /*    */
        //更新订单明细
        $order = app::get('ome')->model('orders')->dump($order_id,"order_id",array("order_objects"=>array("*",array("order_items"=>array("*")))));
        if ($order['order_objects']){
            foreach ($order['order_objects'] as $obj){
                if ($obj) {
                    $delete = true;
                    foreach ($obj['order_items'] as $item){
                        if ($item['sendnum'] == 0){
                            $sql = "UPDATE `sdb_ome_order_items` SET `delete`='true' WHERE `item_id`='".$item['item_id']."'";
                            kernel::database()->exec($sql);
                        }else {
                            $delete = false;
                        }
                    }
                    if ($delete == true){
                        $sql = "UPDATE `sdb_ome_order_objects` SET `delete`='true' WHERE `obj_id`='".$obj['obj_id']."'";
                        kernel::database()->exec($sql);
                    }
                }
            }
        }
        
        //操作日志
        $log_id     = $oOperation_log->write_log('order_modify@ome', $order_id, $msg);
        
        //[拆单]余单撤消_保存订单快照
        $oOrder->write_log_detail($log_id, $order_detail);
        
        
        $c2c_shop_list = ome_shop_type::shop_list();
        if (!in_array($node_type, $c2c_shop_list)){
            //订单编辑同步
            if ($service_order = kernel::servicelist('service.order')){
                foreach($service_order as $object=>$instance){
                    if(method_exists($instance, 'update_order')){
                        $instance->update_order($order_id);
                    }
                }
            }
        }
        //发货单同步
        if (in_array($node_type, $c2c_shop_list)){
            //C2C前端店铺回写发货单
            $sql = "SELECT de.`delivery_id` FROM `sdb_ome_delivery` de
                    JOIN `sdb_ome_delivery_order` dor ON dor.`delivery_id`=de.`delivery_id` AND dor.`order_id`='".$order_id."'
                    WHERE de.`logi_no` IS NOT NULL ORDER BY de.`delivery_id` DESC";
            $c2c_delivery = kernel::database()->selectrow($sql);
            $delivery_id = $c2c_delivery['delivery_id'];
            if ($delivery_id){
                kernel::single('ome_event_trigger_shop_delivery')->delivery_add($delivery_id);
            }
        }
        
        /*------------------------------------------------------ */
        //-- [拆单]余单撤消后_生成销售单
        /*------------------------------------------------------ */
        
        #判断是否存在销售单
        $sql        = "SELECT COUNT(*) AS num FROM sdb_ome_sales WHERE order_id='".$order_id."'";
        $sales_row  = kernel::database()->selectrow($sql);
        if(empty($sales_row['num']))
        {
            #获取"最后成功发货"的普通发货单ID
            $sql    = "SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d 
                        ON(dord.delivery_id=d.delivery_id) WHERE dord.order_id='".$order_id."' AND (d.parent_id=0 OR d.is_bind='true') 
                        AND d.disabled='false' AND d.status='succ' AND d.type='normal' ORDER BY delivery_time DESC";
            $delivery_row   = kernel::database()->selectrow($sql);
            
            $delivery_id    = $delivery_row['delivery_id'];
            if($delivery_id)
            {
                #生成销售单
                $iostock_data       = kernel::single('siso_receipt_iostock_sold')->get_io_data(array('delivery_id'=>$delivery_id,'process_status'=>'remain_cancel'));

                kernel::single('siso_receipt_sales_sold')->create(array('delivery_id'=>$delivery_id,'iostock'=>$iostock_data), $msg);
            }
        }
        //余单撤销后，需要发起多包裹状态回写，因为物流信息作为补充回写，一般是发货完成才会触发
        if (in_array($order_detail['shop_type'], ome_shop_type::many_split_type())) {
            kernel::single('ome_event_trigger_shop_delivery')->delivery_confirm_retry([$order_id]);
        }
        
        // [更新]预约订单的发货状态
        $orderIds = [$order_id];
        kernel::single('ome_order_reservation')->updateReservationByOrderIds($orderIds);
        
        return true;
    }
    
    /**
     * 判断订单中是否有捆绑商品部分发货
     * @Author: xueding
     * @Vsersion: 2022/9/13 下午6:07
     * @param $orderDetail
     * @return bool
     */
    public function getPgkDeliveryStatus($orderDetail)
    {
        foreach($orderDetail['order_objects'] as $object => $objVal) {
            if ($objVal['obj_type'] == 'pkg') {
                $sendnum = array_sum(array_column($objVal['order_items'],'sendnum'));
                $quantity = array_sum(array_column($objVal['order_items'],'quantity'));
                if ($sendnum > 0 && $quantity != $sendnum) {
                    $msg = '该订单存在组合商品部分发货无法撤销余单';
                    $oOperation_log = app::get('ome')->model('operation_log');
                    $oOperation_log->write_log('order_modify@ome', $orderDetail['order_id'], $msg);//操作日志
                    return false;
                }
            }
        }
        return true;
    }
    /**
     * 订单数据字段格式化过滤
     * @access public
     * @param $order_sdf 订单标准sdf结构数据
     * @return 订单标准sdf结构数据
     */
    public function modify_sdfdata($order_sdf){
        if ($order_sdf['shop_type'] == 'taobao'){
            foreach($order_sdf['order_objects'] as $key=>$object){
                if ($object['obj_type'] != 'pkg' || !$object['amount']) continue;
                $obj_amount = $object['amount'];
                $count_items = count($object['order_items']);
                if ( $obj_amount > $count_items ){
                    $average_item_price = floor($obj_amount / $count_items);
                    $remain_price = $obj_amount - $average_item_price * $count_items;
                }else{
                    $average_item_price = round($obj_amount / $count_items, 3);
                }
                $i = 1;
                foreach($object['order_items'] as $k=>$item){
                    $amount = $price = 0;
                    if ( $i == 1 ){
                        $amount = bcadd($average_item_price,$remain_price,3);
                    }else{
                        $amount = $average_item_price;
                    }
                    if ($item['quantity']){
                        $price = round($amount / $item['quantity'], 3);
                    }else{
                        $price = $amount;
                    }
                    $order_sdf['order_objects'][$key]['order_items'][$k]['price'] = $price;
                    $order_sdf['order_objects'][$key]['order_items'][$k]['amount'] = $amount;
                    $i++;
                }
            }
        }
        return $order_sdf;
    }

    /**
     * 计算捆绑、礼包的差额
     * @access public
     * @param $order_objects objects_sdf 结构
     * @return Number 差额
     */
    public function obj_difference($order_objects){
        $obj_amount = $items_amount = 0;
        $db = kernel::database();
        $obj_sendnum = 1;

        if ($order_objects['order_items']){
            $items_tmp = array();
            // 捆绑商品明细总金额
            foreach ($order_objects['order_items'] as $items){
                if (empty($items_tmp)){
                    $items_tmp = $items;
                }
                $items_amount += $items['amount'];
            }
            // 计算当前捆绑的发送数量
            $sql = " SELECT `nums` FROM `sdb_ome_order_items` WHERE `item_id`='".$items_tmp['item_id']."' ";
            $old_order_items = $db->selectrow($sql);
            if ($items_tmp['number']){
                $obj_sendnum = $order_objects['nums'] / ($old_order_items['nums'] / $items_tmp['number']);
            }
            $obj_amount = $obj_sendnum * $order_objects['price'];
        }

        $difference = $obj_amount - $items_amount;
        return $difference;
    }

    /**
      * 处理本地或shopex体系的0元订单
      *
      * @return void
      * @author
      **/
     function order_pay_confirm($shop_id,$order_id,$total_amount)
     {

        $ome_payment_confirm = app::get('ome')->getConf('ome.payment.confirm');

        $orderObj = app::get('ome')->model('orders');

        if($ome_payment_confirm == 'false'){//不需要经过财审,直接生成支付单

            $op_info = kernel::single('ome_func')->getDesktopUser();

            $pay_time = time();

            $paymentObj = app::get('ome')->model('payments');
            $sdf = array(
                'payment_bn' => $paymentObj->gen_id(),
                'shop_id' => $shop_id,
                'order_id' => $order_id,
                'currency' => 'CNY',
                'money' => '0',
                'paycost' => '0',
                'cur_money' => '0',
                'pay_type' => 'online',
                't_begin' => $pay_time,
                'download_time' => $pay_time,
                't_end' => $pay_time,
                'status' => 'succ',
                'memo'=>'0元订单自动生成支付单',
                'op_id'=> $op_info['op_id'],
            );
            $paymentObj->create_payments($sdf);
            $pay_status = '1';
            $sql ="UPDATE `sdb_ome_orders` SET pay_status='".$pay_status."', paytime='".$pay_time."' WHERE `order_id`='".$order_id."'";
        }else{
            $pay_status = '0';
            $sql ="UPDATE `sdb_ome_orders` SET pay_status='".$pay_status."' WHERE `order_id`='".$order_id."'";
        }

        $orderObj->db->exec($sql);

        return true;

     }

     /**
      * 获取余单撤消的商品原价
      */
     public function get_cancel_item_price($order){
         $amount = 0;
         if ($order['order_objects']){
             foreach ($order['order_objects'] as $obj){
                 /* if($obj['obj_type'] == 'pkg'){
                     $tmp_amount = kernel::single('ome_order_remain_pkg')->get_order_total_price($obj);
                     $amount += $tmp_amount;
                 }elseif($obj['obj_type'] == 'gift'){
                     continue; //赠品为0元,跳过
                 }else{ */
                     $tmp_amount = kernel::single('ome_order_remain_goods')->get_order_total_price($obj);
                     $amount += $tmp_amount;
                 //}
             }
         }
         
         return $amount;
     }
     
     /**
      * 有订单优惠时,获取余单撤消的商品实付金额(实付金额 = 销售价 - 优惠分摊)
      */
     public function get_cancel_diff_money($order){
         $amount = 0;
         if ($order['order_objects']){
             foreach ($order['order_objects'] as $obj){
                //  if($obj['obj_type'] == 'pkg'){
                //      $tmp_amount = kernel::single('ome_order_remain_pkg')->get_order_diff_money($obj);
                //      $amount += $tmp_amount;
                //  }elseif($obj['obj_type'] == 'gift'){
                //      continue; //赠品为0元,跳过
                //  }else{
                     $tmp_amount = kernel::single('ome_order_remain_goods')->get_order_diff_money($obj);
                     $amount += $tmp_amount;
                //  }
             }
         }
         
         return $amount;
     }
     
     /**
      * 获取余单撤消的商品优惠金额
      */
     public function get_cancel_pmt_price($order){
         $amount = 0;
         if ($order['order_objects']){
             foreach ($order['order_objects'] as $obj){
                //  if($obj['obj_type'] == 'pkg'){
                //      $tmp_amount = kernel::single('ome_order_remain_pkg')->get_order_pmt_price($obj);
                //      $amount += $tmp_amount;
                //  }elseif($obj['obj_type'] == 'gift'){
                //      continue; //赠品为0元,跳过
                //  }else{
                     $tmp_amount = kernel::single('ome_order_remain_goods')->get_order_pmt_price($obj);
                     $amount += $tmp_amount;
                //  }
             }
         }
          
         return $amount;
     }
     
     /**
      * 获取余单撤消商品的的订单优惠分摊
      */
     public function get_cancel_pmt_order_price($order){
         $amount = 0;
         if ($order['order_objects']){
             foreach ($order['order_objects'] as $obj){
                //  if($obj['obj_type'] == 'pkg'){
                //      $tmp_amount = kernel::single('ome_order_remain_pkg')->get_order_pmt_order_price($obj);
                //      $amount += $tmp_amount;
                //  }elseif($obj['obj_type'] == 'gift'){
                //      continue; //赠品为0元,跳过
                //  }else{
                     $tmp_amount = kernel::single('ome_order_remain_goods')->get_order_pmt_order_price($obj);
                     $amount += $tmp_amount;
                //  }
             }
         }
         
         return $amount;
     }
     
     /**
      * 订单全额退款成功后,系统自动余单撤消 并删除未发货的商品
      * todo满足以下条件：
      *  1、订单是部分发货
      *  2、订单已经全额退款
      *  3、还有货品没有发货
      */
     public function fullRefund_order_revoke($order_id){
         if(empty($order_id)){
             return false;
         }
         
         $oOrder = app::get('ome')->model('orders');
         $orderInfo = $oOrder->dump($order_id, 'order_id, pay_status, ship_status, pmt_order', array("order_objects"=>array("*",array("order_items"=>array("*")))));
         if($orderInfo['pay_status'] != '5' || $orderInfo['ship_status'] != '2'){
             return false; //不是(部分发货、全额退款)
         }
         
         if($orderInfo['archive'] == 1 || $orderInfo['abnormal'] == 'true'){
             return false; //订单已归档 OR 异常订单
         }
         
         if(!in_array($orderInfo['process_status'], array('splitting', 'splited'))){
             return false; //订单不是部分拆分、已拆分完
         }
         
         //判断是否还有货品没有发货
         $is_unshipped = false;
         foreach ($orderInfo['order_objects'] as $objKey => $objVal){
             foreach ($objVal['order_items'] as $itemKey => $itemVal){
                 if($itemVal['delete']=='false' && $itemVal['sendnum']<$itemVal['quantity']){
                     $is_unshipped = true;
                 }
             }
         }
         
         if(!$is_unshipped){
             return false; //货品已经全部发货
         }
         
         //计算未发货商品的金额
         $orderInfo['pmt_order'] = floatval($orderInfo['pmt_order']);
        //  if($orderInfo['pmt_order']){
             //订单优惠时
             $reback_price = kernel::single('ome_order_order')->get_cancel_diff_money($orderInfo);
        //  }else{
        //      $reback_price = kernel::single('ome_order_func')->order_items_diff_money($order_id);
        //  }
         
         $revock_price = 0; //撤销商品总额(默认0元后面会重新计算)
         $result = $this->order_revoke($order_id, $reback_price, $revock_price);
         
         return $result;
     }
}
