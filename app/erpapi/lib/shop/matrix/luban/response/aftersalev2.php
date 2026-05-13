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
class erpapi_shop_matrix_luban_response_aftersalev2 extends erpapi_shop_response_aftersalev2
{
    //检查售后申请单版本是否更新
    protected function _getReturnVersionChange($sdf)
    {
        $version_change = false;
        
        //[抖音平台]只要modified最后更新时间有变化,版本就有变化
        if($sdf['modified'] > $sdf['return_product']['outer_lastmodify']){
            //加入判断备注和申请退款金额
            if(($sdf['return_product']['content'] != $sdf['reason']) || ($sdf['return_product']['money'] != $sdf['refund_fee'])){
                $version_change = true;
            }
        }
        
        //换货转退货(需要变更版本)
        if($sdf['refund_type']=='return' && $sdf['return_product']['return_type']=='change'){
            $version_change = true;
        }
        
        return $version_change;
    }
    
    //售后业务
    /**
     * 添加
     * @param mixed $params 参数
     * @return mixed 返回值
     */

    public function add($params){
        $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')售后业务处理[订单：' . $params['tid'] . ']';
        $this->__apilog['original_bn'] = $params['tid'];
        $this->__apilog['result']['data'] = array('tid'=>$params['tid'],'aftersale_id'=>$params['refund_id'],'retry'=>'false');
        $sdf = $this->_formatAddParams($params);
        if(empty($sdf)) {
            if(!$this->__apilog['result']['msg']) {
                $this->__apilog['result']['msg'] = '没有数据,不接收售后单';
            }
            return false;
        }
        $shopId = $sdf['shop_id'] = $this->__channelObj->channel['shop_id'];
        $sdf['shop_type'] = $this->__channelObj->channel['shop_type'];
        $sdf['shop']['delivery_mode'] = $this->__channelObj->channel['delivery_mode'];
        $field = 'order_id,status,process_status,ship_status,pay_status,payed,cost_payment,pay_bn,member_id,logi_id,logi_no,ship_name,ship_area,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile,shipping,is_protect,is_cod,source,order_type,abnormal,source_status,sync';
        $tgOrder = $this->getOrder($field, $shopId, $sdf['order_bn']);
        
        //抖音订单号去A查询
        if (empty($tgOrder) && substr($sdf['order_bn'], -1) === 'A') {
            $sdf['order_bn'] = substr($sdf['order_bn'], 0, -1);
            $tgOrder = $this->getOrder($field, $shopId, $sdf['order_bn']);
            $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')售后业务处理[订单：' . $sdf['order_bn'] . ']';
            $this->__apilog['original_bn'] = $sdf['order_bn'];
            $this->__apilog['result']['data'] = array('tid'=>$sdf['order_bn'],'aftersale_id'=>$params['refund_id'],'retry'=>'false');
        }
        
        //平台订单号去除A字母
        if(substr($sdf['platform_order_bn'], -1) === 'A') {
            $sdf['platform_order_bn'] = substr($sdf['platform_order_bn'], 0, -1);
        }
        
        //删除退款日志
        if(!$tgOrder && in_array(strtoupper($sdf['status']), array('SELLER_REFUSE_BUYER', 'SUCCESS', 'CLOSED'))) {
            $filter = array(
                'order_bn' => $sdf['order_bn'],
                'shop_id' => $sdf['shop_id'],
                'refund_bn' => $sdf['refund_bn']
            );
            app::get('ome')->model('refund_no_order')->delete($filter);
            $tgOrder = $this->getOrder($field, $shopId, $sdf['order_bn']);
        }
        
        //添加退款日志
        if (!$tgOrder) {
            if(!in_array(strtoupper($sdf['status']), array('SELLER_REFUSE_BUYER', 'SUCCESS', 'CLOSED'))) {
                $this->_dealRefundNoOrder($sdf);
            }
            $this->__apilog['result']['msg'] = '没有订单' . $sdf['order_bn'];
            return false;
        }
        
        $sdf['order'] = $tgOrder;
        

        //[换货完成又退货]获取换货产生的OMS订单进行退货操作
        // if ($type == 'reship' || $type == 'returnProduct') {
            list($is_change, $change_msg, $change_order, $convert) = $this->getChangeReturnProduct($sdf);
            if($is_change === true){
                // OMS生成的新订单号
                $sdf['tid'] = $sdf['order_bn'] = $change_order['order_bn'];
                
                // OMS换货生成的新订单信息
                $sdf['order'] = $change_order;
            }
        // }
        
        $type = $this->_getAddType($sdf);
        if(empty($type)) {
            if(!$this->__apilog['result']['msg']) {
                $this->__apilog['result']['msg'] = '所属店铺类型,不接收售后单';
            }
            return false;
        }
        //未签收的售后仅退款转为售后退货
        if($type == 'refund' 
            && $sdf['order']['ship_status'] == '1' 
            && $sdf['order']['source_status'] != 'TRADE_FINISHED'
            && in_array(app::get('ome')->getConf('ome.reship.refund.only.reship'), ['true', 'refund'])
            && intval($params['partRefundType']) == 0
            && in_array($sdf['tag_type'], ['6','7'])
        ) {
            $sdf['refund_to_returnProduct'] = true;
            if(app::get('ome')->getConf('ome.reship.refund.only.reship') != 'refund'){
                $type = 'returnProduct';
            }
        }

        if(is_array($sdf['refund_item_list'])) {

            $refundItemList = $this->_formatAddItemList($sdf, $convert);
            if(empty($refundItemList)) {
                $sdf['refund_item_list'] = '';
            }else{
                $sdf['refund_item_list'] = $this->_calculateAddPrice($refundItemList, $sdf);
            }
        }
    
        if($type == 'refund') {
            $sdf = $this->_refundAddSdf($sdf);

            //[兼容]订单已经全额退款并取消,创建退款单
            //场景：平台上订单已退款,矩阵先推送了更新取消订单,后面才推送了退款单,导致OMS没有创建退款单;
            //@todo：因为params有process_status订单状态检查,无法走到process方法中进行处理;
            //if($sdf['status']=='4' && $sdf['order']['process_status'] == 'cancel' && empty($sdf['refund_apply'])) {
            //    kernel::single('ome_order_refund')->createFinishRefund($sdf);
            //}

        } elseif( $type == 'returnProduct') {
            $sdf = $this->_returnProductAddSdf($sdf);
        } elseif($type == 'reship') {
            $sdf = $this->_reshipAddSdf($sdf);
        } else {
            if(!$this->__apilog['result']['msg']) {
                $this->__apilog['result']['msg'] = '不接收售后单';
            }
            return false;
        }

        if ( in_array($sdf['response_bill_type'], ['refund', 'refundonly']) ) {
            $refundList = kernel::single('erpapi_router_request')->set('shop',$this->__channelObj->channel['shop_id'])->finance_searchRefund([
                'refund_apply_bn' => $params['refund_id'],
                'order_bn' => $params['tid'],
            ]);

            $orderMdl        = app::get('ome')->model('orders');
            $optMdl          = app::get('ome')->model('operation_log');
            $abnormalMdl     = app::get('ome')->model('abnormal');
            $abnormalTypeMdl = app::get('ome')->model('abnormal_type');

            if ($refundList['rsp'] == 'succ' && $refundList['data'] && $sdf['order']['order_id']) {
                $oids = array_column($sdf['refund_item_list'], 'oid');
                foreach ($refundList['data']['aftersale_items'] as $value) {
                    if (!in_array($value['order_id'], $oids)) {
                        $abnormal = $abnormalMdl->db_dump(['order_id' => $sdf['order']['order_id']], 'abnormal_id');

                        $abnormal_type = $abnormalTypeMdl->db_dump(['type_name' => '退款单子单明细与平台不符'], 'type_id');
                        if (!$abnormal_type) {
                            $abnormal_type = ['type_name' => '退款单子单明细与平台不符'];
                            $abnormalTypeMdl->save($abnormal_type);
                        }

                        $orderMdl->set_abnormal([
                            'abnormal_id'      => $abnormal['abnormal_id'],
                            'order_id'         => $sdf['order']['order_id'],
                            'is_done'          => 'false',
                            'abnormal_memo'    => '退款单子单明细与平台不符',
                            'abnormal_type_id' => $abnormal_type['type_id'],
                        ]);

                        break;
                    }
                }
            }
        }elseif(in_array($sdf['response_bill_type'], array('return_product'))){
            //换货转退货的场景,需要作废换货单;
            if($sdf['return_product']['return_type'] == 'change'){
                $lubanLib = kernel::single('ome_reship_luban');
                $result = $lubanLib->transformExchange($sdf);
                if($result['rsp'] == 'succ'){
                    $sdf['return_product'] = array();
                }else{
                    //作废换货单失败
                    $lubanLib->disposeExchangeBusiness($sdf);
                }
            }
        }
        
        return $sdf;
    }

