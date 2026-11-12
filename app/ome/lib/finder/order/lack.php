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

class ome_finder_order_lack{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $addon_cols = "order_freeze";
    var $column_order_freeze = '订单预占';
    var $column_order_freeze_width = '100';
    function column_order_freeze($row){
        $product_id = $row['product_id'];
        $bn = $row['bn'];
        
        $order_freeze = $row['order_freeze'];
        $stock = "<a href='index.php?app=ome&ctl=admin_order_lack&act=show_order_freeze_list&finder_id={$_GET['_finder']['finder_id']}&p[0]=".$product_id."&p[1]=".$bn."'target=\"_blank\"\"><span>".$order_freeze."</span></a>";
        return $stock;
    }
//
    
    
    var $column_arrive_store = '在途库存';
    var $column_arrive_store_width='100';
    function column_arrive_store($row)
    {
        $product_id = $row['product_id'];
        $oOrder_lack = app::get('ome')->model('order_lack');
        $arrive = $oOrder_lack->getArrivestorelist($product_id,10000,0);
        $arrive_store = $arrive[0]['arrive_store'];
        
        if ($arrive_store>0) {
            return "<a href='index.php?app=ome&ctl=admin_order_lack&act=show_arrive_store&finder_id={$_GET['_finder']['finder_id']}&p[0]=".$product_id."'target=\"_blank\"\"><span>".$arrive_store."</span></a>";
        }
        
        return $arrive_store;
        
    }

    
    /**
     * 缺货数量
     * @param   
     * @return 
     * @access  public
     * @author cyyr24@sina.cn
     */
    var $column_arrive_product_lack='缺货数量(含在途)';
    var $column_arrive_product_lack_width='100';
    function column_arrive_product_lack($row)
    {
        $enum_store = $row['enum_store'];
        $product_id = $row['product_id'];
        $order_freeze = $row['order_freeze'];
        $oOrder_lack = app::get('ome')->model('order_lack');
        $arrive = $oOrder_lack->getArrivestorelist($product_id,10000,0);
        $arrive_store = $arrive[0]['arrive_store'];
        $arrive_product_lack = $order_freeze-($enum_store+$arrive_store);
        if ($arrive_product_lack<=0) {
            return '-';
        }else{
            return "<div style='width:48px;padding:2px;height:16px;background-color:red;float:left;'><span style='color:#eeeeee;'>$arrive_product_lack</span></div>";
        }
    }

    var $column_arrive_enum_store = '库存可用(含在途)';
    var $column_arrive_enum_store_width='100';
    function column_arrive_enum_store($row)
    {
        $oOrder_lack = app::get('ome')->model('order_lack');
        $product_id = $row['product_id'];
        $enum_store = $row['enum_store'];
        $arrive = $oOrder_lack->getArrivestorelist($product_id,10000,0);
        $arrive_store = $arrive[0]['arrive_store'];
        $arrive_enum_store = $arrive_store+$enum_store;
        return $arrive_enum_store;
    }


    var $column_supplier_name = '供应商';
    var $column_supplier_name_width = '100';
    function column_supplier_name($row)
    {
        $goods_id = $row['goods_id'];
        $oOrder_lack = app::get('ome')->model('order_lack');
        $supplier_permission = kernel::single('desktop_user')->has_permission('order_lack_supplier');
        if ($supplier_permission) {
            return $oOrder_lack->getSupplierBygoods($goods_id);
        }else{
            return '-';
        }
    }
}