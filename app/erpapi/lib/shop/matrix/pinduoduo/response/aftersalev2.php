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

class erpapi_shop_matrix_pinduoduo_response_aftersalev2 extends erpapi_shop_response_aftersalev2 {

    protected $_change_return_type = true;
    protected $item_convert_field = [
        'sdf_field'     =>'oid',
        'order_field'   =>'oid',
        'default_field' =>'outer_id'
    ];

    protected function _formatAddParams($params){
        $sdf = parent::_formatAddParams($params);
        $sdf['oid'] = $params['oid'];
        $pinduoduoSdf = array(
            'oid'               => $params['oid'],
            'cs_status'         => $params['cs_status'],
            'seller_nick'       => $params['seller_nick'],
            'payment_id'        => $params['payment_id'],
            'desc'              => $params['descd'],
        );

        //判断是否换货后生成的
        $shopId = $sdf['shop_id'] = $this->__channelObj->channel['shop_id'];
        $tgOrder = $this->getOrder('order_id,ship_status,order_bn', $shopId, $sdf['order_bn']);


        if(in_array($tgOrder['ship_status'],array('3','4'))){
            $change_flag = $this->_tranChange($sdf,$tgOrder);

            if($change_flag){
                $sdf['change_order_flag'] = true;
                $sdf['change_order_id'] = $change_flag['change_order_id'];
                $params['oid'] = '';
                $sdf['memo'] = '换货订单转换生成,原订单号:'.$params['tid'];
                $this->_tranChangeItems($sdf);
            }

        }
        return array_merge($sdf, $pinduoduoSdf);
    }
    