    protected function _formatAddParams($params) {
        $sdf = parent::_formatAddParams($params);
        if($params['tag_type'] ==1){
            $sdf['bool_type'] = ome_refund_bool_type::__PROTECTED_CODE;
        }
        
        //退款的类型
        $tag_type = $params['tag_type'];
        if($tag_type){
            $sdf['tag_type'] = self::$tag_types[$tag_type] ? self::$tag_types[$tag_type] : '0';
        }
        if($params['after_sale_type'] == '1') {
            $sdf['tag_type'] = '6';
        } elseif($params['after_sale_type'] == '2') {
            $sdf['tag_type'] = '7';
        }
        
        //子订单号
        $sdf['oid'] = $params['oid'];
        
        //平台售后单状态
        if($params['source_status']){
            $sdf['platform_status'] = trim($params['source_status']);
        }
        
        //售后申请描述
        $sdf['apply_remark'] = isset($params['remark']) ? trim($params['remark']) : '' ;
        
        //识别如果是已完成的售后，转成退款单更新的逻辑
        if($params['refund_type'] == 'return' && strtolower($params['status']) == 'success'){
            $refundOriginalObj = app::get('ome')->model('return_product');
            $refundOriginalInfo = $refundOriginalObj->getList('return_id', array('return_bn'=>$sdf['refund_bn'],'status' =>'4') , 0 , 1);
            if($refundOriginalInfo){
                $refundApplyObj = app::get('ome')->model('refund_apply');
                $refundApplyInfo = $refundApplyObj->getList('refund_apply_bn', array('return_id'=>$refundOriginalInfo[0]['return_id'],'status' =>array('0','1','2','5','6')) , 0 , 1);
                if($refundApplyInfo){
                    $sdf['refund_bn'] = $refundApplyInfo[0]['refund_apply_bn'];
                    $sdf['tmall_has_finished_return_product'] = true;
                }
            }
        }
        
        //换货转退货,更新return_type售后类型字段
        if($params['refund_type']=='return' && empty($params['return_type'])){
            $sdf['return_type'] = $params['refund_type'];
        }
        
        return $sdf;
    }

