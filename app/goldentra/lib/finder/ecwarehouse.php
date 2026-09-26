<?php

class goldentra_finder_ecwarehouse
{
    var $column_edit = '操作';
    var $column_edit_width = 80;
    var $column_edit_order = 1;

    public function column_edit($row)
    {
        return '<a href="index.php?app=goldentra&ctl=admin_ecwarehouse&act=edit&p[0]='
            . intval($row['id'])
            . '&finder_id=' . (isset($_GET['finder_id']) ? $_GET['finder_id'] : '')
            . '" target="dialog::{width:560,height:380,title:\'编辑电商仓配置\'}">编辑</a>';
    }
}
