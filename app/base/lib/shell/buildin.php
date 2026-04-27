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


class base_shell_buildin extends base_shell_prototype{

    var $vars;

    var $command_alias = array(
            'ls'=>'list',
            'q'=>'exit',
            'quit'=>'exit',
        );

    function php_call(){
        if($this->vars) extract($this->vars);
        $this->output(eval(func_get_arg(0)));
        $this->vars = get_defined_vars();
    }

    function command_reset(){ 
        $this->cmdlibs = null;
        $this->vars = null;
    }

    var $command_help_options = array(
            'verbose'=>array('title'=>'显示详细信息','short'=>'v'),
        );

    function command_help($help_item=null,$shell_command=null){
        if($help_item){
            list($app_id,$package) = explode(':',$help_item);
            $this->app_help($app_id , $package ,$shell_command);
        }else{
            $this->help();
            $this->output_line(app::get('base')->_('应用提供的命令：'));
            if ($handle = opendir(APP_DIR)) {
                while (false !== ($app_id = readdir($handle))) {
                    if($app_id[0]!='.' && is_dir(APP_DIR.'/'.$app_id) && is_dir(APP_DIR.'/'.$app_id.'/lib/command')){
                        $this->app_help($app_id);
                    }
                }
                closedir($handle);
            }
            $this->output_line(app::get('base')->_('原生php命令'));
            $____params=app::get('base')->_('输入命令如果以分号[;]结尾，则被认为是一条php语句.  例如:');
            echo <<<EOF
$____params
  1> \$a = 2;
     int(2)
  2> pow(\$a,8);
     int(256)

EOF;
        }
    }

    function app_help($app_id,$package=null,$command=false){
        if($package){
            $commander = $this->shell->get_commander($app_id,$package);
            $commander->help($command);
        }else{
            if ($handle = opendir(APP_DIR.'/'.$app_id.'/lib/command')) {
                while (false !== ($file = readdir($handle))) {
                    if (substr($file,-4,4)=='.php' && is_file(APP_DIR.'/'.$app_id.'/lib/command/'.$file)) {
                        $commander = $this->shell->get_commander($app_id,substr($file,0,-4));
                        if($commander){
                            $commander->help();
                        }
                    }
                }
                closedir($handle);
            }
        }
    }

    function name_prefix(){
        return '';
    }

    var $command_exit = '退出';
    function command_exit(){ 
        echo 'exit'; 
        exit;
    }

    var $command_man = '显示帮助';
    function command_man(){
        $args = func_get_args();
        foreach($args as $arg){
            kernel::single('base_misc_man')->show($arg);
        }
    }

    var $command_sh = '执行操作系统命令';
    function command_sh($args){
        eval('system("'.str_replace('"','\\"',implode(' ',$args)).'");');
    }

    var $command_mkconfig = '创建config文件';
    var $command_mkconfig_options = array(
            'dbhost'=>array('title'=>'数据库服务器，默认:localhost','short'=>'h','need_value'=>1),
            'dbpassword'=>array('title'=>'数据库密码','short'=>'p','need_value'=>1),
            'dbuser'=>array('title'=>'数据库用户名','short'=>'u','need_value'=>1),
            'dbname'=>array('title'=>'数据库名','short'=>'n','need_value'=>1),
            'dbprefix'=>array('title'=>'数据库前缀, 默认sdb_','short'=>'a','need_value'=>1),
            'timezone'=>array('title'=>'时区','short'=>'t','need_value'=>1),
        );
    function command_mkconfig(){
            $options = $this->get_options();
            $options = array(
                'db_host'=>$options['dbhost']?$options['dbhost']:'localhost',
                'db_user'=>$options['dbuser']?$options['dbuser']:'root',
                'db_password'=>$options['dbpassword'],
                'db_name'=>$options['dbname'],
                'db_prefix'=>$options['dbprefix']?$options['dbprefix']:'sdb_',
                'default_timezone'=>$options['timezone'],
                );
            kernel::single('base_setup_config')->write($options);
    }

    var $command_ls = '列出所有应用';
    function command_ls(){ 
        $rows = app::get('base')->model('apps')->getlist('*',array('installed'=>true));
        foreach($rows as $k=>$v){
            $rows[$k] = array(
                    'app_id'=>$v['app_id'],
                    'name'=>$v['name'],
                    'version'=>$v['version'],
                    'status'=>$v['status']?$v['status']:'uninstalled',
                );
        }
        $this->output_table( $rows );
    }

