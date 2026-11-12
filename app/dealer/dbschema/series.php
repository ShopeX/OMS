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

$db['series'] = array(
    'columns' => array(
        'series_id'   => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => '产品线ID',
        ),
        'series_code' => array(
            'type'            => 'varchar(30)',
            'label'           => '产品线编码',
            'is_title'        => true,
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order'           => 10,
            'width'           => 110,
        ),
        'series_name' => array(
            'type'            => 'varchar(50)',
            'label'           => '产品线名称',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order'           => 20,
            'width'           => 100,
        ),
        'description' => array(
            'type'            => 'text',
            'label'           => '产品线描述',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 30,
        ),
        'cat_name'    => array(
            'type'            => 'varchar(50)',
            'label'           => '产品线分类',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 40,
            'width'           => 110,
        ),
        'status'      => array(
            'type'            => array(
                'active' => '启用',
                'close'  => '停用',
            ),
            'default'         => 'active',
            'label'           => '状态',
            'filtertype' => 'normal',
            'filterdefault'   => true,
            'in_list'         => false,
            'default_in_list' => false,
            'order'           => 60,
            'width'           => 60,
        ),
        'sku_nums'    => array(
            'type'    => 'int unsigned',
            'label'   => '产品数',
            'default' => '0',
        ),
        'betc_id'     => array(
            'type'            => 'table:betc@dealer',
            'label'           => '所属贸易公司',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault'   => true,
            'searchtype'      => 'nequal',
            'order'           => 50,
        ),
        'cos_id'      => array(
            'type'  => 'int unsigned',
            'label' => '组织架构ID',
        ),
        'remark'      => array(
            'label'           => '备注',
            'type'            => 'text',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 80,
        ),
        'op_name'     => array(
            'type'            => 'varchar(32)',
            'label'           => '创建人',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 110,
            'width'           => 80,
        ),
        'at_time'     => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
            'width'           => 150,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 90,
        ),
        'up_time'     => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'           => 150,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 100,
        ),
    ),
    'index'   => array(
        'ind_series_code' => array(
            'columns' => array(
                'series_code',
            ),
        ),
        'ind_series_name' => array(
            'columns' => array(
                'series_name',
            ),
        ),
        'ind_status'      => array(
            'columns' => array(
                'status',
            ),
        ),
        'ind_betc_id'     => array(
            'columns' => array(
                'betc_id',
            ),
        ),
        'ind_cos_id'      => array(
            'columns' => array(
                'cos_id',
            ),
        ),
        'ind_at_time'     => array(
            'columns' => array(
                'at_time',
            ),
        ),
        'ind_up_time'     => array(
            'columns' => array(
                'up_time',
            ),
        ),
    ),
    'comment' => '产品线',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
