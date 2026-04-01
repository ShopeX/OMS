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

class ome_service_login {

    /**
     * 分销王登录回写地址
     * @param Array $msg
     */
    public function b2b_login_error($msg) {
        $server_name = trim($_SERVER['SERVER_NAME']);
        // 安全修复：检查B2B相关常量是否定义
        if (defined('B2B_TG_URL') && defined('B2B_API_URL') && stristr($server_name, B2B_TG_URL)) {
            $arr = array('msg'=>$msg,'url'=>$server_name);
            $arr = json_encode($arr);
            $arr = base64_encode($arr);
            header('location: '.B2B_API_URL.'?act=loginFail&msg='.$arr);
            exit;
        }
    }

    public function signErrorReturn($params) {

        if ($params['visitor_role'] == 'taobao') {

            header("location: http://fuwu.taobao.com/service/my_service.htm");
            exit;
        } else {

            return false;
        }
    }

    public function realLogin($params, $type) {

        // 如果是分销王登录
        if($params['login_from']=='b2b') {
            $account_id = $this->check_name($params['visitor_nick'],$params['visitor_pwd']);
            if (!$account_id) {
                $this->b2b_login_error('帐号或密码错误');
                return false;
            }
        }else{
            $account_id = $this->check_name($params['visitor_nick']);
            
            //用户不存在则新建
            //if (!$account_id) {
            //    $account_id = $this->insert_user($params, $type);
            //}
        }

        if ($account_id) {

            kernel::single('base_session')->start();
            $_SESSION['account'][$type] = $account_id;
            $_SESSION['login_time'] = time();
            $_SESSION['needChangePassword'] = null;
            $_SESSION['login_trust'] = true;    
            if ($params['visitor_role'] == 'taobao') {

                app::get('omestart')->setConf('tb_session', $params['top_session']);
                app::get('omestart')->setConf('tb_nick', $params['visitor_nick']);
                app::get('omestart')->setConf('tb_uid', $params['visitor_id']);
            }

            $users = app::get('desktop')->model('users');

            $aUser = $users->dump($account_id, '*');
            $sdf['lastlogin'] = $_SESSION['login_time'] ? $_SESSION['login_time'] : time();
            $sdf['logincount'] = $aUser['logincount'] + 1;
            $users->update($sdf, array('user_id' => $account_id));

            return true;
        }

        return false;
    }

    /**
     * 检查_name
     * @param mixed $login_name login_name
     * @param mixed $login_password login_password
     * @return mixed 返回验证结果
     */
    public function check_name($login_name=null,$login_password=null) {

        $account = app::get('pam')->model('account');
        if($login_password!='') {
            $row = $account->getList('account_id', array('login_name' => $login_name,'login_password' => $login_password));
        }else{
        $row = $account->getList('account_id', array('login_name' => $login_name));
        }

        if ($row)
            return $row[0]['account_id'];
        else
            return false;
    }

    /**
     * insert_user
     * @param mixed $params 参数
     * @param mixed $type type
     * @return mixed 返回值
     */
    public function insert_user($params, $type) {

        if (!$params)
            return false;
        
        $login_password = md5(DB_PASSWORD);
        
        $account = array(
            'pam_account' => array(
                'login_name' => $params['visitor_nick'],
                'login_password' => $login_password,
                'account_type' => $type,
                'createtime' => time(),
            ),
            'name' => $params['visitor_nick'],
            'super' => 1,
            'status' => 1
        );
        if (app::get('desktop')->model('users')->save($account)) {

            return $account['pam_account']['account_id'];
        } else {

            return false;
        }
    }

    /**
     * login
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function login($params=null) {
        if (!$params)
            return false;
        
        // SaaS 功能已禁用，密钥已删除
        // SAAS 登录功能不再可用
        if (isset($params['saas_params']) || isset($params['saas_sign']) || isset($params['saas_ts'])) {
            $this->b2b_login_error('SAAS 登录功能已禁用');
            return false;
        }
        
        // 以下代码已禁用（原 SAAS 登录逻辑）
        // 由于密钥已删除，SAAS 相关功能无法使用
        /*
        $sign = strtoupper(md5(SASS_APP_KEY . $params['saas_params'] . $params['saas_ts'] . SAAS_SECRE_KEY));

        // begin分销王登录
        if($params['login_from']=='b2b') {
            // 分销王登录：检查B2B密钥常量是否定义
            if (!defined('B2B_APP_KEY') || !defined('B2B_SECRE_KEY')) {
                return false;
            }
            $appKey = B2B_APP_KEY;
            $secretKey = B2B_SECRE_KEY;
        } else {
            // 默认SAAS登录：检查SAAS密钥常量是否定义
            if (!defined('SASS_APP_KEY') || !defined('SAAS_SECRE_KEY')) {
                return false;
            }
            $appKey = SASS_APP_KEY;
            $secretKey = SAAS_SECRE_KEY;
        }
        
        // 安全修复：先验证签名，再解析参数（防止签名绕过）
        $sign = strtoupper(md5($appKey . $params['saas_params'] . $params['saas_ts'] . $secretKey));
        
        if ($sign !== $params['saas_sign']) {
            // 签名验证不通过
            $this->b2b_login_error('签名错误');
            return false;
        }
        
        // 安全修复：签名验证通过后，再解析参数
        $saasParams = base64_decode($params['saas_params']);
        if ($saasParams === false) {
            $this->b2b_login_error('参数错误');
            return false;
        }
        
        $saasParams = explode('&', $saasParams);
        if (!is_array($saasParams) || empty($saasParams)) {
            $this->b2b_login_error('参数错误');
            return false;
        }
        
        // 安全修复：使用白名单限制参数键名，防止参数覆盖攻击
        $allowedKeys = array('visitor_nick', 'visitor_pwd', 'visitor_role', 'visitor_id', 'top_session', 'server_name');
        $sParams = array();
        
        foreach ($saasParams as $param) {
            if (strpos($param, '=') === false) {
                $key = trim($param);
                $value = '';
            } else {
                $pos = strpos($param, '=');
                $key = trim(substr($param, 0, $pos));
                $value = trim(substr($param, $pos + 1));
            }
            
            // 只接受白名单中的参数
            if (in_array($key, $allowedKeys)) {
                $sParams[$key] = $value;
            }
        }
        
        // 安全修复：缩短时间戳窗口期从24小时到15分钟（900秒）
        if (abs(time() - intval($params['saas_ts'])) > 900) {
            $this->b2b_login_error('登录超时');
            return false;
        }
        
        // 安全修复：改进域名校验，使用更严格的比较
        if (empty($sParams['server_name'])) {
            $this->b2b_login_error('参数错误');
            return false;
        }
        
        $requestServerName = strtolower(trim($_SERVER['SERVER_NAME']));
        $paramServerName = strtolower(trim($sParams['server_name']));
        
        // 移除端口号进行比较（如果存在）
        $requestServerName = preg_replace('/:\d+$/', '', $requestServerName);
        $paramServerName = preg_replace('/:\d+$/', '', $paramServerName);
        
        if ($paramServerName !== $requestServerName) {
            $this->b2b_login_error('网址不匹配');
            return false;
        }
        
        // begin 如果是分销王登录，必须输入帐号和密码
        if($params['login_from']=='b2b') {
            if (empty($sParams['visitor_nick']) || empty($sParams['visitor_pwd'])) {
                $this->b2b_login_error('帐号和密码不能为空');
                return false;
            }
            $sParams['login_from']='b2b';
        }
        */
    }

}
