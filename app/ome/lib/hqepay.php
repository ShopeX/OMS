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

#华强宝物流跟踪
class ome_hqepay{
    function detail_delivery($logi_no = false,$order_bn = false,$type = ''){
        #获取物流数据
        $data = $type=='return'?$this->getReturnInfo($logi_no):$this->getDeliveryInfo($logi_no);
        if(empty($data['logi_type'])) {
            $shipping = $this->getShipping($order_bn);
            if($shipping['logi_type']) {
                $data['logi_type'] = $shipping['logi_type'];
                $data['logi_name'] = $shipping['logi_name'];
            }
        }
        $rpc_data['order_bn'] = $order_bn;
        $rpc_data['logi_code'] = $data['logi_type'];#物流编码
        $rpc_data['company_name'] = $data['logi_name'];
        $rpc_data['logi_no'] = $data['logi_no'];
        //if (strtoupper($data['logi_type']) == 'SF') {
            $data['ship_mobile'] = kernel::single('ome_view_helper2')->modifier_ciphertext($data['ship_mobile'], 'order', 'ship_name');
            $rpc_data['customer_name'] = substr($data['ship_mobile'],-4);
        //}
        $rpc_result = $this->get_dly_info($rpc_data);
        if($rpc_result['rsp'] == 'succ'){
            $count = count( $rpc_result['data']);
            $max = $count - 1;#最新那条物流记录
            $html = "<ul style='margin-top:10px;'>";
            foreach($rpc_result['data'] as $key=>$val){
                #这时间是最新的
                if($max == $key ){
                    $html .= "<li style='line-height:15px;border-bottom:1px dotted  #ddd;'><font  style='font-size:13px;COLOR: red'>".$val['AcceptTime']."".$val['AcceptStation']."</font><li/>";
                }else{
                    $html .= "<li style='line-height:15px;border-bottom:1px dotted  #ddd;'>"."<em style='font-size:13px;COLOR: black'>".$val['AcceptTime']."</em>&nbsp;&nbsp;".$val['AcceptStation']." <li/>";
                }
            }
            $html .='</ul>';
        }else{
            $html = "<ul>";
            if($rpc_result['err_msg'] == "'HTTP Error 500: Internal Server Error'"){
                $html .= "<li style='line-height:15px;margin-top:10px;border-bottom:1px dotted  #ddd;'><font color='red'>此订单可能缺少物流公司或运单号</font><li/>";
            }else{
                $html .= "<li style='line-height:15px;margin-top:10px;border-bottom:1px dotted  #ddd;'><font color='red'>".$rpc_result['err_msg']."</font><li/>";
            }
        }
        $html .='</ul>';
        $provider = $this->getTrackProvider();
        $providerName = ($provider == 'sf') ? '顺丰' : '快递鸟';
        $html .= "<div  style='font-weight:700;font-color:#000000;margin-bottom:10px;'>".$providerName."提供数据支持(<font>以上信息由物流公司提供，如无跟踪信息或有疑问，请咨询对应物流公司</font>)<div>";
        
        return array('html'=>$html,'rsp'=>$rpc_result['rsp']);
    }
    function getDeliveryInfo($logi_no = false){
        $deliveryMdl = app::get('ome')->model('delivery');
        $delivery = $deliveryMdl->db_dump(array ('logi_no' => $logi_no),'logi_no,logi_id,logi_name,ship_mobile');
        if (!$delivery) {
            $billMdl = app::get('ome')->model('delivery_bill');
            $bill = $billMdl->db_dump(array ('logi_no'=>$logi_no),'delivery_id');
        
            if ($bill['delivery_id']) {
                $delivery = $deliveryMdl->db_dump($bill['delivery_id'],'logi_no,logi_id,logi_name,ship_mobile');
            }
        }
    
        if ($delivery['logi_id']) {
            $corp = app::get('ome')->model('dly_corp')->db_dump($delivery['logi_id'], 'type');
        
            $delivery['logi_type'] = $corp['type'];
        }
    
        return $delivery;
    }

    #ERP与华强宝快递对接，查看物流状态
    function get_dly_info($rpc_data = false){
        $return = array();
        $provider = $this->getTrackProvider();

        if ($provider == 'sf') {
            $rs = kernel::single('erpapi_router_request')->set('hqepay', 'sf')->sf_query($rpc_data);
        } else {
            // 默认仍走快递鸟
            $rs = kernel::single('erpapi_router_request')->set('hqepay','1227722633')->hqepay_query($rpc_data);
        }

        if ($rs['rsp'] == 'succ') {
            $data = is_array($rs['data']) ? $rs['data'] : @json_decode($rs['data'],true);

            $traces = isset($data['Traces']) ? $data['Traces'] : (is_array($data) ? $data : array());
            if ($traces) {
                krsort($traces);
            }

            $return['data'] = $traces;
        }

        $return['rsp']     = $rs['rsp'];
        $return['err_msg'] = $rs['msg'];

        return $return;
    }

