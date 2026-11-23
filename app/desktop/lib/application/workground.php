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
 
class desktop_application_workground extends desktop_application_prototype_xml {

    var $xml='desktop.xml';
    var $xsd='desktop_content';
    var $path = 'workground';

    function current(){
        $this->current = $this->iterator()->current();
        $this->current['action'] = $this->current['action']?$this->current['action']:'index';
        $this->key = $this->current['id'];
        return $this;
    }

    function row($fag,$key){
        $row = array(
            'menu_type' => $this->content_typename(),
            'app_id'=>$this->target_app->app_id,
            'workground'=>$this->current['id'],
                );
        $this->current['action'] = $this->current['action']?$this->current['action']:'index';
        #$row['menu_path'] = "app={$this->target_app->app_id}&ctl={$this->current['controller']}&act={$this->current['action']}";
        if($this->current['controller']&&$this->current['action']&&$this->current['app']){
            $row['menu_path'] = "app=".$this->current['app'].'&ctl='.$this->current['controller'].'&act='.$this->current['action'];
        }else{
            $row['menu_path'] = '';
        }
        $row['menu_title'] = $this->current['name'];
        $row['menu_order'] = $this->current['order'];
        $row['display'] = $this->current['display']?$this->current['dispaly']:true;
        $row['addon'] = $this->current['controller'];
        if ($this->current['icon']) {
            $row['icon'] = $this->current['icon'];
        }
        if ($this->current['en']) {
            $row['en'] = $this->current['en'];
        }
        return $row;
    }
    
    function install(){
        kernel::log('Installing '.$this->content_typename().' '.$this->current['id']);
        $row = app::get('desktop')->model('menus')->dump(array('menu_type'=>'workground','workground'=>$this->current['id']));
        if($row['menu_id']){
            $data = $this->row($fag,$key);
            $data['menu_id'] = $row['menu_id'];
            $data['app_id'] = $row['app_id'];
            app::get('desktop')->model('menus')->save($data);
            return $row['menu_id'];
        }else{
            $menus_row = $this->row($fag,$key);
            return app::get('desktop')->model('menus')->insert($menus_row);
        }
    }
    
    function clear_by_app($app_id){
        if(!$app_id){
            return false;
        }
        app::get('desktop')->model('menus')->delete(array(
            'app_id'=>$app_id,'menu_type' => $this->content_typename()));
    }

}
