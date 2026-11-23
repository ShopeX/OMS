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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 

/*
 * @package base
 * @copyright Copyright (c) 2010, shopex. inc
 * @author edwin.lzh@gmail.com
 * @license 
 */
class base_cache_redis extends base_cache_abstract implements base_interface_cache
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
            if(defined('CACHE_REDIS_CONFIG') && constant('CACHE_REDIS_CONFIG')){
                self::$_cacheObj = new Redis;
                $config = explode(':', CACHE_REDIS_CONFIG);
                self::$_cacheObj->connect($config[0], $config[1]);

                // 密码
                if(defined('CACHE_REDIS_AUTH') && constant('CACHE_REDIS_AUTH')){
                    self::$_cacheObj->auth(CACHE_REDIS_AUTH);
                }

                //Specify a database
                if(isset($config[2]) && $config[2] >= 0){
                    self::$_cacheObj->select($config[2]);
                }


            }else{
                trigger_error('can\'t load CACHE_REDIS_CONFIG, please check it', E_USER_ERROR);
            }
        }
    }//End Function

    public function fetch($key, &$result) 
    {
        $result = self::$_cacheObj->get($key);
        if($result === false){
            return false;
        }else{
	    $result = json_decode($result, true);
            return true;
        }
    }//End Function

    public function store($key, $value, $ttl=0) 
    {
	$value = json_encode($value);
        return self::$_cacheObj->setex($key, $ttl, $value);
    }//End Function

    public function status() 
    {
        //$status = self::$_cacheObj->info();
        //$return['缓存获取'] = $status['cmd_get'];
        //$return['缓存存储'] = $status['cmd_set'];
        //$return['可使用缓存'] = $status['limit_maxbytes'];
        $return = array();
        return $return;
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
        return self::$_cacheObj->incr($key, $offset);
    }//End Function

    /**
     * 递减
     */
    public function decrement($key, $offset=1)
    {
        return self::$_cacheObj->decr($key, $offset);
    }//End Function


    /**
     * 初始化自增ID值
     *
     * @return void
     * @author 
     */
    public function setcr($key, $value, $ttl=1)
    {
        return self::$_cacheObj->setex($key, $ttl, $value);
    }
}//End Class
