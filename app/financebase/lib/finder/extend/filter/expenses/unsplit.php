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

class financebase_finder_extend_filter_expenses_unsplit{
    function get_extend_colums(){
        $shopName = array_column(app::get('ome')->model('shop')->getList('name,shop_id'),'name','shop_id');

        $billCategory = app::get('financebase')->model('expenses_rule')->getBillCategory();
        $billCategoryData = array();
        foreach($billCategory as $category) {
            $billCategoryData[$category['bill_category']] = $category['bill_category'];
        }

        $db['expenses_unsplit']=array (
            'columns' => array (
                'split_bn' => array(
                    'type'          => 'varchar(50)',
                    'label'         => '拆分单号',
                    'width'         => 100,
                    'editable'      => false,
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                    'order'         => 2,
                ),
                'shop_id' => array(
                    'type'          => $shopName,
                    'label'         => '来源店铺',
                    'width'         => 100,
                    'editable'      => false,
                    'in_list'       => true,
                    'filtertype'    => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                    'order'=>55,
                ),
                'order_bn' => array(
                    'type'          => 'varchar(32)',
                    'label'         => '订单号',
                    'width'         => 240,
                    'editable'      => false,
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                    'order'         => 16,
                ),
                'trade_type' => array(
                    'type'          => 'varchar(30)',
                    'label'         => '业务类型',
                    'width'         => 130,
                    'editable'      => false,
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                    'order'         => 50,
                ),
                'account_id_minus' => array(
                    'type'          => 'table:account_chart@financebase',
                    'label'         => '借方会计科目',
                    'editable'      => false,
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                    'order'         => 70,
                ),
                'bill_category' => array(
                    'type'          => $billCategoryData,
                    'label'         => '具体类别',
                    'width'         => 100,
                    'editable'      => false,
                    'in_list'       => true,
                    'default_in_list' => true,
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                    'order'=>30,
                ),
                'split_time' => array(
                    'type'          => 'time',
                    'label'         => '拆分时间',
                    'width'         => 150,
                    'editable'      => false,
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                    'order'=>25,
                ),
                'split_status' => array(
                    'type'          => array(
                        '0' => '未处理',
                        '1' => '处理中',
                        '2' => '不拆仅呈现',
                        '3' => '拆分成功',
                        '4' => '拆分失败',
                        '5' => '无需拆分',
                    ),
                    'label'         => '拆分状态',
                    'width'         => 100,
                    'editable'      => false,
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                    'order'=>45,
                ),
            )
        );
        return $db;
    }
}
