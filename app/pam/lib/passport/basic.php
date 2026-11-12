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


class pam_passport_basic implements pam_interface_passport{

    function __construct(){
        kernel::single('base_session')->start('login');
        $this->init();
    }
    
    function init(){
        if($ret = app::get('pam')->getConf('passport.'.__CLASS__)){
            return $ret;
        }else{
            $ret = $this->get_setting();
            $ret['passport_id']['value'] = __CLASS__;
            $ret['passport_name']['value'] = $this->get_name();
            $ret['shopadmin_passport_status']['value'] = 'true';
            $ret['site_passport_status']['value'] = 'true';
            $ret['passport_version']['value'] = '1.5';
            app::get('pam')->setConf('passport.'.__CLASS__,$ret);
            return $ret;        
        }
    }
    function get_name(){
        return app::get('pam')->_('用户登录');
    }
    function get_o2o_name(){
        return app::get('pam')->_('门店登录');
    }

    function get_login_form($auth, $appid, $view, $ext_pagedata=array()){
        $render = app::get('pam')->render();
        $render->pagedata['callback'] = $auth->get_callback_url(__CLASS__);
        if($auth->is_enable_vcode()){
            $render->pagedata['show_varycode'] = 'true';
            $render->pagedata['type'] = $auth->type;
        }
        if(isset($_SESSION['last_error']) && ($auth->type == $_SESSION['type'])){
            $render->pagedata['error_info'] = $_SESSION['last_error'];
            if($_SESSION['uname'] && $_SESSION['password']) {
                $render->pagedata['password'] = $_SESSION['password'];
                $render->pagedata['uname'] = $_SESSION['uname'];
                unset($_SESSION['uname']);
                unset($_SESSION['password']);
            }
            unset($_SESSION['last_error']);
            unset($_SESSION['type']);
        }
        if($ext_pagedata){
            foreach($ext_pagedata as $key => $v){
                $render->pagedata[$key] = $v;
            }
        }
        return $render->fetch($view,$appid);
    }
    
    function get_login_o2o_store($appid, $view){
        $render = app::get('pam')->render();
        $render->pagedata['desktop_path'] = app::get('desktop')->res_url;
        $render->pagedata['http_pre_path'] = kernel::base_url(1);
        return $render->fetch($view,$appid);
    }

    /**
     * 过滤不可见字符，支持中文过滤
     * 0至31和127这33个编码是不可见的特殊字符（控制符）
     * @param $str
     * @return string
     */
    private function filterNonPrintableChar($str){
        $i = 0;
        $newStr = '';
        while (isset($str[$i])) {
            $char = $str[$i];
            $asc = ord($char);
            if ($asc > 31 && $asc < 127 || $asc > 127) $newStr .= $char;
            $i++;
        }
        return $newStr;
    }

