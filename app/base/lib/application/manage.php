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

class base_application_manage{

    //应用程序资源探测器。 
    //想添加自己的探测器? 注册服务: app_content_detector
    static function content_detector($app_id=null){
        $content_detectors =  array(
            'list'=>array(
                'base_application_dbtable',
                'base_application_service',
                'base_application_lang',
                'base_application_cache_expires',
                //'base_application_imgbundle',
            )
        );

        if($app_id!='base'){
            $content_detectors_addons = app::get('base')->model('app_content')->getlist('content_path,app_id',array(
                'content_type'=>'service',
                'content_name'=>'app_content_detector',
                'disabled'=>'false',
            ));
            foreach($content_detectors_addons as $row){
                $content_detectors['list'][$row['content_path']] = $row['content_path'];
            }
        }
        return new service($content_detectors);
    }

    public function uninstall_queue($apps){
        if(is_string($apps)){
            $apps = array($apps);
        }
        $rows = kernel::database()->select('select app_id,app_name from sdb_base_apps where status != "uninstalled"');
        $depends_apps_map = array();
        foreach($rows as $row){
            $namemap[$row['app_id']] = $row['app_name'];
            $depends_apps = app::get($row['app_id'])->define('depends/app');
            if($depends_apps){
                foreach($depends_apps as $dep_app){
                    $depends_apps_map[$dep_app['value']][] = $row;
                }
            }
        }
        foreach($apps as $app_id){
            $this->check_depends_uninstall($app_id, $depends_apps_map,$queue);
        }
        foreach($apps as $app_id){
            $queue[$app_id] = array($namemap[$app_id],0);
        }
        return $queue;
    }

    public function active_queue($apps) 
    {
        if(is_string($apps)){
            $apps = array($apps);
        }
        foreach($apps as $app_id){
            $this->check_active_install($app_id, $queue);
            $queue[$app_id] = app::get($app_id)->define();
        }
        return $queue;
    }//End Function

    private function check_active_install($app_id, &$queue){
        $depends_app = app::get($app_id)->define('depends/app');
        foreach((array)$depends_app as $depend_app_id){
            $this->check_active_install($depend_app_id['value'], $queue);
        }
        if(app::get($app_id)->status() == 'uninstalled' || app::get($app_id)->status() == 'paused'){
            $queue[$app_id] = app::get($app_id)->define();
        }
    }

    public function pause_queue($apps) 
    {
        if(is_string($apps)){
            $apps = array($apps);
        }
        $rows = kernel::database()->select('select app_id,app_name from sdb_base_apps where status = "active"');
        $depends_apps_map = array();
        foreach($rows as $row){
            $namemap[$row['app_id']] = $row['app_name'];
            $depends_apps = app::get($row['app_id'])->define('depends/app');
            if($depends_apps){
                foreach($depends_apps as $dep_app){
                    $depends_apps_map[$dep_app['value']][] = $row;
                }
            }
        }
        foreach($apps as $app_id){
            $this->check_depends_uninstall($app_id, $depends_apps_map,$queue);
        }
        foreach($apps as $app_id){
            $queue[$app_id] = array($namemap[$app_id],0);
        }
        return $queue;
    }//End Function

    private function check_depends_uninstall($app_id,$depends_apps_map, &$queue){
        if(isset($depends_apps_map[$app_id])){
            foreach($depends_apps_map[$app_id] as $to_delete){
                $this->check_depends_uninstall($to_delete['app_id'],$depends_apps_map,$queue);
                $queue[$to_delete['app_id']] = array($to_delete['app_name'],1);
            }
        }
    }

    public function install_queue($apps,$force_install=false){
        if(is_string($apps)){
            $apps = array($apps);
        }
        foreach($apps as $app_id){
            $this->check_depends_install($app_id, $queue);
            if($force_install){
                $queue[$app_id] = app::get($app_id)->define();
            }
        }
        return $queue;
    }

