<?php
class taoexlib_finder_sms_sample_items
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_confirm='操作';
    var $column_confirm_width = "120";
    var $addon_cols = 'id';
    function column_confirm($row){

        $id = $row[$this->col_prefix.'id'];
        $rowHtml ='';
        if($row['status']){
            $rowHtml .= "&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要关闭此短信模板吗？\")) {href=\"index.php?app=taoexlib&ctl=admin_sms_items&act=setStatus&p[0]={$row['iid']}&p[1]={$id}&p[2]={$row['status']}&finder_id={$_GET['_finder']['finder_id']}\";}'>暂停</a>"; 
        }else{
            $rowHtml .= "&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要开启此短信模板吗？\")) {href=\"index.php?app=taoexlib&ctl=admin_sms_items&act=setStatus&p[0]={$row['iid']}&p[1]={$id}&p[2]={$row['status']}&finder_id={$_GET['_finder']['finder_id']}\";}'>开启</a>"; 
        }
        
        return $rowHtml;
    }
}

