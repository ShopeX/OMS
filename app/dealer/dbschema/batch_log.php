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

$db['batch_log'] = array (
  'columns' => array (
    'log_id' => array (
      'type' => 'int(10)',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'createtime' => array (
      'type' => 'time',
      'label' => '发起同步时间',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'op_id' => array (
      'type' => 'table:account@pam',
      'editable' => false,
      'required' => true,
    ),
    'op_name' => array (
      'type' => 'varchar(30)',
      'editable' => false,
    ),
    'batch_number' => array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'label' => '批量任务总数',
    ),
    'fail_number' => array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'label' => '执行失败总数',
    ),
    'succ_number' => array (
      'type' => 'number',
      'default' => 0,
      'editable' => false,
      'label' => '执行成功总数',
    ),
    'status' => array(
        'type' => array(
            0 => '等待中',
            1 => '已处理',
            2 => '处理中',
        ),
        'label' => '状态',
        'default' => '0',
    ),
    'log_type' => array (
      'type' => array(
          'ordertaking' => '批量获取订单',
          'confirm_reship' => '退换货单',
      ),
      'default' => 'ordertaking',
      'label' => '日志类型',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'log_text'=> array(
        'type' => 'longtext',
        'label' => '参数',
        'editable' => false,
    ),
    'source' => array(
        'type' => 'varchar(16)',
        'comment' => '来源：direct->默认，task->定时任务',
        'editable' => false,
        'default' => 'direct'
    )
  ),
  'index' => array(
      'idx_status' =>array(
          'columns' => array('status')
      ),
      'idx_log_type' => array(
          'columns' => array('log_type')
      ),
      'idx_status_source' => array(
          'columns' => array(
              0 => 'log_type',
              1 => 'source',
              2 => 'status',
          )
      ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev: $',
  'comment' => '批量日志表',
);
