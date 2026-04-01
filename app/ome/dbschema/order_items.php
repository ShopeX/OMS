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

$db['order_items']=array(
  'columns' =>
  array(
    'item_id' =>
    array(
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
      'comment' => '自增主键ID',
    ),
    'order_id' =>
    array(
      'type' => 'table:orders@ome',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '订单ID,关联ome_orders.order_id'
    ),
    'obj_id' =>
    array(
      'type' => 'table:order_objects@ome',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '订单子单ID,order_objects.obj_id'
    ),
    'shop_goods_id' =>
    array(
      'type' => 'varchar(50)',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '平台商品ID'
    ),
    'product_id' =>
    array(
      'type' => 'table:products@ome',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '基础物料ID,关联material_basic_material.bm_id',
    ),
    'shop_product_id' =>
    array(
      'type' => 'varchar(50)',
      'editable' => false,
      'required' => true,
      'default' => 0,
      'comment' => '平台SKU ID',
    ),
    'bn' =>
    array(
      'type' => 'varchar(40)',
      'editable' => false,
      'is_title' => true,
      'comment' => '基础物料编码'
    ),
    'name' =>
    array(
      'type' => 'varchar(200)',
      'editable' => false,
      'comment' => '基础物料名称',
    ),
    'cost' =>
    array(
      'type' => 'money',
      'editable' => false,
      'comment' => '订单行明细成本价',
    ),
    'price' =>
    array(
      'type' => 'money',
      'default' => '0',
      'required' => true,
      'editable' => false,
      'comment' => '订单行明细零售价',
    ),
    'pmt_price' =>
    array(
      'type' => 'money',
      'default' => '0',
    'editable' => false,
    'comment' => '订单行明细优惠小计',
    ),
    'sale_price' =>
    array(
      'type' => 'money',
      'default' => '0',
        'editable' => false,
        'comment' => '订单行明细销售小计,公式: amount-pmt_price',
    ),
    'amount' =>
    array(
      'type' => 'money',
      'editable' => false,
      'comment' => '订单行明细零售小计,公式: price * nums',
    ),
    'refund_money' => array(
        'type' => 'money',
        'editable' => false,
        'label' => '退款费用',
        'default'=>0,
        'comment' => '订单行明细退款金额',
    ),
    'weight' =>
    array(
      'type' => 'money',
      'editable' => false,
      'comment' => '订单行明细重量,单位:g',
    ),
    'nums' =>
    array(
      'type' => 'number',
      'default' => 1,
      'required' => true,
      'editable' => false,
      'sdfpath' => 'quantity',
      'comment' => '订单行明细购买数量',
    ),
    'split_num' =>
    array(
      'type' => 'number',
      'default' => 0,
      'required' => true,
      'editable' => false,
      'comment' => '订单行明细拆分数量',
    ),
    'sendnum' =>
    array(
      'type' => 'number',
      'default' => 0,
      'required' => true,
      'editable' => false,
      'comment' => '订单行明细发货数量',
    ),
    'addon' =>
    array(
      'type' => 'longtext',
      'editable' => false,
      'comment' => '订单行明细附加信息,格式:unserialize',
    ),
    'item_type' =>
    array(
      'type' => 'varchar(50)',
      'default' => 'product',
      'required' => true,
      'editable' => false,
      'comment' => '订单行明细类型,可选值:product(商品),pkg(捆绑商品),gift(赠品),giftpackage(礼包),lkb(福袋),pko(多选一)',
    ),
    'return_num' =>
    array(
      'type' => 'number',
      'default' => 0,
      'editable' => false,
      'label' => '已退货量',
      'comment' => '订单行明细已退货数量',
    ),
    'divide_order_fee' => array(
          'type' => 'money',
          'editable' => false,
          'label' => '分摊之后的实付金额',
          'comment' => '订单实付金额,公式: sale_price-part_mjz_discount',
    ),
    'part_mjz_discount' => array(
          'type' => 'money',
          'editable' => false,
          'label' => '优惠分摊',
          'comment' => '订单优惠分摊,采用子单part_mjz_discount按价格贡献比分摊',
    ),
    'platform_amount' => array(
        'type'    => 'money',
        'default' => '0',
        'label'   => '平台承担金额（不包含支付优惠）',
    ),
    'settlement_amount' => array(
        'type'    => 'money',
        'default' => '0',
        'label'   => '结算金额',//客户实付 + 平台支付总额
    ),
    'actually_amount' => array(
        'type'    => 'money',
        'default' => '0',
        'label'   => '客户实付',// 已支付金额 减去平台支付优惠，加平台支付总额
    ),
    'platform_pay_amount' => array(
        'type'    => 'money',
        'default' => '0',
        'label'   => '支付优惠金额',
    ),
    'protect_price' => array(
          'type' => 'money',
          'editable' => false,
          'label' => '价保费用',
    ),
    'is_wms_gift' => array(
        'type' => 'bool',
        'default' => 'false',
        'label' => '是否WMS赠品',
        'editable' => false,
        'filtertype' => 'normal',
        'in_list' => true,
        'default_in_list' => false,
    ),
    'luckybag_id' => array(
        'type' => 'int unsigned',
        'required' => true,
        'default'  => 0,
        'editable' => false,
        'label' => '福袋组合ID',
        'comment' => '福袋组合ID',
    ),
    'item_line_no' => array(
          'type' => 'int(10)',
          'default' => 0,
          'editable' => false,
          'label' => '行号',
          'in_list' => false,
          'default_in_list' => false,
    ),
    'delete' =>
    array(
      'type' => 'bool',
      'default' => 'false',
      'editable' => false,
      'comment' => '删除状态,可选值:true(是),false(否)'
    ),
    'score' =>
        array (
            'type' => 'number',
            'editable' => false,//(协同版)
        ),
    'sell_code' =>
        array (
            'type' => 'varchar(32)',
            'editable' => false,
            'default' => '',
            'comment' => '销售编码',//(协同版)
        ),
    'promotion_id' =>
        array (
            'type' => 'varchar(32)',
            'editable' => false,
        
            'comment' => '优惠编码',//(协同版)
        ),
  ),
  'index' => array(
      'idx_bn' => array('columns' => array('bn')),
  ),
  'comment' => '订单行明细表,用于存储订单最小发货单位商品,与子单是一对多关系.比如捆绑商品就是一个子单对应多个订单行明细',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
