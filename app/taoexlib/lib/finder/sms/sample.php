<?php
class taoexlib_finder_sms_sample
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_confirm='操作';
    var $column_confirm_width = "120";
    var $detail_basic = '历史详细列表';
    function detail_basic($id){
        $render = app::get('taoexlib')->render();
        $oItem = app::get('taoexlib')->model('sms_sample_items');
        $items = $oItem->getList('*',
                     array('id' => $id), 0, -1);
        foreach ($items as $k=>$item ) {
            $items[$k]['createtime'] = $item['createtime']!=0 ? date('Y-m-d H:i:s',$item['createtime']) : '-';
            $items[$k]['approvedtime'] = $item['approvedtime']!=0 ? date('Y-m-d H:i:s',$item['approvedtime']) : '-';

        }

        $render->pagedata['items'] = $items;
        $render->display('admin/sms/sample_items.html');
        //return 'detail';
    }	
    function column_confirm($row){
        $find_id = $_GET['_finder']['finder_id'];
        $url= urlencode("index.php?app=taoexlib&ctl=admin_sms_items&act=list_sample&p[0]={$row['id']}&finder_id=".$_GET['_finder']['finder_id']."");
       $edit = "<a href='index.php?app=taoexlib&ctl=admin_sms_sample&act=edit_sample&p[0]="
       .$row['id'].
       "&finder_id=$find_id' target=dialog::{width:900,height:500,title:''}>编辑</a> ";
        if($row['status']){
            $stop = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要暂时停止指定模板的使用吗？？？\\n\\n注意：你还可以随时启用指定模板。\")) {href=\"index.php?app=taoexlib&ctl=admin_sms_sample&act=setStatus&p[0]={$row['id']}&p[1]=$row[status]&finder_id={$_GET['_finder']['finder_id']}\";}'>暂停</a>"; 
        }else{
            $stop = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要暂时停止指定模板的使用吗？？？\\n\\n注意：你还可以随时启用指定模板。\")) {href=\"index.php?app=taoexlib&ctl=admin_sms_sample&act=setStatus&p[0]={$row['id']}&p[1]=$row[status]&finder_id={$_GET['_finder']['finder_id']}\";}'>开启</a>"; 
        }

       return $edit.'&nbsp;'.$stop;
    }
}

