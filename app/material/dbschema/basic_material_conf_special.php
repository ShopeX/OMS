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

$db['basic_material_conf_special'] = array(
    'columns' => array(
        'bm_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'width' => 150,
            'hidden' => true,
            'editable' => false,
            'pkey' => true,
        ),
        'openscan' => array(
            'type' => 'varchar(20)',
            'label' => '是否开启特殊扫码配置',
            'editable' => false,
            'default'  => 'close',
        ),
        'fromposition' => array(
            'type' => 'number',
            'label' => '开始识别位数',
            'editable' => false,
            'default'  => 0,
        ),
        'toposition' => array(
            'type' => 'number',
            'label' => '结束识别位数',
            'editable' => false,
            'default'  => 1,
        ),
    ),
    'comment' => '基础物料特殊扫码配置表',
);