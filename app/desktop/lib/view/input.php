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
 
class desktop_view_input{
    function input_skunum($params) {
		$html = utils::buildTag($params,'input autocomplete="off" class="x-input '.$params['class'].'" style="width:24px;"');

		return $html;
	}

    function input_image($params){

        $params['type'] = 'image';
        $ui = new base_component_ui($this);
        $domid = $ui->new_dom_id();
        
        $input_name = $params['name'];
        $input_value = $params['value'];
      
        $image_src = base_storager::image_path($input_value,'s');
        
        
        
        if(!$params['width']){
           $params['width']=50;
        }
        
        if(!$params['height']){
         $params['height']=50;
        }

        $imageInputWidth = $params['width']+24;
        $url="&quot;index.php?app=desktop&act=alertpages&goto=".urlencode("index.php?app=image&ctl=admin_manage&act=image_broswer")."&quot;";

        $html = '<div class="image-input clearfix" style="width:'.$imageInputWidth.'px;" gid="'.$domid.'">';
            $html.= '<div class="flt"><div class="image-input-view" style="font-size:12px;text-align:center;width:';
            $html.=  $params['width'].'px;line-height:'.$params['height'].'px;height:'.$params['height'].'px;overflow:hidden;">';
            if(!$image_src){
                $image_src = app::get('desktop')->res_url.'/transparent.gif';
            }
            $html.= '<img src="'.$image_src.'" onload="$(this).zoomImg('.$params['width'].','.$params['height'].',function(mw,mh,v){this.setStyle(&quot;marginTop&quot;,(mh-v.height)/2)});"/>';
                          
            
            $html.= '</div></div>';
            $html.= '<div class="image-input-handle" onclick="Ex_Loader(&quot;modedialog&quot;,function(){new imgDialog('.$url.',{handle:this});}.bind(this));" style="width:20px;height:'.$params['height'].'px;">'.app::get('desktop')->_('选择')."".$ui->img(array('src'=>'bundle/arrow-down.gif','app'=>'desktop'));
            $html.= '</div>';
            $html.= '<input type="hidden" name="'.$input_name.'" value="'.$input_value.'"/>';
            $html.= '</div>';
            
        
        
        return $html;
    }

