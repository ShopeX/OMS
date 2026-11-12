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
 * 唯品会平台已经成交的销售单列表
 *
 * @author wangbiao@shopex.cn
 * @version 2025.03.27
 */
$db['inventory_orders'] = array(
    'columns' => array(
        'id' => array(
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
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 130,
            'order' => 10,
        ),
        'root_order_sn' => array(
            'type' => 'varchar(50)',
            'label' => '根订单号',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 20,
        ),
        'shop_id' => array(
            'type' => 'table:shop@ome',
            'editable' => false,
            'label' => '店铺名称',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 15,
        ),
        'platform_status' => array (
            'type' => array (
                'active' => '活动订单',
                'cancel' => '已作废',
                'finish' => '已完成',
            ),
            'default' => 'active',
            'required' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'label' => '平台订单状态',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 110,
            'order' => 11,
        ),
        'dispose_status' => array(
            'type' => array(
                'none' => '未处理',
                'running' => '处理中',
                'part' => '部分处理',
                'finish' => '已完成',
                'cancel' => '已取消',
                'needless' => '无需处理',
                'fail' => '处理失败',
            ),
            'default' => 'none',
            'required' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'label' => '处理状态',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 120,
            'order' => 12,
        ),
        'order_source' => array (
            'type' => array (
                'none' => '未知',
                'jit' => 'JIT订单',
                'jitx' => 'JITX订单',
            ),
            'default' => 'none',
            'required' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'label' => '订单来源',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 110,
            'order' => 15,
        ),
        'is_fail' => array (
            'type' => 'bool',
            'required' => true,
            'default' => 'false',
            'editable' => false,
            'label' => '失败订单',
            'in_list' => true,
            'default_in_list' => false,
            'order' => 35,
        ),
        'occupied_order_sn' => array(
            'type' => 'varchar(80)',
            'label' => '库存占用订单号',
            'in_list' => true,
            'default_in_list' => false,
            'width' => 300,
            'order' => 22,
        ),
        'address_code' => array(
            'type' => 'varchar(50)',
            'label' => '订单的四级地址编码',
            'in_list' => true,
            'default_in_list' => false,
            'order' => 50,
        ),
        'sale_warehouse' => array(
            'type' => 'varchar(50)',
            'label' => '销售区域',
            'in_list' => true,
            'default_in_list' => false,
            'order' => 52,
        ),
        'is_prebuy' => array(
            'type' => 'varchar(10)',
            'label' => '自动抢货订单标识',
            'in_list'  => true,
            'default_in_list' => false,
            'editable' => false,
            'order' => 53,
        ),
        'hold_flag' => array(
            'type' => 'varchar(10)',
            'label' => 'hold标记',
            'in_list'  => true,
            'default_in_list' => true,
            'editable' => false,
            'order' => 32,
        ),
        'brand_id' => array(
            'type' => 'varchar(30)',
            'label' => '品牌ID',
            'in_list'  => true,
            'default_in_list' => false,
            'editable' => false,
            'order' => 25,
        ),
        'warehouse' => array(
            'type' => 'varchar(30)',
            'label' => '仓库编码',
            'in_list'  => true,
            'default_in_list' => true,
            'editable' => false,
            'order' => 25,
        ),
        'warehouse_flag' => array(
            'type' => 'varchar(30)',
            'label' => '仓库编码标识',
            'in_list'  => true,
            'default_in_list' => false,
            'editable' => false,
            'order' => 25,
        ),
        'cooperation_no' => array(
            'type' => 'varchar(30)',
            'label' => '常态合作编码',
            'in_list'  => true,
            'default_in_list' => false,
            'editable' => false,
            'order' => 25,
        ),
        'cooperation_mode' => array(
            'type' => 'varchar(30)',
            'label' => '合作模式',
            'in_list'  => true,
            'default_in_list' => false,
            'editable' => false,
            'order' => 25,
        ),
        'sales_source_indicator' => array(
            'type' => 'varchar(30)',
            'label' => '销售来源',
            'in_list'  => true,
            'default_in_list' => false,
            'editable' => false,
            'order' => 25,
        ),
        'dispose_msg' => array(
            'type' => 'varchar(200)',
            'label' => '处理失败原因',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
        ),
        'create_time' => array(
            'type' => 'time',
            'default' => 0,
            'label' => '订单时间',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 95,
        ),
        'at_time' => array(
            'type' => 'TIMESTAMP',
            'label' => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'filterdefault' => true,
            'width' => 135,
            'order' => 98,
        ),
        'up_time' => array(
            'type' => 'TIMESTAMP',
            'label' => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'filterdefault' => true,
            'width' => 135,
            'order' => 99,
        ),
    ),
    'index' => array(
        'ind_order_sn' => array(
            'columns' => array(
                0 => 'order_sn',
            ),
            'prefix' => 'unique',
        ),
        'ind_root_order_sn' => array(
            'columns' => array(
                0 => 'root_order_sn',
            ),
        ),
        'ind_platform_status' => array(
            'columns' => array(
                0 => 'platform_status',
            ),
        ),
        'ind_dispose_status' => array(
            'columns' => array(
                0 => 'dispose_status',
            ),
        ),
        'ind_create_time' => array(
            'columns' => array(
                0 => 'create_time',
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
    'comment' => '唯品会成交的销售单表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);