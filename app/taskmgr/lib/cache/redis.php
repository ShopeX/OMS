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

class taskmgr_cache_redis extends taskmgr_cache_abstract implements taskmgr_cache_interface
{
    private static $_cacheObj = null;

    /**
     * __construct
     * @return mixed 返回值
     */
    public function __construct()
    {
        $this->connect();
    } //End Function

    /**
     * connect
     * @return mixed 返回值
     */
    public function connect()
    {
        if (!isset(self::$_cacheObj)) {
            if (defined('__REDIS_CONFIG') && constant('__REDIS_CONFIG')) {
                self::$_cacheObj = new Redis;
                $config          = explode(':', __REDIS_CONFIG);
                $host = $config[0];
                $port = isset($config[1]) ? (int) $config[1] : 6379;

                $useTls = defined('__REDIS_TLS') && self::_taskmgrRedisTlsEnabled();
                if ($useTls) {
                    $connectTimeout = defined('__REDIS_CONNECT_TIMEOUT')
                        ? (float) constant('__REDIS_CONNECT_TIMEOUT') : 2.0;
                    $readTimeout = defined('__REDIS_READ_TIMEOUT')
                        ? (float) constant('__REDIS_READ_TIMEOUT') : 2.0;
                    $context = self::_taskmgrRedisTlsStreamContext();
                    self::$_cacheObj->connect($host, $port, $connectTimeout, null, 0, $readTimeout, $context);
                } else {
                    self::$_cacheObj->connect($host, $port);
                }

                // 密码
                if (defined('__REDIS_AUTH') && constant('__REDIS_AUTH')) {
                    self::$_cacheObj->auth(__REDIS_AUTH);
                }

                //Specify a database
                if (isset($config[2]) && $config[2] >= 0) {
                    self::$_cacheObj->select($config[2]);
                }

            } else {
                trigger_error('can\'t load __REDIS_CONFIG, please check it', E_USER_ERROR);
            }
        }
    } //End Function

    /**
     * fetch
     * @param mixed $key key
     * @param mixed $result result
     * @return mixed 返回值
     */
    public function fetch($key, &$result)
    {
        $key = $this->create_key($key);

        $result = self::$_cacheObj->get($key);

        if ($result === false) {

            return false;
        } else {

            $result = is_numeric($result) ? $result : base64_decode($result);

            return true;
        }
    } //End Function

    /**
     * store
     * @param mixed $key key
     * @param mixed $value value
     * @param mixed $ttl ttl
     * @return mixed 返回值
     */
    public function store($key, $value, $ttl = 0)
    {
        $key = $this->create_key($key);


        $value = is_numeric($value) ? $value : base64_encode($value);

        return self::$_cacheObj->setex($key, $ttl, $value);
    } //End Function

    /**
     * 删除
     * @param mixed $key key
     * @return mixed 返回值
     */
    public function delete($key)
    {
        $key = $this->create_key($key);

        return self::$_cacheObj->delete($key);
    }

    /**
     * 累加
     */
    public function increment($key, $offset = 1)
    {
        $key = $this->create_key($key);

        return self::$_cacheObj->incr($key, $offset);
    } //End Function

    /**
     * @return bool
     */
    private static function _taskmgrRedisTlsEnabled()
    {
        $v = constant('__REDIS_TLS');
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
     * @return array
     */
    private static function _taskmgrRedisTlsStreamContext()
    {
        if (defined('__REDIS_STREAM_CONTEXT')) {
            $ctx = constant('__REDIS_STREAM_CONTEXT');
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

} //End Class
