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


if(!defined('APP_DIR')){
    define('APP_DIR',ROOT_DIR.'/app');
}

if(!defined('TRAIT_DIR')){
    define('TRAIT_DIR',ROOT_DIR.'/trait');
}

if(!defined('ECAE_MODE') && defined('ECAE_SITE_ID') && ECAE_SITE_ID > 0){
    define('ECAE_MODE', true);
}else{
    define('ECAE_MODE', false);
}

error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED ^ E_WARNING);

require_once(dirname(__FILE__) . '/lib/ego/ego.php');

class kernel{

    static $base_url = null;
    static $url_app_map = array();
    static $app_url_map = array();
    static $console_output = false;
    static private $__online = null;
    static private $__router = null;
    static private $__db_instance = null;
    static private $__singleton_instance = array();
    static private $__request_instance = null;
    static private $__single_apps = array();
    static private $__service_list = array();
    static private $__base_url = array();
    static private $__language = null;

    static function cleanInstance() {
        self::$__db_instance = null;
        self::$__singleton_instance = [];
        self::$__request_instance =null;
        self::$__single_apps =[];
    }

    static function boot(){
        set_error_handler(array('kernel', 'exception_error_handler'));
        try{
            // 如果已经安装直接引入CONFIG
            if (self::is_online()) {
                require ROOT_DIR . '/config/config.php';
                @include APP_DIR . '/base/defined.php';
            }

            // 兼容PHP ERROR
            if (defined('SENTRY_OPTIONS') && constant('SENTRY_OPTIONS') && is_array(constant('SENTRY_OPTIONS'))){
                \Sentry\init(constant('SENTRY_OPTIONS'));
            }

            require(ROOT_DIR.'/config/mapper.php');
            self::$url_app_map = $urlmap;
            foreach(self::$url_app_map AS $flag=>$value){
                self::$app_url_map[$value['app']] = $flag;
            }

            /*// xss  和 sql 注入问题
            $before_request = json_encode($_REQUEST);
            $after_request = json_encode(self::request_filter($_REQUEST));
            if ($before_request != $after_request) {
                header("HTTP/1.1 508 Not Found");
                exit;
            }*/
            // if(!self::register_autoload()){
            //     require(dirname(__FILE__) . '/autoload.php');
            // }

            $pathinfo = self::request()->get_path_info();
            $jump = false;
            if(isset($pathinfo[1])){
                if($p = strpos($pathinfo,'/',2)){
                    $part = substr($pathinfo,0,$p);
                }else{
                    $part = $pathinfo;
                    $jump = true;
                }
            }else{
                $part = '/';
            }

            if($part=='/api'){
                cachemgr::init();
                if(isset($_POST['method']) && (substr($_POST['method'],0,4) == 'wms.' ||substr($_POST['method'],0,6) == 'store.' || in_array($_POST['method'],['ome.order.deliverypriority']))){
                    #wms请求处理
                    //return kernel::single('rpc_service')->process($pathinfo);
                    return kernel::single('erpapi_rpc_service',1)->process($pathinfo);
                }else{
                    return kernel::single('base_rpc_service',1)->process($pathinfo);
                }
            }elseif($part=='/callback'){
                // RPC callback 功能已移除
                // cachemgr::init();
                // return kernel::single('rpc_service',1)->callback($pathinfo);
                die('RPC callback function has been removed');
            }elseif($part=='/openapi'){
                cachemgr::init();
                return kernel::single('base_rpc_service',1)->process($pathinfo);
            }elseif($part=='/app-doc'){
                cachemgr::init();
                return kernel::single('base_misc_doc',1)->display($pathinfo);
            }elseif($part=='/qimen'){
                cachemgr::init();
                return kernel::single('qimen_rpc_service',1)->process($pathinfo);
            }

            if(isset(self::$url_app_map[$part])){
                if($jump){
                    $request_uri = self::request()->get_request_uri();
                    $urlinfo = parse_url($request_uri);
                    $query = $urlinfo['query']?'?'.$urlinfo['query']:'';
                    header('Location: '.$urlinfo['path'].'/'.$query);
                    exit;
                }else{
                    $app = self::$url_app_map[$part]['app'];
                    $prefix_len = strlen($part)+1;
                    // kernel::set_lang(self::$url_app_map[$part]['lang']);
                }
            }else{
                if ($part !== '/index.php') {
                    header("HTTP/1.1 404 Not Found");exit;
                }

                $app = self::$url_app_map['/']['app'];
                $prefix_len = 1;
                // kernel::set_lang(self::$url_app_map['/']['lang']);
            }

            $lang = kernel::single('base_component_request', 1)->get_cookie('oms-language');
            kernel::set_lang($lang ?: self::$url_app_map['/']['lang']);

            if(!$app){
                readfile(ROOT_DIR.'/app/base/readme.html');
                exit;
            }

            if(!self::is_online()){
                if(file_exists(APP_DIR.'/setup/app.xml')){
                    if($app!='setup'){
                        //todo:进入安装check
                        setcookie('LOCAL_SETUP_URL', app::get('setup')->base_url(1), 0, '/');
                        header('Location: '. kernel::base_url().'/app/setup/check.php');
                        exit;
                    }
                }else{
                    echo '<h1>System is Offline, install please.</h1>';
                    exit;
                }
            }

            date_default_timezone_set(
                defined('DEFAULT_TIMEZONE') ? ('Etc/GMT'.(DEFAULT_TIMEZONE>=0?(DEFAULT_TIMEZONE*-1):'+'.(DEFAULT_TIMEZONE*-1))):'UTC'
            );

            if(isset($pathinfo[$prefix_len])){
                $path = substr($pathinfo,$prefix_len);
            }else{
                $path = '';
            }

            //init cachemgr
            if($app=='setup'){
                cachemgr::init(false);
            }else{
                cachemgr::init();
            }

            //get app router
            self::$__router = app::get($app)->router();
            self::$__router->dispatch($path);
        }catch(Exception $e){
            // 异常上报
            \Sentry\captureException($e);

            base_errorpage::exception_handler($e);
        }
    }