    var $command_cd = '切换当前应用';
    function command_cd($app=''){

        if($app=='..'){
            $app = '';
        }elseif($app=='-'){
            $app = $this->last_app_id;
        }

        if($app[0]!='.' && is_dir(APP_DIR.'/'.$app)){
            $this->last_app_id = $this->shell->app_id;
            $this->shell->app_id = $app;
        }else{
            throw new Exception($app.": No such application.\n");
        }
    }

    var $command_install = '安装应用';
    var $command_install_options = array(
               'reset'=>array('title'=>'重新安装','short'=>'r'),
                'options'=>array('title'=>'参数','short'=>'o','need_value'=>1),
            );
    function command_install(){
        $args = func_get_args();
        $options = $this->get_options();
        $install_queue = kernel::single('base_application_manage')->install_queue($args,$options['reset']);
        $this->install_app_by_install_queue($install_queue, $options);
    }
	
	/**
	 * 安装应用过程
	 * @param array intall queue
	 * @param array setup options 
	 * @return null
	 */
	private function install_app_by_install_queue($install_queue, $options=array())
	{
		if(!$install_queue){
            return ;
        }

        if(kernel::single('base_application_manage')->has_conflict_apps(array_keys($install_queue), $conflict_info)){
            foreach($conflict_info AS $conflict_app_id=>$conflict_detail){
                $conflict_app_info = app::get($conflict_app_id)->define();
                $conflict_message .= $conflict_app_info['name'] . ' ' . app::get('base')->_('与') . ' ' . $conflict_detail['name'] . ' ' . app::get('base')->_('存在冲突') . "\n";
            }
            kernel::log($conflict_message . app::get('base')->_('请手工卸载冲突应用')."\n");
            return false;
        }//todo：安装时判断app冲突，检测包括所有依赖的app和现有安装app之间的冲突

        if($options['options']){
            parse_str($options['options'],$this->shell->input);
        }
        
        foreach((array)$install_queue as $app_id=>$app_info){
            if(!$app_info){
                kernel::log(app::get('base')->_('无法找到应用').$app_id."\n");
                return false;
            }
            if(!kernel::single('base_setup_lock')->lockfile_exists()){
                kernel::single('base_setup_lock')->write_lock_file(false);
            }//todo: 生成setup lock文件
            $install_options = app::get($app_id)->runtask('install_options');
            if(is_array($install_options) && count($install_options)>0 && !$this->shell->input[$app_id]){
                do{
                    $this->shell->input_option($install_options,$app_id);
                }while(app::get($app_id)->runtask('checkenv',$this->shell->input[$app_id])===false);
            }
            kernel::single('base_application_manage')->install($app_id,$this->shell->input[$app_id]);
        }
	}
	
	/**
	 * 安装整个产品的方法
	 * @param string 产品名称
	 * @return null
	 */
	var $command_install_product = "安装产品";
	var $command_install_product_options = array(
	   'reset'=>array('title'=>'重新安装','short'=>'r'),
		'options'=>array('title'=>'参数','short'=>'o','need_value'=>1),
	);
	function command_install_product(){
		$args = func_get_args();
		$options = $this->get_options();
		$config = base_setup_config::deploy_info();
		//if (!$args[0] || $args[0] != $config['product_name']) echo app::get('base')->_('产品名称无法找到！');				
		
		foreach($config['package']['app'] as $k=>$app){
            $applist[] = $app['id'];
        }
                
        $applist = kernel::single('base_application_manage')->install_queue($applist);
		$this->install_app_by_install_queue($applist, $options);		
		
		$this->command_install_demodata('demodata');

		try {
			kernel::single('entermembercenter_stat')->reportInstall();
		} catch (Throwable $e) {
			// 安装统计上报失败不阻断 install_product
		}
	}
	
