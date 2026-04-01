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

class openapi_data_original_orders
{
    /**
     * 获取List
     * @param mixed $filter filter
     * @param mixed $start_time start_time
     * @param mixed $end_time end_time
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @param mixed $time_select time_select
     * @return mixed 返回结果
     */
    public function getList($filter, $start_time, $end_time, $offset = 0, $limit = 100, $time_select = '')
    {
        if (empty($start_time) || empty($end_time)) {
            return false;
        }

        $formatFilter = kernel::single('openapi_format_abstract');
        $orderObj     = app::get('ome')->model('orders');
        $itemObj      = app::get('ome')->model('order_items');
        $objectObj      = app::get('ome')->model('order_objects');
        $pmtObj       = app::get('ome')->model('order_pmt');
        $shopObj      = app::get('ome')->model('shop');
        $memberObj    = app::get('ome')->model('members');
        $codebaseObj  = app::get('material')->model('codebase');
        $refundObj    = app::get('ome')->model('refunds');
        $orderExtMdl  = app::get('ome')->model('order_extend');

        //条件
        $sqlstr = '';
        if ($filter['shop_bn']) {
            $shop_bn = explode('#', $filter['shop_bn']);
            $shop_id = $shopObj->getlist('shop_id', array('shop_bn|in' => $shop_bn));
            foreach ($shop_id as $value) {
                $shop_ids[] = $value['shop_id'];
            }
            $shopIds = "'" . implode("','", $shop_ids) . "'";
            $sqlstr .= " AND shop_id in(" . $shopIds . ")";
        }
        if ($filter['ship_status']) {
            $sqlstr .= " AND ship_status in(" . $filter['ship_status'] . ")";
        }
        if ($filter['pay_status']) {
            $sqlstr .= " AND pay_status in(" . $filter['pay_status'] . ")";
        }
        if ($filter['status']) {
            $sqlstr .= " AND status in('" . implode("','", explode(',', $filter['status'])) . "')";
        }

        //按订单号
        if ($filter['order_bn']) {
            $order_bn = str_replace('，',',',$filter['order_bn']);
            $order_bn = str_replace(' ','',$order_bn);
            $order_bn = explode(',',$order_bn);
            if(count($order_bn) > 1){
                $sqlstr .= " and order_bn in ("."'".join("','",$order_bn)."'".")";
            }else{
                $sqlstr .= " AND order_bn='". $filter['order_bn'] ."'";
            }

        }
        
        //过滤失败订单
        $sqlstr .= " AND is_fail='false'";

        //订单数量
        if ($time_select == 'createtime') {
            $sql = "SELECT count(*) as _count FROM sdb_ome_orders WHERE createtime >=" . $start_time . " AND createtime <" . $end_time . $sqlstr;
        } else {
            $sql = "SELECT count(*) as _count FROM sdb_ome_orders WHERE last_modified >=" . $start_time . " AND last_modified <" . $end_time . $sqlstr;
        }

        $countList = $orderObj->db->selectrow($sql);
        $cursor_id = 0;
        $has_more = false;
        if (intval($countList['_count']) > 0) {
            //店铺列表
            $shopInfos = array();
            $shop_arr  = $shopObj->getList('shop_id,shop_bn,name', array(), 0, -1);
            foreach ($shop_arr as $k => $shop) {
                $shopInfos[$shop['shop_id']] = $shop;
            }

            //订单列表
            $orderIds  = array();
            $orderList = array();
            if ($time_select == 'createtime') {
                $sql = "SELECT * FROM sdb_ome_orders WHERE createtime>=" . $start_time . " AND createtime<" . $end_time . $sqlstr . " ORDER BY createtime ASC limit " . $offset . "," . $limit;
            } else {
                if (isset($filter['cursor_id'])) {
                    $sqlstr .= ($filter['cursor_id'] > 0) ? " AND order_id > '" . $filter['cursor_id'] . "'" : '';
                    $sql = "SELECT * FROM sdb_ome_orders FORCE INDEX (ind_last_modified) WHERE last_modified>=" . $start_time . " AND last_modified<" . $end_time . $sqlstr . " ORDER BY order_id ASC limit " . $limit;
                } else {
                    $sql = "SELECT * FROM sdb_ome_orders WHERE last_modified>=" . $start_time . " AND last_modified<" . $end_time . $sqlstr . "  limit " . $offset . "," . $limit;
                }
            }
            $dataList = $orderObj->db->select($sql);
            if ($dataList) {
                $lastOrder = end($dataList);
                $cursor_id = $lastOrder['order_id'];
                $has_more  = count($dataList) >= $limit;
            }
            
            //获取会员信息
            $memberIds  = array_column($dataList, 'member_id');
            $memberList = $memberObj->getList('name,member_id,buyer_open_uid', ['member_id' => $memberIds]);
            $memberList = array_column($memberList, null, 'member_id');
            
            foreach ($dataList as $key => $val) {
                if ($val['is_fail'] == 'false') {
                    $order_id   = $val['order_id'];
                    $orderIds[] = $order_id;

                    $orderList[$order_id]              = $val;
                    $orderList[$order_id]['shop_bn']   = $shopInfos[$val['shop_id']]['shop_bn'];
                    $orderList[$order_id]['shop_name'] = $shopInfos[$val['shop_id']]['name'];

                    //会员信息
                    $orderList[$order_id]['member_name'] = isset($memberList[$val['member_id']]) ? $memberList[$val['member_id']]['name'] : '';
                    $orderList[$order_id]['buyer_open_uid'] = isset($memberList[$val['member_id']]) ? $memberList[$val['member_id']]['buyer_open_uid'] : '';
                }
            }

            //订单明细列表
            $itemFilter = array('order_id' => $orderIds, 'delete' => 'false');
            if ($filter['close_item_req'] == 'true') {
                unset($itemFilter['delete']);
            }

            $itemList = $itemObj->getList('*', $itemFilter);

            foreach ($itemList as $key => $val) {
                $order_id = $val['order_id'];

                $barcodeInfo    = $codebaseObj->dump(array('bm_id' => $val['product_id']), 'code');
                $val['barcode'] = $barcodeInfo['code'];

                $orderList[$order_id]['items'][] = $val;
            }

            //归档订单商品
            $objectList =$objectObj->getList('*', array('order_id' => $orderIds));
            foreach ($objectList as $key => $val) {
                $order_id = $val['order_id'];
                unset($val['order_id']);
                $orderList[$order_id]['order_objects'][] = $val;
            }


            //订单优惠方案
            $pmtList = $pmtObj->getList('*', array('order_id' => $orderIds));
            foreach ($pmtList as $key => $val) {
                $order_id = $val['order_id'];
                if ($val) {
                    $orderList[$order_id]['pmts'][] = array_map(function($v) {return is_null($v) ? '' : $v;}, $val);
                }
            }

            $refundList = $refundObj->getList('*', array('order_id' => $orderIds));
            foreach ($refundList as $key => $val) {
                $order_id = $val['order_id'];
                if ($val) {
                    $orderList[$order_id]['refund'][] = array_map(function($v) {return is_null($v) ? '' : $v;}, $val);
                }
            }
            unset($shopInfos, $dataList, $itemList, $pmtList, $refundList);

            return array('lists' => $orderList, 'count' => $countList['_count'],'cursor_id'=>$cursor_id,'has_more'=>$has_more);
        } else {
            return array(
                'lists' => array(),
                'count' => 0,
                'cursor_id' => $cursor_id,//最后一条唯一ID 为order_id
                'has_more' => $has_more,//是否结束 true / false
            );
        }
    }

