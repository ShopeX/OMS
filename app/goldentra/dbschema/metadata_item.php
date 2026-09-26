<?php
/**
 * 数据字典值表
 */
$db['metadata_item'] = array(
    'columns' => array(
        'item_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'label' => 'ID',
            'in_list' => true,
            'default_in_list' => false,
        ),
        'metadata_id' => array(
            'type' => 'table:metadata@goldentra',
            'required' => true,
            'label' => '字典头',
            'in_list' => true,
            'default_in_list' => false,
        ),
        'unique_key' => array(
            'type' => 'varchar(50)',
            'required' => true,
            'label' => '业务值',
            'in_list' => true,
            'default_in_list' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),
        'ext_field' => array(
            'type' => 'longtext',
            'label' => '扩展字段',
        ),
        'at_time' => array(
            'type' => 'time',
            'required' => true,
            'label' => '创建时间',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'up_time' => array(
            'type' => 'time',
            'required' => true,
            'label' => '更新时间',
            'in_list' => true,
            'default_in_list' => false,
        ),
    ),
    'index' => array(
        'uni_metadata_unique_key' => array(
            'columns' => array(0 => 'metadata_id', 1 => 'unique_key'),
            'prefix' => 'UNIQUE',
        ),
    ),
    'comment' => '数据字典值表',
    'engine' => 'innodb',
);
