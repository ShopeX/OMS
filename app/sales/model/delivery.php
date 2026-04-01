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
	* 基础物料销售发货明细类
*/
class sales_mdl_delivery extends dbeav_model{
	var $export_name = '基础物料销售发货明细';
	
    function __construct($app){
        parent::__construct(app::get('ome'));
    }

    public function searchOptions(){

        $Options = array(
           'order_bn'=>'订单号',
           'delivery_bn'=>'发货单号',
           'bn'=>'基础物料编码',
        );
        return $Options;
    }

    public function io_title( $ioType='csv' ){
		switch( $ioType ){
            case 'csv':
            default:
	           $this->oSchema['csv']['delivery'] = array(
	                '*:店铺'=>'shop_id',
					'*:订单号'=>'order_id',
					'*:成本单价'=>'cost_price',
					'*:毛利率'=>'gross_sales_rate',
					'*:销售毛利'=>'gross_sales',
					'*:成本金额'=>'cost_amount',
					'*:成交金额'=>'sales_amount',
					'*:下单时间'=>'order_createtime',
					'*:付款时间'=>'order_paytime',
					'*:基础物料类型'=>'type_id',
					'*:基础物料品牌'=>'brand_id',
                    '*:基础物料编码'=>'bn',
                    '*:基础物料名称'=>'name',
					'*:数量'=>'num',
					'*:原始单价'=>'price',
					'*:原始金额'=>'amount',
					'*:优惠金额'=>'discount_amount',
					'*:附加费'=>'additional_costs',
					'*:成交价格'=>'sales_price',
					'*:发货时间'=>'delivery_time',
					'*:发货单号'=>'delivery_id',
					'*:快递单号'=>'express_no',
					'*:发货仓库'=>'branch_id',
	           );
        }


	        $this->ioTitle[$ioType]['delivery'] = array_keys( $this->oSchema[$ioType]['delivery'] );
	        return $this->ioTitle[$ioType]['delivery'];
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
         $list = $this->getList('*',$filter,$offset*$limit,$limit);
	   

	    if( !$list ) return false;
          
        foreach ($list as $aFilter) {
        	foreach ($this->oSchema['csv']['delivery'] as $kk => $v) {

                switch($v){
                	case 'shop_id':
                	   $shop = $this->db->selectrow('select name from sdb_ome_shop where shop_id="'.$aFilter['shop_id'].'"');
                       $aFilter['shop_id'] = $shop['name'];
                	break;
                	case 'order_id':
                       $aFilter['order_id'] = "".$aFilter['order_id']."";
                	break;
                	case 'cost_price':
                       $aFilter['cost_price'] = $aFilter['cost_price']?$aFilter['cost_price']:0.00;
                	break;
                	case 'gross_sales_rate':
                       $aFilter['gross_sales_rate'] = $aFilter['gross_sales_rate']?$aFilter['gross_sales_rate']:0.00;
                	break;
                	case 'gross_sales':
                       $aFilter['gross_sales'] = $aFilter['gross_sales']?$aFilter['gross_sales']:0.00;
                	break;  
                	case 'cost_amount':
                       $aFilter['cost_amount'] = $aFilter['cost_amount']?$aFilter['cost_amount']:0.00;
                	break;
                	case 'sales_amount':
                       $aFilter['sales_amount'] = $aFilter['sales_amount']?$aFilter['sales_amount']:0.00;
                	break;
                	case 'order_createtime':
                	   $aFilter['order_createtime'] = date('Y-m-d H:i:s',$aFilter['order_createtime']);
                	break;
                	case 'delivery_time':
                	   $aFilter['delivery_time'] = date('Y-m-d H:i:s',$aFilter['delivery_time']);
                	break;
                	case 'order_paytime':
                	   $aFilter['order_paytime'] = date('Y-m-d H:i:s',$aFilter['order_paytime']);
                	break;
                	case 'price':
                       $aFilter['price'] = $aFilter['price']?$aFilter['price']:0.00;
                	break;
                	case 'amount':
                       $aFilter['amount'] = $aFilter['amount']?$aFilter['amount']:0.00;
                	break;
                	case 'discount_amount':
                       $aFilter['discount_amount'] = $aFilter['discount_amount']?$aFilter['discount_amount']:0.00;
                	break;
                	case 'additional_costs':
                       $aFilter['additional_costs'] = $aFilter['additional_costs']?$aFilter['additional_costs']:0.00;
                	break;
                	case 'sales_price':
                       $aFilter['sales_price'] = $aFilter['sales_price']?$aFilter['sales_price']:0.00;
                	break;
                	case 'branch_id':
                	   $branch = $this->db->selectrow('select name from sdb_ome_branch where branch_id='.$aFilter['branch_id']);
                       $aFilter['branch_id'] = $branch['name'];
                	break;
                	case 'reship_id':
                	   $branch = $this->db->selectrow('select reship_bn from sdb_ome_reship where reship_id='.$aFilter['reship_id']);
                       $aFilter['reship_id'] = $branch['reship_bn'];
                	break;
                	case 'brand_id':
                	   $branch = $this->db->selectrow('select brand_name from sdb_ome_brand where brand_id='.$aFilter['brand_id']);
                       $aFilter['brand_id'] = $branch['brand_name'];
                	break;
                	case 'type_id':
                	   $branch = $this->db->selectrow('select name from sdb_ome_goods_type where type_id='.$aFilter['type_id']);
                       $aFilter['type_id'] = $branch['name'];
                	break;
                }

            	$delvieryRow[$kk] = $aFilter[$v];

            }
        	$data['contents'][] = '"'.implode('","',$delvieryRow).'"';
        }


        return true;
    }
    
