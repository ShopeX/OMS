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

class openapi_api_function_v1_sales extends openapi_api_function_abstract implements openapi_api_function_interface
{

    public function getList($params, &$code, &$sub_msg)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        $start_time        = strtotime($params['start_time']);
        $end_time          = strtotime($params['end_time']);
        $start_up_time        = strtotime($params['modified_start']);
        $end_up_time          = strtotime($params['modified_end']);
        $page_no           = intval($params['page_no']) > 0 ? intval($params['page_no']) : 1;
        $limit             = (intval($params['page_size']) > 1000 || intval($params['page_size']) <= 0) ? 100 : intval($params['page_size']);
        
        //filter
        $filter['shop_bn'] = $params['shop_bn'];
        if($params['order_bn']){
            $filter['order_bn'] = trim($params['order_bn']);
            $filter['order_bn'] = str_replace(array('"', "'"), '', $filter['order_bn']);
        }
        if($start_time && $end_time) {
            $filter['sale_time'] = array($start_time, $end_time);
        }
        if($start_up_time && $end_up_time) {
            $filter['up_time'] = array($start_up_time, $end_up_time);
        }
        if(empty($filter['sale_time']) && empty($filter['up_time'])) {
            $sub_msg = '请输入查询时间';
            return false;
        }
        //page
        if ($page_no == 1) {
            $offset = 0;
        } else {
            $offset = ($page_no - 1) * $limit;
        }

        $original_sales_data = kernel::single('openapi_data_original_sales')->getList($filter, $offset, $limit);

