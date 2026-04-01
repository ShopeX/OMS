<?php
/**
 * ============================
 * @Author:   yaokangming
 * @describe: 平台相关设置
 * ============================
 */
class ome_finder_platform_set {
    public $addon_cols = "";
    public $column_edit = "操作";
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    
    public function column_edit($row){
        $btn = [];
        $btn[] = '<a class="lnk" target="dialog::{width:770,height:450,title:\'编辑\'}" href="index.php?app=ome&ctl=admin_platform_set&act=edit&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'">编辑</a>';
        return implode('|', $btn);
    }
}

