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
 * @Author: xueding@shopex.cn
 * @Date: 2023/4/20
 * @Describe: 微信视频号
 */

class ome_event_trigger_shop_data_delivery_wxshipin extends ome_event_trigger_shop_data_delivery_common
{
    public function get_sdf($delivery_id)
    {
        $this->__sdf = parent::get_sdf($delivery_id);
        
        if ($this->__sdf) {
            $this->_get_split_sdf($delivery_id);
            // 如果没有全部发货
            if ($this->__sdf['orderinfo']['ship_status'] != '1') {
                return array();
            }
            
            $order = $this->__delivery_orders[$delivery_id];
            
            $orderInfo =  app::get('ome')->model('orders')->dump(array('order_id'=>$order['order_id']) , '*' , array('order_objects' => array('*',array('order_items' => array('*')))));
            
            $expresses = [];
            foreach ($orderInfo['order_objects'] as $key => $object) {
                if (empty($object['oid'])) {
                    continue;
                }
                foreach ($object['order_items'] as $k => $item) {
                    // 过滤掉已经删除的明细
                    if (isset($item['delete']) && $item['delete'] == 'true') {
                        continue;
                    }
                    if ($item['item_type'] == 'pkg') {
                        $shop_goods_id = $object['shop_goods_id'];
                    } else {
                        $shop_goods_id = $item['shop_goods_id'];
                    }
                    $quantity        = $object['quantity'];
                    $logi_no         = $this->__sdf['logi_no'];
                    $logi_type       = $this->__sdf['logi_type'];
                    $logi_name       = $this->__sdf['logi_name'];
                    $shop_product_id = $item['shop_product_id'];
                    // 组合商品(pkg)等场景 shop_product_id 常为 0，平台要求 sku_id > 0
                    // 视频号子单 oid 即为平台 sku_id
                    if (empty($shop_product_id) || $shop_product_id == '0' || $shop_product_id == '-1') {
                        $shop_product_id = $object['oid'];
                    }
                    
                    $newKey                                                  = $object['oid'] . '_' . $shop_product_id;
                    $expresses[$newKey]['nums']                              = $quantity;
                    $expresses[$newKey]['packages'][$newKey]['logistics_no'] = $logi_no;
                    $expresses[$newKey]['packages'][$newKey]['company_code'] = $logi_type;
                    $expresses[$newKey]['packages'][$newKey]['company_name'] = $logi_name;
                    $expresses[$newKey]['packages'][$newKey]['product_id']   = $shop_goods_id;
                    $expresses[$newKey]['packages'][$newKey]['sku_id']       = $shop_product_id;
                    $expresses[$newKey]['packages'][$newKey]['oid']          = $object['oid'];
                    $expresses[$newKey]['packages'][$newKey]['product_cnt']  += $quantity;
                    if ($item['item_type'] == 'pkg') {
                        break;
                    }
                }
            }
            
            $packages = [];
            foreach ($expresses as $oid => $express) {
                $packages[] = current($express['packages']);
            }
            
            if (!$packages) {
                return array();
            }
            
            $this->__sdf['goods']    = $packages;
        }
        
        return $this->__sdf;
    }
}
