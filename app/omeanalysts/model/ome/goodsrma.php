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

class omeanalysts_mdl_ome_goodsrma extends dbeav_model{
    
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
            //$sql = 'SELECT count(*) as _count FROM sdb_omeanalysts_ome_goodsrma as g  WHERE '.$this->_filter($filter);
                $sql = 'SELECT g.goods_bn as goods_bn,g.name as name,g.brand_id as brand_id,g.spec_info as spec_info,g.createtime as createtime,b.brand_name as brand_id,sum(g.sales_num) as sales_num,
                        sum(g.store) as store,sum(g.back_change_num) as back_change_num'.
                   ' FROM sdb_omeanalysts_ome_goodsrma as g left join sdb_ome_brand as b on g.brand_id=b.brand_id WHERE '.$this->_filter($filter).' GROUP BY g.goods_bn,g.name,g.brand_id ,g.spec_info,b.brand_name ORDER BY g.back_change_num DESC';
            
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
       // $sql = 'SELECT * FROM sdb_omeanalysts_ome_goodsrma as g WHERE '.$this->_filter($filter).' ORDER BY back_change_num DESC';
       // $sql = 'SELECT g.goods_bn as goods_bn,g.name as name,g.brand_id as brand_id,g.spec_info as spec_info,sum(g.sales_num) as sales_num,
    //          sum(g.store) as store,sum(g.back_change_num) as back_change_num'.
       //     ' FROM sdb_omeanalysts_ome_goodsrma as g WHERE '.$this->_filter($filter).' GROUP BY g.goods_bn,g.name,g.brand_id ,g.spec_info ORDER BY g.back_change_num DESC';
        $sql = 'SELECT g.goods_bn as goods_bn,g.name as name,sum(g.sales_num) as sales_num,
                sum(g.back_change_num) as back_change_num'.
            ' FROM sdb_omeanalysts_ome_goodsrma as g WHERE '.$this->_filter($filter).' GROUP BY g.goods_bn,g.name ORDER BY g.back_change_num DESC';
        
