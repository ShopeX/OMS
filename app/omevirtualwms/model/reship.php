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

class omevirtualwms_mdl_reship extends dbeav_model{
    public $queue = [];
    /**
     * table_name
     * @param mixed $real real
     * @return mixed 返回值
     */
    public function table_name($real=false){
        $table_name = 'ome_reship';
        if($real){
            return kernel::database()->prefix.$table_name;
        }else{
            return $table_name;
        }
    }
    
        //model和数据库实体不对应时调用的函数
    /**
     * object_name
     * @return mixed 返回值
     */
    public function object_name()
    {
        return 'reship';
    }
    /**
     * 获取ReQueue
     * @return mixed 返回结果
     */
    public function getReQueue(){
     				$Obj = app::get('omequeue')->model("queue");
            $rows = $Obj->getList('*',array('type'=>'store.trade.reship'));
            $str="";
            foreach($rows as $row){
            	$params = $row["params"];
            	if(empty($str)){
             		$str.="'".$params["reship_bn"]."'";
              }else{
              	$str.=","."'".$params["reship_bn"]."'";
              }
            }
            return $str;
     	
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
       $sql = "SELECT count(*) as _count from sdb_ome_reship where is_check='1'" .$where. "and disabled='false' and ".$this->_filter($filter).$sqlstr.'  '; 
        
        $row = $this->db->select($sql);
        return intval($row[0]['_count']);
    }
    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        //获取自有仓仓库branch_id
        $branch_ids = $this->app->model('allocate')->getBranchidByselfwms();
        
        //过滤掉线下仓库(没有回传退换单业务 直接门店操作)
        $mdl_ome_branch = app::get('ome')->model('branch');
        $rs_o2o_branch = $mdl_ome_branch->getList("branch_id",array("b_type"=>2));
        if (!empty($rs_o2o_branch)){
            if (!empty($branch_ids)){
                foreach ($rs_o2o_branch as $var_o_b){
                    if (!in_array($var_o_b["branch_id"],$branch_ids)){
                        $branch_ids[] = $var_o_b["branch_id"];
                    }
                }
            }else{
                $branch_ids = array();
                foreach ($rs_o2o_branch as $var_o_b){
                    $branch_ids[] = $var_o_b["branch_id"];
                }
            }
        }
        
        $sqlstr = '';
        if ($branch_ids){
            $sqlstr.=" AND branch_id not in (".implode(',',$branch_ids).")";
         }
       $sql= "select * from sdb_ome_reship where is_check='1' ".$where." and disabled='false' and ".$this->_filter($filter).$sqlstr.' '; 
        //$rows = $this->db->select($sql);
        $rows = $this->db->selectLimit($sql,$limit,$offset);

            //取订单号 and 售后单号    add by lymz at 2011-11-11 11:13:31
        if (count($rows) < 1)
            return $rows;

        foreach ($rows as $value) {
            $orderIds[] = $value['order_id'];
            $returnIds[] = $value['return_id'];
        }
        $tmp = app::get('ome')->model('orders')->getList('order_id,order_bn',array('order_id'=>$orderIds));
        foreach ($tmp as $value)
            $orders[$value['order_id']] = $value['order_bn'];
        $tmp = app::get('ome')->model('return_product')->getList('return_id,return_bn',array('return_id'=>$returnIds));
        foreach ($tmp as $value)
            $returns[$value['return_id']] = $value['return_bn'];
        foreach ($rows as &$value) {
            $value['order_bn'] = $orders[$value['order_id']];
            $value['aftersale_bn'] = $returns[$value['return_id']];
        }
            //end

                    //读取已在队列中的数据，标红用
            $sql = 'select bn from sdb_omevirtualwms_data_status where type=\'reship\'';
            foreach ($this->db->select($sql) as $row)
                $this->queue[] = $row['bn'];
                //end
      
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

            //根据订单号 and 售后单号 and 退货单生成时间搜索  modify by lymz at 2011-11-11 11:25:04
        if(isset($filter['order_bn']) && $filter['order_bn']){
            $orderObj = app::get('ome')->model("orders");
            $rows = $orderObj->getList('order_id',array('order_bn|has'=>$filter['order_bn']));
            $orderId[] = 0;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }

