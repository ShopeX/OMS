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
 * @desc
 * @author: jintao
 * @since: 2016/7/20
 */
class erpapi_shop_matrix_taobao_response_aftersalev2 extends erpapi_shop_response_aftersalev2 {
    protected $item_convert_field = [
        'sdf_field'     =>'oid',
        'order_field'   =>'oid',
        'default_field' =>'outer_id'
    ];

    protected function _formatAddParams($params) {
        $sdf = parent::_formatAddParams($params);
        if($params['tag_list']) {
            $tagList = json_decode($params['tag_list'], true);
            $tagList = serialize($tagList);
        }
        
        //tag_type
        $tag_type = $params['tag_type'];
        if($tag_type){
            //价保退款
            if($tag_type == '价保退款' || $tag_type == '返现退款'){
                $sdf['bool_type'] = ome_refund_bool_type::__PROTECTED_CODE;
            }
            
            //退款的类型
            $sdf['tag_type'] = self::$tag_types[$tag_type] ? self::$tag_types[$tag_type] : '0';
        }
        $taobaoSdf = array(
            'oid'               => $params['oid'],
            'cs_status'         => $params['cs_status'],
            'advance_status'    => $params['advance_status'],
            'split_taobao_fee'  => (float)$params['split_taobao_fee'],
            'split_seller_fee'  => (float)$params['split_seller_fee'],
            'total_fee'         => (float)$params['total_fee'],
            'seller_nick'       => $params['seller_nick'],
            'good_status'       => $params['good_status'],
            'refund_version'    => $params['refund_version'],
            'order_status'      => $params['order_status'],
            'current_phase_timeout'=>$params['current_phase_timeout']?strtotime($params['current_phase_timeout']):0,
            'ship_addr'         => $params['receiver_address'],
            'tag_list'          => $tagList ? $tagList : '',
            'attribute'         =>  $params['attribute'],
            'address'           => $params['address'] ? $params['address'] : '',
            't_ready'           =>$sdf['t_begin'],
            't_sent'           =>$sdf['modified'],
            't_received'       =>''
        );

        $attributeArr = explode(';', $taobaoSdf['attribute']);
        $attributeCode = [];
        foreach($attributeArr as $attribute) {
            if(strpos($attribute, ':') !== false) {
                list($key, $value) = explode(':', $attribute);
                $attributeCode[$key] = $value;
            }
        }

        // 关联退款单
        $taobaoSdf['associatedDisputeID'] = $attributeCode['associatedDisputeID'] ?? '';

        // 关联子单状态
        $taobaoSdf['disputeTradeStatus'] = $attributeCode['disputeTradeStatus'] ?? '';

        if ($sdf['reason'] == '补退已使用的红包' && $taobaoSdf['associatedDisputeID'] && $taobaoSdf['disputeTradeStatus']=='4') {
            $this->__apilog['result']['msg'] = '不接收补退红包，因为金额已经包含在';
            return [];
        }

        if($attributeCode['lastOrder']) {
            $taobaoSdf['refund_shipping_fee'] = $attributeCode['lastOrder'] / 100;
        }


        if(strstr($taobaoSdf['attribute'],'interceptItemListResult')) {
            preg_match_all('/interceptItemListResult:([^;]+);/', $taobaoSdf['attribute'], $matches);
            if($matches && $matches[1] && $matches[1][0]) {
                $intercept = json_decode(str_replace("#3B", ":", $matches[1][0]), 1);
                if($intercept[0]['autoInterceptAgree'] == 1) {
                    $taobaoSdf['has_good_return'] = 'true';
                    if($sdf['flag_type']) {
                        $taobaoSdf['flag_type'] = $sdf['flag_type'] | ome_reship_const::__ZERO_INTERCEPT;
                    } else {
                        $taobaoSdf['flag_type'] = ome_reship_const::__ZERO_INTERCEPT;
                    }
                }
            }
        }
        // 百补退款处理
        // 判断：ypds_refund_type = 1 视为百补退款
        // 退款字段只从 extend_field 中获取
        $extendField = null;
        if (!empty($params['extend_field'])) {
            $extendField = is_string($params['extend_field']) ? json_decode($params['extend_field'], true) : $params['extend_field'];
        }
        
        // 从 extend_field 中获取 ypds_refund_type
        $ypdsRefundType = '';
        if (is_array($extendField) && isset($extendField['ypds_refund_type'])) {
            $ypdsRefundType = $extendField['ypds_refund_type'];
        }
        
        if ($ypdsRefundType == '1' || $ypdsRefundType == 1) {
            // 理由：取 ypds_refund_reason（从 extend_field）
            $ypdsRefundReason = '';
            if (is_array($extendField) && isset($extendField['ypds_refund_reason'])) {
                $ypdsRefundReason = $extendField['ypds_refund_reason'];
            }
            if (!empty($ypdsRefundReason)) {
                $sdf['reason'] = $ypdsRefundReason;
            }
            
            // 金额：取 ypds_refund_supply_fee（供货价口径，从 extend_field）
            $ypdsRefundSupplyFee = 0;
            if (is_array($extendField) && isset($extendField['ypds_refund_supply_fee'])) {
                $ypdsRefundSupplyFee = floatval($extendField['ypds_refund_supply_fee']);
            }
            if ($ypdsRefundSupplyFee > 0) {
                $sdf['refund_fee'] = sprintf('%.2f', $ypdsRefundSupplyFee);
            }
        }
        
        // 产地优选（超链）退款处理
        // 判断：superlink_refund_id 有值视为产地优选退款
        // 退款字段只从 extend_field 中获取
        $superlinkRefundId = '';
        if (is_array($extendField) && isset($extendField['superlink_refund_id'])) {
            $superlinkRefundId = $extendField['superlink_refund_id'];
        }
        
        if (!empty($superlinkRefundId)) {
            // 理由：取 superlink_refund_reason（从 extend_field）
            $superlinkRefundReason = '';
            if (is_array($extendField) && isset($extendField['superlink_refund_reason'])) {
                $superlinkRefundReason = $extendField['superlink_refund_reason'];
            }
            if (!empty($superlinkRefundReason)) {
                $sdf['reason'] = $superlinkRefundReason;
            }
            
            // 金额：取 superlink_refund_supply_fee（供货价口径，从 extend_field）
            $superlinkRefundSupplyFee = 0;
            if (is_array($extendField) && isset($extendField['superlink_refund_supply_fee'])) {
                $superlinkRefundSupplyFee = floatval($extendField['superlink_refund_supply_fee']);
            }
            if ($superlinkRefundSupplyFee > 0) {
                $sdf['refund_fee'] = sprintf('%.2f', $superlinkRefundSupplyFee);
            }
        }
        return array_merge($sdf, $taobaoSdf);
    }

