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
$db['estimate']=array (
  'columns' =>
  array (
    'eid' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'extra' => 'auto_increment',
    ),

    'aid'=>array(
        'type'=>'number',
        'default'=>0,
        'comment'=>'物流账单主键',

    ),
    'status'=>
    array(
        'type'=>array(
        0=>'未对账',
        1=>'待记账',
        2=>'已记账',
        3=>'已审核',
        4=>'已关账',

        ),
       'default' => '0',
        'label' => '状态',
      'width' => 75,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),

    'task_id'=>array(
      'type' => 'table:actual_task@logisticsaccounts',
      'default' => '0',

      'label' => '任务名称',

      'width' => 75,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
     'branch_id' =>
    array(
      'type' => 'number',
      'editable' => false,
      'label' => '仓库',
      'width' => 110,
    ),
    'branch_name'=>
    array(
        'type'=>'varchar(32)',
        'label'=>'仓库名称',
         'in_list' => true,
      'default_in_list' => true,
    ),
   'shop_id' =>
    array (
      'type' => 'char(32)',
      'label' => '来源店铺',
      'width' => 75,
      'editable' => false,

      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
   'shop_name'=>
   array(
    'type'=>'char(35)',
    'label'=>'店铺名称',
      'in_list' => true,
      'default_in_list' => true,
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
    ),
      'logi_id' =>
    array (
      'type' => 'table:dly_corp@ome',
      'comment' => '物流公司ID',
      'editable' => false,
      'label' => '物流公司',
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
       'in_list' => true,
      'default_in_list' => true,
    ),

     'ship_name' =>
    array (
      'type' => 'varchar(255)',
      'label' => '收货人',
      'comment' => '收货人姓名',
      'editable' => false,
      'searchtype' => 'tequal',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,

    ),
   'weight' =>
    array (
      'type' => 'decimal(20,2)',
      'editable' => false,
      'label' => '出库称重',
       'in_list' => true,
      'default_in_list' => true,
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
  'delivery_cost_expect' =>
    array (
      'type' => 'decimal(20,2)',
      'default' => '0',
      'editable' => false,
      'label' => '预估运费',
           'in_list' => true,
      'default_in_list' => true,
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
    ),
    'actual_amount'=>array(
         'type' => 'decimal(20,2)',
      'default' => '0',
      'editable' => false,
      'label' => '记账费用',
         'in_list' => true,
      'default_in_list' => true,
    ),
    'actual_name' =>
    array (
      'type' => 'varchar(15)',
      'label' => '记账人',
        'in_list' => true,
      'default_in_list' => true,

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

    ),

    'ship_province' =>
    array (
      'type' => 'varchar(50)',
       'label' => '省',
      'editable' => false,
),
     'ship_city' =>
    array (
      'type' => 'varchar(50)',
       'label' => '市',
      'editable' => false,


    ),
    'ship_district' =>
    array (
      'type' => 'varchar(50)',
       'label' => '区域',
      'editable' => false,
),
    'logi_no' =>
    array (
      'type' => 'varchar(50)',
      'label' => '物流单号',
    'editable' => false,
      'width' =>110,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'delivery_bn'=>
    array(
    'type'=>'varchar(32)',
    'label'=>'发货单号',
      'in_list' => true,
      'default_in_list' => true,
    ),
     'order_bn' =>
    array (
      'type' => 'varchar(255)',
        'label' => '订单号',
        'in_list' => true,
      'default_in_list' => true,
    ),
    'ship_area' =>
    array (
      'type' => 'region',
      'label' => '收货地区',
      'comment' => '收货人地区',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>130,
      'in_list' => true,
      'default_in_list' => true,

    ),
     'ship_addr' =>
    array (
      'type' => 'text',
      'label' => '收货地址',
      'comment' => '收货人地址',
      'editable' => false,
      'filtertype' => 'normal',
      'width' =>150,
      'in_list' => true,

    ),
),
  'index' =>
  array (
    'ind_delivery_bn' =>
    array (
      'columns' =>
      array (
        0 => 'delivery_bn',
      ),
    ),
    'ind_logi_no' =>
    array (
      'columns' =>
      array (
        0 => 'logi_no',
      ),
    ),
    'ind_status' =>
    array (
      'columns' =>
      array (
        0 => 'status',
      ),
    ),
    'ind_delivery_time' =>
    array (
      'columns' =>
      array (
        0 => 'delivery_time',
      ),
    ),
     'ind_order_bn' =>
    array (
      'columns' =>
      array (
        0 => 'order_bn',
      ),
    ),
     'ind_logi_name' =>
    array (
      'columns' =>
      array (
        0 => 'logi_name',
      ),
    ),
     'ind_aid' =>
    array (
      'columns' =>
      array (
        0 => 'aid',
      ),
    ),
  ),
  'comment' => '对账审核',
  'engine' => 'innodb',
  'version' => '$Rev: 41996 $',
);
