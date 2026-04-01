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

$db['inventory_snapshot_items'] = [
    'columns' => [
        'id'             => [
            'type'     => 'int unsigned',
            'label'    => 'ID',
            'comment'  => 'ID',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
        ],
        'invs_id'        => [
            'type'     => 'int unsigned',
            'label'    => '快照ID',
            'required' => true,
        ],
        'stock_date'     => [
            'type'            => 'DATE',
            'label'           => '记录日期',
            'default_in_list' => true,
            'in_list'         => true,
            'order'           => 10,
        ],
        'wms_id'         => [
            'type'     => 'int unsigned',
            'label'    => '渠道名称',
            'required' => true,
        ],
        'item_code'      => [
            'type'            => 'varchar(32)',
            'label'           => '基础物料编码',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 10,
            'filtertype'    => 'normal',
            'filterdefault' => true,
            'searchtype'      => 'nequal',
        ],
        'wms_bn'         => [
            'type'            => 'varchar(20)',
            'label'           => '渠道编码',
            'required'        => true,
            'searchtype'      => 'nequal',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 20,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ],
        'warehouse_code' => [
            'type'            => 'varchar(50)',
            'label'           => 'WMS仓库编号',
            'required'        => true,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 30,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ],

        'item_id'        => [
            'type'            => 'varchar(32)',
            'label'           => '基础物料ID',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 50,
        ],
        'inventory_type' => [
            'type'            => 'varchar(32)',
            'label'           => '基础物料类型',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 60,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ],
        'quantity'       => [
            'type'            => 'int',
            'label'           => '数量',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 70,
            'filtertype'    => 'number',
            'filterdefault' => true,
        ],
        'lock_quantity'  => [
            'type'            => 'int',
            'label'           => '冻结数量',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 80,
            'filtertype'    => 'number',
            'filterdefault' => true,
        ],
        'batch_code'     => [
            'type'            => 'varchar(32)',
            'label'           => '批次编码',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 90,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ],
        'produce_code'   => [
            'type'            => 'varchar(32)',
            'label'           => '生产编号',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 100,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ],
        'product_date'   => [
            'type'            => 'varchar(32)',
            'label'           => '基础物料生产日期',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 110,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ],
        'expire_date'    => [
            'type'            => 'varchar(32)',
            'label'           => '基础物料过期日期',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 120,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ],
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
    ],
    'index'   => [
        'ind_warehouse_code' => ['columns' => ['warehouse_code']],
        'ind_item_code'      => ['columns' => ['item_code']],
        'ind_invs_id'        => ['columns' => ['invs_id']],
        'ind_stock_date'     => ['columns' => ['stock_date', 'warehouse_code', 'item_code']],
    ],
    'comment' => 'WMS库存快照明细',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
];
