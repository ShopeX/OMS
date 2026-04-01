<?php

$db['po_props'] = array(
    'columns' => array(
        'id'   => array(
            'type'     => 'int unsigned',
            'extra'    => 'auto_increment',
            'pkey'     => true,
            'editable' => false,
            'label'    => '自增ID',
        ),
        'po_id'  => array(
            'type'          => 'table:po@purchase',
            'label'         => '采购单ID',
            'parent_id'     => true,
            'in_list'       => true,
            'default_in_list' => true,
            'order'         => 10,
        ),
        'props_col'  => array(
            'type'          => 'varchar(255)',
            'label'         => '键名',
            'in_list'       => true,
            'default_in_list' => true,
            'order'         => 20,
        ),
        'props_value'  => array(
            'type'          => 'varchar(255)',
            'label'         => '键值',
            'in_list'       => true,
            'default_in_list' => true,
            'order'         => 30,
        ),
        'at_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 1000,
        ),
        'up_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 1010,
        ),
    ),
    'index'   => array(
        'idx_props_col'     => array('columns' => array('props_col')),
        'idx_at_time'       => array('columns' => array('at_time')),
        'idx_up_time'       => array('columns' => array('up_time')),
    ),
    'engine'  => 'innodb',
    'commit'  => '',
    'version' => 'Rev: 41996 $',
);

