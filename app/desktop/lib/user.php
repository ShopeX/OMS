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


class desktop_user{

    private $mark_modified = false;
    private $userGroup = array();

    function __construct(){
        $this->account_type = pam_account::get_account_type('desktop');
        if(isset($_SESSION['account'][$this->account_type])){
            $this->user_id = $_SESSION['account'][$this->account_type];
            $_inner_key = sprintf("account_user_%s",$this->user_id);
            $this->user_data = cachecore::fetch($_inner_key);
            if ($this->user_data === false) {
                $this->user_data = app::get('desktop')->model('users')->dump($this->user_id,'*',array( ':account@pam'=>array('*') ));
                //缓存15分钟
                cachecore::store($_inner_key, $this->user_data, 60*15);
            }

            if (empty($this->user_data)) {
                unset($_SESSION['account'][$this->account_type]);
            }
        }
    }
    
    /**
     * 队列导出数据时，设置user_id = op_id 查询数据时来判断权限。
     * @Author: xueding
     * @Vsersion: 2022/6/29 上午10:44
     * @param $user_id
     */
    public function setVirtualLogin($user_id)
    {
        if ($user_id) {
            $this->user_id = $user_id;
            $this->user_data = app::get('desktop')->model('users')->dump($this->user_id,'*',array( ':account@pam'=>array('*') ));
        }
    }
    function get_name(){
        return $this->user_data['name'];
    }

    function get_login_name(){
        return $this->user_data['account']['login_name'];
    }
    function get_id(){
        return $this->user_id;
    }

    function is_super(){
        return $this->user_data['super'];
    }

    function get_status(){
        return $this->user_data['status'];
    }
    function get_mobile(){
        return $this->user_data['mobile'];
    }
    
    function logout(){

    }

    function valid(){
    }

    function valid_permission(){
    }

    function get_conf($key,&$return){
        if(!isset($this->config)){
            $info = app::get('desktop')->model('users')->dump($this->user_id,'config');
            $this->config = $info['config'];
        }
        if(array_key_exists($key,(array)$this->config)){
            $return = $this->config[$key];
            return true;
        }else{
            return false;
        }
    }

    function set_conf($key,$value){
        $this->config[$key] = $value;
        if(!$this->mark_modified){
            $this->mark_modified = true;
            register_shutdown_function(array(&$this,'save_conf'));
        }
        return true;
    }

    /**
     * 保存_conf
     * @return mixed 返回操作结果
     */
    public function save_conf(){
        $info = app::get('desktop')->model('users')->dump($this->user_id,'config');
        $this->config = array_merge((array)$info['config'],(array)$this->config);
        app::get('desktop')->model('users')->update(
                            array('config'=>$this->config),
                            array('user_id'=>$this->user_id));
    }

    function get_theme(){
        if($this->get_conf('desktop_theme',$current_theme)){
            return $current_theme;
        }else{
            return 'desktop/default';
        }
    }

    function has_roles(){
        return array(0);
    }

    #获取用户操作权限 permission ID
    function group(){
        if($_SESSION['account']['user_permission']) {
            return $_SESSION['account']['user_permission'];
        }
        $hasrole = app::get('desktop')->model('hasrole');
        $roles = app::get('desktop')->model('roles');
        $menus = app::get('desktop')->model('menus');

        $sdf = $hasrole->getList('role_id',array('user_id'=>$this->user_id));
        $pass = array();
        if ($sdf) {
            $roleIdArr = array_column($sdf, 'role_id');
            $pass = $roles->getList('workground,data_authority', ['role_id|in'=>$roleIdArr]);
        }
        // foreach($sdf as $val){
        //     $pass[] = $roles->dump($val,'workground,data_authority');
        // }
        $group = array();

        foreach($pass as $key){
            $work = unserialize($key['workground']);
            if(!$work){echo app::get('desktop')->_("无任何权限");exit;}
            foreach($work as $val){
                $group[] = $val;
            }
            //增加数据权限判断
            $data_authority = unserialize($key['data_authority']);
            if ($data_authority) {
                foreach($data_authority as $val){
                    $group[] = $val;
                }
            }
        }
        return $group;
    }

