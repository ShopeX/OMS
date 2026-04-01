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
 * 仓库货品数据类
 * 
 * @date 2012-08-15
 * @version hanbingshu sanow@126.com
 */
class tgstockcost_taog_branchproduct extends tgstockcost_common_branchproduct implements tgstockcost_interface_branch
{
    /**
     * 期初
     * 
     * @param unknown $date
     * @param unknown $branch_product 仓库商品
     * @return unknown
     */
    private function get_dailystock_data($date,$branch_product)
    {
        $data = array();

        $dailystockModel = app::get('ome')->model('dailystock');
        $db = $dailystockModel->db;

        $sql = 'SELECT * FROM ' . $dailystockModel->table_name(1) . ' WHERE 1 ';
        
        $filter = array();
        $filter[] = 'stock_date="'.$date.'"';

        if ($branch_product) {
            $branch_product_filter = array();
            foreach ($branch_product as $key => $value) {
                $branch_product_filter[] = 'branch_id=' . $value['branch_id'] . ' AND product_id=' . $value['product_id'];
            }

            if ($branch_product_filter) {
                $filter[] = '((' . implode(') OR (' , $branch_product_filter) . '))';
            }

            unset($branch_product_filter);
        }
        
        $sql .= ' AND ' . implode(' AND ', $filter);

        $data = $db->select($sql);

        return $data;
    }

    /**
     * 获取出入库明细
     *
     * @param Int $start_time 开始时间
     * @param Int $end_time 结束时间
     * @param Array $branch_product 仓库商品
     * @param Int $io 出入库类型
     * @return void
     * @author 
     **/
    private function get_iostock_data($start_time,$end_time,$branch_product,$io,$type_id = NULL)
    {
        $data = array();

        $stockcost_common_iostockrecord = kernel::single("tgstockcost_taog_iostockrecord");
        $io_type = $stockcost_common_iostockrecord->get_type_id($io);

        $iostockModel = app::get('ome')->model('iostock');
        $db = $iostockModel->db;

        // 静态变量缓存索引检查结果（只检查一次，提升性能）
        static $index_exists = null;
        $table_name = $iostockModel->table_name(1);
        
        if ($index_exists === null) {
            // 只检查一次索引是否存在（使用 SHOW INDEX 更可靠）
            try {
                $index_list = $db->select("SHOW INDEX FROM " . $table_name . " WHERE Key_name = 'idx_typeid_branchid_bn_createtime'");
                $index_exists = !empty($index_list);
            } catch (Exception $e) {
                // 如果查询失败（表不存在等），认为索引不存在
                $index_exists = false;
            }
        }
        
        if ($index_exists) {
            // 索引存在，强制使用优化索引（已检查过索引存在，不会报错）
            $sql = 'SELECT SUM(nums) AS nums,SUM(inventory_cost) AS inventory_cost,branch_id,bn FROM ' . $table_name . ' FORCE INDEX (idx_typeid_branchid_bn_createtime) WHERE 1 ';
        } else {
            // 索引不存在，让 MySQL 自动选择
            $sql = 'SELECT SUM(nums) AS nums,SUM(inventory_cost) AS inventory_cost,branch_id,bn FROM ' . $table_name . ' WHERE 1 ';
        }

        $filter = array();
        
        // 按照索引 idx_typeid_branchid_bn_createtime 的顺序：type_id -> branch_id -> bn -> create_time
        // 1. type_id 条件（等值查询，索引第1列）
        if ($type_id) {
            // 如果指定了 type_id，直接使用它（更精确）
            $filter[] = 'type_id='.$type_id;
        } else {
            // 否则使用 io_type 数组
            $filter[] = 'type_id in(' . implode(',',(array) $io_type ) . ')';
        }

        // 2. branch_id 和 bn 条件（等值查询，索引第2、3列）
        // 优化：将多个 OR 条件改为按 branch_id 分组的 IN 查询，提升性能
        if ($branch_product) {
            // 按 branch_id 分组，收集每个 branch_id 对应的 bn 列表
            $branch_bn_map = array();
            foreach ($branch_product as $key => $value) {
                $bid = intval($value['branch_id']);
                if (!isset($branch_bn_map[$bid])) {
                    $branch_bn_map[$bid] = array();
                }
                $branch_bn_map[$bid][] = $db->quote($value['bn']);
            }

            // 构建优化的查询条件：按 branch_id 分组使用 IN 查询
            $branch_filters = array();
            $max_bn_count = 1000; // IN 查询的最大数量，超过此数量会分批处理
            foreach ($branch_bn_map as $branch_id => $bn_list) {
                if (!empty($bn_list)) {
                    // 如果 bn 数量较少，使用 IN 查询；如果太多，分批处理
                    if (count($bn_list) <= $max_bn_count) {
                        $branch_filters[] = '(branch_id=' . $branch_id . ' AND bn IN (' . implode(',', $bn_list) . '))';
                    } else {
                        // 如果 bn 数量超过限制，分批查询
                        $bn_chunks = array_chunk($bn_list, $max_bn_count);
                        foreach ($bn_chunks as $chunk) {
                            $branch_filters[] = '(branch_id=' . $branch_id . ' AND bn IN (' . implode(',', $chunk) . '))';
                        }
                    }
                }
            }

            if ($branch_filters) {
                // 如果只有一个过滤条件，直接使用；否则用 OR 连接
                if (count($branch_filters) == 1) {
                    $filter[] = $branch_filters[0];
                } else {
                    $filter[] = '(' . implode(' OR ', $branch_filters) . ')';
                }
            }

            unset($branch_bn_map, $branch_filters);
        }
        
        // 3. create_time 条件（范围查询，索引第4列，放在最后）
        $filter[] = 'create_time>=' . $start_time;
        $filter[] = 'create_time<' . $end_time;

        $sql .= ' AND ' . implode(' AND ',$filter) . ' GROUP BY branch_id,bn';

        $data = $db->select($sql);

        return $data;
    }
    
