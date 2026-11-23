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
 
$db['files']=array (
  'columns' => 
  array (
    'file_id' => array('type'=>'number','pkey'=>true,'extra' => 'auto_increment'),
    'file_path' => array('type'=>'text'),
    'file_type' =>array('type'=>array('private'=>'','public'=>''),'default'=>'public'),
    'last_change_time' => array('type'=>'last_modify'),
  ), 
  'comment' => 'storager文件存储信息',
  'version' => '$Rev: 41137 $',
);
