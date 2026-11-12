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
class omeanalysts_mdl_ome_storeStatus extends dbeav_model{

    var $has_export_cnf = true;

    var $export_name = '库存状况分析';

    var $table_name = 'sale_products';

    public function table_name($real=false)
    {
        if($real){
            return kernel::database()->prefix.$this->app->app_id.'_'.$this->table_name;
        }else{
            return $this->table_name;
        }
    }

    public function get_schema()
    {
        $schema['columns'] = array(
            'branch_name' => array (
                'type' => 'varchar(200)',
                'required' => true,
                'label' => '线上仓库',
                'width' =>120,
                'orderby' => false,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'order' => 1,
                'in_list' => true,
                'default_in_list'  => true,
            ),
            'brand_id' => array (
                'type' => 'number',
                'required' => true,
                'label' => '品牌',
                'width' =>130,
                'orderby' => false,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'order' => 2,
                'in_list' => true,
                'default_in_list'  => true,
            ),
            'bn' => array (
                'type' => 'number',
                'required' => true,
                'label' => '货号',
                'width' =>130,
                'orderby' => false,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'order' => 3,
                'in_list' => true,
                'default_in_list'  => true,
            ),
            'turnover_rate' => array (
                'type' => 'number',
                'required' => true,
                'label' => '周转率%',
                'width' =>130,
                'orderby' => false,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'order' => 5,
                'in_list' => true,
                'default_in_list'  => true,
            ),
            'name' => array (
                'type' => 'number',
                'required' => true,
                'label' => '名称',
                'width' =>130,
                'orderby' => false,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'order' => 4,
                'in_list' => true,
                'default_in_list'  => true,
            ),
            'sale_store' => array (
                'type' => 'number',
                'required' => true,
                'label' => '当前库存',
                'width' =>130,
                'orderby' => false,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'order' => 7,
                'in_list' => true,
                'default_in_list'  => true,
            ),
            'sale_day' => array (
                'type' => 'number',
                'required' => true,
                'label' => '可售天数',
                'width' =>130,
                'orderby' => false,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'order' => 8,
                'in_list' => true,
                'default_in_list'  => true,
            ),
        );
        foreach($schema['columns'] as $schema_k=>$val)
        {
           //if($schema_k == 'id') continue;
           $schema['default_in_list'][] = $schema_k;
           $schema['in_list'][] = $schema_k;
        }

        return $schema;
    }

