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
class omeanalysts_mdl_ome_goodsale extends dbeav_model{

    var $has_export_cnf = true;

    var $export_name = '商品销售情况';

    public function searchOptions(){
        return array(
            'order_bn' => '订单号',
            'product_bn' => '货号',
            'goods_name'=> '商品名称',
        );
    }

    public function count($filter=null){
        return $this->_get_count($filter);
    }

    public function get_goodsale($filter=null){
        $sql = "select sum(saleitem.nums) as salenums,sum(saleitem.sales_amount) as sale_amount from sdb_ome_sales_items as saleitem left join
                sdb_ome_sales as sales on saleitem.sale_id = sales.sale_id where 1 " .$this->_filter($filter);

        $row = $this->db->select($sql);

        return $row[0];
    }
    public function _get_count($filter=null){
        $sql = "select count(*) as _count 
                from sdb_ome_sales_items as saleitem 
                left join sdb_ome_sales as sales on saleitem.sale_id = sales.sale_id 
                left join sdb_material_basic_material p ON saleitem.product_id = p.bm_id 
                where 1 " .$this->_filter($filter);

        $row = $this->db->select($sql);
        return intval($row[0]['_count']);
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        
         $datas = array();
         $sql = "select saleitem.name,saleitem.spec_name as goods_specinfo,sales.branch_id,sales.shop_id,sales.order_id, 
                 sales.sale_id as sale_id, sales.iostock_bn,saleitem.bn as product_bn,saleitem.nums as buycount, 
                 saleitem.sales_amount as sale_amount,sales.sale_time as createtime 
                from sdb_ome_sales_items as saleitem 
                 left join sdb_ome_sales as sales on saleitem.sale_id = sales.sale_id 
                 left join sdb_material_basic_material p ON saleitem.product_id = p.bm_id 
                where 1 " .$this->_filter($filter);

         $rows = $this->db->selectLimit($sql,$limit,$offset);
         if($rows){
            foreach($rows as $row){
                $data['product_bn'] = $row['product_bn'];
                $data['branch_id'] = $row['branch_id'];
                $data['goods_specinfo'] = $row['goods_specinfo'];
                $data['buycount'] = $row['buycount'];
                $data['shop_id'] = $row['shop_id'];
                $data['createtime'] = $row['createtime'];
                
                $row_product = $basicMaterialObj->dump(array('material_bn'=>$row['product_bn']), '*');
                
                $row_product['goods_id']    = $row_product['bm_id'];
                
                if($row_product && $row_product['goods_id']>0){
                    $data['goods_name'] = $row_product['material_name'];
                    $sql2 = "select brand.brand_name from sdb_ome_brand as brand left join sdb_ome_goods as goods on goods.brand_id = brand.brand_id where goods.goods_id='".$row_product['goods_id']."'";
                    $row_brand = $this->db->selectrow($sql2);
                    $data['brand_name'] = $row_brand['brand_name'];

                    $sql4 = "select gtype.name from sdb_ome_goods_type as gtype left join sdb_ome_goods as goods on goods.type_id = gtype.type_id where goods.goods_id='".$row_product['goods_id']."'";
                    $row_brand = $this->db->selectrow($sql4);
                    $data['type_name'] = $row_brand['name'];
                    $data['obj_type'] = '普通商品';
                }else{
                    foreach(kernel::servicelist('ome.product') as $name=>$object){
                        if(method_exists($object, 'getProductByBn')){
                            $product_info = $object->getProductByBn($row['product_bn']);
                            if(!empty($product_info)){
                                $data['goods_name'] = $product_info['name'];#捆绑商品，使用后台名称
                                $data['brand_name'] = '-';
                                $data['type_name'] = '捆绑商品';
                                $data['obj_type'] = '捆绑商品';
                            }
                        }
                    }
                }

                $sql3 = "select order_bn from sdb_ome_orders where order_id = ".$row['order_id'];
                $row_order = $this->db->selectrow($sql3);
                $data['order_bn'] = $row_order['order_bn'];

                $data['sale_amount'] = "￥".$row['sale_amount'];
                $datas[] = $data;
            }
         }

         //对数组排序
         if($orderType){
             foreach($datas as $k=>$data){
                $product_bn[$k] = $data['product_bn'];
                $buycount[$k] = $data['buycount'];
                $createtime[$k] = $data['createtime'];
                $sale_amount[$k] = $data['sale_amount'];
                $goods_name[$k] = $data['goods_name'];
                $brand_name[$k] = $data['brand_name'];
                $order_bn[$k] = $data['order_bn'];
                $type_name[$k] = $data['type_name'];
                $shop_id[$k] = $data['shop_id'];
             }
            if(is_string($orderType)){
                $arr = explode(" ", $orderType);
                if($arr[0] != 'createtime'){
                    if(strtolower($arr[1]) == 'desc'){
                        array_multisort(${$arr[0]},SORT_DESC,$createtime,SORT_DESC,SORT_NUMERIC,$datas);
                    }
                    else {
                        array_multisort(${$arr[0]},SORT_ASC,$createtime,SORT_DESC,SORT_NUMERIC,$datas);
                    }
                }else{
                    if(strtolower($arr[1]) == 'desc'){
                        array_multisort($createtime,SORT_DESC,$datas);
                    }
                    else{
                        array_multisort($createtime,SORT_ASC,$datas);
                    }
                }
            }
         }

         return $datas;
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        $where = array(1);

        $config = app::get('eccommon')->getConf('analysis_config');
        $order_status = $config['filter']['order_status'];

        if(isset($order_status) && $order_status){
            switch($order_status){
                case 'createorder':
                    $time_filter = 'order_create_time';
                break;
                case 'confirmed':
                    $time_filter = 'order_check_time';
                break;
                case 'pay':
                    $time_filter = 'paytime';
                break;
                case 'ship':
                    $time_filter = 'ship_time';
                break;
            }
        }else{
            $config['filter']['order_status'] = 'ship';
            app::get('eccommon')->setConf('analysis_config', $config);

            $time_filter = 'ship_time';
        }

        if(isset($filter['_createtime_search']) && isset($filter['createtime'])){
            switch ($filter['_createtime_search']){
                case 'than' :
                    $t = " sales.".$time_filter." > ".strtotime($filter['createtime'].' '.$filter['_DTIME_']['H']['createtime'].':'.$filter['_DTIME_']['M']['createtime']);
                    break;
                case 'lthan' :
                    $t = " sales.".$time_filter." < ".strtotime($filter['createtime'].' '.$filter['_DTIME_']['H']['createtime'].':'.$filter['_DTIME_']['M']['createtime']);
                    break;
                case 'nequal' :
                    $t = " sales.".$time_filter." >= ".strtotime($filter['createtime'].' '.$filter['_DTIME_']['H']['createtime'].':'.$filter['_DTIME_']['M']['createtime'])." and
                           sales.".$time_filter." < ".strtotime($filter['createtime'].' '.$filter['_DTIME_']['H']['createtime'].':'.$filter['_DTIME_']['M']['createtime'])+60;
                    break;
                case 'between' :
                    $t = " sales.".$time_filter." >= ".strtotime($filter['createtime_from'].' '.$filter['_DTIME_']['H']['createtime_from'].':'.$filter['_DTIME_']['M']['createtime_from'])." and
                           sales.".$time_filter." < ".strtotime($filter['createtime_to'].' '.$filter['_DTIME_']['H']['createtime_to'].':'.$filter['_DTIME_']['M']['createtime_to']);
                    break;
            }

            $where[] = $t;
        }else{
            if(isset($filter['time_from']) && $filter['time_from']){
                $where[] = ' sales.'.$time_filter.' >='.strtotime($filter['time_from']);
                unset($filter['time_from']);
            }
            if(isset($filter['time_to']) && $filter['time_to']){
                $filter['time_to'] = $filter['time_to'].' 23:59:59';
                $where[] = ' sales.'.$time_filter.' <='.strtotime($filter['time_to']);
                unset($filter['time_to']);
            }
        }

        if(isset($filter['shop_id']) && $filter['shop_id']){
            $where[] = ' sales.shop_id =\''.addslashes($filter['shop_id']).'\'';
            unset($filter['shop_id']);
        }

        if(isset($filter['own_branches']) && $filter['own_branches']){
            $where[]= ' sales.branch_id in ('.implode(',',$filter['own_branches']).')';
        }
        unset($filter['own_branches']);

        if(isset($filter['branch_id']) && $filter['branch_id']){
            $where[] = ' sales.branch_id = '.addslashes($filter['branch_id']);
        }
        unset($filter['branch_id']);

        if(isset($filter['product_bn']) && $filter['product_bn']){
            $where[] = " saleitem.bn like '".$filter['product_bn']."%'";
            unset($filter['product_bn']);
        }

        if(isset($filter['goods_name']) && $filter['goods_name']){
            $where[] = " p.material_name like '".$filter['goods_name']."%'";
            unset($filter['goods_name']);
        }

        if(isset($filter['order_bn']) && $filter['order_bn']){
            $orderObj = app::get('ome')->model("orders");
            $rows = $orderObj->getList('order_id',array('order_bn|has'=>$filter['order_bn']));
            if(count($rows)){
                foreach($rows as $row){
                    $orderId[] = $row['order_id'];
                }

                $orderids = implode(",",$orderId);

                $where[] = ' sales.order_id IN ('.implode(',', $orderId).')';
            }else{
                $where[] = " 1=0 ";
            }
            unset($filter['order_bn']);
        }

        /**基础物料无品牌_已弃用
        if(isset($filter['brand_id']) && $filter['brand_id']){
            $brand_id = $filter['brand_id'];
            $sql = "select distinct saleitem.bn from sdb_ome_sales_items as saleitem
                    left join sdb_ome_products as products on saleitem.bn=products.bn
                    left join sdb_ome_goods as goods on products.goods_id=goods.goods_id
                    where goods.brand_id = ".$brand_id;
            $query = $this->db->select($sql);
            if($query){
                $sale_bns = array();
                foreach($query as $qu){
                    $sale_bns[] = "'".$qu['bn']."'";
                }
                $where[] = " saleitem.bn IN (".implode(',',$sale_bns).")";
            }else{
                $where[] = " 1=0 ";
            }
            unset($filter['brand_id']);
        }
        */

        /**基础物料_已弃用
        if(isset($filter['goods_type_id']) && $filter['goods_type_id']){
            $type_id = $filter['goods_type_id'];
            $sql = "select distinct saleitem.bn from sdb_ome_sales_items as saleitem
                    left join sdb_ome_products as products on saleitem.bn=products.bn
                    left join sdb_ome_goods as goods on products.goods_id=goods.goods_id
                    where goods.type_id = ".$type_id;
            $query = $this->db->select($sql);
            if($query){
                $sale_bns = array();
                foreach($query as $qu){
                    $sale_bns[] = "'".$qu['bn']."'";
                }
                $where[] = " saleitem.bn IN (".implode(',',$sale_bns).")";
            }else{
                $where[] = " 1=0 ";
            }
            unset($filter['goods_type_id']);
        }
        */

        #类型
        if($filter['obj_type'] == 'normal'){
            $where[] = " saleitem.product_id <> 0";
        }elseif($filter['obj_type'] == 'pkg'){
            $where[] = " saleitem.product_id=0";
        }else{
            unset($filter['obj_type']);
        }

        if(isset($filter['_buycount_search']) && is_numeric($filter['buycount'])){
            switch ($filter['_buycount_search']){
                case 'than': $p = ' saleitem.nums >'.$filter['buycount'];break;
                case 'lthan': $p = ' saleitem.nums <'.$filter['buycount'];break;
                case 'nequal': $p = ' saleitem.nums ='.$filter['buycount'];break;
                case 'sthan': $p = ' saleitem.nums <='.$filter['buycount'];break;
                case 'bthan': $p = ' saleitem.nums >='.$filter['buycount'];break;
                case 'between':
                    if(is_numeric($filter['buycount_from']) && is_numeric($filter['buycount_to'])){
                        $p = 'saleitem.nums >='.$filter['buycount_from'].' and saleitem.nums < '.$filter['buycount_to'];
                    }else{
                        $p = '';
                    }
                    break;
            }
            if($p)
                $where[] = $p;
        }

        if(isset($filter['_sale_amount_search']) && is_numeric($filter['sale_amount'])){
            switch ($filter['_sale_amount_search']){
                case 'than': $p = ' saleitem.sales_amount >'.$filter['sale_amount'];break;
                case 'lthan': $p = ' saleitem.sales_amount <'.$filter['sale_amount'];break;
                case 'nequal': $p = ' saleitem.sales_amount ='.$filter['sale_amount'];break;
                case 'sthan': $p = ' saleitem.sales_amount <='.$filter['sale_amount'];break;
                case 'bthan': $p = ' saleitem.sales_amount >='.$filter['sale_amount'];break;
                case 'between':
                    if(is_numeric($filter['sale_amount_from']) && is_numeric($filter['sale_amount_to'])){
                        $p = 'saleitem.sales_amount >='.$filter['sale_amount_from'].' and saleitem.sales_amount < '.$filter['sale_amount_to'];
                    }else{
                        $p = '';
                    }
                    break;
            }
            if($p)
                $where[] = $p;
        }

        return " AND ".implode(' AND ', $where);
    }

