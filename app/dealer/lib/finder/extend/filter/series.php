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

class dealer_finder_extend_filter_series
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public function get_extend_colums()
    {
        $endorseMdl = app::get('dealer')->model('series_endorse');
        $shopMdl    = app::get('ome')->model('shop');

        $seriesShop = $endorseMdl->getList('*');
        $seriesShop = array_unique(array_column($seriesShop, 'shop_id'));
        $shopList   = $shopMdl->getList('shop_id,shop_bn,name', ['delivery_mode' => 'shopyjdf', 'shop_id|in' => $seriesShop]);
        $shopList   = array_column($shopList, 'name', 'shop_id');

        $db['series'] = array(
            'columns' => array(
                'series_shop' => array(
                    'type'          => $shopList,
                    'label'         => '授权经销店铺',
                    'filtertype'    => 'fuzzy_search',
                    // 'filtertype'    => 'fuzzy_search_multiple', // 多选
                    'filterdefault' => true,
                ),
            ),
        );

        return $db;
    }
}
