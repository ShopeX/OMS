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

$db['branch_wms_channel'] = array (
  'columns' => array (
      'id' => array(
          'type' => 'int unsigned',
          'required' => true,
          'pkey' => true,
          'extra' => 'auto_increment',
          'label' => '自增主键',
          'editable' => false,
          'in_list' => false,
          'default_in_list' => false,
          'order' => 1,
      ),
      'branch_id' => array(
          'type' => 'table:branch@ome',
          'required' => true,
          'editable' => false,
          'label' => '仓库',
          'comment' => '仓库ID',
      ),
      'shop_type' => array(
          'type' => 'varchar(50)',
          'required' => true,
          'default' => '',
          'editable' => false,
          'label' => '平台',
          'comment' => '店铺类型/平台',
      ),
      'channel_id' => array(
          'type' => 'table:channel@channel',
          'required' => true,
          'editable' => false,
          'label' => '第三方仓储',
          'comment' => 'WMS渠道ID',
          'width' => 130,
          'in_list' => true,
          'default_in_list' => true,
      ),
      'at_time' => array(
          'type' => 'TIMESTAMP',
          'label' => '创建时间',
          'default' => 'CURRENT_TIMESTAMP',
          'filtertype' => 'time',
          'filterdefault' => true,
          'in_list' => true,
          'default_in_list' => true,
          'width' => 135,
          'order' => 98,
      ),
      'up_time' => array(
          'type' => 'TIMESTAMP',
          'required' => true,
          'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
          'label' => '更新时间',
          'editable' => false,
          'in_list' => true,
          'default_in_list' => true,
          'order' => 99,
      ),
    ),
    'index' => array (
        'ind_branch_shop' => array(
            'columns' => array(
                'branch_id',
                'shop_type',
            ),
            'prefix' => 'unique',
        ),
        'ind_at_time' => array(
            'columns' => array(
                'at_time',
            ),
        ),
    ),
    'comment' => '仓库关联WMS仓储配置表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);
