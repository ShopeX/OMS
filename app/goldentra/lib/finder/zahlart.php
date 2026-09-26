<?php

class goldentra_finder_zahlart
{
    var $column_edit = '操作';
    var $column_edit_width = 80;
    var $column_edit_order = 1;

    public function column_edit($row)
    {
        return '<a href="index.php?app=goldentra&ctl=admin_zahlart&act=edit&p[0]='
            . intval($row['id'])
            . '&finder_id=' . (isset($_GET['finder_id']) ? $_GET['finder_id'] : '')
            . '" target="dialog::{width:520,height:360,title:\'编辑支付方式\'}">编辑</a>';
    }
}
