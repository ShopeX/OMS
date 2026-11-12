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

class wms_finder_products{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $detail_basic = "库存详情";

    function detail_basic($product_id){
        if($_POST) {
            $oBranchPro = app::get('ome')->model('branch_product'); 
            $branch_id = $_POST['branch_id'];
            $product_ids = $_POST['product_id'];
            $safe_store = $_POST['safe_store'];
            $is_locked = $_POST['is_locked'];
            for($k=0;$k<sizeof($branch_id);$k++) {
                $oBranchPro -> update(
                    array('safe_store'=>$safe_store[$k],'is_locked'=>$is_locked[$k]),
                    array(
                        'product_id'=>$product_ids[$k],
                        'branch_id'=>$branch_id[$k]
                    )
                );
            }
            
            $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=0 WHERE bm_id='.$product_ids[$k-1];
            kernel::database()->exec($sql);
            
            $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=999 WHERE bm_id IN
                (
                    SELECT product_id FROM sdb_ome_branch_product
                    WHERE product_id='.$product_ids[$k-1].' AND safe_store>(store - store_freeze + arrive_store)
                )
            ';
            kernel::database()->exec($sql);
        }
        $render = app::get('wms')->render();
        $productObj = kernel::single('wms_receipt_products');
        
        $render->pagedata['pro_detail'] = $productObj->products_detail($product_id);
        return $render->fetch('admin/stock/detail_stock.html');
    }

    var $addon_cols='store, store_freeze, unit, cost';
    
    /*------------------------------------------------------ */
    //-- 格式化字段
    /*------------------------------------------------------ */
    #总库存
    var $column_store = '总库存';
    var $column_store_width = 80;
    var $column_store_order = 80;
    function column_store($row)
    {
        $data    = kernel::database()->selectrow("SELECT store FROM ".DB_PREFIX."material_basic_material_stock WHERE bm_id=".$row['bm_id']);
        
        return $data['store'];
    }
    
    #冻结库存
    var $column_store_freeze = '冻结库存';
    var $column_store_freeze_width = 80;
    var $column_store_freeze_order = 90;
    function column_store_freeze($row)
    {
        //$data    = kernel::database()->selectrow("SELECT store_freeze FROM ".DB_PREFIX."material_basic_material_stock WHERE bm_id=".$row['bm_id']);
        
        //根据基础物料ID获取对应的冻结库存
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $store_freeze    = $basicMStockFreezeLib->getMaterialStockFreeze($row['bm_id']);
        
        return $store_freeze;
    }
    
    #在途库存
    var $column_arrive_store='在途库存';
    var $column_arrive_store_width='60';
    var $column_arrive_store_order = 100;//排在列尾
    function column_arrive_store($row)
    {
        $sql    = "SELECT SUM(arrive_store) AS 'total' FROM ".DB_PREFIX."ome_branch_product WHERE product_id=".$row['bm_id'];
        $count  = kernel::database()->selectrow($sql);
        
        return $count['total'];
    }
}

?>