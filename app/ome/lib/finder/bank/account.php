<?php

class ome_finder_bank_account
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


	var $column_edit = '操作';
	var $column_edit_width = "100";

	public function column_edit($row)
	{
		$finder_id = $_GET['_finder']['finder_id'];
		return '<a href="index.php?app=ome&ctl=admin_setting&act=add_bank_account&ba_id=' . $row['ba_id'] . '&finder_id='.$finder_id.'" target="dialog::{width:550,height:350,resizeable:false,title:\'编辑异常类型\'}">编辑</a>';
	}

}
