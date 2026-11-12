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


class desktop_finder_builder_settag extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function main(){
        $tagctl = app::get('desktop')->model('tag');
        $tag_rel = app::get('desktop')->model('tag_rel');
        $tag_name = $_POST['tag']['name'];
        $tag_stat = $_POST['tag']['stat'];
		$tag_ids = $_POST['tag']['tag_id'];

        if($_POST['filter']){
            $obj = $this->object;
            $schema = $obj->get_schema();
            $idColumn = $schema['idColumn'];
            $filter = unserialize($_POST['filter']);
            $rows = $obj->getList($idColumn,$filter,0,-1);
            foreach($rows as $value){
                $pkey[] = $value[$idColumn];
            }
        }
        $pkey = (array)$pkey;
        
        //没有选择任何标签情况 time：2010-11-24
        if( !$tag_stat || !is_array($tag_stat) ) {  
            header('Content-Type:text/jcmd; charset=utf-8');
            echo '{error:"'.app::get('desktop')->_('标签设置失败！').'"}';
            exit;
        }
        foreach($tag_stat as $key=>$value){
            if($value==2) continue;
            if($value==1){//取消标签
                $tag_item = $tagctl -> getList('tag_id',array('tag_name'=>$tag_name[$key],'tag_type'=>$this->object->table_name()));
                foreach($pkey as $id){
                    $tag_rel->delete(array('tag_id'=>$tag_item[0]['tag_id'],'rel_id'=>$id));
                }
            }else{//设置标签
				$tag_item = $tagctl -> getList('tag_id',array('tag_name'=>$tag_name[$key],'tag_type'=>$this->object->table_name()));
				if ($tag_item && !$tag_ids[$key]){
					header('Content-Type:text/jcmd; charset=utf-8');
					echo '{error:"'.app::get('desktop')->_('标签重复添加！').'",_:null}';exit;
				}
				
                $data['tag_type'] = $this->object->table_name();
                $data['tag_name'] = $tag_name[$key];
                $data['app_id'] = $this->app->app_id;

                $tagctl->save($data);
                if($data['tag_id']){
                    $data2['tag']['tag_id'] = $data['tag_id'];
                    unset($data['tag_id']);
                    foreach($pkey as $id){
                        $data2['tag_type'] = $this->object->table_name();
                        $data2['app_id'] = $this->app->app_id;
                        $data2['rel_id'] = $id;
                        
                        //save修改了data tag_id的位置 edit by jiaolei time:2010-11-17 mantis:0019313 
                        $data2['tag']['tag_id'] or $data2['tag']['tag_id'] = $data2['tag_id'];
                        $tag_rel->save($data2);
                    }
                }
            }
        }

		header('Content-Type:text/jcmd; charset=utf-8');
		echo '{success:"'.app::get('desktop')->_('标签设置成功').'"}';    
    }
}