    public function count($filter=null){
        $data = $this->getList('*',$filter);
        return count($data);
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        return parent::_filter($filter,$tableAlias,$baseWhere);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){

        $rows = $this->header_getlist($filter);

        $_day = $rows['_day'];
        $start_months = $rows['start_months'];
        $end_months = $rows['end_months'];
        $where = $rows['where'];
        $end_day = $rows['end_day'];
        $start_day = $rows['start_day'];
        $start_time = $rows['start_time'];
        $end_time = $rows['end_time'];

        $data = $map = array();
        /*
        if($filter['type'] == 'map'){
           $_where = '';
        }else{
           $_where = ',bpsd.product_id';
        }
        */
        // 周转率
        $sql = sprintf(' SELECT bpsd.product_id,bpsd.branch_id,sum(bpsd.sales_nums) AS sales_nums,ob.name as branch_name,
                op.material_bn AS bn,op.material_name AS name, 
                IF((cast(sobp.store as signed)-cast(sobp.store_freeze as signed))<0,0,cast(sobp.store as signed)-cast(sobp.store_freeze as signed)) as sale_store 
                FROM `sdb_omeanalysts_sale_products` AS bpsd left join sdb_ome_branch as ob on bpsd.branch_id = ob.branch_id 
                left join sdb_material_basic_material as op on bpsd.product_id = op.bm_id 
                
                left join sdb_ome_branch_product as sobp on (bpsd.branch_id = sobp.branch_id and bpsd.product_id = sobp.product_id) 
                WHERE bpsd.sales_time>=\'%s\' AND bpsd.sales_time<=\'%s\' %s GROUP BY bpsd.branch_id,bpsd.product_id',$start_time,$end_time,$where);

        if($filter['type'] == 'map'){
           $turnover_rate_data = $this->db->select($sql);
        }else{
           $turnover_rate_data = $this->db->selectLimit($sql,$limit,$offset);
        }

        $tmp = array();
        if ( $turnover_rate_data ){
            foreach ($turnover_rate_data as $key=>$value){
                 // 期初库存数量
                 $sql = sprintf(' SELECT sum(day%s) AS store FROM `sdb_omeanalysts_branch_product_stock_detail` AS bpsd WHERE bpsd.months>=\'%s\' AND bpsd.months<=\'%s\' AND bpsd.branch_id=\'%s\'  AND bpsd.product_id=\'%s\' ',$start_day,$start_months,$end_months,$value['branch_id'],$value['product_id']);

                 $start_store = $this->db->selectrow($sql);
                 // 期末库存数量
                 $sql = sprintf(' SELECT sum(day%s) AS store FROM `sdb_omeanalysts_branch_product_stock_detail` AS bpsd WHERE bpsd.months>=\'%s\' AND bpsd.months<=\'%s\' AND bpsd.branch_id=\'%s\'  AND bpsd.product_id=\'%s\' ',$end_day,$start_months,$end_months,$value['branch_id'],$value['product_id']);
                 $end_store = $this->db->selectrow($sql);

                 $start_store['store'] = isset($start_store['store']) ? $start_store['store'] : 0;
                 $end_store['store'] = isset($end_store['store']) ? $end_store['store'] : 0;

                 $total_store = $start_store['store'] + $end_store['store'];

                //$getstore = $this->db->selectrow('select (obp.store-obp.store_freeze) as sale_store from sdb_ome_branch_product as obp where obp.product_id= '.$value['product_id']);

                if($filter['type'] == 'map'){
                   // $map[$value['branch_id']]['sale_store'] = $getstore['sale_store'];//当前库存
                    //$map[$value['branch_id']]['brand_id'] = $getbrand['brand_name'];//品牌
                   // $map[$value['branch_id']]['bn'] = $getstore['bn'];//货号
                   // $map[$value['branch_id']]['name'] = $getstore['name'];//名称
                    $tmp[$value['branch_id']]['sales_nums'] += $value['sales_nums'];
                    $tmp[$value['branch_id']]['total_store'] += $total_store;
                    $map[$value['branch_id']]['branch_name'] = $value['branch_name'];
                    //$map[$value['branch_id']]['branch_id'] = $value['branch_id'];
                    //$map[$value['branch_id']]['turnover_rate'] = $turnover_rate;//库存周转率
                   // $map[$value['branch_id']]['sale_day'] = ceil($getstore['sale_store']/($value['sales_nums']/$_day));//当前库存可售天数
                }else{
                    if (empty($total_store)){
                        $turnover_rate = 0;
                    }else{
                        $turnover_rate = round(($value['sales_nums'] * 2 / $total_store) * 100,2);
                    }
                    $data[$key]['sale_store'] = $value['sale_store'];//当前库存
                    $data[$key]['brand_id'] = $value['brand_name'] ? $value['brand_name'] : '-';//品牌
                    $data[$key]['bn'] = $value['bn'];//货号
                    $data[$key]['name'] = $value['name'];//名称
                    $data[$key]['branch_id'] = $value['branch_id'];
                    $data[$key]['branch_name'] = $value['branch_name'];
                    $data[$key]['turnover_rate'] = $turnover_rate.'%';//库存周转率

                    $data[$key]['sale_day'] = ceil($value['sale_store']/($value['sales_nums']/$_day));//当前库存可售天数
                }

             }
             if($tmp){
                 foreach ($tmp as $tk => $tv){
                     if (empty($tv['total_store'])){
                         $map[$tk]['turnover_rate'] = 0;
                     }else{
                         $map[$tk]['turnover_rate'] = round(($tv['sales_nums'] * 2 / $tv['total_store']) * 100,2);
                     }
                 }
             }

        }
        if($filter['type'] == 'map'){
            return $map;
        }else{
            return $data;
        }

    }

    public function exportName(&$data){
        $data['name'] = $_POST['time_from'].'到'.$_POST['time_to'].'库存状况分析';
    }

