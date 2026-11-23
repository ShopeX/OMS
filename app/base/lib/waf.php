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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2022/1/18 10:25:51
 * @describe: waf
 * ============================
 */
class base_waf {

    public function __construct()
    {
        $this->__mq_config = $GLOBALS['_MQ_HCHSAFE_CONFIG'];
    }

    /**
     * 是否已配置MQ
     *
     * @return void
     * @author 
     **/
    protected function __is_config_mq()
    {
        if ($this->__mq_config && defined("MQ_HCHSAFE") && true == constant("MQ_HCHSAFE")) {
            return true;
        }

        return false;
    }

    public function processLog() {
        if (!$this->__is_config_mq()) return ;
        $bqq = kernel::single('base_queue_mq');
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
            $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $realip = $_SERVER["HTTP_CLIENT_IP"];
        } else {
            $realip = $_SERVER["REMOTE_ADDR"];
        }
        if(strpos($realip, ',')) {
            list($realip,) = explode(',', $realip);
        }
        $countryCity = $this->getCity($realip);
        //idaas登录日志
        $pushData = array(
            'topAppKey'    => TOP_APP_KEY,
            'remoteAddr'   => $_SERVER['REMOTE_ADDR'],
            'remotePort'   => $_SERVER['REMOTE_PORT'],
            'upstreamAddr'   => $_SERVER['SERVER_ADDR'].':'.$_SERVER['SERVER_PORT'],
            'reqTime' => time(),
            'wafRuleIndex' => 'wafRuleIndex',
            'wafRuleType' => 'wafRuleType',
            'wafAction' => 'wafAction',
            'host' => $_SERVER['HTTP_HOST'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'url' => kernel::this_url(1).'?'.$_SERVER['QUERY_STRING'],
            'status' => $_SERVER['REDIRECT_STATUS'] ? : '200',
            'upstreamStatus' => $_SERVER['REDIRECT_STATUS'] ? : '200',
            'bodyBytesSent' => $_GET['bodyBytesSent'] ? : rand(10,4000),
            'referer' => $_SERVER['HTTP_REFERER'],
            'xForwardedFor' => $_SERVER['HTTP_X_FORWARDED_FOR'] ? : $_SERVER["REMOTE_ADDR"],
            'requestBody' => file_get_contents('php://input', 'r') ? : time(),
            'cookie' => $_SERVER['HTTP_COOKIE'],
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'https' => $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ? 'true' : 'false',
            'requestContentType' => $_SERVER['HTTP_ACCEPT'],
            'requestMethod' => $_SERVER['REQUEST_METHOD'],
            'requestUri' => $_SERVER['REQUEST_URI'],
            'serverName' => $_SERVER['SERVER_NAME'] ? : $_SERVER['HTTP_HOST'],
            'time' => time(),
            "requestTimeMsec" => $_SERVER['REQUEST_TIME'],
            "upstreamResponseTime" => time(),
            "remoteCity" => $countryCity['city'],
            "remoteCountry" => $countryCity['country'],
            "remoteIsp" => $countryCity['isp'],
            "remoteRegion" => $countryCity['region'],
        );
        $pushData['requestDelayTime'] = $pushData['upstreamResponseTime'] - $pushData['requestTimeMsec'];
        $pushData['requestLength'] = strlen($pushData['requestBody']);
        foreach ($pushData as $key => $value) {
            if(empty($value) && !in_array($key, ['remoteCity','remoteCountry','remoteIsp','remoteRegion'])) {
                $pushData[$key] = 'NULL';
                continue;
            }
            $pushData[$key] = (string) $value;
        }
        $this->__mq_config['routerkey'] = 'tb.waf.log';
        $bqq->connect($this->__mq_config, $this->__mq_config['exchange'], 'tb.waf.log');
        $flag = $bqq->publish(json_encode($pushData),$this->__mq_config['routerkey']);
        $bqq->disconnect();
        return ;
    }

    /**
     * 获取 IP  地理位置
     * 淘宝IP接口
     * @Return: array
     */
    function getCity($ip = '')
    {
        return [];

        if($ip == ''){
            $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json";
            $ip=json_decode(file_get_contents($url),true);
            $data = $ip;
        }else{
            $url="http://ip.taobao.com/service/getIpInfo.php?ip=".$ip;
            $ip=json_decode(file_get_contents($url));   
            if((string)$ip->code=='1'){
               return false;
            }
            $data = (array)$ip->data;
        }
        
        return $data;   
    }
}