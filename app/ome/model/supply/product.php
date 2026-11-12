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

class ome_mdl_supply_product extends dbeav_model{
    /**
     * table_name
     * @param mixed $real real
     * @return mixed 返回值
     */
    public function table_name($real=false){
        $table_name = 'supply_product';
        if($real){
            return kernel::database()->prefix.$table_name;
        }else{
            return $table_name;
        }
    }

    /**
     * 获取_delivery
     * @param mixed $filter filter
     * @return mixed 返回结果
     */
    public function get_delivery($filter=null){
        $sql = 'SELECT count(*) as num, sum(delivery_cost_actual) as cost FROM '.
            'sdb_ome_delivery where is_cod=\'false\' and process=\'true\' and '.$this->_filter($filter);

        $row = $this->db->select($sql);
        return $row[0];
    }

    /**
     * count
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function count($filter=null){
        $sql = 'SELECT count(*) as _count FROM sdb_ome_branch_product as a 
            LEFT JOIN sdb_material_basic_material as b
                ON a.product_id = b.bm_id 
            LEFT JOIN sdb_purchase_supplier_goods as c
                ON b.bm_id=c.bm_id 
            LEFT JOIN sdb_purchase_supplier as d
                ON c.supplier_id=d.supplier_id
            WHERE '.
             ' '.$this->_filter($filter);

        $row = $this->db->select($sql);
        //var_dump($sql);
        return intval($row[0]['_count']);
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        $sql = 'SELECT 
            a.product_id,a.store as real_store,
            a.store+a.arrive_store-a.store_freeze as store,
            a.safe_store,a.store_freeze,
            a.last_modified,
            b.material_name AS name,b.material_bn AS bn, 
            d.name AS supplier_name,
            a.branch_id
            FROM sdb_ome_branch_product as a 
            LEFT JOIN sdb_material_basic_material as b
                ON a.product_id = b.bm_id
            LEFT JOIN sdb_purchase_supplier_goods as c
                ON b.bm_id=c.bm_id 
            LEFT JOIN sdb_purchase_supplier as d
                ON c.supplier_id=d.supplier_id
            WHERE 
             '.$this->_filter($filter);
            //var_dump($sql);
			
        if($orderType)$sql.=' ORDER BY '.(is_array($orderType)?implode(' ',$orderType):$orderType);

        $rows = $this->db->selectLimit($sql,$limit,$offset);
        $this->tidy_data($rows, $cols);
        
        return $rows;
    }

    /**
     * 搜索Options
     * @return mixed 返回值
     */
    public function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            //'order_bn'=>app::get('base')->_('订单号'),
        );
        return $Options = array_merge($parentOptions,$childOptions);
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
        if(isset($filter['branch_id']) && $filter['branch_id']){
            $where[] = ' a.branch_id =\''.addslashes($filter['branch_id']).'\'';
        }
        if(isset($filter['name']) && $filter['name']){
            $where[] = ' b.name like \'%'.addslashes($filter['name']).'%\'';
        }
        if(isset($filter['supplier_name']) && $filter['supplier_name']){
            $where[] = ' d.name like \'%'.addslashes($filter['supplier_name']).'%\'';
        }
        
        if(isset($filter['bn']) && $filter['bn']){
            $where[] = ' b.bn like \'%'.addslashes($filter['bn']).'%\'';
        }
        
        if(isset($filter['barcode']) && $filter['barcode']){
            $where[] = ' b.barcode like \'%'.addslashes($filter['barcode']).'%\'';
        }
        
        if(isset($filter['real_store'])){
            $where[] = ' a.store = '.intval($filter['real_store']).' ';
        }
        
        if(isset($filter['store'])){
            $where[] = ' a.store+a.arrive_store-a.store_freeze = '.intval($filter['store']).' ';
        }
        
        if(isset($filter['safe_store'])){
            $where[] = ' a.safe_store = '.intval($filter['safe_store']).' ';
        }
        
        if(isset($filter['supplier_id']) && $filter['supplier_id']){
            $where[] = ' d.supplier_id = '.intval($filter['supplier_id']).' ';
        }
        
        if(isset($filter['product_id']) && $filter['product_id']){
            $where[] = ' a.product_id IN ('.implode(',',$filter['product_id']).') ';
        }
        
        /**
         * 筛选条件处理：filter_type
         * 指定数量：appoint_store
         * 1=>'可用库存 小于 安全库存',
           2=>'真实库存 小于 安全库存',
           3=>'可用库存 小于 指定数量',
           4=>'真实库存 小于 指定数量',
         */
        if(isset($filter['appoint_store'])){
            $appoint_store = intval($filter['appoint_store']);
        }
        if(isset($filter['filter_type']) && $filter['filter_type']){
            switch($filter['filter_type']) {
                case 1:
                    $where[] = ' a.store+a.arrive_store-a.store_freeze < a.safe_store ';
                break;
                case 2:
                    $where[] = ' a.store < a.safe_store ';
                break;
                case 3:
                    if (isset($appoint_store)) {
                        $where[] = ' a.store+a.arrive_store-a.store_freeze < '.$appoint_store;
                    }
                break;
                case 4:
                    if (isset($appoint_store)) {
                        $where[] = ' a.store < '.$appoint_store;
                    }
                break;
            }
        }
        
        return implode(' AND ', $where);
        //return parent::_filter($filter,$tableAlias,$baseWhere)." AND ".implode($where,' AND ');
    }

    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema(){
        $schema = array (
            'columns' => array (
                'name' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '商品名称',
                    'width' =>280,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'is_title' => true,
                    'order'=>10
                ),
                'bn' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '货号',
                    'width' =>100,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'is_title' => true,
                    'order'=>20
                ),
                'barcode' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '条码',
                    'width' =>100,
                    'searchtype' => 'has',
                    //'filtertype' => 'yes',
                    //'filterdefault' => true,
                    'is_title' => true,
                    'order'=>30
                ),
                'unit' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '单位',
                    'width' =>50,
                    //'searchtype' => 'has',
                    //'filtertype' => 'yes',
                    //'filterdefault' => false,
                    //'is_title' => true,
                    'order'=>31
                ),
                'weight' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '重量',
                    'width' =>50,
                    //'searchtype' => 'has',
                    //'filtertype' => 'yes',
                    //'filterdefault' => false,
                    //'is_title' => true,
                    'order'=>32
                ),
                'real_store' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '实际库存',
                    'width' =>100,
                    'searchtype' => 'has',
                    //'filtertype' => 'yes',
                    //'filterdefault' => true,
                    'is_title' => true,
                    'order'=>40
                ),
                'store' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '可用库存',
                    'width' =>100,
                    'searchtype' => 'has',
                    //'filtertype' => 'yes',
                    //'filterdefault' => true,
                    'is_title' => true,
                    'order'=>50
                ),
                'safe_store' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '安全库存',
                    'width' =>100,
                    'searchtype' => 'has',
                    //'filtertype' => 'yes',
                    //'filterdefault' => true,
                    'is_title' => false,
                    'order'=>60
                ),
                'store_freeze' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '冻结库存',
                    'width' =>100,
                    //'searchtype' => 'has',
                    //'filtertype' => 'yes',
                    //'filterdefault' => true,
                    'is_title' => true,
                    'order'=>61
                ),
                'last_modified' => array (
                    'type' => 'time',
                    'required' => true,
                    'label' => '库存更新时间',
                    'width' =>160,
                    //'searchtype' => 'has',
                    //'filtertype' => 'yes',
                    //'filterdefault' => false,
                    'is_title' => true,
                    'order'=>70
                ),
                'supplier_name' => array (
                    'type' => 'varchar(50)',
                    'required' => true,
                    'label' => '供应商',
                    'width' =>100,
                    'searchtype' => 'has',
                    //'filtertype' => 'yes',
                    //'filterdefault' => true,
                    'is_title' => true,
                    'order'=>80
                ),
            ),
            'idColumn' => 'product_id',
            'in_list' => array (
                0 => 'name',
                1 => 'bn',
                2 => 'barcode',
                3 => 'real_store',
                4 => 'store',
                //5 => 'safe_store',
                6 => 'weight',
                7 => 'unit',
                9 => 'last_modified',
                10 => 'store_freeze',
                11 => 'supplier_name',
            ),
            'default_in_list' => array (
                0 => 'name',
                1 => 'bn',
                2 => 'barcode',
                3 => 'real_store',
                4 => 'store',
                //5 => 'safe_store',
                6 => 'weight',
                7 => 'unit',
                9 => 'last_modified',
                10 => 'store_freeze',
                11 => 'supplier_name',
            ),
        );
        return $schema;
    }
    
    function export_csv($data,$exportType = 1 ){
        $output = array();
        foreach( $data['title'] as $k => $val ){
            $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
        }
        echo implode("\n",$output);
    }
    
    //csv导出
    function fgetlist_csv( &$data,$filter,$offset,$exportType = 1,$limit = 100 ){
        @set_time_limit(60*30); // 30分钟
		@ini_set('memory_limit','128M');
        
        if( !$data['title']['obj'] ){
            $title = array();
            $title[] = $this->charset->utf2local('*:商品名称');
            $title[] = $this->charset->utf2local('*:商品货号');
            $title[] = $this->charset->utf2local('*:商品条码');
            $title[] = $this->charset->utf2local('*:真实库存');
            $title[] = $this->charset->utf2local('*:冻结库存');
            $title[] = $this->charset->utf2local('*:可用库存');
            $title[] = $this->charset->utf2local('*:安全库存');
            $title[] = $this->charset->utf2local('*:供应商');
            $title[] = $this->charset->utf2local('*:库存更新时间');
            $data['title']['obj'] = '"'.implode('","',$title).'"';
        }
        //var_dump($filter);

        if( !$list=$this->getList('*',$filter,$offset*$limit,$limit) )return false;
        foreach( $list as $aFilter ){
            $ObjRow = array();
            $ObjRow['*:商品名称']   = $aFilter['name'];
            $ObjRow['*:商品货号'] = $aFilter['bn']."\t";
            $ObjRow['*:商品条码'] = $aFilter['barcode']."\t";
            $ObjRow['*:真实库存'] = $aFilter['real_store'];
            $ObjRow['*:冻结库存'] = $aFilter['store_freeze'];
            $ObjRow['*:可用库存'] = $aFilter['store'];
            $ObjRow['*:安全库存'] = $aFilter['safe_store'];
            $ObjRow['*:供应商'] = $aFilter['supplier_name'];
            $ObjRow['*:库存更新时间'] = date('Y-m-d H:i:s',$aFilter['last_modified']);

            $data['content']['obj'][] = $this->charset->utf2local('"'.implode('","', $ObjRow ).'"');
        }
        return true;
    }
}
