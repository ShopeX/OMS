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

$db['warehouse_address']=array (
    'columns' =>
    array (
        'id' =>
        array (
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
        ),
      'warehouse_id'=>array(
            'type' => 'number',
            'default' => '0',
            'label'=> '区域仓id',
            'width'=>100,
      ),
      'area'=>array(
        'type'          => 'region',
        'label'         => '地区',
        'width'         => 170,
        'editable'      => false,
        'filtertype' => 'normal',
        'filterdefault' => true,
        'in_list'       => true,
      ),
      'province'=>array(
            'type' => 'varchar(50)',
            'required' => true,
            'default' => '',
            'label'=> '省',
            'width'=>100,
            'default_in_list'=>true,
            'in_list'=>true,
            'editable' => false,
        ),

        'city'=>array(
            'type' => 'varchar(50)',
            'required' => true,
            'default' => '',
            'label'=> '市',
            'width'=>100,
            'default_in_list'=>true,
            'in_list'=>true,
            'editable' => false,
        ),
        'street'=>array(
            'type' => 'varchar(50)',
            'required' => true,
            'default' => '',
            'label'=> '区',
            'width'=>100,
            'default_in_list'=>true,
            'in_list'=>true,
            'editable' => false,
        ),
        'town'=>array(
            'type' => 'varchar(50)',
            'required' => true,
            'default' => '',
            'label'=> '镇',
            'width'=>100,
            'default_in_list'=>true,
            'in_list'=>true,
            'editable' => false,
        ),
        'address' =>
        array (
            'type' => 'varchar(255)',
            'required' => true,
            'default' => '',
            'label'=> '地址',
            'width'=>100,
            'default_in_list'=>true,
            'in_list'=>true,
            'editable' => false,
        ),
        
    ),
    'index' =>
  array (
    'ind_province'=>array(
        'columns' =>
          array (
            0 => 'province',
          ),
    ),
    
  ),
  'comment' => '京标地址表',
);
