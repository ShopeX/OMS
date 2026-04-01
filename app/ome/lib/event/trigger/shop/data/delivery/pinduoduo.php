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
 * 获取数据
 * Class ome_event_trigger_shop_data_delivery_pinduoduo
 */
class ome_event_trigger_shop_data_delivery_pinduoduo extends ome_event_trigger_shop_data_delivery_common
{
    /** 
     * 获取数据
     * @param $delivery_id
     * @return array
     * @throws Exception
     */
    public function get_sdf($delivery_id)
    {
        $this->__sdf = parent::get_sdf($delivery_id);

        if (!$this->__sdf) {
            return [];
        }

        // 订单拆单判断
        $order = $this->__sdf['orderinfo'];
        $is_split = $this->_is_split_order($delivery_id);
        if ($is_split) {
            // 判断第一单还是最后一单
            $this->_nonsupport_mode_request($delivery_id);

            $delivery = $this->__deliverys[$delivery_id];
            $this->__sdf['is_first_delivery'] = false;

            if ($delivery['delivery_id'] == $this->firstDeliveryId || ($delivery['parent_id'] > 0 && $delivery['parent_id'] == $this->firstDeliveryId)){
                $this->__sdf['is_first_delivery'] = true;
            }
    
            $this->__sdf['is_last_delivery'] = false;
            if ((in_array($delivery['delivery_id'], $this->lastDeliveryId) && $order['ship_status'] == '1') || ($delivery['parent_id'] > 0 && in_array($delivery['parent_id'], $this->lastDeliveryId))) {
                $this->__sdf['is_last_delivery'] = true;
            }

            // 第一单发货单是否回写成功
            if ($this->__sdf['is_first_delivery']){
                $delivery = $this->__deliverys[$delivery_id];
                $shipment = app::get('ome')->model('shipment_log')->getList('deliveryCode,status', [
                    'shopId'=>$delivery['shop_id'], 
                    'orderBn'=>$this->__sdf['orderinfo']['order_bn'],
                    'deliveryCode' => $delivery['logi_no'],
                ]);

                if ($shipment['status'] == 'succ'){
                    $this->__sdf['status_first_delivery'] = 'succ';
                }
            }
            
            //只回写第一张和最后一张
            if (!$this->__sdf['is_first_delivery'] && !$this->__sdf['is_last_delivery']) {
                return [];
            }
    
            //订单已发货时，只需要上传补打运单信息
            if (in_array($delivery_id, $this->lastDeliveryId) && $order['ship_status'] == '1') {
                $this->__sdf['status_first_delivery'] = 'succ';
            }
            
            $this->__sdf['first_delivery_id'] = $this->firstDeliveryId;


    
        }
    
        //获取所有包裹
        $orderDelivery = app::get('ome')->model('delivery')->getAllDeliversOrderId($order['order_id']);
        $delivery_package = [];
        foreach($orderDelivery as $value){
            $package = $this->_get_delivery_package($value['delivery_id']);
            $delivery_package    = array_merge($delivery_package,(array)$package);
        }
        $this->__sdf['delivery_package'] = $delivery_package;
        $this->__sdf['is_split']         = $is_split;

        // 唯一码
        $this->__sdf['serial_number'] = $this->_get_product_serial_sn_imei($delivery_id);
        
        $giftinfo = $this->isGIFT($order['order_id']);

        if($giftinfo && $giftinfo['order_sn'] && $giftinfo['relation_type']==4){
            
            $parent_order_bn = $giftinfo['order_sn'];
           
            // 判断父订单是否已发货，未发货则不回传
            $parent_order = app::get('ome')->model('orders')->dump(['order_bn' => $parent_order_bn],'order_id,ship_status');
        
            if($parent_order['ship_status'] != '1'){
                return [];
            }
            
            $this->__sdf['order_sn'] = $giftinfo['order_sn'];
            $this->__sdf['gift_order_flag'] = 1;
        }
        
        $maininfo = $this->isMAINGIFT($order['order_id']);

        if($maininfo){
            $gift_items = $this->_get_gift_items_sdf($maininfo['order_sns']);
          
            if($gift_items){
                $this->__sdf['gift_items'] = $gift_items;
                
            }
            $this->__sdf['gift_order_status'] = $maininfo['gift_order_status'];
        }

        return $this->__sdf;
    }


    
    public function isGIFT($order_id){

        //京东变成可发货
        $ordLabelObj = app::get('ome')->model('bill_label');
       
        $bills = $ordLabelObj->dump(array('label_code'=>'SOMS_GIFT_RELATED_ORDER','bill_type'=>'order','bill_id'=>$order_id),'bill_id,extend_info');

        if($bills){
           
            $extend_info = json_decode($bills['extend_info'],true);

            return $extend_info;
        }

        return false;

    }

    public function isMAINGIFT($order_id){

        //京东变成可发货
        $ordLabelObj = app::get('ome')->model('bill_label');
       
        $bills = $ordLabelObj->dump(array('label_code'=>'SOMS_GIFT_ORDER_STATUS','bill_type'=>'order','bill_id'=>$order_id),'bill_id,extend_info');

        if($bills){
           
            $extend_info = json_decode($bills['extend_info'],true);
            
            // order_sns 可能是 JSON 字符串，需要解析
            if(isset($extend_info['order_sns']) && is_string($extend_info['order_sns'])){
                $extend_info['order_sns'] = json_decode($extend_info['order_sns'], true);
            }
            
            return $extend_info;
        }

        return false;

    }

    protected function _get_gift_items_sdf($order_sn)
    {
        // 判断订单是否已发货，未发货则返回空
        $order = app::get('ome')->model('orders')->dump(['order_bn' => $order_sn],'order_id,ship_status,order_bn,sync');
       
        if($order['ship_status'] != '1'){
            return [];
        }
       
        if($order['sync'] == 'succ'){
            return [];
        }
        // 获取发货包裹信息
        $orderDelivery = app::get('ome')->model('delivery')->getAllDeliversOrderId($order['order_id']);

        $gift_items = [];
        foreach($orderDelivery as $value){
            // 根据 logi_id 从 dly_corp 表获取 type 作为 logi_type
            $dly_corp = app::get('ome')->model('dly_corp')->dump(['corp_id' => $value['logi_id']], 'type');
            
            $gift_items[$value['logi_no']] = array(
                'logi_no'       => $value['logi_no'],
                'logi_type'     => $dly_corp['type'],
                'logi_name'     => $value['logi_name'],
                'order_bn'      => $order['order_bn'], 
            );
        }
       
        return $gift_items;
    }

}
