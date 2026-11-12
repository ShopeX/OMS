<?php
$db['api_log_copy']=array (
  'columns' =>
  array (
    'log_id' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => true,
      'label' => '日志编号',
      'width' => 100,
      'panel_id' => 'api_log_finder_top',
    ),
    'original_bn' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'has',
      'label' => '单据号',
      'width' => '150',
      'order' => '3',
      'panel_id' => 'api_log_finder_top',
    ),
    'task_name' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'has',
      'label' => '任务名称',
      'width' => 350,
      'panel_id' => 'api_log_finder_top',
    ),
    'status' =>
    array (
      'type' =>
        array (
          'running' => '运行中',
          'success' => '成功',
          'fail' => '失败',
          'sending' => '发起中',
        ),
      'required' => true,
      'default' => 'sending',
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'label' => '状态',
      'width' => 60,
      'panel_id' => 'api_log_finder_top',

    ),
    'worker' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'required' => true,
      'label' => 'api方法名',
      'in_list' => false,
    ),
    'original_params' =>
    array (
      'type' => 'longtext',
      'editable' => false,
      'label' => '原始参数',
    ),
    'params' =>
    array (
      'type' => 'longtext',
      'editable' => false,
      'label' => '接口参数',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'has',
      'panel_id' => 'api_log_finder_top',
    ),
    'sync' =>
    array (
      'type' => array(
        'true' => '同步',
        'false' => '异步'
      ),
      'editable' => false,
      'label' => '同异步类型',
    ),
    'msg' =>
    array (
      'type' => 'text',
      'editable' => false,
      'comment' => '信息',
    ),
    'log_type' =>
    array (
      'type' => 'varchar(32)',
      'editable' => false,
      'label' => '日志类型',
    ),
    'api_type' =>
    array (
      'type' =>
        array (
          'response' => '响应',
          'request' => '请求',
        ),
      'editable' => false,
      'default' => 'request',
      'required' => true,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'label' => '同步类型',
      'width' => 70,
    ),
    'error_lv' =>
    array (
      'type' =>
      array (
        'normal' => '正常',
        'system' => '系统级',
        'application' => '应用级',
        'warning' => '警告',
      ),
      'editable' => false,
      'default' => 'normal',
      'required' => true,
      'label' => '错误级别',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'marking_value' =>
    array (
      'type' => 'varchar(80)',
      'edtiable' => false,
      'comment' => '标识值',
    ),
    'marking_type' =>
    array (
      'type' => 'varchar(32)',
      'edtiable' => false,
      'comment' => '标识类型',
    ),
    'memo' =>
    array (
      'type' => 'text',
      'edtiable' => false,
      'comment' => '备注',
    ),
    'msg_id' =>
    array (
      'type' => 'varchar(60)',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'has',
      'label' => 'msg_id',
      'width' => 60,
      'edtiable' => false,
      'panel_id' => 'api_log_finder_top',
      'comment' => '中心信息id',
    ),
    'retry' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'width' => 60,
      'edtiable' => false,
      'in_list' => true,
      'label' => '重试次数',
      'default_in_list' => true,
    ),
    'addon' =>
    array (
      'type' => 'text',
      'editable' => false,
      'label' => '附加参数',
    ),
    'unique' =>
    array (
      'type' => 'varchar(32)',
      'editable' => false,
      'label' => '日志唯一性',
    ),
    'createtime' =>
    array (
      'type' => 'time',
      'label' => '发起同步时间',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      //'filtertype' => 'time',
      //'filterdefault' => true,
    ),
    'last_modified' =>
    array (
      'label' => '最后重试时间',
      'type' => 'last_modify',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
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
    'ind_createtime' =>
    array (
        'columns' =>
        array (
          0 => 'createtime',
        ),
    ),
    'ind_unique' =>
    array (
        'columns' =>
        array (
          0 => 'unique',
        ),
    ),
    'ind_api_type' =>
    array (
        'columns' =>
        array (
          0 => 'api_type',
        ),
    ),
    'ind_error_lv' =>
    array (
        'columns' =>
        array (
          0 => 'error_lv',
        ),
    ),
    'ind_marking' =>
    array (
        'columns' =>
        array (
          0 => 'marking_value',
          1 => 'marking_type',
        ),
        'prefix' => 'unique',
    ),
    'ind_original_bn' =>
    array (
        'columns' =>
        array (
          0 => 'original_bn',
        ),
    ),

    'ind_msg_id' =>
    array (
        'columns' =>
        array (
          0 => 'msg_id',
        ),
    ),
  ),
  'comment' => 'api日志',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
