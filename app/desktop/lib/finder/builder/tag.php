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


class desktop_finder_builder_tag extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function main(){
        $render = app::get('desktop')->render();
        $tagctl = app::get('desktop')->model('tag');
        $tag_rel = app::get('desktop')->model('tag_rel');
        $tags = $tagctl->getList('tag_id,tag_name,tag_abbr,tag_bgcolor,tag_fgcolor',array('tag_type'=>$this->object->table_name()
//            ,'tag_mode'=>'normal'
        ));
        $filter['tag_type'] = $this->object->table_name();
		$rel_mdl = $this->app->model($filter['tag_type']);
        $filter['app_id'] = $this->app->app_id;
		$filter['rel_id|in'] = $_POST[$rel_mdl->idColumn];
        if($_POST['isSelectedAll']=='_ALL_'){
            $_filter = $_POST;
            unset($_filter['isSelectedAll']);
            if($_filter['_finder']) unset($_filter['_finder']);
            $filter = array_merge($_filter,$filter);
        }else{
            $filter[$this->dbschema['idColumn']] = $_POST[$this->dbschema['idColumn']];
        }

        $rel_tag_list = $tag_rel->getList('*',$filter,0,-1);
        if($rel_tag_list){
            foreach($rel_tag_list as $k=>$v){
                $tmp[$v['rel_id']][] = $v['tag_id'];
                $used_tag[$v['tag_id']] = 1;
            }
        }
        $i=0;

        if($tmp){
            if(is_array($_POST['goods_id'])){
                foreach($_POST['goods_id'] as $gk=>$gv){//计算标签的交集
                    if(!isset($tmp[$gv])){
                         $tmp[$gv][0] = null;
                    }
                }
            }
            foreach($tmp as $rel_id=>$rel_tags){//计算标签的交集
                if($i++==0){
                    $intersect = $rel_tags;
                }else{
                    $intersect = array_intersect($intersect,$rel_tags);

                }
                if(!$intersect) break;
            }
        }


        $filter = $_POST;
        unset($filter['_finder']);
        $count_method = $this->object_method['count'];
        $render->pagedata['count'] = $this->object->$count_method($filter);
        $render->pagedata['tags'] = (array)$tags;
        $render->pagedata['filter'] = serialize($filter);
        $render->pagedata['object_name'] = $this->object_name;
        $render->pagedata['used_tag'] = array_keys((array)$used_tag);
        $render->pagedata['intersect'] = (array)$intersect;
        $render->pagedata['url'] = $this->url;

        echo $render->fetch('common/tagsetter.html');
    }

}
