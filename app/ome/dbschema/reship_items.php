<?php
$db['reship_items']=array (
  'columns' =>
  array (
    'item_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
      'comment' => '明细ID',
    ),
    'reship_id' =>
    array (
      'type' => 'table:reship@ome',
      'editable' => false,
      'required' => true,
      'comment' => '退换货单号',
    ),
    'obj_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'default' => 0,
      'editable' => false,
    ),
    'bn' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'required' => true,
      'comment' => '货品bn',
    ),
    'product_name' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'comment' => '货品名称',
    ),
    'product_id' =>
    array (
      'type' => 'int unsigned',
      'editable' => false,
      'comment' => '货品ID',
    ),
    'num' =>
    array (
      'type' => 'number',
      'editable' => false,
      'required' => true,
      'default' => 1,
      'comment' => '数量',
    ),
    'price' =>
    array (
      'type' => 'money',
      'default' => '0',
      'required' => true,
      'editable' => false,
    ),
    'amount' =>
    array (
      'type' => 'money',
      'default' => '0',
      'required' => true,
      'comment' => '小计',
      'label' => '小计',
      'editable' => false,
    ),
    'branch_id' =>
    array (
      'type' => 'number',
      'editable' => false,
      'label'=>'仓库ID',
      'comment'=>'仓库ID',
    ),
    'op_id' =>
    array (
      'type' => 'table:account@pam',
      'editable' => false,
    ),
    'return_type' =>
    array (
      'type' =>
      array (
        'return' => '退货',
        'change' => '换货',
        'refuse' => '拒收退货',
      ),
      'default' => 'return',
      'required' => true,
      'comment' => '退换货类型',
      'editable' => false,
      'label' => '退换货类型',
      'width' =>65,
      'in_list' => true,
      'default_in_list' => true,
    ),
     'defective_num' =>
    array (
      'type' => 'number',
      'editable' => false,
      'required' => true,
      'default' => 0,
      'label' => '不良品',
    ),
    'normal_num' =>
    array (
      'type' => 'number',
      'editable' => false,
      'required' => true,
      'default' => 0,
      'label' => '良品',
    ),
    'order_item_id' =>
    array (
      'type' => 'int unsigned',
      'editable' => false,
      'comment' => '原订单id',
    ),
    'oid'               => array(
      'type'     => 'varchar(50)',
      'default'  => 0,
      'editable' => false,
      'label'    => '子订单号',
  ),
    'changebranch_id'    => array(
        'type'     => 'number',
        'editable' => false,
        'label'    => '换货仓库ID',
        'comment'  => '换货仓库ID',
      ),
    'up_time' => array(
        'type'       => 'TIMESTAMP',
        'label'      => '修改时间',
        'filtertype' => 'time',
        'order'           => 100,
    ),
    'obj_type' =>
    array (
      'type' => 'varchar(50)',
      'default' => 'product',
      'label'=>'货品类型',
      'editable' => false,
    ),
    'shop_goods_bn' =>
    array (
      'type' => 'varchar(50)',
   
      'default' => 0,
      'label'=>'店铺货号',
      'editable' => false,
    ),
    'quantity' =>
    array (
      'type' => 'number',
      'default' => 0,
      'label'=>'申请数量',
      'editable' => false,
    ),
    'sku_uuid' => array(
        'type' => 'varchar(255)',
        'editable' => false,
        'label' => '商品行唯一标识',
    ),
    'is_del' =>
    array(
      'type' => 'bool',
      'default' => 'false',
      'editable' => false,
      'comment' => '删除状态,可选值:true(是),false(否)'
    ),
    'inventory_type' => array (
        'type' => 'varchar(30)',
        'editable' => false,
        'label' => 'WMS入库类型',
    ),
    'luckybag_id' => array(
        'type' => 'int unsigned',
        'required' => true,
        'default' => 0,
        'editable' => false,
        'label' => '福袋组合ID',
        'comment' => '福袋组合ID',
    ),
  ),
  'index' => array (
    'ind_bn' =>
    array (
        'columns' =>
        array (
          0 => 'bn',
          1 => 'obj_id',
        ),
    ),
    'ind_return_type' =>
    array (
        'columns' =>
        array (
          0 => 'return_type',
        ),
    ),
      'ind_order_item_id'=>array('columns'=>array('order_item_id'))
  ),
  'comment' => '退货申请明细',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
  'charset' => 'utf8mb4',
);
