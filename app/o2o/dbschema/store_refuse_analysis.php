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

$db['store_refuse_analysis'] = array(
    'columns' => array(
        'sra_id'      => array(
            'type'     => 'int unsigned',
            'required' => true,
            'width'    => 110,
            'hidden'   => true,
            'editable' => false,
            'pkey'     => true,
            'extra'    => 'auto_increment',
        ),
        'delivery_bn' => array(
            'type'            => 'varchar(32)',
            'required'        => true,
            'label'           => '发货单号',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 20,
            'searchtype'      => 'nequal',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'delivery_id' => array(
            'type'     => 'table:delivery@ome',
            'required' => true,
            'label'    => '发货单ID',
            'editable' => false,
        ),
        'branch_id'   => array(
            'type'     => 'table:branch@ome',
            'label'    => '仓库',
            'editable' => false,
        ),
        'order_id'    => array(
            'type'     => 'table:orders@ome',
            'label'    => '订单ID',
            'editable' => false,
        ),
        'order_bn'    => array(
            'type'            => 'varchar(32)',
            'label'           => '订单号',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 10,
            'searchtype'      => 'nequal',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'store_bn'    => array(
            'type'            => 'varchar(20)',
            'required'        => true,
            'label'           => '门店编码',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 30,
            'searchtype'      => 'nequal',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'store_name'  => array(
            'type'            => 'varchar(255)',
            'required'        => true,
            'label'           => '门店名称',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 40,
        ),
        'reason_id'   => array(
            'type'            => 'varchar(255)',
            'required'        => true,
            'label'           => '拒单原因',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 50,
        ),
        'memo'        => array(
            'type'            => 'text',
            'label'           => '备注',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => false,
            'order'           => 60,
        ),
        'op_name'     => array(
            'type'            => 'varchar(30)',
            'label'           => '操作员',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 70,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'createtime'  => array(
            'type'     => 'time',
            'required' => true,
            'label'    => '创建时间',
            'width'    => 130,
            'editable' => false,
        ),
        'at_time'     => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default_in_list' => true,
            'in_list'         => true,
            'order'           => 90,
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'default'         => 'CURRENT_TIMESTAMP',
        ),
        'up_time'     => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default_in_list' => true,
            'in_list'         => true,
            'order'           => 100,
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ),
    ),
    'index'   => array(
        'ind_store_bn'   => array(
            'columns' => array(
                0 => 'store_bn',
            ),
        ),
        'ind_createtime' => array(
            'columns' => array(
                0 => 'createtime',
            ),
        ),
        'idx_order_id' => array(
            'columns' => array(
                0 => 'order_id',
            ),
        ),
        'idx_branch_id' => array(
            'columns' => array(
                0 => 'branch_id',
            ),
        ),
        'idx_at_time' => array(
            'columns' => array(
                0 => 'at_time',
            ),
        ),
        'idx_up_time' => array(
            'columns' => array(
                0 => 'up_time',
            ),
        ),
    ),
    'comment' => '门店拒单原因表',
    'engine'  => 'innodb',
);
