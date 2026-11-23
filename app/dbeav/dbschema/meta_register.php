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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
$db['meta_register']=array (
  'columns' => 
  array (
    'mr_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'meta id',
      'width' => 110,
      'comment' => 'meta id',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'tbl_name' => 
    array (
      'type' => 'varchar(100)',
      'required' => true,
      'label' => app::get('dbeav')->_('表名'),
      'width' => 110,
      'comment' => app::get('dbeav')->_('表名'),
      'editable' => false,
      'in_list' => true,
      'is_title' => true,
    ),
    'pk_name' => 
    array (
      'type' => 'varchar(255)',
      'required' => true,
      'label' => app::get('dbeav')->_('主表主键名'),
      'width' => 110,
      'comment' => app::get('dbeav')->_('主表主键名'),
      'editable' => false,
      'in_list' => true,
    ),
    'col_name' => 
    array (
      'type' => 'varchar(100)',
      'required' => true,
      'label' => app::get('dbeav')->_('字段名'),
      'width' => 110,
      'comment' => app::get('dbeav')->_('字段名'),
      'editable' => false,
      'in_list' => true,
    ),
    'col_type' => 
    array (
      'type' => 'varchar(255)',
      'required' => true,
      'label' => app::get('dbeav')->_('字段类型'),
      'width' => 110,
      'comment' => app::get('dbeav')->_('字段类型'),
      'editable' => false,
      'in_list' => true,
    ),
    'col_desc' => 
    array (
      'type' => 'serialize',
      'required' => true,
      'label' => app::get('dbeav')->_('字段描述'),
      'width' => 110,
      'comment' => app::get('dbeav')->_('字段描述'),
      'editable' => false,
      'in_list' => true,
    ),
  ),
  'comment' => app::get('dbeav')->_('meta关联表'),
  'index' => 
  array (
    
    'idx_col_name' => 
    array (
      'columns' => 
      array (
        0 => 'col_name',
      ),
    ),
    'idx_tbl_col' => 
    array (
      'columns' => 
      array (
        0 => 'tbl_name',
        1 => 'col_name',
      ),
    ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev: 43312 $',
);
