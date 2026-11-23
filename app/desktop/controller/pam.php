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
 
class desktop_ctl_pam extends desktop_controller{

    function index(){
        $this->finder('desktop_mdl_pam',array(
            'title'=>app::get('desktop')->_('通行证管理'),
            'actions'=>array(
             ),                                   
            ));
    }
    
        
    function setting($passport){
        $passport_model =new $passport;
        if($_POST){
            $this->begin('index.php?app=desktop&ctl=pam&act=index');
            if($_POST['site_passport_status'] === 'false'){
                if(!$this->checkpassport($passport_model)){
                     $this->end(false,app::get('desktop')->_('配置失败,前台必须开启一种认证方式'));
                }
            }
            if(!$passport_model->set_config($_POST)){
                if(!$_POST['error']) $this->end(false,app::get('desktop')->_('配置失败'));    
                else
                $this->end(false,$_POST['error']);    
            }
            else{
                $this->end(true,app::get('desktop')->_('配置成功'));
            }
              
        }
        $len = strlen($html);
        foreach($passport_model->get_config() as $name=>$config){
            if($config['editable'] == 'false' || (isset($config['editable']) && !$config['editable'])) continue;
            $input['name'] = $name;
            $input['title'] = $config['label'];
            $input['type'] = $config['type'];
            $input['required'] = $config['required'];
            if($config['options']){
                $input['options'] = $config['options'];
            }
            if($config['value']){
               $input['value'] = $config['value']; 
            }
            $html .= $this->ui()->form_input($input);
            unset($input);
        }
        if($len == strlen($html)){
            $this->pagedata['basic'] = "true";
            $this->pagedata['html'] = $html;
            $this->pagedata['passport'] = $passport;
            $this->page('pam.html');       
        }else{
            $this->pagedata['html'] = $html;
            $this->pagedata['passport'] = $passport;
            $this->page('pam.html');
        }
    }
    
    function checkpassport($model_passport){
         foreach(kernel::servicelist('passport') as $k=>$passport){
            if($model_passport != $passport){
                $config = $passport->get_config();
                if($config['site_passport_status']['value'] == 'true'){
                    $flag = true;
                    break;
                } 
                else $flag = false;
            }
        } 
        return $flag;
    }

}
