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
class ome_finder_extend_filter_branch_product{
    function get_extend_colums(){
        $brObj = app::get('ome')->model('branch');

        $is_super = kernel::single('desktop_user')->is_super();
        
        #过滤o2o门店虚拟仓库
        if ($is_super){
            $branch_rows = $brObj->getList('branch_id,name', array(),0,-1);
        }else{
            $branch_rows = $brObj->getBranchByUser();
        }
        $branch_list = array();
        foreach($branch_rows as $branch){
            $branch_list [$branch['branch_id']] = $branch['name'];
        }
        $db['branch_product']=array (
            'columns' => array (
               'branch_id' =>
                    array (
                    'type' => $branch_list,
                    'editable' => false,
                    'label' => '仓库',
                    'width' => 110,
                    'default_value'=>$branch_rows[0]['branch_id'],
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'ome_branch_finder_top',
                ),
                'bn' =>
                    array (
                    'type' => 'varchar(40)',
                    'editable' => false,
                    'label' => '基础物料编码',
                    'width' => 110,
                  
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                 
                    'panel_id' => 'ome_branch_finder_top',
                ),
               'material_name' =>
                   array (
                   'type' => 'varchar(200)',
                   'required' => true,
                   'label' => '基础物料名称',
                   'width' => 260,
                   'searchtype' => 'has',
                   'editable' => false,
                   'filtertype' => 'normal',
                   'filterdefault' => true,
                   'panel_id' => 'ome_branch_finder_top',
               ),
                 'actual_store' =>
                array(
                    'type' => 'skunum',
                    'filtertype' => 'normal',
                    'required' => true,
                    'label' => '真实库存',
                  
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'default' => 0,
                    'filterdefault' => true,
                    'panel_id' => 'ome_branch_finder_top',
                ),
                 'enum_store' =>
                array(
                    'type' => 'skunum',
                    'filtertype' => 'normal',
                    'required' => true,
                    'label' => '可用库存',
                
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'default' => 0,
                    'filterdefault' => true,
                    'panel_id' => 'ome_branch_finder_top',
                ),
            )
        );
        return $db;
     }


}
