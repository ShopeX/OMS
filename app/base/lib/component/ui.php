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
 * @license  http://ecos.shopex.com/license/gpl GPL License
 */

class base_component_ui {

    var $base_dir='';
    var $base_url='';
    static $inputer = array();
    static $_ui_id = 0;
    var $_form_path = array();
    private $_imgbundle = array();
    private $_pageid = null;

    function __construct($controller, $app_specal=null){
        $this->controller = $controller;
        if( $app_specal){
            $this->app =  $app_specal;
        }else{
            $this->app = $controller->app;
        }
    }

    function table_begin(){
        return '<table>';
    }

    function table_head($headers){
        return '<thead><th>'.implode('</th><th>',$headers).'</th></thead>';
    }

    function table_colset(){
    }

    function table_panel($html){
        return '<div>'.$html.'</div>';
    }

    function table_rows($rows){
        foreach($rows as $row){
            $return[] = '<tr>';
            foreach($row as $k=>$v){
                $return[]=$v;
            }
            $return[] = '</tr>';
        }
        return implode('',$return);
    }

    function table_end(){
        return '</table>';
    }

    function img($params){
        if(is_string($params)){
            $params = array('src'=>$params);
        }
        $app = $params['app'] ? app::get($params['app']) : $this->app;
        
        if(!isset($this->_imgbundle[$app->app_id])){
            $bundleinfo = array();
            //base_kvstore::instance('imgbundle')->fetch('imgbundle_' . $app->app_id, $bundleinfo);
            $this->_imgbundle[$app->app_id] = (array)$bundleinfo;
        }

        if(is_array($this->_imgbundle[$app->app_id]['info']) && array_key_exists($params['src'], $this->_imgbundle[$app->app_id]['info'])){
            $img_info = $this->_imgbundle[$app->app_id]['info'][$params['src']];
            $params['lib'] = kernel::base_url(1) . '/' . $this->_imgbundle[$app->app_id]['bundleimg'] . '?' . $this->_imgbundle[$app->app_id]['mtime'];
            $params['src'] = app::get('base')->res_url.'/transparent.gif';
            $style = "background-image:url({$params['lib']});background-position:0 {$img_info[0]}px;width:{$img_info[1]}px;height:{$img_info[2]}px";
            $params['style'] = $params['style']?($params['style'].';'.$style):$style;
            $params['class'] = $params['class']?('imgbundle '.$params['class']):'imgbundle';
        }else{
            $params['src'] = $app->res_url.'/'.$params['src'];
        }
        unset($params['lib']);
        return utils::buildTag($params,'img');
    }

    function input($params){
        if($params['params']){
            $p = $params['params'];
            unset($params['params']);
            $params = array_merge($p,$params);
        }

        if(is_array($params['type'])){
            $params['options'] = $params['type'];
            $params['type'] = 'select';
        }
        if(!array_key_exists('value',$params) && array_key_exists('default',$params)){
            $params['value'] = $params['default'];
        }
        if(!$params['id']){
            $params['id'] = $this->new_dom_id();
        }


        if(substr($params['type'],0,6)=='table:'){
            list(,$params['object'],$params['app']) = preg_split('/[:|@]/',$params['type']);
            $params['type'] = 'object';
            if($params['name'] == 'cat_id' ){
                $params['type'] = 'goodscat';  
                return $this->input_element('goodscat', $params);
            }
            if($this->input_element('object_'.$params['type'])){
                return $this->input_element('object_'.$params['type'], $params);
            }else{
                return $this->input_element('object', $params);
            }
        }elseif($this->input_element($params['type'])){
            return $this->input_element($params['type'],$params);
        }else{
            return $this->input_element('default',$params);
        }
    }

