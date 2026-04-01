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
 * 订单接口处理
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_360buy_response_order extends erpapi_shop_response_order
{
    protected $_update_accept_dead_order = true;
    #平台订单状态
    protected $_sourceStatus = array(
        'WAIT_SELLER_STOCK_OUT' => 'WAIT_SELLER_SEND_GOODS', #等待出库 
        'WAIT_GOODS_RECEIVE_CONFIRM' => 'WAIT_BUYER_CONFIRM_GOODS', #等待确认收货 
        'WAIT_SELLER_DELIVERY' => 'WAIT_SELLER_SEND_GOODS', #等待发货（只适用于海外购商家，含义为“等待境内发货”标签下的订单,非海外购商家无需使用） 
        'POP_ORDER_PAUSE' => 'PAID_FORBID_CONSIGN', # POP暂停 
        'FINISHED_L' => 'TRADE_FINISHED', # 完成 
        'TRADE_CANCELED' => 'TRADE_CLOSED', #取消 
        'LOCKED' => 'PAID_FORBID_CONSIGN', #已锁定 
        'WAIT_SEND_CODE' => 'WAIT_SEND_CODE', #等待发码（LOC订单特有状态） 
        'PAUSE' => 'PAID_FORBID_CONSIGN', #暂停（等待出库之前的状态） 
        'DELIVERY_RETURN' => 'DELIVERY_RETURN', #配送退货 
        'UN_KNOWN' => 'UN_KNOWN', #未知 请联系运营
    );
    
    /**
     * 根据平台业务类型处理业务
     * 
     * @param array $sdf
     * @return string
     */

    public function business_flow($sdf)
    {
        if ($sdf['t_type'] == 'fenxiao') {
            $order_type = 'b2b';
        } else {
            $order_type = 'b2c';
        }
        
        return 'erpapi_shop_matrix_360buy_response_order_'. $order_type;
    }
    
    /**
     * 是否接收订单
     * 
     * @return void
     * @author 
     * */
    protected function _canAccept()
    {
        if ($this->_ordersdf['store_order'] == '京仓订单' && $this->_ordersdf['order_type'] != 'platform') {
            $this->__apilog['result']['msg'] = '京仓订单不接收';
            return false;
        }
        
        // if($this->_ordersdf['other_list']){
        //     foreach((array) $this->_ordersdf['other_list'] as $val){
        //         if($val['type'] == 'store' && $val['store_order'] == '京仓订单'){
        //             $this->__apilog['result']['msg'] = '京仓订单不接收';
        //             return false;
        //         }
        //     }
        // }

        // 一盘货京东订单归为平台自发货
        if ($this->__channelObj->channel['config'] && isset($this->__channelObj->channel['config']['platform_type'])){
            if($this->__channelObj->channel['config']['platform_type'] == 'platform' && $this->__channelObj->channel['config']['order_receive'] != 'yes'){
                
                $this->__apilog['result']['msg'] = '平台自发订单不收';
                return false;
            }
        }

        if (in_array($this->_ordersdf['business_type'], array('23','25'))) {
            $this->__apilog['result']['msg'] = 'LBP|SOPL订单不接收';
            return false;
        }

        if (in_array($this->_ordersdf['business_type'], array ('21','112')) && $this->_ordersdf['store_order'] != '京仓订单') {
            $this->__apilog['result']['msg'] = 'FBP|FCS非京仓订单不接收';
            return false;
        }

        // 商户类型不是SOP
        if ( !in_array($this->__channelObj->channel['addon']['type'], array('SOP','FCS')) ) {
            $this->__apilog['result']['msg'] = '商户类型不是SOP订单不接收';

            return false;
        }
        
        if (preg_match('/^渠道订单号/is', $this->_ordersdf['custom_mark']) || preg_match('/^渠道订单号/is', $this->_ordersdf['buyer_memo'])){
            $this->__apilog['result']['msg'] = '平台渠道订单不接收';
            return false;
        }
        
        $presalesetting = app::get('ome')->getConf('ome.order.presale');

        if(app::get('presale')->is_installed() && $presalesetting == '1' && $this->_ordersdf['order_type'] == 'presale'){
            if(in_array($this->_ordersdf['step_trade_status'],array('FRONT_PAID_FINAL_NOPAID'))){
                $this->_accept_unpayed_order = true;
            }
        }

        if(($this->_accept_unpayed_order == false && in_array($this->_ordersdf['step_trade_status'], array('FRONT_NOPAID_FINAL_NOPAID', 'FRONT_PAID_FINAL_NOPAID'))) || ($this->_accept_unpayed_order == true && in_array($this->_ordersdf['step_trade_status'], array('FRONT_NOPAID_FINAL_NOPAID')))){
            $this->__apilog['result']['msg'] = '定金未付尾款未付或定金已付尾款未付订单不接收';
            return false;
        }
       
        return parent::_canAccept();
    }

    protected function get_update_plugins()
    {
        $plugins = parent::get_update_plugins();

        //判断如果是已完成只更新时间
        if ($this->_ordersdf['status'] == 'finish' && $this->_ordersdf['end_time']>0){
            $plugins = array();
            $plugins[] = 'confirmreceipt';
        }

        if ( (in_array($this->_tgOrder['order_type'], array('presale')))
                && ($this->_tgOrder['pay_status'] == '3' || $this->_tgOrder['total_amount'] != $this->_ordersdf['total_amount'])
                && $this->_tgOrder['cost_item'] == $this->_ordersdf['cost_item']
                && $this->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID')
            {
            $plugins[] = 'payment';
            $plugins[] = 'coupon';
            }
        
        array_push($plugins, 'orderlabels');
       
        return $plugins;
    }

    protected function _analysis()
    {
        $this->_ordersdf['is_delivery'] = 'Y';#默认可以发货
        parent::_analysis();

        if(!$this->_ordersdf['lastmodify']){
            $this->_ordersdf['lastmodify'] = date('Y-m-d H:i:s',time());
        }


        //一盘货京东订单归为平台自发货
        if ($this->__channelObj->channel['config'] && isset($this->__channelObj->channel['config']['platform_type'])){
            if($this->__channelObj->channel['config']['platform_type'] == 'platform'){
                $this->_setPlatformDelivery();
                //保存运单号
                
            }
        }

        foreach ($this->_ordersdf['other_list'] as $value) {
            if ($value['store_order'] == '京仓订单') {
                $this->_ordersdf['store_order'] = '京仓订单';
            }
        }


        if ($sendpayMap = $this->_ordersdf['extend_field']['sendpayMap']) {
            if (is_string($sendpayMap)) {
                $sendpayMap = json_decode($sendpayMap, 1);
            }
            if ($sendpayMap) {
                if ($sendpayMap[0]) {
                    if (is_string($sendpayMap[0]) && !is_numeric($sendpayMap[0])) {
                        $sendpayMap = json_decode($sendpayMap[0], 1);
                    } elseif (is_array($sendpayMap[0])) {
                        $sendpayMap = $sendpayMap[0];
                    }
                }

                // 集运标识转成oms本地标识
                if($sendpayMap['1086'] == '1') {
                    $this->_ordersdf['extend_field']['consolidate_info'] = [
                        'consolidate_type'  => 'SOMS_GNJY',
                        'consolidate_value' => 1, // 小标 中国新疆中转
                    ];
                }
                // sendpaymap845:1属于微信支付先用后付(微信小程序先付后用订单)
                if ($sendpayMap['845'] == '1') {
                    $this->_ordersdf['use_before_payed'] = true;
                    $this->_accept_unpayed_order         = true;
                }

                // 分销订单，发货回写的时候需要加360buy_is_dx=true的参数
                if ($sendpayMap['70'] == '1') {
                    $this->_ordersdf['fenxiao_order'] = true;
                }

                // 国补 start ps:国补金额，在后面处理
                if ($sendpayMap['687']) {

                    $this->_ordersdf['guobu_info'] = ['use_gov_subsidy_new' => true];
                    $this->_ordersdf['guobu_info']['guobu_type'][] = '1'; // 支付立减
                    $this->_ordersdf['guobu_info']['sendpayMap'][] = '687:'.$sendpayMap['687'];

                } elseif ($sendpayMap['1064']) {

                    $this->_ordersdf['guobu_info'] = ['use_gov_subsidy_new' => true];
                    $this->_ordersdf['guobu_info']['guobu_type'][] = '2'; // 下单立减
                    $this->_ordersdf['guobu_info']['sendpayMap'][] = '1064:'.$sendpayMap['1064'];
                }

                if ($sendpayMap['1117'] == '1') {

                    if (!$this->_ordersdf['guobu_info']) {
                        $this->_ordersdf['guobu_info'] = ['use_gov_subsidy_new' => true];
                    }
                    $this->_ordersdf['guobu_info']['guobu_type'][] = '4'; // 一品卖多地
                    $this->_ordersdf['guobu_info']['sendpayMap'][] = '1117:1';
                    
                } elseif ($sendpayMap['1248'] == '1') {

                    if (!$this->_ordersdf['guobu_info']) {
                        $this->_ordersdf['guobu_info'] = ['use_gov_subsidy_new' => true];
                    }
                    $this->_ordersdf['guobu_info']['guobu_type'][] = '8'; // 一店多主体
                    $this->_ordersdf['guobu_info']['sendpayMap'][] = '1248:1';
                }

                // 专项补贴是国补自补业务，属于套着国补的壳，商家自补的行为，特意重置guobu_info
                // popSignMap示例："popSignMap": "{\"11\":\"1\",\"24\":\"2\",\"17\":\"1\",\"19\":\"1\"}"
                if ($this->_ordersdf['extend_field']['popSignMap']) {
                    $popSignMap = $this->_ordersdf['extend_field']['popSignMap'];
                    if (is_string($popSignMap)) {
                        $popSignMap = json_decode($popSignMap, 1);
                    }
                    // popSignMap[24] 对应的 guobu_type 映射
                    $popSignMap24TypeMap = [
                        '2' => ['1024'],      // 专项补贴
                        '4' => [0x2000],      // 纯商家出资
                        '5' => [0x10000],     // 政府和商家混合出资
                    ];
                    if ($popSignMap && isset($popSignMap['24']) && isset($popSignMap24TypeMap[$popSignMap['24']])) {
                        // 重置guobu_info
                        $this->_ordersdf['guobu_info'] = [
                            'use_gov_subsidy_new' => true,
                            'guobu_type' => $popSignMap24TypeMap[$popSignMap['24']]
                        ];
                    }
                }
                // 国补 end
                
                // 预约发货 hold 时间
                if ($this->_ordersdf['cn_info']['appointment_ship_time'] && $sendpayMap['1075'] == '2') {
                    $opPickDate = kernel::single('ome_func')->date2time($this->_ordersdf['cn_info']['appointment_ship_time']);
                    $this->_ordersdf['timing_confirm'] = strtotime(date('Y-m-d 22:00:00',$opPickDate)) - 86400;
                }
            }
        }
        $orderExt = $this->_ordersdf['extend_field']['orderExt'] ? : [];
        if(is_string($orderExt)) {
            $orderExt = json_decode($orderExt, 1);
        }
        
        $paymentDetailList = [];
        if($this->_ordersdf['extend_field']['version'] >= 3){ 
            if($orderExt
                && !isset($orderExt['totalSellerReceivable'])){
                $this->_ordersdf['is_delivery'] = 'N';
            }
            $paymentDetailList = $this->_ordersdf['extend_field']['paymentDetailList'] ? : [];
            if(is_string($paymentDetailList)) {
                $paymentDetailList = json_decode($paymentDetailList, 1);
            }
            foreach ($paymentDetailList as $pv){
                foreach($pv['amountExpands'] as $key => $value) {
                    if($value['type'] == 450 #跨境关税扣减
                        || $value['type'] == 183 #店铺支付营销
                        || $value['type'] == 1044 #商家店铺支付营销限商品
                        || $value['type'] == 1083 #整单-银行营销
                        || $value['type'] == 181 #购物金-权益金（POP承担）
                        || $value['type'] == 179 #购物金-权益金（平台承担）
                        || $value['type'] == 1123 # 国补-京东支付整单配资
                        || $value['type'] == 1124 # 国补-京东支付非整单配资
                        //|| $value['type'] == 178 #购物金-本金
                    ) {
                        $this->_ordersdf['total_amount'] = sprintf('%.2f', $this->_ordersdf['total_amount'] - $value['amount']);
                        $this->_ordersdf['payed'] = sprintf('%.2f', $this->_ordersdf['payed'] - $value['amount']);
                        foreach($this->_ordersdf['payments'] as $k => $v) {
                            if($v['money'] >= $value['amount']) {
                                $this->_ordersdf['payments'][$k]['money'] = sprintf('%.2f', $v['money'] - $value['amount']);
                                break;
                            }
                        }
                    }
                    if($value['type'] == 110) {
                        $balanceAmount = 0;
                        // 定义控制变量：初始值为不加
                        $shouldAddAmount = false;
                        foreach ($value['orderCostAmounts'] as $orderCostAmount) {
                            // 条件1：bearer != 6 时可以加
                            if ($orderCostAmount['bearer'] != 6) {
                                $shouldAddAmount = true;
                            }
                            
                            // 条件2：actual_pay == 0 && bearer == 6 时也可以加
                            if ($orderCostAmount['bearer'] == 6 && isset($this->_ordersdf['actual_pay']) && $this->_ordersdf['actual_pay'] == 0) {
                                $shouldAddAmount = true;
                            }
                            
                            if ($orderCostAmount['bearAmount'] > 0 && $shouldAddAmount) {
                                $this->_ordersdf['total_amount'] = sprintf('%.2f', $this->_ordersdf['total_amount'] + $orderCostAmount['bearAmount']);
                                $this->_ordersdf['payed']        = sprintf('%.2f', $this->_ordersdf['payed'] + $orderCostAmount['bearAmount']);
                                $balanceAmount                   = sprintf('%.2f', $balanceAmount + $orderCostAmount['bearAmount']);
                            }
                        }
                        if ($balanceAmount > 0) {
                            $this->_ordersdf['payments'][] = [
                                "money"     => $balanceAmount,
                                "paymethod" => '余额'
                            ];
                        }
                    }

                    // bearer = 5 国补立减,也需要算在订单总额里
                    //@todo：官方文档：https://open.jd.com/v2/#/doc/scene?listId=2168
                    // type = 244 代表：244-新品国补立减
                    // type = 1080 代表：1080-政府消费券（可开票）
                    if(in_array($value['type'], [244, 1080])) {
                        foreach ($value['orderCostAmounts'] as $orderCostAmount) {

                            if($orderCostAmount['bearer'] == 5 && $orderCostAmount['bearAmount'] > 0) {
                                $this->_ordersdf['total_amount'] = sprintf('%.3f', $this->_ordersdf['total_amount'] + $orderCostAmount['bearAmount']);
                                $this->_ordersdf['payed'] = sprintf('%.3f', $this->_ordersdf['payed'] + $orderCostAmount['bearAmount']);

                                $this->_ordersdf['payments'][] = [
                                    "money" => $orderCostAmount['bearAmount'],
                                    "paymethod" => isset($value['typeName']) ? $value['typeName'] : '国补立减-政府承担',
                                ];
                            }

                        }
                    }
                }
            }
        }else{
            if(isset($this->_ordersdf['extend_field']['paymentDetailList'])){
                $paymentDetailList = $this->_ordersdf['extend_field']['paymentDetailList'];
                if(is_string($paymentDetailList)) {
                    $paymentDetailList = json_decode($paymentDetailList, 1);
                }
            }
        }
        
        // 京东E卡
        if($paymentDetailList){
            foreach ($paymentDetailList as $pdKey => $payDetais)
            {
                if(!isset($payDetais['amountExpands'])){
                    continue;
                }
                
                foreach ($payDetais['amountExpands'] as $payDetailKey => $payDetailInfo)
                {
                    if(isset($payDetailInfo['type']) && $payDetailInfo['type'] == 101){
                        $this->_ordersdf['extend_field']['is_jd_e_card'] = true;
                    }
                }
            }
        }
        
        // 国补金额（下单立减）
        if ($orderExt) {

            // 国补 - 京东供销 - start
            if ($filteredSendpay = $orderExt['filteredSendpay']) {
                if (is_string($filteredSendpay)) {
                    $filteredSendpay = preg_replace('/(\d+):/u', '"$1":', $filteredSendpay); // {1064:"82",1117:"1"}修正为{"1064":"82","1117":"1"}，否则json解不开

                    $filteredSendpay = json_decode($filteredSendpay, 1);
                }
                if ($filteredSendpay['687']) {
                    if (!$this->_ordersdf['guobu_info']) {
                        $this->_ordersdf['guobu_info'] = ['use_gov_subsidy_new' => true];
                    }
                    $this->_ordersdf['guobu_info']['guobu_type'][] = '1'; // 支付立减
                }
                if ($filteredSendpay['1064']) {
                    if (!$this->_ordersdf['guobu_info']) {
                        $this->_ordersdf['guobu_info'] = ['use_gov_subsidy_new' => true];
                    }
                    $this->_ordersdf['guobu_info']['guobu_type'][] = '2'; // 下单立减
                }
                if ($filteredSendpay['1117']) {
                    if (!$this->_ordersdf['guobu_info']) {
                        $this->_ordersdf['guobu_info'] = ['use_gov_subsidy_new' => true];
                    }
                    $this->_ordersdf['guobu_info']['guobu_type'][] = '4'; // 一品卖多地
                }
                if ($this->_ordersdf['guobu_info']) {
                    $this->_ordersdf['guobu_info']['filteredSendpay'] = $filteredSendpay;
                }
            }
            // 国补 - 京东供销 - end

            if ($this->_ordersdf['guobu_info']) {

                if ($govSubsidyInfo = $orderExt['govSubsidyInfo']){
                    if (is_string($govSubsidyInfo)) {
                        $govSubsidyInfo = json_decode($govSubsidyInfo, 1);
                    }
                    $this->_ordersdf['guobu_info']['gov_subsidy_amount_new'] = $govSubsidyInfo['govSubsidyAmount'];
                }
                
                // 国补订单拍照规则
                if(isset($orderExt['SnImgRule'])){
                    $SnImgRuleInfo = $orderExt['SnImgRule'];
                    if (is_string($SnImgRuleInfo)) {
                        $SnImgRuleInfo = json_decode($SnImgRuleInfo, true);
                    }
                    
                    // 是否需要拍照
                    if($SnImgRuleInfo['checkImg'] == 1){
                        $this->_ordersdf['guobu_info'] = ['use_gov_subsidy_new' => true];
                        $this->_ordersdf['guobu_info']['guobu_type'][] = 1024; // 需要拍照(十六制数值)
                    }
                }
            }
        }
        
        if(is_array($this->_ordersdf['extend_field']['sendpayMap'])) {
            foreach($this->_ordersdf['extend_field']['sendpayMap'] as $spVal){
                if(is_string($spVal)) {
                    $spVal = json_decode($spVal, 1);
                }

                
                $jdzd = $this->__channelObj->channel['config']['jdzd'];
                if(is_array($spVal) && $spVal['987'] == '2' && $this->_ordersdf['ship_status']=='1' && $this->_ordersdf['status']=='active' && $jdzd=='sync') {
                    $this->_ordersdf['is_delivery']= 'N';
                    $this->_ordersdf['ship_status']= 0;
                    $this->_ordersdf['tran_jdzd_flag']= true;

                }
            }
        }

        
        // 如果是微信支付先用后付的，重置支付金额，支付单
        if ($this->_ordersdf['use_before_payed'] == true && $this->_ordersdf['pay_status'] == '0') {
            $this->_ordersdf['payed']          = '0';
            $this->_ordersdf['payments']       = array();
            $this->_ordersdf['payment_detail'] = array();
        }

        if($this->_ordersdf['store_order'] == '京仓订单'){
            $this->_setPlatformDelivery();
        }

        if($this->_ordersdf['order_type'] != 'platform') {
            if($this->_ordersdf['is_yushou'] == 'true' || in_array($this->_ordersdf['trade_type'],array('step'))){
                $this->_ordersdf['order_type'] = 'presale';
            }
        }

        if (0 == floatval($this->_ordersdf['total_amount']) && $this->_ordersdf['custom_mark']){

            if (preg_match('/^售后返修换新/is', $this->_ordersdf['custom_mark']) || preg_match('/^售后上门换新/is', $this->_ordersdf['custom_mark'])){

                preg_match_all('/\d{4,18}/',$this->_ordersdf['custom_mark'],$mark);

                if ($mark[0][0] && $mark[0][1]){
                  
                    $reship_bn = $mark[0][0];
                    $relate_order_bn = $mark[0][1];
                    $relate_order = $this->_getOrderBn(['order_bn'=>$relate_order_bn, 'shop_id'=>$this->__channelObj->channel['shop_id']], $relate_order_money);
                    $this->_ordersdf['platform_order_bn'] = $relate_order['order_bn'] ? : $relate_order_bn;
                    $this->_ordersdf['relate_order_bn'] = $relate_order_bn;
                   
                    //如果是换出订单释放对应冻结
                    if($reship_bn){
                        kernel::single('console_reship')->releaseShopChangeFreeze($reship_bn, $this->__channelObj->channel['shop_id']);
                    }
                    
                    foreach ($this->_ordersdf['order_objects'] as $objkey=>$object) {
                        foreach ($object['order_items'] as $itemkey =>$item) {
                            $bn = $item['bn'];
                            $change_price = $this->format_change_price($relate_order_bn,$reship_bn,$item['bn']);
                            if($change_price['price'] == 0 && $relate_order_money > 0) {
                                $change_price = ['price'=>$relate_order_money];
                            }
                            $sale_price = $change_price['price'];
                            
                            if ($change_price){
                                
                                $pmt_price = $item['price']*$item['quantity']-$sale_price;
                                
                                $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['sale_price'] =$sale_price;
                                $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['divide_order_fee'] =$sale_price;
                                $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['pmt_price']    =$pmt_price;
                                $this->_ordersdf['total_amount'] = $this->_ordersdf['payed'] = $sale_price;
                            
                                $this->_ordersdf['order_objects'][$objkey]['sale_price'] = $sale_price;
                                $this->_ordersdf['order_objects'][$objkey]['divide_order_fee'] = $sale_price;
                                $this->_ordersdf['order_objects'][$objkey]['pmt_price'] = $pmt_price;
                                $this->_ordersdf['pmt_goods']=$pmt_price;
                                $this->_ordersdf['pmt_order']=0;
                            }
                            
                        }
                    }
                   
                    if($change_price){
                        $this->_ordersdf['payments'][0]['money'] = $this->_ordersdf['payed'];
                    }   
                    $this->_ordersdf['oldchangenew']=true;
                  
                  
                   

                }
                

            }

        }
     
        $trade_refunding = false;
        if($this->_ordersdf['is_yushou'] == 'true' || in_array($this->_ordersdf['trade_type'],array('step'))){
            $order_type = 'presale';
        }

    


        if ($this->_ordersdf['cn_info']['es_date']) {
            $this->_ordersdf['consignee']['r_time'] = kernel::single('ome_func')->date2time($this->_ordersdf['cn_info']['es_date']);
        }
        $modifyTotalAmount = 0;
        if($this->_ordersdf['platform_discount_fee'] > 0) {
            $modifyTotalAmount = $this->_ordersdf['platform_discount_fee'];
            $this->_ordersdf['pmt_goods'] = sprintf('%.2f', $this->_ordersdf['pmt_goods'] - $modifyTotalAmount);
            $this->_ordersdf['total_amount'] = sprintf('%.3f', $this->_ordersdf['total_amount'] + $modifyTotalAmount);
            $this->_ordersdf['payed'] = sprintf('%.3f', $this->_ordersdf['payed'] + $modifyTotalAmount);
            $this->_ordersdf['payments'][] = [
                "money" => $modifyTotalAmount,
                "paymethod" => '平台承担'
            ];
            $couponData = $this->_ordersdf['coupon_field'] ? : [];
            $couponItemsPmt = [];
            foreach ($couponData as $key => $value)
            {
                $oid = $value['sku_uuid'];
                $pmt_info = $value['pmt_info'];
                foreach ($pmt_info as $k => $v)
                {
                    if ($v['coupon_type'] != 1) {
                        continue;
                    }
                    $couponItemsPmt[$oid] += $v['pmt_amount'];
                }
            }
        }
        if($this->_ordersdf['extend_field']['version'] >= 3
        && in_array($this->_ordersdf['status'], ['active','finish'])
        && $orderExt['totalSellerReceivable'] != 0 
        && sprintf('%.2f', $orderExt['totalSellerReceivable']) != sprintf('%.2f', $this->_ordersdf['total_amount'] - $this->_ordersdf['shipping']['cost_shipping'])) {
            $this->_ordersdf['is_delivery'] = 'N';
        }
        //获取货号
        foreach ($this->_ordersdf['order_objects'] as &$object) {
            //京东预约发货设置hold时间
            if ($this->_ordersdf['timing_confirm']) {
                $object['estimate_con_time'] = (int)$this->_ordersdf['timing_confirm'];
            }

            foreach ($object['order_items'] as &$item) {
                if($item['extend_item_list']) {
                    $extend_item_list = $item['extend_item_list'];
                    if(is_array($extend_item_list) && $extend_item_list['skuUuid']) {
                        $object['sku_uuid'] = $extend_item_list['skuUuid'];
                    }
                }
                if($couponItemsPmt && $couponItemsPmt[$object['sku_uuid']]){
                    $object['pmt_price'] = sprintf('%.2f', $object['pmt_price'] - $couponItemsPmt[$object['sku_uuid']]);
                    $object['sale_price'] = sprintf('%.2f', $object['sale_price'] + $couponItemsPmt[$object['sku_uuid']]);
                    $item['pmt_price'] = sprintf('%.2f', $item['pmt_price'] - $couponItemsPmt[$object['sku_uuid']]);
                    $item['sale_price'] = sprintf('%.2f', $item['sale_price'] + $couponItemsPmt[$object['sku_uuid']]);
                }
                if ($this->_ordersdf['timing_confirm']) {
                    $item['estimate_con_time'] = (int)$this->_ordersdf['timing_confirm'];
                }
                //货号不存在
                $sku   = array();
                if (empty($item['bn'])) {
                    $sku   = $this->item_get($item['shop_product_id']);

                    if ($sku['sku'] && $sku['sku']['outer_id']) {
                        //货号
                        $item['bn']   = $sku['sku']['outer_id'];
                        $object['bn'] = $sku['sku']['outer_id'];
                    }
                }

                if ($item['status'] == 'refund') {
                    $trade_refunding = true;
                }

                // 京东预售订单 divide_order_fee 没有的话 计算一下
                if ($item['divide_order_fee'] == 0 && $order_type == 'presale') {
                    // 预售的只有一种物料 满减优惠 直接等于订单促销优惠 
                    if ($item['part_mjz_discount'] == 0) {
                        $item['part_mjz_discount'] = $this->_ordersdf['pmt_order'];
                    }
                    $item['divide_order_fee'] = $item['sale_price'] - $item['part_mjz_discount'];
                }

            }
        }

        if ($trade_refunding) {
            $this->_ordersdf['pay_status'] = '6';
        }

        if ($this->_ordersdf['return_insurance_fee']){
            //service_order_objects
            $service_order = array();
            $service_order[] = array(
                'sale_price'    =>  $this->_ordersdf['return_insurance_fee'],
                'num'           =>  1,
                'total_fee'     =>  $this->_ordersdf['return_insurance_fee'],
                'title'         =>  '退换货无忧',


            );
            if ($service_order){
               $this->_ordersdf['service_order_objects']['service_order'] = $service_order;
            }

        }
        if ($this->_ordersdf['status'] == 'finish' && $this->_ordersdf['ship_status'] == '0') $this->_ordersdf['status'] = 'active';

        // 加密字段处理
        $hashCode = kernel::single('ome_security_hash')->get_code();
        
        //invoice
        if ($this->_ordersdf['index_field']['invoice_bank_account_index']) {
            $this->_ordersdf['invoice_bank_account'] .= $hashCode;
        }
        if ($this->_ordersdf['index_field']['invoice_buyer_address_index']) {
            $this->_ordersdf['invoice_buyer_address'] .= $hashCode;
        }
        if ($this->_ordersdf['index_field']['invoice_buyer_phone_index']) {
            $this->_ordersdf['invoice_buyer_phone'] .= $hashCode;
        }
    }
    protected function _getOrderBn($filter, &$relate_order_money) {
        $field = 'order_id, shop_id, order_bn, createway, relate_order_bn,total_amount';
        $order = app::get('ome')->model('orders')->db_dump($filter, $field);
        if(empty($order)) {
            $order = app::get('archive')->model('orders')->db_dump($filter, $field);
        }
        $billLabel = kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', 'SOMS_OLDCHANGENEW', $error_msg);
        if($billLabel && $order['relate_order_bn']) {
            $relate_order_money = $order['total_amount'];
            return $this->_getOrderBn(['order_bn'=>$order['relate_order_bn'],'shop_id'=>$order['shop_id']], $relate_order_money);
        }
        return $order;
    }
    protected function _createAnalysis(){
        parent::_createAnalysis();
	    if($this->_ordersdf['extend_field']['version'] >= 3) {
            // 订单obj明细唯一标识
            $this->object_comp_key = 'bn-oid-obj_type';
            foreach ($this->_ordersdf['order_objects'] as &$object) {
                if($object['sku_uuid']) {
                    $object['oid'] = $object['sku_uuid'];
                }
            }
        }
        //保存优惠明细
        $this->getCouponDetailParamsFormat();
    }

    protected function _operationSel()
    {
        parent::_operationSel();

        if ($this->_tgOrder) $this->_operationSel = 'update';
        if($this->_operationSel == 'update'){
            if ($this->_ordersdf['status'] == 'dead' && $this->_tgOrder['status']=='active' &&  $this->_ordersdf['pay_status']=='5' && $this->_tgOrder['pay_status']=='4' && $this->_ordersdf['ship_status']=='0'){
                $this->_operationSel = 'close';
                
            }
            
        }
    }

    protected function get_update_components()
    {
        $components = array('markmemo');
        if($this->_tgOrder['is_delivery'] == 'N' && $this->_tgOrder['pay_status'] == '1') {
            $components[] = 'master';
            $components[] = 'items';
        }

        if($this->_tgOrder){
            $rs = app::get('ome')->model('order_extend')->getList('extend_status',array('order_id'=>$this->_tgOrder['order_id']));
            // 如果ERP收货人信息未发生变动时，则更新京东收货人信息
            if ($rs[0]['extend_status'] != 'consignee_modified') {
                $orRe = app::get('ome')->model('order_receiver')->db_dump(['order_id'=>$this->_tgOrder['order_id']], 'encrypt_source_data');
                $ensd = json_decode($orRe['encrypt_source_data'], 1);
                if(empty($ensd['oaid']) || $ensd['oaid'] != $this->_ordersdf['extend_field']['oaid']) {
                    $components[] = 'consignee';
                }
                
            }
        }

        if ( ($this->_ordersdf['pay_status'] != $this->_tgOrder['pay_status']) ||($this->_ordersdf['shipping']['is_cod']=='true' && $this->_ordersdf['status'] == 'dead') ) {
            $refundApply = app::get('ome')->model('refund_apply')->getList('apply_id',array('order_id'=>$this->_tgOrder['order_id'],'status|noequal'=>'3'));
            // 如果没有退款申请单，以前端为主
            if (!$refundApply) {
                $components[] = 'master';
            }
        }

        //查看是否预售订单已支付状态
        if(in_array($this->_tgOrder['order_type'], array('presale'))){
            $components[] = 'tbpresale';
            if ( ($this->_tgOrder['pay_status'] == '3' || $this->_tgOrder['total_amount'] != $this->_ordersdf['total_amount']) && $this->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID' )
                {
                if(!array_search('master', $components)) $components[] = 'master';
                $components[] = 'items';
            }
        }

        if($this->_tgOrder['status']=='finish'){
            $components = [];
        }
        return $components;
    }


    protected function item_get($sku_id)
    {
        if (empty($sku_id)) {
            return array();
        }

        $rs = kernel::single('erpapi_router_request')->set('shop',$this->__channelObj->channel['shop_id'])->product_item_sku_get(array('sku_id'=>$sku_id));

        if ($rs['rsp'] == 'fail' || !$rs['data']) return array();

        return $rs['data'];
    }

    protected function _canUpdate()
    {
        
        if ($this->_tgOrder['order_type'] == 'presale' && empty($this->_ordersdf['step_trade_status'])) {
            $this->__apilog['result']['msg'] = '预售订单中间以普通订单下来的不接收';
            return false;
        }

        return parent::_canUpdate();
    }
    
    protected function _updateAnalysis()
    {
        parent::_updateAnalysis();
        if($this->_tgOrder['api_version'] >= 3) {
            // 订单obj明细唯一标识
            $this->object_comp_key = 'bn-oid-obj_type';
            foreach ($this->_ordersdf['order_objects'] as &$object) {
                if($object['sku_uuid']) {
                    $object['oid'] = $object['sku_uuid'];
                }
            }
        }
        //入手付尾款后保存优惠明细
        if (in_array($this->_tgOrder['order_type'], array('presale')) && $this->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID') {
            $this->getCouponDetailParamsFormat();
        }

        // 更新订单的时候先清理当前订单的集运标识
        $order_id = $this->_tgOrder['order_id'];
        $omsConsolidateType = kernel::single('ome_bill_label')->consolidateTypeBox;
        $labelAll = app::get('omeauto')->model('order_labels')->getList('*', ['label_code|in'=>$omsConsolidateType]);
        if ($labelAll) {
            $labelAll = array_column($labelAll, 'label_id');
            kernel::single('ome_bill_label')->delLabelFromBillId($order_id, $labelAll, 'order', $error_msg);
        }
    }

     function format_change_price($order_bn,$reship_bn,$bn){


        //$this->__channelObj->channel['shop_id']
        $returnObj = app::get('ome')->model('return_product');
        $order_detail = app::get('ome')->model('orders')->db_dump(array('order_bn'=>$order_bn,'shop_id'=>$this->__channelObj->channel['shop_id']),'order_id');
        if (!$order_detail) {
            $order_detail = app::get('archive')->model('orders')->db_dump(array('order_bn'=>$order_bn,'shop_id'=>$this->__channelObj->channel['shop_id']),'order_id');
            if (!$order_detail){
                return false;
            }
            $order_items = $returnObj->db->select("SELECT bn FROM sdb_archive_order_items WHERE order_id=".$order_detail['order_id']);
        }else{
            $order_items = $returnObj->db->select("SELECT bn FROM sdb_ome_order_items WHERE order_id=".$order_detail['order_id']);
        }

        $bn_list = array_map('current',$order_items);

        $order_id = $order_detail['order_id'];
        $return_detail = $returnObj->db_dump(array('return_bn'=>$reship_bn,'order_id'=>$order_id),'return_id,refundmoney');
        if (!$return_detail) return false;

        //$item = $returnObj->db->selectrow("SELECT price FROM sdb_ome_return_product_items WHERE return_id=".$return_detail['return_id']." AND bn in ('".implode('\'\'',$bn_list)."')");
        $item['price'] = $return_detail['refundmoney'];
        return $item;


    }

    protected function _canCreate()
    {
        if ($this->_ordersdf['source_status'] == 'LOCKED') {
            $this->__apilog['result']['msg'] = '平台锁定订单不接收';
            return false;
        }

        return parent::_canCreate();
    }
    

    protected function get_convert_components()
    {
        $components = parent::get_convert_components();
     
        $components[] = 'tbpresale';
        return $components;
    }

    //创建订单的插件
    protected function get_create_plugins()
    {
        $plugins = parent::get_create_plugins();
        //订单优惠明细数据
        if ($this->_ordersdf['is_yushou'] != 'true' || $this->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID') {
            array_push($plugins, 'coupon');
        }
        array_push($plugins, 'platsplit');
        array_push($plugins, 'orderlabels');
    
        return $plugins;
    }
    
    protected function getCouponDetailParamsFormat()
    {
        if($this->_ordersdf['extend_field']['version'] >= 3) {
            $this->_ordersdf['coupon_oid_field'] = 'sku_uuid';
            if (isset($this->_ordersdf['coupon_field']) && is_array($this->_ordersdf['coupon_field'])) {
                // 调用自定义的service来修改$coupon
                foreach (kernel::servicelist('erpapi.service.360buy.order.coupon.format') as $service) {
                    if (method_exists($service, 'format_coupon')) {
                        $service->format_coupon($this, $this->_ordersdf['coupon_field']);
                    }
                }
                // 先构建sku_uuid到实付金额的映射
                $skuCalcActuallyPayMap = [];
                foreach ($this->_ordersdf['coupon_field'] as $cf) {
                    if (isset($cf['sku_uuid']) && isset($cf['calcActuallyPay'])) {
                        $skuCalcActuallyPayMap[$cf['sku_uuid']] += $cf['calcActuallyPay'];
                    }
                }
                // 将实付金额写入到order_objects
                if (!empty($skuCalcActuallyPayMap) && isset($this->_ordersdf['order_objects']) && is_array($this->_ordersdf['order_objects'])) {
                    foreach ($this->_ordersdf['order_objects'] as $k => $object) {
                        if (isset($object['sku_uuid']) && isset($skuCalcActuallyPayMap[$object['sku_uuid']])) {
                            $this->_ordersdf['order_objects'][$k]['actually_amount'] = $skuCalcActuallyPayMap[$object['sku_uuid']];
                        }
                    }
                }
            }
        } else {
            //矩阵返回优惠数据不请求接口，否则请求接口
            $ext_data                   = array();
            $ext_data['order_objects']  = $this->_ordersdf['order_objects'];
            $ext_data['shop_id']        = $this->__channelObj->channel['shop_id'];
            $ext_data['shop_type']      = $this->__channelObj->channel['shop_type'];
            $ext_data['payment_detail'] = $this->_ordersdf['payment_detail'];
            $ext_data['createtime']     = $this->_ordersdf['createtime'];
            $ext_data['order_bn']       = $this->_ordersdf['order_bn'];
            $ext_data['coupon_source']  = 'rpc';
            if (isset($this->_ordersdf['coupon_field']) && !empty($this->_ordersdf['coupon_field'])) {
                //优惠明细数据format
                $ext_data['coupon_source']  = 'push';
                $result = kernel::single('ome_order_coupon')->couponDataFormat($this->_ordersdf['coupon_field'], $ext_data);
            } else {
                //获取订单优惠明细数据
                $result = kernel::single('erpapi_router_request')
                    ->set('shop', $this->__channelObj->channel['shop_id'])
                    ->order_couponDetailGet($this->_ordersdf['order_bn'], $ext_data);
                if ($result['rsp'] != 'succ') {
                    return false;
                }
            }
            $this->_ordersdf['coupon_data']         = $result['coupon_data'];
            $this->_ordersdf['objects_coupon_data'] = $result['objects_coupon_data'];
        
            // 订单实付金额与优惠金额,sum 实付金额 + 平台付款总价 等于total_amount进行赋值
            $actualPayment = 0;
            foreach ($result['price_list'] as $key => $value) {
                // 按实付升序排
                usort($value,function($a,$b){
                    if ($a['divide_order_fee'] == $b['divide_order_fee']) {
                        return 0;
                    }
                    return ($a['divide_order_fee'] < $b['divide_order_fee']) ? -1 : 1;
                });
            
                $result['price_list'][$key] = $value;
            
                $actualPayment += array_sum(array_column($value, 'divide_order_fee'))
                    + array_sum(array_column($value, 'cost_freight'));
            }
        
            if ($this->_ordersdf['total_amount'] == $actualPayment && empty($result['divide_order_fee_zero'])) {
                $order_objects = $this->_ordersdf['order_objects'];
            
                // 按销售价升序排
                usort($order_objects,function($a,$b){
                    if ($a['sale_price'] == $b['sale_price']) {
                        return 0;
                    }
                    return ($a['sale_price'] < $b['sale_price']) ? -1 : 1;
                });
            
                $price_list = $result['price_list'];

                $totalDivideOrderFee = $totalPartMjzDiscount = 0;
                foreach ($order_objects as $objKey => $objVal) {
                    $totalDivideOrderFee += (float)$objVal['divide_order_fee'];
                    $totalPartMjzDiscount += (float)$objVal['part_mjz_discount'];


                    $divideOrderFeeInfo = is_array($price_list[$objVal['oid'].'-'.$objVal['quantity']]) ? array_shift($price_list[$objVal['oid'].'-'.$objVal['quantity']]) : 0;
                    if (!$divideOrderFeeInfo){
                        continue;
                    }
                
                    $order_objects[$objKey]['divide_order_fee'] = $divideOrderFeeInfo['divide_order_fee'];
                    $order_objects[$objKey]['part_mjz_discount'] = $objVal['sale_price'] - $divideOrderFeeInfo['divide_order_fee'];
                
                    foreach ($objVal['order_items'] as $itemKey => $itemVal) {
                        $order_objects[$objKey]['order_items'][$itemKey]['divide_order_fee'] = $divideOrderFeeInfo['divide_order_fee'];
                        $order_objects[$objKey]['order_items'][$itemKey]['part_mjz_discount'] = $itemVal['sale_price'] - $divideOrderFeeInfo['divide_order_fee'];
                    }
                }
            
                // $totalDivideOrderFee  = array_sum(array_column($order_objects, 'divide_order_fee'));
                // $totalPartMjzDiscount = array_sum(array_column($order_objects, 'part_mjz_discount'));
                if (0 == bccomp($totalDivideOrderFee, ($this->_ordersdf['total_amount']-$this->_ordersdf['shipping']['cost_shipping']), 3)
                    && 0 == bccomp(empty($this->_ordersdf['pmt_order']) ? 0 : $this->_ordersdf['pmt_order'], empty($totalPartMjzDiscount) ? 0 : $totalPartMjzDiscount, 3)
                ){
                    $this->_ordersdf['order_objects'] = $order_objects;
                }
        
                if (isset($result['divide_order_fee_mount']) && $result['divide_order_fee_mount'] > 0) {
                    //运费是否开票
                    $invoiceAmount = $result['divide_order_fee_mount'];
                    if ('1' == app::get('ome')->getConf('ome.invoice.amount.infreight')) {
                        $invoiceAmount = $invoiceAmount + $this->_ordersdf['shipping']['cost_shipping'];
                    }
                    $this->_ordersdf['invoice_amount'] = $invoiceAmount;
                }
            }
        }
    }

    /**
     * 取消订单
     * @return boolen
     */
    protected function _closeOrder()
    {
        $split_oid = app::get('ome')->model('order_platformsplit')->getList('obj_id, split_oid', ['order_id'=>$this->_tgOrder['order_id']]);
        if($split_oid) {
            return true;
        }
        return parent::_closeOrder();
    }
}

