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


class ome_service_aftersale{
    
    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct(&$app){
        $this->app = $app;
    }

    /**
     * 售后申请
     * @access public
     * @param int $return_id 售后申请ID
     */
    public function add_aftersale($return_id){
        $returnModel = $this->app->model('return_product');
        $returninfo = $returnModel->dump($return_id);
        kernel::single('erpapi_router_request')->set('shop', $returninfo['shop_id'])->aftersale_addAfterSale($returninfo);
    }
    
    /**
     * 售后申请状态修改
     * @access public
     * @param int $return_id 售后申请ID
     */
    public function update_status($return_id,$status='',$mod='async',$memo=array()){
        $returnModel = $this->app->model('return_product');
        $returninfo = $returnModel->dump($return_id);
        if (is_array($memo)) {
            $returninfo['refuse_message'] = $memo['refuse_message'];
            $returninfo['refuse_proof']   = $memo['refuse_proof'];
            $returninfo['imgext']         = $memo['imgext'];
        }
        //退换货单取消拒绝备注
        if (!$memo) {
            $reshipInfo = app::get('ome')->model('reship')->db_dump(['return_id' => $return_id], 'return_id,memo');
            $position   =  strrpos($reshipInfo['memo'], '拒绝备注:');
            if ($position !== false) {
                $returninfo['refuse_message'] = substr($reshipInfo['memo'], $position + strlen('拒绝备注:'));
            }
        }
        if($returninfo['source'] == 'matrix' && $returninfo['shop_type'] == 'tmall'){
            #拒绝退货的
            if($status == '5'){
                #没有凭证的，不再往前端打请求，避免二次请求
                if(empty($memo)){
                    return true;
                }
            }
            if ($status == '5' && $memo['refund_type']=='change'){//换货拒绝
                $status = '9';
                $returninfo['seller_refuse_reason_id'] = $memo['seller_refuse_reason_id'] ? : 153;
            }
        }

        if ($returninfo['source'] == 'matrix' && in_array($returninfo['shop_type'],array('website')) && $returninfo['return_type'] == 'change' ){
            if (!in_array($returninfo['status'],array('3','4'))  && $status!='5'){
                return true;
            }
            //PUBLICB2C换货时状态转换因为是同一接口
            if ($returninfo['status'] == '3' && $status == ''){//同意
                $status = '13';
            }
            if ($status == '5'){//拒绝
                $status = '15';
                $returninfo['seller_refuse_reason_id'] = $memo['seller_refuse_reason_id'];
                $returninfo['refuse_message'] = self::$refuse_reason[$memo['seller_refuse_reason_id']];

            }
            if ($returninfo['status'] == '4'){
                $status = '16';
            }

        }
        // 订单
        $returninfo['order'] = app::get('ome')->model('orders')->db_dump($returninfo['order_id']);

        if ($returninfo['source'] == 'matrix' && in_array($returninfo['shop_type'],array('wxshipin')) && $returninfo['return_type'] == 'change' ){
            if ($status!='5'){
                //return true;
            }
            
        }
        // 退货地址库
        // $returninfo['return_address'] = app::get('ome')->model('return_address')->db_dump(array ('shop_id' => $['shop_id']));
        
        if ($returninfo['source'] == 'matrix' && in_array($returninfo['shop_type'],array('bbc','ecos.b2c','ecos.b2b2c.stdsrc','360buy','meituan4medicine'))){
            $returninfo['memo'] = $memo;
        }elseif($returninfo['source'] == 'matrix' && in_array($returninfo['shop_type'], array('luban','jd'))){
            //使用售后申请单上的内容
            $returninfo['memo'] = $memo;
        }
        
        $rs = kernel::single('erpapi_router_request')->set('shop', $returninfo['shop_id'])->aftersale_updateAfterSaleStatus($returninfo,$status,$mod);
        if ($mod == 'sync') {
            return $rs;
        }
    }

