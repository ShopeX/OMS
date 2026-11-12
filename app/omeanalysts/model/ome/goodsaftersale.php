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
    class omeanalysts_mdl_ome_goodsaftersale extends dbeav_model{
    
        public function count($filter=null){
            return $this->_get_count($filter);
        }
    
        public function _get_count($filter=null){
            $sql = "select count(*) as _count 
                    from sdb_ome_return_product as rp 
                    inner join sdb_ome_return_product_items as rpi on rpi.return_id = rp.return_id where ".$this->_filter($filter);
    
            $row = $this->db->select($sql);
            return intval($row[0]['_count']);
        }
        public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
        {
            $basicMaterialObj = app::get('material')->model('basic_material');
            
            $datas = array();
            $sql = "select rpi.product_id,rp.return_bn as return_bn,
                    rp.add_time as add_time,
                    rpi.bn as product_bn,
                    rpi.num as nums 
                    from sdb_ome_return_product as rp 
                    inner join sdb_ome_return_product_items as rpi on rpi.return_id = rp.return_id where ".$this->_filter($filter)." order by rp.add_time desc";

//          if($orderType)
//              $sql .= (is_array($orderType) ? implode($orderType,',') : $orderType);
            base_kvstore::instance('omeanalysts_goodsaftersale')->store('goodsaftersale_filter',serialize($filter));
            //echo $sql;
            $rows = $this->db->selectLimit($sql,$limit,$offset);

            if($rows){
                foreach($rows as $row){
                    $data['return_bn'] = $row['return_bn'];
                    $data['add_time'] = $row['add_time'];
                    $data['product_bn'] = $row['product_bn'];
                    $data['nums'] = $row['nums'];
                    
                    $row_product = $basicMaterialObj->dump(array('bm_id'=>$row['product_id']), '*');
                    
                    $row_product['goods_id']    = $row_product['bm_id'];
                    
                    /*基础物料_无goods
                    $sql2 = "select brand_id,name from sdb_ome_goods where goods_id = '".$row_product['goods_id']."'";
                    $row_goods = $this->db->selectrow($sql2);
                    $data['goods_name'] = $row_goods['name'];
                    
                    $sql3 = "select brand_name from sdb_ome_brand where brand_id = '".$row_goods['brand_id']."'";
                    $row_brand = $this->db->selectrow($sql3);
                    $data['brand_name'] = $row_brand['brand_name'];
                    */
                    
                    $datas[] = $data;
                }
            }
            
            //对数组按照dbschema排序(2012年1月11日 luolongjie)
         	//begin
         	$schema_type=$this->get_schema();							//获取dbschema的值，就是输出后csv里的顺序
         	$schema_array=array_flip($schema_type['default_in_list']);	//其中的default_in_list是顺序，并进行键值对调
         	foreach($datas as $k=>$v)									//把原来的$datas数组的值循环读出来
         	{
         		$datas[$k]=array_merge($schema_array,$v);				//然后进行数组的组合
        	 }
        	 //end
            
            return $datas;
            
        }
        
        public function get_schema(){
            //售后申请单号，售后申请时间,名称，品牌，货号，规格，数量
            
//          快速搜索：货号，售后申请单号。
//          3.高级筛选：品牌，货号，数量（区间），售后申请单号，售后申请时间。
            
            $schema = array (
                'columns' => array (
                    'return_bn' => array (
                        'type' => 'varchar(32)',
                        'required' => true,
                        'label' => '售后申请单号',
                        'comment' => '售后申请单号',
                        'editable' => false,
                        'orderby'=>false,
                        'order'=>'1',
                        'width' =>200,
                        'searchtype' => 'has',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'is_title' => true,
                    ),
                    'add_time' => array (
                        'type' => 'time',
                        'label' => '售后申请时间',
                        'comment' => '申请时间',
                        'filtertype' => 'normal',
                        'orderby'=>false,
                        'order'=>'2',
                        'filterdefault' => true,
                        'editable' => false,
                        'width' =>75,
                    ),
                   
                    'goods_name' => array (
                        'type' => 'varchar(200)',
                        'label' => '商品名称',
                        'comment' => '商品名称',
                        'orderby'=>false,
                        'order'=>'3',
                        'editable' => false,
                        'width' =>110,
                    ),
                    'brand_name' => array (
                        'type' => 'table:brand@ome',
                        'label' => '品牌',
                        'comment' => '品牌名称',
                        'orderby'=>false,
                        'order'=>'4',
                        //'filtertype' => 'yes',
                        //'filterdefault' => true,
                        'width' =>130,
                        'editable' => false,
                    ),
                    'product_bn' => array (
                        'type' => 'varchar(255)',
                        'editable' => false,
                        'label' => '货号',
                        'comment' => '商品货号',
                        'orderby'=>false,
                        'order'=>'5',
                        'width' =>85,
                        'searchtype' => 'has',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                    ),
                    /*'spec_info' => array (
                        'type' => 'varchar(200)',
                        'label' => '规格',
                        'comment' => '货品规格',
                        'orderby'=>false,
                        'order'=>'6',
                        'editable' => false,
                        'width' =>110,
                    ),*/
                    'nums' => array (
                        'type' => 'number',
                        'label' => '数量',
                        'comment' => '数量',
                        'editable' => false,
                        'filtertype' => 'normal',
                        'orderby'=>false,
                        'order'=>'7',
                        'filterdefault' => true,
                        'width' =>130,
                    ),
                ),
                'idColumn' => 'return_bn',
                'in_list' => array (
                    0 => 'return_bn',
                    1 => 'add_time',
                    2 => 'goods_name',
                    3 => 'brand_name',
                    4 => 'product_bn',
                    //5 => 'spec_info',
                    6 => 'nums',        
                ),
                'default_in_list' => array (
                    0 => 'return_bn',
                    1 => 'add_time',
                    2 => 'goods_name',
                    3 => 'brand_name',
                    4 => 'product_bn',
                    //5 => 'spec_info',
                    6 => 'nums',   
                ),
            );
            return $schema;
        }
        
        public function _filter($filter,$tableAlias=null,$baseWhere=null){
            $where = array(1);

            if(isset($filter['time_from']) && $filter['time_from']){
                $where[] = ' rp.add_time >='.strtotime($filter['time_from']);
            }
            if(isset($filter['time_to']) && $filter['time_to']){
                $where[] = ' rp.add_time <'.(strtotime($filter['time_to'])+86400);
            }
            if(isset($filter['type_id']) && $filter['type_id']){
                $where[] = ' rp.shop_id =\''.addslashes($filter['type_id']).'\'';
            }
            if(isset($filter['return_bn']) && $filter['return_bn']){
                $where[] = " rp.return_bn like '%".$filter['return_bn']."%'";
            }
            if(isset($filter['product_bn']) && $filter['product_bn']){
                $where[] = " rpi.bn like '%".$filter['product_bn']."%'";
            }
            
            if(isset($filter['_nums_search']) && is_numeric($filter['nums'])){
                switch ($filter['_nums_search']){
                    case 'than': $p = ' rpi.num >'.$filter['nums'];break;
                    case 'lthan': $p = ' rpi.num <'.$filter['nums'];break;
                    case 'nequal': $p = ' rpi.num ='.$filter['nums'];break;
                    case 'sthan': $p = ' rpi.num <='.$filter['nums'];break;
                    case 'bthan': $p = ' rpi.num >='.$filter['nums'];break;
                    case 'between': 
                        if(is_numeric($filter['nums_from']) && is_numeric($filter['nums_to'])){
                            $p = 'rpi.num >='.$filter['nums_from'].' and rpi.num < '.$filter['nums_to'];
                        }else{
                            $p = '';
                        }
                        break;  
                }
                if($p)
                    $where[] = $p;
            }
            if(isset($filter['_add_time_search']) && isset($filter['add_time'])){
                switch ($filter['_add_time_search']){
                    case 'than' : 
                        $t = " rp.add_time > ".strtotime($filter['add_time'].' '.$filter['_DTIME_']['H']['add_time'].':'.$filter['_DTIME_']['M']['add_time']);
                        break;
                    case 'lthan' :
                        $t = " rp.add_time < ".strtotime($filter['add_time'].' '.$filter['_DTIME_']['H']['add_time'].':'.$filter['_DTIME_']['M']['add_time']);
                        break;
                    case 'nequal' :
                        $t = " rp.add_time >= ".strtotime($filter['add_time'].' '.$filter['_DTIME_']['H']['add_time'].':'.$filter['_DTIME_']['M']['add_time'])." and 
                               rp.add_time < ".strtotime($filter['add_time'].' '.$filter['_DTIME_']['H']['add_time'].':'.$filter['_DTIME_']['M']['add_time'])+60;
                        break;
                    case 'between' :
                        $t = " rp.add_time >= ".strtotime($filter['add_time_from'].' '.$filter['_DTIME_']['H']['add_time_from'].':'.$filter['_DTIME_']['M']['add_time_from'])." and 
                               rp.add_time < ".strtotime($filter['add_time_to'].' '.$filter['_DTIME_']['H']['add_time_to'].':'.$filter['_DTIME_']['M']['add_time_to']);
                        break;
                }
                $where[] = $t;
            }
            return " ".implode($where,' AND ');
        }

    //配置信息
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
                    'filename' => 'goodsaftersaleContent',
                ),
            ),
        );
        return $params;

    }

    //商品销量统计title
    public function get_export_main_title(){
        $title = array(
            'col:售后申请单号',
            'col:售后申请时间',
            'col:商品名称',
            'col:品牌',
            'col:货号',
            //'col:规格',
            'col:退货数量',
        );
        return $title;
    }

    //商品销量统计
    public function get_export_main($filter,$offset,$limit,&$data){
        $goodsaftersaleMdl = $this->app->model('ome_goodsaftersale');
        //$brandMdl = app::get('ome')->model('brand');
        $goodsaftersale = $goodsaftersaleMdl->getList('*',$filter,$offset,$limit);
        if(!empty($goodsaftersale)){
            //$brand=$brandMdl->dump(array('brand_id'=>$v['brand_id']),'brand_name');
            foreach($goodsaftersale as $v){
                $data[] = array(
                    'return_bn' => $v['return_bn'],
                    'add_time' => date('Y-m-d H:i:s',$v['add_time']),
                    'goods_name' => $v['goods_name'],
                    'brand_name' => $v['brand_name'],
                    'product_bn' => $v['product_bn'],
                    //'spec_info' => $v['spec_info'] ? $v['spec_info'] : '-',
                    'nums' => $v['nums'] ? $v['nums'] : 0,
                );
            }
        }
    }

