<?php
$db['bill_match_notify_log'] = array(
    'columns' => array(
        'id' => array(
            'type' => 'number',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
        ),
        'bill_date' => array(
            'type' => 'varchar(10)',
            'required' => true,
            'label' => '账单日期',
            'comment' => '统计的账单日期 Y-m-d',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'send_date' => array(
            'type' => 'varchar(10)',
            'required' => true,
            'label' => '发送日期',
            'comment' => '实际发送日期 Y-m-d',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'shop_summary' => array(
            'type' => 'longtext',
            'label' => '店铺汇总',
            'comment' => 'JSON：各店铺失败条数',
        ),
        'total_fail_count' => array(
            'type' => 'number',
            'required' => true,
            'default' => 0,
            'label' => '失败合计',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'receivers' => array(
            'type' => 'text',
            'label' => '收件人',
        ),
        'send_status' => array(
            'type' => array(
                'succ' => '成功',
                'fail' => '失败',
            ),
            'default' => 'fail',
            'label' => '发送状态',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'send_result' => array(
            'type' => 'varchar(255)',
            'default' => '',
            'label' => '发送结果',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'send_time' => array(
            'type' => 'time',
            'label' => '发送时间',
            'in_list' => true,
            'default_in_list' => true,
        ),
    ),
    'index' => array(
        'ind_bill_send_date' => array(
            'columns' => array(
                0 => 'bill_date',
                1 => 'send_date',
            ),
        ),
    ),
    'comment' => '店铺收支明细匹配失败日报发送日志',
    'engine' => 'innodb',
    'version' => '$Rev: 1 $',
);
