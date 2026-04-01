<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class console_finder_branch_product
{
    public $_braProExtObj = null;
    public $_branchObj = null;
    public $_basicMaterialCodeObj = null;
    public $_basicMaterialExtObj = null;
    
    public $_basicMStockFreezeLib = null;
    
    static $_vopCartStockList = null;
    

    
    var $addon_cols = 'product_id,branch_id,store';
    var $column_barcode = '条形码';
    var $column_barcode_width = 150;
    var $column_barcode_order = 30;
    function column_barcode($row)
    {
        //基础物料的条形码
        $code = $this->_basicMaterialCodeObj->dump(array('bm_id'=>$row[$this->col_prefix.'product_id'], 'type_id'=>1), 'code');
        
        return $code['code'];
    }
    
    var $column_bn = '基础物料编码';
    var $column_bn_width = 200;
    var $column_bn_order = 10;
    var $column_bn_order_field = 'p.material_bn';
    function column_bn($row, $list)
    {
        return $row['bn'];
    }
    
    var $column_product_name = '基础物料名称';
    var $column_product_name_width = 300;
    var $column_product_name_order = 20;
    function column_product_name($row)
    {
        $str = '';
        if ($row['sku_property']) $str = '('.$row['sku_property'].')';
        
        return $row['name'].$str;
    }
    
    var $column_spec_info = '规格';
    var $column_spec_info_width = '80';
    var $column_spec_info_order = 32;
    function column_spec_info($row)
    {
        //基础物料的规格
        $spec = $this->_basicMaterialExtObj->dump(array('bm_id'=>$row[$this->col_prefix.'product_id']), 'specifications');
        
        return $spec['specifications'];
    }
    
    var $column_branch_name = '仓库';
    var $column_branch_name_width = 150;
    var $column_branch_name_order = 12;
    function column_branch_name($row)
    {
        $aRow = $this->_branchObj->dump($row[$this->col_prefix.'branch_id'], 'name');
        
        return $aRow['name'];
    }
    
    // 动态设置列标题
    function __construct()
    {
        $this->_braProExtObj = app::get('ome')->model('branch_product_extend');
        $this->_branchObj = app::get('ome')->model('branch');
        
        $this->_basicMaterialCodeObj = app::get('material')->model('codebase');
        $this->_basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        
        $this->_basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        // 如果是门店过滤，改变列标题
        if(isset($_POST['b_type']) && $_POST['b_type'] == '2'){
            $this->column_branch_name = '门店名称';
        }
    }
    
    //冻结库存
    var $column_store_freeze = '冻结库存';
    var $column_store_freeze_width = 100;
    var $column_store_freeze_order = 15;
    function column_store_freeze($row)
    {
        $store_freeze = $this->_basicMStockFreezeLib->getBranchFreeze($row[$this->col_prefix.'product_id'], $row[$this->col_prefix.'branch_id']);
        
        return $store_freeze;
    }
    
    //可售库存
    var $column_available_store = '可售库存';
    var $column_available_store_width = 100;
    var $column_available_store_order = 16;
    function column_available_store($row)
    {
        $store = isset($row[$this->col_prefix.'store']) ? $row[$this->col_prefix.'store'] : 0;
        $store_freeze = $this->_basicMStockFreezeLib->getBranchFreeze($row[$this->col_prefix.'product_id'], $row[$this->col_prefix.'branch_id']);
        
        // 可售库存 = 库存 - 冻结库存，最小为0
        return max(0, $store - $store_freeze);
    }
    
    var $column_material_spu = '款号';
    var $column_material_spu_width = 120;
    var $column_material_spu_order = 20;
    function column_material_spu($row)
    {
        return $row['material_spu'] ?? '';
    }

    var $column_vop_sku_stock = 'VOP购物车预占';
    var $column_vop_sku_stock_width = 150;
    var $column_vop_sku_stock_order = 50;
    function column_vop_sku_stock($row, $list)
    {
        $bm_id = $row[$this->col_prefix.'product_id'];
        
        //批量获取基础物料关联的唯品会库存预占
        $this->_getVopCartStocks($list);
        
        $current_hold = 0;
        if(isset(self::$_vopCartStockList[$bm_id])){       
            $current_hold = self::$_vopCartStockList[$bm_id]['current_hold'];
        }
        
        return $current_hold;
    }
    
    /**
     * 批量获取基础物料关联的唯品会库存预占
     *
     * @param array $list
     * @return null
     */
    private function _getVopCartStocks($list)
    {
        //check
        if(isset(self::$_vopCartStockList)){
            return self::$_vopCartStockList;
        }
        
        $vopSkuStockMdl = app::get('vop')->model('sku_stock');
        $codeBaseLib = kernel::single('material_codebase');
        
        //product_id
        $bmIds = array_column($list, $this->col_prefix.'product_id');
        self::$_vopCartStockList = [];
        
        //获取基础物料关联的条形码列表
        $barcodeList = $codeBaseLib->getBarcodeByBmIds($bmIds);
        if(empty($barcodeList)){
            return self::$_vopCartStockList;
        }
        $barcodes = array_column($barcodeList, 'code');
        
        //已经存在的数据
        $skuStockList = $vopSkuStockMdl->getList('id,shop_id,bm_id,barcode,current_hold', ['barcode'=>$barcodes]);
        if(empty($skuStockList)){
            return self::$_vopCartStockList;
        }
        
        //format
        foreach ($skuStockList as $key => $val)
        {
            $bm_id = $val['bm_id'];
            $current_hold = $val['current_hold'];
            
            //库存占用：目前为购物车+未支付订单占用的库存值
            if(isset(self::$_vopCartStockList[$bm_id])){
                self::$_vopCartStockList[$bm_id]['current_hold'] += $current_hold;
            }else{
                self::$_vopCartStockList[$bm_id]['current_hold'] = $current_hold;
            }
        }
        
        return self::$_vopCartStockList;
    }


}

