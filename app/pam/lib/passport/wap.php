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


class pam_passport_wap implements pam_interface_passport{

    function __construct(){
        kernel::single('base_session')->start();
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
    
    function login($auth,&$usrdata)
    {
        $_POST['uname'] = strip_tags($_POST['uname']);

        if($auth->is_enable_vcode())
        {
            $key = $auth->appid;
            if(!base_vcode::verify($key,intval($_POST['verifycode'])))
            {
                $usrdata['log_data'] = app::get('pam')->_('验证码不正确！');
                $_SESSION['error'] = app::get('pam')->_('验证码不正确！');
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
            $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('验证失败！');
            $_SESSION['error'] = app::get('pam')->_('用户名或密码错误');
            $_SESSION['error_count'][$auth->appid] = $_SESSION['error_count'][$auth->appid]+1;
            return false;
        }
        
        // 根据 is_hash256 值选择加密方式（0=MD5，1=MD5+SHA256）
        // 处理 is_hash256 字段：NULL、空字符串、'0' 都视为 0（旧加密方式），其他值视为 1（新加密方式）
        $is_hash256 = 1; // 默认使用新加密方式
        if(isset($accountInfo[0]['is_hash256'])){
            $hashValue = $accountInfo[0]['is_hash256'];
            if($hashValue === '0' || $hashValue === 0 || $hashValue === null || $hashValue === ''){
                $is_hash256 = 0; // 旧加密方式
            } else {
                $is_hash256 = 1; // 新加密方式
            }
        }
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
        
        if($rows[0])
        {
            if($_POST['remember'] === "true") setcookie('pam_passport_basic_uname',$_POST['uname'],time()+365*24*3600,'/');
            else setcookie('pam_passport_basic_uname','',0,'/');
            
            $usrdata['log_data'] = app::get('pam')->_('用户').$_POST['uname'].app::get('pam')->_('验证成功！');
            kernel::single('pam_login_error_counter')->reset($auth->appid);
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
