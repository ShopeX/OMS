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


class desktop_ctl_default extends desktop_controller {

    var $workground = 'desktop_ctl_dashboard';

    function index() {

        $this->_init_keyboard_setting();

        $desktop_user = kernel::single('desktop_user');

        // $menus = $desktop_user->get_work_menu();
        $menus = [];
        $user_id = $this->user->get_id();
        $desktop_user->get_conf('fav_menus', $fav_menus);
        //默认显示5个workground
        $workground_count = (app::get('desktop')->getConf('workground.count')) ? (app::get('desktop')->getConf('workground.count') - 1) : 5;
        if (!$fav_menus) {
            $i = 0;
            foreach ((array)$menus['workground'] as $key => $value) {
                //if($i++>$workground_count) break;
                $fav_menus[] = $key;

            }
        }


        $obj = kernel::service('desktop_index_seo');
        if (is_object($obj) && method_exists($obj, 'title')) {
            $title = $obj->title();
        } else {
            $title = app::get('desktop')->_('管理后台');
        }
        if (is_object($obj) && method_exists($obj, 'title_desc')) {
            $title_desc = $obj->title_desc();
        } else {
            $title_desc = 'Powered By shopeX';
        }

        //消息通知
        $rpcNotifyCount = app::get('base')->model('rpcnotify')->count(array('status'=>'false'));
        $this->pagedata['rpc_notify_count'] = $rpcNotifyCount;
        //短信
        $key = 'sms_user_info_num';
        $sms_info = cachecore::fetch($key);
        if(!$sms_info) {
            $sms_info = kernel::single('erpapi_router_request')->set('sms', $account)->sms_getUserInfo();
            cachecore::store($key, $sms_info, 300);
        }
        $msgCount = 0;
        if('succ' == $sms_info['rsp']){
            $msgCount = $sms_info['data']['month_residual'];
        }
        $this->pagedata['msg_count'] = $msgCount;
        $this->pagedata['title'] = $title;
        $this->pagedata['title_desc'] = $title_desc;
        $this->pagedata['session_id'] = kernel::single('base_session')->sess_id();
        $this->pagedata['uname'] = $this->user->get_login_name();
        $this->pagedata['first_uname'] = mb_substr($this->user->get_login_name(),0,1);
        $this->pagedata['param_id'] = $user_id;
        $this->pagedata['menus'] = $menus;
        $this->pagedata['fav_menus'] = (array)$fav_menus;
        $this->pagedata['shop_base'] = kernel::base_url(1);
        $this->pagedata['shopadmin_dir'] = ($_SERVER['REQUEST_URI']);
        $desktop_user->get_conf('shortcuts_menus', $shortcuts_menus);
        $this->pagedata['shortcuts_menus'] = (array)$shortcuts_menus;
        $desktop_menu = array();
        foreach (kernel::servicelist('desktop_menu') as $service) {
            $array = $service->function_menu();
            $desktop_menu = (is_array($array)) ? array_merge($desktop_menu, $array) : array_merge($desktop_menu, array($array));
        }
        $this->pagedata['desktop_menu'] = (count($desktop_menu)) ? '<span>' . join('</span>|<span>', $desktop_menu) . '</span>' : '';
        list($this->pagedata['theme_scripts'], $this->pagedata['theme_css']) =
            desktop_application_theme::get_files($this->user->get_theme());

        $this->Certi = base_certificate::get('certificate_id');

        // 不做sess_id验证
        $confirmkey = $this->setEncode('', $this->Certi);
        $this->pagedata['certificate_url'] = "https://service.shopex.cn/info.php?certi_id=" . urlencode($this->Certi) . "&version=ecstore&confirmkey=" . urlencode($confirmkey) . "&_key_=do";
        if (app::get('bizsuite')->is_actived()) {
            $bind = app::get('bizsuite')->model('relation')->getList('shop_id', array('node_type' => 'bizsuite', 'status' => 'bind'));
            $oauth_info = app::get('bizsuite')->getConf('biz.oauth');
            if ($bind && $oauth_info) {
                $url = rtrim($oauth_info['client_url'], '/');
                if (!preg_match('/^(http|https)/', $url)) {
                    $url = 'http://' . $url;
                }
                $this->pagedata['cloud_url'] = $url;
            }
        }
        $this->display('index.vue');

    }

    function setEncode($sess_id, $certi_id) {
        $ENCODEKEY = 'ShopEx@License';
        $confirmkey = md5($sess_id . $ENCODEKEY . $certi_id);

        return $confirmkey;
    }

