<?php
$db['productprice']=array (
  'columns' => 
  array (
    'id' =>
    array (
        'type' => 'int unsigned',
        'required' => true,
        'pkey' => true,
        'extra' => 'auto_increment',
    ),
    'material_bn' => 
    array (
      'type' => 'varchar(50)',
     
      'label' => '物料编码',

      'in_list' => true,
      'default_in_list' => true,
      'searchtype'      => 'has',
      'filterdefault'   => true,
      'filtertype'      => 'textarea',
      'order' => 10,
    ),
    'bm_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'label' => '物料ID',
      'width' => 110,
      'editable' => false,
      'order' => 11,
    ),
    'store_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'label' => '门店id',
      'editable' => false,
      'order' => 20,
    ),
    'store_bn' => 
    array (
        'type'            => 'varchar(20)',
        'label'           => '门店编码',
        'in_list'         => true,
        'default_in_list' => true,
    ),
    'store_sort'     => array(
            'label'           => '门店分类',
            'type'            => 'varchar(20)',
            'width'           => 130,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
    'tariff'      =>
    array(
        'type'            => 'int unsigned',
        'label'           => 'tariff',
        'in_list'         => true,
        'default'         => '0',
    ),
    'price' => 
    array (
      'type' => 'money',
      'label' => '单价',
      'in_list' => true,
      'default_in_list' => true,
      'order' => 31,
    ),
    'at_time'       => array(
      'type'    => 'TIMESTAMP',
      'label'   => '创建时间',
      'default' => 'CURRENT_TIMESTAMP',
      'width'   => 120,
      'in_list' => true,
      'order'   => 11,
    ),
    'up_time'       => array(
      'type'    => 'TIMESTAMP',
      'label'   => '更新时间',
      'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
      'width'   => 120,
      'in_list' => true,
      'order'   => 11,
    ),
    'onsale'=>array(
         'type'     => 'tinyint',
         'label'    => 'onsale',
    ),
    'disabled'           => array(
            'type'     => [
                'false' => '是',
                'true' => '否'
            ],
            'required' => true,
            'default'  => 'false',
            'editable' => false,
            'label'           => '是否有效',
            'in_list'       => true,
            'default_in_list' => true,
      ),
    'price_status'        => [
        'type'            => [
            '0' => '正常',
            '1' => '异常',

        ],
        'editable'        => false,
        'width'           => 100,
        'in_list'         => true,
        'default'         => '0',
        'comment'         => '价格获取状态',
        'label'           => '价格获取状态',
        'in_list'         => true,
        'default_in_list' => true,
        'filtertype'      => 'normal',
        'filterdefault'   => true,
    ],
    'sync_status' =>
    array (
     'type' =>
      array (
        0 => '待同步',
        1 => '同步成功',
        2 => '同步失败',
        3=>'运行中',
      ),
    'default' => '0',
    'required' => true,
    'label' => '同步状态',
    'in_list' => true,
    'default_in_list' => true,
    'searchtype' => 'has',
    'editable' => false,
    'filtertype' => 'normal',
    'filterdefault' => true,
    'order' => 90,
    ),
    'sync_msg'=>array(
        'type' => 'varchar(250)',
        'label' => '同步原因',
    ),
    'msg_id'          => array(
      'type'            => 'varchar(60)',
      'filtertype' => 'normal',
      'filterdefault'   => true,
      'in_list'         => true,
      'default_in_list' => true,
      'filtertype'      => 'normal',
      'filterdefault'   => true,

      'label'           => 'msg_id',

    ),
  ),
  'index' =>
      array (
        'ind_store_material'     => array('columns' => array(0 => 'store_id', 1 => 'bm_id'), 'prefix' => 'unique'),
        
      ),
  'comment' => 'pos price关联表',
  'engine' => 'innodb',
  'version' => '$Rev: 40654 $',
);
