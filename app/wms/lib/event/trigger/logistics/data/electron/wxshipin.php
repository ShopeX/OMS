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
 * @DateTime: 2021/5/12 15:49:16
 * @describe: 微信视频号
 * ============================
 */
class wms_event_trigger_logistics_data_electron_wxshipin extends wms_event_trigger_logistics_data_electron_common
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
        } elseif ($shop['addon']) {
            foreach ($shop['addon'] as $k => $v) {
                $shop[$k] = $v;
            }
        }

        $orders = app::get('ome')->model('orders')->getList('total_amount,shop_type,order_bn,custom_mark,mark_text,order_id', array('order_bn|in' => $delivery['order_bns']));

        $orderItemsList  = app::get('ome')->model('order_items')->getList('*', ['order_id|in' => array_column($orders, 'order_id')]);
        $shopGoodsIdList = array_column($orderItemsList, 'shop_goods_id', 'shop_product_id');

        foreach ($deliveryItems as $k => $v) {
            if ($shopGoodsIdList[$v['shop_product_id']]) {
                $deliveryItems[$k]['shop_goods_id'] = $shopGoodsIdList[$v['shop_product_id']];
            }
        }

        $total_amount = 0;
        foreach ($orders as $order) {
            $total_amount += $order['total_amount'];
            $shop['shop_type'] = $order['shop_type'];
        }

        $dlyCorp = app::get('ome')->model('dly_corp')->dump(array('corp_id' => $delivery['logi_id']));
        app::get('ome')->model('dly_corp_channel')->getChannel($dlyCorp, array($delivery));

        $prt_tmpl_id = $dlyCorp['prt_tmpl_id'];

        $templateMdl  = app::get('logisticsmanager')->model('express_template');
        $templateInfo = $templateMdl->db_dump(['template_id' => $prt_tmpl_id]);
        if (strpos($templateInfo['out_template_id'], 'single') !== false) {
            $templateInfo['out_template_id'] = 'single';
        }

        $sdf                  = parent::getDirectSdf($arrDelivery, $arrBill, $shop);
        $sdf['primary_bn']    = $delivery['delivery_bn'];
        $sdf['delivery']      = $delivery;
        $sdf['delivery_item'] = $deliveryItems;
        $sdf['shop']          = $shop;
        $sdf['dly_corp']      = $dlyCorp;
        $sdf['total_amount']  = $total_amount;
        $sdf['order']         = $orders;
        $sdf['template']      = $templateInfo;

        return $sdf;
    }

    /**
     * 获取DeliveryItems
     * @param mixed $delivery_id ID
     * @return mixed 返回结果
     */
    public function getDeliveryItems($delivery_id) {
        static $deliveryItems = array();
        if(!$deliveryItems[$delivery_id]) {
            $deliveryItems[$delivery_id] = app::get('wms')->model('delivery_items')->getList('*', array('delivery_id' => $delivery_id));
        }
        return $deliveryItems[$delivery_id];
    }

}
