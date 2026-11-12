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

class omeanalysts_mdl_ome_goodsamount extends dbeav_model{
    
    /**
     * count
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function count($filter=null){
            return $this->_get_count($filter);
        }

    /**
     * _get_count
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function _get_count($filter=null){
          //  $sql = 'SELECT count(*) as _count FROM sdb_omeanalysts_ome_goodsamount as g  WHERE '.$this->_filter($filter);
            $sql = 'SELECT g.goods_bn as goods_bn,
                    g.name as name,
                    b.brand_name as brand_id,
                    sum(g.sales_num) as sales_num,
                    sum(g.purchase_num) as purchase_num,
                    sum(g.allocation_num) as allocation_num'.
                    ' FROM sdb_omeanalysts_ome_goodsamount as g 
                    left join sdb_ome_brand as b 
                    on g.brand_id=b.brand_id 
                    WHERE '.$this->_filter($filter).
                    ' GROUP BY g.goods_bn,g.name,b.brand_name 
                    ORDER BY g.sales_num DESC';
            $row = $this->db->select($sql);
            return count($row);
        }
    
    /**
     * 获取_sale
     * @param mixed $filter filter
     * @return mixed 返回结果
     */
    public function get_sale($filter=null){
        //销售额
      //  $sql = 'SELECT * as sale_amount FROM sdb_omeanalysts_ome_goodsamount as g WHERE '.$this->_filter($filter).' ORDER BY sales_num DESC';
        $sql = 'SELECT g.goods_bn as goods_bn,g.name as name,g.brand_id as brand_id,g.createtime as createtime,sum(g.sales_num) as sales_num,
                    g.store as store,sum(g.purchase_num) as purchase_num,sum(g.allocation_num) as allocation_num'.
                ' FROM sdb_omeanalysts_ome_goodsamount as g WHERE '.$this->_filter($filter).' GROUP BY g.goods_bn,g.name,g.brand_id ,g.createtime ORDER BY g.sales_num DESC';       
        
        $row = $this->db->select($sql);
        return $row;
    }
    
    //获取期初货存
    /**
     * 获取_pre_sale
     * @param mixed $filter filter
     * @param mixed $goods_bn goods_bn
     * @return mixed 返回结果
     */
    public function get_pre_sale($filter=null,$goods_bn=null){
        $time_from = strtotime($filter['time_from']);
        $sql = 'SELECT g.store FROM sdb_omeanalysts_ome_goodsamount as g WHERE g.goods_bn=\''.$goods_bn.'\' and g.createtime='.$time_from;
        $row = $this->db->selectrow($sql);
        return $row ? $row['store'] : 0;
    }
    
    //配置信息
    /**
     * export_params
     * @return mixed 返回值
     */
    public function export_params(){

        $filter = $this->export_filter;
        if($filter['isSelectedAll'] == '_ALL_'){
            $filter = array();
        }
        $data = $_SESSION['data'];
        $filter = $data;
        
        $params = array(
            'filter' => $filter,
            'single'=> array(
                '1'=> array(
                    'method' => 'get_export_main',
                    'offset' => 0,
                    'limit' => 5000,
                    'filename' => 'goodsamountContent',
                ),
            ),
        );
        return $params;

    }

    //商品销量统计title
    /**
     * 获取_export_main_title
     * @return mixed 返回结果
     */
    public function get_export_main_title(){
        $title = array(
            '*:商品货号',
            '*:商品名称',
            '*:品牌',
            '*:销量',
            '*:期初库存',
            '*:采购入库数量',
            '*:调拨入库数量',
            '*:售罄率',
        );
        return $title;
    }

