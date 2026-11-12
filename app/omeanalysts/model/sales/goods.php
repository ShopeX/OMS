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
class omeanalysts_mdl_sales_goods extends dbeav_model
{
    var $has_export_cnf = true;

    var $export_name = '销售商品明细统计';

    var $stockcost_enabled = false;
    static public $_goods_types = array(
            'goods' => array('name'=>'普通','type'=>'goods'),
            'pkg' =>  array('name'=>'促销','type'=>'pkg'),
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
        
        //成本配置
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
     * 搜索项
     */
    public function searchOptions()
    {
        $columns = array();
        foreach($this->_columns() as $k=>$v)
        {
            if(isset($v['searchtype']) && $v['searchtype']){
                $columns[$k] = $v['label'];
            }
        }
        
        return $columns;
    }
    
    public function _filter($filter, $tableAlias=null, $baseWhere=null)
    {
        $config = app::get('eccommon')->getConf('analysis_config');

        $filter['order_status'] = $config['filter']['order_status'];
        $where = array(1);
        $itemsid = array();
        
        //店铺
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    $where[] = " S.shop_id IN('". implode("','", $shopIds) ."')";
                }
            } else {
                $where[] = " S.shop_id='". addslashes($filter['shop_id']) ."'";;
            }
        }
        
        //平台类型对应所有店铺shop_id
        if (isset($filter['shop_type']) && $filter['shop_type']){
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            
            $shop_ids = $shopList[$filter['shop_type']];
            if($shop_ids){
                $where[] = " S.shop_id IN ('". implode("','", $shop_ids) ."')";
            }
        }
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " S.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }
        
        //货号
        $filter['goods_bn'] = trim($filter['goods_bn']);
        $filter['goods_bn'] = str_replace(array("'", '"', '\\'), '', $filter['goods_bn']);
        if(isset($filter['goods_bn']) && $filter['goods_bn']){
            $where[] = " SI.goods_bn LIKE '". addslashes($filter['goods_bn']) ."%'";
            
            $_SESSION['bn'] = $filter['goods_bn'];
        }else{
            unset($_SESSION['bn']);
        }
        
        if(isset($filter['order_status']) && $filter['order_status']){
            switch($filter['order_status']){
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
                $time_from = ' S.'.$time_filter.' >='.strtotime($filter['time_from']);
                $where[] = $time_from;
                
                //$ftime = $time_from;
            }
            
            if(isset($filter['time_to']) && $filter['time_to']){
                $time_to = ' S.'.$time_filter.' <='.strtotime($filter['time_to']);
                $where[] = $time_to;
                
                //$ftime .= ' AND '.$time_to;
            }
        }else{
            $config['filter']['order_status'] = 'ship';
            
            app::get('eccommon')->setConf('analysis_config', $config);
            $time_filter = 'ship_time';
        }

        //查询销售额
        if(isset($filter['_sale_amount_search']) && is_numeric($filter['sale_amount'])){
            switch ($filter['_sale_amount_search']){
                case 'than': $_sql = ' GROUP BY SI.goods_bn HAVING S.sale_amount >'.$filter['sale_amount'];break;
                case 'lthan': $_sql = ' GROUP BY SI.goods_bn HAVING S.sale_amount <'.$filter['sale_amount'];break;
                case 'nequal': $_sql = ' GROUP BY SI.goods_bn HAVING S.sale_amount ='.$filter['sale_amount'];break;
                case 'sthan': $_sql = ' GROUP BY SI.goods_bn HAVING S.sale_amount <='.$filter['sale_amount'];break;
                case 'bthan': $_sql = ' GROUP BY SI.goods_bn HAVING S.sale_amount >='.$filter['sale_amount'];break;
                case 'between':
                    if($filter['sale_amount_from'] && $filter['sale_amount_to'] ){
                        $_sql = ' GROUP BY SI.goods_bn HAVING (S.sale_amount  >='.$filter['sale_amount_from'].' AND S.sale_amount < '.$filter['sale_amount_to'].')';
                    }else{
                        $_sql = '';
                    }
                break;
            }
        }

        //查询商品销售量
        if(isset($filter['_sale_num_search']) && is_numeric($filter['sale_num'])){
            if(isset($filter['_sale_amount_search']) && is_numeric($filter['sale_amount'])){
                $_sql = $_sql.' and ';
            }else{
                $_sql = ' GROUP BY SI.goods_bn HAVING ';
            }
            
            switch ($filter['_sale_num_search']){
                case 'than': $_sql =   $_sql.' sale_num >'.$filter['sale_num'];break;
                case 'lthan': $_sql =  $_sql.' sale_num <'.$filter['sale_num'];break;
                case 'nequal': $_sql = $_sql.' sale_num ='.$filter['sale_num'];break;
                case 'sthan': $_sql =  $_sql.' sale_num <='.$filter['sale_num'];break;
                case 'bthan': $_sql =  $_sql.' sale_num >='.$filter['sale_num'];break;
                case 'between':
                    if($filter['sale_num_from'] && $filter['sale_num_to'] ){
                        $_sql = $_sql.'(sale_num >='.$filter['sale_num_from'].' and sale_num < '.$filter['sale_num_to'].')';
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
     * 额外filter条件格式化
     * 
     * @param array $filter
     * @return string
     */
    public function rfilter($filter)
    {
        $where = array(1);
        
        //店铺
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    $where[] = " sa.shop_id IN('". implode("','", $shopIds) ."')";
                }
            } else {
                $where[] = " sa.shop_id='". addslashes($filter['shop_id']) ."'";
            }
        }
        
        if(isset($filter['time_from']) && $filter['time_from']){
            $where[] = ' S.ship_time >='.strtotime($filter['time_from']);
        }

        if(isset($filter['time_to']) && $filter['time_to']){
            $where[] = ' S.ship_time <='.strtotime($filter['time_to']);
        }
        
        $where[] = " sai.return_type='return'";
        
        return implode($where,' AND ');
    }
    
    /**
     * count统计总数
     */
    public function count($filter=null)
    {
        if(isset($filter['time_to']) && $filter['time_to']){
            $filter['time_to'] = $filter['time_to'].' 23:59:59';
        }
        
        $sql = 'SELECT count(*) AS _count FROM (SELECT SI.goods_id FROM sdb_ome_sales_objects SI LEFT JOIN sdb_ome_sales S ON SI.sale_id = S.sale_id 
                WHERE '.$this->_filter($filter).' GROUP BY SI.goods_bn ) AS tb';
        
        $row = $this->db->select($sql);
        
        return intval($row[0]['_count']);
    }
    
    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        if(isset($filter['time_to']) && $filter['time_to']){
            $filter['time_to'] = $filter['time_to'].' 23:59:59';
        }
        
        //sql
        $sales_sql = "SELECT S.sale_id,S.sale_bn,S.order_id,S.shop_id,S.shop_type, SI.obj_id,SI.goods_id,SI.goods_bn,SI.goods_name";
        $sales_sql .= ",SI.obj_type,SI.price,SI.sale_price,SI.pmt_price,SI.apportion_pmt,SI.sales_amount,SI.cost AS aftersale_cost_amount";
        $sales_sql .= ",sum(SI.cost_amount) AS cost_amount, sum(SI.quantity) AS sale_num, sum(SI.sales_amount) as sale_amount";
        $sales_sql .= ",sext.brand_id,sext.cat_id,sext.unit ";
        $sales_sql .= " FROM sdb_ome_sales_objects AS SI LEFT JOIN sdb_ome_sales AS S ON SI.sale_id = S.sale_id 
                        LEFT JOIN sdb_material_sales_material_ext AS sext ON SI.goods_id=sext.sm_id WHERE ".$this->_filter($filter);
        
        //group
        if((!$filter['_sale_amount_search'])&&(!$filter['_sale_num_search'])){
            $sales_sql .= ' GROUP BY SI.goods_bn ';
        }
        
        //select
        $rows = $this->db->selectLimit($sales_sql, $limit, $offset);
        if(empty($rows)){
            return array();
        }
        
        //订单关联退货统计
        //$reshipFilter = array();
        //$result = $this->_reshipStatis($rows, $reshipFilter);
        
        //获取基础物料
