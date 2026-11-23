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
 
class base_shell_webproxy extends base_shell_loader{
    
    function __construct(){
        header('Content-type: text/html;charset=utf-8');
        ignore_user_abort(false);
        ob_implicit_flush(1);
        ini_set('implicit_flush',true);
        
        set_error_handler(array(&$this,'error_handle'));
        chdir('data');
        kernel::$console_output = true;
        
        while(ob_get_level()){
            ob_end_flush();
        }
        
        echo str_repeat("\0",1024);
        //$this->buildin_commander = new base_shell_buildin($this);
        parent::__construct();
    }
    
    function exec_command($command){
        echo '<pre>';
        echo '>'. $command."\n";
        parent::exec_command($command);
        echo '</pre>';
    }
    
    function error_handle($code,$msg,$file,$line){
        
        if($code == ($code & (E_ERROR ^ E_USER_ERROR ^ E_USER_WARNING))){
            echo 'ERROR: ',$code,':',$msg,'  @',basename($file),':',$line."\n\n";
            if($code == ($code & (E_ERROR ^ E_USER_ERROR))){
                exit;
            }
        }
        return true;
    }

}
