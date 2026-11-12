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

class o2o_finder_extend_filter_performance_orders{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


     function get_extend_colums(){
        $storeObj = app::get('o2o')->model('store');

        $branch_arr   = $storeObj->getList('branch_id, name',array('b_type'=>'2'), 0, -1);
        $branch_list = array();
        foreach($branch_arr as $branch){
            $branch_list[$branch['branch_id']] = $branch['name'];
        }

        $dlyCorpObj    = app::get('ome')->model('dly_corp');
        $corp_arr      = $dlyCorpObj->getList('corp_id, name', array('d_type'=>'2'), 0, -1);
        foreach($corp_arr as $corp){
            $corp_list[$corp['corp_id']] = $corp['name'];
        }

        $db['performance_orders']=array (
            'columns' => array (
               'branch_id' => array (
                    'type' => $branch_list,
                    'editable' => false,
                    'label' => '分派门店',
                    'width' => 110,
                   'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'performance_orders_finder_top',
                ),
                'logi_id' => array(
                  'type' => $corp_list,
                  'comment' => '履约方式',
                  'editable' => false,
                  'label' => '履约方式',
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'panel_id' => 'performance_orders_finder_top',
                ),
            )
        );
        return $db;
     }

}
