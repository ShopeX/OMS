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

class wms_event_trigger_logistics_data_electron_youzan extends wms_event_trigger_logistics_data_electron_common {

    /**
     * 获取DirectSdf
     * @param mixed $arrDelivery arrDelivery
     * @param mixed $arrBill arrBill
     * @param mixed $shop shop
     * @return mixed 返回结果
     */
    public function getDirectSdf($arrDelivery, $arrBill, $shop) {
        $deliveryExtend = array();
        
        foreach($arrDelivery as $delivery) {
            $package_items = $this->getpackage_items($delivery['delivery_id']);
            $failArray = array('delivery_id'=>$delivery['delivery_id'],'delivery_bn'=>$delivery['delivery_bn']);
            if(empty($delivery['ship_province']) || empty($delivery['ship_addr'])){
                $failArray['msg'] = '收货地址省份和详细地址不能少';
                $this->directRet['fail'][] =  $failArray;
                continue;
            }
            if(empty($package_items)){
                $failArray['msg'] = '包裹明细不能少';
                $this->directRet['fail'][] =  $failArray;
                continue;
            }
            if(empty($delivery['ship_mobile']) && empty($delivery['ship_tel'])){
                $failArray['msg'] = '收货地址手机号和电话不能同时少';
                $this->directRet['fail'][] =  $failArray;
                continue;
            }
            $orderBn = array();
            $orderReceiver = array();
            // foreach ($delivery['delivery_order'] as $val) {
            //     if($val['shop_type'] == 'luban' && $val['createway'] == 'matrix') {
            //         $orderBn[] = $val['order_bn'];
            //         if(empty($orderReceiver)) {
            //             $rs = app::get('ome')->model('order_extend')->getList('extend_status,bool_extendstatus',array('order_id'=>$val['order_id']));
            //             if ($rs[0]['extend_status'] != 'consignee_modified') {
            //                 $orderReceiver = app::get('ome')->model('order_receiver')->db_dump(['order_id'=>$val['order_id']], 'ship_province,ship_city,ship_district,ship_town,ship_village');
            //             }
            //         }
            //     }
            // }

            if($orderReceiver) {
                $delivery = array_merge($delivery, $orderReceiver);
            }

            $tmpDelivery = $delivery;
            // $tmpDelivery['order_channels_type'] = $orderBn ? 'LUBAN' : 'OTHER';
            $tmpDelivery['package_items'] = $package_items;
            $tmpDelivery['order_bn'] = $orderBn;
            $deliveryExtend[] = $tmpDelivery;
            if(empty($arrBill)) {
                $this->needRequestId[] = $delivery['delivery_id'];
            } else {
                $dlyExtend = $deliveryExtend[0];
                $deliveryExtend = array();
                foreach($arrBill as $bill) {
                    $tmp = $dlyExtend;
                    $this->needRequestId[] = $bill['b_id'];
                    $tmp['delivery_bn'] = $this->setChildRqOrdNo($dlyExtend['delivery_bn'], $bill['b_id']);
                    $deliveryExtend[] = $tmp;
                }
                break;
            }
        }
        if(empty($deliveryExtend)) {
            return false;
        }
        $primary_bn = uniqid('oed');

        $sdf = parent::getDirectSdf($arrDelivery, $arrBill, $shop);
        $sdf['primary_bn'] = $primary_bn;
        $sdf['shop']       = $shop;
        $sdf['delivery']   = $deliveryExtend;

        return $sdf;
    }

    # 获取包裹明细
    /**
     * 获取package_items
     * @param mixed $delivery_id ID
     * @return mixed 返回结果
     */
    public function getpackage_items($delivery_id)
    {
        $items = $this->getDeliveryItems($delivery_id);
        $package = array();
        foreach ($items as $item ) {
            $product_name = $item['product_name'] ? $item['product_name'] : '商品名称';
            if(isset($product_name[120])) {
                $product_name = mb_substr($product_name, 0, 120, 'utf8');
            }
            $package[] = array('item_name'=>$product_name,'count'=>$item['number']);
        }
        return $package;
    }
}