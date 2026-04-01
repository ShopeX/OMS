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

$db['monthly_report']=array (
  'columns' => 
  array (
    'monthly_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'shop_id' => array(
        'type' => 'table:shop@ome',
        'label' => '店铺名称',
        // 'searchtype' => 'nequal',
        'default_in_list' => true,
        'in_list' => true,
        // 'filtertype' => true,
        // 'filterdefault' => true,
        'order' => '5',
    ),
    'monthly_date' => 
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'comment'=>'账期名称',
      'label'=>'账期名称',
      'editable' => false,
      'is_title' => true,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'nequal',
      'filtertype' => true,
      'filterdefault' => true,
      'order'=>'10',
    ),
    'bill_in_amount' => array(
        'type' => 'money',
        'label' => '实收金额',
        'default_in_list' => true,
        'in_list' => true,
        'order' => 40
    ),
    'bill_out_amount' => array(
        'type' => 'money',
        'label' => '实退金额',
        'default_in_list' => true,
        'in_list' => true,
        'order' => 50,
    ),
    'ar_in_amount' => array(
        'type' => 'money',
        'label' => '应收金额',
        'default_in_list' => true,
        'in_list' => true,
        'order' => 20,
    ),
    'ar_out_amount' => array(
        'type' => 'money',
        'label' => '应退金额',
        'default_in_list' => true,
        'in_list' => true,
        'order' => 30,
    ),
    'actually_amount' => array(
        'type'    => 'money',
        'default' => '0',
        'width'           => 65,
        'editable'        => false,
        'in_list'         => true,
        'default_in_list' => true,
        'label'   => '客户实付',
    ),
    'platform_amount' => array(
        'type'    => 'money',
        'default' => '0',
        'label'   => '平台承担金额',
        'width'           => 65,
        'editable'        => false,
        'in_list'         => true,
        'default_in_list' => true,
        'comment' => '平台承担金额（不包含支付优惠）',
    ),
    'refund_actually_amount' => array(
        'type'    => 'money',
        'default' => '0',
        'width'           => 65,
        'editable'        => false,
        'in_list'         => true,
        'default_in_list' => true,
        'label'   => '客户应退',
    ),
    'refund_platform_amount' => array(
        'type'    => 'money',
        'default' => '0',
        'label'   => '平台补贴退',
        'width'           => 65,
        'editable'        => false,
        'in_list'         => true,
        'default_in_list' => true,
        'comment' => '平台补贴退（不包含支付优惠）',
    ),
    'begin_time' => 
    array (
      'type' => 'time',
      'comment'=>'起始时间',
      'label'=>'起始时间',
      'editable' => false,
      'in_list' => true,
      // 'default_in_list' => true,
      'order'=>'20',
    ),
    'end_time' =>
    array (
      'type' => 'time',
      'required' => true,
      'comment'=>'结束时间',
      'label'=>'结束时间',
      'editable' => false,
      'in_list' => true,
      // 'default_in_list' => true,
      'order'=>'30',
    ),
    'status' => 
    array (
      'type' => 'int',
      'required' => true,
      'comment' => '未启用(0) 未关账(1) 已关账(2)',
      'default'=>'1',
      'label'=>'是否关账',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'order'=>'40',
    ),
    'status_time' => 
    array (
      'type' => 'time',
      'comment'=>'关账时间',
      'label'=>'关账时间',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'order'=>'50',
    ),

    
  ),
  'index'=>array(
    'ind_monthly_date' =>
    array (
        'columns' =>
        array (
          0 => 'monthly_date',
          1 => 'shop_id',
        ),
        'prefix' => 'unique'
    ),
    'ind_begin_time' =>
    array (
        'columns' =>
        array (
          0 => 'begin_time',
        ),
    ),
    'ind_end_time' =>
    array (
        'columns' =>
        array (
          0 => 'end_time',
        ),
    ),
    'ind_status' =>
    array (
        'columns' =>
        array (
          0 => 'status',
        ),
    ),
    // 'ind_shop_id' =>
    // array (
    //     'columns' =>
    //     array (
    //       0 => 'shop_id',
    //     ),
    // ),
  ), 
  'comment' => '月结账单',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);