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
 
$db['kvstore']=array (
  'columns' => 
  array (
    'id' => array(
        'type' => 'number',
        'pkey' => true,
        'extra' => 'auto_increment',
    ),
    'prefix' => array(
        'type'=>'varchar(255)',
        'required'=>true,
    ),
    'key' => array(
        'type'=>'varchar(255)',
        'required'=>true,
    ),
    'value' => array(
        'type'=>'serialize',
    ),
    'dateline' => array(
        'type'=>'time',
    ),
    'ttl' => array(
        'type'=>'time',
        'default' => 0,
    ),
  ),
  'index' => 
  array (
    'ind_prefix' => 
    array (
      'columns' => 
      array (
        0 => 'prefix',
      ),
    ),
    'ind_key' => 
    array (
      'columns' => 
      array (
        0 => 'key',
      ),
    ),
  ),
  'comment' => 'kvstore存储表',
  'engine' => 'innodb',
  'version' => '$Rev: 41137 $',
  'ignore_cache' => true,
);
