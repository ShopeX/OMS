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

$db['sales']=[
    'columns' => [
        'id' => [
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'order' => 1,
        ],
        'sale_bn' => [
            'type' => 'varchar(32)',
            'required' => true,
            'label' => 'SO单号',
            'width' => 180,
            'searchtype' => 'nequal',
            'in_list' => true,
            'default_in_list' => true,
            'filterdefault' => true,
            'filtertype' => 'normal',
            'order'     => 2,
        ],
        'bill_bn' => [
            'type' => 'varchar(32)',
            'required' => true,
            'label' => '业务单号',
            'width' => 140,
            'searchtype' => 'nequal',
            'in_list' => true,
            'default_in_list' => true,
            'filterdefault' => true,
            'filtertype' => 'normal',
            'order' => 3,
        ],
        'bill_type' => [
            'type' => 'varchar(20)',
            'label' => '业务类型',
            'width' => 140,
            'in_list' => true,
            'default_in_list' => true,
            'comment' => '业务类型:JIT',
            'order' => 4,
        ],
        'bill_id' => [
            'type' => 'int unsigned',
            'label' => '业务单据ID',
        ],
        'shop_id'            => array(
            'type'          => 'varchar(32)',
            'label'         => '店铺ID',
        ),
        'shop_bn'            => array(
            'type'          => 'varchar(20)',
            'label'         => '店铺编码',
        ),
        'shop_name'            => array(
            'type'          => 'varchar(255)',
            'label'         => '店铺名称',
        ),
        'sale_time'            => [
            'type'            => 'time',
            'label'           => '销售时间',
            'width'           => 130,
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
        ],
        'ship_time'            => [
            'type'            => 'time',
            'label'           => '发货时间',
            'width'           => 130,
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
        ],
        'original_bn' =>
        array(
            'type' => 'varchar(32)',
            'label' => '原始单据号',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'default_in_list'=>true,
            'in_list'=>true,
        ),
        'original_id' => [
            'type' => 'int unsigned',
            'label' => '原始单据ID',
        ],
        'branch_id' => [
            'type' => 'int unsigned',
            'label' => '仓库ID',
        ],
        'branch_bn' => [
            'type' => 'varchar(32)',
            'label' => '仓库编码',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 20,
            'filtertype' => true,
            'filterdefault' => true,
        ],
        'branch_name' => [
            'type' => 'varchar(200)',
            'label' => '仓库名称',
            'in_list' => true,
            'default_in_list' => true,
        ],
        'logi_code' =>[
            'type' => 'varchar(32)',
            'label' => '承运商',
            'width' => 120,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 20,
        ],
        'logi_no' => [
            'type' => 'varchar(32)',
            'label' => '运单号',
            'width' => 120,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 20,
        ],
        'po_bn' =>
            array(
                    'type' => 'varchar(32)',
                    'label' => '采购单号',
                    'width' => 140,
                    'in_list' => true,
                    'default_in_list' => true,
                    'searchtype' => 'nequal',
                    'filterdefault' => true,
                    'filtertype' => 'normal',
                    'order' => 6,
            ),
        'at_time'           => [
            'type'    => 'TIMESTAMP',
            'label'   => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'width'           => 130,
            'in_list' => true,
        ],
        'up_time'           => [
            'type'    => 'TIMESTAMP',
            'label'   => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'           => 130,
            'in_list' => true,
        ],
        'in_ar' => [
            'type'    => [
                '0' => '未进',
                '1' => '处理中',
                '2' => '已进',
            ],
            'label'   => '转AR单',
            'default' => '0',
            'in_list' => true,
        ],
        'total_amount'             => array(
            'type'    => 'money',
            'default' => 0,
            'label'   => '销售总额',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'settlement_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '结算总额',
            'in_list' => true,
            'default_in_list' => true,
        ),
    ],
    'index' => [
        'ind_at_time' => ['columns' => ['at_time']],
        'ind_up_time' => ['columns' => ['up_time']],
        'ind_branch_bn' => ['columns' => ['branch_bn']],
        'ind_sale_time' => ['columns' => ['sale_time']],
        'ind_original_bn' => ['columns' => ['original_bn', 'bill_type'], 'prefix' => 'unique'],
        'ind_original_id' => ['columns' => ['original_id']],
        'ind_in_ar' => ['columns' => ['in_ar']],
        'ind_po_bn' => ['columns' => ['po_bn']],
        'ind_bill_bn' => ['columns' => ['bill_bn', 'shop_id', 'bill_type']],
        'ind_bill_id' => ['columns' => ['bill_id']],
        'ind_sale_bn' => ['columns' => ['sale_bn'], 'prefix' => 'unique'],
    ],
    'comment' => '唯品会销售单',
    'engine' => 'innodb',
    'version' => '$Rev: $',
];