    public function login($auth,&$usrdata)
    {
        // Vue 直接传明码时绕过 cipher 解密
        if (isset($_POST['login_name']) && isset($_POST['login_password'])) {
            $_POST['uname'] = $_POST['login_name'];
            $_POST['password'] = $_POST['login_password'];
        } else {
            $key =  date('Y-m-d',time())."eDu189";
            $decrypted = openssl_decrypt($_POST['login_cipher'], 'aes-128-cbc', $key, OPENSSL_ZERO_PADDING , OPENSSL_CPIHER_IV);
            if(empty($decrypted) || false == $decrypted){
                $usrdata['log_data'] = app::get('pam')->_('登录信息异常');
                $_SESSION['error'] = app::get('pam')->_('登录信息异常');
                return false;
            }
            $decrypted = $this->filterNonPrintableChar($decrypted);
            $decrypted = json_decode($decrypted,true);
            if(!is_array($decrypted)){
                $usrdata['log_data'] = app::get('pam')->_('登录信息异常');
                $_SESSION['error'] = app::get('pam')->_('登录信息异常');
                return false;
            }
            $_POST = array_merge($_POST,$decrypted);
            utils::_filter_input($_POST);
        }
        if($auth->is_enable_vcode())
        {
               $key = $auth->appid;  
            if(!base_vcode::verify($key,intval($_POST['verifycode'])))
            {
                $usrdata['log_data'] = app::get('pam')->_('图形验证码不正确，请重新输入');
                $_SESSION['error'] = app::get('pam')->_('图形验证码不正确，请重新输入');
                return false;
            }
        }
        if(!$_POST['uname'] || ($_POST['password']!=='0' && !$_POST['password']))
        {
            $usrdata['log_data'] = app::get('pam')->_('验证失败！');
            $_SESSION['error'] = app::get('pam')->_('用户名或密码错误');
            kernel::single('pam_login_error_counter')->increment($auth->appid);
            return false;
        }
        // 先查询账户信息，获取 is_hash256 字段值
        $accountInfo = app::get('pam')->model('account')->getList('account_id,is_hash256',array(
            'login_name'=>$_POST['uname'],
            'account_type' => $auth->type,
            'disabled' => 'false',
        ),0,1);
        
        if(empty($accountInfo)){
            $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('登录验证失败！');
            $_SESSION['error'] = app::get('pam')->_('用户名或密码错误');
            $_SESSION['error_count'][$auth->appid] = $_SESSION['error_count'][$auth->appid]+1;
            return false;
        }
        
        // 根据 is_hash256 值选择加密方式（0=MD5，1=MD5+SHA256）
        $is_hash256 = isset($accountInfo[0]['is_hash256']) ? intval($accountInfo[0]['is_hash256']) : 1;
        $encrypted_password = pam_encrypt::get_encrypted_password($_POST['password'], $auth->type, $is_hash256);
        
        // 使用加密后的密码查询账户
        $rows = app::get('pam')->model('account')->getList('*',array(
            'login_name'=>$_POST['uname'],
            'login_password'=>$encrypted_password,
            'account_type' => $auth->type,
            'disabled' => 'false',
        ),0,1);
        
        // 如果验证失败，尝试用另一种加密方式验证（兼容数据不一致的情况）
        if(empty($rows)){
            $tryIsHash256 = ($is_hash256 == 1) ? 0 : 1; // 尝试另一种加密方式
            $tryEncryptedPassword = pam_encrypt::get_encrypted_password($_POST['password'], $auth->type, $tryIsHash256);
            $rows = app::get('pam')->model('account')->getList('*',array(
                'login_name'=>$_POST['uname'],
                'login_password'=>$tryEncryptedPassword,
                'account_type' => $auth->type,
                'disabled' => 'false',
            ),0,1);
            
            // 如果另一种方式验证成功，更新 is_hash256 字段（修复数据不一致）
            if(!empty($rows)){
                app::get('pam')->model('account')->update(
                    array('is_hash256' => (string)$tryIsHash256),
                    array('account_id' => $rows[0]['account_id'])
                );
            }
        }   
        if(kernel::single('pam_account')->isFreezeAccount($_POST['uname'], $rows[0])) {
            $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('账户被冻结，10分钟后再登录!');
            $_SESSION['error'] = app::get('pam')->_('用户名或密码错误');
            kernel::single('pam_login_error_counter')->increment($auth->appid);
            return false;
        }
        if($rows[0])
        {
            if($rows[0]['is_lock'] == '1') {
                $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('账户被锁定!');
                $_SESSION['error'] = app::get('pam')->_('用户名或密码错误');
                kernel::single('pam_login_error_counter')->increment($auth->appid);
                return false;
            }
            if($_POST['needmobileverifycode']) {
                if($_POST['needmobileverifycode'] == '2') {
                    $user = app::get('desktop')->model('users')->dump(array('user_id'=>$rows[0]['account_id']), 'mobile');
                    $errorMsg = '';
                    if(kernel::single('base_session')->getMobileVerifyCode($user['mobile'], $errorMsg)) {
                        $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('手机验证码已发送');
                        $_SESSION['error'] = app::get('pam')->_('手机验证码已发送');
                    } else {
                        $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('手机验证码发送失败：'.$errorMsg);
                        $_SESSION['error'] = app::get('pam')->_('手机验证码发送失败：'.$errorMsg);
                    }
                    $_SESSION['uname'] = $_POST['uname'];
                    $_SESSION['password'] = $_POST['password'];
                    return false;
                }
                if(!kernel::single('base_session')->checkMobileVerifyCode()) {
                    $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('手机验证码输入错误');
                    $_SESSION['error'] = app::get('pam')->_('手机验证码输入错误');
                    $_SESSION['uname'] = $_POST['uname'];
                    $_SESSION['password'] = $_POST['password'];
                    return false;
                }
            }

            // IP白名单
            $configIPdata = app::get('desktop')->getConf('ip_setting_white_list');
            if ($configIPdata['ip_addr'] && kernel::single('desktop_ip')->limit($configIPdata)) {
                $ip = kernel::single('desktop_ip')->seg();
                $ip = implode(' | ', $ip);
                $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('登录IP受限！ - '.$ip);
                $_SESSION['error'] = app::get('pam')->_('登录IP受限 - ' . $ip);
                kernel::single('pam_login_error_counter')->increment($auth->appid);
                return false;
            }
            if ($_POST['remember'] === "true") {
                setcookie('pam_passport_basic_uname', $_POST['uname'], time() + 365 * 24 * 3600, '/', kernel::domain_url());
            } else {
                setcookie('pam_passport_basic_uname', '', 0, '/', kernel::domain_url());
            }
            $usrdata['log_data'] = app::get('pam')->_('用户') . $_POST['uname'] . app::get('pam')->_('验证成功！');
            // Note: error_counter reset is done by caller after risk validation passes
            return $rows[0]['account_id'];
        }
        else
        {
            $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('验证失败！');
            $_SESSION['error'] = app::get('pam')->_('用户名或密码错误');
            kernel::single('pam_login_error_counter')->increment($auth->appid);
            return false;
        }
    }
    
