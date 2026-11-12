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
 * 销售商品明细统计
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 2022.05.18
 */
class omeanalysts_mdl_sales_products extends dbeav_model
{
    var $has_export_cnf = true;

    var $export_name = '销售商品明细统计';

    var $stockcost_enabled = false;

    //物料类型
    static public $sales_material_type = array(
            'product' =>  array('name'=>'普通', 'type'=>'goods'),
            'pkg' =>  array('name'=>'组合商品','type'=>'pkg'),
            'gift' =>  array('name'=>'赠品','type'=>'gift'),
            'lkb' =>  array('name'=>'福袋','type'=>'lkb'),
            'pko' =>  array('name'=>'多选一','type'=>'pko'),
            'giftpackage' =>  array('name'=>'礼盒','type'=>'giftpackage'),
    );
    
    /**
     * __construct
     * @return mixed 返回值
     */

    public function __construct()
    {
        parent::__construct();
        
        if(app::get('tgstockcost')->is_installed()){
            $setting_stockcost_cost = app::get("ome")->getConf("tgstockcost.cost");
            if(!$setting_stockcost_cost){
                $this->stockcost_enabled = false;
            }else{
                $this->stockcost_enabled = true;
            }
        }
    }

    /**
     * 搜索Options
     * @return mixed 返回值
     */
    public function searchOptions()
    {
        $columns = array();
        foreach($this->_columns() as $k=>$v){
            if(isset($v['searchtype']) && $v['searchtype']){
                $columns[$k] = $v['label'];
            }
        }
        
        return $columns;
    }

    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $config = app::get('eccommon')->getConf('analysis_config');

        $filter['order_status'] = $config['filter']['order_status'];
        $where = array(1);
        $itemsid = array();
        
        $_sql = '';
        $groupByStr = " GROUP BY S.shop_id, SI.sales_material_bn, SI.product_id";

