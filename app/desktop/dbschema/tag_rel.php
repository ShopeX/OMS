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
 
$db['tag_rel']=array (
  'columns' => 
  array (
    'tag_id' => 
    array (
      'type' => 'table:tag',
      'sdfpath' => 'tag/tag_id',
      'required' => true,
      'default' => 0,
      'pkey' => true,
      'editable' => false,
      'comment' => '标签id',
    ),
    'rel_id' => 
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'default' => 0,
      'pkey' => true,
      'editable' => false,
      'comment' => '关联id',
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
  ),
  'comment' => 'tag和对象关联表',
  'version' => '$Rev$',
);
