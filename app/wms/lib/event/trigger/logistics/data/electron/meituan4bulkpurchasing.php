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

class wms_event_trigger_logistics_data_electron_meituan4bulkpurchasing extends wms_event_trigger_logistics_data_electron_common
{

    /**
     * 获取DirectSdf
     * @param mixed $arrDelivery arrDelivery
     * @param mixed $arrBill arrBill
     * @param mixed $shop shop
     * @return mixed 返回结果
     */
    public function getDirectSdf($arrDelivery, $arrBill, $shop)
    {
        $delivery = $arrDelivery[0];

        if (empty($arrBill)) {
            $this->needRequestId[] = $delivery['delivery_id'];
        } else {
            $this->needRequestId[]   = $arrBill[0]['b_id'];
            $delivery['delivery_bn'] = $this->setChildRqOrdNo($delivery['delivery_bn'], $arrBill[0]['b_id']);
        }

        $deliveryItems = $this->getDeliveryItems($delivery['delivery_id']);

        if (empty($shop)) {
            $shop   = [];
            $branch = app::get('ome')->model('branch')->db_dump($delivery['branch_id']);

            list(, $mainland)             = explode(':', $branch['area']);
            list($province, $city, $area) = explode('/', $mainland);

            $shop['shop_name']      = $branch['name'];
            $shop['province']       = $province;
            $shop['city']           = $city;
            $shop['area']           = $area;
            $shop['street']         = '';
            $shop['address_detail'] = $branch['address'];
            $shop['default_sender'] = $branch['uname'];
            $shop['mobile']         = $branch['mobile'];
            $shop['tel']            = $branch['phone'];
            $shop['zip']            = $branch['zip'];
        }

        $orders = app::get('ome')->model('orders')->getList('total_amount,shop_type,order_bn,custom_mark,mark_text,order_id,createway', array('order_bn|in' => $delivery['order_bns']));

        $orderIdArr  = array_column($orders, 'order_id');
        $orderExtend = app::get('ome')->model('order_extend')->getList('*', ['order_id|in' => $orderIdArr]);
        $orderExtend = array_column($orderExtend, null, 'order_id');

        $total_amount = 0;
        foreach ($orders as $k => $order) {
            $total_amount += $order['total_amount'];
            $shop['shop_type'] = $order['shop_type'];

            if ($orderExtend[$order['order_id']]) {
                $orders[$k]['order_extend'] = [
                    'extend_field' => json_decode($orderExtend[$order['order_id']]['extend_field'], 1),
                ];
            }
        }

        $dlyCorp = app::get('ome')->model('dly_corp')->dump(array('corp_id' => $delivery['logi_id']));

        $sdf                  = parent::getDirectSdf($arrDelivery, $arrBill, $shop);
        $sdf['primary_bn']    = $delivery['delivery_bn'];
        $sdf['delivery']      = $delivery;
        $sdf['delivery_item'] = $deliveryItems;
        $sdf['shop']          = $shop;
        $sdf['dly_corp']      = $dlyCorp;
        $sdf['total_amount']  = $total_amount;
        $sdf['order']         = $orders;

        return $sdf;
    }

    /**
     * 获取DeliveryItems
     * @param mixed $deliveryId ID
     * @return mixed 返回结果
     */
    public function getDeliveryItems($deliveryId) {
        $deliveryIdInfo = $this->getDeliveryIdBywms($deliveryId);
        $deliveryIds = array_column($deliveryIdInfo,'ome_delivery_id');
        $ditemd = app::get('ome')->model('delivery_items_detail')->getList('order_item_id,order_obj_id,number', ['delivery_id'=>$deliveryIds]);
        $items = [];
        foreach($ditemd as $v) {
            if($items[$v['order_obj_id']]) {
                continue;
            }
            $items[$v['order_obj_id']] = app::get('ome')->model('order_objects')->db_dump(['obj_id'=>$v['order_obj_id']], 'shop_goods_id,bn,name,quantity,oid');
            $oi = app::get('ome')->model('order_items')->db_dump(['item_id'=>$v['order_item_id']], 'nums');
            $items[$v['order_obj_id']]['quantity'] = sprintf('%.0f', $v['number']/$oi['nums']*$items[$v['order_obj_id']]['quantity']);
        }
        return $items;
    }

}
