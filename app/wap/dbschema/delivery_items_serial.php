<?php
/**
 * WAP 端发货单唯一码（备货扫描先落本地，回写 OMS 后标记 sync_status=1）
 */
$db['delivery_items_serial'] = array(
    'columns' =>
    array(
        'item_serial_id' =>
        array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
        ),
        'delivery_id' =>
        array(
            'type' => 'table:delivery@wap',
            'required' => true,
            'default' => 0,
            'editable' => false,
        ),
        'product_id' =>
        array(
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'comment' => '基础物料 bm_id',
        ),
        'bn' =>
        array(
            'type' => 'varchar(30)',
            'editable' => false,
            'is_title' => true,
        ),
        'product_name' =>
        array(
            'type' => 'varchar(200)',
            'editable' => false,
        ),
        'serial_id' =>
        array(
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'comment' => 'machinecenter material_sn 主键',
        ),
        'serial_number' =>
        array(
            'type' => 'varchar(30)',
            'required' => true,
            'editable' => false,
            'label' => '唯一码',
        ),
        'op_no' =>
        array(
            'type' => 'varchar(30)',
            'default' => '',
            'editable' => false,
            'label' => '工号',
            'comment' => '备货扫描工号',
        ),
        'status' =>
        array(
            'type' => 'tinyint(1)',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '状态',
        ),
        'sync_status' =>
        array(
            'type' => 'tinyint(1)',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '回写状态',
            'comment' => '0=仅WAP本地,1=已回写OMS',
        ),
        'create_time' =>
        array(
            'type' => 'time',
            'label' => '创建时间',
            'default' => 0,
        ),
    ),
    'index' =>
    array(
        'idx_delivery_id' =>
        array(
            'columns' =>
            array(
                0 => 'delivery_id',
            ),
        ),
        'idx_serial_number' =>
        array(
            'columns' =>
            array(
                0 => 'serial_number',
            ),
        ),
        'idx_sync_serial' =>
        array(
            'columns' =>
            array(
                0 => 'sync_status',
                1 => 'serial_number',
            ),
        ),
    ),
    'comment' => 'WAP发货单货品唯一码表（备货扫描本地存储）',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);