    public function has_conflict_apps($apps, &$conflict_apps) 
    {
        if(!kernel::is_online())    return false;
        if(is_string($apps)){
            $apps = array($apps);
        }
        $queue = array();
        $installed_queue = array();
        $install_apps = array();
        $installed_apps = array();
        foreach($apps AS $app_id){
            $install_apps[$app_id] = array();
            $this->check_conflicts_install($app_id, $queue);
        }

        $rows = [];
        $isExist = kernel::database()->select("SHOW TABLES LIKE 'sdb_base_apps'");
        if ($isExist){
            $rows = kernel::database()->select('select app_id from sdb_base_apps where status != "uninstalled"');
        }

        foreach($rows AS $row){
            $installed_apps[$row['app_id']] = array();
            $this->check_conflicts_install($row['app_id'], $installed_queue);
        }
        $conflict_one = array_intersect_key($queue, $installed_apps);
        $conflict_two = array_intersect_key($installed_queue, $install_apps);
        $conflict_apps = array_merge($conflict_one, $conflict_two);
        return (count($conflict_apps)) ? true : false;
    }//End Function

    private function check_conflicts_install($app_id, &$queue) 
    {
        $conflicts_app = app::get($app_id)->define('conflicts/app');
        foreach((array)$conflicts_app AS $conflict_app){
            $conflict_app_id = $conflict_app['value'];
            $queue[$conflict_app_id] = app::get($app_id)->define();
        }
    }//End Function

    private function check_depends_install($app_id, &$queue){
        $depends_app = app::get($app_id)->define('depends/app');
        foreach((array)$depends_app as $depend_app_id){
            $this->check_depends_install($depend_app_id['value'], $queue);
        }
        if(app::get($app_id)->status() == 'uninstalled'){
            $queue[$app_id] = app::get($app_id)->define();
        }
    }

    public function install($app_id,$options=null,$auto_enable=1){
        $app = app::get($app_id);
        if(!file_exists(APP_DIR.'/'.$app_id.'/app.xml')){
            if(!$this->download($app_id)){
                kernel::log('Application package can not be download.');
                return false;
            }
        }

        $app_info = $app->define('main_app');
        $app_exclusion = app::get('base')->getConf('system.main_app');
        if($app_info['value'] == 'true'){
            if($app_info['exclusion'] == 'true'){
                if($app_exclusion['value'] == 'true' && $app_exclusion['exclusion'] == 'true' && $app_exclusion['app_id'] != $app_id){
                    kernel::log('Application '.$app_id.' exclusioned '.$app_exclusion['app_id'].'.');
                    return false;
                }
            }
            $app_info['app_id'] = $app_id;
            $app_exclusion = app::get('base')->setConf('system.main_app', $app_info);
        }

        $app_self_detector = null;

        $app->runtask('pre_install',$options);

        kernel::single('base_application_dbtable')->clear_by_app($app_id);  //清除冗余表信息
        foreach($this->content_detector($app_id) as $detector){
            foreach($detector->detect($app) as $name=>$item){
                $item->install();
            }
            kernel::set_online(true);
            base_kvstore::instance('system')->store(
                'service_last_modified.'.get_class($detector).'.'.$app_id , 
                $detector->last_modified($app_id));
        }

        //todo:clear service cache... 如果以后做了缓存的话...


        //用自己新安装的资源探测器，安装自己的资源
        foreach(kernel::servicelist('app_content_detector') as $k=>$detector){
            if($detector->app->app_id==$app_id){
                //遍历所有已经安装的app
                foreach($detector->detect($app) as $name=>$item){
                    $item->install();
                }
                base_kvstore::instance('system')->store(
                    'service_last_modified.'.get_class($detector).'.'.$app_id , 
                    $detector->last_modified($app_id));
            }
        }
        app::get('base')->model('apps')->replace(
            array('status'=>'installed','app_id'=>$app_id, 'dbver'=>$app->define('version'))
            ,array('app_id'=>$app_id)
        );

        $deploy_info = base_setup_config::deploy_info();
        foreach((array)$deploy_info['setting'] as $set){
            if($set['app']==$app_id){
                $app->setConf($set['key'],$set['value']);
            }
        }
        kernel::single('base_initial', $app_id)->init();

        $app->runtask('post_install',$options);

        if($auto_enable){
            $this->enable($app_id);
        }

        //app submit servicelist
        $params['certificate_id'] = $app_id;
        $params['app_id'] = $app_id;
        // $rst = app::get($app_id)->matrix()->set_callback('dev_sandbox','show',array(1,2,3,'aa'=>time()))
        // ->call('node.addshop',$app_id);

        kernel::log('Application '.$app_id.' installed... ok.');
    }