    #检查工作组权限
    function chkground($workground)
    {
       $passWg = [
           'desktop_ctl_login',
       ];
       
       if (in_array($workground, $passWg)) {
           return true;
       }
        
        if ($workground == 'desktop_ctl_recycle') {
            return true;
        }
        if ($workground == 'taoexlib_ctl_ietask') {
            return true;
        }
        if ($workground == 'omevirtualwms_helper') {
            return true;
        }
        if ($workground == 'desktop_ctl_passport') {
            return true;
        }
        if ($workground == 'desktop_ctl_dashboard' && $_GET['act'] != 'alertpages') {
            return true;
        }
        if ($workground == 'taoapi_view_helper') {
            return true;
        }
        if ($workground == '') {
            return true;
        }
        if ($_GET['goto'] == 'index.php?app=desktop&ctl=recycle&act=index&nobuttion=1') {
            return true;
        }
        if ($_GET['ctl'] == 'adminpanel') {
            return true;
        }
        if ( in_array($_GET['act'],['execlImportDailog', 'execlImportTmpl', 'execlImportTmpl','doExcelImport'] ) ) {
            return true;
         }

        $authorityFinderId = $_GET['_finder']['finder_id'] ?: ($_GET['finder_id'] ?: ($_GET['find_id'] ?: substr(md5($_SERVER['QUERY_STRING']),5,6)));

        $menus  = app::get('desktop')->model('menus');
        $group  = $this->group();
        $uriArr = $_GET;
        if ($_GET['act'] == 'alertpages' && $_GET['hash']) {
            parse_str(substr($_GET['hash'], 1), $uriArr);
            if ($uriArr['find_id']) {
                $authorityFinderId = $uriArr['find_id'];
            }
            if ($uriArr['finder_id']) {
                $authorityFinderId = $uriArr['finder_id'];
            }
            if ($uriArr['_finder']['finder_id']) {
                $authorityFinderId = $uriArr['finder_id'];
            }
        }
        $permission_id = $menus->permissionId($uriArr);
        if ($permission_id == '0') {
            if (!$_SESSION['account'][$_SESSION['type']]
                || ($_SERVER['HTTP_X_REQUESTED_BY'] == 'shopex-ui' && $_GET['act'] != 'alertpages')
                || strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false
                || in_array($authorityFinderId, (array)$_SESSION['authority'])) {
                return true;
            }
            return false;
        }
        if (in_array($permission_id, $group)) {
            if ($authorityFinderId) {
                $_SESSION['authority'][$authorityFinderId] = $authorityFinderId;
            }

            // 删除掉KEY为空的
            unset($_SESSION['authority']['']);

            return true;
        } else {
            return false;
        }
    }

    #更新登陆信息
    function login(){
        $users = app::get('desktop')->model('users') ;
        $aUser = $users->dump($this->user_id,'*');
        $sdf['lastlogin'] = $_SESSION['login_time']?$_SESSION['login_time']:time();
        unset($_SESSION['login_time']);
        $sdf['logincount'] = $aUser['logincount']+1;
        if($this->user_id){$users->update($sdf,array('user_id'=>$this->user_id));}
    }

   #todo根据管理员ID获得工作组菜单和相应的子菜单
   function get_work_menu(){
        $aWorkground = app::get('desktop')->model('menus')->getList(
            'menu_id,app_id,menu_title,menu_path,menu_type,workground,menu_group,target,icon,en',
            array('menu_type'=>'workground','disabled'=>'false','display' => 'true')
        );

        $aMenu = app::get('desktop')->model('menus')->getList(
            'menu_id,app_id,menu_title,menu_path,menu_type,workground,menu_group,addon,target,icon,en',
            array('menu_type'=>'menu','disabled'=>'false','display' =>'true')
        );

        if($this->is_super()){
            foreach($aWorkground as $value){
                if($value['menu_title']) $value['menu_title'] = app::get($value['app_id'])->_($value['menu_title']);
                
                $tmp[$value['workground']] = $value;
            }
            $aData['workground'] = $tmp;
            $allkey_workground = array_keys($aData['workground']);
            unset($tmp);
            foreach($aMenu as $value){
                if($value['menu_title']) $value['menu_title'] = app::get($value['app_id'])->_($value['menu_title']);
                if($value['menu_group']) $value['menu_group'] = app::get($value['app_id'])->_($value['menu_group']);
                $group= $value['menu_group']?$value['menu_group']:'nogroup';
                $tmp[$value['workground']][$group][] = $value;
            }
            $aData['menu'] = $tmp;
        }else{
            $group = $this->group();
            $meuns = app::get('desktop')->model('menus');
            $data = array();
            $data_menus = array();

            $aTmpAll  = $meuns->workgroup($group, true);
            $aMenuAll = $meuns->get_menu($group, true);
            foreach($group as $key=>$val){
                $aTmp = $aTmpAll[$val];
                foreach($aMenuAll[$val] as $v){
                    $group= $v['menu_group']?$v['menu_group']:'nogroup';
                    if(!@in_array($v,(array)$data_menus[$aTmp[0]['workground']][$group])) $data_menus[$aTmp[0]['workground']][$group][] = $v;
                }
                foreach($aTmp as $val ){
                    $data[$val['workground']] =$val;
                }
            }
            $aData['workground'] = $data;
            $allkey_workground = array_keys($aData['workground']);
            $aData['menu'] = $data_menus;
        }

        foreach((array)$aData['menu'] as $k1=>$group){
            if(!in_array($k1,(array)$allkey_workground)) {
                continue;
            }
            $menu_default = current(current($aData['menu'][$k1]));
            $__query = '';
            if($menu_default['addon']){
                $__params =  unserialize($menu_default['addon']);
                if(is_array($__params['url_params'])) $__query = '&'.utils::http_build_query($__params['url_params']);
            }

            if($__query) $menu_default['menu_path'] = $menu_default['menu_path'].$__query;
            $aData['workground'][$k1]['menu_path'] = $aData['workground'][$k1]['menu_path']?$aData['workground'][$k1]['menu_path']:$menu_default['menu_path'];
            $aData['workground'][$k1]['target'] = $aData['workground'][$k1]['target']?$aData['workground'][$k1]['target']:$menu_default['target'];

            foreach($group as $k2=>$menus){
                if(!$menus){unset($aData['menu'][$k1][$k2]);continue;}
                foreach($menus as $k3=>$menu){
                    $query = '';
                    if($menu['addon']){
                        $params =  unserialize($menu['addon']);
                        if(is_array($params['url_params'])) $query = '&'.utils::http_build_query($params['url_params']);

                        unset($menu['addon']);
                    }
                    $finderId = app::get('desktop')->router()->getFinderVid($menu['menu_path'].$query);
                    $menu['menu_path'] = $menu['menu_path'].$query.'&finder_vid='.$finderId;
                    
                    // 如果target有值且以"/"开头，使用target作为route_path，如：/audit-template-list（审批流模板列表）
                    if(!empty($menu['target']) && strpos($menu['target'], '/') === 0){
                        $menu['route_path'] = $menu['target'];
                    } else {
                        $menu['route_path'] = '/m-'.str_replace('_','',$menu['workground']).'/'.$menu['en'].'/'.$menu['menu_id'];
                    }
                    
                    $aData['menu'][$k1][$k2][$k3] = $menu;
                }
            }
        }
        return $aData;
   }

