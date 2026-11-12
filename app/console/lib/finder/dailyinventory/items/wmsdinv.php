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

class console_finder_dailyinventory_items_wmsdinv
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "outer_stock";

    public $column_outer_stock       = 'WMS库存';
    public $column_outer_stock_order = 70;
    /**
     * column_outer_stock
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_outer_stock($row)
    {
        return $row[$this->col_prefix . 'outer_stock'];
    }
}