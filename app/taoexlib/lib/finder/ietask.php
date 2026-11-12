<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */ 
class taoexlib_finder_ietask {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


	var $addon_cols = 'file_name,export_ver,total_count,app,model,status';
    
    var $column_edit3 = '下载链接';
	var $column_edit3_width = 100;
	var $column_edit3_order = 15;
    function column_edit3($row){
        $link = '---';
        $title = '点击下载';
		if($row['status'] == 'finished' && $row[$this->col_prefix.'export_ver'] == 2){
            $link = '<a target="dialog::{width:650,height:250,title:\'' . $title . '\'}" href="index.php?app=taoexlib&ctl=ietask&act=predownload&finder_id=' . $_GET['_finder']['finder_id'] . '&durl=' . urlencode("index.php?app=taoexlib&ctl=ietask&act=download&p[0]={$row['task_id']}") . '">' . $title . '</a>  ';
        }
        return $link;
	}

    public $column_progress_bar       = '进度%';
    public $column_progress_bar_width = 100;
    public $column_progress_bar_order = 51;
    public function column_progress_bar($row)
    {
        kernel::single('taskmgr_interface_cache', $row['task_id'])->fetch('exp_task_' . $row['task_id'] . '_counter', $count);
        $split     = ome_export_whitelist::allowed_lists($row[$this->col_prefix . 'app'] . '_mdl_' . $row[$this->col_prefix . 'model']);
        if (!$split) return;

        $split_num = ceil($row[$this->col_prefix . 'total_count'] / $split['splitnums']);
        if ($split_num <= 0) {
            return '';
        }
        $count = floatval($count);
        $split_num = floatval($split_num);
        $radio     = $count / $split_num >= 1 ? 99 : $count / $split_num * 100;
        $percent   = $row[$this->col_prefix . 'status'] == 'finished' ? 100 : number_format($radio, 0);
        return $percent;
    }

    public $column_cur_records       = '当前条数';
    public $column_cur_records_width = 100;
    public $column_cur_records_order = 51;
    public function column_cur_records($row)
    {
        kernel::single('taskmgr_interface_cache', $row['task_id'])->fetch('exp_task_' . $row['task_id'] . '_records', $records);

        return $records;
    }
}
