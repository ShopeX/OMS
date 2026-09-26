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

$db['change_items']=array (
  'columns' => 
  array (

    'item_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
      'label'=>'明细ID',
      'comment'=>'明细ID',
    ),
    'return_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'editable' => false,
      'label'=>'售后ID',
 
    ),
    'product_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'editable' => false,
      'label'=>'货品ID',
      
    ),
    'shop_id' =>
    array (
     'type' => 'varchar(32)',

     'label'=>'店铺',
    ),
    'sales_material_bn' => 
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'label'=>'销售物料货号',
   
    ),
    'bn' => 
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'label'=>'货品bn',
    ),
    'name' => 
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'label'=>'货品名称',
     
    ),
   
    'num' => 
    array (
      'type' => 'number',
      'editable' => false,
      'label'=>'数量',
    ),
      
  ), 
  'engine' => 'innodb',
  'version' => '$Rev:  $',
  'comment'=>'换货明细',
);