    protected function _getAddType($sdf)
    {
        //需要退货才更新为售后单
        if ($sdf['has_good_return'] == 'true') {
            if (in_array($sdf['order']['ship_status'], array('0'))) {
                //有退货，未发货的,做退款
                return 'refund';
            }elseif($sdf['refund_refer'] == 'aftersale'){
                //售后仅退款(场景：已发货、未签收)
                return 'refund';
            }elseif(in_array($sdf['order']['ship_status'],array('3','4')) && $sdf['tmall_has_finished_return_product']){
                //退款单
                return 'refund';
            }else{

                //有退货，已发货的,做售后
                return 'returnProduct';
            }
        }else{
            //无退货的，直接退款
            return 'refund';
        }
    }

    protected function _formatAddItemList($sdf, $convert=array()) {
        if($sdf['from_platform'] == 'yjdf'){
            $itemList = $sdf['refund_item_list']['return_item'];
            
            $arrItem = array();
            foreach ($itemList as $item) {
                $arrItem[$item['bn']] = $item;
            }
            
            return $arrItem;
        }else{
            $convert = array(
                'sdf_field'=>'oid',
                'order_field'=>'oid',
        
            );
            
            return parent::_formatAddItemList($sdf, $convert);
        }
    }
    
    /**
     * 平台扩展信息
     */
    protected function _returnProductAdditional($sdf)
    {
        $ret = array(
                'model' => 'return_product_luban',
                'data' => array(
                        'shop_id' => $sdf['shop_id'],
                        'oid' => $sdf['oid'],
                        'refund_type' => $sdf['refund_type'],
                        'bill_type' => $sdf['bill_type'],
                        'refund_fee' => $sdf['refund_fee'],
                        'extend_field' => json_encode($sdf['extend_field'])
                ),
        );
        
        return $ret;
    }
}