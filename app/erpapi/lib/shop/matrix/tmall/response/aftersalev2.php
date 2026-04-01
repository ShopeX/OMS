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
 * @author ykm 2016/6/3
 * @describe 天猫售后数据转换
 */
class erpapi_shop_matrix_tmall_response_aftersalev2 extends erpapi_shop_response_aftersalev2 {

    protected $item_convert_field = [
        'sdf_field'     =>'oid',
        'order_field'   =>'oid',
        'default_field' =>'outer_id'
    ];

    protected function _formatAddParams($params) {
        if (($params['spider_type'] == 'tm_refund') || ($params['spider_type'] != 'tm_refund_i')) {
            $this->__apilog['result']['msg'] = '天猫售后老接口数据，不接受';
            return array();
        }
        $sdf = parent::_formatAddParams($params);
        if($params['t_type'] == 'fenxiao') { //天猫分销没数量只会全退
            $this->refund_item_all = true;
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
        
        //shop_id
        $shop_id = $this->__channelObj->channel['shop_id'];
        
        //[换货开关]支持天猫订单换货完成后又申请退货
        //获取换货产生的OMS订单进行退货操作
        $change_flag = $this->_getExchangeOmsOrder($params, $shop_id);
        
        //items
        $item = current($sdf['refund_item_list']['return_item']);
        if($change_flag){
            //$sdf['change_order_id'] = $change_flag['change_order_id'];
            //$params['oid'] = '';
            //$sdf['memo'] = '换货订单转换生成,原订单号:'.$params['tid'];
            
            //获取天猫订单换货完成生成的OMS新订单信息
            //$this->_getExchangeOmsItems($sdf);
        }

        if($params['tag_list']) {
            $tagList = json_decode($params['tag_list'], true);
            $tagList = serialize($tagList);
        }

        $tmallSdf = array(
            'oid'               => $params['oid'] ? $params['oid'] : $item['oid'],
            'tmall_refund_type' => $params['refund_type'],
            'refund_phase'      => $params['refund_phase'] ? $params['refund_phase'] : $item['refund_phase'],
            'cs_status'         => $params['cs_status'],
            'advance_status'    => $params['advance_status'],
            'split_taobao_fee'  => (float)$params['split_taobao_fee'],
            'split_seller_fee'  => (float)$params['split_seller_fee'],
            'total_fee'         => (float)$params['total_fee'],
            'seller_nick'       => $params['seller_nick'],
            'good_status'       => $params['good_status'],
            'order_status'       => $params['order_status'],
            'current_phase_timeout'=>$params['current_phase_timeout']?strtotime($params['current_phase_timeout']):0,
            'ship_addr'         => $params['receiver_address'],
            'tag_list'          => $tagList ? $tagList : '',
            'address'           => $params['address'] ? $params['address'] : '',
            'shipping_type'   => $params['shipping_type'],
            'buyer_nick'      => $params['buyer_nick'],
            'has_good_return' => $params['has_good_return'],
            'good_return_time'=> $params['good_return_time'],
            'refund_type'     => $params['refund_type'],
            'refund_version'  => $params['refund_version'],
            'alipay_no'       => $params['payment_id'],
            'trade_status'      =>$params['trade_status'],
            'bill_type'       => $params['bill_type'],
            't_ready'         =>$sdf['created'],
            't_sent'          =>$sdf['modified'],
            't_received'      =>'',
            'attribute'         =>  $params['attribute'],
            'tmall_mcard_pz_sp' =>  $params['tmall_mcard_pz_sp'],
            'extend_field' =>  $params['extend_field'],
        );
        $attributeArr = explode(';', $tmallSdf['attribute']);
        $attributeCode = [];
        foreach($attributeArr as $attribute) {
            if(strpos($attribute, ':') !== false) {
                list($key, $value) = explode(':', $attribute);
                $attributeCode[$key] = $value;
            }
        }

        // 关联退款单
        $tmallSdf['associatedDisputeID'] = $attributeCode['associatedDisputeID'] ?? '';

        // 关联子单状态
        $tmallSdf['disputeTradeStatus'] = $attributeCode['disputeTradeStatus'] ?? '';

        if ($sdf['reason'] == '补退已使用的红包' && $tmallSdf['associatedDisputeID'] && $tmallSdf['disputeTradeStatus']=='4') {
            $this->__apilog['result']['msg'] = '不接收补退红包，因为金额已经包含在';
            return [];
        }


        if($attributeCode['lastOrder']) {
            $tmallSdf['refund_shipping_fee'] = $attributeCode['lastOrder'] / 100;
        }
    
        //判断单据是否有保价退款
        if($attributeCode['price_protection']) {
            $tmallSdf['price_protection'] = $attributeCode['price_protection'];
        }
        //一退多收
        if($attributeCode['pInst']) {
            $pInst = json_decode(str_replace('#3B', ':', $attributeCode['pInst']), true);
            //权益金
            $mcardPZ = 0;
            foreach($pInst as $key => $value) { 
                // $pInst["20881112776463240156"]["presetRefundPayTool"]
                foreach($value['presetRefundPayTool'] as $key2 => $value2) {
                    //$pInst["20881112776463240156"]["presetRefundPayTool"]["TMALL_MCARD_PZ_SP"]
                    if($key2 == 'TMALL_MCARD_PZ_SP') {
                        $mcardPZ += $value2['amount'];
                    }
                }
            }
            if($mcardPZ > $params['tmall_mcard_pz_sp']) {
                $sdf['refund_fee'] = sprintf('%.2f', $sdf['refund_fee'] - ($mcardPZ - $params['tmall_mcard_pz_sp']));
            }
        }
        
        if(strstr($tmallSdf['attribute'],'interceptItemListResult')) {
            preg_match_all('/interceptItemListResult:([^;]+);/', $tmallSdf['attribute'], $matches);
            if($matches && $matches[1] && $matches[1][0]) {
                $intercept = json_decode(str_replace("#3B", ":", $matches[1][0]), 1);
                if($intercept[0]['autoInterceptAgree'] == 1) {
                    $tmallSdf['refund_type'] = 'return';
                    if($sdf['flag_type']) {
                        $tmallSdf['flag_type'] = $sdf['flag_type'] | ome_reship_const::__ZERO_INTERCEPT;
                    } else {
                        $tmallSdf['flag_type'] = ome_reship_const::__ZERO_INTERCEPT;
                    }
                }
            }
        }
        //喵住对接
        if ($params['trade_from'] && $params['trade_from'] == 'miaozhu') {
            $sdf['order_source'] = $params['trade_from'];
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
        
        return array_merge($sdf, $tmallSdf);
    }

    protected function _getAddType($sdf) {
        if ($sdf['refund_type'] == 'return') {
            if (in_array($sdf['order']['ship_status'],array('0'))) {
                #退款单
                return 'refund';
            }else{
                #退货申请单
                return 'returnProduct';
            }
        }elseif($sdf['refund_type'] == 'reship'){
            #退货单
            return 'reship';
        }else{
            #退款单
            return 'refund';
        }
    }

    protected function _formatAddItemList($sdf, $convert = array()) {
        if($sdf['refund_type'] == 'reship') {
            return array();
        }
        if ($sdf['order']['tran_type'] == 'archive'){
            return $this->formatArchiveitemlist($sdf,$convert);
        }
        if(!$convert) {
            $convert = $this->item_convert_field;
        }
        
        //[天猫定制订单]申请售后退货
        if($sdf['order']['order_type'] == 'custom'){
            $returnItems = $this->_customFormatAddItemList($sdf, $convert);
            if(!empty($returnItems)){
                //所有定制子订单号
                return $returnItems;
            }
        }
        
        return parent::_formatAddItemList($sdf, $convert);
    }

    protected function _calculateAddPrice($refundItems, $sdf) {
        $items = parent::_calculateAddPrice($refundItems, $sdf);
        return $items;
    }

    protected function _calculateAddPriceFromRefundFee($items, $sdf) {
        if($sdf['refund_fee'] && $sdf['refund_shipping_fee']) {
            $sdf['refund_fee'] -= $sdf['refund_shipping_fee'];
        }
        return parent::_calculateAddPriceFromRefundFee($items, $sdf);
    }

    protected function _refundApplyAdditional($sdf) {
        $ret = array(
            'model' => 'refund_apply_tmall',
            'data' => array(
                'shop_id'           => $sdf['shop_id'],
                'shipping_type'     => $sdf['shipping_type'],
                'cs_status'         => $sdf['cs_status'],
                'advance_status'    => $sdf['advance_status'],
                'split_taobao_fee'  => $sdf['split_taobao_fee'],
                'split_seller_fee'  => $sdf['split_seller_fee'],
                'total_fee'         => $sdf['total_fee'],
                'buyer_nick'        => $sdf['buyer_nick'],
                'seller_nick'       => $sdf['seller_nick'],
                'good_status'       => $sdf['good_status'],
                'order_status'       => $sdf['order_status'],
                'has_good_return'   => strtolower($sdf['has_good_return']),
                'good_return_time'  => $sdf['good_return_time'],
                'oid'               => $sdf['oid'],
                'refund_version'    => $sdf['refund_version'],
                'bill_type'         => $sdf['bill_type'],
                'outer_lastmodify'  => $sdf['modified'],
                'alipay_no'         => $sdf['alipay_no'],
                'current_phase_timeout'=>$sdf['current_phase_timeout'] ? : null,
                'refund_type'       => $sdf['tmall_refund_type'] == 'return' ? $sdf['tmall_refund_type'] : 'refund',
                'refund_phase'      => $sdf['refund_phase'] ? $sdf['refund_phase'] : '',
                'tag_list'          => $sdf['tag_list'],
                'refund_fee'         => $sdf['refund_fee'],
            )
        );
        return $ret;
    }

    protected function _refundAddSdf($sdf){
        $sdf['shop_type'] = 'tmall';
        if(self::$refund_status[strtoupper($sdf['status'])] != '4') {
            $sdf['refund_type'] = 'apply';
        }
        $sdf = parent::_refundAddSdf($sdf);
        if($sdf['cs_status'] == '6' && $sdf['response_bill_type'] != 'refund_apply') {
            $sdf['tag_type'] = '8';
        }
        /*
        if($sdf['refund_apply']) {
            $oRefundTmall = app::get('ome')->model('refund_apply_tmall');
            $refundTmallData = $oRefundTmall->getList('refund_version', array('apply_id'=>$sdf['refund_apply']['apply_id'],'shop_id'=>$sdf['shop_id']), 0, 1);
            if ($sdf['refund_version'] > $refundTmallData[0]['refund_version']) {
                $sdf['refund_version_change'] = true;
                $sdf['table_additional'] = $this->_refundApplyAdditional($sdf);
            } else {
                $sdf['refund_version_change'] = false;
            }
        }
        */

        //[兼容]订单已经全额退款并取消,创建退款单
        //场景：平台上订单已退款,矩阵先推送了更新取消订单,后面才推送了退款单,导致OMS没有创建退款单;
        //@todo：因为params有process_status订单状态检查,无法走到process方法中进行处理;
        //if($sdf['status']=='4' && $sdf['order']['process_status'] == 'cancel' && empty($sdf['refund_apply'])) {
        //    kernel::single('ome_order_refund')->createFinishRefund($sdf);
        //}
        
        return $sdf;
    }

    protected function _returnProductAdditional($sdf) {
        $ret = array(
            'model' => 'return_product_tmall',
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
                'refund_type'     => $sdf['refund_type'],
                'refund_phase'    => $sdf['refund_phase'],
                'refund_version'  => $sdf['refund_version'],
                'alipay_no'       => $sdf['alipay_no'],
                'trade_status'    => $sdf['trade_status'],
                'oid'             => $sdf['oid'],
                'bill_type'       => $sdf['bill_type'],
                'current_phase_timeout'=>$sdf['current_phase_timeout'],
                'tag_list'        => $sdf['tag_list'],
                'address'         => $sdf['address'],
                'refund_fee'      => $sdf['refund_fee'],
                'attribute'       =>  $sdf['attribute'],
                'extend_field'    => $sdf['extend_field'],
            )
        );
        return $ret;
    }

    protected function _returnProductAddSdf($sdf) {
        $sdf['shop_type'] = 'tmall';
        
        //平台状态值
        $status = strtoupper($sdf['status']);
        
        //check
        if ($sdf['order']['tran_type'] == 'archive'){
            $sdf['archive'] = '1';
            //$sdf['source'] = 'archive';
        }
        
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
        
        $sdf['choose_type_flag'] = 0;
        if($sdf['return_product']) {
            $oRefundTmall = app::get('ome')->model('return_product_tmall');
            $refundTmallData = $oRefundTmall->getList('refund_version', array('return_id'=>$sdf['return_product']['return_id'],'shop_id'=>$sdf['shop_id']), 0, 1);
            if ($sdf['refund_version'] > $refundTmallData[0]['refund_version']) {
                $sdf['refund_version_change'] = true;
            }elseif($status == 'SELLER_REFUSE_BUYER' && $sdf['modified'] > $sdf['return_product']['outer_lastmodify']){
                //@todo：商家拒绝退款时,发现refund_version版本号并没有变化,modified修改时间有变化;
                $sdf['refund_version_change'] = true;
            }else {
                $sdf['refund_version_change'] = false;
            }
        }
        $sdf['table_additional'] = $this->_returnProductAdditional($sdf);
        return $sdf;
    }

    /*
    * 判断是否已产生换货后退货订单
    *
    *
    */

    protected function _tranChange($sdf){
        $oid = $sdf['oid'];
        $tid = $sdf['tid'];
        $db = kernel::database();
        $sql = "SELECT r.change_order_id FROM sdb_ome_return_product_tmall as t LEFT JOIN sdb_ome_reship as r ON t.return_id=r.return_id WHERE t.oid='".$oid."' AND r.is_check not in('5','9') AND r.return_type='change'";
        $reship_detail = $db->selectrow($sql);
        if($reship_detail){
            $sql = "SELECT o.order_id as change_order_id FROM sdb_ome_orders as o  WHERE o.relate_order_bn='".$tid."' AND o.order_id=".$reship_detail['change_order_id'];
            $order_detail = $db->selectrow($sql);
            return $order_detail;
        }

    }


    protected function _tranChangeItems(&$sdf){
        $order_id = $sdf['change_order_id'];
        $orderObj = app::get('ome')->model('orders');
        $itemObj = app::get('ome')->model('order_items');
        if ($order_id>0){
            $order_detail = $orderObj->dump($order_id,"order_id,order_bn",array("order_objects"=>array("*",array("order_items"=>array('*')))));

        if($order_detail){
            $sdf['tid']  =$sdf['order_bn']   =   $order_detail['order_bn'];
            $order_object = current($order_detail['order_objects']);
            $return_item = $sdf['refund_item_list']['return_item'];
            $return_item    =    current($return_item);
            $item_list = array();
            //判断是否捆绑
            $obj_type = $order_object['obj_type'];
            $radio = $return_item['num']/$order_object['quantity'];

                foreach($order_object['order_items'] as $ov){
                    if($ov['delete'] == 'false'){

                        $item_list[] = array(
                            'product_id' => $ov['product_id'],
                            'bn'         => $ov['bn'],
                            'name'       => $ov['name'],
                            'num'        => $obj_type == 'pkg' ? (int)($radio * $ov['quantity']) : $return_item['num'],
                            'price'      => $obj_type == 'pkg' ? $ov['price'] : $return_item['price'],
                            'sendNum'   =>  $ov['sendnum'],
                            'op_id'     => '888888',
                            'order_item_id' => $ov['item_id'],
                        );

                    }

                }

                $sdf['refund_item_list'] = $item_list;

            }
        }

    }

    /**
     * formatArchiveitemlist
     * @param mixed $sdf sdf
     * @param mixed $convert convert
     * @return mixed 返回值
     */
    public function formatArchiveitemlist($sdf, $convert){

        $refund_item_list = $sdf['refund_item_list']['return_item'];
        $archiveOrderObjectMdl = app::get('archive')->model('order_objects');
    
        $oids   = array_column($refund_item_list, 'oid');
        $object = $archiveOrderObjectMdl->getList('order_id,oid,bn,quantity', ['oid' => $oids]);
        $object = array_column($object, null, 'oid');
    
        $arrItem = [];
        foreach ($refund_item_list as $item) {
            $item['bn'] = $object[$item['oid']]['bn'];
            if ($arrItem[$item['bn']]) {
                $arrItem[$item['bn']]['num'] += $item['num'];
            } else {
                $arrItem[$item['bn']] = $item;
            }
        }
        return $arrItem;
    }
    
    /**
     * 客服拒绝退货后,平台介入或顾客上传凭证后,重新恢复退货;
     * 场景：顾客申请退货，商家在天猫后台拒绝退货退款;顾客上传退货凭证后,平台自动同意退货申请,恢复原退货单；
     * 
     * @param $sdf
     * @return void
     */
    public function _checkRecoverReturn($sdf)
    {
        $order_id = $sdf['order']['order_id'];
        
        //检查售后单状态
        if(!in_array($sdf['status'], array('0', '1', '3'))){
            return false;
        }
        
        //检查售后申请单
        if(empty($sdf['return_product'])){
            return false;
        }
        
        //售后申请单不是拒绝状态
        if($sdf['return_product']['status'] != '5'){
            return false;
        }
        
        //检查退换货单状态
        if($sdf['reship']){
            //退换货单不是已取消状态
            if($sdf['reship']['is_check'] != '5'){
                return false;
            }

//            if(!in_array($sdf['reship']['status'], array('cancel', 'back'))){
//                return false;
//            }
        }
        
        //更新时间有变化,才做操作
        $sdf['modified'] = empty($sdf['modified']) ? 0 : $sdf['modified'];
        if($sdf['modified'] <= $sdf['return_product']['outer_lastmodify']) {
            return false;
        }
        
        //申请售后的商品
        $refund_item_list = $sdf['refund_item_list'];
        
        //没有退货商品
        if(empty($refund_item_list)){
            return false;
        }
        
        //订单明细
        $orderItemObj = app::get('ome')->model('order_items');
        $orderItemList = $orderItemObj->getList('item_id,obj_id,product_id,bn,nums,sendnum,return_num', array('order_id'=>$order_id, 'delete'=>'false'));
        $orderItemList = array_column($orderItemList, null, 'bn');
        
        //检查申请的货品
        foreach ($refund_item_list as $itemKey => $itemVal)
        {
            $item_bn = $itemVal['bn'];
            
            //check
            if(empty($orderItemList[$item_bn])){
                return false;
            }
            
            if($orderItemList[$item_bn]['return_num'] >= $orderItemList[$item_bn]['sendnum']){
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 获取天猫换货产生的OMS新订单,并且进行退货操作
     * @param $sdf
     * @return mixed|void
     */
    protected function _getExchangeOmsOrder($sdf, $shop_id)
    {
        $db = kernel::database();
        
        //info
        $tid = $sdf['tid'];
        $oid = $sdf['oid'];
        
        //check
        if(empty($tid) || empty($oid) || empty($shop_id)){
            return false;
        }
        
        //获取完成的换货单
        $sql = "SELECT b.change_order_id FROM sdb_ome_return_product_tmall AS a LEFT JOIN sdb_ome_reship AS b ON a.return_id=b.return_id
                WHERE a.oid='". $oid ."' AND a.shop_id='". $shop_id ."' AND a.refund_type='change' AND b.is_check IN('7') AND b.return_type='change'";
        $reshipInfo = $db->selectrow($sql);
        if(empty($reshipInfo)){
            return false;
        }
        
        //获取天猫订单换货生成的OMS新订单
        $sql = "SELECT order_id AS change_order_id,order_bn FROM sdb_ome_orders WHERE relate_order_bn='". $tid ."' AND order_id=". $reshipInfo['change_order_id'];
        $exchangeOrderInfo = $db->selectrow($sql);
        if(empty($exchangeOrderInfo)){
            return false;
        }
        
        return $exchangeOrderInfo;
    }
    
    /**
     * 获取天猫订单换货完成生成的OMS新订单信息
     * @param $sdf
     * @return void
     */
    protected function _getExchangeOmsItems(&$sdf)
    {
        $orderObj = app::get('ome')->model('orders');
        $itemObj = app::get('ome')->model('order_items');
        
        //换货生成的OMS新订单
        $order_id = $sdf['change_order_id'];
        if(empty($order_id)){
            return false;
        }
        
        //订单信息
        $order_detail = $orderObj->dump($order_id, "order_id,order_bn", array("order_objects"=>array("*", array("order_items"=>array('*')))));
        if(empty($order_detail)){
            return false;
        }
        
        $sdf['tid'] = $sdf['order_bn'] = $order_detail['order_bn'];
        
        //objects
        $order_object = current($order_detail['order_objects']);
        
        //return_item
        $return_item = $sdf['refund_item_list']['return_item'];
        $return_item = current($return_item);
        
        //判断是否捆绑
        $obj_type = $order_object['obj_type'];
        $radio = $return_item['num'] / $order_object['quantity'];
        
        //items
        $item_list = array();
        foreach($order_object['order_items'] as $ov)
        {
            if($ov['delete'] == 'true'){
                continue;
            }
            
            //items
            $item_list[] = array(
                'product_id' => $ov['product_id'],
                'bn' => $ov['bn'],
                'name' => $ov['name'],
                'num' => $obj_type == 'pkg' ? (int)($radio * $ov['quantity']) : $return_item['num'],
                'price' => $obj_type == 'pkg' ? $ov['price'] : $return_item['price'],
                'sendNum' =>  $ov['sendnum'],
                'op_id' => '888888',
                'order_item_id' => $ov['item_id'], //订单明细item_id
            );
        }
        
        $sdf['refund_item_list'] = $item_list;
        
        return true;
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
    
    //特殊处理一行明细的订单退款
    protected function _formatRefundFee($params) {
        // 记录传入参数到日志文件
        $order = $params['order'];
        $orderObjectModel = app::get('ome')->model('order_objects');
        
        if(isset($params['order_status']) && $params['order_status'] != 'TRADE_CLOSED'){
            return $params;
        }
        //存在服务费返回
        if ($order['service_price'] > 0) {
            return $params;
        }
        // 如果订单未支付，则直接返回原始参数
        if (!in_array($order['pay_status'],[1,6])) {
            return $params;
        }
        //是保价 且 退款金额小于支付金额 不处理
        if(isset($params['price_protection']) && $params['refund_fee'] < $order['payed']){
            return $params;
        }
        // 获取与订单相关的对象列表
        $objectList = $orderObjectModel->getList('obj_id,order_id,quantity', ['order_id' => $order['order_id']]);
        
        // 检查是否只有一个订单明细项且商品数量为1
        if (count($objectList) != 1 || $objectList[0]['quantity'] != 1) {
            return $params;
        }
        
        // 设置退款金额为订单实付金额
        $params['refund_fee'] = $order['payed'];
        return $params;
    }
    
    /**
     * [天猫定制订单]申请售后退货
     * 
     * @param $sdf
     * @param $convert
     * @return array
     */
    protected function _customFormatAddItemList($sdf, $convert=array())
    {
        $db = kernel::database();
        
        if(empty($convert)) {
            return array();
        }
        
        $order_id = $sdf['order']['order_id'];
        $itemList = $sdf['refund_item_list']['return_item'];
        //$sdfField = $convert['sdf_field']; //oid
        //$orderField = $convert['order_field']; //oid
        //$defaultField = $convert['default_field']; //outer_id
        
        //oid
        $oidList = [];
        foreach($itemList as $val)
        {
            $oid = $val['oid'];
            
            $oidList[$oid] = $oid;
        }
        
        //check
        if(empty($order_id) || empty($oidList)){
            return array();
        }
        
        //按oid模糊查询所有子订单号(例如：2496846507706018065-001、2496846507706018065-002、2496846507706018065-003)
        $sql = "SELECT obj_id,oid,obj_type,bn,quantity,divide_order_fee,`delete` FROM sdb_ome_order_objects WHERE order_id=". $order_id ." AND `delete`='false'";
        if(count($oidList) > 1){
            //支持一次退多个子订单
            $orList = [];
            foreach ($oidList as $oidKey => $oid)
            {
                $orList[] = "oid LIKE '". $oid ."-%'";
            }
            
            $sql .= " AND (". implode(" OR ", $orList) .")";
        }else{
            $oid = current($oidList);
            $sql .= " AND oid LIKE '". $oid ."-%'";
        }
        
        $objectList = $db->select($sql);
        if(empty($objectList)){
            return array();
        }
        
        //format
        $arrItem = array();
        foreach($objectList as $oVal)
        {
            $oid = $oVal['oid'];
            $goods_bn = $oVal['bn'];
            
            $arrItem[$goods_bn] = [
                'oid' => $oid,
                'bn' => $goods_bn,
                'num' => $oVal['quantity'],
                'price' => $oVal['divide_order_fee'],
                'divide_order_fee' => $oVal['divide_order_fee'],
                'modified' => date('Y-m-d H:i:s', time()),
                'is_custom_bn' => true,
            ];
        }
        
        return $arrItem;
    }
}
