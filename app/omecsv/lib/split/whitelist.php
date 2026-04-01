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

class omecsv_split_whitelist
{
    private $bill_type = [
        'normal' => [
            'name'  => '导入任务',//任务名称
            'class' => 'omecsv_split_import',//处理类
        ],
        'platform' => [
            'name'  => '导入任务',//任务名称
            'class' => 'ome_order_import',//平台自发订单导入
        ],
        'iostock' => [
            'name'  => '出入库单导入任务',//任务名称
            'class' => 'taoguaniostockorder_iso_to_import',
        ],
        'order' => [
            'name'  => '导入任务',//任务名称
            'class' => 'ome_order_importV2',//订单导入
        ],
        'material_add' => [
            'name'  => '导入任务',//任务名称
            'class' => 'material_basic_material_importAddV2',//基础物料导入
        ],
        'material_update' => [
            'name'  => '导入任务',//任务名称
            'class' => 'material_basic_material_importUpV2',//基础物料导入
        ],
        'material_props_update' => [
            'name'  => '导入任务',//任务名称
            'class' => 'material_basic_material_importPropsUpV2',//基础物料导入
        ],
        'material_sales_add' => [
            'name'  =>  '导入任务',
            'class' => 'material_sales_material_importAddV2',//销售物料导入
        ],
        'goods_price' => [
            'name'  => '经销商品价格导入任务',
            'class' => 'dealer_goods_price_importV2',
        ],
        'finance_gap_type_import' => [
            'name'  => '差异类型导入任务',
            'class' => 'finance_monthly_report_items_import',
        ],
    ];
    
    public function getBillType($type)
    {
        return $this->bill_type[$type];
    }
}
