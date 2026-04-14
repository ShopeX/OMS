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


class desktop_ctl_appmgr extends desktop_controller{

    var $workground = 'desktop_ctl_dashboard';
    var $require_super_op = true;

    /** @var int GET data 最大长度，防止超大序列化串消耗资源 */
    private static $appmgr_data_max_len = 65536;

    public function __construct(&$app) 
    {
        if(defined('WITHOUT_DESKTOP_APPMGR') && constant('WITHOUT_DESKTOP_APPMGR')){
            die(app::get('desktop')->_('APP管理已被禁用'));
        }
        parent::__construct($app);
        if(!$this->user->is_super()){
        	echo "您无权操作";
        	exit;
        }
    }//End Function

    public function index(){
        $this->finder('base_mdl_apps',array(
            'title'=>app::get('desktop')->_('应用程序管理'),
            'actions'=>array(
                array(
                    'label'=>app::get('desktop')->_('检查更新'),
                    'icon'=>'afresh.gif',
                    'href'=>"index.php?ctl=appmgr&act=fetchindex",
                    'target'=>'command::{title:\''.app::get('desktop')->_('检查更新').'\'}'
                ),
            ),
            'base_filter'=>array('normalview'=>true),
            'use_buildin_recycle'=>false,
            'use_buildin_selectrow'=>false,
            'use_view_tab'=>true,
       ));
    }

  public function _views(){
    $app = app::get('base')->model('apps');
    
    return array(
        0=>array('label'=>app::get('desktop')->_('全部'),'optional'=>false,'filter'=>null,'addon'=>$app->count(array('normalview'=>true))),
        1=>array('label'=>app::get('desktop')->_('已安装'),'optional'=>false,'filter'=>array('installed'=>true),'addon'=>$app->count(array('normalview'=>true,'installed'=>true))),
        2=>array('label'=>app::get('desktop')->_('未安装'),'optional'=>false,'filter'=>array('installed'=>false),'addon'=>$app->count(array('normalview'=>true,'installed'=>false)))
           );
    
    
  }
    
  /*  public function browser(){
        $this->finder('base_mdl_apps',array(
            'base_filter'=>array('installed'=>false),
            'title'=>app::get('desktop')->_('应用程序'),'actions'=>array(
                //array('label'=>'安装选中的应用','icon'=>'add.gif','submit'=>'index.php?ctl=appmgr&act=install_app','target'=>'command::'),

                array('label'=>app::get('desktop')->_('已安装的应用程序'),'href'=>'index.php?ctl=appmgr&act=index'),
                array('label'=>app::get('desktop')->_('检查更新'),'icon'=>'afresh.gif','href'=>'index.php?ctl=appmgr&act=fetchindex','target'=>'command::{title:\''.app::get('desktop')->_('检查更新').'\'}'),
            ),'use_buildin_recycle'=>false));

    }
        */
    function prepare(){
        if(method_exists($this,'prepare_'.$_POST['action'])){
            $prepare_result = $this->{'prepare_'.$_POST['action']}($_POST['app_id']);
            foreach($prepare_result['queue'] as $k=>$queue){
                $prepare_result['queue'][$k]['data'] = serialize($queue['data']);
            }
            echo json_encode($prepare_result);
        }
    }
    
    public function command(){
        if(kernel::single('base_setup_lock')->lockfile_exists()){
            echo "\nError: ".app::get('desktop')->_('系统已安装，禁止通过此接口执行应用命令。');
            return;
        }
        if(!isset($_GET['command_id']) || !method_exists($this,'command_'.$_GET['command_id'])){
            return;
        }
        $data = $this->_appmgr_unserialize_get_data('string');
        if($data === false){
            echo "\nError: invalid data.";
            return;
        }
        $this->{'command_'.$_GET['command_id']}($data);
        echo "\nok.";
    }
    
    public function maintenance(){
        kernel::single('base_shell_webproxy')->exec_command('update');
    }
    
    public function fetchindex(){
        kernel::single('base_shell_webproxy')->exec_command('update --sync-only');
    }

