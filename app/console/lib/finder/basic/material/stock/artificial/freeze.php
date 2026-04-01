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

/**
 * 人工库存预占流水记录
 */

class console_finder_basic_material_stock_artificial_freeze{
    
    public $addon_cols = 'branch_id,bm_id,status';//调用字段
    
    function __construct(){
        $this->_mdl_ma_ba_ma = app::get('material')->model('basic_material');
    }
    
    var $column_edit  = '操作';
    var $column_edit_order = 1;
    var $column_edit_width = '50';
    function column_edit($row){
        $bmsaf_id = $row["bmsaf_id"];
        $finder_id = $_GET['_finder']['finder_id'];
        $status = $row[$this->col_prefix.'status'];
        if($status == 1){ //预占中可释放
            return sprintf('<a href="javascript:if (confirm(\'你确定要释放当前仓库货品的预占数量吗？\')){W.page(\'index.php?app=console&ctl=admin_stock_artificial_freeze&act=single_unfreeze&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">释放</a>',$bmsaf_id,$finder_id);
        }
    }
    
    var $column_branch_name = '仓库名称';
    var $column_branch_name_width = 150;
    var $column_branch_name_order = 5;
    function column_branch_name($row){
        $branch_id = $row[$this->col_prefix.'branch_id'];
        $mdl_ome_branch = app::get('ome')->model('branch');
        $rs_branch = $mdl_ome_branch->dump(array("branch_id"=>$branch_id),"name");
        return $rs_branch['name'];
    }
    
    var $column_basic_material_name = '基础物料名称';
    var $column_basic_material_name_width = 150;
    var $column_basic_material_name_order = 10;
    function column_basic_material_name($row){
        $bm_id = $row[$this->col_prefix.'bm_id'];
        $rs_ma = $this->_mdl_ma_ba_ma->dump(array("bm_id"=>$bm_id),"material_name");
        return $rs_ma['material_name'];
    }
    
    var $column_basic_material_bn = '基础物料编码';
    var $column_basic_material_bn_width = 150;
    var $column_basic_material_bn_order = 15;
    function column_basic_material_bn($row){
        $bm_id = $row[$this->col_prefix.'bm_id'];
        $rs_ma = $this->_mdl_ma_ba_ma->dump(array("bm_id"=>$bm_id),"material_bn");
        return $rs_ma['material_bn'];
    }
    
    var $column_basic_material_spu = '基础物料款号';
    var $column_basic_material_spu_width = 150;
    var $column_basic_material_spu_order = 16;
    function column_basic_material_spu($row){
        $bm_id = $row[$this->col_prefix.'bm_id'];
        $rs_ma = $this->_mdl_ma_ba_ma->dump(array("bm_id"=>$bm_id),"material_spu");
        return $rs_ma['material_spu'];
    }
    
    var $column_status = '状态';
    var $column_status_width = 150;
    var $column_status_order = 20;
    function column_status($row){
        $status= $row[$this->col_prefix.'status'];
        $status_text = "预占中";
        if($status == 2){
            $status_text = "已释放";
        }
        return $status_text;
    }
    
}