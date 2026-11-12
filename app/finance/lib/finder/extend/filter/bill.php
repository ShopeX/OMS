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

class finance_finder_extend_filter_bill{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $db['bill']=array (
            'columns' => array (

                'channel_id' => array (
                    'type' => kernel::single('finance_bill')->get_name_by_shop(),
                    'default' => '0',
                    'label' => '渠道名称',
                    'width' => 65,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'status' => array (
                    'type' => kernel::single('finance_bill')->get_name_by_status('',1),
                    'default' => '0',
                    'required' => true,
                    'label' => '核销状态',
                    'width' => 75,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                // 'charge_status' => array (
                //     'type' => kernel::single('finance_bill')->get_name_by_charge_status('',1),
                //     'default' => '0',
                //     'label' => '记账状态',
                //     'width' => 65,
                //     'editable' => false,
                //     'filtertype' => 'normal',
                //     'filterdefault' => true,
                // ),
                // 'monthly_status' => array (
                //     'type' => kernel::single('finance_bill')->get_name_by_monthly_status('',1),
                //     'default' => '0',
                //     'label' => '月结状态',
                //     'width' => 65,
                //     'editable' => false,
                //     'filtertype' => 'normal',
                //     'filterdefault' => true,
                // ),
                'bill_type' => array (
                    'type' => array('0'=>'实收单','1'=>'实退单'),
                    'default' => '0',
                    'label' => '单据类型',
                    'comment' => '单据类型',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => false,
                ),
            )
        );
        if($_GET['app'] == 'finance' && $_GET['ctl']=='bill' && $_GET['act'] == 'index'){
            unset($db['bill']['columns']['status']);
        }
        return $db;
    }
}

