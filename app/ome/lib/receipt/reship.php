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
 * 发货单数据打接口.
 * @package     main
 * @subpackage  classes
 * @author cyyr24@sina.cn
 */
class ome_receipt_reship
{

    /**
     * 退货单数据
     * @param   array   退货单信息
     * @
     * @return array
     * @
     */
    public function reship_create($data)
    {
        $reship_id       = $data['reship_id'];
        $oReship         = app::get('ome')->model('reship');
        $oReship_item    = app::get('ome')->model('reship_items');
        $oReturn         = app::get('ome')->model('return_product');
        $oDelivery_order = app::get('ome')->model('delivery_order');
        $oDelivery       = app::get('ome')->model('delivery');
        $oOrder          = app::get('ome')->model('orders');
        $reship          = $oReship->dump($reship_id, '*');
        $return_product  = $oReturn->dump($reship['return_id'], '*');
        $order_id        = $reship['order_id'];
        $branch_id       = $reship['branch_id'];
        $reship_item    = $oReship_item->getlist('product_id,bn,product_name as name,num,price,branch_id,order_item_id,item_id as reship_item_id', array('reship_id' => $reship_id, 'return_type' => array('return', 'refuse')), 0, -1);
        // source=archive or archive=1时，获取发货单信息
        if ($reship['source'] == 'archive' || $reship['archive'] == '1') {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $archive_delObj = kernel::single('archive_interface_delivery');

            $order          = $archive_ordObj->getOrders(array('order_id' => $order_id), '*');
            $delivery_order = $archive_delObj->getDelivery_order($order_id);
            $deliveryIds    = array();
            foreach ($delivery_order as $key => $value) {
                $deliveryIds[] = $value['delivery_id'];
            }
            $delivery = $archive_delObj->getDelivery(array('delivery_id' => $deliveryIds), 'delivery_id,delivery_bn');
        } else {
            $order = $oOrder->dump($order_id, 'order_bn,shop_id');
            // 获取对应的发货单
            if($reship['logi_no']) $delivery = $oDelivery->db_dump(['logi_no'=>$reship['logi_no']], 'delivery_id,delivery_bn');
            if(!$delivery) {
                $order_item_id = current($reship_item)['order_item_id'];
                $delivery = $oDelivery->db->selectrow("SELECT doi.delivery_id,doi.delivery_bn FROM sdb_sales_delivery_order_item AS doi
                                            WHERE order_item_id=" . $order_item_id);
            }
        }
        $historyLog = app::get('ome')->model('product_serial_history')->getList('bn,serial_number', array('bill_id' => $delivery['delivery_id'], 'bill_type' => '1'));
        if($historyLog) {
            $bnSerial = [];
            foreach($historyLog as $v) {
                $bnSerial[$v['bn']][] = $v['serial_number'];
            }
            foreach($reship_item as $k => $v) {
                if(empty($bnSerial[$v['bn']])) continue;

                for($i = 0; $i < $v['num']; $i++) {
                    if(empty($bnSerial[$v['bn']])) break;
                    $reship_item[$k]['sn'][] = array_shift($bnSerial[$v['bn']]);
                }
            }
        }
        // 过滤掉虚拟类型的基础物料(type=5)
        if (!empty($reship_item)) {
            $product_ids = array_column($reship_item, 'product_id');
            $basicMaterialObj = app::get('material')->model('basic_material');
            $basic_materials = $basicMaterialObj->getList('bm_id,type', array('bm_id' => $product_ids));
            $basic_materials = array_column($basic_materials, 'type', 'bm_id');
            $filtered_reship_item = array();
            foreach ($reship_item as $item) {
                $bm_id = $item['product_id'];
                if (isset($basic_materials[$bm_id]) && $basic_materials[$bm_id] == 5) {
                    continue;
                }
                $filtered_reship_item[] = $item;
            }
            $reship_item = $filtered_reship_item;
        }
        if (empty($reship_item)) {
            return array();
        }
        // 外部订单
        $dextMdl      = app::get('console')->model('delivery_extension');
        $delivery_ext = $dextMdl->dump(array('delivery_bn' => $delivery['delivery_bn']), 'original_delivery_bn');

        $shopObj        = app::get('ome')->model('shop');
        $shopInfo       = $shopObj->dump($order['shop_id'], 'name,node_id,shop_bn');

        $problem = app::get('ome')->model('return_product_problem')->db_dump(intval($reship['problem_id']), 'problem_name');


        $iostockdataObj = kernel::single('console_iostockdata');
        $branch         = kernel::single('ome_branch')->getBranchInfo($branch_id, 'branch_bn,storage_code,owner_code,type');
        $return_id      = $reship['return_id'];
        $ship_area      = $reship['ship_area'];
        $ship_area      = explode(':', $ship_area);
        $ship_area      = explode('/', $ship_area[1]);
        $reship_data    = array(
            'order_id'             => $order_id,
            'reship_id'            => $reship_id,
            'shop_id'              => $reship['shop_id'],
            'reship_bn'            => $reship['reship_bn'],
            'branch_id'            => $branch_id,
            'branch_bn'            => $branch['branch_bn'],
            'branch_type'          => $branch['type'],
            'owner_code'            => $branch['owner_code'],
            'problem_id'           => $reship['problem_id'], //售后退货类型
            'problem_name'         => $problem['problem_name'],
            'create_time'          => $reship['t_begin'],
            'memo'                 => $reship['memo'],
            'flag_type'            => $reship['flag_type'],
            'original_delivery_bn' => $delivery_ext['original_delivery_bn'],
            'delivery_bn'          => $delivery['delivery_bn'],
            'logi_no'              => $reship['return_logi_no'],
            'logi_name'            => $reship['return_logi_name'],
            'order_bn'             => $order['order_bn'],
            'receiver_name'        => $reship['ship_name'],
            'receiver_zip'         => $reship['ship_zip'],
            'receiver_state'       => $ship_area[0],
            'receiver_city'        => $ship_area[1],
            'receiver_district'    => $ship_area[2],
            'receiver_addr'        => $reship['ship_addr'],
            'receiver_phone'       => $reship['ship_tel'],
            'receiver_mobile'      => $reship['ship_mobile'],
            'receiver_email'       => $reship['ship_email'],
            'storage_code'         => $branch['storage_code'],
            'items'                => $reship_item,
            'return_type'          => $reship['return_type'],
            'shop_code'            => $shopInfo['name'],
            'node_id'              => $shopInfo['node_id'],
            'shop_bn'              => $shopInfo['shop_bn'],
            'shop_type'            => $reship['shop_type'],
            'ship_name'            => $reship['ship_name'],
            'ship_mobile'          => $reship['ship_mobile'],
            'ship_tel'             => $reship['ship_tel'],
            'ship_addr'            => $reship['ship_addr'],
            'extend_info'          => $reship['extend_info'], //扩展信息
            'reason'               => $reship['reason'],
            'return_reason'        => $return_product['content'],
        );
        $reship_data['apply_remark'] = $reship['shop_type'] == 'luban' ? $return_product['apply_remark'] : '' ;
    
        // 优化：一次性获取所有扩展字段，然后在循环中判断取值
        $arr_props = app::get('ome')->model('branch_props')->getPropsByBranchId($branch_id);
        foreach ($arr_props as $k => $v) {
            //仓库自定义字段-活动号
            if ($k == 'activity_no' && $v) {
                $reship_data['activity_no'] = $v;
            }
        }
        return $reship_data;
    }
}