    function input_element($type,$params=false){
     
        if(!base_component_ui::$inputer){
            if(kernel::is_online()){
                base_component_ui::$inputer = kernel::servicelist('html_input');
            }else{
                base_component_ui::$inputer = array('base_view_input' => new base_view_input);
            }
        }

        if($params===false){
            foreach(base_component_ui::$inputer as $inputer){
                $inputer->app = $this->app;
                if(method_exists($inputer,'input_'.$type)){
                    return true;
                }
            }
        }else{
            foreach(base_component_ui::$inputer as $inputer){

                $inputer->app = $this->app;
                if(method_exists($inputer,'input_'.$type)){

                    $html = $inputer->{'input_'.$type}($params);
                }
            }
            return $html;
        }
        return false;
    }

    function form_start($params=null){

        if(is_string($params)){
            $params = array('action'=>$params);
        }
        if(!$params['action']){
            $params['action'] = 'index.php?'.$_SERVER['QUERY_STRING'];
        }

        array_unshift($this->_form_path,$params);

        $return = '';
        if($params['title']){
            $return.='<h4>'.$params['title'].'</h4>';
            unset($params['title']);
        }

        $return .='<div class="tableform'.($params['tabs']?' tableform-tabs':'').'">';

        if($params['tabs']){

            $this->form_tab_html = array();
            $dom_tab_ids = array();
            $current = false;

            foreach($params['tabs'] as $k=>$tab){
                $dom_id = $this->new_dom_id();
                $dom_tab_ids[$k] = $dom_id;
                if($current){
                    $style = 'style="display:none"';
                }else{
                    $style = '';
                    $current = true;
                }
                $this->form_tab_html[$k] = '<div class="division" id="'.$dom_id.'" '.$style.'><table width="100%" cellspacing="0" cellpadding="0">';
            }

            $return.='<div class="tabs-wrap clearfix"><ul>';
            $current = false;
            foreach($params['tabs'] as $k=>$tab){
                if($current){
                    $style = '';
                }else{
                    $style = ' current';
                    $current = true;
                }
                $return.='<li id="_'.$dom_tab_ids[$k].'" class="tab'.
                    $style.'" onclick="setTab([\''.
                    $dom_tab_ids[$k].'\',[\''.implode('\',\'',$dom_tab_ids).'\']],[\'current\'])"><span>'.$tab.'</span></li>';
            }
            $return.='</ul>';

            $this->_form_path[0]['element_started'] = true;
        }

        return utils::buildTag($params,'form',false).$return;
    }

    function form_input($params){
        if(!isset($params['id'])){
            $params['id'] = $this->new_dom_id();
        }
        if(isset($params['tab'])){
            $tab = $params['tab'];
            unset($params['tab']);
        }

        $return ='';

        if(!$this->_form_path[0]['element_started']){
            $return.=<<<EOF
    <div class="division">
        <table width="100%" cellspacing="0" cellpadding="0">
EOF;
            $this->_form_path[0]['element_started'] = true;
        }
        if($params['helpinfo']) $span = '<label class="help">'.$params['helpinfo'].'</label>';
        else $span='';
        if (isset($params['style']) && $params['style'] && $params['style'] == 'display:none;')
        {
            $return.='<tr style="display:none;"><th>'.($params['required']?'<em class="red">*</em>':'').'<label for="'.$params['id'].'">'.$params['title'].'</label>'.
                     '</th><td>'.$this->input($params).$span.'</td></tr>';
        }
        else
            $return.='<tr><th>'.($params['required']?'<em class="red">*</em>':'').'<label for="'.$params['id'].'">'.$params['title'].'</label>'.
                     '</th><td>'.$this->input($params).$span.'</td></tr>';
        if(isset($this->form_tab_html[$tab])){
            $this->form_tab_html[$tab].=$return;
            return '';
        }else{
            return $return;
        }
    }

    static function new_dom_id(){
        return 'dom_el_'.substr(md5(time()),0,6).intval(self::$_ui_id++);
    }

