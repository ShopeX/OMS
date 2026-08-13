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
        parent::__construct($app);
        kernel::single('base_session')->start();

        $_GOTO = app::get('wap')->router()->gen_url(array('ctl'=>'passport','act'=>'index'), true);

        $auth = pam_auth::instance(pam_account::get_account_type('desktop'));
        $account = $auth->account();
        if(get_class($this)!='wap_ctl_passport' && !$account->is_valid()){
            echo "<script>location ='$_GOTO'</script>";
            exit;
        }

        $this->user = kernel::single('desktop_user');
        if(get_class($this)!='wap_ctl_passport'){
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
        $this->delivery_link['order_confirm']    = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'confirm'), true);
        $this->delivery_link['order_consign']    = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'consign'), true);
        $this->delivery_link['order_sign']       = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'sign'), true);
        $this->delivery_link['overtimeOrders']   = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'overtimeOrders'), true);
        $this->delivery_link['aftersale_returnproduct']   = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'pending'), true);
        $this->delivery_link['aftersale_changeproduct']   = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_changeproduct','act'=>'pending'), true);
        
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
