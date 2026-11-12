<?php
$db['credit_sheet']=array (
  'columns' => 
  array (
    'cs_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
  'po_bn' =>
  array (
       'type' => 'varchar(32)',
        'default' => '',
       'label' => '采购单编号',
       'width' => 140,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'is_title' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
  ),

    'eo_id' => 
    array (
      'type' => 'table:eo',
      'label' => '入库单编号',
      'width' => 140,
      'default_in_list' => false,
      'editable' => false,
      'in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'iso_bn' =>
    array (
      'type' => 'varchar(32)',
      'label' => '出入库单号',
      'is_title' => true,
      'default_in_list'=>true,
      'searchtype' => 'has',
	  'in_list'=>true,
      'width' => 125,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'cs_bn' => 
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '赊账单编号',
      'width' => 140,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'is_title' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    
    'add_time' => 
    array (
      'type' => 'time',
      'label' => '制单日期',
      'width' => 70,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'supplier_id' => 
    array (
      'type' => 'table:supplier',
      'label' => '供应商',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'operator' => 
    array (
      'type' => 'varchar(50)',
      'label' => '经办人',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'payable' => 
    array (
      'type' => 'money',
      'label' => '应付金额',
      'width' => 90,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'product_cost' => 
    array (
      'type' => 'money',
      'label' => '商品总额',
      'width' => 75,
      'default' => 0,
      'editable' => false,
      'in_list' => true,
    ),
    'delivery_cost' => 
    array (
      'type' => 'money',
      'label' => '物流费用',
      'width' => 75,
      'default' => 0,
      'editable' => false,
      'in_list' => true,
    ),
    'paid' => 
    array (
      'type' => 'money',
      'label' => '结算金额',
      'width' => 90,
      'editable' => false,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'balance' => 
    array (
      'type' => 'money',
      'label' => '结算余额',
      'width' => 90,
      'default_in_list' => false,
      'editable' => false,
      'in_list' => true,
    ),
    'memo' => 
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'op_id' =>
    array (
      'type' => 'table:account@pam',
      'editable' => false,
    ),
    'statement_time' =>
    array (
      'type' => 'time',
      'label' => '结算日期',
      'default_in_list' => true,
      'in_list' => true,
      'width' => 70,
      'editable' => false,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'statement_status' =>
    array (
      'type' => 
      array (
        1 => '未结算',
        2 => '已结算',
        3 => '拒绝结算',
        4 => '部分结算',
      ),
      'default' => '1',
      'label' => '结算状态 ',
      'required' => true,
      'width' => 90,
      'default_in_list' => false,
      'in_list' => true,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'bank_no' => 
    array (
      'type' => 'varchar(50)',
      'label' => '银行账号',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
    ),
  ),
  'index' => 
  array (
    'ind_statement_status' => 
    array (
      'columns' => 
      array (
        0 => 'statement_status',
      ),
    ),
    'ind_cs_bn' => 
    array (
      'columns' => 
      array (
        0 => 'cs_bn',
      ),
    ),
  ),
  'comment' => '赊账单',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
