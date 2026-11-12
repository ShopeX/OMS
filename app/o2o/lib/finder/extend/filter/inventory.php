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

class o2o_finder_extend_filter_inventory
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    /**
     * 获取_extend_colums
     * @return mixed 返回结果
     */
    public function get_extend_colums()
    {
        $storeList = app::get('o2o')->model('store')->getList('store_bn,name');
        $storeList = array_column($storeList, 'name', 'store_bn');

        $db['inventory'] = array(
            'columns' => array(
                'physics_id'    => array(
                    'type'          => $storeList,
                    'label'         => '门店',
                    'filtertype'    => 'fuzzy_search',
                    'filterdefault' => true,
                    'panel_id'      => 'inventory_finder_top',
                    'order'         => 1,

                ),
            ),
        );

        return $db;
    }

}
