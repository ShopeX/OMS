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
$db['actual']=array (
  'columns' =>
  array (
    'aid' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'extra' => 'auto_increment',
    ),
    'task_id'=>array (
      'type' => 'table:actual_task@logisticsaccounts',
        'label' => '任务名称',
      'required' => true,
      'default' => 0,
      'editable' => false,
    ),

    'status'=>
    array(
        'type'=>array(
        0=>'未匹配',
        1=>'已匹配',
        2=>'比预估低',
        3=>'比预估高',
        4=>'已记账',

        ),
      'default' => '0',
        'label' => '对账结果',

      'width' => 75,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'order'    => 10,
    ),
    'confirm'=>array(
        'type'=>array(
        0=>'未记账',
        1=>'已记账',
        2=>'已审核',
        3=>'已关账',

        ),
        'label'=>'记账状态',
        'default'=>'0',
        'filterdefault' => true,
         'in_list' => true,
      'default_in_list' => true,
      'order'    => 20,
    ),
 'delivery_time' =>
    array (
      'type' => 'time',
      'label' => '发货时间',

      'editable' => false,
      'filtertype' => 'time',
      'filterdefault' => true,
         'in_list' => true,
      'default_in_list' => true,
       'order'    => 30,
    ),
'logi_no' =>
    array (
      'type' => 'varchar(50)',
      'label' => '物流单号',

      'editable' => false,
      'width' =>110,
         'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'order'    => 50,
    ),
    'delivery_bn'=>
        array(
            'type'=>'varchar(32)',
            'label'=>'发货单号',
            'filtertype' => 'normal',
      'filterdefault' => true,
    'in_list' => true,
      'default_in_list' => true,
       'order'    => 40,
),
  'ship_city' =>
    array (
      'type' => 'varchar(50)',
       'label' => '收货省市',
      'editable' => false,

          'in_list' => true,
      'default_in_list' => true,
      'order'    => 60,
    ),
    'ship_name' =>
    array (
      'type' => 'varchar(50)',
      'label' => '收货人',
      'comment' => '收货人姓名',
      'editable' => false,

      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
       'order'    => 70,

    ),

     'logi_weight' =>
    array (
      'type' => 'decimal(20,2)',
      'editable' => false,
      'label' => '物流称重',
       'filtertype' => 'number',
      'filterdefault' => true,
         'in_list' => true,
      'default_in_list' => true,
      'order'    => 80,
    ),
     'weight' =>
    array (

      'type' => 'decimal(20,2)',
      'editable' => false,
      'label' => '出库称重',
       'filtertype' => 'number',
      'filterdefault' => true,
         'in_list' => true,
      'default_in_list' => true,
       'order'    => 90,
    ),
    'delivery_cost_actual'=>array(

      'type' => 'decimal(20,2)',
      'default' => '0',
      'editable' => false,
      'label' => '账单金额',
         'filtertype' => 'number',
      'filterdefault' => true,
          'in_list' => true,
      'default_in_list' => true,
      'order'    => 100,
    ),
   'delivery_cost_expect' =>
    array (
      'type' => 'decimal(20,2)',
      'default' => '0',
      'editable' => false,
      'label' => '预估运费',
          'in_list' => true,
      'default_in_list' => true,
      'order'    => 110,
    ),
'delivery_cost_diff' =>
    array (
      'type' => 'decimal(20,2)',
      'default' => '0',
      'editable' => false,
      'label' => '差额',
          'in_list' => true,
      'default_in_list' => true,
      'order'    => 110,
    ),


    'actual_amount'=>array(
         'type' => 'decimal(20,2)',
      'default' => '0',
      'editable' => false,
      'label' => '记账金额',
         'in_list' => true,
      'default_in_list' => true,
      'order'    => 120,
    ),
'actual_name' =>
    array (
      'type' => 'varchar(15)',
      'label' => '记账人',
        'in_list' => true,
      'default_in_list' => true,
      'order'    =>130,

    ),
    'actual_time' =>
    array (
      'type' => 'time',
      'label' => '记账时间',
        'in_list' => true,
      'default_in_list' => true,
        'order'=> 140,
    ),


    'confirm_name' =>
    array (
      'type' => 'varchar(15)',
      'label' => '审核人',
         'in_list' => true,
      'default_in_list' => true,
        'order'    => 150,
    ),
     'confirm_time' =>
    array (
      'type' => 'time',
      'label' => '审核时间',
          'in_list' => true,
      'default_in_list' => true,
      'order'    => 1620,
    ),
   'memo'=>array(
    'type'=>'text',
    'label'=>'备注',
        'in_list' => true,
      'default_in_list' => true,
       'order'    => 170,
   ),

 ),
  'index' =>
  array (

    'ind_status' =>
    array (
      'columns' =>
      array (
        0 => 'status',
      ),
    ),
 
'ind_confirm' =>
    array (
      'columns' =>
      array (
        0 => 'confirm',
      ),
    ),
    'ind_logi_no' => array(
        'columns' =>
        array(
          0 => 'logi_no',
      ),
    ),
  ),

  'comment' => '物流账单',
  'engine' => 'innodb',
  'version' => '$Rev: 41996 $',
);
