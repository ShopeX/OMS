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

$db['goods_price'] = array(
    'columns' => array(
        'id'           => array(
            'type'     => 'int',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => 'ID',
            'in_list'         => true,
            'default_in_list' => true,
            'width'           => 50,
        ),
        'bs_id'        => array(
            'type'            => 'table:business@dealer',
            'required'        => true,
            'label'           => '经销商编码',
            'width'           => 120,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 10,
        ),
        'bm_id'        => array(
            'type'            => 'table:basic_material@material',
            'required'        => true,
            'label'           => '基础物料编码',
            'width'           => 120,
            'in_list'         => true,
            'default_in_list' => true,
            // 'filtertype'      => 'normal',
            // 'filterdefault'   => true,
            'order'           => 20,
        ),
        'price'        => array(
            'type'            => 'decimal(20,3)',
            'required'        => true,
            'label'           => '采购价',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 30,
        ),
        'price_unit'         => array(
            'type'            => 'varchar(10)',
            'label'           => '价格单位',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 35,
            'default'         => '',
        ),
        'start_time'   => array(
            'type'            => 'time',
            'label'           => '生效时间',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'date',
            'filterdefault'   => true,
            'order'           => 40,
        ),
        'end_time'     => array(
            'type'            => 'time',
            'label'           => '过期时间',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'date',
            'filterdefault'   => true,
            'order'           => 50,
        ),
        'at_time'      => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
            'width'           => 150,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 60,
        ),
        'up_time'      => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'           => 150,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 70,
        ),
    ),
    'index'   => array(
        'ind_bs_bm' => array(
            'columns' => array(
                0 => 'bs_id',
                1 => 'bm_id',
            ),
        ),
        'ind_bs_id' => array(
            'columns' => array(
                0 => 'bs_id',
            ),
        ),
        'ind_bm_id' => array(
            'columns' => array(
                0 => 'bm_id',
            ),
        ),
    ),
    'comment' => '经销基础物料价格管理',
    'engine'  => 'innodb',
    'version' => '$Rev: $',
); 