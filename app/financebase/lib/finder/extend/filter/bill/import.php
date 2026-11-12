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

class financebase_finder_extend_filter_bill_import{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){

        $db['bill_import']=array (
            'columns' => array (
//                'create_time' => array (
//                    'type' => 'time',
//                    'label' => '导入时间',
//                    'comment' => '导入时间',
//                    'editable' => false,
//                    'filtertype' => 'time',
//                    'filterdefault' => true,
//                    'in_list' => true,
//                    'panel_id' => 'billimport_finder_top',
//                ),

                'type' => array (
                     'type' =>array(
                            'order' => '单号',
                            'sku' => 'sku',
                            'sale' => '销售周期',
                     ),
                    'label' => '模板类型',
                    'comment' => '模板类型',
                    'editable' => false,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'billimport_finder_top',
                ),

                'file_name' => array (
                    'type' => 'varchar(255)',
                    'label' => '导入文件名',
                    'comment' => '导入文件名',
                    'editable' => false,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'billimport_finder_top',
                ),
            )
        );
        return $db;
    }
}