    /**
     * 退款留言
     * @access  public
     * @author cyyr24@sina.cn
     */
    function refund_message($apply_id,$type){
        $data = array();
        if (in_array($type,array('return','change'))) {
            $oReturn = $this->app->model('return_product');
            $oReturn_tmall = $this->app->model('return_product_tmall');
            $return = $oReturn->dump($apply_id);
            $shop_id = $return['shop_id'];
            $data['refund_bn'] = $return['return_bn'];
            $return_tmall = $oReturn_tmall->dump(array('return_bn'=>$return['return_bn'],'shop_id'=>$shop_id));
            if ($return_tmall) {
                $data['refund_phase'] = $return_tmall['refund_phase'];
                $data['refund_version'] = $return_tmall['refund_version'];
            }
            $data['tmall_type'] = $type;
        }else{
            $oRefund = $this->app->model('refund_apply');
            $refund = $oRefund->dump($apply_id);
            $shop_id = $refund['shop_id'];
            $refund_bn = $refund['refund_apply_bn'];
            $oRefund_tmall = $this->app->model('refund_apply_tmall');
            $refund_tmall = $oRefund_tmall->dump(array('apply_id'=>$apply_id,'shop_id'=>$shop_id));
            
            if ($refund_tmall) {
                $data['refund_phase'] = $refund_tmall['refund_phase'];
                $data['refund_version'] = $refund_tmall['refund_version'];
            }
            $data['refund_bn'] = $refund_bn;
        }
        $rs = kernel::single('erpapi_router_request')->set('shop', $shop_id)->finance_getRefundMessage($data);
         if($rs){
            if($rs['rsp'] == 'succ'){
                $tmp = $rs['data'];
                $tmp = $tmp['refund_messages']['refund_message'];
                
                 foreach ($tmp as $tk=>$tv) {
                    if (isset($tv['pic_urls'])) {
                        $tmp[$tk]['voucher_urls'] = $tv['pic_urls']['pic_url'][0]['url'];
                    }
                }
                return $tmp;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    
    /**
     * 拒绝退货
     * @access  public
     * @author cyyr24@sina.cn
     */
    function refuse_return($returninfo){
        $returnModel = $this->app->model('return_product');
        $return_id = $returninfo['return_id'];
        $return = $returnModel->dump($return_id);
        $returninfo['return_bn'] = $return['return_bn'];
        $returninfo['order_id'] = $return['order_id'];

        $rs = array('rsp'=>'fail','msg'=>'失败');
        return $rs;
    }

    /**
     * 获取平台店铺售后退货地址库
     * 
     * @param string $shop_id 店铺ID
     * @param string $search_type 搜索类型,默认为空
     * @param int $page 页码
     * @return array
     */
    function searchAddress($shop_id, $search_type='', $page=0)
    {
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->logistics_searchAddress($search_type, $page);
        
        return $result;
    }
    
    /**
     * 退货回填物流单号.
     * @access  public
     * @author cyyr24@sina.cn
     */
    function update_return_logistics($reship_id){
        $oReship = $this->app->model('reship');
        $reshipinfo = $oReship->dump($reship_id);
        $rs = kernel::single('erpapi_router_request')->set('shop', $reshipinfo['shop_id'])->logistics_updateReturnLogistics($reshipinfo);
    }

    /**
     * 拒绝理由获取
     *  sunjing@shopex.cn
     */
    public function refuse_reason($return_id){
        $returnModel = $this->app->model('return_product');
        $returninfo = $returnModel->dump(array('return_id'=>$return_id,'source'=>'matrix','return_type'=>'change'),'return_bn,return_id,shop_id');
        if ($returninfo){
            $rs = kernel::single('erpapi_router_request')->set('shop', $returninfo['shop_id'])->aftersale_getRefuseReason($returninfo);

            if($rs){
                if($rs['rsp'] == 'succ'){
                    $tmp = $rs['data']['exchange']['exchange_refusereason'];
                    $return_model = $this->app->model('return_product_tmall');
                    $return_model->update(array('refusereason'=>json_encode($tmp)),array('return_id'=>$return_id));

                    return $tmp;
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }

    }

    /**
     * 根据售后单信息返回换货单类型相关信息
     * sunjing@shopex.cn
     */
    public function get_return_type($returninfo){

        $tmallObj = app::get('ome')->model('return_product_tmall');
        $tmall_detail = $tmallObj->dump(array('return_id'=>$returninfo['return_id'],'refund_type'=>'change'),'*');

        return $tmall_detail;

    }

    /**
     * 卖家确认收货(换货)
     * 
     */
    public function returngoods_agree($return_id){
        $returnModel = $this->app->model('return_product');
        $returninfo = $returnModel->dump(array('return_id'=>$return_id,'source'=>'matrix','return_type'=>'change'),'return_bn,return_id,shop_id,return_type,flag_type');
        if($returninfo){
            $params = array(
                'dispute_id'    =>  $returninfo['return_bn'],
                'flag_type'     =>  $returninfo['flag_type'],

            );
            $rs = kernel::single('erpapi_router_request')->set('shop', $returninfo['shop_id'])->aftersale_returnGoodsAgree($params);
        }


    }

    /**
     * 
     * 卖家拒绝确认收货
     */
    public function returngoods_refuse($data){
        $return_bn = $data['dispute_id'];
        $returnObj = app::get('ome')->model('return_product');
        $return_detail = $returnObj->dump(array('return_bn'=>$return_bn,'source'=>'matrix','return_type'=>'change'),'*');
        if ($return_detail){
            $data['shop_id'] = $return_detail['shop_id'];
            $data['return_id'] = $return_detail['return_id'];
            $data['order_id'] = $return_detail['order_id'];

            //return_bn
            $data['return_bn'] = $return_detail['return_bn'];

            //request
            $rs = kernel::single('erpapi_router_request')->set('shop', $return_detail['shop_id'])->aftersale_updateAfterSaleStatus($data,'10','sync');

            return $rs;
        }

    }
    
    /**
     * [换货生成的新订单]卖家发货完成后回传平台确认收货
     * 
     * @todo 卖家进行发货tmall.exchange.consigngoods
     * @param int $order_id
     * @return bool || array
     */
    public function exchange_consigngoods($order_id)
    {
        $orderObj = app::get('ome')->model('orders');
        $reshipObj = app::get('ome')->model('reship');
        $returnObj = app::get('ome')->model('return_product');
        
        //换货产生的新订单信息
        $order_detail = $orderObj->dump(array('order_id'=>$order_id,'createway'=>'after'),'order_id,shop_id,order_bn,shop_type,relate_order_bn,platform_order_bn');
        $shop_type = $order_detail['shop_type'];
        
        //获取原订单的换货信息
        $filter = array('change_order_id'=>$order_id,'return_type'=>'change','source'=>'matrix');
        
        //店铺类型
        if(in_array($shop_type, array('taobao', 'tmall'))){
            $filter['shop_type'] = 'tmall';
            $shop_type = 'tmall';
        }else{
            $filter['shop_type'] = $shop_type;
        }
        
        $reship_detail = $reshipObj->dump($filter, 'reship_id,return_id,flag_type');
        if (!$order_detail || !$reship_detail){
            return true;
        }
        
        //售后申请单信息
        if($shop_type == 'tmall'){
            $returnInfo = kernel::single('ome_service_aftersale')->get_return_type(array('return_id'=>$reship_detail['return_id']));
        }else{
            $returnInfo = $returnObj->dump(array('return_id'=>$reship_detail['return_id']), '*');
        }
        
        //获取换货订单的发货物流信息
        $sql = "SELECT d.delivery_id,d.logi_no,d.logi_name,d.logi_id FROM sdb_ome_delivery_order AS dord
                                            LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                            WHERE dord.order_id=".$order_id." AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false' AND d.status  IN('succ')";
        $delivery_detail = $orderObj->db->selectrow($sql);
        if ($delivery_detail){
            //物流公司信息
            $corpObj = app::get('ome')->model('dly_corp');
            $corp_detail = $corpObj->dump(array('corp_id'=>$delivery_detail['logi_id']),'type,name');
            
            //params
            $params = array(
                'order_id'               => $order_id,
                'order_bn'               => $order_detail['order_bn'],
                'relate_order_bn'        => $order_detail['relate_order_bn'],
                'return_id'              => $returnInfo['return_id'],
                'return_bn'              => $returnInfo['return_bn'],
                'dispute_id'             => $returnInfo['return_bn'],
                'logistics_no'           => $delivery_detail['logi_no'],
                'corp_type'              => $corp_detail['type'],
                'logistics_company_name' => $delivery_detail['logi_name'],
                'address_id'             => $returnInfo['address_id'], //退货地址ID
                'flag_type'              => $reship_detail['flag_type'],//新换货标识
                'platform_order_bn'      => $order_detail['platform_order_bn'],
            );
            $rs = kernel::single('erpapi_router_request')->set('shop', $order_detail['shop_id'])->aftersale_consignGoods($params);
            
            //tmall
            if($shop_type == 'tmall'){
                app::get('ome')->model('return_product_tmall')->update(array('seller_logistic_no'=>$delivery_detail['logi_no'],'seller_logistic_name'=>$delivery_detail['logi_name']),array('return_id'=>$reship_detail['return_id']));
            }
            
            return $rs;
        }
        
        return true;
    }

    /**
     * 卖家确认收货(退/换货)
     * 
     */
    public function returngoods_confirm($return_id){
        $returnModel = app::get('ome')->model('return_product');
        $returninfo = $returnModel->db_dump(array('return_id'=>$return_id,'source'=>'matrix'),'return_bn,return_id,shop_id,kinds,platform_order_bn,order_id');
        if($returninfo){
            $rs = kernel::single('erpapi_router_request')->set('shop', $returninfo['shop_id'])->aftersale_returnGoodsSign($returninfo);
            $rs = kernel::single('erpapi_router_request')->set('shop', $returninfo['shop_id'])->aftersale_returnGoodsConfirm($returninfo);
        }
    }
    
    /**
     * 同步添加抖音售后单备注内容
     * 
     * @return array
     */
    function syncReturnRemark($reshipInfo)
    {
        $shop_id = $reshipInfo['shop_id'];
        
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->aftersale_syncReturnRemark($reshipInfo);
        
        return $result;
    }


    /**
     * 商家代客填写退货单号
     * 
     * @return array
     */
    public function aftersaleSubmitReturnInfo($reship_id)
    {
        $reshipInfo = app::get('ome')->model('reship')->db_dump(['reship_id' => $reship_id]);
        if ($reshipInfo['source'] == 'local') {
            return ['res'=>'succ'];
        }

        $corp_type = '';
        $corpInfo  = app::get('ome')->model('dly_corp')->db_dump(['name'=>$reshipInfo['return_logi_name']], 'corp_id,type');
        if ($corpInfo) {
            $corp_type = $corpInfo['type'];
        }

        // 根据物流公司名称获取物流编码
        try {
            $classname = sprintf("logisticsmanager_waybill_%s",$reshipInfo['shop_type']);
            if (class_exists($classname)){
                $platformLogistics = kernel::single($classname)->logistics();
                if (is_array($platformLogistics)) {
                    foreach ($platformLogistics as $k => $v) {
                        if ($v['name'] == $reshipInfo['return_logi_name']) {
                            $corp_type = $v['code'];
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {}

        if (!$corp_type) {
            return ['res'=>'fail', 'msg'=>'corp_type is null'];
        }

        $subMsg = [
            'reship_bn'         =>  $reshipInfo['reship_bn'],
            'return_logi_no'    =>  $reshipInfo['return_logi_no'],
            'return_logi_code'  =>  $corp_type,
            // 'reship_id'         =>  $reshipInfo['reship_id'],
            // 'shop_id'           =>  $reshipInfo['shop_id'],
            // 'shop_type'         =>  $reshipInfo['shop_type'],
            // 'return_logi_name'  =>  $reshipInfo['return_logi_name'],
        ];
        $rs = kernel::single('erpapi_router_request')->set('shop', $reshipInfo['shop_id'])->aftersale_submitReturnInfo($subMsg);
        return $rs;
    }

}
