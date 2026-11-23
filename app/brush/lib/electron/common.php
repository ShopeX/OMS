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
 * @author ykm 2017/7/14
 * @describe 刷单电子面单公用
 */
class brush_electron_common extends brush_electron_abstract{
    private $batchWaybillChannel = array('taobao', 'pdd');
    public function deliveryToSdf($arrDelivery) {
        $sdf = parent::deliveryToSdf($arrDelivery);
        $num = $this->bufferGetWaybill();
        if($num > 1 || in_array($this->channel['channel_type'], $this->batchWaybillChannel)) {
            $shop = $this->getChannelExtend();
            $deliveryExtend = array();
            foreach($arrDelivery as $delivery) {
                $deliveryItems = $this->getDeliveryItems($delivery['delivery_id']);//发货单明细
                $tmpDelivery = $delivery;
                $tmpDelivery['delivery_item'] = $deliveryItems;
                $package = array();
                foreach ($deliveryItems as $item ) {
                    $product_name = $item['product_name'] ? $item['product_name'] : '商品名称';
                    if(isset($product_name[120])) {
                        $product_name = mb_substr($product_name, 0, 120, 'utf8');
                    }
                    $package[] = array('item_name'=>$product_name,'count'=>$item['number']);
                }
                $tmpDelivery['package_items'] = $package;
                $deliveryExtend[] = $tmpDelivery;
            }
            $primary_bn = uniqid();
            $sdf['primary_bn'] = $primary_bn;
            $sdf['shop']       = $shop;
            $sdf['delivery']   = $deliveryExtend;
        } else {
            $delivery = $arrDelivery[0];
            $shop = $this->getChannelExtend();
            if(empty($shop)){
                $shop_obj = app::get('ome')->model('shop');
                $_shop = $shop_obj->getList('shop_id,default_sender,mobile,tel,area,name as shop_name,addr as address_detail',array('shop_id' => $delivery['shop_id']));
                $shop  = $_shop[0];
                $addr_arr = explode(':',$shop['area']);
                $address = explode('/', $addr_arr[1]);
                $shop['province'] = $address[0];
                $shop['city'] = $address[1];
                $shop['area'] = $address[2];
            }
            $deliveryItems = $this->getDeliveryItems($delivery['delivery_id']);
            $sdf['primary_bn']    = $delivery['delivery_bn'];
            $sdf['delivery']      = $delivery;
            $sdf['shop']          = $shop;
            $sdf['delivery_item'] = $deliveryItems;
            $sdf['corp']          = $this->getDlyCorp($delivery['logi_id']);
        }
        return $sdf;
    }
}