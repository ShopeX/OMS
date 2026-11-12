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


class desktop_ctl_comeback extends desktop_controller{


    function __construct($app) {
        header("cache-control: no-store, no-cache, must-revalidate");
        kernel::single('base_session')->start();
        $o = kernel::single("desktop_user");
        $o->user_data['status'] = true;
        if( !$o->get_id() ) exit('error');
        parent::__construct($app);
    }
    function index(){
        $this->path[] = array('text'=>app::get('desktop')->_('数据恢复'));
        $oDSB = kernel::single("desktop_system_backup");
        $arr = $oDSB->getList();
        if( $arr ) {
            foreach( (array)$arr as $row ) {   //去除重复数据
                $key = md5($row['name']);
                if( !$arr_list[$key] )
                    $arr_list[$key] = $row;
            }
        }

        $this->pagedata['archive'] = $arr_list;
        $this->pagedata['comeback'] = 'current';
        kernel::single("desktop_ctl_data")->index();
        $this->page('system/comeback/tgzFileList.html');
    }

    function comeback(){
        $this->display('system/comeback/comeback.html');
    }



    function recover(){
        kernel::single('base_session')->close();
        set_time_limit(0);
        $filename = isset($_GET['file']) ? (string) $_GET['file'] : '';
        // CNVD-2026-43974: reject path traversal / absolute paths; basename only.
        if($filename === ''
            || strpos($filename, '..') !== false
            || strpos($filename, '/') !== false
            || strpos($filename, '\\') !== false
            || $filename !== basename($filename)
        ){
            echo '{error:"'.app::get('desktop')->_('非法备份文件名').'"}';
            return;
        }
        $fileid = intval($_GET['fileid']) ? intval($_GET['fileid']) : 1;
        $vols = $_GET['vols'] ? $_GET['vols'] : 1;
        $oDSB = kernel::single("desktop_system_backup");
        $app = $_GET['appid']; //备份中的app
        //$objB2c = app::get('b2c');
    	//$objB2c->setConf('member.attr','');
       // $attr_model = $objB2c->model('member_attr')->init();
        if($oDSB->recover($filename, $vols, $fileid, $app) === false){
            echo '{error:"'.app::get('desktop')->_('恢复失败或非法备份文件').'"}';
            return;
        }
        if($vols<=$fileid){
            ob_start();
            kernel::single('base_shell_loader')->exec_command('kvrecovery'); //还原kvstore
            ob_end_clean();
            foreach(kernel::servicelist('restore_firevent') as $k=>$firevent){
	             if(is_object($firevent)){
	                  $firevent->restoreEvent();
	             }
	        }
            echo '{success:"'.app::get('desktop')->_('恢复完成').'"}';
        }
        else{
            echo '{message:"'.$oDSB->show_message . app::get('desktop')->_('正在恢复第').($fileid+1).app::get('desktop')->_('卷 共').$vols.''.app::get('desktop')->_('卷').'",               nexturl:"index.php?app=desktop&ctl=comeback&act=recover&file='.$filename.'&vols='.$vols.'&fileid='.($fileid+1).'&appid='.$app.'"}';
        }


    }

    function removeTgz() {
        $this->begin(  'index.php?app=desktop&ctl=data&act=index' );
        $arr_tgz = $_GET['tgz'];

        if(empty($arr_tgz)) $this->end(false, app::get('desktop')->_('数据错误！'));
        kernel::single("desktop_system_backup")->removeTgz($arr_tgz);
        $this->end(true, app::get('desktop')->_('移除成功！'));
    }


}
