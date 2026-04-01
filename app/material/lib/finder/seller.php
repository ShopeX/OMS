<?php

/**
 * ============================
 * @Author:   yaokangming
 * @describe: 销售人员
 * ============================
 */
class material_finder_seller {
    public $addon_cols = "";
    public $column_edit = "操作";
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    public function column_edit($row){
        $btn = [];
        $btn[] = '<a class="lnk" target="dialog::{width:450,height:200,title:\'编辑\'}" href="index.php?app=material&ctl=admin_seller&act=edit&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'">编辑</a>';
        return implode('|', $btn);
    }

    public $detail_oplog = "操作记录";
    public function detail_oplog($id){
        $render = app::get('console')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$id,'obj_type'=>'seller@material'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
            $logdata[$k]['memo'] = str_replace('查看快照', '<a class="lnk" target="dialog::{width:450,height:200,title:\'查看\'}" href="index.php?app=material&ctl=admin_seller&act=oplog&p[0]='.$v['log_id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'">查看快照</a>', $v['memo']);
        }
        $render->pagedata['log'] = $logdata;
        return $render->fetch('admin/oplog.html');
    }
}