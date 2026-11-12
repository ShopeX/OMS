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
 * JIT订单明细查询表(barcode条形码纬度)
 *
 * @author wangbiao@shopex.cn
 * @version 2025.03.27
 */
$db['pick_order_items'] = array (
    'columns' => array(
        'item_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'label' => 'ID',
            'comment' => '自增主键ID'
        ),
        'order_sn' => array(
            'type' => 'varchar(32)',
            'required' => true,
            'label' => '订单号',
            'is_title' => true,
            'width' => 150,
            'searchtype' => 'nequal',
            'editable' => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 10,
        ),
        'old_order_sn' => array(
            'type' => 'varchar(50)',
            'label' => '原订单号',
            'in_list'  => true,
            'default_in_list' => false,
            'editable' => false,
            'order' => 15,
        ),
        'shop_id' => array(
            'type' => 'table:shop@ome',
            'editable' => false,
            'label' => '店铺名称',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 19,
        ),
        'status' => array(
            'type' => array(
                'none' => '未处理',
                'running' => '处理中',
                'finish' => '已处理',
                'cancel' => '已取消',
                'needless' => '无需处理',
                'fail' => '处理失败',
            ),
            'default' => 'none',
            'required' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'label' => '处理状态',
            'width' => 110,
            'hidden' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 13,
        ),
        'stat' => array(
            'type' => 'varchar(20)',
            'label' => '订单状态',
            'in_list'  => false,
            'default_in_list' => false,
            'editable' => false,
            'order' => 32,
        ),
        'po' => array(
            'type' => 'varchar(32)',
            'label' => '采购单号',
            'editable' => false,
            'width' => 160,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 20,
        ),
        'pick_no' => array(
            'type' => 'varchar(32)',
            'label' => '拣货单号',
            'is_title' => true,
            'width' => 160,
            'searchtype' => 'nequal',
            'editable' => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 18,
        ),
        'vip_delivery_no' => array(
            'type' => 'varchar(50)',
            'label' => '送货单号',
            'in_list'  => true,
            'default_in_list' => false,
            'editable' => false,
            'order' => 25,
        ),
        'good_sn' => array(
            'type' => 'varchar(50)',
            'label' => '条形码',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list'  => true,
            'default_in_list' => true,
            'editable' => false,
            'order' => 30,
        ),
        'product_id' => array (
            'type' => 'int unsigned',
            'default' => 0,
            'label' => 'OMS货品ID',
            'editable' => false,
            'width' => 110,
        ),
        'product_bn' => array (
            'type' => 'varchar(50)',
            'label' => 'OMS货号',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 28,
        ),
        'amount' => array(
            'type' => 'number',
            'label' => '数量',
            'in_list'  => true,
            'default_in_list' => true,
            'editable' => false,
            'order' => 32,
        ),
        'is_prebuy' => array(
            'type' => 'varchar(6)',
            'label' => '是否预购订单',
            'in_list'  => true,
            'default_in_list' => true,
            'editable' => false,
            'order' => 50,
        ),
        'is_split_order' => array(
            'type' => 'varchar(6)',
            'label' => '是否拆单订单',
            'in_list'  => true,
            'default_in_list' => true,
            'editable' => false,
            'order' => 52,
        ),
        'shelf_num' => array(
            'type' => 'varchar(6)',
            'label' => '上架数',
            'in_list'  => true,
            'default_in_list' => true,
            'editable' => false,
            'order' => 54,
        ),
        'dispose_msg' => array(
            'type' => 'varchar(200)',
            'label' => '处理失败原因',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
        ),
        'add_time' => array(
            'type' => 'time',
            'default' => 0,
            'label' => '下单时间',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 120,
            'order' => 95,
        ),
        'update_time' => array(
            'type' => 'time',
            'default' => 0,
            'label' => '更新时间',
            'in_list' => true,
            'default_in_list' => false,
            'width' => 120,
            'order' => 96,
        ),
        'delivery_kpi_start_time' => array(
            'type' => 'time',
            'default' => 0,
            'label' => '发货开始考核时间',
            'in_list' => true,
            'default_in_list' => false,
            'width' => 120,
            'order' => 97,
        ),
        'at_time' => array(
            'type' => 'TIMESTAMP',
            'label' => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'in_list' => true,
            'default_in_list' => true,
            'width' => 135,
            'order' => 98,
        ),
        'up_time' => array(
            'type' => 'TIMESTAMP',
            'label' => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'in_list' => true,
            'default_in_list' => true,
            'width' => 135,
            'order' => 99,
        ),
    ),
    'index' => array(
        'ind_order_barcode' => array(
            'columns' => array(
                0 => 'order_sn',
                1 => 'good_sn',
            ),
            'prefix' => 'unique',
        ),
        'ind_status' => array(
            'columns' => array(
                0 => 'status',
            ),
        ),
        'ind_po' => array(
            'columns' => array(
                0 => 'po',
            ),
        ),
        'ind_pick_no' => array(
            'columns' => array(
                0 => 'pick_no',
            ),
        ),
        'ind_product_status' => array(
            'columns' => array(
                0 => 'product_id',
                1 => 'status',
            ),
        ),
        'ind_bn_status' => array(
            'columns' => array(
                0 => 'product_bn',
                1 => 'status',
            ),
        ),
        'ind_good_status' => array(
            'columns' => array(
                0 => 'good_sn',
                1 => 'status',
            ),
        ),
        'ind_add_time' => array(
            'columns' => array(
                0 => 'add_time',
            ),
        ),
        'ind_at_time' => array(
            'columns' => array(
                0 => 'at_time',
            ),
        ),
        'ind_up_time' => array(
            'columns' => array(
                0 => 'up_time',
            ),
        ),
    ),
    'comment' => 'JIT订单明细查询表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);