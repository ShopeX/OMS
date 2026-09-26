<?php

class goldentra_finder_mold
{
    public $column_edit = '操作';
    public $column_edit_width = '80';

    public function column_edit($row)
    {
        $finder_id = isset($_GET['_finder']['finder_id']) ? $_GET['_finder']['finder_id'] : '';

        return "<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=goldentra&ctl=admin_mold&act=add&p[0]={$row['m_id']}&finder_id={$finder_id}',{width:600,height:300,title:'编辑'});\">编辑</a>";
    }
}
