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

$db['refund_noreturn'] = array(
    'columns' => array(
        'id'                => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'editable' => false,
        ),
        'order_id'          => array(
            'type'            => 'table:orders@ome',
            'required'        => true,
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
            'label'           => '订单ID',
        ),
        'order_bn'          => array(
            'type'            => 'varchar(32)',
            'label'           => '订单号',
            'is_title'        => true,
            'width'           => 180,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'   => 10,
        ),
        'sales_material_bn' => array(
            'type'            => 'varchar(30)',
            'label'           => '销售物料编码',
            'required' => true,
            'width'           => 120,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'   => 20,
        ),
        'basic_material_bn' => array(
            'type'            => 'varchar(30)',
            'label'           => '基础物料编码',
            'required' => true,
            'width'           => 120,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'   => 30,
        ),
        'order_obj_id'      => array(
            'type'     => 'int unsigned',
            'required' => true,
            'default'  => 0,
            'editable' => false,
            'comment'  => '订单子单ID,order_objects.obj_id'
        ),
        'order_item_id'     => array(
            'type'     => 'int unsigned',
            'required' => true,
            'default'  => 0,
            'editable' => false,
            'comment'  => '订单子单ID,order_items.item_id'
        ),
        'oid'               => array(
            'type'     => 'varchar(50)',
            'default'  => 0,
            'editable' => false,
            'label'    => '子订单号',
            'order'   => 40,
        ),
        'order_status'      => array(
            'type'            => array(
                0 => '未发货',
                1 => '已发货',
                2 => '部分发货',
                3 => '部分退货',
                4 => '已退货',
            ),
            'default'         => '0',
            'label'           => '订单状态',
            'width'           => 75,
            'hidden'          => true,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'comment' => '发货状态,可选值:0(未发货),1(已发货),2(部分发货),3(部分退货),4(已退货)',
            'order'   => 50,
        ),
        'oid_status'        => array(
            'type'    => array(
                0 => '未发货',
                1 => '已发货',
                2 => '部分发货',
                3 => '部分退货',
                4 => '已退货',
            ),
            'default' => '0',
            'label'   => '子订单状态',
            'comment' => '子订单状态,0:未发货,1:已发货,2:部分发货,3:部分退货,4:已退货',
            'order'   => 60,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'apply_id'          => array(
            'type'     => 'int unsigned',
            'default'  => 0,
            'editable' => false,
            'comment'  => '退款单申请ID'
        ),
        'refund_apply_bn'   => array(
            'type'            => 'varchar(32)',
            'default'         => '',
            'label'           => '退款申请单号',
            'width'           => 180,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'   => 70,
        ),
        'refund_status'     => array(
            'type'            => array(
                0  => '未审核',
                1  => '审核中',
                2  => '已接受申请',
                3  => '已拒绝',
                4  => '已退款',
                5  => '退款中',
                6  => '退款失败',
                10 => '卖家拒绝退款',
            ),
            'default'         => '0',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '退款状态',
            'order'   => 80,
        ),
        'return_id'         => array(
            'type'     => 'int unsigned',
            'default'  => 0,
            'editable' => false,
            'comment'  => '退货ID'
        ),
        'return_bn'         => array(
            'type'            => 'varchar(32)',
            'label'           => '退货单号',
            'comment'         => '退货单号',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'width'           => 180,
            'order'   => 90,
            
        ),
        'return_status'     => array(
            'type'            => array(
                0 => '未入库',
                1 => '待入库',
                2 => '已入库',
            ),
            'editable'        => false,
            'label'           => '退货状态',
            'in_list'         => true,
            'default_in_list' => true,
            'default'         => '0',
            'width'           => 75,
            'order'   => 100,
        ),
        'at_time'       => array(
            'type'    => 'TIMESTAMP',
            'label'   => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'width'   => 130,
            'in_list'         => true,
            'default_in_list' => true,
            'order'   => 110,
        ),
        'up_time'       => array(
            'type'    => 'TIMESTAMP',
            'label'   => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'   => 130,
            'in_list'         => true,
            'default_in_list' => true,
            'order'   => 120,
        ),
    ),
    'index'   => array(
        'idx_at_time' => array('columns' => array('at_time')),
        'idx_up_time' => array('columns' => array('up_time')),
        'idx_order_bn' => array('columns' => array('order_bn')),
        'idx_order_item_id' => array('columns' => array('order_item_id')),
        'idx_order_status' => array('columns' => array('order_status')),
        'idx_refund_status' => array('columns' => array('refund_status')),
        'idx_return_status' => array('columns' => array('return_status')),
    ),
    'comment' => '退款未退货报表',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
