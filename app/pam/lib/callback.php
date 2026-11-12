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


class pam_callback{

    function login($params){
        $auth = pam_auth::instance($params['type']);
        $auth->set_appid($params['appid']);
        if($params['module']){
            if($passport_module = kernel::single($params['module'])){
                if($passport_module instanceof pam_interface_passport){
                    $module_uid = $passport_module->login($auth,$auth_data);
                    if($module_uid){
                        $auth_data['account_type'] = $params['type'];
                        $auth->account()->update($params['module'], $module_uid, $auth_data);
                    }
                    $log = array(
                        'event_time'=>time(),
                        'event_type'=>$auth->type,
                        'event_data'=>base_request::get_remote_addr().':'.$auth_data['log_data'],

                    );
                    app::get('pam')->model('log')->insert($log);
                    if(!$module_uid)$_SESSION['last_error'] = $auth_data['log_data'];

                    //实例化
                    $usersObj = app::get('desktop')->model('users');
                    $sessionLib = kernel::single('base_session');

                    //本次登录的session_id
                    $session_id = $sessionLib->sess_id();

                    //注销同账号,其它电脑上登录的session_id
                    $is_restrict = app::get('ome')->getConf('desktop.account.equal.restrict');
                    if($is_restrict !== 'false'){
                        //配置默认是开启的
                        $userInfo = $usersObj->dump(array('user_id'=>$module_uid), 'user_id,session_id');
                        if($userInfo['session_id'] && $userInfo['session_id'] != $session_id){
                            $checkDel = $sessionLib->deleteSessionId($userInfo['session_id']);
                        }
                    }

                    //保存登录的session_id
                    $loginSdf = array();
                    $loginSdf['session_id'] = $session_id;

                    $usersObj->update($loginSdf, array('user_id'=>$module_uid));

                    // SESSION auth writes removed - JWT is now primary auth
                    // Keeping $_SESSION['account'][$params['type']] for backward compatibility (read only)
                    $params['member_id'] = $_SESSION['account'][$params['type']] ?? null;
                    $params['uname'] = $_POST['uname'];

                    foreach(kernel::servicelist('pam_login_listener') as $service)
                    {
                        $service->listener_login($params);
                    }

                    // 登陆御城河
                    kernel::single('base_hchsafe')->login_log($params);

                    if($params['redirect'] && $module_uid) {
                        $service = kernel::service('callback_infomation');
                        if (is_object($service)) {
                            if (method_exists($service, 'get_callback_infomation') && $module_uid) {
                                $data = $service->get_callback_infomation($module_uid, $params['type']);
                                if (!$data) $url = '';
                                else $url = '?' . utils::http_build_query($data);
                            }

                        }
                    }
                    if($module_uid) {
                        // 登录成功，御城河风险系统评估
                        $params['password_string'] = pam_encrypt::get_encrypted_password($_POST['password'],$auth->type);
                        $risk = kernel::single('base_hchsafe')->compute_risk($params,$msg);

                        if (!$risk) {
                            unset($_SESSION['account'][$auth->type]);
                            $_SESSION['last_error'] = $msg;
                        }
                    }

                    if($_COOKIE['autologin'] > 0){
                        kernel::single('base_session')->set_cookie_expires($_COOKIE['autologin']);
                        //如果自动登录，设置cookie过期时间，单位：分
                    }

                    if($_SESSION['callback'] && !$module_uid){
                        $callback_url = $_SESSION['callback'];
                        unset($_SESSION['callback']);
                        header('Location:' .urldecode($callback_url));
                        exit;
                    }
                     else{
                         if($params['redirect']){
                             $redirect = base64_decode(urldecode($params['redirect']));
                         }

                         $redirect = preg_match('/http:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is',$redirect) ? $redirect : app::get('desktop')->router()->gen_url(array(),1);

                         if (!$module_uid) {
                             $_GOTO  = kernel::base_url(1).'/index.php?ctl=passport';
                         } else {
                             $_GOTO  = kernel::get_host_url();
                         }
                         
                         header('Location:' .$_GOTO. $url);
                         exit;
                     }

                }
            }else{

            }
        }
    }

}
