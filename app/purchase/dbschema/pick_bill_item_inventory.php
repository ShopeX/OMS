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

$db['pick_bill_item_inventory']=array (
    'columns' => array (
        'bill_inventory_id'     => array (
            'type'            => 'int unsigned',
            'required'        => true,
            'pkey'            => true,
            'extra'           => 'auto_increment',
            'editable'        => false,
        ),
        'bill_id'               => array (
            'type'            => 'number',
            'default'         => 0,
            'label'           => '拣货单编号',
            'editable'        => false,
        ),
        'pick_no'               => array (
            'type'            => 'varchar(32)',
            'label'           => '拣货单号',
            'width'           => 140,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'filterdefault'   => true,
            'filtertype' => 'normal',
            'order'           => 2,
        ),
        // 'stockout_id'           => array (
        //     'type'            => 'number',
        //     'required'        => true,
        //     'default'         => 0,
        //     'label'           => '出库单编号',
        //     'editable'        => false,
        // ),
        'order_sn'              => array(
            'type'            => 'varchar(32)',
            'label'           => '订单号',
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
            'filtertype'      => 'textarea',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'occupied_order_sn'     => array(
            'type'            => 'varchar(255)',
            'label'           => '库存占用订单号',
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'root_order_sn'         => array (
            'type'            => 'varchar(64)',
            'label'           => '根订单号',
            'editable'        => false,
            'default'         => 0,
        ),
        // 'bn'                     => array (
        //     'type'            => 'varchar(30)',
        //     'label'           => '货号',
        //     'width'           => 100,
        //     'editable'        => false,
        // ),
        // 'product_name'          => array (
        //     'type'            => 'varchar(80)',
        //     'label'           => '商品名称',
        //     'width'           => 130,
        //     'editable'        => false,
        // ),
        'barcode'                => array (
            'type'            => 'varchar(80)',
            'label'           => '条码',
            'width'           => 100,
            'editable'        => false,
        ),
        'amount'                => array (
            'type'            => 'number',
            'label'           => '数量',
            'editable'        => false,
            'default'         => 0,
        ),
        'num'                   => array (
            'type'            => 'number',
            'label'           => '已处理数量',
            'editable'        => false,
            'default'         => 0,
        ),
        'brand_id'              => array (
            'type'            => 'varchar(32)',
            'label'           => '品牌ID',
            'width'           => 80,
            'editable'        => false,
            'in_list'         => true,
        ),
        'cooperation_no'        => array (
            'type'            => 'varchar(255)',
            'editable'        => false,
            'label'           => '常态合作编码',
            'in_list'         => true,
            'order'           => 19,
        ),
        'warehouse'             => array(
            'type'            => 'varchar(32)',
            'label'           => '仓库编码',
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'sales_source_indicator'=> array (
            'type'            =>  array (
                '-1'    => '未知来源',
                '0'     => '运营专场',
                '1'     => '旗舰店',
            ),
            'label'           => '销售来源',
            'width'           => 75,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'filterdefault'   => true,
            'filtertype' => 'normal',
        ),
        'sales_no'              => array(
            'type'            => 'varchar(32)',
            'label'           => '旗舰店/运营专场编号',
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'vop_create_time'       => array(
            'type'            => 'varchar(32)',
            'label'           => 'vop的订单时间',
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'cooperation_mode'      => array(
            'type'            => 'varchar(32)',
            'label'           => '合作模式', // 目前返回dvd/jit/jit_4a
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'warehouse_flag'        => array(
            'type'            => 'varchar(32)',
            'label'           => '仓库编码标识', // 0或者null:全国逻辑仓或7大仓 1：省仓编码
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'sale_warehouse'        => array(
            'type'            => 'varchar(32)',
            'label'           => '销售区域',
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'address_code'          => array(
            'type'            => 'varchar(32)',
            'label'           => '订单的四级地址编码', // 直发的订单可返回此字段，JIT订单不返回此字段
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'hold_flag'             => array(
            'type'            => 'varchar(32)',
            'label'           => 'hold标记', // 正向订单有返回，取消订单不返回， 0=正常hold单，1=hold单时间16天 没有返回则此字段则正常hold单
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'is_prebuy'             => array(
            'type'            => [
                '0'     =>  '非自动抢货订单',
                '1'     =>  '自动抢货订单',
            ],
            'label'           => '自动抢货订单标识',
            'width'           => 180,
            'searchtype'      => 'nequal',
            'editable'        => false,
        ),
        'at_time'        => [
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
            'width'           => 120,
            'in_list'         => true,
        ],
        'up_time'        => [
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'           => 120,
            'in_list'         => true,
        ],
    ),
    'index' => array (
        'ind_pick_order_bar'  => array (
            'columns' => array (
                0 => 'pick_no',
                1 => 'order_sn',
                2 => 'barcode',
            ),
            'prefix' => 'unique'
        ),
        'ind_order_sn'  => array (
            'columns' => array (
                0 => 'order_sn',
            ),
        ),
        'ind_pick_no'   => array (
            'columns' => array (
                0 => 'pick_no',
            ),
        ),
    ),
    'comment' => '拣货单明细的详单',
    'engine' => 'innodb',
    'version' => '$Rev: $',
);