    /**
     * 获取CouponList
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @return mixed 返回结果
     */
    public function getCouponList($filter, $offset = 0, $limit = 100)
    {
        if ($filter['order_bn']) {
            $order_bn = str_replace('，',',',$filter['order_bn']);
            $order_bn = str_replace(' ','',$order_bn);
            $order_bn = explode(',',$order_bn);
            $sqlstr = 'select order_id from sdb_ome_orders where 1';
            if(count($order_bn) > 1){
                $sqlstr .= " and order_bn in ("."'".join("','",$order_bn)."'".")";
            }else{
                $sqlstr .= " AND order_bn='". $filter['order_bn'] ."'";
            }
            $orders = kernel::database()->select($sqlstr);
            $filter['order_id'] = [-1];
            foreach ($orders as $key => $value) {
                $filter['order_id'][] = $value['order_id'];
            }
            unset($filter['order_bn']);
        }
        $couObj = app::get('ome')->model('order_coupon');
        $countNum = $couObj->count($filter);
        if ($countNum == 0) {
            return array(
                'lists' => array(),
                'count' => 0,
            );
        }
        $couponList = $couObj->getList('*', $filter, $offset, $limit);
        $orderObj = app::get('ome')->model('orders');
        $orderIds = array_column($couponList, 'order_id');
        $orderList = $orderObj->getList('order_bn,order_id', ['order_id' => $orderIds]);
        $orderList = array_column($orderList, 'order_bn', 'order_id');
        $lists = [];
        foreach ($couponList as $key => $value) {
            $lists[] = [
                'item_id' => $value['id'],
                'order_bn' => $orderList[$value['order_id']],
                'type' => $value['type'],
                'type_name' => $value['type_name'],
                'coupon_type' => $couObj->schema['columns']['coupon_type']['type'][$value['coupon_type']],
                'num' => $value['num'],
                'material_name' => $value['material_name'],
                'material_bn' => $value['material_bn'],
                'price' => $value['amount'],
                'amount' => $value['total_amount'],
                'oid' => $value['oid'],
                'pay_time' => $value['pay_time'],
                'shop_type' => $value['shop_type'],
            ];
        }
        return [
            'lists' => $lists,
            'count' => $countNum,
        ];
    }

