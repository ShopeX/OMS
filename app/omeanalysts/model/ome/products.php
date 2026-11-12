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
class omeanalysts_mdl_ome_products extends dbeav_model{

    var $has_export_cnf = true;

    var $export_name = '货品销售情况';

    var $stockcost_enabled = false;

    public function __construct(){
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

    public function searchOptions(){
        $columns = array();
        foreach($this->_columns() as $k=>$v){
            if(isset($v['searchtype']) && $v['searchtype']){
                $columns[$k] = $v['label'];
            }
        }

        return $columns;
    }

    public function get_products($filter=null){
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
        if (isset($filter['shop_type']) && $filter['shop_type']){
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            $shop_ids = $shopList[$filter['shop_type']];

            if ($shop_ids){
                $rwhere .= ' and sa.shop_id in (\''.implode("','", $shop_ids).'\')';
            }
        }
        if(isset($filter['org_id']) && $filter['org_id']){
            $rwhere .= " and sa.org_id in ('".implode('\',\'',$filter['org_id'])."')";
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

        $data['reship_nums']      = $reshipstat['total_reship_num'];
        $data['reship_amounts']   = $reshipstat['reship_total_amount'];

        $data['gross_sales']      = $salestat['gross_sales'] - $data['reship_amounts'];
        $data['gross_sales_rate'] = $data['sale_amount'] > 0 ? bcdiv($data['gross_sales'], $data['sale_amount'],3) : 0;

        return $data;
    }

    public function count($filter=null){
        if(isset($filter['time_to']) && $filter['time_to']){
            $filter['time_to'] = $filter['time_to'].' 23:59:59';
        }

        $row = $this->db->select('SELECT count(*) as _count FROM (SELECT SI.product_id FROM sdb_ome_sales_items SI LEFT JOIN sdb_ome_sales S ON SI.sale_id = S.sale_id WHERE '.$this->_filter($filter).' GROUP BY SI.bn,S.shop_id ) as tb');

        return intval($row[0]['_count']);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        if(isset($filter['time_to']) && $filter['time_to']){
            $filter['time_to'] = $filter['time_to'].' 23:59:59';
        }
        
        // $sales_sql = 'SELECT P.material_name,P.type,SI.obj_type as sales_items_obj_type,SI.product_id,SI.cost as aftersale_cost_amount,SI.bn,
        //         sum(SI.cost_amount) as cost_amount,sum(SI.nums) as sale_num,sum(SI.sales_amount) as sale_amount,
        //         S.order_id,S.shop_id,SI.product_id, pext.cat_id, pext.brand_id, pext.cat_id
        //         FROM sdb_ome_sales_items SI 
        //         LEFT JOIN sdb_ome_sales S ON SI.sale_id = S.sale_id 
        //         LEFT JOIN sdb_material_basic_material AS P ON SI.product_id=P.bm_id
        //         LEFT JOIN sdb_material_basic_material_ext AS pext ON P.bm_id=pext.bm_id
        //         WHERE '.$this->_filter($filter);
        $sales_sql = 'SELECT SI.obj_type as sales_items_obj_type,SI.product_id,SI.cost as aftersale_cost_amount,SI.bn,
                sum(SI.cost_amount) as cost_amount,sum(SI.nums) as sale_num,sum(SI.sales_amount) as sale_amount,
                S.order_id,S.shop_id,S.org_id,SI.product_id
                FROM sdb_ome_sales_items SI 
                LEFT JOIN sdb_ome_sales S ON SI.sale_id = S.sale_id 
                WHERE '.$this->_filter($filter);

        if((!$filter['_sale_amount_search'])&&(!$filter['_sale_num_search'])){
            $sales_sql .= ' GROUP BY SI.bn,S.shop_id';
        }

        $rows = $this->db->selectLimit($sales_sql,$limit,$offset);
        // 批量获取运营组织名称
        $org_ids = array_unique(array_column($rows, 'org_id'));
        $org_names = array();
        if (!empty($org_ids)) {
            $orgModel = app::get('ome')->model('operation_organization');
            $orgList = $orgModel->getList('org_id,name', array('org_id'=>$org_ids));
            foreach ($orgList as $org) {
                $org_names[$org['org_id']] = $org['name'];
            }
        }

        $product_id_arr = array_unique(array_column($rows, 'product_id'));
        $sku_sql = 'SELECT P.bm_id,P.material_name,P.type,pext.cat_id,pext.brand_id,pext.cat_id
                FROM sdb_material_basic_material AS P 
                LEFT JOIN sdb_material_basic_material_ext AS pext ON P.bm_id=pext.bm_id
                WHERE P.bm_id in ('.($product_id_arr?implode(',', $product_id_arr):'0').')';
        $skuList = $this->db->select($sku_sql);
        $skuList = array_column($skuList, null, 'bm_id');

        $goods_type_obj = app::get('ome')->model('goods_type');
        $goods_types = $goods_type_obj->getList('type_id,name', array());
        $types_arr = array();
        foreach($goods_types as $kk => $vv){
            $types_arr[$vv['type_id']] = $vv['name'];
        }

        $dayNum = intval((strtotime($filter['time_to'])-strtotime($filter['time_from'])+1)/86400);
        if($rows){
            foreach($rows as $key=>$val){
                if (isset($skuList[$val['product_id']])) {
                    $rows[$key] = $val = array_merge($val, $skuList[$val['product_id']]);
                }
                if($rows[$key]['product_id'] ){
                    $rows[$key]['name'] = $val['material_name'];
                    //sunjing平铺sales_item后product_id都为bm_id 没有老数据的0值存在
                    if($val["sales_items_obj_type"] == "pkg"){
                        $rows[$key]['type_id'] = '组合';
                    }elseif ($val["sales_items_obj_type"] == "lkb"){
                        $rows[$key]['type_id'] = '福袋';
                    }elseif ($val["sales_items_obj_type"] == "pko"){
                        $rows[$key]['type_id'] = '多选一';
                    }else{
                        $rows[$key]['type_id'] = '普通';
                    }
                }else{ //老数据product_id为0为促销类型
                    $salesMObj = app::get('material')->model('sales_material');
                    $salesMInfo = $salesMObj->getList('sales_material_name,sales_material_type',array('sales_material_bn'=>$val['bn'],'sales_material_type'=>2), 0, 1);
                    if($salesMInfo){
                        $rows[$key]['type_id'] = '组合';
                        $rows[$key]['name'] = $salesMInfo[0]['sales_material_name'];
                    }else{
                        $rows[$key]['type_id'] = '未知';
                    }
                }
                $rows[$key]['org_name'] = isset($org_names[$val['org_id']]) ? $org_names[$val['org_id']] : '';
                $rows[$key]['day_num'] = $dayNum?round($rows[$key]['sale_num']/$dayNum,2):0;
                $rows[$key]['day_amount'] = $dayNum?strval($rows[$key]['sale_amount']/$dayNum):0;
                $rows[$key]['order_id'] = $rows[$key]['order_id'] ? $rows[$key]['order_id'] : 0;
                $rows[$key]['cat_id'] = $types_arr[$val['cat_id']];
                $rows[$key]['shop_type']    = kernel::single('omeanalysts_shop')->getShopDetail($val['shop_id']);
                $sql = "select sum(sai.num*sai.price) as reship_total_amount,sum(sai.num) as total_reship_num from sdb_sales_aftersale_items sai left join sdb_sales_aftersale sa on sai.aftersale_id = sa.aftersale_id where ".$this->rfilter($filter)." and sai.bn = '".addslashes($val['bn'])."' and sa.shop_id='".$val['shop_id']."'";
                $row = $this->db->select($sql);

                $rows[$key]['reship_total_amount'] = $row[0]['reship_total_amount']?$row[0]['reship_total_amount']:0;

                if($this->stockcost_enabled){
                    $aftersale['aftersale_cost_amount'] = $val['aftersale_cost_amount'];//$product_info[$val['bn']]['aftersale_cost_amount'];
                }else{
                    $aftersale['aftersale_cost_amount'] = 0;
                }

                $aftersale['aftersale_cost_amount'] = ($aftersale['aftersale_cost_amount'] * $row[0]['total_reship_num']);
                $rows[$key]['name'] = $rows[$key]['name'];
                $rows[$key]['reship_num'] = intval($row[0]['total_reship_num']);//退货数
                $reship_ratio = $rows[$key]['sale_num']?round($rows[$key]['reship_num']/$rows[$key]['sale_num'],2):0;//退货率
                $rows[$key]['reship_ratio'] = ($reship_ratio*100)."%";
                $rows[$key]['agv_cost_amount'] = round($rows[$key]['cost_amount']/$rows[$key]['sale_num'],2);//平均成本
                $rows[$key]['total_cost_amount'] = round($rows[$key]['cost_amount'] - $aftersale['aftersale_cost_amount'],2);//总成本 = 销售成本-售后商品成本之和
                $rows[$key]['gross_sales'] = $rows[$key]['sale_amount'] - $rows[$key]['reship_total_amount']-$rows[$key]['total_cost_amount'];//销售毛利 = 销售额-退货总额-总成本
                $rows[$key]['agv_gross_sales'] = round($rows[$key]['gross_sales']/$rows[$key]['sale_num'],2);//销售平均毛利 = 销售毛利/销售量
                $gross_sales_rate = ($rows[$key]['sale_amount']>0) ? round($rows[$key]['gross_sales']/$rows[$key]['sale_amount'],2) : 0;//销售毛利率 = 销售毛利/销售额
                $rows[$key]['gross_sales_rate'] = ($gross_sales_rate*100)."%";
                $rows[$key]['sale_price'] = strval($rows[$key]['sale_amount']/$rows[$key]['sale_num']);//销售单价 = 商品销售之和/销售量
                //获取order_bn
                // $sql_order_bn = "select order_bn from sdb_ome_orders where order_id = ".$rows[$key]['order_id']." limit 1";
                // $rs_order_bn = $this->db->select($sql_order_bn);
                // $rows[$key]['order_bn'] = $rs_order_bn[0]["order_bn"];

                // 基础分类
                $rows[$key]['category_id'] = $this->_getCategory($val['product_id'], $rows);
            }

             $createtime = time();
             //对数组排序
             if($orderType){
                $reship_ratio = $gross_sales_rate = [];
                foreach($rows as $k=>$data){
                    $type_id[$k] = $data['type_id'];
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
                    if(${$arr[0]}) {
                        if(strtolower($arr[1]) == 'desc'){
                            array_multisort(${$arr[0]},SORT_DESC,$rows);
                        }
                        else{
                            array_multisort(${$arr[0]},SORT_ASC,$rows);
                        }
                    }
                }
             }
        }

        return $rows;
    }

    public function rfilter($filter){
        $where = array(1);
        #店铺
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    $where[] = ' sa.shop_id in(\''.implode("','", $shopIds).'\')';
                }
            } else {
                $where[] = ' sa.shop_id =\''.addslashes($filter['shop_id']).'\'';
            }
        }

