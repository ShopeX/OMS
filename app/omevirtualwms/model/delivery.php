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

class omevirtualwms_mdl_delivery extends dbeav_model{
    public $queue = [];
    /**
     * table_name
     * @param mixed $real real
     * @return mixed 返回值
     */
    public function table_name($real = false){
        if($real){
           $table_name = 'sdb_ome_delivery';
        }else{
           $table_name = 'delivery';
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

        $sql = "SELECT count(*) as _count from sdb_ome_delivery where `status` IN('ready','progress') and  parent_id=0  and process = 'false' and disabled='false' and ".$this->_filter($filter).$sqlstr.' ';
        $row = $this->db->select($sql);
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
    	$sql= "select delivery_id,delivery_bn,op_name,create_time from sdb_ome_delivery where `status` IN('ready','progress') and parent_id=0   and process = 'false' and disabled='false' and ".$this->_filter($filter).$sqlstr.' '; 
          
        //$rows = $this->db->select($sql);
        $rows = $this->db->selectLimit($sql,$limit,$offset);

            //取订单号  add by lymz at 2011-11-11 11:08:34
        foreach ($rows as $row) {
            if ( substr($row['delivery_bn'], 0, 1) == 'M' ) continue;
            $deliveryIds .= ','.$row['delivery_id'];
        }
        $deliveryIds = isset($deliveryIds) ? substr($deliveryIds,1) : 0;
        $sql = 'select o.order_bn,d.delivery_id from sdb_ome_orders o join sdb_ome_delivery_order d on o.order_id=d.order_id where d.delivery_id in ('.$deliveryIds.')';
        $record = $this->db->select($sql);
        foreach ($record as $value)
            $result[$value['delivery_id']] = $value['order_bn'];
        foreach ($rows as $key => &$value) {
            if ( substr($value['delivery_bn'], 0, 1) == 'M' ) continue;
            $value['order_bn'] = $result[$value['delivery_id']];
        }
       

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
        $where = array(1);
        if(isset($filter['time_from']) && $filter['time_from']){
            $where[] = ' delivery_time >='.strtotime($filter['time_from']);
        }
        if(isset($filter['time_to']) && $filter['time_to']){
            $where[] = ' delivery_time <'.(strtotime($filter['time_to'])+86400);
        }
        if(isset($filter['type_id']) && $filter['type_id']){
            $where[] = ' logi_id =\''.addslashes($filter['type_id']).'\'';
        }
        if(isset($filter['order_bn']) && $filter['order_bn']){
            $orderObj = app::get('ome')->model("orders");
            $rows = $orderObj->getList('order_id',array('order_bn|has'=>$filter['order_bn']));
            $orderId[] = 0;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }

            $deliOrderObj = app::get('ome')->model("delivery_order");
            $rows = $deliOrderObj->getList('delivery_id',array('order_id'=>$orderId));
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }

            $where[] = ' delivery_id IN ('.implode(',', $deliveryId).')';
            unset($filter['order_bn']);
        }
        return parent::_filter($filter,$tableAlias,$baseWhere) . ' and ' . implode(' and ',$where);
    }
    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema(){
        $schema = array (
            'columns' => array (
                'delivery_bn' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '发货单号',
                    'comment' => '发货单号',
                    'editable' => false,
                    'width' =>180,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'is_title' => true,
                ),
                'op_name' => array (
                    'type' => 'varchar(100)',
                    'label' => '操作人',
                    'comment' => '操作人',
                    'editable' => false,
                    'width' =>100,
                ),
               'create_time' => array (
                    'type' => 'time',
                    'label' => '时间',
                    'comment' => '单据生成时间',
                    'width' =>160,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
               'order_bn' => array (
                    'type' => 'varchar(32)',
                    'label' => '订单号',
                    'comment' => '订单号',
                    'width' =>180,
                    'editable' => false,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
         
            ),
            'idColumn' => 'delivery_bn',
            'in_list' => array (
                0 => 'delivery_bn',
                1 => 'op_name',
                2 => 'create_time',
                3 => 'order_bn'
               ),
            'default_in_list' => array (
                0 => 'delivery_bn',
                1 => 'op_name',
                2 => 'create_time',
                3 => 'order_bn'
            ),
        );
        return $schema;
    }
}
