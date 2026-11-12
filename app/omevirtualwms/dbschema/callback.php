<?php
$db['callback']=array (
  'columns' => 
  array (
    'msg_id' => 
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'searchtype' => 'has',
      'label' => 'msg_id',
      'in_list' => true,
      'default_in_list' => true,
      'width' => 250,
      'order' => '1',
    ),
    'callback_url' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'required' => true,
      'label' => 'callback地址',
      'in_list' => false,
    ),
    'url' =>
    array (
      'type' => 'varchar(250)',
      'editable' => false,
      'label' => '接口url',
      'in_list' => true,
      'default_in_list' => true,
      'order' => '2',
    ),
    'method' =>
    array (
      'type' => 'varchar(250)',
      'editable' => false,
      'label' => '接口',
      'in_list' => true,
      'default_in_list' => true,
      'order' => '2',
    ),
    'params' => 
    array (
      'type' => 'serialize',
      'editable' => false,
      'label' => '任务参数',
      'filtertype' => 'normal',
    ),
    'msg' =>
    array (
      'type' => 'varchar(250)',
      'editable' => false,
      'label' => '消息',
      'in_list' => true,
      'default_in_list' => true,
      'order' => '3',
    ),
    'createtime' =>
    array (
      'type' => 'time',
      'label' => '添加时间',
      'width' => '130',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
      'order' => '4',
    ),
    'last_modified' =>
    array (
      'label' => '最后修改时间',
      'type' => 'last_modify',
      'width' => '130',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'order' => '5',
    ),
  ),
  'index' =>
  array (
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
  'comment' => '任务记录表',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
