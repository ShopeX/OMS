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

class console_receipt_common{

    public function getSupplier($supplier_id){
        static $supplier;
        if (isset($supplier[$supplier_id])) return $supplier[$supplier_id];
        $supperObj = app::get('purchase')->model('supplier');
        $rows = $supperObj->dump(array('supplier_id'=>$supplier_id),'name');
        if ($rows){
            $supplier[$supplier_id] = $rows['name'];
            return $supplier[$supplier_id];
        }else{
            return array();
        }
    }

    public function getProducts($bn){
        static $products;
        if (isset($products[$bn])) return $products[$bn];
        
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $bm_ids    = $basicMaterialObj->dump(array('material_bn'=>$bn), 'bm_id');
        $rows      = $basicMaterialLib->getBasicMaterialExt($bm_ids['bm_id']);
        
        if ($rows){
            $rows['product_id'] = $bm_ids['bm_id'];
            $products[$bn] = $rows;
            return $products[$bn];
        }else{
            return array();
        }
    }

    function getIostockList($type_id,$obj_id){
        $db = kernel::database();
        $sql = "SELECT i.product_id,i.bn,i.normal_num FROM sdb_taoguaniostockorder_iso s LEFT JOIN sdb_taoguaniostockorder_iso_items as i ON s.iso_id=i.iso_id WHERE s.type_id='".$type_id."' AND s.original_id=".$obj_id;
        
        $iso_items = $db->select($sql);
        $items = array();
        foreach ($iso_items as $item){
            $items[] = $item['product_id'];
        }
        return $items;
    }
}