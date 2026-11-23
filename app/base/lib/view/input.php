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

class base_view_input{

    function input_bool($params){
        $params['type'] = 'radio';
        $value = $params['value'];
        unset($params['value']);
        $id = $params['id'];
        $params['id']=$id.'-t';
        $return = utils::buildTag($params,'input value="true"'.(($value==='1' || $value==='true' || $value===true || $value===1)?' checked="checked"':'')).'<label for="'.$params['id'].'">'.app::get('base')->_('是').'</label>';

        $params['id']=$id.'-f';
        //$return .='<br />'.utils::buildTag($params,'input value="false"'.(($value==='false' || !$value )?' checked="checked"':'')).'<label for="'.$params['id'].__('">否</label>');
        $return .= utils::buildTag($params,'input value="false"'.(($value==='0' || $value==='false' || !$value)?' checked="checked"':'')).'<label for="'.$params['id'].'">'.app::get('base')->_('否').'</label>';
        if($params['needall']) {
            $return .= utils::buildTag($params,'input value=""'.((!$value)?' checked="checked"':'')).'<label for="'.$params['id'].'">'.app::get('base')->_('全部').'</label>';
        }
        return $return.'<input type="hidden" name="_DTYPE_BOOL[]" value="'.htmlspecialchars($params['name']).'" />';
    }

    function input_color($params){
        $domid = $params['id'];
        if($params['value']==''){
            $params['value']='#cccccc';
        }
        $params['size'] = 7;
        $params['maxlength'] = 7;
        //todo:ever litie check js
    return utils::buildTag($params,'input autocomplete="off"').' <input type="button" id="c_'.$domid.'" style="width:12px;height:12px;overflow:hidden;background-color:'.$params['value'].';border:0px #ccc solid;cursor:pointer"/><script>
	Ex_Loader("picker",function(){
        new GoogColorPicker("c_'.$domid.'",{
            onSelect:function(hex,rgb,el){
                $("'.$domid.'").set("value",hex);
                el.setStyle("background-color",hex);
			}
		})
	});
</script>';
    }
    function input_label($params){
        return '<span>'.$params['value'].'</span>';
    }
    function input_date($params){
        if(!$params['type']){
            $params['type'] = 'date';
        }
        if(!$params['vtype']){
            $params['vtype'] = 'date';
        }
        if(is_numeric($params['value'])){
            $params['value'] = date('Y-n-j',$params['value']);
        }
        if(isset($params['concat'])){
            $params['name'] .= $params['concat'];
            unset($params['concat']);
        }
        if(!$params['format'] || $params['format']=='timestamp'){
            $prefix = '<input type="hidden" name="_DTYPE_'.strtoupper($params['type']).'[]" value="'.htmlspecialchars($params['name']).'" />';
        }else{
            $prefix = '';
        }

        $params['type'] = 'text';
        $return = utils::buildTag($params,'input class="cal '.$params['class'].'" size="10" maxlength="10" autocomplete="off"');
        return $prefix.$return.'<script>try{Ex_Loader("picker",function(){new DatePickers([$("'.$params['id'].'")]);});}catch(e){$("'.$params['id'].'").makeCalable();}</script>';
    }

    function input_default($params){
        $ignore = array(
            'password'=>1,
            'file'=>1,
            'hidden'=>1,
            'checkbox'=>1,
            'radio'=>1,
        );
        if(!isset($ignore[$params['type']])){
            if(!isset($params['vtype'])){
                $params['vtype'] = $params['type'];
            }
            $params['type'] = 'text';
        }

        return utils::buildTag($params,'input autocomplete="off" class="x-input '.$params['class'].'"');
    }

    function input_file($params){
        $html = '<div class="input-file">';
        $ui = new base_component_ui($this);
        if($params['multi']){
            foreach($params['value'] as $value){
                $item = array(
                    'name'=>$params['name'].'[]',
                    'value'=>$value,
                );
                $html.= $this->_input_file_one($item,$ui->new_dom_id());
            }
        }else{
            $html.= $this->_input_file_one($params,$ui->new_dom_id());
        }
        return $html.'</div>';
    }

