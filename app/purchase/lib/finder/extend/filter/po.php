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

class purchase_finder_extend_filter_po{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        
        $brObj = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        //过滤o2o门店虚拟仓库
        if ($is_super){
            $branch_rows = $brObj->getList('branch_id,name',array('b_type'=>1),0,-1);
        }else{
            $branch_rows = $brObj->getBranchByUser();
        }
        $branch_list = array();
        foreach($branch_rows as $branch){
            $branch_list [$branch['branch_id']] = $branch['name'];
        }
        
        $db['po']=array (
            'columns' => array (
                'branch_id' => array (
                    'type' => $branch_list,
                    'label' => '仓库',
                    'width' => 75,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => false,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => false,
                ),
                'bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '基础物料编码',
                    'width' => 85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'barcode' => array (
                    'type' => 'varchar(32)',
                    'label' => '条形码',
                    'width' => 110,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
            )
        );
        return $db;
    }
}

