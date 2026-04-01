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
class wms_finder_extend_filter_branch_product{
    function get_extend_colums(){
        $db['branch_product']=array (
            'columns' => array (
              'bn'=>  array (
                    'type' => 'varchar(40)',
                    'editable' => false,
                    'label' => '基础物料编码',
                    'width' => 110,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                     'in_list' => true,
                    'panel_id' => 'wms_branch_finder_top',
                ),    
            'actual_store'=>array(
                    'type' => 'skunum',
                    'filtertype' => 'normal',
                    'required' => true,
                    'label' => '真实库存',
                  
                    'editable' => false,
                    'in_list' => true,
         
                    'default' => 0,
                    'filterdefault' => true,
                    'panel_id' => 'wms_branch_finder_top',
                ),
            'enum_store'=>array(
                    'type' => 'skunum',
                    'filtertype' => 'normal',
                    'required' => true,
                    'label' => '可用库存',
                    'editable' => false,
                    'in_list' => true,
                    'default' => 0,
                    'filterdefault' => true,
                    'panel_id' => 'wms_branch_finder_top',
                ),
            ),
       );
        return $db;
    }
}

?>
