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

$db['order']=array (
    'columns' => array (
        'po_id' =>
            array (
              'type' => 'number',
              'required' => true,
              'pkey' => true,
              'extra' => 'auto_increment',
              'editable' => false,
              'order' => 1,
        ),
        'po_bn' =>
            array (
                    'type' => 'varchar(32)',
                    'required' => true,
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
        'shop_id' =>
            array (
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
        'co_mode' =>
            array (
                    'type' => 'varchar(30)',
                    'label' => '合作模式编码',
                    'required' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => 100,
                    'editable' => false,
                    'order' => 90,
            ),
        'sell_st_time' =>
            array (
                    'type' => 'time',
                    'label' => '档期开始时间',
                    'default' => 0,
                    'editable' => false,
                    'width' => 140,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 50,
            ),
        'sell_et_time' =>
            array (
                    'type' => 'time',
                    'label' => '档期结束时间',
                    'default' => 0,
                    'editable' => false,
                    'width' => 140,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 51,
            ),
        'stock' =>
            array (
                    'type' => 'number',
                    'label' => '虚拟总库存',
                    'default' => 0,
                    'width' => 80,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 23,
            ),
        'sales_num' =>
            array (
                    'type' => 'number',
                    'label' => '销售数量',
                    'default' => 0,
                    'width' => 80,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 20,
            ),
        'unpick_num' =>
            array (
                    'type' => 'number',
                    'label' => '未拣货数量',
                    'default' => 0,
                    'width' => 85,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 21,
            ),
        'trade_mode' =>
            array (
                    'type' => 'varchar(30)',
                    'label' => '海淘档期交易模式',
                    'width' => 100,
                    'editable' => false,
                    'order' => 97,
            ),
        'schedule_id' =>
            array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '档期号',
                    'width' => 140,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'searchtype' => 'nequal',
                    'order' => 11,
            ),
        'schedule_name' =>
            array (
                    'type' => 'varchar(50)',
                    'label' => '档期名称',
                    'width' => 160,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'order' => 10,
            ),
        'supplier_name' =>
            array (
                    'type' => 'varchar(100)',
                    'label' => '供应商名称',
                    'width' => 150,
                    'editable' => false,
                    'order' => 91,
            ),
        'brand_name' =>
            array (
                    'type' => 'varchar(32)',
                    'label' => '品牌名称',
                    'width' => 120,
                    'editable' => false,
                    'in_list' => true,
                    'order' => 90,
            ),
        'warehouse' =>
            array (
                    'type' => 'varchar(32)',
                    'label' => '入库仓库',
                    'width' => 120,
                    'editable' => false,
                    'order' => 30,
            ),
        'is_normal' =>
            array (
                    'type' => 'tinyint(1)',
                    'label' => '是否常态档期',
                    'width' => 130,
                    'editable' => false,
                    'default' => 0,
                    'order' => 95,
            ),
        'create_time' =>
            array (
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
            array (
                    'type' => 'time',
                    'label' => '最后更新时间',
                    'default' => 0,
                    'in_list' => true,
                    'width' => 130,
                    'editable' => false,
                    'order' => 99,
            ),
            'need_pull' =>
            array (
                'type' => 'tinyint(1)',
                'label' => '是否需要获取',
                'width' => 130,
                'editable' => false,
                'default' => 0,
                'in_list' => true,
                'order' => 95,
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
        'ind_po_bn' =>
            array (
            'columns' =>
                  array (
                    0 => 'po_bn',
                  ),
            'prefix' => 'unique'
        ),
        'ind_at_time' => ['columns' => ['at_time']],
        'ind_up_time' => ['columns' => ['up_time']],
    ),
    'comment' => 'PO单',
    'engine' => 'innodb',
    'version' => '$Rev: $',
);