    /**
     * 物流跟踪来源：kdn（默认）| sf
     */
    public function getTrackProvider()
    {
        $provider = app::get('ome')->getConf('ome.logistics.track.provider');
        return $provider ?: 'kdn';
    }
    
    public function rpc_logistics_hqepay($rpc_data){
        #检测是否已经绑定华强宝物流
        base_kvstore::instance('ome/bind/hqepay')->fetch('ome_bind_hqepay', $is_ome_bind_hqepay);
        if(!$is_ome_bind_hqepay){
            $rs = $this->bind();
            if(!$rs){
                $return_data['rsp'] = 'fail';
                $return_data['err_msg'] = '没有绑定!';
                return  $return_data;
            }
        }
        $Ofunc = kernel::single('ome_rpc_func');
        $app_xml = $Ofunc->app_xml();
        $params['to_node_id'] = '1227722633';#写死node_id
        
       
        $params['tid'] = $rpc_data['order_bn']; #订单号
        $params['company_code'] = $rpc_data['logi_code'];
        $params['company_name'] = $rpc_data['company_name'];
        $params['logistic_code'] = $rpc_data['logi_no'];
        
        
        $time_out = 5;
        $res = &app::get('ome')->matrix()->set_realtime(true)->set_timeout($time_out)->call('logistics.trace.detail.get', $params);
        $return_data = null;
        if($res->rsp == 'fail'){
            $return_data['rsp'] = 'fail';
            $return_data['err_msg'] = $res->err_msg;
        }else{
            $return_data['rsp'] = 'succ';
            $_data = json_decode($res->data,true);
            $return_data['data'] =  $_data['Traces'];           
        }
        return $return_data;
    }
    #绑定华强宝物流
    public function bind() {
        $token = base_certificate::token();
        $params = array(
                'app' => 'app.applyNodeBind',
                'node_id' => base_shopnode::node_id('ome'),
                'from_certi_id' => base_certificate::certi_id(),
                'callback' => '',
                'sess_callback' => '',
                'api_url' => kernel::base_url(1).kernel::url_prefix().'/api',
                'node_type' => 'hqepay',
                'to_node' => '1227722633',#写死的
                'shop_name' => '物流跟踪',
                "api_key"=> "1236217",#写死的
                "api_secret"=> "cf98e49d-9ebe-43cb-a690-ad96295b3457",#写死的
               // "api_url"=>"http://port.hqepay.com/Ebusiness/EbusinessOrderHandle.aspx", #写死的
        );
        
        $params['certi_ac']=$this->genSign($params,$token);
        //$api_url = 'http://sws.ex-sandbox.com/api.php';
        $api_url = MATRIX_RELATION_URL . 'api.php';
        $headers = array('Connection' => 5);
        $core_http = kernel::single('base_httpclient');
        $response = $core_http->set_timeout(10)->post($api_url, $params, $headers);
        $response = json_decode($response,true);
        if($response['res'] == 'succ' || $response['msg']['errorDescription'] == '绑定关系已存在,不需要重复绑定') {
            base_kvstore::instance('ome/bind/hqepay')->store('ome_bind_hqepay', true);
            return true;
        }
        return false;
    }   
    public function genSign($params, $token) {
        ksort($params);
        $str = '';
        foreach ($params as $key =>$value) {
            if ($key != 'certi_ac') {
                $str .= $value;
            }
        }
        $signString = md5($str.$token);
        return $signString;
    }

    function getReturnInfo($logi_no = false){
        $reship = app::get('ome')->model('reship')->db_dump(array ('return_logi_no' => $logi_no), 'return_logi_no,return_logi_name,ship_mobile');
    
        $corp = array ();
        if ($reship['return_logi_name']) {
            $corpMdl = app::get('ome')->model('dly_corp');
        
            $corp = $corpMdl->db_dump(['name' => $reship['return_logi_name']], 'type,corp_id,name');
        
            if (!$corp) {
                $corp = $corpMdl->db_dump(['type' => $reship['return_logi_name']], 'type,corp_id,name');
            }
        }
    
        $data = array (
            'logi_no'     => $logi_no,
            'logi_id'     => $corp['corp_id'],
            'logi_name'   => $corp['name'],
            'logi_type'   => $corp['type'],
            'ship_mobile' => $reship['ship_mobile'],
        );
    
        if (!$data['logi_type'] && preg_match('/^[A-Z]+/', $logi_no, $match)){
            $data['logi_type'] = $match[0];
        }
    
        return $data;
    }

    public function getShipping($order_bn) {
        $order = app::get('ome')->model('orders')->db_dump(['order_bn'=>$order_bn], 'shipping,shop_type');
        return kernel::single('ome_hqepay_shipping')->getLogiNameType($order['shipping'], $order['shop_type']);
    }
}
