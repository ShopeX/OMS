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
 
class base_request{

    var $request_params = array();

    public function set_params($request_params){
        $this->request_params = $request_params;
    }
    
    static function get_base_url(){
        $filename = (isset($_SERVER['SCRIPT_FILENAME'])) ? basename($_SERVER['SCRIPT_FILENAME']) : '';
        if (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename) {
            $base_url = $_SERVER['ORIG_SCRIPT_NAME']; 
        } elseif (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $filename) {
            $base_url = $_SERVER['SCRIPT_NAME'];
        } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $filename) {
            $base_url = $_SERVER['PHP_SELF'];
        } else {
            $path    = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
            $file    = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
            $segs    = explode('/', trim($file, '/'));
            $segs    = array_reverse($segs);
            $index   = 0;
            $last    = count($segs);
            $base_url = '';
            do {
                $seg = $segs[$index];
                $base_url = '/' . $seg . $base_url;
                ++$index;
            } while (($last > $index) && (false !== ($pos = strpos($path, $base_url))) && (0 != $pos));
        }
        
        $request_uri = self::get_request_uri();
        if (0 === strpos($request_uri, $base_url)) {
            return self::dirname($base_url);
        }
        if (0 === strpos($request_uri, strstr(PHP_OS, "WIN") ? str_replace('\\', '/', dirname($base_url)) : dirname($base_url))) {
            return self::dirname($base_url);
        }
        
        $truncatedrequest_uri = $request_uri;
        if (($pos = strpos($request_uri, '?')) !== false) {
            $truncatedrequest_uri = substr($request_uri, 0, $pos);
        }
        
        $basename = basename($base_url);
        if (empty($basename) || !strpos($truncatedrequest_uri, $basename)) {
            return;
        }
        
        if ((strlen($request_uri) >= strlen($base_url))
        && ((false !== ($pos = strpos($request_uri, $base_url))) && ($pos !== 0)))  {
            $base_url = substr($request_uri, 0, $pos + strlen($base_url));
        }            
        return  rtrim(self::dirname($base_url), '/');
    }
    
    static function get_path_info() {
        $path_info = '';
        if (isset($_SERVER['PATH_INFO'])) {
            $path_info = $_SERVER['PATH_INFO'];
        }elseif(isset($_SERVER['ORIG_PATH_INFO'])){
            $path_info = $_SERVER['ORIG_PATH_INFO'];
            $script_name = self::get_script_name();
            if(substr($script_name, -1, 1) == '/'){
                $path_info = $path_info . '/';
            }
        }else{
            $script_name = self::get_script_name();
            $script_dir = preg_replace('/[^\/]+$/', '', $script_name);
            $request_uri = self::get_request_uri();
            $urlinfo = parse_url($request_uri);
            if ( strpos($urlinfo['path'], $script_name) === 0) {
                $path_info = substr($urlinfo['path'], strlen($script_name));
            } elseif ( strpos($urlinfo['path'], $script_dir) === 0 ) {
                $path_info = substr($urlinfo['path'], strlen($script_dir));
            }
        }
        if($path_info){
            $path_info = "/".ltrim($path_info,"/");
        }
        return $path_info;
    }
    
    static function get_script_name() {
        return isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['ORIG_SCRIPT_NAME']) ? $_SERVER['ORIG_SCRIPT_NAME'] : '');
    }
    
    static function get_request_uri() {
        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
            return $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            return $_SERVER['REQUEST_URI'];
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
            return $_SERVER['ORIG_PATH_INFO'] . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        }
    }
    
    static function get_host() {
        $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
        //$host = $_SERVER['HTTP_HOST'];
        if (!empty($host)) {
            return $host;
        }
        
        $scheme = self::get_schema();
        $name   = self::get_name();
        $port   = self::get_port();

        if (($scheme == "HTTP" && $port == 80) || ($scheme == "HTTPS" && $port == 443)) {
            return $name;
        } else {
            return $name . ':' . $port;
        }
    }

    static function get_name() 
    {
        return $_SERVER['SERVER_NAME'];
    }//End Function

    static function get_schema() 
    {
        if (defined('WITH_HTTPS_SUPPORT') && strtolower(WITH_HTTPS_SUPPORT) == 'on') {
            return "HTTPS";
        }else{
            return ($_SERVER['HTTPS'] == 'on') ? "HTTPS" : "HTTP";
        }
    }//End Function

    static function get_port() 
    {
        return $_SERVER['SERVER_PORT'];
    }//End Function
    
    static function get_remote_addr(){
        if(!isset($GLOBALS['_REMOTE_ADDR_'])){
            $addrs = array();
            if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
                foreach( array_reverse( explode( ',',  $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) as $x_f ) {
                    $x_f = trim($x_f);
                    if ( preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $x_f ) )  {
                        $addrs[] = $x_f;
                    }
                }
            }
            $GLOBALS['_REMOTE_ADDR_'] = isset($addrs[0])?$addrs[0]:$_SERVER['REMOTE_ADDR'];
        }
        return $GLOBALS['_REMOTE_ADDR_'];
    }

    static function dirname($dir){
        return substr($dir,0,strrpos($dir,"/"));
    }
}
