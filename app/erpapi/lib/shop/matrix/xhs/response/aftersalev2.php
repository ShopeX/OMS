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
 * @author sunjing@shopex.cn
 * @describe
 */
class erpapi_shop_matrix_xhs_response_aftersalev2 extends erpapi_shop_response_aftersalev2
{
    protected $_change_return_type = true;
    protected function _formatAddParams($params)
    {
        $sdf = parent::_formatAddParams($params);
        //判断是否换货后生成的
        $shopId  = $sdf['shop_id'] = $this->__channelObj->channel['shop_id'];
        $tgOrder = $this->getOrder('order_id,ship_status,order_bn', $shopId, $sdf['order_bn']);
    
        if (in_array($tgOrder['ship_status'], array('3', '4'))) {
            $params['order_id'] = $tgOrder['order_id'];
            $change_flag = $this->_getExchangeOmsOrder($params, $shopId);
        
            if ($change_flag) {
                $sdf['change_order_flag'] = true;
                $sdf['change_order_id']   = $change_flag['change_order_id'];
                $params['oid']            = '';
                $sdf['memo']              = '换货订单转换生成,原订单号:' . $params['tid'];
                $this->_getExchangeOmsItems($sdf);
            }
        }
        if ($sdf['refund_type'] == 'refund') {
            $sdf['refund_type'] = 'apply';
        }
        //极速退款
        if ($sdf['jsrefund_flag']) {
            $sdf['tag_type'] = '5';
        }
        return $sdf;
    }
    
    protected function _getAddType($sdf)
    {
        //需要退货才更新为售后单
        if ($sdf['has_good_return'] == 'true') {
            if (in_array($sdf['order']['ship_status'],array('0'))) {
                //有退货，未发货的,做退款
                return 'refund';
            } else{
                //有退货，已发货的,做售后
                return 'returnProduct';
            }
        }else{
            //无退货的，直接退款
            return 'refund';
        }
    }

    protected function _formatAddItemList($sdf, $convert=array())
    {
        if ($sdf['order']['tran_type'] == 'archive') {
            return $this->formatArchiveitemlist($sdf, $convert);
        }
        $convert = array(
            'sdf_field'=>'oid',
            'order_field'=>'oid',
            
        );
        
        return parent::_formatAddItemList($sdf, $convert);
    }

    /**
     * 归档订单：按 oid 关联 archive.order_objects，返回以 object bn 为键的明细
     */
    public function formatArchiveitemlist($sdf, $convert)
    {
        $refund_item_list = $sdf['refund_item_list']['return_item'];
        if (empty($refund_item_list)) {
            return array();
        }

        $archiveOrderObjectMdl = app::get('archive')->model('order_objects');
        $oids = array_column($refund_item_list, 'oid');
        $filter = array('oid' => $oids);
        if ($sdf['order']['order_id']) {
            $filter['order_id'] = $sdf['order']['order_id'];
        }
        $object = $archiveOrderObjectMdl->getList('order_id,oid,bn,quantity,obj_id', $filter);
        $object = array_column($object, null, 'oid');

        $arrItem = array();
        foreach ($refund_item_list as $item) {
            if ($object[$item['oid']]['bn']) {
                $item['bn'] = $object[$item['oid']]['bn'];
            }
            if ($arrItem[$item['bn']]) {
                $arrItem[$item['bn']]['num'] += $item['num'];
            } else {
                $arrItem[$item['bn']] = $item;
            }
            if ($object[$item['oid']]['obj_id']) {
                $arrItem[$item['bn']]['obj_id'][] = $object[$item['oid']]['obj_id'];
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
    
    /**
     * 判断换货转退货是否需要变更版本
     * @Author: xueding
     * @Vsersion: 2023/8/2 下午3:20
     * @param $sdf
     * @return bool
     */

    protected function _getReturnVersionChange($sdf)
    {
        $version_change = false;
        
        if ($sdf['modified'] > $sdf['return_product']['outer_lastmodify'] && ($sdf['return_product']['content'] != $sdf['reason']) || $sdf['return_product']['money'] != $sdf['refund_fee']) {
            $version_change = true;
        }
        
        //换货转退货(需要变更版本)
        $is_change = false;
        if ($sdf['return_product']['return_type'] == 'change' || $sdf['return_product']['kinds'] == 'change') {
            $is_change = true;
        }

        if ($is_change) {
            $version_change = true;
        }
        
        return $version_change;
    }
    
    /**
     * 获取天猫换货产生的OMS新订单,并且进行退货操作
     * @param $sdf
     * @return bool|void
     */
    protected function _getExchangeOmsOrder($sdf, $shop_id)
    {
        $db = kernel::database();
        
        $tid = $sdf['tid'];
        //check
        if(empty($tid) || empty($shop_id)){
            return false;
        }
        
        //获取完成的换货单
        $sql = "SELECT b.change_order_id FROM sdb_ome_reship AS b WHERE b.order_id='".$sdf['order_id']."' AND b.is_check IN('7') AND b.return_type='change'";
        $reshipInfo = $db->selectrow($sql);
        if(empty($reshipInfo)){
            return false;
        }
        
        //获取订单换货生成的OMS新订单
        $sql = "SELECT order_id AS change_order_id,order_bn FROM sdb_ome_orders WHERE  order_id=". $reshipInfo['change_order_id'];
        $exchangeOrderInfo = $db->selectrow($sql);
        if(empty($exchangeOrderInfo)){
            return false;
        }
        
        return $exchangeOrderInfo;
    }
    
    /**
     * 换货完成生成的OMS新订单信息
     * 获取天猫订单
     * @param $sdf
     * @return bool
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
}