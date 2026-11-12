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

class omeauto_finder_branchgroup {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "branch_group";
    public $column_edit = '操作';
    public $column_edit_width = "100";
    public $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row) {
        $btn = '';
        $btn .= "<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=omeauto&ctl=branchgroup&act=edit&bg_id={$row['bg_id']}&finder_id={$_GET['_finder']['finder_id']}',{width:760,height:400,title:'修改仓库分组'}); \">编辑</a>";
        return $btn;
    }

    public $column_branch_group = '仓库';
    public $column_branch_group_width = "200";
    public $column_branch_group_order = 1;
    /**
     * column_branch_group
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_branch_group($row) {
        $branchGroup = app::get('ome')->model('branch')->getList('name', array('branch_id'=>explode(',', $row[$this->col_prefix.'branch_group'])));
        return implode(',', array_map('current', $branchGroup));
    }
}