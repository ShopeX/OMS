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


$db['difference_items_freeze'] = array(
    'columns' => array(
        'id'     => array(
            'type'      => 'mediumint(8)',
            'label'     => 'ID',
            'comment'   => 'ID',
            'required'  => true,
            'pkey'      => true,
            'extra'     => 'auto_increment',
        ),
        'diff_id'     => array(
            'type'              => 'table:difference@console',
            'label'             => '盘点差异单ID',
            'in_list'           => false,
            'default_in_list'   => false,
            'order'             => 10,
        ),
        'di_id'     => array(
            'type'              => 'table:difference_items@console',
            'label'             => '盘点差异单明细ID',
            'in_list'           => false,
            'default_in_list'   => false,
            'order'             => 10,
        ),
        'branch_id' => array(
            'type'              => 'table:branch@ome',
            'label'             => '盘点库存',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 70,
        ),
        'bm_id'    => array(
            'type'              => 'int unsigned',
            'label'             => '基础物料ID',
            'in_list'           => false,
            'default_in_list'   => false,
            'order'             => 20,
        ),
        'material_bn'    => array(
            'type'              => 'varchar(200)',
            'label'             => '基础物料编码',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 30,
        ),
        'freeze_num' => array(
            'type'              => 'mediumint',
            'label'             => '冻结',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 80,
        ),
        'out_status'   => array(
            'type'              => [
                '0'=>'','1'=>'生成失败'
            ],
            'label'             => '出库单',
            'default'           => '0',
            'in_list'           => false,
            'default_in_list'   => false,
            'order'             => 90,
        ),
    ),
    'comment' => '盘点差异单明细冻结',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
