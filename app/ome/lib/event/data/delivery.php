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

class ome_event_data_delivery
{

    /**
     *
     * 发货通知单请求数据生成
     * @param array $delivery_id 本地发货通知单(合并后的)id
     */
    public function generate($delivery_id)
    {
        $deliveryObj      = app::get('ome')->model('delivery');
        $deliveryOrderObj = app::get('ome')->model('delivery_order');
        $orderObj         = app::get('ome')->model('orders');
        $shopObj          = app::get('ome')->model('shop');
        $branchObj        = app::get('ome')->model('branch');
        $memberObj        = app::get('ome')->model('members');
        $dlyCorpObj       = app::get('ome')->model('dly_corp');
        $orderExtendObj   = app::get('ome')->model('order_extend');
        $didObj           = app::get('ome')->model('delivery_items_detail');
        $checkMdl         = app::get('ome')->model('order_objects_check_items');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $data             = $deliveryObj->dump($delivery_id, '*', array('delivery_items' => array('*'), 'delivery_order' => array('*')));
        if ($data['parent_id'] > 0) {
            return array();
        }

        //判断是否是平台发货订单如果是不允许
        if ($data['bool_type'] & ome_delivery_bool_type::__PLATFORM_CODE) {
            return array();
        }

        //重组发货单明细上的金额信息 单价、平摊优惠价、平摊优惠金额
        $dly_order = $deliveryOrderObj->getlist('*', array('delivery_id' => $delivery_id), 0, -1);

        $pmt_orders       = $deliveryObj->getPmt_price($dly_order);
        $sale_orders      = $deliveryObj->getsale_price($dly_order);
        $tbdx_flag        = false;
        $is_order_invoice = 'false';
        $invoice_items    = array();
        if ($data) {
            // 保留发货单上的备注信息
            $data['delivery_memo'] = $data['memo'];
            
            // 批量获取发货商品关联的所有基础物料信息
            $basic_materials = array();
            $product_ids = array_column($data['delivery_items'], 'product_id');
            if (!empty($product_ids)) {
                $basic_materials = $basicMaterialObj->getList('bm_id,material_bn,type,is_ctrl_store', array('bm_id' => $product_ids));
                $basic_materials = array_column($basic_materials, null, 'bm_id');
            }
            
            // 过滤掉物料属性为：虚拟类型，并且不管控库存的基础物料(type=5 && is_ctrl_store=2)
            $filtered_delivery_items = array();
            $virtualDeliveryItems = array();
            foreach ($data['delivery_items'] as $key => $item)
            {
                $product_id = $item['product_id'];
                
                // 虚拟类型 + 不管控库存的基础物料
                if ($basic_materials[$product_id]['type'] == 5 && $basic_materials[$product_id]['is_ctrl_store'] == 2) {
                    $virtualDeliveryItems[$key] = $item;
                }else{
                    $filtered_delivery_items[$key] = $item;
                }
            }
            
            // 如果过滤虚拟类型的基础物料之后没有发货商品，则返回空数组
            if (empty($filtered_delivery_items)) {
                //@todo：发货单明细里都是虚拟商品,则打标记为虚拟发货单，并推送selfwms接口，自动完成发货
                if($virtualDeliveryItems){
                    // 虚拟发货单：请求selfwms标记
                    $data['is_self_shipment'] = true;
                    
                    // [重置发货商品明细]使用过滤后的数据
                    $data['delivery_items'] = $virtualDeliveryItems;
                }else{
                    // 发货单：无发货商品，直接返回空数组
                    return array();
                }
            }else{
                // [重置发货商品明细]使用过滤后的数据
                $data['delivery_items'] = $filtered_delivery_items;
            }
            
            // ========== 性能优化：批量查询所有关联数据 ==========
            // 1. 收集所有需要查询的ID
            $order_ids = array_column($data['delivery_order'], 'order_id');
            if (empty($order_ids)) {
                return array();
            }
            $first_order_id = $order_ids[0];
            
            // 2. 查询第一个订单信息（用于商品循环中的判断）
            $order_detail = $orderObj->dump($first_order_id, 'order_source,shop_type,status');
            if($order_detail['status'] != 'active'){
                return array();
            }
            $tbdx_flag = ($order_detail['order_source'] == 'tbdx' && $order_detail['shop_type'] == 'taobao');
            
            // 3. 批量查询tbdx订单的明细信息（如果需要）
            $did_arrs_map = array();
            $tbfx_items_map = array();
            if ($tbdx_flag && !empty($data['delivery_items'])) {
                $delivery_item_ids = array_column($data['delivery_items'], 'item_id');
                if (!empty($delivery_item_ids)) {
                    $did_arrs = $didObj->getList('delivery_item_id,item_type,order_obj_id,order_item_id', 
                        array('delivery_id' => $delivery_id, 'delivery_item_id' => $delivery_item_ids));
                    $did_arrs_map = array_column($did_arrs, null, 'delivery_item_id');
                    
                    // 批量查询tbfx订单明细
                    if (!empty($did_arrs)) {
                        $obj_ids = array_unique(array_column($did_arrs, 'order_obj_id'));
                        $item_ids = array_unique(array_column($did_arrs, 'order_item_id'));
                        if (!empty($obj_ids) && !empty($item_ids)) {
                            $tbitemObj = app::get('ome')->model('tbfx_order_items');
                            $tbfx_items = $tbitemObj->getList('obj_id,item_id,buyer_payment', 
                                array('obj_id' => $obj_ids, 'item_id' => $item_ids));
                            // 构建映射：obj_id + item_id => buyer_payment
                            foreach ($tbfx_items as $tbfx_item) {
                                $key = $tbfx_item['obj_id'] . '_' . $tbfx_item['item_id'];
                                $tbfx_items_map[$key] = $tbfx_item['buyer_payment'];
                            }
                        }
                    }
                }
            }
            
            // 4. 批量查询所有订单的关联数据
            $orders_data = array();
            $shop_ids = array();
            $member_ids = array();
            
            // 批量查询订单详情
            foreach ($order_ids as $order_id) {
                $row = $orderObj->dump($order_id, '*', array('order_objects' => array('*', array('order_items' => array('*')))));
                $orders_data[$order_id] = $row;
                if ($row['shop_id']) $shop_ids[] = $row['shop_id'];
                if ($row['member_id']) $member_ids[] = $row['member_id'];
            }
            
            // 批量查询订单扩展信息
            $order_extends = $orderExtendObj->getList('*', array('order_id' => $order_ids));
            $order_extends_map = array_column($order_extends, null, 'order_id');
            
            // 批量查询订单收货人信息
            $orderReceiverMdl = app::get('ome')->model('order_receiver');
            $order_receivers = $orderReceiverMdl->getList('order_id,encrypt_source_data', array('order_id' => $order_ids));
            $order_receivers_map = array_column($order_receivers, null, 'order_id');
            
            // 批量查询检查明细（唯品会）
            $is_jitx = false;
            if (!empty($orders_data)) {
                $first_order_data = reset($orders_data);
                $is_jitx = kernel::single('ome_order_bool_type')->isJITX($first_order_data['order_bool_type']);
            }
            $check_lists_map = array();
            if ($is_jitx && !empty($order_ids)) {
                $check_lists = $checkMdl->getList('order_id,bn', array('order_id' => $order_ids));
                foreach ($check_lists as $check) {
                    $check_lists_map[$check['order_id']][] = $check;
                }
            }
            
            // 批量查询店铺信息
            $shops_map = array();
            if (!empty($shop_ids)) {
                $shops = $shopObj->getList('shop_id,shop_bn,name,node_id,addon,business_category', array('shop_id' => array_unique($shop_ids)));
                $shops_map = array_column($shops, null, 'shop_id');
            }
            
            // 批量查询会员信息
            $members_map = array();
            if (!empty($member_ids)) {
                $members = $memberObj->getList('member_id,uname,name', array('member_id' => array_unique($member_ids)));
                $members_map = array_column($members, null, 'member_id');
            }
            
            // 批量查询tbdx订单的total_amount（如果需要）- 使用参数化查询避免SQL注入
            $tbfx_totals_map = array();
            if ($tbdx_flag && !empty($order_ids)) {
                // 使用参数化查询，避免SQL注入
                $order_ids_placeholders = implode(',', array_fill(0, count($order_ids), '?'));
                $tbfx_totals = $orderObj->db->select(
                    "SELECT order_id, SUM(buyer_payment) as total_amount FROM sdb_ome_tbfx_order_items WHERE order_id IN ({$order_ids_placeholders}) GROUP BY order_id",
                    $order_ids
                );
                foreach ($tbfx_totals as $tbfx_total) {
                    $tbfx_totals_map[$tbfx_total['order_id']] = $tbfx_total['total_amount'];
                }
            }
            
            // 5. 查询物流公司和仓库信息（循环外，只查询一次）
            $dlyCorpInfo = $dlyCorpObj->dump($data['logi_id'], 'type, protect');
            $data['logi_code'] = isset($dlyCorpInfo['type']) ? $dlyCorpInfo['type'] : '';
            $data['logi_protect'] = $dlyCorpInfo['protect'];
            
            // 查询仓库信息
            //@todo：千万别用dump查询，否则自动推送WMS时，没有仓库信息
            //$branchInfo = $branchObj->dump($data['branch_id'], 'branch_bn,storage_code,owner_code');
            $branchInfo = $branchObj->db->selectrow('SELECT branch_bn,storage_code,owner_code FROM sdb_ome_branch WHERE branch_id='. $data['branch_id']);
            
            $data['storage_code'] = $branchInfo['storage_code'] ?? '';
            $data['branch_bn'] = $branchInfo['branch_bn'] ?? '';
            $data['owner_code'] = $branchInfo['owner_code'] ?? '';
            
            // 查询仓库扩展属性
            $arr_props = app::get('ome')->model('branch_props')->getPropsByBranchId($data['branch_id']);
            foreach ($arr_props as $k => $v) {
                if ($k == 'activity_no' && $v) {
                    $data['activity_no'] = $v;
                }
            }
            // ========== 性能优化结束 ==========
            
            // format - 处理发货商品明细
            foreach ($data['delivery_items'] as $key => $item) {
                if ($tbdx_flag && isset($did_arrs_map[$item['item_id']])) {
                    $didArrs = $did_arrs_map[$item['item_id']];
                    $tbfx_key = $didArrs['order_obj_id'] . '_' . $didArrs['order_item_id'];
                    if (isset($tbfx_items_map[$tbfx_key])) {
                        $buyer_payment = $tbfx_items_map[$tbfx_key];
                        $data['delivery_items'][$key]['price'] = round(($buyer_payment / $item['number']), 2);
                        $data['delivery_items'][$key]['sale_price'] = $buyer_payment;
                    }
                } else {
                    $data['delivery_items'][$key]['price'] = $sale_orders[$item['bn']];
                    $data['delivery_items'][$key]['sale_price'] = $sale_orders[$item['bn']] * $item['number'];
                }

                $data['delivery_items'][$key]['pmt_price'] = $pmt_orders[$item['bn']]['pmt_price'];

                //回传奇门平台时需要qimen_delivery_id
                $data['delivery_items'][$key]['qimen_delivery_id'] = $data['delivery_items'][$key]['delivery_id'];
                $data['delivery_items'][$key]['delivery_items_id'] = $item['item_id'];
                unset($data['delivery_items'][$key]['delivery_id']);
            }

            // 处理订单数据
            $order_bns = array();
            $encrypt_source_order_bn = '';
            foreach ($data['delivery_order'] as $dk => $order) {
                $order_id = $order['order_id'];
                $row = $orders_data[$order_id];
                $orderextend = $order_extends_map[$order_id] ?? array();
                $orderReceivers = $order_receivers_map[$order_id] ?? array();
                $shopInfo = $shops_map[$row['shop_id']] ?? array();
                $member_uname = $members_map[$row['member_id']] ?? array();

                if ($tbdx_flag && isset($tbfx_totals_map[$order_id])) {
                    $row['cost_item'] = $row['total_amount'] = $tbfx_totals_map[$order_id];
                }

                if ($row['is_tax'] == 'true') {
                    $data['is_order_invoice'] = 'true';
                    $data['invoice']['invoice_desc'] = $row['tax_title'];
                    $data['invoice_money'] += ($row['total_amount'] - $row['service_price']);
                }
                
                $order_bns[] = $row['order_bn'];
                $data['is_cod'] = $row['shipping']['is_cod'];
                $data['total_amount'] += $row['total_amount'];
                $data['discount_fee'] += ($row['pmt_order'] - $row['discount']);
                $data['total_goods_amount'] += $row['cost_item'];
                $data['goods_discount_fee'] += $row['pmt_goods'];
                $data['cost_tax'] += $row['cost_tax'];
                
                $data['shop_type'] = $row['shop_type'];
                $data['relate_order_bn'] = $row['relate_order_bn'];
                $data['cert_id'] = $orderextend['cert_id'] ?? '';
                $data['extend_field'] = @json_decode($orderextend['extend_field'] ?? '', 1);
                $data['extend_status'] = $orderextend['extend_status'] ?? '';
                $data['order_bool_type'] = $row['order_bool_type'];
            
                // 合并发货单时，优先取第一个有密文数据的订单，并让它的订单号排在首位
                if(!$encrypt_source_order_bn && isset($orderReceivers['encrypt_source_data']) && $orderReceivers['encrypt_source_data']){
                    $data['encrypt_source_data'] = json_decode($orderReceivers['encrypt_source_data'], true);
                    $encrypt_source_order_bn = $row['order_bn'];
                }
                
                //平台订单信息
                $data['platform_createtime'] = ($row['createtime'] ? date('Y-m-d H:i:s', $row['createtime']) : '');
                $data['platform_download_time'] = ($row['download_time'] ? date('Y-m-d H:i:s', $row['download_time']) : '');
                $data['platform_paytime'] = ($row['paytime'] ? date('Y-m-d H:i:s', $row['paytime']) : '');

                // 如果是唯品会，检测是否有重点检测的明细
                if ($is_jitx && isset($check_lists_map[$order_id])) {
                    $data['quality_check'] = $check_lists_map[$order_id];
                }
                
                //支付时间
                $data['pay_time'] = $data['is_cod'] == 'false' ? date('Y-m-d H:i:s', $row['paytime']) : '';
                
                //店铺信息
                $data['shop_code'] = isset($shopInfo['shop_bn']) ? $shopInfo['shop_bn'] : '';
                $data['shop_name'] = isset($shopInfo['name']) ? $shopInfo['name'] : '';
                $data['node_id'] = $shopInfo['node_id'] ?? '';
                $data['business_category'] = $shopInfo['business_category'] ?? '';

                if(isset($shopInfo['addon']) && $shopInfo['addon']){
                    $data['platform_shop_id'] = $shopInfo['addon']['user_id'];
                    $data['platform_shop_unikey'] = $shopInfo['addon']['unikey'];
                }

                $data['createway'] = $row['createway'];

                if (isset($orderextend['platform_logi_no']) && $orderextend['platform_logi_no']) {
                    $waybill_arr = explode(',', $orderextend['platform_logi_no']);
                    $data['sub_logi_nos'] = count($waybill_arr) > 1 ? array_slice($waybill_arr, 1) : '';
                }

                $data['logistics_costs'] += $row['shipping']['cost_shipping'];
                $receivable = (isset($orderextend['receivable']) && $orderextend['receivable'] > 0) ? $orderextend['receivable'] : $row['total_amount'];
                $data['cod_fee'] += ($row['shipping']['is_cod'] == 'true' ? $receivable : 0.00);

                $data['order_type'] = $row['order_type'];
                $data['order_source'] = $row['order_source'];
                
                //平台订单号(换货生成新订单的场景)
                if(isset($row['platform_order_bn']) && $row['platform_order_bn']){
                    $data['platform_order_bn'] = $row['platform_order_bn'];
                }
                
                if ($member_uname) {
                    $data['member_name'] = $member_uname['uname'];
                }
                $data['member'] = $member_uname;

                if ($row['mark_text']) {
                    $mark_text_arr = @unserialize($row['mark_text']);
                    $tmp_memo = is_array($mark_text_arr) ? array_pop($mark_text_arr) : [];
                }
                $data['memo'] = isset($tmp_memo['op_content']) ? $tmp_memo['op_content'] : '';
                $custom_mark = '';
                if ($row['custom_mark']) {
                    $custom_mark = kernel::single('ome_func')->format_memo($row['custom_mark']);
                    $mark = array_pop($custom_mark);
                    $custom_mark = $mark['op_content'];
                }
                $data['custom_mark'] = $custom_mark;
                
                foreach ($row['order_objects'] as $ok => $obj) {
                    $data['order_objects'][$ok] = array(
                        'order_id'          => $row['order_id'],
                        'order_bn'          => $row['order_bn'],
                        'order_type'        => $row['order_type'],
                        'relate_order_bn'   => $row['relate_order_bn'],
                        'obj_id'            => $obj['obj_id'],
                        'obj_type'          => $obj['obj_type'],
                        'shop_goods_id'     => $obj['shop_goods_id'],
                        'goods_id'          => $obj['goods_id'],
                        'bn'                => $obj['bn'],
                        'name'              => $obj['name'],
                        'quantity'          => $obj['quantity'],
                        'price'             => $obj['price'],
                        'pmt_price'         => $obj['pmt_price'],
                        'sale_price'        => $obj['sale_price'],
                        'divide_order_fee'  => $obj['divide_order_fee'],
                        'part_mjz_discount' => $obj['part_mjz_discount'],
                        'author_id'         => $obj['author_id'],
                        'author_name'       => $obj['author_name'],
                        'oid'               => $obj['oid'],
                    );

                    foreach ($obj['order_items'] as $ik => $item) {
                        $data['order_objects'][$ok]['order_items'][$ik] = array(
                            'order_id'          => $row['order_id'],
                            'obj_id'            => $obj['obj_id'],
                            'item_id'           => $item['item_id'],
                            'item_type'         => $item['item_type'],
                            'product_id'        => $item['product_id'],
                            'bn'                => $item['bn'],
                            'name'              => $item['name'],
                            'nums'              => $item['quantity'],
                            'price'             => $item['price'],
                            'pmt_price'         => $item['pmt_price'],
                            'sale_price'        => $item['sale_price'],
                            'divide_order_fee'  => $item['divide_order_fee'],
                            'part_mjz_discount' => $item['part_mjz_discount'],
                            'oid'               => $obj['oid'],
                        );
                        if ($row['is_tax'] == 'true' && $item['sale_price'] > 0) {
                            $invoice_items[] = array(
                                'item_name' => $item['name'],
                                'spec'      => $item['spec'],
                                'nums'      => $item['quantity'],
                                'price'     => $item['sale_price'],
                            );
                        }
                    }
                }

                unset($row);
            }
            
            // 第一个订单号必须与密文数据字段值：encrypt_source_data保持一致；
            //@todo：解决当多个订单合并发货时，第一个订单号可能不是加密后的订单号
            if ($encrypt_source_order_bn && isset($order_bns[0]) && $order_bns[0] != $encrypt_source_order_bn) {
                $encrypt_order_bns = array($encrypt_source_order_bn);
                foreach ($order_bns as $order_bn) {
                    if ($order_bn != $encrypt_source_order_bn) {
                        $encrypt_order_bns[] = $order_bn;
                    }
                }
                $order_bns = $encrypt_order_bns;
            }
            
            //订单号(合并发货单以'|'竖线分隔多个订单号)
            $data['order_bn'] = implode('|', $order_bns);
            // 如果是唯品会，如果是合单，取第一个订单号，保证oms获取唯品会接口和给到wms的订单号一致
            if (kernel::single('ome_order_bool_type')->isJITX($data['order_bool_type']) && count($order_bns)>1) {
                $data['is_vop_merge'] = true;
                $data['order_bn']     = $order_bns[0];
            }
        }
        if ($invoice_items) {
            $data['invoice']['invoice_items'] = $invoice_items;
        }
        if ($data['pause'] == 'true') {
            $data['pause'] = 'true';
        }
        $data['outer_delivery_bn'] = $data['delivery_bn'];
        //是否预打包
        if(kernel::single('ome_bill_label_delivery')->isPrepackage($delivery_id)){
            $data['prepackage'] = 'true';
        }
        // 订单支付单
        if ($data['delivery_order']) {
            $paymentMdl       = app::get('ome')->model('payments');
            $data['payments'] = $paymentMdl->getList('payment_bn,trade_no,paymethod', ['order_id' => array_column($data['delivery_order'], 'order_id')]);
        }

        unset($data['delivery_bn'], $data['delivery_order']);

        return $data;
    }

}
