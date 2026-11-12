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
 * 基础物料关联特性数据结构
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['basic_material_feature_group']=array (
  'columns' =>
  array (
    'bm_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'width' => 150,
      'hidden' => true,
      'editable' => false,
      'pkey' => true,
    ),
    'feature_group_id' =>
    array (
      'type' => 'int unsigned',
      'default' => 0,
      'required' => true,
      'hidden' => true,
      'width' => 150,
      'editable' => false,
    ),
  ),
  'comment' => '基础物料关联特性扩展表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
