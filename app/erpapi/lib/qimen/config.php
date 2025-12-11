<?php
/**
 * Copyright © ShopeX （http://www.shopex.cn）. All rights reserved.
 * See LICENSE file for license details.
 */

class erpapi_qimen_config extends erpapi_config
{
    protected $_publicParams = [];
    
    public function init(erpapi_channel_abstract $channel)
    {
        // 共用erpapi_shop路由中的白名单
        //$this->__whitelist = kernel::single('erpapi_shop_whitelist')->getWhiteList($channel->channel['node_type']);
        
        // 单拉订单接口
        $this->__whitelist = ['qimen.taobao.erp.order.sync'];
        
        return parent::init($channel);
    }
    
    /**
     * 奇门接口地址
     *
     * @param $method
     * @param $params
     * @param $realtime
     * @return string|void
     */
    public function get_url($method, $params, $realtime)
    {
        $url = defined('QIMEN_URL') ? QIMEN_URL : '';
        
        // 拼接URL参数
        $urlParams = [];
        if($this->_publicParams){
            // sign
            $this->_publicParams['sign'] = '';
            
            // 重组URL参数
            foreach ($this->_publicParams as $key => $val)
            {
                $urlParams[$key] = $params[$key];
            }
        }
        
        if($urlParams){
            $url .= '?' . http_build_query($urlParams);
        }
        
        return $url;
    }
    
    /**
     * 奇门接口公共请求参数
     *
     * @param $method 请求方法
     * @param $params 业务级请求参数
     * @return array
     */
    public function get_query_params($method, $params)
    {
        // 实现方appKey(矩阵appKey)
        $target_app_key = defined('TOP_APP_KEY') ? TOP_APP_KEY : '';
        
        $systemParam = array (
            'method'            => $method,
            'app_key'           => $this->__channelObj->channel['app_key'], // 调用方appKey(OMS系统)
            'target_app_key'    => $target_app_key, // 实现方appKey(矩阵系统)
            'timestamp'         => date('Y-m-d H:i:s', time()),
            'v'                 => '2.0',
            'sign_method'       => 'md5',
            'format'            => 'json',
        );
        
        // public params
        $this->_publicParams = $systemParam;
        
        // merge
        $params = array_merge($systemParam, $params);
        
        // headers
        $headers = array(
            'Content-Type' => 'application/json',
        );
        
        $params['headers'] = $headers;
        
        return $params;
    }
    
    public function gen_sign($params)
    {
        // app_secret
        $secretKey = $this->__channelObj->channel['secret_key'];
        
        // get params
        if(isset($params['method']) && in_array($params['method'], ['qimen.taobao.erp.order.sync'])){
            // unset headers
            unset($params['headers']);
            
            $bodyParams = $params;
            $params = [];
            
            // 手工单拉订单
            if($this->_publicParams){
                foreach ($this->_publicParams as $key => $val)
                {
                    // 重组公共参数
                    $params[$key] = $bodyParams[$key];
                    
                    // 删除公共参数
                    unset($bodyParams[$key]);
                }
            }
            
            // json
            $body_json = json_encode($bodyParams, JSON_UNESCAPED_UNICODE);
        }else{
            // get
            $params = $_GET;
            
            // body
            $body_json = '';
            if (stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
                // 需要判断php://input是否为空
                if ($input = file_get_contents('php://input')) {
                    //$bodyParams = json_decode($input, true);
                    $body_json = $input;
                }
            }
        }
        
        // unset sign
        unset($params['sign']);
        
        // 排序
        ksort($params);
        
        // 拼接字符串
        $stringToBeSigned = $secretKey;
        foreach ($params as $k => $v)
        {
            if(is_string($v) && "@" != substr($v, 0, 1))
            {
                $stringToBeSigned .= "$k$v";
            }
        }
        
        // add body string
        $stringToBeSigned .= $body_json;
        
        // add secretKey
        $stringToBeSigned .= $secretKey;
        
        // sign
        $sign = strtoupper(md5($stringToBeSigned));
        
        return $sign;
    }
    
    /**
     * 格式化请求参数
     *
     * @param $query_params
     * @return false|string|void
     */
    public function format($query_params)
    {
        if($this->_publicParams){
            // sign
            $this->_publicParams['sign'] = '';
            
            // 删除公共参数
            foreach ($this->_publicParams as $key => $val)
            {
                unset($query_params[$key]);
            }
        }
        
        // json
        return json_encode($query_params, JSON_UNESCAPED_UNICODE);
    }
}