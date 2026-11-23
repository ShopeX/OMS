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
class cachemgr 
{
    /*
     * @var boolean $_enable
     * @access static private
     */
    static private $_enable = false;

    /*
     * @var string $_co_depth
     * @access static private
     */
    static private $_co_depth = 0;

    /*
     * @var string $_cache_objects
     * @access static private
     */
    static private $_cache_objects = array();

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
     * @var string $_cache_objects_exists
     * @access static private
     */
    static private $_cache_objects_exists = array();

    /*
     * @var string $_cache_check_version_key
     * @access static private
     */
    static private $_cache_check_version_key = '__ECOS_CACHEMGR_CACHE_CHECK_VERSION_KEY__';

    /*
     * @var string $_cache_check_version
     * @access static private
     */
    static private $_cache_check_version = null;

    /*
     * @var string $_cache_key_global_varys
     * @access static private
     */
    static private $_cache_key_global_varys = null;

    /*
     * @var string $_vary_list_froce_mysql
     * @access static private
     */
    static private $_vary_list_froce_mysql = false;

    /*
     * @var array $_cache_expirations
     * @access static private
     */
    static private $_cache_expirations = array();

    /*
     * 初始化
     * @var boolean $with_cache
     * @access static public
     * @return void
     */
    static public function init($with_cache=true) 
    {
        if($with_cache && defined('CACHE_STORAGE') && constant('CACHE_STORAGE') && !WITHOUT_CACHE){
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
        if(is_null(self::$_instance)){
            self::$_instance = kernel::single(self::$_instance_name);
        }//使用实例时再构造实例
        return self::$_instance;
    }//End Function
    
    /*
     * 获取modified
     * @var string $type
     * @var string $vary_key
     * @access static public
     * @return mixed
     */
    static public function get_modified($type, $vary_key) 
    {
        return self::instance()->get_modified($type, $vary_key);
    }//End Function

    /*
     * 设置modified
     * @var string $type
     * @var string $vary_key
     * @var int $time
     * @access static public
     * @return boolean
     */
    static public function set_modified($type, $vary_key, $time=0) 
    {
        self::store_vary_list(self::fetch_vary_list(true));
        return self::instance()->set_modified($type, $vary_key, $time);
    }//End Function

    /*
     * 设置当前区块过期时间
     * @var int $time
     * @access static public
     * @return void
     */
    static public function set_expiration($time) 
    {
        self::update_expiration($time, self::$_co_depth);
    }//End Function

    /*
     * 设置区块过期时间
     * @var int $time
     * @access static private
     * @return void
     */
    static private function update_expiration($time, $level) 
    {
        if($time > 0){
            if(!isset(self::$_cache_expirations[$level])){
                self::$_cache_expirations[$level] = $time;
            }elseif(self::$_cache_expirations[$level] > $time){
                self::$_cache_expirations[$level] = $time; //todo: 如果有更小的时间进来，则取用
            }
        }
    }//End Function

    /*
     * 升级当前区块所有上级过期时间
     * @var int $time
     * @access static private
     * @return void
     */
    static private function update_parents_expiration($time) 
    {
        for($level=self::$_co_depth; $level>0; $level--){
            self::update_expiration($time, $level);
        }
    }//End Function

    /*
     * 获取缓存
     * @var string $key
     * @var mixed &$return
     * @access static public
     * @return boolean
     */
    static public function get($key, &$return) 
    {
        if(self::instance()->fetch(self::get_key($key), $data)){
            if($data['expires'] > 0 && time() > $data['expires']){
                return false;   
            }//todo:人工设置过期功能判断
            if(is_array($data['varys'])){
                foreach($data['varys'] AS $type=>$vary){
                    foreach($vary AS $o){
                        if(!is_array($data['cotime'][$type]) 
                        || !array_key_exists($o, $data['cotime'][$type])
                        || $data['cotime'][$type][$o] != self::get_modified($type, $o)){
                            return false;
                        }else{
                            $checks[$type][] = $o;
                        }
                    }
                }
            }
            $return = $data['content'];
            if(isset($checks)){
                foreach($checks AS $type=>$check){
                    foreach($check AS $o){
                        self::check_expires($type, $o);
                    }
                }
            }//设置上级cache的check_expires
            return true;
        }else{
            return false;
        }
    }//End Function

    /*
     * 设置缓存
     * @var string $key
     * @var mixed $content
     * @var array $varys
     * @access static public
     * @return boolean
     */
    static public function set($key, $content, $params=array()) 
    {
        $data = array('content' => $content);
        if(is_array($params['varys'])){
            $data['cotime'] = array();
            $data['varys'] = array();
            foreach($params['varys'] AS $type=>$vary){
                $type = strtoupper($type);
                foreach($vary AS $o=>$val){
                    $o = strtoupper($o);
                    $data['cotime'][$type][$o] = self::get_modified($type, $o);
                    $data['varys'][$type][] = $o;
                }
            }
        }
        $data['expires'] = ($params['expires']>0) ? $params['expires'] : 0;       //todo: 设置过期时间
        if($data['expires'] > 0){
            self::update_parents_expiration($data['expires']);  
        }//todo: 更新所有上级的过期时间
        return self::instance()->store(self::get_key($key), $data);
    }//End Function

    /*
     * 方法缓存
     * @var mixed $func
     * @var array $args
     * @var int $ttl
     * @access static public
     * @return mixed
     */
    static public function exec($func, $args, $ttl=3600) 
    {
        if(is_array($func)){
            $key = self::get_key('ECOS_CACHEMGR_PREFIX' . '_CLASS_' . get_class($func[0]) . '_FUNC_' . serialize($func[1]) . '_PARAMS_' . serialize($args));
        }else{
            $key = self::get_key('ECOS_CACHEMGR_PREFIX' . '_FUNC_' . $func . '_PARAMS_' . serialize($args));
        }

        if(self::instance()->fetch($key, $data) === false || (time() - $data['time'] > $ttl)){
            $data['return'] = call_user_func_array($func, $args);
            $data['time'] = time();
            self::instance()->store($key, $data);
        }
        return $data['return'];
    }//End Function

    /*
     * 获取缓存key
     * @var string $key
     * @access static public
     * @return string
     */
    static public function get_key($key) 
    {
        $key_array['key'] = $key;
        $key_array['version'] = &self::get_cache_check_version();
        $key_array['global_varys'] = &self::get_cache_global_varys();
        if(method_exists(self::instance(), "get_key")){
            return self::instance()->get_key($key_array);
        }else{
            return md5(serialize($key_array));
        }
    }//End Function

    /*
     * 取得缓存版本
     * @access static public
     * @return string
     */
    static public function get_cache_check_version() 
    {
        if(!isset(self::$_cache_check_version)){
            self::$_cache_check_version = self::ask_cache_check_version();
        }//只取一次
        return self::$_cache_check_version;
    }//End Function

    /*
     * 取得全局varys
     * @access static public
     * @return string
     */
    static public function get_cache_global_varys() 
    {
        //引响全局的vary
        //todo：一般数据来源为get、post、cookie、session、server中取值或从http_refer等信息来判断取值
        //保证global_varys的值不受程序改变而改变
        if(!isset(self::$_cache_key_global_varys)){
            self::$_cache_key_global_varys = self::get_global_varys();
        }//只取一次
        return self::$_cache_key_global_varys;
    }//End Function

    /*
     * 获取全局key_varys属性，将影响全局key的生成
     * @access static public
     * @return array
     */
    static public function get_global_varys() 
    {
        $app_varys = array();
        $serviceList = kernel::serviceList('cachemgr_global_vary');
        foreach($serviceList AS $service){
            $class_name = get_class($service);
            $p = strpos($class_name,'_');
            $varys = null;
            if(method_exists($service, 'get_varys')){
                $varys = (array)$service->get_varys();
            }
            if(is_array($varys) && $p){
                $app_id = substr($class_name,0,$p);
                if(isset($app_varys[$app_id])){
                    $app_varys[$app_id] = array_merge($app_varys[$app_id], $varys);
                }else{
                    $app_varys[$app_id] = $varys;
                }
                ksort($app_varys[$app_id]);
            }
        }
        ksort($app_varys);
        return $app_varys;
    }//End Function

    /*
     * 询问缓存版本号
     * @var boolean $force
     * @access static public
     * @return string
     */
    static public function ask_cache_check_version($force=false) 
    {
        $key = self::get_cache_check_version_key();
        if($force || self::instance()->fetch($key, $val) === false){
            $val = md5($key . time());
            self::instance()->store($key, $val);
            self::$_cache_check_version = $val; //todo：强制更新
        }
        return $val;
    }//End Function

    /*
     * 获得版本号的key
     * @void
     * @access static public
     * @return string
     */
    static public function get_cache_check_version_key() 
    {
        $kvprefix = (defined('KV_PREFIX')) ? KV_PREFIX : '';
        $key = md5($kvprefix . self::$_cache_check_version_key);
        return $key;
    }//End Function

    /*
     * 缓存检查开始
     * @access static public
     * @return void
     */
    static public function co_start() 
    {
        unset(self::$_cache_objects[++self::$_co_depth]);
        unset(self::$_cache_objects_exists[self::$_co_depth]);
    }//End Function

    /*
     * 缓存检查结果
     * @access static public
     * @return array
     */
    static public function co_end() 
    {
        $data['expires'] = isset(self::$_cache_expirations[self::$_co_depth]) ? self::$_cache_expirations[self::$_co_depth] : null;
        $data['varys'] = self::$_cache_objects[self::$_co_depth--];
        return $data;
    }//End Function

    /*
     * 检查过期
     * @var string $type
     * @var mixed $cache_name
     * @access static public
     * @return void
     */
    static public function check_expires($type, $cache_name) 
    {
        $upper_type = strtoupper($type);
        for($i=self::$_co_depth; $i>0; $i--){
            if(is_array($cache_name)){
                foreach($cache_name AS $name){
                    $upper_cache_name = strtoupper($name);
                    if($upper_type!='DB' || self::get_modified($type, $name)>0){
                        self::$_cache_objects[$i][$upper_type][$upper_cache_name] = 1;
                    }
                }
            }else{
                $upper_cache_name = strtoupper($cache_name);
                if($upper_type!='DB' || self::get_modified($type, $cache_name)>0){
                    self::$_cache_objects[$i][$upper_type][$upper_cache_name] = 1;
                }
            }
            self::$_cache_objects_exists[$i][$upper_type][strtoupper(md5(serialize($cache_name)))] = 1;
        }
    }//End Function

    /*
     * 检查当前缓存深度
     * @access static public
     * @return int
     */
    static public function check_current_co_depth() 
    {
        return self::$_co_depth;
    }//End Function

    /*
     * 检查当前缓存层中是否已经check_expires todo：优化缓存性能
     * @var string $type
     * @var mixed $cache_name
     * @access static public
     * @return boolean
     */
    static public function check_current_co_objects_exists($type, $cache_name) 
    {
        return isset(self::$_cache_objects_exists[self::$_co_depth][strtoupper($type)][strtoupper(md5(serialize($cache_name)))]);
    }//End Function

    /*
     * 保存vary_list
     * @var array $vary_list
     * @access static public
     * @return boolean
     */
    static public function store_vary_list($vary_list) 
    {
        return base_kvstore::instance('cache/expires')->store('vary_list', $vary_list);
    }//End Function

    /*
     * 读取vary_list
     * @access static public
     * @return mixed
     */
    static public function fetch_vary_list($force=false) 
    {
        $vary_list = array();
        return $vary_list;
        if(self::$_vary_list_froce_mysql===true || $force===true){
            $rows = kernel::database()->select('SELECT UPPER(`type`) AS `type`, UPPER(`name`) AS `name`, `expire` FROM sdb_base_cache_expires', true);
            foreach($rows AS $row){
                $vary_list[$row['type']][$row['name']] = $row['expire'];
            }
        }else{
            base_kvstore::instance('cache/expires')->fetch('vary_list', $vary_list);
            if(empty($vary_list)){
                //如果发生意外，取出数据为空，则再取次数据库数据并写回kvstore
                $vary_list = self::fetch_vary_list(true);
                self::store_vary_list($vary_list);
            }
        }
        return $vary_list;
    }//End Function

    /*
     * 查看缓存状态
     * @var array &$msg
     * @access static public
     * @return boolean
     */
    static public function status(&$msg) 
    {
        if(method_exists(self::instance(), "status")){
            $msg = self::instance()->status();
            return true;
        }else{
            $msg = app::get('base')->_('当前缓存控制器无法显示状态');
            return false;
        }
    }//End Function

    /*
     * 优化缓存
     * @var array &$msg
     * @access static public
     * @return boolean
     */
    static public function optimize(&$msg) 
    {
        if(method_exists(self::instance(), "optimize")){
            return self::instance()->optimize();
        }else{
            $msg = app::get('base')->_('当前缓存控制器无需优化');
            return false;
        }
    }//End Function

    /*
     * 清空缓存 
     * todo：不是真正删除
     * 只是迭代新的缓存版本号
     * 如果使用的cache_storage不会自动释放空间，则需要人工干预
     * 也可以重截cache_storage的clean方法，实现物理删除
     * @var array &$msg
     * @access static public
     * @return boolean
     */
    static public function clean(&$msg) 
    {
        if(method_exists(self::instance(), "clean")){
            $res = self::instance()->clean();
        }else{
            $res = self::ask_cache_check_version(true);
        }
        if($res){
            foreach(kernel::servicelist('base_cachemgr_clean') AS $service){
                if(is_object($service) && method_exists($service, 'clean')){
                    $service->clean();
                }
            }
            return true;
        }else{
            return false;
        }
    }//End Function


    /**
     * 增加用于后端直接控制缓存的方法， fetch 和 store 
     */

    //获取缓存
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
     * 
     */
    //保存数据
    static public function store($key, $value, $ttl=0) {

        $data = array('content' => $value);
        $data['expires'] = ($ttl > 0) ? time() + $ttl : 0;       //todo: 设置过期时间
        return self::instance()->store(self::get_key($key), $data, $ttl);
    } 

}//End Class
