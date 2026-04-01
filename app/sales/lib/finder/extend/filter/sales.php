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
class sales_finder_extend_filter_sales{
    function get_extend_colums(){
        $db['sales']=array (
            'columns' => array (
                'order_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '订单号',
                    'width' => 130,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),              
                'shop_id' =>array (//店铺名称
                        'type' => 'table:shop@ome',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'default_in_list'=>true,
                        'in_list'=>true,
                        'label' => '店铺名称',
                        'comment' => '店铺名称',
                ),
                'original_bn' =>array (//原始单据号
                        'type' => 'varchar(32)',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'default_in_list'=>true,
                        'in_list'=>true,
                        'label' => '发货单号',
                        'comment' => '发货单号',
                ),
                'ship_area' =>
                array (
                  'type' => 'region',
                  'label' => '收货地区',
                  'comment' => '收货人地区',
                  'editable' => false,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'width' =>130,
                  'in_list' => true,
                  'default_in_list' => true,
                  'sdfpath' => 'consignee/area',
                ),
                'bn' =>array (
                        'type' => 'varchar(32)',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'default_in_list'=>true,
                        'in_list'=>true,
                        'label' => '基础物料编码',
                        'comment' => '基础物料编码',
                ),
                'product_name' =>array (
                        'type' => 'varchar(32)',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'default_in_list'=>true,
                        'in_list'=>true,
                        'label' => '基础物料名称',
                        'comment' => '基础物料名称',
                ),

                
            )
        );
        return $db;
    }
}
