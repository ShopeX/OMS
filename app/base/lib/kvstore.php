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
 * 为了数据安全，请确保persistent方法的调用正确
 */
class base_kvstore{

    /*
     * @var string $__instance
     * @access static private
     */
    static private $__instance = array();

    /*
     * @var string $__persistent
     * @access static private
     */
    static private $__persistent = true;

    /*
     * @var string $__controller
     * @access private
     */
    private $__controller = null;

    /*
     * @var string $__prefix
     * @access private
     */
    private $__prefix = null;
    
    /*
     * @var string $__fetch_count
     * @access static public
     */
    static public $__fetch_count = 0;

    /*
     * @var string $__store_count
     * @access static public
     */
    static public $__store_count = 0;

    /*
     * 构造
     * @var string $prefix
     * @access public
     * @return void
     */
    function __construct($prefix){
        if(defined('FORCE_KVSTORE_STORAGE') && constant('FORCE_KVSTORE_STORAGE')){
            $this->set_controller(kernel::single(FORCE_KVSTORE_STORAGE, $prefix));
        }else{
            if(defined('KVSTORE_STORAGE') && constant('KVSTORE_STORAGE')){
                $this->set_controller(kernel::single(KVSTORE_STORAGE, $prefix));
            }else{
                $this->set_controller(kernel::single('base_kvstore_filesystem', $prefix));
            }
        }
        $this->set_prefix($prefix);
    }//End Function

    /*
     * 设置持久化与否
     * @var boolean $flag
     * @access public
     * @return string
     */
    static function config_persistent($flag) 
    {
        self::$__persistent = ($flag) ? true : false;
    }//End Function

    /*
     * 返回KV_PREFIX
     * @access public
     * @return string
     */
    static public function kvprefix() 
    {
        return (defined('KV_PREFIX')) ? KV_PREFIX : 'default';
    }//End Function

    /**
     * 实例一个kvstore
     * @var string $prefix
     * @access public
     * @return base_kvstore
     */
    static public function instance($prefix){
        if(!isset(self::$__instance[$prefix])){
            self::$__instance[$prefix] = new base_kvstore($prefix);
        }
        return self::$__instance[$prefix];
    }//End Function

    /*
     * 设置prefix
     * @var string $prefix
     * @access public
     * @return void
     */
    public function set_prefix($prefix) 
    {
        $this->__prefix = $prefix;
    }//End Function

    /*
     * 取得prefix
     * @access public
     * @return string
     */
    public function get_prefix() 
    {
        return $this->__prefix;
    }//End Function

    /*
     * 设置kvstore控制器
     * @var object $controller
     * @access public
     * @return void
     */
    public function set_controller($controller) 
    {
        if($controller instanceof base_interface_kvstore_base){
            $this->__controller = $controller;
        }else{
            throw new Exception('this instance must implements base_interface_kvstore_base');
        }
    }//End Function

    /*
     * 得到kvstore控制器
     * @access public
     * @return object
     */
    public function get_controller() 
    {
        return $this->__controller;
    }//End Function

    /*
     * 自增
     * @var string $key
     * @var int $offset
     * @access public
     * @return int
     */
    public function increment($key, $offset=1) 
    {
        if($this->get_controller() instanceof base_interface_kvstore_extension){
            return $this->get_controller()->increment($key, $offset);
        }else{
            throw new Exception('this instance can\'t support increment');
        }
    }//End Function

    /*
     * 自减
     * @var string $key
     * @var int $offset
     * @access public
     * @return int
     */
    public function decrement($key, $offset=1) 
    {
        if($this->get_controller() instanceof base_interface_kvstore_extension){
            return $this->get_controller()->decrement($key, $offset);
        }else{
            throw new Exception('this instance can\'t support decrement');
        }
    }//End Function

    /*
     * 获取key的内容
     * @var string $key
     * @var mixed &$value
     * @var int $timeout_version
     * @access public
     * @return boolean
     */
    public function fetch($key, &$value, $timeout_version=null){
        self::$__fetch_count++;
        if($this->get_controller()->fetch($key, $value, $timeout_version)){
            return true;
        }else{
            return false;
        }
    }//End Function