    /**
     * 添加
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function add($params){
        $sdf = parent::add($params);
        if ($sdf) {
            if(in_array($sdf['response_bill_type'], array('return_product'))){
                //换货转退货的场景,需要更新原单change 为 return;
                if($sdf['return_product']['return_type'] == 'change'){
                    //$sdf['exchange_to_return'] = true;
                }
            }
            return $sdf;
        }
        return false;
    }
    protected function _getAddType($sdf) {
        if ($sdf['refund_type'] == 'return') {
            if ($sdf['has_good_return'] == 'true') {//需要退货才更新为售后单
                if (in_array($sdf['order']['ship_status'],array('0'))) {
                    #有退货，未发货的,做退款
                    return 'refund';
                }else{
                    #有退货，已发货的,做售后
                    return 'returnProduct';
                }
            }else{
                return 'refund';
            }
        }elseif($sdf['refund_type'] == 'reship'){
            #退货单
            return 'reship';
        }else{
            #无退货的，直接退款(包括refund_type=apply)
            return 'refund';
        }
    }

    protected function _formatAddItemList($sdf, $convert = array()) {
        if($sdf['refund_type'] == 'reship') {
            return array();
        }
        
        if($sdf['from_platform'] == 'yjdf'){
            $itemList = $sdf['refund_item_list']['return_item'];

            $arrItem = array();
            foreach ($itemList as $item) {

                $arrItem[$item['bn']] = $item;
            }
            
            return $arrItem;
        }else{
            $convert = $this->item_convert_field;
            
            return parent::_formatAddItemList($sdf, $convert);
        }
    }

    # 拼多多退款申请附加
    protected function _refundApplyAdditional($sdf) {
        $ret = array(
            'model' => 'refund_apply_pinduoduo',
            'data' => array(
                'shop_id'               => $sdf['shop_id'],
                'oid'                   => $sdf['oid'],
                'cs_status'             => $sdf['cs_status'],
                'seller_nick'           => $sdf['seller_nick'],
                'buyer_nick'            => $sdf['buyer_nick'],
                'has_good_return'       => $sdf['has_good_return'],
                'payment_id'            => $sdf['payment_id'],
                'refund_fee'            => $sdf['refund_fee'],
                'operation_constraint'  => $sdf['operation_constraint']
            )
        );
        return $ret;
    }

    # 拼多多售后申请附加
    protected function _returnProductAdditional($sdf) {
        $ret = array(
            'model' => 'return_product_pinduoduo',
            'data' => array(
                'shop_id'               => $sdf['shop_id'],
                'shipping_type'         => $sdf['shipping_type'],
                'cs_status'             => $sdf['cs_status'],
                'buyer_nick'            => $sdf['buyer_nick'],
                'seller_nick'           => $sdf['seller_nick'],
                'has_good_return'       => $sdf['has_good_return'],
                'payment_id'            => $sdf['payment_id'],
                'good_return_time'      => $sdf['good_return_time']? $sdf['good_return_time'] : time(),
                'oid'                   => $sdf['oid'],
                'refund_fee'            => $sdf['refund_fee'],
                'operation_constraint'  => $sdf['operation_constraint']
            )
        );
        return $ret;
    }
    
    //特殊处理一行明细的订单退款
    protected function _formatRefundFee($params) {
        $order = $params['order'];
        $orderObjectModel = app::get('ome')->model('order_objects');
        
        // 如果订单未支付，则直接返回原始参数
        if (!in_array($order['pay_status'],[1,6])) {
            return $params;
        }
        
        // 获取与订单相关的对象列表
        $filter = [
            'order_id'   => $order['order_id'],
            'filter_sql' => " ((obj_type != 'gift' OR oid != 0)) ", // 排除掉本地赠品
        ];
        $objectList = $orderObjectModel->getList('obj_id,order_id,quantity', $filter);
        
        // 检查是否只有一个订单明细项且商品数量为1
        if (count($objectList) != 1 || $objectList[0]['quantity'] != 1) {
            return $params;
        }
    
        $sumPmtAmount = 0;
        $pmtList      = app::get('ome')->model('order_pmt')->getList('order_id,pmt_amount,pmt_describe', ['order_id' => $order['order_id'], 'pmt_describe' => '平台优惠金额']);
        if ($pmtList) {
            $pmtAmounts = array_map(function ($pmtList) {
                return $pmtList['pmt_amount'] ?? 0;
            }, $pmtList);
        
            $sumPmtAmount = array_sum($pmtAmounts);
        }
        $newRefundFee = $params['refund_fee'] + $sumPmtAmount;
        if ($order['total_amount'] != $newRefundFee) {
            return $params;
        }
        // 设置退款金额为订单实付金额
        $params['refund_fee'] = $order['total_amount'];
        
        return $params;
    }


    /*
    * 判断是否已产生换货后退货订单
    *
    *
    */
    protected function _tranChange($sdf,$tgOrder){


        $order_id = $tgOrder['order_id'];
        $tid = $tgOrder['order_bn'];
        $refund_item_list = $sdf['refund_item_list'];

        $oid = $sdf['oid'];
        $db = kernel::database();
        $sql = "SELECT r.change_order_id FROM  sdb_ome_return_product_pinduoduo as t LEFT JOIN sdb_ome_reship as r ON t.return_id=r.return_id WHERE r.order_id='".$order_id."' AND r.is_check not in('5','9') AND r.return_type='change' AND  t.oid='".$oid."'";


        $reship_detail = $db->selectrow($sql);

        if($reship_detail){
            $sql = "SELECT o.order_id as change_order_id,o.ship_status FROM sdb_ome_orders as o  WHERE o.platform_order_bn='".$tid."' AND o.order_id=".$reship_detail['change_order_id'];


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
            $refund_fee = $sdf['refund_fee'];
            if($order_detail){

                $sdf['tid']  =$sdf['order_bn']   =   $order_detail['order_bn'];
                $order_object = current($order_detail['order_objects']);
                $return_item = $sdf['refund_item_list']['return_item'];
                $return_item    =    current($return_item);
                $item_list = array();
                //判断是否捆绑
                $obj_type = $order_object['obj_type'];
                $radio = $return_item['num']/$order_object['quantity'];
                $price = sprintf('%.2f', $refund_fee / $return_item['num']);
                foreach($order_object['order_items'] as $ov){
                    if($ov['delete'] == 'false'){
                        
                        $item_list[] = array(
                            'product_id' => $ov['product_id'],
                            'bn'         => $ov['bn'],
                            'name'       => $ov['name'],
                            'num'        => $ov['quantity'],
                            'price'      => $ov['price'],
                            'sendNum'   =>  $ov['sendnum'],
                            'op_id'     => '888888',
                            'order_item_id' => $ov['item_id'], //订单明细item_id
                        );

                    }

                }

                $sdf['refund_item_list'] = $item_list;

            }
        }

    }
}
