<?php
class wms_stock{

     /**
     * 库存查询相关方法，2011.11.01更新
     */
    function search_stockinfo($keywords,$branch_type='all')
    {
        $db = kernel::database();
        
        $product_ids = array();
        $product_info = array();
        
        # [模板搜索]基础物料
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $product_info    = $basicMaterialSelect->search_stockinfo($keywords, $branch_type);
        
        return $product_info;
    }
}
?>