        $sale_arr = array();
        foreach ($original_sales_data['lists'] as $k => $sale) {
            $sales_arr[$k]['shop_code']   = $this->charFilter($sale['shop_bn']);
            $sales_arr[$k]['shop_name']   = $this->charFilter($sale['shop_name']);
            $sales_arr[$k]['order_no']    = $this->charFilter($sale['order_bn']);
            $sales_arr[$k]['order_type']    = $this->charFilter($sale['order_type']);
            $sales_arr[$k]['member_name'] = $this->charFilter($sale['member_name']);
            $sales_arr[$k]['sale_no']     = $this->charFilter($sale['sale_bn']);
            $sales_arr[$k]['pay_method']  = $this->charFilter($sale['payment']);
            $sales_arr[$k]['sale_time']   = date('Y-m-d H:i:s', $sale['sale_time']);

            //订单下单订单日期为空,则输出空值
            $sales_arr[$k]['order_create_time'] = (empty($sale['order_create_time']) ? '' : date('Y-m-d H:i:s', $sale['order_create_time']));

            $sales_arr[$k]['pay_time']          = date('Y-m-d H:i:s', $sale['paytime']);
            $sales_arr[$k]['ship_time']         = date('Y-m-d H:i:s', $sale['ship_time']);
            $sales_arr[$k]['order_check_op']    = $this->charFilter($sale['order_check_name']);
            $sales_arr[$k]['order_check_time']  = date('Y-m-d H:i:s', $sale['order_check_time']);
            $sales_arr[$k]['goods_amount']      = $sale['total_amount'];
            $sales_arr[$k]['freight_amount']    = $sale['cost_freight'];
            $sales_arr[$k]['additional_amount'] = $sale['additional_costs'];
            $sales_arr[$k]['has_tax']           = $sale['is_tax'] == 'false' ? '否' : '是';
            $sales_arr[$k]['pmt_amount']        = $sale['discount'];
            $sales_arr[$k]['sale_amount']       = $sale['sale_amount'];
            $sales_arr[$k]['logi_name']         = $this->charFilter($sale['logi_name']);
            $sales_arr[$k]['logi_code']         = $this->charFilter($sale['logi_code']);
            $sales_arr[$k]['logi_no']           = $this->charFilter($sale['logi_no']);
            $sales_arr[$k]['branch_name']       = $this->charFilter($sale['branch_name']);
            $sales_arr[$k]['branch_bn']         = $this->charFilter($sale['branch_bn']);
            $sales_arr[$k]['delivery_no']       = $this->charFilter($sale['delivery_bn']);
            $sales_arr[$k]['consignee']         = $this->charFilter($sale['ship_name']);
            $sales_arr[$k]['consignee_area']    = $sale['ship_area'];
            $sales_arr[$k]['consignee_addr']    = $this->charFilter($sale['ship_addr']);
            $sales_arr[$k]['consignee_zip']     = $this->charFilter($sale['ship_zip']);
            $sales_arr[$k]['consignee_tel']     = $this->charFilter($sale['ship_tel']);
            $sales_arr[$k]['consignee_mobile']  = $this->charFilter($sale['ship_mobile']);
            $sales_arr[$k]['consignee_email']   = $this->charFilter($sale['ship_email']);

            //delivery_cost_actual,order_memo
            $sales_arr[$k]['weight']               = $sale['weight'];
            $sales_arr[$k]['delivery_cost_actual'] = $sale['delivery_cost_actual'];
            $sales_arr[$k]['order_memo']           = $this->charFilter($sale['order_memo']);
            $sales_arr[$k]['tax_title']            = $this->charFilter($sale['tax_title']);
            //relate_order_bn
            $sales_arr[$k]['relate_order_bn'] = $sale['relate_order_bn'];
            $sales_arr[$k]['settlement_amount'] = $sale['settlement_amount'];//结算金额
            $sales_arr[$k]['platform_amount']   = $sale['platform_amount'];//平台承担金额
            $sales_arr[$k]['order_source']      = $sale['order_source'];
            // selling agent info
            $sales_arr[$k]['agent_info'] = $sale['agent_info'];
            foreach ($sale['sale_items'] as $key => $sale_item) {
                $sales_arr[$k]['sale_items'][$key]['item_id'] = $sale_item['item_id'];
                $sales_arr[$k]['sale_items'][$key]['bn']            = $sale_item['bn'];
                $sales_arr[$k]['sale_items'][$key]['name']          = $this->charFilter($sale_item['name']);
                $sales_arr[$k]['sale_items'][$key]['spec_name']     = $this->charFilter($sale_item['spec_name']);
                $sales_arr[$k]['sale_items'][$key]['barcode']       = $sale_item['barcode'];
                $sales_arr[$k]['sale_items'][$key]['price']         = $sale_item['price'];
                $sales_arr[$k]['sale_items'][$key]['nums']          = $sale_item['nums'];
                $sales_arr[$k]['sale_items'][$key]['pmt_price']     = $sale_item['pmt_price'];
                $sales_arr[$k]['sale_items'][$key]['sale_price']    = $sale_item['sale_price'];
                $sales_arr[$k]['sale_items'][$key]['apportion_pmt'] = $sale_item['apportion_pmt'];
                $sales_arr[$k]['sale_items'][$key]['sales_amount']  = $sale_item['sales_amount'];
                $sales_arr[$k]['sale_items'][$key]['cost']          = $sale_item['cost'];
                $sales_arr[$k]['sale_items'][$key]['cost_amount']   = $sale_item['cost_amount'];
                $sales_arr[$k]['sale_items'][$key]['order_item_id'] = $sale_item['order_item_id'];
                $sales_arr[$k]['sale_items'][$key]['item_type']     = $this->charFilter($sale_item['item_type']);

                //关联销售物料
                $sales_arr[$k]['sale_items'][$key]['type_name']           = $this->charFilter($sale_item['type_name']);
                $sales_arr[$k]['sale_items'][$key]['sales_material_bn']   = $this->charFilter($sale_item['sales_material_bn']);
                $sales_arr[$k]['sale_items'][$key]['sales_material_name'] = $this->charFilter($sale_item['sales_material_name']);

                //商品采购成本价
                $sales_arr[$k]['sale_items'][$key]['cost_amount'] = $sale_item['cost_amount'];
                
                //基础物料价格
                $sales_arr[$k]['sale_items'][$key]['retail_price'] = $sale_item['retail_price'];
                
                //other
                $sales_arr[$k]['sale_items'][$key]['brand_code'] = $sale_item['brand_code']; //物料品牌
                $sales_arr[$k]['sale_items'][$key]['cat_name'] = $this->charFilter($sale_item['cat_name']); //物料分类
                $sales_arr[$k]['sale_items'][$key]['goods_type'] = $this->charFilter($sale_item['goods_type']); //物料属性
                $sales_arr[$k]['sale_items'][$key]['shop_goods_id'] = $sale_item['shop_goods_id'];//平台商品ID
                $sales_arr[$k]['sale_items'][$key]['shop_product_id'] = $sale_item['shop_product_id'];//平台SkuID
                $sales_arr[$k]['sale_items'][$key]['settlement_amount'] = $sale_item['settlement_amount'];//结算金额
                $sales_arr[$k]['sale_items'][$key]['platform_amount']   = $sale_item['platform_amount'];//平台承担金额
    
                // 如果 $i_v 是 null，则返回空字符串；否则返回原始值
                $sales_arr[$k]['sale_items'][$key] = array_map(function($i_v) {return is_null($i_v) ? '' : $i_v;}, $sales_arr[$k]['sale_items'][$key]);
            }
            // 如果 $v 是 null，则返回空字符串；否则返回原始值
            $sales_arr[$k] = array_map(function($v) {return is_null($v) ? '' : $v;}, $sales_arr[$k]);
        }