	var $command_install_demodata = "安装初始化数据";
	var $command_install_demodata_options = array(
	   'reset'=>array('title'=>'重新安装','short'=>'r'),
		'options'=>array('title'=>'参数','short'=>'o','need_value'=>1),
	);
	function command_install_demodata($app_id='demodata'){
		/** 所有app安装完成后执行安装demo数据 **/
		$args = func_get_args();
		$options = $this->get_options();
		$config = base_setup_config::deploy_info();
		
		if($options['options']){
            parse_str($options['options'],$this->shell->input);
        }
		
		$install_options = array();
		$tmp_arr_options = array();		
		foreach ((array)$config['demodatas'] as $key=>$demo_data){			
			foreach ((array)$demo_data['options'] as $arr_options){
				$tmp_arr_options[$arr_options['key']] = $arr_options['value'];
			}
			unset($demo_data['options']);
			$demo_data['options'] = $tmp_arr_options;
			$install_options[$key] = $demo_data;			
		}
		
		if(is_array($install_options) && count($install_options)>0 && !$this->shell->input[$app_id]){
			$this->shell->input_option($install_options,$app_id);
		}
		
		if ($this->shell->input[$app_id]){
			kernel::log('Import demo data');
			kernel::single('base_demo')->init();
			kernel::log('Application demodata installed... ok.');
		}
	}

    var $command_uninstall = '卸载应用';
    var $command_uninstall_options = array(
            'recursive'=>array('title'=>'递归删除依赖之app','short'=>'r'),
        );
    function command_uninstall(){
        $args = func_get_args();
        $uninstall_queue = kernel::single('base_application_manage')->uninstall_queue($args);
        $options = $this->get_options();
        
        if(!$options['recursive']){
            foreach($uninstall_queue as $app_id=>$type){
                $to_delete[$type[1]][] = $app_id;
            }
            if($to_delete[1]){
                echo 'error in remove app '.implode(' ',$args)."\n";
                echo app::get('base')->_("以下应用依赖欲删除的app: ").implode(' ',$to_delete[1])."\n";
                echo app::get('base')->_("使用 -r 参数按依赖关系全部删除");
                return true;
            }
        }
        foreach($uninstall_queue as $app_id=>$type){
            kernel::single('base_application_manage')->uninstall($app_id);
        }
    }

    var $command_pause = '暂停应用';
    var $command_pause_options = array(
            'recursive'=>array('title'=>'递归删除依赖之app','short'=>'r'),
        );
    function command_pause() 
    {
        $args = func_get_args();
        $pause_queue = kernel::single('base_application_manage')->pause_queue($args);
        $options = $this->get_options();
        
        if(!$options['recursive']){
            foreach($pause_queue as $app_id=>$type){
                $to_pause[$type[1]][] = $app_id;
            }
            if($to_pause[1]){
                echo 'error in pause app '.implode(' ',$args)."\n";
                echo app::get('base')->_("以下应用依赖欲暂停的app: ").implode(' ',$to_pause[1])."\n";
                echo app::get('base')->_("使用 -r 参数按依赖关系全部暂停");
                return true;
            }
        }
        foreach($pause_queue as $app_id=>$type){
            kernel::single('base_application_manage')->pause($app_id);
        }
    }//End Function

    var $command_active = '开启应用';
    var $command_active_options = array(
            'recursive'=>array('title'=>'递归删除依赖之app','short'=>'r'),
        );
    function command_active() 
    {
        $args = func_get_args();
        $active_queue = kernel::single('base_application_manage')->active_queue($args);
        $options = $this->get_options();
        if(!$active_queue){
            return ;
        }

        if(kernel::single('base_application_manage')->has_conflict_apps(array_keys($active_queue), $conflict_info)){
            foreach($conflict_info AS $conflict_app_id=>$conflict_detail){
                $conflict_app_info = app::get($conflict_app_id)->define();
                $conflict_message .= $conflict_app_info['name'] . ' ' . app::get('base')->_('与') . ' ' . $conflict_detail['name'] . ' ' . app::get('base')->_('存在冲突') . "\n";
            }
            kernel::log($conflict_message . app::get('base')->_('请手工卸载冲突应用')."\n");
            return false;
        }//todo：安装时判断app冲突，检测包括所有依赖的app和现有安装app之间的冲突
        
        foreach((array)$active_queue as $app_id=>$app_info){
            if(!$app_info){
                kernel::log(app::get('base')->_('无法找到应用').$app_id."\n");
                return false;
            }
            kernel::single('base_application_manage')->active($app_id);
        }
    }//End Function