    private function prepare_install($app_id){
        $depends_install = app::get('desktop')->_("以下应用将被安装, 是否继续?")."\n";
        $install_queue = kernel::single('base_application_manage')->install_queue(array($app_id));
        if(kernel::single('base_application_manage')->has_conflict_apps(array_keys($install_queue), $conflict_info)){
            foreach($conflict_info AS $conflict_app_id=>$conflict_detail){
                $conflict_app_info = app::get($conflict_app_id)->define();
                $conflict_message .= (($conflict_app_id==$app_id||app::get($conflict_app_id)->status()!='uninstalled')?$conflict_app_info['name']:$conflict_app_info['name'].app::get('desktop')->_("(被依赖)")) . ' ' . app::get('desktop')->_('与') . ' ' . $conflict_detail['name'] . ' ' . app::get('desktop')->_('存在冲突') . "\n";
            }
            return array(
                    'status' => 'alert',
                    'message' => $conflict_message . app::get('desktop')->_('请手工卸载冲突应用'),
                    'queue' => array(),
                );
        }//todo：安装时判断app冲突，检测包括所有依赖的app和现有安装app之间的冲突
        $queue = array();
        $download_queue = array();
        foreach($install_queue as $queue_app_id=>$appinfo){
            $depends_install .= "\t".($queue_app_id==$app_id?$appinfo['name']:str_pad($appinfo['name'],20)."\t".app::get('desktop')->_("(被依赖)"))."\n";
            if(!file_exists(APP_DIR.'/'.$queue_app_id.'/app.xml') && !file_exists(CUSTOM_CORE_DIR.'/'.$queue_app_id.'/app.xml')){
                $download_queue[] = array('type'=>'command','command_id'=>'download','data'=>$queue_app_id);                
            }
            $queue[] = array('type'=>'command','name'=>$appinfo['name'],'command_id'=>'install','data'=>$queue_app_id);
        }
        
        if($queue){
            array_unshift($queue,array('type'=>'dialog','action'=>'install_options','data'=>array_keys($install_queue)));
        }
        
        if($download_queue){
            $queue = array_merge($download_queue,$queue);
        }
        
        $return = array(
                'status' => 'confirm',
                'message' => $depends_install,
                'queue' => $queue
            );    
        return $return;
    }
    
    public function install_options(){
        $apps = $this->_appmgr_unserialize_get_data('array');
        if($apps === false){
            return;
        }
        $rows = app::get('base')->model('apps')->getList('app_id,app_name',array('app_id'=>$apps));
        foreach($rows as $r){
            $apps_name[$r['app_id']] = $r['app_name'];
        }
        foreach($apps as $app_id){
            $option = app::get($app_id)->runtask('install_options');
            if(is_array($option) && count($option)>0){
                $install_options[$app_id] = $option;
            }
        }
        $this->pagedata['install_options'] = &$install_options;
        $this->pagedata['apps_name'] = &$apps_name;
        $this->display('appmgr/install.html');
    }
    
    public function app_console(){
		$this->pagedata['base_url'] = kernel::base_url();
        $this->display('appmgr/console.html');
    }
    
    private function command_install($app_id){
        $shell = kernel::single('base_shell_webproxy');
        $shell->input = $_POST['options'];
        $shell->exec_command('install '.$app_id);
    }
     
    private function prepare_uninstall($app_id){
        $depends_uninstall = app::get('desktop')->_("以下应用将被删除, 是否继续?")."\n";
        $uninstall_queue = kernel::single('base_application_manage')->uninstall_queue(array($app_id));
        $queue = array();
        foreach($uninstall_queue as $queue_app_id=>$appinfo){
            $depends_uninstall .= "\t".$appinfo[0].' '.($appinfo[1]?"\t".app::get('desktop')->_("(依赖)"):'')."\n";
            $queue[] = array('type'=>'command','name'=>$appinfo[0],'command_id'=>'uninstall','data'=>$queue_app_id);
        }
        
        if($queue){  //追加备份提示
            array_unshift($queue,array('type'=>'dialog','action'=>'uninstall_bakup','data'=>''));
        }
        
        $return = array(
                'status' => 'confirm',
                'message' => $depends_uninstall,
                'queue' => $queue
            );    
        return $return;
    }

    private function prepare_pause($app_id) 
    {
        $depends_pause = app::get('desktop')->_("以下应用将被停用, 是否继续?")."\n";
        $pause_queue = kernel::single('base_application_manage')->pause_queue(array($app_id));
        $queue = array();
        foreach($pause_queue as $queue_app_id=>$appinfo){
            $depends_pause .= "\t".$appinfo[0].' '.($appinfo[1]?"\t".app::get('desktop')->_("(依赖)"):'')."\n";
            $queue[] = array('type'=>'command','name'=>$appinfo[0],'command_id'=>'pause','data'=>$queue_app_id);
        }
                
        $return = array(
                'status' => 'confirm',
                'message' => $depends_pause,
                'queue' => $queue
            );    
        return $return;
    }//End Function
    