    public function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){

        @ini_set('memory_limit','1024M');
        if( !$data['title'] ){
            $title = array();
            foreach( $this->io_title() as $k => $v ){
                $title[] = $v;
            }

            $data['title']['storestatus'] = mb_convert_encoding('"'.implode('","',$title).'"', 'GBK', 'UTF-8');
        }

        $limit = 100;

        $storestatus_arr = $this->getList('*',$filter,$offset*$limit,$limit);

        if(!$storestatus_arr) return false;

        foreach ($storestatus_arr as $k => $aFilter) {
            foreach( $this->oSchema['csv']['main'] as $kk => $vv ){
                $storestatusRow[$kk] = $aFilter[$vv];
            }
            $data['content']['storestatus'][] = mb_convert_encoding('"'.implode('","',$storestatusRow).'"', 'GBK', 'UTF-8');
        }

        return true;

    }

    function export_csv($data,$exportType = 1 ){

        $output = array();

        $output[] = $data['title']['storestatus']."\n".implode("\n",(array)$data['content']['storestatus']);

        echo implode("\n",$output);
    }

    public function io_title( $filter=null,$ioType='csv'){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '*:仓库'=>'branch_name',
                    '*:品牌'=>'brand_id',
                    '*:货号'=>'bn',
                    '*:名称'=>'name',
                    '*:周转率%'=>'turnover_rate',
                    '*:当前库存'=>'sale_store',
                    '*:可售天数'=>'sale_day',
                );
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType][$filter];
    }

    public function header_getlist($filter = null){
        unset($filter['order_status']);
        $time_from = strtotime($filter['time_from']);
        $time_to = strtotime($filter['time_to']);

        //$_day = $filter['time_from'] - $filter['time_to'];//期间天数

        //获取选择时间范围内的最早一天
        $sql = 'SELECT `sale_time` FROM `sdb_ome_sales` WHERE `sale_time` IS NOT NULL ORDER BY `sale_time` ASC';

        $start_sales = $this->db->selectrow($sql);
        $start_sales_time = $start_sales['sale_time'];
        if (date('Ym',$start_sales_time) == date('Ym',$time_from)){
            $start_time = strtotime(date("Y-m-d 00:00:00",$start_sales_time));
        }else{
            $start_time = strtotime(date("Y-m-1 00:00:00",$time_from));
        }

        //获取选择时间范围内的最后一天
        if ( date('m',$time_to) >= date('m') ){
            $end_time = strtotime(date("Y-m-j 23:59:59",time()-24*60*60));
        }else{
            $end_time = strtotime(date('Y-m-t 23:59:59', $time_to));//1351612799
        }

        $end_day = intval(date("d",$end_time));
        $start_day = intval(date("d",$start_time));
        $start_months = date('Ym', $time_from);
        $end_months = date('Ym', $time_to);

        if(isset($filter['own_branches']) && $filter['own_branches']){
            $where= ' AND bpsd.branch_id in ('.implode(',',$filter['own_branches']).')';
        }
        unset($filter['own_branches']);

        if ( isset($filter['branch_id']) && $filter['branch_id'] ){
             $where = ' AND bpsd.branch_id=\''.$filter['branch_id'].'\'';
        }
        unset($filter['branch_id']);

        //期间天数
        $_start_time = date('Y-m-d H:i:s',$start_time);
        $_end_time = date('Y-m-d H:i:s',$end_time);

        $_day = ($this->maketime($_end_time) - $this->maketime($_start_time)) / (3600*24);

        $rows = array();
        $rows['_day'] = $_day;
        $rows['start_months'] = $start_months;
        $rows['end_months'] = $end_months;
        $rows['where'] = $where;
        $rows['start_day'] = $start_day;
        $rows['end_day'] = $end_day;
        $rows['start_time'] = $start_time;
        $rows['end_time'] = $end_time;
        return $rows;
    }

    public function maketime($date)
    {
         list($_date,$_time) = explode(' ',$date);
         list($year,$month,$day) = explode('-',$_date);
         list($hour,$minute,$second) = explode(':',$_time);
         return mktime($hour,$minute,$second,$month,$day,$year);
    }
    
    public function export_params(){
        //获取框架filter信息
        $params = unserialize($_POST['params']);
        $filter['time_from'] = $params['time_from'];
        $filter['time_to'] = $params['time_to'];
        $params = array(
            'filter' => $filter,
            //单文件
            'single'=> array(
                '1'=> array(
                    //定义返回主体信息方法，提供自定义方法名给method，系统会分页调取主体信息
                    'method' => 'get_export_main',
                    'offset' => 0,
                    'limit' => 4000,
                    //导出文件名
                    'filename' => '库存状况综合分析',
                ),
            ),
        );
        return $params;
    }

    public function get_export_main_title(){
        $title = array(
           'col:线上仓库',
           #'col:适销率%',
           'col:周转率%',
        );
        return $title;
    }

     //注：$filter是在export_params()方法里获取到的filter,$offset,$limit已做处理，直接带到getList里即可
    public function get_export_main($filter,$offset,$limit,&$data){
        if($offset > 0) return;
        $list=$this->getList('*',$filter,$offset*$limit,$limit);
        $branchModel = app::get('ome')->model('branch');
        $data = array();
        if ($list){
            foreach($list as $v){
                $branchs = $branchModel->dump($v['branch_id'],'name');
                $data[] = array(
                    'col:线上仓库'=>$branchs['name'],
                    #'col:适销率%' => $v['marketable_rate'],
                    'col:周转率%' => $v['turnover_rate'],
                );
            }
        }
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
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_storeStatus') {
            $type .= '_analysisReport_storeStatusAnalysis';
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
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_storeStatus') {
            $type .= '_analysisReport_storeStatusAnalysis';
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

        //为了调用出oschema变量
        $this->io_title();

        $storestatus_arr = $this->getList('*',$filter,$start,$end);
        if(!$storestatus_arr) return false;

        foreach ($storestatus_arr as $k => $aFilter) {
            foreach( $this->oSchema['csv']['main'] as $kk => $vv ){
                $storestatusRow[$vv] = $aFilter[$vv];
            }

            $exptmp_data = array();
            foreach (explode(',', $fields) as $key => $col) {
                if(isset($storestatusRow[$col])){
                    $storestatusRow[$col] = mb_convert_encoding($storestatusRow[$col], 'GBK', 'UTF-8');
                    $exptmp_data[] = $storestatusRow[$col];
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
