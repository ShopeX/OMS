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
 
$db['queue']=array (
  'columns' => 
  array (
    'queue_id' => 
    array (
      'type' => 'bigint(20)',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'editable' => false,
    ),
    'queue_title' => 
    array (
      'type' => 'varchar(50)',
      'label'=>app::get('base')->_('队列名称'),
      'required' => true,
      'is_title'=>true,
      'in_list'=>true,
      'width'=>300,
      'default_in_list'=>true,
    ),
    'status'=>array(
      'label'=>app::get('base')->_('状态'),
        'type'=>array(
            'running'=>app::get('base')->_('运行中'),
            'hibernate'=>app::get('base')->_('休眠中'),
            'paused'=>app::get('base')->_('已暂停'),
            'failure'=>app::get('base')->_('执行失败'),
        ),
        'required' => true,
        'default' => 'hibernate',
      'in_list'=>true,
      'width'=>100,
      'default_in_list'=>true,
    ),
    'worker'=>array(
      'type' => 'varchar(200)',
      'required' => true,
      //'in_list'=>true,
      'width'=>200,
      //'default_in_list'=>true,
    ),
    'start_time'=>array(
      'type' => 'time',
      'label'=>app::get('base')->_('任务产生时间'),
      'required' => true,
      'in_list'=>true,
      'width'=>150,
//      'default_in_list'=>true,
    ),
    'worker_active'=>array(
      'type' => 'time',
      'label'=>app::get('base')->_('上次运行时间'),
      'in_list'=>true,
      'width'=>150,
      'default_in_list'=>true,
    ),
    'total'=>array(
      'type' => 'number',
      'label'=>app::get('base')->_('队列长度'),
      'in_list'=>true,
      'width'=>120,
      'default_in_list'=>true,
    ),
    'remaining'=>array(
      'type' => 'number',
      'label'=>app::get('base')->_('剩余任务'),
      'in_list'=>true,
      'width'=>120,
      'default_in_list'=>true,
    ),
    'cursor_id'=>array(
      'type' => 'varchar(255)',
      'label'=>app::get('base')->_('执行游标'),
      'default'=>0,
      'required' => true,
    ),
    'runkey'=>array(
      'type' => 'char(32)',
    ),
    'task_name'=>array(
      'type' => 'varchar(50)',
      'label'=>app::get('base')->_('永久任务名称'),
    ),
    'params'=>array(
      'type' => 'serialize',
      'label'=>app::get('base')->_('参数'),
      'required' => true,
      'comment'=>app::get('base')->_('参数，通常就是filter'),
    ),
    'errmsg'=>array(
      'type' => 'varchar(255)',
      'default_in_list'=>true,
      'in_list'=>true,
      'width'=>300,
      'label'=>app::get('base')->_('错误信息'),
    ),
  ),
  'index' => 
  array (
    'ind_worker' => 
    array (
      'columns' => 
      array (
        0 => 'worker',
      ),
    ),
    'ind_worker_active' => 
    array (
      'columns' => 
      array (
        0 => 'worker_active',
      ),
    ),
    'ind_status' => 
    array (
      'columns' => 
      array (
        0 => 'status',
      ),
    ),
  ),
  'comment' => '对列表',
  'engine' => 'innodb',
  'version' => '$Rev: 40912 $',
  'ignore_cache' => true,

);


//需要id从大到小的执行
