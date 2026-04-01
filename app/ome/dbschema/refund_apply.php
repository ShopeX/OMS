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

$db['refund_apply'] = array(
    'columns' => array(
        'apply_id'         => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'editable' => false,
        ),
        'refund_apply_bn'  => array(
            'type'          => 'varchar(32)',
            'required'      => true,
            'default'       => '',
            'label'         => '退款申请单号',
            'width'         => 200,
            'editable'      => false,
            'in_list'       => true,
            'default_in_list' => true,
            'is_title'      => true,
            'searchtype'    => 'nequal',
            'filtertype'    => 'yes',
            'filterdefault' => true,
        ),
        'order_id'         => array(
            'type'            => 'table:orders@ome',
            'required'        => true,
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
            'label'           => '订单号',
            'editable'        => false,
        ),
        'pay_type'         => array(
            'type'          => array(
                'online'  => '在线支付',
                'offline' => '线下支付',
                'deposit' => '预存款支付',
            ),
            'default'       => 'online',
            'required'      => true,
            'label'         => '支付类型',
            'width'         => 110,
            'editable'      => false,
            'filtertype'    => 'yes',
            'filterdefault' => true,
            'in_list'       => true,
        ),
        'account'          => array(
            'type'     => 'varchar(50)',
            'editable' => false,
            'in_list'  => true,
            'label'    => '退款帐号',
        ),
        'bank'             => array(
            'type'            => 'varchar(50)',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '退款银行',
        ),
        'pay_account'      => array(
            'type'     => 'varchar(50)',
            'editable' => false,
            'in_list'  => true,
            'label'    => '收款帐号',
        ),
        'money'            => array(
            'type'            => 'money',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '申请退款金额',
            'width'           => '70',
        ),
        'refunded'         => array(
            'type'            => 'money',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '已退款金额',
            'width'           => '70',
        ),
        'payment'          => array(
            'type'          => 'table:payment_cfg@ome',
            'editable'      => false,
            'in_list'       => true,
            'label'         => '付款方式',
            'filtertype'    => 'yes',
            'filterdefault' => true,
        ),
        'memo'             => array(
            'type'            => 'text',
            'editable'        => false,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '退款原因',
        ),
        'create_time'      => array(
            'type'            => 'time',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '申请时间',
            'width'           => 130,
            'filtertype'      => 'time',
            'filterdefault'   => true,
        ),
        'last_modified'    => array(
            'label'    => '最后更新时间',
            'type'     => 'last_modify',
            'width'    => 130,
            'editable' => false,
            'in_list'  => true,
        ),
        'apply_op_id'      => array(
            'type'            => 'table:account@pam',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '申请操作员',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'verify_op_id'     => array(
            'type'     => 'table:account@pam',
            'editable' => false,
        ),
        'status'           => array(
            'type'            => array(
                0 => '未审核',
                1 => '审核中',
                2 => '已接受申请',
                3 => '已拒绝',
                4 => '已退款',
                5 => '退款中',
                6 => '退款失败',
                10 => '卖家拒绝退款',
            ),
            'default'         => '0',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '退款状态',
            'filtertype'      => 'yes',
            'filterdefault'   => true,
        ),
        'disabled'         => array(
            'type'     => 'bool',
            'required' => true,
            'default'  => 'false',
            'editable' => false,
        ),
        'shop_id'          => array(
            'type'          => 'table:shop@ome',
            'label'         => '来源店铺',
            'width'         => 75,
            'editable'      => false,
            'in_list'       => true,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ),
        'delivery_mode'     => array(
            'type'     => 'varchar(32)',
            'label'    => '来源店铺发货模式',
            'default'  => '',
            'comment'  => '可选值请参考ome_shop表的delivery_mode (self=>自发,jingxiao=>经销)'
        ),
        'return_id'        => array(
            'type'     => 'int(10)',
            'editable' => false,
            'comment'  => '售后ID',
        ),
        'reship_id'        => array(
            'type'     => 'int(10)',

            'editable' => false,
            'comment'  => '退换货ID',
        ),
        'addon'            => array(
            'type'     => 'longtext',
            'editable' => false,
        ),
        'refund_refer' => array(
            'type' => array(
                0 => '售前',
                1 => '售后',
            ),
            'default' => '0',
            'label' => '退款来源',
            'in_list' => true,
            'default_in_list' => false,
            'filtertype' => 'yes',
            'filterdefault' => true,
        ),
        'bcmoney'          => array(
            'type'    => 'money',
            'label'   => app::get('ome')->_('补偿费用'),
            'in_list' => true,
            'default' => 0,
        ),
        'real_refund_amount' => array(
            'type' => 'money',
            'label' => '买家实退',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
        ),
        'cs_status' => array(
            'type' => 'varchar(50)',
            'default'=>'',
            'editable' => false,
            'label' => '客服介入状态',
            'width' =>65,
            'in_list' => true,
            'default_in_list' => true,
        ),
        'product_data' => array(
            'type'     => 'longtext',
            'editable' => false,
            'label'    => '售后申请商品',
        ),
        'source'           => array(
            'type'          => 'varchar(50)',
            'default'       => 'local',
            'editable'      => false,
            'label'         => '来源',
            'in_list'       => true,
            'filterdefault' => true,
        ),
        'source_status' => array(
            'type'          => 'varchar(255)',
            'default'       => '',
            'label'         => '平台状态',
            'in_list'       => true,
        ),
        'shop_type'        => array(
            'type'          => 'varchar(50)',
            'label'         => '店铺类型',
            'width'         => 75,
            'editable'      => false,
            'in_list'       => true,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ),
        'mark_text'        => array(
            'type'     => 'longtext',
            'label'    => '商家备注',
            'editable' => false,
        ),
        'problem_id'       => array(
            'type'  => 'table:return_product_problem@ome',
            'label' => '问题类型ID',
        ),
        'outer_lastmodify' => array(
            'label'    => '前端店铺最后更新时间',
            'type'     => 'time',
            'width'    => 130,
            'editable' => false,
        ),
        'archive'          => array(
            'type'     => 'tinyint unsigned',
            'required' => true,
            'default'  => 0,
            'editable' => false,
        ),
        'org_id'           => array(
            'type'            => 'table:operation_organization@ome',
            'label'           => '运营组织',
            'editable'        => false,
            'width'           => 60,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'oid'              => array(
            'type'     => 'varchar(255)',
            'default'  => 0,
            'editable' => false,
            'label'    => '子订单号',
            'in_list'       => true,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ),
        'bn'               => array(
            'type'     => 'varchar(755)',
            'editable' => false,
            'label'    => '货号',
            'in_list'       => true,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ),
        'abnormal_status'  => array(
            'type'     => 'bigint(20)',
            'label'    => '异常状态标识位',
            'editable' => false,
            'default'  => '0',
        ),
        'bool_type'    => array(
            'type'     => 'bigint(20)',
            'label'    => '单据种类',
            'editable' => false,
            'default'  => '0',
        ),
        'flag' => array(
            'type'     => 'varchar(255)',
            'label'    => '标识',
            'editable' => false,
            'in_list'       => true,
            'default'  => '',
        ),

        'tag_type' => array(
            'type' => array(
                '0' => '常规退款',
                '1' => '价保退款',
                '2' => '返现退款',
                '3' => '赔付退款',
                '5' => '极速退款',
                '6' => '售后仅退款',
                '7' => '发货前退款',
                '8' => '强制退款',
            ),
            'default' => '0',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'label' => '退款类型',
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),
        'cost_freight'       => array(
          'type'       => 'money',
          'default'    => '0',
       
          'label'      => '运费',
          'width'      => 70,
          'editable'   => false,
        
          'in_list'    => true,
      ),
        'order_source' => array (
            'type'            => 'varchar(50)',
            'label'           => '来源渠道',
            'default'         => 'direct',
            'filtertype'      => 'yes',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'negotiate_sync_status' => array(
            'type' => array(
                'none' => '不可协商',
                'pending' => '可协商',
                'succ' => '协商发起成功',
                'fail' => '协商发起失败',
                'running' => '协商发起中',
            ),
            'default' => 'pending',
            'editable' => true,
            'label' => '协商状态',
            'comment' => '协商状态',
            'in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),
        'negotiate_sync_msg' => array(
            'type' => 'varchar(500)',
            'default' => '',
            'editable' => false,
            'label' => '协商同步消息',
            'comment' => '协商同步消息',
            'in_list' => false,
        ),
    ),
    'index' => array(
        'ind_refund_apply_bn_shop' => array(
            'columns' => array(
                0 => 'refund_apply_bn',
                1 => 'shop_id',
            ),
            'prefix'  => 'unique',
        ),
        'ind_reship_id' => array(
            'columns' => array(
                0 => 'reship_id',
            ),
        ),
        
        'ind_status_disabled' => array(
            'columns' => array(
                'status','disabled','delivery_mode'
            ),
        ),
        'idx_return_id' => array('columns' => array('return_id')),

        'idx_abnormal_status' => array('columns' => array('abnormal_status')),
        'idx_oid' => array('columns' => array('oid')),
        'idx_bn' => array('columns' => array('bn')),
        'idx_bool_type' => array('columns'=>array('bool_type')),
        'idx_tag_type' => array('columns'=>array('tag_type')),
        'ind_source_status' => array(
            'columns' => array(
                0 => 'source_status',
            ),
        ),
        'idx_delivery_mode' => array(
            'columns' => array(
                0 => 'delivery_mode',
            ),
        ),
        'ind_refund_refer' => array(
            'columns' => array(
                'refund_refer',
            ),
        ),
        'idx_order_source' => array('columns'=>array('order_source')),
        'idx_create_time' => array('columns'=>array('create_time')),
        'idx_disabled_org_deliverymode' => [
            'columns'   =>  [
                'disabled',
                'org_id',
                'delivery_mode',
            ],
        ],
        'idx_refundrefer_orderid' => array(
            'columns' => array(
                'refund_refer',
                'order_id',
            )
        ),
    ),
    'comment' => '退款单申请',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
    'charset' => 'utf8mb4',
);
