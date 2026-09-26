<?php
$db['workwx_rule_store'] = array(
    'columns' => array(
        'id' => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'editable' => false,
        ),
        'rule_id' => array(
            'type'     => 'table:workwx_rule@monitor',
            'required' => true,
            'label'    => '通知规则',
            'editable' => false,
        ),
        'store_id' => array(
            'type'     => 'number',
            'required' => true,
            'label'    => '门店ID',
            'editable' => false,
        ),
        'store_bn' => array(
            'type'  => 'varchar(20)',
            'label' => '门店编码',
        ),
        'store_name' => array(
            'type'  => 'varchar(255)',
            'label' => '门店名称',
        ),
        'at_time' => array(
            'type'    => 'TIMESTAMP',
            'label'   => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
        ),
    ),
    'index' => array(
        'uni_store_id' => array(
            'columns' => array('store_id'),
            'prefix'  => 'UNIQUE',
        ),
        'ind_rule_id' => array(
            'columns' => array('rule_id'),
        ),
    ),
    'comment' => '企微通知规则关联门店',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
