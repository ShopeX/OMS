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
 * @DateTime: 2020/11/9 14:37:01
 * @describe: 类
 * ============================
 */
class ome_finder_gift_logs {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'order_bn,shop_id';


    public $column_createtime = "下单时间";
    public $column_createtime_width = 120;
    /**
     * column_createtime
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_createtime($row) {
        $mdl_ome_orders = app::get('ome')->model('orders');
        $order = $mdl_ome_orders->dump(array("order_bn"=>$row[$this->col_prefix . "order_bn"],"shop_id"=>$row[$this->col_prefix . "shop_id"]),"createtime");
        return $order['createtime'] ? date('Y-m-d H:i:s', $order['createtime']) : '-';
    }

    public $column_paytime = "付款时间";
    public $column_paytime_width = 120;
    /**
     * column_paytime
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_paytime($row) {
        $mdl_ome_orders = app::get('ome')->model('orders');
        $order = $mdl_ome_orders->dump(array("order_bn"=>$row[$this->col_prefix . "order_bn"],"shop_id"=>$row[$this->col_prefix . "shop_id"]),"paytime");
        return $order['paytime'] ? date('Y-m-d H:i:s', $order['paytime']) : '-';
    }

}