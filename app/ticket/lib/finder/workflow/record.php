<?php
/**
 * 审批流记录Finder类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_finder_workflow_record {
    public $addon_cols = "";
    
//    public $column_edit = '操作';
//    public $column_edit_width = 120;
//    public $column_edit_order = 1;
//    public function column_edit($row) {
//        $finder_id = $_GET['_finder']['finder_id'];
//        $id = $row['id'];
//
//        $button = sprintf('<a href="index.php?app=ticket&ctl=admin_workflow_record&act=edit&p[0]=%s&finder_id=%s">编辑</a>', $id, $finder_id);
//
//        return $button;
//    }
    
    var $detail_basic = '详细信息';
    public function detail_basic($id) {
        $render = app::get('ticket')->render();

        $model = app::get('ticket')->model('workflow_record');
        $row = $model->dump($id);

        $render->pagedata['data'] = $row;
        return $render->fetch('admin/workflow/record/detail.html');
    }
} 