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

$db['api_order_log']=array( 
  'columns' => 
  array(
    'log_id' => 
    array(
      'type' => 'varchar(32)',    
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => true,
      'label' => '日志编号',
      'width' => 100,
    ),   
    'task_name' =>
    array(
      'type' => 'varchar(255)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => false,
      'filterdefault' => false,
      'searchtype' => false,
      'label' => '任务名称',
      'width' => 380,     
        'order' => 10,
    ),
    'status' =>
    array(
      'type' => 
        array(
          'running' => '同步中',
          'success' => '成功',
          'fail' => '失败',
          'sending' => '等待同步',
        ),
      'required' => true,
      'default' => 'sending',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'editable' => false,
      'filtertype' => false,
      'filterdefault' => false,
      'label' => '同步状态',
      'width' => 100,
      'order' => 50,
    ),
    'worker' =>
    array(
      'type' => 'varchar(200)',
      'editable' => false,
      'required' => true,
      'label' => 'api方法名',
      'in_list' => false,
    ),
    'params' => 
    array(
      'type' => 'longtext',
      'editable' => false,
      'label' => '任务参数',
      'filtertype' => false,
        'filterdefault' => false,
    ),
    'msg' =>
    array(
      'type' => 'varchar(1000)',
      'editable' => false,
      'label' => '响应消息',
      'in_list' => true,
      'default_in_list' => true,
      'width' => 150,
        'order' => 55,
    ),
    'api_type' => 
    array(
      'type' => 
        array(
          'response' => '响应',
          'request' => '请求',
        ),
      'editable' => false,
      'default' => 'request',
      'required' => true,
      'in_list' => false,
      'default_in_list' => true,
      'filtertype' => false,
      'filterdefault' => false,
      'label' => '同步类型',
      'width' => 70,
    ),
    'memo' =>
    array(
      'type' => 'text',
      'edtiable' => false,
    ),
    'msg_id' =>
    array(
      'type' => 'varchar(60)',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'label' => 'msg_id',
      'width' => 60,
      'edtiable' => false,
    ),
    'retry' =>
    array(
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'width' => 60,
      'edtiable' => false,
      'in_list' => false,
      'label' => '同步次数',
      'default_in_list' => false,
        'order' => 60,
    ),
    'createtime' =>
    array(
      'type' => 'time',
      'label' => '发起时间',
      'width' => 150,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
        'order' => 70,
    ),
    'last_modified' =>
    array(
      'label' => '响应时间',
      'type' => 'last_modify',
      'width' => 150,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
        'order' => 80,
    ),
    'order_bn' =>
    array(
       'type' => 'varchar(32)',
       'label' => '订单号',
       'width' => 150,
       'editable' => false,
       'in_list' => true,
       'default_in_list' => true,       
    ),
      'shop_id' =>
    array(
      'label' => '店铺id',
      'type' => 'varchar(32)',
      'width' => 150,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
        'order' => 30,
    ),
    'shop_name' =>
    array(
      'label' => '店铺名称',
      'type' => 'varchar(100)',
      'width' => 200,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
        'filterdefault' => true,
        'order' => 20,
    ),
  ),
  'index' =>
  array(
    'ind_status' =>
    array(
        'columns' =>
        array(
          0 => 'status',
        ),
    ),
    'ind_api_type' =>
    array(
        'columns' =>
        array(
          0 => 'api_type',
        ),
    ),
      'idx_shop_id' =>
      array(
            'columns' =>array('shop_id'),
      ),
  ),
  'comment' => '前端店铺订单列表同步日志',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
