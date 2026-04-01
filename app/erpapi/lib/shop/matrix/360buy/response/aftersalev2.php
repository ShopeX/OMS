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
 * @desc    京东售后数据转换
 * @author: jintao
 * @since: 2016/7/20
 */
class erpapi_shop_matrix_360buy_response_aftersalev2 extends erpapi_shop_response_aftersalev2
{
    protected function _formatAddParams($params)
    {
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
        if ($sdf['refund_type'] == 'refund') {//新增京东售前退款格式转化
            //0、未审核 1、审核通过2、审核不通过 3、京东财务审核通过 4、京东财务审核不通过 5、人工审核通过 6、拦截并退款 7、青龙拦截成功 8、青龙拦截失败 9、强制关单并退款
            $status = $sdf['status'];
            if (in_array( $status,array('0','8')) ) {
                $sdf['status'] = 'WAIT_SELLER_AGREE';
                $sdf['refund_type'] = 'apply';
            }else if( in_array($status,array('1','5','10','6')) ){//申请通过
                $sdf['status'] = 'WAIT_BUYER_RETURN_GOODS';
                $sdf['refund_type'] = 'apply';
            }else if( in_array($status,array('2','4','11')) ){ //拒绝
                $sdf['status'] = 'SELLER_REFUSE_BUYER';
                $sdf['refund_type'] = 'apply';
            }else if( in_array($status,array('3','9')) ){ //成功
                $sdf['status'] = 'SUCCESS';
            }
            if ($sdf['bool_type'] != ome_refund_bool_type::__PROTECTED_CODE && $params['partRefundType']!=1){
                $order = $this->getOrder('order_id,payed', $this->__channelObj->channel['shop_id'], $sdf['order_bn'],'aftersale');
                $sdf['real_refund_amount'] = $params['refund_fee'];
                $sdf['refund_fee'] = $order['payed'] ? : $params['refund_fee'];
            }

            if ($sdf['logistics_company'] == '自动审核' && in_array($status,array('1'))){
                $sdf['status'] = 'SUCCESS';
            }
        } 
      
        if ($sdf['extend_field']){
            if($sdf['extend_field'] && $sdf['extend_field']['refundAmt']>0){
                $sdf['jsrefund_flag']=1;
                
            }
        }
        if($sdf['status'] == 'confirm_success') {
            $sdf['jsrefund_flag'] = 1;
        }

        $sdf['pickware_type']       = $params['pickware_type'];
        $sdf['refund_version']      = $params['refund_version'];
        $sdf['return_ware_address'] = $params['return_ware_address'];
        $sdf['pickware_address']    = $params['pickware_address'];
        $sdf['customer_info']       = $params['customer_info'];
        $sdf['apply_detail_list']   = $params['apply_detail_list'];
        $sdf['customerExpect']      = $params['customerExpect'];
        if (in_array($sdf['refund_type'],array('return','reship'))){
            if($sdf['customerExpect']==20) {
                $sdf['return_type'] = 'jdchange';
            }
            $sdf['real_refund_amount'] = $params['refund_fee'];
            $sdf['refund_fee'] = $this->_getRefundFee($params, $sdf);
        }
    
        //退款转退货明细缺少oid outer_id为子单号，顾做此兼容
        if (isset($sdf['refund_item_list']) && $sdf['refund_item_list']) {
            foreach ($sdf['refund_item_list']['return_item'] as $k => $item) {
                if (empty($item['oid'])) {
                    $sdf['refund_item_list']['return_item'][$k]['oid'] = $item['outer_id'];
                }
            }
            $params['apply_detail_list'] = $params['apply_detail_list'] ? : [];
            if(is_string($params['apply_detail_list'])) {
                $params['apply_detail_list'] = json_decode($params['apply_detail_list'], true);
            }
            if(count($sdf['refund_item_list']['return_item']) == count($params['apply_detail_list']) 
                && count($params['apply_detail_list'])== 1) {
                $sdf['refund_item_list']['return_item'][0]['sku_uuid'] = $params['apply_detail_list'][0]['skuUuid'];
            }

            // 价保退款没有 sku_uuid，通过 item_id 与订单上的平台 skuid(shop_product_id) 匹配找出 oid，多条时用 PHP 取金额最大的子单，并回填 sku_uuid
            if ($sdf['bool_type'] & ome_refund_bool_type::__PROTECTED_CODE) {
                $order = $this->getOrder('order_id', $this->__channelObj->channel['shop_id'], $sdf['order_bn'], 'aftersale');
                if ($order && $order['order_id']) {
                    $db = kernel::database();
                    foreach ($sdf['refund_item_list']['return_item'] as $k => $item) {
                        if (!empty($item['item_id'])) {
                            $item_id = $db->quote($item['item_id']);
                            $order_id = intval($order['order_id']);
                            $sql = "SELECT ob.oid, ob.sku_uuid, COALESCE(ob.divide_order_fee, ob.amount, 0) AS order_fee "
                                . "FROM sdb_ome_order_objects ob "
                                . "INNER JOIN sdb_ome_order_items oi ON ob.obj_id = oi.obj_id AND oi.delete = 'false' "
                                . "WHERE ob.order_id = {$order_id} AND oi.shop_product_id = {$item_id} AND ob.delete = 'false'";
                            $rows = $db->select($sql);
                            if ($rows && is_array($rows)) {
                                usort($rows, function ($a, $b) {
                                    return bccomp((string)$b['order_fee'], (string)$a['order_fee'], 4);
                                });
                                $row = reset($rows);
                                if (!empty($row['oid'])) {
                                    $sdf['refund_item_list']['return_item'][$k]['oid'] = $row['oid'];
                                    if (isset($row['sku_uuid']) && $row['sku_uuid'] !== '' && $row['sku_uuid'] !== null) {
                                        $sdf['refund_item_list']['return_item'][$k]['sku_uuid'] = $row['sku_uuid'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    
        return $sdf;
    }

    protected function _getRefundFee($params, $sdf) {
        $refund_fee = $params['refund_fee'];
        if ($params['apply_detail_list']){
            if(is_string($params['apply_detail_list'])) {
                $params['apply_detail_list'] = json_decode($params['apply_detail_list'], true);
            }
            $order = app::get('ome')->model('orders')->db_dump(array('order_bn'=>$sdf['order_bn'], 'shop_id'=>$this->__channelObj->channel['shop_id']),'order_id');
            $skuUuid = $params['apply_detail_list'][0]['skuUuid'];
            $num = $sdf['refund_item_list']['return_item'][0]['num'];
            $obj = app::get('ome')->model('order_objects')->db_dump(array('order_id'=>$order['order_id'], 'sku_uuid'=>$skuUuid),'obj_id');
            if(empty($obj)) return $refund_fee;
            $saleObj = app::get('ome')->model('sales_objects')->db_dump(array('order_id'=>$order['order_id'], 'order_obj_id'=>$obj['obj_id']),'quantity,sales_amount');
            if($saleObj){
                if($num == $saleObj['quantity']){
                    $refund_fee = $saleObj['sales_amount'];
                } else {
                    $refund_fee = sprintf('%.2f', $saleObj['sales_amount']/$saleObj['quantity']*$num);
                }
            }
        }
        return $refund_fee;
    }

    protected function _getAddType($sdf) {
        if(in_array($sdf['refund_type'],array('refund','apply'))) { #退款
            return 'refund';
        } elseif ($sdf['refund_type'] == 'return') {
            return 'returnProduct';
        } elseif ($sdf['refund_type'] == 'reship') {
            if ($sdf['status'] == 'confirm_failed'){
                return 'returnProduct';
            }else{
               return 'reship'; 
            }
        }
    }

    protected function _formatAddItemList($sdf, $convert = array()) {
        
        if ($sdf['order']['tran_type'] == 'archive'){
            return $this->formatArchiveitemlist($sdf, $convert);
        }
        $convert = array(
            'sdf_field'     =>$sdf['order']['api_version'] >= 3 ? 'sku_uuid' : 'oid',
            'order_field'   =>'oid',
            'default_field' =>'outer_id'
        );
        return parent::_formatAddItemList($sdf, $convert);
    }

    protected function _returnProductAddSdf($sdf) {
        $sdf = parent::_returnProductAddSdf($sdf);
        if ($sdf['order']['tran_type'] == 'archive'){
            $sdf['archive'] = '1';
           
        }
        if(!$sdf) return false;
        
        $sdf['choose_type_flag'] = 0;
        if($sdf['return_product']) {
            $data = app::get('ome')->model('return_product_360buy')->db_dump(array('return_id'=>$sdf['return_product']['return_id'],'shop_id'=>$sdf['shop_id']), 'refund_version');

            $sdf['refund_version_change'] = $sdf['refund_version'] > $data['refund_version'] ? true :false;
        }

        return $sdf;
    }

    protected function _returnProductAdditional($sdf) {
        $ret = array(
            'model' => 'return_product_360buy',
            'data' => array(
                'shop_id'        => $sdf['shop_id'],
                'return_bn'      => $sdf['refund_bn'],
                'receive_state'  => $sdf['status'],
                'pickware_type'  => $sdf['pickware_type'],
                'buyer_nick'     => $sdf['buyer_nick'],
                'logi_no'        => $sdf['express_code'],
                'refund_version' => $sdf['refund_version'],
                'return_address' => $sdf['return_ware_address'],
                'pick_address'   => $sdf['pickware_address'],
                'customer_info'  => $sdf['customer_info'],
                'apply_detail'   => $sdf['apply_detail_list'],
            )
        );
        return $ret;
    }

   #重新计算单价($refundItems 以bn作主键的数组 捆绑商品使用捆绑商品的bn)
    protected function _calculateAddPrice($refundItems, $sdf) {
        if ($sdf['order']['tran_type'] == 'archive'){
            $db = kernel::database();
            foreach($refundItems as $k=>$v){
                $order_item = $db->selectrow("SELECT sendnum,item_id,price FROM sdb_archive_order_items WHERE order_id=".$sdf['order']['order_id']." AND bn='".$v['bn']."'");
                $refundItems[$k]['sendNum'] = $order_item['sendnum'];
                $refundItems[$k]['order_item_id'] = $order_item['item_id'];

            }
            return $refundItems;
        }
        if(empty($refundItems)) {
            return array();
        }

        return parent::_calculateAddPrice($refundItems, $sdf);
    }

    protected function _reshipAddItemList($sdf) {
        $reship_items = array();

        $rows = app::get('ome')->model('return_product_items')->getList('product_id,bn,name,num,price,amount,order_item_id',array('return_id'=>$sdf['return_product']['return_id']));
        $itemMdl   = app::get('ome')->model('order_items');
        foreach ($rows as $value) {
            $item = $itemMdl->db->selectrow("SELECT sum(sendnum) as sendnum FROM sdb_ome_order_items WHERE order_id=".$sdf['order']['order_id']." and product_id=".$value['product_id']." and `delete`='false'");
            $reship_items[] = array (
                'product_id' => $value['product_id'],
                'bn'         => $value['bn'],
                'name'       => $value['name'],
                'price'      => $value['price'],
                'amount'     => $value['amount'],
                'num'        => $value['num'],
                'sendNum'    => $item['sendnum'],
                'order_item_id'=>$value['order_item_id'],
            );
        }

        return $reship_items;
    }

    /**
     * 添加
     * @param mixed $params 参数
     * @return mixed 返回值
     */

    public function add($params){
        if ($params['refund_type'] == 'reship' && $params['refund_id']) {

            $returnModel = app::get('ome')->model('return_product');
            $count = $returnModel->count(array (
                'shop_id'   => $this->__channelObj->channel['shop_id'],
                'return_bn' => $params['refund_id'],
                'source'    => 'matrix',
            ));
            if ($count == 0) {
                $returnRsp                = $params;
                $returnRsp['status']      = 'WAIT_BUYER_RETURN_GOODS';
                $returnRsp['refund_type'] = 'return';
                kernel::single('ome_return')->get_return_log($returnRsp,$this->__channelObj->channel['shop_id'],$msg);
            }
        }

        return parent::add($params);
    }



    /**
     * formatArchiveitemlist
     * @param mixed $sdf sdf
     * @param mixed $convert convert
     * @return mixed 返回值
     */
    public function formatArchiveitemlist($sdf, $convert){

        $refund_item_list = $sdf['refund_item_list']['return_item'];
        $items = array();
        $skuObj = app::get('inventorydepth')->model('shop_skus');
        foreach($refund_item_list as $v){

            //bn
            $item_id = $v['item_id'];//array('item_id'=>$item_id,'shop_id'=>$sdf['shop_id']),'shop_product_bn'
            $sku_detail = $skuObj->db->selectrow("SELECT shop_product_bn FROM sdb_inventorydepth_shop_skus WHERE shop_sku_id='".$item_id."' AND shop_id='".$sdf['shop_id']."'");
            if ($sku_detail){
                $productData = kernel::single('material_basic_select')->dump('material_name,material_bn,bm_id',array('material_bn'=>$sku_detail['shop_product_bn']));
           
            
                $items[] = array(

                    'bn'            =>  $sku_detail['shop_product_bn'],
                    'product_id'    =>  $productData['product_id'],
                    'name'          =>  $productData['name'],
                    'price'         =>  $sdf['refund_fee'],
                    //'sendNum'       =>  $order_item['sendnum'],
                    
                    'num'           =>  $v['num'],
                );
            }
            
        }

        return $items;
    }


    protected function _refundAddSdf($sdf){

        $sdf = parent::_refundAddSdf($sdf);

        if (($sdf['response_bill_type'] != 'refundonly' && bccomp($sdf['order']['payed'], $sdf['refund_fee'],3) < 0) || $sdf['refund_all_money']) {
            $sdf['refund_fee'] = $sdf['order']['payed'];
        }
        return $sdf;
    }

}