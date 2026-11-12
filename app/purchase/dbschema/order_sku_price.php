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

$db['order_sku_price']=array (
    'columns' => array (
        'id' =>
            array (
              'type' => 'int unsigned',
              'required' => true,
              'pkey' => true,
              'extra' => 'auto_increment',
              'editable' => false,
              'order' => 1,
        ),
        'po_id' =>
            array (
                    'type' => 'int unsigned',
                    'label' => '采购单ID',
                    'order' => 2,
            ),
        'po_bn' =>
            array (
                    'type' => 'varchar(32)',
                    'label' => '采购单号',
                    'width' => 140,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'searchtype' => 'nequal',
                    'filterdefault' => true,
                    'filtertype' => 'normal',
                    'order' => 2,
            ),
            'barcode' =>
            array (
                    'type' => 'varchar(80)',
                    'label' => '条码',
                    'width' => 180,
                    'in_list' => true,
                    'default_in_list' => true,
                    'searchtype' => 'nequal',
                    'filterdefault' => true,
                    'filtertype' => 'normal',
            ),
            'actual_market_price' =>
            array (
                    'type' => 'money',
                    'label' => '含税结算价',
                    'default' => 0,
                    'width' => 100,
                    'in_list' => true,
                    'default_in_list' => true,
            ),
            'actual_unit_price' =>
            array (
                    'type' => 'money',
                    'label' => '不含税结算价',
                    'default' => 0,
                    'width' => 100,
                    'in_list' => true,
                    'default_in_list' => true,
            ),
            'price' =>
            array (
                'type' => 'money',
                'label' => '原价',
                'default' => 0,
                'width' => 100,
                'in_list' => true,
                'default_in_list' => true,
            ),
            'at_time'           => [
                'type'    => 'TIMESTAMP',
                'label'   => '创建时间',
                'default' => 'CURRENT_TIMESTAMP',
                'width'   => 120,
                'in_list' => true,
            ],
            'up_time'           => [
                'type'    => 'TIMESTAMP',
                'label'   => '更新时间',
                'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'width'   => 120,
                'in_list' => true,
            ],
    ),
    'index' => array (

        'ind_unique'    => ['columns' => ['po_bn', 'barcode', 'actual_market_price'], 'prefix' => 'unique'],
        'ind_po_id'     => ['columns' => ['po_id']],
        'ind_barcode'  => ['columns' => ['barcode']],
        'ind_at_time'  => ['columns' => ['at_time']],
        'ind_up_time'  => ['columns' => ['up_time']],
    ),
    'comment' => 'PO采购单供货价',
    'engine' => 'innodb',
    'version' => '$Rev: $',
);