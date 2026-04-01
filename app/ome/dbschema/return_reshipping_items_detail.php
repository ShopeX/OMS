<?php
$db['return_reshipping_items_detail'] = array(
    'columns' => array(
        'detail_id' => array(
            'type' => 'bigint unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'comment' => '明细主键ID，自增',
        ),
        'reshipping_id' => array(
            'type' => 'bigint unsigned',
            'required' => true,
            'editable' => false,
            'comment' => '补寄申请单ID（外键）',
        ),
        'item_id' => array(
            'type' => 'bigint unsigned',
            'editable' => false,
            'comment' => '关联商品明细ID（外键，关联return_reshipping_items表）',
        ),
        'sm_id' => array(
            'type' => 'bigint unsigned',
            'editable' => false,
            'comment' => '销售物料ID（点击同意时确认，可修改）',
        ),
        'sales_material_bn' => array(
            'type' => 'varchar(50)',
            'editable' => false,
            'comment' => '销售物料编码（点击同意时确认，可修改）',
        ),
        'nums' => array(
            'type' => 'int unsigned',
            'editable' => false,
            'comment' => '数量（点击同意时确认，可修改）',
        ),
        'price' => array(
            'type' => 'money',
            'editable' => false,
            'comment' => '价格',
        ),
        'at_time' => array(
            'type' => 'TIMESTAMP',
            'default' => 'CURRENT_TIMESTAMP',
            'editable' => false,
            'comment' => '创建时间',
        ),
        'up_time' => array(
            'type' => 'TIMESTAMP',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'editable' => false,
            'comment' => '更新时间',
        ),
    ),
    'index' => array(
        'ind_reshipping_id' => array(
            'columns' => array(
                0 => 'reshipping_id',
            ),
        ),
        'ind_item_id' => array(
            'columns' => array(
                0 => 'item_id',
            ),
        ),
    ),
    'comment' => '补寄申请单商品明细表（实际补寄商品），存储实际补寄的商品信息（点击同意时确认的商品信息，可修改）',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
    'charset' => 'utf8mb4',
);

