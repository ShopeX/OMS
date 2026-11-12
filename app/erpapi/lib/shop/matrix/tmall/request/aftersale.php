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
class erpapi_shop_matrix_tmall_request_aftersale extends erpapi_shop_request_aftersale {
    protected function __afterSaleApi($status, $returnInfo=null) {
        switch( $status ){
            case '3':
                $api_method = SHOP_AGREE_RETURN_I_GOOD_TMALL;
                break;
            case '5':
                $api_method = SHOP_REFUSE_RETURN_I_GOOD_TMALL;
                break;
            case '6'://同意退货
                $api_method = SHOP_AGREE_CHANGE_I_GOOD_TMALL;
                break;
            case '9'://拒绝退货
                $api_method = SHOP_REFUSE_CHANGE_I_GOOD_TMALL;
                break;
            case '10'://拒绝确认收货
                $api_method = SHOP_EXCHANGE_RETURNGOODS_REFUSE;
                break;
            default :
                $api_method = '';
                break;
        }
        return $api_method;
    }

    protected function __formatAfterSaleParams($aftersale,$status) {
        $shop_id = $this->__channelObj->channel['shop_id'];
        $oReturn_tmall = app::get('ome')->model('return_product_tmall');
        $return_tmall = $oReturn_tmall->dump(array('shop_id'=>$shop_id,'return_id'=>$aftersale['return_id']));
        $oReturn_address = app::get('ome')->model('return_address');
        $return_address = $oReturn_address->getDefaultAddress($shop_id);
        $params = array(
            'refund_id'     =>$aftersale['return_bn'],
            'refund_version'=>$return_tmall['refund_version'],
            'refund_type'   =>$return_tmall['refund_phase'],
            'return_type'   =>$return_tmall['refund_phase'],
        );
        switch ($status) {
            case '3':
                $batchList = kernel::single('ome_refund_apply')->return_batch('accept_return');
                $return_batch = $batchList[$shop_id];
                $params['seller_logistics_address_id'] = $return_tmall['contact_id'] ? $return_tmall['contact_id'] : $return_address['contact_id'];
                $params['memo'] = $return_batch['memo'] ? $return_batch['memo'] : '同意退货申请';
                $params['post_fee_bear_role'] = $aftersale['post_fee_bear_role'];#邮费承担方,买家承担值为1，卖家承担值为0
                break;
            case '5':
                $params['oid'] = $return_tmall['oid'];
                $params['imgext']         = $aftersale['imgext'];
                $params['refuse_proof']   = $aftersale['refuse_proof'];
                $params['refuse_message'] = $aftersale['refuse_message'];
                break;
            case '6'://同意退货
                $params['address_id'] = $return_tmall['contact_id'] ? $return_tmall['contact_id'] : $return_address['contact_id'];
                $params['dispute_id'] = $aftersale['return_bn'];
                break;
            case '9'://拒绝退货
                $params['dispute_id'] = $aftersale['return_bn'];
                $params['seller_refuse_reason_id'] = $aftersale['seller_refuse_reason_id'];
            break;
            case '10'://拒绝确认收货
                $params = array(
                    'leave_message'     => $aftersale['leave_message'],
                    'leave_message_pics'=> $aftersale['leave_message_pics'],
                    'dispute_id'        => ($aftersale['return_bn'] ? $aftersale['return_bn'] : $aftersale['dispute_id']),
                    'seller_refuse_reason_id'=>$aftersale['seller_refuse_reason_id'],

                );
            break;
            default: break;
        }
        return $params;
    }

    /**
     * 获取RefuseReason
     * @param mixed $returninfo returninfo
     * @return mixed 返回结果
     */

    public function getRefuseReason($returninfo){

        $title = '店铺('.$this->__channelObj->channel['name'].')获取拒绝换货原因列表,(售后单号:'.$returninfo['return_bn'].')';

        $params = array(
            'dispute_id'=>$returninfo['return_bn'],
            //'fields'=>'reason_id,reason_text',

        );
        $result = $this->__caller->call(SHOP_EXCHANGE_REFUSEREASON_GET, $params, array(), $title, 10, $returninfo['return_bn']);

        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }elseif(isset($result['res']) && $result['res']){
            $rs['msg'] = $result['res'];
        }
        $rs['rsp'] = $result['rsp'];
        $data =json_decode($result['data'], true);

