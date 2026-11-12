<?php
class taoguaninventory_finder_inventory{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "盘点记录";
    var $detail_log = '盘点日志';
    function __construct(){

        if ($_GET['act']=='confirm'){
            unset($this->column_view);
        }

        if ($_GET['act']=='import'){
            unset($this->column_confirm);
        }

    }

    /*function detail_basic($inventory_id){

        $render = app::get('taoguaninventory')->render();
        $oInventory = app::get('taoguaninventory')->model("inventory");
        $inventory_detail = $oInventory->dump($inventory_id, '*');

        $render->pagedata['detail'] = $inventory_detail;
        $render->pagedata['inventory_id'] = $inventory_detail['inventory_id'];
        return $render->fetch('admin/inventory/base_detail.html');
    }

    function detail_log($inventory_id){
        $render = app::get('taoguaninventory')->render();
         $opObj  = app::get('ome')->model('operation_log');
         $logdata = $opObj->read_log(array('obj_id'=>$inventory_id,'obj_type'=>'inventory@taoguaninventory'), 0, -1);
          foreach($logdata as $k=>$v){
           $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
          }
          $render->pagedata['log'] = $logdata;
          return $render->fetch('admin/inventory/operation_log.html');

    }*/

    /*var $addon_cols = "inventory_id,import_status,branch_id";
    var $column_view = '操作';
    var $column_view_width = "100";
    function column_view($row){
        $id = $row[$this->col_prefix.'inventory_id'];
        $import_status = $row[$this->col_prefix.'import_status'];
        $branch_id = $row[$this->col_prefix.'branch_id'];
        $find_id = $_GET['_finder']['finder_id'];
        $button= <<<EOF
<a href="index.php?app=taoguaninventory&ctl=admin_inventorylist&act=detail_inventory&p[0]=$id&view=true" class="lnk" " target="dialog::{width:700,height:600,title:'盘点查看'}">查看</a>&nbsp;
EOF;
        if ($_GET['flt'] == 'list'){

             if($row['confirm_status'] == 1){
$button.= <<<EOF
<a href="index.php?app=taoguaninventory&ctl=admin_inventorylist&act=go_inventory&inventory_id=$id" class="lnk">加入</a>
EOF;
             }
        }else if($_GET['flt']=='confirm'){
            if($row['confirm_status'] == 1 || $row['confirm_status'] == 4){
        	$button.= <<<EOF
<a href="index.php?app=taoguaninventory&ctl=admin_inventorylist&act=confirm_inventory&p[0]=$id&find_id=$find_id" class="lnk" target="dialog::{width:700,height:600,title:'盘点'}">盘点</a>
<a href="index.php?app=taoguaninventory&ctl=admin_inventorylist&act=edit_inventory&inventory_id=$id&finder_id=$find_id" class="lnk" target="_blank">编辑</a>&nbsp;
EOF;
}
}


        return $button;
    }
    */

//    var $column_confirm = '盘点损益';
//    var $column_confirm_width = "60";
//    function column_confirm($row){
//        $id = $row[$this->col_prefix.'inventory_id'];
//        $import_status = $row[$this->col_prefix.'import_status'];
//
//        if ($import_status=='2'){
//        $button = <<<EOF
//         <a href="index.php?app=taoguaninventory&ctl=admin_inventory&act=confirm_detail&p[0]=$id" class="lnk">确认</a>
//EOF;
//        }else $button = '-';
//        return $button;
//    }

}
?>
