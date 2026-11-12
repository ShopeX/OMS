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

class ome_finder_extend_filter_product_serial_history{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $db['product_serial_history']=array (
            'columns' => array (
                'act_type' =>
                array (
                    'type' => array(
                        '1' => '出库',
                        '2' => '退入',
                    ),
                    'label' => '操作类型',
                    'required' => true,
                    'default' => 0,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'bill_type' =>
                array (
                    'type' => array(
                        '1' => '发货单',
                        '2' => '退货单',
                    ),
                    'label' => '单据类型',
                    'required' => true,
                    'default' => 0,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
            )
        );
        return $db;
    }


}