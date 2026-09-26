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

class ome_sales_data_type_lkb{

    /**
     * doTrans
     * @param mixed $obj obj
     * @return mixed 返回值
     */
    public function doTrans($obj){
        $deliveryObj = app::get('ome')->model('delivery');
        $delivery_items_detailObj = app::get('ome')->model('delivery_items_detail');

        $delivery_id = $obj['delivery_id'];
        $oDelivery      = app::get('ome')->model('delivery');

        //[拆单]获取订单对应所有发货单delivery_id
        $orderSplitLib    = kernel::single('ome_order_split');
        $split_seting     = $orderSplitLib->get_delivery_seting();
        if($split_seting && !empty($obj['order_id'])){
            $order_id       = $obj['order_id'];
        }
        
        //获取平台优惠明细
        $couponListDetail = kernel::single('ome_order_coupon')->getOrderCoupon($obj['order_id']);
        $platformAmount = 0;
        if ($couponListDetail && current($couponListDetail)['source'] == 'push' && in_array(current($couponListDetail)['shop_type'], array('360buy'))) {
            $platformAmount = isset($couponListDetail[$obj['oid']]['calcPlatformDiscountsTotalAmount']) ? $couponListDetail[$obj['oid']]['calcPlatformDiscountsTotalAmount'] : 0;
        }
    
        //捆绑取最后一个货品的item_id做下标以及库存信息
        $items = $obj['order_items'];
        foreach($items as $k =>$item){
            $mark = $k;
            $lkb['order_id'] = $item['order_id'];
            $lkb['item_id'] = $item['item_id'];
            $lkb['obj_id'] = $item['obj_id'];
        }

        $sale_item[$mark] = array(
    		'iostock_id'=>'',
    		'product_id' => 0,
    		'bn' => $obj['bn'],
    		'name' => $obj['name'],
    		'spec_name'=>'',
    		'pmt_price' => $obj['pmt_price'],
    		'orginal_price' => $obj['price'],
    		'price' => $obj['price'],
    		'nums' => $obj['quantity'],
    		'sale_price' => $obj['sale_price'],
    		'cost'=> 0.00,
        	'obj_id' => $obj['obj_id'],
            'obj_type'=>'lkb',
            's_type' => $obj['s_type'],
            'oid' => $obj['oid'],
            'order_item_id' => $item['item_id'],
            'platform_amount' => $platformAmount,
            'sales_material_bn'=>$obj['bn'],
            'refund_money' => isset($obj['refund_money']) ? $obj['refund_money'] : 0,
        );

        $delivery_items_detail_info = $delivery_items_detailObj->dump(array('order_id'=>$lkb['order_id'],'order_item_id'=>$lkb['item_id'],'order_obj_id'=>$lkb['obj_id'],'delivery_id'=>$delivery_id));
        $sale_item[$mark]['item_detail_id'] = $delivery_items_detail_info['item_detail_id'];

        $delivery_info = $deliveryObj->dump(array('delivery_id'=>$delivery_items_detail_info['delivery_id']),'branch_id');
        $sale_item[$mark]['branch_id'] = $delivery_info['branch_id'];

        return $sale_item;
    }
}