    //商品销量统计
    /**
     * 获取_export_main
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @param mixed $data 数据
     * @return mixed 返回结果
     */
    public function get_export_main($filter,$offset,$limit,&$data){
        $goodsamountMdl = $this->app->model('ome_goodsamount');
        $brandMdl = app::get('ome')->model('brand');
        $data = $goodsamountMdl->getList('goods_bn,name,brand_id,sales_num,store,purchase_num,allocation_num,sold_out_rate',$filter,$offset,$limit);
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
            $where[] = ' g.createtime >='.strtotime($filter['time_from']);
        }
        if(isset($filter['time_to']) && $filter['time_to']){
            $where[] = ' g.createtime <'.(strtotime($filter['time_to'])+86400);
        }
        if(isset($filter['name']) && $filter['name']){
            $where[] = ' g.name LIKE \''.addslashes($filter['name']).'%\'';
        }
    	if(isset($filter['type_id']) && $filter['type_id']){
            $where[] = ' g.shop_id =\''.addslashes($filter['type_id']).'\'';
        }
        if(isset($filter['brand_id']) && $filter['brand_id']){
            $where[] = ' g.brand_id LIKE \''.addslashes($filter['brand_id']).'%\'';
        }
        if(isset($filter['goods_bn']) && $filter['goods_bn']){
            if(is_array($filter['goods_bn'])){
                $fil=array();
                foreach($filter['goods_bn'] as $f){
                    $fil[]='\''.$f.'\'';
                }
                $where[] = ' g.goods_bn IN ('.implode(',',$fil).')';
            }else{
                $where[] = ' g.goods_bn LIKE \''.addslashes($filter['goods_bn']).'%\'';
            }
            unset($filter['goods_bn']);
        }
        return parent::_filter($filter,'g',$baseWhere)." AND ".implode(' AND ', $where);
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        //$time_from = $filter['time_from'];
        //$time_to = $filter['time_to'];
        //$time_from = strtotime($time_from);
        /*
        if($time_from == strtotime(date("Y-m-d"),time())){
            $time_from = ($time_from - 86400);
        }
        */
    
        //$last_time = date("Y-m-d",($time_from - 86400));
        $fil['time_from'] = $filter['time_from'];
        $fil['time_to'] = $filter['time_to'];
        base_kvstore::instance('omeanalysts_goodsamount')->store('goodsamount_filter',serialize($filter));
        $sql = 'SELECT g.goods_bn as goods_bn,
                g.name as name,
                b.brand_name as brand_id,
                sum(g.sales_num) as sales_num,
                sum(g.purchase_num) as purchase_num,
                sum(g.allocation_num) as allocation_num'.
                ' FROM sdb_omeanalysts_ome_goodsamount as g 
                left join sdb_ome_brand as b 
                on g.brand_id=b.brand_id 
                WHERE '.$this->_filter($filter).
                ' GROUP BY g.goods_bn,g.name
                ';
        if($orderType && $orderType!='sold_out_rate asc' && $orderType!='sold_out_rate desc')
        {
        	$sql.= 'ORDER BY  '.(is_array($orderType)?implode(' ',$orderType):$orderType);
        }
        $rows = $this->db->selectLimit($sql,$limit,$offset);

        if(empty($rows)){
            return null;
        }else{  
            $nRow = array();
            
            foreach($rows as $row){
                $time_from_store = $this->get_pre_sale($fil,$row['goods_bn']);
                $num_rate =$row['purchase_num']+$row['allocation_num']+$time_from_store;
                $rate = ($row['sales_num']/$num_rate)*100;
                if($rate>0.01){
                    $rate = round($rate,2)."%";
                }else{
                    if($rate>0){
                        $rate = '0.01%';
                    }else{
                        $rate = '0.00%';
                    }
                }
                $row['purchase_num'] = $row['purchase_num'];            //期间内采购入库总和
                $row['allocation_num'] = $row['allocation_num'];        //期间内调拨入库总和
                $row['sold_out_rate'] = $rate;                          //售罄率
                $row['sales_num'] = $row['sales_num'];                  //期间内销量总和
                $row['brand_id'] = $row['brand_id'];
                //$row['spec_info'] = $row['spec_info'];
                $row['store'] = $time_from_store;                       //商品期初库存
                //$row['spec_info'] = $sale_row['spec_info'];
                //$row['brand_id'] = $sale_row['brand_id'];
                //$row['createtime'] = date('Y-m-d',$row['createtime']);
                $nRow[] = $row;

            }
        }
        //对数组按照dbschema排序(2012年1月11日 luolongjie)
        //begin
        $schema_type=$this->get_schema();							//获取dbschema的值，就是输出后csv里的顺序
        $schema_array=array_flip($schema_type['default_in_list']);	//其中的default_in_list是顺序，并进行键值对调
        foreach($nRow as $k=>$v)									//把原来的$datas数组的值循环读出来
        {
        	$nRow[$k]=array_merge($schema_array,$v);				//然后进行数组的组合
        }
        //end
        