    var $command_update = '升级应用程序';
    var $command_update_options = array(
            'force-download'=>array('title'=>'强制下载'),
            'download-only'=>array('title'=>'仅下载应用'),
            'ignore-download'=>array('title'=>'忽略下载'),
            'force-update-db'=>array('title'=>'强制更新数据库'),
            'force-update-app'=>array('title'=>'强制更新应用程序'),
            'force-update-services'=>array('title'=>'强制更新所有SERVICES'),
            'force-update-desktop'=>array('title'=>'强制更新所有Desktop相关内容'),
        );
    function command_update(){
        $options = $this->get_options();
        kernel::single('base_application_manage')->update_local();
        
        $args = func_get_args();
        if(!$args){
            $rows = app::get('base')->model('apps')->getList('app_id',array('installed'=>1));
            foreach($rows as $r){
                if($r['app_id'] == 'base')  continue;
                $args[] = $r['app_id'];
            }
        }
        array_unshift($args, 'base');   //todo:总是需要先更新base
        $args = array_unique($args);
        
        if(!$options['ignore-download']){
            foreach($args as $app_id){
                //kernel::single('base_application_manage')->download($app_id,$options['force-download']);  //todo:临时去掉
            }
        }

        if($options['force-update-db']){
            base_application_dbtable::$force_update = true;
        } else {
	    base_application_dbtable::$force_update = false;
	}
	if($options['force-update-desktop']) {	
	    desktop_application_prototype_xml::$force_update = true;
	} else {
	    desktop_application_prototype_xml::$force_update = false;
	}
        if($options['force-update-services']) {
            base_application_service::$force_update = true;
        } else {
            base_application_service::$force_update = false;
	}

        if(!$options['download-only']){
            foreach($args as $app_id){
                $appinfo = app::get('base')->model('apps')->getList('*', array('app_id'=>$app_id));
                if(version_compare($appinfo[0]['local_ver'], $appinfo[0]['dbver'], '>') || $options['force-update-app']){
                    app::get($app_id)->runtask('pre_update', array('dbver'=>$appinfo[0]['dbver']));
                    kernel::single('base_application_manage')->update_app_content($app_id);
                    app::get($app_id)->runtask('post_update', array('dbver'=>$appinfo[0]['dbver']));
                    app::get('base')->model('apps')->update(array('dbver'=>$appinfo[0]['local_ver']), array('app_id'=>$app_id));
                }else{
                    kernel::single('base_application_manage')->update_app_content($app_id);
                }
            }
            kernel::single('desktop_roles')->syncPermissionQueue();
            kernel::log('Applications database and services is up-to-date, ok.');
        }
    }

    var $command_trace = '打开/关闭性能检测';
    function command_trace($mode=null){
        switch($mode){
        case 'on':
            $this->register_trigger('trace');
            break;

        case 'off':
            $this->unregister_trigger('trace');
            break;
        }
        $this->shell->skip_trigger = true;
        echo 'Trace mode is ' , $this->shell->trigger['trace']?'on':'off';
    }
    
    var $command_status = '显示系统状态';
    function command_status($part=null){
        $partlen = strlen($part);
        foreach(kernel::servicelist('status') as $srv){
            foreach($srv->get_status() as $k=>$v){
                if(!$partlen || substr($k,0,$partlen+1)==$part.'.'){
                    echo $k,'=>',$v,"\n";
                }
            }
        }
    }

    var $command_search = '在程序库中搜索';
    function command_search(){
        $keywrods = func_get_args();
        foreach($keywrods as $word){
            $where[] = "app_id like '%{$word}%' or app_name like '%{$word}%' or `description` like '%{$word}%'";
        }
        $sql = 'select app_id,app_name,description,local_ver,remote_ver from sdb_base_apps where 1 and '.implode(' and ',$where);
        $rows = kernel::database()->select($sql);
        $this->output_table( $rows );
    }

    function begin_trace(){
        $this->memtrace_begin = memory_get_usage();
        list($usec, $sec) = explode(" ", microtime());
        $this->time_start = ((float)$usec + (float)$sec);
    }

    function end_trace(){
        if(!$this->memtrace_begin)return ;
        list($usec, $sec) = explode(" ", microtime());
        $time_start = ((float)$usec + (float)$sec);
        $mem = memory_get_usage() - $this->memtrace_begin;

        list($usec, $sec) = explode(" ", microtime());
        $timediff = ((float)$usec + (float)$sec) - $this->time_start;
        printf("\n * Command memroy useage = %d, Time left = %f " , $mem , $timediff);
    }
    