    public function uninstall($app_id){
        $this->disable($app_id);

        $app = app::get($app_id);
        $app->runtask('pre_uninstall');

        //对于BASE, 只要删除数据库即可  删无可删,无需再删
        if($app_id=='base'){
            kernel::single('base_application_dbtable')->clear_by_app('base');
        }else{
            foreach($this->content_detector($app_id) as $detector){
                $detector->clear_by_app($app_id);
            }
            app::get('base')->model('app_content')->delete(array('app_id'=>$app_id));

            $app->runtask('post_uninstall');
            /*
            app::get('base')->model('apps')->update(
                array('status'=>'uninstalled')
                ,array('app_id'=>$app_id)
            );
            */
            //todo:应要求暂时在app卸载时把app信息一同抹去，需要手工运行检查更新 
            //modify by edwin.lzh@gmail.com 2011/3/24
            app::get('base')->model('apps')->delete(array('app_id'=>$app_id));

            $app_ext = app::get('base')->getConf('system.main_app');
            if($app_id == $app_ext['app_id']){
                app::get('base')->setConf('system.main_app', array());
            }
        }
        kernel::log('Application '.$app_id.' removed');
    }

    public function pause($app_id) 
    {   
        if($app_id == 'base'){
            kernel::log('Appication base can\'t be paused');
        }else{
            $row = kernel::database()->select('select app_id from sdb_base_apps where app_id = "'.$app_id.'" AND status = "active"');
            if(empty($row)){
                kernel::log('Application ' . $app_id . ' don\'t be pause');
                return ;
            }
            $this->disable($app_id);
            $app = app::get($app_id);
            
            foreach($this->content_detector($app_id) as $detector){
                $detector->pause_by_app($app_id);
            }
            app::get('base')->model('app_content')->delete(array('app_id'=>$app_id));

            app::get('base')->model('apps')->update(
                array('status'=>'paused')
                ,array('app_id'=>$app_id)
            );

            kernel::log('Application '.$app_id.' paused');
        }
    }//End Function

    public function active($app_id) 
    {   
        $row = kernel::database()->selectrow('select status from sdb_base_apps where app_id = "'.$app_id.'" AND status IN ("uninstalled", "paused") ');
        switch($row['status'])
        {
            case 'paused':
                $this->enable($app_id);
                $app = app::get($app_id);
                
                foreach($this->content_detector($app_id) as $detector){
                    $detector->active_by_app($app_id);
                }

                //用自己新启用的资源探测器，启用自己的资源
                foreach(kernel::servicelist('app_content_detector') as $k=>$detector){
                    if($detector->app->app_id==$app_id){
                        //遍历所有已经安装的app
                        $detector->active_by_app($app_id);
                    }
                }

                app::get('base')->model('apps')->update(
                    array('status'=>'active')
                    ,array('app_id'=>$app_id)
                );

                kernel::log('Application '.$app_id.' actived');
                return;
            case 'uninstalled':
                $this->install($app_id);
                return;
            default:
                kernel::log('Application ' . $app_id . ' don\'t be active');
                return ;
        }
    }//End Function

    public function enable($app_id){
        $app = app::get($app_id);
        $app->runtask('pre_enable');

        app::get('base')->model('app_content')->update(
            array('disabled'=>'false')
            ,array('app_id'=>$app_id)
        );
        app::get('base')->model('apps')->update(
            array('status'=>'active')
            ,array('app_id'=>$app_id)
        );

        $app->runtask('post_enable');
    }

    public function disable($app_id){
        $app = app::get($app_id);
        $app->runtask('pre_disable');

        app::get('base')->model('app_content')->update(
            array('disabled'=>'true')
            ,array('app_id'=>$app_id)
        );
        app::get('base')->model('apps')->update(
            array('status'=>'installed')
            ,array('app_id'=>$app_id)
        );

        $app->runtask('post_disable');
    }