    /**
     * 进销存成本统计count方法
     * @todo：重写与stock_getList中stockFilter保持一致
     * 
     * @param array $filter
     * @return int
     */
    function stock_count($filter=array())
    {
        $branch_product = app::get("ome")->model("branch_product");
        
        //sql
        $sql = "select count(*) AS _count FROM sdb_ome_branch_product AS obp LEFT JOIN sdb_material_basic_material AS op ON obp.product_id=op.bm_id 
                LEFT JOIN sdb_material_basic_material_ext AS g ON op.bm_id=g.bm_id 
                WHERE op.visibled=1 AND ". $this->stockFilter($filter);
        
        return $branch_product->db->count($sql);
    }
    
    // 进销存FINDER
    public function stock_getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        if(empty($filter['time_from']) || empty($filter['time_to'])) return false;

        $now = mktime(0,0,0,date('m'),date('d'),date('Y'));
        
        $branch_product = app::get("ome")->model("branch_product");
        
        $sql = "select obp.store,obp.unit_cost,obp.inventory_cost,obp.branch_id,obp.product_id, op.material_bn AS bn,op.material_name AS name, 
                op.bm_id, g.specifications, g.brand_id, g.cat_id 
                FROM sdb_ome_branch_product AS obp LEFT JOIN sdb_material_basic_material AS op ON obp.product_id=op.bm_id 
                LEFT JOIN sdb_material_basic_material_ext AS g ON op.bm_id=g.bm_id 
                WHERE op.visibled=1 AND ".$this->stockFilter($filter);
        $data = $branch_product->db->selectLimit($sql,$limit,$offset);
        if (!$data) return array();
        
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
        
        $branch_product = array(); $dailystock_cols = array(); $iostock_cols = array();
        
