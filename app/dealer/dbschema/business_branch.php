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


$db['business_branch'] = array(
    'columns' => array(
        'bs_id' => array(
            'type' => 'mediumint(8)',
            'required' => true,
            'label' => '经销商ID',
        ),
        'branch_id' => array(
            'type' => 'number',
            'required' => true,
            'label' => '仓库ID',
        ),
    ),
    'index' => array(
        'index_bs_id' => array('columns' => array('bs_id')),
        'index_branch_id' => array('columns' => array('branch_id'),'prefix'=>'unique'),
    ),
    'comment' => '经销商仓库',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);