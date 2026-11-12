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
class logisticsaccounts_finder_extend_filter_actual_task{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    function get_extend_colums(){
        
        #过滤o2o门店虚拟仓库
        $omeBranchObj    = app::get('ome')->model('branch');
        $branch_data     = $omeBranchObj->getlist('branch_id, name', array('b_type'=>1), 0,-1);
        $branch_list     = array();
        foreach ($branch_data as $key => $val)
        {
            $branch_list[$val['branch_id']]    = $val['name'];
        }
        
        $db['actual_task']=array (
            'columns' => array (
               
              'branch_id' =>
                    array (
                    'type' => $branch_list,
                    'editable' => false,
                    'label' => '仓库',
                    'width' => 110,
                    
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'actual_task_finder_top',
                ),
                
            )
        );
        
        if (kernel::single('desktop_user')->is_super()) {
           $db['actual_task']['columns'] = array(
                'op_id' =>
                array (
                  'type' => 'table:account@pam',
                  'label' => '创建人',
                  'editable' => false,
                  'width' => 60,
                  
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'in_list' => true,
                  'default_in_list' => true,
                  'panel_id' => 'actual_task_finder_top',
                ),
                'branch_id' =>
                array (
                           'type' => $branch_list,
                           'editable' => false,
                           'label' => '仓库',
                           'width' => 110,
                   
                           'filtertype' => 'normal',
                           'filterdefault' => true,
                           'in_list' => true,
                           'panel_id' => 'actual_task_finder_top',
                ),
            );
        }
       
        return $db;
    }
}