    function form_end($has_ok_btn=1, $btn_txt='确定'){

        if($this->_form_path[0]['element_started']){
            $return .='</table></div>';
        }

        foreach((array)$this->form_tab_html as $html){
            $return.=$html.'</table></div>';
        }

        if($has_ok_btn){
            $return .='<div class="table-action">'.$this->button(array(
                'type'=>'submit',
                'class'=>'btn-primary',
                'label'=>$btn_txt,
            )).'</div>';
        };

        array_shift($this->_form_path);
        if($this->form_tab_html){
            $return.='</div>';
        }
        $return .='</div></form>';
        $this->form_tab_html = null;
        return $return;
    }

    function button($params){
        if($params['class']){
            $params['class'] = 'btn '.$params['class'];
        }else{
            $params['class'] = 'btn';
        }

        if($params['icon']){
            $icon = '<i class="btn-icon">'.$this->img(array('src'=>'bundle/'.$params['icon'], 'app'=>$params['app'])).'</i>';
            $params['class'] .= ' btn-has-icon';
            unset($params['icon']);
        }

        $app = $params['app']?app::get($params['app']):$this->app;

        if($params['label']){
            $label = htmlspecialchars($app->_($params['label']));
            unset($params['label']);
        }

        $type = $params['type'];
        if($type=='link'){
            $element = 'a';
        }else{
            $element = 'button';
            if($params['href']){
                $params['onclick'] = 'W.page(\''.$params['href'].'\')';
                unset($params['href']);
            }
            if($type!='submit'){
                $params['type'] = 'button';
            }
        }

        if($params['dropmenu']){
            if(!$params['id']){
                $params['id'] = $this->new_dom_id();
            }

            if($type!='dropmenu'){
                $element = 'span';
                $class .= ' btn-drop-menu drop-active';
                $drop_handel_id = $params['id'].'-handel';
                $dropmenu = '<img dropfor="'.$params['id'].'"
                    id="'.$drop_handel_id.'" dropmenu='.$params['dropmenu']
                    .' src="'.app::get('base')->res_url.'/transparent.gif" class="drop-handle drop-handle-stand" />';
                unset($params['dropmenu']);
            }else{
                $drop_handel_id = $params['id'];
                $dropmenu = '<img src="'.app::get('base')->res_url.'/transparent.gif" class="drop-handle" />';
            }
            $scripts = '<script>new DropMenu("'.$drop_handel_id.'",{'.$params['dropmenu_opts'].'});';
            $scripts .= '</script>';
        }

        return utils::buildTag($params,$element,0).'<span><span>'.$icon.$label.$dropmenu.'</span></span></'.$element.'>'.$script;
    }

    function script($params){
        $app = $params['app']?app::get($params['app']):$this->app;      //todo：APP机制了，每个APP自己控制
		
        $pdir = (defined('DEBUG_JS') && constant('DEBUG_JS'));
		
		if( $params['pdir'] && !$pdir ){
	        $pdir = $params['pdir'];  
        }else{
            $pdir = 'js';
        }

        $file = $app->res_dir.'/'.$pdir.'/'.$params['src'];
        if($params['content']){
            return '<script type="text/javascript" >'.file_get_contents($file).'</script>';
        }else{
            $time = substr(cachemgr::ask_cache_check_version(), 0, 6);
            return '<script type="text/javascript" src="'.$app->res_url.'/'.$pdir.'/'.$params['src'].'?'.$time.'"></script>';
        }
    }

