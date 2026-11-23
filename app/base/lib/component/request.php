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
 * @package component
 * @author  edwin.lzh@gmail.com 2010/4/12
 */
class base_component_request 
{

    /*
     * app name
     * @val string
     */
    protected $_app = null;

    /*
     * app key
     * @val string
     */
    protected $_app_key = "app";

    /*
     * ctl name
     * @val string
     */
    protected $_ctl = null;

    /*
     * ctl key
     * @val string
     */
    protected $_ctl_key = "ctl";

    /*
     * act name
     * @val string
     */
    protected $_act = null;

    /*
     * act key
     * @val string
     */
    protected $_act_key = "act";

    /*
     * request params
     * @val array
     */
    protected $_params = array();

    /*
     * allow export params
     * @val array
     */
    protected $_ext_param_source = array("GET", "POST");

    /*
     * request uri
     * @val string
     */
    protected $_request_uri = null;

    /*
     * core url
     * @val string
     */
    protected $_core_url = null;

    /*
     * path info
     * @val string
     */
    protected $_path_info = null;
    

    static function is_ajax(){ //我们ajax系统的统一标示符
        return $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
    }

    /*
     * class init
     * @return self
     */
    static function init(){
    }

    static function base_url(){
    }

    /*
     * set app name
     * @param string $value
     * @return self
     */
    public function set_app_name($value) 
    {
        $this->_app = $value;
        return $this;
    }//End Function

    /*
     * set ctl name
     * @param string $value
     * @return self
     */
    public function set_ctl_name($value) 
    {
        $this->_ctl = $value;
        return $this;
    }//End Function

    /*
     * set act name
     * @param string $value
     * @return self
     */
    public function set_act_name($value) 
    {
        $this->_act = $value;
        return $this;
    }//End Function

    /*
     * get ctl name
     * @return string
     */
    public function get_app_name() 
    {
        return $this->_app;
    }//End Function

    /*
     * get ctl name
     * @return string
     */
    public function get_ctl_name() 
    {
        return $this->_ctl;
    }//End Function

    /*
     * get act name
     * @return string
     */
    public function get_act_name() 
    {
        return $this->_act;
    }//End Function
    
    /*
     * get all exists param by $key
     * @param string $key
     * @param boolean $ext
     * @param string $def
     * @return self
     */
    public function get_param($key, $ext=false, $def=null) 
    {
        switch(true)
        {
            case isset($this->_params[$key]):
                return $this->_params[$key];
            case $ext:
                if(in_array("GET", $this->get_ext_param_source()))
                return $this->get_get($key);
            case $ext:
                if(in_array("POST", $this->get_ext_param_source()))
                return $this->get_post($key);
            case $ext:
                if(in_array("COOKIE", $this->get_ext_param_source()))
                return $this->get_cookie($key);
            case $ext:
                if(in_array("SERVER", $this->get_ext_param_source()))
                return $this->get_server($key);
            case $ext:
                if(in_array("ENV", $this->get_ext_param_source()))
                return $this->get_env($key);
            default:
                return $def;
       }//End Switch
    }//End Function

    /*
     * set params
     * @param string $key
     * @param mixed $val
     * @return self
     */
    public function set_param($key, $val) 
    {
        if($val==null){
            return $this->del_param($key);
        }
        $this->_params[$key] = $val;
        return $this;
    }//End Function

    /*
     * get all params
     * @param boolean $ext
     * @return array
     */
    public function get_params($ext=false) 
    {
        $param = array();
        switch(true)
        {
            case $ext:
                if(in_array("GET", $this->get_ext_param_source()))
                $param = $param + (array) $this->get_get();
            case $ext:
                if(in_array("POST", $this->get_ext_param_source()))
                $param = $param + (array) $this->get_post();
            case $ext:
                if(in_array("COOKIE", $this->get_ext_param_source()))
                $param = $param + (array) $this->get_cookie();
            case $ext:
                if(in_array("SERVER", $this->get_ext_param_source()))
                $param = $param + (array) $this->get_server();
            case $ext:
                if(in_array("ENV", $this->get_ext_param_source()))
                $param = $param + (array) $this->get_env();
            default:
                $param = $param + $this->_params;
       }//End Switch
       return $param;
    }//End Function

