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

class o2o_finder_branch_products extends console_finder_branch_product
{
    var $column_branch_name = '门店名称';

    function __construct()
    {
        parent::__construct();
        $this->column_branch_name = '门店名称';
    }
    
    /**
     * 基础物料重量字段
     */
    var $column_weight = '基础物料重量(g)';
    var $column_weight_width = 100;
    var $column_weight_order = 35;
    function column_weight($row, $list)
    {
        return $row['weight'];
    }
    
    /**
     * 包装单位字段
     */
    var $column_unit = '包装单位';
    var $column_unit_width = 100;
    var $column_unit_order = 36;
    function column_unit($row, $list)
    {
        return $row['unit'];
    }
}
?>


