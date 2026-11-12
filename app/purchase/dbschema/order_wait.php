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

$db['order_wait'] = array(
    'columns' => array(
        'ow_id' =>
            array(
                'type' => 'number',
                'required' => true,
                'pkey' => true,
                'extra' => 'auto_increment',
                'editable' => false,
                'order' => 1,
            ),
        'order_bn' =>
            array(
                'type' => 'varchar(32)',
                'required' => true,
                'label' => '订单号',
                'width' => 140,
                'editable' => false,
                'in_list' => true,
                'default_in_list' => true,
                'searchtype' => 'nequal',
                'filterdefault' => true,
                'filtertype' => 'normal',
                'order' => 2,
            ),
        'shop_id' =>
            array(
                'type' => 'table:shop@ome',
                'label' => '来源店铺',
                'editable' => false,
                'required' => true,
                'in_list' => true,
                'default_in_list' => true,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'order' => 5,
            ),
        'shop_type' =>
            array(
                'type' => 'varchar(50)',
                'label' => '来源店铺类型',
                'editable' => false,
                'default' => '',
            ),
        'available_warehouses' =>
            array(
                'type' => 'varchar(128)',
                'label' => '可用仓库编码',
                'required' => true,
                'in_list' => true,
                'default_in_list' => true,
                'width' => 100,
                'editable' => false,
                'order' => 10,
            ),
        'buyer_address' =>
            array(
                'type' => 'varchar(128)',
                'label' => '收件人详细地址',
                'default' => '',
                'in_list' => true,
                'default_in_list' => true,
                'width' => 100,
                'editable' => false,
                'order' => 20,
            ),
        'vendor_id' =>
            array(
                'type' => 'number',
                'label' => '供应商ID',
                'default' => 0,
                'width' => 100,
                'editable' => false,
                'order' => 20,
            ),
        'vendor_name' =>
            array(
                'type' => 'varchar(64)',
                'label' => '供应商名称',
                'default' => '',
                //'in_list' => true,
                //'default_in_list' => true,
                'width' => 100,
                'editable' => false,
                'order' => 30,
            ),
        'status' =>
            array(
                'type' => 'varchar(32)',
                'label' => '状态',
                'default' => '',
                'in_list' => true,
                'default_in_list' => true,
                'width' => 100,
                'editable' => false,
                'order' => 40,
            ),
        'status_remark' =>
            array(
                'type' => 'text',
                'label' => '状态备注',
                'default' => '',
                'in_list' => true,
                'default_in_list' => true,
                'width' => 100,
                'editable' => false,
                'order' => 50,
            ),
        'branch_id' =>
            array(
                'type' => 'number',
                'label' => '选定仓库ID',
                'default' => 0,
                'width' => 100,
                'editable' => false,
                'order' => 60,
            ),
        'warehouse' =>
            array(
                'type' => 'varchar(32)',
                'label' => '选定仓库编码',
                'default' => '',
                'in_list' => true,
                'default_in_list' => true,
                'width' => 100,
                'editable' => false,
                'order' => 60,
            ),
        'create_time' =>
            array(
                'type' => 'time',
                'label' => '创建时间',
                'default' => 0,
                'in_list' => true,
                'default_in_list' => true,
                'width' => 130,
                'editable' => false,
                'filtertype' => 'time',
                'filterdefault' => true,
                'order' => 98,
            ),
        'last_modified' =>
            array(
                'type' => 'time',
                'label' => '最后更新时间',
                'default' => 0,
                'in_list' => true,
                'width' => 130,
                'editable' => false,
                'order' => 99,
            ),
    ),
    'index' => array(
        'ind_order_bn' =>
            array(
                'columns' => array(0 => 'order_bn',),
            ),
        'ind_shop_id_order_bn' =>
            array(
                'columns' => array(0 => 'shop_id', 1=>'order_bn'),
                'prefix' => 'unique',
            ),
    ),
    'comment' => '待寻仓订单',
    'engine' => 'innodb',
    'version' => '$Rev: $',
);