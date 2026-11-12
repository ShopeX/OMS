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
 * 审批流节点Finder类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_finder_workflow_node {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "";
    
    public $column_edit = '操作';
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    public function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        $id = $row['id'];
        
        $button = sprintf('<a href="index.php?app=ticket&ctl=admin_workflow_node&act=edit&p[0]=%s&finder_id=%s">编辑</a>', $id, $finder_id);

        return $button;
    }
    
    var $detail_basic = '详细信息';
    public function detail_basic($id) {
        $render = app::get('ticket')->render();

        $model = app::get('ticket')->model('workflow_node');
        $row = $model->dump($id);

        $render->pagedata['data'] = $row;
        return $render->fetch('admin/workflow/node/detail.html');
    }
} 