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

class purchase_finder_supplier_goods
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = 'supplier_id,bm_id';
    
    var $column_supplier_bn = '供应商编码';
    var $column_supplier_bn_width = '120';
    var $column_supplier_bn_order = 10;
    function column_supplier_bn($row)
    {
        $supplierObj    = app::get('purchase')->model('supplier');
        $data    = $supplierObj->dump(array('supplier_id'=>$row[$this->col_prefix.'supplier_id']), 'bn');
        
        return $data['bn'];
    }
    
    var $column_supplier_name = '供应商';
    var $column_supplier_name_width = '150';
    var $column_supplier_name_order = 15;
    function column_supplier_name($row)
    {
        $supplierObj    = app::get('purchase')->model('supplier');
        $data    = $supplierObj->dump(array('supplier_id'=>$row[$this->col_prefix.'supplier_id']), 'name');
        
        return $data['name'];
    }
    
    var $column_material_bn = '基础物料编码';
    var $column_material_bn_width = '200';
    var $column_material_bn_order = 20;
    function column_material_bn($row)
    {
        $materialObj    = app::get('material')->model('basic_material');
        $data    = $materialObj->dump(array('bm_id'=>$row[$this->col_prefix.'bm_id']), 'material_bn');
        
        return $data['material_bn'].$row['bm_id'];
    }
    
    var $column_material_name = '基础物料名称';
    var $column_material_name_width = '150';
    var $column_material_name_order = 25;
    function column_material_name($row)
    {
        $materialObj    = app::get('material')->model('basic_material');
        $data    = $materialObj->dump(array('bm_id'=>$row[$this->col_prefix.'bm_id']), 'material_name');
        
        return $data['material_name'].$row['bm_id'];
    }
}
?>