    public function supportUUID(){
        
        return $this->get_controller()->supportUUID();
    }//End Function


    public function getUUIDFix(){
        
        return $this->get_controller()->getUUIDFix();
    }//End Function

    /*
     * 设置key的内容
     * @var string $key
     * @var mixed $value
     * @var int $ttl
     * @access public
     * @return boolean
     */
    public function store($key, $value, $ttl=0)
    {
        self::$__store_count++;
        if(!(defined('WITHOUT_KVSTORE_PERSISTENT') && constant('WITHOUT_KVSTORE_PERSISTENT')) && self::$__persistent && get_class($this->get_controller())!='base_kvstore_mysql' && kernel::is_online()){
            $this->persistent($key, $value, $ttl);
        }
        return $this->get_controller()->store($key, $value, $ttl);
    }//End Function

    /*
     * 删除key的内容
     * @var string $key
     * @var int $ttl
     * @access public
     * @return boolean
     */
    public function delete($key, $ttl=1) 
    {
        if($this->fetch($key, $value)){
            return $this->store($key, $value, ($ttl>0)?$ttl:1);    //todo: 不实际删除，由cron统一处理delete
        }
        return true;
    }//End Function

	public function real_delete($key) 
    {
    	$single = base_kvstore::instance($this->__prefix);
        $single->get_controller()->delete($key);
    }
    
   
    /*
     * 数据持久化
     * @var string $key
     * @var mixed $value
     * @var int $ttl
     * @access public
     * @return void
     */
    public function persistent($key, $value, $ttl=0) 
    {
        kernel::single('base_kvstore_mysql', $this->get_prefix())->store($key, $value, $ttl);  //todo: 持久化
    }//End Function
    
    /*
     * 数据还原
     * @var array $record
     * @access public
     * @return boolean
     */
    public function recovery($record) 
    {
        return $this->get_controller()->recovery($record);
    }//End Function

    /*
     * 删除过期数据
     * @var array $record
     * @access public
     * @return boolean
     */
    static public function delete_expire_data() 
    {
        $time = time();
        $count = kernel::database()->count('SELECT count(*) FROM sdb_base_kvstore WHERE ttl>0 AND (dateline+ttl)<'.$time);
        $pagesize = 100;
        $page = ceil($count / 100);
        for($i=0; $i<$page; $i++){
            $rows = kernel::database()->selectlimit('SELECT `prefix`, `key` FROM sdb_base_kvstore WHERE ttl>0 AND (dateline+ttl)<'.$time, $pagesize, $i*$pagesize);
            foreach($rows AS $row){
                $single = base_kvstore::instance($row['prefix']);
                if(get_class($single->get_controller()) != 'base_kvstore_mysql'){
                    $single->get_controller()->delete($row['key']);
                }
            }
        }
        kernel::database()->exec('DELETE FROM sdb_base_kvstore WHERE ttl>0 AND (dateline+ttl)<'.$time, true);
    }//End Function
    
    public function append($key, $value, $ttl=0)
    {
        
        if($this->fetch($key, $last_value)){
        	$value = $last_value . $value;
        }
        
        if(!(defined('WITHOUT_KVSTORE_PERSISTENT') && constant('WITHOUT_KVSTORE_PERSISTENT')) && self::$__persistent && get_class($this->get_controller())!='taoexlib_kvstore_mysql' && kernel::is_online()){
            $this->persistent($key, $value, $ttl);
        }
        
        return $this->get_controller()->store($key, $value, $ttl);
    }//End Function

    /**
     * 初始化自增ID值
     *
     * @return void
     * @author 
     */
    public function setcr($key, $value, $ttl=0)
    {
        if ($this->supportUUID() && method_exists($this->get_controller(),'setcr')) {
            return $this->get_controller()->setcr($key, $value, $ttl);
        }

        return false;
    }

}//End Class
