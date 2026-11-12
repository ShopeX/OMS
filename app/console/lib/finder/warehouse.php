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

class console_finder_warehouse{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $addon_cols    = 'branch_id';
    
    var $column_edit  = '操作';
    var $column_edit_order = 2;
    var $column_edit_width = '100';
    function column_edit($row)
    {
        $finder_id   = $_GET['_finder']['finder_id'];
        $branch_id   = $row[$this->col_prefix .'branch_id'];
        
        $button = '<a href="index.php?app=console&ctl=admin_warehouse&act=edit&p[0]='. $branch_id .'&_finder[finder_id]='. $finder_id .'&finder_id='. $finder_id .'" target="_blank">编辑</a>';
        
        return '<span class="c-gray">'. $button .'</span>';
    }
    
    var $detail_basic = "仓库详情";
    function detail_basic($branch_id)
    {
        $render    = app::get('console')->render();
        $branchObj = app::get('console')->model('warehouse');
        
        $render->pagedata['branch'] = $branchObj->dump($branch_id);
        
        return $render->fetch('admin/vop/warehouse_detail.html');
    }
}