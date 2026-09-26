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

$db['reship_items']=array (
  'columns' =>
  array (
    'item_id' =>
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
      'comment' => '明细ID',
    ),
    'reship_id' =>
    array (
      'type' => 'int unsigned',
      'editable' => false,
      'required' => true,
      'comment' => '退换货单号',
    ),
    'obj_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'editable' => false,
    ),
    'bn' =>
    array (
      'type' => 'varchar(30)',
      'editable' => false,
      'required' => true,
      'comment' => '货品bn',
    ),
    'product_name' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'comment' => '货品名称',
    ),
    'product_id' =>
    array (
      'type' => 'int unsigned',
      'editable' => false,
      'comment' => '货品ID',
    ),
    'num' =>
    array (
      'type' => 'number',
      'editable' => false,
      'required' => true,
      'default' => 1,
      'comment' => '数量',
    ),
    'price' =>
    array (
      'type' => 'money',
      'default' => '0',
      'required' => true,
      'editable' => false,
    ),
    'branch_id' =>
    array (
      'type' => 'number',
      'editable' => false,
      'label'=>'仓库ID',
      'comment'=>'仓库ID',
    ),
    'op_id' =>
    array (
      'type' => 'table:account@pam',
      'editable' => false,
    ),
    'return_type' =>
    array (
      'type' =>
      array (
        'return' => '退货',
        'change' => '换货',
        'refuse' => '拒收退货',
      ),
      'default' => 'return',
      'required' => true,
      'comment' => '退换货类型',
      'editable' => false,
      'label' => '退换货类型',
      'width' =>65,
      'in_list' => true,
      'default_in_list' => true,
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
  ),
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
