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

$db['statement']=array (
  'columns' => 
  array (
    'statement_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 110,
      'editable' => false,
    ),
    'supplier_id' => 
    array (
      'type' => 'table:supplier',
      'required' => true,
      'label' => '供应商ID',
      'width' => 110,
      'editable' => false,
    ),
    'supplier_bn' => 
    array (
      'type' => 'varchar(32)',
      'label' => '供应商编号',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'object_bn' =>                  //TODO:单据bn，名字修正？
    array (
      'type' => 'varchar(32)',
      'editable' => false,
      'width' => 130,
      'label' => '单据号',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'statement_time' => 
    array (
      'type' => 'time',
      'label' => '结算时间',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'object_type' =>
    array (
      'type' => 
      array (
        1 => '赊购入库',
        2 => '现款结算',
        3 => '采购退货',
      ),
      'required' => true,
      'default' => 1,
      'editable' => false,
      'label' => '业务类型',
      'width' => 60,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'object_id' =>                  //TODO:单据id，名字修正？
    array (
      'type' => 'number',
      'editable' => false,
    ),
    'initial_pay' =>
    array (
      'type' => 'money',
      'comment' => '期初应付',
      'editable' => false,
      'width' => 60,
      'label' => '期初应付',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'pay_add' =>
    array (
      'type' => 'money',
      'comment' => '本期增加应付',
      'editable' => false,
      'width' => 80,
      'label' => '本期增加应付',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'paid' =>
    array (
      'type' => 'money',
      'comment' => '本期已付',
      'editable' => false,
      'width' => 60,
      'label' => '本期已付',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'final_pay' =>
    array (
      'type' => 'money',
      'comment' => '期末应付',
      'editable' => false,
      'width' => 60,
      'label' => '期末应付',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'initial_receive' =>
    array (
      'type' => 'money',
      'comment' => '期初应收',
      'editable' => false,
      'width' => 60,
      'label' => '期初应收',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'receive_add' =>
    array (
      'type' => 'money',
      'comment' => '本期增加应收',
      'editable' => false,
      'width' => 80,
      'label' => '本期增加应收',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'received' =>
    array (
      'type' => 'money',
      'comment' => '本期已收',
      'editable' => false,
      'width' => 60,
      'label' => '本期已收',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'final_receive' =>
    array (
      'type' => 'money',
      'comment' => '期末应收',
      'editable' => false,
      'width' => 60,
      'label' => '期末应收',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'difference' =>
    array (
      'type' => 'money',
      'comment' => '差额',
      'editable' => false,
      'width' => 60,
      'label' => '差额',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
  ),
  'index' => 
  array (
    'ind_object_id' => 
    array (
      'columns' => 
      array (
        0 => 'object_id',
      ),
    ),
     'ind_object_type' => 
    array (
      'columns' => 
      array (
        0 => 'object_type',
      ),
    ),
  ),
  'comment' => '结算单',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
