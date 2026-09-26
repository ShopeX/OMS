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

$db['order_objects']=array (
  'columns' =>
  array (
     'obj_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'order_id' =>
    array (
      'type' => 'table:orders@archive',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '订单ID',
    ),
    'obj_type' =>
    array (
      'type' => 'varchar(50)',
      'default' => '',
      'required' => true,
      'editable' => false,
      'comment' => '商品对象类型',
    ),
    'obj_alias' => array(
        'type' => 'varchar(255)',
        'editable' => false,
    ),
    'shop_goods_id' => array(
        'type' => 'varchar(50)',
        'editable' => false,
    ),
    'goods_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '商品ID',
    ),
    'bn' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'is_title' => true,
      'comment' => '货号',
    ),
    'name' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'comment' => '名称',
    ),
    'price' =>
    array (
      'type' => 'money',
      'default' => '0',
      'required' => true,
      'editable' => false,
      'comment' => '单价',
    ),
    'amount' =>
    array (
      'type' => 'money',
      'default' => '0',
      'required' => true,
      'editable' => false,
      'comment' => '合计',
    ),
    'quantity' =>
    array (
      'type' => 'number',
      'default' => 1,
      'required' => true,
      'editable' => false,
      'comment' => '数量',
    ),
    'pmt_price' =>
    array (
      'type' => 'money',
      'default' => '0',
      'editable' => false,
      'comment' => '优惠金额',
    ),
    'sale_price' =>
    array (
      'type' => 'money',
      'default' => '0',
      'editable' => false,
      'comment' => '销售金额',
    ),
    'oid' => 
    array (
      'type' => 'varchar(50)',
      'default' => 0,
      'editable' => false,
      'label' => '子订单号',
    ),
    'divide_order_fee' =>
    array (
        'type' => 'money',
        'editable' => false,
        'label' => '分摊之后的实付金额',
    ),
    'part_mjz_discount' =>
    array (
        'type' => 'money',
        'editable' => false,
        'label' => '优惠分摊',
    ),
    'platform_amount' =>
    array (
        'type' => 'money',
        'default' => '0',
        'editable' => false,
        'label' => '平台承担金额（不包含支付优惠）',
    ),
    'settlement_amount' =>
    array (
        'type' => 'money',
        'default' => '0',
        'editable' => false,
        'label' => '结算金额',
    ),
    'actually_amount' =>
    array (
        'type' => 'money',
        'default' => '0',
        'editable' => false,
        'label' => '客户实付',
    ),
    'platform_pay_amount' =>
    array (
        'type' => 'money',
        'default' => '0',
        'editable' => false,
        'label' => '支付优惠金额',
    ),
     'refund_money' => array (
         'type' => 'money',
         'editable' => false,
         'label' => '退款金额',
         'default'=>0,
         'comment' => '子单退款金额',
     ),
     'delete' => array(
        'type' => 'bool',
        'default' => 'false',
        'editable' => false,
    ),
    'ship_status' => array(
        'type' => array(
            0 => '未发货',
            1 => '已发货',
            2 => '部分发货',
            3 => '部分退货',
            4 => '已退货',
        ),
        'default' => '0',
        'label'   => '子订单状态',
    ),
    'presale_status' => array(
        'type' => array(
            0 => '非预售',
            1 => '预售',
        ),
        'default' => '0',
        'label'   => '预售状态',
    ),
  ),
  'index' =>
  array (
    'idx_c_order_id' =>
    array (
        'columns' =>
        array (
          0 => 'order_id',
        ),
    ),
  ),
  'comment' => '归档订单商品对象表',
  'engine' => 'innodb',
  'version' => '$Rev: 40912 $',
);