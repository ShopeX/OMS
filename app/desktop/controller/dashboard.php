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
 
class desktop_ctl_dashboard extends desktop_controller{

    var $workground = 'desktop_ctl_dashboard';

    public function __construct($app)
    {
        parent::__construct($app);
        //$this->member_model = $this->app->model('members');
        header("cache-control: no-store, no-cache, must-revalidate");
    }
    
    function index(){
        //如果没有请求到证书，可以重新请求
        if (!base_certificate::certi_id()|| !base_certificate::token()){
            base_certificate::register();
        }

        if (!base_shopnode::node_id('ome')&&base_certificate::certi_id()&&base_certificate::token()){
            $obj_buildin = kernel::single('base_shell_buildin');
            $obj_buildin->command_active_node_id('ceti_node_id');
        }

        /* node.update 接口 start */
        $nodeUpdateKey = 'ome_node_update'.base_shopnode::node_id('ome');
        $nodeUpdate = app::get('ome')->getConf($nodeUpdateKey);
        if(!$nodeUpdate || $nodeUpdate != 'true'){
            $certi_id = base_certificate::certi_id();
            $token = base_certificate::token();
            $node_id = base_shopnode::node_id('ome');

            if(!empty($certi_id) && !empty($token) && !empty($node_id)){
                  base_certificate::update_node();
            }
        }
        /* node.update 接口 end */

        $this->pagedata['tip'] = base_application_tips::tip(); 
        $user = kernel::single('desktop_user');
        $is_super = $user->is_super();

        $group = $user->group();
        $group = (array)$group;
        
        //桌面挂件排序，用户自定义
        //$user->get_conf( 'arr_dashboard_sort',$arr_dashboard_sort );

        foreach(kernel::servicelist('desktop.widgets') as $key => $obj){ 
            if($is_super || in_array(get_class($obj),$group)){    
                $class_full_name = get_class($obj); 
                $key = $obj->get_width();
                $tmp = array(
                    'title'=>$obj->get_title(),
                    'html'=>$obj->get_html(),
                    'width'=>$obj->get_width(),
                    'className'=>$obj->get_className(),
                    'class_full_name' => $class_full_name,
                    );
                foreach( (array)$arr_dashboard_sort as $__dashboard_sort_key => $__dashboard_sort ) {
                    if( is_array($__dashboard_sort) && (false!==($hk=array_search($class_full_name,$__dashboard_sort))) ) {
                        $sort_with[$__dashboard_sort_key][] = $hk;
                        $key = $__dashboard_sort_key;
                        $continue = true;
                        break;
                    }
                }
                if( !$continue ) $sort_with[$key][] = $obj->order?$obj->order:1;
                $widgets[$key][] = $tmp;
            }
        }
        foreach((array)$widgets as $key=>$arr){
            array_multisort($sort_with[$key], SORT_ASC,$arr);
            $widgets[$key] = $arr;
        }
        
        $this->pagedata['widgets_1'] = $widgets['l-1'];
        $this->pagedata['widgets_2'] = $widgets['l-2'];
        $this->pagedata['widgets_3'] = $widgets['l-3'];

        $deploy = kernel::single('base_xml')->xml2array(file_get_contents(ROOT_DIR.'/config/deploy.xml'),'base_deploy');
        $this->pagedata['deploy'] = $deploy;
        
        $this->pagedata['dashboard_sort_url'] = $this->app->router()->gen_url( array('app'=>'desktop','ctl'=>'dashboard','act'=>'dashboard_sort') );
        $this->page('dashboard.html');
    }
    
    /*
     * 桌面排序
     * 桌面挂件排序，用户自定义
     */
    public function dashboard_sort( )
    {
        $desktop_user = kernel::single('desktop_user');
        $arr = explode(' ',trim($_POST['sort']));
        $conf = array();
        if( $arr && is_array($arr) ) {
            foreach( $arr as $value ) {
                if( !($hk=strpos($value,':')) ) continue;
                $key = substr($value,0,$hk);
                $conf[$key] = explode(',',substr($value,($hk+1)));
            }
        }
        $desktop_user->set_conf( 'arr_dashboard_sort',$conf );
    }
    #End Func
    
    
    function advertisement(){
        $conf = base_setup_config::deploy_info();
        $this->pagedata['product_key'] = $conf['product_key'];        
        $this->pagedata['cross_call_url'] =base64_encode( kernel::single('base_component_request')->get_full_http_host().$this->app->base_url().
        'index.php?ctl=dashboard&act=cross_call'
        );
        
        $this->display('advertisement.html');
    }
    
