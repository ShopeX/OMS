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
class omeanalysts_mdl_ome_branchdelivery extends dbeav_model{

    var $has_export_cnf = true;

    var $export_name = '仓库发货情况汇总';

    public function get_count($filter=null){
        
        $sales_sql ='
         select sum(items.number) as total_sales from sdb_ome_delivery delivery 
         left join sdb_ome_delivery_items items  on delivery.delivery_id = items.delivery_id
         left join sdb_material_basic_material p on p.bm_id = items.product_id 
         where '.$this->_newFilter($filter);
        $salesdata = $this->db->select($sales_sql);

        $aftersale_sql = 'select sum(AI.num) as total_aftersales from sdb_sales_aftersale_items AI left join sdb_sales_aftersale A on AI.aftersale_id = A.aftersale_id where '.$this->_rfilter($filter);

        $aftersaledata = $this->db->select($aftersale_sql);

        return array(
            'total_sales' => $salesdata[0]['total_sales']?$salesdata[0]['total_sales']:0,
            'total_aftersales' => $aftersaledata[0]['total_aftersales']?$aftersaledata[0]['total_aftersales']:0,
        );
    }

    public function count($filter=null){
       
        return count($this->getList('*',$filter));
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $bmExtObj = app::get('material')->model('basic_material_ext');
        
        //商品品牌
        $brandList    = array();
        $oBrand       = app::get('ome')->model('brand');
        $tempData     = $oBrand->getList('brand_id, brand_name', '', 0, -1);
        foreach ($tempData as $key => $val)
        {
            $brandList[$val['brand_id']]    = $val['brand_name'];
        }
        
        //商品类型
        $goodsTypeList    = array();
        $oType        = app::get('ome')->model('goods_type');
        $tempData     = $oType->getList('type_id, name', '', 0, -1);
        foreach ($tempData as $key => $val)
        {
            $goodsTypeList[$val['type_id']]    = $val['name'];
        }
        unset($tempData, $oBrand, $oType);
        
        //sales
        $sale_sql ='
         select
              items.product_id,items.bn,p.material_name AS name,delivery.branch_id,delivery.shop_id,sum(items.number) nums,
               p.bm_id, g.specifications, g.brand_id, g.cat_id 
         from sdb_ome_delivery delivery
         left join sdb_ome_delivery_items items  on delivery.delivery_id = items.delivery_id
         left join sdb_material_basic_material p on p.bm_id = items.product_id 
         LEFT JOIN sdb_material_basic_material_ext AS g ON p.bm_id=g.bm_id 
         where '.$this->_newFilter($filter).' group by items.bn, delivery.shop_id ';
        
        $orderType = preg_replace('/`branch_name`/', 'delivery.branch_id', $orderType);
        $orderType = preg_replace('/`sale_num`/', 'nums', $orderType);
        $orderType = preg_replace('/`shop_type`/', 'delivery.shop_type', $orderType);
        if($orderType) $sale_sql .= ' order by '.(is_array($orderType)?implode($orderType,' '):$orderType);
        
        $sale_datas = $this->db->selectlimit($sale_sql,$limit,$offset);
        
        //list
        $rowdatas = array();
        $productIds = array();
        $md5List = array();
        foreach ($sale_datas as $k => $v)
        {
            $md_key =  md5($v['shop_id'].'-'.$v['branch_id'].'-'.$v['bn']).'sale';
            
            //md5
            $md5List[$md_key] = $md_key;
            
            //product_id
            $product_id = $v['product_id'];
            $productIds[$product_id] = $product_id;
            
            $rowdatas[$md_key]['branch_name']  = $v['branch_id'];
            $rowdatas[$md_key]['product_bn']   = $v['bn'];
            $rowdatas[$md_key]['product_name'] = $v['name'];
            $rowdatas[$md_key]['sale_num']     = $v['nums'];
            $rowdatas[$md_key]['shop_id']      = $v['shop_id'];
            $rowdatas[$md_key]['shop_type']    = kernel::single('omeanalysts_shop')->getShopDetail($v['shop_id']);
            
            //基础物料扩展信息
            $rowdatas[$md_key]['goods_specinfo']    = $v['specifications'];
            $rowdatas[$md_key]['goods_type']        = $goodsTypeList[$v['cat_id']];
            $rowdatas[$md_key]['brand_name']        = $brandList[$v['brand_id']];
        }
        unset($sale_datas);
        
        //获取售后单退货数量
        $filter['productIds'] = $productIds;
        $aftersale_sql = 'select AI.bn,AI.product_id,sum(AI.num) as num,AI.branch_id,A.shop_id from sdb_sales_aftersale_items AI left join sdb_sales_aftersale A on AI.aftersale_id =  A.aftersale_id
                          where '.$this->_rfilter($filter).' group by AI.bn,AI.branch_id ,A.shop_id ';
        
        // if($orderType) $aftersale_sql .= ' order by '.(is_array($orderType)?implode($orderType,' '):$orderType);

        $aftersale_datas = $this->db->selectlimit($aftersale_sql,$limit,$offset);
        foreach ($aftersale_datas as $k => $v)
        {
            $md_key =  md5($v['shop_id'].'-'.$v['branch_id'].'-'.$v['bn']).'sale';
            
            //check必须以销售单数量为准,防止数量比实际查询数量多,无法导出
            if(empty($md5List[$md_key])){
                continue;
            }
            
            //基础物料扩展信息
            $bmExtInfo    = $bmExtObj->dump(array('bm_id'=>$v['product_id']), 'specifications, brand_id, cat_id');
            if($bmExtInfo)
            {
                $rowdatas[$md_key]['goods_specinfo']    = $bmExtInfo['specifications'];
                $rowdatas[$md_key]['goods_type']        = $goodsTypeList[$bmExtInfo['cat_id']];
                $rowdatas[$md_key]['brand_name']        = $brandList[$bmExtInfo['brand_id']];
            }
            
            $rowdatas[$md_key]['branch_name']   = $v['branch_id'];
            $rowdatas[$md_key]['product_bn']    = $v['bn'];
            // product_name 保持来自 sale_datas（sdb_material_basic_material），不覆盖
            $rowdatas[$md_key]['aftersale_num'] = $v['num'];
            $rowdatas[$md_key]['shop_id']       = $v['shop_id'];
            $rowdatas[$md_key]['shop_type']    = kernel::single('omeanalysts_shop')->getShopDetail($v['shop_id']);       
        }
        unset($aftersale_datas, $md5List);
        
        $i = 0;
        $rows = array();
        foreach ($rowdatas as $v)
        {
            $rows[$i]['branch_name']     = $v['branch_name']?$v['branch_name']:'-';
            $rows[$i]['goods_type']      = $v['goods_type']?$v['goods_type']:'-';
            $rows[$i]['brand_name']      = $v['brand_name']?$v['brand_name']:'-';
            
            $rows[$i]['goods_specinfo']  = $v['goods_specinfo']?$v['goods_specinfo']:'-';
            $rows[$i]['product_bn']      = $v['product_bn']?$v['product_bn']:'-';
            $rows[$i]['product_name']    = $v['product_name']?$v['product_name']:'-';
            $rows[$i]['sale_num']        = $v['sale_num']?$v['sale_num']:0;
            $rows[$i]['aftersale_num']   = $v['aftersale_num']?$v['aftersale_num']:0;
            $rows[$i]['shop_id']         = $v['shop_id']?$v['shop_id']:'-'; 
            $rows[$i]['total_nums']      = $v['sale_num'] - $v['aftersale_num'];
            $rows[$i]['shop_type']       = $v['shop_type'];
            
            $i++;
        }
        
        return $rows;
    }
    public function _newFilter($filter){ 
        #$where = array();
        #已发货的基础过滤条件
        $where[] = ' delivery.status=\'succ\'';
        $where[] = 'delivery.type=\'normal\'';
        $where[] = 'delivery.pause=\'FALSE\'';
        $where[] = 'delivery.parent_id=\'0\'';
        $where[] =' delivery.disabled=\'false\''; 
        
        #店铺
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    // 对数组中每个元素进行 addslashes 转义
                    $escapedShopIds = array_map('addslashes', $shopIds);
                    $where[] = ' delivery.shop_id IN (\'' . implode("','", $escapedShopIds) . '\')';
                }
            } else {
                $where[] = ' delivery.shop_id =\''.addslashes($filter['shop_id']).'\'';
            }
        }

        if(isset($filter['own_branches']) && $filter['own_branches']){
            $where[]= ' delivery.branch_id in ('.implode(',',$filter['own_branches']).')';
        }
        unset($filter['own_branches']);

        #仓库
        if(isset($filter['branch_id']) && $filter['branch_id']){
            $where[] = ' delivery.branch_id = '.addslashes($filter['branch_id']);
        }
        #货号       
        if(isset($filter['product_bn']) && $filter['product_bn']){
            $where[] = ' items.bn =\''.addslashes($filter['product_bn']).'\'';
        } 
        #时间
        if(isset($filter['time_from']) && $filter['time_from']){
            $time_from = ' delivery.delivery_time >='.strtotime($filter['time_from']);
            $where[] = $time_from;
        }
        if(isset($filter['time_to']) && $filter['time_to']){
            $time_to = ' delivery.delivery_time <='.strtotime($filter['time_to'].' 23:59:59');
            $where[] = $time_to;
        }
        
        #基础物料品牌
        if(isset($filter['brand_id']) && $filter['brand_id']){
            $where[]= ' g.brand_id = '.$filter['brand_id'];
        }
        
        #基础物料类型
        if(isset($filter['goods_type_id']) && $filter['goods_type_id']){
            $where[]= '  g.cat_id = '.$filter['goods_type_id'];
        }
        
        if (isset($filter['shop_type']) && $filter['shop_type']){
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            $shop_ids = $shopList[$filter['shop_type']];

            if ($shop_ids){
                $where[] = " delivery.shop_id in ('".implode('\',\'',$shop_ids)."')";
            }
        }
        
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " delivery.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }
        
        return  implode(' AND ', $where);
    }

    public function _sfilter($filter){
        
        $where = array();

        #店铺
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    // 对数组中每个元素进行 addslashes 转义
                    $escapedShopIds = array_map('addslashes', $shopIds);
                    $where[] = ' S.shop_id IN (\'' . implode("','", $escapedShopIds) . '\')';
                }
            } else {
                $where[] = ' S.shop_id =\''.addslashes($filter['shop_id']).'\'';
            }
        }
        
        if(isset($filter['branch_id']) && $filter['branch_id']){
            $where[] = ' S.branch_id = '.addslashes($filter['branch_id']);
        }

        if(isset($filter['product_bn']) && $filter['product_bn']){
            $where[] = ' SI.bn =\''.addslashes($filter['product_bn']).'\'';
        }

        if(isset($filter['time_from']) && $filter['time_from']){
            $time_from = ' S.sale_time >='.strtotime($filter['time_from']);
            $where[] = $time_from;
            $ftime = $time_from;
        }

        if(isset($filter['time_to']) && $filter['time_to']){

            $time_to = ' S.sale_time <='.strtotime($filter['time_to'].' 23:59:59');
            $where[] = $time_to;
            $ftime .= ' AND '.$time_to;
        }
        

        $_where = '1';
        $filter_sql = false;

        #基础物料品牌
        if(isset($filter['brand_id']) && $filter['brand_id']){
            $_where .= ' and g.brand_id = '.$filter['brand_id'];
            $filter_sql = true;
        }

        #基础物料类型
        if(isset($filter['goods_type_id']) && $filter['goods_type_id']){
            $_where .= ' and g.cat_id = '.$filter['goods_type_id'];
            $filter_sql = true;
        }
        
        
        if($filter_sql){
            $sql = "select si.bn from sdb_ome_sales_items si, 
                    p.bm_id, g.specifications, g.brand_id, g.cat_id 
                left join sdb_ome_sales s on si.sale_id = s.sale_id
                left join sdb_material_basic_material p on si.bn = p.material_bn 
                LEFT JOIN sdb_material_basic_material_ext AS g ON p.bm_id=g.bm_id 
                where ".$_where." and s.sale_time >=".strtotime($filter['time_from'])." and s.sale_time <=".strtotime($filter['time_to'].' 23:59:59');
            $query = $this->db->select($sql);

            if($query){
                foreach($query as $qu){
                    $sale_bns[] = "'".$qu['bn']."'";
                }
                $where[] = " SI.bn IN (".implode(',',$sale_bns).")";
            }else{
                $where[] = " 1=0 ";
            }

        }
        
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " S.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }


        return implode($where,' AND ');
    }

    
    public function _rfilter($filter){
        
        $where = array();

        #店铺
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    // 对数组中每个元素进行 addslashes 转义
                    $escapedShopIds = array_map('addslashes', $shopIds);
                    $where[] = ' A.shop_id IN (\'' . implode("','", $escapedShopIds) . '\')';
                }
            } else {
                $where[] = ' A.shop_id =\''.addslashes($filter['shop_id']).'\'';
            }
        }
        
        if(isset($filter['own_branches']) && $filter['own_branches']){
            $where[]= ' AI.branch_id in ('.implode(',',$filter['own_branches']).')';
        }
        unset($filter['own_branches']);

        if(isset($filter['branch_id']) && $filter['branch_id']){
            $where[] = ' AI.branch_id = '.addslashes($filter['branch_id']);
        }
        
        //[注意]使用销售单明细中的product_id商品进行精准查询
        if($filter['productIds'] && is_array($filter['productIds'])){
            $where[] = ' AI.product_id IN('. implode(',', $filter['productIds']) .')';
        }elseif(isset($filter['product_bn']) && $filter['product_bn']){
            $where[] = ' AI.bn =\''.addslashes($filter['product_bn']).'\'';
        }

        if(isset($filter['time_from']) && $filter['time_from']){
            $time_from = ' A.aftersale_time >='.strtotime($filter['time_from']);
            $where[] = $time_from;
            $ftime = $time_from;
        }

        if(isset($filter['time_to']) && $filter['time_to']){

            $time_to = ' A.aftersale_time <='.strtotime($filter['time_to'].' 23:59:59');
            $where[] = $time_to;
            $ftime .= ' AND '.$time_to;
        }

        $_where = '1';
        $filter_sql = false;

        #基础物料品牌
        if(isset($filter['brand_id']) && $filter['brand_id']){
            $_where .= ' and g.brand_id = '.$filter['brand_id'];
            $filter_sql = true;
        }

        #基础物料类型
        if(isset($filter['goods_type_id']) && $filter['goods_type_id']){
            $_where .= ' and g.cat_id = '.$filter['goods_type_id'];
            $filter_sql = true;
        }
        
        if($filter_sql){
            $sql = "select AI.bn,p.bm_id, g.specifications, g.brand_id, g.cat_id from sdb_sales_aftersale_items AI 
                left join sdb_sales_aftersale A on AI.aftersale_id = A.aftersale_id
                left join sdb_material_basic_material p on AI.bn = p.material_bn 
                LEFT JOIN sdb_material_basic_material_ext AS g ON p.bm_id=g.bm_id 
                where ".$_where." and A.aftersale_time >=".strtotime($filter['time_from'])." and A.aftersale_time <=".strtotime($filter['time_to'].' 23:59:59');
            $query = $this->db->select($sql);
            if($query){
                foreach($query as $qu){
                    $afersale_bns[] = "'".$qu['bn']."'";
                }
                $where[] = " AI.bn IN (".implode(',',$afersale_bns).")";
            }else{
                $where[] = " 1=0 ";
            }
        }
        
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " A.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }

        $where[] = 'AI.return_type in("return","refuse")';

        return implode(' AND ', $where);
    } 

    private function get_productinfo($bn,$all_bns,$product_info,&$data){
        if(in_array($bn,$all_bns)){
            $data['goods_type'] = $product_info[$bn]['goods_type'];
            $data['brand_name'] = $product_info[$bn]['brand_name'];
            $data['goods_specinfo'] = $product_info[$bn]['spec_info'];
            
        }else{
                $data['goods_specinfo'] = '-';
                $data['goods_type'] = '系统不存在此货号';
                $data['brand_name'] = '-';
        }
    }

    public function io_title( $ioType='csv' ){
    
        switch( $ioType ){
            case 'csv':
                $this->oSchema['csv']['main'] = array(
                    '*:发货仓库' => 'branch_name', 
                    '*:商品类型' => 'goods_type',
                    '*:品牌'     => 'brand_name',
                    '*:基础物料编码'     => 'product_bn',
                    '*:基础物料名称' => 'product_name',
                    '*:商品规格' => 'goods_specinfo',
                    '*:销售数量' => 'sale_num',
                    '*:退货数量' => 'aftersale_num',
                    '*:店铺名称' => 'shop_id',
                    '*:合计数量' => 'total_nums',
                );
            break;
        }
        $this->ioTitle[$ioType] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType];
    }
    
    public function export_csv($data){
        $output = array();
        $output[] = $data['title']['branchdelivery']."\n".implode("\n",(array)$data['content']['branchdelivery']);
        echo implode("\n",$output);
    }

    public function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){

        @ini_set('memory_limit','64M');

        if( !$data['title']['branchdelivery']){
            $title = array();
            foreach( $this->io_title('csv') as $k => $v ){
                $title[] = $v;
            }
            $data['title']['branchdelivery'] = mb_convert_encoding('"'.implode('","',$title).'"', 'GBK', 'UTF-8');
        }

        $limit = 100;
        
        if( !$list=$this->getlist('*',$filter,$offset*$limit,$limit) ) return false;
        
        $branchdeliveryRow = array();
        
        $Oshop = app::get('ome')->model('shop');

        $shops = $Oshop->getList('name,shop_id');
        
        foreach ($shops as $v) {
            $shop[$v['shop_id']] = $v['name'];
        }
        
        unset($shops);

        $Obranch = app::get('ome')->model('branch');

        $branchs = $Obranch->getList('branch_id,name',array('is_deliv_branch'=>'true'));
        
        foreach ($branchs as $v) {
            $branch[$v['branch_id']] = $v['name'];
        }
        
        unset($branchs);


        foreach( $list as $aFilter ){

            $branchdeliveryRow['*:发货仓库'] = $branch[$aFilter['branch_name']];
            $branchdeliveryRow['*:商品类型'] = $aFilter['goods_type'];
            $branchdeliveryRow['*:品牌']     = $aFilter['brand_name'];
            $branchdeliveryRow['*:货号']     = $aFilter['product_bn'];
            $branchdeliveryRow['*:货品名称'] = $aFilter['product_name'];
            $branchdeliveryRow['*:商品规格'] = $aFilter['goods_specinfo'];
            $branchdeliveryRow['*:销售数量'] = $aFilter['sale_num'];
            $branchdeliveryRow['*:退货数量'] = $aFilter['aftersale_num'];
            $branchdeliveryRow['*:店铺名称'] = $shop[$aFilter['shop_id']];
            $branchdeliveryRow['*:合计数量'] = $aFilter['total_nums'];

            $data['content']['branchdelivery'][] = mb_convert_encoding('"'.implode('","',$branchdeliveryRow).'"', 'GBK', 'UTF-8');
        }

        $data['name'] = $this->export_name.date("YmdHis");

        return true;
    }

    public function exportName(&$data){
        $data['name'] = $_POST['time_from'].'到'.$_POST['time_to'].$this->export_name;
    }


    public function get_schema(){

        $schema = array (
            'columns' => array (
                'shop_type'=>array(
                    'type' => 'varchar(32)',
                    'label' => '店铺类型',
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => '70'
                ), 
                'branch_name' =>
                array(
                  'type' => 'table:branch@ome',
                  'editable' => false,
                  'label'=>'发货仓库',
                  'order' => 1,
                ),
                'goods_type' =>
                array(
                    'type' => 'table:goods_type@ome',
                    'label' => '商品类型',
                    'width' => 130,
                    'order' => 2,
                    'orderby' => false,
                ),
                'brand_name' =>
                array(
                    'type' => 'table:brand@ome',
                    'label' => '品牌',
                    'width' => 130,
                    'order' => 3,
                    'orderby' => false,
                ),
                'product_bn' =>
                array(
                    'type' => 'varchar(30)',
                    'label' => '基础物料编码',
                    'width' => 130,
                    'order' => 5,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'searchtype' => 'has',
            'orderby' => false,
                ),
                'product_name' =>
                array(
                    'type' => 'varchar(200)',
                    'label' => '基础物料名称',
                    'width' => 130,
                    'order' => 6,
            'orderby' => false,
                ),
                'goods_specinfo'=>
                array(
                    'type' => 'varchar(200)',
                    'label' => '商品规格',
                    'width' => 130,
                    'order' => 7,
            'orderby' => false,
                ),
                'sale_num' =>
                array(
                  'type' => 'number',
                  'label' => '销售数量',
                  'width' => 100,
                  'order' => 8,
                ),                               
                'aftersale_num' =>
                array(
                  'type' => 'number',
                  'label' => '退货数量',
                  'width' => 100,
                  'order' => 9,    
            'orderby' => false,
                ),
                'shop_id' =>
                array(
                  'type'  => 'table:shop@ome',
                  'label' => '店铺名称',
                  'width' => 120,
                  'order' => 10,
            'orderby' => false,
                ),
                'total_nums' =>
                array(
                  'type'  => 'number',
                  'label' => '合计数量',
                  'width' => 120,
                  'order' => 11,
            'orderby' => false,
                ),                                     
            ),
            'in_list' => array(
                0 => 'branch_name',
                1 => 'goods_type',
                2 => 'brand_name',
                4 => 'product_bn',
                5 => 'product_name',
                6 => 'goods_specinfo',        
                7 => 'sale_num',
                8 => 'aftersale_num',
                9 => 'shop_id',
                10 => 'total_nums',
                11=>'shop_type',
            ),
            'default_in_list' => array(
                0 => 'branch_name',
                1 => 'goods_type',
                2 => 'brand_name',
                4 => 'product_bn',
                5 => 'product_name',
                6 => 'goods_specinfo',        
                7 => 'sale_num',
                8 => 'aftersale_num',
                9 => 'shop_id',
                10 => 'total_nums',
                11=>'shop_type',
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
            $type .= '_purchaseReport_branchDeliveryAnalysis';
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
            $type .= '_purchaseReport_branchDeliveryAnalysis';
        }
        $type .= '_import';
        return $type;
    }

    //根据查询条件获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id){

        //根据选择的字段定义导出的第一行标题
        if($curr_sheet == 1){
            $data['content']['main'][] = $this->getExportTitle($fields);
        }

        if( !$list=$this->getlist('*',$filter,$start,$end) ) return false;
        
        $branchdeliveryRow = array();
        $Oshop = app::get('ome')->model('shop');
        $shops = $Oshop->getList('name,shop_id');
        foreach ($shops as $v) {
            $shop[$v['shop_id']] = $v['name'];
        }
        unset($shops);

        $Obranch = app::get('ome')->model('branch');
        $branchs = $Obranch->getList('branch_id,name',array('is_deliv_branch'=>'true'));
        foreach ($branchs as $v) {
            $branch[$v['branch_id']] = $v['name'];
        }
        unset($branchs);

        foreach( $list as $aFilter ){
            $branchdeliveryRow['branch_name'] = $branch[$aFilter['branch_name']];
            $branchdeliveryRow['goods_type'] = $aFilter['goods_type'];
            $branchdeliveryRow['brand_name']     = $aFilter['brand_name'];
            
            $branchdeliveryRow['product_bn']     = $aFilter['product_bn'];
            $branchdeliveryRow['product_name'] = $aFilter['product_name'];
            $branchdeliveryRow['goods_specinfo'] = $aFilter['goods_specinfo'];
            $branchdeliveryRow['sale_num'] = $aFilter['sale_num'];
            $branchdeliveryRow['aftersale_num'] = $aFilter['aftersale_num'];
            $branchdeliveryRow['shop_id'] = $shop[$aFilter['shop_id']];
            $branchdeliveryRow['total_nums'] = $aFilter['total_nums'];

            $exptmp_data = array();
            foreach (explode(',', $fields) as $key => $col) {
                if(isset($branchdeliveryRow[$col])){
                    //过滤地址里的特殊字符
                    $branchdeliveryRow[$col] = str_replace('&nbsp;', '', $branchdeliveryRow[$col]);
                    $branchdeliveryRow[$col] = str_replace(array("\r\n","\r","\n"), '', $branchdeliveryRow[$col]);
                    $branchdeliveryRow[$col] = str_replace(',', '', $branchdeliveryRow[$col]);

                    $branchdeliveryRow[$col] = mb_convert_encoding($branchdeliveryRow[$col], 'GBK', 'UTF-8');
                    $exptmp_data[] = $branchdeliveryRow[$col];
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
