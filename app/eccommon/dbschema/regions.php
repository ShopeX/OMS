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


/**
 * @table regions;
 *
 * @package Schemas
 * @version $
 * @copyright 2003-2009 ShopEx
 * @license Commercial
 */

$db['regions']=array (
    'columns' =>
    array (
        'region_id' =>
        array (
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
        ),
        'local_name' =>
        array (
            'type' => 'varchar(50)',
            'required' => true,
            'default' => '',
            'label'=> '当地名称',
            'width'=>100,
            'default_in_list'=>true,
            'in_list'=>true,
            'editable' => false,
        ),
        'package' =>
        array (
            'type' => 'varchar(20)',
            'required' => true,
            'default' => '',
            'label'=> '数据包',
            'width'=>100,
            'default_in_list'=>true,
            'in_list'=>true,
            'editable' => false,
        ),
        'p_region_id' =>
        array (
            'type' => 'int unsigned',
            'editable' => false,
            'comment' => '父区域ID',
        ),
        'region_path' =>
        array (
            'type' => 'varchar(255)',
            'width'=>300,
            'editable' => false,
            'comment' => '区域路径',
        ),
        'region_grade' =>
        array (
            'type' => 'number',
            'editable' => false,
            'comment' => '路径级数',
        ),
        'p_1' =>
        array (
            'type' => 'varchar(50)',
            'editable' => false,
        ),
        'p_2' =>
        array (
            'type' => 'varchar(50)',
            'editable' => false,
        ),
        'ordernum' =>
        array (
            'type' => 'number',
            'editable' => true,
            'comment' => '排序',
        ),
        'haschild' =>
        array (
            'type' => 'number',
            'default' => 0,
            'editable' => false,
            'comment' => '',
        ),
        'disabled' =>
        array (
            'type' => 'bool',
            'default' => 'false',
            'editable' => false,
        ),
        'source'=>array(
            'type'=>array(
                'systems'=>'系统',
                'platform'=>'平台'
            ),
            'default'=>'systems',
            'label' => '增加类型',
            'comment' => '增加类型',
        ),
    ),
    'index' =>
  array (
    'ind_p_regions_id' =>
    array (
        'columns' =>
        array (
          0 => 'p_region_id',
          1 => 'region_grade',
          2 => 'local_name',
        ),
        'prefix' => 'unique',
    ),
  ),
  'comment' => '电商商务通用应用区域表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
  'charset' => 'utf8mb4',
);
