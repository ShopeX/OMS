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


class desktop_finder_tagcols{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_tag = '标签';
    var $column_tag_order = COLUMN_IN_TAIL;
    function column_tag($row){
        $rel_id = $row[$row['idColumn']];
        $oTagRel = app::get('desktop')->model('tag_rel');
        $oTag = app::get('desktop')->model('tag');
        $filter = array('rel_id'=>$rel_id,'tag_type'=>$row['tag_type'],'app_id'=>$row['app_id']);
        $tag_ids = $oTagRel->getList('tag_id',$filter);
        foreach($tag_ids as $id){
            $ids[] = $id['tag_id'];
        }
        if($ids){
            $rows = $oTag->getList('*',array('tag_id'=>$ids));
            foreach($rows as $row){
                $color_str = '';
				
                if($row['tag_fgcolor']){
                    $color[] = 'color:'.$row['tag_fgcolor'];
                }
                if($row['tag_bgcolor']){
                    $color[] = 'background-color:'.$row['tag_bgcolor'];
                }
                if($row['tag_bgcolor']&&is_array($color)) $color_str = implode(';',$color);
                $return .= '<span class="tag-label" style="'.$color_str.'">'.$row['tag_name'].'</span>';
            }
            return $return;
        }
    }

}
