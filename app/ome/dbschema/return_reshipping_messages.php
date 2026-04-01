<?php
$db['return_reshipping_messages'] = array(
    'columns' => array(
        'message_id' => array(
            'type' => 'bigint unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'comment' => '留言主键ID，自增',
        ),
        'reshipping_id' => array(
            'type' => 'bigint unsigned',
            'required' => true,
            'editable' => false,
            'comment' => '补寄申请单ID（外键）',
        ),
        'message_type' => array(
            'type' => 'varchar(20)',
            'required' => true,
            'editable' => false,
            'comment' => '留言类型（消费者/商家）',
        ),
        'content' => array(
            'type' => 'text',
            'required' => true,
            'editable' => false,
            'comment' => '留言内容',
        ),
        'attachment' => array(
            'type' => 'text',
            'editable' => false,
            'comment' => '附件URL',
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
        'op_id' => array(
            'type' => 'bigint unsigned',
            'editable' => false,
            'comment' => '操作员ID（商家留言时）',
        ),
    ),
    'index' => array(
        'ind_reshipping_id' => array(
            'columns' => array(
                0 => 'reshipping_id',
            ),
        ),
    ),
    'comment' => '补寄留言表，存储补寄申请单的留言信息（消费者留言和商家留言）',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
    'charset' => 'utf8mb4',
);

