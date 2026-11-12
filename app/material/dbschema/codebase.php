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
 * 基础物料与条码关联数据结构
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['codebase']=array (
  'columns' =>
  array (
    'bm_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'width' => 150,
      'hidden' => true,
      'editable' => false,
    ),
    'type' =>
    array (
      'type' => 'tinyint(1)',
      'label' => '条码类型',
      'required' => true,
      'default' => 1,
      'hidden' => true,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'code' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'label' => '条码',
      'required' => true,
      'hidden' => true,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'at_time'       => array(
        'type'            => 'TIMESTAMP',
        'label'           => '创建时间',
        'default_in_list' => false,
        'in_list'         => false,
        'default'         => 'CURRENT_TIMESTAMP',
    ),
    'up_time'       => array(
        'type'            => 'TIMESTAMP',
        'label'           => '更新时间',
        'default_in_list' => false,
        'in_list'         => false,
        'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ),
  ),
'index' =>
  array (
    'ind_code' =>
    array (
        'columns' =>
        array (
          0 => 'code',
        ),
    ),
    'ind_bm_id' =>
    array (
        'columns' =>
        array (
          0 => 'bm_id',
        ),
    ),
    'idx_at_time'           => array(
        'columns' => array(
            0 => 'at_time'
        )
    ),
    'idx_up_time'           => array(
        'columns' => array(
            0 => 'up_time'
        )
    ),
  ),
  'comment' => '基础物料与条码关联表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
