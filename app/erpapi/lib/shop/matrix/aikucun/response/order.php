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
 * Created by PhpStorm.
 * User: qiudi
 * Date: 18/10/10
 * Time: 上午10:51
 */
class erpapi_shop_matrix_aikucun_response_order extends erpapi_shop_response_order{
    protected $_update_accept_dead_order = true;

    protected function get_update_components()
    {
        $components = array('markmemo','marktype','custommemo');

        if ( ($this->_ordersdf['shipping']['is_cod']=='true' && $this->_ordersdf['status'] == 'dead') || ($this->_ordersdf['shipping']['is_cod'] != 'true' && $this->_ordersdf['pay_status'] == '5'))
        {
            $components[] = 'master';
        }

        return $components;
    }

    protected function get_create_plugins()
    {
        $plugins = parent::get_create_plugins();

        $plugins[] = 'waybill';
        $plugins[] = 'orderextend';

        return $plugins;
    }

    protected function _analysis()
    {
        parent::_analysis();

        // 98代表商家自发
        if ($this->_ordersdf['shipping']['shipping_name'] == '98') {
            $this->_ordersdf['shipping']['shipping_name'] = '';
        }

        // 如果 cost_item 为 0 或空，根据 order_objects 的 amount 重新计算
        // 如果 pmt_goods 为 0 或空，根据 order_objects 的 pmt_price 重新计算
        $need_recalc_cost_item = empty($this->_ordersdf['cost_item']) || (float)$this->_ordersdf['cost_item'] == 0;
        $need_recalc_pmt_goods = empty($this->_ordersdf['pmt_goods']) || (float)$this->_ordersdf['pmt_goods'] == 0;
        
        if ($need_recalc_cost_item || $need_recalc_pmt_goods) {
            $cost_item = 0;
            $pmt_goods = 0;
            if (!empty($this->_ordersdf['order_objects']) && is_array($this->_ordersdf['order_objects'])) {
                foreach ($this->_ordersdf['order_objects'] as $object) {
                    // 跳过 status == 'close' 的对象（与基础验证逻辑一致）
                    if (isset($object['status']) && $object['status'] == 'close') {
                        continue;
                    }
                    // 累加每个对象的 amount 字段（用于计算 cost_item）
                    if ($need_recalc_cost_item && isset($object['amount'])) {
                        $cost_item = bcadd($cost_item, (float)$object['amount'], 3);
                    }
                    // 累加每个对象的 pmt_price 字段（用于计算 pmt_goods）
                    if ($need_recalc_pmt_goods && isset($object['pmt_price'])) {
                        $pmt_goods = bcadd($pmt_goods, (float)$object['pmt_price'], 3);
                    }
                }
            }
            // 如果计算出的 cost_item > 0，则更新
            if ($need_recalc_cost_item && $cost_item > 0) {
                $this->_ordersdf['cost_item'] = $cost_item;
            }
            // 如果计算出的 pmt_goods > 0，则更新
            if ($need_recalc_pmt_goods && $pmt_goods > 0) {
                $this->_ordersdf['pmt_goods'] = $pmt_goods;
            }
        }
    }

    /**
     * _canAccept
     * @return mixed 返回值
     */

    public function _canAccept()
    {
        

        if ($this->_ordersdf['consignee']['telephone'] == '分配中' || $this->_ordersdf['consignee']['mobile'] == '分配中'){
            $this->__apilog['result']['msg'] = '手机或电话 分配中不处理';
            return false;
        }

        return parent::_canAccept();
    }
}