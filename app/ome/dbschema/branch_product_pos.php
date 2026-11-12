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

$db['branch_product_pos']=array (
  'columns' =>
  array (
    'pp_id' =>
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'product_id' =>
    array (
      'type' => 'table:products@ome',
      'required' => true,
      'editable' => false,
     // 'pkey' => true,
    ),
    'pos_id' =>
    array (
      'type' => 'table:branch_pos@ome',
      'required' => true,
     // 'pkey' => true,
      'editable' => false,
      'label' => '货位',
   	  'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'required' => true,
      'editable' => false,
    ),
    'store' =>
    array (
      'type' => 'mediumint',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'label' => '库存',
      'in_list' => false,
      'default_in_list' => false,
    ),
    'default_pos' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
    'create_time' =>
    array (
      'label' => '更新时间',
      'type' => 'time',
      'default' => 0,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'operator' =>
    array (
      'type' => 'varchar(50)',
      'label' => '操作人',
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
    ),
  ),
  'comment' => '仓库中货品所在货位关系表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);