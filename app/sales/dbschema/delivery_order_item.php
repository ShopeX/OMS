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

$db['delivery_order_item'] = array(
    'columns' => array(
        'id'                => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
        ),
        'shop_id'           => array(
            'type'            => 'table:shop@ome',
            'label'           => '来源店铺',
            'width'           => 75,
            'editable'        => false,
            'order'           => 1,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'shop_bn'           => array(
            'type'     => 'varchar(30)',
            'label'    => '来源店铺',
            'width'    => 75,
            'editable' => false,
            'order'    => 2,
        ),
        'shop_type'         => array(
            'type'            => 'varchar(50)',
            'label'           => '店铺类型',
            'width'           => 75,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'branch_id'         => array(
            'type'          => 'table:branch@ome',
            'editable'      => false,
            'label'         => '仓库',
            'width'         => 110,
            'filtertype'    => 'normal',
            'filterdefault' => true,
            'in_list'       => true,
        ),
        'branch_bn'         => array(
            'type'     => 'varchar(32)',
            'editable' => false,
            'label'    => '仓库',
            'width'    => 110,
            'order'    => 3,
        ),
        'delivery_id'       => array(
            'type'     => 'int unsigned',
            'required' => true,
            'label'    => '发货单',
        ),
        'delivery_item_id'  => array(
            'type'     => 'int unsigned',
            'required' => true,
            'editable' => false,
        ),
        'order_id'          => array(
            'type'     => 'int unsigned',
            'required' => true,
            'label'    => '订单',
        ),
        'order_bn'          => array(
            'type'            => 'varchar(32)',
            'label'           => '订单号',
            'order'           => 4,
            'searchtype'      => 'nequal',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'textarea',
            'filterdefault'   => true,
        ),
        'delivery_bn'       => array(
            'type'            => 'varchar(32)',
            'label'           => '发货单号',
            'order'           => 5,
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'filtertype'      => 'yes',
            'filterdefault'   => true,
        ),
        'order_obj_id'      => array(
            'type'     => 'int unsigned',
            'required' => true,
            'editable' => false,
        ),
        'order_item_id'     => array(
            'type'     => 'int unsigned',
            'required' => true,
            'comment'  => '订单item ID',
        ),
        'obj_type'          => array(
            'type'     => 'varchar(50)',
            'default'  => 'product',
            'required' => true,
            'editable' => false,
        ),
        'product_id'        => array(
            'type'     => 'number',
            'required' => true,
            'default'  => 0,
            'editable' => false,
            'label'    => '基础物料ID',
        ),
        'sales_material_bn' => array(
            'type'            => 'varchar(200)',
            'label'           => '销售物料编码',
            'order'           => 6,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'bn'                => array(
            'type'            => 'varchar(200)',
            'required'        => true,
            'label'           => '基础物料编码',
            'order'           => 7,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'name'              => array(
            'type'            => 'varchar(255)',
            'default'         => '',
            'label'           => '基础物料名称',
            'order'           => 8,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'price'             => array(
            'type'            => 'money',
            'default'         => 0,
            'label'           => '原价',
            'order'           => 9,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'nums'              => array(
            'type'            => 'mediumint',
            'required'        => true,
            'label'           => '销售数量',
            'order'           => 10,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'pmt_price'         => array(
            'type'            => 'money',
            'default'         => '0',
            'editable'        => false,
            'label'           => '基础物料优惠价',
            'order'           => 11,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'sale_price'        => array(
            'type'            => 'money',
            'default'         => '0',
            'label'           => '销售价',
            'order'           => 12,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'apportion_pmt'     => array(
            'type'            => 'money',
            'default'         => '0',
            'label'           => '平摊优惠金额',
            'order'           => 13,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'sales_amount'      => array(
            'type'            => 'money',
            'default'         => '0',
            'label'           => '销售额',
            'order'           => 14,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'number',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'platform_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '平台承担金额（不包含支付优惠）',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'settlement_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '结算金额',//客户实付 + 平台支付总额
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'actually_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '客户实付',// 已支付金额 减去平台支付优惠，加平台支付总额
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'platform_pay_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '支付优惠金额',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'return_num' => array(
            'type'    => 'number',
            'default' => '0',
            'label'   => '退款数量',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'return_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '退款金额',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'delivery_time'     => array(
            'type'            => 'time',
            'label'           => '发货时间',
            'comment'         => '发货时间',
            'editable'        => false,

            'order'           => 15,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'time',
            'filterdefault'   => true,
        ),
        'order_create_time' => array(
            'type'            => 'time',
            'label'           => '订单下单时间',
            'editable'        => false,
            'width'           => 130,
            'order'           => 16,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'time',
            'filterdefault'   => true,
        ),
        'order_pay_time'    => array(
            'type'            => 'time',
            'label'           => '订单支付时间',
            'editable'        => false,
            'width'           => 130,
            'order'           => 17,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'time',
            'filterdefault'   => true,
        ),
        'sale_time'         => array(
            'type'            => 'time',
            'label'           => '销售时间',
            'editable'        => false,
            'width'           => 130,
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 17,
        ),
        's_type'            => array(
            'type'    => 'varchar(50)',
            'label'   => '销售类型',
            'default' => 'zx',
        ),
        'org_id'               => array(
            'type'            => 'table:operation_organization@ome',
            'label'           => '运营组织',
            'editable'        => false,
            'width'           => 60,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 8,
        ),
        'addon' => array(
            'type'     => 'longtext',
            'editable' => false,
            'label'    => '扩展字段',
            'comment'  => '扩展字段',
        ),
        'settlement_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '结算金额',//客户实付 + 平台支付总额
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'platform_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '平台支付总额',//平台优惠 + 平台支付优惠
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'at_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default_in_list' => false,
            'in_list'         => false,
            'default'         => 'CURRENT_TIMESTAMP',
        ),
        'up_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default_in_list' => false,
            'in_list'         => false,
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ),
        'oid'               => array(
            'type'     => 'varchar(50)',
            'default'  => 0,
            'editable' => false,
            'label'    => '子订单号',
            'in_list'         => true,
            'default_in_list' => true,
        ),
    ),
    'index'   => array(
        'ind_delivery_order_item_id' => array(
            'columns' => array(
                0 => 'order_item_id',
                1 => 'delivery_id',
            ),
            'prefix'  => 'unique',
        ),
        'ind_delivery_id'            => array(
            'columns' => array(
                0 => 'delivery_id',
            ),
        ),
       
        'ind_order_id'               => array(
            'columns' => array(
                0 => 'order_id',
            ),
        ),
        'ind_bn'                     => array(
            'columns' => array(
                0 => 'bn',
            ),
        ),
        'ind_order_bn'              => array(
            'columns' => array(
                0 => 'order_bn',
            ),
        ),
        'ind_delivery_bn'           => array(
            'columns' => array(
                0 => 'delivery_bn',
            ),
        ),
        'ind_sale_time' => array(
            'columns' => array(
                0 => 'sale_time',
            ),
        ),
        'idx_at_time'           => array(
            'columns' => array(
                0 => 'at_time'
            )
        ),
        'idx_up_time'           => array(
            'columns' => array(
                0 => 'up_time',
                1 => 'delivery_id',
            )
        ),
        'idx_delivery_time'     => array(
            'columns' => array(
                0 => 'delivery_time'
            )
        ),
        'idx_oid'     => array(
            'columns' => array(
                0 => 'oid'
            )
        ),
        'idx_bn_orgid_deliverytime' => array(
            'columns' => array(
                0 => 'bn',
                1 => 'org_id',
                2 => 'delivery_time',
            )
        ),
    ),
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
    'charset' => 'utf8mb4',
);
