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
 * 基础物料配置信息数据结构
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['basic_material_props']=array (
  'columns' =>
  array (
    'id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 150,
      'hidden' => true,
      'editable' => false,
      'comment' => '自增主键ID'
    ),

    'bm_id' =>
    array(
        'type' => 'int unsigned',
        'required' => true,
        'width' => 150,
        'hidden' => true,
        'editable' => false,

    ),
    'props_col'=>array(
      'type' => 'varchar(50)',
      'editable' => false,
      'label' => '键名',
     
    ),
    'props_value'=>array(
      'type' => 'varchar(200)',
      'editable' => false,
      'label' => '值',
     
    ),
    'at_time'       => array(
        'type'            => 'TIMESTAMP',
        'label'           => '创建时间',
        'default_in_list' => true,
        'in_list'         => true,
        'filtertype'      => 'time',
        'filterdefault'   => true,
        'default'         => 'CURRENT_TIMESTAMP',
        'order' => 100,
    ),
    'up_time'       => array(
        'type'            => 'TIMESTAMP',
        'label'           => '更新时间',
        'default_in_list' => true,
        'in_list'         => true,
        'filtertype'      => 'time',
        'filterdefault'   => true,
        'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'order' => 110,
    ),
  ),
  'comment' => '基础物料配置表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
