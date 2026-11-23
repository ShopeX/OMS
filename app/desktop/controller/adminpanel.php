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
 
class desktop_ctl_adminpanel extends desktop_controller{

    var $workground = 'desktop_ctl_system';

    function index(){
        
        $user = kernel::single('desktop_user');
        $menus = $this->app->model('menus');
        if($user->is_super()){      
        $list = app::get('desktop')->model('menus')->getlist(
            'app_id,menu_title,addon,menu_path',array(
                'menu_type'=>'panelgroup',
                'disabled'=>'false'
        ));
        $pers = $menus->getList('permission',array('menu_type' => 'permission','disabled' => 'false'));
	    foreach($pers as $val){
	        $aper[] = $val['permission'];
	    }
        foreach($list as $row){
            $group[$row['app_id'].'_'.$row['addon']] = array(
                    'icon'=>app::get($row['app_id'])->res_url.'/'.$row['menu_path'],
                    'title'=>$row['menu_title'],
                    'items'=>array(),
                );
        }

             $list = app::get('desktop')->model('menus')->getlist(
            'app_id,menu_title,menu_path,permission',array(
                'menu_type'=>'adminpanel',
                'disabled'=>'false',
                'display' => 'true',
        ));
        foreach($list as $row){
        	if(!in_array($row['permission'],(array)$aper))
        	continue;
            $p = strpos($row['menu_title'],':');
            if($p){
                $group_name = substr($row['menu_title'],0,$p);
                $row['menu_title'] = substr($row['menu_title'],$p+1);
                if(isset($group[$group_name])){
                    $group[$group_name]['items'][] = $row;
                }else{
                    //$group['base_other']['items'][] = $row;
                }
            }else{
                $group['base_other']['items'][] = $row;
            }

        }

        $this->pagedata['groups'] = $group;
            
        }
        else{
        $group = $user->group();
        $aPer = array();
        foreach($group as $val){
            #$sdf = $menus->dump($val);
            $aPer[] = $val;
        }
        
        $adminpanel = app::get('desktop')->model('menus')->getlist(
            'app_id,menu_title,menu_path',array(
                'menu_type'=>'adminpanel',
                'disabled'=>'false',
                'permission'=>$aPer,
                'display' => 'true',
        ));
        
        $list = app::get('desktop')->model('menus')->getlist(
            'app_id,menu_title,addon,menu_path',array(
                'menu_type'=>'panelgroup',
                'disabled'=>'false'
        ));
        $aTitle = array();
        foreach($adminpanel as $key=>$val){
        $aTmp = explode(':',$val['menu_title']);
        $aTitle[] = $aTmp[0];    
        }
        $list_bak = array();
        foreach($list as $key=>$val){
        $fag = $val['app_id'].'_'.$val['addon'];
        if(in_array($fag,$aTitle))
            $list_bak[] = $val;
        }
        $group = array();
        foreach($list_bak as $row){
            $group[$row['app_id'].'_'.$row['addon']] = array(
                    'icon'=>app::get($row['app_id'])->res_url.'/'.$row['menu_path'],
                    'title'=>$row['menu_title'],
                    'items'=>array(),
                );
        }
        $list = app::get('desktop')->model('menus')->getlist(
            'app_id,menu_title,menu_path',array(
                'menu_type'=>'adminpanel',
                'disabled'=>'false',
                'permission'=>$aPer,
                'display' => 'true',
        ));
        foreach($list as $row){
            $p = strpos($row['menu_title'],':');
            if($p){
                $group_name = substr($row['menu_title'],0,$p);
                $row['menu_title'] = substr($row['menu_title'],$p+1);
                if(isset($group[$group_name])){
                    $group[$group_name]['items'][] = $row;
                }else{
                    //$group['base_other']['items'][] = $row;
                }
            }else{
                $group['base_other']['items'][] = $row;
            }

        }

        $this->pagedata['groups'] = $group;
        }
        

        $this->page('system/adminpanel.html');
    }

    function licence(){
        $this->url_frame(base_misc_url::licence());
    }

}
