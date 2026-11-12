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
 * User: jintao
 * 售后单请求相关接口
 */
class erpapi_shop_request_aftersale extends erpapi_shop_request_abstract
{
    //售后类型
    public $_return_type = array(
            'return'   => '退货',
            'change'   => '换货',
            'refund'   => '退款',
            'jdchange' => '京东换货',
    );
    
    /**
     * 添加AfterSale
     * @param mixed $returninfo returninfo
     * @return mixed 返回值
     */

    public function addAfterSale($returninfo){}

    protected function __afterSaleApi($status, $returnInfo=null) {
        return '';
    }

    protected function __formatAfterSaleParams($aftersale,$status) {
        return array();
    }
    
    /**
     * 格式化平台请求退款返回的状态
     * 
     * @param array $response
     * @return array
     */
    protected function _formatResultStatus($response){
        return $response;
    }
    
    public function updateAfterSaleStatus($aftersale, $status='', $mod='async')
    {
        $rs = array();
        if(empty($aftersale)) {
            return array('rsp'=>'fail', 'msg'=>'no return');
        }
        
        //售后类型
        $return_type = $aftersale['return_type'];
        
        //订单信息
        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->dump($aftersale['order_id'], 'order_bn,relate_order_bn,createway');
        
        //换货生成的OMS订单,退货时找到原平台订单号
        if($order['createway'] == 'after' && $order['relate_order_bn']){
            $order['order_bn'] = $order['relate_order_bn'];
        }
        
        //售后确认状态
        if (!$status) {
            $status = $aftersale['status'];
        }
        
        $api_method = $this->__afterSaleApi($status, $aftersale);
        if (empty($api_method)) {
            return true;
        }
        
        $return_title = ($this->_return_type[$return_type] ? $this->_return_type[$return_type] : '售后');
        $return_title .= '单号:'. $aftersale['return_bn'];
        
        $title = '店铺('.$this->__channelObj->channel['name'].')更新[交易售后状态]:'.$status.',(订单号:'.$order['order_bn']. $return_title .')';
        $params = $this->__formatAfterSaleParams($aftersale, $status);
        $params['tid'] = $order['order_bn'];
        
        if($params['status'] == '5'){
            $params['content']!='' ? $params['content'] : 'erp拒绝';
        }
        
        $callback = array();
        if ($mod == 'async') {
            $callback = array(
                'class' => get_class($this),
                'method' => 'callback',
            );
        }
        
        //添加请求失败记录
        $api_msg = '';
        $obj_type = 'reship';
        $obj_bn = ($aftersale['return_bn'] ? $aftersale['return_bn'] : $aftersale['dispute_id']);
        $apiFailId = app::get('erpapi')->model('api_fail')->saveTriggerRequest($obj_bn, $obj_type, $api_method, $api_msg);
        if ($callback) {
            // api_fail_id
            if ($apiFailId) {
                $callback['params']['api_fail_id'] = $apiFailId;
            }
            
            // 退换货/退款拒绝回调参数（抖音拒绝需用 apply_id 收口本地单据）
            $callback['params']['obj_type'] = $obj_type;
            $callback['params']['return_bn'] = $obj_bn;
            $callback['params']['order_bn'] = $order['order_bn'];
            
            // 申请退款单ID
            if (!empty($aftersale['apply_id'])) {
                $callback['params']['apply_id'] = $aftersale['apply_id'];
            }
            
            if (isset($aftersale['prev_status']) && $aftersale['prev_status'] !== '' && $aftersale['prev_status'] !== null) {
                $callback['params']['prev_status'] = $aftersale['prev_status'];
            }
            
            if (!empty($aftersale['order_id'])) {
                $callback['params']['order_id'] = $aftersale['order_id'];
            }
            
            if (!empty($aftersale['shop_id'])) {
                $callback['params']['shop_id'] = $aftersale['shop_id'];
            }
        }
        
        //request
        $result = $this->__caller->call($api_method, $params, $callback, $title, 10, $order['order_bn']);
        
        //[抖音平台]格式化退货单状态()
        if($aftersale['shop_type'] == 'luban'){
            if($result['rsp'] == 'succ' && $mod == 'sync'){
                $result = $this->_formatResultStatus($result);
            }
        }
        
        //msg
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }elseif(isset($result['res']) && $result['res']){
            $rs['msg'] = $result['res'];
        }
        
