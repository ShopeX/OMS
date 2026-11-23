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

class console_mdl_interface_iostocksearchs extends dbeav_model{

    public function count($filter = null)
    {
        $prefix = kernel::database()->prefix;
        $app_ome = app::get('ome');

        // 获取货品库存
        $mdl_branch_product = $app_ome->model('branch_product');
        $mdl_iostock        = $app_ome->model('iostock');

        //$sql = sprintf("SELECT COUNT(iostock_id) AS _count
                        //FROM (SELECT iostock_id
                              //FROM %s AS iostock
                              //WHERE 1 = 1
                                //AND %s
                              //GROUP BY iostock.bn, iostock.branch_id) AS _iostock", $mdl_iostock->table_name(true), $this->_filter($filter));

         $sql = sprintf("SELECT COUNT(iostock.iostock_id) as _count  FROM 
                        (
                        SELECT (iostock.iostock_id) FROM %s AS iostock,%s AS a,%s AS branch_product 
                        WHERE 1 = 1 AND iostock.bn = a.material_bn AND a.bm_id = branch_product.product_id AND branch_product.branch_id = iostock.branch_id
                            AND %s
                        GROUP BY iostock.bn, iostock.branch_id) as iostock", $mdl_iostock->table_name(true), 'sdb_material_basic_material', $mdl_branch_product->table_name(true), $this->_filter($filter)
                            );
        $row = $mdl_iostock->db->select($sql);

        return intval($row[0]['_count']);
    }

