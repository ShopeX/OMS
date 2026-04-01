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
/**
	* ShopEx licence
	*
	* @copyright  Copyright (c) 2005-2012 ShopEx Technologies Inc. (http://www.shopex.cn)
	* @license  http://ecos.shopex.cn/ ShopEx License
	* @date 2012-08-22
	* 商品销售退货明细类
*/
class sales_mdl_reship extends dbeav_model{
	var $export_name = '商品销售退货明细';
	
    function __construct($app){
        parent::__construct(app::get('ome'));
    }

    public function searchOptions(){

        $Options = array(
           'return_bn'=>'售后申请单号',
           'order_bn'=>'订单号',           
           'reship_bn'=>'退换货单号',
           'bn'=>'基础物料编码',
        );
        return $Options;
     }

    public function io_title( $ioType='csv' ){
		switch( $ioType ){
            case 'csv':
            default:
	           $this->oSchema['csv']['reship'] = array(
	                '*:店铺'=>'shop_id',
					'*:售后申请单号'=>'return_bn',
					'*:申请时间'=>'add_time',
					'*:订单号'=>'order_id',					
					'*:基础物料编码'=>'bn',
					'*:数量'=>'num',
					'*:售后类型'=>'return_type',
					'*:退款金额'=>'refundmoney',
					'*:商品名称'=>'name',
					'*:退换货单号'=>'reship_id',
					'*:基础物料品牌'=>'brand_id',
					'*:基础物料类型'=>'type_id',
					'*:收货时间'=>'reship_time',
					'*:收货仓库'=>'branch_id',
	           );
        }

	    $this->ioTitle[$ioType]['reship'] = array_keys( $this->oSchema[$ioType]['reship'] );
	    return $this->ioTitle[$ioType]['reship'];
    }

