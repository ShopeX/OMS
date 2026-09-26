<?php
/**
 * 数据字典头表
 */
$db['metadata'] = array(
    'columns' => array(
        'metadata_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'label' => 'ID',
            'in_list' => true,
            'default_in_list' => false,
        ),
        'code' => array(
            'type' => 'varchar(30)',
            'required' => true,
            'label' => '业务编码',
            'in_list' => true,
            'default_in_list' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),
        'name' => array(
            'type' => 'varchar(100)',
            'required' => true,
            'label' => '业务名称',
            'in_list' => true,
            'default_in_list' => true,
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),
        'unique_key' => array(
            'type' => 'varchar(50)',
            'required' => true,
            'label' => '唯一KEY',
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
            'filtertype' => 'time',
        ),
        'up_time' => array(
            'type' => 'time',
            'required' => true,
            'label' => '更新时间',
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'time',
        ),
    ),
    'index' => array(
        'uni_code_unique_key' => array(
            'columns' => array(0 => 'code', 1 => 'unique_key'),
            'prefix' => 'UNIQUE',
        ),
    ),
    'comment' => '数据字典头表',
    'engine' => 'innodb',
);
