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


$db['export_template']=array (
  'columns' => 
  array (
    'et_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'et_name' => 
    array (
      'type' => 'varchar(50)',
      'required' => true,
      'default' => '',
      'label' => app::get('desktop')->_('导出模板名称'),
      'width' => 200,
      'editable' => true,
      'in_list' => true,
      'default_in_list' => true,
      'is_title' => true,
    ),
    'et_type' => 
    array (
      'type' => 'varchar(50)',
      'label' => app::get('desktop')->_('模板对象'),
      'required' => true,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'et_filter' => 
    array (
      'type' => 'text',
      'required' => true,
      'default' => '',
      'label' => app::get('desktop')->_('模板条件'),
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
    ),
  ),
  'index' => 
  array (
    'ind_type' => 
    array (
      'columns' => 
      array (
        0 => 'et_type',
      ),
    ),
    'ind_name' => 
    array (
      'columns' => 
      array (
        0 => 'et_name',
      ),
    ),
  ),
  'comment' => '新版导出模板存储表',
  'version' => '$Rev: 42201 $',
);