        //format
        foreach ($data as $key => $value)
        {
            //基础物料扩展信息
            $data[$key]['spec_info']     = $value['specifications'];
            $data[$key]['type_name']     = $goodsTypeList[$value['cat_id']];
            $data[$key]['brand_name']    = $brandList[$value['brand_id']];
            
            $data[$key]['product_bn']   = $value['bn'];
            $data[$key]['product_name'] = $value['name'];
            
            //branch_product
            $bp_key = $value['branch_id'] .'-'. $value['product_id'];
            $branch_product[$bp_key] = array(
                'branch_id' => $value['branch_id'],
                'bn' => $value['bn'],
                'product_id' => $value['product_id'],
            );

            $data[$key]['start_nums']           = &$dailystock_cols[$value['branch_id']][$value['product_id']]['start_nums'];            # 期初数
            $data[$key]['start_unit_cost']      = &$dailystock_cols[$value['branch_id']][$value['product_id']]['start_unit_cost'];       # 期初单位成本
            $data[$key]['start_inventory_cost'] = &$dailystock_cols[$value['branch_id']][$value['product_id']]['start_inventory_cost'];  # 期初商品成本
            
            $data[$key]['store']                = &$dailystock_cols[$value['branch_id']][$value['product_id']]['store'];                 # 结存数
            $data[$key]['inventory_cost']       = &$dailystock_cols[$value['branch_id']][$value['product_id']]['inventory_cost'];        # 结存商品成本
            $data[$key]['unit_cost']            = &$dailystock_cols[$value['branch_id']][$value['product_id']]['unit_cost'];             # 结存单位成本
            
            $data[$key]['in_nums']              = &$iostock_cols[$value['branch_id']][$value['bn']]['in_nums'];               # 入库数
            $data[$key]['in_unit_cost']         = &$iostock_cols[$value['branch_id']][$value['bn']]['in_unit_cost'];          # 入库单位成本
            $data[$key]['in_inventory_cost']    = &$iostock_cols[$value['branch_id']][$value['bn']]['in_inventory_cost'];     # 入库商品成本
            
            $data[$key]['out_nums']             = &$iostock_cols[$value['branch_id']][$value['bn']]['out_nums'];              # 出库数
            $data[$key]['sale_out_nums']        = &$iostock_cols[$value['branch_id']][$value['bn']]['sale_out_nums'];              # 销售出库数
            $data[$key]['out_unit_cost']        = &$iostock_cols[$value['branch_id']][$value['bn']]['out_unit_cost'];         # 出库单位成本
            $data[$key]['out_inventory_cost']   = &$iostock_cols[$value['branch_id']][$value['bn']]['out_inventory_cost'];    # 出库商品成本

            $dailystock_cols[$value['branch_id']][$value['product_id']]['start_nums'] = 0;
            $dailystock_cols[$value['branch_id']][$value['product_id']]['start_unit_cost'] = 0;
            $dailystock_cols[$value['branch_id']][$value['product_id']]['start_inventory_cost'] = 0;
            
            if ($now>strtotime($filter['time_to'])) {
                $dailystock_cols[$value['branch_id']][$value['product_id']]['store'] = 0;
                $dailystock_cols[$value['branch_id']][$value['product_id']]['inventory_cost'] = 0;
                $dailystock_cols[$value['branch_id']][$value['product_id']]['unit_cost'] = 0;
            } else {
                $dailystock_cols[$value['branch_id']][$value['product_id']]['store'] = $value['store'];
                $dailystock_cols[$value['branch_id']][$value['product_id']]['inventory_cost'] = $value['inventory_cost'];
                $dailystock_cols[$value['branch_id']][$value['product_id']]['unit_cost'] = $value['unit_cost'];
            }

            $iostock_cols[$value['branch_id']][$value['bn']]['in_nums'] = 0; 
            $iostock_cols[$value['branch_id']][$value['bn']]['in_unit_cost'] = 0;      
            $iostock_cols[$value['branch_id']][$value['bn']]['in_inventory_cost'] = 0;   
            
            $iostock_cols[$value['branch_id']][$value['bn']]['out_nums'] = 0;      
            $iostock_cols[$value['branch_id']][$value['bn']]['sale_out_nums'] = 0;      
            $iostock_cols[$value['branch_id']][$value['bn']]['out_unit_cost'] = 0;      
            $iostock_cols[$value['branch_id']][$value['bn']]['out_inventory_cost'] = 0;
        }

