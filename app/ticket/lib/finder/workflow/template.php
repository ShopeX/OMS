<?php
/**
 * 审批流模板Finder类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_finder_workflow_template {
    public $addon_cols = "";
    
    public $column_edit = '操作';
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    public function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        $id = $row['id'];
        
        $button = sprintf('<a href="index.php?app=ticket&ctl=admin_workflow_template&act=edit&p[0]=%s&finder_id=%s">编辑</a>', $id, $finder_id);

        return $button;
    }
    
    var $detail_basic = '详细信息';
    public function detail_basic($id) {
        $render = app::get('ticket')->render();
        
        $model = app::get('ticket')->model('workflow_template');
        $templateLib = kernel::single('ticket_workflow_template');
        
        // info
        $row = $model->dump($id);
        
        // 获取审批场景类型名称
        $row['scene_type_name'] = $templateLib->getSceneTypeName($row['scene_type']);
        
        $render->pagedata['data'] = $row;
        return $render->fetch('admin/workflow/template/detail.html');
    }
} 