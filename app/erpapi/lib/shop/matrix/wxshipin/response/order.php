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
 * @Author: xueding@shopex.cn
 * @Date: 2023/4/20
 * @Describe: 订单接收类
 */
class erpapi_shop_matrix_wxshipin_response_order extends erpapi_shop_response_order
{
    public $object_comp_key = 'bn-oid-obj_type';
    
    protected $_update_accept_dead_order = true;
    
    #平台订单状态
    protected $_sourceStatus = array(
        '10'  => 'WAIT_BUYER_PAY', #待付款
        '20'  => 'WAIT_SELLER_SEND_GOODS', #待发货
        '21'  => 'SELLER_CONSIGNED_PART', #部分发货
        '30'  => 'WAIT_BUYER_CONFIRM_GOODS', #待收货
        '100' => 'TRADE_FINISHED', # 完成
        '200' => 'TRADE_CLOSED', #取消
        '250' => 'TRADE_CLOSED', #取消
    );
    
    protected function _analysis()
    {
        $this->_ordersdf['is_delivery'] = 'Y';//默认可以发货
        parent::_analysis();
        
        // 特殊处理重庆市县区逻辑，否则订单省市区会乱，且预取号收货地址部分字段为null，例如："consignee": "{\"area_street\": \"\", \"telephone\": \"15000000666\", \"area_state\": \"重庆市\", \"addr\": \"中和街道东风路666号\", \"name\": \"潘贵\", \"mobile\": \"15000000666\", \"country\": \"\", \"area_city\": \"县\", \"area_district\": \"秀山土家族苗族自治县\"}"
        if (isset($this->_ordersdf['consignee']['area_state']) && 
            $this->_ordersdf['consignee']['area_state'] == '重庆市' &&
            isset($this->_ordersdf['consignee']['area_city']) && 
            $this->_ordersdf['consignee']['area_city'] == '县') {
            $this->_ordersdf['consignee']['area_city'] = '重庆市';
        }

        // 处理平台优惠券（coupon_type=3）
        if($this->_ordersdf['extend_field']['version'] >= 3) {
            $this->_processV3ObjectAmounts();
        }else{
            $this->_processPlatformCoupons();
        }
    }

    /**
     * v3 金额处理：
     * - settlement_amount = divi_fee + coupon_field.pmt_info(coupon_type=1)汇总(按oid)
     * - actually_amount   = coupon_field.calcActuallyPay(按oid)
     */
    protected function _processV3ObjectAmounts()
    {
        if (empty($this->_ordersdf['order_objects']) || !is_array($this->_ordersdf['order_objects'])) {
            return;
        }

        $oidCouponType1Map = array();
        $oidActuallyPayMap = array();
        $couponField = array();

        if (!empty($this->_ordersdf['coupon_field']) && is_array($this->_ordersdf['coupon_field'])) {
            $couponField = $this->_ordersdf['coupon_field'];
        }

        foreach ($couponField as $couponRow) {
            if (!is_array($couponRow) || empty($couponRow['oid'])) {
                continue;
            }

            $oid = (string)$couponRow['oid'];

            if (isset($couponRow['calcActuallyPay']) && is_numeric($couponRow['calcActuallyPay'])) {
                $oidActuallyPayMap[$oid] = bcadd(
                    isset($oidActuallyPayMap[$oid]) ? (string)$oidActuallyPayMap[$oid] : '0',
                    (string)$couponRow['calcActuallyPay'],
                    3
                );
            }

            if (!empty($couponRow['pmt_info']) && is_array($couponRow['pmt_info'])) {
                foreach ($couponRow['pmt_info'] as $pmtInfo) {
                    if (!is_array($pmtInfo)) {
                        continue;
                    }
                    if (!isset($pmtInfo['coupon_type']) || (string)$pmtInfo['coupon_type'] !== '1') {
                        continue;
                    }

                    $pmtAmount = isset($pmtInfo['pmt_amount']) && is_numeric($pmtInfo['pmt_amount']) ? (string)$pmtInfo['pmt_amount'] : '0';
                    $oidCouponType1Map[$oid] = bcadd(
                        isset($oidCouponType1Map[$oid]) ? (string)$oidCouponType1Map[$oid] : '0',
                        $pmtAmount,
                        3
                    );
                }
            }
        }

        foreach ($this->_ordersdf['order_objects'] as $objectKey => $object) {
            if (!is_array($object) || empty($object['oid'])) {
                continue;
            }

            $oid = (string)$object['oid'];
            $diviFee = isset($object['divide_order_fee']) && is_numeric($object['divide_order_fee']) ? (string)$object['divide_order_fee'] : '0';

            $couponType1Amount = isset($oidCouponType1Map[$oid]) ? (string)$oidCouponType1Map[$oid] : '0';
            $settlementAmount = bcadd($diviFee, $couponType1Amount, 3);
            $this->_ordersdf['order_objects'][$objectKey]['settlement_amount'] = $settlementAmount;

            if (isset($oidActuallyPayMap[$oid])) {
                $this->_ordersdf['order_objects'][$objectKey]['actually_amount'] = (string)$oidActuallyPayMap[$oid];
            } else {
                $this->_ordersdf['order_objects'][$objectKey]['actually_amount'] = $diviFee;
            }
        }
    }
    
