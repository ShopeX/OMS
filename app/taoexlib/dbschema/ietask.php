<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
$db['ietask']=
    array (
        'columns' =>
        array (
            'task_id' => array (
                'label' => '任务ID',
                'type' => 'number',
                'required' => true,
                'extra' => 'auto_increment',
                'pkey' => true,
                'in_list'=>true,
                'default_in_list'=>true,
                'width'=>60,
                'order'=>10,
            ),
             'queue_id' => 
		    array (
		      'type' => 'int unsigned',
		      'label' => '队列ID',
		      'editable' => false,
		      'in_list'=>true,
		      'default_in_list'=>true,
		      'default' => 0,
		      'order'=>11,
		    ),
           'use_slave_db' =>
		    array (
		      'type' => 'tinyint unsigned',
		      'required' => true,
		      'default' => 0,
		      'editable' => false,
		      'comment' => '是否使用从数据库',
		    ),  
            'task_name' => array (
                'label' => '任务名称',
                'type' => 'varchar(50)',
                'in_list'=>true,
                'default_in_list'=>true,
                'width'=>240,
                'order'=>20,
            ),
            'app' => array (
                'label' => 'app',
                'type' => 'varchar(50)',
                'comment' => '应用名',
            ),
            'model' => array (
                'label' => 'model',
                'type' => 'varchar(50)',
                'comment' => '数据模型名',
            ),
            'op_id' =>
		    array (
		      'type' => 'table:account@pam',
		      'label' => '确认人',
		      'editable' => false,
		      'width' => 60,
		      'filtertype' => 'normal',
		      'filterdefault' => true,
		      'in_list' => true,
		      'default_in_list' => true,
		    ),
            'total_count' => array (
                'label' => '记录总数',
                'type' => 'number',
                'in_list'=>true,
                'default_in_list'=>true,
                'width'=>100,
                'order'=>40,
		    	'default' => 0
            ),
            'finish_count' => array (
                'label' => '执行次数',
                'type' => 'number',
                'in_list'=>false,
                'default_in_list'=>false,
                'width'=>100,
                'order'=>50,
            	'default' => 0
            ),
            'create_time' => array (
                'label' => '创建时间',
                'type' => 'time',
                'in_list'=>true,
                'default_in_list'=>true,
                'width'=>150,
                'order'=>60,
            	'default' => 0
            ),
            'last_time' => array (
                'label' => '最后执行时间',
                'type' => 'time',
                'in_list'=>true,
                'default_in_list'=>true,
                'width'=>150,
                'order'=>70,
            	'default' => 0
            ),
            'expire_time' => array (
                'label' => '到期时间',
                'type' => 'time',
                'in_list'=>true,
                'default_in_list'=>true,
                'width'=>150,
                'order'=>70,
            	'default' => 0
            ),
            'file_name' => array (
                'label' => '文件名',
                'type' => 'varchar(255)',
                'in_list'=>false,
                'default_in_list'=>false,
                'width'=>200,
                'order'=>80,
            ),
            'status' => array (
                'label' => '任务状态',
                'type' => array(
            		'sleeping'  => app::get('base')->_('请求中'),
                    'running'   => app::get('base')->_('执行中'),
                    'finished'  => app::get('base')->_('完成'),
                    'fail'      => app::get('base')->_('失败'),
                ),
                'filtertype' => 'normal',
                'default' => 'sleeping',
                'in_list'=>true,
                'default_in_list'=>true,
                'width'=>80,
                'order'=>90,
            ),
            'export_ver' => array(
                'label' => '导出版本',
                'type' => 'number',
                'in_list'=> false,
                'default_in_list'=> false,
                'required' => true,
                'default' => 1,
            ),
            'filter_data' => array (
                'label' => '查询条件',
                'type' => 'text',
                'in_list'=>false,
                'default_in_list'=>false,
            ),
            'msg' => [
                'type'              => 'varchar(255)',
                'label'             => '错误信息',
                'in_list'           => true,
                'default_in_list'   => true,
            ],
        ),
		'index' => array(
			'idx_status' => array(
				'columns'=>array('status'),
			),
		),
        'comment' => '导出任务列表',
        'engine' => 'innodb',
		'version' => '$Rev: 40654 $',
    );
