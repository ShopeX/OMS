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


class wap_ctl_passport extends wap_controller{

    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct($app)
    {
        parent::__construct($app);
        header("cache-control: no-store, no-cache, must-revalidate");
    }
    
    function index(){
        //验证码URL
        $base_url    = kernel::router()->app->base_url(1);
        $desktop_url = explode('index.php', $base_url);
        
        $this->pagedata['desktop_url']  = $desktop_url[0];
        $this->pagedata['redirect_url'] = app::get('wap')->router()->gen_url(array('ctl'=>'passport','act'=>'ajaxLogin'), true);
        
        //验证码
        $auth_type    = pam_account::get_account_type('desktop');
        $app_id       = $this->app->app_id;
        
        $params       = array('module'=>'pam_passport_basic', 'type'=>$auth_type, 'appid'=>$app_id);
        $auth         = pam_auth::instance($params['type']);
        $auth->set_appid($params['appid']);
        
        if($auth->is_enable_vcode()){
            $this->pagedata['show_varycode'] = 'true';
            $this->pagedata['type'] = $auth->type;
        }
        
        // 添加 desktop 应用的资源URL，用于访问背景图片和logo
        $this->pagedata['desktop_res_url'] = app::get('desktop')->res_url;
        $this->pagedata['desktop_res_full_url'] = app::get('desktop')->res_full_url;
        
        // 获取站点信息（用于logo）
        $siteInfo = kernel::single('desktop_site')->getInfo();
        $this->pagedata['siteInfo'] = $siteInfo;
        
        $this->display('login.html');
    }
    
    function ajaxLogin()
    {
        $auth_type    = pam_account::get_account_type('desktop');
        $app_id       = $this->app->app_id;
        
        $uname    = strip_tags($_POST['uname']);
        $password = $_POST['password'];
        
        //登录验证
        $params    = array('module'=>'pam_passport_wap', 'type'=>$auth_type, 'appid'=>$app_id);
        $auth    = pam_auth::instance($params['type']);
        $auth->set_appid($params['appid']);
        
        $passport_module = kernel::single($params['module']);
        $module_uid    = $passport_module->login($auth, $auth_data);
        if($module_uid)
        {
            $auth_data['account_type']    = $params['type'];
            $auth->account()->update($params['module'], $module_uid, $auth_data);

            //自动登录cookie
            if($_COOKIE['autologin'] > 0){
                kernel::single('base_session')->set_cookie_expires($_COOKIE['autologin']);
                //如果自动登录，设置cookie过期时间，单位：分
            }

            //登录成功标识(执行task任务)
            $_SESSION['login_flag']    = 1;

            //清除免登标识
            setcookie('relogin', '', time()-3600, '/');

            // Issue HttpOnly JWT cookie for WAP (JWT-first auth)
            $accountModel = app::get('pam')->model('account');
            $accountRow = $accountModel->getList('*', ['account_id' => $module_uid], 0, 1);
            $accountRow = $accountRow[0] ?? [];
            $loginName = $accountRow['login_name'] ?? '';
            $userModel = app::get('desktop')->model('users');
            $userRow = $userModel->getList('super', ['user_id' => $module_uid], 0, 1);
            $isSuper = ($userRow[0]['super'] ?? 0) == 'true' || ($userRow[0]['super'] ?? 0) == 1;
            $profile = ['is_super' => $isSuper];
            $tokens = desktop_auth_jwt_flow::issueTokensForUser($module_uid, $loginName, $profile, true);
            desktop_auth_jwt_transport::setWapAuthCookies($tokens);

            $result    = array('success'=>true, 'message'=>'验证成功！');
            $result['redirect']    = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'index'), true);
            echo json_encode($result);
            exit;
        }
        else 
        {
            $result                = array('error'=>true, 'message'=>$_SESSION['error']);
            $result['redirect']    = app::get('wap')->router()->gen_url(array('ctl'=>'passport','act'=>'index'), true);
            echo json_encode($result);
            exit;
        }
    }
    
    function gen_vcode(){
        $vcode = kernel::single('base_vcode');
        $vcode->length(4);
        $vcode->verify_key($this->app->app_id);
        $vcode->display();
    }

    function logout($backurl='index.php'){
        $url = app::get('wap')->router()->gen_url(array('ctl'=>'passport','act'=>'index'), true);
        $this->begin($url);

        // Revoke WAP JWT: increment token version so the current cookie token becomes invalid.
        $wapToken = desktop_auth_jwt::extractWapAccessToken();
        if ($wapToken) {
            try {
                $payload = desktop_auth_jwt::decode($wapToken, false);
                $userId = (int)($payload['sub'] ?? 0);
                if ($userId > 0) {
                    desktop_auth_jwt::incrementTokenVersion($userId);
                }
            } catch (desktop_auth_exception $e) {
            }
        }
        desktop_auth_jwt_transport::clearWapAuthCookies();

        $this->user->login();
        $this->user->logout();
        $auth = pam_auth::instance(pam_account::get_account_type('desktop'));
        $passport = kernel::single('pam_passport_wap');
        if($auth->is_module_valid('pam_passport_wap','desktop')){
            $passport->loginout($auth,$backurl);
        }

        kernel::single('base_session')->destory();
        $this->end(true,'已成功退出系统,正在转向...');
    }
}