/*      function io_title( $ioType='csv' ){
        
            switch( $ioType ){
                case 'csv':
                    $this->oSchema['csv'] = array(
                        'col:售后申请单号' => 'return_bn',
                        'col:售后申请时间' => 'add_time',
                        'col:商品名称'=>'goods_name',
                        'col:品牌' => 'brand_name',
                        'col:货号' => 'product_bn',
                        'col:规格' => 'spec_info',
                        'col:退货数量' => 'num',
                    );
                    break;
            }
            $this->ioTitle[$ioType] = array_keys( $this->oSchema[$ioType] );
            return $this->ioTitle[$ioType];
        }
        
        function export_csv($data){
            $output = array();
            $output[] = $data['title']."\n".implode("\n",(array)$data['content']);
            echo implode("\n",$output);
        }
    
        function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
            @ini_set('memory_limit','64M');
            if( !$data['title']){
                $title = array();
                foreach( $this->io_title('csv') as $k => $v ){
                    $title[] = iconv("UTF-8","GBK",$v);
                }
                $data['title'] = '"'.implode('","',$title).'"';
            }
            $filter_value = '';
            base_kvstore::instance('omeanalysts_goodsaftersale')->fetch('goodsaftersale_filter',$filter_value);
            
            $filter_value = unserialize($filter_value);
            //print_r($filter_value);die;
            $filter_value = $filter_value ? $filter_value : $filter;
            $limit = 50;
            
            if( !$list=$this->getlist('*',$filter_value,$offset*$limit,$limit) )
                return false;
            
            foreach( $list as $aFilter ){
                $detail = array();
                $detail['col:售后申请单号'] = $aFilter['return_bn'];
                $detail['col:售后申请时间'] = date('Y-m-d H:i:s',$aFilter['add_time']);
                $detail['col:商品名称'] = $aFilter['goods_name'];
                $detail['col:品牌'] = $aFilter['brand_name'];
                $detail['col:货号'] = $aFilter['product_bn'];
                $detail['col:规格'] = $aFilter['spec_info'];
                $detail['col:退货数量'] =  $aFilter['nums'];
                foreach( $detail as $m => $t ){
                    $detail[$m] = iconv("UTF-8","GBK",$detail[$m]);
                }
                $data['content'][] = '"'.implode('","',$detail).'"';      
            }
            $data['name'] = '商品售后汇总'.date("YmdHis");
            return true;
        }*/
    }
