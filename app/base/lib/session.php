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
 

class base_session{
    
    private $_sess_id;
    private $_sess_key = 's';
    private $_session_started = false;
    private $_sess_expires = 60;
    private $_cookie_expires = 0;
    private $_session_destoryed = false;

    function __construct() 
    {
        if(defined('SESS_NAME') && constant('SESS_NAME'))    $this->_sess_key = constant('SESS_NAME');
        if(defined('SESS_CACHE_EXPIRE') && constant('SESS_CACHE_EXPIRE'))   $this->_sess_expires = constant('SESS_CACHE_EXPIRE');
    }//End Function
    
    public function sess_id(){
        return $this->_sess_id;
    }

    public function set_sess_expires($minute) 
    {
        $this->_sess_expires = $minute;
    }//End Function

    public function set_cookie_expires($minute) 
    {
        $this->_cookie_expires = ($minute > 0) ? $minute : 0;
        if(isset($this->_sess_id)){
            $cookie_path = kernel::base_url();
            $cookie_path = $cookie_path ? $cookie_path : "/";
            header(sprintf('Set-Cookie: %s=%s; path=%s; expires=%s; httpOnly; SameSite=Strict; %s;', $this->_sess_key, $this->_sess_id, $cookie_path, gmdate('D, d M Y H:i:s T', time()+$minute*60), constant('WITH_HTTPS_SUPPORT')=='on'?'Secure':''), true);
        }
    }//End Function

    public function start($sence = ''){
        if($this->_session_started !== true){

            // 登陆后重置session
            if ($sence == 'login') {
                unset($_GET['sess_id'], $_COOKIE[$this->_sess_key], $this->_sess_id);
            }

            $cookie_path = kernel::base_url();
            $cookie_path = $cookie_path ? $cookie_path : "/";
            if($this->_cookie_expires > 0){
                $cookie_expires = sprintf("expires=%s;",  gmdate('D, d M Y H:i:s T', time()+$this->_cookie_expires*60));
            }else{
                $cookie_expires = '';
            }
            if(isset($_GET['sess_id'])){
                $this->_sess_id = $_GET['sess_id'];
                if($_COOKIE[$this->_sess_key] != $_GET['sess_id'])
                    header(sprintf('Set-Cookie: %s=%s; path=%s; %s httpOnly; SameSite=Strict; %s;', $this->_sess_key, $this->_sess_id, $cookie_path, $cookie_expires, constant('WITH_HTTPS_SUPPORT')=='on'?'Secure':''), true);
            }elseif($_COOKIE[$this->_sess_key]){
                $this->_sess_id = $_COOKIE[$this->_sess_key];
            }elseif(!$this->_sess_id){
                $this->_sess_id = md5(microtime().base_request::get_remote_addr().mt_rand(0,9999));
                header(sprintf('Set-Cookie: %s=%s; path=%s; %s httpOnly; SameSite=Strict; %s;', $this->_sess_key, $this->_sess_id, $cookie_path, $cookie_expires, constant('WITH_HTTPS_SUPPORT')=='on'?'Secure':''), true);
            }
            if($this->getStore()->fetch($this->getKey($this->_sess_id), $_SESSION) === false){
                $_SESSION = array();
            }
            $this->_session_started = true;
            register_shutdown_function(array(&$this,'close'));
        }
        return true;
    }

    public function close($writeBack = true){
        if(strlen($this->_sess_id) != 32){
            return false;
        }
        if(!$this->_session_started){
            return false;
        }
        $this->_session_started = false;
        if(!$writeBack){
            return false;
        }
        if($this->_session_destoryed){
            return true;
        }else{
            return $this->getStore()->store($this->getKey($this->_sess_id), $_SESSION, ($this->_sess_expires * 60));
        }
    }
    
    public function destory(){
        if(!$this->_session_started){
            return false;
        }
        $this->_session_started = false;
        $res = $this->getStore()->store($this->getKey($this->_sess_id), array(), 1);
        if($res){
            $_SESSION = array();
            $this->_session_destoryed = true;
            $cookie_path = kernel::base_url();
            $cookie_path = $cookie_path ? $cookie_path : "/";
            header(sprintf('Set-Cookie: %s=%s; path=%s; httpOnly; SameSite=Strict; %s;', $this->_sess_key, $this->_sess_id, $cookie_path, constant('WITH_HTTPS_SUPPORT')=='on'?'Secure':''), true);
            unset($this->_sess_id);
            return true;
        }else{
            return false;
        }
    }

	/**
	 * 通过对常量　MEMCACHE 的识别返回不同的存储对像
	 *
	 * @author hzjsq
	 × @param void
	 * @return Object
	 */
	private function getStore() {
		
		if ($this->useCache()) {

			return cachecore::instance();
		} else {

			return base_kvstore::instance('sessions');	
		}
	}

	/**
	 * 通过对常量对缓存key进行适配
	 *
	 * @author hzjsq
	 × @param $key String
	 * @return String
	 */
	private function getKey($key) {
        
		if ($this->useCache()) {

			$key = 'S_' . $key; 
		}

		return $key;
	}

	/**
	 * 确定是否使用 MEMCACHE 缓存SESSION
	 *
	 * @author hzjsq
	 * @praram void
	 * @return Boolean
	 */
	private function useCache() {
		
		//判断是否具有缓存配置来判断能否使用Cache模块保存SESSION
		if(defined('CACHE_STORAGE') && in_array(constant('CACHE_STORAGE'), array('base_cache_memcache','base_cache_memcached','base_cache_redis'))){
			return true;
		} else {

			return false;
		}
	}

    public function getMobileVerifyCode($telephone, &$errorMsg) {
        $key = $this->getKey($this->_sess_id.'mobileverifycode');
        $this->getStore()->fetch($key, $verifyCode);
        if(!$verifyCode) {
            $verifyCode = mt_rand(100000,999999);
        }
        if(!kernel::single('taoexlib_sms')->sendSms(array('event_type'=>'login','check_code'=>$verifyCode,'telephone'=>$telephone), $errorMsg)) {
            return false;
        }
        $this->getStore()->store($key, $verifyCode, 600);
        return true;
    }

    public function checkMobileVerifyCode() {
        $key = $this->getKey($this->_sess_id.'mobileverifycode');
        $this->getStore()->fetch($key, $verifyCode);
        if($verifyCode && $verifyCode == $_POST['mobileverifycode']) {
            return true;
        }
        return false;
    }
    
    /**
     * 删除指定的session_id
     * @param string $session_id
     * 
     * @return boolean
     */
    public function deleteSessionId($session_id)
    {
        if(empty($session_id)){
            return true;
        }
        
        //kv缓存key键名
        $kv_session_id = $this->getKey($session_id);
        
        //删除指定kv缓存
        $this->getStore()->store($kv_session_id, array(), 1);
        
        return true;
    }
    
    
    /**
     * 指定KEY缓存SESSION
     *
     * @return void
     * @author
     **/
    public function appointStore($key, $ttl)
    {
        $res = $this->getStore()->store($this->getKey($key), $_SESSION, $ttl);
        
        return $res;
    }
    
    /**
     * 指定KEY获取SESSION
     *
     * @return void
     * @author
     **/
    public function appointFetch($key)
    {
        $this->getStore()->fetch($this->getKey($key), $s);
        
        return $s;
    }
}
