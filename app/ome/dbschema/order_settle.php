<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$db['order_settle'] = array(
	'columns' => array(
		'id'                  => array(
			'type'     => 'int unsigned',
			'required' => true,
			'pkey'     => true,
			'extra'    => 'auto_increment',
			'label'    => 'ID',
			'width'    => 110,
			'hidden'   => true,
			'editable' => false,
		),
		'order_id'            => array(
			'type'     => 'table:orders@ome',
			'required' => true,
			'editable' => false,
		),
		'type'                => array(
			'type'          => 'varchar(200)',//佣金：commission
			'default'       => '',
			'label'         => '金额类型',
			'editable'      => false,
			'filtertype' => 'normal',
			'filterdefault' => true,
		),
		'material_bn'         => array(
			'type'            => 'varchar(200)',
			'label'           => '物料编码',
			'width'           => 120,
			'editable'        => false,
			'in_list'         => true,
			'default_in_list' => true,
			'required'        => true,
			'searchtype'      => 'nequal',
			'filtertype'      => 'normal',
			'filterdefault'   => true,
		),
		'oid'                 => array(
			'type'     => 'varchar(50)',
			'default'  => 0,
			'editable' => false,
			'label'    => '子订单号',
		),
		'real_comission'      => array(
			'type'    => 'money',
			'default' => '0.000',
			'label'   => '真实佣金',
			'width'   => 75,
		),
		'estimated_comission' => array(
			'type'    => 'money',
			'default' => '0.000',
			'label'   => '预估佣金',
			'width'   => 75,
		),
		'commission_rate'     => array(
			'type'    => 'money',
			'default' => '0.000',
			'label'   => '佣金率',
			'width'   => 75,
		),
		'extend'              => array(
			'type'     => 'serialize',
			'editable' => false,
		),
	),
	'index'   => array(
		'ind_order_oid' => array(
			'columns' => array('order_id', 'oid'),
			'prefix'  => 'unique'
		),
	),
	'comment' => '平台订单金额明细',
	'engine'  => 'innodb',
	'version' => '$Rev:  $',
	'charset' => 'utf8mb4',
);