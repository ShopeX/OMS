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
 * [快手平台]获取数据
 * 
 */
class ome_event_trigger_shop_data_delivery_kuaishou extends ome_event_trigger_shop_data_delivery_common
{
    /**
     * 支持拆单发货、多个订单合并发货
     * 
     * @param int $delivery_id
     * @return array
     */
    public function get_sdf($delivery_id)
    {
        //获取发货单主信息
        $this->__sdf = parent::get_sdf($delivery_id);

        if (!$this->__sdf) {
            return [];
        }

        $this->_get_split_sdf($delivery_id);

        //获取发货的商品明细
        $this->_get_delivery_items_sdf($delivery_id);

        // 订单明细
        $this->_get_order_objects_sdf($delivery_id);

        // 唯一码
        $this->__sdf['serial_number'] = $this->_get_product_serial_sn_imei($delivery_id);

        // 如果订单已经发货，处理多包裹
        $this->__sdf['delivery_package'] = [];
        if ($this->__sdf['orderinfo']['ship_status'] == '1'){
            // 查询订单所有发货单
            foreach ($this->__delivery_orders as $delivery_id => $order){
                $delivery_package = $this->_get_delivery_package($delivery_id);

                $package_count = count(array_unique(array_column((array)$delivery_package, 'logi_no')));
                if ($package_count <= 1) {
                    continue;
                }

                // 需要过滤掉主运单号
                foreach ($delivery_package as $dp){
                    if (!$dp['logi_no'] || $dp['logi_no'] == $this->__deliverys[$delivery_id]['logi_no']){
                        continue;
                    }

                    $this->__sdf['delivery_package'][] = $dp;
                }
            }

        }

        // 找到主单对应赠品的运单号，赠品不能单独发货，暂时先不考虑
        // if ($this->__sdf['is_split']){
        //     $mainOid = array_column($this->__sdf['delivery_items'], 'oid');

        //     $shipmentList = app::get('ome')->model('shipment_log')->getList('deliveryCode', [
        //         'orderBn' => $this->__sdf['orderinfo']['order_bn'],
        //         'shopId' => $this->__sdf['orderinfo']['shop_id'],
        //         'status' => 'succ'
        //     ]);
        //     $shipmentList = array_column($shipmentList, 'deliveryCode');

        //     $gift_logi_no = [];

        //     $delivery_items_detail = $this->_get_delivery_items_detail_order($delivery_id, false);
        //     foreach ($delivery_items_detail as $detail){
        //         if ($detail['item_type'] == 'gift' 
        //                 && $detail['oid'] 
        //                 && $detail['main_oid'] 
        //                 && in_array($detail['main_oid'], $mainOid)
        //                 && $detail['delivery_id'] != $delivery_id
        //                 && $detail['logi_no']
        //                 && !in_array($detail['logi_no'], $shipmentList)
        //             ){
        //             // 找到对应
        //             $gift_logi_no[$detail['delivery_id']]['logi_no'] = $detail['logi_no'];
        //             $gift_logi_no[$detail['delivery_id']]['logi_type'] = $detail['logi_type'];
        //             $gift_logi_no[$detail['delivery_id']]['oid'] = $detail['oid'];
        //         }
        //     }

        //     // 关联赠品
        //     $this->__sdf['gift_logi_no'] = $gift_logi_no;
        // }

        return $this->__sdf;
    }
}