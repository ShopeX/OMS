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

$db['delivery_sync']=array (
      'columns' =>
        array(
            'syn_id' => array(
                'type' => 'int unsigned',
                'required' => true,
                'pkey' => true,
                'extra' => 'auto_increment',
                'label' => '编号',
                'in_list' => true,
                'default_in_list' => true,
                'width' => 60,
                'hidden' => true,
                'order' => 10,
            ),
            'order_id' => array(
                'type' => 'int unsigned',
                'default' => '0',
                'required' => true,
                'label' => '订单ID',
                'in_list' => false,
                'default_in_list' => false,
				'order' => 20,
            ),
            'order_bn' =>
			    array (
			      'type' => 'varchar(32)',
			      'default' => '',
                  'required' => true,
			      'label' => '订单号',
			      'is_title' => true,
			      'width' => 130,
			      'searchtype' => 'nequal',
			      'filtertype' => 'normal',
			      'filterdefault' => true,
			      'in_list' => true,
			      'default_in_list' => true,
				  'order' => 30,
            ),
            'delivery_id' => array(
                'type' => 'int unsigned',
                'default' => '0',
                'required' => true,
                'label' => '发货单ID',
                'in_list' => false,
                'default_in_list' => false,
				'order' => 40,
            ),
            'delivery_bn' =>
			    array (
			      'type' => 'varchar(32)',
			      'default' => '',
                  'required' => true,
			      'label' => '发货单号',
			      'width' => 130,
			      'searchtype' => 'nequal',
			      'filtertype' => 'normal',
			      'filterdefault' => true,
			      'in_list' => true,
			      'default_in_list' => true,
			      'is_title' => true,
				  'order' => 50,
            ),
            'logi_no' =>
			    array (
			      'type' => 'varchar(50)',
			      'default' => '',
			      'required' => true,
			      'label' => '物流单号',
			      'width' => 130,
			      'in_list' => true,
			      'default_in_list' => true,
			      'filtertype' => 'normal',
			      'filterdefault' => true,
			      'searchtype' => 'nequal',
				  'order' => 55,
            ),
            'logi_id' =>
			    array (
			      'type' => 'table:dly_corp@ome',
			      'required' => true,
			      'default' => '0',
			      'label' => '物流公司',
			      'filtertype' => 'normal',
			      'filterdefault' => true,
				  'order' => 60,
            ),
            'branch_id' =>
			    array (
			      'type' => 'table:branch@ome',
			      'required' => true,
			      'default' => '0',
			      'label' => '仓库',
			      'width' => 110,
			      'filtertype' => 'normal',
			      'filterdefault' => true,
			      'in_list' => true,
				  'default_in_list' => true,
				  'order' => 65,
            ),
            'status' =>
			    array (
			      'type' =>
				      array (
				        'succ' => '已发货',
				        'failed' => '发货失败',
				        'cancel' => '已取消',
				        'progress' => '等待配货',
				        'timeout' => '超时',
				        'ready' => '等待配货',
				        'stop' => '暂停',
				        'back' => '打回',
				      ),
			      'default' => 'ready',
			      'width' => 80,
			      'required' => true,
				  'filterdefault' => true,
				  'default_in_list' => true,
			      'in_list' => true,
			      'label' => '发货状态',
				  'order' => 79,
            ),
            'shop_id' =>
			    array (
			      'type' => 'table:shop@ome',
			      'required' => true,
			      'default' => '',
			      'label' => '来源店铺',
			      'width' => 120,
			      'default_in_list' => true,
			      'in_list' => true,
			      'filtertype' => 'normal',
			      'filterdefault' => true,
				  'order' => 75,
            ),
            'delivery_time' =>
                array (
                  'type' => 'time',
                  'required' => true,
                  'default' => '0',
                  'label' => '发货时间',
                  'default_in_list' => true,
			      'in_list' => true,
				  'order' => 80,
            ),
            'dateline' =>
                array (
                  'type' => 'time',
                  'required' => true,
                  'default' => '0',
                  'label' => '回写时间',
                  'default_in_list' => true,
			      'in_list' => true,
				  'order' => 85,
            ),
			'sync' =>
				array (
				  'type' => array(
					  'none' => '未回写',
					  'run' => '运行中',
					  'fail' => '回写失败',
					  'succ' => '回写成功',
				  ),
				  'required' => true,
				  'default' => 'none',
				  'label' => '回写状态',
				  'default_in_list' => true,
			      'in_list' => true,
				  'filtertype' => 'normal',
				  'filterdefault' => true,
				  'width' => 80,
				  'order' => 90,
				),
            'split_model' =>
                array (
                  'type' => 'tinyint(1)',
                  'required' => true,
                  'default' => '0',
                  'label' => '拆单方式',
                  'order' => 95,
            ),
            'split_type' =>
                array (
                  'type' => 'tinyint(1)',
                  'required' => true,
                  'default' => '0',
                  'label' => '回写方式',
                  'order' => 95,
            ),
    ),
    'index' =>
	  array (
	    'ind_order_bn' =>
        array (
            'columns' =>
            array (
              0 => 'order_bn',
            ),
        ),
	    'ind_delivery_bn' =>
	    array (
	      'columns' =>
	      array (
	        0 => 'delivery_bn',
	      ),
	    ),
	    'ind_logi_no' =>
        array (
		      'columns' =>
		      array (
		        0 => 'logi_no',
		      ),
        ),
    ),
    'comment' => '订单拆单发货单回写状态表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);