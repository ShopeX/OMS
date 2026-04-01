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
$db['aftersale_items'] = array(
  'columns' =>
  array(
    'item_id' => array(
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
      'comment' => '明细ID',
      ),
    'aftersale_id' => 
    array(
      'type' => 'table:aftersale@sales',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '售后单据ID',
    ),  
    'obj_item_id' => 
    array(
      'type' => 'int unsigned',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'comment' => '来源明细ID',
    ),  
    'bn' =>
    array(
      'type' => 'varchar(200)',
      'editable' => false,
      'required' => false,
      'comment' => '基础物料编码',
    ),
    'product_name' =>
    array(
      'type' => 'varchar(200)',
      'editable' => false,
      'comment' => '基础物料名称',
    ),
    'product_id' =>
    array (
      'type' => 'int unsigned',
      'editable' => false,
      'comment' => '基础物料ID',
    ),
    'apply_num' =>
    array(
      'type' => 'number',
      'editable' => false,
      'required' => false,
      'default' => 1,
      'comment' => '申请数量',
    ),
    'num' =>
    array(
      'type' => 'number',
      'editable' => false,
      'required' => false,
      'default' => 1,
      'comment' => '数量',
    ),
    'defective_num' =>
    array (
      'type' => 'number',
      'editable' => false,
      'required' => true,
      'default' => 0,
      'label' => '不良品',
    ),
    'normal_num' =>
    array (
      'type' => 'number',
      'editable' => false,
      'required' => true,
      'default' => 0,
      'label' => '良品',
    ),
    'price' =>
    array(
      'type' => 'money',
      'default' => '0',
      'required' => false,
      'editable' => false,
      'comment' => '单价',
    ),
    'saleprice' =>
    array(
      'type' => 'money',
      'default' => '0',
      'required' => false,
      'editable' => false,
      'comment' => '销售价',
    ),
    'branch_id' =>
    array(
      'type' => 'table:branch@ome',
      'editable' => false,
      'label'=>'仓库ID',
      'comment'=>'仓库ID',
    ),
    'branch_name' =>
    array(
      'type' => 'varchar(200)',
      'editable' => false,
      'label'=>'仓库名称',
      'comment'=>'仓库名称',
    ),
    'return_type' =>
    array(
      'type' =>
      array(
        'return' => '退货',
        'change' => '换货',
            'refunded' => '退款',
             'refuse'=>'追回',
      ),
      'default' => 'return',
      'required' => true,
      'comment' => '售后类型',
      'editable' => false,
      'label' => '售后类型',
    ),
    'pay_type' => 
    array(
      'type' => 
      array(
        'online' => '在线支付',
        'offline' => '线下支付',
        'deposit' => '预存款支付',
      ),
      'default' => 'online',
      'required' => false,
      'label' => '支付类型',
      'width' => 110,
      'editable' => false,
    ),
    'account' =>
    array(
      'type' => 'varchar(50)',
      'editable' => false,
      'label' => '退款帐号',
    ),
    'bank' =>
    array(
      'type' => 'varchar(50)',
      'editable' => false,
      'label' => '退款银行',
    ),
    'pay_account' =>
    array(
      'type' => 'varchar(50)',
      'editable' => false,
      'label' => '收款帐号',
    ),
    'money' =>
    array(
      'type' => 'money',
      'editable' => false,
      'label' => '申请退款金额',
      'width' => '70',
    ),
    'refunded' =>
    array(
      'type' => 'money',
      'editable' => false,
      'label' => '已退款金额',
      'width' => '70',
    ),
    'payment' =>
    array(
      'type' => 'table:payment_cfg@ome',
      'editable' => false,
      'label' => '付款方式',
    ),
    'create_time' =>
    array(
      'type' => 'time',
      'editable' => false,
      'label' => '退款申请时间',
    ),
    'last_modified' => 
    array(
      'label' => '退款完成时间',
      'type' => 'last_modify',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
    ),
    'cost'              => array(
        'type'    => 'money',
        'default' => 0,
        'comment' => '成本价格',
    ),
    'cost_amount'       => array(
        'type'            => 'money',
        'default'         => 0,
        'label'           => '成本金额',
        'filterdefault'   => true,
        'default_in_list' => true,
        'comment'         => '数量*成本单价',
    ),
    'order_item_id' =>
    array (
      'type' => 'int unsigned',
      'editable' => false,
      'comment' => '原订单id',
    ),
    'item_type' => array(
        'type' => 'varchar(50)',
        'default' => 'product',
        'editable' => false,
        'default_in_list' => false,
        'in_list' => true,
        'label' => '物料类型',
    ),
    'sales_material_bn' => array(
        'type' => 'varchar(50)',
        'editable' => false,
        'default_in_list' => false,
        'in_list' => true,
        'label' => '销售物料编码',
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
    ),
    'platform_amount' => array(
        'type'    => 'money',
        'default' => '0',
        'label'   => '平台承担金额（不包含支付优惠）',
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
    'oid'     =>
    array(
        'type'     => 'varchar(50)',
        'default'  => 0,
        'editable' => false,
        'label'    => '子订单号',
    ),
  ),
  'index' =>
  array(
    'ind_create_time' =>
    array(
      'columns' =>
      array(
        0 => 'create_time',
      ),
    ),
    'ind_return_type' =>
    array(
      'columns' =>
      array(
        0 => 'return_type',
      ),
    ),    
    'ind_last_modified' =>
    array(
      'columns' =>
      array(
        0 => 'last_modified',
      ),
    ),
  ), 
  'engine' => 'innodb',
  'version' => '$Rev: 41996 $',
  'comment' => '售后单据明细',
  'charset' => 'utf8mb4',
);
