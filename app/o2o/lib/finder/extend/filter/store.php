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

class o2o_finder_extend_filter_store
{

    /**
     * 获取_extend_colums
     * @return mixed 返回结果
     */
    public function get_extend_colums()
    {
        // 门店下拉：[编码]名称
        $storeRows = app::get('o2o')->model('store')->getList('store_id,store_bn,name', array(), 0, -1);
        $storeList = array();
        foreach ($storeRows as $row) {
            $storeList[$row['store_id']] = '[' . $row['store_bn'] . ']' . $row['name'];
        }

        // 获取经销商列表，key是bs_bn，value是[bs_bn]name格式
        $dealerList = app::get('dealer')->model('business')->getList('bs_bn,name');
        $dealerList = array_column($dealerList, 'name', 'bs_bn');
        
        // 格式化经销商列表，添加bs_bn前缀
        $formattedDealerList = array();
        foreach ($dealerList as $bs_bn => $name) {
            $formattedDealerList[$bs_bn] = '[' . $bs_bn . ']' . $name;
        }

        $db['store'] = array(
            'columns' => array(
                'store_id' => array(
                    'type'          => $storeList,
                    'label'         => '门店',
                    'filtertype'    => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                    'order'         => 0,
                ),
                'dealer_bs_bn'    => array(
                    'type'          => $formattedDealerList,
                    'label'         => '经销商',
                    'filtertype'    => 'fuzzy_search',
                    'filterdefault' => true,
                    'panel_id'      => 'store_finder_top',
                    'order'         => 1,
                ),
            ),
        );

        return $db;
    }

}
