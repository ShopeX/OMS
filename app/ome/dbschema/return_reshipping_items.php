<?php
$db['return_reshipping_items'] = array(
    'columns' => array(
        'item_id' => array(
            'type' => 'bigint unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'comment' => '商品明细主键ID，自增',
        ),
        'reshipping_id' => array(
            'type' => 'bigint unsigned',
            'required' => true,
            'editable' => false,
            'comment' => '补寄申请单ID（外键）',
        ),
        'oid' => array(
            'type' => 'varchar(50)',
            'editable' => false,
            'comment' => '子订单号',
        ),
        'sku_uuid' => array(
            'type' => 'varchar(255)',
            'editable' => false,
            'comment' => '商品行唯一标识',
        ),
        'goods_id' => array(
            'type' => 'bigint unsigned',
            'editable' => false,
            'comment' => '销售物料ID',
        ),
        'bn' => array(
            'type' => 'varchar(50)',
            'editable' => false,
            'comment' => '销售物料编码',
        ),
        'nums' => array(
            'type' => 'int unsigned',
            'editable' => false,
            'comment' => '数量',
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
    ),
    'comment' => '补寄申请单商品表，存储补寄申请单的商品明细信息（可选，补寄申请单详情中暂无商品信息）',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
    'charset' => 'utf8mb4',
);

