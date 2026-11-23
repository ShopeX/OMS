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

class desktop_ctl_passport extends desktop_controller
{
    public $checkCSRF         = false;

    public $login_times_error = 3;

    public function __construct($app)
    {
        parent::__construct($app);
        header("cache-control: no-store, no-cache, must-revalidate");
    }

    public function index()
    {
        $params = json_decode(urldecode($_GET['params']), true);

        // begin 转换分销王登录参数
        if ($params && $params['login_from'] == 'b2b') {
            $params['params']      = str_replace(array('_', ',', '~'), array('+', '/', '='), $params['params']);
            $params['saas_params'] = $params['params'];
            $params['saas_ts']     = $params['ts'];
            $params['saas_appkey'] = $params['appkey'];
            $params['saas_sign']   = $params['sign'];
        }
        // end

        if ($params['saas_params'] && $params['saas_appkey'] && $params['saas_ts'] && $params['saas_sign']) {
            $params['type'] = pam_account::get_account_type($this->app->app_id);
            foreach (kernel::servicelist('login_trust') as $service) {
                if ($service->login($params)) {
                    //$this->redirect('index.php');
                    echo '<script>location="'.kernel::get_host_url().'";</script>';
                    exit();
                }
            }
        }

        // 登录之前的预先验证
        $obj_services = kernel::servicelist('app_pre_auth_use');
        foreach ($obj_services as $obj) {
            if (method_exists($obj, 'pre_auth_uses') && method_exists($obj, 'login_verify')) {
                if (!$obj->pre_auth_uses()) {
                    $this->pagedata['desktop_login_verify'] = $obj->login_verify();
                }
            }
        }
        // end

        //检查证书是否合法,从而判定产品功能是否可用。比如b2c功能
        $certCheckObj = kernel::service("product_soft_certcheck");
        if (is_object($certCheckObj) && method_exists($certCheckObj, "check")) {
            $certCheckObj->check();
        }

        $auth = pam_auth::instance(pam_account::get_account_type($this->app->app_id));
        $auth->set_appid($this->app->app_id);
        $auth->set_redirect_url($_GET['url']);
        $this->pagedata['desktop_url']    = kernel::router()->app->base_url(1);
        $this->pagedata['login_iv'] = OPENSSL_CPIHER_IV;
        $this->pagedata['cross_call_url'] = base64_encode(kernel::router()->app->base_url(1) .
            'index.php?ctl=passport&act=cross_call'
        );
        if ('false' != app::get('ome')->getConf('desktop.account.mobile.verify')
            && app::get('taoexlib')->model('sms_sample')->db_dump(array('send_type' => 'login', 'status' => '1'))) {
            $this->pagedata['mobile_check'] = 1;
        }

        $piplProtocol = '';
        $file = fopen("pipl.protocol", "r");
        while(!feof($file)) {
          $piplProtocol .= '<p>'.fgets($file).'</p>';
        }
        fclose($file);
        $pagedata['piplProtocol'] = $piplProtocol;

        // 登陆界面
        foreach (kernel::servicelist('passport') as $k => $passport) {
            if ($auth->is_module_valid($k, $this->app->app_id)) {
                // 信任登陆
                if ($passport->loginType == 'SSO'){
                    $this->pagedata['passports_sso'][] = array(
                        'name'  => $auth->get_name($k) ? $auth->get_name($k) : $passport->get_name(),
                        'html'  => $passport->get_login_form($auth, 'desktop', '', $pagedata),
                        'value' => substr($k,strrpos($k,'_')+1),
                    );
                    continue;
                }
        
                $this->pagedata['passports'][] = array(
                    'name'  => $auth->get_name($k) ? $auth->get_name($k) : $passport->get_name(),
                    'html'  => $passport->get_login_form($auth, 'desktop', 'basic-login.html', $pagedata),
                    'value' => substr($k,strrpos($k,'_')+1),
                );

                //安装门店相关app后 增加门店登录的tab
                if (app::get('o2o')->is_installed() && method_exists($passport, 'get_o2o_name')) {
                    $this->pagedata['passports'][] = array(
                        'name'  => $passport->get_o2o_name(),
                        'html'  => $passport->get_login_o2o_store('desktop', 'o2o-login.html'),
                        'value' => 'o2o',
                    );
                }
            }
        }

        // 如果是分销王，直接转到分销王登录页面
        // $server_name = $_SERVER['SERVER_NAME'];
        // if (stristr($server_name, 'tg.test.taoex.com') || stristr($server_name, B2B_TG_URL)) {
        //     $msg = array('msg' => '请登录系统', 'url' => trim($server_name));
        //     $msg = json_encode($msg);
        //     $msg = base64_encode($msg);
        //     header("location: " . B2B_API_URL . "?act=logout&msg=" . $msg);
        //     exit;
        // }
        // //如果是旺旺精灵，转到改造后的页面
        // if ($params['suitelogin'] == 'mini') {
        //     $this->pagedata['passports']   = null;
        //     $this->pagedata['passports'][] = array(
        //         'name' => $auth->get_name($k) ? $auth->get_name($k) : $passport->get_name(),
        //         'html' => $passport->get_login_form($auth, 'desktop', 'wwgenius-basic-login.html', $pagedata),
        //     );
        //     $this->pagedata['product_key'] = $conf['product_key'];
        //     $this->display('wwgenius-login.html');
        //     exit;
        // }

        $conf = base_setup_config::deploy_info();
        $this->pagedata['product_key'] = $conf['product_key'];
        
        
        $siteInfo = kernel::single('desktop_site')->getInfo();
        $this->pagedata['siteInfo'] = $siteInfo;
        
        $this->display('login.html');
    }