    static function request_filter($data){
        if(is_array($data)){
            foreach($data as $key=>$v){
                $data[$key] = self::request_filter($data[$key]);
            }
        }else{
            $length=strlen($data);
            if($length){
                if($length<3000){// 字符长度超过太长的跳过验证
                    $filter_rule=array(
                        'xss' =>"[\'\"\;\*\<\>]+.*\b(on)[a-zA-Z]{3,15}[\s\r\n\v\f]*\=|\b(expression)\(|<script[\s\\\\\/]*.*>|(<!\[cdata\[)|\b(eval|alert|prompt|msgbox)\s*\(|url\((\#|data|javascript)",
                        'sql' =>"([^{\s]{1}.+(select|update|insert((\/\*[\S\s]*?\*\/)|(\s)|(\+))+into).+?(from|set)((\/\*[\S\s]*?\*\/)|(\s)|(\+))+)|[^{\s]{1}.+(create|delete|drop|truncate|rename|desc)((\/\*[\S\s]*?\*\/)|(\s)|(\+))+(table|from|database)((\/\*[\S\s]*?\*\/)|(\s)|(\+))|(into((\/\*[\S\s]*?\*\/)|\s|\+)+(dump|out)file\b)|\bsleep\((\s*)(\d*)(\s*)\)|benchmark\(([^\,]*)\,([^\,]*)\)|\b(declare|set|select)\b.*@|union\b.*(select|all)\b|(select|update|insert|create|delete|drop|grant|truncate|rename|exec|desc|from|table|database|set|where)\b.*((charset|ascii|bin|char|uncompress|concat|concat_ws|conv|export_set|hex|instr|left|load_file|locate|mid|sub|substring|oct|reverse|right|unhex)\(|(master\.\.sysdatabases|msysaccessobjects|msysqueries|sysmodules|mysql\.db|sys\.database_name|information_schema\.|sysobjects|sp_makewebtask|xp_cmdshell|sp_oamethod|sp_addextendedproc|sp_oacreate|xp_regread|sys\.dbms_export_extension))",
                    );
                    foreach ($filter_rule as $key => $value) {
                        $data = preg_replace("/" . $value . "/si", "", $data);
                    }
                }
            } else {
                $data = $data;
            }
        }
        return $data;
    }