        $row = $this->db->select($sql);
        return $row;
    }

    /**
     * 获取_pre_sale
     * @param mixed $filter filter
     * @param mixed $goods_bn goods_bn
     * @return mixed 返回结果
     */
    public function get_pre_sale($filter=null,$goods_bn=null){
        //销售额
        $sql = 'SELECT * as sale_amount FROM sdb_omeanalysts_ome_goodsrma as g WHERE goods_bn='.$goods_bn.'and'.$this->_filter($filter);

        $row = $this->db->select($sql);
        return $row[0];
    }

    function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        @ini_set('memory_limit','256M');//设置当前PHP的处理内存
        set_time_limit(0);//设置超时时间
        $title = array();
    //  $shopObj = app::get('ome')->model('shop');
        $brandObj = app::get('ome')->model('brand');
        foreach( $this->io_title('goodsrma') as $k => $v ){
            //$title[] = $this->charset->utf2local($v);
            $title[] = iconv("UTF-8","GBK",$v);
            //$title[] = $v;
        }    
        $data['title'] = '"'.implode('","',$title).'"';
        $limit = 1000;
        base_kvstore::instance('omeanalysts_goodsrma')->fetch('goodsrma_filter',$new_filter);
        
        $nfilter = unserialize($new_filter);
        $re = array_diff($filter,$nfilter);
        if(!empty($re)){
            $p_filter = $nfilter;
            $p_filter['_io_type'] = $filter['_io_type'];
        }else{
            $p_filter = $filter;
        }
        
        if( !$list=$this->getList('*',$p_filter,$offset*$limit,$limit) )return false;
        foreach( $list as $aFilter ){
                $memberRow = array();
                
            //  $shopInfo=$shopObj->dump(array('shop_id'=>$aFilter['shop_id']),'*');
                $brandInfo=$brandObj->dump(array('brand_id'=>$aFilter['brand_id']),'*');
                $row = array();
                $row['*:商品货号'] = $aFilter['goods_bn'];
                $row['*:商品名称'] = $aFilter['name'];
                $row['*:品牌'] = $brandInfo['name'];
                $row['*:销量'] = $aFilter['sales_num'];
                $row['*:退换货入库数量'] = $aFilter['back_change_num'];
          //      $row['*:换货入库数量'] = $aFilter['change_num'];
                $row['*:退换货率'] = $aFilter['rma_rate'];
                foreach( $row as $m => $t ){
                    $row[$m] = iconv("UTF-8","GBK",$row[$m]);
                    //$memRow[$t] = $memberRow[$t];
                    
                }
                $data['content'][] = '"'.implode('","',$row).'"';
        }
        $data['name'] = '商品销售报表'.date('YmdHis',time());
        return true;
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();
      //  if( $exportType == 2 ){
            //foreach( $data['title'] as $k => $val ){
                $output[] = $data['title']."\n".implode("\n",(array)$data['content']);
            //}
      //  }
        echo implode("\n",$output);
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
        $params = array(
            'filter' => $filter,
            'single'=> array(
                '1'=> array(
                    'method' => 'get_export_main',
                    'offset' => 0,
                    'limit' => 500,
                    'filename' => 'goodsrmaContent',
                ),
            ),
        );
        return $params;

    }

    //商品售后统计title
    /**
     * 获取_export_main_title
     * @return mixed 返回结果
     */
    public function get_export_main_title(){
        $title = array(
            '*:商品编号',
            '*:商品名称',
            '*:品牌',
            '*:销量',
            '*:当天库存',
            '*:退换货入库数量',
            '*:退换货率',
        );
        return $title;
    }

    //商品售后统计
    /**
     * 获取_export_main
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @param mixed $data 数据
     * @return mixed 返回结果
     */
    public function get_export_main($filter,$offset,$limit,&$data){
        $goodsrmaMdl = $this->app->model('ome_goodsrma');
        $brandMdl = app::get('ome')->model('brand');
        $goodsrma = $goodsrmaMdl->getList('*',$filter,$offset,$limit);
        if(!empty($goodsrma)){
            $brand=$brandMdl->dump(array('brand_id'=>$v['brand_id']),'brand_name');
            foreach($goodsrma as $v){
                $data[] = array(
                    'goods_bn' => $v['goods_bn'],
                    'name' => $v['name'],
                    'brand_name' => $brand['brand_name'],
                    'sales_num' => $v['sales_num'],
                    'store' => $v['store'],
                    'back_change_num' => $v['back_change_num'],
                    'rma_rate' => $v['rma_rate'],
                );
            }
        }
    }

    function io_title( $filter,$ioType='csv' ){
        $title = array();
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['goodsrma'] = array(
                /*
                    '*:商品编号'=>'goods_bn',
                    '*:商品名称'=>'name',
                    '*:品牌'=>'brand_id',
                    '*:销量'=>'sales_num',
                    '*:当天库存'=>'store',
                    '*:退换货入库数量'=>'back_change_num',
                    '*:退换货率'=>'rma_rate',
                */
                    '商品货号'=>'goods_bn',
                    '商品名称'=>'name',
                    '品牌'=>'brand_id',
                    '销量'=>'sales_num',
                    '退换货量'=>'back_change_num',
                    '退换货率'=>'rma_rate',

                );
                break;
        }
        $this->ioTitle['csv'][$filter] = array_keys( $this->oSchema['csv']['goodsrma'] );
        return $this->ioTitle['csv'][$filter];
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
            
        }
        if(isset($filter['id']) && $filter['id']){
            $where[] = ' g.id LIKE \''.addslashes($filter['id']).'%\'';
        }
        
        return parent::_filter($filter,'g',$baseWhere)." AND ".implode($where,' AND ');
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        $time_from = $filter['time_from'];
        $time_to = $filter['time_to'];
        $time_from = strtotime($filter['time_from']);
        if($time_from == strtotime(date("Y-m-d"))){
            $time_from = ($time_from - 86400);
        }
        //$last_time = date("Y-m-d",($time_from - 86400));
       // $fil['time_from'] = date("Y-m-d",$last_time);
       // $fil['time_to'] = date("Y-m-d",$filter['time_from']);
      /*  $sql = 'SELECT g.goods_bn as goods_bn,g.name as name,b.name as brand_id,sum(g.sales_num) as sales_num,
                sum(g.store) as store,sum(g.back_change_num) as back_change_num,g.createtime as createtime'.
            'FROM sdb_omeanalysts_ome_goodsrma as g,sdb_ome_brand as b WHERE '.$this->_filter($filter).' GROUP BY g.goods_bn,g.name,b.name ';
    */
        $sql = 'SELECT g.goods_bn as goods_bn,g.name as name,g.brand_id as brand_id,g.spec_info as spec_info,g.createtime as createtime,b.brand_name as brand_id,sum(g.sales_num) as sales_num,
                sum(g.store) as store,sum(g.back_change_num) as back_change_num'.
            ' FROM sdb_omeanalysts_ome_goodsrma as g left join sdb_ome_brand as b on g.brand_id=b.brand_id WHERE '.$this->_filter($filter).' GROUP BY g.goods_bn,g.name,g.brand_id ,g.spec_info,b.brand_name ORDER BY g.back_change_num DESC';
        if($orderType)$sql.=',g.'.(is_array($orderType)?implode($orderType,' '):$orderType);
        //  $sql .= ' LIMIT '. $offset*$limit.','.$limit.' '; 
        $rows = $this->db->selectLimit($sql,$limit,$offset);
        $nRow = array();
        base_kvstore::instance('omeanalysts_goodsrma')->store('goodsrma_filter',serialize($filter));
        foreach($rows as $row){
        /*  $sale_row = $this->db->select('SELECT g.goods_bn as goods_bn,g.name as name,b.name as brand_id,g.sales_num as sales_num,
                    g.store as store,g.back_num as back_num,g.change_num as change_num,g.createtime as createtime'.
                'FROM sdb_omeanalysts_ome_goodsrma as g WHERE g.goods_bn='.$row['goods_bn'].'AND'.$this->_filter($fil));*/
            if($row['sales_num']==0 || $row['sales_num']==null){
                $rate = 'N/A';
            }else{
                $row['rma_rate'] = $row['back_change_num']/$row['sales_num'];
                $rate = ($row['rma_rate'])*100;
                if($rate>0.01){
                    $rate = round($rate,2)."%";
                }else{
                    if($rate>0){
                        $rate = '0.01%';
                    }else{
                        $rate = '0.00%';
                    }
                }
            }
            
            $row['rma_rate'] = $rate;
            $nRow[] = $row;
            
        }

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
                  'width' => 150,
                  'searchtype' => 'has',
                  'editable' => false,
                  'filterdefault' => true,
                  'filtertype' => 'normal',
                ),
                'name' => 
                array (
                  'type' => 'varchar(200)',
                  'label' => '商品名称',
                  'width' => 100,
                  'editable' => false,
                  'orderby' =>false,
                ),

                'brand_id' => 
                array (
                  'type' => 'varchar(200)',
                  'label' => '品牌',
                  'width' => 75,
                  'editable' => false,
                  'orderby' =>false,
                ),
                'sales_num' =>
                array (
                  'type' => 'number',
                  'editable' => false,
                  'label' => '销量',
                  'width' => 100,
                  'default' => 0,
                ),
                'back_change_num' =>
                array (
                  'type' => 'number',
                  'editable' => false,
                  'label' => '退换货量',
                  'width' => 100,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'orderby' =>false,
                  'default' => 0,
                ),
                'rma_rate' =>
                array (
                  'type' => 'number',
                  'editable' => false,
                  'label' => '退换货率',
                  'width' => 75,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'orderby' =>false,
                  'default' => 0,
                ),
                /*'spec_info' => 
                array (
                  'type' => 'longtext',
                  'label' => '规格',
                  'width' => 100,
                  'editable' => false,
                  'width' => 75,
                  'orderby' =>false,
                ),
              'createtime' =>
                array (
                  'type' => 'time',
                  'label' => '所属时间',
                  'width' => 80,
                  'editable' => false,
                ),*/
              ),
              'idColumn' => 'goods_bn',
                'in_list' => array (
                    0 => 'goods_bn',
                    1 => 'name',
                    2 => 'brand_id',
                    3 => 'sales_num',
                    4 => 'back_change_num',
                    5 => 'rma_rate',
                    //6 => 'spec_info',
                  //  7 => 'createtime',

                ),
                'default_in_list' => array (
                     0 => 'goods_bn',
                     1 => 'name',
                     2 => 'brand_id',
                     3 => 'sales_num',
                     4 => 'back_change_num',
                     5 => 'rma_rate',
                     //6 => 'spec_info',
                  //   7 => 'createtime',
                    
                ),
        );
        return $schema;
    }
    
}