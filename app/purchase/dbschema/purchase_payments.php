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

$db['purchase_payments']=array (
  'columns' => 
  array (
    'payment_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'payment_bn' => 
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '付款单编号',
      'width' => 140,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'is_title' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'po_id' => 
    array (
      'type' => 'table:po',
      'label' => '采购单编号',
      'width' => 140,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'add_time' => 
    array (
      'type' => 'time',
      'label' => '制单日期',
      'width' => 80,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'supplier_id' => 
    array (
      'type' => 'table:supplier',
      'label' => '供应商',
      'width' => 120,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'po_type' => 
    array (
      'type' => 
      array (
        'cash' => '现款',
        'credit' => '预付款',
      ),
      'label' => '采购类型',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'operator' => 
    array (
      'type' => 'varchar(50)',
      'label' => '经办人',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'payable' => 
    array (
      'type' => 'money',
      'label' => '应付金额',
      'width' => 90,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'product_cost' => 
    array (
      'type' => 'money',
      'label' => '商品费用',
      'width' => 75,
      'default' => 0,
      'editable' => false,
      'in_list' => true,
    ),
    'delivery_cost' => 
    array (
      'type' => 'money',
      'label' => '物流费用',
      'width' => 75,
      'default' => 0,
      'editable' => false,
      'in_list' => true,
    ),
    'deposit' => 
    array (
      'type' => 'money',
      'label' => '预付金额',
      'width' => 90,
      'editable' => false,
      'in_list' => true,
    ),
    'paid' => 
    array (
      'type' => 'money',
      'label' => '结算金额',
      'width' => 90,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'statement_time' =>
    array (
      'type' => 'time',
      'editable' => false,
      'label' => '结算日期',
      'width' => 130,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'balance' => 
    array (
      'type' => 'money',
      'label' => '结算余额',
      'default_in_list' => false,
      'width' => 90,
      'editable' => false,
      'in_list' => true,
    ),
    'memo' => 
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'payment' =>
    array (
      'type' => 'table:payment_cfg@ome',
      'editable' => false,
    ),
    'paymethod' =>
    array (
      'type' => 'varchar(100)',
      'editable' => false,
    ),
    'op_id' =>
    array (
      'type' => 'table:account@pam',
      'editable' => false,
    ),
    
    'statement_status' =>
    array (
      'type' => 
      array (
        1 => '未结算',
        2 => '已结算',
        3 => '拒绝结算',
        4 => '部分结算'
      ),
      'default' => '1',
      'required' => true,
      'label' => '结算状态',
      'width' => 90,
      'editable' => false,
      'default_in_list' => false,
      'in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'tax_no' => 
    array (
      'type' => 'varchar(50)',
      'label' => '发票号',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
    ),
    'bank_no' => 
    array (
      'type' => 'varchar(50)',
      'label' => '银行账号',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
    ),
    'logi_no' => 
    array (
      'type' => 'varchar(50)',
      'label' => '物流运单号',
      'width' => 80,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
    ),
    'disabled' => 
    array (
      'type' => 'bool',
      'default' => 'false',
      'comment' => '无效',
      'editable' => false,
      'label' => '无效',
    ),
  ),
  'index' => 
  array (
    'ind_payment_bn' => 
    array (
      'columns' => 
      array (
        0 => 'payment_bn',
      ),
    ),
    'ind_statement_status' => 
    array (
      'columns' => 
      array (
        0 => 'statement_status',
      ),
    ),
  ),
  'comment' => '付款单',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