    public function count($filter = null){

	    $sql = 'select count(di.bn) as _count from sdb_ome_delivery d left join sdb_ome_delivery_items di on d.delivery_id = di.delivery_id where '.$this->_filter($filter).' and status="succ"';
        $row = $this->db->select($sql);
        return intval($row[0]['_count']);
    }

    public function getList($cols = '*',$filter = array(),$offset = 0,$limit = 1,$orderType = null){

	       $sql = 'select di.item_id,d.shop_id,di.bn,di.product_name as name,di.number as num,di.product_id,d.delivery_time,d.delivery_id,d.branch_id,d.logi_no as express_no from sdb_ome_delivery d left join sdb_ome_delivery_items di on d.delivery_id = di.delivery_id where '.$this->_filter($filter).' and status="succ"';
           
           if($orderType)$sql.=' ORDER BY '.(is_array($orderType)?implode($orderType,' '):$orderType);

	       $data = $this->db->selectLimit($sql,$limit,$offset);

           //附加费：保价费+税金
           $additional_costs = 0;
           
            foreach($data as $k=>$v){
               $order = $this->db->selectrow('select o.paytime,o.createtime,o.order_bn from sdb_ome_orders o left join sdb_ome_delivery_order do on o.order_id = do.order_id where do.delivery_id = '.$v['delivery_id']);
               $data[$k]['order_paytime'] = $order['paytime'];
               $data[$k]['order_createtime'] = $order['createtime'];
               $data[$k]['order_id'] = $order['order_bn'];

               if(!empty($v['product_id'])){
                   $sql = 'select p.material_name, p.type AS type_id AS name from sdb_material_basic_material p where p.bm_id='.$v['product_id'];
                   $Oproduct = $this->db->selectrow($sql);
                   $data[$k]['name'] = $Oproduct['name'];
                   $data[$k]['type_id'] = $Oproduct['type_id'];
                   //$data[$k]['brand_id'] = $Oproduct['brand_id'];
               }
            }
/*
cost_price 成本单价
gross_sales_rate  毛利率
gross_sales  销售毛利
cost_amount  成本金额
sales_amount  成交金额
price         原始单价
amount        原始金额
discount_amount  优惠金额
additional_costs  附加费
sales_price      成交价格
*/
	       return $data;
    }
    
    public function _filter($filter){

       $where = ' 1 ';
       if(isset($filter['order_bn'])){
       	  $deliverys = array(0);
       	  $delivery = $this->db->select('select sdo.delivery_id from sdb_ome_delivery_order sdo left join sdb_ome_orders o on sdo.order_id = o.order_id left join sdb_ome_delivery d on d.delivery_id = sdo.delivery_id where o.order_bn like "'.$filter['order_bn'].'%" and d.status="succ"');

          foreach ($delivery as $v) {
          	 $deliverys[] = $v['delivery_id'];
          }
          $where .= 'and d.delivery_id in ('.implode(',',$deliverys).')';
          unset($filter['order_bn']);
       }

       if(isset($filter['delivery_bn'])){
       	   $where .= ' and delivery_bn like "'.$filter['delivery_bn'].'%"';
       	   unset($filter['delivery_bn']);
       }
       
       if(isset($filter['item_id'])){

            $where = 'di.item_id in ('.implode(',',$filter['item_id']).')';
          
            unset($filter['item_id']);
       }

       if(isset($filter['bn'])){
          $products = array(0);
          if(is_array($filter['bn'])){
             $_where = 'bn in ("'.implode('","',$filter['bn']).'")';
          }else{
          	 $_where = 'bn like \''.$filter['bn'].'%\'';
          }

          $sql = 'SELECT product_id FROM sdb_ome_delivery_items WHERE '.$_where;
          $product = kernel::database()->select($sql);  

          foreach ($product as $v) {
          	 $products[] = $v['product_id'];
          }
          $where .= ' and di.product_id in ('.implode(',',$products).')';
          unset($filter['bn']);
       }

       return $where.' and '.parent::_filter($filter);
    }

