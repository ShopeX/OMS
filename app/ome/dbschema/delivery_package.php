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

$db['delivery_package'] = array (
    'columns' => array (
        'package_id' => array (
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'editable' => false,
            'extra' => 'auto_increment',
            'order' => 1,
        ),
        'delivery_id' => array (
            'type' => 'table:delivery@ome',
            'required' => true,
            'editable' => false,
            'label' => '发货单号',
            'order' => 10,
        ),
        'order_id' => array(
            'type' => 'varchar(500)',
            'label' => '订单ID',
            'default' => '',
            'comment' => '多个订单时英文逗号分隔',
            'editable' => false,
            'width' => 120,
            'in_list' => false,
            'default_in_list' => false,
        ),
        'pay_status' => array(
            'type' => array(
                0 => '未支付',
                1 => '已支付',
                2 => '处理中',
                3 => '部分付款',
                4 => '部分退款',
                5 => '全额退款',
                6 => '退款申请中',
                7 => '退款中',
                8 => '支付中',
            ),
            'default' => '0',
            'required' => true,
            'label' => '付款状态',
            'width' => 75,
            'editable' => false,
            'filtertype' => 'yes',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'comment' => '付款状态,可选值:0(未支付),1(已支付),2(处理中),3(部分付款),4(部分退款),5(全额退款),6(退款申请中),7(退款中),8(支付中)',
            'order' => 9,
        ),
        'package_bn' => array (
            'type' => 'varchar(50)',
            'label' => '京东子单号',
            'editable' => false,
            'filtertype' => 'textarea',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 11,
        ),
        'logi_bn' => array (
            'type' => 'varchar(50)',
            'label' => '物流公司编码',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 70,
        ),
        'logi_no' => array (
            'type' => 'varchar(50)',
            'label' => '物流单号',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 71,
        ),
        'product_id' => array (
            'type' => 'table:products@ome',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'order' => 30,
        ),
        'bn' => array (
            'type' => 'varchar(50)',
            'editable' => false,
            'label' => '基础物料号',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 31,
        ),
        'order_item_id'    => array(
            'type'     => 'varchar(500)',
            'default'  => '',
            'required' => false,
            'editable' => false,
            'comment'  => '订单明细ID(ome_order_items.item_id)，多条时英文逗号分隔',
        ),
        'outer_sku' => array (
            'type' => 'varchar(50)',
            'label' => '外部sku',
            'editable' => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 32,
        ),
        'main_sku_id' => array (
            'type' => 'varchar(50)',
            'label' => '关联主sku',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 35,
        ),
        'status' => array (
            'type' => 'varchar(30)',
            'label' => '包裹状态',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'order' => 12,
        ),
        'shipping_status' => array (
            'type' => 'varchar(30)',
            'label' => '配送状态',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 13,
        ),
        'number' => array (
            'type' => 'int',
            'label' => '数量',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 60,
        ),
        'create_time' => array (
            'type' => 'time',
            'label' => 'WMS包裹创建时间',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 99,
            'filtertype' => 'time',
            'filterdefault' => true,
        ),
        'delivery_time' => array (
            'type' => 'time',
            'label' => '发货时间',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 99,
            'filtertype' => 'time',
            'filterdefault' => true,
        ),
        'sign_time' => array (
            'type' => 'time',
            'label' => '签收时间',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 99,
        ),
        'is_wms_gift' => array(
            'type' => 'bool',
            'default' => 'false',
            'label' => '是否WMS赠品',
            'editable' => false,
            'filtertype' => 'normal',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 70,
        ),
        'apply_num' => array (
            'type' => 'number',
            'default' => 0,
            'required' => true,
            'editable' => false,
            'label' => '申请退货数量',
            'in_list' => true,
            'default_in_list' => false,
            'order' => 80,
        ),
        'return_num' => array (
            'type' => 'number',
            'default' => 0,
            'editable' => false,
            'label' => '已退货数量',
            'in_list' => true,
            'default_in_list' => false,
            'order' => 81,
        ),
        'push_kepler' => array(
            'type' => array(
                'none' => '未回写',
                'fail' => '回写失败',
                'succ' => '回写成功',
                'retry' => '重试推送',
            ),
            'default' => 'none',
            'label' => '推送云交易',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 99,
        ),
    ),
    'index' => array (
        'in_order_id' => array (
            'columns' => array (
                0 => 'order_id',
            ),
        ),
        'in_package_bn' => array (
            'columns' => array (
                0 => 'package_bn',
            ),
        ),
        'in_status' => array (
            'columns' => array (
                0 => 'status',
            ),
        ),
        'ind_create_time' => array(
            'columns' => array(
                0 => 'create_time',
            ),
        ),
        'ind_delivery_time' => array(
            'columns' => array(
                0 => 'delivery_time',
            ),
        ),
        'ind_logi_no' => array(
            'columns' => array(
                0 => 'logi_no',
            ),
        ),
        'index_push_kepler' => array(
            'columns' => array(
                0 => 'push_kepler',
            ),
        ),
    ),
    'comment' => '发货包裹表',
    'engine' => 'innodb',
    'version' => '$Rev: 41996 $',
);