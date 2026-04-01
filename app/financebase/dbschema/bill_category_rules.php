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

$db['bill_category_rules']=array (
  'columns' => 
  array (
    'rule_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'bill_category' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
      'label' => '具体类别',
      'comment' => '具体类别',
      'is_title' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' => 200,
      'order' => 10,
    ),
    'rule_content' => array (
        'type' => 'longtext',
        'label' => '规则内容',
    ),
    'business_type' => array (
        'type' => 'varchar(64)',
        'label' => '业务归属',
        'default_in_list' => true,
        'in_list' => true,
    ),
    'ordernum' => array (
      'type' => 'smallint(3) unsigned',
      'default' => 50,
      'label' => '排序',
      'comment' => '排序',
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
      'width' => 50,
      'order' => 30,
    ),
    'create_time' => array(
        'type' => 'time',
        'label' => '创建时间',
        'comment' => '创建时间',
        'editable' => false,
        'default_in_list' => true,
        'in_list' => true,
        'width' => 150,
        'order' => 50,
    ),
    'disabled' => 
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
    'split_type' => 
    array (
      'type' => 'varchar(32)',
      'default' => '',
      'editable' => false,
      'default_in_list' => false,
      'in_list' => true,
      'label' => '拆分维度'
    ),
    'split_rule' => 
    array (
      'type' => 'varchar(32)',
      'default' => '',
      'editable' => false,
      'default_in_list' => false,
      'in_list' => true,
      'label' => '拆分规则'
    ),
    'split_last_modify' => 
    array (
      'type' => 'time',
      'editable' => false,
      'default_in_list' => false,
      'in_list' => true,
      'label' => '设置最后修改时间'
    ),
    'account_id_plus' => 
    array (
      'type' => 'table:account_chart@financebase',
      'label' => '贷会计科目ID',
      'editable' => false,
      'default_in_list' => false,
      'in_list' => false,
      'order' => 60,
    ),
    'account_id_minus' => 
    array (
      'type' => 'table:account_chart@financebase',
      'label' => '借会计科目ID',
      'editable' => false,
      'default_in_list' => false,
      'in_list' => false,
      'order' => 65,
    ),
    'need_ar_verify' => array(
          'type' => 'tinyint(1)',
          'default' => '1',
          'label' => '是否需要核对AR',
          'comment' => '是否需要核对AR：0-无需核对AR，1-需核对AR',
          'editable' => false,
          'in_list' => true,
          'default_in_list' => true,
          'order' => 70,
    ),
    'at_time' => 
    array (
      'type' => 'TIMESTAMP',
      'label' => '创建时间',
      'default' => 'CURRENT_TIMESTAMP',
      'width' => 150,
      'in_list' => true,
      'default_in_list' => true,
      'order' => 80,
    ),
    'up_time' => 
    array (
      'type' => 'TIMESTAMP',
      'label' => '更新时间',
      'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
      'width' => 150,
      'in_list' => true,
      'default_in_list' => true,
      'order' => 90,
    ),
  ),
  'index' => array(
    'ind_bill_category' => array('columns' => array(0 => 'bill_category')),
    'ind_ordernum' => array('columns' => array(0 => 'ordernum')),
    'ind_disabled' => array('columns' => array(0 => 'disabled')),
    'ind_need_ar_verify' => array('columns' => array(0 => 'need_ar_verify')),
   ),
  'comment' => '对账类目规则表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);