    function get_schema(){
    	$schema =  array(
			'columns' => array (  
				'shop_id' => array (
				    'type' => 'table:shop@ome',
				    'label' => '店铺',
				    'width' => 120,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'order_id' => array (
				    'type' => 'table:orders@ome',
				    'label' => '订单号',
				    'width' => 140,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'cost_price' => array (
				    'type' => 'money',
				    'label' => '成本单价',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'gross_sales_rate' => array (
				    'type' => 'money',
				    'label' => '毛利率',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'gross_sales' => array (
				    'type' => 'money',
				    'label' => '销售毛利',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'cost_amount' => array (
				    'type' => 'money',
				    'label' => '成本金额',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'sales_amount' => array (
				    'type' => 'money',
				    'label' => '成交金额',
				    'width' => 75,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'order_createtime' => array (
				    'type' => 'time',
				    'label' => '下单时间',
				    'width' => 130,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'order_paytime' => array (
				    'type' => 'time',
				    'label' => '付款时间',
				    'width' => 130,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'type_id' => array (
				    'type' => 'table:goods_type@ome',
				    'label' => '基础物料类型',
				    'width' => 120,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'brand_id' => array (
				    'type' => 'table:brand@ome',
				    'label' => '基础物料品牌',
				    'width' => 120,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'bn' => array (
				    'type' => 'varchar(30)',
                    'label' => '基础物料编码',
				    'width' => 85,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'name' => array (
				    'type' => 'varchar(200)',
                    'label' => '基础物料名称',
				    'width' => 190,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'num' => array (
				    'type' => 'number',
				    'label' => '数量',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'price' => array (
				    'type' => 'money',
				    'label' => '原始单价',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'amount' => array (
				    'type' => 'money',
				    'label' => '原始金额',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'discount_amount' => array (
				    'type' => 'money',
				    'label' => '优惠金额',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'additional_costs' => array (
				    'type' => 'money',
				    'label' => '附加费',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'sales_price' => array (
				    'type' => 'money',
				    'label' => '成交价格',
				    'width' => 75,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'delivery_time' => array (
				    'type' => 'time',
				    'label' => '发货时间',
				    'width' => 130,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'delivery_id' => array (
				    'type' => 'table:delivery@ome',
				    'label' => '发货单号',
				    'width' => 120,
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'express_no' => array (
				    'type' => 'money',
				    'label' => '快递单号',
				    'width' => 210,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'branch_id' => array (
				    'type' => 'table:branch@ome',
				    'label' => '发货仓库',
				    'width' => 75,
				    'searchtype' => 'has',
				    'editable' => false,
				    'in_list' => true,
				    'default_in_list' => true,
				),
				'item_id' => array (
				    'type' => 'table:delivery_items@ome',
				    'label' => '发货明细ID',
				    'editable' => false,
				    'in_list' => false,
				    'pkey' => true,
				    'default_in_list' => false,
				),
			),
            'idColumn' => 'item_id',
            'in_list' => array (
				0=>'shop_id',
				1=>'order_id',
				2=>'cost_price',
				3=>'gross_sales_rate',
				4=>'gross_sales',
				5=>'cost_amount',
				6=>'sales_amount',
				7=>'order_createtime',
				8=>'order_paytime',
				9=>'type_id',
				10=>'brand_id',
				11=>'bn',
				12=>'name',
				13=>'num',
				14=>'price',
				15=>'amount',
				16=>'discount_amount',
				17=>'additional_costs',
				18=>'sales_price',
				19=>'delivery_time',
				20=>'delivery_id',
				21=>'express_no',
				22=>'branch_id',
            ),
            'default_in_list' => array (
				0=>'shop_id',
				1=>'order_id',
				2=>'cost_price',
				3=>'gross_sales_rate',
				4=>'gross_sales',
				5=>'cost_amount',
				6=>'sales_amount',
				7=>'order_createtime',
				8=>'order_paytime',
				9=>'type_id',
				10=>'brand_id',
				11=>'bn',
				12=>'name',
				13=>'num',
				14=>'price',
				15=>'amount',
				16=>'discount_amount',
				17=>'additional_costs',
				18=>'sales_price',
				19=>'delivery_time',
				20=>'delivery_id',
				21=>'express_no',
				22=>'branch_id',
            ),
    	);
    	return $schema;
    }
}
