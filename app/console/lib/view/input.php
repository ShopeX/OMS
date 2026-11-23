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
 * 2017/11/10 by wangjianjun
 */
class console_view_input{

    public function __construct(&$app){
        $this->app = $app;
    }
    
    public function input_console_object($params){
        
        $return_url = $params['return_url']?$params['return_url']:'index.php?app=console&ctl=admin_stock_artificial_freeze&act=object_rows';
        $callback = $params['callback']?$params['callback'] : ($params['data']['callback'] ? $params['data']['callback'] : '');
        $params['breakpoint'] = isset($params['breakpoint'])?$params['breakpoint']:20;

        $object = $params['object'];
        if(strpos($params['object'],'@')!==false){
            list($object,$app_id) = explode('@',$params['object']);
            $params['object'] = $object;
        }elseif($params['app']){
            $app_id = $params['app'];
        }else{
            $app_id = $this->app->app_id;
        }

        $app = app::get($app_id);
        $o = $app->model($object);
        $render = new base_render(app::get('desktop'));
        $ui = new base_component_ui($app);

        $dbschema = $o->get_schema();

        $params['app_id'] = $app_id;

        if(isset($params['filter'])){
            if(!is_array($params['filter'])){
                parse_str($params['filter'],$params['filter']);
            }
        }

        $params['domid'] = substr(md5(uniqid()),0,6);

        $key = $params['key']?$params['key']:$dbschema['idColumn'];
        $textcol = $params['textcol']?$params['textcol']:$dbschema['textColumn'];

        //显示列 可以多列显示 不完全修改 。。。。。。。
        $textcol = explode(',',$textcol);
        $_textcol = $textcol;
        $textcol = $textcol[0];

        $tmp_filter = $params['filter']?$params['filter']:null;
        
        //人工库存预占“新增货品预占”根据branch_id获取bm_ids 重写$params['filter']
        if ($params["name"] == "bm_id" && $object == "basic_material" && $app_id == "material"){
            if($tmp_filter["branch_id"]){
                $params['filter'] = array("branch_id"=>$tmp_filter["branch_id"]);
            }
        }
        
        $count = $o->count($tmp_filter);
        if($count<=$params['breakpoint']&&!$params['multiple']&&$params['select']!='checkbox'){
            if(strpos($textcol,'@')===false){
                $list = $o->getList($key.','.$textcol,$tmp_filter);
                if(!$list[0]) $type=array();
                foreach($list as $row){
                    $label = $row[$textcol];
                    if(!$label&&method_exists($o,'title_modifier')){
                        $label = $o->title_modifier($row[$key]);
                    }
                    $type[$row[$key]] = $label;
                }

            }else{
                list($name,$table,$app_id) = explode('@',$textcol);
                $app = $app_id?app::get($app_id):$app;
                $mdl = $app->model($table);
                $list = $o->getList($key,$tmp_filter);
                foreach($list as $row){
                    $tmp_row = $mdl->getList($name,array($mdl->idColumn=>$row[$key]),0,1);
                    $label = $tmp_row[0][$name];
                    if(!$label&&method_exists($o,'title_modifier')){
                        $label = $o->title_modifier($row[$key]);
                    }
                    $type[$row[$key]] = $label;
                }

            }
            $tmp_params['name'] = $params['name'];
            $tmp_params['value'] = $params['value'];
            $tmp_params['type'] = $type;
            if($callback)
                $tmp_params['onchange'] = $callback.'(this)';
            $str_filter = $ui->input($tmp_params);
            unset($tmp_params);
            return $str_filter;
        }

        $params['idcol'] = $keycol['keycol'] = $key;
        $params['textcol'] = implode(',',$_textcol);

        $params['_textcol'] = $_textcol;
        if($params['value']){
            if(strpos($params['view'],':')!==false){
                list($view_app,$view) = explode(':',$params['view']);
                $params['view_app'] = $view_app;
                $params['view'] = $view;
            }
            if(is_string($params['value'])){
                $params['value'] = explode(',',$params['value']);
            }
            $params['items'] = &$o->getList('*',array($key=>$params['value']),0,-1);

            //过滤不存在的值
            //某些数据被添加后 可能原表数据已删除，但此处value中还存在。
            $_params_items_row_key = array();
            foreach( $params['items'] as $_params_items_row ) {
                $_params_items_row_key[] = $_params_items_row[$key];
            }
            $params['value'] = implode(',',$_params_items_row_key);
        }

        if(isset($params['multiple']) && $params['multiple']){
            $render->pagedata['_input'] = $params;
            return $render->fetch('admin/gift/input.html','ome');
        }else{
            if($params['value']){
                $string = $params['items'][0][$textcol];
            }else{
                $string = $params['emptytext']?$params['emptytext']:app::get('desktop')->_('请选择...');
            }

            unset($params['app']);

            if($params['data']){
                $_params = (array)$params['data'];
                unset($params['data']);
                $params = array_merge($params,$_params);
            }

            if($params['select']=='checkbox'){
                if($params['default_id'] ) $params['domid'] = $params['default_id'];
                $params['type'] = 'checkbox';
            }else{
                $id = "handle_".$params['domid'];
                $params['type'] = 'radio';
                $getdata = '&singleselect=radio';
            }
            if(is_array($params['items'])){
                foreach($params['items'] as $key=>$item){
                    $items[$key] = $item[$params['idcol']];
                }
            }
            $params['return_url'] = urlencode($params['return_url']);
            $vars = $params;
            $vars['items'] = $items;
            $vars['items'] = array();
            $vars['value'] = array();

            unset($vars['replacehtml']);
            $object = utils::http_build_query($vars);

            $url = 'index.php?app=desktop&act=alertpages&goto='.urlencode('index.php?app=console&ctl=admin_stock_artificial_freeze&act=finder_common&app_id='.$app_id.'&'.$object.$getdata);

            $render->pagedata['string'] = $string;
            $render->pagedata['url'] = $url;
            $render->pagedata['return_url'] = $return_url;
            $render->pagedata['id'] = $id;
            $render->pagedata['params'] = $params;
            $render->pagedata['object'] = $object;
            $render->pagedata['callback'] = $callback;
            return $render->fetch('admin/gift/input_radio.html','ome');
        }
    }

}