        // 期初
        $start_data = $this->get_dailystock_data(date('Y-m-d',(strtotime($filter['time_from'])-86400)),$branch_product);
        foreach ((array) $start_data as $key => $value) {
            $dailystock_cols[$value['branch_id']][$value['product_id']]['start_nums'] = $value['stock_num'];
            $dailystock_cols[$value['branch_id']][$value['product_id']]['start_unit_cost'] = $value['unit_cost'];
            $dailystock_cols[$value['branch_id']][$value['product_id']]['start_inventory_cost'] = $value['inventory_cost'];
        }
        unset($start_data);

        // 期末
        // if (strtotime($filter['time_to'])<strtotime(date('Y-m-d'))) {
            $end_data = $this->get_dailystock_data(date('Y-m-d',(strtotime($filter['time_to'])) ),$branch_product);
            foreach ((array) $end_data as $key => $value) {
                $dailystock_cols[$value['branch_id']][$value['product_id']]['store'] = $value['stock_num'];
                $dailystock_cols[$value['branch_id']][$value['product_id']]['unit_cost'] = $value['unit_cost'];
                $dailystock_cols[$value['branch_id']][$value['product_id']]['inventory_cost'] = $value['inventory_cost'];
            }
            unset($end_data);
        // }


        // 入库明细
        $istock_data = $this->get_iostock_data(strtotime($filter['time_from']),(strtotime($filter['time_to'])+86400),$branch_product,1);
        foreach ((array) $istock_data as $key => $value) {
            $iostock_cols[$value['branch_id']][$value['bn']]['in_nums'] = $value['nums'];
            $iostock_cols[$value['branch_id']][$value['bn']]['in_unit_cost'] = $value['nums'] ? bcdiv($value['inventory_cost'], $value['nums'],2) : 0;
            $iostock_cols[$value['branch_id']][$value['bn']]['in_inventory_cost'] = $value['inventory_cost'];   
        }
        unset($istock_data);

        // 出库明细
        $ostock_data = $this->get_iostock_data(strtotime($filter['time_from']),(strtotime($filter['time_to'])+86400),$branch_product,0);
        foreach ((array) $ostock_data as $key => $value) {
            $iostock_cols[$value['branch_id']][$value['bn']]['out_nums'] = $value['nums'];
            $iostock_cols[$value['branch_id']][$value['bn']]['out_unit_cost'] = $value['nums'] ? bcdiv($value['inventory_cost'], $value['nums'],2) : 0;
            $iostock_cols[$value['branch_id']][$value['bn']]['out_inventory_cost'] = $value['inventory_cost'];   
        }
        unset($ostock_data);

        // 销售出库
        $sale_ostock_data = $this->get_iostock_data(strtotime($filter['time_from']),(strtotime($filter['time_to'])+86400),$branch_product,0,3);
        foreach ((array) $sale_ostock_data as $key => $value) {
            $iostock_cols[$value['branch_id']][$value['bn']]['sale_out_nums'] = $value['nums'];
        }
        unset($sale_ostock_data);

