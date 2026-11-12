<?php
class wms_finder_branch_product
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_barcode = "条形码";
    var $column_barcode_width = "100";
    function column_barcode($row)
    {
        $basicMBarcodeLib    = kernel::single('material_basic_material_barcode');
        $barcode             = $basicMBarcodeLib->getBarcodeById($row['product_id']);

        return $barcode;
    }

    var $column_bn = "基础物料编码";
    var $column_bn_width = "300";
    var $column_bn_order_field = "bn";
    function column_bn($row){
        return $row['bn'];
    }

    var $column_store_position_width = "150";
    function column_store_position($row){
        return $row['store_position'];
    }

    var $column_product_name = "基础物料名称";
    var $column_product_name_width = "100";
    function column_product_name($row){
        if ($row['sku_property']) $str = "(".$row['sku_property'].")";
        return $row['name'].$str;
    }

    var $column_spec_info = '规格';
    var $column_spec_info_width='80';
    function column_spec_info($row)
    {
        $material_basic_obj = app::get('material')->model('basic_material');
        $material_basic_ext_obj = app::get('material')->model('basic_material_ext');
        $material_basics = $material_basic_obj->dump(array('material_bn'=>$row['bn']), 'bm_id');
        $material_basic_exts = $material_basic_ext_obj->dump(array('bm_id' => $material_basics['bm_id']), 'specifications');

        return $material_basic_exts['specifications'];//基础物料无规格：$row['spec_info']
    }

    var $column_brand = '基础物料品牌';
    var $column_brand_width = '100';
    function column_brand($row)
    {
        $material_basic_obj = app::get('material')->model('basic_material');
        $material_basic_ext_obj = app::get('material')->model('basic_material_ext');
        $ome_brand_obj = app::get('ome')->model('brand');
        $material_basics = $material_basic_obj->dump(array('material_bn'=>$row['bn']), 'bm_id');
        $material_basic_exts = $material_basic_ext_obj->dump(array('bm_id' => $material_basics['bm_id']), 'brand_id');
        $brands = $ome_brand_obj->dump(array('brand_id' => $material_basic_exts['brand_id']), 'brand_name');

        return $brands['brand_name'];
    }

    var $column_branch_name = "仓库";
    var $column_branch_name_width = "150";
    function column_branch_name($row)
    {
        $brObj = app::get('ome')->model('branch');
        $aRow = $brObj->dump($row['branch_id'], 'name');

        return $aRow['name'];
    }
    
    #冻结库存
    var $column_store_freeze = '冻结库存';
    var $column_store_freeze_width = 120;
    var $column_store_freeze_order = 90;
    function column_store_freeze($row)
    {
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $store_freeze          = $basicMStockFreezeLib->getBranchFreeze($row['product_id'], $row['branch_id']);
        
        return $store_freeze;
    }
    
    #总仓单位平均成本
    var $column_entity_unit_cost = '总仓单位平均成本';
    var $column_entity_unit_cost_width = 120;
    var $column_entity_unit_cost_order = 90;
    function column_entity_unit_cost($row)
    {
        $entityBranchLib    = kernel::single('ome_entity_branch_product');
        //总仓成本
        $entityBranchInfo = $entityBranchLib->getBranchCountCostPrice( $row['branch_id'],$row['product_id']);
        if (!kernel::single('desktop_user')->has_permission('cost_price')) {
            return '-';
        }else{
            return isset($entityBranchInfo[$row['branch_id']]) ? $entityBranchInfo[$row['branch_id']][$row['product_id']]['unit_cost'] : 0;
        }
    }
}