    function set_main_menu() {
        $desktop_user = new desktop_user();
        $workground = $_POST['workgrounds'];
        $desktop_user->set_conf('fav_menus', $workground);
        header('Content-Type:text/jcmd; charset=utf-8');

        echo '{success:"' . app::get('desktop')->_("保存成功！") . '"
        }';
    }


    function allmenu() {
        $desktop_user = new desktop_user();
        $menus = $desktop_user->get_work_menu();
        $desktop_user->get_conf('shortcuts_menus', $shortcuts_menus);

        foreach ($menus['workground'] as $k => $v) {
            $v['menu_group'] = $menus['menu'][$k];
            $workground_menus[$k] = $v;
        }
        $this->pagedata['menus'] = $workground_menus;
        $this->pagedata['shortcuts_menus'] = (array)$shortcuts_menus;
        $this->display('allmenu.html');

    }

    function main_menu_define() {
        $desktop_user = kernel::single('desktop_user');

        $menus = $desktop_user->get_work_menu();
        $user_id = $this->user->get_id();
        $desktop_user->get_conf('fav_menus', $fav_menus);
        //默认显示5个workground
        $workground_count = (app::get('desktop')->getConf('workground.count')) ? (app::get('desktop')->getConf('workground.count') - 1) : 5;
        if (!$fav_menus) {
            $i = 0;
            foreach ((array)$menus['workground'] as $key => $value) {
                //if($i++>$workground_count) break;
                $fav_menus[] = $key;
            }
        }

        $this->pagedata['fav_menus'] = (array)$fav_menus;
        $this->pagedata['menus'] = $menus;
        $this->display('main_menu_define.html');

    }


    private function _init_keyboard_setting() {
        $desktop_user = kernel::single('desktop_user');
        $desktop_user->get_conf('keyboard_setting', $keyboard_setting);
        $o = kernel::single('desktop_keyboard_setting');
        $json = $o->get_setting_json($keyboard_setting);
        $this->pagedata['keyboard_setting_json'] = $json;
    }


    public function keyboard_setting() {
        $desktop_user = kernel::single('desktop_user');
        if ($_POST['keyboard_setting']) {
            $desktop_user->set_conf('keyboard_setting', $_POST['keyboard_setting']);
            $this->_init_keyboard_setting();
            echo $this->pagedata['keyboard_setting_json'];
            exit;
        }

        $desktop_user->get_conf('keyboard_setting', $keyboard_setting);

        //初始化数据
        $o = kernel::single('desktop_keyboard_setting');
        $o->init_keyboard_setting_data($setting, $keyword, $keyboard_setting);

        foreach ($setting as $key => &$_setting) {
            foreach ($_setting as &$row) {
                if ($key != '导航菜单上的栏目') {
                    $default = array('ctrl', 'shift');
                    $o->set_default_control($default, $row);
                } else {
                    $default = array('alt');
                    $o->set_default_control($default, $row);
                }
            }
        }

        $this->pagedata['form_action_url'] = $this->app->router()->gen_url(array('app' => 'desktop', 'act' => 'keyboard_setting', 'ctl' => 'default'));
        $this->pagedata['keyword'] = $keyword;
        $this->pagedata['setting'] = $setting;
        $this->display('keyboard_setting.html');
    }


    function workground() {
        $wg = $_GET['wg'];
        if (!$wg) {
            echo app::get('desktop')->_("参数错误");
            exit;
        }
        $user = new desktop_user();
        $menus = $this->app->model('menus');
        $group = $user->group();
        $aPermission = array();
        foreach ((array)$group as $val) {
            #$sdf_permission = $menus->dump($val);
            $aPermission[] = $val;
        }

        if ($user->is_super()) {
            $sdf = $menus->getList('*', array('menu_type' => 'menu', 'workground' => $wg));
        } else {
            $sdf = $menus->getList('*', array('menu_type' => 'menu', 'workground' => $wg, 'permission' => $aPermission));
        }

        foreach ((array)$sdf as $value) {
            $url = $value['menu_path'];
            if ($value['display'] == 'true') {
                $url_params = unserialize($value['addon']);
                if (count($url_params['url_params']) > 0) {
                    foreach ((array)$url_params['url_params'] as $key => $val) {
                        $parmas = $params . '&' . $key . '=' . $val;
                    }
                }
                $url = $value['menu_path'] . $parmas;
                break;
            }

        }
        $this->redirect('index.php?' . $url);

    }


    function alertpages() {
        $this->pagedata['goto'] = strip_tags(urldecode($_GET['goto']));

        $this->singlepage('loadpage.html');
    }


    function set_shortcuts() {
        $desktop_user = new desktop_user();
        $_POST['shortcuts'] = ($_POST['shortcuts'] ? $_POST['shortcuts'] : array());
        foreach ($_POST['shortcuts'] as $k => $v) {
            list($k, $v) = explode('|', $v);
            $shortcuts[$k] = $v;
        }
        $desktop_user->set_conf('shortcuts_menus', $shortcuts);
        header('Content-Type:text/jcmd; charset=utf-8');
        echo '{success:"' . app::get('desktop')->_("设置成功") . '"}';
    }


    function status() {

        set_time_limit(0);
        ob_start();
        /*        if($_POST['events']){
                    foreach($_POST['events'] as $worker=>$task){
                        foreach(kernel::servicelist('desktop_task.'.$worker) as $object){
                            $object->run($task,$this);
                        }
                    }
                }
        */
        $flow = $this->app->model('flow');
        if ($flow->fetch_role_flow($this->user)) {
            echo '<script>alert("' . app::get('desktop')->_("您有新短消息！") . '");</script>';
        }

        //当日回写错误提醒条数
        //$this->_get_warn_num();

        //系统通知 desktop  未读条数
        //$this->_get_notify_num();

        $output = ob_get_contents();
        ob_end_clean();
        header('Content-length: ' . strlen($output));
        header('Connection: close');
        echo $output;

        /*
        $taskObj = kernel::single('taoexlib_task_limit');
        if($taskObj->available()){
            $taskObj->setStatus(taoexlib_task_limit::$__RUNNING);
            app::get('base')->model('queue')->flush();
            kernel::single('base_misc_autotask')->trigger();
            $taskObj->setStatus(taoexlib_task_limit::$__FINISH);
        }
        */

        kernel::single('base_session')->close(false);
    }

    function desktop_events() {

        if ($_POST['events']) {
            foreach ($_POST['events'] as $worker => $task) {
                foreach (kernel::servicelist('desktop_task.' . $worker) as $object) {
                    $object->run($task, $this);
                }
            }
        }
    }

    public function _get_notify_num() {
        $count = app::get('base')->model('rpcnotify')->count(array('status' => 'false'));
        if ($count) {
            $js = '$$("#topbar .notify_num").getParent().setStyle("display","inline");';
        }
        echo '<script>' . $js . '$$("#topbar .notify_num")[0].set(\'text\',"' . ($count ? "($count)" : '') . '");</script>';
    }

    public function _get_warn_num() {
        $start_time = strtotime(date("Y-m-d"));
        $up_time = array($start_time, time());
        $orderObj = app::get('ome')->model('orders');
        $count = $orderObj->count(array('createway' => 'matrix', 'sync' => 'fail', 'up_time|between' => $up_time));
        if ($count) {
            $js = '$("syncwarn").setStyle("display","block");';

            $shipped_count = $orderObj->count(array('createway' => 'matrix', 'sync' => 'fail', 'sync_fail_type' => 'shipped', 'up_time|between' => $up_time));
            $params_count = $orderObj->count(array('createway' => 'matrix', 'sync' => 'fail', 'sync_fail_type' => 'params', 'up_time|between' => $up_time));
            $other_count = $orderObj->count(array('createway' => 'matrix', 'sync' => 'fail', 'sync_fail_type' => array('none', 'unbind'), 'up_time|between' => $up_time));
            $countjs = '$("sync-shipped-count").set(\'text\',"' . $shipped_count . '");$("sync-params-count").set(\'text\',"' . $params_count . '");$("sync-other-count").set(\'text\',"' . $other_count . '");';
        }
        echo '<script>' . $js . $countjs . '$$("#syncwarn .warn_num")[0].set(\'text\',"' . ($count ? "($count)" : '') . '");</script>';
    }

    public function about_blank() {
        echo '<html><head></head><body>ABOUT_BLANK_PAGE</body></html>';
    }

    function clear_session() {
        $this->user->id = 0;
        $_SESSION = array();
        echo 'succ';
    }

    public function getviewcount() {
        if(empty($_POST['mdl'])) {
            echo json_encode(array('count'=>0));
            exit();
        }
        // CNVD-2026-42947: only allow reflecting dbeav model classes.
        $mdlName = (string) $_POST['mdl'];
        if(!preg_match('/^[a-z0-9]+_mdl_[a-z0-9_]+$/', $mdlName) || !class_exists($mdlName)) {
            echo json_encode(array('count'=>0));
            exit();
        }
        $mdl = kernel::single($mdlName);
        if(!is_object($mdl) || !($mdl instanceof dbeav_model)) {
            echo json_encode(array('count'=>0));
            exit();
        }
        if($_POST['filter']) {
            $filter = urldecode($_POST['filter']);
            parse_str($filter, $filter);
        } else {
            $filter = array();
        }
        echo json_encode(array('count'=>$mdl->count($filter)));
    }

    /**
     * 接口获取菜单
     * @Author: XueDing
     * @Date: 2023/12/27 3:15 PM
     * @return void
     */
    public function getMenus()
    {
        $desktop_user = kernel::single('desktop_user');
        $oldMenus = $desktop_user->get_work_menu();

        $menus = $oldMenus['workground'];
        foreach ($menus as $key => $val) {
            unset($menus[$key]['app_id'], $menus[$key]['menu_path'], $menus[$key]['workground'], $menus[$key]['menu_group'], $menus[$key]['target'],);
            $tmpSubMenu       = $oldMenus['menu'][$key];
            $secondLevelMenus = [];
            foreach ($tmpSubMenu as $tmpKey => $tmpVal) {
                $secondLevelMenus[] = [
                    'menu_id'    => current($tmpVal)['en'],
                    'menu_title' => $tmpKey,
                    'menu_type'  => '',
                    'submenus'   => $tmpVal,
                ];
            }
            if (empty($secondLevelMenus)) {
                unset($menus[$key]);
                continue;
            }
            $menus[$key]['menu_id']  = $key;
            $menus[$key]['submenus'] = $secondLevelMenus;
        }
        $menus = array_values($menus);

        $this->returnJson($menus);
    }

    /**
     * 获取快捷菜单
     * @Author: XueDing
     * @Date: 2023/12/27 3:16 PM
     * @return void
     */
    public function getShortcuts()
    {
        $desktop_user = kernel::single('desktop_user');
        $desktop_user->get_conf('shortcuts_menus', $shortcuts_menus);
        $this->returnJson($shortcuts_menus);
    }

    public function getAllMenu()
    {
        $desktop_user = new desktop_user();
        $menus        = $desktop_user->get_work_menu();
        $desktop_user->get_conf('shortcuts_menus', $shortcuts_menus);

        foreach ($menus['workground'] as $k => $v) {
            $v['menu_group']      = $menus['menu'][$k];
            $workground_menus[$k] = $v;
        }
        $this->returnJson(['menus'=>$workground_menus,'shortcuts_menus'=>$shortcuts_menus]);
    }

    /**
     * 设置快捷菜单
     * @Author: XueDing
     * @Date: 2023/12/27 5:36 PM
     * @return void
     */
    public function setShortcuts()
    {
        $desktop_user = new desktop_user();
        $_POST['shortcuts'] = ($_POST['shortcuts'] ? $_POST['shortcuts'] : array());
        foreach ($_POST['shortcuts'] as $k => $v) {
            list($k, $v) = explode('|', $v);
            $shortcuts[$k] = $v;
        }
        $desktop_user->set_conf('shortcuts_menus', $shortcuts);
        $this->returnJson([]);
    }

    /**
     * 修改密码
     * @Author: XueDing
     * @Date: 2023/12/27 5:36 PM
     * @return void
     */
    public function changePassword()
    {
        $userLib                  = kernel::single('desktop_user');
        $users                    = $this->app->model('users');
        $account_id               = $this->user->get_id();
        $sdf                      = $users->dump($account_id, '*', array (':account@pam' => array ('*'), 'roles' => array ('*')));
        $filter['account_id']     = $account_id;
        $filter['account_type']   = pam_account::get_account_type($this->app->app_id);
        $filter['login_password'] = pam_encrypt::get_encrypted_password(trim($_POST['old_login_password']), pam_account::get_account_type($this->app->app_id));
        $pass_row                 = app::get('pam')->model('account')->getList('account_id', $filter);
        $status                   = false;
        if ($_POST) {
            $db = kernel::database();
            $db->beginTransaction();
            $loginPassword = pam_encrypt::get_encrypted_password(trim($_POST['new_login_password']), pam_account::get_account_type($this->app->app_id));
            if (!$pass_row) {
                $msg = '原始密码不正确';
            } elseif (trim($_POST['new_login_password']) == trim($_POST['old_login_password'])) {
                $msg = '新密码和旧密码需要不一致';
            } elseif ($_POST['new_login_password'] != $_POST[':account@pam']['login_password']) {
                $msg = '两次密码不一致';
            } elseif (!$userLib->checkRepeatPassWord($account_id, $loginPassword, $error_msg)) {
                $msg = $error_msg;
            } elseif (!$userLib->checkUserPassWordLength($account_id, $_POST['new_login_password'], $error_msg)) {
                $msg = $error_msg;
            } elseif (!$userLib->validPassWord($_POST['new_login_password'], $error_msg, $sdf['name'])) {
                $msg = $error_msg;
            } else {
                $_POST['pam_account']['account_id']     = $account_id;
                $_POST['pam_account']['login_password'] = $loginPassword;
                $_POST = desktop_auth_password_security::stampPasswordChangedAt($_POST);
                $saved = $users->save($_POST);
                if ($saved === false) {
                    $msg = '密码保存失败，请稍后重试';
                } else {
                    $desktop_users   = app::get('desktop')->model('users');
                    $sdf['modifyip'] = $_SERVER["REMOTE_ADDR"];
                    $desktop_users->update($sdf, array ('user_id' => $account_id));
                    //echo "密码修改成功";
                    $userLib->checkUpdatePwd($_POST['pam_account']['account_id'], true);
                    //新增插入日志
                    $this->app->model('user_logs')->changePwd($account_id);

                    // IDAAS
                    list($rs, $msg) = kernel::single('desktop_user_auth')->sync_account(array (
                        'account_id'     => $_POST['pam_account']['account_id'],
                        'login_name'     => $sdf['account']['login_name'],
                        'login_password' => $_POST['pam_account']['login_password'],
                    ), 'password');
                    if ($rs === false) {} else {
                        desktop_auth_password_security::revokeAfterSuccessfulChange(
                            (int) $account_id,
                            static function (int $userId): void {
                                desktop_auth_jwt::incrementTokenVersion($userId);
                            }
                        );
                        $status = true;
                        $msg    = '密码修改成功';
                    }
                }
            }
            if ($status) {
                $db->commit();
                kernel::single('base_session')->destory();
            } else {
                $db->rollBack();
            }
        } else {
            $status = false;
            $msg    = '更新数据不能为空';
        }
        $this->returnJson([], $status, $msg);
    }
    
    /**
     * 京麦评价获取Token
     * @todo：获取京东平台token，每天只获取一次，防止多个操作员登录频繁请求接口；
     * 
     * @return string
     */
    public function getJDToken()
    {
        $shopObj = app::get('ome')->model('shop');
        
        //获取京东店铺
        $shopInfo = $shopObj->dump(array('node_type'=>'360buy','node_id|nothan'=>''), 'shop_id,shop_bn,shop_type,name,node_id');
        if(empty($shopInfo)){
            $error_msg = '没有绑定京东店铺,无需进行京麦评价!';
            $this->returnJson([], false, $error_msg);
        }
        
        //获取上一次获取的token
        $lastGetJdToken = app::get('ome')->getConf('lastGetJdToken');
        $isGetFlag = true;
        $depToken = '';
        if($lastGetJdToken){
            $depToken = $lastGetJdToken['token'];
            $Expires = $lastGetJdToken['execTime'] + 86400;
            $lastGetDay = date('Ymd', $lastGetJdToken['execTime']);
            $nowDay = date('Ymd', time());
            
            //过期时间大于现在时间,不需要重复请求;
            //@todo：每天第一次访问，获取新的Token;
            if($Expires > time() && $lastGetDay == $nowDay){
                $isGetFlag = false;
            }
        }
        
        //请求获取token
        $result = array();
        if($isGetFlag || empty($depToken)){
            $shop_id = $shopInfo['shop_id'];
            $params = array();
            $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->base_getNpsToken($params);
            
            //Token
            $depToken = $result['data']['token'];
            
            //缓存Token
            if($depToken){
                $jdTokenInfo = array(
                    'execTime' => time(),
                    'token' => $depToken,
                );
                app::get('ome')->setConf('lastGetJdToken', $jdTokenInfo);
            }
        }
        
        //check
        if(empty($depToken)){
            $error_msg = '获取Token失败：';
            $error_msg .= (isset($result['err_msg']) ? $result['err_msg'] : '没有返回值');
            $this->returnJson([], false, $error_msg);
        }else{
            //appId服务商ID
            $appId = defined('JD_NPS_APPID') ? JD_NPS_APPID : '';
            
            //data
            $data = array(
                'appId' => $appId,
                'depToken' => $depToken,
            );
            
            $this->returnJson($data, true, '成功获取Token!');
        }
    }
    
    /**
     * 获取站点信息
     * 
     * @access public
     * @author chenping<chenping@shopex.cn>
     * @time 2024-06-20 11:35:00
     */
    public function getSiteInfo()
    {
        $siteInfo = kernel::single('desktop_site')->getInfo();
        
        $this->returnJson($siteInfo, true, '获取站点信息成功');
    }
}
