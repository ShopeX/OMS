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

$db['specification']=array (
  'columns' => 
  array (
    'spec_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => '规格id',
      'width' => 150,
      'editable' => false,
    ),
    'spec_name' => 
    array (
      'type' => 'varchar(50)',
      'default' => '',
      'required' => true,
      'label' => '规格名称',
      'width' => 180,
      'editable' => false,
      'in_list' => true,
      'is_title' => true,
      'default_in_list' => true,
      'filterdefault' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'alias' => 
    array (
      'type' => 'varchar(255)',
      'default' => '',
      'label' => '规格别名',
      'width' => 160,
      'in_list' => true,
      'default_in_list' => true,
      'filterdefault' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'spec_show_type' => 
    array (
      'type' => 
      array (
        'select' => '下拉',
        'flat' => '平铺',
      ),
      'default' => 'flat',
      'required' => true,
      'label' => '显示方式',
     // 'width' => 75,
      'editable' => false,
      'filterdefault' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'spec_type' => 
    array (
      'type' => 
      array (
        'text' => '文字',
        'image' => '图片',
      ),
      'default' => 'text',
      'required' => true,
      'label' => '显示类型',
      'width' => 75,
      'editable' => false,
      'filterdefault' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'spec_memo' => 
    array (
      'type' => 'varchar(50)',
      'default' => '',
      'required' => true,
      'label' => '规格备注',
      'width' => 350,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'p_order' => 
    array (
      'type' => 'number',
      'default' => 0,
      'required' => true,
      'editable' => false,
      'default_in_list' => true,
    ),
    'disabled' => 
    array (
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'editable' => false,
    ),
  ),
  'comment' => '商店中商品规格',
  'engine' => 'innodb',
  'version' => '$Rev: 40654 $',
);