    function input_object($params){
        $return_url = $params['return_url']?$params['return_url']:'index.php?app=desktop&ctl=editor&act=object_rows'; 
        $callback = $params['callback']?$params['callback']:'';
        $params['breakpoint'] = isset($params['breakpoint'])?$params['breakpoint']:100;

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
        $textcol = is_array($textcol) ? $textcol : explode(',',$textcol);
        $_textcol = $textcol;
        $textcol = current($textcol);


        $tmp_filter = $params['filter']?$params['filter']:null;
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
            $tmp_params['data-search'] = 'fuzzy-search';
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
            if(isset($params['items']) && count($params['items'])){
                $params['display_datarow'] = 'true';
            }
            $render->pagedata['_input'] = $params;
            return $render->fetch('finder/input.html');
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
            
            $object = utils::http_build_query($vars);

            $url = 'index.php?app=desktop&act=alertpages&goto='.urlencode('index.php?app=desktop&ctl=editor&act=finder_common&app_id='.$app_id.'&'.$object.$getdata);
            
            $render->pagedata['string'] = $string;
            $render->pagedata['url'] = $url;
            $render->pagedata['return_url'] = $return_url;
            $render->pagedata['id'] = $id;
            $render->pagedata['params'] = $params;
            $render->pagedata['object'] = $object;
            $render->pagedata['callback'] = $callback;
            return $render->fetch('finder/input_radio.html');
        }
    }
    function input_html($params){
        $id = 'mce_'.substr(md5(rand(0,time())),0,6);
        $editor_type=app::get('desktop')->getConf("system.editortype");
        $editor_type==''?$editor_type='wysiwyg':$editor_type='wysiwyg';
        $includeBase=$params['includeBase']?$params['includeBase']:true;
        $params['id']=$id;

        $img_src = app::get('desktop')->res_url;
        $render = new base_render(app::get('desktop'));
        $render->pagedata['id'] = $id;
        $render->pagedata['img_src'] = $img_src;
        $render->pagedata['includeBase'] = $includeBase;
        $render->pagedata['params'] = $params;
        
        $style2=$render->fetch('editor/html_style2.html');

        if($editor_type =='textarea'||$params['editor_type']=='textarea'){
            $html=$style2;
        }else{
            $style1 = $render->fetch('editor/html_style1.html');
            $html=$style1;
            $html.=$style2;
        }
        return $html;
    }

    /**
     * 带放大镜功能的图片输入组件（支持单图和多图）
     * @param array $params 参数数组
     * @return string HTML代码
     */
    function input_image_magnifier($params){
        $ui = new base_component_ui($this);
        $domid = $ui->new_dom_id();
        
        // 参数处理
        $input_name = $params['name'];
        $input_value = $params['value'];
        $max_images = isset($params['max_images']) ? intval($params['max_images']) : 1; // 默认支持1张图片
        
        if ($max_images > 1) {
            // 多图片模式
            $existing_images = array();
            if ($input_value) {
                // 如果是逗号分隔的多个图片ID
                if (strpos($input_value, ',') !== false) {
                    $image_ids = explode(',', $input_value);
                    foreach ($image_ids as $image_id) {
                        $image_id = trim($image_id);
                        if ($image_id) {
                            try {
                                $imageModel = app::get('image')->model('image');
                                $image_info = $imageModel->dump($image_id);
                                if ($image_info) {
                                    $existing_images[] = array(
                                        'image_id' => $image_id,
                                        'url' => base_storager::image_path($image_id, 's'),
                                        'full_url' => $image_info['url']
                                    );
                                }
                            } catch (Exception $e) {
                                // 忽略无效的图片ID
                            }
                        }
                    }
                } else {
                    // 单个图片ID
                    try {
                        $imageModel = app::get('image')->model('image');
                        $image_info = $imageModel->dump($input_value);
                        if ($image_info) {
                            $existing_images[] = array(
                                'image_id' => $input_value,
                                'url' => base_storager::image_path($input_value, 's'),
                                'full_url' => $image_info['url']
                            );
                        }
                    } catch (Exception $e) {
                        // 忽略无效的图片ID
                    }
                }
            }
            
            // 通过 target_type 和 target_id 获取关联图片（如果现有图片为空）
            if (empty($existing_images) && isset($params['target_type']) && isset($params['target_id'])) {
                try {
                    $imageModel = app::get('image')->model('image');
                    $attachedImages = $imageModel->getAttachedImages($params['target_type'], $params['target_id']);
                    
                    foreach ($attachedImages as $attachedImage) {
                        $existing_images[] = array(
                            'image_id' => $attachedImage['image_id'],
                            'url' => $attachedImage['s_url'] ?: $attachedImage['full_url'],
                            'full_url' => $attachedImage['full_url']
                        );
                    }
                } catch (Exception $e) {
                    // 如果获取失败，继续使用空数组
                }
            }
            
            // 默认尺寸
            $display_width = $params['display_width'] ?: ($params['width'] ?: 50);
            $display_height = $params['display_height'] ?: ($params['height'] ?: 50);
            
            // 放大镜相关参数
            $magnifier_class = $params['magnifier_class'] ?: 'img-magnifier';
            
            // 样式参数
            $border_style = $params['border_style'] ?: '2px dashed #ddd';
            $border_radius = $params['border_radius'] ?: '4px';
            $cursor_style = $params['cursor_style'] ?: 'pointer';
            
            // 只读模式参数
            $readonly = isset($params['readonly']) ? $params['readonly'] : false;
            
            // 准备模板数据
            $render = new base_render(app::get('desktop'));
            $render->pagedata['domid'] = $domid;
            $render->pagedata['input_name'] = $input_name;
            $render->pagedata['input_value'] = $input_value;
            $render->pagedata['existing_images'] = $existing_images;
            $render->pagedata['max_images'] = $max_images;
            $render->pagedata['display_width'] = $display_width;
            $render->pagedata['display_height'] = $display_height;
            $render->pagedata['magnifier_class'] = $magnifier_class;
            $render->pagedata['border_style'] = $border_style;
            $render->pagedata['border_radius'] = $border_radius;
            $render->pagedata['cursor_style'] = $cursor_style;
            $render->pagedata['transparent_gif'] = app::get('desktop')->res_url.'/transparent.gif';
            $render->pagedata['size'] = isset($params['size']) ? $params['size'] : '';
            $render->pagedata['target_type'] = $params['target_type'] ?: '';
            $render->pagedata['target_id'] = $params['target_id'] ?: '';
            $render->pagedata['readonly'] = $readonly;
            
            // 使用统一的HTML模板（支持多图片）
            return $render->fetch('input_image_magnifier.html');
        } else {
            // 单图片模式（原有逻辑）
            $image_src = '';
            $main_image = null;
            
            // 第一优先级：直接传入的 image_src
            if (isset($params['image_src']) && $params['image_src']) {
                $image_src = $params['image_src'];
            }
            // 第二优先级：通过 image_id 用 dump 查询
            elseif ($input_value) {
                try {
                    $imageModel = app::get('image')->model('image');
                    $main_image = $imageModel->dump($input_value);
                    
                    if ($main_image) {
                        // 根据需要的尺寸获取对应的URL
                        $size = isset($params['size']) ? $params['size'] : '';
                        if ($size && isset($main_image[strtolower($size) . '_url'])) {
                            $image_src = $main_image[strtolower($size) . '_url'];
                        } else {
                            $image_src = $main_image['url'];
                        }
                        
                        // 生成完整路径
                        if ($main_image['storage'] !== 'network') {
                            $image_src = base_storager::image_path($input_value, $size);
                        }
                    }
                } catch (Exception $e) {
                    // 如果查询失败，继续到下一优先级
                }
            }
            // 第三优先级：通过 target_type 和 target_id 获取关联图片
            elseif (isset($params['target_type']) && isset($params['target_id'])) {
                try {
                    $imageModel = app::get('image')->model('image');
                    $attachedImages = $imageModel->getAttachedImages($params['target_type'], $params['target_id']);
                    
                    if (!empty($attachedImages)) {
                        $main_image = $attachedImages[0]; // 获取第一张图片
                        $image_src = $main_image['full_url'];
                        $input_value = $main_image['image_id'];
                    }
                } catch (Exception $e) {
                    // 如果获取失败，继续到默认值
                }
            }
            
            // 如果以上都没有获取到，使用默认值
            if (!$image_src) {
                if ($input_value) {
                    $image_src = base_storager::image_path($input_value, 's');
                } else {
                    $image_src = app::get('desktop')->res_url.'/transparent.gif';
                }
            }
            
            // 默认尺寸
            $display_width = $params['display_width'] ?: ($params['width'] ?: 50);
            $display_height = $params['display_height'] ?: ($params['height'] ?: 50);
            
            // 放大镜相关参数
            $magnifier_src = $params['magnifier_src'] ?: $image_src;
            $magnifier_class = $params['magnifier_class'] ?: 'img-magnifier';
            
            // 样式参数
            $border_style = $params['border_style'] ?: '2px dashed #ddd';
            $border_radius = $params['border_radius'] ?: '4px';
            $cursor_style = $params['cursor_style'] ?: 'pointer';
            
            // 只读模式参数
            $readonly = isset($params['readonly']) ? $params['readonly'] : false;
            
            // 准备模板数据
            $render = new base_render(app::get('desktop'));
            $render->pagedata['domid'] = $domid;
            $render->pagedata['input_name'] = $input_name;
            $render->pagedata['input_value'] = $input_value;
            $render->pagedata['image_src'] = $image_src;
            $render->pagedata['display_width'] = $display_width;
            $render->pagedata['display_height'] = $display_height;
            $render->pagedata['magnifier_class'] = $magnifier_class;
            $render->pagedata['magnifier_src'] = $magnifier_src;
            $render->pagedata['border_style'] = $border_style;
            $render->pagedata['border_radius'] = $border_radius;
            $render->pagedata['cursor_style'] = $cursor_style;
            $render->pagedata['transparent_gif'] = app::get('desktop')->res_url.'/transparent.gif';
            $render->pagedata['size'] = isset($params['size']) ? $params['size'] : '';
            $render->pagedata['target_type'] = $params['target_type'] ?: '';
            $render->pagedata['target_id'] = $params['target_id'] ?: '';
            $render->pagedata['readonly'] = $readonly;
            
            // 使用单图片HTML模板
            return $render->fetch('input_image_magnifier.html');
        }
    }

}