    function cross_call(){
        header('Content-Type: text/html;charset=utf-8');
        echo '<script>'.str_replace('top.', 'parent.parent.', base64_decode($_REQUEST['script'])).'</script>';
    }


    function appmgr() {
        $arr = app::get('base')->model('apps')->getList('*', array('status'=>'active'));
        foreach( $arr as $k => $row ) {
            if( $row['remote_ver'] <= $row['local_ver'] ) unset($arr[$k]);
        }
        $this->pagedata['apps'] = $arr;
       
        $this->display('appmgr/default_msg.html');
        
        
    }
    
    
    
    function fetch_tip(){
        echo $this->pagedata['tip'] = base_application_tips::tip();
    }

    function profile(){

        //获取该项记录集合
        $users = $this->app->model('users');
        $roles=$this->app->model('roles');
        $workgroup=$roles->getList('*');
        $sdf_users = $users->dump($this->user->get_id());
        
        if($_POST){
            $this->user->set_conf('desktop_theme',$_POST['theme']);
            $this->user->set_conf('timezone',$_POST['timezone']);
             header('Content-Type:text/jcmd; charset=utf-8');
             echo '{success:"'.app::get('desktop')->_("设置成功").'",_:null}';
             exit;
        }

        $themes = array();
        foreach(app::get('base')->model('app_content')
            ->getList('app_id,content_name,content_path'
        ,array('content_type'=>'desktop theme')) as $theme){
            $themes[$theme['app_id'].'/'.$theme['content_name']] = $theme['content_name'];
        }

        //返回无内容信息
        $this->pagedata['themes'] = $themes;
        
        $this->pagedata['current_theme'] = $this->user->get_theme();

        $this->pagedata['name'] = $sdf_users['name'];
        $this->pagedata['super'] = $sdf_users['super'];
        $this->display('users/profile.html');
    }

    ##非超级管理员修改密码
    function chkpassword(){
        $userLib = kernel::single('desktop_user');
        $users = $this->app->model('users');
        $account_id = $this->user->get_id();
        $sdf = $users->dump($account_id,'*',array( ':account@pam'=>array('*'),'roles'=>array('*') ));
        $old_password = $sdf['account']['login_password'];
        $filter['account_id'] = $account_id;
        $filter['account_type'] = pam_account::get_account_type($this->app->app_id);
        $filter['login_password'] = pam_encrypt::get_encrypted_password(trim($_POST['old_login_password']),pam_account::get_account_type($this->app->app_id));
         $pass_row = app::get('pam')->model('account')->getList('account_id',$filter);
        if($_POST){
            $this->begin();
            $loginPassword = pam_encrypt::get_encrypted_password(trim($_POST['new_login_password']),pam_account::get_account_type($this->app->app_id));
            if(!$pass_row){
                $this->end(false, app::get('desktop')->_('原始密码不正确'));
            }elseif(trim($_POST['new_login_password'])==trim($_POST['old_login_password'])){
                $this->end(false, app::get('desktop')->_('新密码和旧密码需要不一致'));
            }elseif($_POST['new_login_password']!=$_POST[':account@pam']['login_password']){
                $this->end(false, app::get('desktop')->_('两次密码不一致'));
            }elseif(!$userLib->checkRepeatPassWord($account_id, $loginPassword, $error_msg)){
                $this->end(false, app::get('desktop')->_($error_msg));
            }elseif(!$userLib->checkUserPassWordLength($account_id, $_POST['new_login_password'], $error_msg)){
                $this->end(false, app::get('desktop')->_($error_msg));
            }elseif(!$userLib->validPassWord($_POST['new_login_password'], $error_msg,$sdf['name'])){
                $this->end(false, app::get('desktop')->_($error_msg));
            }elseif(!$userLib->checkPassword($_POST['new_login_password'], $error_msg)){
                $this->end(false, app::get('desktop')->_($error_msg));
            }else{
                $_POST['pam_account']['account_id'] = $account_id;
                $_POST['pam_account']['login_password'] = $loginPassword;
                $users->save($_POST);
                
                $desktop_users = app::get('desktop')->model('users');
                $sdf['modifyip'] = $_SERVER["REMOTE_ADDR"];
                $desktop_users->update($sdf,array('user_id'=>$account_id));
                //echo "密码修改成功";
                $userLib->checkUpdatePwd($_POST['pam_account']['account_id'], true);
                //新增插入日志
                $this->app->model('user_logs')->changePwd($account_id);
    
                // IDAAS
                list($rs,$msg) = kernel::single('desktop_user_auth')->sync_account(array(
                    'account_id'        => $_POST['pam_account']['account_id'],
                    'login_name'        => $sdf['account']['login_name'],
                    'login_password'    => $_POST['pam_account']['login_password'],
                ),'password');
                if ($rs === false) {
                    $this->end(false,$msg);
                }
    
                $this->end(true, app::get('desktop')->_('密码修改成功'));

            }
        }
        $this->page('chkpass.html');
    }
    