        unset($original_sales_data['lists']);
        $original_sales_data['lists'] = $sales_arr;

        return $original_sales_data;
    }

    public function add($params, &$code, &$sub_msg)
    {
        //==
    }

    public function getSalesAmount($params, &$code, &$sub_msg)
    {
        $start_time = strtotime($params['start_time']);
        $end_time   = strtotime($params['end_time']);
        $page_no    = intval($params['page_no']) > 0 ? intval($params['page_no']) : 1;
        $limit      = (intval($params['page_size']) > 100 || intval($params['page_size']) <= 0) ? 100 : intval($params['page_size']);
        if ($page_no == 1) {
            $offset = 0;
        } else {
            $offset = ($page_no - 1) * $limit;
        }

        $shop_bn = array();
        if ($params['shop_bn']) {
            $all_shop_bn = explode('#', $params['shop_bn']);
            foreach ($all_shop_bn as $v) {
                if (trim($v)) {
                    $shop_bn[] = trim($v);
                }
            }
        }
        $original_sales_data = kernel::single('openapi_data_original_sales')->SalesAmount($start_time, $end_time, $offset, $limit, $shop_bn);
        return $original_sales_data;
    }

    /**
     * 销售发货明细
     *
     * @return Array
     * @author CP
     * @version 4.3.9 2021-08-14T10:39:02+08:00
     **/
    public function getDeliveryList($params, &$code, &$sub_msg)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        $deliveryStartTime = strtotime($params['start_time'] ?? '');
        $deliveryEndTime   = strtotime($params['end_time'] ?? '');
        $modifiedStartTime = strtotime($params['modified_start'] ?? '');
        $modifiedEndTime   = strtotime($params['modified_end'] ?? '');

        $pageNo   = intval($params['page_no']) > 0 ? intval($params['page_no']) : 1;
        $pageSize = (intval($params['page_size']) > 1000 || intval($params['page_size']) <= 0) ? 100 : intval($params['page_size']);

        if (($deliveryStartTime && !$deliveryEndTime) || (!$deliveryStartTime && $deliveryEndTime)) {
            $sub_msg = '发货开始时间和结束时间必须同时传入';
            return false;
        }
        if (($modifiedStartTime && !$modifiedEndTime) || (!$modifiedStartTime && $modifiedEndTime)) {
            $sub_msg = '修改开始时间和结束时间必须同时传入';
            return false;
        }
        if ($modifiedStartTime && $modifiedEndTime && $modifiedStartTime >= $modifiedEndTime) {
            $sub_msg = '修改开始时间必须早于结束时间';
            return false;
        }
        if ($modifiedStartTime && $modifiedEndTime && ($modifiedEndTime - $modifiedStartTime) > 86400) {
            $sub_msg = '修改时间跨度不能超过24小时';
            return false;
        }
        if (!$deliveryStartTime && !$modifiedStartTime) {
            $sub_msg = '发货时间范围和修改时间范围至少传入一组';
            return false;
        }

        $filter = [];
        if ($deliveryStartTime && $deliveryEndTime) {
            $filter['delivery_time|bthan'] = $deliveryStartTime;
            $filter['delivery_time|lthan'] = $deliveryEndTime;
        }
        if ($modifiedStartTime && $modifiedEndTime) {
            // 发货销售主单没有更新时间。通过明细 up_time 反查主单，既支持历史单据重拉，
            // 又不需要篡改 delivery_time 这一业务事实字段。
            $filter['filter_sql'] = 'delivery_id IN ('
                . 'SELECT DISTINCT delivery_id FROM sdb_sales_delivery_order_item '
                . 'WHERE up_time >= "' . date('Y-m-d H:i:s', $modifiedStartTime) . '" '
                . 'AND up_time < "' . date('Y-m-d H:i:s', $modifiedEndTime) . '"'
                . ')';
        }

        if ($params['delivery_bn']) {
            $filter['delivery_bn'] = $params['delivery_bn'];
        }

        if ($params['branch_bn']) {
            $branchMdl = app::get('ome')->model('branch');
            $branch    = $branchMdl->db_dump(['branch_bn' => $params['branch_bn'], 'check_permission' => 'false'], 'branch_id');

            $filter['branch_id'] = (int) $branch['branch_id'];
        }

        if ($params['shop_bn']) {
            $shopMdl           = app::get('ome')->model('shop');
            $shop              = $shopMdl->db_dump(['shop_bn' => $params['shop_bn']], 'shop_id');
            $filter['shop_id'] = (string) $shop['shop_id'];
        }

        if ($params['order_bn']) {
            $orderMdl  = app::get('ome')->model('orders');
            $orderList = $orderMdl->getList('order_id', ['order_bn' => explode(',', $params['order_bn'])]);

            $order_id          = $orderList ? array_column($orderList, 'order_id') : [0];
            $deliveryOrderMdl  = app::get('ome')->model('delivery_order');
            $deliveryOrderList = $deliveryOrderMdl->getList('delivery_id', ['order_id' => $order_id]);

            $filter['delivery_id'] = $deliveryOrderList ? array_column($deliveryOrderList, 'delivery_id') : [0];
        }

        $data = kernel::single('openapi_data_original_sales')->getDeliveryList($filter, ($pageNo - 1) * $pageSize, $pageSize);
        foreach ($data['lists'] as $k => $v) {
            $data['lists'][$k]['ship_name'] = $this->charFilter($v['ship_name']);
            $data['lists'][$k]['ship_mobile'] = $this->charFilter($v['ship_mobile']);
            $data['lists'][$k]['ship_email'] = $this->charFilter($v['ship_email']);
            $data['lists'][$k]['ship_addr'] = $this->charFilter($v['ship_addr']);
        }
        return $data;
    }

    /**
     * 获取JIT销售单
     *
     * @return array
     **/
    public function getGxList($params, &$code, &$sub_msg)
    {
        $start_modified       = $params['start_time'];
        $end_modified         = $params['end_time'];
        $page_no          = intval($params['page_no']) > 0 ? intval($params['page_no']) : 1;
        $limit            = (intval($params['page_size']) > 1000 || intval($params['page_size']) <= 0) ? 100 : intval($params['page_size']);
        
        $filter = [];

        $filter['up_time|betweenstr'] = [$start_modified, $end_modified];

        $offset = ($page_no - 1) * $limit;

        $originalData = kernel::single('openapi_data_original_sales')->getGxList($filter, $offset, $limit);
        

        $data = [
            'count' => $originalData['count'],
        ];
        foreach ($originalData['lists'] as $k => $v) {
            
            $lists = [
                'sale_bn' => $v['sale_bn'],
                'bill_bn' => $v['bill_bn'],
                'bill_type' => $v['bill_type'],
                'shop_bn' => $v['shop_bn'],
                'shop_name' => $v['shop_name'],
                'sale_time' => date('Y-m-d H:i:s',$v['sale_time']),
                'ship_time' => date('Y-m-d H:i:s',$v['ship_time']),
                'original_bn' => $v['original_bn'],
                'branch_bn' => $v['branch_bn'],
                'branch_name' => $v['branch_name'],
                'logi_name' => $v['logi_name'],
                'logi_code' => $v['logi_code'],
                'logi_no' => $v['logi_no'],
                'order_bn' => $v['order_bn'],
                'at_time' => $v['at_time'],
                'up_time' => $v['up_time'],
                'total_amount' => $v['total_amount'],
                'settlement_amount' => $v['settlement_amount'],
                'total_sale_price'  => $v['total_sale_price'],
            ];
            
            $items = [];
            foreach ($v['items'] as $item) {
                $items[] = [
                    'item_id' => $item['id'],
                    'material_bn' => $item['material_bn'],
                    'barcode' => $item['barcode'],
                    'material_name' => $this->charFilter($item['material_name']),
                    'nums' => $item['nums'],
                    'retail_price' => $item['retail_price'],
                    'price' => $item['price'],
                    'amount' => $item['amount'],
                    'settlement_amount' => $item['settlement_amount'],
                    'sale_price' => $item['sale_price'],
                ];
            }

            $lists['items'] = $items;

            $data['lists'][] = $lists;
        }
        
        return $data;
    }
}
