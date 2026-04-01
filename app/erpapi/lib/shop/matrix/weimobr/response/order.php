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
 * User: ghc
 * Date: 18/10/10
 * Time: 上午10:51
 */
class erpapi_shop_matrix_weimobr_response_order extends erpapi_shop_response_order{
    protected $_update_accept_dead_order = true;

    /**
     * 订单是否创建
     * @return bool|void
     */

    protected function _canAccept(){
        if($this->_ordersdf['shipping']['shipping_name'] == '自提') {
            $this->__apilog['result']['msg'] = '到店自提订单暂不支持';
            return false;
        }
        if($this->_ordersdf['is_delivery'] == 'N') {
            $this->__apilog['result']['msg'] = '不发货订单不接收';
            return false;
        }
       
        return parent::_canAccept();
    }

    protected function _analysis()
    {
        parent::_analysis();
        
        //买家实付字段名
        $this->_ordersdf['coupon_actuallypay_field'] = 'extend_item_list/payAmount';
        foreach ($this->_ordersdf['order_objects'] as &$object) {
            $arrField = explode('/', $this->_ordersdf['coupon_actuallypay_field']);
            $object['actually_amount'] = current($object['order_items'])[$arrField[0]][$arrField[1]];
        }
        
        // custom_mark
        if(empty($this->_ordersdf['custom_mark']) && $this->_ordersdf['buyer_memo']){
            $this->_ordersdf['custom_mark'] = $this->_ordersdf['buyer_memo'];
        }
    }

    /**
     * @return array
     */
    protected function get_update_components()
    {
        $components = array('markmemo','marktype');

        if ($this->_ordersdf['pay_status'] != $this->_tgOrder['pay_status']) {
            $components[] = 'master';
        }

        // 如果没有收货人信息，
        if (!$this->_tgOrder['consignee']['name'] || !$this->_tgOrder['consignee']['area'] || !$this->_tgOrder['consignee']['addr']) {
            $components[] = 'consignee';
        }

        return $components;
    }

    protected function _operationSel()
    {
        parent::_operationSel();

        if ($this->_tgOrder) {
            $this->_operationSel = 'update';
        }
    }

    protected function get_create_plugins()
    {
        $plugins = parent::get_create_plugins();

        $plugins[] = 'weimobvo2o';
        

        return $plugins;
    }
}