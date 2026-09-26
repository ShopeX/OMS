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

class erpapi_hqepay_response_process_logistics {
    static public $sms_method = array(
        '2'=>'express',#已揽收
        '1'=>'received',#已签收
    );

    static public $state_value = array(
        '1'=>'已揽收',#已揽收
        '2'=>'在途中',#在途中
        '3'=>'已签收',#已签收
        '4'=>'退件',#退件/问题件
        '5'=>'待取件',#待取件
        '6'=>'待派件',#待派件
    );
    /**
     * push
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function push($params) {
        $filter = array(
            'delivery_id' => $params['delivery_id'],
            'logi_status|noequal' => $params['logi_status']
        );

        $strReceived = self::$state_value[$params['logi_status']]; 
        $data = array('logi_status'=>$params['logi_status']);
        if ($params['sign_time']){
            $data['sign_time'] = $params['sign_time'];
        }

        if ($params['embrace_time']){
            $data['embrace_time'] = $params['embrace_time'];
        }

        // 同步物流状态到 delivery_bill（按 logi_no；已签收写 sign_time）
        $this->syncDeliveryBillByLogiNo($params);
        
        $ret = app::get('ome')->model('delivery')->update($data, $filter);
        if(is_bool($ret)) {
            return array('rsp'=>'succ', 'msg'=>'该发货单已' . $strReceived);
        }

        $memo = '更新物流状态为：' . $strReceived . '（物流单号：'.$params['logi_no'].'）';
        app::get('ome')->model('operation_log')->write_log('delivery_process@ome',$params['delivery_id'],$memo);
        
        #已揽收、已签收的，需要发送短信
        $method = self::$sms_method[$params['logi_status']];
        if($method){
            #如果taoexlib存在，发货短信开启的 发送短信
            if(kernel::service('message_setting') && defined('APP_TOKEN') && defined('APP_SOURCE')){
                kernel::single('taoexlib_sms')->sendSms(array('event_type'=>$method,'delivery_id'=>$params['delivery_id']), $error_msg);
            }
        }

        #只有已签收的货到付款单才自动支付
        if($params['logi_status'] == '3' && $params['is_cod'] == 'true' && 'false' != app::get('ome')->getConf('ome.codorder.autopay')){
            kernel::single('ome_order')->codAutoPay($params['delivery_id']);
        }

        //已签收的获取order_id 调用自动电子发票业务
        if($params['logi_status'] == '3'){
            $funcObj = kernel::single('invoice_func');
            $orderDelivery = app::get('ome')->model('delivery_order')->getList('*',array('delivery_id'=>$params['delivery_id']));
            foreach($orderDelivery as $od) {
                $funcObj->do_einvoice_bill($od['order_id'],"2");
            }
        }

        return array('rsp'=>'succ', 'msg' => '更新物流状态成功!');
    }

    /**
     * 按运单号同步物流跟踪状态到 delivery_bill
     * 关联：logi_no（可选附带 delivery_id）
     * 已签收(logi_status=3)时同步 sign_time
     *
     * @param array $params logi_no / logi_status / sign_time / delivery_id(可选)
     * @return mixed update 结果；无运单号返回 false
     */
    public function syncDeliveryBillByLogiNo($params)
    {
        $logi_no = trim((string) ($params['logi_no'] ?? ''));
        if ($logi_no === '' || !isset($params['logi_status']) || $params['logi_status'] === '') {
            return false;
        }

        $data = array(
            'logi_status' => $params['logi_status'],
        );
        if ((string) $params['logi_status'] === '3') {
            $data['sign_time'] = !empty($params['sign_time']) ? $params['sign_time'] : time();
        }

        $filter = array(
            'logi_no' => $logi_no,
        );
        if (!empty($params['delivery_id'])) {
            $filter['delivery_id'] = $params['delivery_id'];
        }

        return app::get('ome')->model('delivery_bill')->update($data, $filter);
    }
}
