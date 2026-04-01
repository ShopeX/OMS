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
 * @author ykm 2018/12/7
 * @describe 京东供应商订单
 */
class erpapi_shop_matrix_jd_response_order extends erpapi_shop_response_order
{
    protected $_update_accept_dead_order = true;

    protected function get_update_components()
    {
        $components = array('markmemo');

        if ( ($this->_ordersdf['pay_status'] != $this->_tgOrder['pay_status']) ||($this->_ordersdf['shipping']['is_cod']=='true' && $this->_ordersdf['status'] == 'dead') ) {
            $refundApply = app::get('ome')->model('refund_apply')->getList('apply_id',array('order_id'=>$this->_tgOrder['order_id'],'status|noequal'=>'3'));
            // 如果没有退款申请单，以前端为主
            if (!$refundApply) {
                $components[] = 'master';
            }
        }

        // 更新收货地址
        if($this->_tgOrder){
            $rs = app::get('ome')->model('order_extend')->getList('extend_status',array('order_id'=>$this->_tgOrder['order_id']));
            if ($rs[0]['extend_status'] != 'consignee_modified') {
                $orRe = app::get('ome')->model('order_receiver')->db_dump(['order_id'=>$this->_tgOrder['order_id']], 'encrypt_source_data');
                $ensd = json_decode($orRe['encrypt_source_data'], 1);
                if(empty($ensd['oaid']) || $ensd['oaid'] != $this->_ordersdf['extend_field']['oaid']) {
                    $components[] = 'consignee';
                }
            }
        }

        return $components;
    }
    protected function _analysis()
    {
        parent::_analysis();
        //买家实付字段名
        $this->_ordersdf['coupon_actuallypay_field'] = $field = 'extend_item_list/cost';
        // 预约发货 hold 时间
        if ($this->_ordersdf['cn_info']['appointment_ship_time']) {
            $opPickDate = kernel::single('ome_func')->date2time($this->_ordersdf['cn_info']['appointment_ship_time']);
            $this->_ordersdf['timing_confirm'] = strtotime(date('Y-m-d 22:00:00',$opPickDate)) - 86400;
        }

        if ($this->_ordersdf['cn_info']['es_date']) {
            $this->_ordersdf['consignee']['r_time'] = kernel::single('ome_func')->date2time($this->_ordersdf['cn_info']['es_date']);
        }

        foreach ($this->_ordersdf['order_objects'] as &$object) {
            //京东预约发货设置hold时间
            if ($this->_ordersdf['timing_confirm']) {
                $object['estimate_con_time'] = (int)$this->_ordersdf['timing_confirm'];
            }
            $arrField = explode('/', $field);
            $object['actually_amount'] = current($object['order_items'])[$arrField[0]][$arrField[1]];
            foreach ($object['order_items'] as &$item) {
                if ($this->_ordersdf['timing_confirm']) {
                    $item['estimate_con_time'] = (int)$this->_ordersdf['timing_confirm'];
                }
            }
        }
    }
    
    //创建订单的插件
    protected function get_create_plugins()
    {
        $plugins   = parent::get_create_plugins();
        $plugins[] = 'jdgxd';
        return $plugins;
    }
}
