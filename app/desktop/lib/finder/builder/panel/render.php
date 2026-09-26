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


class desktop_finder_builder_panel_render
{
    private $finder;

    function __contruct($finder_aliasname){
        $this->finder = $finder;
        $this->finder_aliasname = $finder_aliasname;
    }

    /**
     * 设置Finder
     * @param mixed $finder finder
     * @return mixed 返回操作结果
     */
    public function setFinder($finder) {
        $this->finder = $finder;
    }

    function main($object_name,$app,$filter=null,$controller=null,$cusrender=null){
        if(strpos($_GET['object'],'@')!==false){
            $tmp = explode('@',$object_name);
            $app = app::get($tmp[1]);
            $object_name = $tmp[0];
        }

        $object = $app->model($object_name);
        $ui = new base_component_ui($this->controller,$app);
        require(APP_DIR.'/base/datatypes.php');
        $this->dbschema = $object->get_schema();
        $finder_id = $_GET['_finder']['finder_id'];

        foreach(kernel::servicelist('extend_filter_'.get_class($object)) as $extend_filter){
            $colums = $extend_filter->get_extend_colums($this->finder_aliasname);
            if($colums[$object_name]){
                $this->dbschema['columns'] = array_merge((array)$this->dbschema['columns'],(array)$colums[$object_name]['columns']);
            }
        }

        foreach($this->dbschema['columns'] as $c=>$v){
            if(!$v['filtertype']) continue;

            if( isset($filter[$c]) ) {
                continue;
            }

            if(!is_array($v['type']))
                if(strpos($v['type'],'decimal')!==false&&$v['filtertype']=='number'){
                    $v['type'] = 'number';
                }
            $columns[$c] = $v;
            if(!is_array($v['type']) && $v['type']!='bool' && isset($datatypes[$v['type']]) && isset($datatypes[$v['type']]['searchparams'])){
                $addon='<select search="1" name="_'.$c.'_search" class="x-input-select  inputstyle">';
                foreach($datatypes[$v['type']]['searchparams'] as $n=>$t){
                    $addon.="<option value='{$n}'>{$t}</option>";
                }
                $addon.='</select>';
            }elseif($v['type'] == 'skunum'){
				$addon='<select search="1" name="_'.$c.'_search" class="x-input-select  inputstyle">';
				$__select = array('nequal'=>app::get('base')->_('='),'than'=>app::get('base')->_('>'),'lthan'=>app::get('base')->_('<'));
				foreach($__select as $n=>$t){
                    $addon.="<option value='{$n}'>{$t}</option>";
				}
                $addon.='</select>';
			}else{
                if($v['type']!='bool')
                    $addon = app::get('desktop')->_('是');
                else $addon = '';
            }
            $columns[$c]['addon'] = $addon;
			if($v['type']=='last_modify'){
				$v['type'] = 'time';
			}
             $params = array(
                    'type'=>$v['type'],
                    'name'=>$c,
                );
            if($v['type']=='bool'&&$v['default']){
                $params = array_merge(array('value'=>$v['default']),$params);
            }
            if($this->name_prefix){
                $params['name'] = $this->name_prefix.'['.$params['name'].']';
            }
            if($v['type']=='region'){
                $params['app'] = 'eccommon';
            }
            // 与高级筛选 filter/render.php、filter/top.php 保持一致
            if ($v['filtertype'] == 'fuzzy_search') {
                $params['data-search'] = 'fuzzy-search';
            }
            if ($v['filtertype'] == 'fuzzy_search_multiple') {
                $params['data-search'] = 'fuzzy-search';
                $params['multiple'] = 'multiple';
                $params['name'] = $c.'[]';
            }

            $inputer = $ui->input($params);
            $columns[$c]['inputer'] = $inputer;
        }

        if($cusrender){
          return array('filter_cols'=>$columns,'filter_datatypes'=>$datatypes);
        }

        $render = new base_render(app::get('desktop'));

        if($object->has_tag){
            $render->pagedata['app_id'] = $app->app_id;
            $render->pagedata['tag_type'] = $object_name;
            $tag_inputer = $render->fetch('finder/tag_inputer.html');
            $columns['tag'] = array('filtertype'=>true,'filterdefault'=>true,'label'=>app::get('desktop')->_('标签'),'inputer'=>$tag_inputer);
        }

        if(is_null($this->finder)) {
            $render->pagedata['panel_id'] = '_c';
        } else {
            $render->pagedata['panel_id'] = $this->finder->getId();
        }

        $render->pagedata['columns'] = $columns;
        $render->pagedata['datatypes'] = $datatypes;
        $render->pagedata['finder_id'] = $finder_id;

        return $render->display($this->finder->getFile(), $app->app_id, true);
    }
}

