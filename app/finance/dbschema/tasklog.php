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

$db['tasklog']=array (
  'comment' => '对账支付宝接口请求日志表',
  'columns' => 
  array (
    'log_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'log_title' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'has',
      'label' => '日志名称',
      'width' => '350',
      'order' => '2',
    ),
    'log_type' => 
    array (
      'type' => 'varchar(32)',
      'editable' => false,
      'label' => '日志类型',
    ),
    'crc32_log_type' => 
    array (
      'type' => 'int',
      'editable' => false,
      'label' => 'crc32日志类型值',
    ),
    'status' =>
    array (
      'type' => 
        array (
          'running' => '运行中',
          'success' => '成功',
          'fail' => '失败',
          'retring' => '重试中',
        ),
      'required' => true,
      'default' => 'retring',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'label' => '状态',
      'width' => '60',
      'order' => '4',
    ),
    'retry' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'width' => '60',
      'edtiable' => false,
      'in_list' => true,
      'label' => '重试次数',
      'default_in_list' => true,
      'order' => '5',
    ),
    'node_id' => 
    array (
      'type' => 'varchar(32)',
      'editable' => false,
      'in_list' => false,
      'label' => '节点ID',
      'width' => '100',
      'order' => '2',
    ),
    'params' => 
    array (
      'type' => 'longtext',
      'editable' => false,
      'label' => '日志参数',
      'filtertype' => 'normal',
    ),
    'msg' =>
    array (
      'type' => 'text',
      'editable' => false,
      'label' => '同步消息',
    ),
    'addon' =>
    array (
      'type' => 'text',
      'editable' => false,
      'label' => '附加参数',
    ),
    'createtime' =>
    array (
      'type' => 'time',
      'label' => '发起同步时间',
      'width' => '130',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
      'order' => '7',
    ),
    'last_modified' =>
    array (
      'label' => '最后重试时间',
      'type' => 'last_modify',
      'width' => '130',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'order' => '8',
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
    'ind_crc32_log_type' =>
    array (
        'columns' =>
        array (
          0 => 'crc32_log_type',
        ),
    ),
    'ind_node_id' =>
    array (
        'columns' =>
        array (
          0 => 'node_id',
        ),
    ),
    'ind_createtime' =>
    array (
        'columns' =>
        array (
          0 => 'createtime',
        ),
    ),
    'ind_last_modified' =>
    array (
        'columns' =>
        array (
          0 => 'last_modified',
        ),
    ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
