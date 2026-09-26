<?php
$db['mapping'] = array(
    'columns' => array(
        'mapping_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'label' => 'ID',
        ),
        'mold_code' => array(
            'type' => 'varchar(50)',
            'required' => true,
            'label' => '模号编码',
            'in_list' => true,
            'default_in_list' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
        ),
        'type' => array(
            'type' => array('gold' => '定价', 'diamond' => '钻石'),
            'required' => true,
            'default' => 'gold',
            'label' => '类型',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'disabled' => array(
            'type' => array('0' => '启用', '1' => '禁用'),
            'required' => true,
            'default' => '0',
            'label' => '是否启用',
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),
        'at_time' => array(
            'type' => 'time',
            'required' => true,
            'default' => 0,
            'label' => '创建时间',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'up_time' => array(
            'type' => 'time',
            'required' => true,
            'default' => 0,
            'label' => '更新时间',
        ),
    ),
    'index' => array(
        'ind_mold_code' => array('columns' => array(0 => 'mold_code')),
    ),
    'comment' => 'Mapping主表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);
