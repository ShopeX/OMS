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

class o2o_finder_autostore_rule {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols = "rule_id,branch_id";

    var $column_edit = "操作";
    var $column_edit_width = "100";
    var $column_edit_order = "1";
    function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        $ret= "&nbsp;<a href='index.php?app=o2o&ctl=admin_autostore&act=editRule&p[0]={$row[rule_id]}&finder_id={$finder_id}' target=\"_blank\">编辑</a>";
        return $ret;
    }

}

?>