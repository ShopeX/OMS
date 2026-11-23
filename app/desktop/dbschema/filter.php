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
 
/**
* @table magicvars;
*
* @package Schemas
* @version $
* @copyright 2003-2009 ShopEx
* @license Commercial
*/

$db['filter']=array (
  'columns' => 
  array (
    'filter_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'filter_name' => 
    array (
      'type' => 'varchar(20)',
      'required' => false,
      'label' => app::get('desktop')->_('筛选器名'),
      'class' => 'span-3',
      'in_list' => true,
      'default_in_list' => true,
      'editable' => false,
    ),
    'user_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'label' => app::get('desktop')->_('用户id'),
      'width' => 110,
      'editable' => false,
      'hidden' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'model' => 
    array (
      'type' => 'varchar(100)',
      'required' => true,
      'label' => app::get('desktop')->_('表'),
      'class' => 'span-3',
      'in_list' => true,
      'default_in_list' => true,
      'editable' => false,
    ),
    'filter_query' => 
    array (
      'type' => 'text',
      'hidden' => true,
      'label' => app::get('desktop')->_('筛选条件'),
      'class' => 'span-4',
      'in_list' => true,
      'editable' => false,
    ),
    'ctl'=>array(
      'type' => 'varchar(100)',
      'required' => true,
      'default'=>'',
      'label' => app::get('desktop')->_('控制器'),
      'class' => 'span-3',
      'editable' => false,
    ),
    'app'=>array(
      'type' => 'varchar(50)',
      'required' => true,
      'default'=>'',
      'label' => app::get('desktop')->_('控制器'),
      'class' => 'span-3',
      'editable' => false,
    ),
    'act'=>array(
      'type' => 'varchar(50)',
      'required' => true,
      'default'=>'',
      'label' => app::get('desktop')->_('方法'),
      'class' => 'span-3',
      'editable' => false,
    ),
    'create_time' => 
    array (
      'type' => 'time',
      'default' => 0,
      'required' => true,
      'label' => app::get('desktop')->_('建立时间'),
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
  ),
  'comment' => '后台搜索过滤器表',	
);