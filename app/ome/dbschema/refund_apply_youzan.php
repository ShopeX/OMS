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

$db['refund_apply_youzan']=array (
  'columns' => 
  array (
    'apply_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
     
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
   'operation_contraint'=>array(
        'type'=>'varchar(45)',
        'label'=>'支付宝交易号',
   
   ),
   'current_phase_timeout'=>array(
        'type'=>'time',
        'label'=>'当前状态超时时间',
   ),
   
    'alipay_no'=>array(
        'type'=>'varchar(45)',
        'label'=>'支付宝交易号',
    ),
    'tag_list'=>array(
        'type'=>'longtext',
        'label'=>'退款标签',
    ),
    'memo' =>
    array (
      'type' => 'longtext',
      'label' => '留言凭证',
      'editable' => false,
    ),
    'refuse_message'=>array(
        'type' => 'longtext',
        'label' => '拒绝退款原因留言',
    ),
    'refuse_proof'=>array(
      'type' => 'varchar(255)',
      'label' => '拒绝退款举证上传',
    ),
    'cs_status'=>array(
        'label'=>'淘宝小二是否介入',
        'type' =>'varchar(50)',
      'default' => 'no',
    ),
    'outer_lastmodify' =>
    array (
      'label' => '数据推送的修改时间',
      'type' => 'time',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
    ),
     'buyer_nick'=>array(
        'type' => 'varchar(50)',
        'label'=>'买家昵称',
    ),
    'seller_nick'=>array(
         'type' => 'varchar(50)',
        'label'=>'卖家昵称',
    ),
    'trade_status'=>array(
        'type'=>'varchar(64)',
    ),
    'refund_phase'=>array(
        'type'=>array(
            'onsale'    =>'售中',
            'aftersale' =>'售后',
        ),
        'default' => 'onsale',
    ),
   'refund_type'=>array(
        'type'=>array(
            'refund'=>'退款单',
            'return'=>'退货单',
        ),
        'default'=>'refund',
    ),
    'bill_type'=>array(
        'type'=>array(
            'refund_bill'=>'退款单',
            'return_bill'=>'退货单',
        ),
        'default'=>'refund_bill',
    ),
    'oid' => 
    array (
      'type' => 'varchar(50)',
      'default' => 0,
      'editable' => false,
      'label' => '子订单号',
    ),
     'refund_version'=>array (
      'type' => 'varchar(50)',
      'label'=>'退款版本号',
      'editable' => false,
    ),
    'alipay_no'=>array(
    'type'=>'varchar(100)',
    'label'=>'支付单编号',
   ),
   'online_memo'=>array(
        'type' => 'longtext',
        'label' => '线上留言凭证',
    ),
      'refund_fee'=>array(
          'type'=>'money',
          'label'=>'需退金额',
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
          2=>'apply_id',
        ),
        'prefix' => 'unique',
    ),
    
  ),
  'comment' => '退款申请拼多多附加信息表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
