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

class ome_sales_price{
    private function cmp_by_sale_price($a,$b){
        if(0 == bccomp((float) $a['sale_price'],(float) $b['sale_price'],3) ){
            return 0;
        }

        return (bccomp((float) $a['sale_price'],(float) $b['sale_price'],3) == -1) ? -1 : 1;
    }

    private function cmp_by_part_mjz_discount($a,$b){
        if((float)$a['part_mjz_discount']==(float)$b['part_mjz_discount']) {
            return 0;
        }

        return ((float)$a['part_mjz_discount']>(float)$b['part_mjz_discount'])  ? -1 : 1;
    }

    // 对订单明细重新排序
    private function sort_order($order){
        $sort_flag = true;
        foreach($order['order_objects'] as $object){
            if ($object['part_mjz_discount']>0){
                $sort_flag = false;
                break;
            }
        }
        foreach ($order['order_objects'] as &$object){
            if($sort_flag){
                uasort($object['order_items'],array($this,'cmp_by_sale_price'));
            }else{
                uasort($object['order_items'],array($this,'cmp_by_part_mjz_discount'));
            }

        }
        if($sort_flag){
            uasort($order['order_objects'],array($this,'cmp_by_sale_price'));
        }else{
            uasort($order['order_objects'],array($this,'cmp_by_part_mjz_discount'));
        }


        return $order;
    }

    public function calculate($order_original_data,&$sales_data){

        if(!$this->_check($order_original_data, $sales_data)){
            $this->_worldPeaceMode($sales_data);
        }
        $this->_calcPlatformTotal($sales_data);

        // 主表的platform_amount加上sdb_ome_order_coupon里的platform_cost_amount, 仅限拼多多
        $oids = [];
        if(isset($sales_data['sales_objects']) && $sales_data['sales_objects']){
            $oids = array_column($sales_data['sales_objects'], 'oid');
        }
        if ($oids && in_array($order_original_data['shop_type'], ['pinduoduo'])) {
            $filter = [
                'oid|in'    =>  $oids,
                'order_id'  =>  $sales_data['order_id'],
                'type'      =>  'platform_cost_amount',
            ];
            $couponInfo = app::get('ome')->model('order_coupon')->getList('*', $filter);
            foreach ($couponInfo as $coupon) {
                $sales_data['platform_amount'] += $coupon['total_amount'];
            }
        }
        return true;
    }

