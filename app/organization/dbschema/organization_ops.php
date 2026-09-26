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

$db['organization_ops'] = array(
    'columns' => array(
        'op_id'   => array(
            'type'     => 'table:account@pam',
            'required' => true,
            'pkey'     => true,
            'editable' => false,
            'label'    => '操作员ID',
        ),
        'org_ids' => array(
            'type'            => 'text',
            'label'           => '门店组织架构ID',
            'in_list'         => false,
            'default_in_list' => false,
            'filterdefault'   => false,
        ),
    ),
    'comment' => '门店组织架构管理员关联表',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);