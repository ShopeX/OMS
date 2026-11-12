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

class ome_finder_branch_pos {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function __construct(){

        if ($_GET['act']=='view'){
            unset($this->column_edit);
        }else {
            unset($this->column_product_name);
            unset($this->column_product_bn);
        }
    }

    var $addon_cols = "pos_id";
    var $column_edit = "操作";
    var $column_edit_width = "100";
    function column_edit($row){
        $finder_id = $_GET['_finder']['finder_id'];
        return '<a href="index.php?app=ome&ctl=admin_branch_pos&act=edit_pos&p[0]='.$row['pos_id'].'&finder_id='.$finder_id.'" target="_blank">编辑</a>';
    }

    var $column_product_name = "货品名称";
    var $column_product_name_width = "300";
    var $column_product_name_order = COLUMN_IN_TAIL;//排在列尾
    function column_product_name($row){
        if ($row['sku_property']) $str = "(".$row['sku_property'].")";
        return $row['name'].$str;
    }

    var $column_product_bn = "货号";
    var $column_product_bn_width = "150";
    var $column_product_bn_order = COLUMN_IN_TAIL;//排在列尾
    function column_product_bn($row){
        return $row['bn'];
    }
}
?>