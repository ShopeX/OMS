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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 2020/12/7 10:59:28
 * @describe: 类
 * ============================
 */
class financebase_finder_expenses_export_log {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = 'export_type,filter';

    public $column_trade = "账单日期";
    public $column_trade_width = "200";
    /**
     * column_trade
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_trade($row) {
        $filter = json_decode($row[$this->col_prefix . 'filter'], 1);
        return $filter['time_from'] . ' ~ ' . $filter['time_to'];
    }

    public $column_split = "拆分日期";
    public $column_split_width = "200";
    /**
     * column_split
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_split($row) {
        $filter = json_decode($row[$this->col_prefix . 'filter'], 1);
        return $filter['split_time_from'] . ' ~ ' . $filter['split_time_to'];
    }

    public $column_category = "费用项";
    public $column_category_width = "100";
    /**
     * column_category
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_category($row) {
        $exportType = $row[$this->col_prefix . 'export_type'];
        if($exportType == 'items') {
            return '-';
        }
        $filter = json_decode($row[$this->col_prefix . 'filter'], 1);
        return $filter['bill_category'] ? : '全部';
    }
    
    public $column_material = "基础物料编码";
    public $column_material_width = "100";
    /**
     * column_material
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_material($row) {
        $exportType = $row[$this->col_prefix . 'export_type'];
        if($exportType == 'main') {
            return '-';
        }
        $filter = json_decode($row[$this->col_prefix . 'filter'], 1);
        return $filter['material_bn'] ? : '-';
    }
}