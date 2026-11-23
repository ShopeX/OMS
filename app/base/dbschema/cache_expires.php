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
 
$db['cache_expires']=array (
  'columns' => 
  array (
    'type' => array(
        'type' => 'varchar(20)',
        'pkey' => true,
        'required' => true,
    ),
    'name' => array(
        'type'=>'varchar(255)',
        'pkey' => true,
        'required'=>true,
    ),
    'expire' => array(
        'type'=>'time',
        'required' => true,
    ),
    'app' => array(
        'type'=>'varchar(50)',
        'required'=>true,
    ),
  ),
  'index' => 
  array (
    'ind_name' => 
    array (
      'columns' => 
      array (
        0 => 'name',
      ),
    ),
  ), 
  'comment' => '缓存过期表', 
  'engine' => 'innodb',
  'version' => '$Rev: 41137 $',
  'ignore_cache' => true,
);
