<?php
/**
 * SN 扩展属性（props）
 */
$db['material_sn_props'] = array(
    'columns' => array(
        'id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'label' => 'ID',
            'width' => 110,
            'hidden' => true,
            'editable' => false,
        ),
        'sn_id' => array(
            'type' => 'table:material_sn@machinecenter',
            'required' => true,
            'label' => 'SN ID',
            'editable' => false,
        ),
        'props_col' => array(
            'type' => 'varchar(50)',
            'required' => true,
            'default' => '',
            'label' => '键名',
            'editable' => false,
        ),
        'props_value' => array(
            'type' => 'varchar(200)',
            'default' => '',
            'label' => '值',
            'editable' => false,
        ),
        'at_time' => array(
            'type' => 'time',
            'label' => '创建时间',
            'default' => 0,
        ),
        'up_time' => array(
            'type' => 'time',
            'label' => '更新时间',
            'default' => 0,
        ),
    ),
    'index' => array(
        'uni_sn_props_col' => array(
            'columns' => array(0 => 'sn_id', 1 => 'props_col'),
            'prefix' => 'unique',
        ),
        'ind_props_col' => array(
            'columns' => array(0 => 'props_col'),
        ),
    ),
    'comment' => '机器中心SN扩展属性',
    'engine' => 'innodb',
    'version' => '$Rev$',
);