    /**
     * 处理平台优惠券（coupon_type=3）
     * 将优惠券金额累加到相关字段中
     */

    protected function _processPlatformCoupons()
    {
        $totalCouponDiscount = 0; // 总优惠金额
        $couponData          = array(); // 优惠券数据
        
        // order_objects 数据已经是数组格式
        $orderObjects = $this->_ordersdf['order_objects'];
        
        if (is_array($orderObjects)) {
            foreach ($orderObjects as $objKey => $object) {
                $objectCouponDiscount = 0; // 当前商品的优惠金额
                
                if (isset($object['order_items']) && is_array($object['order_items'])) {
                    foreach ($object['order_items'] as $itemKey => $item) {
                        // 检查是否有extend_item_list和order_product_coupon_info_list
                        if (isset($item['extend_item_list']['order_product_coupon_info_list']) &&
                            is_array($item['extend_item_list']['order_product_coupon_info_list'])) {
                            
                            foreach ($item['extend_item_list']['order_product_coupon_info_list'] as $couponInfo) {
                                // 只处理coupon_type=3的平台优惠券，且优惠金额大于0
                                if (isset($couponInfo['coupon_type']) && $couponInfo['coupon_type'] == 3 && intval($couponInfo['discounted_price']) > 0) {
                                    $discountedPrice = intval($couponInfo['discounted_price']) / 100; // 优惠金额（分转元）
                                    
                                    $totalCouponDiscount  += $discountedPrice;
                                    $objectCouponDiscount += $discountedPrice;
                                    
                                    // 组装优惠券数据
                                    $couponData[] = array(
                                        'num'           => $object['quantity'],
                                        'material_bn'   => $object['bn'],
                                        'oid'           => $object['oid'],
                                        'material_name' => $object['name'],
                                        'type'          => '3',
                                        'type_name'     => '平台优惠券',
                                        'coupon_type'   => '3',
                                        'amount'        => $discountedPrice, // 已经是元单位
                                        'total_amount'  => $discountedPrice, // 已经是元单位
                                        'create_time'   => kernel::single('ome_func')->date2time($this->_ordersdf['createtime']),
                                        'pay_time'      => kernel::single('ome_func')->date2time($this->_ordersdf['payment_detail']['pay_time']),
                                        'shop_type'     => 'wxshipin',
                                        'source'        => 'push',
                                    );
                                    
                                    // 更新order_items层的divide_order_fee（加上优惠金额）
                                    $orderObjects[$objKey]['order_items'][$itemKey]['divide_order_fee'] =
                                        bcadd($item['divide_order_fee'], $discountedPrice, 2);
                                    
                                    // 更新order_items层的part_mjz_discount（扣除优惠金额）
                                    $orderObjects[$objKey]['order_items'][$itemKey]['part_mjz_discount'] =
                                        bcsub($item['part_mjz_discount'], $discountedPrice, 2);
                                    
                                    // 更新order_items层的sale_amount（加上优惠金额）
                                    $orderObjects[$objKey]['order_items'][$itemKey]['sale_amount'] =
                                        bcadd($item['sale_amount'], $discountedPrice, 2);
                                }
                            }
                        }
                    }
                    
                    // 更新order_objects层的divide_order_fee（加上当前商品的优惠金额）
                    $orderObjects[$objKey]['divide_order_fee'] =
                        bcadd($object['divide_order_fee'], $objectCouponDiscount, 2);
                    
                    // 更新order_objects层的part_mjz_discount（扣除当前商品的优惠金额）
                    $orderObjects[$objKey]['part_mjz_discount'] =
                        bcsub($object['part_mjz_discount'], $objectCouponDiscount, 2);
                }
            }
        }
        
        // 验证总优惠金额是否合理
        if ($totalCouponDiscount > 0) {
            // 支付手续费总额
            $paycost_amount = 0;
            foreach ($this->_ordersdf['payments'] as $value) {
                $paycost_amount += $value['paycost'] ?: 0;
            }
            
            // 进行完整的金额验证
            $amount = $pmt_price = $part_mjz_discount = $divide_order_fee = 0;
            
            foreach ($orderObjects as $object) {
                // 订单是否关单
                if ($object['status'] == 'close') {
                    continue;
                }
                
                $amount            = bcadd($amount, $object['amount'], 3);
                $pmt_price         = bcadd($pmt_price, $object['pmt_price'], 3);
                $part_mjz_discount = bcadd($part_mjz_discount, $object['part_mjz_discount'], 3);
                $divide_order_fee  = bcadd($divide_order_fee, $object['divide_order_fee'], 3);
            }
            
            // 验证订单总金额是否正确
            $total_amount = (float)$this->_ordersdf['cost_item'] + (float)$this->_ordersdf['shipping']['cost_shipping'] + (float)$this->_ordersdf['shipping']['cost_protect'] + (float)$this->_ordersdf['cost_tax'] + (float)$paycost_amount - (float)$this->_ordersdf['pmt_goods'] - (float)$this->_ordersdf['pmt_order'];
            $total_amount = round($total_amount, 3);
            
            // 验证商品原价金额总和不等于商品总额
            $amountCheck = (bccomp($amount, $this->_ordersdf['cost_item'], 3) == 0);
            
            // 验证子订单商品优惠金额总和不等于商品优惠总金额
            $pmtPriceCheck = (bccomp($pmt_price, $this->_ordersdf['pmt_goods'], 3) == 0);
            
            // 验证子订单均摊优惠总和不等于订单优惠总金额
            $partMjzDiscountCheck = (bccomp($part_mjz_discount, $this->_ordersdf['pmt_order'], 3) == 0);
            
            // 验证子订单商品实付金额总和是否等于订单总金额
            $checkTotalAmount = bcadd($divide_order_fee, $this->_ordersdf['shipping']['cost_shipping'], 3);
            $checkTotalAmount = bcadd($checkTotalAmount, $this->_ordersdf['shipping']['cost_protect'], 3);
            $checkTotalAmount = bcadd($checkTotalAmount, $this->_ordersdf['cost_tax'], 3);
            $checkTotalAmount = bcadd($checkTotalAmount, $paycost_amount, 3);
            $divideOrderFeeCheck = (bccomp($checkTotalAmount, $this->_ordersdf['total_amount'], 3) == 0);
            
            // 验证订单总金额是否正确
            $totalAmountCheck = (bccomp($total_amount, $this->_ordersdf['total_amount'], 3) == 0);
            
            // 如果所有验证都通过，则更新数据
            if ($amountCheck && $pmtPriceCheck && $partMjzDiscountCheck && $divideOrderFeeCheck && $totalAmountCheck) {
                $this->_ordersdf['order_objects'] = $orderObjects;
                
                // 添加新的支付记录（平台优惠券）
                $this->_ordersdf['payments'][] = array(
                    "money"     => $totalCouponDiscount,
                    "paymethod" => '平台优惠券'
                );
    
                // 更新payed字段，加上平台优惠券金额
                $this->_ordersdf['payed'] = bcadd($this->_ordersdf['payed'], $totalCouponDiscount, 2);
                
                // 设置优惠券数据
                $this->_ordersdf['coupon_data'] = $couponData;
            } else {
                // 验证失败，不允许发货
                $this->_ordersdf['is_delivery'] = 'N';
            }
        }
    }

    protected function get_update_components()
    {
        $components = array('markmemo', 'custommemo', 'marktype');
        if($this->_tgOrder['is_delivery'] == 'N') {
            $components[] = 'master';
            $components[] = 'items';
        }
        if ($this->_tgOrder) {
            $rs = app::get('ome')->model('order_extend')->getList('extend_status,bool_extendstatus', array('order_id' => $this->_tgOrder['order_id']));
            // 如果ERP收货人信息未发生变动时，则更新淘宝收货人信息
            if ($rs[0]['extend_status'] != 'consignee_modified') {
                $components[] = 'consignee';
            }
        }
        
        if (($this->_ordersdf['pay_status'] != $this->_tgOrder['pay_status']) || ($this->_ordersdf['shipping']['is_cod'] == 'true' && $this->_ordersdf['status'] == 'dead')) {
            $refundApply = app::get('ome')->model('refund_apply')->getList('apply_id', array('order_id' => $this->_tgOrder['order_id'], 'status|noequal' => '3'));
            // 如果没有退款申请单，以前端为主
            if (!$refundApply) {
                $components[] = 'master';
            }
        }
        
        return $components;
    }
}