    function css($params)
    {
        $default = array(
            'rel' => 'stylesheet',
            'type' => 'text/css',
            'media' => 'screen, projection',
        );
        $app = $params['app']?app::get($params['app']):$this->app;
		$pdir = (defined('DEBUG_CSS') && constant('DEBUG_CSS'));
		if( $params['pdir'] && !$pdir ){
	        $pdir = $params['pdir'];  
        }else{
            $pdir = 'css';
        }
		
		if (is_dir($app->res_dir.'/'.$pdir))
			$file = $app->res_url.'/'.$pdir.'/'.$params['src'];
		else
			$file = $app->res_url.'/'.$params['src'];
		if ($params['pdir']) unset($params['pdir']);
		
        if(isset($params['src'])) unset($params['src']);
        if(isset($params['app'])) unset($params['app']);
        $params = count($params) ? $params+$default : $default;
        foreach($params AS $k=>$v){
            $ext .= sprintf('%s="%s" ', $k, $v);
        }
        $time = substr(cachemgr::ask_cache_check_version(), 0, 6);
        $file = $file . '?' . $time;
        return sprintf('<link href="%s" %s/>', $file, $ext);
    }//End Function

    function tree($model,$template='%2$s'){

        $pagelimit = 20;

        $model = $this->app->model($model);
        $pid = intval($_GET['tree']['pid']);
        $offset = intval($_GET['tree']['p']);

        foreach($model->schema['columns'] as $k=>$col){
            if($col['parent_id']){
                $pid_col = $k;
            }
        }

        $items = $model->getList($model->idColumn.','.$model->textColumn,array($pid_col=>$pid),$offset,$pagelimit);
        foreach($items as $item){
            $html.=sprintf('<span class="node node-hasc" item="%1$d">
                <span class="node-handle">&nbsp;</span>'
                .$template.'</span>'
                ,$item[$model->idColumn],$item[$model->textColumn]);
        }

        $count = $model->count(array($pid_col=>$pid));
        $current = $offset+count($items);
        if($count>$current){
            $html.='<div><span class="more" pid="'.$pid.'" more="'.($current).'">'.($count-$current).app::get('base')->_('个未显示').'&hellip;</span></div>';
        }

        if($_GET['act']=='treenode'){
            return $html;
        }else{
            $new_dom_id = $this->new_dom_id();
            $params = json_encode(array('args'=>func_get_args()));
            return <<<EOF
<div class="x-tree-list" id="{$new_dom_id}" child="{$count}">{$html}</div>
<script>
init_tree($('{$new_dom_id}'),{$params});
</script>
EOF;
        }
    }

    function pager($params){

        if(substr($params['link'],0,11)=='javascript:'){
            $tag = 'span';
            $this->pager_attr = 'onclick';
            $params['link'] = substr($params['link'],11);
        }else{
            $tag = 'a';
            $this->pager_attr = 'href';
        }

        $this->pager_tag = $tag;

        if(!$params['current'])$params['current'] = 1;
        if(!$params['total'])$params['total'] = 1;
        if($params['total']<2){
            return '';
        }

        if(!$params['nobutton']){
            if($params['current']>1){
                $prev = '<'.$tag.' '.$this->pager_attr.'="'.sprintf($params['link'],$params['current']-1)
                    .'" class="prev">&laquo;</'.$tag.'>';
            }else{
                $prev = '<span class="prev disabled">&laquo;</span>';
            }

            if($params['current']<$params['total']){
                $next = '<'.$tag.' '.$this->pager_attr.'="'.sprintf($params['link'],$params['current']+1)
                    .'" class="next">&raquo;</'.$tag.'>';
            }else{
                $next = '<span class="next disabled">&raquo;</span>';
            }
        }

        $c = $params['current']; $t=$params['total']; $v = array();  $l=$params['link'];;

        if($t<11){
            $v[] = $this->pager_link(1,$t,$l,$c);
            //123456789
        }else{
            if($t-$c<8){
                $v[] = $this->pager_link(1,3,$l);
                $v[] = $this->pager_link($t-8,$t,$l,$c);
                //12..50 51 52 53 54 55 56 57
            }elseif($c<10){
                $v[] = $this->pager_link(1,max($c+3,10),$l,$c);
                $v[] = $this->pager_link($t-1,$t,$l);
                //1234567..55
            }else{
                $v[] = $this->pager_link(1,3,$l);
                $v[] = $this->pager_link($c-2,$c+3,$l,$c);
                $v[] = $this->pager_link($t-1,$t,$l);
                //123 456 789
            }
        }
        $links = implode('&hellip;',$v);

        return <<<EOF
    <div class="pager">
     <div class="pagernum">
      {$prev}{$links}{$next}
     </div>
    </div>
EOF;
    }