//        $goodsIds = array_column($rows, 'goods_id');
//        $goodsList = $this->getProductList($goodsIds);
        
        //获取销售商品成本金额(数量*成本单价)
        $obj_ids = array_column($rows, 'obj_id');
        $goodsCostAmount = $this->getGoodsCostAmount($obj_ids);
        
        //格式化数据
        $dayNum = intval((strtotime($filter['time_to']) - strtotime($filter['time_from'])+1) / 86400);
        foreach($rows as $key => $val)
        {
            $order_id = $val['order_id'];
            $goods_id = $val['goods_id'];
            $cat_id = $val['cat_id'];
            $obj_type = $val['obj_type'];
            $obj_id = $val['obj_id'];
            
            $rows[$key]['day_num'] = $dayNum ? round($rows[$key]['sale_num']/$dayNum,2):0;
            $rows[$key]['day_amount'] = $dayNum ? strval($rows[$key]['sale_amount']/$dayNum):0;
            $rows[$key]['order_id'] = $rows[$key]['order_id'] ? $rows[$key]['order_id'] : 0;
            
            //关联基础物料
//            if($goodsList[$goods_id]){
//                $rows[$key]['product_bns'] = implode('，', $goodsList[$goods_id]['bns']);
//
//                $rows[$key]['product_names'] = implode('，', $goodsList[$goods_id]['names']);
//            }
            
            //销售物料类型
            $rows[$key]['goods_type'] = self::$_goods_types[$obj_type]['name'];
            
            //退货相关汇总
            //$rows[$key]['total_reship_num'] = $result[$order_id][$goods_id]['total_reship_num'];
            //$rows[$key]['reship_total_amount'] = $result[$order_id][$goods_id]['reship_total_amount']; //退货总额
            
            //aftersale_cost_amount
            if($this->stockcost_enabled){
                $aftersale['aftersale_cost_amount'] = $val['aftersale_cost_amount'];
            }else{
                $aftersale['aftersale_cost_amount'] = 0;
            }
            
            $aftersale['aftersale_cost_amount'] = ($aftersale['aftersale_cost_amount'] * $rows[$key]['total_reship_num']);
            
            //退货数
            $rows[$key]['reship_num'] = $rows[$key]['total_reship_num'];
            
            //退货率
            $reship_ratio = $rows[$key]['sale_num'] ? round($rows[$key]['reship_num'] / $rows[$key]['sale_num'], 2) : 0;
            $rows[$key]['reship_ratio'] = ($reship_ratio * 100) . "%";
    
            //通过基础物料销售明细获取总的销售成本
            $item_cost_amount = (empty($goodsCostAmount[$obj_id]['cost_amount']) ? 0 : $goodsCostAmount[$obj_id]['cost_amount']);
            
            //销售成本
            $rows[$key]['cost_amount'] = $item_cost_amount;
            
            //平均成本
            $rows[$key]['agv_cost_amount'] = round($item_cost_amount / $rows[$key]['sale_num'], 2);
            
            //总成本 = 销售成本-售后商品成本之和
            $rows[$key]['total_cost_amount'] = round($rows[$key]['cost_amount'] - $aftersale['aftersale_cost_amount'], 2);
            
            $rows[$key]['gross_sales'] = $rows[$key]['sale_amount'] - $rows[$key]['reship_total_amount']-$rows[$key]['total_cost_amount']; //销售毛利 = 销售额-退货总额-总成本
            $rows[$key]['agv_gross_sales'] = round($rows[$key]['gross_sales']/$rows[$key]['sale_num'],2); //销售平均毛利 = 销售毛利/销售量
            
            $gross_sales_rate = ($rows[$key]['sale_amount']>0) ? round($rows[$key]['gross_sales']/$rows[$key]['sale_amount'],2) : 0; //销售毛利率 = 销售毛利/销售额
            $rows[$key]['gross_sales_rate'] = ($gross_sales_rate*100)."%";
            
            //销售物料关联的五级分类名称
            //$rows[$key]['category_id'] = $this->_getCategory($cat_id);
        }
        
        return $rows;
    }
    
    /**
     * 订单关联退货统计
     * @todo：最好需要sdb_sales_aftersale创建objects层数据,否则数据会不准确;
     * 
     * @param array $rows
     * @param array $filter
     * @return array
     */
    public function _reshipStatis($rows, $filter)
    {
        $orderIds = array_column($rows, 'order_id');
        $saleIds = array_column($rows, 'sale_id');
        
        //select
        $sql = "SELECT sa.order_id,sa.sale_id, si.item_id,si.product_id, so.obj_id,so.goods_id FROM sdb_ome_sales_items AS si ";
        $sql .= " LEFT JOIN sdb_ome_sales AS sa ON si.sale_id=sa.sale_id LEFT JOIN sdb_ome_sales_objects AS so ON si.obj_id=so.obj_id ";
        $sql .= " WHERE sa.sale_id IN(". implode(',', $saleIds) .")";
        $tempList = $this->db->select($sql);
        
        $salesItemList = array();
        foreach ($tempList as $key => $val)
        {
            $order_id = $val['order_id'];
            $product_id = $val['product_id'];
            $goods_id = $val['goods_id'];
            
            $salesItemList[$order_id][$product_id] = $val;
        }
        
        //where
        $whereList = array();
        
        /***
         * 发货时间
         * 
        if(isset($filter['time_from']) && $filter['time_from']){
            $whereList[] = ' S.ship_time >='.strtotime($filter['time_from']);
        }
        
        if(isset($filter['time_to']) && $filter['time_to']){
            $whereList[] = ' S.ship_time <'.strtotime($filter['time_to']);
        }
        ***/
        
        $whereList[] = " sai.return_type='return'";
        $where = implode($whereList,' AND ');
        
        $sql = "SELECT sa.order_id,sai.bn,sai.product_id,sai.num,sai.price FROM sdb_sales_aftersale_items AS sai 
                LEFT JOIN sdb_sales_aftersale AS sa ON sai.aftersale_id=sa.aftersale_id WHERE sa.order_id IN(". implode(',', $orderIds) .") ". $where;
        $dataList = $this->db->select($sql);
        if(empty($dataList)){
            return array();
        }
        
        $result = array();
        foreach($dataList as $key => $val)
        {
            $order_id = $val['order_id'];
            $product_id = $val['product_id'];
            
            //销售单信息(当PKG组合商品下的子货品 与 普通货品相同时,会不准)
            $salesInfo = $salesItemList[$order_id];
            $goods_id = $salesInfo[$product_id]['goods_id'];
            
            //退货金额
            $reship_total_amount = $val['num'] * $val['price'];
            if($result[$order_id][$goods_id]){
                $result[$order_id][$goods_id]['total_reship_num'] += $val['num'];
                $result[$order_id][$goods_id]['reship_total_amount'] += $reship_total_amount;
            }else{
                $result[$order_id][$goods_id]['total_reship_num'] = $val['num'];
                $result[$order_id][$goods_id]['reship_total_amount'] = $reship_total_amount;
            }
        }
        
        return $result;
    }
    
    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema()
    {
        $schema = array (
            'columns' => array (
                'type_id' => array (
                    'type' => 'varchar(50)',
                    'label' => '类型',
                    'width' => 110,
                    'editable' => false,
                    'in_list' => false, //此字段隐藏
                    'default_in_list' => false,
                    'order' => 1,
                    'orderby' => true,
                ),
                'goods_bn' => array (
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
                'goods_name' => array (
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
                    'order' => 12,
                ),
                'sale_num' => array (
                    'type' => 'number',
                    'label' => '销售量',
                    'width' => 75,
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => 'true',
                    'in_list' => true,
                    'default_in_list' => true,
                    'orderby' => true,
                    'order' => 15,
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
                    'order' => 14,
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
                    'order' => 22,
                ),
                'day_num' => array (
                    'type' => 'number',
                    'label' => '日均销售量',
                    'width' => 75,
                    'orderby' => true,
                    'editable' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 24,
                ),
                'reship_num' => array (
                    'type' => 'varchar(200)',
                    'default' => 1,
                    'required' => true,
                    'label' => '退货量',
                    'orderby' => true,
                    'width' => 110,
                    'editable' => true,
                    'in_list' => false, //此字段隐藏
                    'default_in_list' => false,
                    'order' => 26,
                ),
                'reship_ratio' => array (
                    'type' => 'varchar(30)',
                    'label' => '退货率',
                    'width' => 110,
                    'orderby' => false,
                    'editable' => false,
                    'in_list' => false, //此字段隐藏
                    'default_in_list' => false,
                    'order' => 28,
                ),
                'reship_total_amount' => array (
                    'type' => 'money',
                    'default' => 1,
                    'required' => true,
                    'label' => '退货总额',
                    'orderby' => true,
                    'width' => 110,
                    'editable' => true,
                    'in_list' => false, //此字段隐藏
                    'default_in_list' => false,
                    'order' => 29,
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
                    'order' => 16,
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
                    'order' => 18,
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
                    'order' => 19,
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
                    'order' => 30,
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
                    'order' => 32,
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
                    'order' => 40,
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
                    'order' => 42,
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
                    'order' => 46,
                ),
                'goods_type' => array (
                    'type' => 'varchar(200)',
                    'default' => 0,
                    'required' => true,
                    'label' => '类型',
                    'width' => 110,
                    'orderby' => false,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 1,
                ),
//                'category_id' => array (
//                    'type' => 'varchar(200)',
//                    'default' => 0,
//                    'label' => '物料分类',
//                    'width' => 240,
//                    'orderby' => false,
//                    'in_list' => true,
//                    'default_in_list' => true,
//                    'order' => 92,
//                ),
                'shop_id' => array (
                    'type' => 'table:shop@ome',
                    'label' => '店铺名称',
                    'editable' => false,
                    'width' => 130,
                    'default_in_list' => true,
                    'in_list' => true,
                    'order' => 11,
                ),
                'shop_type'=>array(
                    'type' => 'varchar(32)',
                    'label' => '店铺类型',
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => '90',
                    'order' => 12,
                ), 
                'brand_id' => array (
                    'type' => 'table:brand@ome',
                    'label' => '品牌',
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 25,
                    'width' =>100,
                    'order' => 98,
                ),
//                'product_bns' => array (
//                        'type' => 'varchar(50)',
//                        'label' => '基础物料编码',
//                        'width' => 230,
//                        'editable' => true,
//                        'in_list' => true,
//                        'default_in_list' => true,
//                        'order' => 9,
//                ),
//                'product_names' => array (
//                    'type' => 'varchar(300)',
//                    'label' => '基础物料名称',
//                    'width' => 230,
//                    'editable' => true,
//                    'in_list' => true,
//                    'default_in_list' => true,
//                    'order' => 10,
//                ),
            ),
            'idColumn' => 'goods_bn',
            'in_list' => array (
                1 => 'goods_type',
                2 => 'goods_bn',
                3 => 'goods_name',
//                4 => 'product_bns',
//                5 => 'product_names',
                6 => 'sale_price',
                7 => 'sale_num',
                8 => 'sale_amount',
                9 => 'day_amount',
                10 => 'day_num',
                //11 => 'reship_num',
                //12 => 'reship_ratio',
                //13 => 'reship_total_amount',
                14 => 'agv_cost_amount',
                15 => 'cost_amount',
                16 => 'agv_gross_sales',
                17 => 'gross_sales',
                18 => 'gross_sales_rate',
                21 => 'shop_id',
                22 => 'shop_type',
//                23 => 'category_id',
                24 => 'brand_id',
            ),
            'default_in_list' => array (
                1 => 'goods_type',
                2 => 'goods_bn',
                3 => 'goods_name',
//                4 => 'product_bns',
//                5 => 'product_names',
                6 => 'sale_price',
                7 => 'sale_num',
                8 => 'sale_amount',
                9 => 'day_amount',
                10 => 'day_num',
                //11 => 'reship_num',
                //12 => 'reship_ratio',
                //13 => 'reship_total_amount',
                14 => 'agv_cost_amount',
                15 => 'cost_amount',
                16 => 'agv_gross_sales',
                17 => 'gross_sales',
                18 => 'gross_sales_rate',
                21 => 'shop_id',
                22 => 'shop_type',
//                23 => 'category_id',
                24 => 'brand_id',
            ),
        );
        
        return $schema;
    }
    
    /**
     * 头部销售额统计情况
     * 
     * @param array $filter
     * @return array
     */
    public function get_products($filter=null)
    {
        if(isset($filter['time_to']) && $filter['time_to']){
            $filter['time_to'] = $filter['time_to'].' 23:59:59';
        }
        
        $dayNum = intval((strtotime($filter['time_to']) - strtotime($filter['time_from']) + 1) / 86400);
        $dayNum = $dayNum ? $dayNum : 1;
        
        $sql = 'SELECT sum(SI.cost_amount) as cost_amount,sum(SI.quantity) as sale_num,sum(SI.sales_amount) as sale_amount,sum(SI.refund_money) AS sum_refund_money ';
        $sql .= ' FROM sdb_ome_sales_objects SI LEFT JOIN sdb_ome_sales S ON SI.sale_id = S.sale_id WHERE '.$this->_filter($filter);
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
                    $rwhere .= ' and sa.shop_id in(\''. implode("','", $shopIds) .'\')';
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
                where sai.return_type='return' ".$rwhere;
    
        $reshipstat = $this->db->selectrow($sql);
        
        $data['reship_nums']      = ($reshipstat['total_reship_num'] ? $reshipstat['total_reship_num'] : 0);
        $data['reship_amounts']   = ($reshipstat['reship_total_amount'] ? $reshipstat['reship_total_amount'] : 0);
        
        //销售毛利 = 销售额-退货总额-总成本
        $data['gross_sales'] = $salestat['sale_amount'] - $data['reship_amounts'] - $salestat['cost_amount'];
        
        //销售平均毛利 = 销售毛利/销售量
        $data['agv_gross_sales'] = $salestat['sale_num'] > 0 ? round($data['gross_sales'] / $salestat['sale_num'], 2) : 0;
        
        //销售毛利率 = 销售毛利/销售额
        $data['gross_sales_rate'] = ($salestat['sale_amount'] > 0) ? round($data['gross_sales'] / $salestat['sale_amount'], 2) : 0;
        
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
     * 
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
                if($col == 'category_id'){
                    $title[] = '分类1';
                    $title[] = '分类2';
                    $title[] = '分类3';
                    $title[] = '分类4';
                    $title[] = '分类5';
                }elseif(isset($main_columns['columns'][$col])){
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
            foreach (explode(',', $fields) as $key => $col)
            {
                if($col == 'category_id'){
                    $category = explode('-', $aFilter[$col]);
                    
                    $exptmp_data['分类1'] = mb_convert_encoding($category[0], 'GBK', 'UTF-8');
                    $exptmp_data['分类2'] = mb_convert_encoding($category[1], 'GBK', 'UTF-8');
                    $exptmp_data['分类3'] = mb_convert_encoding($category[2], 'GBK', 'UTF-8');
                    $exptmp_data['分类4'] = mb_convert_encoding($category[3], 'GBK', 'UTF-8');
                    $exptmp_data['分类5'] = mb_convert_encoding($category[4], 'GBK', 'UTF-8');
                    
                }elseif($col == 'brand_id'){
                    $brand_id = $aFilter[$col];
                    $exptmp_data[] = $brand_id ? mb_convert_encoding($brandList[$brand_id]['brand_name'], 'GBK', 'UTF-8') : '';
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
     * 获取销售物料对应的五级分类名称
     * 
     * @param int $product_id
     * @param array $list
     * @return array
     */
    private function _getCategory($category_id)
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
     * 获取销售物料关联的基础物料
     * 
     * @param array $goodsIds
     */
    public function getProductList($sm_ids)
    {
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        //sm
        $dataList = $salesBasicMaterialObj->getList('*', array('sm_id'=>$sm_ids));
        if(empty($dataList)){
            return false;
        }
        
        $bm_ids = array_column($dataList, 'bm_id');
        
        //list
        $productList = $basicMaterialObj->getList('bm_id,material_bn,material_name', array('bm_id'=>$bm_ids));
        $productList = array_column($productList, null, 'bm_id');
        
        //format
        $smList = array();
        foreach ($dataList as $key => $val)
        {
            $sm_id = $val['sm_id'];
            $bm_id = $val['bm_id'];
            
            $smList[$sm_id]['bns'][$bm_id] = $productList[$bm_id]['material_bn'];
            $smList[$sm_id]['names'][$bm_id] = $productList[$bm_id]['material_name'];
        }
        
        return $smList;
    }
    
    /**
     * 获取销售商品成本金额(读取销售单基础物料明细中总销售成本金额)
     * 
     * @param $obj_ids
     * @return void
     */
    public function getGoodsCostAmount($obj_ids)
    {
        //check
        if(empty($obj_ids)){
            return array();
        }
        
        //list
        $sql = "SELECT item_id,obj_id,product_id,nums,cost_amount FROM sdb_ome_sales_items WHERE obj_id IN(". implode(',', $obj_ids) .")";
        $dataList = $this->db->select($sql);
        if(empty($dataList)){
            return array();
        }
        
        $costAmount = array();
        foreach ($dataList as $key => $val)
        {
            $obj_id = $val['obj_id'];
            $val['cost_amount'] = (empty($val['cost_amount']) ? 0 : $val['cost_amount']);
            
            //value
            if(empty($costAmount[$obj_id])){
                $costAmount[$obj_id] = array('cost_amount'=>$val['cost_amount'], 'item_nums'=>$val['nums']);
            }else{
                $costAmount[$obj_id]['cost_amount'] += $val['cost_amount'];
                $costAmount[$obj_id]['item_nums'] += $val['nums'];
            }
        }
        
        //unset
        unset($dataList);
        
        return $costAmount;
    }
}