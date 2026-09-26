<?php
/**
 * 模号主数据（SAP / Mapping 模号选择器）
 */
$db['mold'] = array(
    'columns' => array(
        'm_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'label' => 'ID',
            'width' => 110,
            'hidden' => true,
            'editable' => false,
        ),
        'mold_code' => array(
            'type' => 'varchar(50)',
            'label' => '模号',
            'width' => 150,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'required' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'order' => 1,
        ),
        'create_time' => array(
            'type' => 'time',
            'label' => '创建时间',
            'in_list' => true,
            'default_in_list' => true,
            'default' => 0,
            'order' => 3,
        ),
    ),
    'comment' => '模号表',
    'index' => array(
        'uni_mold_code' => array(
            'columns' => array(0 => 'mold_code'),
            'prefix' => 'UNIQUE',
        ),
    ),
    'engine' => 'innodb',
);
