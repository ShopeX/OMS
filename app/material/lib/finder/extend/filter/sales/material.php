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

class material_finder_extend_filter_sales_material{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        //获取销售物料类型
        $salesMLib = kernel::single('material_sales_material');
        $typeList = $salesMLib->getSalesMaterialTypes();
        
        //扩展字段
        $db['sales_material']=array (
            'columns' => array (
                'sales_material_type' => array(
                    'type' => $typeList,
                    'label' => '销售物料类型',
                    'width' => 120,
                    'editable' => false,
                    'default' => 1,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'brand_id' => array (
                    'type' => 'table:brand@ome',
                    'editable' => false,
                    'label' => '品牌',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'fuzzy_sales_material_bn' => array (
                    'type' => 'varchar(200)',
                    'label' => '销售物料编码(模糊)',
                    'width' => 100,
                    'editable' => false,
                    'default' => 1,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                ),
                'pz_basic_material_bn' => array (
                    'type' => 'varchar(50)',
                    'label' => '基础物料编码(普通/组合)',
                    'width' => 120,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
            )
        );
        
        return $db;
    }
}
