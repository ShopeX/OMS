<?php
class tgkpi_finder_pick {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

	var $column_ident = "打印批次号";
    var $column_ident_width = "120";
    var $addon_cols = "print_ident,print_ident_dly";
    function column_ident($row) {
        $identStr = '';
        if($row[$this->col_prefix.'print_ident']){
            $identStr .= $row[$this->col_prefix.'print_ident']."_".$row[$this->col_prefix.'print_ident_dly'];
        }
        return $identStr;
    }
    var $column_product_name = "商品名称";
    var $column_product_name_width = "120";
    function column_product_name($row)
    {
        $basicMaterialLib    = kernel::single('material_basic_material');
        $bMaterialRow    = $basicMaterialLib->getBasicMaterialBybn($row['product_bn']);
        
        return $bMaterialRow['material_name'];
    }
    var $column_spec_info = "规格";
    var $column_spec_info_width = "120";
    function column_spec_info($row)
    {
        $basicMaterialLib    = kernel::single('material_basic_material');
        $bMaterialRow    = $basicMaterialLib->getBasicMaterialBybn($row['product_bn']);
        
        return $bMaterialRow['specifications'];
    }
}