    public $command_createproject = '创建新项目';
    function command_createproject($project_path=null,$install_confirm = null){
        if(!$project_path){
             $project_path = readline('Project name: ');
        }
        
        while(file_exists($project_path)){
             $project_path = readline('Project already exists. enter anthoer one: ');
        }
        
        $project_name = basename($project_path);
        
        //init files
        $base_dir = dirname(__FILE__).'/../../';
        kernel::log('Init project... '.realpath($project_path.'/'.$project_name),1);
        utils::cp($base_dir.'/examples/project',$project_path);
        utils::cp($base_dir,$project_path.'/app/base');
        utils::cp($base_dir.'/examples/app',$project_path.'/app/'.$project_name);
        chmod($project_path.'/app/base/cmd',0744);
        chmod($project_path.'/data',0777);
        utils::replace_p($project_path.'/config',array('%*APP_NAME*%'=>$project_name));
        utils::replace_p($project_path.'/app/'.$project_name,array('%*APP_NAME*%'=>$project_name));
        
        kernel::log('. done!');
        
        if($install_confirm===null){
            do{
                $install_confirm = readline('Install now? [Y/n] ');
                switch(strtolower(trim($install_confirm))){
                    case '':
                    case 'y':
                        $install_confirm = true;
                        $command_succ = true;
                    break;
                
                    case 'n':
                        $install_confirm = false;
                        $command_succ = true;
                    break;
                
                    default:
                        $command_succ = false;
                }
            }while(!$command_succ);
        }
        
        $install_command = 'app'.DIRECTORY_SEPARATOR.'base'.DIRECTORY_SEPARATOR.'cmd install '.$project_name;
            
        if($install_confirm){
            kernel::log('Installing...');
            kernel::log("\n".$project_path.' > '.$install_command."\n");
            chdir($project_path);
            passthru($install_command);
        }else{
            "Change dir to $project_dir: ".$install_command;
        }
    }

    var $command_kvrecovery = 'kvstore数据恢复';
    function command_kvstorerecovery($instance=null) {
        return $this->command_kvrecovery($instance);
    }
    
    function command_kvrecovery($instance=null) 
    {
        if(!is_null($instance) && !defined('FORCE_KVSTORE_STORAGE')){
            $instance = trim($instance);
            if(!(strpos($instance, '_') === 0)){
                $instance = 'base_kvstore_' . $instance;
            }
            define('FORCE_KVSTORE_STORAGE', $instance);
        }
        base_kvstore::config_persistent(false);
        $testObj = base_kvstore::instance('test');
        if(get_class($testObj->get_controller()) === 'base_kvstore_mysql'){
            kernel::log('The \'base_kvstore_mysql\' is default persistent, Not necessary recovery');
            exit;
        }
        kernel::log('KVstore Recovery...');
        $db = kernel::database();
        $count = $db->count('SELECT count(*) AS count FROM sdb_base_kvstore', true);
        if(empty($count)){
            kernel::log('No data recovery');
            exit;
        }
        $pagesize = 100;
        $page = ceil($count / 100);
        for($i=0; $i<$page; $i++){
            $rows = $db->selectlimit('SELECT * FROM sdb_base_kvstore', $pagesize, $i*$pagesize);
            foreach($rows AS $row){
                //kernel::log($row['key']);continue;
                $row['value'] = unserialize($row['value']); //todo:合法数据
                if(base_kvstore::instance($row['prefix'])->recovery($row)){
                    kernel::log($row['prefix'] .'=>' . $row['key'] . ' ... Recovery Success');
                }else{
                    kernel::log($row['prefix'] .'=>' . $row['key'] . ' ... Recovery Failure');
                }
            }
        }
    }//End Function

    var $command_kvdelexpires = 'kvstore清除过期数据，开启持久化功能有效';
    function command_kvdelexpires() 
    {
        kernel::log('KVstore Delete Expires Data...');
        base_kvstore::delete_expire_data();
    }//End Function

    var $command_cacheclean = '清除缓存';
    function command_cacheclean() 
    {
        kernel::log('Cache Clear...');
        cachemgr::init(true);
        if(cachemgr::clean($msg)){
            kernel::log($msg ? $msg : '...Clear Success');
        }else{
            kernel::log($msg ? $msg : '...Clear Failure');
        }
        cachemgr::init(false);
    }//End Function

    var $command_configcompat = 'config兼容配置检测';
    function command_configcompat() 
    {
        kernel::single('base_setup_config')->write_compat();
    }//End Function
}

