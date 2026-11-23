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

class console_mdl_interface_account extends dbeav_model{

    public function count($filter = null){
        $prefix = kernel::database()->prefix;
        $app_ome = app::get('console');
        $where = implode(' AND ',$this->_filter($filter));
        $sql = sprintf("SELECT COUNT(items_id) AS _count FROM sdb_console_stock_account_items WHERE %s",$where);
        $row = kernel::database()->select($sql);
        
        return intval($row[0]['_count']);
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        $_SESSION['fil'] = $filter;
        $where = array('1=1');
        if(isset($filter['time_from']) && $filter['time_from']){
            $where[] = ' account_time >='.strtotime($filter['time_from']);
        }
        if(isset($filter['time_to']) && $filter['time_to']){
            $where[] = ' account_time <'.(strtotime($filter['time_to'])+86400);
        }
        if(isset($filter['account_bn'])){
            $where[] = ' account_bn =\'' . $filter['account_bn'] . '\'';
        }
        if(isset($filter['batch'])){
            $where[] = ' batch =\'' . $filter['batch'] . '\'';
        }
        if(isset($filter['wms_id'])){
                    if(is_array($filter['wms_id']))
        $where[] = ' wms_id in (\'' . implode($filter['wms_id'],'\',\'').'\')';
        else $where[] = ' wms_id =' . $filter['wms_id'];
        }
        return $where;
     }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        $prefix = kernel::database()->prefix;
        $app_ome = app::get('ome');
        $stok_account_items = $app_ome->model('stock_account_items');
        $where = implode(' AND ',$this->_filter($filter));
        $order = empty($orderType)?'':'ORDER BY '.$orderType;
        $list = kernel::database()->select("SELECT * FROM sdb_console_stock_account_items WHERE $where $order LIMIT $offset,$limit");
        
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        if($list)
        {
            foreach ($list as &$val)
            {
                $p     = $basicMaterialObj->dump(array('material_bn'=>$val['account_bn']), 'bm_id, material_bn, material_name');
                
                $val['product_name'] = $p['material_name'];
            }
        }        
   
        return $list;

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

    function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        $filter = $_SESSION['fil'];
        unset($_SESSION['fil']);
           $limit = 100;
            $cols = $this->_columns();
            $oSchema = array_flip($this->getTitle($cols));

            if(!$data['title']){
                $title = array();
                foreach( $this->getTitle($cols) as $titlek => $aTitle ){
                    $title[$titlek] = $aTitle;
                }
                $data['title'] = '"'.implode('","',$title).'"';
            }
            if(!$list = $this->getList('account_bn,account_time,original_goods_stock,account_goods_stock,goods_diff_nums,original_rejects_stock,account_rejects_stock,rejects_diff_nums,wms_id',$filter,$offset*$limit,$limit))return false;
            foreach( $list as $line => $row ){
                $row['wms_id'] = $this->modifier_wms_id($row['wms_id']);
                $row['account_time'] = $this->modifier_account_time($row['account_time']);
                $rowRow = array();
                foreach( $oSchema as $k => $v ){
                    $v = utils::apath( $row,explode('/',$v) ) . ''; 
                    $rowRow[$k] = $v;
                }
                $data['contents'][] = '"'.implode('","',$rowRow).'"';
            }

            $data['name'] = '库存对账'.date("m月d日",time());

            return true;
        }
/**/
        function getTitle(&$cols){
            $title = array();
            foreach( $cols as $col => $val ){
                if( !$val['deny_export'] )
                    $title[$col] = $val['label'].'('.$col.')';
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
            'columns' => 
                  array (
                    'items_id' => 
                    array (
                      'type' => 'number',
                      'required' => true,
                      'pkey' => true,
                      'extra' => 'auto_increment',
                      'editable' => false,
                      'deny_export' => true,
                    ),
                    'batch' => 
                     array (
                      'type' => 'varchar(64)',
                      'required' => false,
                      'editable' => false,
                      'searchtype' => 'has',
                      'filtertype' => 'normal',
                      'filterdefault' => true,
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 130,
                      'label' => '批次号',
                    ),
                    'account_bn' => 
                    array (
                      'type' => 'varchar(32)',
                      'required' => true,
                      'editable' => false,
                      'searchtype' => 'has',
                      'filtertype' => 'normal',
                      'filterdefault' => true,
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 150,
                      'label' => '基础物料编码',
                      'order' => '1',
                    ),
                    'product_name' => 
                    array (
                      'type' => 'varchar(32)',
                      'editable' => false,
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 270,
                      'label' => '基础物料名称',
                      'order' => '2',
                    ),
                    'account_time' => 
                    array (
                      'type' => 'time',
                      'label' => '日期',
                      'width' => 80,
                      'editable' => false,
                      'in_list' => true,
                      'default_in_list' => true,
                      'order' => '1',
                    ),
                    'original_goods_stock' =>
                    array (
                      'label' => '仓库良品数量',
                      'type' => 'mediumint',
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 85,
                      'default' => 0,
                      'order' => '4',
                    ),
                    'account_goods_stock' =>
                    array (
                      'label' => '良品数量',
                      'type' => 'mediumint',
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 70,
                      'default' => 0,
                      'order' => '3',
                    ),
                    'goods_diff_nums' =>
                    array (
                      'label' => '良品差异',
                      'type' => 'mediumint',
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 70,
                      'default' => 0,
                      'order' => '5',
                    ),
                    'original_rejects_stock' =>
                    array (
                      'label' => '仓库不良品数量',
                      'type' => 'mediumint',
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 100,
                      'default' => 0,
                      'order' => '7',
                    ),
                    'account_rejects_stock' =>
                    array (
                      'label' => '不良品数量',
                      'type' => 'mediumint',
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 75,
                      'default' => 0,
                      'order' => '6',
                    ),
                    'rejects_diff_nums' =>
                    array (
                      'label' => '不良品差异',
                      'type' => 'mediumint',
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 75,
                      'default' => 0,
                      'order' => '8',
                    ),
                    'wms_id' =>
                    array (
                      'label' => '第三方仓储',
                      'type' => 'number',
                      'in_list' => true,
                      'default_in_list' => true,
                      'width' => 100,
                      'order' => '1',
                    ),
                ),

        'idColumn' => 'items_id',
        'in_list' => array (
                'batch',
                'account_bn',
                'account_time',
                'original_goods_stock',
                'account_goods_stock',
                'goods_diff_nums',
                'original_rejects_stock',
                'account_rejects_stock',
                'rejects_diff_nums',
                'wms_id',
                'product_name',
            ),
        'default_in_list' => array (
                'batch',
                'account_bn',
                'account_time',
                'original_goods_stock',
                'account_goods_stock',
                'goods_diff_nums',
                'original_rejects_stock',
                'account_rejects_stock',
                'rejects_diff_nums',
                'wms_id',
                'product_name',
            ),
        );
        return $schema;
    }

    public function modifier_account_time($row){
		return date('Y-m-d',$row);
	}

    public function modifier_wms_id($row){
		      
        return kernel::single('channel_func')->getChannelNameById($row);

	}
}