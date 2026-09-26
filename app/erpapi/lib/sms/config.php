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
 * @author ykm 2016-01-19
 * @describe 短信接口公用设置
 */
class erpapi_sms_config extends erpapi_config
{
    #使用其他方法生成签名
    public $otherSign = array(
        SMS_USER_INFO,
        SMS_SERVER_TIME
    );
    /**
     * 应用级参数
     *
     * @param String $method 请求方法
     * @param Array $params 业务级请求参数
     * @return Array
     * @author 
     **/
    public function get_query_params($method, $params){
        if(in_array($method, $this->otherSign)) {
            return array();
        }
        $query_params = array(
            'sign_time' => time(),
            'sign_method' => 'md5',
        );
        return $query_params;
    }

    /**
     * 获取请求地址
     *
     * @param String $method 请求方法
     * @param Array $params 业务级请求参数
     * @param Boolean $realtime 同步|异步
     * @return String
     * @author 
     **/
    public function get_url($method, $params, $realtime){
        switch($method) {
            case SMS_SERVER_TIME :
                $url = 'http://webapi.sms.shopex.cn/';
                break;
            case SMS_USER_INFO :
                $url = 'http://api.sms.shopex.cn/';
                break;
            case SMS_NEW_OAUTH:
            case SMS_UPDATE_OAUTH:
                $url = 'http://openapi.shopex.cn' . $this->get_path($method);
                break;
            default :
                $url = 'http://openapi.ishopex.cn' . $this->get_path($method);
                if($params['sign']) {
                    $url .= '?client_id=' . $params['client_id'] . '&sign_time=' . $params['sign_time'] . '&sign_method=' . $params['sign_method'] . '&sign=' . $params['sign'];
                    unset($params['client_id'], $params['sign_time'], $params['sign_method'], $params['sign']);
                }
                break;
        }
        return $url;
    }

    /**
     * 签名
     *
     * @param Array $params 参数
     * @return String
     * @author 
     **/
    public function gen_sign($params,$method=''){
        if(in_array($method, $this->otherSign)) {
            return null;
        }
        $secret = $params['secret'];
        if(isset($params['secret'])) {
            unset($params['secret']);
        }
        $path = $this->get_path($method);
        $signUrl = $params['signUrl'];
        if(isset($params['signUrl'])) {
            unset($params['signUrl']);
        }
        $tmpParam = $params;
        if($signUrl) {
            $query[] = 'client_id=' . $tmpParam['client_id'];
            $query[] = 'sign_method=' . $tmpParam['sign_method'];
            $query[] = 'sign_time=' . $tmpParam['sign_time'];
            unset($tmpParam['client_id'], $tmpParam['sign_time'], $tmpParam['sign_method']);
            $strQuery = implode('&', $query);
        } else {
            $strQuery = '';
        }
        foreach($tmpParam as $k=>$v){
            $tmp[] = $k.'='.$v;
        }
        ksort($tmpParam);
        $tmp = array();
        foreach($tmpParam as $k=>$v){
            $tmp[] = $k.'='.$v;
        }
        $strParam = implode('&', $tmp);
        $sign = array(
            $secret,
            'POST',
            rawurlencode($path),
            null,
            rawurlencode($strQuery),
            rawurlencode($strParam),
            $secret
        );
        $sign = implode('&', $sign);
        return strtoupper(md5($sign));
    }

    protected function get_path($method) {
        $arr = explode('.', $method);
        if($arr[0] == 'erp' && $arr[1] == 'sms') {
            unset($arr[0], $arr[1]);
            return '/' . implode('/', $arr);
        }
        return '';
    }
}
