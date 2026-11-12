<?php
$db['inventory']=array (
  'columns' => 
  array (
    'inventory_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'inventory_name' => 
    array (
      'type' => 'varchar(100)',
      'required' => true,
      'label' => '盘点名称',
      'width' => 150,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'is_title' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'inventory_bn' => 
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '盘点单编号',
      'width' => 140,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'inventory_date' => 
    array (
      'type' => 'time',
      'label' => '盘点日期',
      'width' => 70,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'inventory_checker' => 
    array (
      'type' => 'varchar(20)',
      'label' => '盘点人',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'second_checker' => 
    array (
      'type' => 'varchar(20)',
      'label' => '复核人',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'finance_dept' => 
    array (
      'type' => 'varchar(20)',
      'label' => '财务负责人',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'warehousing_dept' =>
    array (
      'type' => 'varchar(20)',
      'label' => '仓库负责人',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'difference' => 
    array (
      'type' => 'money',
      'label' => '差异金额',
      'width' => 90,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'op_id' =>
    array (
      'type' => 'table:account@pam',
      'label' => '操作工号',
      'editable' => false,
    ),
    'op_name' =>
    array (
      'type' => 'varchar(20)',
      'label' => '工号姓名',
      'editable' => false,
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'label' => '盘点仓库ID',
      'width' => 75,
      'editable' => false,
    ),
    'branch_name' =>
    array (
      'type' => 'varchar(60)',
      'label' => '盘点仓库',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'inventory_type' =>
    array (
      'type' => 
      array (
        1 => '自定义',
        2 => '全盘',
        3 => '在线盘点',
      ),
      'default' => '1',
      'label' => '盘点类别 ',
      'required' => true,
      'width' => 90,
      'default_in_list' => false,
      'in_list' => true,
      'editable' => false,
    ),
    'confirm_status' =>
    array (
      'type' => 
      array (
        1 => '未确认',
        2 => '已确认',
      ),
      'default' => '1',
      'required' => true,
      'label' => '确认状态',
      'width' => 65,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'confirm_op' =>
    array (
      'type' => 'table:account@pam',
      'label' => '确认工号',
      'editable' => false,
    ),
    'confirm_time' =>
    array (
      'type' => 'time',
      'label' => '确认时间',
      'editable' => false,
    ),
    'import_status' =>
    array (
      'type' => 
      array (
        0 => '正在导入',
        1 => '失败',
        2 => '成功',
      ),
      'default' => '0',
      'required' => true,
      'label' => '导入状态',
      'width' => 75,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'update_status' =>
    array (
      'type' => 
      array (
        0 => '更新中',
        1 => '失败',
        2 => '成功',
      ),
      'default' => '0',
      'required' => true,
      'label' => '库存更新',
      'width' => 75,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'is_create' =>
    array (
      'type' => 
      array (
        1 => '未生成',
        2 => '已生成',
      ),
      'default' => '2',
      'required' => true,
      'label' => '盘点表',
      'width' => 60,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'inventory_overview' => 
    array (
      'type' => 'text',
      'editable' => false,
      'width' => 300,
      'default_in_list' => false,
      'in_list' => true,
      'label' => '盘点概况'
    ),
    'memo' => 
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
  ),
  'index' =>
  array (
    'ind_inventory_bn' =>
    array (
        'columns' =>
        array (
          0 => 'inventory_bn',
        ),
    ),
    'ind_inventory_date' =>
    array (
        'columns' =>
        array (
          0 => 'inventory_date',
        ),
    ),
    'ind_inventory_checker' =>
    array (
        'columns' =>
        array (
          0 => 'inventory_checker',
        ),
    ),
    'ind_inventory_type' =>
    array (
        'columns' =>
        array (
          0 => 'inventory_type',
        ),
    ),
    'ind_confirm_status' =>
    array (
        'columns' =>
        array (
          0 => 'confirm_status',
        ),
    ),
    'ind_import_status' =>
    array (
        'columns' =>
        array (
          0 => 'import_status',
        ),
    ),
    'ind_update_status' =>
    array (
        'columns' =>
        array (
          0 => 'update_status',
        ),
    ),
    'ind_branch_name' =>
    array (
        'columns' =>
        array (
          0 => 'branch_name',
        ),
    ),
  ),
  'comment' => '盘点表',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
