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
 
class desktop_ctl_backup extends desktop_controller{

    function index(){
        $this->path[] = array('text'=>app::get('desktop')->_('数据备份'));
        if($time = app::get('shopex')->getConf("system.last_backup")){
            $this->pagedata['time'] = date('Y-m-d H:i:s',$time);
        }
        $this->pagedata['backup'] = 'current';
        kernel::single("desktop_ctl_data")->index();
        $this->page('system/backup/backup.html');
    }



    function backup_sdf(){
        ini_set("max_exection_time", 0);
        header("Content-type:text/html;charset=utf-8");

        $params['dirname'] = ($_GET["dirname"]=="") ? date("YmdHis", time()) : $_GET["dirname"];
        $params['appname'] = $_GET['appname'];
        #$params['cols'] = $_GET['cols'] ? $_GET['cols'] : 0;
        #$params['model'] = $_GET['model'] ? $_GET['model'] : '';
        #$params['startid'] = $_GET['startid'] ? $_GET['startid'] : 0;
        
        $oBackup = kernel::single('desktop_system_backup');
        
        if(!$oBackup->start_backup_sdf($params,$nexturl)){
            echo '{message:"'.app::get('desktop')->_('正在备份应用：').($params['appname']).'",
				   nexturl:"'.$nexturl.'"}';
        }
        else{
            app::get('shopex')->setConf("system.last_backup", time(), true);
           echo '{success:"'.app::get('desktop')->_('备份完成').'",nexturl:"index.php?app=desktop&ctl=backup&act=getFile&file=multibak_'.$params['dirname'].'.zip"}';

        }

    }
    
    
     
    
    
    public function getFile() {
        $file = $_GET['file'];
		if($file && preg_match('/(\..\/){1,}/', $file)){
			header("Content-type: text/html; charset=utf-8");
			echo app::get('desktop')->_('非法操作');exit;;
		}
        kernel::single('desktop_system_backup')->download($file);
    }
    

}
