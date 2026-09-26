<?php
$db['log'] = array (
	'columns' => array(
		'id' => array(
			'type' => 'int unsigned',
			'required' => true,
			'pkey' => true,
			'extra' => 'auto_increment',
			'editable' => false,
		),
		'batchno' => array(
			'type' => 'varchar(50)',
			'required' => true,
			'label' => '批号',
			'in_list'=>true,
			'default_in_list'=>true,
            'filtertype'=>true,
            'searchtype'=>true,
            'searchtype' => 'has',
		),
		'mobile' => array(
			'type' => 'varchar(50)',
			'required' => true,
			'label' => '手机号',
			'in_list'=>true,
			'default_in_list'=>true,
            'filtertype'=>true,
            'searchtype'=>true,
            'searchtype' => 'has',
		),
		'content' => array(
            'type' => 'text',
			'required' => true,
			'label' => '内容',
			'in_list'=>true,
			'default_in_list'=>true,
		),
		'status' => array(
			'default' => '1',			
			'type' => array(
				'0' => '发送失败',
				'1' => '发送成功'
			),
			'required' => true,
			'label' => '状态',
			'in_list'=>true,
			'default_in_list'=>true,
		),
		'msg' => array(
			'type' => 'varchar(100)',
			'label' => '失败原因',
			'in_list'=>true,
			'default_in_list'=>true,
		),
		'sendtime' => array(
			'type' => 'time',
			'required' => true,
			'label' => '发送时间',
			'in_list'=>true,
			'default_in_list'=>true,
		)
	),
	'comment' => '短信日志',
	'version' => '$Rev: 44513 $',
);
