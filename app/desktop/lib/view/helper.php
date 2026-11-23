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
 
class desktop_view_helper{

    function block_tab($params, $content, &$smarty){
        if(null!==$content){
            for($i=count($smarty->_tag_stack);$i>0;$i--){
                if($smarty->_tag_stack[$i-1][0]=='tabber'){
                    $id = $smarty->_tag_stack[$i-1][1]['_tabid'].'-'.intval($smarty->_tag_stack[$i-1][1]['_i']++);
                    foreach($params as $k=>$v){
                        if($k!='name' && $k!='url'){
                            $attrs[] = $k.'="'.htmlspecialchars($v).'"';
                        }
                    }
                    $smarty->_tag_stack[$i-1][1]['items'][$id]=$params;
                    if(!isset($smarty->_tag_stack[$i-1][1]['current']) || $params['current']){
                        $smarty->_tag_stack[$i-1][1]['current']=$id;
                    }
                    break;
                }
            }
            return '<div id="'.$id.'" style="display:none" '.implode(' ',(array)$attrs).'>'.$content.'</div>';
        }
    }

    function block_tabber($params, $content, &$smarty){
        if(null===$content){
            $i = count($smarty->_tag_stack)-1;
            $smarty->_tag_stack[$i][1]['_tabid']=substr(md5(rand(0,time())),0,6);
            $smarty->_tag_stack[$i][1]['_i']=0;
        }else{
            foreach($params as $k=>$v){
                if($k!='items' && $k!='class'){
                    $attrs[] = $k.'="'.htmlspecialchars($v).'"';
                }
            }

            foreach($params['items'] as $k=>$v){
                $cls = $k==$params['current']?'tab current':'tab';
                $a = array_slice($params['items'],0,count($params['items']));
                unset($a[$k]);
                $a = "['".$k.'\',[\''.implode('\',\'',array_keys($a)).'\']]';
                $c="['current','tab']";
                $handle[]="<li class=\"{$cls} {$v['class']}\"".($v['url']?('url="'.$v['url'].'"'):'')." onclick=\"setTab({$a},{$c})\" id=\"_{$k}\"><span>{$v['name']}</span></li>";
            }
            return '<div class="tabs-wrap'.($params['class']?(' '.$params['class']):'').'" '.implode(' ',$attrs).'><ul>'.implode(' ',$handle).'</ul></div><div class="tabs">'.str_replace('id="'.$params['current'].'" style="display:none"','id="'.$params['current'].'"',$content).'</div>';
        }
    }
    
    function block_help($params, $content, &$template_object){
        if(null!==$content){
            $help_types = array(
                'info'=>array('size'=>18,'icon'=>app::get('desktop')->res_url.'/tips_info.gif'),
                'dialog'=>array('size'=>18,'icon'=>app::get('desktop')->res_url.'/tips_info.gif','dialog'=>1),
                'link'=>array('size'=>15,'icon'=>app::get('desktop')->res_url.'/tips_help.gif'),
                'link-mid'=>array('size'=>14,'icon'=>app::get('desktop')->res_url.'/tips_help_mid.gif'),
                'link-small'=>array('size'=>12,'icon'=>app::get('desktop')->res_url.'/tips_help_small.gif'),
            );
            if(!is_array($params)) {
                $params = ['params'=>$params];
            }
            $params['dom_id'] = base_component_ui::new_dom_id();
            if($content=trim($content)){
                $params['text'] = preg_replace( array('/\n/','/\r/','/\"/','/\'/'), array('<br>','<br>','&quot;','&#39;'), $content);
            }
            $params['type'] = isset($help_types[$params['type']])?$help_types[$params['type']]:$help_types['info'];
            //$vars = $template_object->_vars;
            //unset( $template_object->_vars['docid'] );
            //$template_object->_vars = array_merge($params,$vars);
            //$template_object->_vars = $params;
            $tmp = $template_object->_fetch_compile_include('desktop', 'helper.html', $params);
            //$template_object->_vars = $vars;
            return $tmp;
        }
    }
    
    function block_permission($params, $content, &$tpl){
        
        if($params['perm_id'] && !$tpl->has_permission($params['perm_id'])){
            return null;
        }
        return $content;
    }
    
    function function_filter($params,&$smarty){
        $o = new desktop_finder_builder_filter_render();
        $o->name_prefix = $params['name'];
        if($params['app']){ 
            $app = app::get($params['app']);
        }else{
            $app = $smarty->app;
        }
        $html = $o->main($params['object'],$app,$filter,$smarty);
        echo $html;
    }

    function function_uploader($params, &$smarty){
        echo $smarty->_fetch_compile_include('desktop','system/tools/uploader.html',$params);
    }

    public function function_desktop_header($params, &$smarty) 
    {
        $headers = $smarty->pagedata['headers'];
        if(is_array($headers)){
            foreach($headers AS $header){
                $html .= $header;
            }
        }//
        $services = kernel::servicelist("desktop_view_helper");
        foreach($services AS $service){
            if(method_exists($service, 'function_desktop_header'))
                $html .= $service->function_desktop_header($params, $smarty);
        }
        return $html;
    }//End Function

    public function function_desktop_footer($params, &$smarty) 
    {
        $footers = $smarty->pagedata['footers']; 
        if(is_array($footers)){
            foreach($footers AS $footer){
                $html .= $footer;
            }
        }//
        $services = kernel::servicelist("desktop_view_helper");
        foreach($services AS $service){
            $html .= $service->function_desktop_footer($params, $smarty);
        }
        return $html;
    }//End Function

    function modifier_userdate($timestamp){
        return utils::mydate(app::get('desktop')->getConf('format.date'),$timestamp);
    }

    function modifier_usertime($timestamp){        
        return utils::mydate(app::get('desktop')->getConf('format.time'),$timestamp);
    }

}
