<?php
$db['delivery_order']=array (
  'columns' =>
  array (
    'delivery_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'editable' => false,
    ),
   
    'delivery_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '发货单号',
      'comment' => '配送流水号',
      'editable' => false,
      'width' =>140,
      'searchtype' => 'nequal',
      'filtertype' => 'yes',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'is_title' => true,
      'order'   => 1,
    ),
    'shop_id' =>
    array (
      'type' => 'table:shop@ome',
      'label' => '来源店铺',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'filtertype' => 'normal',
      'order'           => 2,
    ),
    'shop_type' =>
    array (
      'type' => 'varchar(50)',
      'label' => '店铺类型',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'order'           => 3,
    ),
    'member_id' =>
    array (
      'type' => 'table:members@ome',
      'label' => '会员用户名',
      'comment' => '订货会员ID',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
      'order'           => 4,
    ),
     'delivery_time' =>
    array (
      'type' => 'time',
      'label' => '发货时间',
      'comment' => '发货时间',
      'editable' => false,
      'in_list' => true,
      'filterdefault' => true,
      'filtertype'  => 'time',
      'order'           => 5,
    ),
   
    'sale_time'            => array(
        'type'            => 'time',
        'label'           => '销售时间',
        'editable'        => false,
        'width'           => 130,
        'filtertype'      => 'time',
        'filterdefault'   => true,
        'in_list'         => true,
        'default_in_list' => true,
        'order'           => 6,
    ),
   
    'org_id'               => array(
        'type'            => 'table:operation_organization@ome',
        'label'           => '运营组织',
        'editable'        => false,
        'width'           => 60,
        'filtertype'      => 'normal',
        'filterdefault'   => true,
        'in_list'         => true,
        'default_in_list' => true,
        'order'           => 8,
    ),
    'logi_id' =>
    array (
      'type' => 'table:dly_corp@ome',
      'comment' => '物流公司ID',
      'editable' => false,
      'label' => '物流公司',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'logi_name' =>
    array (
      'type' => 'varchar(100)',
      'label' => '物流公司',
      'comment' => '物流公司名称',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'logi_no' =>
    array (
      'type' => 'varchar(50)',
      'label' => '物流单号',
      'comment' => '物流单号',
      'editable' => false,
      'width' =>130,
      'in_list' => true,
      'default_in_list' => true,
	  'filtertype' => 'normal',
      'filterdefault' => true,
	  'searchtype' => 'nequal',
    ),
    'ship_name' =>
    array (
      'type' => 'varchar(255)',
      'label' => '收货人',
      'comment' => '收货人姓名',
      'editable' => false,
      'searchtype' => 'tequal',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'ship_area' =>
    array (
      'type' => 'region',
      'label' => '收货地区',
      'comment' => '收货人地区',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>130,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'ship_province' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
    ),
    'ship_city' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
    ),
    'ship_district' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
    ),
    'ship_addr' =>
    array (
      'type' => 'text',
      'label' => '收货地址',
      'comment' => '收货人地址',
      'editable' => false,
      'filtertype' => 'normal',
      'width' =>150,
      'in_list' => true,
    ),
    'ship_zip' =>
    array (
      'type' => 'varchar(20)',
      'label' => '收货邮编',
      'comment' => '收货人邮编',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'ship_tel' =>
    array (
      'type' => 'varchar(200)',
      'label' => '收货人电话',
      'comment' => '收货人电话',
      'editable' => false,
      'in_list' => true,
    ),
    'ship_mobile' =>
    array (
      'type' => 'varchar(200)',
      'label' => '收货人手机',
      'comment' => '收货人手机',
      'editable' => false,
      'in_list' => true,
    ),
    'ship_email' =>
    array (
      'type' => 'varchar(150)',
      'label' => '收货人Email',
      'comment' => '收货人Email',
      'editable' => false,
      'in_list' => true,
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'editable' => false,
      'label' => '仓库',
      'width' => 110,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
    ),
    'refund_money' => array(
      'type'     => 'money',
      'default'  => '0',
      'label'    => '退款金额',
      'editable' => false,
      'in_list'  => true,
      'default_in_list' => false,
      'filtertype' => 'normal',
    ),
    'addon' => array(
        'type'     => 'longtext',
        'editable' => false,
        'label'    => '扩展字段',
        'comment'  => '扩展字段',
    ),
   
   
  ),
  'index' =>
  array (
    'ind_delivery_bn' => array ('columns' => array (0 => 'delivery_bn', ), 'prefix' => 'unique', ),
  
    'ind_delivery_time' => array('columns' => array(0 => 'delivery_time', ), ),
    'ind_ship_mobile' => array('columns' => array(0 => 'ship_mobile', ), ),
    'ind_sale_time' => array('columns' => array(0 => 'sale_time', ), ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev: 41996 $',
  'charset' => 'utf8mb4',
);