    static function exception_error_handler($errno, $errstr, $errfile, $errline )
    {
        switch ($errno) {
            case E_ERROR:
            case E_USER_ERROR:
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            break;

            case E_STRICT:
            case E_USER_WARNING:
            case E_USER_NOTICE:
            default:
                //do nothing
            break;
        }
        return true;
    }//End Function

    /**
     * @return base_router
     */
    static function router(){
        return self::$__router;
    }

	static function openapi_url($openapi_service_name,$method='access',$params=null){
        if(substr($openapi_service_name,0,8)!='openapi.'){
            trigger_error('$openapi_service_name must start with: openapi.');
            return false;
        }
        $arg = array();
        foreach((array)$params as $k=>$v){
            $arg[] = urlencode($k);
            $arg[] = urlencode(str_replace('/','%2F',$v));
        }
        return kernel::base_url(1).kernel::url_prefix().'/openapi/'.substr($openapi_service_name,8).'/'.$method.'/'.implode('/',$arg);
    }

    static function request(){
        if(!isset(self::$__request_instance)){
            self::$__request_instance = kernel::single('base_request',1);
        }
        return self::$__request_instance;
    }

    static function url_prefix(){
        return (defined('WITH_REWRITE') && WITH_REWRITE === true)?'':'/index.php';
    }

    static function this_url($full=false){
        return self::base_url($full).self::url_prefix().self::request()->get_path_info();
    }

