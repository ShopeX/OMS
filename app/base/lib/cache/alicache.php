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
 * 阿里云-Memcached服务
 *
 * @package base
 * @copyright Copyright (c) 2010, shopex. inc
 * @author chenping@shopex.cn
 * @license 
 */
class base_cache_alicache extends base_cache_abstract implements base_interface_cache
{
    static private $_cacheObj = null;

    function __construct() 
    {
        $this->connect();
        $this->check_vary_list();
    }//End Function

    public function connect() 
    {
        if(!isset(self::$_cacheObj)){
            if(defined('CACHE_MEMCACHE_CONFIG') && constant('CACHE_MEMCACHE_CONFIG')){
                self::$_cacheObj = new Memcached;
                self::$_cacheObj->setOption(Memcached::OPT_COMPRESSION, false); //关闭压缩功能
                self::$_cacheObj->setOption(Memcached::OPT_BINARY_PROTOCOL, true); //使用binary二进制协议
                self::$_cacheObj->setOption(Memcached::OPT_TCP_NODELAY, true); //重要，php memcached有个bug，当get的值不存在，有固定40ms延迟，开启这个参数，可以避免这个bug

                foreach(explode(',', CACHE_MEMCACHE_CONFIG) AS $row){
                    list ($ip, $port) = explode(':', trim($row));

                    self::$_cacheObj->addServer($ip, $port);
                }

                // 是否有账号密码
                if (defined('CACHE_MEMCACHE_AUTH') && constant('CACHE_MEMCACHE_AUTH')) {
                    list($username, $password) = explode(':', CACHE_MEMCACHE_AUTH);

                    self::$_cacheObj->setSaslAuthData($username, $password);
                }
            }else{
                trigger_error('can\'t load CACHE_MEMCACHE_CONFIG, please check it', E_USER_ERROR);
            }
        }
    }//End Function

    public function fetch($key, &$result) 
    {
        $result = self::$_cacheObj->get($key);
        if($result === false){
            return false;
        }else{
            return true;
        }
    }//End Function

    public function store($key, $value, $ttl=0) 
    {
        return self::$_cacheObj->set($key, $value, $ttl);
    }//End Function

    public function status() 
    {
        $status = self::$_cacheObj->getStats();
        $return['缓存获取'] = $status['cmd_get'];
        $return['缓存存储'] = $status['cmd_set'];
        $return['可使用缓存'] = $status['limit_maxbytes'];
        return $return;
    }//End Function

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function supportUUID()
    {
        return true;
    }
    
    /**
     * 累加
     */
    public function increment($key, $offset=1)
    {
        $ret = self::$_cacheObj->increment($key, $offset);
        if ($ret === false) {
            //返回 false 说明 key 在memcache 中不存在，需创建
            if (self::$_cacheObj->set($key, $offset, 86401)) {

                $ret = $offset;
            } else {

                $ret = false;
            }
        }

        return $ret;
    }//End Function

    /**
     * 递减
     */
    public function decrement($key, $offset=1)
    {
        $ret = self::$_cacheObj->decrement($key, $offset);
        if ($ret === false) {
            //返回 false 说明 key 在memcache 中不存在，需创建
            $value = 0 - $offset;
            if (self::$_cacheObj->set($key, $value, 86401)) {

                $ret = $value;
            } else {

                $ret = false;
            }
        }

        return $ret;
    }//End Function

    /**
     * 初始化自增ID值
     *
     * @return void
     * @author 
     */
    public function setcr($key, $value, $ttl=0)
    {
        return self::$_cacheObj->set($key, $value, $ttl);
    }
    
}//End Class
