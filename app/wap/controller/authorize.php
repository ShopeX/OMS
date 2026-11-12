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
 * 第三方免登
 */
 
class wap_ctl_authorize extends base_controller{

    private $_token    = 'penkr2oms2017';
    private $_source   = 'shopex_penkr';
    public function __construct($app)
    {
        header("Cache-Control:no-store, no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");// 强制查询etag
        header('Progma: no-cache');
        $this->defaultwg = $this->defaultWorkground;
        
        parent::__construct($app);
        
        kernel::single('base_session')->start();
    }
    
    /**
     * 绑定
     */
    function bind()
    {
        $uname        = strip_tags(trim($_POST['uname']));
        $password     = $_POST['password'];//md5的密码字符串
        $source       = $this->_source;//来源
        $sign         = $_POST['sign'];//签名
        
        //check
        $rsp    = array('rsp'=>'fail', 'error_msg'=>'');
        if(empty($uname) || empty($password))
        {
            $rsp['error_msg']    = '用户名和密码不能为空';
            echo json_encode($rsp);
            exit;
        }
        
        //签名验证
        $params         = array('uname'=>$uname, 'password'=>$password, 'source'=>$source);
        $params['sign'] = $this->gen_sign($params);
        if($sign != $params['sign'])
        {
            $rsp['error_msg']    = '签名验证失败';
            echo json_encode($rsp);
            exit;
        }
        
        $authObj      = app::get('wap')->model('authorize');
        $accountObj   = app::get('pam')->model('account');
        
        $auth_type    = pam_account::get_account_type('desktop');
        $app_id       = $this->app->app_id;
        
        //绑定验证
        $rows    = $accountObj->getList('*', array('login_name'=>$uname, 'account_type'=>$auth_type, 'disabled'=>'false'), 0, 1);
        $rows    = $rows[0];
        if(empty($rows))
        {
            $rsp['error_msg']    = '用户名：'. $uname .' 不存在';
            echo json_encode($rsp);
            exit;
        }
        
        if($rows['login_password'] != $password)
        {
            $rsp['error_msg']    = '用户名：'. $uname .' 密码错误';
            echo json_encode($rsp);
            exit;
        }
        
        /**
         * 查询免登用户是否存在
         *
        $authInfo    = $authObj->dump(array('uname'=>$uname), '*');
        if($authInfo)
        {
            $rsp['error_msg']    = '用户名：'. $uname .' 已经绑定过，不能重复绑定';
            echo json_encode($rsp);
            exit;
        }
        ***/
        
        //生成32位code(如果绑定关系已存在，则生成最新的code更新)
        $dateline    = time();
        
        $params   = array(
                'account_type'=>$auth_type,
                'app_id'=>$app_id,
                'account_id'=>$rows['account_id'],
                'uname'=>$uname,
                'password'=>$password,
                'source'=>$source,
                'bind_time'=>$dateline,
        );
        
        $code    = $this->gen_sign($params);
        
        //保存
        $params['code']         = $code;
        if($authObj->save($params))
        {
            $rsp    = array('rsp'=>'succ', 'code'=>$code, 'bind_time'=>$dateline);
            echo json_encode($rsp);
            exit;
        }
        
        $rsp['error_msg']    = '用户名：'. $uname .' 绑定失败';
        echo json_encode($rsp);
        exit;
    }
    
