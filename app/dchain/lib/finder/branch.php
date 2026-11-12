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

class dchain_finder_branch{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "channel_id,config,node_id,node_type";
    
    public $column_edit = "操作";
    public $column_edit_width = "170";
    public $column_edit_order = "1";
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row){
        $finder_id  = $_GET['_finder']['finder_id'];
        $channel_id = $row[$this->col_prefix.'channel_id'];
        // 编辑
        $btn = '<a href="index.php?app=dchain&ctl=admin_branch&act=edit&p[0]='.$channel_id.'&finder_id='.$finder_id.'" target="dialog::{width:650,height:400,title:\'外部优仓\'}">编辑</a>';

        return $btn;
    }
}
