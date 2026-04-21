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
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class ome_event_trigger_shop_data_delivery_taobao extends ome_event_trigger_shop_data_delivery_common
{
    public function get_sdf($delivery_id)
    {
        $this->__sdf = parent::get_sdf($delivery_id);

        if ($this->__sdf) {
            $this->_get_product_serial_sdf($delivery_id);
           
            $this->_get_split_sdf($delivery_id);
            $this->__sdf['is_virtual'] = $this->_order_is_all_virtual($delivery_id);
            
//            if($this->__sdf['oid_list']) {
//                $delivery = $this->__deliverys[$delivery_id];
//                $shipMent = app::get('ome')->model('shipment_log')->getList('deliveryCode,oid_list', ['shopId'=>$delivery['shop_id'], 'orderBn'=>$this->__sdf['orderinfo']['order_bn']]);
//                foreach ($shipMent as $value) {
//                   if(!$value['oid_list'] || $this->__sdf['logi_no'] == $value['deliveryCode']) {
//                       continue;
//                   }
//                   $oid_list = explode(',', $value['oid_list']);
//                   foreach ($this->__sdf['oid_list'] as $k => $v) {
//                       if(in_array($v, $oid_list)) {
//                           unset($this->__sdf['oid_list'][$k]);
//                       }
//                   }
//                   if(empty($this->__sdf['oid_list'])) {
//                       return false;
//                   }
//                }
//            }
            
            $this->__sdf['order_extend'] = $this->_get_order_extend($delivery_id);

            $this->__sdf['store_code']  = '';

            // BN对应的OID
            $oidList      = [];
            $orderObjects = $this->_get_all_order_objects($delivery_id);
            $this->__sdf['order_objects'] = $orderObjects;
            foreach ($orderObjects as $object) {

                if ($object['store_code']) {
                    $this->__sdf['store_code']  = $object['store_code'];
                }
                
                foreach ($object['order_items'] as $item) {
                    if(empty($object['oid'])) {
                        continue;
                    }
                    $oidList[$item['bn']][$object['oid']] = $object['oid'];
                }
            }

            // 多包裹回写
            $delivery_package = $this->_get_delivery_package($delivery_id);
            $this->__sdf['delivery_package'] = [];
            if (count(array_unique(array_column((array)$delivery_package, 'logi_no'))) > 1){
                $this->__sdf['delivery_package'] = $delivery_package;
            }

            // 唯一码
            $this->_get_product_serial_sn_imei_sdf($delivery_id);
            $this->__sdf['serial_number'] = $this->_get_product_serial_sn_imei($delivery_id);

        }

        $branch = $this->_get_branch($this->__deliverys[$delivery_id]['branch_id']);
        $this->__sdf['branch'] = $branch;
        if ($branch['wms_id']) {
            $express = app::get('wmsmgr')->model('express_relation')->db_dump(array('wms_id' => $branch['wms_id'], 'logi_id' => $this->__deliverys[$delivery_id]['logi_id']));

            if ($express['wms_express_bn'] && false !== strpos($express['wms_express_bn'], 'DISTRIBUTOR_')) {
                $this->__sdf['logi_type'] = $express['wms_express_bn'];
            }

        }

        return $this->__sdf;
    }
}
