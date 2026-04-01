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
 * vop发货数据
 *
 * @category
 * @package
 * @author sunjing@shopex.cn
 * @version $Id: Z
 */
class ome_event_trigger_shop_data_delivery_vop extends ome_event_trigger_shop_data_delivery_common
{
    public function get_sdf($delivery_id)
    {
        $this->__sdf = parent::get_sdf($delivery_id);
        $delivery = $this->__deliverys[$delivery_id];
      
        if ($delivery['type'] == 'vopczc') {
            $this->__sdf['logi_no']   = $this->__delivery_orders[$delivery_id]['order_bn'];
        }
        $this->_get_split_sdf($delivery_id);
        if ($this->__sdf['is_split'] == 1) {
            $this->_get_delivery_items_sdf($delivery_id, true);
            if(empty($this->__sdf['delivery_items'])) {
                return array();
            }
        } else {
            $this->_get_delivery_items_sdf($delivery_id);
        }

        if (kernel::single('ome_order_bool_type')->isJITX($this->__delivery_orders[$delivery_id]['order_bool_type'])) {
            $orderObject = $this->_get_order_objects($delivery_id);
            $one = current($orderObject);
            $this->__sdf['store_code'] = $one['store_code'];

            $this->_get_order_all_objects_sdf($delivery_id);
        }
        
        if ($this->__sdf) {
            $delivery_bill_detail = $this->_get_delivery_bill_detail($delivery_id);
            $delivery_bill_items = array();
        	foreach ($delivery_bill_detail as $d_b_detail) {
        		if ($d_b_detail['logi_no']) {
                    //
                    $delivery_items = $this->__sdf['delivery_items'];

                    foreach($delivery_items as $v){
                        if($v['delivery_id'] == $delivery_id){
                            $delivery_bill_items[] = array(

                                'logi_no'	=>	$d_b_detail['logi_no'],
                                'number'        => 0,
                              
                                'shop_goods_id' => $v['shop_goods_id'],

                            );
                        }
                    }
        		}
        	}
            if ($delivery_bill_items) $this->__sdf['delivery_bill_items'] = $delivery_bill_items;
        }

        // 判断发货单是否有合单
        $this->__sdf && $this->__sdf['vop_merge_list'] = [];
        if ($this->__sdf['parent_id']) {
            $deliItemDetailMdl = app::get('ome')->model('delivery_items_detail');
            $mergeData         = $deliItemDetailMdl->getList('*', [
                'delivery_id'    => $this->__sdf['parent_id'],
            ]);
            if ($mergeData) {
                $mergeOrderIdArr       = array_column($mergeData, 'order_id');
                $mergeOrderObjectIdArr = array_column($mergeData, 'order_obj_id');

                // 去除掉已作废的订单
                $mergeOrderList  = app::get('ome')->model('orders')->getList('order_bn,order_id', ['order_id|in'=>$mergeOrderIdArr, 'status|noequal'=>'dead']);
                $mergeOrderList  = array_column($mergeOrderList, null, 'order_id');
                $mergeOrderIdArr = array_column($mergeOrderList, 'order_id');

                $orderObjectMdl       = app::get('ome')->model('order_objects');
                $mergeOrderObjectList = $orderObjectMdl->getList('order_id,shop_goods_id,quantity', ['order_id|in'=>$mergeOrderIdArr, 'obj_id|in'=>$mergeOrderObjectIdArr]);
                if ($mergeOrderObjectList) {
                    foreach ($mergeOrderObjectList as $k => $v) {
                        $this->__sdf['vop_merge_list'][] = [
                            'barcode'     => $v['shop_goods_id'],
                            'quantity'    => $v['quantity'],
                            'trade_id'    => $mergeOrderList[$v['order_id']]['order_bn'],
                        ];
                    }

                    // 唯品会合单发货，只有主单才发起发货回写
                    $order_extend = $this->_get_order_extend($delivery_id);
                    if ($order_extend['platform_logi_no'] && $order_extend['platform_logi_no'] != $this->__sdf['logi_no']) {
                        return false;
                    }
                }
            }
        }

        return $this->__sdf;
    }

    protected function _get_delivery_items_sdf($delivery_id, $allDelivery = false, $check_ship_status = true)
    {
        $delivery_items = array();

        if($allDelivery) {
            $delivery_items_detail = $this->_get_delivery_items_detail_order($delivery_id, $check_ship_status);
        } else {
            $delivery_items_detail = $this->_get_delivery_items_detail($delivery_id);
        }
        $order_objects         = $this->_get_order_objects($delivery_id);
        $pkg_nums = array();
        foreach ($delivery_items_detail as $key => $value) {
            $order_item = $order_objects[$value['order_obj_id']]['order_items'][$value['order_item_id']];

            if ($value['item_type'] == 'pkg') {
                $number = $order_objects[$value['order_obj_id']]['quantity']*$value['number']/$order_item['nums'];
                $number = ceil($number);
                if (isset($pkg_nums[$value['order_obj_id']])){
                     if($pkg_nums[$value['order_obj_id']]>=$order_objects[$value['order_obj_id']]['quantity']){
                           $number=0;
                     }

                     $pkg_nums[$value['order_obj_id']]+=$number;
                }else{
                    $pkg_nums[$value['order_obj_id']]=$number;
                }
                // 解决捆绑商品回传，销售物料重复且数量为0，报“商品数量 必须大于等于1”的问题
                if (!$number) {
                    continue;
                }
                $delivery_items[] = array(
                    'name'          => trim($order_objects[$value['order_obj_id']]['name']),
                    'bn'            => trim($order_objects[$value['order_obj_id']]['bn']),
                    'number'        => $number,
                    'item_type'     => $value['item_type'],
                    'shop_goods_id' => $order_objects[$value['order_obj_id']]['shop_goods_id'],
                    'oid'           => $order_objects[$value['order_obj_id']]['oid'],
                    'logi_no'       => $value['logi_no'] ? $value['logi_no'] : $this->__sdf['logi_no'],
                    'logi_type'     => $value['logi_type'] ? $value['logi_type'] : $this->__sdf['logi_type'],
                    'logi_name'     => $value['logi_name'] ? $value['logi_name'] : $this->__sdf['logi_name'],
                    'delivery_id'   =>$value['delivery_id'],
                );

            } else {
                $delivery_items[] = array(
                    'name'          => trim($order_objects[$value['order_obj_id']]['name']),
                    'bn'            => trim($value['bn']),
                    'number'        => $value['number'],
                    'item_type'     => $value['item_type'],
                    'shop_goods_id' => $order_item['shop_goods_id'],
                    'shop_product_id' => $order_item['shop_product_id'],
                    'promotion_id'  => $order_item['promotion_id'],
                    'oid'           => $order_objects[$value['order_obj_id']]['oid'],
                    'nums'          => $order_item['nums'],
                    'sendnum'       => $order_item['sendnum'],
                    'logi_no'         => $value['logi_no'] ? $value['logi_no'] : $this->__sdf['logi_no'],
                    'logi_type'       => $value['logi_type'] ? $value['logi_type'] : $this->__sdf['logi_type'],
                    'logi_name'     => $value['logi_name'] ? $value['logi_name'] : $this->__sdf['logi_name'],
                    'delivery_id'   =>$value['delivery_id'],
                );
            }
        }

        $this->__sdf['delivery_items'] = $delivery_items;
    }
}