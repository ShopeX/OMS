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

$db['order_coupon'] = array(
    'columns' => array(
        'id'            => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => 'ID',
            'width'    => 110,
            'hidden'   => true,
            'editable' => false,
        ),
        'order_id'      => array(
            'type'     => 'table:orders@ome',
            'required' => true,
            'editable' => false,
        ),
        'type'          => array(
            'type'          => 'varchar(50)',
            'label'         => '金额类型',
            'editable'      => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),

        'type_name'     => array(
            'type'          => 'varchar(50)',
            'label'         => '优惠名称',
        ),
        'coupon_type'   => array(
            'type'    => array(
                '0' => '暂无',
                '1' => '平台优惠',
                '2' => '商家优惠',
                '3' => '平台支付优惠 ',
            ),
            'default' => '0',
            'label'   => '优惠类型',
        ),
        'num'           => array(
            'type'     => 'number',
            'editable' => false,
            'label'    => 'sku数量',
            'comment'  => 'sku数量',
        ),
        'material_name' => array(
            'type'            => 'varchar(200)',
            'required'        => true,
            'label'           => '基础物料名称',
            'is_title'        => true,
            'default_in_list' => true,
            'width'           => 260,
            'searchtype'      => 'has',
            'editable'        => false,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'in_list'         => true,
        ),
        'material_bn'   => array(
            'type'            => 'varchar(200)',
            'label'           => '物料编码',
            'width'           => 120,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'amount'        => array(
            'type'    => 'money',
            'default' => '0.000',
            'label'   => '金额',
            'width'   => 75,
        ),
        'total_amount'        => array(
            'type'    => 'money',
            'default' => '0.000',
            'label'   => '小计金额',
            'width'   => 75,
        ),
        'oid'           => array(
            'type'     => 'varchar(50)',
            'default'  => '',
            'editable' => false,
            'label'    => '子订单号',
        ),
        'pay_time'      => array(
            'type'     => 'time',
            'label'    => '付款时间',
            'width'    => 130,
            'editable' => false,
            'in_list'  => true,
        ),
        'create_time'   => array(
            'type'          => 'time',
            'label'         => '下单时间',
            'width'         => 130,
            'editable'      => false,
            'filtertype'    => 'time',
            'filterdefault' => true,
            'in_list'       => true,
        ),
        'shop_type'     => array(
            'type'          => 'varchar(50)',
            'label'         => '店铺类型',
            'width'         => 75,
            'editable'      => false,
            'in_list'       => true,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ),
        'extend'        => array(
            'type'     => 'serialize',
            'editable' => false,
        ),
        'source'        => array(
            'type'    =>
                array(
                    'local' => '本地创建',
                    'rpc'   => 'api请求',
                    'push'  => '矩阵推送',
                ),
            'default' => 'local',
            'label'   => '来源',
        ),
    ),
    'index' => array(
        'idx_oid'         => array('columns' => array('oid')),
        'idx_coupon_type' => array('columns' => array('coupon_type')),
        'idx_create_time' => array('columns' => array('create_time')),
        'idx_pay_time'    => array('columns' => array('pay_time')),
    ),
    'comment' => '平台订单金额明细',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
    'charset' => 'utf8mb4',
);