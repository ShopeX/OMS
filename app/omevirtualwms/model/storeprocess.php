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

class omevirtualwms_mdl_storeprocess extends dbeav_model{
    /**
     * table_name
     * @param mixed $real real
     * @return mixed 返回值
     */
    public function table_name($real = false){
        if($real){
           $table_name = 'sdb_console_material_package';
        }else{
           $table_name = 'material_package';
        }
        return $table_name;
    }
    /**
     * count
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function count($filter=null){
        $branch_ids = $this->app->model('allocate')->getBranchidByselfwms();
        $sqlstr = '';
        if ($branch_ids)
         {
            $sqlstr.=" AND branch_id not in (".implode(',',$branch_ids).")";
         }

        $sql = "SELECT count(*) as _count from sdb_console_material_package where `status` in ('2','5') and ".$this->_filter($filter).$sqlstr.' ';
        $row = $this->db->select($sql);
        $c = 0;
        foreach ( $row as $val) $c += $val['_count'];
        return intval($c);
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        $branch_ids = $this->app->model('allocate')->getBranchidByselfwms();
        $sqlstr = '';
        if ($branch_ids)
         {
            $sqlstr.=" AND branch_id not in (".implode(',',$branch_ids).")";
         }
    	$sql= "select id,mp_bn,mp_name,at_time from sdb_console_material_package where `status` in ('2','5') and ".$this->_filter($filter).$sqlstr.' '; 
          
        $rows = $this->db->selectLimit($sql,$limit,$offset);

         return $rows;
    }

    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */
    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        return parent::_filter($filter,$tableAlias,$baseWhere);
    }

    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema(){
        $schema = array (
            'columns' => array (
                'mp_bn' => array (
                    'type' => 'varchar(32)',
                    'label' => '加工单号',
                    'editable' => false,
                    'width' =>180,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'is_title' => true,
                ),
                'mp_name' => array (
                    'type' => 'varchar(100)',
                    'label' => '加工单名称',
                    'editable' => false,
                    'width' =>100,
                ),
               'at_time' => array (
                    'type' => 'varchar(255)',
                    'label' => '时间',
                    'width' =>160,
                    'editable' => false,
                ),
            ),
            'idColumn' => 'mp_bn',
            'in_list' => array (
                0 => 'mp_bn',
                1 => 'mp_name',
                2 => 'at_time',
               ),
            'default_in_list' => array (
                0 => 'mp_bn',
                1 => 'mp_name',
                2 => 'at_time',
            ),
        );
        return $schema;
    }
}
