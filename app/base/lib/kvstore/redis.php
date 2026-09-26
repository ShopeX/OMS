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


/*
 * @package base
 * @copyright Copyright (c) 2010, shopex. inc
 * @author edwin.lzh@gmail.com
 * @license
 */
class base_kvstore_redis extends base_kvstore_abstract implements base_interface_kvstore_base, base_interface_kvstore_extension
{
    static private $_cacheObj;

    function __construct($prefix)
    {
        $this->connect();
        $this->prefix = $prefix;
    }//End Function

    public function connect()
    {
        if(!isset(self::$_cacheObj))
        {
            if(defined('KVSTORE_REDIS_CONFIG') && constant('KVSTORE_REDIS_CONFIG')){
                self::$_cacheObj = new Redis();
                $config = explode(':', KVSTORE_REDIS_CONFIG);
                $host = $config[0];
                $port = isset($config[1]) ? (int) $config[1] : 6379;

                $useTls = defined('KVSTORE_REDIS_TLS') && self::_redisTlsEnabled();

                if ($useTls) {
                    $connectTimeout = defined('KVSTORE_REDIS_CONNECT_TIMEOUT')
                        ? (float) constant('KVSTORE_REDIS_CONNECT_TIMEOUT') : 2.0;
                    $readTimeout = defined('KVSTORE_REDIS_READ_TIMEOUT')
                        ? (float) constant('KVSTORE_REDIS_READ_TIMEOUT') : 2.0;
                    $context = self::_redisTlsStreamContext();
                    // $timeout, $persistent_id, $retry_interval, $read_timeout, $context (phpredis 5.3+)
                    self::$_cacheObj->connect($host, $port, $connectTimeout, null, 0, $readTimeout, $context);
                } else {
                    self::$_cacheObj->connect($host, $port);
                }

                // 密码
                if(defined('KVSTORE_REDIS_AUTH') && constant('KVSTORE_REDIS_AUTH')){
                    self::$_cacheObj->auth(KVSTORE_REDIS_AUTH);
                }

                //Specify a database
                if(isset($config[2]) && $config[2] >= 0){
                    self::$_cacheObj->select($config[2]);
                }


            } else {
                trigger_error('Can\'t load KVSTORE_REDIS_CONFIG, please check it', E_USER_ERROR);
            }
        }
    }//End Function

    /**
     * 是否启用 Redis TLS（需 phpredis 支持 connect 第 7 参数 stream context）
     *
     * @return bool
     */
    private static function _redisTlsEnabled()
    {
        $v = constant('KVSTORE_REDIS_TLS');
        if ($v === true || $v === 1) {
            return true;
        }
        if (is_string($v)) {
            $v = strtolower(trim($v));
            return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
        }
        return false;
    }

    /**
     * TLS 连接的 stream context；可通过 KVSTORE_REDIS_STREAM_CONTEXT 完全自定义
     *
     * @return array|null
     */
    private static function _redisTlsStreamContext()
    {
        if (defined('KVSTORE_REDIS_STREAM_CONTEXT')) {
            $ctx = constant('KVSTORE_REDIS_STREAM_CONTEXT');
            if (is_array($ctx) && $ctx !== array()) {
                return $ctx;
            }
        }
        return array(
            'stream' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        );
    }

    public function fetch($key, &$value, $timeout_version=null)
    {
        $store = self::$_cacheObj->get($this->create_key($key));
        $store = json_decode($store,true);
        if($store !== false)
        {
            if($timeout_version < $store['dateline'])
            {
                if($store['ttl'] > 0 && ($store['dateline']+$store['ttl']) < time()){
                    return false;
                }
                $value = $store['value'];
                return true;
            }
        }
        return false;
    }//End Function

    public function store($key, $value, $ttl=0)
    {
        $store['value'] = $value;
        $store['dateline'] = time();
        $store['ttl'] = $ttl;
        return self::$_cacheObj->set($this->create_key($key), json_encode($store));
    }//End Function

    public function delete($key)
    {
        return self::$_cacheObj->delete($this->create_key($key));
    }//End Function

    public function recovery($record)
    {
        $key = $record['key'];
        $store['value'] = $record['value'];
        $store['dateline'] = $record['dateline'];
        $store['ttl'] = $record['ttl'];
        return self::$_cacheObj->set($this->create_key($key), json_encode($store));
    }//End Function

    /**
     * 是否支持同步的自增单号处理
     */
    public function supportUUID() {

        return true;
    }

    /**
     * 累加
     */
    public function increment($key, $offset=1)
    {
        return self::$_cacheObj->incr($this->create_key($key), $offset);
    }//End Function

    /**
     * 递减
     */
    public function decrement($key, $offset=1)
    {
        return self::$_cacheObj->decr($this->create_key($key), $offset);
    }//End Function

    /**
     * 初始化自增ID值
     *
     * @return void
     * @author 
     */
    public function setcr($key, $value, $ttl=1)
    {
        return self::$_cacheObj->setex($this->create_key($key), $ttl, $value);
    }

}//End Class