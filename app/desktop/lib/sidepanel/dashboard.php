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
 
class desktop_sidepanel_dashboard{

    function __construct($app){
        $this->app = $app;
    }
####根据工作组显示侧边栏菜单
    function get_output(){ 
        $render = $this->app->render();
        $act = app::get('desktop')->model('menus')->getList(
            'menu_id,app_id,menu_title,menu_path,workground',
            array('menu_type'=>'workground','disabled'=>'false')
        );
        $user = kernel::single('desktop_user');  
        if($user->is_super()){
            $aData = $act;
        }
        else{
            $group = $user->group();//print_r($group);
            $meuns = app::get('desktop')->model('menus');
            $data = array();
            foreach($group as $key=>$val){
            $aTmp = $meuns->workgroup($val);
               foreach($aTmp as $val ){
               $data[] =$val;
          }
      }
            $aData = $data;
        }
        $menu_id = array();
        $wrokground = array(); 
        foreach((array)$aData as $value){
            if(!in_array($value['menu_id'],(array)$menu_id)){
                $workground[] = $value;
            }
            $menu_id[] = $value['menu_id'];
        }
        $render->pagedata['actions'] = $workground;
        $render->pagedata['side'] = "sidepanel";
        return $render->fetch('sidepanel.html');
    }
}