        return $data;
    }

    /**
     * 获取指定时间范围内货品出入库数据
     * 
     * @params $from_time开始时间 2012-07-03,$to_time结束时间 2012-07-25,$product_id货品ID,$branch_id仓库ID
     * @return array() 出库数量，单位成本，库存成本等
     */
    public function get_out_stock($from_time,$to_time,$product_bn = '',$branch_id = '',$io_type,$is_all = false)
    {

        $from_time = strtotime($from_time);
        $stockcost_install_time = app::get("ome")->getConf("tgstockcost_install_time");
        if($from_time<$stockcost_install_time) $from_time = $stockcost_install_time;
        $to_time = strtotime($to_time)+(24*3600-1);
        $iostock_mdl = app::get("ome")->model("iostock");
        $stockcost_common_iostockrecord = kernel::single("tgstockcost_taog_iostockrecord");
        $iostock_type_arr = $stockcost_common_iostockrecord->get_type_id($io_type);//出库类型ID数组

        $where = 'and 1';
        if(!empty($product_bn)){
            $where .=' and oio.bn = "'.$product_bn.'"';
        }

        if(!empty($branch_id)){
            $where .=' and oio.branch_id = '.$branch_id;
        }
                
        $sql = "select a.bm_id AS product_id, oio.branch_id,oio.bn,sum(oio.nums) as nums,sum(oio.inventory_cost) as inventory_cost 
                from sdb_ome_iostock oio 
                left join sdb_material_basic_material a on oio.bn = a.material_bn 
                where oio.create_time>".intval($from_time)." and oio.create_time<".intval($to_time)." 
                and oio.type_id in (".implode(',',$iostock_type_arr).") ".$where." group by oio.branch_id,oio.bn order by null";

        $out_data = $iostock_mdl->db->select($sql);

        foreach ($out_data as $key => $value) {
            $out_data[$key]['nums'] = $value['nums']?$value['nums']:'0';
            if($out_data[$key]['nums']){
                $out_data[$key]['unit_cost'] = strval(round($value['inventory_cost']/$value['nums'],2));
            }else{
                $out_data[$key]['unit_cost'] = '0';
            }
            $out_data[$key]['inventory_cost'] = $value['inventory_cost']?$value['inventory_cost']:'0';

            $out_data[$key]['product_bn'] = $value['bn'];
            $out_data[$key]['product_id'] = $value['product_id'];
            $out_data[$key]['branch_id'] = $value['branch_id'];
        }
        
        if($is_all){
            return $out_data;
        }

        return $out_data[0];
    }
    
    function get_export_href($params)
    {
        $res = '';
        $stockcost_install_time = app::get("ome")->getConf("tgstockcost_install_time");
        if(!$_POST['time_from'])$_POST['time_from'] = date("Y-m-d",$stockcost_install_time);
        if(!$_POST['time_to'])$_POST['time_to'] = date("Y-m-d",time());

        foreach((array)$_POST as $k=>$v){

            if($k!='_DTYPE_DATE'){
               $res .='&'.$k.'='.$v; 
            }
        }
        
        return 'index.php?app=tgstockcost&ctl=stocksummary&act=index&action=export'.$res;
    }

    function fgetlist_csv(&$data,$filter,$offset,$exportType=1,$pass_data=false)
    {
        $filter = array_merge($filter,$_GET);
        
        $this->charset = kernel::single('base_charset');
        @ini_set('memory_limit','64M');
        $limit = 100;
        $list = $this->getproductIostock($filter,$offset*$limit,$limit);
        if(!$list) return false;

        $csv_title = $this->io_title();
        
        if( !$data['title'] ){
            $title = array();
            foreach( $csv_title as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['branch_product'] = '"'.implode('","',$title).'"';
        }        

        foreach($list['main'] as $k=>$aFilter)
        {
            foreach ($this->oSchema['csv']['main'] as $kk => $v) {
                $iostockRow[$kk] = $this->charset->utf2local($aFilter[$v]);
            }
            
            $data['content']['branch_product'][] = '"'.implode('","',$iostockRow).'"';
        }

        return true;
    }
    
    function exportName(&$data){
         
         $data['name'] = $_POST['time_from'].'到'.$_POST['time_to'].'进销存统计';
    }

    function export_csv($data)
    {
        $output = array();
        foreach( $data['title'] as $k => $val ){
            $output[] = $val."\n".implode("\n",(array) $data['content'][$k]);
        }

        echo implode("\n",$output);
    }

    function io_title($filter=null,$ioType='csv')
    {
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '*:货号'     =>'product_bn',
                    '*:名称'     =>'product_name',
                    
                    //'*:商品编号'   =>'goods_bn',//销售物料编号已弃用
                    
                    '*:商品类型'   =>'type_name',
                    '*:品牌'     =>'brand_name',
                    '*:规格'     =>'spec_info',
                    '*:单位'     =>'unit',
                    '*:期初数量'   =>'start_nums',
                    '*:期初单位成本' =>'start_unit_cost',
                    '*:期初商品成本' =>'start_inventory_cost',
                    '*:入库数量'   =>'in_nums',
                    '*:入库平均成本' =>'in_unit_cost',
                    '*:入库商品成本' =>'in_inventory_cost',
                    '*:出库数量'   =>'out_nums',
                    '*:出库单位成本' =>'out_unit_cost',
                    '*:出库商品成本' =>'out_inventory_cost',
                    '*:结存数量'   =>'store',
                    '*:结存单位成本' =>'unit_cost',
                    '*:结存商品成本' =>'inventory_cost',
                    '*:仓库'     =>'branch_id',
                    '*:销售出库数量'=>'sale_out_nums'
                );

                if ($_GET['act'] == 'sellstorage' && $_GET['ctl'] == 'stocksummary' && $_GET['app'] == 'tgstockcost') {
                    $cost_cols = array('start_unit_cost','start_inventory_cost','in_unit_cost','in_inventory_cost','out_unit_cost','','out_inventory_cost','unit_cost','inventory_cost');
                    foreach ($this->oSchema['csv']['main'] as $key => $value) {
                        if (in_array($value, $cost_cols)) {
                            unset($this->oSchema['csv']['main'][$key]);
                        }
                    }
                }
        }
        
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType][$filter];
    }

    function getproductIostock($filter,$offset,$limit)
    {
        $productIostock = app::get('tgstockcost')->model('branch_product');
        $Obranch = app::get('ome')->model('branch');
        
        $list = $productIostock->getList("*",$filter,$offset,$limit);

        foreach($list as $list_k=>$list_v)
        {
            $list['main'][] = array(
                                'product_bn'=>$list_v['product_bn'],
                                'product_name'=>$list_v['product_name'],
                    
                                //'goods_bn'=>$list_v['goods_bn'],//销售物料编码_已弃用
                    
                                'type_name'=> ($list_v['type_name'] ? $list_v['type_name'] : '-'),
                                'brand_name'=>$list_v['brand_name'] ? $list_v['brand_name'] : '-',
                                'spec_info'=>$list_v['spec_info'] ? $list_v['spec_info'] : '-',
                                'unit'=>$list_v['unit'] ? $list_v['unit'] : '-',
                                'start_nums'=>$list_v['start_nums']?$list_v['start_nums']:0,
                                'start_unit_cost'=>$list_v['start_unit_cost']?$list_v['start_unit_cost']:0,
                                'start_inventory_cost'=>$list_v['start_inventory_cost']?$list_v['start_inventory_cost']:0,
                                'in_nums'=>$list_v['in_nums']?$list_v['in_nums']:0,
                                'in_unit_cost'=>$list_v['in_unit_cost']?$list_v['in_unit_cost']:0,
                                'in_inventory_cost'=>$list_v['in_inventory_cost']?$list_v['in_inventory_cost']:0,
                                'out_nums'=>$list_v['out_nums']?$list_v['out_nums']:0,
                                'out_unit_cost'=>$list_v['out_unit_cost']?$list_v['out_unit_cost']:0,
                                'out_inventory_cost'=>$list_v['out_inventory_cost']?$list_v['out_inventory_cost']:0,
                                'store'=>$list_v['store']?$list_v['store']:0,
                                'unit_cost'=>$list_v['unit_cost']?$list_v['unit_cost']:0,
                                'inventory_cost'=>$list_v['inventory_cost']?$list_v['inventory_cost']:0,
                                'branch_id'=>$Obranch->Get_name($list_v['branch_id']),
                                'sale_out_nums'=>$list_v['sale_out_nums']?$list_v['sale_out_nums']:0,
            );
        }

        return $list;
    }

    private function getProductFilter($filter = array())
    {
        $where = array('1');

        $branch_product = app::get("ome")->model("branch_product");

        //品牌
        if(isset($filter['brand']) && $filter['brand'] ){
            $where[] = " g.brand_id=".intval($filter['brand']);
        }

        //商品类型
        if(isset($filter['type_id']) && $filter['type_id']){
            $where[] = ' g.cat_id='.$filter['type_id'];
        }
        
        //货号
        if(isset($filter['product_bn']) && $filter['product_bn']){
            $where[] = " op.material_bn=".$branch_product->db->quote($filter['product_bn']);
        }
        
        //货品名称
        if(isset($filter['product_name']) && $filter['product_name']){
            $where[] = " op.material_name like '".trim($filter['product_name'])."%'";
        }
        
        //仓库
        if(isset($filter['branch_id']) && $filter['branch_id']){
            $where[] = " obp.branch_id=".intval($filter['branch_id']);
        }else{
            $Obranch = app::get('ome')->model('branch');
            $branchs = $Obranch->getList('branch_id');
            $branchs_id = array();
            foreach ($branchs as $v) {
                $branchs_id[] = $v['branch_id'];
            }
            $where[] = " obp.branch_id IN ('".implode("','",$branchs_id)."')";
            unset($branchs_id);
        }
        
        return implode(' AND ', $where);
    }

    /**
     * 
     *
     * @param String $date 
     * @param Array $branch_product 仓库商品
     * @return void
     * @author 
     **/
    private function get_dailystock_data_total($date,$branch_id,$product_ids = array())
    {
        $data = array();

        // dailystock表记的是期初
        $dailystockModel = app::get('ome')->model('dailystock');
        $db = $dailystockModel->db;

        // 只查可视的货品
        $sql = 'SELECT SUM(d.stock_num) AS stock_num,SUM(d.inventory_cost) AS inventory_cost 
                FROM sdb_ome_dailystock AS d 
                LEFT JOIN sdb_material_basic_material AS a ON(d.product_id=a.bm_id) 
                WHERE a.visibled=1';
        
        $filter = array();
        $filter[] = 'd.stock_date="'.$date.'"';
        $filter[] = 'd.branch_id="'.$branch_id.'"';

        if ($product_ids && is_array($product_ids)) {
            $filter[] = 'd.product_id in(' . implode(',',$product_ids) . ')';
        }

        $sql .= ' AND ' . implode(' AND ', $filter);

        $data = $db->selectrow($sql);

        return $data;
    }

    private function get_iostock_data_total($start_time,$end_time,$branch_id,$product_bns,$io)
    {
        $data = array();

        $stockcost_common_iostockrecord = kernel::single("tgstockcost_taog_iostockrecord");
        $io_type = $stockcost_common_iostockrecord->get_type_id($io);

        $iostockModel = app::get('ome')->model('iostock');
        $db = $iostockModel->db;

        $sql = 'SELECT SUM(io.nums) AS nums,SUM(io.inventory_cost) AS inventory_cost 
                FROM ' . $iostockModel->table_name(1) . ' as io 
                LEFT JOIN sdb_material_basic_material as a on(io.bn=a.material_bn) 
                WHERE 1 AND a.visibled=1';

        $filter = array();
        $filter[] = 'io.create_time>=' . $start_time;
        $filter[] = 'io.create_time<' . $end_time;
        $filter[] = 'io.type_id in(' . implode(',',(array) $io_type ) . ')';
        $filter[] = 'io.branch_id='.$branch_id;

        if ($product_bns && is_array($product_bns)) {
            $filter[] = 'io.bn in("' . implode('","',$product_bns) . '")';
        }

        $sql .= ' AND ' . implode(' AND ',$filter);

        $data = $db->selectrow($sql);

        return $data;
    }

    //进销存表统计总数据
    public function getTotalCostInfo($filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $total_start_nums = $total_start_inventory_cost = $total_in_nums = $total_in_inventory_cost = $total_out_nums = $total_out_inventory_cost = $total_store = $total_inventory_cost = 0;

        $product_filter = $this->getProductFilter($filter);
        
        $db = kernel::database();
        $product_ids = array(); $product_bns = array();
        if ($product_filter && $product_filter != '1'){
            $sql = "SELECT op.material_bn AS bn,op.bm_id AS product_id FROM sdb_ome_branch_product AS obp LEFT JOIN sdb_material_basic_material AS op 
                    ON obp.product_id=op.bm_id LEFT JOIN sdb_material_basic_material_ext AS g ON op.bm_id=g.bm_id WHERE ". $product_filter;
            
            $productList = $db->select($sql);
            if (!$productList) {
                return array();
            }
            
            foreach ($productList as $key => $value) {
                if($value['product_id']) {
                    $product_ids[] = $value['product_id'];
                    $product_bns[] = $value['bn'];
                }
            }
        }
        
        // 期初
        $start_dailystock = $this->get_dailystock_data_total(date('Y-m-d',(strtotime($filter['time_from'])-86400)),$filter['branch_id'],$product_ids);
        $total_start_nums = (int) $start_dailystock['stock_num'];
        $total_start_inventory_cost = (float) $start_dailystock['inventory_cost'];

        // 期末
        $now  = mktime(0,0,0,date('m'),date('d'),date('Y'));
        if ($now>strtotime($filter['time_to'])) {   
            $end_dailystock = $this->get_dailystock_data_total(date('Y-m-d',(strtotime($filter['time_to']))),$filter['branch_id'],$product_ids);
        } else {
            $sql = 'SELECT SUM(store) AS stock_num, SUM(inventory_cost) AS inventory_cost FROM sdb_ome_branch_product WHERE branch_id='.$filter['branch_id'];
            if ($product_ids) {
                $sql .= ' AND product_id in(' . implode(',',$product_ids) . ')';
            }
            
            $end_dailystock = $db->selectrow($sql);
        }
        $total_store = (int) $end_dailystock['stock_num'];
        $total_inventory_cost = (float) $end_dailystock['inventory_cost'];

        // 入库
        $in_stock = $this->get_iostock_data_total(strtotime($filter['time_from']),(strtotime($filter['time_to'])+86400),$filter['branch_id'],$product_bns,1);
        $total_in_nums = (int) $in_stock['nums'];
        $total_in_inventory_cost = (float) $in_stock['inventory_cost'];

        // 出库
        $out_stock = $this->get_iostock_data_total(strtotime($filter['time_from']),(strtotime($filter['time_to'])+86400),$filter['branch_id'],$product_bns,0);
        $total_out_nums = (int) $out_stock['nums'];
        $total_out_inventory_cost = (float) $out_stock['inventory_cost'];

        $branch_product_cost['total_start_nums'] = $total_start_nums;
        $branch_product_cost['total_start_inventory_cost'] =$total_start_inventory_cost;
        $branch_product_cost['total_in_nums'] = $total_in_nums;
        $branch_product_cost['total_in_inventory_cost'] = $total_in_inventory_cost;
        $branch_product_cost['total_out_nums']=  $total_out_nums;
        $branch_product_cost['total_out_inventory_cost'] =$total_out_inventory_cost;
        $branch_product_cost['total_store'] = $total_store;
        $branch_product_cost['total_inventory_cost'] = $total_inventory_cost;
    
        return $branch_product_cost;
    }
    
    //进销存统计过滤条件
    public function stockFilter($filter = array())
    {
        $branch_product = app::get("ome")->model("branch_product");
        $where = array(1);
        
        //仓库
        if(isset($filter['branch_id']) && $filter['branch_id']){
            $where[] = " obp.branch_id=".intval($filter['branch_id']);
        }else{
            $Obranch = app::get('ome')->model('branch');
            $branchs = $Obranch->getList('branch_id');
            $branchIds = array();
            foreach ($branchs as $v)
            {
                $branch_id = $v['branch_id'];
    
                $branchIds[$branch_id] = $branch_id;
            }
            
            $where[] = " obp.branch_id IN (".implode(',',$branchIds).")";
            
            unset($branchIds);
        }
        
        //货号
        if(isset($filter['product_bn']) && $filter['product_bn']){
          $where[] = " op.material_bn=".$branch_product->db->quote($filter['product_bn']);
        }
        
        //货品名称
        if(isset($filter['product_name']) && $filter['product_name']){
            $where[] = " op.material_name like '".trim($filter['product_name'])."%'";
        }
        
        //基础物料品牌
        if(isset($filter['brand']) && $filter['brand']){
            $where[] = ' g.brand_id = '.$filter['brand'];
        }
        
        //基础物料类型
        if(isset($filter['type_id']) && $filter['type_id']){
            $where[] = ' g.cat_id = '.$filter['type_id'];
        }
        
        return implode(' AND ', $where);
    }
}