    /*
     * set params
     * @param array $arr
     * @return self
     */
    public function set_params( $arr) 
    {
        $this->_params = $this->_params + (array) $arr;
        foreach($this->_params AS $key=>$val){
            if($val == null)
                $this->del_param($key);
        }
        return $this;
    }//End Function

    /*
     * delete param by $key
     * @param string $key
     * @return self
     */
    public function del_param($key) 
    {
        if(isset($this->_params[$key]))
            unset($this->_params[$key]);
        return $this;
    }//End Function

    /*
     * set ext params source
     * @param array $arr
     * @return self
     */
    public function set_ext_param_source($arr) 
    {
        if(is_array($arr))
            $this->_ext_param_source = $arr;
        return $this;
    }//End Function

    /*
     * get ext params source
     * @return array
     */
    public function get_ext_param_source() 
    {
        return (array) $this->_ext_param_source;
    }//End Function

    /*
     * get param by $key
     * @param string $key
     * @return mixed
     */
    public function get($key) 
    {
        switch(true)
        {
            case isset($this->_params[$key]):
                return $this->_params[$key];
            case isset($_GET[$key]):
                return $_GET[$key];
            case isset($_POST[$key]):
                return $_POST[$key];
            case isset($_COOKIE[$key]):
                return $_COOKIE[$key];
            case ($key == 'REQUEST_URI'):
                return $this->get_request_uri();
            case ($key == 'PATH_INFO'):
                return $this->get_path_info();
            case isset($_SERVER[$key]):
                return $_SERVER[$key];
            case isset($_ENV[$key]):
                return $_ENV[$key];
            default:
                return null;
        }//End Switch
    }//End Function

    /*
     * exists 
     * @param string $key
     * @return boolean
     */
    public function has($key) 
    {
        switch(true)
        {
            case isset($this->_params[$key]):
                return true;
            case isset($_GET[$key]):
                return true;
            case isset($_POST[$key]):
                return true;
            case isset($_COOKIE[$key]):
                return true;
            case isset($_SERVER[$key]):
                return true;
            case isset($_ENV[$key]):
                return true;
            default:
                return false;
        }//End Switch
    }//End Function

    /*
     * get $_GET
     * @param string $key
     * @return mixed
     */
    public function get_get($key = null) 
    {
        if($key === null)
            return $_GET;
        return $_GET[$key];
    }//End Function
    
    /*
     * get $_POST
     * @param string $key
     * @return mixed
     */
    public function get_post($key = null) 
    {
        if($key === null)
            return $_POST;
        return $_POST[$key];
    }//End Function

    /*
     * get $_COOKIE
     * @param string $key
     * @return mixed
     */
    public function get_cookie($key = null) 
    {
        if($key === null)
            return $_COOKIE;
        return $_COOKIE[$key];
    }//End Function

    /*
     * get $_SERVER
     * @param string $key
     * @return mixed
     */
    public function get_server($key = null) 
    {
        if($key === null)
            return $_SERVER;
        return $_SERVER[$key];
    }//End Function

    /*
     * get $_ENV
     * @param string $key
     * @return mixed
     */
    public function get_env($key = null) 
    {
        if($key === null)
            return $_ENV;
        return $_ENV[$key];
    }//End Function

    /*
     * get request uri
     * @return string
     */
    public function get_request_uri() 
    {
        if(empty($this->_request_uri))
            $this->set_request_uri();
        return $this->_request_uri;
    }//End Function

    /*
     * get code url
     * @return string
     */
    public function get_core_url() 
    {
        if(empty($this->_core_url))
            $this->set_core_url();
        return $this->_core_url;
    }//End Function

    /*
     * get path info
     * @return string
     */
    public function get_path_info() 
    {
        if(empty($this->_path_info))
            $this->set_path_info();
        return $this->_path_info;
    }//End Function

