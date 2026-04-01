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
class ome_sales_original_data
{
    private $appName = 'ome';
    
    public function setAppName($appName) {
        $this->appName = $appName;
        return $this;
    }
    
    /**
     * 初始化销售单所需的原始数据
     */
    public function init($order_id){
        if(!$order_id){
            return false;
        }

        $ordersObj = app::get('ome')->model('orders');
        $order_info = $ordersObj->dump($order_id,'*',array('order_objects'=>array('*',array('order_items'=>array('*')))));

        // 代销人信息
        $agent_info = app::get('ome')->model('order_selling_agent')->dump(array('order_id' => $order_id));
        $order_info['selling_agent_id'] = empty($agent_info['selling_agent_id']) ? 0 : $agent_info['selling_agent_id'];

        $presale_flag = false;
        if ($order_info['order_type'] == 'presale' && $order_info['pmt_goods']>0 && $order_info['pmt_order']>0 && $order_info['discount']<0){
            //if(0 == bccomp((float) $order_info['pmt_order'],(float) abs($order_info['discount']),3)){
                $presale_flag = true;

            //}
        }
        $shopType = $order_info['createway'] == 'after' ? 'after' : $order_info['shop_type'];
        $couponPlatform = kernel::single('ome_sales_original_platform_factory')->init($order_id, $shopType);
        //数据做兼容修正
        foreach ($order_info['order_objects'] as $key => $obj){

            $is_obj_delete = false;

            $tmp_obj_price = ($obj['price'] > 0) ? $obj['price'] : (($obj['amount']/$obj['quantity'] > 0) ? $obj['amount']/$obj['quantity'] : 0.00);
            $order_info['order_objects'][$key]['price'] = $tmp_obj_price;

            $tmp_obj_amount = ($obj['amount'] > 0) ? $obj['amount'] : (($tmp_obj_price*$obj['quantity'] > 0) ? $tmp_obj_price*$obj['quantity'] : 0.00);
            $order_info['order_objects'][$key]['amount'] = $tmp_obj_amount;

            $items = $obj['order_items'] ? : [];
            $tmp_item_pmt_price_all = 0.00;
            $items_count =count($items);
            $item_delete_flag = 0;
            foreach($items as $k => $item){
                //如果存在已删除货品，该商品对象直接排除
                $is_item_delete = false;
                if($item['delete'] == 'true'){
                    $is_item_delete = true;
                    unset($order_info['order_objects'][$key]['order_items'][$k]);
                    unset($items[$k]);
                    $item_delete_flag++;
                }
                if (!$is_item_delete){
                    $tmp_item_price = ($item['price'] > 0) ? $item['price'] : (($item['amount']/$item['quantity'] > 0) ? $item['amount']/$item['quantity'] : 0.00);
                    $order_info['order_objects'][$key]['order_items'][$k]['price'] = $tmp_item_price;

                    $tmp_item_amount = ($item['amount'] > 0) ? $item['amount'] : (($tmp_item_price*$item['quantity'] > 0) ? $tmp_item_price*$item['quantity'] : 0.00);
                    $order_info['order_objects'][$key]['order_items'][$k]['amount'] = $tmp_item_amount;

                    $tmp_item_pmt_price = ($item['pmt_price'] > 0) ? $item['pmt_price'] : 0.00;
                    $order_info['order_objects'][$key]['order_items'][$k]['pmt_price'] = $tmp_item_pmt_price;

                    $tmp_item_cost = ($item['cost'] > 0) ? $item['cost'] : 0.00;
                    $order_info['order_objects'][$key]['order_items'][$k]['cost'] = $tmp_item_cost;

                    // 使用系统精度处理类避免浮点数精度问题
                    $calculated_price = kernel::single('eccommon_math')->number_minus(array($tmp_item_price*$item['quantity'], $tmp_item_pmt_price));
                    $tmp_sale_price = ($item['sale_price'] > 0) ? $item['sale_price'] : (($calculated_price > 0) ? $calculated_price : 0.00);
                    if ($presale_flag && $item['pmt_price']<0){
                        $tmp_sale_price =$tmp_sale_price+ $item['pmt_price'];
                    }
                    $tmp_sale_price = $tmp_sale_price-$item['refund_money'];

                    // 格式化避免浮点数精度问题导致科学计数法
                    $order_info['order_objects'][$key]['order_items'][$k]['sale_price'] = floatval(sprintf('%.2f', $tmp_sale_price));

                    $tmp_item_pmt_price_all += $tmp_item_pmt_price;
                }
            }
            
            if($items_count==$item_delete_flag){//明细数量和删除数量一致，删除OBJ
                unset($order_info['order_objects'][$key]);
                $is_obj_delete = true;
            }

            if(!$is_obj_delete){
                $tmp_obj_pmt_price = ($obj['pmt_price'] > 0) ? $obj['pmt_price'] : 0.00;
                $order_info['order_objects'][$key]['pmt_price'] = $tmp_obj_pmt_price;

                $tmp_obj_sale_price = ($obj['sale_price'] > 0) ? $obj['sale_price'] : ((($tmp_obj_amount-$tmp_obj_pmt_price-$tmp_item_pmt_price_all) > 0) ? ($tmp_obj_amount-$tmp_obj_pmt_price-$tmp_item_pmt_price_all) : 0.000);
                // 格式化避免浮点数精度问题导致科学计数法，使用三位小数与原始代码保持一致
                $order_info['order_objects'][$key]['sale_price'] = floatval(sprintf('%.3f', $tmp_obj_sale_price));

                if($order_info['settlement_amount'] > 0) {
                    continue;
                }
                $platformAmount = $couponPlatform->getPlatformAmount($obj);
                $order_info['order_objects'][$key]['platform_amount'] = $platformAmount;
                $order_info['order_objects'][$key]['settlement_amount'] = $platformAmount + $obj['divide_order_fee'];
                $platformPayAmount = $couponPlatform->getPlatformPayAmount($obj);
                $actualAmount = $couponPlatform->getActualAmount($obj, $platformPayAmount);
                $order_info['order_objects'][$key]['platform_pay_amount'] = $platformPayAmount;
                $order_info['order_objects'][$key]['actually_amount'] = $actualAmount;
                $options = array (
                    'part_total'  => $platformAmount,
                    'part_field'  => 'platform_amount',
                    'porth_field' => 'divide_order_fee',
                );
                $items = kernel::single('ome_order')->calculate_part_porth($items, $options);
                $options = array (
                    'part_total'  => $platformPayAmount,
                    'part_field'  => 'platform_pay_amount',
                    'porth_field' => 'divide_order_fee',
                    'minuend_field' => 'divide_order_fee',
                );
                $items = kernel::single('ome_order')->calculate_part_porth($items, $options);
                foreach($items as $k => $item){
                    $order_info['order_objects'][$key]['order_items'][$k]['platform_amount'] = $item['platform_amount'];
                    $order_info['order_objects'][$key]['order_items'][$k]['settlement_amount'] = $item['platform_amount'] + $item['divide_order_fee'];
                    $order_info['order_objects'][$key]['order_items'][$k]['platform_pay_amount'] = $item['platform_pay_amount'];
                    $order_info['order_objects'][$key]['order_items'][$k]['actually_amount'] = $item['divide_order_fee'] - $item['platform_pay_amount'];
                }
            }
        }
        if ($presale_flag){
            $order_info['pmt_goods'] = 0;
            $order_info['discount'] = 0;
        }
        if($order_info['process_status'] == 'remain_cancel') {
            $this->splitNumChange($order_info);
        }
        //校验最终数据
        if($this->_check($order_info,$flag)){

        }else{
            //将异常原始订单塞队列里
            if(defined('ERROR_HTTPSQS_HOST') && defined('ERROR_HTTPSQS_PORT') && defined('ERROR_HTTPSQS_CHARSET') && defined('ERROR_PENDING_QUEUE')){
                $tmp = array(
                    'domain' => $_SERVER['SERVER_NAME'],
                    'order_bn' => $order_info['order_bn'].$flag,
                );
                $httpsqsLib = kernel::single('taoexlib_httpsqs');
                $httpsqsLib->put(ERROR_HTTPSQS_HOST, ERROR_HTTPSQS_PORT, ERROR_HTTPSQS_CHARSET, ERROR_PENDING_QUEUE, json_encode($tmp));
            }
        }
        return $order_info;
    }

