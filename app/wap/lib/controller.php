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

class wap_controller extends base_controller{

    var $defaultwg;
    var $user_menu    = array();
    var $delivery_link    = array();
    
    function __construct($app){
        header("Cache-Control:no-store, no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");// 强制查询etag
        header('Progma: no-cache');
        $this->defaultwg = $this->defaultWorkground;
        // base_session start 放在父类之前，供 2FA appointStore 等场景使用
        kernel::single('base_session')->start();

        $_GOTO = app::get('wap')->router()->gen_url(array('ctl'=>'passport','act'=>'index'), true);
        $isPassport = get_class($this) === 'wap_ctl_passport';

        if (!$isPassport) {
            $wapToken = $_COOKIE['wap-token-key'] ?? null;
            if (!$wapToken) {
                $this->redirectToLogin($_GOTO);
            }

            try {
                $claims = desktop_auth_jwt::decodeWithValidation($wapToken, true);
                $userId = (int)($claims['user_id'] ?? 0);
                if ($userId <= 0) {
                    throw new desktop_auth_exception('Invalid user ID in token');
                }
                // User existence + status 兜底（decodeWithValidation 只验 JWT 签名和 exp，不查库）
                $userModel = app::get('desktop')->model('users');
                $user = $userModel->dump(
                    ['user_id' => $userId],
                    'user_id,status',
                    [':account@pam' => ['login_name']]
                );
                if (!$user || ($user['status'] ?? '0') !== '1' || empty($user['account']['login_name'])) {
                    $this->redirectToLogin($_GOTO);
                }
                // 写 desktop_auth_context（内存态，不写 SESSION）
                desktop_auth_context::setUserId($userId);
            } catch (desktop_auth_exception $e) {
                if ($e->getMessage() === 'Token has expired' && $this->refreshWapJwtSession()) {
                    // refreshWapJwtSession 内部已通过 desktop_auth_context::setUserId 写入
                } else {
                    $this->redirectToLogin($_GOTO);
                }
            }
        }

        // desktop_user 必须在 JWT context 写入之后才初始化
        parent::__construct($app);
        $this->user = kernel::single('desktop_user');
        if(!$isPassport){
            $this->status = $this->user->get_status();
            if(!$this->status&&$this->status==0){
                $this->pagedata['link_url'] = $_GOTO;
                $this->pagedata['error_msg'] = '管理员未启用！';
                echo $this->fetch('auth_error.html');
                exit;
            }

            ###如果不是超级管理员就查询操作权限
            if(!$this->user->is_super()){
                
                $pam_group    = $this->user->group();
                foreach ($pam_group as $key => $val)
                {
                    if(strpos($val, 'wap_') !== false)
                    {
                        $this->user_menu[]    = $val;//操作员拥有权限的导航栏目
                    }
                }
                
                if(empty($this->user_menu)){
                    $this->pagedata['link_url'] = $_GOTO;
                    $this->pagedata['error_msg'] = '管理员没有门店权限！';
                    echo $this->fetch('auth_error.html');
                    exit;
                }
                
                //当前访问的URL地址(只取 ? 前面的内容)
                $request_uri    = kernel::single('base_component_request')->get_request_uri();
                if (strpos($request_uri, '?') !== false)
                {
                    $temp_url    = explode('?', $request_uri);
                    $request_uri = reset($temp_url);
                }
                $url_param    = explode('/wap/', $request_uri);
                $url_param    = explode('/', $url_param[1]);
                $url_param    = array('app'=>'wap', 'ctl'=>'admin_'. $url_param[0], 'act'=>$url_param[1]);
                
                //操作员权限
                $menus         = app::get('desktop')->model('menus');
                $permission_id = $menus->permissionId($url_param);
                if($permission_id != '0')
                {
                    if(!in_array($permission_id, $this->user_menu)){
                        $this->pagedata['link_url'] = $_GOTO;
                        $this->pagedata['error_msg'] = '您无栏目操作权限';
                        echo $this->fetch('auth_error.html');
                        exit;
                    }
                }
                
                //已登录并且直接访问了index
                $strpos    = strpos(strtolower($url_param['ctl']), 'admin_index');
                if($url_param['ctl'] == 'admin_' || $strpos !== false)
                {
                    $_GOTO    = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'index'), true);
                    echo "<script>location ='$_GOTO'</script>";
                    exit;
                }
            }else{
                $this->pagedata['link_url'] = $_GOTO;
                $this->pagedata['error_msg'] = '主账号不能登录门店系统！';
                echo $this->fetch('auth_error.html');
                exit;
            }
        }
        
        //全局Url连接
        $this->delivery_link['desktop']    = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'index'), true);
        $this->delivery_link['mine']       = app::get('wap')->router()->gen_url(array('ctl'=>'user','act'=>'mine'), true);
        
        $this->delivery_link['order_index']      = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'index'), true);
        $this->delivery_link['order_wait_delivery'] = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'index','view'=>'wait_delivery'), true);
        $this->delivery_link['order_confirm']    = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'confirm'), true);
        $this->delivery_link['order_consign']    = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'consign'), true);
        $this->delivery_link['order_sign']       = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'sign'), true);
        $this->delivery_link['overtimeOrders']   = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'overtimeOrders'), true);
        $this->delivery_link['aftersale_returnproduct']   = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'pending'), true);
        $this->delivery_link['aftersale_changeproduct']   = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_changeproduct','act'=>'pending'), true);
        $this->delivery_link['store_selfpick'] = app::get('wap')->router()->gen_url(array('ctl'=>'store_selfpick','act'=>'index'), true);
        
        $this->delivery_link['setting']        = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'setting'), true);
        
        //免登去除头部
        $this->pagedata['show_header']    = ($_COOKIE['relogin'] == '1' ? false : true);
    }

     /* 返回json成功信息
     * @param $msg
     * @param $data
     * @param $rsp
     * @return void
     */
    protected function success($msg = '', $data = [])
    {
        $result = [
            'rsp' => 'succ',
            'msg' => $msg,
            'data' => $data
        ];
        exit(json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    protected function redirectToLogin(string $_GOTO): void
    {
        desktop_auth_jwt_transport::clearWapAuthCookies();
        setcookie('relogin', '', time() - 3600, '/');
        echo "<script>location ='$_GOTO'</script>";
        exit;
    }

    protected function refreshWapJwtSession(): bool
    {
        $refreshToken = desktop_auth_jwt::extractWapRefreshToken();
        if ($refreshToken === null || $refreshToken === '') {
            return false;
        }

        try {
            try {
                $payload = desktop_auth_jwt::consumeRefreshToken($refreshToken);
            } catch (desktop_auth_exception $e) {
                desktop_auth_jwt::revokeSessionOnRefreshReuse($refreshToken, $e);
                throw $e;
            }

            $userId = (int)($payload['sub'] ?? 0);
            if ($userId <= 0) {
                return false;
            }

            $userModel = app::get('desktop')->model('users');
            $user = $userModel->dump(
                ['user_id' => $userId],
                'user_id,name,super,status',
                [':account@pam' => ['login_name']]
            );
            if (!$user || ($user['status'] ?? '0') !== '1' || empty($user['account']['login_name'])) {
                return false;
            }

            $loginName = (string)$user['account']['login_name'];
            $profile = [
                'is_super' => (bool)($user['super'] ?? false),
                'login_time' => desktop_auth_jwt::preservedLoginTimeFromClaims($payload),
                // SCRUM-7917 Plan B: rotate within the SAME session family.
                'fid' => (string)($payload['fid'] ?? ''),
            ];
            $refreshExpiresAt = (int)($payload['exp'] ?? 0);
            $tokens = desktop_auth_jwt_flow::issueTokensForUser(
                $userId,
                $loginName,
                $profile,
                false,
                $refreshExpiresAt > 0 ? $refreshExpiresAt : null
            );
            desktop_auth_jwt_transport::setWapAuthCookies($tokens);
            desktop_auth_context::setUserId($userId);
            desktop_auth_context::setLoginName($loginName);
            return true;
        } catch (desktop_auth_exception $e) {
            return false;
        }
    }

    /**
     * 返回json失败信息
     * @param $msg
     * @return void
     */
    protected function error($msg = '')
    {
        $result = [
            'rsp' => 'fail',
            'msg' => $msg
        ];
        exit(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
}
