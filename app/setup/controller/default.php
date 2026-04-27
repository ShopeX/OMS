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
ini_set('display_errors', 1);

class setup_ctl_default extends setup_controller{
    
    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct($app){
        kernel::set_online(false);
        // Allow hard lock via environment flag in addition to lock file
        $installLockFlag = getenv('ONEX_OMS_INSTALL');
        if ($installLockFlag && strtoupper($installLockFlag) === 'LOCK') {
            $this->lock();
        }
        if(kernel::single('base_setup_lock')->lockfile_exists()){
            if(!kernel::single('base_setup_lock')->check_lock_code()){
                $this->lock();
            }
        }
        parent::__construct($app);
        define('LOG_TYPE', 3);
    }
    
    /**
     * console
     * @return mixed 返回值
     */
    public function console(){

        $shell = new base_shell_webproxy;
        $shell->input = $_POST['options'];
        echo "\n";
        $shell->exec_command($_POST['cmd']);
    }
    
    private function lock(){
        header('Content-type: text/html',1,401);

        // 首页地址
        $url = kernel::base_url(1);

        // 3秒后跳转至登陆页。
        header('Refresh: 3; url='.$url);

        echo '<h3>系统已经安装，3秒后为您跳转至登陆页，您也可以<a href="'.$url.'">点击</a>前往。</h3><hr />';
        //echo '<h3>Setup Application locked by config/install.lock.php</h3><hr />';
        exit;
    }
    
    /**
     * index
     * @return mixed 返回值
     */
    public function index(){
          $this->pagedata['conf'] = base_setup_config::deploy_info();
		  $this->pagedata['install_bg'] = kernel::base_url(1).'/config/setup_product.jpg';
		  $this->pagedata['statics_url'] = $this->app->res_url;
          $output = $this->fetch('installer-start.html');
          echo str_replace('%BASE_URL%',kernel::base_url(1),$output);
    }

    /**
     * 处理
     * @return mixed 返回值
     */
    public function process(){
        set_time_limit(0);
        $serverinfo = kernel::single('setup_serverinfo')->run($_POST['installer_check']);
		if($serverinfo['allow_install'] != 1){
			$this->pagedata['serverinfo'] = $serverinfo;
		}
        $this->pagedata['conf'] = base_setup_config::deploy_info();
        $install_queue = $this->install_queue($this->pagedata['conf']);
        
        $install_options = array();
        if(is_array($install_queue)){
            foreach($install_queue as $app_id=>$app_info){
                $option = app::get($app_id)->runtask('install_options');
                if(is_array($option) && count($option)>=1){
                    $install_options[$app_id] = $option;
                }
            }
        }
        $this->pagedata['install_options'] = &$install_options;
		$this->pagedata['install_demodata_options'] = $this->install_demodata_options($this->pagedata['conf']);
		
		$this->pagedata['res_url'] = $this->app->res_url;
        $this->pagedata['apps'] = &$install_queue;
		if ($this->pagedata['conf']['demodatas']){
			$this->pagedata['demodata'] = array(
				'install'=>'true',
				'name'=>'demodata',
				'description'=>'demodata',
			);
		}else{
			$this->pagedata['demodata'] = [];
		}
		
		$this->pagedata['success_page'] = 'success';
			
        if($_GET['console']){
            $output = $this->fetch('console.html');
        }else{
            $output = $this->fetch('installer.html');
        }
		
        echo str_replace('%BASE_URL%',kernel::base_url(1),$output);
		
    }
    
    /**
     * success
     * @return mixed 返回值
     */
    public function success(){
        try {
            kernel::single('entermembercenter_stat')->reportInstall();
        } catch (Throwable $e) {
            // 上报失败不阻断成功页
        }
        $this->pagedata['statics_url'] = $this->app->res_url;
		$this->pagedata['conf'] = base_setup_config::deploy_info();
		$output = $this->fetch('installer-success.html');
		echo str_replace('%BASE_URL%',kernel::base_url(1),$output);
    }
    
    private function write_lock_code(){
        kernel::single('base_setup_lock')->write_lock_file();
    }
    
    /**
     * install_queue
     * @param mixed $config 配置
     * @return mixed 返回值
     */
    public function install_queue($config=null){
        $config = $config?$config:base_setup_config::deploy_info();      
        
        foreach($config['package']['app'] as $k=>$app){
            $applist[] = $app['id'];
        }
                
        return kernel::single('base_application_manage')->install_queue($applist);
    }
	
	/**
	 * �õ�deploy�����demo dataѡ����Ŀ 
	 * @param null
	 * @return array
	 */
	public function install_demodata_options($config=null)
	{
		$config = $config?$config:base_setup_config::deploy_info(); 
		
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
		
		return $install_options;
	}

    public function initenv(){

        require_once APP_DIR.'/base/defined.php';

        $this->write_lock_code();
        
        header('Content-type: text/plain; charset=UTF-8');
        $install_queue = $this->install_queue();
        foreach($install_queue as $app_id=>$app_info){
            if(false === app::get($app_id)->runtask('checkenv',$_POST['options'][$app_id])){
                $error = true;
            }
        }
        if($error){
            echo 'check env failed';
        }else{
            echo 'config init ok.';            
        }
    }
    
    /**
     * install_app
     * @return mixed 返回值
     */
    public function install_app(){
        kernel::set_online(true);
        $app = $_GET['app'];
        if(file_exists(ROOT_DIR.'/config/config.php')){
            $shell = new base_shell_webproxy;
            $shell->input = $_POST['options'];
            $shell->exec_command('install -r '.$app);
        }else{
            echo 'config file?';
        }
    }
	
    /**
     * install_demodata
     * @return mixed 返回值
     */
    public function install_demodata(){
        kernel::set_online(true);
       
        if(file_exists(ROOT_DIR.'/config/config.php')){
            $shell = new base_shell_webproxy;
            $shell->input = $_POST['options'];
            $shell->exec_command('install_demodata -r demodata');
        }else{
            echo 'config file?';
        }
    }

    /**
     * 设置uptools
     * @return mixed 返回操作结果
     */
    public function setuptools() 
    {
        $app = addslashes($_GET['app']);
        $method = addslashes($_GET['method']);
        if(empty($app) || empty($method))   die('call error');
        $data = app::get($app)->runtask($method, $_POST['options']);
        header('Content-type: application/json; charset=UTF-8');
        echo json_encode($data);
    }//End Function

}
