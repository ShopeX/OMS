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

$db['logistics_log']=array (
  'columns' => 
  array (
    'log_id' => 
    array (
      'type' => 'bigint unsigned',
      'required' => true,
      'editable' => false,
      'pkey' => true,
      'comment' => '日志主键',
      'label' => '日志ID',
      'extra' => 'auto_increment',
    ),
    'delivery_id' =>
    array (
      'type' => 'table:delivery@ome',
      'required' => true,
      'editable' => false,
      'label' => '发货单号',
      'width' =>110,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'nequal',
      'order' => 10,
    ),
    'logi_no' =>
    array (
      'type' => 'varchar(50)',
      'required' => true,
      'editable' => false,
      'label' => '物流单号',
      'width' =>110,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'nequal',
      'order' => 20,
    ),
    'channel_id' =>
    array (
      'type' => 'table:channel@logisticsmanager',
      'required' => true,
      'editable' => false,
      'comment' => '渠道主键',
      'label' => '请求来源',
      'width' => 150,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'order' => 30,
    ),
    'channel_type' =>
    array (
      'type' => 'varchar(32)',
 
      'default' => '',
      'comment' => '渠道类型',
      'label' => '渠道类型',
    ),
    'status' =>
    array (
      'type' => 
        array (
          'running' => '运行中',
          'success' => '成功',
          'fail' => '失败',
        ),
      'required' => true,
      'default' => 'running',
      'editable' => false,
      'label' => '状态',
      'width' => 60,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'order' => 40,
    ),
    'retry' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'edtiable' => false,
      'label' => '重试次数',
      'width' => 60,
      'in_list' => true,
      'default_in_list' => true,
      'order' => 50,
    ),
    'create_time' =>
    array (
      'type' => 'time',
      'editable' => false,
      'label' => '创建时间',
      'width' => '130',
      'in_list' => true,
      'default_in_list' => true,
      'order' => 60,
    ),
    'last_modified' =>
    array (
      'type' => 'last_modify',
      'editable' => false,
      'label' => '最后重试时间',
      'width' => 130,
      'in_list' => true,
      'default_in_list' => true,
      'order' => 70,
    ),
    'params' =>
    array (
      'type' => 'serialize',
      'editable' => false,
      'label' => '请求参数',
    ),
    'type' =>
    array (
      'type' => array(
        'normal' => '普通',
       'delivery' => '发货',
       
      ),
      'required' => true,
      'default' => 'normal',
      'label' => '日志类型',
      'width' => 110,
      'editable' => true,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'order' => 20,
    ),
  ),
  'comment' => '请求面单号日志表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);