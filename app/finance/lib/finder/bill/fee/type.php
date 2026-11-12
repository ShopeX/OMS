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

class finance_finder_bill_fee_type{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    /**
     * __construct
     * @return mixed 返回值
     */
    public function __construct()
    {
        $this->_render = app::get('finance')->render();
    }

    var $detail_items = '科目明细';
    /**
     * detail_items
     * @param mixed $fee_type_id ID
     * @return mixed 返回值
     */
    public function detail_items($fee_type_id)
    {
        $fee_items = app::get('finance')->model('bill_fee_item')->getList('*',array('fee_type_id' => $fee_type_id));
        foreach ((array) $fee_items as $key => $value) {
            $fee_items[$key]['channel'] = finance_channel::$channel_name[$value['channel']];
        }
        $this->_render->pagedata['fee_items'] = $fee_items;

        $fee_type = app::get('finance')->model('bill_fee_type')->dump($fee_type_id);
        $this->_render->pagedata['fee_type'] = $fee_type;

        return $this->_render->fetch('bill/fee/items.html');
    }

}