    public function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){

        if( !$data['title'] ){
            $title = array();
            foreach( $this->io_title() as $k => $v ){
                $title[] = $v;
			}
            $data['title'] = '"'.implode('","',$title).'"';
        }
       
        $limit = 100;

  
	    if(!$list = $this->getList('*',$filter,$offset*$limit,$limit)){
            return false;
        }

        foreach ($list as $aFilter) {
            foreach ($this->oSchema['csv']['reship'] as $kk => $v) {
                switch($v){
                	case 'shop_id':
                	   $shop = $this->db->selectrow('select name from sdb_ome_shop where shop_id="'.$aFilter['shop_id'].'"');
                       $aFilter['shop_id'] = $shop['name'];
                	break;
                	case 'refundmoney':
                       $aFilter['refundmoney'] = $aFilter['refundmoney']?$aFilter['refundmoney']:0.00;
                	break;
                	case 'order_id':
                	   $order = $this->db->selectrow('select order_bn from sdb_ome_orders where order_id="'.$aFilter['order_id'].'"');
                       $aFilter['order_id'] = '="'.$order['order_bn'].'"';
                	break;
                	case 'branch_id':
                	   $branch = $this->db->selectrow('select name from sdb_ome_branch where branch_id='.$aFilter['branch_id']);
                       $aFilter['branch_id'] = $branch['name'];
                	break;
                	case 'reship_id':
                	   $branch = $this->db->selectrow('select reship_bn from sdb_ome_reship where reship_id='.$aFilter['reship_id']);
                       $aFilter['reship_id'] = '  '.$branch['reship_bn'];
                	break;
                	case 'brand_id':
                	   $branch = $this->db->selectrow('select brand_name from sdb_ome_brand where brand_id='.$aFilter['brand_id']);
                       $aFilter['brand_id'] = $branch['brand_name'];
                	break;
                	case 'type_id':
                	   $branch = $this->db->selectrow('select name from sdb_ome_goods_type where type_id='.$aFilter['type_id']);
                       $aFilter['type_id'] = $branch['name'];
                	break;
                	case 'add_time':
                	   $aFilter['add_time'] = date('Y-m-d H:i:s',$aFilter['add_time']);
                	break;
                	case 'reship_time':
                	   $aFilter['reship_time'] = date('Y-m-d H:i:s',$aFilter['reship_time']);
                	break;
                }

	        	$reshipRow[$kk] = $aFilter[$v];

            }
            
            $data['contents'][] = '"'.implode('","',$reshipRow).'"';
        }

        return true;
    }

    public function count( $filter = null ){

        return count($this->getList($filter));
    }
    
    public function getList($cols = '*',$filter = array(),$offset = 0,$limit = 1,$orderType = null){

	       $sql = 'select rp.return_id,reship.reship_id,reship.order_id,reship.return_type,rp.refundmoney,rp.member_id,reship.ship_mobile,rp.shop_id,ra.apply_id as return_apply_id,rp.add_time,reship.t_begin as check_time,ra.create_time as refundtime,reship.op_id as check_op_id,ri.op_id,ra.apply_op_id as refund_op_id,reship.is_check from sdb_ome_return_product rp left join sdb_ome_reship reship on rp.return_id = reship.return_id left join sdb_ome_refund_apply ra on rp.return_id = ra.return_id left join sdb_ome_reship_items ri on reship.reship_id = ri.reship_id where '.$this->_filter($filter);
           
           if($orderType)$sql.=' ORDER BY '.(is_array($orderType)?implode($orderType,' '):$orderType);

	       $data = $this->db->selectLimit($sql,$limit,$offset);

#echo "<pre>";
#print_r($data);exit;

           foreach($data as $k=>$v){
               if(!empty($v['reship_id'])){
                   $sql = 'select acttime from sdb_ome_return_process_items where reship_id='.$v['reship_id'];
                   $Oreturn_process = $this->db->selectrow($sql);
                   $data[$k]['acttime'] = $Oreturn_process['acttime'];

                   if(!empty($v['return_id'])){
                   	  $id = 'return_'.$v['return_id'];
                   }elseif (!empty($v['reship_id'])) {
                   	  $id = 'reship_'.$v['reship_id'];
                   }else{
                   	  $id = 'apply_'.$v['return_apply_id'];
                   } 
                   

                   $data[$k]['id'] = $id;
               }
           }

	       return $data;
    }
    
    public function _filter($filter,$tableAlias=null,$baseWhere=null){

       $where = '1 ';
       if(isset($filter['order_bn'])){
       	  $orders = array(0);
       	  $Oorder = $this->app->model("orders");
          $order = $Oorder->getList('order_id',array('order_bn|head'=>$filter['order_bn']));
          foreach ($order as $v) {
          	 $orders[] = $v['order_id'];
          }
          $where .= 'and pi.order_id in ('.implode(',',$orders).')';
          unset($filter['order_bn']);
       }

       if(isset($filter['reship_bn'])){
       	  $reships = array(0);
       	  $Oreship = $this->app->model("reship");
          $reship = $Oreship->getList('reship_id',array('reship_bn|head'=>$filter['reship_bn']));
          foreach ($reship as $v) {
          	 $reships[] = $v['reship_id'];
          }
          $where .= ' and pi.reship_id in ('.implode(',',$reships).')';
          unset($filter['reship_bn']);
       }

       if(isset($filter['item_id'])){
            if(is_array($filter['item_id'])){
                $where .= ' and ri.item_id in ('.implode(',',$filter['item_id']).')';
            }else{
                $where .= ' and ri.item_id = '.$filter['item_id'];
            }
          
            unset($filter['item_id']);
       }

       if(isset($filter['bn'])){
          $products = array(0);
          
          if(is_array($filter['bn'])){
             $_where = 'bn in ("'.implode('","',$filter['bn']).'")';
          }else{
          	 $_where = 'bn like \''.$filter['bn'].'%\'';
          }

          $sql = 'SELECT product_id FROM sdb_ome_reship_items WHERE '.$_where;
          $product = kernel::database()->select($sql);  

          foreach ($product as $v) {
          	 $products[] = $v['product_id'];
          }
          $where .= ' and ri.product_id in ('.implode(',',$products).')';
       	  unset($filter['bn']);
       }

       if(isset($filter['return_bn'])){
          $return_products = array(0);
          $Oreturn = $this->app->model("return_product");
          $return_product = $Oreturn->getList('return_id',array('return_bn|head'=>$filter['return_bn']));

          foreach ($return_product as $v) {
          	 $return_products[] = $v['return_id'];
          }
          $where .= ' and rp.return_id in ('.implode(',',$return_products).')';
          unset($filter['return_bn']);
       }
       
       return $where.' and '.parent::_filter($filter,$tableAlias,$baseWhere);
    }

    function get_schema(){
    	$schema =  array(
			'columns' => array (  
				'id' => array (
				    'type' => 'varchar(32)',
				    'pkey' => true,
				    'label' => 'ID',
				    'editable' => false,
				    'in_list' => false,
				    'default_in_list' => false,
				),
				'shop_id' => array (
				    'type' => 'table:shop@ome',
				    'label' => '店铺',
				    'width' => 120,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				    'filtertype' => 'normal',
				    'filterdefault' => true,
				    'order'=>1,
				),
				'order_id' => array (
				    'type' => 'table:orders@ome',
				    'label' => '订单号',
				    'width' => 140,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				    'filtertype' => 'normal',
				    'filterdefault' => true,
				    'order'=>2,
				),
				'return_type' => array (
				    'type' =>
				     array (
				        'return' => '退货',
				        'change' => '换货',
				     ),
				    'label' => '售后类型',
				    'width' => 95,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				    'filtertype' => 'normal',
				    'filterdefault' => true,
				    'order'=>3,
				),
				'refundmoney' => array (
				    'type' => 'money',
				    'label' => '退款金额',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				    'order'=>4,
				),
				'member_id' => 
				array (
				  'type' => 'table:members@ome',
				  'required' => false,
				  'editable' => false,
				  'label' => '用户名',
				  'in_list' => true,
				  'default_in_list' => true,
				  'order' => 5,
			      'filtertype' => 'normal',
			      'filterdefault' => true,
				  'width' => 130,
				),
				'ship_mobile' => 
				array (
				  'type' => 'table:reship@ome',
				  'required' => false,
				  'editable' => false,
				  'label' => '手机号',
				  'in_list' => false,
				  'default_in_list' => false,
				  'filtertype' => 'normal',
				  'filterdefault' => true,
				  'order' => 6,
				  'width' => 130,
				),
				'return_id' => array (
				    'type' => 'table:return_product@ome',
				    'label' => '售后申请单号',
				    'editable' => false,
				    'in_list' => false,
				    'default_in_list' => false,
				    'order'=>7,
				    'filtertype' => 'normal',
				    'filterdefault' => true,
				),
				'reship_id' => array (
				    'type' => 'table:reship@ome',
				    'label' => '退换货单号',
				    'width' => 140,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				    'order'=>8,
				    'filtertype' => 'normal',
				    'filterdefault' => true,
				),
				'return_apply_id' => array (
				    'type' => 'table:refund_apply@ome',
				    'label' => '退款申请单号',
				    'width' => 140,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				    'order'=>9,
				    'filtertype' => 'normal',
				    'filterdefault' => true,
				),
				'add_time' => array (
				    'type' => 'time',
				    'label' => '售后申请时间',
				    'width' => 130,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				    'filtertype' => 'time',
				    'filterdefault' => true,
				    'order'=>10,
				),
				'check_time' => array (
				    'type' => 'time',
				    'label' => '审核时间',
				    'width' => 130,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				    'filtertype' => 'time',
				    'filterdefault' => true,
				    'order'=>11,
				),
				'acttime' => 
				array (
				  'type' => 'time',
				  'required' => false,
				  'editable' => false,
				  'label' => '质检时间',
				  'filterdefault' => true,
				  'filtertype' => 'time',
				  'in_list' => true,
				  'default_in_list' => true,
				  'order' => 12,
				  'width' => 130,
				),
				'refundtime' => 
				array (
				  'type' => 'time',
				  'required' => false,
				  'editable' => false,
				  'label' => '退款时间',
				  'filterdefault' => true,
				  'filtertype' => 'time',
				  'in_list' => true,
				  'default_in_list' => true,
				  'order' => 13,
				  'width' => 130,
				),
				'check_op_id' => 
				array (
				  'type' => 'table:account@pam',
				  'required' => false,
				  'editable' => false,
				  'label' => '审核人',
				  'in_list' => true,
				  'filterdefault' => true,
				  'filtertype' => 'yes',
				  'default_in_list' => true,
				  'order' => 14,
				  'width' => 130,
				), 
				'op_id' => 
				array (
				  'type' => 'table:account@pam',
				  'required' => false,
				  'editable' => false,
				  'label' => '质检人',
				  'in_list' => true,
				  'filterdefault' => true,
				  'filtertype' => 'yes',
				  'default_in_list' => true,
				  'order' => 15,
				  'width' => 130,
				), 
				'refund_op_id' => 
				array (
				  'type' => 'table:account@pam',
				  'required' => false,
				  'editable' => false,
				  'label' => '退款人',
				  'in_list' => true,
				  'filterdefault' => true,
				  'filtertype' => 'yes',
				  'default_in_list' => true,
				  'order' => 16,
				  'width' => 130,
				), 
				'is_check' => 
				array (
				  'type' => array(
				    0 => '未审核',
				    1 => '审核成功',
				    2 => '审核失败',
				    3 => '收货成功',
				    4 => '拒绝收货',
				    5 => '拒绝',
				    6 => '补差价',
				    7 => '完成',
				    8 => '质检通过',
				    9 => '拒绝质检',
				    10 => '质检异常',
				  ),
				  'required' => false,
				  'editable' => false,
				  'label' => '状态',
				  'in_list' => false,
				  'default_in_list' => false,
				  'filtertype' => 'normal',
				  'filterdefault' => true,
				  'order' => 17,
				  'width' => 130,
				),
			),
            'idColumn' => 'id',
            'in_list' => array (
				0 => 'shop_id',
				1 => 'order_id',
				2 => 'return_type',
				3 => 'refundmoney',
				4 => 'member_id',
				5 => 'ship_mobile',
				6 => 'return_id',
				7 => 'reship_id',
				8 => 'return_apply_id',
				9 => 'add_time',
				10 => 'check_time',
				11 => 'acttime',
				12 => 'refundtime',
				13 => 'check_op_id',
				14 => 'op_id',
				15 => 'refund_op_id',
				16 => 'is_check',
            ),
            'default_in_list' => array (
                0 => 'shop_id',
				1 => 'order_id',
				2 => 'return_type',
				3 => 'refundmoney',
				4 => 'member_id',
				5 => 'ship_mobile',
				6 => 'return_id',
				7 => 'reship_id',
				8 => 'return_apply_id',
				9 => 'add_time',
				10 => 'check_time',
				11 => 'acttime',
				12 => 'refundtime',
				13 => 'check_op_id',
				14 => 'op_id',
				15 => 'refund_op_id',
				16 => 'is_check',
            ),
    	);
    	return $schema;
    }
}