    public function exportName(&$data){
        $data['name'] = $_POST['time_from'].'到'.$_POST['time_to'].'商品销售情况';
    }

    public function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        @ini_set('memory_limit','1024M');
        if( !$data['title'] ){
            $title = array();
            foreach( $this->io_title() as $k => $v ){
                $title[] = $v;
            }

            $data['title']['goodsale'] = mb_convert_encoding('"'.implode('","',$title).'"', 'GBK', 'UTF-8');

        }

        $limit = 100;
        $oBranch = app::get('ome')->model('branch');
        // 所有的仓库
        $rs = $oBranch->getList('branch_id,branch_bn,name');
        foreach($rs as $v) {
            $branchs[$v['branch_id']] = $v;
        }

        $oShop = app::get('ome')->model('shop');
        // 所有的仓库
        $rs = $oShop->getList('shop_id,name');
        foreach($rs as $v) {
            $shops[$v['shop_id']] = $v;
        }

        if(!$goodssale = $this->getList('*',$filter,$offset*$limit,$limit)) return false;

        foreach ($goodssale as $aFilter) {

            $goodsaleRow['*:订单号'] = "=\"\"".$aFilter['order_bn']."\"\"";
            $goodsaleRow['*:仓库名称'] = $branchs[$aFilter['branch_id']]['name'];
            $goodsaleRow['*:商品名称'] = $aFilter['goods_name'];
            $goodsaleRow['*:商品规格'] = $aFilter['goods_specinfo'];
            $goodsaleRow['*:货号'] = $aFilter['product_bn']."\t";;
            $goodsaleRow['*:品牌'] = $aFilter['brand_name'];
            $goodsaleRow['*:数量'] = $aFilter['buycount'];
            $goodsaleRow['*:销售金额'] = $aFilter['sale_amount'];
            $goodsaleRow['*:销售单时间'] = date('Y-m-d H:i:s',$aFilter['createtime']);
            $goodsaleRow['*:商品类型'] = $aFilter['type_name'];
            $goodsaleRow['*:店铺名称'] = $shops[$aFilter['shop_id']]['name'];

            $data['content']['goodsale'][] = mb_convert_encoding('"'.implode('","',$goodsaleRow).'"', 'GBK', 'UTF-8');

        }

