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
class brush_electron_taobao extends brush_electron_abstract{
    protected $preBn = 'bet';

    public function deliveryToSdf($delivery){
        $shop = $this->getChannelExtend();
        $deliveryExtend = $this->getTradeOrderInfoCols($delivery);
        $primary_bn = $this->preBn ? $this->preBn . $this->requestTime : $delivery[0]['delivery_bn'];

        $sdf = parent::deliveryToSdf($delivery);
        $sdf['primary_bn'] = $primary_bn;
        $sdf['shop']       = $shop;
        $sdf['delivery']   = $deliveryExtend;

        return $sdf;
    }

    /**
     * @param $arrDelivery  array
     * @return array
     */
    public function getTradeOrderInfoCols($arrDelivery) {
        $this->getDeliveryOrder($this->needRequestId);//先一步获取所有关联订单，避免多次查询，提高效率
        $deliveryExtend = array();
        foreach($arrDelivery as $delivery) {
            $order_channels_type = $this->getOrderChannelsType($delivery['delivery_id']);
            $item_name = $this->getItemName($delivery['delivery_id']);
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
            if(strlen($delivery['ship_district'])>60){
                $failArray['msg'] = '地区长度最多20个汉字';
                $this->directRet['fail'][] =  $failArray;
                continue;
            }
            $delivery['ship_mobile'] = trim($delivery['ship_mobile']);
            $delivery['ship_tel'] = trim($delivery['ship_tel']);
            $order = $this->getDeliveryOrder($delivery['delivery_id']);
            $orderBn = $shopexB2bOrder = $tbfxOrder =array();
            $totalAmount = 0;
            foreach($order['order_info'] as $val) {
                $totalAmount += $val['total_amount'];
                if($val['createway'] == 'matrix') {
                    $frontShop = $this->getShop($val['shop_id']);
                    if($frontShop['node_type'] == 'shopex_b2b') {
                        $shopexB2bOrder[$val['order_id']] = $val;
                    } elseif ($frontShop['node_type'] == 'taobao' && $frontShop['business_type'] == 'fx') {
                        $tbfxOrder[$val['order_id']] = $val;
                    } else {
                        $orderBn[] = $val['order_bn'];
                    }
                }
            }
            if($shopexB2bOrder) {
                $shopexBn = $this->_getShopexBn($shopexB2bOrder);
                is_array($shopexBn) && $orderBn = array_merge($orderBn, $shopexBn);
            }
            if($tbfxOrder) {
                $tbfxBn = $this->_getTbfxBn($tbfxOrder);
                is_array($tbfxBn) && $orderBn = array_merge($orderBn, $tbfxBn);
            }
            $tmpDelivery = $delivery;
            //取货品描述
            $goods_description = $this->getGoodsDesc($delivery['delivery_id']);
            if($goods_description) $tmpDelivery['goods_description'] = $goods_description;
            $tmpDelivery['total_amount'] = $totalAmount;
            $tmpDelivery['order_channels_type'] = $order_channels_type;
            $tmpDelivery['item_name'] = $item_name;
            $tmpDelivery['package_items'] = $package_items;
            $tmpDelivery['order_bn'] = $orderBn;
            $deliveryExtend[] = $tmpDelivery;

        }
        return $deliveryExtend;
    }

    /**
     * 获取订单店铺类型
     * @param Int $delivery_id 发货单ID
     */
    public function getOrderChannelsType($delivery_id) {
        $deliveryOrder = $this->getDeliveryOrder($delivery_id);
        $tbbusiness_type = 'other';
        $createway = 'local';
        $node_type = '';
        foreach ($deliveryOrder['order_info'] as $k => $v) {
            $createway = $v['createway'];
            if ($v['createway'] != 'matrix') {
                break;
            }
        }

        if ($deliveryOrder && $createway == 'matrix') {
            $firstOrderId = $deliveryOrder['order_id'][0];
            $shop_id = $deliveryOrder['order_info'][$firstOrderId]['shop_id'];
            $shop = $this->getShop($shop_id);
            if ($shop) {
                $tbbusiness_type = $shop['tbbusiness_type'];
                $node_type       = $shop['node_type'];
            }
        }
        $order_channels_type = logisticsmanager_waybill_taobao::get_order_channels_type($tbbusiness_type, $node_type);
        return $order_channels_type;
    }

    /**
     * 获取商品名称
     * @param Int $delivery_id 发货单ID
     * @return string
     */
    public function getItemName($delivery_id) {
        $deliveryOrder = $this->getDeliveryOrder($delivery_id);
        $item_name = 'other item name';
        if ($deliveryOrder) {
            $firstOrderId = $deliveryOrder['order_id'][0];
            $orderItems = $this->getOrderItems($firstOrderId);
            if ($orderItems) {
                //订单明细中第一个商品名称
                $item_name = $orderItems[0]['name'] ? $orderItems[0]['name']:'other item name';
                if(isset($item_name[120])) {
                    $item_name = mb_substr($item_name, 0, 120, 'utf8');
                }
            }
        }
        return $item_name;
    }

    /**
     * 获取包裹明细
     * @param Int $delivery_id 发货单ID
     * @return array
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

    private function _getShopexBn($arrOrder) {
        $fxw = app::get('ome')->model('fxw_orders')->getList('*', array('order_id'=>array_keys($arrOrder)));
        $fxwData = array();
        foreach($fxw as $val) {
            $fxwData[$val['order_id']] = $val;
        }
        $shopexBn = array();
        foreach($arrOrder as $order) {
            $shopexBn[] = $fxwData[$order['order_id']]['dealer_order_id'] ? $fxwData[$order['order_id']]['dealer_order_id'] : $order['order_bn'];
        }
        return $shopexBn;
    }

    private function _getTbfxBn($arrOrder) {
        $tbfx = app::get('ome')->model('tbfx_orders')->getList('*', array('order_id'=>array_keys($arrOrder)));
        $tbfxData = array();
        foreach($tbfx as $val) {
            $tbfxData[$val['order_id']] = $val;
        }
        $tbfxBn = array();
        foreach ($arrOrder as $order) {
            $tbfxBn[] = $tbfxData[$order['order_id']]['tc_order_id'] ? $tbfxData[$order['order_id']]['tc_order_id'] : $order['order_bn'];
        }
        return $tbfxBn;
    }

    public function getGoodsDesc($delivery_id)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        $extMaterialMdl = app::get('material')->model('basic_material_ext');
        $goodsTypeMdl = app::get('ome')->model('goods_type');
        
        $items = $this->getDeliveryItems($delivery_id);
        
        $product_ids = array_column($items, 'product_id');
        
        //ext
        $extList = $extMaterialMdl->getlist('cat_id', array('bm_id'=>$product_ids));
        $catIds = array_column($extList, 'cat_id');
        
        //type
        $typeList = $goodsTypeMdl->getList('type_id,name', array('type_id'=>$catIds));
        if($typeList){
            return $typeList[0]['name'];
        }
    }
}