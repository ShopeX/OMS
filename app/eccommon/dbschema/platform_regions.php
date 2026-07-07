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

$db['platform_regions'] = array(
    'columns' => array(
        'id'                => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'editable' => false,
        ),
        'outregion_id'      => array(
            'type'            => 'bigint(20)',
            'default'         => 0,
            // 'required'        => true,
            'label'           => '平台区域id',
            'width'           => 100,
            'default_in_list' => true,
            'in_list'         => true,
            'editable'        => false,
        ),
        'outregion_name'    => array(
            'type'            => 'varchar(50)',
            // 'required'        => true,
            'default'         => '',
            'label'           => '平台区域名称',
            'width'           => 100,
            'default_in_list' => true,
            'in_list'         => true,
            'searchtype'      => 'has',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'editable'        => false,
        ),
        'outparent_id'      => array(
            'type'            => 'bigint(20)',
            'default'         => 0,
            // 'required'        => true,
            'label'           => '平台父区域id',
            'width'           => 100,
            'default_in_list' => true,
            'in_list'         => true,
            'editable'        => false,
        ),
        'outparent_name'    => array(
            'type'            => 'varchar(50)',
            'default'         => 0,

            'label'           => '平台父区域名称',
            'width'           => 100,
            'default_in_list' => true,
            'in_list'         => true,
            'editable'        => false,
        ),
        'out_path_name'     => array(
            'type'    => 'varchar(255)',
            'label'   => '平台多级路径',
            'default' => '',
        ),
        'out_path_id'       => array(
            'type'    => 'varchar(255)',
            'label'   => '平台多级路径ID',
            'default' => '',
        ),
        'region_grade'      => array(
            'type'            => 'number',
            'editable'        => false,
            'label'           => '区域层级',
            'default'         => 1,
            'default_in_list' => true,
            'in_list'         => true,
        ),
        'shop_type'         => array(
            'type'            => 'varchar(50)',
            'editable'        => false,
            'label'           => '平台类型',
            'default_in_list' => true,
            'in_list'         => true,
            'searchtype'      => 'has',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'local_region_id'   => array(
            'type'            => 'bigint(20)',
            // 'required'        => true,
            'default'         => 0,
            'label'           => 'oms区域id',
            'width'           => 100,
            'editable'        => false,
            'default_in_list' => true,
            'in_list'         => true,
        ),
        'local_region_name' => array(
            'type'            => 'varchar(50)',
            'label'           => 'oms区域名称',
            'width'           => 100,
            'default_in_list' => true,
            'in_list'         => true,
            'editable'        => false,
            'searchtype'      => 'has',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'local_path_name'   => array(
            'type'    => 'varchar(255)',
            'label'   => 'oms多级路径',
            // 'default' => '',
        ),
        'mapping'           => array(
            'type'            => array(
                0 => '未关联',
                1 => '已关联',
            ),
            'label'           => '是否关联',
            'default'         => '0',
            'in_list'         => true,
            'default_in_list' => true,
        ),
    ),
    'index'   => array(
        'ind_local_region_id' => array('columns' => array('local_region_id')),
        'ind_local_path_name' => array('columns' => array('local_path_name','shop_type'),'prefix' => 'unique'),
        'ind_shop_outregion_name' => array (
            'columns' => array (
                0 => 'shop_type',
                1 => 'outregion_name',
            ),
        ),
        'ind_shop_region_name' => array (
            'columns' => array (
                0 => 'shop_type',
                1 => 'local_region_name',
            ),
        ),
        'ind_shop_outregion' => array (
            'columns' => array (
                0 => 'shop_type',
                1 => 'region_grade',
                2 => 'outregion_name',
                3 => 'outparent_id',
            ),
        ),
    ),
    'comment' => '平台地区表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
    'charset' => 'utf8mb4',
);
