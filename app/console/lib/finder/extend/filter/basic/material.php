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
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/8/31
 * @Describe: 总库存列表增加额外高级筛选条件
 */
class console_finder_extend_filter_basic_material
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums()
    {
        $list = app::get('ome')->model('brand')->getList('brand_name,brand_id');
        $list = array_column($list, 'brand_name', 'brand_id');
        
        $db['basic_material'] = array(
            'columns' => array(
                'brand_id' => array(
                    'type'            => $list,
                    'label'           => '品牌',
                    'editable'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                    'filtertype'      => 'fuzzy_search_multiple',
                    'filterdefault'   => true,
                ),
                'create_time' => array(
                    'type' => 'time',
                    'label' => '创建时间',
                    'in_list' => true,
                    'default_in_list' => true,
                    'default' => 0,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                ),
                'last_modified' => array(
                    'label' => '最后更新时间',
                    'type' => 'time',
                    'width' => 130,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                ),
            )
        );
        return $db;
    }
}