    function input_sfile($params){
        if(!$params['f_type']){
            $params['f_type'] = 'public';
        }
        $hidden = '<input type="hidden" name="_f_type" value="'.$params['f_type'].'"/>';
        $code = '<input type="file" name="'.$params['name'].'"/>';
        return $code.$hidden;
    }

    function _input_file_one($params,$domid){
        $html='<span class="input-file" id="'.$domid.'">';
        if($params['value']){
            list($ret['url'],$ret['id'],$ret['storager']) = explode('|',$params['value']);
            $html.='<a href="'.$ret['url'].'">'.app::get('base')->_('下载').'</a>&nbsp;&nbsp;&nbsp;&nbsp;';
        }else{
            $html.='<span class="input-file-selected"></span>';
        }
        $html.='</span>';

        $html.='<button href="index.php?ctl=editor&act=uploader&name='.$params['name'].'&type='.$params['type']
            .'&domid='.$domid.'" target="dialog::{width:350,height:150,title:\"'.app::get('base')->_('选择上传的文件').'\",modal:true}">'.app::get('base')->_('上传').'</button>';
        return $html;
    }

    function input_gender($params){
        $params['type'] = 'radio';
        $value = $params['value'];
        unset($params['value']);
        $id = $params['id'];
        $params['id']=$id.'-m';
        $return = utils::buildTag($params,'input value="male"'.($value=='male'?' checked="checked"':'')).'<label for="'.$params['id'].'">'.app::get('base')->_('男').'</label>';

        $params['id']=$id.'-fm';
        $return .='&nbsp'.utils::buildTag($params,'input value="female"'.($value=='female'?' checked="checked"':'')).'<label for="'.$params['id'].'">'.app::get('base')->_('女').'</label>';
        return $return;
    }
    function input_intbool($params){
        $params['type'] = 'radio';
        $value = $params['value'];
        unset($params['value']);
        $id = $params['id'];
        $params['id']=$id.'-t';
        $return = utils::buildTag($params,'input value="1"'.($value==1?' checked="checked"':'')).'<label for="'.$params['id'].'">'.app::get('base')->_('是').'</label>';

        $params['id']=$id.'-f';
        $return .='<br />'.utils::buildTag($params,'input value="0"'.($value==0?' checked="checked"':'')).'<label for="'.$params['id'].'">'.app::get('base')->_('否').'</label>';
        return $return;
    }


    function input_radio($params){

        $params['type'] = 'radio';
        $options = $params['options'];
        $value = $params['value'];
        unset($params['options'],$params['value']);
        $input_tpl = utils::buildTag($params,'input ',true);
        $id_base = $params['id'];
        $htmls = array();
        $i = 1;
        foreach($options as $k=>$item){
            $id = $id_base.'-'.($i++);
            if($value==$k){
                $html = str_replace('/>',' value="'.htmlspecialchars($k).'" checked="checked" />',$input_tpl);
            }else{
                $html = str_replace('/>',' value="'.htmlspecialchars($k).'" />',$input_tpl);
            }
            $html = str_replace('id="'.$id_base.'"', 'id="'.$id.'"', $html);
            $htmls[]= $html.'<label for="'.$id.'">'.htmlspecialchars($item).'</label>';
        }
        $params['separator'] = $params['separator']?$params['separator']:'<br>';
        $return = implode($params['separator'],$htmls);

        return $return;
    }