    /**
     * 获取PmtList
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @return mixed 返回结果
     */
    public function getPmtList($filter, $offset = 0, $limit = 100)
    {
        if ($filter['order_bn']) {
            $order_bn = str_replace('，',',',$filter['order_bn']);
            unset($filter['order_bn']);
            $order_bn = str_replace(' ','',$order_bn);
            $order_bn = explode(',',$order_bn);
            $sqlstr = 'select order_id from sdb_ome_orders where 1';
            if(count($order_bn) > 1){
                $sqlstr .= " and order_bn in ("."'".join("','",$order_bn)."'".")";
            }else{
                $sqlstr .= " AND order_bn='". $filter['order_bn'] ."'";
            }
            $orders = kernel::database()->select($sqlstr);
            $filter['order_id'] = [-1];
            foreach ($orders as $key => $value) {
                $filter['order_id'][] = $value['order_id'];
            }
        }
        $pmtObj = app::get('ome')->model('order_pmt');
        $countNum = $pmtObj->count($filter);
        if ($countNum == 0) {
            return array(
                'lists' => array(),
                'count' => 0,
            );
        }
        $rows = $pmtObj->getList('*', $filter, $offset, $limit);
        $orderObj = app::get('ome')->model('orders');
        $orderIds = array_column($rows, 'order_id');
        $orderList = $orderObj->getList('order_bn,order_id', ['order_id' => $orderIds]);
        $orderList = array_column($orderList, 'order_bn', 'order_id');
        $lists = [];
        foreach ($rows as $key => $value) {
            $lists[] = [
                'item_id' => $value['id'],
                'order_bn' => $orderList[$value['order_id']],
                'pmt_describe' => $value['pmt_describe'],
                'pmt_amount' => $value['pmt_amount'],
                'coupon_id' => $value['coupon_id'],
            ];
        }
        return [
            'lists' => $lists,
            'count' => $countNum,
        ];
    }
}