        //店铺
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    $where[] = " S.shop_id IN('". implode("','", $shopIds) ."')";
                }
            } else {
                $where[] = " S.shop_id ='". addslashes($filter['shop_id']) ."'";
            }
        }

        if (isset($filter['shop_type']) && $filter['shop_type']){
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            $shop_ids = $shopList[$filter['shop_type']];

            if ($shop_ids){
                $where[] = " S.shop_id IN('". implode("','", $shop_ids) ."')";
            }
        }
        
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " S.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }
        
        //货号
        if(isset($filter['bn']) && $filter['bn']){
            $where[] = " SI.bn LIKE '". addslashes($filter['bn']) ."%'";
            
            $_SESSION['bn'] = $filter['bn'];
        }else{
            unset($_SESSION['bn']);
        }
        
        //销售物料编码
        if(isset($filter['sales_material_bn']) && $filter['sales_material_bn']){
            $where[] = " SI.sales_material_bn LIKE '". addslashes($filter['sales_material_bn']) ."%'";
        }

        if(isset($filter['order_status']) && $filter['order_status']){
            switch($filter['order_status'])
            {
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

            if(isset($filter['time_from']) && $filter['time_from']){
                $time_from = " S.". $time_filter ." >=". strtotime($filter['time_from']);
                $where[] = $time_from;
                $ftime = $time_from;
            }

            if(isset($filter['time_to']) && $filter['time_to']){
                $time_to = " S.". $time_filter ." <=". strtotime($filter['time_to']);
                $where[] = $time_to;
                $ftime .= ' AND '.$time_to;
            }
        }else{
            $config['filter']['order_status'] = 'ship';
            app::get('eccommon')->setConf('analysis_config', $config);
            
            $time_filter = 'ship_time';
        }

        //查询销售额
        if(isset($filter['_sale_amount_search']) && is_numeric($filter['sale_amount'])){
            switch ($filter['_sale_amount_search']){
                case 'than': $_sql = $groupByStr. ' HAVING sale_amount >'.$filter['sale_amount'];break;
                case 'lthan': $_sql = $groupByStr. ' HAVING sale_amount <'.$filter['sale_amount'];break;
                case 'nequal': $_sql = $groupByStr. ' HAVING sale_amount ='.$filter['sale_amount'];break;
                case 'sthan': $_sql = $groupByStr. ' HAVING sale_amount <='.$filter['sale_amount'];break;
                case 'bthan': $_sql = $groupByStr. ' HAVING sale_amount >='.$filter['sale_amount'];break;
                case 'between':
                    if($filter['sale_amount_from'] && $filter['sale_amount_to'] ){
                        $_sql = $groupByStr. ' HAVING (sale_amount >='.$filter['sale_amount_from'].' AND sale_amount < '.$filter['sale_amount_to'].')';
                    }else{
                        $_sql = '';
                    }
                break;
            }
        }

        //查询销售量
        if(isset($filter['_sale_num_search']) && is_numeric($filter['sale_num'])){
            if(isset($filter['_sale_amount_search']) && is_numeric($filter['sale_amount'])){
                $_sql = $_sql .' AND ';
            }
            
            switch ($filter['_sale_num_search']){
                case 'than': $_sql = $groupByStr.' sale_num >'.$filter['sale_num'];break;
                case 'lthan': $_sql = $groupByStr.' sale_num <'.$filter['sale_num'];break;
                case 'nequal': $_sql = $groupByStr.' sale_num ='.$filter['sale_num'];break;
                case 'sthan': $_sql = $groupByStr.' sale_num <='.$filter['sale_num'];break;
                case 'bthan': $_sql = $groupByStr.' sale_num >='.$filter['sale_num'];break;
                case 'between':
                    if($filter['sale_num_from'] && $filter['sale_num_to'] ){
                        $_sql = $groupByStr.'(sale_num  >='.$filter['sale_num_from'].' AND sale_num < '.$filter['sale_num_to'].')';
                    }else{
                        $_sql = '';
                    }
                 break;
             }
        }
        
        if($where){
            $basefilter = implode(' AND ', $where);    
            if($_sql){
                $basefilter = $basefilter.' '.$_sql;
                return $basefilter;
            }else{
                return $basefilter;
            }
        }
    }

    /**
     * count
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function count($filter=null)
    {
        if(isset($filter['time_to']) && $filter['time_to']){
            $filter['time_to'] = $filter['time_to'].' 23:59:59';
        }
        
        $sql = 'SELECT count(*) as _count FROM (SELECT SI.product_id FROM sdb_ome_sales_items SI LEFT JOIN sdb_ome_sales S ON SI.sale_id = S.sale_id WHERE '.$this->_filter($filter).' GROUP BY S.shop_id, SI.sales_material_bn, SI.product_id ) as tb';
        
        $row = $this->db->select($sql);

        return intval($row[0]['_count']);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        if(isset($filter['time_to']) && $filter['time_to']){
            $filter['time_to'] = $filter['time_to'].' 23:59:59';
        }
        
        //sql
        $sales_sql = "SELECT S.shop_id,S.sale_bn";
        $sales_sql .= ", SI.obj_type,SI.product_id,SI.bn,SI.name,SI.sales_material_bn,SI.cost AS aftersale_cost_amount,sum(SI.cost_amount) AS cost_amount,sum(SI.nums) AS sale_num,sum(SI.sales_amount) AS sale_amount ";
        $sales_sql .= " FROM sdb_ome_sales_items SI LEFT JOIN sdb_ome_sales S ON SI.sale_id = S.sale_id ";
        $sales_sql .= " WHERE ". $this->_filter($filter);
        
        if((!$filter['_sale_amount_search'])&&(!$filter['_sale_num_search'])){
            $sales_sql .= " GROUP BY S.shop_id, SI.sales_material_bn, SI.product_id";
        }

        $rows = $this->db->selectLimit($sales_sql,$limit,$offset);
        if(empty($rows)){
            return false;
        }
        
        //获取销售物料列表
        $goodsBns = array_column($rows, 'sales_material_bn');
        $goodsList = $this->getGoodstList($goodsBns);
        
        //获取基础物料列表
        $productIds = array_column($rows, 'product_id');
        $productList = $this->getProductList($productIds);
        
        //day
        $dayNum = intval((strtotime($filter['time_to'])-strtotime($filter['time_from'])+1)/86400);
        
        //list
        foreach($rows as $key => $val)
        {
            $obj_type = $val['obj_type'];
            $shop_id = $val['shop_id'];
            $sales_material_bn = $val['sales_material_bn'];
            $product_id = $val['product_id'];
            
            //店铺类型
            $shopInfo = $this->_getShopList($shop_id);
            $rows[$key]['shop_type'] = $shopInfo['shop_type_name'];
            
            //物料类型
            $rows[$key]['obj_type'] = self::$sales_material_type[$obj_type]['name'];
            
            //销售物料名称
            $rows[$key]['sales_material_name'] = $goodsList[$sales_material_bn]['sales_material_name'];
            
            //基础物料信息
            $productInfo = $productList[$product_id];
            $category_id = $productInfo['cat_id'];
            $brand_id = $productInfo['brand_id'];
            
            //品牌
            $rows[$key]['brand_id'] = $this->getBrandName($brand_id);
            
            //info
            $rows[$key]['day_num'] = $dayNum?round($rows[$key]['sale_num']/$dayNum,2):0;
            $rows[$key]['day_amount'] = $dayNum?strval($rows[$key]['sale_amount']/$dayNum):0;
            
            //退货统计
            $aftersale_sql = "select sum(sai.num*sai.price) AS reship_total_amount,sum(sai.num) AS total_reship_num FROM sdb_sales_aftersale_items AS sai ";
            $aftersale_sql .= "LEFT JOIN sdb_sales_aftersale AS sa ON sai.aftersale_id = sa.aftersale_id ";
            $aftersale_sql .= "WHERE ". $this->rfilter($filter) ." AND sa.shop_id='". $shop_id ."' ";
            $aftersale_sql .= "AND sai.bn='". addslashes($val['bn']) ."' AND sai.sales_material_bn='". addslashes($val['sales_material_bn']) ."'";
            $aftersaleTj = $this->db->selectrow($aftersale_sql);
            
            $rows[$key]['reship_total_amount'] = $aftersaleTj['reship_total_amount'] ? $aftersaleTj['reship_total_amount']:0;
            
            if($this->stockcost_enabled){
                $aftersale['aftersale_cost_amount'] = $val['aftersale_cost_amount'];
            }else{
                $aftersale['aftersale_cost_amount'] = 0;
            }
            $aftersale['aftersale_cost_amount'] = ($aftersale['aftersale_cost_amount'] * $aftersaleTj['total_reship_num']);
            
            //退货数
            $rows[$key]['reship_num'] = intval($aftersaleTj['total_reship_num']);
            
            //退货率
            $reship_ratio = $rows[$key]['sale_num'] ? round($rows[$key]['reship_num'] / $rows[$key]['sale_num'], 2) : 0;
            $rows[$key]['reship_ratio'] = ($reship_ratio * 100)."%";
            
            //平均成本
            $rows[$key]['agv_cost_amount'] = round($rows[$key]['cost_amount']/$rows[$key]['sale_num'],2);
            $rows[$key]['total_cost_amount'] = round($rows[$key]['cost_amount'] - $aftersale['aftersale_cost_amount'],2);//总成本 = 销售成本-售后商品成本之和
            $rows[$key]['gross_sales'] = $rows[$key]['sale_amount'] - $rows[$key]['reship_total_amount']-$rows[$key]['total_cost_amount'];//销售毛利 = 销售额-退货总额-总成本
            $rows[$key]['agv_gross_sales'] = round($rows[$key]['gross_sales']/$rows[$key]['sale_num'],2);//销售平均毛利 = 销售毛利/销售量
            
            //销售毛利率 = 销售毛利/销售额
            $gross_sales_rate = ($rows[$key]['sale_amount']>0) ? round($rows[$key]['gross_sales']/$rows[$key]['sale_amount'],2) : 0;
            $rows[$key]['gross_sales_rate'] = ($gross_sales_rate*100)."%";
            
            //销售单价 = 商品销售之和/销售量
            $rows[$key]['sale_price'] = strval($rows[$key]['sale_amount'] / $rows[$key]['sale_num']);
            
            // 基础分类
            $rows[$key]['category_id'] = $this->_getCategory($val['product_id'], $rows);
        }
        
        //对数组排序
        if($orderType){
            foreach($rows as $k=>$data)
            {
                $bn[$k] = $data['bn'];
                $name[$k] = $data['name'];
                $sale_price[$k] = $data['sale_price'];
                $sale_num[$k] = $data['sale_num'];
                $sale_amount[$k] = $data['sale_amount'];
                $day_amount[$k] = $data['day_amount'];
                $day_num[$k] = $data['day_num'];
                $reship_num[$k] = $data['reship_num'];
                $reship_ratio[$k] = $data['reship_ratio'];
                $reship_total_amount[$k] = $data['reship_total_amount'];
                $agv_cost_amount[$k] = $data['agv_cost_amount'];
                $cost_amount[$k] = $data['cost_amount'];
                $agv_gross_sales[$k] = $data['agv_gross_sales'];
                $gross_sales[$k] = $data['gross_sales'];
                $gross_sales_rate[$k] = $data['gross_sales_rate'];
                $cat_id[$k] = $data['cat_id'];
            }
            
            if(is_string($orderType)){
                $arr = explode(" ", $orderType);
                if(strtolower($arr[1]) == 'desc'){
                    array_multisort(${$arr[0]},SORT_DESC,$rows);
                }else{
                    array_multisort(${$arr[0]},SORT_ASC,$rows);
                }
            }
        }
        
        return $rows;
    }
    
    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema()
    {
        $schema = array (
            'columns' => array (
                'obj_type' => array (
                    'type' => 'varchar(30)',
                    'label' => '类型',
                    'width' => 110,
                    'orderby' => false,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 1,
                ),
                'sales_material_bn' => array (
                    'type' => 'varchar(50)',
                    'required' => true,
                    'default' => 0,
                    'label' => '销售物料编码',
                    'width' => 160,
                    'searchtype' => 'has',
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => 'true',
                    'in_list' => true,
                    'default_in_list' => true,
                    'orderby' => true,
                    'order' => 2,
                ),
                'sales_material_name' => array (
                    'type' => 'varchar(200)',
                    'pkey' => true,
                    'label' => '销售物料名称',
                    'width' => 310,
                    'editable' => false,
                    'in_list' => true,
                    'orderby' => true,
                    'default_in_list' => true,
                    'order' => 4,
                ),
                'bn' => array (
                    'type' => 'varchar(50)',
                    'required' => true,
                    'default' => 0,
                    'label' => '基础物料编码',
                    'width' => 120,
                    'searchtype' => 'has',
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => 'true',
                    'in_list' => true,
                    'default_in_list' => true,
                    'orderby' => true,
                    'order'=>4,
                    'realtype' => 'varchar(50)',
                ),
                'name' => array (
                    'type' => 'varchar(200)',
                    'pkey' => true,
                    'label' => '基础物料名称',
                    'width' => 310,
                    'editable' => false,
                    'in_list' => true,
                    'orderby' => true,
                    'default_in_list' => true,
                    'order'=>5,
                    'realtype' => 'varchar(200)',
                ),
                'sale_price' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '销售单价',
                    'width' => 110,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'orderby' => true,
                    'order'=>7,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'sale_num' => array (
                    'type' => 'number',
                    'label' => '销售量',
                    'width' => 75,
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => 'true',
                    'in_list' => true,
                    'is_title' => true,
                    'default_in_list' => true,
                    'orderby' => true,
                    'order'=>8,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'sale_amount' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '销售额',
                    'width' => 110,
                    'orderby' => true,
                    'editable' => false,
                    'filtertype' => 'number',
                    'filterdefault' => 'true',
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>9,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'day_amount' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '日均销售额',
                    'width' => 110,
                    'orderby' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>10,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'day_num' => array (
                    'type' => 'number',
                    'label' => '日均销售量',
                    'width' => 75,
                    'orderby' => true,
                    'editable' => true,
                    'in_list' => true,
                    'is_title' => true,
                    'default_in_list' => true,
                    'order'=>11,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'reship_num' => array (
                    'type' => 'varchar(200)',
                    'default' => 1,
                    'required' => true,
                    'label' => '退货量',
                    'orderby' => true,
                    'width' => 110,
                    'editable' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>12,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'reship_ratio' => array (
                    'type' => 'varchar(200)',
                    'label' => '退货率',
                    'width' => 110,
                    'orderby' => false,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>13,
                    'realtype' => 'varchar(50)',
                ),
                'reship_total_amount' => array (
                    'type' => 'money',
                    'default' => 1,
                    'required' => true,
                    'label' => '退货总额',
                    'orderby' => true,
                    'width' => 110,
                    'editable' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>14,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'agv_cost_amount' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '平均成本',
                    'width' => 110,
                    'orderby' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>15,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'cost_amount' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '销售成本',
                    'width' => 110,
                    'orderby' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>16,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'agv_gross_sales' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '销售平均毛利',
                    'width' => 110,
                    'orderby' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>17,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'gross_sales' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '销售毛利',
                    'width' => 110,
                    'orderby' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>18,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'gross_sales_rate' => array (
                    'type' => 'varchar(200)',
                    'default' => 0,
                    'required' => true,
                    'label' => '销售毛利率',
                    'width' => 110,
                    'orderby' => false,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>19,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'total_cost_amount' => array (
                    'type' => 'money',
                    'default' => 1,
                    'required' => true,
                    'label' => '总成本',
                    'orderby' => true,
                    'width' => 110,
                    'editable' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>20,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'total_gross_sales' => array (
                    'type' => 'money',
                    'default' => 1,
                    'required' => true,
                    'label' => '总毛利',
                    'orderby' => true,
                    'width' => 110,
                    'editable' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>21,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'total_gross_sales_rate' => array (
                    'type' => 'varchar(200)',
                    'default' => 0,
                    'required' => true,
                    'label' => '总毛利率',
                    'width' => 110,
                    'orderby' => false,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>22,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'category_id' => array (
                    'type' => 'varchar(80)',
                    'default' => 0,
                    'label' => '物料分类',
                    'width' => 240,
                    'orderby' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>23,
                ),
                'shop_id' =>
                array (
                    'type' => 'table:shop@ome',
                    'label' => '店铺名称',
                    'editable' => false,
                    'width' =>130,
                    'default_in_list'=>true,
                    'in_list'=>true,
                ),
                'shop_type'=>array(
                    'type' => 'varchar(32)',
                    'label' => '店铺类型',
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => '70'
                ), 
                'brand_id' => array (
                    'type' => 'varchar(30)',
                    'label' => '品牌',
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 25,
                    'width' =>100,
                ),
            ),
            'idColumn' => 'bn',
            'in_list' => array (
                0 => 'obj_type',
                1 => 'sales_material_bn',
                2 => 'sales_material_name',
                3 => 'bn',
                4 => 'name',
                6 => 'sale_price',
                7 => 'sale_num',
                8 => 'sale_amount',
                9 => 'day_amount',
                10 => 'day_num',
                11 => 'reship_num',
                12 => 'reship_ratio',
                13 => 'reship_total_amount',
                14 => 'agv_cost_amount',
                15 => 'cost_amount',
                16 => 'agv_gross_sales',
                17 => 'gross_sales',
                18 => 'gross_sales_rate',
                21 => 'shop_id',
                22 => 'shop_type',
                23 => 'category_id',
                24 => 'brand_id',
            ),
            'default_in_list' => array (
                0 => 'obj_type',
                1 => 'sales_material_bn',
                2 => 'sales_material_name',
                3 => 'bn',
                4 => 'name',
                6 => 'sale_price',
                7 => 'sale_num',
                8 => 'sale_amount',
                9 => 'day_amount',
                10 => 'day_num',
                11 => 'reship_num',
                12 => 'reship_ratio',
                13 => 'reship_total_amount',
                14 => 'agv_cost_amount',
                15 => 'cost_amount',
                16 => 'agv_gross_sales',
                17 => 'gross_sales',
                18 => 'gross_sales_rate',
                21 => 'shop_id',
                22 => 'shop_type',
                23 => 'category_id',
                24 => 'brand_id',
            ),
        );
        
        return $schema;
    }
    
    /**
     * 售后统计filter条件
     * 
     * @param array $filter
     * @return string
     */
    public function rfilter($filter)
    {
        $where = array(1);
        
        if(isset($filter['time_from']) && $filter['time_from']){
            $where[] = " sa.ship_time >=".strtotime($filter['time_from']);
        }
        
        if(isset($filter['time_to']) && $filter['time_to']){
            $where[] = " sa.ship_time <=".strtotime($filter['time_to']);
        }
        
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " sa.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }
        
        $where[] = " sai.return_type = 'return'";
        
        return implode(" AND ", $where);
    }

    public function get_products($filter=null)
    {
        if(isset($filter['time_to']) && $filter['time_to']){
            $filter['time_to'] = $filter['time_to'].' 23:59:59';
        }

        $dayNum = intval((strtotime($filter['time_to'])-strtotime($filter['time_from'])+1)/86400);
        $dayNum = $dayNum ? $dayNum : 1;

        $sql = 'SELECT sum(SI.cost_amount) as cost_amount,sum(SI.nums) as sale_num,sum(SI.sales_amount) as sale_amount,sum(SI.gross_sales) AS gross_sales FROM sdb_ome_sales_items SI LEFT JOIN sdb_ome_sales S ON SI.sale_id = S.sale_id WHERE '.$this->_filter($filter);
        $salestat = $this->db->selectrow($sql);

        $data['sale_amount']      = $salestat['sale_amount'];
        $data['salenums']         = $salestat['sale_num'];
        $data['day_amounts']      = bcdiv($data['sale_amount'], $dayNum,3);
        $data['day_nums']         = bcdiv($data['salenums'], $dayNum,3);

        // 店铺
        $rwhere = '';
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    $rwhere .= ' and sa.shop_id in(\''.implode("','", $shopIds).'\')';
                }
            } else {
                $rwhere .= ' and sa.shop_id =\''.addslashes($filter['shop_id']).'\'';
            }
        }
        if (isset($filter['time_from']) && $filter['time_from'] && isset($filter['time_to']) && $filter['time_to']) {
            $rwhere .= ' and sa.ship_time >=' . strtotime($filter['time_from']) . ' AND ' . ' sa.ship_time <=' . strtotime($filter['time_to']);
            unset($filter['time_from'], $filter['time_to']);
        }

        $sql = "select sum(sai.num*sai.price) as reship_total_amount,sum(sai.num) as total_reship_num 
                from sdb_sales_aftersale_items sai 
                left join sdb_sales_aftersale sa on sai.aftersale_id = sa.aftersale_id 
                left join sdb_material_basic_material as p on sai.product_id=p.bm_id
                where sai.return_type='return' ".$rwhere;
        $reshipstat = $this->db->selectrow($sql);

        $data['reship_nums']      = $reshipstat['total_reship_num'];
        $data['reship_amounts']   = $reshipstat['reship_total_amount'];

        $data['gross_sales']      = $salestat['gross_sales'] - $data['reship_amounts'];
        $data['gross_sales_rate'] = $data['sale_amount'] ? bcdiv($data['gross_sales'], $data['sale_amount'],3) : 0;

        return $data;
    }
    
    /**
     * 获得日志类型(non-PHPdoc)
     * @see dbeav_model::getLogType()
     */
    public function getLogType($logParams)
    {
        $type = $logParams['type'];
        $logType = 'none';
        
        if ($type == 'export') {
            $logType = $this->exportLogType($logParams);
        }elseif ($type == 'import') {
            $logType = $this->importLogType($logParams);
        }
        
        return $logType;
    }
    
    /**
     * 导出日志类型
     * 
     * @param Array $logParams 日志参数
     */
    public function exportLogType($logParams)
    {
        $params = $logParams['params'];
        
        $type = 'report';
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_analysis') {
            $type .= '_salesReport_posSales';
        }
        
        $type .= '_export';
        
        return $type;
    }
    /**
     * 导入操作日志类型
     * @param Array $logParams 日志参数
     */
    public function importLogType($logParams)
    {
        $params = $logParams['params'];
        
        $type = 'report';
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_analysis') {
            $type .= '_salesReport_posSales';
        }
        
        $type .= '_import';
        
        return $type;
    }

    //根据查询条件获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id)
    {
        //根据选择的字段定义导出的第一行标题
        if($curr_sheet == 1){
            $title = array();
            
            $main_columns = $this->get_schema();
            
            foreach( explode(',', $fields) as $k => $col )
            {
                if ($col == 'category_id'){
                    $title[] = '分类1';
                    $title[] = '分类2';
                    $title[] = '分类3';
                    $title[] = '分类4';
                    $title[] = '分类5';
                } elseif (isset($main_columns['columns'][$col])){
                    $title[] = $main_columns['columns'][$col]['label'];
                }
            }

            $data['content']['main'][] =  mb_convert_encoding(implode(',',$title), 'GBK', 'UTF-8');
        }
        
        //商品品牌
        $brandMdl = app::get('ome')->model('brand');
        $brandList = $brandMdl->getList('brand_id, brand_name', array(), 0, -1);
        $brandList = array_column($brandList, null, 'brand_id');
        
        //list
        $productssale = $this->getList('*',$filter,$start,$end);
        
        if(!$productssale) return false;
        
        //统一获取shop_id用来处理店铺名称字段
        $arr_shop_ids = array();
        foreach ($productssale as $var_p_s){
            if(!in_array($var_p_s["shop_id"],$arr_shop_ids)){
                $arr_shop_ids[] = $var_p_s["shop_id"];
            }
        }
        
        $mdl_ome_shop = app::get('ome')->model('shop');
        $rs_ome_shop = $mdl_ome_shop->getList("shop_id,name",array("shop_id"=>$arr_shop_ids));
        $rl_shop_id_name = array();
        foreach ($rs_ome_shop as $var_o_s){
            $rl_shop_id_name[$var_o_s["shop_id"]] = $var_o_s["name"];
        }
        
        foreach ($productssale as &$var_shop_id_change){
            $var_shop_id_change["shop_id"] = $rl_shop_id_name[$var_shop_id_change["shop_id"]];
        }
        unset($var_shop_id_change);
        
        foreach ($productssale as $k => $aFilter)
        {
            $exptmp_data = array();
            foreach (explode(',', $fields) as $key => $col) {

                if ($col == 'category_id'){
                    $category = explode('-',$aFilter[$col]);
                    
                    $exptmp_data['分类1'] = mb_convert_encoding($category[0], 'GBK', 'UTF-8');
                    $exptmp_data['分类2'] = mb_convert_encoding($category[1], 'GBK', 'UTF-8');
                    $exptmp_data['分类3'] = mb_convert_encoding($category[2], 'GBK', 'UTF-8');
                    $exptmp_data['分类4'] = mb_convert_encoding($category[3], 'GBK', 'UTF-8');
                    $exptmp_data['分类5'] = mb_convert_encoding($category[4], 'GBK', 'UTF-8');
                }elseif($col == 'brand_id'){
                    //$brand_id = $aFilter[$col];
                    //$exptmp_data[] = $brand_id ? mb_convert_encoding($brandList[$brand_id]['brand_name'], 'GBK', 'UTF-8') : '';
                    
                    $exptmp_data[] = $aFilter[$col] ? mb_convert_encoding($aFilter[$col], 'GBK', 'UTF-8') : '';
                }elseif(isset($aFilter[$col])){
                    $aFilter[$col] = mb_convert_encoding($aFilter[$col], 'GBK', 'UTF-8');
                    $exptmp_data[] = $aFilter[$col];
                }else{
                    $exptmp_data[] = '';
                }
            }
            
            $data['content']['main'][] = implode(',', $exptmp_data);
        }

        return $data;
    }
    
    /**
     * 获取店铺列表
     * 
     * @return array
     */
    public function _getShopList($shop_id)
    {
        static $shopList;
        
        if($shopList) {
            return $shopList[$shop_id];
        }
        
        //店铺列表
        $shopModel = app::get('ome')->model('shop');
        $shopList = $shopModel->getList('shop_id,shop_type', array());
        
        //店铺类型
        $shoptype = ome_shop_type::get_shop_type();
        
        $shops = array();
        foreach($shopList as $shop)
        {
            $shop_id = $shop['shop_id'];
            $shop_type = $shop['shop_type'];
            
            $shop['shop_type_name'] = $shoptype[$shop_type];
            
            $shops[$shop_id] = $shop;
        }
        
        return $shops[$shop_id];
    }
    
    /**
     * 获取销售物料列表
     * 
     * @param array $goodsIds
     * @return array
     */
    public function getGoodstList($goodsBns)
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        
        //check
        if(empty($goodsBns)){
            return false;
        }
        
        //sm
        $dataList = $salesMaterialObj->getList('sm_id,sales_material_bn,sales_material_name', array('sales_material_bn'=>$goodsBns));
        if(empty($dataList)){
            return false;
        }
        
        $smList = array_column($dataList, null, 'sales_material_bn');
        
        return $smList;
    }
    
    /**
     * 获取基础物料列表
     * 
     * @param array $goodsIds
     * @return array
     */
    public function getProductList($productIds)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        //check
        if(empty($productIds)){
            return false;
        }
        
        $sql = "SELECT a.bm_id,a.material_bn,a.cat_id, b.cost,b.retail_price,b.brand_id FROM sdb_material_basic_material AS a 
                LEFT JOIN sdb_material_basic_material_ext AS b ON a.bm_id=b.bm_id WHERE a.bm_id IN(". implode(',', $productIds) .")";
        $dataList = $basicMaterialObj->db->select($sql);
        $dataList = array_column($dataList, null, 'bm_id');
        
        return $dataList;
    }
    
    /**
     * 获取销售物料对应的五级分类名称
     * 
     * @param int $product_id
     * @param array $list
     * @return array
     */
    public function _getCategoryName($category_id)
    {
        static $catList;
        
        if($catList) {
            return $catList[$category_id]['cat_pathname'];
        }
        
        $materialCatMdl = app::get('material')->model('basic_material_cat');
        
        //所有分类(只获取前3000条数据)
        $tempCat = $materialCatMdl->getList('cat_id,parent_id,cat_path,cat_name', array(), 0, 1000);
        if(empty($tempCat)){
            return false;
        }
        
        $catList = array();
        foreach($tempCat as $key => $val)
        {
            $cat_id = $val['cat_id'];
            $parent_id = $val['parent_id'];
            
            $catList[$cat_id] = $val;
        }
        
        //格式化cat_path
        foreach($catList as $cat_id => $val)
        {
            $pathList = array_filter(explode(',', $val['cat_path']));
            if(empty($pathList)){
                continue;
            }
            $pathList[] = $cat_id; //加入自身
            
            //path路径
            $pathNames = array();
            foreach($pathList as $pathKey => $pathCatId)
            {
                $pathNames[] = $catList[$pathCatId]['cat_name'];
            }
            
            $catList[$cat_id]['cat_pathname'] = implode('-', $pathNames);
        }
        
        return $catList[$category_id]['cat_pathname'];
    }
    
    /**
     * 获取基础物料五级分类
     * 
     * @return void
     * @author 
     * */
    private function _getCategory($product_id, $list)
    {
        static $l;

        if (isset($l)) {
            return $l[$product_id];
        }
        $bmMdl = app::get('material')->model('basic_material');
        $catMdl = app::get('material')->model('basic_material_cat');

        $filter = [];
        $filter['bm_id'] = array_column($list, 'product_id');

        $l = [];
        foreach($bmMdl->getList('bm_id,cat_id',$filter) as $value){
            if (!$value['cat_id']) {
                continue;
            }

            $l[$value['bm_id']] = &$c[$value['cat_id']]['cat_pathname'];
        }

        if ($c && $cat_id = array_keys($c)){

            $parent_id = [0];
            foreach ($catMdl->getList('cat_id,cat_path,cat_name',['cat_id'=>$cat_id]) as $value) {
                $parent_id = array_unique(array_filter(array_merge($parent_id,explode(',', $value['cat_path']))));

                $c[$value['cat_id']]['cat_path'] = $value['cat_path'];
                $c[$value['cat_id']]['cat_name'] = $value['cat_name'];
            }

            $parents = $catMdl->getList('cat_id,cat_name', ['cat_id' => $parent_id]);
            $parents = array_column($parents, null, 'cat_id');

            foreach ($c as $cid => $value){
                $cat_pathname = [];

                foreach(explode(',', $value['cat_path']) as $pid){
                    if ($pid && $parents[$pid]){
                        $cat_pathname[] = $parents[$pid]['cat_name'];
                    }
                }

                $cat_pathname[] = $value['cat_name'];
                $c[$cid]['cat_pathname'] = implode('-', $cat_pathname);
            }
        }
        
        return $l[$product_id];
    }
    
    /**
     * 获取基础物料对应的品牌名称
     * 
     * @param int $product_id
     * @return array
     */
    public function getBrandName($brand_id)
    {
        static $brandList;
        
        if($brandList) {
            return $brandList[$brand_id]['brand_name'];
        }
        
        $brandMdl = app::get('ome')->model('brand');
        $brandList = $brandMdl->getList('brand_id, brand_name', array(), 0, 1000);
        $brandList = array_column($brandList, null, 'brand_id');
        
        return $brandList[$brand_id]['brand_name'];
    }
}