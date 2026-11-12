<?php

class iostock_finder_iostocksearch{

    private $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    protected $branchInfo = [];
    protected $materiralInfo = [];
    var $column_branch_id = '仓库编号';
    function column_branch_id($row, $list){
        $branchObj = app::get('ome')->model('branch');
        if(!$this->branchInfo) {
            $rows = $branchObj->getList('branch_id,branch_bn', array('branch_id'=>array_column($list, $this->col_prefix . 'branch_id')));
            $this->branchInfo = array_column($rows, null, 'branch_id');
        }
        return $this->branchInfo[$row[$this->col_prefix . 'branch_id']]['branch_bn'];
    }

    var $column_name = '基础物料名称';
    function column_name($row, $list){
        $basicMaterialObj = app::get('material')->model('basic_material');
        if(!$this->materiralInfo) {
            $rows = $basicMaterialObj->getList('material_bn, material_name', array('material_bn'=>array_column($list, $this->col_prefix . 'bn')));
            $this->materiralInfo = array_column($rows, null, 'material_bn');
        }
        return $this->materiralInfo[$row[$this->col_prefix . 'bn']]['material_name'];
    }

    var $addon_cols = 'supplier_name,nums,original_id,bn,type_id,branch_id';
    var $column_supplier = '供应商';
    var $column_supplier_width = 150;
    function column_supplier($row){
      return $row[$this->col_prefix . 'supplier_name'];
    }
    
    var $column_nums = "出入库数量";
    //var $column_nums_width = "80";
    function column_nums($row){
    	$iostock_instance = kernel::service('ome.iostock');
     	if($iostock_instance->getIoByType($row[$this->col_prefix . 'type_id'])){
     		return '+'.	$row[$this->col_prefix .'nums'];
     	}else{
     		return '-'.	$row[$this->col_prefix .'nums'];
     	}
    }
    
    private $appropriation_type_ids = array("4","40","11");
    var $column_appropriation_no = '调拨单号';
    var $column_appropriation_no_width = "130";
    function column_appropriation_no($row){
        if(in_array($row[$this->col_prefix . "type_id"],$this->appropriation_type_ids)){
            $taoguaniostockorder_iso_obj = app::get('taoguaniostockorder')->model('iso');
            $taoguaniostockorder_info = $taoguaniostockorder_iso_obj->db_dump(array('iso_id'=>$row[$this->col_prefix .'original_id']),'appropriation_no');
            return $taoguaniostockorder_info["appropriation_no"];
        }else{
            return "-";
        }
    }

}
