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

$db['ar'] = array(
    'columns'      => array(
        'ar_id'                 => array(
            'type'     => 'int unsigned',
            //'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'editable' => false,
        ),
        'ar_bn'                 => array(
            'type'            => 'varchar(32)',
            //'required'        => true,
            'label'           => '单据编号',
            'searchtype'      => 'nequal',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'width'           => 180,
            'order'           => 5,
        ),
        'order_bn'              => array(
            'type'            => 'varchar(32)',
            //'required'        => true,
            'label'           => '业务订单号',
            'width'           => 240,
            'searchtype'      => 'nequal',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 1,
        ),
        'crc32_order_bn'        => array(
            'type'    => 'int unsigned',
            'label'   => '订单号的crc32表示',
            'comment' => '用于快速搜索订单号',
        ),
        'relate_order_bn'       => array(
            'type'            => 'varchar(32)',
            //'required'        => true,
            'label'           => '关联订单号',
            'width'           => 240,
            'searchtype'      => 'nequal',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => false,
            'order'           => 7,
        ),
        'crc32_relate_order_bn' => array(
            'type'    => 'int unsigned',
            'label'   => '订单号的crc32表示',
            'comment' => '用于快速搜索订单号',
        ),
        'channel_id'            => array(
            'type'     => 'varchar(32)',
            'editable' => false,
            'comment'  => '渠道ID',
        ),
        'channel_name'          => array(
            'type'            => 'varchar(255)',
            'label'           => '渠道名称',
            'width'           => 130,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            // 'filtertype' => 'normal',
            // 'filterdefault' => true,
            'order'           => 2,
        ),
        'member'                => array(
            'type'            => 'varchar(255)',
            'label'           => '客户/会员',
            'comment'         => '客户/会员 /交易对方ID',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'width'           => 60,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order'           => 4,
        ),
        'status'                => array(
            'type'            => 'tinyint',
            //'required'        => true,
            'default'         => 0,
            'label'           => '核销状态',
            'comment'         => '核销状态  未核销(0)、部分核销(1)、已核销(2)',
            'editable'        => false,
            'width'           => 65,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order'           => 11,
        ),
        'verification_time'     => array(
            'type'     => 'time',
            'default'  => 0,
            'comment'  => '核销时间',
            'editable' => false,
            'label'           => '核销时间',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'time',
            'filterdefault'   => true,
        ),
        'type'                  => array(
            'type'            => 'tinyint',
            //'required'        => true,
            'label'           => '业务类型',
            'comment'         => '销售出库、销售退货、销售换货、销售退款、售后退货、售后换货、售后退款',
            'width'           => 65,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order'           => 5,
        ),
        'charge_status'         => array(
            'type'     => 'tinyint',
            //'required' => true,
            'default'  => 0,
            'label'    => '记账状态',
            'comment'  => '记账状态  未记账(0)、已记账(1)',
            // 'width' => 65,
            // 'editable' => false,
            // 'in_list' => true,
            // 'default_in_list' => true,
            // 'filtertype' => 'normal',
            // 'filterdefault' => true,
            'order'    => 14,
        ),
        'charge_time'           => array(
            'type'  => 'time',
            'label' => '记账日期',
            // 'editable' => false,
            // 'in_list' => true,
            // 'default_in_list' => true,
            // 'filtertype' => 'time',
            // 'filterdefault' => true,
            'order' => 15,
        ),
        'monthly_id'            => array(
            'type'     => 'int unsigned',
            'label'    => '所属账期',
            'editable' => false,
        ),
        'monthly_item_id'            => array(
            'type'     => 'int unsigned',
            'label'    => '所属账期明细',
            'default'  => 0,
            'editable' => false,
        ),
        'premonthly_id'         => array(
            'type'     => 'int unsigned',
            'label'    => '往期账期',
            'editable' => false,
        ),
        'monthly_status'        => array(
            'type'            => 'tinyint',
            'default'         => 0,
            'label'           => '是否关账',
            'comment'         => '是否关账 未关账（0），已关账（1）',
            'width'           => 65,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 16,
        ),
        'create_time'           => array(
            'type'     => 'time',
            'label'    => '进入系统日期',
            'editable' => false,
        ),
        'trade_time'            => array(
            'type'     => 'time',
            'label'    => '账单日期',
            //'required' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'filterdefault' => true,
            'order'    => 3,
        ),
        'delivery_time'            => array(
            'type'     => 'time',
            'label'    => '发货时间',
            //'required' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'filterdefault' => true,
            'order'    => 30,
        ),
        'money'                 => array(
            'type'            => 'money',
            //'required'        => true,
            'label'           => '应收金额',
            'comment'         => '金额,区分正负号',
            'width'           => 65,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 10,
        ),
        'confirm_money'         => array(
            'type'            => 'money',
            //'required'        => true,
            'label'           => '已核销金额',
            'width'           => 65,
            'default'         => 0,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 12,
        ),
        'unconfirm_money'       => array(
            'type'            => 'money',
            //'required'        => true,
            'label'           => '未核销金额',
            'width'           => 65,
            'default'         => 0,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 13,
        ),
        'actually_money'       => array(
            'type'            => 'money',
            //'required'        => true,
            'label'           => '客户实付',
            'width'           => 65,
            'default'         => 0,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 13,
        ),
        'platform_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '平台承担金额',
            'width'           => 65,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'comment' => '平台承担金额（不包含支付优惠）',
        ),
        'addon'                 => array(
            'type'     => 'serialize',
            'comment'  => '附加字段Serialize(array(‘sale_money’=>’商品成交金额’,’fee_money’=>’运费收入’))',
            'editable' => false,
        ),
        'auto_flag'             => array(
            'type'     => 'tinyint',
            //'required' => true,
            'comment'  => '自动核销标识 未核销（0） 已核销（1）',
            'default'  => 0,
            'editable' => false,
        ),
        'verification_flag'     => array(
            'type'     => 'tinyint',
            'comment'  => '能否应收对冲标识 不可（0） 可（1）',
            'default'  => 0,
            'editable' => false,
            'label'    => '应收对冲标识',
            'in_list'  => true,
            'default_in_list' => true,
        ),
        'serial_number'         => array(
            'type'            => 'varchar(64)',
            //'required'        => true,
            'label'           => '业务流水号',
            'comment'         => '应收流水号',
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 18,
        ),
        'unique_id'             => array(
            'type'     => 'varchar(32)',
            'required' => true,
            'comment'  => '唯一标识',
            'editable' => false,
        ),
        'ar_type'               => array(
            'type'            => 'tinyint',
            'default'         => 0,
            //'required'        => true,
            'label'           => '单据类型',
            'comment'         => '资金流向 0流入 ，1流出',
            'editable'        => true,
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'verification_status'   => array(
            'type'    => 'tinyint',
            'default' => 0,
            'label'   => '核销状态',
            'comment' => '核销状态  等待核销(0)、正常核销(1)、差异核销(2)、强制核销(3) ',

        ),
        'memo'                  => array(
            'type'     => 'longtext',
            'label'    => '备注',
            'editable' => false,
        ),
        'gap_type'              => array(
            'type'     => 'varchar(255)',
            'label'    => '差异类型',
            'editable' => false,
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'is_check'              => array(
            'type'    => 'tinyint',
            'default' => 0,
            'label'   => '自动核销检查状态',
            'comment' => '  未检查(0)、检查中(1)、已检查(2)',

        ),
        'up_time'      => array(
            'type'    => 'TIMESTAMP',
            'label'   => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'   => 120,
            'in_list' => true,
            'default_in_list' => false,
            'order'   => 11,
        ),
        'at_time'      => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
            'width'           => 120,
            'in_list'         => true,
            'default_in_list' => false,
            'order'           => 11,
        ),
    ),

    'index'        => array(
        'ind_order_bn'                => array(
            'columns' => array(
                0 => 'order_bn',
            ),
        ),
        'ind_crc32_order_bn'          => array(
            'columns' => array(
                0 => 'crc32_order_bn',
            ),
        ),
        'ind_relate_order_bn'         => array(
            'columns' => array(
                0 => 'relate_order_bn',
            ),
        ),
        'ind_crc32_relate_order_bn'   => array(
            'columns' => array(
                0 => 'crc32_relate_order_bn',
            ),
        ),
        'ind_auto_flag'               => array(
            'columns' => array(
                0 => 'auto_flag',
            ),
        ),
        'ind_verification_flag'       => array(
            'columns' => array(
                0 => 'verification_flag',
            ),
        ),
        'ind_serial_number'           => array(
            'columns' => array(
                0 => 'serial_number',
            ),
        ),
        'ind_unique_id'               => array(
            'columns' => array(
                0 => 'unique_id',
            ),
            'prefix'  => 'unique',
        ),
        'ind_channel_id'              => array(
            'columns' => array(
                0 => 'channel_id',
            ),
        ),
        'ind_delivery_time'              => array(
            'columns' => array(
                0 => 'delivery_time',
            ),
        ),
        'ind_member'                  => array(
            'columns' => array(
                0 => 'member',
            ),
        ),
        'ind_trade_verification_time' => array(
            'columns' => array(
                0 => 'trade_time',
                1 => 'verification_time',
            ),
        ),
        'ind_create_time'             => array(
            'columns' => array(
                0 => 'create_time',
            ),
        ),
        'ind_money'                   => array(
            'columns' => array(
                0 => 'money',
            ),
        ),
        'ind_unconfirm_money'         => array(
            'columns' => array(
                0 => 'unconfirm_money',
            ),
        ),
        'ind_confirm_money'           => array(
            'columns' => array(
                0 => 'confirm_money',
            ),
        ),
        'ind_status'                  => array(
            'columns' => array(
                'status','is_check','charge_status','order_bn','channel_id'
            ),
        ),
        'ind_charge_status'           => array(
            'columns' => array(
                0 => 'charge_status',
            ),
        ),
        'ind_charge_time'             => array(
            'columns' => array(
                0 => 'charge_time',
            ),
        ),
        'ind_monthly_status'          => array(
            'columns' => array(
                0 => 'monthly_status',
            ),
        ),
        'ind_monthly_id'              => array(
            'columns' => array(
                0 => 'monthly_id',
                1 => 'charge_status',
                2 => 'money',
                3 => 'ar_type',
            ),
        ),
        'ind_monthly_item_id'              => array(
            'columns' => array(
                0 => 'monthly_item_id',
            ),
        ),
        'ind_ar_type'                 => array(
            'columns' => array(
                0 => 'ar_type',
            ),
        ),
        'ind_ar_bn'                   => array(
            'columns' => array(
                0 => 'ar_bn',
            ),
        ),
        'ind_at_time' => array('columns' => array('at_time')),
        'ind_up_time' => array('columns' => array('up_time')),
    ),
    'ind_is_check' => array(
        'columns' => array(
            0 => 'is_check',
        ),
    ),
    'engine'       => 'innodb',
    'version'      => '$Rev:  $',
);
