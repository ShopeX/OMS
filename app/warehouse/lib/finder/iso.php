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

class warehouse_finder_iso{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $addon_cols = 'iso_status,iso_id,check_status,defective_status';
    
    var $column_edit = '操作';
    var $column_edit_width = '100';
    function column_edit($row){
        $mdl_wiis = app::get('warehouse')->model('iso_items_simple');
        $string = array();
        $iso_status = $row[$this->col_prefix.'iso_status'];
        $iso_id = $row[$this->col_prefix.'iso_id'];
        $check_status = $row[$this->col_prefix.'check_status'];
        $defective_status = $row[$this->col_prefix.'defective_status'];
        $finder_id = $_GET['_finder']['finder_id'];
        
        $edit_button = <<<EOF
        <a href="index.php?app=warehouse&ctl=admin_iostockorder&act=iostock_edit&p[0]=$iso_id&finder_id=$finder_id" target="_blank">编辑</a>
EOF;
        $confirm_button = <<<EOF
        <a href="index.php?app=warehouse&ctl=admin_iostockorder&act=check&p[0]=$iso_id&finder_id=$finder_id" target="_blank">审核</a>
EOF;
        $cancel_button = <<<EOF
        <span class="lnk" onclick="new Dialog('index.php?app=warehouse&ctl=admin_iostockorder&act=cancel&p[0]=$iso_id&finder_id=$finder_id',{title:'取消出库',width:500,height:250})">取消</span>
EOF;
        $defective_confirm_button = <<<EOF
            <a class="lnk" href="index.php?app=warehouse&ctl=admin_iostockorder&act=doDefective&p[0]=$iso_id&finder_id=$finder_id" target="_blank">残损确认</a>
EOF;
        $difference_button = <<<EOF
            <a class="lnk" href="index.php?app=warehouse&ctl=admin_iostockorder&act=difference&p[0]=$iso_id&finder_id=$finder_id" target="_blank">差异查看</a>
EOF;
        
        if($iso_status == '3'){
            if($defective_status == "1"){ //残损确认
                $string[] = $defective_confirm_button;
            }
            //查看是否有差异
            $items = $mdl_wiis->db->select("SELECT * from sdb_warehouse_iso_items_simple where iso_id=".intval($iso_id)." and (`normal_num`!=`nums` || defective_num>0)");
            if($items){
                $string[] = $difference_button;
            }
        }
        
        if ($check_status == '1' && $iso_status=='1'){
            //调拨入库隐藏 编辑  并且查看是否进行过调拨取消入库操作
//             $string[]= $edit_button;
            $string[]= $confirm_button;
        }
        
        #取消
        if ($iso_status<='1'){
            $string[] = $cancel_button;
        }
        if($string){
            return '<span class="c-gray">'.implode('|',$string).'</span>';
        }
    }
    
    var $detail_item = "原始内容";
    function detail_item($iso_id){
        $basicMaterialLib = kernel::single('material_basic_material');
        $render = app::get('warehouse')->render();
        $mdl_warehouse_items = app::get('warehouse')->model('iso_items');
        $rs_items = $mdl_warehouse_items->getList("*",array("iso_id"=>$iso_id));
        foreach($rs_items as &$var_item){
            $product = $basicMaterialLib->getBasicMaterialExt($var_item['product_id']);
            $var_item['spec_info'] = $product['specifications'];
            $var_item['barcode'] = $product['barcode'];
            $var_item['unit'] = isset($product['unit']) ? $product['unit'] : '';
        }
        unset($var_item);
        $render->pagedata["rs_items"] = $rs_items;
        return $render->fetch("admin/iostock/warehouse_item.html");
    }
    
    var $detail_item_simple = "入库明细";
    function detail_item_simple($iso_id){
        $basicMaterialLib = kernel::single('material_basic_material');
        $render = app::get('warehouse')->render();
        $mdl_warehouse_items = app::get('warehouse')->model('iso_items_simple');
        $rs_items = $mdl_warehouse_items->getList("*",array("iso_id"=>$iso_id));
        foreach($rs_items as &$var_item){
            $product = $basicMaterialLib->getBasicMaterialExt($var_item['product_id']);
            $var_item['spec_info'] = $product['specifications'];
            $var_item['barcode'] = $product['barcode'];
            $var_item['unit'] = isset($product['unit']) ? $product['unit'] : '';
        }
        unset($var_item);
        $render->pagedata["rs_items"] = $rs_items;
        return $render->fetch("admin/iostock/warehouse_item_simple.html");
    }
    
}