        $rs['data'] = $result['data'] ? $data : array();
        return $rs;


    }

    /**
     * consignGoods
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function consignGoods($data){
        $title = '店铺('.$this->__channelObj->channel['name'].')换货订单卖家发货,(售后单号:'.$data['dispute_id'].')';
        $api_name = SHOP_EXCHANGE_CONSIGNGOODS;
        $params = array(
            'dispute_id'            =>  $data['dispute_id'],
            'logistics_no'          =>  $data['logistics_no'],
            'corp_type'            =>  $data['corp_type'],
            'logistics_type'        =>  '200',
            'logistics_company_name'=>  $data['logistics_company_name'],
        );
        $topspeedLib = kernel::single('ome_order_platform_taobao_aftersale');
        $flagType = isset($data['flag_type']) ? $data['flag_type'] : 0;
        $forceConsigngoods = $topspeedLib->shouldForceConsigngoods($flagType);
        if ($forceConsigngoods) {
            // 天猫极速换货：强制 consigngoods，禁止走新换货升级 confirm.consign
        } elseif (kernel::single('ome_reship_const')->isNewExchange($data['flag_type'])) {
            $api_name               = SHOP_EXCHANGE_CONFIRMCONSIGN;
            $params['company_code'] = $data['corp_type'];
            $params['company_name'] = $data['logistics_company_name'];
            unset($params['corp_type'], $params['logistics_company_name']);
        }
        $result = $this->__caller->call($api_name, $params, array(), $title, 10, $data['order_bn']);
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }elseif(isset($result['res']) && $result['res']){
            $rs['msg'] = $result['res'];
        }
        $rs['rsp'] = $result['rsp'];


        $rs['data'] = $result['data'] ? $result['data'] : array();
        // 发货记录

        $log_id = uniqid($_SERVER['HOSTNAME']);
        $status = ($rs['rsp']=='succ') ? 'succ' : 'fail';
        $log = array(
            'shopId'           => $this->__channelObj->channel['shop_id'],
            'ownerId'          => '16777215',
            'orderBn'          => $data['order_bn'],
            'deliveryCode'     => $params['logistics_no'],
            'deliveryCropCode' => isset($params['corp_type']) ? $params['corp_type'] : $params['company_code'],
            'deliveryCropName' => isset($params['logistics_company_name']) ? $params['logistics_company_name'] : $params['company_name'],
            'receiveTime'      => time(),
            'status'           => $status,
            'updateTime'       => time(),
            'message'          => $rs['msg'] ? $rs['msg'] : '成功',
            'log_id'           => $log_id,
        );

        $shipmentLogModel = app::get('ome')->model('shipment_log');
        $shipmentLogModel->insert($log);
        if ($data['order_id']){
            $orderModel    = app::get('ome')->model('orders');


            $updateOrderData = array(
                'sync'           => $status,
                'up_time'        => time(),

            );

            $orderModel->update($updateOrderData,array('order_id'=>$data['order_id'],'sync|noequal'=>'succ'));

            // 极速换货发货成功：解除 18 拒发禁发标
            if ($status === 'succ' && $forceConsigngoods) {
                $topspeedLib->releaseRefuseSendIntercept($data['order_id']);
            }

        }

        return $rs;
    }

    /**
     * refuseReturnGoods
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function refuseReturnGoods($data){

        $title = '店铺('.$this->__channelObj->channel['name'].')拒绝确认发货,(售后单号:'.$data['dispute_id'].')';
        $result = $this->__caller->call(SHOP_EXCHANGE_RETURNGOODS_REFUSE, $data, array(), $title, 10, $data['dispute_id']);

        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }elseif(isset($result['res']) && $result['res']){
            $rs['msg'] = $result['res'];
        }
        $rs['rsp'] = $result['rsp'];
        $rs['data'] = $result['data'] ? json_decode($result['data'], true) : array();
        return $rs;

    }

    /**
     * returnGoodsAgree
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function returnGoodsAgree($data){
        //天猫换货升级 【确认收货并发货】合并，在换货订单发货后走新接口tmall.exchange.confirm.consign
        if (kernel::single('ome_reship_const')->isNewExchange($data['flag_type'])) {
            return $this->succ('天猫换货升级换出单发货后在回写状态！');
        }
        unset($data['flag_type']);
        $title = '店铺('.$this->__channelObj->channel['name'].')确认收货,售后单号:' . $data['dispute_id'];

        $this->__caller->call(SHOP_EXCHANGE_RETURNGOODS_AGREE, $data, array(), $title, 10, $data['dispute_id']);
    }

    /**
     * 获取_aftersale_detail
     * @param mixed $aftersale_bn aftersale_bn
     * @return mixed 返回结果
     */
    public function get_aftersale_detail($aftersale_bn){

        $params['dispute_id'] = $aftersale_bn;

        $title = "店铺(".$this->__channelObj->channel['name'].")获取前端店铺".$aftersale_bn."的换货单详情";


        $api_name = SHOP_EXCHANGE_GET;

        $rsp = $this->__caller->call($api_name,$params,array(),$title,20,$aftersale_bn);

        $result = array();
        $result['rsp']        = $rsp['rsp'];
        $result['err_msg']    = $rsp['err_msg'];
        $result['msg_id']     = $rsp['msg_id'];
        $result['res']        = $rsp['res'];
        $result['data']       = json_decode($rsp['data'],1);


        return $result;

    }
    
    /**
     * 换货拒绝发出新品请求参数，回传平台接口名
     *
     * @return null
     */
    protected function get_consigngoods_refuse_apiname()
    {
        return SHOP_EXCHANGE_CONSIGNGOODS_REFUSE;
    }
    
    /**
     * 换货拒绝发出新品请求参数
     *
     * @param $sdf
     * @return void
     */
    protected function get_consigngoods_refuse_params($params=[])
    {
        // 返回字段列表，必填。支持dispute_id、bizorder_id、modified、status、speed_exchange；speed_exchange按需返回1。
        $fields = 'dispute_id,bizorder_id,modified,status,speed_exchange';
        
        // params
        $sdf = [
            'dispute_id' => $params['dispute_id'], // 换货单号，必选项。
            'fields' => $fields,
            //'leave_message_pics' => '', // 拒绝发新品的凭证图片，可选。
        ];
        
        // 拒绝原因ID，需属于当前换货单允许的原因，必选项。
        if(isset($params['seller_refuse_reason_id']) && $params['seller_refuse_reason_id']){
            $sdf['seller_refuse_reason_id'] = $params['seller_refuse_reason_id'];
        }
        
        // 拒绝发新品备注信息，可选。
        if(isset($params['leave_message']) && $params['leave_message']){
            $sdf['leave_message'] = $params['leave_message'];
        }
        
        return $sdf;
    }
}