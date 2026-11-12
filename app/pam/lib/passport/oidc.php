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

class pam_passport_oidc implements pam_interface_passport
{
    /**
     * 任务登陆标识
     *
     * @var string
     **/
    public $loginType = 'SSO';

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function __construct()
    {
        
    }

    public function get_name()
    {
        return 'Account Sign In';
    }

    public function get_login_form($auth, $appid, $view, $ext_pagedata = array())
    {
        $oidcInfo = app::get('ome')->getConf('pam.passport.oidc.info');
        $oidctype = $oidcInfo['oidctype'] ? strtoupper($oidcInfo['oidctype']) : '第三方';

        $str = '';
        $rs = kernel::single('erpapi_router_request')->set('account','account')->user_loginUrl([]);
        if($rs['rsp'] == 'succ') {
            $str = '<a href="'.$rs['data'].'">'.$oidctype.'信任登陆</a>';
        }
        return $str;
    }

    public function login($auth, &$usrdata)
    {

    }

    public function callback()
    {
        $state = (string)($_GET['state'] ?? '');
        $loginUrl = desktop_auth_oidc::vueLoginUrl();

        if (empty($_GET['code'])) {
            header('Location: ' . $loginUrl);
            exit;
        }

        if (!desktop_auth_oidc::validateState($state)) {
            error_log('OIDC state validation failed');
            header('Location: ' . desktop_auth_oidc::vueLoginUrl(['oidc_error' => 'state']));
            exit;
        }

        $rs = kernel::single('erpapi_router_request')->set('account', 'account')->user_login([
            'code' => $_GET['code'],
            'oidc_state' => $state,
        ]);

        if (($rs['rsp'] ?? '') === 'succ') {
            $userId = (int)($rs['user_id'] ?? 0);
            if ($userId > 0) {
                pam_trust_grant::issue($userId, 'oidc');
                header('Location: ' . desktop_auth_oidc::vueLoginUrl(['oidc' => '1']));
                exit;
            }
        }

        $message = trim((string)($rs['msg'] ?? ($rs['err_msg'] ?? 'OIDC 登录失败')));
        if ($state !== '') {
            desktop_auth_oidc::discardChallenge($state);
        }
        header('Location: ' . desktop_auth_oidc::vueLoginUrl([
            'oidc_error' => 'login',
            'message' => $message,
        ]));
        exit;
    }

    public function loginout($auth, $backurl = "index.php")
    {
        $userId = (int)($_SESSION['account'][$auth->type] ?? 0);
        if ($userId <= 0) {
            $userId = desktop_auth_context::userId();
        }
        unset($_SESSION['account'][$auth->type]);
        unset($_SESSION['last_error']);
        $rs = kernel::single('erpapi_router_request')->set('account','account')->user_loginOutUrl([
            'user_id' => $userId,
        ]);
        if(($rs['rsp'] ?? '') == 'succ' && !empty($rs['data'])) {
            desktop_auth_oidc::clearIdToken($userId);
            kernel::single('base_session')->destory();

            die('<script>location="'.$rs['data'].'";</script>');
        }
    }

    public function get_data()
    {
    }

    public function get_id()
    {
    }

    public function get_expired()
    {
    }

    public function get_config()
    {
        $oidc = app::get('ome')->getConf('pam.passport.oidc.enable');
        if($oidc != 'true') {
            return [];
        }
        $ret = [];
        $ret['shopadmin_passport_status']['value'] = 'true';
        return $ret;
    }

}
