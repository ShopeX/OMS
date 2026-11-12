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
$db['setting']= array(
  'columns' =>
  array (
    's_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 110,
      'hidden' => true,
      'editable' => false,
    ),
    'name' =>
    array (
      'type' => 'varchar(200)',
      'required' => true,
      'label' => '访问来源名称',
      'is_title' => true,
      'searchtype' => 'head',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'width' => 250,
    ),
    'code' =>
    array (
      'type' => 'varchar(200)',
      'required' => true,
      'label' => '标识',
      'default_in_list' => true,
      'in_list' => true,
      'editable' => false,
    ),
    'interfacekey' =>
    array (
      'type' => 'varchar(100)',
      'required' => true,
      'label' => '私钥',
      'default_in_list' => true,
      'in_list' => true,
      'editable' => false,
    ),
    'config' =>
    array (
      'type' => 'serialize',
      'editable' => false,
    ),
    'status' =>
    array (
      'type' => 'tinyint(1)',
      'label' => '接口状态',
      'width' => 100,
      'in_list' => true,
      'default_in_list' => true,
      'default' => 0,
    ),
    'is_data_mask' =>
    array (
      'type' => 'tinyint(1)',
      'label' => 'Data Mask',
      'width' => 100,
      'in_list' => true,
      'default_in_list' => true,
      'default' => 0,
      'comment' => '0表示否，1表示是，默认是0',
    ),
  ),
  'comment' => '开放数据接口配置表',
  'index' =>
    array (
      'uni_code' =>
        array (
    	  'columns' =>
            array (
              0 => 'code',
            ),
	      'prefix' => 'UNIQUE',
        ),
	  'ind_name' =>
        array (
    	  'columns' =>
            array (
              0 => 'name',
            ),
        ),
    ),
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
