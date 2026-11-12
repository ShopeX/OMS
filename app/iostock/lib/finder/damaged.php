<?php
class iostock_finder_damaged{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

var $column_in = '目标仓库编号';
    function column_in($row){      
        $sql = "select branch_id from sdb_ome_iostock where type_id=50";
        $branch_id = kernel::database()->select($sql);
        $sql2 = "select branch_bn from sdb_ome_branch where branch_id=".$branch_id[0]['branch_id']."";
        $branch_bn = kernel::database()->select($sql2);         
        return $branch_bn[0]['branch_bn'];
    }
     var $column_inname = '目标仓库名称';
    function column_inname($row){
        $sql = "select branch_id from sdb_ome_iostock where type_id=50";
        $branch_id = kernel::database()->select($sql);
        $sql2 = "select name from sdb_ome_branch where branch_id=".$branch_id[0]['branch_id']."";
        $branch_bn = kernel::database()->select($sql2);
        return $branch_bn[0]['name'];
    }
    var $column_out = '来源仓库编号';
    function column_out($row){
        $sql = "select branch_id from sdb_ome_iostock where type_id=5";
        $branch_id = kernel::database()->select($sql);
        $sql2 = "select branch_bn from sdb_ome_branch where branch_id=".$branch_id[0]['branch_id']."";
        $branch_bn = kernel::database()->select($sql2);   
        return $branch_bn[0]['branch_bn'];
    }
    var $column_outname = '来源仓库名称';
    function column_outname($row){
        $sql = "select branch_id from sdb_ome_iostock where type_id=5";
        $branch_id = kernel::database()->select($sql);
        $sql2 = "select name from sdb_ome_branch where branch_id=".$branch_id[0]['branch_id']."";
        $branch_bn = kernel::database()->select($sql2);
        return $branch_bn[0]['name'];
    }
    var $column_detail = 'detail';
    function detail_edit($id){
        $render = app::get('iostock')->render();
        $oItem = kernel::single("ome_mdl_iostock");
        $items = $oItem->getList('*',array('iostock_id'=>$id));
        $product = kernel::single('ome_mdl_products');
        $pname = $product->getList('name',array('bn'=>$items[0]['bn']));
        $render->pagedata['pname'] = $pname[0];
        $render->pagedata['items'] = $items;
        $render->display('damaged.html');

    }

}
