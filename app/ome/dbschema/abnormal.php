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

$db['abnormal']=array (
  'columns' => 
  array (
    'abnormal_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'abnormal_memo' =>
    array (
      'type' => 'text',
      'editable' => false,
    ),
    'abnormal_type_id' => 
    array (
      'type' => 'table:abnormal_type@ome',
      'editable' => false,
    ),
   'abnormal_type_name' =>
    array (
      'type' => 'varchar(100)',
      'label' => '异常类型',
      'default' => '',
      'default_in_list' => true,
      'in_list' => true,
       'is_title' => true,
      'editable' => false,
      'width' => 75,
      'filtertype' => 'yes',
      'filterdefault' => true,
    ),
    'order_id' => 
    array (
      'type' => 'table:orders@ome',
      'editable' => false,
    ),
    'is_done' => 
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
  ), 
  'comment' => '订单异常信息表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
