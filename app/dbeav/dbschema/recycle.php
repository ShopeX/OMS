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
 
$db['recycle']=array (
  'columns' => 
  array (
    'item_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'item_title' => 
    array (
      'type' => 'varchar(200)',
      'label'=>app::get('dbeav')->_('名称'),
      'required' => true,
      'is_title'=>true,
      'in_list'=>true,
      'width'=>200,
      'default_in_list'=>true,
    ),
    'item_type'=>array(
      'label'=>app::get('dbeav')->_('类型'),
      'type' => 'varchar(80)',
      'required' => true,
      'in_list'=>true,
      'width'=>100,
      'default_in_list'=>true,
    ),
    'drop_time'=>array(
      'type' => 'time',
      'label'=>app::get('dbeav')->_('删除时间'),
      'required' => true,
      'in_list'=>true,
      'width'=>150,
      'default_in_list'=>true,
    ),
    'item_sdf'=>array(
      'type' => 'serialize',
      'required' => true,
    ),
  ),
  'comment'=>'回收站',
  'engine' => 'innodb',
  'version' => '$Rev: 40912 $',
);