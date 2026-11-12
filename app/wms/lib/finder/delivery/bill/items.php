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

class wms_finder_delivery_bill_items
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "bill_id";

    public $column_logi_no = "物流单号";
    /**
     * column_logi_no
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_logi_no($row, $list)
    {
        $deliveryBill = $this->_getDeliveryBill($list);

        return $deliveryBill[$row[$this->col_prefix . 'bill_id']]['logi_no'];
    }

    public $column_package_bn       = "包裹号";
    public $column_package_bn_width = "auto";
    /**
     * column_package_bn
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_package_bn($row, $list)
    {
        $deliveryBill = $this->_getDeliveryBill($list);

        return $deliveryBill[$row[$this->col_prefix . 'bill_id']]['package_bn'];
    }

    public $column_delivery_bn       = "发货单号";
    public $column_delivery_bn_width = "auto";
    /**
     * column_delivery_bn
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_delivery_bn($row, $list)
    {
        $deliveryBill = $this->_getDeliveryBill($list);
        $delivery_id  = $deliveryBill[$row[$this->col_prefix . 'bill_id']]['delivery_id'];
        $delivery     = $this->_getDelivery($delivery_id, $list);

        return $delivery['delivery_bn'];
    }

    // public $column_order_bn = "订单号";
    /**
     * column_order_bn
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_order_bn($row, $list)
    {
    }

    private function _getDeliveryBill($list)
    {
        static $data;
        if (isset($data)) {
            return (array) $data;
        }

        $filter['b_id'] = array_column($list, $this->col_prefix . 'bill_id');

        $data = app::get('wms')->model('delivery_bill')->getList('*', $filter);
        $data = array_column($data, null, 'b_id');

        return (array) $data;
    }

    private function _getDelivery($delivery_id, $list)
    {
        static $data;
        if (isset($data)) {
            return (array) $data[$delivery_id];
        }

        $deliveryBillList = $this->_getDeliveryBill($list);

        $filter['delivery_id'] = array_column($deliveryBillList, 'delivery_id');

        $data = app::get('wms')->model('delivery')->getList('delivery_id, delivery_bn', $filter);
        $data = array_column($data, null, 'delivery_id');

        



        return (array) $data[$delivery_id];
    }
}
