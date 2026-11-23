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
 
class desktop_ctl_debug extends desktop_controller{

    function index() {
        $this->path[] = array('text'=>app::get('desktop')->_('数据备份'));
        if($time = app::get('shopex')->getConf("system.last_backup")){
            $this->pagedata['time'] = date('Y-m-d H:i:s',$time);
        }
        $this->pagedata['debug'] = 'current';
        kernel::single("desktop_ctl_data")->index();
        $this->page('system/debug/clear.html');
    }
    function cleardata(){
        $filter['uname'] = $_POST['uname'];
        $filter['password'] = $_POST['password'];
        if( !$filter['uname'] || !$filter['password'] ) $this->error_splash();
        $arr = $this->login( $filter );
        if( !is_array($arr) ) $this->error_splash();
        reset( $arr );
        $arr = current( $arr );
        $arr = $this->app->model('users')->dump( $arr['account_id'] );
        
        if( $arr['super'] ) $this->clear();
        else $this->error_splash();
    }
    
    private function error_splash( $flag=false,$msg='用户名密码错误',$url=false ) {
        $this->begin($url);
        $this->end( $flag, $msg );
    }
    
    private function login( $filter ) {
        $type = pam_account::get_account_type('desktop');
        $arr = app::get('pam')->model('account')->getList('*',array(
                'login_name'=>$filter['uname'],
                'login_password'=>pam_encrypt::get_encrypted_password($filter['password'],$type),
                'account_type' => $type,
                'disabled' => 'false',
                ),0,1
            ); 
        return $arr;
    }
    
    private function clear() {
    	 foreach( kernel::servicelist("desktop_debug_clean_data") as $object ) {
    	 	if( method_exists($object,'clean') ) 
    	 		$object->clean();
    	 }

        $this->error_splash( true, '数据清理成功!' );
    }

    

}