    protected function _getAddType($sdf) {
        if ($sdf['has_good_return'] == 'true') {//需要退货才更新为售后单
            if (in_array($sdf['order']['ship_status'],array('0'))) {
                #有退货，未发货的,做退款
                return 'refund';
            }else{
                #有退货，已发货的,做售后
                return 'returnProduct';
            }
        }else{
            #无退货的，直接退款
            return 'refund';
        }
    }

    protected function _formatAddItemList($sdf, $convert=array()) {
        $convert = $this->item_convert_field;

        return parent::_formatAddItemList($sdf, $convert);
    }

    protected function _refundApplyAdditional($sdf) {
        $ret = array(
            'model' => 'refund_apply_taobao',
            'data' => array(
                'shop_id'           => $sdf['shop_id'],
                'oid'               => $sdf['oid'],
                'cs_status'         => $sdf['cs_status'],
                'advance_status'    => $sdf['advance_status'],
                'split_taobao_fee'  => $sdf['split_taobao_fee'],
                'split_seller_fee'  => $sdf['split_seller_fee'],
                'total_fee'         => $sdf['total_fee'],
                'seller_nick'       => $sdf['seller_nick'],
                'good_status'       => $sdf['good_status'],
                'has_good_return'   => $sdf['has_good_return'],
                'alipay_no'         => $sdf['alipay_no'],
                'current_phase_timeout'=>$sdf['current_phase_timeout'],
                'refund_fee'         => $sdf['refund_fee'],
                'refund_version'     => $sdf['refund_version'],
                'order_status'     => $sdf['order_status'],
            )
        );
        return $ret;
    }

