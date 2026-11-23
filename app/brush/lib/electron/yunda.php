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
 * @describe 发起电子面单请求
 */
class brush_electron_yunda extends brush_electron_abstract{
    protected $preBn = 'bey';

    public function deliveryToSdf($arrDelivery){
        $shop = $this->getChannelExtend();
        $this->getDeliveryOrder($this->needRequestId);//先一步获取所有关联订单，避免多次查询，提高效率
        $deliveryExtend = array();
        foreach($arrDelivery as $delivery) {
            $total_amount = $this->getOrderTotalAmount($delivery['delivery_id']);
            $deliveryItems = $this->getDeliveryItems($delivery['delivery_id']);//发货单明细
            $tmpDelivery = $delivery;
            $tmpDelivery['total_amount'] = $total_amount;
            $tmpDelivery['delivery_item'] = $deliveryItems;
            $deliveryExtend[] = $tmpDelivery;
        }
        $primary_bn = $this->preBn ? $this->preBn . $this->requestTime : $arrDelivery[0]['delivery_bn'];

        $sdf = parent::deliveryToSdf($arrDelivery);
        $sdf['primary_bn'] = $primary_bn;
        $sdf['shop']       = $shop;
        $sdf['delivery']   = $deliveryExtend;

        return $sdf;
    }
}