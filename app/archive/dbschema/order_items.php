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

$db['order_items']=array (
  'columns' => 
  array (
    'item_id' => 
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
    'obj_id' => 
    array (
      'type' => 'table:order_objects@archive',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '订单商品对象ID',
    ),
    'shop_goods_id' => array (
        'type' => 'varchar(50)',
        'editable' => false,
    ),
    'product_id' => 
    array (
      'type' => 'table:products@ome',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '货品ID',
    ),
    'shop_product_id' => array (
        'type' => 'varchar(50)',
        'editable' => false,
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
      'comment' => '货品名称',
    ),
    'cost' => 
    array (
      'type' => 'money',
      'editable' => false,
      'comment' => '成本价',
    ),
    'price' => 
    array (
      'type' => 'money',
      'default' => '0',
      'required' => true,
      'editable' => false,
      'comment' => '单价',
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
    'amount' => 
    array (
      'type' => 'money',
      'editable' => false,
      'comment' => '合计',
    ),
    'refund_money' => array(
        'type' => 'money',
        'editable' => false,
        'label' => '退款费用',
        'default'=>0,
        'comment' => '订单行明细退款金额',
    ),
    'weight' =>
    array (
      'type' => 'money',
      'editable' => false,
      'comment' => '重量',
    ),
    'nums' => 
    array (
      'type' => 'number',
      'default' => 1,
      'required' => true,
      'editable' => false,
      'sdfpath' => 'quantity',
      'comment' => '数量',
    ),
    'sendnum' => 
    array (
      'type' => 'number',
      'default' => 0,
      'required' => true,
      'editable' => false,
      'comment' => '已发数量',
    ),
    'addon' => 
    array (
      'type' => 'longtext',
      'editable' => false,
      'comment' => '附加信息',
    ),
    'item_type' => 
    array (
      'type' => 'varchar(50)',
      'default' => 'product',
      'required' => true,
      'editable' => false,
      'comment' => '货品明细类型',
    ),
    'return_num' => 
    array (
      'type' => 'number',
      'default' => 0,
      'editable' => false,
      'label' => '已退货量',
    ),
    'delete' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'editable' => false,
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
    'score' =>
        array (
            'type' => 'number',
            'editable' => false,
        ),
    'sell_code' =>
        array (
            'type' => 'varchar(32)',
            'editable' => false,
            'default' => '',
            'comment' => '销售编码',
        ),
    'promotion_id' =>
        array (
            'type' => 'varchar(32)',
            'editable' => false,
        
            'comment' => '优惠编码',
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
    'idx_c_obj_id' =>
    array (
        'columns' =>
        array (
          0 => 'obj_id',
        ),
    ),
  ),
  'comment' => '归档订单商品明细表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);