    protected function _refundAddSdf($sdf){
        $sdf = parent::_refundAddSdf($sdf);
        if($sdf['cs_status'] == '6' && $sdf['response_bill_type'] != 'refund_apply') {
            $sdf['tag_type'] = '8';
        }
        return $sdf;
    }

    protected function _returnProductAdditional($sdf) {
        $ret = array(
            'model' => 'return_product_taobao',
            'data' => array(
                'shop_id'         => $sdf['shop_id'],
                'shipping_type'   => $sdf['shipping_type'],
                'cs_status'       => $sdf['cs_status'],
                'advance_status'  => $sdf['advance_status'],
                'split_taobao_fee'=> $sdf['split_taobao_fee'],
                'split_seller_fee'=> $sdf['split_seller_fee'],
                'total_fee'       => $sdf['total_fee'],
                'buyer_nick'      => $sdf['buyer_nick'],
                'seller_nick'     => $sdf['seller_nick'],
                'good_status'     => $sdf['good_status'],
                'has_good_return' => $sdf['has_good_return'],
                'good_return_time'=> $sdf['good_return_time'],
                'alipay_no'       => $sdf['alipay_no'],
                'ship_addr'       => $sdf['receiver_address'],
                'outer_lastmodify'=> $sdf['modified'],
                'oid'             => $sdf['oid'],
                'current_phase_timeout'=>$sdf['current_phase_timeout'],
                'tag_list'        => $sdf['tag_list'],
                'attribute'       =>  $sdf['attribute'],
                'address'         => $sdf['address'],
                'refund_fee'      => $sdf['refund_fee'],
                'refund_version'  => $sdf['refund_version'],
            )
        );
        return $ret;
    }
    
    /**
     * 售后申请单数据转换
     *
     * @param $sdf
     * @return array|false
     */
    protected function _returnProductAddSdf($sdf)
    {
        //平台状态值
        $status = strtoupper($sdf['status']);
        
        //format
        $sdf = parent::_returnProductAddSdf($sdf);
        if(!$sdf) {
            return false;
        }
        
        //商家拒绝退款
        //@todo：SELLER_REFUSE_BUYER是商家拒绝退款,只有CLOSED时才是取消退货单;
        if($status == 'SELLER_REFUSE_BUYER'){
            $sdf['status'] = '10';
        }
        
        //检查版本变化
        if($status == 'SELLER_REFUSE_BUYER' && $sdf['modified'] && $sdf['return_product']['outer_lastmodify']) {
            //@todo：商家拒绝退款时,发现refund_version版本号并没有变化,modified修改时间有变化;
            if($sdf['modified'] > $sdf['return_product']['outer_lastmodify']) {
                $sdf['refund_version_change'] = true;
            }
        }
        
        return $sdf;
    }
    
    /**
     * 退货数据转换
     *
     * @param $sdf
     * @return false|void
     */
    protected function _reshipAddSdf($sdf, $params=null)
    {
        //平台状态值
        $status = strtoupper($sdf['status']);
        
        //format
        $sdf = parent::_reshipAddSdf($sdf, $params);
        if(empty($sdf)){
            return false;
        }
        
        //商家拒绝退款
        //@todo：SELLER_REFUSE_BUYER是商家拒绝退款,只有CLOSED时才是取消退货单;
        if($status == 'SELLER_REFUSE_BUYER'){
            $sdf['status'] = '10';
        }
        
        return $sdf;
    }
}