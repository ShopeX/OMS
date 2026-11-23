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

class cachecore {

	/*
     * @var boolean $_enable
     * @access static private
     */
    static private $_enable = false;

    /*
     * @var string $_instance
     * @access static private
     */
    static private $_instance = null;

    /*
     * @var string $_instance_name
     * @access static private
     */
    static private $_instance_name = null;

	/*
     * 初始化
     * @var boolean $with_cache
     * @access static public
     * @return void
     */
    static public function init() 
    {
        if(defined('CACHE_STORAGE') && constant('CACHE_STORAGE')){
            self::$_instance_name = CACHE_STORAGE;
            self::$_enable = true;
        }else{
            self::$_instance_name = 'base_cache_nocache';    //todo：增加无cache类，提高无cache情况下程序的整体性能
            self::$_enable = false;
        }
        self::$_instance = null;
    }//End Function

    /*
     * 是否启用
     * @access static public
     * @return boolean
     */
    static public function enable() 
    {
        return self::$_enable;
    }//End Function

    /*
     * 获取cache_storage实例
     * @access static public
     * @return object
     */
    static public function instance() 
    {
    	if(is_null(self::$_instance_name)) {
    		self::init();
    	}

        if(is_null(self::$_instance)){
            //self::$_instance = kernel::single(self::$_instance_name);
	    self::$_instance = new self::$_instance_name;
        }//使用实例时再构造实例
        return self::$_instance;
    }//End Function

    /*
     * 获取缓存key
     * @var string $key
     * @access static public
     * @return string
     */
    static public function get_key($key) 
    {
        return md5(sprintf('%s_%s', KV_PREFIX, $key));
    }//End Function

    /**
     * 获取缓存
     */
    static public function fetch($key) {

        if(self::instance()->fetch(self::get_key($key), $data)){
            if($data['expires'] > 0 && time() > $data['expires']){
                return false;   
            }//todo:人工设置过期功能判断
            
            return $data['content'];
        }else{
            return false;
        }
    }
    
    /**
     * 获取缓存
     */
    static public function store($key, $value, $ttl=0) {

        $data = array('content' => $value);
        $data['expires'] = ($ttl > 0) ? time() + $ttl : 0;       //todo: 设置过期时间
        return self::instance()->store(self::get_key($key), $data, $ttl);
    } 

    /**
    * 删除方法
    */
    static public function delete($key) {
        $data = array('content' => '');
        $data['expires'] = 1;       //todo: 设置过期时间
        return self::instance()->store(self::get_key($key), $data, 1);
    }
    
    /**
     * 删除缓存方法
     */
    public function real_delete($key) {
        self::delete($key);
    }

    /**
     * 初始化自增ID值
     *
     * @return boolean
     * @author 
     */
    static public function setcr($key, $value, $ttl=0)
    {
        if (method_exists(self::instance(),'setcr')) {
            return self::instance()->setcr(self::get_key($key), $value, $ttl);
        }

        return false;
    }

    static public function supportUUID(){
        
        return self::instance()->supportUUID();
    }//End Function

    /*
     * 自增
     * @var string $key
     * @var int $offset
     * @access public
     * @return int
     */
    static public function increment($key, $offset=1) 
    {
        if (self::supportUUID() && method_exists(self::instance(),'increment')) {
            return self::instance()->increment(self::get_key($key), $offset);
        }

        return false;
    }//End Function

    /*
     * 自减
     * @var string $key
     * @var int $offset
     * @access public
     * @return int
     */
    static public function decrement($key, $offset=1) 
    {
        if (self::supportUUID() && method_exists(self::instance(),'decrement')) {
            return self::instance()->decrement(self::get_key($key), $offset);
        }

        return false;
    }//End Function
}