    /**
     * 登录
     */
    function login()
    {
        $uname            = strip_tags(trim($_GET['uname']));
        $code             = $_GET['code'];
        $source           = $this->_source;//来源
        $sign             = $_GET['sign'];//签名
        $timestamp        = abs(time() - intval($_GET['timestamp']));//时间戳
        
        //check
        $rsp    = array('rsp'=>'fail', 'error_msg'=>'');
        if(empty($uname) || empty($code))
        {
            $this->pagedata['error_msg']    = '用户名和code不能为空';
            $this->display('error_login.html');
            exit;
        }
        
        //签名验证
        $params         = array('uname'=>$uname, 'code'=>$code, 'source'=>$source, 'timestamp'=>$_GET['timestamp']);
        $params['sign'] = $this->gen_sign($params);
        if($sign != $params['sign'])
        {
            $this->pagedata['error_msg']    = '签名验证失败';
            $this->display('error_login.html');
            exit;
        }
        
        //时效验证
        if($timestamp > (12*3600))
        {
            $this->pagedata['error_msg']    = '链接超时';
            $this->display('error_login.html');
            exit;
        }
        
        $authObj      = app::get('wap')->model('authorize');
        $accountObj   = app::get('pam')->model('account');
        
        $auth_type    = pam_account::get_account_type('desktop');
        $app_id       = $this->app->app_id;
        
        //验证操作员
        $rows    = $accountObj->getList('*', array('login_name'=>$uname, 'account_type'=>$auth_type, 'disabled'=>'false'), 0, 1);
        $rows    = $rows[0];
        if(empty($rows))
        {
            $rsp['error_msg']    = '用户名：'. $uname .' 不存在';
            
            //登录失败计数
            $_SESSION['error'] = $rsp['error_msg'];
            $_SESSION['error_count'][$app_id] = $_SESSION['error_count'][$app_id] + 1;
            
            $this->pagedata['error_msg']    = $rsp['error_msg'];
            $this->display('error_login.html');
            exit;
        }
        
        //查询免登用户是否存在
        $authInfo    = $authObj->dump(array('uname'=>$uname, 'code'=>$code), '*');
        if(empty($authInfo))
        {
            $rsp['error_msg']    = '用户名或code验证失败';
            
            //登录失败计数
            $_SESSION['error'] = $rsp['error_msg'];
            $_SESSION['error_count'][$app_id] = $_SESSION['error_count'][$app_id] + 1;
            
            $this->pagedata['error_msg']    = $rsp['error_msg'];
            $this->display('error_login.html');
            exit;
        }
        
        //登录
        $account_id    = $rows['account_id'];

        $params  = array('module'=>'pam_passport_wap', 'type'=>$auth_type, 'appid'=>$app_id);
        $auth    = pam_auth::instance($params['type']);
        $auth->set_appid($params['appid']);
        
        
        $passport_module = kernel::single($params['module']);
        //$module_uid    = $passport_module->login($auth, $auth_data);
        
        $auth_data    = array('log_data'=>'用户'. $uname .'验证成功！', 'account_type'=>$auth_type);
        $auth->account()->update($params['module'], $account_id, $auth_data);
        
        //登录成功标识(执行task任务)
        $_SESSION['login_flag']    = 1;
        //$_SESSION['account'][$auth_type]   = $account_id;
        
        //设置免登标识
        setcookie('relogin', 1, time()+30*24*3600, '/');

        // Issue HttpOnly JWT cookie for WAP authorize (免登)
        $loginName = $uname;
        $userModel = app::get('desktop')->model('users');
        $userRow = $userModel->getList('super', ['user_id' => $account_id], 0, 1);
        $isSuper = ($userRow[0]['super'] ?? 0) == 'true' || ($userRow[0]['super'] ?? 0) == 1;
        $profile = ['is_super' => $isSuper];
        $tokens = desktop_auth_jwt_flow::issueTokensForUser($account_id, $loginName, $profile, true);
        desktop_auth_jwt_transport::setWapAuthCookies($tokens);
        
        //跳转到工作台
        $_GOTO    = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'index'), true);
        
        echo "<script>location ='$_GOTO'</script>";
        exit;
    }
    
    /**
     *
     * 生成签名算法函数
     * @param array $params
     */
    private function gen_sign($params)
    {
        return strtoupper(md5(strtoupper(md5($this->assemble($params))) . $this->_token));
    }
    
    /**
     *
     * 签名参数组合函数
     * @param array $params
     */
    private function assemble($params)
    {
        if(!is_array($params))  return null;
        
        ksort($params, SORT_STRING);
        
        $sign = '';
        foreach($params AS $key => $val)
        {
            if(is_null($val)) continue;
            
            if(is_bool($val)) $val = ($val) ? 1 : 0;
            
            $sign .= $key . (is_array($val) ? $this->assemble($val) : $val);
        }
        
        return $sign;
    }
}