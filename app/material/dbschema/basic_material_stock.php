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
 * 基础物料库存数据结构
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['basic_material_stock']=array (
  'columns' =>
  array (
    'bm_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'width' => 110,
      'hidden' => true,
      'editable' => false,
      'pkey' => true,
    ),
    'store' =>
    array (
      'type' => 'int NOT NULL',
      'editable' => false,
      'comment' => '库存（各仓库 的库存总和）',
      'label' => '库存',
      'default' => 0,
      'width' => 65,
      'in_list' => true,
      'filtertype' => 'number',
      'filterdefault' => true,
      'default_in_list' => true,
    ),
    'store_freeze' =>
    array (
      'type' => 'int NOT NULL',
      'sdfpath' => 'freez',
      'label' => '冻结库存',
      'width' => 65,
      'hidden' => true,
      'filtertype' => 'number',
      'filterdefault' => true,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
      'default' => 0,
    ),
    'alert_store' =>
    array (
      'type' => 'number',
      'label' => '安全库存数',
      'default' => 0,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
    ),
    'last_modified' =>
    array (
      'type' => 'last_modify',
      'label' => '最后修改日期',
      'width' => 90,
      'editable' => false,
      'in_list' => true,
    ),
    'real_store_lastmodify' =>
    array (
      'type' => 'time',
      'editable' => false,
      'comment' => '实际库存最后更新时间',
    ),
    'max_store_lastmodify' =>
    array (
      'type' => 'time',
      'editable' => false,
      'comment' => '最大可下单库存最后更新时间',
    ),
    'inc_store_lastmodify' =>
    array (
      'type' => 'time',
      'editable' => false,
      'default' => 0,
      'comment' => '库存最后添加时间（仅库存增加时更新）',
      'in_list' => false,
      'default_in_list' => false,
    ),
  ),
  'index' => array (
      'ind_last_modified' => array (
          'columns' => array (
              0 => 'last_modified',
          ),
      ),
      'ind_store_lastmodify' => array (
          'columns' => array (
              0 => 'max_store_lastmodify',
          ),
      ),
      'ind_inc_store_lastmodify' => array (
          'columns' => array (
              0 => 'inc_store_lastmodify',
          ),
      ),
  ),
  'comment' => '基础物料总库存表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