    public function download($app_id,$force = false){

        $download_able = $force;
        if(!$download_able){
            $download_able = !file_exists(APP_DIR.'/'.$app_id.'/app.xml');
            if(!$download_able){
                $rows = app::get('base')->model('apps')->getList('app_id,local_ver,remote_ver',array('app_id'=>$app_id),0,1);
                $download_able = $rows[0]['local_ver']?version_compare($rows[0]['remote_ver'],$rows[0]['local_ver'],'>'):true;
            }
        }

        if($download_able){
            $tmpfile = tempnam(false,'app_');
            $download_result = kernel::single('base_pget')->dl(sprintf(URL_APP_FETCH,$app_id),$tmpfile);
            if(!$download_result){
                kernel::log('Appliction ['.$app_id.'] download failed.');
                exit;
            }
            $tmpdir = DATA_DIR.'/tmp/'.basename($tmpfile);
            $broken = false;
            kernel::log("\nExtra from package.");
            foreach(base_package::walk($tmpfile) as $file){
                if(!$file){
                    $broken = true;
                    break;
                }
                kernel::log($file['name']);
                base_package::extra($file,$tmpdir);
            }
            @unlink($tmpfile);

            if(!$broken && file_exists($tmpdir.'/app.xml')){
                if(!is_dir(DATA_DIR.'/backup')){
                    utils::mkdir_p(DATA_DIR.'/backup');
                }
                utils::cp(APP_DIR.'/'.$app_id , DATA_DIR.'/backup/app.'.$app_id.'.'.time());
                utils::remove_p(APP_DIR.'/'.$app_id);
                utils::cp($tmpdir , APP_DIR.'/'.$app_id);
                utils::remove_p($tmpdir);

                $this->update_local_app_info($app_id);

                return true;
            }else{
                utils::remove_p($tmpdir);
                return false;
            }

        }
    }

    public function update_app_content($app_id,$autofix=true){
        foreach($this->content_detector($app_id) as $k=>$detector){
            $last_modified = $detector->last_modified($app_id);
            if($last_modified == -1 || base_kvstore::instance('system')->fetch('service_last_modified.'.get_class($detector).'.'.$app_id, $current_define_modified) == false || $last_modified != $current_define_modified){
                kernel::log('Updating '.$k.'@'.$app_id.'.');
                if($autofix){
                    $detector->update($app_id);
                    if ($last_modified!=-1) {
                        base_kvstore::instance('system')->store(
                            'service_last_modified.'.get_class($detector).'.'.$app_id , 
                            $last_modified);
                    }
                }
            }
        }
    }

    public function sync(){

        kernel::log('Updating Application library..');
        $xmlfile = tempnam(false,'appdb_');
        kernel::single('base_pget')->dl(URL_APP_FETCH_INDEX,$xmlfile);

        $appdb = kernel::single('base_xml')->xml2array(
		file_get_contents($xmlfile),'base_app');
        @unlink($xmlfile);
        app::get('base')->model('apps')->update(array('remote_ver'=>''));
        if($appdb['app']){
            app::get('base')->model('apps')->delete(array('installed'=>false));
        }

        foreach((array)$appdb['app'] as $app){
            $data = array(
                'app_id'=>$app['id'],
                'app_name'=>$app['name'],
                'remote_ver'=>$app['version'],
                'description'=>$app['description'],
                'author_name'=>$app['author']['name'],
                'author_url'=>$app['author']['url'],
                'author_email'=>$app['author']['email'],
                'remote_config'=>$app,
            );

            app::get('base')->model('apps')->replace($data,array('app_id'=>$app['id']));
        }

        $this->update_local();

        kernel::log('Application libaray is updated, ok.');
    }

    private function update_local_app_info($app_id){
        $app = app::get($app_id)->define();
        $data = array(
            'app_id'=>$app_id,
            'app_name'=>$app['name'],
            'local_ver'=>$app['version'],
            'description'=>$app['description'],
            'author_name'=>$app['author']['name'],
            'author_url'=>$app['author']['url'],
            'author_email'=>$app['author']['email'],
        );
        app::get('base')->model('apps')->replace($data,array('app_id'=>$app_id));
    }

    public function update_local(){
        kernel::log('Scanning local Applications... ',1);
        if ($handle = opendir(APP_DIR)) {
            while (false !== ($file = readdir($handle))) {
                if($file[0]!='.' && is_dir(APP_DIR.'/'.$file) && file_exists(APP_DIR.'/'.$file.'/app.xml')){
                    $this->update_local_app_info($file);
                }
            }
            closedir($handle);
        }
        kernel::log('ok.');
        return $this->_list;
    }
}