    static function log($message,$keepline=false){
        if(self::$console_output){
            if($keepline){
                echo $message;
            }else{
                echo $message = $message."\n";
            }
        }else{
            $kernel_log = app::get('ome')->getConf('ome.kernel.log');
            if($kernel_log == 'true'){
                //modify by edwin.lzh@gmail.com 2010/6/10
                $message = sprintf("%s\t%s\n", date("Y-m-d H:i:s"), $message);
                switch(LOG_TYPE)
                {
                    case 3:
                        if(defined('LOG_FILE')){
                            $logfile = str_replace('{date}', date("Ymd"), LOG_FILE);
                            $ip = ($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
                            $ip = str_replace(array('.', ':'), array('_', '_'), $ip);
                            $logfile = str_replace('{ip}', $ip, $logfile);
                        }else{
                            $logfile = DATA_DIR . '/logs/all.php';
                        }
                        if(!file_exists($logfile)){
                            if(!is_dir(dirname($logfile)))  utils::mkdir_p(dirname($logfile));
                            file_put_contents($logfile, (defined(LOG_HEAD_TEXT))?LOG_HEAD_TEXT:'<'.'?php exit()?'.">\n");
                        }
                        @error_log($message, 3, $logfile);
                    break;
                    // case 0:
                    // default:
                    //     @error_log($message, 0);
                }//End Switch
            }
        }
    }

    static function base_url($full=false){
        $c = ($full) ? 'true' : 'false';
        if(!isset(self::$__base_url[$c])){
            if(defined('BASE_URL')){
                if($full){
                    self::$__base_url[$c] = constant('BASE_URL');
                }else{
                    $url = parse_url(constant('BASE_URL'));
                    if(isset($url['path'])){
                        self::$__base_url[$c] = $url['path'];
                    }else{
                        self::$__base_url[$c] = '';
                    }
                }
            }else{
                if(!isset(self::$base_url)){
                    self::$base_url = self::request()->get_base_url();
                }

                if(self::$base_url == '/'){
                    self::$base_url = '';
                }

                if($full){
                    self::$__base_url[$c] = strtolower(self::request()->get_schema()).'://'.self::request()->get_host().self::$base_url;
                }else{
                    self::$__base_url[$c] = self::$base_url;
                }
            }
        }
        return self::$__base_url[$c];
    }

    /**
     * 获取当前网站的域名
     * @param bool $full
     */
    public static function domain_url($full = true)
    {
        $result = kernel::base_url($full);
        if (empty(strpos($result, '://'))) {
            return $result;
        }

        list($prefix, $domain) = explode('://', $result);
        return $domain;
    }
    
    public static function get_host_url()
    {
        return strtolower(self::request()->get_schema()).'://'.self::request()->get_host();
    }

    static function set_online($mode){
        self::$__online = $mode;
    }

    static function is_online(){
        if(self::$__online===null){
            self::$__online = file_exists(ROOT_DIR.'/config/config.php');
        }
        return self::$__online;
    }

    static function single($class_name,$arg=null){
        if($arg===null){
            $p = strpos($class_name,'_');
            if($p){
                $app_id = substr($class_name,0,$p);
                if(!isset(self::$__single_apps[$app_id])){
                    self::$__single_apps[$app_id] = app::get($app_id);
                }
                $arg = self::$__single_apps[$app_id];
            }
        }
        if(is_object($arg)){
            $key = get_class($arg);
            if($key==='app'){
                $key .= '.' . $arg->app_id;
            }
            $key = '__class__' . $key;
        }else{
            $key = md5('__key__'.serialize($arg));
        }
        if(!isset(self::$__singleton_instance[$class_name][$key])){
            self::$__singleton_instance[$class_name][$key] = new $class_name($arg);
        }
        return self::$__singleton_instance[$class_name][$key];
    }

    /** @return base_db_connections */
    static function database(){
        if(!isset(self::$__db_instance)){
            $classname = defined('DATABASE_OBJECT') ? constant('DATABASE_OBJECT') : 'base_db_connections';
            $obj = new $classname;
            if($obj instanceof base_interface_db){
                self::$__db_instance = $obj;
            }else{
                trigger_error(DATABASE_OBJECT.' must implements base_interface_db!', E_USER_ERROR);
                exit;
            }
        }
        return self::$__db_instance;
    }

    static function service($srv_name,$filter=null){
        $defined_service = app::get('base')->getConf('server.'.$srv_name);
        if($defined_service && $defined_service = kernel::single($defined_service)){
            return $defined_service;
        }
        return self::servicelist($srv_name,$filter)->current();
    }

    static function servicelist($srv_name,$filter=null){
        if(self::is_online()){
            if(base_kvstore::instance('service')->fetch($srv_name,$service_define)){
                return $service_define ? new service($service_define,$filter) : new ArrayIterator(array());
            }
            if(!(defined('WITHOUT_KVSTORE_PERSISTENT') && constant('WITHOUT_KVSTORE_PERSISTENT')) && get_class(base_kvstore::instance('service')->get_controller())!='base_kvstore_mysql'){
                if(kernel::single('base_kvstore_mysql', 'service')->fetch($srv_name, $service_define)) {
                    base_kvstore::instance('service')->store($srv_name, $service_define);
                    return $service_define ? new service($service_define,$filter) : new ArrayIterator(array());
                }
                base_kvstore::instance('service')->store($srv_name, []);
            }
        }
        return new ArrayIterator(array());
    }

    static function strip_magic_quotes(&$var){
        foreach($var as $k=>$v){
            if(is_array($v)){
                self::strip_magic_quotes($var[$k]);
            }else{
                $var[$k] = stripcslashes($v);
            }
        }
    }

    static function register_autoload($load=array('kernel', 'autoload'))
    {
        if(function_exists('spl_autoload_register')){
            return spl_autoload_register($load);
        }else{
            return false;
        }
    }

    static function unregister_autoload($load=array('kernel', 'autoload'))
    {
        if(function_exists('spl_autoload_register')){
            return spl_autoload_unregister($load);
        }else{
            return false;
        }
    }

    static function autoload($class_name)
    {
        self::require_ego();
        _eogo_auto_load($class_name);
    }

    /**
     * 设置_lang
     * @param mixed $language language
     * @return mixed 返回操作结果
     */
    static public function set_lang($language)
    {
        self::$__language = trim(strtolower($language));
    }//End Function

    /**
     * 获取_lang
     * @return mixed 返回结果
     */
    static public function get_lang()
    {
        return  self::$__language ? self::$__language : ((defined('LANG')&&constant('LANG')) ? LANG : 'zh-cn');
    }//End Function

    /**
     * require_ego
     * @return mixed 返回值
     */
    static public function require_ego() {

        require_once(dirname(__FILE__) . '/lib/ego/ego.php');

    }
}

function __($str){
    return $str;
}
