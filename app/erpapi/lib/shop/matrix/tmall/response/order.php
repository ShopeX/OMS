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
 *
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_tmall_response_order extends erpapi_shop_response_order
{
    protected $_update_accept_dead_order = true;
    /**
     * 订单obj明细唯一标识
     * 
     * @var string
     * */
    public $object_comp_key = 'bn-oid-obj_type';

    /**
     * 订单item唯一标识
     * 
     * @var string
     * */
    public $item_comp_key = 'bn-shop_product_id-item_type';


    protected function _analysis()
    {
        $this->_ordersdf['is_delivery'] = 'Y';#默认可以发货
        parent::_analysis();
        $this->_ordersdf['is_service_order'] = ($this->_ordersdf['is_service_order'] || $this->_ordersdf['service_order_objects']['service_order']);
        if($this->_ordersdf['ship_status'] == '2' && $this->_ordersdf['is_service_order']) {
            $this->_ordersdf['ship_status'] = '0';
            foreach($this->_ordersdf['order_objects'] as $object) {
                if($object['source_status'] != 'WAIT_SELLER_SEND_GOODS') {
                    $this->_ordersdf['ship_status'] = '2';
                }
            }
        }

        if (strtolower($this->_ordersdf['is_daixiao']) == 'true') {
            $this->_setPlatformDelivery();
            $this->_ordersdf['is_sh_ship'] = 'true';
            $this->_ordersdf['sh_ship_exists'] = true;
        }

        // 国补自补处理逻辑
        if (isset($this->_ordersdf['extend_field']['gov_subsidy'])) {
            $gov_subsidy = $this->_ordersdf['extend_field']['gov_subsidy'];
            
            // 获取出资类型
            $gov_subsidy_type_extra = isset($gov_subsidy['gov_subsidy_type_extra']) 
                ? intval($gov_subsidy['gov_subsidy_type_extra']) 
                : 0;
            
            // 商家出资金额（单位：元）
            $merchant_subsidy_amount = 0;
            
            // 根据出资类型获取商家出资金额
            if ($gov_subsidy_type_extra == 4) {
                // 纯商家出资：使用 gov_subsidy_amount_exact
                if (isset($gov_subsidy['gov_subsidy_amount_exact']) && $gov_subsidy['gov_subsidy_amount_exact'] > 0) {
                    $merchant_subsidy_amount = floatval($gov_subsidy['gov_subsidy_amount_exact']) / 100;
                }
            } elseif (in_array($gov_subsidy_type_extra, [6, 7, 8])) {
                // 平台和商家混合出资(6)、政府和商家混合出资(7)、政府商家平台三方混合出资(8)
                // 从 gov_subsidy_funds_detail 中提取商家出资金额（b部分）
                if (isset($gov_subsidy['gov_subsidy_funds_detail']) && $gov_subsidy['gov_subsidy_funds_detail']) {
                    $funds_detail = explode('_', $gov_subsidy['gov_subsidy_funds_detail']);
                    // 格式：a_b_c，其中 a:政府出资金额，b:商家出资金额，c:平台出资金额（单位：分）
                    if (isset($funds_detail[1]) && $funds_detail[1] > 0) {
                        $merchant_subsidy_amount = floatval($funds_detail[1]) / 100;
                    }
                }
            }
            
            // 如果商家出资金额大于0，计算结算金额
            if ($merchant_subsidy_amount > 0 && in_array($gov_subsidy_type_extra, [4, 6, 7, 8])) {
                // 国补订单只有一个 order_object，直接处理第一个
                if (isset($this->_ordersdf['order_objects'][0]['divide_order_fee'])) {
                    $divide_order_fee = floatval($this->_ordersdf['order_objects'][0]['divide_order_fee']);
                    
                    // 计算 settlement_amount = divide_order_fee - 商家出资金额
                    $settlement_amount = $divide_order_fee - $merchant_subsidy_amount;
                    
                    // 更新 order_object 的 settlement_amount
                    $this->_ordersdf['order_objects'][0]['settlement_amount'] = sprintf('%.2f', $settlement_amount);
                }
            }
        }

        // 全部是屏蔽发货才定位平台自发
        $shShip = app::get('ome')->getConf('ome.platform.order.consign');
        if($shShip == 'true') {

            $is_sh_ship_num = 0;
            foreach($this->_ordersdf['order_objects'] as $object){
                if($object['is_sh_ship'] == 'true'){
                    // $this->_ordersdf['order_type'] = 'platform';
                    $is_sh_ship_num++;

                    $this->_ordersdf['sh_ship_exists'] = true;
                }
            }

            if ($is_sh_ship_num > 0 && $is_sh_ship_num == count($this->_ordersdf['order_objects'])) {
                    $this->_ordersdf['order_type'] = 'platform';
                    $this->_ordersdf['is_sh_ship'] = 'true';
            }
        }
        if($this->_ordersdf['order_type'] != 'platform') {
            if(in_array($this->_ordersdf['trade_type'],array('step')) || in_array($this->_ordersdf['t_type'],array('step'))){
                $this->_ordersdf['order_type'] = 'presale';
            }
            foreach($this->_ordersdf['order_objects'] as $object){
                if($object['zhengji_status']){
                    if(in_array($object['zhengji_status'],array('1','2'))){
                        $this->_ordersdf['order_type'] = 'presale';

                    }
                }
            }
        }

        if($this->_ordersdf['step_trade_status']){

            $this->_ordersdf['step_trade_status'] = $this->_ordersdf['step_trade_status'];
        }
        if($this->_ordersdf['cn_info'] && $this->_ordersdf['cn_info']['es_date']) {
            $this->_ordersdf['consignee']['r_time'] = $this->_ordersdf['cn_info']['es_date'] . ' ' . $this->_ordersdf['cn_info']['es_range'];
        }
        //商品总额扣掉服务费:淘宝的服务费算在总额上
        $total_fee = 0;
        foreach ((array)$this->_ordersdf['service_order_objects']['service_order'] as $s) {
            $total_fee += (float)$s['total_fee'];
        }

        if ($total_fee>0) $this->_ordersdf['cost_item'] -= $total_fee;
        
        //发票处理
        $mdl_invoice_order_taobao = app::get('invoice')->model('order_taobao');
        $rs_invoice_order_taobao = $mdl_invoice_order_taobao->dump(array("platform_tid"=>$this->_ordersdf["order_bn"]));
        if(!empty($rs_invoice_order_taobao)){
            $this->_ordersdf["is_tax"] = 'true';
            $this->_ordersdf["tax_title"] = $rs_invoice_order_taobao["payer_name"];
            $this->_ordersdf["payer_register_no"] = $rs_invoice_order_taobao["payer_register_no"];
            $this->_ordersdf["invoice_kind"] = $rs_invoice_order_taobao["invoice_kind"];
        }
        
        // 喵速达
        $label_code = '';
        if (isset($this->_ordersdf['extend_field']['businessModel']) && $this->_ordersdf['extend_field']['businessModel']) {
            $businessModel = intval($this->_ordersdf['extend_field']['businessModel']);
            if ($businessModel >= 1 && $businessModel <= 4) {
                $label_code = 'SOMS_ASCP';
                
                // 喵速达订单结算金额重新计算
                // 获取商家应收(订单总额)
                $totalAmount = floatval($this->_ordersdf['total_amount']);
                
                // 查询扣点比例（固定配置键 commission）
                $platformSetMdl = app::get('ome')->model('platform_set');
                $platformSet = $platformSetMdl->db_dump([
                    'shop_type' => 'taobao',
                    'scene' => 'tmsd_commission',
                    'kname' => 'commission'
                ], 'kvalue');
                
                if ($platformSet && isset($platformSet['kvalue']) && $platformSet['kvalue']) {
                    $commissionRate = floatval($platformSet['kvalue']); // 如 0.02 表示 2%
                    
                    // 计算新的结算金额: 结算金额 = 商家应收 - (商家应收 * 扣点比例)
                    $newSettlementAmount = $totalAmount - ($totalAmount * $commissionRate);
                    
                    // 使用 calculate_part_porth 方法重新均摊结算金额
                    if ($newSettlementAmount > 0 && isset($this->_ordersdf['order_objects'])) {
                        $options = [
                            'part_total' => $newSettlementAmount,
                            'part_field' => 'settlement_amount',
                            'porth_field' => 'divide_order_fee',
                        ];
                        $this->_ordersdf['order_objects'] = kernel::single('ome_order')->calculate_part_porth($this->_ordersdf['order_objects'], $options);
                    }
                }
            }
        }
        
        // 是否请求平台获取实付金额
        //@todo：喵速达订单，不需要请求平台获取实付金额;
        $is_request_payment = true;
        if(in_array($label_code, ['SOMS_ASCP'])){
            $is_request_payment = false;
        }
        
        // 检查订单状态：只有在订单不存在或status为active时才处理实付金额
        if ($is_request_payment) {
            $orderModel = app::get('ome')->model('orders');
            $filter = array(
                'order_bn' => $this->_ordersdf['order_bn'], 
                'shop_id' => $this->__channelObj->channel['shop_id']
            );
            $existingOrder = $orderModel->db_dump($filter, 'status');
            
            // 如果订单存在且status不是active，则不处理实付金额
            if (!empty($existingOrder) && isset($existingOrder['status']) && $existingOrder['status'] != 'active') {
                $is_request_payment = false;
            }
        }
        
        // 实付金额处理
        $getPaymentEnabled = app::get('ome')->getConf('ome.order.get.payment');
        if($this->_ordersdf['pay_status'] == '1' && $getPaymentEnabled === 'true' && $is_request_payment){
            $router = kernel::single('erpapi_router_request');
            $router->set('shop', $this->__channelObj->channel['shop_id']);
            $actuallyPay = $router->order_getActuallyPay($this->_ordersdf['order_bn']);
            
            if ($actuallyPay && $actuallyPay['rsp'] == 'succ' && isset($actuallyPay['data']['actually_pay'])) {
                $totalActuallyPay = floatval($actuallyPay['data']['actually_pay']); 
                $totalActuallyPay = $totalActuallyPay - (float)$this->_ordersdf['shipping']['cost_shipping'];
                if ($totalActuallyPay > 0 && isset($this->_ordersdf['order_objects']) && is_array($this->_ordersdf['order_objects'])) {
                    // 使用均摊方法处理
                    $options = [
                        'part_total' => $totalActuallyPay,
                        'part_field' => 'actually_amount',
                        'porth_field' => 'divide_order_fee',
                        'minuend_field' => 'divide_order_fee',
                    ];
                    $this->_ordersdf['coupon_actuallypay_field'] = 'actually_amount';
                    $this->_ordersdf['order_objects'] = kernel::single('ome_order')->calculate_part_porth($this->_ordersdf['order_objects'], $options);
                }
                
                // 天猫优品订单结算金额重新计算
                // 判断依据：gov_main_subject 以 'FIN_TMYPT' 开头
                $isTmypOrder = false;
                if (isset($this->_ordersdf['extend_field']['gov_subsidy']['gov_main_subject'])) {
                    $govMainSubject = $this->_ordersdf['extend_field']['gov_subsidy']['gov_main_subject'];
                    if ($govMainSubject && strpos($govMainSubject, 'FIN_TMYPT') === 0) {
                        $isTmypOrder = true;
                    }
                }
                
                if ($isTmypOrder) {
                    
                    // 获取消费者实付(API返回的原始金额,未减去运费)
                    $consumerActuallyPay = floatval($actuallyPay['data']['actually_pay']);
                    
                    // 获取 coupon_fee (单位为分,需要除以100)
                    $couponFee = 0;
                    if (isset($this->_ordersdf['coupon_fee'])) {
                        //$couponFee = floatval($this->_ordersdf['coupon_fee']) / 100;
                    }
                    
                    // 获取商家应收(订单总额)
                    $totalAmount = floatval($this->_ordersdf['total_amount']);
                    
                    // 从 gov_zhaoshangpi 获取供应商编码
                    $supplierCode = null;
                    if (isset($this->_ordersdf['order_objects']) && is_array($this->_ordersdf['order_objects'])) {
                        foreach ($this->_ordersdf['order_objects'] as $k => $_object) {
                            if (isset($_object['order_items']) && is_array($_object['order_items'])) {
                                foreach ($_object['order_items'] as $_item) {
                                    if (isset($_item['extend_item_list']['gov_zhaoshangpi']) && $_item['extend_item_list']['gov_zhaoshangpi']) {
                                        $this->_ordersdf['guobu_info']['gov_zhaoshangpi'][$_object['oid']][] = $_item['extend_item_list'];
                                        $parts = explode('_', $_item['extend_item_list']['gov_zhaoshangpi']);
                                        if (!empty($parts[0])) {
                                            $supplierCode = $parts[0];
                                            break 2; // 找到第一个就退出
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // 查询扣点比例
                    if ($supplierCode) {
                        $platformSetMdl = app::get('ome')->model('platform_set');
                        $platformSet = $platformSetMdl->db_dump([
                            'shop_type' => 'taobao',
                            'scene' => 'tmyp_commission',
                            'kname' => $supplierCode
                        ], 'kvalue');
                        
                        if ($platformSet && isset($platformSet['kvalue']) && $platformSet['kvalue']) {
                            $commissionRate = floatval($platformSet['kvalue']); // 如 0.02 表示 2%
                            
                            // 计算新的结算金额: 结算金额 = 消费者实付 + coupon_fee - (商家应收 * 扣点)
                            $newSettlementAmount = $consumerActuallyPay + $couponFee - ($totalAmount * $commissionRate);
                            
                            // 使用 calculate_part_porth 方法重新均摊结算金额
                            if ($newSettlementAmount > 0 && isset($this->_ordersdf['order_objects'])) {
                                $options = [
                                    'part_total' => $newSettlementAmount,
                                    'part_field' => 'settlement_amount',
                                    'porth_field' => 'divide_order_fee',
                                ];
                                $this->_ordersdf['order_objects'] = kernel::single('ome_order')->calculate_part_porth($this->_ordersdf['order_objects'], $options);
                            }
                        }
                    }
                }
            } else {
                $this->_ordersdf['is_delivery'] = 'N';
            }
        }
        $oidObject = [];
        $coupon = [];
        foreach ($this->_ordersdf['order_objects'] as $k => $object){
            if (!empty($object['estimate_con_time'])) {
                $arrDate = array();
                $tmpTime = 0;
                if(preg_match_all('/付款后([0-9])天内/', $object['estimate_con_time'], $arrDate)) {
                    $tmpTime = strtotime(date('Y-m-d', strtotime($arrDate[1][0].' days')));
                }
                if(preg_match_all('/[0-9]+年[0-9]+月[0-9]+日/', $object['estimate_con_time'], $arrDate)) {
                    $tmpTime = strtotime(str_replace(array('年','月','日'), '', $arrDate[0][0]));
                }
                if($tmpTime) {
                    $this->_ordersdf['latest_delivery_time'] = $tmpTime;
                    /*$this->_ordersdf['order_objects'][$k]['estimate_con_time'] = $tmpTime;
                    foreach ( $object['order_items'] as $ik => $item ){
                        $this->_ordersdf['order_objects'][$k]['order_items'][$ik]['estimate_con_time'] = $tmpTime;
                    }*/
                }
            }
            if($object['divide_order_fee'] > 0) $oidObject[$object['oid']] = $object;
            //权益金处理
            foreach ( $object['order_items'] as $itemkey => $item ){
                if($item['expand_card_expand_price_used_suborder'] > 0) {
                    $coupon[] = array(
                        'num'           => $object['quantity'],
                        'material_bn'   => $object['bn'],
                        'oid'           => $object['oid'],
                        'material_name' => $object['name'],
                        'type'          => '',
                        'type_name'     => '购物金优惠',
                        'coupon_type'   => '0',
                        'amount'        => $item['expand_card_expand_price_used_suborder'] / $object['quantity'],
                        'total_amount'  => $item['expand_card_expand_price_used_suborder'],
                        'create_time'   => sprintf('%.0f', time()),
                        'pay_time'      => kernel::single('ome_func')->date2time($this->_ordersdf['payment_detail']['pay_time']),
                        'shop_type'     => 'taobao',
                        'source'        => 'push',
                    );
                    $this->_ordersdf['order_objects'][$k]['part_mjz_discount'] = 
                        bcadd($object['part_mjz_discount'], $item['expand_card_expand_price_used_suborder'], 2);
                    $this->_ordersdf['order_objects'][$k]['divide_order_fee'] = 
                        bcsub($object['divide_order_fee'], $item['expand_card_expand_price_used_suborder'], 2);
                    $this->_ordersdf['order_objects'][$k]['order_items'][$itemkey]['part_mjz_discount'] = 
                        bcadd($item['part_mjz_discount'], $item['expand_card_expand_price_used_suborder'], 2);
                    $this->_ordersdf['order_objects'][$k]['order_items'][$itemkey]['divide_order_fee'] = 
                        bcsub($item['divide_order_fee'], $item['expand_card_expand_price_used_suborder'], 2);
                }
            }
        }
        if ($this->_ordersdf['pmt_detail']) {
            foreach ((array) $this->_ordersdf['pmt_detail'] as $key => $value) {
                if (!is_array($value) || trim($value['pmt_amount']) == '' || trim($value['pmt_amount']) == 0) {
                    continue;
                }
                if($value['pmt_describe'] == '购物金优惠') {
                    continue;
                }
                $type = '';
                if(strpos($value['promotion_id'], '-')) {
                    list($type, ) = explode('-', $value['promotion_id'], 2);
                }
                if($value['kd_child_discount_fee']) {
                    foreach (explode(',', $value['kd_child_discount_fee']) as $kd) {
                        list($oid, $total_amount) = explode('|', $kd);
                        $object = $oidObject[$oid];
                        if(empty($object)) {
                            continue;
                        }
                        $coupon[] = array(
                            'num'           => $object['quantity'],
                            'material_bn'   => $object['bn'],
                            'oid'           => $object['oid'],
                            'material_name' => $object['name'],
                            'type'          => $type,
                            'type_name'     => $value['pmt_describe'],
                            'coupon_type'   => '0',
                            'amount'        => $total_amount / $object['quantity'],
                            'total_amount'  => $total_amount,
                            'create_time'   => sprintf('%.0f', time()),
                            'pay_time'      => kernel::single('ome_func')->date2time($this->_ordersdf['payment_detail']['pay_time']),
                            'shop_type'     => 'taobao',
                            'source'        => 'push',
                        );
                    }
                } else {
                    $object = $oidObject[$value['pmt_id']];
                    if(empty($object)) {
                        continue;
                    }
                    $coupon[] = array(
                        'num'           => $object['quantity'],
                        'material_bn'   => $object['bn'],
                        'oid'           => $object['oid'],
                        'material_name' => $object['name'],
                        'type'          => $type,
                        'type_name'     => $value['pmt_describe'],
                        'coupon_type'   => '0',
                        'amount'        => $value['kd_discount_fee'] / $object['quantity'],
                        'total_amount'  => $value['kd_discount_fee'],
                        'create_time'   => sprintf('%.0f', time()),
                        'pay_time'      => kernel::single('ome_func')->date2time($this->_ordersdf['payment_detail']['pay_time']),
                        'shop_type'     => 'taobao',
                        'source'        => 'push',
                    );
                }
            }
        }
    
        $this->_ordersdf['coupon_data']         = $coupon;
        //针对预售订单 优惠价是负的处理
        if ($this->_ordersdf['trade_type'] == 'step' || $this->_ordersdf['t_type'] == 'step'){
            $presale_flag = false;
            $total_pmt_price = 0;
            foreach ($this->_ordersdf['order_objects'] as &$objects){
                foreach ( $objects['order_items'] as &$items ){
                    if ($items['pmt_price']<0){
                        $items['pmt_price'] =  ($items['price'] * $items['quantity']) - $items['amount'];
                        
                        $presale_flag = true;
                    }
                    $total_pmt_price+=$items['pmt_price'];
                }
            }
            if ($presale_flag){
                $this->_ordersdf['discount'] = 0;
                $this->_ordersdf['pmt_goods'] = $total_pmt_price;
            }
            
        }
         //预售订单判断逻辑 立减优惠没有下来进行均摊
        if( (in_array($this->_ordersdf['trade_type'],array('step')) || in_array($this->_ordersdf['t_type'],array('step')))
            && $this->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID'
        ){
            $objPmtOrder = array_sum(array_column($this->_ordersdf['order_objects'],'part_mjz_discount'));
            foreach ($this->_ordersdf['order_objects'] as $ok =>$ov) {
                $obj_amount     = $ov['amount'] ? $ov['amount'] : bcmul($ov['quantity'], $ov['price'], 3);
                $obj_sale_price = (isset($ov['sale_price']) && is_numeric($ov['sale_price']) && -1 != bccomp($ov['sale_price'], 0, 3)) ? $ov['sale_price'] : bcsub($obj_amount, $ov['pmt_price'], 3);
                $part_mjz_discount = $obj_sale_price - $ov['divide_order_fee'];
                if ($part_mjz_discount != $ov['part_mjz_discount'] && $ov['divide_order_fee'] > 0 ) {
                    $this->_ordersdf['order_objects'][$ok]['part_mjz_discount'] = $part_mjz_discount;
                    foreach ($ov['order_items'] as $itemKey => $itemVal) {
                        $this->_ordersdf['order_objects'][$ok]['order_items'][$itemKey]['part_mjz_discount'] = $part_mjz_discount;
                    }
                    $objPmtOrder -= ($ov['part_mjz_discount'] ?: 0);
                    $objPmtOrder += $part_mjz_discount;
                }
            }

            $newPmtOrder = $objPmtOrder + $this->_ordersdf['discount'];
            $paycostList = array_column($this->_ordersdf['payments'], 'paycost');
            $paycost_amount = 0;
            if (!empty($paycostList)) {
                $paycost_amount = array_sum($paycostList);
            }
            $total_amount = (float)$this->_ordersdf['cost_item'] +
                (float)$this->_ordersdf['cost_freight'] +
                (float)$this->_ordersdf['cost_protect'] +
                (float)$this->_ordersdf['discount'] +
                (float)$this->_ordersdf['cost_tax'] +
                (float)$paycost_amount -
                (float)$this->_ordersdf['pmt_goods'] - (float)$newPmtOrder;
            if ($total_amount == $this->_ordersdf['total_amount'] && $newPmtOrder > 0) {
                $this->_ordersdf['pmt_order'] = sprintf('%.2f', $newPmtOrder);
            }
        }

        if ($this->_ordersdf['cn_info']) {
            if ($this->_ordersdf['cn_info']['es_date']) {
                $this->_ordersdf['consignee']['r_time'] = $this->_ordersdf['cn_info']['es_date'] . ' ' . $this->_ordersdf['cn_info']['es_range'];
            }
            if ($this->_ordersdf['cn_info']['deliveryCps']) {
                $this->_ordersdf['shipping']['shipping_name'] = $this->_ordersdf['cn_info']['deliveryCps'];
            }
        
            // 送货上门
            if ($this->_ordersdf['cn_info']['asdp_biz_type'] == 'logistics_upgrade') {
                $this->_ordersdf['cpup_service'] = $this->_ordersdf['cn_info']['asdp_ads'];
                $this->_ordersdf['consignee']['r_time']       = $this->_ordersdf['cn_info']['sign_time'];
                $this->_ordersdf['promise_service'] = is_array($this->_ordersdf['cn_info']['logistics_agreement']) ? $this->_ordersdf['cn_info']['logistics_agreement']['logistics_service_msg'] : '';
                // 物流公司映射
                $shipping_name = $this->_ordersdf['cn_info']['delivery_cps'];
                if ($shipping_name) {
        
                    $corpMdl = app::get('ome')->model('dly_corp');
        
                    if ($this->__channelObj->channel['config']['cpmapping'][$shipping_name]) {
                        $shipping_name = $this->__channelObj->channel['config']['cpmapping'][$shipping_name];
                    } elseif (!in_array($shipping_name, array_keys($corpMdl->corp_default()))) {
                        $this->_ordersdf['cpuperr'] = true;
                    }
                }
                $this->_ordersdf['shipping']['shipping_name'] = $shipping_name;
            }
            
            // 闪购业务处理：判断 scenarioGroup=XSDBC，如果存在则设置 is_xsdbc 和 o2o_store_bn
            if (isset($this->_ordersdf['cn_info']) 
                && isset($this->_ordersdf['cn_info']['trade_attr']) 
                && isset($this->_ordersdf['cn_info']['trade_attr']['scenarioGroup']) 
                && $this->_ordersdf['cn_info']['trade_attr']['scenarioGroup'] == 'XSDBC') {
                
                // 设置闪购订单标记
                $this->_ordersdf['is_xsdbc'] = true;
                
                // 设置 o2o_info.o2o_store_bn
                $storeId = $this->_ordersdf['cn_info']['trade_attr']['xsdFulfillmentInfo']['storeId'];
                $trade_attr = $this->_ordersdf['cn_info']['trade_attr'];
                //仓配一体
                if($trade_attr['_F_sxts'] == '1'){
                    $this->_ordersdf['is_sh_ship'] = 'true';
                    $this->_ordersdf['order_type'] = 'platform';
                    $this->_ordersdf['accept_platform'] = true;
                    $logistics_infos = is_string($this->_ordersdf['logistics_infos']) ? json_decode($this->_ordersdf['logistics_infos'], true) : $this->_ordersdf['logistics_infos'];
                 
                    foreach($logistics_infos as $logistics_info){
                        if($logistics_info['store_code']){
                            $storeId = $logistics_info['store_code'];
                            break;
                        }
                    }
                    
                }
             
                $this->_ordersdf['o2o_info']['o2o_store_bn'] = $storeId;
                
                // 设置所有订单商品的 store_code
                if (isset($this->_ordersdf['order_objects']) && is_array($this->_ordersdf['order_objects'])) {
                    foreach ($this->_ordersdf['order_objects'] as &$order_object) {
                        $order_object['store_code'] = $storeId;
                        if($trade_attr['_F_sxts'] == '1'){
                            $order_object['is_sh_ship'] = 'true';
                            $this->_ordersdf['is_delivery'] = 'N';
                            foreach($order_object['order_items'] as $ik=>$item){
                                $order_object['order_items'][$ik]['is_sh_ship'] = 'true';
                            }
                        }
                        
                        
                    }
                }
               
                
            }
        }
      
        //天猫优仓订单
        if ($this->_ordersdf['erpHold'] == '1') {
            $this->_ordersdf['is_delivery'] = 'N'; //hold单:不用审核订单,禁止发货
        }elseif($this->_ordersdf['erpHold'] == '4') {
            //$this->_ordersdf['is_delivery'] = 'N';
            //指定仓库store_code 和 物流公司shipping_name 进行发货;
        }
        if(app::get('dchain')->is_installed()){
            $shopInfo = app::get('ome')->model('shop')->dump(array('shop_id'=>$this->__channelObj->channel['shop_id']), 'shop_id,shop_bn,node_id');
            $channelInfo = app::get('channel')->model('channel')->db_dump(array('node_id'=>$shopInfo['node_id'], 'channel_type'=>'dchain','disabled'=>'false'), 'channel_id,channel_bn,config');
            $isDchain = ($channelInfo ? true : false);
            if ($isDchain && $this->_ordersdf['erpHold'] != 4) {
                foreach ((array) $this->_ordersdf['order_objects'] as $objkey => $object) {
                    $this->_ordersdf['order_objects'][$objkey]['store_code']  = '';
                }
            }
        }

        //喵住对接
        if ($this->_ordersdf['trade_from'] && $this->_ordersdf['trade_from'] == 'miaozhu') {
            $this->_ordersdf['order_source'] = $this->_ordersdf['trade_from'];
        }

        //order_source
        if ($this->_ordersdf['extend_field']['qn_distr'] == '1') {
            $this->_ordersdf['order_source'] = 'goofish';
        }

        //[兑换券]定制订单
        if($this->_ordersdf['order_customs'] == 'Y'){
            $customizationLib = kernel::single('ome_order_customization');
            
            $this->_ordersdf['shop_id'] = $this->__channelObj->channel['shop_id'];
            
            //开始转换
            $transResult = $customizationLib->transformCustomOrder($this->_ordersdf);
            if($transResult['rsp'] == 'succ' && $transResult['data']){
                $this->_ordersdf['custom_transform_status'] = 'succ';
                
                $this->_ordersdf['order_objects'] = $transResult['data']['newObjects'];
                
                //coupon
                if($transResult['data']['coupons']){
                    $this->_ordersdf['coupon_data'] = array_merge($this->_ordersdf['coupon_data'], $transResult['data']['coupons']);
                }
                
                //cost_item
                if($transResult['data']['order_amount']){
                    //重置订单总额(物料总额)
                    $this->_ordersdf['cost_item'] = $transResult['data']['order_amount'];
                    
                    //订单促销优惠
                    $this->_ordersdf['pmt_order'] = $this->_ordersdf['cost_item'] - $this->_ordersdf['payed'];
                }
                
                //重置关联订单号
                if($transResult['data']['orderId']){
                    //customization orderId
                    $this->_ordersdf['relate_order_bn'] = $transResult['data']['orderId'];
                }
                
            }else{
                $this->_ordersdf['custom_transform_status'] = 'fail';
                $this->_ordersdf['custom_abnormal_msg'] = $transResult['error_msg'];
            }
        }

        if ($this->_ordersdf['extend_field']) {
            $this->_ordersdf['guobu_info'] = [];
            // 国补 - 淘宝直销
            if ($gov_subsidy = $this->_ordersdf['extend_field']['gov_subsidy']) {

                if ($gov_subsidy['use_gov_subsidy_new'] == 1) {

                    $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                    $this->_ordersdf['guobu_info']['guobu_type'][] = 1; // 支付立减

                } elseif (!$gov_subsidy['use_gov_subsidy_new'] && $gov_subsidy['gov_subsidy_amount_new']>0) {

                    $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                    $this->_ordersdf['guobu_info']['guobu_type'][] = 2; // 下单立减
                    $this->_ordersdf['guobu_info']['gov_subsidy_amount_new'] = $gov_subsidy['gov_subsidy_amount_new'];

                }

                if ($gov_subsidy['gov_store'] == 1 || $gov_subsidy['gov_main_subject']) {

                    $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                    
                    // 保存gov_main_subject到guobu_info中
                    if ($gov_subsidy['gov_main_subject']) {
                        $this->_ordersdf['guobu_info']['gov_main_subject'] = $gov_subsidy['gov_main_subject'];
                    }
                    
                    // 检查是否为天猫优品订单
                    if ($gov_subsidy['gov_main_subject'] && strpos($gov_subsidy['gov_main_subject'], 'FIN_TMYPT') === 0) {
                        $this->_ordersdf['guobu_info']['guobu_type'][] = 2048; // 天猫优品订单
                    }
                    
                    // 一品卖多地类型始终添加
                    $this->_ordersdf['guobu_info']['guobu_type'][] = 4; // 一品卖多地

                    
                }

                if ($this->_ordersdf['guobu_info']) {

                    // 是否需要采集sn+imei码，以及需要的个数，以及是否需要校验
                    // a_b_c
                    // a：大于0的整数——表示需要采集sn码的数量
                    // b：0-5的整数——表示需要采集的imei码数量
                    // c：1/0——表示是否会对sn和imei进行校验
                    if ($gov_subsidy['gov_sn_check']) {
                        $gov_sn_check = explode('_', $gov_subsidy['gov_sn_check']);
                        if ($gov_sn_check[0]>0) {
                            $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0040; // 需采集SN码
                        }
                        if ($gov_sn_check[1]>0 and $gov_sn_check[1]<=5) {
                            $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0080; // 需采集IMEI码
                        }
                        if ($gov_sn_check[2] == 1) {
                            $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0100; // 需校验SN码
                            $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0200; // 需校验IMEI码
                        }
                    }

                    // 处理出资类型：根据 gov_subsidy_type_extra 判断具体补贴场景
                    if (isset($gov_subsidy['gov_subsidy_type_extra'])) {
                        $gov_subsidy_type_extra = intval($gov_subsidy['gov_subsidy_type_extra']);
                        switch ($gov_subsidy_type_extra) {
                            case 4:
                                $this->_ordersdf['guobu_info']['guobu_type'][] = 0x2000; // 纯商家出资
                                break;
                            case 5:
                                $this->_ordersdf['guobu_info']['guobu_type'][] = 0x4000; // 平台出资
                                break;
                            case 6:
                                $this->_ordersdf['guobu_info']['guobu_type'][] = 0x8000; // 平台和商家混合出资
                                break;
                            case 7:
                                $this->_ordersdf['guobu_info']['guobu_type'][] = 0x10000; // 政府和商家混合出资
                                break;
                            case 8:
                                $this->_ordersdf['guobu_info']['guobu_type'][] = 0x20000; // 政府、商家和平台三方混合出资
                                break;
                        }
                    }

                    $this->_ordersdf['guobu_info']['gov_subsidy'] = $gov_subsidy;
                    // 招商皮信息 N_云闪付优惠码_折扣比例_类目编码
                    // 1）修改后第一段内容为N，将不再展示招商皮ID；
                    // 2）如果不是云闪付模式下的订单，第二段云闪付优惠码为N
                    foreach ($this->_ordersdf['order_objects'] as $k => $_object) {
                        foreach ( $_object['order_items'] as  $_item ){
                            if (isset($_item['extend_item_list']['gov_zhaoshangpi']) && $_item['extend_item_list']['gov_zhaoshangpi']) {
                                $this->_ordersdf['guobu_info']['gov_zhaoshangpi'][$_object['oid']][] = $_item['extend_item_list'];
                            }
                            // 国补 唯一码获取
                            if (isset($_item['extend_item_list']['gov_sn_check']) && $_item['extend_item_list']['gov_sn_check']) {
                                $this->_ordersdf['guobu_info']['gov_sn_check'][$_object['oid']][] = $_item['extend_item_list']['gov_sn_check'];
                            }
                        }
                    }

                }
                
                // 天猫优品国补打标记
                if ($gov_subsidy['gov_main_subject'] && strpos($gov_subsidy['gov_main_subject'], 'FIN_TMYPT') === 0) {
                    $this->_ordersdf['guobu_info']['tmyp_info'] = [
                        'is_tmyp_order' => true,
                        'supplier' => $gov_subsidy['gov_main_subject'], // 供应商编码
                    ];
                }
            }
            // 国补 - 淘宝分销
            if ($features = $this->_ordersdf['extend_field']['features']) {
                foreach ($features['feature'] as $attr) {
                    if ($attr['attr_key'] == '_F_zfbt_06' && $attr['attr_value']) {

                        $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                        $this->_ordersdf['guobu_info']['guobu_type'][] = 1; // 支付立减 (临时给支付立减类型)

                    } elseif ($attr['attr_key'] == 'gov_main_subject' && $attr['attr_value']) {
                        
                        $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                        $this->_ordersdf['guobu_info']['guobu_type'][] = 4; // 一品卖多地
                    }
                }

                if ($this->_ordersdf['guobu_info']) {

                    foreach ($features['feature'] as $attr) {
                        if ($attr['attr_key'] == '_F_zfbt_sn' && $attr['attr_value']) {
                            $_F_zfbt_sn = explode('_', $attr['attr_value']);
                            if ($_F_zfbt_sn[0]>0) {
                                $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0040; // 需采集SN码
                            }
                            if ($_F_zfbt_sn[1]>0 and $_F_zfbt_sn[1]<=5) {
                                $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0080; // 需采集IMEI码
                            }
                            if ($_F_zfbt_sn[2] == 1) {
                                $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0100; // 需校验SN码
                                $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0200; // 需校验IMEI码
                            }
                            break;
                        }
                    }

                    $this->_ordersdf['guobu_info']['features'] = $features; 

                    foreach ($this->_ordersdf['order_objects'] as $k => $_object) {
                        foreach ( $_object['order_items'] as  $_item ){
                            if (isset($_item['extend_item_list']['features']) && $_item['extend_item_list']['features']) {
                                $this->_ordersdf['guobu_info']['features_items'][$_object['oid']][] = $_item['extend_item_list']['features'];
                            }
                        }
                    }
                }
            }
            // 国补 - 淘宝供应链(ASCP)
            if ($extraContent = $this->_ordersdf['extend_field']['extraContent']) {
                $extraContent = array_filter(explode(';', $extraContent));
                foreach ($extraContent as $_ec) {
                    list($_ec_key, $_ec_value) = explode(':', $_ec);
                    if ($_ec_key == '_F_zfbt_06' && $_ec_value == 1) {
                        $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                        $this->_ordersdf['guobu_info']['guobu_type'][] = 1; // 支付立减 (临时给支付立减类型)
                    }
                }
                if ($this->_ordersdf['guobu_info']) {

                    $this->_ordersdf['guobu_info']['extraContent'] = $extraContent; 

                    foreach ($this->_ordersdf['order_objects'] as $k => $_object) {
                        foreach ( $_object['order_items'] as  $_item ){
                            if (isset($_item['extend_item_list']['expProps']) && $expProps = $_item['extend_item_list']['expProps']) {

                                $expProps = explode(';', $expProps);
                                foreach ($expProps as $expProp) {
                                    list($expProp_key, $expProp_value) = explode(':', $expProp);
                                    if ($expProp_key == '_F_zfbt_sn' && $expProp_value) {
                                        $_F_zfbt_sn = explode('_', $expProp_value);
                                        if ($_F_zfbt_sn[0]>0) {
                                            $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0040; // 需采集SN码
                                        }
                                        if ($_F_zfbt_sn[1]>0) {
                                            $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0080; // 需采集IMEI码
                                        }
                                        if ($_F_zfbt_sn[2] == 1) {
                                            $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0100; // 需校验SN码
                                            $this->_ordersdf['guobu_info']['guobu_type'][] = 0x0200; // 需校验IMEI码
                                        }
                                        break;
                                    }
                                }
                                $this->_ordersdf['guobu_info']['expProps'][$_object['oid']][] = $_item['extend_item_list']['expProps'];
                            }
                        }
                    }
                }
            }
        }
        // 百补超级半托
        $ypdsInfo = array();
        $ypdsOrderSupplyPriceTotal = 0;
        // 用于记录每个 object 的金额汇总
        $objectAmounts = array();
        foreach ($this->_ordersdf['order_objects'] as $k => $_object) {
            $objectAmounts[$k] = array(
                'amount' => 0,
                'sale_price' => 0,
                'divide_order_fee' => 0,
                'pmt_price' => 0,
                'part_mjz_discount' => 0,
            );
            
            foreach ( $_object['order_items'] as $itemKey => $_item ){
                if (isset($_item['extend_item_list']['ypds_order_type']) && $ypdsOrderType = $_item['extend_item_list']['ypds_order_type']) {
                    // 提取百补字段
                    $extendItemList = $_item['extend_item_list'];
                    if (empty($ypdsInfo)) {
                        $ypdsInfo = array(
                            'ypds_platform_order_id' => isset($extendItemList['ypds_platform_order_id']) ? $extendItemList['ypds_platform_order_id'] : '',
                            'ypds_platform_pay_order_id' => isset($extendItemList['ypds_platform_pay_order_id']) ? $extendItemList['ypds_platform_pay_order_id'] : '',
                            'ypds_order_type' => $ypdsOrderType,
                            'ypds_order_supply_price' => isset($extendItemList['ypds_order_supply_price']) ? floatval($extendItemList['ypds_order_supply_price']) : 0,
                        );
                    }
                    
                    // 获取供货总价
                    $ypdsOrderSupplyPrice = isset($extendItemList['ypds_order_supply_price']) ? floatval($extendItemList['ypds_order_supply_price']) : 0;
                    if ($ypdsOrderSupplyPrice > 0) {
                        $ypdsOrderSupplyPriceTotal = bcadd($ypdsOrderSupplyPriceTotal, $ypdsOrderSupplyPrice, 2);
                        
                        // 获取数量
                        $quantity = isset($_object['quantity']) ? floatval($_object['quantity']) : 1;
                        if ($quantity <= 0) $quantity = 1;
                        
                        // 根据文档23-25行规则更新行明细金额
                        // 行单价：price = ypds_order_supply_price / quantity
                        $itemPrice = bcdiv($ypdsOrderSupplyPrice, $quantity, 2);
                        
                        // 行金额：amount = sale_price = divide_order_fee = ypds_order_supply_price
                        // 优惠置零：pmt_price = 0，part_mjz_discount = 0
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['price'] = $itemPrice;
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['amount'] = $ypdsOrderSupplyPrice;
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['sale_price'] = $ypdsOrderSupplyPrice;
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['divide_order_fee'] = $ypdsOrderSupplyPrice;
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['pmt_price'] = 0;
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['part_mjz_discount'] = 0;
                        
                        // 累计 object 层金额
                        $objectAmounts[$k]['amount'] = bcadd($objectAmounts[$k]['amount'], $ypdsOrderSupplyPrice, 2);
                        $objectAmounts[$k]['sale_price'] = bcadd($objectAmounts[$k]['sale_price'], $ypdsOrderSupplyPrice, 2);
                        $objectAmounts[$k]['divide_order_fee'] = bcadd($objectAmounts[$k]['divide_order_fee'], $ypdsOrderSupplyPrice, 2);
                    }
                }
            }
            
            // 更新 object 层金额（汇总所有 items）
            if ($objectAmounts[$k]['amount'] > 0) {
                $objectQuantity = isset($_object['quantity']) ? floatval($_object['quantity']) : 1;
                if ($objectQuantity <= 0) $objectQuantity = 1;
                $objectPrice = bcdiv($objectAmounts[$k]['amount'], $objectQuantity, 2);
                
                $this->_ordersdf['order_objects'][$k]['price'] = $objectPrice;
                $this->_ordersdf['order_objects'][$k]['amount'] = $objectAmounts[$k]['amount'];
                $this->_ordersdf['order_objects'][$k]['sale_price'] = $objectAmounts[$k]['sale_price'];
                $this->_ordersdf['order_objects'][$k]['divide_order_fee'] = $objectAmounts[$k]['divide_order_fee'];
                $this->_ordersdf['order_objects'][$k]['pmt_price'] = 0;
                $this->_ordersdf['order_objects'][$k]['part_mjz_discount'] = 0;
            }
        }
        
        // 如果有百补信息，更新订单级金额和设置相关字段
        if (!empty($ypdsInfo) && $ypdsOrderSupplyPriceTotal > 0) {
            // 设置订单级百补信息
            $this->_ordersdf['ypds_info'] = $ypdsInfo;
            
            // 汇总更新订单级金额
            // cost_item = 汇总所有行的 amount
            $this->_ordersdf['cost_item'] = $ypdsOrderSupplyPriceTotal;
            // total_amount = cost_item（根据文档，订单应收为供货总价汇总）
            $this->_ordersdf['total_amount'] = $ypdsOrderSupplyPriceTotal;
            // payed = total_amount（百补订单按供货价支付）
            $this->_ordersdf['payed'] = $ypdsOrderSupplyPriceTotal;
            
            // 设置关联订单号
            if (!empty($ypdsInfo['ypds_platform_order_id'])) {
                $this->_ordersdf['relate_order_bn'] = $ypdsInfo['ypds_platform_order_id'];
            }
            
            // 组织支付单数据到 payments 数组
            if (!empty($ypdsInfo['ypds_platform_pay_order_id'])) {
                // 初始化 payments 数组（如果不存在）
                if (!isset($this->_ordersdf['payments']) || !is_array($this->_ordersdf['payments'])) {
                    $this->_ordersdf['payments'] = array();
                }
                
                // 获取支付时间
                $payTime = isset($this->_ordersdf['payment_detail']['pay_time']) 
                    ? kernel::single('ome_func')->date2time($this->_ordersdf['payment_detail']['pay_time'])
                    : (isset($this->_ordersdf['paytime']) ? $this->_ordersdf['paytime'] : time());
                
                // 获取支付方式
                $payBn = isset($this->_ordersdf['pay_bn']) ? $this->_ordersdf['pay_bn'] : 'alipay';
                
                // 组织支付单数据
                $paymentData = array(
                    'trade_no' => $ypdsInfo['ypds_platform_pay_order_id'],  // 支付单号
                    'money' => $ypdsOrderSupplyPriceTotal,                    // 支付金额（供货总价）
                    'pay_time' => $payTime,                                   // 支付时间
                    'pay_bn' => $payBn,                                       // 支付方式
                    'paycost' => 0,                                           // 支付手续费
                    'account' => '',                                          // 支付账号
                    'bank' => '',                                             // 银行
                    'pay_account' => '',                                      // 支付账户
                    'paymethod' => '',                                         // 支付方法
                    'memo' => '百补超级半托订单支付',                          // 备注
                    'op_name' => isset($this->_ordersdf['payment_detail']['op_name']) 
                        ? $this->_ordersdf['payment_detail']['op_name'] 
                        : $this->__channelObj->channel['node_type'],
                );
                
                // 添加到 payments 数组
                $this->_ordersdf['payments'][] = $paymentData;
            }
        }
        
        // 产地优选（超链）订单处理
        $superlinkInfo = array();
        $superlinkOrderSupplyPriceTotal = 0;
        $superlinkPlatformPayment = 0;
        // 用于记录每个 object 的金额汇总
        $superlinkObjectAmounts = array();
        
        // 先从订单级 extend_field 提取订单级字段
        $extendField = isset($this->_ordersdf['extend_field']) ? $this->_ordersdf['extend_field'] : array();
        $superlinkPlatformOrderId = '';
        $superlinkAlipayNo = '';
        if (is_array($extendField)) {
            $superlinkPlatformOrderId = isset($extendField['superlink_platform_order_id']) ? $extendField['superlink_platform_order_id'] : '';
            $superlinkAlipayNo = isset($extendField['superlink_alipay_no']) ? $extendField['superlink_alipay_no'] : '';
            if (isset($extendField['superlink_platform_payment'])) {
                $superlinkPlatformPayment = floatval($extendField['superlink_platform_payment']);
            }
        }
        
        // 如果订单级没有，尝试从行级提取
        if (empty($superlinkPlatformOrderId)) {
            foreach ($this->_ordersdf['order_objects'] as $k => $_object) {
                foreach ($_object['order_items'] as $itemKey => $_item) {
                    if (isset($_item['extend_item_list']['superlink_platform_order_id']) && !empty($_item['extend_item_list']['superlink_platform_order_id'])) {
                        $superlinkPlatformOrderId = $_item['extend_item_list']['superlink_platform_order_id'];
                        break 2;
                    }
                }
            }
        }
        
        // 判断是否为产地优选订单
        if (!empty($superlinkPlatformOrderId)) {
            foreach ($this->_ordersdf['order_objects'] as $k => $_object) {
                $superlinkObjectAmounts[$k] = array(
                    'amount' => 0,
                    'sale_price' => 0,
                    'divide_order_fee' => 0,
                    'pmt_price' => 0,
                    'part_mjz_discount' => 0,
                );
                
                foreach ($_object['order_items'] as $itemKey => $_item) {
                    $extendItemList = isset($_item['extend_item_list']) ? $_item['extend_item_list'] : array();
                    
                    // 从行级提取字段
                    $superlinkPlatformSubOrderId = isset($extendItemList['superlink_platform_sub_order_id']) ? $extendItemList['superlink_platform_sub_order_id'] : '';
                    $superlinkOrderSupplyPrice = isset($extendItemList['superlink_order_supply_price']) ? floatval($extendItemList['superlink_order_supply_price']) : 0;
                    $superlinkItemPayment = isset($extendItemList['superlink_platform_payment']) ? floatval($extendItemList['superlink_platform_payment']) : 0;
                    
                    // 如果订单级没有实付金额，尝试从行级获取
                    if ($superlinkPlatformPayment <= 0 && $superlinkItemPayment > 0) {
                        $superlinkPlatformPayment = $superlinkItemPayment;
                    }
                    
                    // 初始化订单级信息（只初始化一次）
                    if (empty($superlinkInfo)) {
                        $superlinkInfo = array(
                            'superlink_platform_order_id' => $superlinkPlatformOrderId,
                            'superlink_platform_sub_order_id' => $superlinkPlatformSubOrderId,
                            'superlink_platform_payment' => $superlinkPlatformPayment > 0 ? $superlinkPlatformPayment : 0,
                            'superlink_alipay_no' => $superlinkAlipayNo,
                            'superlink_order_supply_price' => 0, // 订单级汇总后设置
                        );
                    }
                    
                    // 如果有供货单价，进行金额计算
                    if ($superlinkOrderSupplyPrice > 0) {
                        // 获取数量
                        $quantity = isset($_object['quantity']) ? floatval($_object['quantity']) : 1;
                        if ($quantity <= 0) $quantity = 1;
                        
                        // 行金额计算：amount = sale_price = divide_order_fee = superlink_order_supply_price * quantity
                        $itemAmount = bcmul($superlinkOrderSupplyPrice, $quantity, 2);
                        
                        // 累计订单级供货总价
                        $superlinkOrderSupplyPriceTotal = bcadd($superlinkOrderSupplyPriceTotal, $itemAmount, 2);
                        
                        // 更新行明细金额
                        // 行单价：price = superlink_order_supply_price
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['price'] = $superlinkOrderSupplyPrice;
                        // 行金额：amount = sale_price = divide_order_fee = superlink_order_supply_price * quantity
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['amount'] = $itemAmount;
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['sale_price'] = $itemAmount;
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['divide_order_fee'] = $itemAmount;
                        // 优惠置零：pmt_price = 0，part_mjz_discount = 0
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['pmt_price'] = 0;
                        $this->_ordersdf['order_objects'][$k]['order_items'][$itemKey]['part_mjz_discount'] = 0;
                        
                        // 累计 object 层金额
                        $superlinkObjectAmounts[$k]['amount'] = bcadd($superlinkObjectAmounts[$k]['amount'], $itemAmount, 2);
                        $superlinkObjectAmounts[$k]['sale_price'] = bcadd($superlinkObjectAmounts[$k]['sale_price'], $itemAmount, 2);
                        $superlinkObjectAmounts[$k]['divide_order_fee'] = bcadd($superlinkObjectAmounts[$k]['divide_order_fee'], $itemAmount, 2);
                    }
                }
                
                // 更新 object 层金额（汇总所有 items）
                if ($superlinkObjectAmounts[$k]['amount'] > 0) {
                    $objectQuantity = isset($_object['quantity']) ? floatval($_object['quantity']) : 1;
                    if ($objectQuantity <= 0) $objectQuantity = 1;
                    $objectPrice = bcdiv($superlinkObjectAmounts[$k]['amount'], $objectQuantity, 2);
                    
                    $this->_ordersdf['order_objects'][$k]['price'] = $objectPrice;
                    $this->_ordersdf['order_objects'][$k]['amount'] = $superlinkObjectAmounts[$k]['amount'];
                    $this->_ordersdf['order_objects'][$k]['sale_price'] = $superlinkObjectAmounts[$k]['sale_price'];
                    $this->_ordersdf['order_objects'][$k]['divide_order_fee'] = $superlinkObjectAmounts[$k]['divide_order_fee'];
                    $this->_ordersdf['order_objects'][$k]['pmt_price'] = 0;
                    $this->_ordersdf['order_objects'][$k]['part_mjz_discount'] = 0;
                }
            }
            
            // 如果有产地优选信息，更新订单级金额和设置相关字段
            if (!empty($superlinkInfo) && $superlinkOrderSupplyPriceTotal > 0) {
                // 更新订单级供货总价
                $superlinkInfo['superlink_order_supply_price'] = $superlinkOrderSupplyPriceTotal;
                
                // 设置订单级产地优选信息
                $this->_ordersdf['superlink_info'] = $superlinkInfo;
                
                // 汇总更新订单级金额
                // cost_item = total_amount = payed = 汇总所有行的 divide_order_fee
                $this->_ordersdf['cost_item'] = $superlinkOrderSupplyPriceTotal;
                $this->_ordersdf['total_amount'] = $superlinkOrderSupplyPriceTotal;
                // 实付金额：使用 superlink_platform_payment（如果存在），否则使用汇总的供货价
                $this->_ordersdf['payed'] = $superlinkPlatformPayment > 0 ? $superlinkPlatformPayment : $superlinkOrderSupplyPriceTotal;
                
                // 设置关联订单号
                if (!empty($superlinkInfo['superlink_platform_order_id'])) {
                    $this->_ordersdf['relate_order_bn'] = $superlinkInfo['superlink_platform_order_id'];
                }
                
                // 组织支付单数据到 payments 数组
                if (!empty($superlinkInfo['superlink_alipay_no'])) {
                    // 初始化 payments 数组（如果不存在）
                    if (!isset($this->_ordersdf['payments']) || !is_array($this->_ordersdf['payments'])) {
                        $this->_ordersdf['payments'] = array();
                    }
                    
                    // 获取支付时间
                    $payTime = isset($this->_ordersdf['payment_detail']['pay_time']) 
                        ? kernel::single('ome_func')->date2time($this->_ordersdf['payment_detail']['pay_time'])
                        : (isset($this->_ordersdf['paytime']) ? $this->_ordersdf['paytime'] : time());
                    
                    // 获取支付方式
                    $payBn = isset($this->_ordersdf['pay_bn']) ? $this->_ordersdf['pay_bn'] : 'alipay';
                    
                    // 支付金额：使用 superlink_platform_payment（如果存在），否则使用汇总的供货价
                    $paymentAmount = $superlinkPlatformPayment > 0 ? $superlinkPlatformPayment : $superlinkOrderSupplyPriceTotal;
                    
                    // 组织支付单数据
                    $paymentData = array(
                        'trade_no' => $superlinkInfo['superlink_alipay_no'],  // 支付单号（支付宝单号）
                        'money' => $paymentAmount,                            // 支付金额
                        'pay_time' => $payTime,                                // 支付时间
                        'pay_bn' => $payBn,                                    // 支付方式
                        'paycost' => 0,                                        // 支付手续费
                        'account' => '',                                       // 支付账号
                        'bank' => '',                                          // 银行
                        'pay_account' => '',                                   // 支付账户
                        'paymethod' => '',                                      // 支付方法
                        'memo' => '产地优选超链订单支付',                       // 备注
                        'op_name' => isset($this->_ordersdf['payment_detail']['op_name']) 
                            ? $this->_ordersdf['payment_detail']['op_name'] 
                            : $this->__channelObj->channel['node_type'],
                    );
                    
                    // 添加到 payments 数组
                    $this->_ordersdf['payments'][] = $paymentData;
                }
            }
        }
    }

        /**
     * business_flow
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */

    public function business_flow($sdf)
    {
        if ($sdf['t_type'] == 'fenxiao' || $sdf['order_source'] == 'taofenxiao') {
            $order_type = 'b2b';
        } else {
            $order_type = 'b2c';
        }

        return 'erpapi_shop_matrix_tmall_response_order_'.$order_type;
    }


    protected function _operationSel()
    {
        parent::_operationSel();

        ///////////////////////////////////////////
        // 解决订单备注没更新(淘宝平台问题，备注修改订单最后时间不变),
        // 同时防止比较明细，失败订单恢复后又重新更新为失败订单
        ///////////////////////////////////////////
        $memochg = false;

        if ($this->_tgOrder) {
            $last_custom_mark = array();$last_mark_text=array();
            $custom_mark = array();
            if ($this->_tgOrder['custom_mark'] && is_string($this->_tgOrder['custom_mark'])) {
                $custom_mark = unserialize($this->_tgOrder['custom_mark']);
            }

            $mark_text = array();
            if ($this->_tgOrder['mark_text'] && is_string($this->_tgOrder['mark_text'])) {
                $mark_text = unserialize($this->_tgOrder['mark_text']);
            }

            foreach ((array) $custom_mark as $key => $value) {
                if ( strstr($value['op_time'], "-") ) $value['op_time'] = strtotime($value['op_time']);

                if ( intval($value['op_time']) > intval($last_custom_mark['op_time']) ) {
                    $last_custom_mark = $value;
                }
            }

            foreach ((array) $mark_text as $key => $value) {
                if ( strstr($value['op_time'], "-") ) $value['op_time'] = strtotime($value['op_time']);

                if ( intval($value['op_time']) > intval($last_mark_text['op_time']) ) {
                    $last_mark_text = $value;
                }
            }

            if ( ($this->_ordersdf['custom_mark'] && $this->_ordersdf['custom_mark'] != $last_custom_mark['op_content']) ||
                 ($this->_ordersdf['mark_text'] && $this->_ordersdf['mark_text'] != $last_mark_text['op_content'] ) ) {
                $memochg = true;
            }
        }

        ///////////////////////////////////////////
        // 解决订单地址修改不更新(淘宝平台问题，订单地址修改最后时间不变),
        ///////////////////////////////////////////
        $lastmodify = kernel::single('ome_func')->date2time($this->_ordersdf['lastmodify']);
        if (!$this->_operationSel && $this->_tgOrder && $lastmodify >= $this->_tgOrder['outer_lastmodify']) {
            $consignee = array();
            $area = $this->_ordersdf['consignee']['area_state'] . '/' . $this->_ordersdf['consignee']['area_city'] . '/' . $this->_ordersdf['consignee']['area_district'];
            kernel::single('ome_func')->region_validate($area);

            $consignee['area']      = $area;
            $consignee['name']      = $this->_ordersdf['consignee']['name'];
            $consignee['addr']      = $this->_ordersdf['consignee']['addr'];
            $consignee['telephone'] = $this->_ordersdf['consignee']['telephone'];
            $consignee['mobile']    = $this->_ordersdf['consignee']['mobile'];

            $diff_consignee = array_diff_assoc(array_filter($consignee), $this->_tgOrder['consignee']);
            if ($diff_consignee) $memochg = true;
        }

        // 即不是更新，也是不是创建,才做这样逻辑判断
        if (!$this->_operationSel && $memochg) {
            $this->_operationSel = 'update';
        }
    }

    /**
     * _canAccept
     * @return mixed 返回值
     */
    public function _canAccept()
    {
        if($this->__channelObj->channel['business_type']=='zx' && in_array($this->_ordersdf['order_source'],array('tbdx','tbjx'))) {
            $this->__apilog['result']['msg'] = '直销店铺不接收分销订单';
            return false;
        }

        if($this->__channelObj->channel['business_type']=='fx' && !in_array($this->_ordersdf['order_source'],array('tbdx','tbjx'))) {
            $this->__apilog['result']['msg'] = '分销店铺不接收直销订单';
            return false;
        }
        $presalesetting = app::get('ome')->getConf('ome.order.presale');

        foreach($this->_ordersdf['order_objects'] as $object){
            if(app::get('presale')->is_installed() && $presalesetting == '1' && $object['zhengji_status']){
                if(in_array($object['zhengji_status'],array('1'))){
                    $this->_accept_unpayed_order = true;
                }


                if (in_array($object['zhengji_status'],array('3'))){//预售订单标识
                    $this->__apilog['result']['msg'] = '征集失败订单不收!';
                    return false;
                }
            }else{
                if (in_array($object['zhengji_status'],array('1','3'))){
                    $this->__apilog['result']['msg'] = '征集中和征集失败订单不收!';
                    return false;
                }
            }

            // if(!empty($object['is_sh_ship']) && $this->_ordersdf['order_type'] != 'platform'){
            //     if($object['is_sh_ship'] == 'true'){
            //         $this->__apilog['result']['msg'] = '菜鸟自动流转订单,不接受';
            //         return false;
            //     }
            // }
        }

        if ($this->_ordersdf['is_sh_ship'] == 'true' && $this->_ordersdf['order_type'] != 'platform') {
            $this->__apilog['result']['msg'] = '菜鸟自动流转订单,不接受';
            return false;
        }

         if(app::get('presale')->is_installed() && $presalesetting == '1' && $this->_ordersdf['order_type'] == 'presale'){
                if(in_array($this->_ordersdf['step_trade_status'],array('FRONT_PAID_FINAL_NOPAID'))){
                    $this->_ordersdf['partpayed'] = 'true';
                    $this->_accept_unpayed_order = true;
                }

        }
        if(($this->_accept_unpayed_order==false && in_array($this->_ordersdf['step_trade_status'],array('FRONT_NOPAID_FINAL_NOPAID','FRONT_PAID_FINAL_NOPAID'))) || ($this->_accept_unpayed_order == true && in_array($this->_ordersdf['step_trade_status'],array('FRONT_NOPAID_FINAL_NOPAID')))){

                $this->__apilog['result']['msg'] = '定金未付尾款未付或定金已付尾款未付订单不接收';
                return false;
        }

        if($this->_ordersdf['other_list']){
            foreach((array) $this->_ordersdf['other_list'] as $val){
                // 淘宝处方类订单
                if($val['type'] == 'rx_audit' && $val['rx_audit_status'] == '0'){
                    $this->__apilog['result']['msg'] = '处方药未审核状态，不接受';
                    return false;
                }
            }
        }

        if (strtolower($this->_ordersdf['is_daixiao']) == 'true') {
            $this->_ordersdf['order_type'] = 'platform';
            $this->_ordersdf['is_sh_ship'] = 'true';
        }

        return parent::_canAccept();
    }

    protected function get_create_plugins()
    {
        $plugins = parent::get_create_plugins();

        $plugins[] = 'tbgift';
        $plugins[] = 'coupon';
        // $plugins[] = 'tbjz';
        $plugins[] = 'tboid';//[拆单]保存淘宝平台的原始属性值
        
        //[翱象系统]获取订单详情
        if($this->_ordersdf['cn_info']['asdp_biz_type'] == 'aox' || $this->_ordersdf['cn_info']['logistics_agreement']['asdp_biz_type'] == 'aox'){
            //$plugins[] = 'orderextend'; //公共类中已经定义
            $plugins[] = 'orderdetial';
        }
        return $plugins;
    }

    protected function get_update_plugins()
    {
        $plugins = parent::get_update_plugins();

        if ( (in_array($this->_tgOrder['order_type'], array('presale'))) 
                && ($this->_tgOrder['pay_status'] == '3' || $this->_tgOrder['total_amount'] != $this->_ordersdf['total_amount'])
                && $this->_tgOrder['cost_item'] == $this->_ordersdf['cost_item'] 
                && $this->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID')
        {
            $plugins[] = 'payment';
            
            if ($this->_ordersdf['cn_info']['asdp_biz_type'] == 'logistics_upgrade') {
                $plugins[] = 'orderextend';
            }
        }
        //判断如果是已完成只更新时间
        if ($this->_ordersdf['status'] == 'finish' && $this->_ordersdf['end_time']>0){
            $plugins = array();
            $plugins[] = 'confirmreceipt';
        }

        if (false === array_search('ordertype', $plugins)) {
            $plugins[] = 'ordertype';
        }
        
        //[翱象系统]获取订单扩展信息
        if($this->_ordersdf['cn_info']['asdp_biz_type'] == 'aox' || $this->_ordersdf['cn_info']['logistics_agreement']['asdp_biz_type'] == 'aox'){
            $plugins[] = 'orderextend';
        }
        return $plugins;
    }

    protected function get_update_components()
    {
        $components = array('markmemo','custommemo','marktype','oversold');
        if($this->_tgOrder['is_delivery'] == 'N') {
            $components[] = 'master';
            $components[] = 'items';
        }
        $rs = app::get('ome')->model('order_extend')->getList('extend_status',array('order_id'=>$this->_tgOrder['order_id']));
        // 如果ERP收货人信息未发生变动时，则更新淘宝收货人信息
        if ($rs[0]['extend_status'] != 'consignee_modified') {
            $components[] = 'consignee';
        }
        if ($this->_ordersdf['pay_status'] != '0' && (($this->_ordersdf['pay_status'] != $this->_tgOrder['pay_status']) || ($this->_ordersdf['shipping']['is_cod'] == 'true' && $this->_ordersdf['status'] == 'dead'))) {
            $refundApply = app::get('ome')->model('refund_apply')->getList('apply_id', array('order_id' => $this->_tgOrder['order_id'], 'status|noequal' => '3'));
            // 如果没有退款申请单，以前端为主
            if (!$refundApply) {
                $components[] = 'master';
            }
        }
        $items_flag = false;
        foreach($this->_ordersdf['order_objects'] as $objects){
            foreach($this->_tgOrder['order_objects'] as $tgobjects){
                if($objects['oid'] == $tgobjects['oid'] && $tgobjects['delete'] == 'false'){
                    if($tgobjects["quantity"] == $objects["quantity"] && $tgobjects["price"] == $objects["price"] && $tgobjects["bn"] != $objects["bn"]){
                        $items_flag = true;
                    }
                }
            }
        }
        if($items_flag && $this->_tgOrder['is_modify']!='true'){
            $components[] = 'items';
        }
        //查看是否预售订单已支付状态
        if(in_array($this->_tgOrder['order_type'], array('presale'))){
            $components[] = 'tbpresale';
        }
        if ( (in_array($this->_tgOrder['order_type'], array('presale'))) 
                && ($this->_tgOrder['pay_status'] == '3' || $this->_tgOrder['total_amount'] != $this->_ordersdf['total_amount'])
                && $this->_tgOrder['cost_item'] == $this->_ordersdf['cost_item'] 
                && $this->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID')
            {
            if(!array_search('master', $components)) $components[] = 'master';
            if($this->_tgOrder['is_modify']!='true') {
                $components[] = 'items';
            }
        }

        if($this->_tgOrder['status']=='finish'){
            $components = [];
        }
        return $components;
    }

    protected function get_convert_components()
    {
        $components = parent::get_convert_components();
        $components[] = 'oversold';
        $components[] = 'tbpresale';
        return $components;
    }

    /**
     * 获取开票信息
     *
     * @return void
     * @author 
     **/
    public function getInvoice()
    {
        $invoiceInfo = kernel::single('erpapi_router_request')
                        ->set('shop', $this->__channelObj->channel['shop_id'])
                        ->invoice_getApplyInfo([
                            'order_bn'=>$this->_ordersdf['order_bn']
                        ]);
        if ($invoiceInfo['rsp'] == 'succ' && $invoiceInfo['data'] && $invoiceInfo['data'][0]['invoice_type'] == 'blue') {
            $invoice = array_shift($invoiceInfo['data']);

            // $this->_ordersdf['tax_no'] = $invoice[''];
            $this->_ordersdf['is_tax'] = 'true';
            $this->_ordersdf['tax_title'] = $invoice['payer_name'];
            $this->_ordersdf['payer_register_no'] = $invoice['payer_register_no'];

            if ($invoice['invoice_kind'] == '2') {
                $this->_ordersdf['invoice_kind'] = '3';
                $this->_ordersdf['value_added_tax_invoice'] = 'true';
            } else {
                $this->_ordersdf['invoice_kind'] = '1';
            }

            $this->_ordersdf['invoice_amount'] = $invoice['invoice_amount'];

            $this->_ordersdf['invoice_phone'] = $invoice['payer_phone'];
            $this->_ordersdf['invoice_address'] = $invoice['payer_address'];

            $this->_ordersdf['invoice_bank_name'] = $invoice['payer_bank'];
            $this->_ordersdf['invoice_bank_account'] = $invoice['payer_bankaccount'];
        }
    }
}
