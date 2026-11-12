<?php

class iostock_finder_inventory{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_name = '商品名称';
    function column_name($row){
        $basicMaterialObj = app::get('material')->model('basic_material');
        $name = $basicMaterialObj->dump(array('material_bn'=>$row['bn']),'material_name');
        return $name['material_name'];
    }

    var $column_amount = '盈亏金额';
    function column_amount($row){
        $ectoolObj = app::get('eccommon')->model('currency');
        $amount = $ectoolObj->formatNumber($row['iostock_price']*$row['nums']);
        return $amount;
    }
}
