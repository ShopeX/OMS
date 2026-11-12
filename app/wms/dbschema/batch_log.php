<?php
$db['batch_log']=array (
  'columns' =>
   array (
    'log_id' =>
    array (
      'type' => 'int(10)',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'createtime' =>
    array (
      'type' => 'time',
      'label' => '发起同步时间',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
	'op_id' =>
    array (
      'type' => 'table:account@pam',
      'editable' => false,
      'required' => true,
    ),
    'op_name' =>
    array (
      'type' => 'varchar(30)',
      'editable' => false,
    ),
	'batch_number' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'label' => '批量发货总数',
    ),
    'fail_number' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'label' => '批量发货失败总数',
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
     'log_type' =>
    array (
      'type' => array(
          'consign' => '发货',
          'check' => '校验',

      ),
      'default' => 'consign',
      'label' => '日志类型',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
     'log_text'=>
    array(
    'type' => 'longtext',
      'label' => '参数',
     'editable' => false,
    )
  ),
  'comment' => '批量日志',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
