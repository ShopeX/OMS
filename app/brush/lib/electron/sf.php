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
class brush_electron_sf extends brush_electron_abstract{

    public function deliveryToSdf($delivery) {
        $sdf = parent::deliveryToSdf($delivery);
        $delivery = $delivery[0];
        $shop = $this->getChannelExtend();
        $deliveryItems = $this->getDeliveryItems($delivery['delivery_id']);
        $totalAmount = $this->getOrderTotalAmount($delivery['delivery_id']);
        $dlyCorp = $this->getDlyCorp($delivery['logi_id']);
        $sdf['primary_bn']    = $delivery['delivery_bn'];
        $sdf['delivery']      = $delivery;
        $sdf['shop']          = $shop;
        $sdf['total_amount']  = $totalAmount;
        $sdf['dly_corp']      = $dlyCorp;
        $sdf['delivery_item'] = $deliveryItems;

        return $sdf;
    }
}