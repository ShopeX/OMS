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
 * @author ykm 2015-12-16
 * @describe 发起电子面单请求 每次请求一条
 */
class brush_electron_360buy extends brush_electron_abstract{

    public function deliveryToSdf($delivery) {
        $sdf = parent::deliveryToSdf($delivery);
        $sdf['primary_bn'] = $delivery[0]['delivery_bn'];
        $sdf['preNum'] = 1;
        $sdf['delivery'] = $delivery[0];
        return $sdf;
    }

    //回填发货信息
    public function delivery() {
        $deliveryIds = array();
        foreach($this->delivery as $delivery) {
            $deliveryIds[] = $delivery['delivery_id'];
        }
        $this->getDeliveryOrder($deliveryIds);
        $shop = $this->getChannelExtend();
        $dlyCorp = $this->getDlyCorp($this->delivery[0]['logi_id']);
        foreach($this->delivery as $delivery) {
            $totalAmount = 0;
            $order = $this->getDeliveryOrder($delivery['delivery_id']);
            $orderBn = array();
            foreach($order['order_info'] as $val) {
                $orderBn[] = $val['order_bn'];
                $totalAmount += $val['total_amount'];
            }
            $params = array();
            $params['delivery'] = $delivery;
            $params['shop'] = $shop;
            $params['dly_corp'] = $dlyCorp;
            $params['total_amount'] = $totalAmount;
            $params['order_bn'] = implode(',', $orderBn);
            if ($delivery['is_cod'] == 'true') {
                $params['receivable_amount'] = $this->getPayMoney($order['order_id']);//代收货款金额
            }
            $this->request('delivery', $params);
        }
    }

    private function getPayMoney($orderIds) {
        $money = 0;
        $orderExtendObj = app::get('ome')->model('order_extend');
        $orderExtends = $orderExtendObj->getList('receivable', array('order_id' => $orderIds));
        foreach ($orderExtends as $extend) {
            $money += $extend['receivable'];
        }
        return $money;
    }
}