    function loginout($auth,$backurl="index.php"){
        unset($_SESSION['account'][$auth->type]);
        unset($_SESSION['last_error']);
        #Header('Location: '.$backurl);
    }

    function get_data(){
    }

    function get_id(){
    }

    function get_expired(){
    }
    
    
    function get_config(){
        $ret = app::get('pam')->getConf('passport.'.__CLASS__);
        if($ret && isset($ret['shopadmin_passport_status']['value']) && isset($ret['site_passport_status']['value'])){
            return $ret;
        }else{
            $ret = $this->get_setting();
            $ret['passport_id']['value'] = __CLASS__;
            $ret['passport_name']['value'] = $this->get_name();
            $ret['shopadmin_passport_status']['value'] = 'true';
            $ret['site_passport_status']['value'] = 'true';
            $ret['passport_version']['value'] = '1.5';
            app::get('pam')->setConf('passport.'.__CLASS__,$ret);
            return $ret;        
        }
    }
    
    function set_config(&$config){
        $save = app::get('pam')->getConf('passport.'.__CLASS__);
        if(count($config))
            foreach($config as $key=>$value){
                if(!in_array($key,array_keys($save))) continue;
                $save[$key]['value'] = $value;
            }
            $save['shopadmin_passport_status']['value'] = 'true';
            
        return app::get('pam')->setConf('passport.'.__CLASS__,$save);
         
    }

    function get_setting(){
        return array(
            'passport_id'=>array('label'=>app::get('pam')->_('通行证id'),'type'=>'text','editable'=>false),
            'passport_name'=>array('label'=>app::get('pam')->_('通行证'),'type'=>'text','editable'=>false),
            'shopadmin_passport_status'=>array('label'=>app::get('pam')->_('后台开启'),'type'=>'bool','editable'=>false),
            'site_passport_status'=>array('label'=>app::get('pam')->_('前台开启'),'type'=>'bool'),
            'passport_version'=>array('label'=>app::get('pam')->_('版本'),'type'=>'text','editable'=>false),
        );
    }
    
    


}
