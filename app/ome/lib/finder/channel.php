<?php
class ome_finder_channel{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_edit = '操作';
    var $column_edit_width = "100";
    public function column_edit($row){
        $channel_id = $row['channel_id'];
        $finder_id = $_GET['_finder']['finder_id'];
        $obj_channel = app::get('ome')->model('channel');
        $node_id = $obj_channel->getList('node_id',array('channel_id'=>$channel_id));
        $bind = null;
        if(empty($node_id[0]['node_id'])){
            #节点为空,申请绑定
            $bind = '&nbsp;|&nbsp;<a href="index.php?app=ome&ctl=admin_channel_channel&act=apply_bindrelation&channel_id='.$channel_id.'&finder_id='.$finder_id.'" target="_blank">申请绑定</a>';
        }
        return  '<a href="index.php?app=ome&ctl=admin_channel_channel&act=editChannel&channel_id='.$row['channel_id'].'&finder_id='.$finder_id.'" target="dialog::{width:420,height:280,title:\'编辑绑定应用\'}">编辑</a>'.$bind;
    }
    var $detail_basic = '基本信息';
    function detail_basic($channel_id){
        $obj_channel = app::get('ome')->model('channel');
        $data = $obj_channel->getList('channel_bn,channel_name,channel_type,memo',array('channel_id'=>$channel_id));
        if($data[0]['channel_type'] == '1'){
            $data[0]['channel_type']= 'crm';
        }
        $render = app::get('ome')->render();
        $render->pagedata['data'] = $data[0]; 
        return $render->fetch('admin/channel/detail_basic.html');
    }
}
