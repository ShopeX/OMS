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

class ome_finder_extend_filter_payment_cfg{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $pay_type = ome_payment_type::pay_type();
        $shopObj = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('*');
        $shop_id = array();
        foreach($shopList as $shop){
            $shop_id[$shop['shop_id']] = $shop['name'];
        }

        $db['payment_cfg']=array (
            'columns' => array (
                'pay_type' => array (
                    'type' => $pay_type,
                    'width' => 75,
                    'editable' => false,
                    'label' => '支付类型',
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'shop_id' => array (
                    'type' => $shop_id,
                    'label' => '关联店铺',
                    'width' => 75,
                    'editable' => false,
                    'in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
            )
        );
        return $db;
    }
}