    /*
     * set request uri
     * @param string $request_uri
     * @return self
     */
    public function set_request_uri($request_uri = null) 
    {
        if ($request_uri === null) {
            if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // check this first so IIS will catch
                $request_uri = $_SERVER['HTTP_X_REWRITE_URL'];
            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $request_uri = $_SERVER['REQUEST_URI'];
                if (isset($_SERVER['HTTP_HOST']) && strstr($request_uri, $_SERVER['HTTP_HOST'])) {
                    $pathInfo    = parse_url($request_uri, PHP_URL_PATH);
                    $queryString = parse_url($request_uri, PHP_URL_QUERY);
                    $request_uri  = $pathInfo
                                 . ((empty($queryString)) ? '' : '?' . $queryString);
                }
            } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
                $request_uri = $_SERVER['ORIG_PATH_INFO'];
                if (!empty($_SERVER['QUERY_STRING'])) {
                    $request_uri .= '?' . $_SERVER['QUERY_STRING'];
                }
            } else {
                return $this;
            }
        } elseif (!is_string($request_uri)) {
            return $this;
        } else {
            // Set GET items, if available
            if (false !== ($pos = strpos($request_uri, '?'))) {
                // Get key => value pairs and set $_GET
                $query = substr($request_uri, $pos + 1);
                parse_str($query, $vars);
                $this->set_query($vars);
            }
        }

        $this->_request_uri = $request_uri;
        return $this;
    }//End Function

    /*
     * set request uri query
     * @param array $spec
     * @param mixed $value
     * @return self
     */
    public function set_query($spec, $value=null) 
    {
        if ((null === $value) && is_array($spec)) {
            foreach ($spec as $key => $value) {
                $this->set_query($key, $value);
            }
            return $this;
        }
        $_GET[(string) $spec] = $value;
        return $this;
    }//End Function

    /*
     * set core url
     * @param string $core_url
     * @return self
     */
    public function set_core_url($core_url = null) 
    {
        if ((null !== $core_url) && !is_string($core_url)) {
            return $this;
        }//force

        if ($core_url === null) {
            $filename = (isset($_SERVER['SCRIPT_FILENAME'])) ? basename($_SERVER['SCRIPT_FILENAME']) : '';

            if (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename) {
                $core_url = $_SERVER['ORIG_SCRIPT_NAME']; // 1and1 shared hosting compatibility
            } elseif (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $filename) {
                $core_url = $_SERVER['SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $filename) {
                $core_url = $_SERVER['PHP_SELF'];
            } else {
                // Backtrack up the script_filename to find the portion matching
                // php_self
                $path    = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
                $file    = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
                $segs    = explode('/', trim($file, '/'));
                $segs    = array_reverse($segs);
                $index   = 0;
                $last    = count($segs);
                $core_url = '';
                do {
                    $seg     = $segs[$index];
                    $core_url = '/' . $seg . $core_url;
                    ++$index;
                } while (($last > $index) && (false !== ($pos = strpos($path, $core_url))) && (0 != $pos));
            }

            // Does the baseUrl have anything in common with the request_uri?
            $request_uri = $this->get_request_uri();

            if (0 === strpos($request_uri, $core_url)) {
                // full $baseUrl matches
                $this->_core_url = $core_url;
                return $this;
            }

            if (0 === strpos($request_uri, dirname($core_url))) {
                // directory portion of $baseUrl matches
                $this->_core_url = rtrim(dirname($core_url), '/');
                return $this;
            }

            if (!strpos($request_uri, basename($core_url))) {
                // no match whatsoever; set it blank
                $this->_core_url = '';
                return $this;
            }

            // If using mod_rewrite or ISAPI_Rewrite strip the script filename
            // out of baseUrl. $pos !== 0 makes sure it is not matching a value
            // from PATH_INFO or QUERY_STRING
            if ((strlen($request_uri) >= strlen($core_url))
                && ((false !== ($pos = strpos($request_uri, $core_url))) && ($pos !== 0)))
            {
                $core_url = substr($request_uri, 0, $pos + strlen($core_url));
            }
        }

        $this->_core_url = rtrim($core_url, '/');
        return $this;
    }//End Function
        
    /*
     * set path info
     * @param string $path_info
     * @return self
     */
    public function set_path_info($path_info = null) 
    {
        if ($path_info === null) {
            $core_url = $this->get_core_url();

            if (null === ($request_uri = $this->get_request_uri())) {
                return $this;
            }

            // Remove the query string from REQUEST_URI
            if ($pos = strpos($request_uri, '?')) {
                $request_uri = substr($request_uri, 0, $pos);
            }

            if ((null !== $core_url)
                && (false === ($path_info = substr($request_uri, strlen($core_url)))))
            {
                // If substr() returns false then PATH_INFO is set to an empty string
                $pathInfo = '';
            } elseif (null === $core_url) {
                $path_info = $request_uri;
            }
        }

        $this->_path_info = $path_info;
        return $this;
    }//End Function

    /*
     * get http method
     * @return string
     */
    public function get_method() 
    {
        return $this->get_server('REQUEST_METHOD');
    }//End Function

    /*
     * is GET request
     * @return boolean
     */
    public function is_get() 
    {
        if('GET' == $this->get_method())
            return true;
        return false;
    }//End Function

    /*
     * is POST request
     * @return boolean
     */
    public function is_post() 
    {
        if('POST' == $this->get_method())
            return true;
        return false;
    }//End Function

    /*
     * is PUT request
     * @return boolean
     */
    public function is_put() 
    {
        if('PUT' == $this->get_method())
            return true;
        return false;
    }//End Function

    /*
     * is DELETE request
     * @return boolean
     */
    public function is_delete() 
    {
        if('DELETE' == $this->get_method())
            return true;
        return false;
    }//End Function

    /*
     * is HEAD request
     * @return boolean
     */
    public function is_head() 
    {
        if('HEAD' == $this->get_method())
            return true;
        return false;
    }//End Function

    /*
     * is OPTIONS request
     * @return boolean
     */
    public function is_options() 
    {
        if('OPTIONS' == $this->get_method())
            return true;
        return false;
    }//End Function

    /*
     * is ssl
     * @return boolean
     */
    public function is_https() 
    {
        return ($this->get_scheme() === 'HTTPS');
    }//End Function

    /*
     * is ajax
     * @return boolean
     */
    public function is_xml_httprequest() 
    {
        return ($this->get_header('X_REQUESTED_WITH') == 'XMLHttpRequest');
    }//End Function

    /*
     * is flase
     * @return boolean
     */
    public function is_flash_request() 
    {
        $header = strtolower($this->get_header('USER_AGENT'));
        return (strstr($header, ' flash')) ? true : false;
    }//End Function

    /*
     * get request header info
     * @return mixed
     */
    public function get_header($header) 
    {
        $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (!empty($_SERVER[$temp])) {
            return $_SERVER[$temp];
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (!empty($headers[$header])) {
                return $headers[$header];
            }
        }

        return false;
    }//End Function

    /*
     * get scheme
     * @return string
     */
    public function get_scheme() 
    {
        if (defined('WITH_HTTPS_SUPPORT') && strtolower(WITH_HTTPS_SUPPORT) == 'on') {
            return "HTTPS";
        }else{
            return ($this->get_server('HTTPS') == 'on') ? "HTTPS" : "HTTP";
        }
    }//End Function
    
    /*
     * get host
     * @return string
     */
    public function get_http_host() 
    {
        $host = $this->get_server('HTTP_HOST');
        if (!empty($host)) {
            return $host;
        }
        
        $scheme = $this->get_scheme();
        $name   = $this->get_server('SERVER_NAME');
        $port   = $this->get_server('SERVER_PORT'); 

        if (($scheme == "HTTP" && $port == 80) || ($scheme == "HTTPS" && $port == 443)) {
            return $name;
        } else {
            return $name . ':' . $port;
        }
    }//End Function

    /*
     * get full_host
     * @return string
     */
    public function get_full_http_host() 
    {
        return sprintf("%s://%s", strtolower($this->get_scheme()), $this->get_http_host());
    }//End Function
    
    /*
     * get removeip
     * @return string
     */
    public function get_remote_ip($checkProxy = false) 
    {
        if($checkProxy && $this->get_server('HTTP_CLIENT_IP')){
            $ip = $this->get_server('HTTP_CLIENT_IP');
        } elseif ($checkProxy && $this->get_server('HTTP_X_REAL_IP')) {
            $ip =  $this->get_server('HTTP_X_REAL_IP');
        } elseif ($checkProxy && $this->get_server('HTTP_X_FORWARDED_FOR')){
            $ip = $this->get_server('HTTP_X_FORWARDED_FOR');
        }else{
            $ip = $this->get_server('REMOTE_ADDR');
        }
        return $ip;
    }//End Function

    /*
     * browscap configuration setting in php.ini must point to the correct location of the browscap.ini file on your system
     * http://browsers.garykeith.com/downloads.asp
     * get browser
     * @return string
     */
    public function get_browser($return_array=true) 
    {
        return get_browser($this->get_server('HTTP_USER_AGENT'), $return_array);
    }//End Function

}//End Class