    public function details($bn, $branch_id, $filter = array())
    {
        $prefix         = kernel::database()->prefix;
        $app_ome        = app::get('ome');
        $mdl_iostock    = $app_ome->model('iostock');

        // 库存类型
        $types = $this->get_branch_types();

        $sql = sprintf("SELECT a.bm_id AS product_id, a.material_bn AS bn, iostock.iostock_bn, iostock.original_bn, iostock.iostock_price, iostock.nums,
                iostock.type_id, iostock.create_time, branch_product.branch_id 
                FROM %s AS iostock,%smaterial_basic_material AS a,%some_branch_product AS branch_product
                WHERE iostock.bn = '%s'
                    AND iostock.bn = a.material_bn
                    AND a.bm_id = branch_product.product_id AND branch_product.branch_id = iostock.branch_id
                    AND iostock.branch_id = %s
                    AND %s ", $mdl_iostock->table_name(true), $prefix, $prefix, $bn, $branch_id, $this->_filter($filter).' ORDER BY create_time DESC ');
        $row = kernel::database()->select($sql);
        foreach($row as $key => $r)
        {
            $row[$key]['create_time']   = date("Y-m-d H:i:s", $r['create_time']);
            $row[$key]['type_name']     = $types[$r['type_id']];
        }
        return $row;
    }
    public function getlistbase($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        return parent::getList($cols, $filter, $offset, $limit, $orderType);
    }
    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $prefix = kernel::database()->prefix;
        $app_ome = app::get('ome');
        
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');

        // 获取库存列表
        $branchs = $this->get_branch_list();

        // 获取货品库存
        $mdl_branch_product = $app_ome->model('branch_product');
        $mdl_iostock        = $app_ome->model('iostock');
        if(!$cols)
            $cols = $this->defaultCols;
        if(!empty($this->appendCols))
            $cols.=','.$this->appendCols;
        if($this->use_meta)
             $meta_info = $this->prepare_select($cols);
        $orderType = $orderType ? $orderType : $this->defaultOrder;
        $sql = sprintf("SELECT branch_product.branch_id, branch_product.product_id, 
                        a.material_name AS name, a.material_bn AS bn, branch_product.store, branch_product.store_freeze
                        FROM %s AS iostock, %s AS a, %s AS branch_product
                        WHERE 1 = 1
                            AND iostock.bn = a.material_bn 
                            AND a.bm_id = branch_product.product_id AND branch_product.branch_id = iostock.branch_id
                            AND %s
                        GROUP BY iostock.bn, iostock.branch_id", $mdl_iostock->table_name(true), 'sdb_material_basic_material', $mdl_branch_product->table_name(true), $this->_filter($filter));

        $branch_info = $this->db->selectLimit($sql, $limit, $offset);

        $arr = array();
        foreach($branch_info as $key => $branch)
        {
            //获取仓库级的预占
            $branch['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($branch['product_id'], $branch['branch_id']);
            
            $arr[$key]['bn']            = $branch['bn'];
            $arr[$key]['goods_name']    = $branch['name'];
            $arr[$key]['store']         = $branch['store'] - $branch['store_freeze'];
            $arr[$key]['store_name']    = $branchs[$branch['branch_id']];
            $arr[$key]['store_freeze']  = $branch['store_freeze'];
            $arr[$key]['stores']        = $branch['store'];
            $arr[$key]['time_to']       = $filter['time_to'];
            $arr[$key]['time_from']     = $filter['time_from'];
            $arr[$key]['bn_branch']     = sprintf("%s*$**%s", $branch['branch_id'], $branch['bn']);
        }
        return $arr;
    }

    /**
     * 获取库存列表
     *
     * @return          array
     */
    public function get_branch_list()
    {
        $_branchs = app::get('ome')->model('branch')->getlist('branch_id, name');
        $branchs = array();
        foreach ($_branchs as $value)
        {
            $branchs[$value['branch_id']] = $value['name'];
        }
        return $branchs;
    }

    /**
     * 获取库存列表
     *
     * @return          array
     */
    public function get_branch_types()
    {
        $_types = app::get('ome')->model('iostock_type')->getlist('type_id, type_name');
        $types = array();
        foreach ($_types as $value)
        {
            $types[$value['type_id']] = $value['type_name'];
        }

        return $types;
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        $_SESSION['fil'] = $filter;
        $where = array(1);
            if(isset($filter['time_from']) && $filter['time_from']){
                $where[] = 'iostock.create_time >='.strtotime($filter['time_from']);
            }
            if(isset($filter['time_to']) && $filter['time_to']){
                $where[] = 'iostock.create_time <'.(strtotime($filter['time_to'])+86400);
            }
            if(isset($filter['bn'])){
                $where[] = ' iostock.bn like \'%' . $filter['bn'] . '%\' ';
            }
            if(isset($filter['goods_name'])){
                $where[] = ' a.material_name like \'%' . $filter['goods_name'] . '%\' ';
            }
            if(isset($filter['store_name'])){
				if(is_array($filter['store_name']))
					$where[] = ' iostock.branch_id in (' . implode($filter['store_name'],' ,').')';
				else $where[] = ' iostock.branch_id =' . $filter['store_name'];
                
            }
            //可用库存的查询
            if(isset($filter['_store_search'])){
                $var = ($filter['_store_search'] == 'between' ? array($filter['store_from'],$filter['store_to']) : $filter['store']);
                $where[] = $this->getMultiFilter('(branch_product.store-CAST(branch_product.store_freeze AS SIGNED))',$filter['_store_search'],$var);
            }
            //实际库存的查询
            if(isset($filter['_stores_search'])){
                $var = ($filter['_stores_search'] == 'between' ? array($filter['stores_from'],$filter['stores_to']) : $filter['stores']);
                $where[] = $this->getMultiFilter('branch_product.store',$filter['_stores_search'],$var);
            }
        return implode(' AND ', $where);
     }

     /**
     *解析搜索类型
     *
     **/
    function getMultiFilter($col,$type,$var){
        $FilterArray= array('than'=>' > '.$var,
                            'lthan'=>' < '.$var,
                            'nequal'=>' = \''.$var.'\'',
                            'noequal'=>' <> \''.$var.'\'',
                            'tequal'=>' = \''.$var.'\'',
                            'sthan'=>' <= '.$var,
                            'bthan'=>' >= '.$var,
                            'has'=>' like \'%'.$var.'%\'',
                            'head'=>' like \''.$var.'%\'',
                            'foot'=>' like \'%'.$var.'\'',
                            'nohas'=>' not like \'%'.$var.'%\'',
                            'between'=>" {$col}>=".$var[0].' and '." {$col}<".$var[1],
                            'in' =>" in ('".implode("','",(array)$var)."') ",
                            'notin' =>" not in ('".implode("','",(array)$var)."') ",
                            );
        return $type == 'between' ? $FilterArray[$type] : $col . $FilterArray[$type];

    }

    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id){
        $cols = $this->_columns();
        $colTitle = $this->getTitle($cols);
        if($curr_sheet == 1){
            $title = array();
            foreach($colTitle as $titlek => $aTitle ){
                if($titlek=='bn_branch'){
                    continue;
                }
                $title[$titlek] = $aTitle;
            }
            $data['content']['main'][] = implode(',',$title);
        }
        if(!$list = $this->getList('*',$filter,$start,$end))return false;
        foreach( $list as $line => $row ){
            $rowRow = array();
            foreach( $colTitle as $k => $v ){
                if($k=='bn_branch'){
                    continue;
                }
                $rowRow[$k] = kernel::single('ome_func')->csv_filter($row[$k]);
            }
            $data['content']['main'][] = implode(',',$rowRow);
        }
        return $data;
    }
/**/
        function getTitle(&$cols){
            $title = array();
            foreach( $cols as $col => $val ){
                if( !$val['deny_export'] )
                    $title[$col] = mb_convert_encoding($val['label'], 'GBK', 'UTF-8').'('.$col.')';
            }
            return $title;
        }

        function export_csv($data,$exportType){
            $rs = '';
            if( is_array( $data ) ){
                $data = (array)$data;
                if( empty( $data['title'] ) && empty( $data['contents'] ) ){
                    $rs = implode( "\n", $data );
                }else{
					$rs = $data['title']."\n".implode("\n",(array)$data['contents']);
                }
            }else{
                $rs = (string)$data;
            }
            return $rs;
        }

        public function get_schema(){
            $schema = array (
            'columns' => array (
                'bn' => array (
                    'type' => 'varchar(50)',
                    'required' => true,
                    'default' => 0,
                    'label' => '货号',
                    'width' => 150,
                    'searchtype' => 'has',
                     'filtertype' => 'normal',
                     'filterdefault' => true,
                    'editable' => false,
                    'order' =>1,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                 'goods_name' => array (
                    'type' => 'varchar(100)',
                    'label' => '货品名称',
                    'width' => 260,
                    'editable' => false,
                    'order' =>2,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                ),

                'store_name' => array (
                    'type' => 'table:branch@ome',
                    'label' => '仓库名称',
                    'width' => 110,
                    'order' =>3,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'store_freeze' => array (
                    'type' => 'int(10)',
                    'label' => '冻结库存',
                    'width' => 75,
                    'order' =>4,
                    'in_list' =>true,
                    'default_in_list' => true,
                ),

                'store' => array (
                    'type' => 'number',
                    'required' => true,
                    'default' => 0,
                    'label' => '可用库存',
                    'filtertype' => 'number',
                    'filterdefault' => true,
                    'width' => 100,
                    'order' =>5,
                    'in_list' =>true,
                    'default_in_list' => true,
                ),
                'stores' => array (
                    'type' => 'number',
                    'default' => 0,
                    'required' => true,
                    'label' => '实际库存',
                    'filtertype' => 'number',
                    'filterdefault' => true,
                    'width' => 110,
                    'order' =>6,
                    'in_list' =>true,
                    'default_in_list' => true,
                ),
                 'bn_branch' => array (
                    'type' => 'varchar(100)',
                    'label' => '货品仓库',
                    'width' => 110,
                    'editable' => false,
                    'in_list' => false,
                    'default_in_list' => false,
                ),

            ),

        'idColumn' => 'bn_branch',
        'in_list' => array (
                0 => 'bn',
                1 => 'goods_name',
                2 => 'store_name',
                3 => 'store_freeze',
                4 => 'store',
                5 => 'stores',
                //6 => 'bn_branch',
            ),
        'default_in_list' => array (
                0 => 'bn',
                1 => 'goods_name',
                2 => 'store_name',
                3 => 'store_freeze',
                4 => 'store',
                5 => 'stores',
                //6 => 'bn_branch',
            ),
        );
        return $schema;
    }
}