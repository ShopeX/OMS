<?php
class iostock_finder_purchase{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

   
    var $column_branchbn = '仓库编号';
    function column_branchbn($row){
        $branch_bn = "select branch_bn from sdb_ome_branch where branch_id=".$row['branch_id'];
        $bn = kernel::database()->select($branch_bn);
        return $bn[0]['branch_bn'];
    }
    var $column_productname = '商品名称';
    function column_productname($row){
        $sql = "select material_name AS name from sdb_material_basic_material where material_bn='".$row['bn']."'";
        $name = kernel::database()->select($sql);
        return $name[0]['name'];
    }
    var $addon_cols = 'supplier_name';
    var $column_suppliername = '供应商名称';
    function column_suppliername($row){
        return $row[$this->col_prefix . 'supplier_name'];
    }

}
