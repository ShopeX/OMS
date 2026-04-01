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
 * @author chenping<chenping@shopex.cn>
 * @version $Id: 2013-3-12 17:23Z
 * 订单标签打标
 */
class erpapi_shop_response_plugins_order_orderlabels extends erpapi_shop_response_plugins_order_abstract
{

    public function convert(erpapi_shop_response_abstract $platform)
    {
        $labels = [];

        if ($platform->_ordersdf['extend_field']['sku_order_tag_ui']) {
            foreach ($platform->_ordersdf['extend_field']['sku_order_tag_ui'] as $oid => $skuVal) {
                // 商品单标签，只要有一个商品是顺丰包邮，则整单打顺丰包邮的标签
                foreach ($skuVal as $sk => $sv) {
                    if ($sv['key'] == 'sf_free_shipping') {
                        $labels[] = [
                            'label_code' => 'sf_free_shipping',
                            // 'label_name' => '顺丰包邮',
                        ];
                        break;
                    }
                }
                // 全款预售
                foreach ($skuVal as $sk => $sv) {
                    if ($sv['key'] == 'pre_sale_label') {
                        $labels[] = [
                            'label_code' => 'SOMS_FULLPAY_PRESALE',
                        ];
                        break;
                    }
                }
            }
        }
        //晚发赔
        if(is_array($platform->_ordersdf['extend_field']['sendpayMap'])) {
            foreach($platform->_ordersdf['extend_field']['sendpayMap'] as $spVal){
                if(is_string($spVal)) {
                    $spVal = json_decode($spVal, 1);
                }
                if(is_array($spVal) && $spVal['860'] == '1') {
                    $labels[] = [
                        'label_code' => 'SOMS_WFP',
                    ];
                }
                if (is_array($spVal) && isset($spVal['810']) && $spVal['810'] == '1') {
                    $labels[] = [
                        'label_code' => 'SOMS_GXD',
                    ];
                }
            }
        }
        //imei sn
        if(is_array($platform->_ordersdf['extend_field']['serialNumberInfo'])
            && is_array($platform->_ordersdf['extend_field']['serialNumberInfo']['serialType'])) {
            foreach($platform->_ordersdf['extend_field']['serialNumberInfo']['serialType'] as $val){
                if($val == '1') {
                    $labels[] = [
                        'label_code' => 'SOMS_SERIALNUMBER',
                    ];
                }
                if($val == '2') {
                    $labels[] = [
                        'label_code' => 'SOMS_IMEI',
                    ];
                }
            }
        }
        //送货上门
        if($platform->_ordersdf['extend_field']['delivery_to_home']) {
            $delivery_to_home = 0;
            if($platform->_ordersdf['extend_field']['delivery_to_home'] == '1') {
                $delivery_to_home = 1;
            }
            if($platform->_ordersdf['extend_field']['delivery_to_home'] == '2') {
                $delivery_to_home = 2;
            }
            if($platform->_ordersdf['extend_field']['delivery_to_home'] == '3') {
                $delivery_to_home = 4;
            }
            if ($delivery_to_home) {
                $labels[] = [
                    'label_code'  => 'SOMS_SHSM',
                    'label_value' => $delivery_to_home,
                ];
            }
        }

        // 集运信息打标签
        if ($platform->_ordersdf['extend_field']['consolidate_info']) {
            $consolidate_type   = $platform->_ordersdf['extend_field']['consolidate_info']['consolidate_type'];
            $consolidate_value  = $platform->_ordersdf['extend_field']['consolidate_info']['consolidate_value'];
            $omsConsolidateType = kernel::single('ome_bill_label')->consolidateTypeBox;
            if (in_array($consolidate_type, $omsConsolidateType)) {
                $label = [
                    'label_code' => $consolidate_type,
                ];
                $consolidate_value && $label['label_value'] = $consolidate_value;

                $labels[] = $label;
            }
        }

        // 官方提货物流
        if ($platform->_ordersdf['official_pickup']) {
            $labels[] = [
                'label_code'  => 'SOMS_PICKUP',
                'label_value' => 0x0001,
            ];
        }

        // 京东POP订单，微信支付先用后付打标签
        if ($platform->_ordersdf['use_before_payed']) {
            $labels[] = [
                'label_code' => 'use_before_payed',
            ];
        }

        // 优先发货、使用全新纸箱包装发货、使用礼盒包装发货
        if (is_array($platform->_ordersdf['extend_field']) && $platform->_ordersdf['extend_field']['action_list']) {
            foreach ($platform->_ordersdf['extend_field']['action_list'] as $k => $action_list) {
                if (in_array($action_list['action_code'], array_keys(kernel::single('ome_bill_label')->orderLabelsPreset))) {
                    $labels[] = [
                        // priority_delivery(优先发货) 
                        // newcarton_package(使用全新纸箱包装发货) 
                        // gift_package(使用礼盒包装发货)
                        'label_code' => $action_list['action_code'],
                    ];
                }
            }
        }

        // 重点检查
        $quality_check_type = 0;
        foreach ($platform->_ordersdf['order_objects'] as $k => $order_objects) {
            if (is_array($order_objects['extend_item_list']) && $order_objects['extend_item_list']['quality_check_type']) {
                !$quality_check_type && $quality_check_type = $order_objects['extend_item_list']['quality_check_type'];
                $quality_check_type = $quality_check_type | $order_objects['extend_item_list']['quality_check_type'];
            }
        }
        if ($quality_check_type) {
            $labels[] = [
                'label_code'  => 'quality_check',
                'label_value' => $quality_check_type,
            ];
        }
        //自选快递订单履约发货
        if (is_array($platform->_ordersdf['extend_field']['order_tag']) && isset($platform->_ordersdf['extend_field']['order_tag']['shop_optional_express_info'])) {
            foreach ($platform->_ordersdf['extend_field']['order_tag']['shop_optional_express_info']['ExpressCompanys'] as $val) {
                if(isset($val['ExpressCompanyCode']) && $val['ExpressCompanyCode']){
                    $labels[] = [
                        'label_code' => kernel::single('ome_bill_label')->isExpressMust(),//自选快递
                    ];
                    break;
                }
            }
        }

        // 订单达人
        if (is_array($platform->_ordersdf['extend_field']) && $platform->_ordersdf['extend_field']['is_host']) {
            $labels[] = [
                'label_code'    =>  'SOMS_HOST',
            ];
        }

        // 抽奖
        if (is_array($platform->_ordersdf['extend_field']) && $platform->_ordersdf['extend_field']['is_lucky_flag']) {
            $labels[] = [
                'label_code'    =>  'SOMS_LOTTERY',
            ];
        }


        if($platform->_ordersdf['extend_field']['sendpayMap'] && $platform->_ordersdf['tran_jdzd_flag']){

            foreach($platform->_ordersdf['extend_field']['sendpayMap'] as $spVal){
                if(is_string($spVal)) {
                    $spVal = json_decode($spVal, 1);
                }
                if(is_array($spVal) && $spVal['987'] == '2') {
                    $labels[] = [
                        'label_code' => 'SOMS_JDZD',
                    ];

                }
            }
        }

        //本地仓订单
        if (isset($platform->_ordersdf['trade_type']) && $platform->_ordersdf['trade_type'] && $platform->_ordersdf['trade_type'] == 'auto_delivery') {
            $labels[] = [
                'label_code'    =>  'SOMS_LOCAL_WAREHOUSE',
            ];
        }

         if($platform->_ordersdf['oldchangenew']==true){
            $labels[] = [
                'label_code'    =>  'SOMS_OLDCHANGENEW',
            ];
        }
        
        //定制订单
        if (isset($platform->_newOrder['order_type']) && $platform->_newOrder['order_type'] == 'custom') {
            $labels[] = [
                'label_code' => 'ORDER_CUSTOMS',
            ];
        }

        //清仓订单
        if(!empty($platform->_ordersdf['extend_field']['qn_distr'])) {
            $labels[] = [
                'label_code'    =>  'SOMS_QN_DISTR',
            ];
        }
        
        //送礼订单
        if($platform->_ordersdf['extend_field']['present']['is_present'] == '1') {
            $labels[] = [
                'label_code'    =>  'SOMS_PRESENT',
            ];
        }
        if($platform->_ordersdf['partpayed'] == 'true'){
            $labels[] = [
                'label_code'    =>  'SOMS_PREPAYED',
            ];
        }

        // 国补
        if ($platform->_ordersdf['guobu_info'] && $platform->_ordersdf['guobu_info']['use_gov_subsidy_new']) {

            $guobu_label_value = 0;
            foreach ($platform->_ordersdf['guobu_info']['guobu_type'] as $guobu_type) {
                !$guobu_label_value && $guobu_label_value = $guobu_type;
                $guobu_label_value = $guobu_label_value | $guobu_type;
            }

            $labels[] = [
                'label_code'    =>  'SOMS_GB',
                'label_value'   =>  $guobu_label_value,
                'extend_info'   =>  json_encode($platform->_ordersdf['guobu_info'], JSON_UNESCAPED_UNICODE),
            ];
        }
        
        // 天猫优品国补打标记
        if (isset($platform->_ordersdf['guobu_info']) && isset($platform->_ordersdf['guobu_info']['tmyp_info'])) {
            if($platform->_ordersdf['guobu_info']['tmyp_info']['is_tmyp_order']){
                $labels[] = [
                    'label_code' => 'SOMS_TMYP',
                ];
            }
        }
        
        //微派服务
        if($platform->_ordersdf['extend_field']['order_tag_list']) {
            $order_tag_list = $platform->_ordersdf['extend_field']['order_tag_list'];
            foreach($order_tag_list as $tag){
                if($tag['name']=='has_weipai_service' && $tag['value']==1){
                    $labels[] = [
                        'label_code'    =>  'SOMS_WEIPAI',
                    ];
                }
            }
        }
        
        if(isset($platform->_ordersdf['extend_field']['gift_order_status']) && in_array($platform->_ordersdf['extend_field']['gift_order_status'],['0','1','2']) ) {
            $gift_order_status = $platform->_ordersdf['extend_field']['gift_order_status'];
            $related_order_list = $platform->_ordersdf['extend_field']['related_order_list'];
            $gift_list = $platform->_ordersdf['extend_field']['gift_list'];
            $order_sns = array_column($gift_list, 'order_sn');
            
            $gift_extdnd = [
                'gift_order_status' =>  $gift_order_status,
                'order_sns'=>  json_encode($order_sns, JSON_UNESCAPED_UNICODE),   

            ];
            if($gift_order_status==1){//related_order_list
                
                
                $labels[] = [
                    'label_code'    =>  'SOMS_GIFT_ORDER_STATUS',
                  
                    'extend_info'   =>  json_encode($gift_extdnd, JSON_UNESCAPED_UNICODE),
                ];
                
            }


        }

        if($platform->_ordersdf['extend_field']['related_order_list']){
            $related_order_list = $platform->_ordersdf['extend_field']['related_order_list'];
            foreach($related_order_list as $related_order){
                if($related_order['order_sn'] && $related_order['relation_type']==4){
                    $labels[] = [
                        'label_code'    =>  'SOMS_GIFT_RELATED_ORDER',
                        'extend_info'   =>  json_encode($related_order, JSON_UNESCAPED_UNICODE),
                    ];
                }
            }
        }
        // 京东分销订单，发货回写需要添加360buy_is_dx=true
        if ($platform->_ordersdf['fenxiao_order']) {
            $labels[] = [
                'label_code'    =>  'SOMS_FENXIAO',
            ];
        }
        
        // Maochao订单SOMS_ASCP标识
        if ($platform->_ordersdf['extend_field']['businessModel']) {
            $businessModel = intval($platform->_ordersdf['extend_field']['businessModel']);
            if ($businessModel >= 1 && $businessModel <= 4) {
                $label_value = pow(2, $businessModel - 1); // 转换为二进制位
                
                // 收集 order_objects 层的 store_code，以 oid 为维度存储
                $storeCodeMap = array();
                if (isset($platform->_ordersdf['order_objects']) && is_array($platform->_ordersdf['order_objects'])) {
                    foreach ($platform->_ordersdf['order_objects'] as $order_object) {
                        if (isset($order_object['oid']) && $order_object['oid']) {
                            $oid = $order_object['oid'];
                            
                            // 获取 store_code，注意 clear_plate_branch='true' 时可能为空字符串
                            $store_code = isset($order_object['store_code']) ? $order_object['store_code'] : '';
                            $storeCodeMap[$oid] = $store_code;
                        }
                    }
                }
                
                // 构建 extend_info，以 storeCodeMap 为键名存储
                $extend_info = '';
                if (!empty($storeCodeMap)) {
                    $extend_info = json_encode(array('storeCodeMap' => $storeCodeMap), JSON_UNESCAPED_UNICODE);
                }
                
                $labels[] = [
                    'label_code' => 'SOMS_ASCP',
                    'label_value' => $label_value,
                    'extend_info' => $extend_info,
                ];
            }
        }
        
        // 京东E卡
        if (is_array($platform->_ordersdf['extend_field']) && $platform->_ordersdf['extend_field']['is_jd_e_card']) {
            $labels[] = [
                'label_code' => 'SOMS_JDECARD',
            ];
        }

        if(isset($platform->_ordersdf['extend_field']['orderLabels'])){

            foreach($platform->_ordersdf['extend_field']['orderLabels'] as $v){
               
                if($v==32){
                    $labels[] = [
                        'label_code'    =>  'SOMS_LOGISTICS',
                        'label_value'   =>  0x0001,
                        
                    ];
                }

                if($v==33){
                    $labels[] = [
                        'label_code'    =>  'SOMS_LOGISTICS',
                        'label_value'   =>  0x0002,
                        
                    ];
                }
            }
            

            
        }


        //风控
        if(isset($platform->_ordersdf['is_risklabels']) && $platform->_ordersdf['is_risklabels']){
            $risklabels = $platform->_ordersdf['is_risklabels'];
            foreach($risklabels as $v){
                $labels[] = $v;
            }

        }
        //物流升级
        if(isset($platform->_ordersdf['logictics_labels']) && $platform->_ordersdf['logictics_labels']){
            $logictics_labels = $platform->_ordersdf['logictics_labels'];
            $log_label_value = 0;
            foreach($logictics_labels as $v){
                $log_label_value = $log_label_value | $v['label_value'];
            }
            $labels[] = [
                'label_code'    =>  'SOMS_LOGISTICS',
                'label_value'   =>  $log_label_value,
            ];
        }
        // 闪购订单标签
        if (isset($platform->_ordersdf['is_xsdbc']) && $platform->_ordersdf['is_xsdbc']) {
            $extend_info = '';
            $xsd_label_value = 0;
            
            // 安全的多维数组访问，兼容 PHP 8.2 语法
            $xsdInfo = null;
            if (isset($platform->_ordersdf['cn_info']) 
                && is_array($platform->_ordersdf['cn_info'])
                && isset($platform->_ordersdf['cn_info']['trade_attr'])
                && is_array($platform->_ordersdf['cn_info']['trade_attr'])
                && isset($platform->_ordersdf['cn_info']['trade_attr']['xsdFulfillmentInfo'])) {
                $xsdInfo = $platform->_ordersdf['cn_info']['trade_attr']['xsdFulfillmentInfo'];

            }
            if (is_array($xsdInfo)) {
                $extend_info = json_encode($xsdInfo, JSON_UNESCAPED_UNICODE);
                
                // 根据配送方式设置小标
                if (isset($xsdInfo['DType'])) {
                    switch ($xsdInfo['DType']) {
                        case 'thirdparty':
                            $xsd_label_value = 0x0001; // 第三方运力
                            break;
                        case 'seller':
                            $xsd_label_value = 0x0002; // 商家自配运力
                            break;
                        case 'official':
                            $xsd_label_value = 0x0004; // 平台运力
                            break;
                    }
                }
            }

            if(isset($platform->_ordersdf['cn_info']['trade_attr']) && $platform->_ordersdf['cn_info']['trade_attr']['_F_sxts'] == '1'){
                $xsd_label_value = 0x0008; // 平台运力
            }
            
            $labels[] = [
                'label_code'    =>  'SOMS_XSDBC',
                'label_value'   =>  $xsd_label_value,
                'extend_info'   =>  $extend_info,
            ];
        }

        // 百补超级半托
        // 只从订单级 ypds_info 获取
        if (isset($platform->_ordersdf['ypds_info']) && !empty($platform->_ordersdf['ypds_info'])) {
            $ypdsInfo = $platform->_ordersdf['ypds_info'];
            
            if (!empty($ypdsInfo['ypds_order_type'])) {
                // 构建 extend_info JSON
                $extendInfo = array(
                    'ypds_platform_order_id' => isset($ypdsInfo['ypds_platform_order_id']) ? $ypdsInfo['ypds_platform_order_id'] : '',
                    'ypds_platform_pay_order_id' => isset($ypdsInfo['ypds_platform_pay_order_id']) ? $ypdsInfo['ypds_platform_pay_order_id'] : '',
                    'ypds_order_type' => $ypdsInfo['ypds_order_type'],
                    'ypds_order_supply_price' => isset($ypdsInfo['ypds_order_supply_price']) ? $ypdsInfo['ypds_order_supply_price'] : 0,
                );
                
                $labelValue = 0;
                // 当 ypds_order_type = 1 时，设置 label_value 为标识"百补超级半托管"
                if ($ypdsInfo['ypds_order_type'] == '1' || $ypdsInfo['ypds_order_type'] == 1) {
                    $labelValue = 0x0001; // 百补超级半托管（使用位运算）
                }
                
                $labels[] = array(
                    'label_code'    => 'SOMS_YPDS',
                    'label_value'   => $labelValue,
                    'extend_info'   => json_encode($extendInfo, JSON_UNESCAPED_UNICODE),
                );
            }
        }

        // 产地优选（超链）
        // 只从订单级 superlink_info 获取
        if (isset($platform->_ordersdf['superlink_info']) && !empty($platform->_ordersdf['superlink_info'])) {
            $superlinkInfo = $platform->_ordersdf['superlink_info'];
            
            if (!empty($superlinkInfo['superlink_platform_order_id'])) {
                // 构建 extend_info JSON
                $extendInfo = array(
                    'superlink_platform_order_id' => isset($superlinkInfo['superlink_platform_order_id']) ? $superlinkInfo['superlink_platform_order_id'] : '',
                    'superlink_platform_sub_order_id' => isset($superlinkInfo['superlink_platform_sub_order_id']) ? $superlinkInfo['superlink_platform_sub_order_id'] : '',
                    'superlink_platform_payment' => isset($superlinkInfo['superlink_platform_payment']) ? $superlinkInfo['superlink_platform_payment'] : 0,
                    'superlink_alipay_no' => isset($superlinkInfo['superlink_alipay_no']) ? $superlinkInfo['superlink_alipay_no'] : '',
                    'superlink_order_supply_price' => isset($superlinkInfo['superlink_order_supply_price']) ? $superlinkInfo['superlink_order_supply_price'] : 0,
                );
                
                $labels[] = array(
                    'label_code'    => 'SOMS_SUPERLINK',
                    'label_value'   => 0,
                    'extend_info'   => json_encode($extendInfo, JSON_UNESCAPED_UNICODE),
                );
            }
        }

        return $labels;
    }

    /**
     *
     * @return void
     * @author
     **/
    public function postCreate($order_id, $labels)
    {
        $labelLib = kernel::single('ome_bill_label');

        if ($labels) {
            foreach ($labels as $k => $code) {

                if(!isset($code['label_action'])){
                    $lab = $labelLib->markBillLabel($order_id, '', $code['label_code'], 'order', $err, $code['label_value'], $code['extend_info'],$code['label_desc']);
                }
                
            }
        }

    }

    /**
     *
     * @param Array
     * @return void
     * @author
     **/
    public function postUpdate($order_id, $labels)
    {
        $labelLib = kernel::single('ome_bill_label');

        if ($labels) {
            foreach ($labels as $k => $code) {

                if($code['label_action']=='del'){
                    $labelAll = app::get('omeauto')->model('order_labels')->getList('*', ['label_code' => $code['label_code']]);
                    if($labelAll){
                        $labelAll = array_column($labelAll, 'label_id');
                        $labelLib->delLabelFromBillId($order_id, $labelAll,'order',$err);
                    }
                    
                    
                }else{
                    $lab = $labelLib->markBillLabel($order_id, '', $code['label_code'], 'order', $err, $code['label_value'], $code['extend_info']);
                }
                
            }
        }

    }
}