     function redit(){
        $desktop_user = kernel::single('desktop_user');
        if($desktop_user->is_super()){
            $this->redirect('index.php?ctl=adminpanel');
        }
        else{
            $aData = $desktop_user->get_work_menu();
            $aMenu = $aData['menu'];
            foreach($aMenu as $val){
                foreach($val as $value){
                    foreach($value as $v){
                        if($v['display']==='true'){
                            $url = $v['menu_path'];break;
                        }  
                    }
                    break;
                }
                break;
            }
            if(!$url) $url = "ctl=adminpanel";
            $this->redirect('index.php?'.$url);
        }
    }
    
    public function get_license_html()
    {
        $this->display('license.html');
    }
    
    public function application(){
        $certificate = kernel::single('base_certificate');
        if($certificate->register()===false)
        {
            header('Content-Type:text/jcmd; charset=utf-8');
            echo '{error:"'.app::get('desktop')->_("申请失败").'",_:null}';
            //$this->end(false,app::get('desktop')->_('申请失败'));
        }
        else
        {
            header('Content-Type:text/jcmd; charset=utf-8');
            echo '{success:"'.app::get('desktop')->_("申请成功").'",_:null}';
            //$this->end(true,app::get('desktop')->_('申请成功'));
        }
    }

    /**
     * 完善帐号信息
     *
     * @return void
     * @author 
     **/
    public function perfectAccount()
    {
        if ($_SESSION['needChangePassword']) {$this->chkpassword();exit;}

        $html = '<h4>安全升级</h4>此账号对应手机号为空，请输入您可以接受短信手机号码:';

        $fields = array(
            array('title'=>app::get('desktop')->_('手机号'), 'type'=>'text', 'name'=>'mobile', 'required'=>true, 'vtype'=>'unsignedint', 'length'=>'11'),
        );

        if ($_POST) {
            $this->begin('index.php?app=desktop&ctl=dashboard&act=index');
            $data = array();
            foreach ($fields as $field) {
                $value = trim($_POST[$field['name']]);
                if ($field['required'] && empty($value)) $this->end(false,$field['title'].'不能为空');
                if ($field['vtype']=='unsignedint' && !is_numeric($value)) $this->end(false,$field['title'].'必须为数值型');
                if (isset($field['length']) && $field['length'] != strlen($value)) $this->end(false,$field['title'].'长度必须'.$field['length'].'位');

                $data[$field['name']] = $value;
            }

            if ($data) {
                $affect_rows = app::get('desktop')->model('users')->update($data,array('user_id'=>$this->user->get_id()));

                if ($affect_rows) {
                    // IDAAS
                    $userMdl = app::get('desktop')->model('users');
                    $user_data = $userMdl->dump($this->user->get_id(),'*',array( ':account@pam'=>array('*') ));
                    list($rs,$msg) = kernel::single('desktop_user_auth')->sync_account(array(
                        'account_id'        => $user_data['account']['account_id'],
                        'login_name'        => $user_data['account']['login_name'],
                        'login_password'    => $user_data['account']['login_password'],
                    ),'update');
                    if ($rs === false) {
                        $this->end(false,$msg);
                    }
                    cachecore::delete(sprintf("account_user_%s",$this->user->get_id()));
                }
            }
            $this->end(true, app::get('desktop')->_('操作成功'));
        }

        $ui= new base_component_ui($this);
        $html .= $ui->form_start(array('method' => 'POST','isCloseDialog'=>true));
        foreach($fields as  $field){
            $html .= $ui->form_input($field); 
        }
        $html .= $ui->form_end();
        $html .= '<span style="color:red;">*如需要修改，请联系管理员，修改操作员档案</span>';
        echo $html;exit;
    }

}
