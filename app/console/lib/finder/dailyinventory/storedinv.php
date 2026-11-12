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

class console_finder_dailyinventory_storedinv
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "outer_stock,channel_id";

    public $column_opt       = '操作';
    public $column_opt_order = 1;
    /**
     * column_opt
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_opt($row)
    {
        $buttons = [];

        $url = sprintf("index.php?app=console&ctl=admin_dailyinventory&p[]=%s&act=storeItemIndex&finder_vid=%s", $row['id'], $_GET['finder_vid']);

        $buttons['items'] = sprintf('<a href="%s">查看明细</a>', $url);

        return implode(' | ', $buttons);
    }

    public $column_outer_stock       = '门店库存';
    public $column_outer_stock_order = 50;
    /**
     * column_outer_stock
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_outer_stock($row)
    {
        return $row[$this->col_prefix . 'outer_stock'];
    }

    public $column_store_bn       = '门店编码';
    public $column_store_bn_order = 30;
    /**
     * column_store_bn
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_store_bn($row)
    {
        return $this->_getStore($row[$this->col_prefix . 'channel_id'], $list)['store_bn'];
    }

    public $column_store_name       = '门店名称';
    public $column_store_name_order = 30;
    /**
     * column_store_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_store_name($row, $list)
    {
        return $this->_getStore($row[$this->col_prefix . 'channel_id'], $list)['name'];
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function _getStore($store_id, $list)
    {
        static $storeList;

        if (isset($storeList)) {
            return $storeList[$store_id];
        }

        $storeList = app::get('o2o')->model('store')->getList('store_id,store_bn,name', [
            'store_id' => array_column($list, $this->col_prefix . 'channel_id'),
        ]);

        $storeList = array_column($storeList, null, 'store_id');

        return $storeList[$store_id];
    }
}
