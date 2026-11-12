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

$db['supplier']=array (
  'columns' => 
  array (
    'supplier_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'bn' => 
    array (
      'type' => 'varchar(32)',
       'required' => true,
      'label' => '编号',
      'width' => 100,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'name' => 
    array (
      'type' => 'varchar(200)',
      'label' => '供应商',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'is_title' => true,
    ),
    'company' => 
    array (
      'type' => 'varchar(32)',
      'label' => '公司名称',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'arrive_days' => 
    array (
      'type' => 'int(3)',
      'label' => '到货天数',
      'width' => 60,
      'editable' => false,
      'in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'brief' => 
    array (
      'type' => 'varchar(32)',
      'label' => '快速索引',
      'width' => 100,
      'editable' => false,
      'in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    
    'area' => 
    array (
      'type' => 'region',
      'label' => '地区',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'addr' => 
    array (
      'type' => 'varchar(255)',
      'label' => '详细地址',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
    ),
    'zip' => 
    array (
      'type' => 'varchar(20)',
      'label' => '邮编',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'telphone' => 
    array (
      'type' => 'varchar(20)',
      'label' => '电话',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'fax' => 
    array (
      'type' => 'varchar(20)',
      'label' => '传真',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'contacter' => 
    array (
      'type' => 'text',
      'label' => '联系人',
      'width' => 80,
      'editable' => false,
    ),
    'account' => 
    array (
      'type' => 'varchar(50)',
      'label' => '银行账号',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
    ),
    'bank' => 
    array (
      'type' => 'varchar(50)',
      'label' => '开户银行',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
    ),
    'memo' => 
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'operator' => 
    array (
      'type' => 'varchar(100)',
      'label' => '采购员',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'credit_lv' => 
    array (
      'type' => 'varchar(10)',
      'label' => '信用等级',
      'width' => 60,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
  ),
  'index' => 
  array (
    'ind_bn' => 
    array (
      'columns' => 
      array (
        0 => 'bn',
      ),
    ),
    'ind_name' => 
    array (
      'columns' => 
      array (
        0 => 'name',
      ),
    ),
    'ind_brief' => 
    array (
      'columns' => 
      array (
        0 => 'brief',
      ),
    ),
  ),
  'comment' => '供应商',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
