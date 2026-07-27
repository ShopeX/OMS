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
$db['order_retrial']=array (
  'columns' => 
    array (
        'id' => array(
            'type' => 'int(10)',
            'pkey' => true,
            'extra' => 'auto_increment',
            'required' => true,
            'label' => '编号',
            'filterdefault' => true,
            'in_list' => false,
            'default_in_list' => true,
            'width' => 60,
            'hidden' => true,
            'order'=>10,
        ),
        'order_id' =>
        array (
            'type' => 'int unsigned',
            'default' => '0',
            'required' => true,
            'in_list' => false,
            'label' => '订单ID',
            'width' => 100,
            'order'=>15,
        ),
        'order_bn' =>
        array (
          'type' => 'varchar(32)',
          'label' => '订单号',
          'is_title' => true,
          'searchtype' => 'nequal',
          'editable' => false,
          'filtertype' => 'normal',
          'filterdefault' => false,
          'in_list' => true,
          'default_in_list' => true,
          'width' => 150,
          'order'=>20,
        ),
        'retrial_type' =>
        array (
          'type' =>
          array (
            'normal' => '复审',
            'audit' => '价格复审',
          ),
          'default' => 'normal',
          'required' => true,
          'label' => '审核类型',
          'filtertype' => 'normal',
          'filterdefault' => true,
          'in_list' => true,
          'default_in_list' => true,
          'width' => 100,
          'order'=>30,
        ),
        'status' => array(
          'type' => array(
            0=>'待复审',
            1=>'复审通过',
            2=>'复审未通过',
            3=>'恢复原订单',
            4=>'订单已取消',
          ),
          'default' => '0',
          'required' => true,
          'label' => '审核状态',
          'filtertype' => 'normal',
          'filterdefault' => true,
          'in_list' => true,
          'default_in_list' => true,
          'order'=>30,
          'width' => 80,
        ),
        'op_id' =>array (
          'type' => 'table:account@pam',
          'default' => '0',
          'label' => '操作员',
          'editable' => false,
          'width' => 60,
          'filtertype' => 'normal',
          'filterdefault' => false,
          'in_list' => false,
          'default_in_list' => false,
        ),
        'kefu_remarks' => array(
          'type' => 'text',
          'label' => '客服修改备注',
          'order'=>40,
        ),
        'remarks' => array(
          'type' => 'text',
          'label' => '审核备注',
          'order'=>40,
        ),
        'dateline' => array(
          'type' => 'time',
          'default' => '0',
          'required' => true,
          'label' => '添加日期',
          'filtertype' => 'time',
          'filterdefault' => true,
          'in_list' => true,
          'default_in_list' => true,
          'width' => 130,
          'order'=>50,
        ),
        'lastdate' => array(
          'type' => 'time',
          'default' => '0',
          'required' => false,
          'label' => '审核日期',
          'filtertype' => 'time',
          'filterdefault' => true,
          'in_list' => false,
          'default_in_list' => false,
          'width' => 130,
          'order'=>80,
        ),
  ),
  'index' => array(
        'type_status' => array(
            'columns'=>array(
                0=>'status',
                1=>'retrial_type',
            ),
        ),
        'order_id'=>array(
            'columns'=>array(
                0=>'order_id',
            ),
        ),
    ),
  'comment' => '复审订单表',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