    public function gen_vcode()
    {
        $vcode = kernel::single('base_vcode');
        $vcode->length(4);
        $vcode->verify_key($this->app->app_id);
        $vcode->display();
    }

    public function cross_call()
    {
        header('Content-Type: text/html;charset=utf-8');
        echo '<script>' . base64_decode($_REQUEST['script']) . '</script>';
    }

    public function logout($backurl = 'index.php')
    {

        $opinfo = kernel::single('ome_func')->getDesktopUser();
        $log    = [
            'event_time'    =>  time(),
            'event_type'    =>  pam_account::get_account_type('desktop'),
            'event_data'    =>  base_request::get_remote_addr() . ':' . app::get('pam')->_('用户') . $opinfo['op_name'] . '退出登录',
        ];
        app::get('pam')->model('log')->insert($log);

        // begin 分销王退出
        if (defined('B2B_TG_URL') && stristr(trim($_SERVER['SERVER_NAME']), B2B_TG_URL)) {
            $msg     = array('msg' => '退出系统成功', 'url' => trim($_SERVER['SERVER_NAME']));
            $msg     = json_encode($msg);
            $msg     = base64_encode($msg);
            $backurl = (B2B_API_URL . "?act=logout&msg=" . $msg);
            $this->begin('javascript:Cookie.dispose("basicloginform_password");Cookie.dispose("basicloginform_autologin");
            top.location="' . $backurl . '"');
        } else {
            $this->begin('javascript:Cookie.dispose("basicloginform_password");Cookie.dispose("basicloginform_autologin");
            top.location="' . kernel::router()->app->base_url(1) . '"');
        }
        // end

        $this->user->login();
        $this->user->logout();
        $auth = pam_auth::instance(pam_account::get_account_type($this->app->app_id));
        foreach (kernel::servicelist('passport') as $k => $passport) {
            if ($auth->is_module_valid($k, $this->app->app_id)) {
                $passport->loginout($auth, $backurl);
            }

        }
        kernel::single('base_session')->destory();

        // $_GOTO  = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/index.php?ctl=passport';
        
        $_GOTO = kernel::router()->gen_url(['ctl' => 'passport'], 1);
        echo "<script>location ='$_GOTO'</script>";exit;

        /* $this->redirect('');*/

    }
    #套件的oauth认证
    public function login_verify()
    {
        $toUrl = app::get('desktop')->base_url(true);
        #避免二次登陆请求
        if ($_SESSION['account']) {
            if (!empty($_GET['sess_id'])) {
                $rs = kernel::single('base_hchsafe')->isVerifyPassed($_GET);
                if ($rs['rsp'] != 'succ') {
                    $_SESSION = array(
                        'type'       => 'shopadmin',
                        'last_error' => $rs['msg'],
                    );
                    $toUrl .= 'index.php?ctl=passport&act=index';
                }
            }
        }
        header('Location:' . $toUrl);
        exit();
    }
    
    /**
     * 二次认证页面, 已经弃用
     *
     * @return void
     * @author
     **/
    public function verifySecondFactor()
    {
        die('此方法已弃用');
        $sess_id     = kernel::single('base_session')->sess_id();
        
        $fid              = $_POST['fid'];
        $username         = $_POST['username'];
        $mobileverifycode = $_POST['mobileverifycode'];
        $phoneNumber      = $_POST['phoneNumber'];
        
        $s = kernel::single('base_session')->appointFetch($fid);
        
        if ($s && $mobileverifycode) {
            $res = kernel::single('erpapi_router_request')->set('idaas', 'aliyun')->account_checkMobileVerifyCode(array(
                'login_name'       => $username,
                'mobileverifycode' => $mobileverifycode,
                'fid'              => $fid,
            ));
            
            if ($res['rsp'] == 'succ') {
                $_GET['sess_id'] = $sess_id;
                kernel::single('base_session')->start();
                $_SESSION = $s;
                
                header('Location:' . kernel::get_host_url());exit;
            }
            
            if ($res['rsp'] != 'succ') {
                $errorMsg = $res['err_msg'].'，<a class="c-blue lnk" href="'.kernel::base_url(1).'">重新登陆</a>';
            }
        } else {
            $errorMsg = '无效验证码'.'，<a class="c-blue lnk" href="'.kernel::base_url(1).'">重新登陆</a>';
        }
        
        $this->pagedata['fid']         = $fid;
        $this->pagedata['username']    = $username;
        $this->pagedata['phoneNumber'] = $phoneNumber;
        $this->pagedata['sess_id']     = $sess_id;
        $this->pagedata['err_msg']     = $errorMsg;
        $this->display('login_second_factor.html');
    }
}
