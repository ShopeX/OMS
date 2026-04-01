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

$db['diff_items'] = array(
    'columns' => array(
        'diff_items_id'  => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
        ),
        'diff_id'        => array(
            'type'     => 'table:diff@taoguaniostockorder',
            'required' => true,
            'default'  => 0,
            'editable' => false,
        ),
        'diff_bn'             => array(
            'type'            => 'varchar(32)',
            'required'        => true,
            'label'           => '出入库单号',
            'is_title'        => true,
            'default_in_list' => true,
            'in_list'         => true,
            'width'           => 125,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'to_branch_id'        => array(
            'type'            => 'table:branch@ome',
            'required'        => false,
            'label'           => '收货仓库名称',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'default_in_list' => true,
            'in_list'         => true,
        ),
        'diff_reason'         =>array(
            'type'            => array(
                // ''       =>  '',
                'less'   =>  '短发',
                'lost'   =>  '丢失',
                'wrong'  =>  '收货操作失误',
                'other'  =>  '其他原因',
                'more'   =>  '超发',
            ),
            // 'default'         =>  '',
            'label'           => '差异原因',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'diff_memo'           => array(
            'type'            => 'varchar(200)',
            'label'           => '差异备注',
            'width'           => 160,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'filtertype'      => 'yes',
            'filterdefault'   => true,
        ),
        'responsible'       => array(
            'type'          => array(
                1 => 'N/A',
                2 => '发货方',
                3 => '收货方',
                4 => '第三方物流',
            ),
            'default'       => 1,
            'label'         => '责任方',
            'width'         => 60,
            'editable'      => false,
            'filtertype'    => 'has',
            'filterdefault' => true,
        ),
        'diff_status'       => array(
            'type'          => array(
                1 => '未处理',
                2 => '处理中',
                3 => '已处理',
                4 => '取消',
            ),
            'default'       => 1,
            'label'         => '处理状态',
            'width'         => 60,
            'editable'      => false,
            'filtertype'    => 'has',
            'filterdefault' => true,
        ),
        'handle_type'         =>array(
            'type'            => array(
                // ''            =>  '',
                'transfer'    =>  '调拔',
                'directOut'   =>  '直接出库',
            ),
            // 'default'         =>  '',
            'label'           => '最终处理类型',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'handle_bn'      => array(
            'type'            => 'varchar(32)',
            'label'           => '最终处理单号',
            'searchtype'      => 'has',
            'default_in_list' => true,
            'in_list'         => true,
            'comment'         => '调拨单号/直接出库单号',
        ),
        'operator'         => array(
            'type'            => 'varchar(30)',
            'comment'         => '操作人员',
            'default_in_list' => true,
            'in_list'         => true,
            'label'           => '操作人员',
        ),
        'product_id'    => array(
            'type'     => 'table:products@ome',
            'required' => true,
            'comment'         => '基础主档ID',
        ),
        'product_name'  => array(
            'type'            => 'varchar(200)',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '货品名称',
        ),
        'bn'            => array(
            'type'            => 'varchar(30)',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '货号',
        ),
        'unit'          => array(
            'type'            => 'varchar(20)',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '单位',
        ),
        'price'         => array(
            'type'            => 'money',
            'label'           => '单价',
            'required'        => true,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'nums'          => array(
            'type'            => 'number',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '数量',
        ),
        'original_items_id'        => array(
            'type'     => 'int unsigned',
            'default'  => 0,
            'editable' => false,
        ),
        'at_time'               => array(
            'type'    => 'TIMESTAMP',
            'label'   => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'width'   => 120,
            'in_list' => false,
        ),
        'up_time'               => array(
            'type'    => 'TIMESTAMP',
            'label'   => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'   => 120,
            'in_list' => false,
        ),
    ),
    'index'   => array(
        'ind_diff_bn'     => array(
            'columns' => array(
                0 => 'diff_bn',
            ),
        ),
        'ind_product_id' => array(
            'columns' => array(
                0 => 'product_id',
            ),
        ),
        'ind_bn'         => array(
            'columns' => array(
                0 => 'bn',
            ),
        ),
        'ind_at_time' => array(
            'columns' => array(
                0 => 'at_time',
            ),
        ),
        'ind_up_time' => array(
            'columns' => array(
                0 => 'up_time',
            ),
        ),
    ),
    'comment' => '调拨入库差异明细表',
    'engine'  => 'innodb',
    'version' => '$Rev:  51996',
);