        return true;
    }
    //商品销售汇总title
    public function io_title($filter=null,$ioType='csv'){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '*:订单号'=>'order_bn',
                    '*:仓库名称'=>'branch_id',
                    '*:商品名称'=>'goods_name',
                    '*:商品规格'=>'goods_specinfo',
                    '*:货号'=>'product_bn',
                    '*:品牌'=>'brand_name',
                    '*:数量'=>'buycount',
                    '*:销售金额'=>'sale_amount',
                    '*:销售单时间'=>'createtime',
                    '*:商品类型'=>'type_name',
                    '*:店铺名称'=>'shop_id',
                );
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType][$filter];
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();
        $output[] = $data['title']['goodsale']."\n".implode("\n",(array)$data['content']['goodsale']);

        echo implode("\n",$output);

    }

    public function get_schema(){
        //1.表格字段：订单号,名称,货号,品牌,规格，数量，下单时间
        //高级筛选：品牌，货号，数量（区间），订单号，下单时间。
        $schema = array (
            'columns' => array (
                'order_bn' => array (
                    'type' => 'varchar(50)',
                    'label' => '订单号',
                    'comment' => '订单号',
                    'order' =>'1',
                    'editable' => false,
                    'orderby' =>true,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'width' =>130,
                ),
                'branch_id' =>
                array (
                  'type' => 'table:branch@ome',
                  'label' => '仓库名称',
                  'default_in_list'=>true,
                  'in_list'=>true,
                  'order' =>'2',
                  //'filtertype' => 'normal',
                  //'filterdefault' => true,
                ),
                'goods_name' => array (
                    'type' => 'varchar(50)',
                    'label' => '商品名称',
                    'comment' => '商品名称',
                    'width' => 310,
                    'orderby' =>true,
                    'editable' => false,
                    'order' =>'3',
                ),
                'goods_specinfo' => array (
                    'type' => 'table:goods_type@ome',
                    'pkey' => true,
                    'label' => '商品规格',
                    'width' => 110,
                    'orderby' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>6,
                    'realtype' => 'varchar(200)',
                ),
                'product_bn' => array (
                    'type' => 'varchar(100)',
                    'label' => '货号',
                    'comment' => '商品货号',
                    'orderby' =>true,
                    'order' =>'4',
                    'editable' => false,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'width' =>75,
                ),
                'brand_name' => array (
                    'type' => 'table:brand@ome',
                    'required' => true,
                    'label' => '品牌',
                    'orderby' =>true,
                    'comment' => '品牌名称',
                    'editable' => false,
                    'order' =>'5',
                    'is_title' => true,
                    'width' =>100,
                ),
                'type_name' => array (
                    'type' => 'table:goods_type@ome',
                    'pkey' => true,
                    'label' => '商品类型',
                    'width' => 110,
                    'orderby' =>true,
                    'in_list' => true,
                    'order' =>'6',
                    'default_in_list' => true,
                    'realtype' => 'varchar(200)',
                ),
                'buycount' => array (
                    'type' => 'number',
                    'editable' => false,
                    'label' => '数量',
                    'comment' => '购买数量',
                    'orderby' =>true,
                    'order' =>'7',
                    'width' =>85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'sale_amount' => array (
                    'type' => 'number',
                    'editable' => false,
                    'label' => '销售金额',
                    'orderby' =>true,
                    'order' =>'8',
                    'width' =>85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'createtime' => array (
                    'type' => 'time',
                    'label' => '销售单时间',
                    'comment' => '',
                    'order' =>'9',
                    'orderby' =>true,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'width' =>130,
                ),
                'shop_id' =>
                array (
                  'type' => 'table:shop@ome',
                  'label' => '店铺名称',
                  'default_in_list'=>true,
                  'in_list'=>true,
                  //'filtertype' => 'normal',
                  //'filterdefault' => true,
                  'order' =>'10',
                ),
                'obj_type' => array (
                    'type' => '',
                    'label' => '类型',
                    'width' => 110,
                    'orderby' =>true,
                    'in_list' => true,
                    'order' =>'10',
                    'default_in_list' => true,
                    'realtype' => 'varchar(200)',
                ),
            ),
            'idColumn' => 'order_bn',
            'in_list' => array (
                0 => 'order_bn',
                1 => 'branch_id',
                2 => 'goods_name',
                3 => 'goods_specinfo',
                4 => 'product_bn',
                5 => 'brand_name',
                6 => 'type_name',
                7 => 'buycount',
                8 => 'sale_amount',
                9 => 'createtime',
                10=> 'shop_id',
                11=> 'obj_type'
            ),
            'default_in_list' => array (
                0 => 'order_bn',
                1 => 'branch_id',
                2 => 'goods_name',
                3 => 'goods_specinfo',
                4 => 'product_bn',
                5 => 'brand_name',
                6 => 'type_name',
                7 => 'buycount',
                8 => 'sale_amount',
                9 => 'createtime',
                10=> 'shop_id',
                11=> 'obj_type'
            ),
        );
        return $schema;
    }

    /**
     * 获得日志类型(non-PHPdoc)
     * @see dbeav_model::getLogType()
     */
    public function getLogType($logParams) {
        $type = $logParams['type'];
        $logType = 'none';
        if ($type == 'export') {
            $logType = $this->exportLogType($logParams);
        }
        elseif ($type == 'import') {
            $logType = $this->importLogType($logParams);
        }
        return $logType;
    }

    /**
     * 导出日志类型
     * @param Array $logParams 日志参数
     */
    public function exportLogType($logParams) {
        $params = $logParams['params'];
        $type = 'report';
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_goodsale') {
            $type .= '_salesReport_goodsSales';
        }
        $type .= '_export';
        return $type;
    }

    /**
     * 导入操作日志类型
     * @param Array $logParams 日志参数
     */
    public function importLogType($logParams) {
        $params = $logParams['params'];
        $type = 'report';
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_goodsale') {
            $type .= '_salesReport_goodsSales';
        }
        $type .= '_import';
        return $type;
    }

    //根据过滤条件获取导出发货单的主键数据数组
    public function getPrimaryIdsByCustom($filter, $op_id){
        $rows = array();
        $sql = "select saleitem.item_id
                from sdb_ome_sales_items as saleitem 
                left join sdb_ome_sales as sales on saleitem.sale_id = sales.sale_id 
                left join sdb_material_basic_material p ON saleitem.product_id = p.bm_id 
                where 1 " .$this->_filter($filter);

        $rows = $this->db->select($sql);

        $ids = array();
        foreach ($rows as $k => $row){
            $ids[] = $row['item_id'];
        }

        return $ids;
    }

    //根据主键id获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id){
        
        #基础物料
        $basicMaterialExtObj    = app::get('material')->model('basic_material_ext');
        
        $ids = $filter['item_id'];

        $datas = array();
        $sql = "select saleitem.name,saleitem.spec_name as goods_specinfo,sales.branch_id,sales.shop_id,sales.order_id,
                sales.sale_id as sale_id,sales.iostock_bn,saleitem.bn as product_bn,saleitem.nums as buycount,
                saleitem.sales_amount as sale_amount,sales.sale_time as createtime, saleitem.product_id, saleitem.name 
                from sdb_ome_sales_items as saleitem 
                left join sdb_ome_sales as sales on saleitem.sale_id = sales.sale_id 
                where saleitem.item_id in (".implode(',',$ids).")";

         $rows = $this->db->select($sql);
         if($rows){
            foreach($rows as $row){
                $data['product_bn'] = $row['product_bn'];
                $data['branch_id'] = $row['branch_id'];
                $data['goods_specinfo'] = $row['goods_specinfo'];
                $data['buycount'] = $row['buycount'];
                $data['shop_id'] = $row['shop_id'];
                $data['createtime'] = $row['createtime'];
                
                #基础物料信息
                $data['goods_name']    = $row['name'];
                if($row['product_id'])
                {
                    $materialRow    = $basicMaterialExtObj->dump(array('bm_id'=>$row['product_id']), 'specifications, brand_id, cat_id');
                    
                    #物料品牌
                    $sql_info    = "SELECT brand_name FROM sdb_ome_brand WHERE brand_id='". $materialRow['brand_id'] ."'";
                    $row_info    = $this->db->selectrow($sql_info);
                    $data['brand_name']    = $row_info['brand_name'];
                    
                    #物料类型
                    $sql_info    = "SELECT name FROM sdb_ome_goods_type WHERE type_id='". $materialRow['cat_id'] ."'";
                    $row_info    = $this->db->selectrow($sql_info);
                    $data['type_name']    = $row_info['name'];
                }
                
                $sql3 = "select order_bn from sdb_ome_orders where order_id = ".$row['order_id'];
                $row_order = $this->db->selectrow($sql3);
                $data['order_bn'] = $row_order['order_bn'];

                $data['sale_amount'] = "￥".$row['sale_amount'];
                $datas[] = $data;
            }
        }

        unset($data);

        //根据选择的字段定义导出的第一行标题
        if($curr_sheet == 1){
            $data['content']['main'][] = $this->getExportTitle($fields);
        }

        $oBranch = app::get('ome')->model('branch');
        // 所有的仓库
        $rs = $oBranch->getList('branch_id,branch_bn,name');
        foreach($rs as $v) {
            $branchs[$v['branch_id']] = $v;
        }

        $oShop = app::get('ome')->model('shop');
        // 所有的仓库
        $rs = $oShop->getList('shop_id,name');
        foreach($rs as $v) {
            $shops[$v['shop_id']] = $v;
        }

        foreach ($datas as $aFilter) {
            $goodsaleRow['order_bn'] = $aFilter['order_bn'];
            $goodsaleRow['branch_id'] = $branchs[$aFilter['branch_id']]['name'];
            $goodsaleRow['goods_name'] = $aFilter['goods_name'];
            $goodsaleRow['goods_specinfo'] = $aFilter['goods_specinfo'];
            $goodsaleRow['product_bn'] = $aFilter['product_bn'];
            $goodsaleRow['brand_name'] = $aFilter['brand_name'];
            $goodsaleRow['buycount'] = $aFilter['buycount'];
            $goodsaleRow['sale_amount'] = $aFilter['sale_amount'];
            $goodsaleRow['createtime'] = date('Y-m-d H:i:s',$aFilter['createtime']);
            $goodsaleRow['type_name'] = $aFilter['type_name'];
            $goodsaleRow['obj_type'] = $aFilter['obj_type'];
            $goodsaleRow['shop_id'] = $shops[$aFilter['shop_id']]['name'];

            //根据配置字段排序结果
            $exptmp_data = array();
            foreach (explode(',', $fields) as $key => $col) {
                if(isset($goodsaleRow[$col])){
                    $goodsaleRow[$col] = mb_convert_encoding($goodsaleRow[$col], 'GBK', 'UTF-8');
                    $exptmp_data[] = $goodsaleRow[$col];
                }
                else
                {
                    $exptmp_data[]    = '';
                }
            }

            $data['content']['main'][] = implode(',', $exptmp_data);
        }

        return $data;
    }

}
