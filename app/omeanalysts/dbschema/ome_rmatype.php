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

$db['ome_rmatype']=array (
  'columns' => 
  array (
    'id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 110,
      'hidden' => true,
      'editable' => false,
	  'filtertype' => 'normal',
    ),
    'problem_id' => 
    array (
      'type' => 'table:return_product_problem@ome',
	  'sdfpath' => 'problem/problem_id',
      'label' => '售后类型',
      'width' => 120,
      'searchtype' => 'head',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
	  'filtertype' => 'normal',
    ),
	'num' => 
    array (
      'type' => 'longtext',
      'label' => '售后单据数量',
      'width' => 110,
      'filtertype' => 'normal',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
	'shop_id' => 
    array (
	  'type' => 'table:shop@ome',
      'sdfpath' => 'shop/shop_id',
      'required' => false,
      'pkey' => true,
      'editable' => false,
	  'label' => '所属店铺',
    ),
	'createtime' =>
    array (
      'type' => 'time',
      'label' => '所属时间',
      'width' => 130,
      'editable' => false,
      'filtertype' => 'time',
      'filterdefault' => true,
      'in_list' => true,
    ),
  ),
  'comment' => '售后类型',
  'index' => 
  array (
    
  ),
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
