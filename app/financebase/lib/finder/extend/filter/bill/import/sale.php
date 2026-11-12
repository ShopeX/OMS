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

class financebase_finder_extend_filter_bill_import_sale
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums()
    {

        $db['bill_import_sale'] = array(
            'columns' => array(

                'confirm_status' => array(
                    'type'       => array(
                        '0' => '未确认',
                        '1' => '已确认',
                    ),
                    'label'      => '确认状态',
                    'comment'    => '确认状态',
                    'editable'   => false,
                    'filtertype' => 'time',

                    'filterdefault' => true,
                    'in_list'       => true,
                    'panel_id'      => 'importsale_finder_top',
                ),

                'pay_serial_number' => array(
                    'type'          => 'varchar(500)',
                    'label'         => '支付流水号',
                    'comment'       => '支付流水号',
                    'filtertype'    => 'time',
                    'editable'      => false,
                    'filterdefault' => true,
                    'in_list'       => true,
                    'panel_id'      => 'importsale_finder_top',
                ),
            )
        );
        return $db;
    }
}
