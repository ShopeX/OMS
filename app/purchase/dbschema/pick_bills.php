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

$db['pick_bills']=array(
    'columns' => array(
        'bill_id' =>
            array(
              'type' => 'number',
              'required' => true,
              'pkey' => true,
              'extra' => 'auto_increment',
              'editable' => false,
              'order' => 1,
        ),
        'pick_no' =>
            array(
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '拣货单号',
                    'width' => 140,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'searchtype' => 'nequal',
                    'filterdefault' => true,
                    'filtertype' => 'normal',
                    'order' => 2,
            ),
        'pull_status' => array(
            'type' => array(
                'none' => '未处理',
                'running' => '处理中',
                'succ' => '已完成',
                'fail' => '处理失败',
            ),
            'default' => 'none',
            'required' => true,
            'label' => '拉取订单状态',
            'width' => 110,
            'hidden' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 12,
        ),
        'po_id' =>
            array(
                    'type' => 'number',
                    'label' => 'po单编号',
                    'editable' => false,
                    'required' => true,
                    'default' => 0,
            ),
        'po_bn' =>
            array(
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '采购单号',
                    'width' => 140,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'searchtype' => 'nequal',
                    'filterdefault' => true,
                    'filtertype' => 'normal',
                    'order' => 6,
            ),
        'to_branch_bn' =>
            array(
                    'type' => 'varchar(50)',
                    'label' => '入库仓库',
                    'width' => 120,
                    'editable' => false,
                    'order' => 20,
            ),
        'order_cate' =>
            array(
                    'type' => 'varchar(10)',
                    'label' => '订单类别',
                    'width' => 120,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => false,
                    'order' => 8,
            ),
        'pick_num' =>
            array(
                    'type' => 'number',
                    'label' => '拣货数量',
                    'editable' => false,
                    'width' => 90,
                    'in_list' => true,
                    'default_in_list' => true,
                    'required' => true,
                    'default' => 0,
                    'order' => 10,
            ),
        'create_time' =>
            array(
                    'type' => 'time',
                    'label' => '创建时间',
                    'default' => 0,
                    'width' => 130,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'order' => 98,
            ),
        'delivery_status' =>
            array(
                    'type' => 'tinyint(1)',
                    'label' => '发货状态',
                    'width' => 130,
                    'editable' => false,
                    'default' => 1,
                    'order' => 19,
            ),
        'delivery_num' =>
            array(
                    'type' => 'number',
                    'label' => 'VOP发货数量',
                    'editable' => false,
                    'width' => 100,
                    'in_list' => true,
                    'default_in_list' => true,
                    'default' => 0,
                    'order' => 11,
            ),
        'branch_send_num' =>
            array(
                    'type' => 'number',
                    'label' => '仓库发货数量',
                    'editable' => false,
                    'width' => 110,
                    'in_list' => true,
                    'default_in_list' => true,
                    'default' => 0,
                    'order' => 11,
            ),
        'status' =>
            array(
                    'type' => 'tinyint(1)',
                    'label' => '审核状态',
                    'width' => 130,
                    'editable' => false,
                    'default' => 1,
                    'order' => 14,
                    'comment' => '审核状态。1（待审核）、2（已审核）、3（取消）',
            ),
        'last_modified' =>
            array(
                    'type' => 'time',
                    'label' => '最后更新时间',
                    'default' => 0,
                    'in_list' => true,
                    'width' => 130,
                    'editable' => false,
                    'order' => 99,
            ),
            'shop_id' =>
            array(
                    'type' => 'table:shop@ome',
                    'label' => '来源店铺',
                    'editable' => false,
                 
                    'in_list' => true,
                    'default_in_list' => true,
                  
                    'order' => 5,
            ),
            'at_time'           => [
                'type'    => 'TIMESTAMP',
                'label'   => '创建时间',
                'default' => 'CURRENT_TIMESTAMP',
                'width'   => 120,
                // 'in_list' => true,
            ],
            'up_time'           => [
                'type'    => 'TIMESTAMP',
                'label'   => '更新时间',
                'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'width'   => 120,
                // 'in_list' => true,
            ],
            'default_branch_id'=>array(
                'type' => 'table:branch@ome',
                'default'=>0,
                'editable' => false,
                'label' => '首选仓库',
                'width' => 110,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'in_list' => true,
            ),
            'branch_status'=>array(
                'type' => array(
    
                    '0'=>'无',
                    '1'=>'异',
                ),
                'label' => '仓库状态',
                'in_list' => true,
                'default_in_list' => true,
                'width' => 130,
                'editable' => false,
                'default' => '0',
                'order' => 14,
    
            ),
            'is_download_finished' => [
                'type' => [
                    '0' => '否',
                    '1' => '是',
                ],
                'label' => '是否下载完成',
                'in_list' => true,
                'default' => '1',
                'filtertype' => 'normal',
            ],
            'download_msg' => [
                'type' => 'text',
                'label' => '下载失败原因',
                'in_list' => true,
            ],
            'pull_order_msg' => array(
                'type' => 'varchar(200)',
                'label' => '拉取失败原因',
                'editable' => false,
                'in_list' => true,
                'default_in_list' => false,
            ),
    ),
    'index' => array(
        'ind_po_bn' => ['columns'  => ['po_bn']],
        'ind_pick_no' => ['columns'  => ['pick_no'], 'prefix' => 'unique'],
        'ind_at_time' => ['columns' => ['at_time']],
        'ind_up_time' => ['columns' => ['up_time']],
        'ind_pull_status' => array(
            'columns' => array(
                0 => 'pull_status',
            ),
        ),
        'ind_create_time' => array(
            'columns' => array(
                0 => 'create_time',
            ),
        ),
    ),
    'comment' => '拣货单',
    'engine' => 'innodb',
    'version' => '$Rev: $',
);