        //因为售罄率并不是直接从字段取出的，是指销量除以期初库存,采购入库数量,调拨入库数量的和
        //所以在最后进行手动排序
        if($orderType=='sold_out_rate asc' || $orderType=='sold_out_rate desc')		//获取$orderType，就是点击后的值
        {
        	$order_by=explode(' ',$orderType);
        	$order_by_xsc=$order_by[1];		//获取desc还是asc
        	//单独取出需要排序的值，组成对应维数的数组(这里是二维)，为array_multisort作准备
            foreach($nRow as $key=>$val)
        	{
        		$sold_out_rate[$key]=$val['sold_out_rate'];
        	}
        	//判断asc，不是的话只能是desc
        	if($order_by_xsc=='asc')
        	{
        		array_multisort($sold_out_rate,SORT_ASC,$nRow);
        	}
        	else
        	{
        		array_multisort($sold_out_rate,SORT_DESC,$nRow);
        	}
        }
        //售罄率排序结束
        
        return $nRow;
    }

    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema(){
        $schema = array (
            'columns' => array(
                'goods_bn' => 
                array (
                    'type' => 'varchar(200)',
                     'label' => '商品货号',
                     'width' => 120,
                     'searchtype' => 'head',
                     'editable' => false,
                     'filtertype' => 'normal',
                     'filterdefault' => true,
                     'filtertype' => 'normal',
                     'required' => true,
                     'searchtype' => 'has',
                     'is_title' => true,
                     'order' => 1,
                ),
                'name' => 
                array (
                  'type' => 'varchar(200)',
                  'required' => true,
                  'default' => '',
                  'label' => '商品名称',
                  'is_title' => true,
                  'width' => 75,
                  'editable' => false,
                  'orderby' =>false,
                  'order' => 2,
                ),
                
                'brand_id' => 
                array (
                  'type' => 'varchar(50)',
                  'label' => '品牌',
                  'width' => 75,
                  'editable' => false,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'orderby' =>false,
                  'order' => 3,
                ),
                'sales_num' =>
                array (
                  'type' => 'number',
                  'editable' => false,
                  'label' => '销量',
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'order' => 4,
                ),
                'store' =>
                array (
                  'type' => 'number',
                  'editable' => false,
                  'label' => '期初库存',
                  'orderby' =>false,
                  'order' => 5,
                ),
                'purchase_num' =>
                array (
                  'type' => 'number',
                  'editable' => false,
                  'label' => '采购入库数量',
                  'orderby' =>false,
                  'order' => 6,
                ),
                'allocation_num' =>
                array (
                  'type' => 'number',
                  'editable' => false,
                  'label' => '调拨入库数量',
                  'orderby' =>false,
                  'order' => 7,
                ),
                'sold_out_rate' =>
                array (
                  'type' => 'varchar(20)',
                  'editable' => false,
                  'label' => '售罄率',
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'order' => 8,
                ),
                /*'spec_info' => 
                array (
                  'type' => 'longtext',
                  'label' => '规格',
                  'width' => 110,
                  'filtertype' => 'normal',
                  'editable' => false,
                  'orderby' =>false,
                ),
                
                'createtime' =>
                array (
                  'type' => 'time',
                  'label' => '所属时间',
                  'width' => 130,
                  'editable' => false,
                ),
                */
              ),
              'idColumn' => 'goods_bn',
                'in_list' => array (
                    0 => 'goods_bn',
                    1 => 'name',
                    2 => 'brand_id',
                    3 => 'sales_num',
                    4 => 'store',
                    5 => 'purchase_num',
                    6 => 'allocation_num',
                    7 => 'sold_out_rate',
                    //8 => 'spec_info',
                    //9 => 'createtime',
                ),
                'default_in_list' => array (
                    0 => 'goods_bn',
                    1 => 'name',
                    2 => 'brand_id',
                    3 => 'sales_num',
                    4 => 'store',
                    5 => 'purchase_num',
                    6 => 'allocation_num',
                    7 => 'sold_out_rate',
                    //8 => 'spec_info',
                    //9 => 'createtime',
                ),
        );
        return $schema;
    }
}