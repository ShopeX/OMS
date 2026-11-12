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

class dealer_finder_extend_filter_goods_price
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public function get_extend_colums()
    {
        // 获取经销商列表
        $dealerMdl = app::get('dealer')->model('business');
        $dealerList = $dealerMdl->getList('bs_id,bs_bn,name', array('status' => 'active'));
        
        $dealerOptions = array();
        foreach ($dealerList as $dealer) {
            $dealerOptions[$dealer['bs_id']] = $dealer['bs_bn'] . ' - ' . $dealer['name'];
        }

        $db['goods_price'] = array(
            'columns' => array(
                'bs_id' => array(
                    'type'          => $dealerOptions,
                    'label'         => '经销商',
                    'filtertype'    => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                ),
                'material_bn' => array(
                    'type'          => 'text',
                    'label'         => '基础物料编码',
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                ),
            ),
        );

        return $db;
    }
}
