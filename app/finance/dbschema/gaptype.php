<?php
$db['gaptype'] = array(
    'columns' =>
    array(
        'id' =>
        array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
        ),
        'gap_name' =>
        array(
            'type' => 'varchar(20)',
            'required' => true,
            'label' => '类型名称',
            'width' => 120,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'order' => 1,
        ),
        'status' =>
        array(
            'type' => [
                '0' => '无效',
                '1' => '有效',
            ],
            'required' => true,
            'default' => '1',
            'label' => '状态',
            'comment' => '状态。可选状态：1（有效），0（无效）',
            'width' => 65,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'order' => 2,
        ),
        'disabled' =>
        array(
            'type' => [
                'true' => '是',
                'false' => '否',
            ],
            'required' => true,
            'default' => 'false',
            'label' => '删除状态',
            'comment' => '删除状态',
            'width' => 65,
            'in_list' => false,
            'default_in_list' => false,
            'order' => 3,
        ),
        'at_time' =>
        array(
            'type' => 'TIMESTAMP',
            'label' => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'width' => 120,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 4,
        ),
        'up_time' =>
        array(
            'type' => 'TIMESTAMP',
            'label' => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width' => 120,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 5,
        ),
    ),

    'index' => array(
        'ind_gap_name' =>
        array(
            'columns' =>
            array(
                0 => 'gap_name',
            ),
        ),
        'ind_status' =>
        array(
            'columns' =>
            array(
                0 => 'status',
            ),
        ),
        'ind_disabled' =>
        array(
            'columns' =>
            array(
                0 => 'disabled',
            ),
        ),
        'ind_at_time' =>
        array(
            'columns' =>
            array(
                0 => 'at_time',
            ),
        ),
        'ind_up_time' =>
        array(
            'columns' =>
            array(
                0 => 'up_time',
            ),
        ),
    ),
    'comment' => '差异类型表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);
