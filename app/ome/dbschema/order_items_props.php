<?php
$db['order_items_props'] = array(
    'columns' => array(
        'pro_id'   => array(
            'type' => 'int unsigned',
            'extra' => 'auto_increment',
            'pkey' => true,
            'editable' => false,
            'label' => '自增ID',
        ),
        'item_id'  => array(
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '订单明细ID',
            'comment' => '订单明细ID',
        ),
        'order_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '订单ID',
            'comment' => '订单ID'
        ),
        'props_col' => array(
            'type' => 'varchar(50)',
            'label' => '键名',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 20,
        ),
        'props_value' => array(
            'type' => 'varchar(255)',
            'label' => '键值',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 30,
        ),
        'at_time' => array(
            'type' => 'TIMESTAMP',
            'label' => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ),
        'up_time' => array(
            'type' => 'TIMESTAMP',
            'label' => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 99,
        ),
    ),
    'index' => array(
        'ind_order_props_col' => array(
            'columns' => array(
                0 => 'order_id',
                1 => 'props_col',
            ),
        ),
        'ind_item_props_col' => array(
            'columns' => array(
                0 => 'item_id',
                1 => 'props_col',
            ),
        ),
        'idx_at_time' => array('columns' => array('at_time')),
        'idx_up_time' => array('columns' => array('up_time')),
    ),
    'engine' => 'innodb',
    'commit' => '订单明细行扩展属性表',
    'version' => '$Rev: $',
);