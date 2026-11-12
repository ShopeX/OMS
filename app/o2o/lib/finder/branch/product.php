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

class o2o_finder_branch_product {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'id';
    
    var $column_edit = "操作";
    var $column_edit_width = "100";
    var $column_edit_order = "1";
    function column_edit($row) {
        $link_arr = array();
        $id = $row['id'];
        $finder_id = $_GET['_finder']['finder_id'];
        
        //配置
        $config_btn = '<a href="index.php?app=o2o&ctl=admin_branch_product&act=setConfig&id='.$id.'&finder_id='.$finder_id.'" target="dialog::{width:300,height:150,title:\'配置\'}">配置</a>';
        $link_arr[] = $config_btn;
        
//         $is_bind = '<a href="index.php?app=o2o&ctl=admin_branch_product&act=is_bind&finder_id='.$finder_id.'" target="dialog::{width:800,height:630,title:\'编辑发票信息\'}">同步</a>';
//         $link_arr[] = $is_bind;
        
        return implode(" | ",$link_arr);
    }

}

?>