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
 
class base_controller extends base_render{

    var $pagedata = array();
    var $force_compile = 0;
    var $_tag_stack = array();
    public $_end_message = null;

    function begin($url_params=null){
        set_error_handler(array(&$this,'_errorHandler'), E_USER_ERROR | E_ERROR);
        if($this->transaction_start) trigger_error('The transaction has been started',E_USER_ERROR);
        $db = kernel::database();
        $this->transaction_status = $db->beginTransaction();
        $this->transaction_start = true;
        if(is_array($url_params)){
            $this->_action_url = $this->app->router()->gen_url($url_params);
        }else{
            $this->_action_url = $url_params;
        }
    }
    
    function endonly($result=true){
        if(!$this->transaction_start) trigger_error('The transaction has not started yet',E_USER_ERROR);
        $this->transaction_start = false;
        $db = kernel::database();
        restore_error_handler();
        if($result){
            $db->commit($this->transaction_status);
        }else{
            $db->rollback();
        }
    }

    function end($result=true,$message=null,$url_params=null,$params=array()){
        if(!$this->transaction_start) trigger_error('The transaction has not started yet',E_USER_ERROR);
        $this->transaction_start = false;
        $db = kernel::database();
        restore_error_handler();
        if(is_null($url_params)){
            $url = $this->_action_url;
        }elseif(is_array($url_params)){
            $url = $this->app->router()->gen_url($url_params);
        }else{
            $url = $url_params;
        }
        if($result){
            $db->commit($this->transaction_status);
            $status = 'success';
            $message = ($message=='' ? app::get('base')->_('操作成功！') : app::get('base')->_('成功：').$message);
        }else{
            $db->rollback();
            $status = 'error';
            $message = $message?$message:app::get('base')->_("操作失败: 对不起,无法执行您要求的操作");
        }
        $this->_end_message = $message;
        $this->splash($status,$url,$message,'redirect',$params);
    }
    
    function splash($status='success',$url=null,$msg=null,$method='redirect',$params=array()){
        header("Cache-Control:no-store, no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");// 强制查询etag
        header('Progma: no-cache');
        header('Location: '.$url);
    }

    function page($detail){
        header('Content-type: text/html; charset=utf-8');
        $object = kernel::service('theme');
        if($object){
            $object->display($detail);
        }else{
            $this->display($detail);
        }
    }

    function _errorHandler($errno, $errstr, $errfile, $errline){
        if($errno==E_ERROR){
            $errstr = basename($errfile).':'.$errline.'&nbsp;'.$errstr;
        }
        $this->splash('error',$this->_action_url,$errstr);
        return true;
    }

}
