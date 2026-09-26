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

class finance_finder_extend_filter_ar{
    function get_extend_colums($finder_aliasname = null){
        $arLib = kernel::single('finance_ar');
        $shopOptions = $arLib->get_name_by_shop();

        $db['ar']=array (
            'columns' => array (
                'channel_id' => array (
                    'type' => $shopOptions,
                    'default' => '0',
                    'label' => '渠道名称',
                    'width' => 65,
                    'editable' => false,
                    'filtertype' => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                ),
                'shop_type' => array (
                    'type' => $arLib->get_name_by_shop_type(),
                    'label' => '店铺类型',
                    'width' => 65,
                    'editable' => false,
                    'filtertype' => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                ),
                'status' => array (
                    'type' => kernel::single('finance_ar')->get_name_by_status('',1),
                    'default' => '0',
                    'required' => true,
                    'label' => '核销状态',
                    'width' => 75,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => false,
                ),
                'type' => array (
                    'type' => kernel::single('finance_ar')->get_name_by_type('',1),
                    'default' => '0',
                    'label' => '业务类型',
                    'comment' => '业务类型',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => false,
                ),
                'ar_type' => array (
                    'type' => array('0'=>'应收单','1'=>'应退单'),
                    'default' => '0',
                    'label' => '单据类型',
                    'comment' => '单据类型',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => false,
                ),
                'monthly_date' => array (
                    'type' => 'varchar(32)',
                    'label'=>'账期名称',
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
