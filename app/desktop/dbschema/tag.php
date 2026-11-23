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
 
$db['tag']=array (
  'columns' => 
  array (
    'tag_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'tag_name' => 
    array (
      'type' => 'varchar(20)',
      'required' => true,
      'default' => '',
      'label' => app::get('desktop')->_('标签名'),
      'width' => 200,
      'editable' => true,
      'in_list' => true,
      'default_in_list' => true,
      'is_title' => true,
    ),
    'tag_mode' => 
    array (
      'type' => 
      array (
        'normal' => app::get('desktop')->_('普通标签'),
        'filter' => app::get('desktop')->_('自动标签'),
      ),
      'default' => 'normal',
      'label' => app::get('desktop')->_('标签类型'),
      'required' => true,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'app_id' => 
    array (
      'type' => 'varchar(32)',
      'label' => app::get('desktop')->_('应用'),
      'required' => true,
      'width' => 100,
      'in_list' => true,
    ),
    'tag_type' => 
    array (
      'type' => 'varchar(255)',
      'required' => true,
      'default' => '',
      'label' => app::get('desktop')->_('标签对象'),
      'editable' => false,
      'in_list' => true,
    ),
    'tag_abbr' => 
    array (
      'type' => 'varchar(150)',
      'required' => true,
      'default' => '',
      'label' => app::get('desktop')->_('标签备注'),
      'editable' => false,
      'in_list' => true,
    ),
    'tag_bgcolor' => 
    array (
      'type' => 'varchar(7)',
      'required' => true,
      'default' => '',
      'label' => app::get('desktop')->_('标签背景颜色'),
      'editable' => false,
      'in_list' => true,
    ),
    'tag_fgcolor' => 
    array (
      'type' => 'varchar(7)',
      'required' => true,
      'default' => '',
      'label' => app::get('desktop')->_('标签字体颜色'),
      'editable' => false,
      'in_list' => true,
    ),
    'tag_filter' => 
    array (
      'type' => 'varchar(255)',
      'required' => true,
      'default' => '',
      'label' => app::get('desktop')->_('标签条件'),
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
    ),
    'rel_count' => 
    array (
      'type' => 'number',
      'default' => 0,
      'required' => true,
      'editable' => false,
      'comment' => '关联的个数',
    ),
  ),
  'index' => 
  array (
    'ind_type' => 
    array (
      'columns' => 
      array (
        0 => 'tag_type',
      ),
    ),
    'ind_name' => 
    array (
      'columns' => 
      array (
        0 => 'tag_name',
      ),
    ),
  ),
  'comment' => '列表标签表',
  'version' => '$Rev: 42201 $',
);
