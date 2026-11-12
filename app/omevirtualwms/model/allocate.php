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

class omevirtualwms_mdl_allocate extends dbeav_model{
    public $queue = [];

     public function count($filter=null){
        $branch_ids = $this->getBranchidByselfwms();
        $sqlstr = '';
        if ($branch_ids)
         {
            $sqlstr.=" AND branch_id not in (".implode(',',$branch_ids).")";
         }
         
         $sql= "select count(*) as _count from sdb_taoguaniostockorder_iso where iso_status not in('3','4') and check_status='2' and ".$this->_filter($filter).'  and create_time >='.(time()-(WAIT_TIME*60)).')'.$sqlstr;
       
        
        
        $row = $this->db->select($sql);
        return intval($row[0]['_count']);
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        $branch_ids = $this->getBranchidByselfwms();
        $sqlstr = '';
        if ($branch_ids)
         {
            $sqlstr.=" AND branch_id not in (".implode(',',$branch_ids).")";
         }
        $sql= "select * from sdb_taoguaniostockorder_iso where iso_status not in('3','4') and check_status='2' and ".$this->_filter($filter).$sqlstr.' sand create_time >='.(time()-(WAIT_TIME*60)).') order by create_time desc';
        $rows = $this->db->selectLimit($sql,$limit,$offset);
        
     
        return $rows;
    }

    public function get_schema(){
        $schema = array (
            'columns' => array (
                'iso_bn' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '调拨出入库单号',
                    'editable' => false,
                    'width' =>180,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'is_title' => true,
                ),
                'operator' => array (
                    'type' => 'varchar(100)',
                    'label' => '操作人',
                    'editable' => false,
                    'width' =>100,
                ),
               'create_time' => array (
                    'type' => 'time',
                    'label' => '时间',
                    'width' =>160,
                    'editable' => false,
                ),
         
            ),
            'idColumn' => 'iso_bn',
            'in_list' => array (
                0 => 'iso_bn',
                1 => 'operator',
                2 => 'create_time',
               ),
            'default_in_list' => array (
                0 => 'iso_bn',
                1 => 'operator',
                2 => 'create_time',
            ),
        );
        return $schema;
    }

    function getBranchidByselfwms(){
        $oBranch = &app::get('ome')->model('branch');
        $filter['wms_id'] =kernel::single('wms_branch')->getBranchByselfwms();
        
        $branch_list = $oBranch->getList('branch_id', $filter, 0, -1);
        if ($branch_list)
        $branch_ids = array();
        foreach ($branch_list as $branch_list) {
            $branch_ids[] = $branch_list['branch_id'];

        }
        return $branch_ids;
    }
}
