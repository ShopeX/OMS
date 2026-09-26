<?php
$db['queue']=array (
  'columns' => 
  array (
    'queue_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'queue_name' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
      'label' => '队列名',
      'is_title' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' => 400,
      'order' => 10,
    ),
    'queue_mode' =>
    array (
      'type' => 'varchar(30)',
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
      'label' => '队列模式',
      'default' => 'goodssync',
      'width' => 100,
      'order' => 15,
    ),
    'status' => array(
        'type' => array(
            'ready'=>'准备处理',
            'process'=>'处理中',
            'error'=>'处理失败',
            'succ'=>'处理成功',
        ),
        'default' => 'ready',
        'comment' => '状态',
        'label' => '状态',
        'in_list' => true,
        'default_in_list' => true,
        'width' => 100,
        'order' => 50,
    ),
    'create_time' => array(
        'type' => 'time',
        'comment' => '创建时间',
        'label' => '创建时间',
        'in_list' => true,
        'default_in_list' => true,
        'width' => 120,
        'order' => 30,
    ),
    'modify_time' => array(
        'type' => 'time',
        'comment' => '完成时间',
        'label' => '完成时间',
        'in_list' => true,
        'default_in_list' => true,
        'width' => 120,
        'order' => 40,
    ),
    'queue_data' => array (
        'type' => 'longtext',
        'label' => '队列数据',
    ),
    'error_msg' => array (
        'type' => 'longtext',
        'label' => '错误原因',
        'in_list' => true,
        'default_in_list' => true,
    ),
    'disabled' => 
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
  ),
  'index' => array(
    'ind_status' => array('columns' => array(0 => 'status')),
    'ind_disabled' => array('columns' => array(0 => 'disabled')),
    'ind_queue_mode' => array('columns' => array(0 => 'queue_mode')),
   ),
  'comment' => '物料队列表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);