    function input_select($params){

        if(is_string($params['options'])){
            $ui = new base_component_ui($this);
            if(!$params['id'])$params['id'] = $ui->new_dom_id();
            $params['remote_url'] = $params['options'];
            $params['options'] = array($params['value']=>$params['value']);
            $script='<script>$(\''.$params['id'].'\').addEvent(\'focus\',window.init_select)</script>';
        }
        if($params['rows']){
            foreach($params['rows'] as $r){
                $step[$r[$params['valueColumn']]]=intval($r['step']);
                $options[$r[$params['valueColumn']]] = $r[$params['labelColumn']];
            }
            unset($params['valueColumn'],$params['labelColumn'],$params['rows']);
        }else{
            $options = $params['options'];
            unset($params['options']);
        }
        $params['name'] = $params['search']?'_'.$params['name'].'_search':$params['name'];
        $value = $params['value'];
        unset($params['value']);
        $html=utils::buildTag($params,'select',false);
        if(!$params['required']){
            $html.='<option></option>';
        }
        foreach((array)$options as $k=>$item){
            if($k==='0' || $k===0){
                $selected = ($value==='0' || $value===0);
            }else{
                $selected = ($value==$k);
            }
            $t_step=$step[$k]?str_repeat('&nbsp;',($step[$k]-1)*3):'';
            $html.='<option'.($selected?' selected="selected"':'').' value="'.htmlspecialchars($k).'">'.$t_step.htmlspecialchars($item).'</option>';
        }
        $html.='</select>';
        return $html.$script;
    }

    function input_textarea($params){
        $value = $params['value'];

        // $params['style'].=';width:'.($params['width']?$params['width']:'400').'px;';
        // $params['style'].=';height:'.($params['height']?$params['height']:'300').'px;';

        unset($params['width'],$params['height']);
        return utils::buildTag($params,'textarea',false).htmlspecialchars($value).'</textarea>';
    }

    function input_time($params){
        $params['type'] = 'time';
        $return = $this->input_date($params);
        if($params['value']){
            $hour = utils::mydate('H',$params['value']);
            $minute = utils::mydate('i',$params['value']);
        }
        $select = '&nbsp;&nbsp; <select name="_DTIME_[H]['.htmlspecialchars($params['name']).']">';
        for($i=0;$i<24;$i++){
            $tmpNum = str_pad($i,2,'0',STR_PAD_LEFT);
            $select.=($hour==$i?'<option value="'.$tmpNum.'" selected="selected">':'<option value="'.$tmpNum.'">').$tmpNum.'</option>';
        }
        $select.='</select> : <select name="_DTIME_[M]['.htmlspecialchars($params['name']).']">';
        for($i=0;$i<60;$i++){
            $tmpNum = str_pad($i,2,'0',STR_PAD_LEFT);
            $select.=($minute==$i?'<option value="'.$tmpNum.'" selected="selected">':'<option value="'.$tmpNum.'">').$tmpNum.'</option>';
        }
        $select.='</select>';

        return $return.$select;
    }

    function input_tinybool($params){
        $params['type'] = 'radio';
        $value = $params['value'];
        unset($params['value']);
        $id = $params['id'];
        $params['id']=$id.'-t';
        $return = utils::buildTag($params,'input value="Y"'.($value=='Y'?' checked="checked"':'')).'<label for="'.$params['id'].'">'.app::get('base')->_('是').'</label>';

        $params['id']=$id.'-f';
        $return .='<br />'.utils::buildTag($params,'input value="N"'.($value=='N'?' checked="checked"':'')).'<label for="'.$params['id'].'">'.app::get('base')->_('否').'</label>';
        return $return;
    }

    function input_goodscat($params){
        return '<div class="object-select clearfix" id="gEditor-GCat-category">
                    <div class="label" rel="分类不限">
                    分类不限
                    </div>
                    <div class="handle">&nbsp;</div><input type="hidden" value="" name='.$params['name'].' id="gEditor-GCat-input">
                </div>
				<script>
					$(\'gEditor-GCat-category\').addEvent(\'click\',function(){
						var handle = $(\'gEditor-GCat-category\'),cat_id= handle.getElement(\'input\').value;
						//cat_id=0;
						new Dialog(\'index.php?app=material&ctl=admin_material_cat&act=get_subcat&p[0]=\'+cat_id,{
							width:600,height:420,resizeable:false,
							onShow:function(){
								this.handle=handle;
							}
						});
					});
				</script>';

    }
}