    private function prepare_active($app_id) 
    {
        $depends_active = app::get('desktop')->_("以下应用将被启用或安装, 是否继续?")."\n";
        $active_queue = kernel::single('base_application_manage')->active_queue(array($app_id));
        if(kernel::single('base_application_manage')->has_conflict_apps(array_keys($active_queue), $conflict_info)){
            foreach($conflict_info AS $conflict_app_id=>$conflict_detail){
                $conflict_app_info = app::get($conflict_app_id)->define();
                $conflict_message .= (($conflict_app_id==$app_id||app::get($conflict_app_id)->status()!='uninstalled')?$conflict_app_info['name']:$conflict_app_info['name'].app::get('desktop')->_("(被依赖)")) . ' ' . app::get('desktop')->_('与') . ' ' . $conflict_detail['name'] . ' ' . app::get('desktop')->_('存在冲突') . "\n";
            }
            return array(
                    'status' => 'alert',
                    'message' => $conflict_message . app::get('desktop')->_('请手工卸载冲突应用'),
                    'queue' => array(),
                );
        }//todo：安装时判断app冲突，检测包括所有依赖的app和现有安装app之间的冲突
        $queue = array();
        $download_queue = array();
        foreach($active_queue as $queue_app_id=>$appinfo){
            $depends_active .= "\t".($queue_app_id==$app_id?$appinfo['name']:str_pad($appinfo['name'],20)."\t".app::get('desktop')->_("(被依赖)"))."\n";
            if(!file_exists(APP_DIR.'/'.$queue_app_id.'/app.xml') && !file_exists(CUSTOM_CORE_DIR.'/'.$queue_app_id.'/app.xml')){
                $download_queue[] = array('type'=>'command','command_id'=>'download','data'=>$queue_app_id);                
                $queue[] = array('type'=>'command','name'=>$appinfo['name'],'command_id'=>'install','data'=>$queue_app_id);
            }elseif(app::get($queue_app_id)->status() == 'paused'){
                $queue[] = array('type'=>'command','name'=>$appinfo['name'],'command_id'=>'active','data'=>$queue_app_id);
            }else{
                $queue[] = array('type'=>'command','name'=>$appinfo['name'],'command_id'=>'install','data'=>$queue_app_id);
            }
        }
        
        if($queue){
            array_unshift($queue,array('type'=>'dialog','action'=>'install_options','data'=>array_keys($install_queue)));
        }
        
        if($download_queue){
            $queue = array_merge($download_queue,$queue);
        }
        
        $return = array(
                'status' => 'confirm',
                'message' => $depends_active,
                'queue' => $queue
            );    
        return $return;
    }//End Function

    public function uninstall_bakup() {
        $this->display('system/backup/check.html');
    }

    private function command_pause($app_id) 
    {
        kernel::single('base_shell_webproxy')->exec_command('pause '.$app_id);
    }//End Function
    
    private function command_active($app_id) 
    {
        kernel::single('base_shell_webproxy')->exec_command('active '.$app_id);
    }//End Function

    private function command_uninstall($app_id){
        if( $_POST['bakup'] ) {
            kernel::single('desktop_system_backup')->uninstall_backup($app_id);
        }
        kernel::single('base_shell_webproxy')->exec_command('uninstall '.$app_id);
    }
    
    private function command_download($app_id){
        kernel::single('base_shell_webproxy')->exec_command('update --force-download --download-only '.$app_id);
    }
    
    private function command_update($app_id){
        kernel::single('base_shell_webproxy')->exec_command('update '.$app_id);
    }
    
    /* start/stop
    private function prepare_start($app_id){
        $return = array(
                'queue' => array(
                    array('type'=>'command','command_id'=>'start'),
                    )
            );
        return $return;
    }
    
    private function prepare_stop($app_id){
        $return = array(
                'queue' => array(
                    array('type'=>'command','command_id'=>'stop'),
                    )
            );
        return $return;
    }
    */
   
    private function prepare_download($app_id){
        $return = array(
                'queue' => array(
                    array('type'=>'command','name'=>$app_id,'command_id'=>'download','data'=>$app_id),
                    )
            );
        return $return;
    }
    
    private function prepare_update($app_id){
        $return = array(
                'queue' => array(
                    array('type'=>'command','name'=>$app_id,'command_id'=>'update','data'=>$app_id),
                    )
            );
        return $return;
    }

    /**
     * 安全反序列化 $_GET['data']：禁止实例化类，防止对象注入 gadget（如 CNVD 通报链）。
     *
     * @param string $expect string=单行 command 的 app_id；array=install_options 的 app_id 列表
     * @return string|array|false
     */
    private function _appmgr_unserialize_get_data($expect){
        if(!isset($_GET['data']) || !is_string($_GET['data'])){
            return false;
        }
        $raw = $_GET['data'];
        if($raw === '' || strlen($raw) > self::$appmgr_data_max_len){
            return false;
        }
        $data = @unserialize($raw, array('allowed_classes' => false));
        if($expect === 'string'){
            if(!is_string($data) || $data === ''){
                return false;
            }
            if(!$this->_appmgr_is_valid_app_id($data)){
                return false;
            }
            return $data;
        }
        if($expect === 'array'){
            if(!is_array($data) || empty($data)){
                return false;
            }
            foreach($data as $app_id){
                if(!$this->_appmgr_is_valid_app_id($app_id)){
                    return false;
                }
            }
            return $data;
        }
        return false;
    }

    /**
     * app_id 白名单校验（目录名级，禁止路径与控制字符）
     */
    private function _appmgr_is_valid_app_id($app_id){
        if(!is_string($app_id) || $app_id === '' || strlen($app_id) > 64){
            return false;
        }
        return (bool)preg_match('/^[a-zA-Z0-9_\\-]+$/', $app_id);
    }

}
