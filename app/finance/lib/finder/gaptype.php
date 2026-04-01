<?php


/**
 * ============================
 * @Author:   yaokangming
 * @describe: 财务差异类型查找器
 * ============================
 */
class finance_finder_gaptype {
    public $addon_cols = "";
    public $column_edit = "操作";
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    public function column_edit($row){
        $btn = [];
        $btn[] = '<a class="lnk" target="dialog::{width:600,height:300,title:\'编辑\'}" href="index.php?app=finance&ctl=gaptype&act=edit&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'">编辑</a>';
        return implode('|', $btn);
    }
}