    #检查当前登录管理员是否有相应的操作权限
    function has_permission($permission_id)
    {
        if($this->is_super()) return true;
        if(!$this->userGroup) {
            $this->userGroup = $this->group();
        }
        if(in_array($permission_id,$this->userGroup)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 检查UpdatePwd
     * @param mixed $accountId ID
     * @param mixed $write write
     * @return mixed 返回验证结果
     */
    public function checkUpdatePwd($accountId, $write = false){
        $return = false;
        $cookieExpires = 30*1440*60;
        $cacheObj = function_exists('init_domain') ? kernel::single('taoexlib_params_cache') : base_kvstore::instance('sessions');
        $remount = '';
        $cacheObj->fetch('password_change_remount', $remount);
        $remount = unserialize($remount);
        if($write) {
            if (empty($remount)) {
                $remount[] = $accountId;
            } elseif(!in_array($accountId, $remount)) {
                array_push($remount, $accountId);
            }
            $return = true;
        } else {
            if(!$remount){
                return false;
            }
            if(in_array($accountId, $remount)) {
                $return = true;
                foreach($remount as $k => $val) {
                    if($accountId == $val) {
                        unset($remount[$k]);
                        break;
                    }
                }
            }
        }
        $cacheObj->store('password_change_remount', serialize($remount), $cookieExpires);
        return $return;
    }

    /**
     * @description 检查修改密码的有效性
     * @access public
     * @param string $pswd 新密码
     * @param string $error_msg 错误描述
     * @param string $name 用户名
     * @return boolean true/false
     */
    public function validPassWord($pswd, &$error_msg,$name = ''){
        if(!preg_match('/[a-z]+/', $pswd) || !preg_match('/[A-Z]+/', $pswd) || !preg_match('/[0-9]/', $pswd)){
            $error_msg = '密码必须包含英文数字大小写';
            return false;
        }

        if(!empty($name) && strpos($pswd,$name) !== false){
            $error_msg = "密码不能包含【{$name}】";
            return false;
        }

        return true;
    }

    public function checkUserPassWordLength($userId, $pswd, &$error_msg) {
        if ('true' == app::get('ome')->getConf('desktop.password.length.limit') ) {
              if($userId == 1) {
                  if(strlen($pswd) < 16){
                      $error_msg = '密码长度不能小于16位';
                      return false;
                  }

                  if(strlen($pswd) > 32){
                      $error_msg = '密码长度不能大于32位';
                      return false;
                  }
              } else {
                if(strlen($pswd) < 12){
                    $error_msg = '密码长度不能小于12位';
                    return false;
                }

                if(strlen($pswd) > 32){
                    $error_msg = '密码长度不能大于32位';
                    return false;
                }
              }
        }else{
            if(strlen($pswd) < 12){
                $error_msg = '密码长度不能小于12位';
                return false;
            }

            if(strlen($pswd) > 32){
                $error_msg = '密码长度不能大于32位';
                return false;
            }
        }
        return true;
    }

    /**
     * 检查RepeatPassWord
     * @param mixed $userId ID
     * @param mixed $password password
     * @param mixed $msg msg
     * @return mixed 返回验证结果
     */
    public function checkRepeatPassWord($userId, $password, &$msg) {
        $userLogsModel = app::get('desktop')->model('user_logs');
        $limitTimes = 8;
        $oldRows = $userLogsModel->getList('operation_detail', array('obj_id'=>$userId, 'operation_type'=>array(1,4)), 0, $limitTimes, 'log_id desc');
        foreach ($oldRows as $v) {
            $opDetail = unserialize($v['operation_detail']);
            if($password == $opDetail['pam_account']['login_password']) {
                $msg = '密码不能与近'.$limitTimes.'次相同';
                return false;
            }
        }
        return true;
    }

    /**
     * 检查Password
     * @param mixed $password password
     * @param mixed $errmsg errmsg
     * @return mixed 返回验证结果
     */
    public function checkPassword($password, &$errmsg) {
        $errmsg = '';

        $len = 12;

        // 基础正则表达式验证
        if (!preg_match("/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{{$len},}$/", $password)) {
            $errmsg = '密码必须包含英文数字大小写且至少'.$len.'位';
            return false;
        }

        // 检查是否有递增或递减序列
        $length = strlen($password);
        for ($i = 0; $i < $length - 2; $i++) {
            // 检查数字
            if (ctype_digit(substr($password, $i, 3)) && (
                ((int)$password[$i] + 1 == (int)$password[$i + 1]) &&
                ((int)$password[$i] + 2 == (int)$password[$i + 2]) ||
                ((int)$password[$i] - 1 == (int)$password[$i + 1]) &&
                ((int)$password[$i] - 2 == (int)$password[$i + 2])
            )) {
                $errmsg = '密码不能包含递增或递减的数字';
                return false;
            }

            // 检查字母
            if (ctype_alpha(substr($password, $i, 3)) && (
                (ord($password[$i]) + 1 == ord($password[$i + 1])) &&
                (ord($password[$i]) + 2 == ord($password[$i + 2])) ||
                (ord($password[$i]) - 1 == ord($password[$i + 1])) &&
                (ord($password[$i]) - 2 == ord($password[$i + 2]))
            )) {
                $errmsg = '密码不能包含递增或递减的字母';
                return false;
            }
        }

        return true;
    }

    /**
     * 获取_organization_permission
     * @return mixed 返回结果
     */
    public function get_organization_permission(){
        $operationOpsObj = app::get('ome')->model('operation_ops');
        $ops = array();

        if(!$this->is_super()){
            $operationOpsInfo = $operationOpsObj->getList('org_id', array('op_id' => $this->user_id), 0, -1);
            if($operationOpsInfo){
                foreach($operationOpsInfo as $operationOp){
                    $ops[] = $operationOp['org_id'];
                }
            }
        }
        return $ops;
    }

    
    /**
     * 是否强制重置密码
     * 
     * @return void
     * @author 
     */
    public function isForceResetPwd($accountId)
    {
        $cycle = app::get('ome')->getConf('desktop.password.reset.cycle');
        if ($cycle) {
            // 获取上次修改密码时间
            $log = app::get('desktop')->model('user_logs')->getList('operation_time',array('obj_id'=>$accountId),0,1,'log_id desc');

            switch ($cycle) {
                case 'week':
                    if ( strtotime('-1 week') > intval($log[0]['operation_time'])) return true;

                    break;
                case 'month':
                    if (strtotime('-1 month') > intval($log[0]['operation_time'])) return true;
                    break;
            }
        }

        return false;
    }

    /**
     * 锁定操作员
     * @return void 
     */
    public function lockUser() {
        $userLimit = app::get('ome')->getConf('desktop.account.use.limit');
        if($userLimit != 'true') {
            return;
        }
        $lastModify = time() - 86400;
        $lastLogin = time() - 90 * 86400;
        $filterSql = 'last_modify < ' . $lastModify . ' and lastlogin != 0 and lastlogin < ' . $lastLogin;
        $filterSql = '('.$filterSql.') or (lastlogin = 0 and last_modify < ' . $lastLogin .')';
        $rows = app::get('desktop')->model('users')->getList('user_id',['filter_sql'=>$filterSql]);
        if($rows) {
            app::get('desktop')->model('users')->update(['is_lock'=>'1','lock_reason'=>'90天未登录'],['user_id'=>array_column($rows, 'user_id')]);
        }
    }
}
