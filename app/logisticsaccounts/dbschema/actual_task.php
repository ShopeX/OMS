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
$db['actual_task']=array (
  'columns' =>
  array (
    'task_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'extra' => 'auto_increment',
    ),

    'task_bn'=>
    array(
      'type'=>'varchar(100)',
      'default' => '0',
        'label' => '任务名称',
      'width' => 100,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
       'is_title' => true,
      'searchtype' => 'has',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'logi_id'=>array(
   'type' => 'table:dly_corp@ome',
    'label'=>'物流公司',
       'filtertype' => 'normal',
      'filterdefault' => true,
    ),
     'logi_name' =>
    array (
      'type' => 'varchar(100)',
      'label' => '物流公司',

      'editable' => false,
      'width' =>75,

      'in_list' => true,
      'default_in_list' => true,
    ),

    'branch_id' =>
    array (

      'type' => 'table:branch@ome',
      'editable' => false,
      'label' => '仓库',
      'width' => 110,
      'filtertype' => 'normal',

      'filterdefault' => true,
      'in_list' => true,
      'panel_id' => 'actual_task_finder_top',
    ),
    'branch_name'=>array(
        'type'=>'varchar(32)',
        'label'=>'仓库名称',
        'in_list' => true,
      'default_in_list' => true,

    ),
    'add_time'=>array(
        'type'=>'time',
        'label'=>'创建时间',
        'in_list' => true,
      'default_in_list' => true,

    ),
    'modifiey_time'=>array(
        'type'=>'last_modify',
        'label'=>'最后更新时间',
        'in_list' => true,
      'default_in_list' => true,
    ),

   'op_id' =>
    array (
      'type' => 'table:account@pam',
      'label' => '创建人',
      'editable' => false,
      'width' => 60,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,

    ),

    'actual_name'=>array(
        'type'=>'varchar(20)',
        'label'=>'记账人',
        'in_list' => true,
      'default_in_list' => true,
    ),
    'confirm_name'=>array(
        'type'=>'varchar(20)',
        'label'=>'审核人',
        'in_list' => true,
      'default_in_list' => true,
    ),
    'delivery_cost_actual'=>array(
         'type'=>'decimal(20,2)',
      'default'=>'0',
      'label'=>'账单总金额',
    'in_list' => true,
      'default_in_list' => true,
    ),
    'delivery_cost_expect'=>array(
     'type'=>'decimal(20,2)',
      'default'=>'0',
      'label'=>'预估总金额',
    'in_list' => true,
      'default_in_list' => true,
    ),
    'actual_amount'=>array(
     'type'=>'decimal(20,2)',
      'default'=>'0',
      'label'=>'实际记账总金额',
        'in_list' => true,
      'default_in_list' => true,
    ),
    'status'=>
    array(
        'type'=>array(
        0=>'未记账',
        1=>'已记账',
        2=>'已审核',
        3=>'已关账',
        4=>'记账中',
        5=>'审核中',
        ),
      'default' => '0',

      'label' => '记账状态',

      'width' => 75,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'panel_id' => 'actual_task_finder_top',
    ),
   'actual_number'=>array(
     'type'=>'number',
      'default'=>'0',
      'label'=>'已记账',

    ),
    'actual_total'=>array(
     'type'=>'number',
      'default'=>'0',
      'label'=>'总数',

    ),

 ),

  'comment' => '对账任务',
  'engine' => 'innodb',
  'version' => '$Rev: 41996 $',
);
