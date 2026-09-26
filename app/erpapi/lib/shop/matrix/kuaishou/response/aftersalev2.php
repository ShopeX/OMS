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
 * @author ykm 2020/8/26 18:13:56
 * @describe 售后数据转换
 */
class erpapi_shop_matrix_kuaishou_response_aftersalev2 extends erpapi_shop_response_aftersalev2 {
    protected $refund_item_all = true;

    protected function _formatAddParams($params) {
        $sdf = parent::_formatAddParams($params);

         //tag_type
        $tag_type = $params['tag_type'];
        if($tag_type){
            //价保退款
            if($tag_type == '价保退款'){
                $sdf['bool_type'] = ome_refund_bool_type::__PROTECTED_CODE;
            }

            //退款的类型
            $sdf['tag_type'] = self::$tag_types[$tag_type] ? self::$tag_types[$tag_type] : '0';
        }

        //识别如果是已完成的售后，转成退款单更新的逻辑
         if($sdf['has_good_return'] == 'true' && strtolower($params['status']) == 'success'){
            $refundOriginalObj = app::get('ome')->model('return_product');
            #退货状态必须是已完成
            $refundOriginalInfo = $refundOriginalObj->getList('return_id', array('return_bn'=>$sdf['refund_bn'],'status' =>'4') , 0 , 1);
            if($refundOriginalInfo){
                $refundApplyObj = app::get('ome')->model('refund_apply');
                #售后退款申请单的退款状态，不能是已退款
                $refundApplyInfo = $refundApplyObj->getList('refund_apply_bn', array('return_id'=>$refundOriginalInfo[0]['return_id'],'status' =>array('0','1','2','5','6')) , 0 , 1);
                if($refundApplyInfo){
                    $sdf['refund_bn'] = $refundApplyInfo[0]['refund_apply_bn'];
                    $sdf['has_finished_return_product'] = true;
                }
            }
        }
        $kuaishouSdf = array(
            'source_status' => $params['source_status'],
            'source_refund_type' => $params['source_refund_type'],
            'refund_version'    =>  $params['refund_version'],
            'return_freight'    =>  is_array($sdf['returnFreightInfo']) ? $sdf['returnFreightInfo']['returnFreightAmount'] : 0,
            'returnFreightInfo'    =>  $params['returnFreightInfo'],
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
        return array_merge($sdf, $kuaishouSdf);
    }
    protected function _getAddType($sdf) {
        if ($sdf['has_good_return'] == 'true') {//需要退货才更新为售后单
            if (in_array($sdf['order']['ship_status'],array('0'))) {
                #有退货，未发货的,做退款
                return 'refund';
            } elseif (in_array($sdf['order']['ship_status'],array('3','4')) && $sdf['has_finished_return_product']) {
                #退款单
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

        if ($sdf['order']['tran_type'] == 'archive') {
            return $this->formatArchiveitemlist($sdf, $convert);
        }
        if($sdf['from_platform'] == 'yjdf'){
            $itemList = $sdf['refund_item_list']['return_item'];

            $arrItem = array();
            foreach ($itemList as $item) {

                $arrItem[$item['bn']] = $item;
            }

            return $arrItem;
         
        }else{
            $convert = array(
                'sdf_field'=>'item_id',
                'order_field'=>'shop_goods_id',
                'default_field'=>'item_id'
            );
            return parent::_formatAddItemList($sdf, $convert);

        }
        
    }

    /**
     * 归档订单：按 item_id→shop_goods_id 关联 archive.order_objects
     */
    public function formatArchiveitemlist($sdf, $convert)
    {
        $refund_item_list = $sdf['refund_item_list']['return_item'];
        if (empty($refund_item_list)) {
            return array();
        }

        $archiveOrderObjectMdl = app::get('archive')->model('order_objects');
        $shopGoodsIds = array_column($refund_item_list, 'item_id');
        $filter = array('shop_goods_id' => $shopGoodsIds);
        if ($sdf['order']['order_id']) {
            $filter['order_id'] = $sdf['order']['order_id'];
        }
        $object = $archiveOrderObjectMdl->getList('order_id,shop_goods_id,bn,quantity,obj_id', $filter);
        $object = array_column($object, null, 'shop_goods_id');

        $arrItem = array();
        foreach ($refund_item_list as $item) {
            $matchKey = (string) $item['item_id'];
            if ($object[$matchKey]['bn']) {
                $item['bn'] = $object[$matchKey]['bn'];
            }
            if ($arrItem[$item['bn']]) {
                $arrItem[$item['bn']]['num'] += $item['num'];
            } else {
                $arrItem[$item['bn']] = $item;
            }
            if ($object[$matchKey]['obj_id']) {
                $arrItem[$item['bn']]['obj_id'][] = $object[$matchKey]['obj_id'];
            }
        }
        return $arrItem;
    }

    protected function _returnProductAddSdf($sdf)
    {
        if ($sdf['order']['tran_type'] == 'archive') {
            $sdf['archive'] = '1';
        }
        return parent::_returnProductAddSdf($sdf);
    }

    protected function _refundApplyAdditional($sdf) {
        $ret = array(
            'model' => 'return_apply_special',
            'data' => array(
                'special' => json_encode(array(
                    'order_status' => $sdf['source_status'],
                    'refund_handing_way' => $sdf['source_refund_type'],
                    'refund_version'    =>  $sdf['refund_version'],
                ), JSON_UNESCAPED_UNICODE)
            )
        );
        return $ret;
    }

    protected function _returnProductAdditional($sdf) {
        $ret = array(
            'model' => 'return_apply_special',
            'data' => array(
                'special' => json_encode(array(
                    'order_status' => $sdf['source_status'],
                    'refund_handing_way' => $sdf['source_refund_type'],
                    'refund_version'    =>  $sdf['refund_version'],
                    'returnFreightInfo'    =>  $sdf['returnFreightInfo'],
                ), JSON_UNESCAPED_UNICODE)
            )
        );
        return $ret;
    }

    protected function _returnFreight($sdf) {
        if(is_array($sdf['returnFreightInfo']) && $sdf['returnFreightInfo']['returnFreightAmount'] > 0) {
            return [
                'amount' => $sdf['returnFreightInfo']['returnFreightAmount'],
                'apply_desc' => $sdf['returnFreightInfo']['returnFreightApplyDesc'],
                'apply_images' => json_encode($sdf['returnFreightInfo']['returnFreightApplyImages']),
            ];
        }
        return [];
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
       
        $oid = $refund_item_list['return_item'][0]['item_id'];
        $db = kernel::database();
        $sql = "SELECT r.change_order_id FROM  sdb_ome_return_apply_special as t LEFT JOIN sdb_ome_reship as r ON t.return_id=r.return_id WHERE r.order_id='".$order_id."' AND r.is_check not in('5','9') AND r.return_type='change' AND  t.special like '%".$oid."%'";


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
                $price = sprintf('%.2f', $return_item['price'] / $return_item['num']);
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
                            'order_item_id' => $ov['item_id'],
                        );

                    }

                }

                $sdf['refund_item_list'] = $item_list;

            }
        }

    }
}