    private function splitNumChange(&$order_info) {
        foreach($order_info['order_objects'] as $ok => &$ov) {
            $quantity = 0;
            $sendnum = 0;
            $objPrice = [];
            $needC = false;
            foreach($ov['order_items'] as $ik => &$iv) {
                $quantity += $iv['quantity'];
                $sendnum += $iv['sendnum'];
                if($iv['sendnum'] < $iv['quantity']) {
                    $needC = true;
                    $radio = $iv['sendnum'] / $iv['quantity'];
                    $iv['quantity'] = $iv['sendnum'];
                    $iv['pmt_price'] = sprintf("%.2f", $iv['pmt_price'] * $radio);
                    $iv['sale_price'] = sprintf("%.2f", $iv['sale_price'] * $radio);
                    $iv['part_mjz_discount'] = sprintf("%.2f", $iv['part_mjz_discount'] * $radio);
                    $iv['divide_order_fee'] = sprintf("%.2f", $iv['divide_order_fee'] * $radio);
                    $iv['platform_amount'] = sprintf("%.2f", $iv['platform_amount'] * $radio);
                    $iv['settlement_amount'] = sprintf("%.2f", $iv['settlement_amount'] * $radio);
                    $iv['platform_pay_amount'] = sprintf("%.2f", $iv['platform_pay_amount'] * $radio);
                    $iv['actually_amount'] = sprintf("%.2f", $iv['actually_amount'] * $radio);
                }
                $objPrice['pmt_price'] += $iv['pmt_price'];
                $objPrice['sale_price'] += $iv['sale_price'];
                $objPrice['part_mjz_discount'] += $iv['part_mjz_discount'];
                $objPrice['divide_order_fee'] += $iv['divide_order_fee'];
                $objPrice['platform_amount'] += $iv['platform_amount'];
                $objPrice['settlement_amount'] += $iv['settlement_amount'];
                $objPrice['platform_pay_amount'] += $iv['platform_pay_amount'];
                $objPrice['actually_amount'] += $iv['actually_amount'];
            }
            if($needC) {
                foreach($objPrice as $index => $indexVal) {
                    $ov[$index] = $indexVal;
                }
                $ov['amount'] = $ov['sale_price'] + $ov['pmt_price'];
                $ov['quantity'] = sprintf("%.2f", $sendnum/$quantity*$ov['quantity']);
            }
        }
    }

    /**
     * 检查原始数据是否有异常
     */
    private function _check(&$data,&$flag) {
        $sum = $data['cost_item']+$data['shipping']['cost_shipping']+$data['shipping']['cost_protect']+$data['cost_tax']+$data['payinfo']['cost_payment']-$data['refund_money'];

        if($data['discount'] > 0){
            $sum += $data['discount'];
        }else{
            $sum -= abs($data['discount']);
        }

        // 使用 bcmath 进行精确计算，避免浮点数精度问题
        $sum = bcsub(bcsub($sum, $data['pmt_goods'], 3), $data['pmt_order'], 3);
        $data['total_amount'] = $data['total_amount']-$data['service_price']-$data['refund_money'];
        if(bccomp($data['total_amount'], $sum, 3) == 0){
            return true;
        }elseif(bccomp($data['total_amount'], $sum, 3) > 0){
            $data['discount'] = $data['discount']+($data['total_amount']-$sum);
            $flag = '_q';
            return false;
        }elseif(bccomp($data['total_amount'], $sum, 3) < 0){
            $data['discount'] = $data['discount']+($data['total_amount']-$sum);
            $flag = '_d';
            return false;
        }
    }

}
