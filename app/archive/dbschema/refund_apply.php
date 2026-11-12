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

$db['refund_apply']=array (
  'columns' => 
  array (
    'apply_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'refund_apply_bn' =>
    array (
      
      'type' => 'varchar(32)',
      'required' => true,
      'default' => '',
      'label' => '退款申请单号',
      'width' => 140,
      'editable' => false,
      'in_list' => true,
      'is_title' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'order_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
      'label' => '订单号',
      'editable' => false,
    ),
    'pay_type' => 
    array (
      'type' => 
      array (
        'online' => '在线支付',
        'offline' => '线下支付',
        'deposit' => '预存款支付',
      ),
      'default' => 'online',
      'required' => true,
      'label' => '支付类型',
      'width' => 110,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
    ),
    'account' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'in_list' => true,
      'label' => '退款帐号',
    ),
    'bank' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '退款银行',
    ),
    'pay_account' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'in_list' => true,
      'label' => '收款帐号',
    ),
    'money' =>
    array (
      'type' => 'money',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '申请退款金额',
      'width' => '70',
    ),
    'refunded' =>
    array (
      'type' => 'money',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '已退款金额',
      'width' => '70',
    ),
    
    'memo' =>
    array (
      'type' => 'text',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
       'in_list' => true,
      'default_in_list' => true,
      'label' => '退款原因',
    ),
    'create_time' =>
    array (
      'type' => 'time',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '申请时间',
      'width' => 130,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'last_modified' => 
    array (
      'label' => '最后更新时间',
      'type' => 'last_modify',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
    ),
    
    'status' =>
    array (
      'type' =>
      array (
        0 => '未审核',
        1 => '审核中',
        2 => '已接受申请',
        3 => '已拒绝',
        4 => '已退款',
        5 => '退款中',
        6 => '退款失败',
      ),
      'default' => '0',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '退款状态',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    
    'shop_id' =>
    array (
      'type' => 'table:shop@ome',
      'label' => '来源店铺',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'return_id' => 
    array(
      'type' => 'int unsigned',
      //'required' => true,
      'editable' => false,
      'comment' => '售后ID',
    ),
    'reship_id' =>
    array(
      'type' => 'int(10)',

      'editable' => false,
      'comment' => '退换货ID',
    ),        
    'addon' =>
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'refund_refer' =>
    array(
      'type' =>
      array(
         0=>'normal',
         1=>'aftersale',
      ),
      'default' => '0',
      'label' => '退款来源',            
    ),
    'bcmoney' => array(
        'type' => 'money',
        'label' => app::get('ome')->_('补偿费用'),
        'in_list' => true,
        'default' => 0,
    ),    
    'product_data' =>
    array (
      'type' => 'longtext',
      'editable' => false,
      'label'=>'售后申请商品',
    ),
    'source' =>
    array (
      'type' => 'varchar(50)',
      'default' => 'local',
      'editable' => false,
      'label' => '来源',
      'in_list' => true,
      'filterdefault' => true,
    ),
       
     'shop_type' =>
    array (
      'type' => 'varchar(50)',
      'label' => '店铺类型',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'mark_text' =>
    array (
      'type' => 'longtext',
      'label' => '卖家备注',
      'editable' => false,
    ),
   
     'outer_lastmodify' =>
    array (
      'label' => '前端店铺最后更新时间',
      'type' => 'time',
      'width' => 130,
      'editable' => false,
    
    ),
   
    'oid' =>
    array(
      'type'     => 'varchar(50)',
      'default'  => 0,
      'editable' => false,
      'label'    => '子订单号',
      'in_list'       => true,
      'filtertype'    => 'normal',
      'filterdefault' => true,
    ),
    'bn' =>
    array(
      'type'     => 'varchar(40)',
      'editable' => false,
      'label'    => '货号',
      'in_list'       => true,
      'filtertype'    => 'normal',
      'filterdefault' => true,
    ),
    
  ),
  'index' =>
  array (
    'ind_refund_apply_bn_shop' =>
    array (
        'columns' =>
        array (
          0 => 'refund_apply_bn',
          1 => 'shop_id',
        ),
        'prefix' => 'unique',
    ),
    
    'idx_oid' =>
    array(
        'columns' =>
        array(
          0 => 'oid',
        ),
    ),
    'idx_bn' =>
    array(
        'columns' =>
        array(
          0 => 'bn',
        ),
    ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);