            $where[] = 'order_id IN ('.implode(',', $orderId).')';
            unset($filter['order_bn']);
        }
        if(isset($filter['aftersale_bn']) && $filter['aftersale_bn']){
            $orderObj = app::get('ome')->model("return_product");
            $rows = $orderObj->getList('return_id',array('return_bn|has'=>$filter['aftersale_bn']));
            $returnId[] = 0;
            foreach($rows as $row){
                $returnId[] = $row['return_id'];
            }

            $where[] = 'return_id IN ('.implode(',', $returnId).')';
            unset($filter['aftersale_bn']);
        }
        return parent::_filter($filter,$tableAlias,$baseWhere) . ' and ' . implode(' and ',$where);;
    }
    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema(){
        $schema = array (
            'columns' => array (
                'reship_bn' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '退货单号',
                    'comment' => '退货单号',
                    'editable' => false,
                    'order' =>1,
                    'width' =>180,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'is_title' => true,
                ),
                'op_id' => array (
                    'type' => 'varchar(100)',
                    'label' => '操作人',
                    'comment' => '操作人',
                    'editable' => false,
                    'width' =>100,
                      'order' =>2,
                ),
               't_begin' => array (
                    'type' => 'time',
                    'label' => '时间',
                    'comment' => '单据生成时间',
                    'width' =>160,
                    'editable' => false,
                      'order' =>3,
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
                'aftersale_bn' => array (
                    'type' => 'varchar(32)',
                    'label' => '售后单号',
                    'comment' => '售后单号',
                    'width' =>180,
                    'editable' => false,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
         
            ),
            'idColumn' => 'return_id',
            'in_list' => array (
                0 => 'reship_bn',
                1 => 'op_id',
                2 => 't_begin',
                'order_bn','aftersale_bn'
               ),
            'default_in_list' => array (
                0 => 'reship_bn',
                1 => 'op_id',
                2 => 't_begin',
                'order_bn','aftersale_bn'
            ),
        );
        return $schema;
    }

    /**
     * modifier_branch_id
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_branch_id($row){ 
		$oBranch = app::get('ome')->model('branch');
		$data = $oBranch->getList('name',array('branch_id'=>$row));
		return $data[0]['name'];
    }
	
    /**
     * modifier_op_id
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_op_id($row){ 
        $obj = app::get('pam')->model('account');
		$op  = $obj->dump(array('account_id'=>$row),'login_name');
        return $op["login_name"];
    }

        //自定义导出 add by lymz at 2011-11-11 12:01:38
    function fgetlist_csv( &$data,$filter,$offset,$exportType =1 ){
        $limit = 100;

        if (!$data['title']) {
            $cols = array('return_bn'=>'退货单号','order_id'=>'订单号','last_modified'=>'生成日期','title'=>'标题','reship_name'=>'收货人姓名','reship_addr'=>'收货人地址','reship_zip'=>'收货人邮编','reship_tel'=>'收货人电话');
            $data['title'] = '"*:'.implode('","*:',$cols).'"';
        }
        if(!$data['sub_title']){
            $productCols = array('bn'=>'货号','name'=>'名称','num'=>'数量','price'=>'价格');
            $data['sub_title'] = '"","*:'.implode('","*:',$productCols).'"';
        }
        
        if(!$list = app::get('ome')->model('return_product')->getList(implode(',',array_keys($cols)).',return_id,reship_province,reship_city,reship_district,reship_mobile',$filter,$offset*$limit,$limit)) return false;

		$data['contents'] = array();
        foreach( $list as $line => $row ){
            $rowVal = array();
            foreach( $cols as $key => $nam ) {
                if ($key == 'reship_addr') {//收货人地址
		            $row[$key] = $row['reship_province'].$row['reship_city'].$row['reship_district'].$row['reship_addr'];
                }
                if ($key == 'last_modified') {//发货单生成日期
                    $row[$key] = date('Y-m-d',$row[$key]);
                }
                if ($key == 'reship_tel') {//收货人电话
                    $row[$key] = $row['reship_tel'] ? $row['reship_tel'] : $row['reship_mobile'];
                }
                if ($key == 'order_id') {//订单号
                    $row[$key] = current(app::get('ome')->model('orders')->dump(array('order_id'=>$row[$key]),'order_bn'));
                }
                $rowVal[] = addslashes($row[$key]);
            }
            $data['contents'][] = '"'.implode('","',$rowVal).'"';
            
                //发货单对应商品明细
            $data['contents'][] = $data['sub_title'];
            $products = app::get('ome')->model('return_product_items')->getList('*',array('return_id'=>$row['return_id']));
            foreach ($products as $product) {
                $rowVal = array();
                foreach( $productCols as $key => $nam ) {
                    $rowVal[] = addslashes($product[$key]);
                }
                $data['contents'][] = '"","'.implode('","',$rowVal).'"';
            }
        }
        return true;

    }

}
