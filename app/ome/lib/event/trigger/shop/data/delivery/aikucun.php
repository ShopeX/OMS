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
 * User: qiudi
 * Date: 18/10/12
 * Time: 上午10:32
 */

class ome_event_trigger_shop_data_delivery_aikucun extends ome_event_trigger_shop_data_delivery_common
{
    public function get_sdf($delivery_id)
    {
        $this->__sdf = parent::get_sdf($delivery_id);
        if ($this->__sdf) {
            $this->__sdf['split_model'] = $this->_is_split_switch($delivery_id);
            $this->_get_delivery_items_sdf($delivery_id);
            $this->_get_split_sdf($delivery_id);
            $this->_add_item_info();

            $deleteOlds = array();
            if($this->__sdf['oid_list']) {
                $delivery = $this->__deliverys[$delivery_id];

                $filter = [
                    'shopId'  => $delivery['shop_id'],
                    'orderBn' => $this->__sdf['orderinfo']['order_bn'],
                    'status'  => 'succ',
                ];
                $shipMent = app::get('ome')->model('shipment_log')->getList('deliveryCode,oid_list', $filter);
                
                foreach ($shipMent as $value) {
                    if(!$value['oid_list'] || $delivery['logi_no'] == $value['deliveryCode']) {
                        continue;
                    }
                    
                    $oid_list = explode(',', $value['oid_list']);
                    $deleteOlds = array_merge($deleteOlds,$oid_list);
                    foreach ($this->__sdf['oid_list'] as $k => $v) {
                        if(in_array($v, $oid_list)) {
                            unset($this->__sdf['oid_list'][$k]);
                        }
                    }
                    
                    if(empty($this->__sdf['oid_list'])) {
                        return false;
                    }
                }
            }
            
            $expresses = [];
            foreach ($this->__sdf['delivery_items'] as $item) {
                if (in_array($item['oid'],$deleteOlds)) {
                    continue;
                }
                $expresses[$item['oid']]['skuOrderNo']  = $item['oid'];
                $expresses[$item['oid']]['goodsNum']    = $item['nums'];
            }
            
            foreach ($expresses as $oid => $express) {
                $this->__sdf['sku_order_list'][] = $express;
            }
        }
        return $this->__sdf;
    }

    public function _add_item_info(){
        $productsModel = app::get('ome')->model('products');
        $goodsModel = app::get('ome')->model('goods');
        $brandModel = app::get('ome')->model('brand');

        $item_bn_arr = array();
        foreach($this->__sdf['delivery_items'] as $k => $v){
            $item_bn_arr[] = $v['bn'];
        }

        $product_data_arr = $productsModel->getList('bn,goods_id', array('bn'=>$item_bn_arr));
        $item_goods_id_arr = array();
        $item_bn_map = array();
        foreach($product_data_arr as $v){
            $item_goods_id_arr[] = $v['goods_id'];
            $item_bn_map[$v['bn']] = $v['goods_id'];
        }

        $goods_data_arr = $goodsModel->getList('goods_id,brand_id', array('goods_id'=>$item_goods_id_arr));
        $goods_brands_arr = array();
        $goods_id_map = array();
        foreach($goods_data_arr as $v){
            $goods_brands_arr[] = $v['brand_id'];
            $goods_id_map[$v['goods_id']] = $v['brand_id'];
        }

        $brand_arr = $brandModel->getList('brand_name,brand_id', array('brand_id'=>$goods_brands_arr));
        $brand_map = array();
        foreach ($brand_arr as $v){
            $brand_map[$v['brand_id']] = $v['brand_name'];
        }


        foreach($this->__sdf['delivery_items'] as $k => $v){
            $brand_name = $brand_map[$goods_id_map[$item_bn_map[$v['bn']]]];
            $this->__sdf['delivery_items'][$k]['brand_name'] = $brand_name;
        }
    }
}