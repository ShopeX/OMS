<?php
$db['order_objects_freeze'] = array(
    'columns' => array(
        'freeze_id' => array(
            'type'     => 'int unsigned',
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'editable' => false,
            'label'    => '编号',
            'in_list'  => false,
            'default_in_list' => false,
            'comment' => '自增主键ID'
        ),
        'obj_id' => array(
            'type'     => 'table:order_objects@ome',
            'default'  => '0',
            'editable' => false,
            'label'    => '订单子单ID',
            'in_list'  => false,
            'default_in_list' => false,
            'comment' => '订单子单ID,关联ome_order_objects.obj_id'
        ),
        'order_id' => array(
            'type'     => 'table:orders@ome',
            'default'  => '0',
            'editable' => false,
            'label'    => '订单ID',
            'in_list'  => false,
            'default_in_list' => false,
            'comment' => '订单ID,关联ome_orders.order_id'
        ),
        'order_bn' => array(
            'type'     => 'varchar(32)',
            'default'  => '',
            'editable' => false,
            'label'    => '订单编号',
            'in_list'  => true,
            'default_in_list' => true,
            'comment' => '订单编号'
        ),
        'sm_id' => array(
            'type'     => 'table:sales_material@material',
            'default'  => '0',
            'editable' => false,
            'label'    => '销售物料ID',
            'in_list'  => false,
            'default_in_list' => false,
            'comment' => '销售物料ID,关联material_sales_material.sm_id'
        ),
        'bn' => array(
            'type'     => 'varchar(40)',
            'editable' => false,
            'label'    => '销售物料编码',
            'in_list'  => true,
            'default_in_list' => true,
            'comment' => '销售物料编码'
        ),
        'shop_id' => array(
            'type'     => 'table:shop@ome',
            'default'  => '0',
            'editable' => false,
            'label'    => '店铺ID',
            'in_list'  => false,
            'default_in_list' => false,
            'comment' => '店铺ID,关联ome_shop.shop_id'
        ),
        'quantity' => array(
            'type'     => 'number',
            'default'  => 0,
            'editable' => false,
            'label'    => '冻结数量',
            'in_list'  => true,
            'default_in_list' => true,
            'comment' => '冻结数量'
        ),
        'at_time' => array(
            'type'     => 'time',
            'default'  => 0,
            'editable' => false,
            'label'    => '创建时间',
            'in_list'  => true,
            'default_in_list' => false,
            'comment' => '创建时间'
        ),
        'up_time' => array(
            'type'     => 'time',
            'default'  => 0,
            'editable' => false,
            'label'    => '更新时间',
            'in_list'  => true,
            'default_in_list' => false,
            'comment' => '更新时间'
        ),
    ),
    'index' => array(
        'idx_order_bn' => array('columns' => array('order_bn')),
        'idx_bn' => array('columns' => array('bn')),
    ),
    'comment' => '订单子单冻结表,用于存储处理中的订单数据',
    'charset' => 'utf8mb4',
    'engine'  => 'innodb',
    'version' => '$Rev: $',
);