        $rs['rsp'] = $result['rsp'];
        $rs['data'] = is_string($result['data']) ? @json_decode($result['data'], true) : (array) $result['data'];
        
        return $rs;
    }

    /**
     * 添加Reship
     * @param mixed $reship reship
     * @return mixed 返回值
     */
    public function addReship($reship){}

    /**
     * 更新ReshipStatus
     * @param mixed $reship reship
     * @return mixed 返回值
     */
    public function updateReshipStatus($reship){}

    /**
     * 获取RefuseReason
     * @param mixed $return_id ID
     * @return mixed 返回结果
     */
    public function getRefuseReason($return_id){}

    /**
     * consignGoods
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function consignGoods($data){}

    /**
     * refuseReturnGoods
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function refuseReturnGoods($data){}

    /**
     * returnGoodsAgree
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function returnGoodsAgree($data){}
    
    /**
     * returnGoodsSign
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function returnGoodsSign($data){}

    /**
     * returnGoodsConfirm
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function returnGoodsConfirm($data){}

    /**
     * 获取_aftersale_detail
     * @param mixed $aftersale_bn aftersale_bn
     * @return mixed 返回结果
     */
    public function get_aftersale_detail($aftersale_bn){}

    /**
     * 获取审核原因
     * 
     * @return void
     * @author 
     * */
    public function getApproReason($sdf)
    {
        $title = '获取审核原因';

        $params = array (
            'service_id'   => $sdf['return_bn'],
            'parent_code'  => '',
            'operate_nick' => '',
            'operate_pin'  => '',
        );

        $res = $this->__caller->call(SHOP_ASC_AUDIT_REASON_GET, $params, array (), $title, 10, $sdf['service_id']);

        if ($res['rsp'] == 'succ' && $data = @json_decode($res['data'],true)) {
            $res['data'] = $data['reason_list'];
        }

        return $res;
    }
    
    //售后原因
    public function getReturnResaon($params){
        $this->error('不支持获取售后原因', 'e00100');
    }
    
    /**
     * 同步售后单备注内容
     */
    public function syncReturnRemark($params){
        $this->error('不支持同步售后单备注内容', 'e00100');
    }
    
    /**
     * [淘宝极速换货]换货拒绝发出新品
     *
     * @param $params
     * @return array
     */
    public function consigngoodsRefuse($params)
    {
        $title = '换货拒绝发出新品';
        $disputeId = isset($params['dispute_id']) ? trim((string)$params['dispute_id']) : '';
        
        // check
        if ($disputeId === '') {
            return $this->error('换货单号为空，不能请求接口。');
        }
        
        // api接口
        $api_name = $this->get_consigngoods_refuse_apiname();
        if (empty($api_name)){
            return $this->error('接口暂不支持换货拒绝发出新品!');
        }
        
        // 请求参数
        $params = $this->get_consigngoods_refuse_params($params);
        if (empty($params)){
            return $this->error('换货拒绝发出新品接口，没有请求参数!');
        }
        
        // request
        $result = $this->__caller->call($api_name, $params, null, $title, 5, $disputeId);
        
        return $result;
    }
    
    /**
     * 换货拒绝发出新品请求参数，回传平台接口名
     *
     * @return null
     */
    protected function get_consigngoods_refuse_apiname()
    {
        return null;
    }
    
    /**
     * 换货拒绝发出新品请求参数
     *
     * @param $params
     * @return void
     */
    protected function get_consigngoods_refuse_params($params=[])
    {
        return [];
    }
}