        if(isset($filter['time_from']) && $filter['time_from']){
            $where[] = ' sa.ship_time >='.strtotime($filter['time_from']);
        }
        
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " sa.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }
        
        if(isset($filter['time_to']) && $filter['time_to']){
            $where[] = ' sa.ship_time <='.strtotime($filter['time_to']);
        }

        $where[] = ' sai.return_type = "return"';
        return implode(' AND ', $where);
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        $config = app::get('eccommon')->getConf('analysis_config');

        $filter['order_status'] = $config['filter']['order_status'];
        $where = array(1);
        $itemsid = array();

        #店铺
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    $where[] = ' S.shop_id in(\''. implode("','", $shopIds) .'\')';
                }
            } else {
                $where[] = ' S.shop_id =\''.addslashes($filter['shop_id']).'\'';
            }
        }

        if (isset($filter['shop_type']) && $filter['shop_type']){
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            $shop_ids = $shopList[$filter['shop_type']];

            if ($shop_ids){
                $where[] = " S.shop_id in ('".implode('\',\'',$shop_ids)."')";
            }
        }
        #货号
        if(isset($filter['bn']) && $filter['bn']){
            $where[] = ' SI.bn LIKE \''.addslashes($filter['bn']).'%\'';
            $_SESSION['bn'] = $filter['bn'];
        }else{
            unset($_SESSION['bn']);
        }
        
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " S.org_id in ('".implode('\',\'',$filter['org_id'])."')";
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
                $ftime = $time_from;
            }

            if(isset($filter['time_to']) && $filter['time_to']){

                $time_to = ' S.'.$time_filter.' <='.strtotime($filter['time_to']);
                $where[] = $time_to;
                $ftime .= ' AND '.$time_to;
            }
        }else{
            $config['filter']['order_status'] = 'ship';
            app::get('eccommon')->setConf('analysis_config', $config);
            $time_filter = 'ship_time';
        }

        #查询销售额
        if(isset($filter['_sale_amount_search']) && is_numeric($filter['sale_amount'])){
            switch ($filter['_sale_amount_search']){
                case 'than': $_sql =   ' group by SI.bn HAVING  sale_amount >'.$filter['sale_amount'];break;
                case 'lthan': $_sql =  ' group by SI.bn HAVING  sale_amount <'.$filter['sale_amount'];break;
                case 'nequal': $_sql = ' group by SI.bn HAVING  sale_amount ='.$filter['sale_amount'];break;
                case 'sthan': $_sql =  ' group by SI.bn HAVING  sale_amount <='.$filter['sale_amount'];break;
                case 'bthan': $_sql =  ' group by SI.bn HAVING  sale_amount >='.$filter['sale_amount'];break;
                case 'between':
                    if($filter['sale_amount_from'] && $filter['sale_amount_to'] ){
                        $_sql = ' group by SI.bn HAVING  (sale_amount  >='.$filter['sale_amount_from'].' and sale_amount < '.$filter['sale_amount_to'].')';
                    }else{
                        $_sql = '';
                    }
                    break;
            }
        }

        #查询销售量
        if(isset($filter['_sale_num_search']) && is_numeric($filter['sale_num'])){
            if(isset($filter['_sale_amount_search']) && is_numeric($filter['sale_amount'])){
                $_sql = $_sql.' and ';
            }else{
                $_sql = ' group by SI.bn HAVING ';
            }
            switch ($filter['_sale_num_search']){
                case 'than': $_sql =   $_sql.' sale_num >'.$filter['sale_num'];break;
                case 'lthan': $_sql =  $_sql.' sale_num <'.$filter['sale_num'];break;
                case 'nequal': $_sql = $_sql.' sale_num ='.$filter['sale_num'];break;
                case 'sthan': $_sql =  $_sql.' sale_num <='.$filter['sale_num'];break;
                case 'bthan': $_sql =  $_sql.' sale_num >='.$filter['sale_num'];break;
                case 'between':
                    if($filter['sale_num_from'] && $filter['sale_num_to'] ){
                        $_sql = $_sql.'(sale_num  >='.$filter['sale_num_from'].' and sale_num < '.$filter['sale_num_to'].')';
                    }else{
                        $_sql = '';
                    }
                 break;
             }
         }
         
        //  if(isset($filter['order_bn']) && $filter['order_bn']){
        //      $orderObj = app::get('ome')->model("orders");
        //      $rows = $orderObj->getList('order_id',array('order_bn|has'=>$filter['order_bn']));
        //      if(!empty($rows)){
        //          $orderIds = array();
        //          foreach($rows as $row){
        //              $orderIds[] = $row['order_id'];
        //          }
        //          $where[] = ' S.order_id IN ('.implode(',', $orderIds).')';
        //      }else{
        //          $where[] = " 1=0 "; //没有匹配到不显示数据
        //      }
        // }

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

    public function get_schema(){
        $schema = array (
            'columns' => array (
                'type_id' => array (
                    'type' => 'varchar(50)',
                    'label' => '类型',
                    'width' => 110,
                    'editable' => false,
                    'in_list' => false,
                    'default_in_list' => false,
                    'order'=>1,
                    'orderby' => true,
                    'realtype' => 'varchar(200)',
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
                    #'searchtype' => 'has',
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
                    //'filtertype' => 'number',
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
                    //'filtertype' => 'number',
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
                    //'filtertype' => 'normal',
                    //'filterdefault' => 'true',
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
                    //'filtertype' => 'yes',
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
                    //'filtertype' => 'time',
                    //'filterdefault' => true,
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
                   // 'filtertype' => 'yes',
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
                    //'filtertype' => 'number',
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
                    //'filtertype' => 'number',
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
                   // 'filtertype' => 'number',
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
                    //'filtertype' => 'number',
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
                    //'filtertype' => 'number',
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
                    //'filtertype' => 'yes',
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
                   // 'filtertype' => 'yes',
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
                    //'filtertype' => 'number',
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>22,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'cat_id' => array (
                    'type' => 'varchar(200)',
                    'default' => 0,
                    'required' => true,
                    'label' => '物料类型',
                    'width' => 110,
                    'orderby' => false,
                    'editable' => false,
                    //'filtertype' => 'number',
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>23,
                    'realtype' => 'mediumint(8) unsigned',
                ),
                'org_name' => array (
                    'type' => 'varchar(200)',
                    'label' => '运营组织',
                    'width' => 110,
                    'orderby' => false,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>24,
                ),
                'category_id' => array (
                    'type' => 'varchar(200)',
                    'default' => 0,
                    'label' => '物料分类',
                    'width' => 240,
                    'orderby' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order'=>23,
                ),
                // 'order_bn' => array (
                //     'type' => 'varchar(50)',
                //     'label' => '订单号',
                //     'editable' => false,
                //     'searchtype' => 'has',
                //     'filtertype' => 'normal',
                //     'filterdefault' => true,
                //     'width' =>130,
                //     'in_list' => true,
                //     'default_in_list' => true,
                // ),
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
                    'type' => 'table:brand@ome',
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
                //0 => 'type_id',
                //1 => 'brand',
                //2 => 'goods_bn',
                3 => 'bn',
                4 => 'name',
                //5 => 'goods_specinfo',
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
                19 => 'cat_id',
                // 20 => 'order_bn',
                21 => 'shop_id',
                22=>'shop_type',
                23=>'category_id',
                24 => 'brand_id',
                25 => 'org_name',
            ),
            'default_in_list' => array (
                //0 => 'type_id',
                //1 => 'brand',
                //2 => 'goods_bn',
                3 => 'bn',
                4 => 'name',
                //5 => 'goods_specinfo',
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
                19 => 'cat_id',
                // 20 => 'order_bn',
                21 => 'shop_id',
                22=>'shop_type',
                23=>'category_id',
                24 => 'brand_id',
                25 => 'org_name',
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
    public function importLogType($logParams) {
        $params = $logParams['params'];
        $type = 'report';
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_analysis') {
            $type .= '_salesReport_posSales';
        }
        $type .= '_import';
        return $type;
    }

    //根据查询条件获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id){
        //根据选择的字段定义导出的第一行标题
        if($curr_sheet == 1){
            $title = array();
            $main_columns = $this->get_schema();
            foreach( explode(',', $fields) as $k => $col ){
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
        
        foreach ($productssale as $k => $aFilter) {
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
     * undocumented function
     *
     * @return void
     * @author 
     **/
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
}