    private function _check($order_original_data, $sales_data){
        $all_product_price = 0.00;
        foreach($sales_data['sales_items'] as $k => $sale_item){
            $all_product_price += $sale_item['sales_amount'];
        }

        $sum = $all_product_price+$order_original_data['shipping']['cost_shipping']+$order_original_data['shipping']['cost_protect']+$order_original_data['cost_tax']+$order_original_data['payinfo']['cost_payment'];

        if($order_original_data['discount'] > 0){
            $sum += $order_original_data['discount'];
        }

        if(bccomp($order_original_data['total_amount'], $sum, 3) == 0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 验证不通过的数据走万金油模式
     */
    private function _worldPeaceMode(&$sales_data){

        $all_sale_price = 0.00;
        $all_pmt_price = 0;
        foreach($sales_data['sales_items'] as $k => $sale_item){
            $all_sale_price += $sale_item['price']*$sale_item['nums'];
            $all_pmt_price += $sale_item['pmt_price'];
        }
        
        $all_pmt_price = sprintf('%.2f', $all_pmt_price);
        $all_sale_price = sprintf('%.2f', $all_sale_price);
        $old_discount = sprintf('%.2f', $sales_data['discount']);

        if(bccomp($sales_data['total_amount'], $all_sale_price, 3) > 0){
            $sales_data['discount'] = $sales_data['discount']-($sales_data['total_amount']-$all_sale_price);
            $sales_data['total_amount'] = $all_sale_price;
        }elseif(bccomp($sales_data['total_amount'], $all_sale_price, 3) < 0){
            $sales_data['discount'] = $sales_data['discount']+($all_sale_price-$sales_data['total_amount']);
            $sales_data['total_amount'] = $all_sale_price;
        }
        $sales_data['discount'] = sprintf('%.2f', $sales_data['discount']);
        $sales_data['total_amount'] = sprintf('%.2f', $sales_data['total_amount']);
        if($old_discount > 0 && $sales_data['discount'] > 0 && $old_discount > $sales_data['discount']){
            $sales_data['additional_costs'] = $sales_data['additional_costs'] - ($old_discount - $sales_data['discount']);
        }elseif($old_discount > 0 && $sales_data['discount'] > 0 && $old_discount < $sales_data['discount']){
            $sales_data['additional_costs'] = $sales_data['additional_costs'] + ($sales_data['discount'] - $old_discount);
        }elseif($old_discount > 0 && $sales_data['discount'] <= 0){
            $sales_data['additional_costs'] = $sales_data['additional_costs'] - $old_discount;
        }elseif($old_discount < 0 && $sales_data['discount'] > 0){
            $sales_data['additional_costs'] = $sales_data['additional_costs'] + $sales_data['discount'];
        }
        $sales_data['additional_costs'] = sprintf('%.2f', $sales_data['additional_costs']);

        //货品实际销售价 = 支付总金额-运费-其他附加费
        $product_sales_amount = $sales_data['sale_amount']-$sales_data['cost_freight']-$sales_data['additional_costs'];

        //可分摊优惠金额
        $can_apportion_pmt_amount = $all_sale_price - $product_sales_amount;
        $can_apportion_pmt_amount = sprintf('%.2f', $can_apportion_pmt_amount);
        if($can_apportion_pmt_amount < 0) {
            $options = array (
                'part_total'  => -$can_apportion_pmt_amount,
                'part_field'  => 'apportion_pmt',
                'porth_field' => 'sales_amount',
            );
            $sales_data['sales_items'] = kernel::single('ome_order')->calculate_part_porth($sales_data['sales_items'], $options);
            foreach($sales_data['sales_items'] as $k => $sale_item){
                $sales_data['sales_items'][$k]['pmt_price'] = 0;
                $sales_data['sales_items'][$k]['apportion_pmt'] = -$sale_item['apportion_pmt'];
            }
        } elseif($can_apportion_pmt_amount >= $all_pmt_price) {
            $options = array (
                'part_total'  => $can_apportion_pmt_amount - $all_pmt_price,
                'part_field'  => 'apportion_pmt',
                'porth_field' => 'sales_amount',
                'minuend_field' => 'sale_price',
            );
            $sales_data['sales_items'] = kernel::single('ome_order')->calculate_part_porth($sales_data['sales_items'], $options);
        } else {
            $options = array (
                'part_total'  => $can_apportion_pmt_amount,
                'part_field'  => 'pmt_price',
                'porth_field' => 'sales_amount',
                'minuend_field' => 'amount',
            );
            $sales_data['sales_items'] = kernel::single('ome_order')->calculate_part_porth($sales_data['sales_items'], $options);
            foreach($sales_data['sales_items'] as $k => $sale_item){
                $sales_data['sales_items'][$k]['apportion_pmt'] = 0;
            }
        }
        $objTotal = [];
        foreach($sales_data['sales_items'] as $k => $val) {
            $sales_data['sales_items'][$k]['sale_price'] = $val['amount'] - $val['pmt_price'];
            $sales_data['sales_items'][$k]['sales_amount'] = $sales_data['sales_items'][$k]['sale_price'] - $val['apportion_pmt'];
            $sales_data['sales_items'][$k]['settlement_amount'] = $sales_data['sales_items'][$k]['sales_amount'] + $val['platform_amount'];
            $sales_data['sales_items'][$k]['actually_amount'] = $sales_data['sales_items'][$k]['sales_amount'] - $val['platform_pay_amount'];
            $objTotal[$val['obj_id']]['sale_price'] += $val['amount'];
            $objTotal[$val['obj_id']]['pmt_price'] += $sales_data['sales_items'][$k]['pmt_price'];
            $objTotal[$val['obj_id']]['apportion_pmt'] += $sales_data['sales_items'][$k]['apportion_pmt'];
            $objTotal[$val['obj_id']]['sales_amount'] += $sales_data['sales_items'][$k]['sales_amount'];
            $objTotal[$val['obj_id']]['settlement_amount'] += $sales_data['sales_items'][$k]['settlement_amount'];
            $objTotal[$val['obj_id']]['actually_amount'] += $sales_data['sales_items'][$k]['actually_amount'];
            $objTotal[$val['obj_id']]['platform_amount'] += $sales_data['sales_items'][$k]['platform_amount'];
            $objTotal[$val['obj_id']]['platform_pay_amount'] += $sales_data['sales_items'][$k]['platform_pay_amount'];
        }
        foreach($sales_data['sales_objects'] as $k => $val) {
            foreach($objTotal[$k] as $index => $ival) {
                $sales_data['sales_objects'][$k][$index] = sprintf('%.2f', $ival);
            }
        }
    }

    private function _calcPlatformTotal(&$sales_data) {
        $sales_data['settlement_amount'] = 0;
        $sales_data['actually_amount'] = 0;
        $sales_data['platform_amount'] = 0;
        $sales_data['platform_pay_amount'] = 0;
        foreach($sales_data['sales_objects'] as $obj) {
            $sales_data['settlement_amount'] += $obj['settlement_amount'];
            $sales_data['actually_amount'] += $obj['actually_amount'];
            $sales_data['platform_amount'] += $obj['platform_amount'];
            $sales_data['platform_pay_amount'] += $obj['platform_pay_amount'];
        }
        if(!in_array($sales_data['shop_type'], ['meituan4sg'])) {
            $sales_data['settlement_amount'] += $sales_data['cost_freight'];
        }
        $sales_data['settlement_amount'] += $sales_data['service_price'];
        $sales_data['settlement_amount'] -= $sales_data['platform_service_fee'];
        $sales_data['actually_amount'] += $sales_data['cost_freight'];
        $sales_data['actually_amount'] += $sales_data['service_price'];
    }

    public function getItemProductSalePrice($saleData, $arrOrder, $productGoods) {
        $itemSalePrice = array();
        foreach ($saleData as $orderId => $val) {
            foreach ($val['sales_items'] as $v) {
                $orderObject = $arrOrder[$orderId]['order_objects'][$v['obj_id']];
                if($orderObject['obj_type'] == 'pkg') {
                    $endItem = array_pop($orderObject['order_items']);
                    $endItem['num_price'] = bcmul($endItem['quantity'],
                        ($endItem['price'] ? $endItem['price'] : $productGoods[$endItem['product_id']]['mktprice']),
                        2);
                    $totalItemPrice = $endItem['num_price'];
                    $totalNum = $endItem['quantity'];
                    if($orderObject['order_items']) {
                        foreach ($orderObject['order_items'] as $itemId => $item) {
                            $orderObject['order_items'][$itemId]['num_price'] = bcmul($item['quantity'],
                                ($item['price'] ? $item['price'] : $productGoods[$item['product_id']]['mktprice']),
                                2);
                            $totalItemPrice += $orderObject['order_items'][$itemId]['num_price'];
                            $totalNum += $item['quantity'];
                        }
                    }
                    if($totalItemPrice > 0) {
                        $itemTotal = $totalItemPrice;
                        $itemTotalIndex = 'num_price';
                    } else {
                        $itemTotal = $totalNum;
                        $itemTotalIndex = 'quantity';
                    }
                    $hasPmtPrice = $hasSalePrice = $hasApportionPmt = 0;
                    if($orderObject['order_items']) {
                        foreach ($orderObject['order_items'] as $itemId => $item) {
                            $tmpItemPrice = array();
                            $tmpItemPrice['spec_name'] = $v['spec_name'];
                            $tmpItemPrice['number'] = $item['quantity'];
                            $tmpItemPrice['pmt_price'] = bcmul($v['pmt_price']/$itemTotal, $item[$itemTotalIndex], 2);
                            $tmpItemPrice['sale_price'] = bcmul($v['sale_price']/$itemTotal, $item[$itemTotalIndex], 2);
                            $tmpItemPrice['apportion_pmt'] = bcmul($v['apportion_pmt']/$itemTotal, $item[$itemTotalIndex], 2);
                            $tmpItemPrice['price'] = bcdiv(
                                bcadd($tmpItemPrice['sale_price'], $tmpItemPrice['pmt_price'], 2),
                                $item['quantity'], 2);
                            $tmpItemPrice['sales_amount'] = bcsub($tmpItemPrice['sale_price'], $tmpItemPrice['apportion_pmt'], 2);
                            $itemSalePrice[$item['item_id']] = $tmpItemPrice;
                            $hasPmtPrice = bcadd($hasPmtPrice, $tmpItemPrice['pmt_price'], 2);
                            $hasSalePrice = bcadd($hasSalePrice, $tmpItemPrice['sale_price'], 2);
                            $hasApportionPmt = bcadd($hasApportionPmt, $tmpItemPrice['apportion_pmt'], 2);
                        }
                    }
                    $tmpItemPrice = array();
                    $tmpItemPrice['spec_name'] = $v['spec_name'];
                    $tmpItemPrice['number'] = $endItem['quantity'];
                    $tmpItemPrice['pmt_price'] = bcsub($v['pmt_price'], $hasPmtPrice, 2);
                    $tmpItemPrice['sale_price'] = bcsub($v['sale_price'], $hasSalePrice, 2);
                    $tmpItemPrice['apportion_pmt'] = bcsub($v['apportion_pmt'], $hasApportionPmt, 2);
                    $tmpItemPrice['price'] = bcdiv(
                        bcadd($tmpItemPrice['sale_price'], $tmpItemPrice['pmt_price'], 2),
                        $endItem['quantity'], 2);
                    $tmpItemPrice['sales_amount'] = bcsub($tmpItemPrice['sale_price'], $tmpItemPrice['apportion_pmt'], 2);
                    $itemSalePrice[$endItem['item_id']] = $tmpItemPrice;
                } else {
                    foreach ($orderObject['order_items'] as $item) {
                        if($item['bn'] == $v['bn']) {
                            $itemSalePrice[$item['item_id']] = array(
                                'spec_name' => $v['spec_name'],
                                'number' => $v['nums'],
                                'price' => $v['price'],
                                'pmt_price' => $v['pmt_price'],
                                'sale_price' => $v['sale_price'],
                                'apportion_pmt' => $v['apportion_pmt'],
                                'sales_amount' => $v['sales_amount'],
                            );
                        }
                    }
                }
            }
        }
        return $itemSalePrice;
    }

}
