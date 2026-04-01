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
 * User: gehuachun
 * Date: 2018/11/20
 * Time: 11:18 AM
 */

class ome_event_trigger_shop_data_delivery_weimobr extends ome_event_trigger_shop_data_delivery_common
{
    /**
     * @param Int $delivery_id
     * @return array|void
     */
    public function get_sdf($delivery_id)
    {
        $this->__sdf = parent::get_sdf($delivery_id);
        if ($this->__sdf) {
            $this->__sdf['split_model'] = $this->_is_split_switch($delivery_id);
            $this->_get_order_objects_sdf($delivery_id);
            $this->_get_delivery_items_sdf($delivery_id);
            $this->_get_split_sdf($delivery_id);
            
            // 拆单时，过滤已经回写成功的oid
            if($this->__sdf['oid_list']) {
                $shop_id = isset($this->__deliverys[$delivery_id]['shop_id']) ? $this->__deliverys[$delivery_id]['shop_id'] : '-1';
                $order_bn = isset($this->__sdf['orderinfo']['order_bn']) ? $this->__sdf['orderinfo']['order_bn'] : '-1';
                
                // shipment_log
                $shipMent = app::get('ome')->model('shipment_log')->getList('deliveryCode,oid_list', ['shopId'=>$shop_id, 'orderBn'=>$order_bn]);
                if($shipMent){
                    foreach ($shipMent as $value)
                    {
                        if(!$value['oid_list'] || $this->__sdf['logi_no'] == $value['deliveryCode']) {
                            continue;
                        }
                        
                        $oid_list = explode(',', $value['oid_list']);
                        foreach ($this->__sdf['oid_list'] as $k => $v)
                        {
                            if(in_array($v, $oid_list)) {
                                unset($this->__sdf['oid_list'][$k]);
                            }
                        }
                        
                        // delivery_items
                        foreach ($this->__sdf['delivery_items'] as $dlyItemKey => $dlyItemValue)
                        {
                            if(in_array($dlyItemValue['oid'], $oid_list)) {
                                unset($this->__sdf['delivery_items'][$dlyItemKey]);
                            }
                        }
                        
                        // check
                        if(empty($this->__sdf['oid_list'])) {
                            return false;
                        }
                    }
                }
            }
        }
        
        return $this->__sdf;
    }
}