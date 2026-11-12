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

class ome_finder_branchtype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols = 'source';
    public $column_edit       = "操作";
    public $column_edit_width = "280";
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row){

        $source = $row[$this->col_prefix.'source'];

        if($source == 'system') return ;

        $finder_id = $_GET['_finder']['finder_id'];
        $type_id = $row['type_id'];

        $buttons = [];

        $buttons[] = '<a href="index.php?app=ome&ctl=admin_branchtype&act=edit&p[0]='.$row ['type_id'].'&finder_id='.$finder_id.'" target="dialog::{width:600,height:300,title:\'编辑\'}">编辑</a>';


        $buttons[] = <<<BTN
            <a class="c-red" onclick="if(confirm('确认删除？')){ W.page('index.php?app=ome&ctl=admin_branchtype&act=del&p[0]={$type_id}&finder_id={$finder_id}')}" >删除</a>
BTN;
        return implode('&nbsp;',$buttons);
    }


   

}