    function pager_link($from,$to,$l,$c=null){
        for($i=$from;$i<$to+1;$i++){
            if($c==$i){
                $r[]=' <span class="current">'.$i.'</span> ';
            }else{
                $r[]=' <'.$this->pager_tag.' '.$this->pager_attr.'="'.sprintf($l,$i).'">'.$i.'</'.$this->pager_tag.'> ';
            }
        }
        return implode(' ',$r);
    }

    function lang_script($params){
        $app = $params['app']?app::get($params['app']):$this->app;
        $lang = kernel::get_lang();
        $src = 'js/' . $params['src'];
        if(is_array($app->lang_resource[$lang]) && in_array($src, $app->lang_resource[$lang])){
            $file = $app->lang_dir . '/' . $lang . '/' . $src;
        }elseif(is_array($app->lang_resource['zh-cn']) && in_array($src, $app->lang_resource['zh-cn'])){
            $file = $app->lang_dir . '/zh-cn/' . $src;
        }else{
            return '';
        }

        if($params['content']){
            return '<script type="text/javascript" >'.file_get_contents($file).'</script>';
        }else{
            $time = substr(cachemgr::ask_cache_check_version(), 0, 6);
            return '<script type="text/javascript" src="'.$app->lang_url.'/'.$lang.'/'.$src.'?'.$time.'"></script>';
        }
    }

    function lang_css($params) 
    {
        $default = array(
            'rel' => 'stylesheet',
            'type' => 'text/css',
            'media' => 'screen, projection',
        );
        $app = $params['app']?app::get($params['app']):$this->app;
        $lang = kernel::get_lang();
        $src = 'css/' . $params['src'];
        if(is_array($app->lang_resource[$lang]) && in_array($src, $app->lang_resource[$lang])){
            $file = $app->lang_url . '/' . $lang . '/' . $src;
        }elseif(is_array($app->lang_resource['zh-cn']) && in_array($src, $app->lang_resource['zh-cn'])){
            $file = $app->lang_url . '/zh-cn/' . $src;
        }else{
            return '';
        }
        if(isset($params['src'])) unset($params['src']);
        if(isset($params['app'])) unset($params['app']);
        $params = count($params) ? $params+$default : $default;
        foreach($params AS $k=>$v){
            $ext .= sprintf('%s="%s" ', $k, $v);
        }
        $time = substr(cachemgr::ask_cache_check_version(), 0, 6);
        $file = $file . '?' . $time;
        return sprintf('<link href="%s" %s/>', $file, $ext);
    }//End Function

    public function pageid() 
    {
        if(is_null($this->_pageid)){
            $obj = kernel::single('base_component_request');
            $key = md5(sprintf('%s_%s_%s_%s', $obj->get_app_name(), $obj->get_ctl_name(), $obj->get_act_name(), serialize($obj->get_params())));
            $this->_pageid = base_convert(strtolower($key), 16, 10);
            $this->_pageid = substr($this->dec2any($this->_pageid), 4, 8);
        }
        return $this->_pageid;
    }//End Function

    function dec2any($num, $base=62, $index=false) {
        if (! $base ) {
            $base = strlen( $index );
        } else if (! $index ) {
            $index = substr( "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ,0 ,$base );
        }
        $out = "";
        for ( $t = floor( log10( $num ) / log10( $base ) ); $t >= 0; $t-- ) {
            $a = floor( $num / pow( $base, $t ) );
            $out = $out . substr( $index, $a, 1 );
            $num = $num - ( $a * pow( $base, $t ) );
        }
        return $out;
    }

}
