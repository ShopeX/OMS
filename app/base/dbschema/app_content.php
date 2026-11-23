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
 
$db['app_content']=array (
  'columns' => 
  array (
    'content_id'=>array(
      'type' => 'number',
      'pkey' => true,
      'extra' => 'auto_increment',
    ),
    'content_type' => 
    array (
      'type' => 'varchar(80)',
      'required' => true,
      'width' => 100,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'app_id' => 
    array (
      'type' => 'table:apps',
      'required' => true,
      'width' => 100,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'content_name'=>array(
        'type'=>'varchar(80)',
    ),
    'content_title'=>array(
        'type'=>'varchar(100)',
        'is_title'=>true,
    ),
    'content_path'=>array(
        'type'=>'varchar(255)',
    ),
	'ordernum' => 
    array (
      'type' => 'smallint(4)',
      'default' => 50,
      'label' => app::get('base')->_('排序'),
    ),
	'input_time' =>
    array (
      'type' => 'time',
      'label' => app::get('base')->_('加载时间'),
    ),
    'disabled'=>array(
        'type'=>'bool',
        'default'=>'false'
    )
  ),
  'comment' => 'app资源信息表',
  'version' => '$Rev: 44008 $',
);
