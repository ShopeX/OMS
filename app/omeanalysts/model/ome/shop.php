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

class omeanalysts_mdl_ome_shop extends dbeav_model{

    var $has_export_cnf = true;

    public $export_name = '店铺每日汇总';


    /**
     * 添加_shoplog
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function add_shoplog($filter=null){
        $result = array(
            'sale_order'=>0,
            'sale_num'=>0,
            'sale_amount'=>0,
            'aftersale_order'=>0,
            'aftersale_num'=>0,
            'aftersale_amount'=>0,
            'total_amount'=>0
        );


        //销售信息
        $sale_sql = 'select count(S.sale_id) as sale_order,sum(S.sale_amount) as sale_amount from sdb_ome_sales S where '.$this->sfilter($filter);

        $saledata = $this->db->select($sale_sql);
        $result['sale_order'] = $saledata[0]['sale_order'];
        $result['sale_amount'] = $saledata[0]['sale_amount'];
        $saleitem_sql = 'select sum(SI.nums) as sale_num from sdb_ome_sales_items SI left join sdb_ome_sales S on SI.sale_id = S.sale_id where '.$this->sfilter($filter);
        $saleitemdata = $this->db->select($saleitem_sql);
        $result['sale_num'] = $saleitemdata[0]['sale_num'];

        //售后信息
        $aftersale_sql = 'select count(A.aftersale_id) as aftersale_order,sum(A.refundmoney) as aftersale_amount from sdb_sales_aftersale A where '.$this->rfilter($filter);
        $aftersaledata = $this->db->select($aftersale_sql);
        $result['aftersale_order'] = $aftersaledata[0]['aftersale_order'];
        $result['aftersale_amount'] = $aftersaledata[0]['aftersale_amount'];

        $afteritem_sql = 'select sum(AI.num) as aftersale_num from sdb_sales_aftersale_items AI left join sdb_sales_aftersale A on AI.aftersale_id = A.aftersale_id where AI.return_type!="refunded" and '.$this->rfilter($filter);
        $aftersaleitemdata = $this->db->select($afteritem_sql);
        $result['aftersale_num'] = $aftersaleitemdata[0]['aftersale_num'];
        $result['total_amount'] = $result['sale_amount'] - $result['aftersale_amount'];

        return $result;
    }
    
    /**
     * count
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function count($filter=null){

        $date_range = array();
        $filter['time_from'] = strtotime($filter['time_from']);
        $filter['time_to'] = (strtotime($filter['time_to'])+86400);
        for($i=$filter['time_from']; $i<$filter['time_to']; $i+=86400){
            $date_range[] = date("Y-m-d", $i);
        }
        
        return count($date_range);
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){

        @ini_set('memory_limit','1024M');
        //平台类型
        if (isset($filter['shop_type']) && $filter['shop_type']) {
            $shopList = app::get('ome')->model('shop')->getList('shop_id,shop_type', ['shop_type' => $filter['shop_type']]);
            $shopIds  = array_column($shopList, 'shop_id');
            if ($filter['type_id'] && is_array($filter['type_id'])) {
                $filter['type_id'] = array_intersect($filter['type_id'], $shopIds);
            } else {
                $filter['type_id'] = $shopIds;
            }
        }
        
        
        if (isset($filter['org_id']) && $filter['org_id']) {
            $shopList = app::get('ome')->model('shop')->getList('shop_id', ['org_id' => $filter['org_id']]);
            $shopIds  = array_column($shopList, 'shop_id');
            if ($filter['type_id'] && is_array($filter['type_id'])) {
                $filter['type_id'] = array_intersect($filter['type_id'], $shopIds);
            } else {
                $filter['type_id'] = $shopIds;
            }
            if (isset($filter['type_id']) && is_array($filter['type_id']) && !$filter['type_id']) {
                $filter['type_id'] = [0];
            }
        }
        
        if($filter['type_id'] && is_array($filter['type_id'])){
            $flagId = [];
            $flagName = [];
            $typeObj = app::get('omeanalysts')->model('ome_type');
            $flagList = $typeObj->get_shop();
            foreach($flagList as $key=>$val){
                if(in_array($val['type_id'], $filter['type_id'])){
                    $flagId[] = $val['relate_id'];
                    $flagName[$val['relate_id']] = $val['name'];
                }
            }
        }else{
            $flagId = ['0'];
            $flagName = ['0' => '所有店铺'];
        }

        $date_range = array();
        if(isset($filter['report']) && $filter['report']=='month'){
            $filter['time_from'] = strtotime($filter['time_from']);
            $filter['time_to'] = strtotime($filter['time_to']);
            for($i=$filter['time_from']; $i<=$filter['time_to'];){
                $date_range[] = date("Y-m", $i);
                $i = mktime(0, 0, 0, date('m',$i)+1, date('d',$i), date('Y',$i));
            }
        }else{
            $filter['time_from'] = strtotime($filter['time_from']);
            $filter['time_to'] = (strtotime($filter['time_to'])+86400);
            for($i=$filter['time_from']; $i<$filter['time_to']; $i+=86400){
                $date_range[] = date("Y-m-d", $i);
                //$filter['_time']
            }
        }
        //if($orderType == 'time desc'){
            $date_range = array_reverse($date_range);
        //}
        if($limit > 0){
            $date_range = array_slice($date_range, $offset, $limit);
        }
        $analysis_info = app::get('eccommon')->model('analysis')->select()->columns('*')->where('service = ?', 'omeanalysts_ome_shop')->instance()->fetch_row();
        $flagTmp = [];
        if($analysis_info){
            $obj = app::get('eccommon')->model('analysis_logs')->select()->columns('*')->where('analysis_id = ?', $analysis_info['id']);
            $obj->where('time >= ?', $filter['time_from']);
            $obj->where('time < ?', $filter['time_to']);

            if(isset($this->_params['type'])) $obj->where('type = ?', $this->_params['type']);
            $rows = $obj->where('flag in (\''.implode("','", $flagId).'\')', '')->instance()->fetch_all();

            foreach($rows AS $row){

                $date = date('Y-m-d', $row['time']);
                $flagTmp[$row['flag']][$date][$row['target']] = $row['value'];
            }
        }
        $data = [];
        foreach($date_range AS $k=>$date){
            foreach ($flagId as $fid) {
                $tmp = $flagTmp[$fid];
                $data[] = array(
                    'shop_name'=>$flagName[$fid],
                    'time' => $date,
                    'sale_order'=>($tmp[$date][1])?$tmp[$date][1]:0,
                    'sale_num'=>($tmp[$date][2])?$tmp[$date][2]:0,
                    'sale_amount'=>($tmp[$date][3])?$tmp[$date][3]:0,
                    'aftersale_order'=>($tmp[$date][4])?$tmp[$date][4]:0,
                    'aftersale_num'=>($tmp[$date][5])?$tmp[$date][5]:0,
                    'aftersale_amount'=>($tmp[$date][6])?$tmp[$date][6]:0,
                    'total_amount'=>($tmp[$date][7])?$tmp[$date][7]:0,
               );
            }
        }
        
        return $data;
    }
    function sfilter($filter = null){
        $where = array(1);

        if(isset($filter['type_id']) && $filter['type_id']){
            $where[]= 'S.shop_id = \''.addslashes($filter['type_id']).'\'';
            
        }
        unset($filter['type_id']);

        if(isset($filter['time_from']) && $filter['time_from']){
            $where[]= 'S.sale_time >='.strtotime($filter['time_from']);
            unset($filter['time_from']);
        }

        if(isset($filter['time_to']) && $filter['time_to']){
            $where[]= 'S.sale_time <='.strtotime($filter['time_to'].' 23:59:59');
            unset($filter['time_to']);
        }

        if (isset($filter['shop_type']) && $filter['shop_type']){
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            $shop_ids = $shopList[$filter['shop_type']];

            if ($shop_ids){
                $where[] = " S.shop_id in ('".implode('\',\'',$shop_ids)."')";
            }
            unset($filter['shop_type']);
        }
        return implode(' AND ',$where);
    }

    function rfilter($filter = null){
        $where = array(1);

        if(isset($filter['type_id']) && $filter['type_id']){
            $where[]= 'A.shop_id = \''.addslashes($filter['type_id']).'\'';
            
        }
        unset($filter['type_id']);

        if(isset($filter['time_from']) && $filter['time_from']){
            $where[]= 'A.aftersale_time >='.strtotime($filter['time_from']);
            unset($filter['time_from']);
        }

        if(isset($filter['time_to']) && $filter['time_to']){
            $where[]= 'A.aftersale_time <='.strtotime($filter['time_to'].' 23:59:59');
            unset($filter['time_to']);
        }

        if (isset($filter['shop_type']) && $filter['shop_type']){
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            $shop_ids = $shopList[$filter['shop_type']];

            if ($shop_ids){
                $where[] = " A.shop_id in ('".implode('\',\'',$shop_ids)."')";
            }
            unset($filter['shop_type']);
        }
        return implode(' AND ',$where);
    }

    /**
     * io_title
     * @param mixed $ioType ioType
     * @return mixed 返回值
     */
    public function io_title( $ioType='csv' ){
    
        switch( $ioType ){
            case 'csv':
                $this->oSchema['csv']['main'] = array(
                    '*:店铺名称'     => 'shop_name',

                    '*:日期'         => 'time',
                    '*:销售单数'     =>'sale_order',
                    '*:销售货品数'   => 'sale_num',
                    '*:销售金额'     => 'sale_amount',
                    '*:售后单数'     => 'aftersale_order',
                    '*:售后货品数'   => 'aftersale_num',
                    '*:退款金额'     => 'aftersale_amount',
                    '*:合计金额'     => 'total_amount',
                );
            break;
        }
        $this->ioTitle[$ioType] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType];
    }
    
    /**
     * export_csv
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function export_csv($data){
        $output = array();
        $output[] = $data['title']['shop']."\n".implode("\n",(array)$data['content']['shop']);
        echo implode("\n",$output);
    }

    /**
     * fgetlist_csv
     * @param mixed $data 数据
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $exportType exportType
     * @return mixed 返回值
     */
    public function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){

        @ini_set('memory_limit','64M');

        if( !$data['title']['shop']){
            $title = array();
            foreach( $this->io_title('csv') as $k => $v ){
                $title[] = $v;
            }
            $data['title']['shop'] = mb_convert_encoding('"'.implode('","',$title).'"', 'GBK', 'UTF-8');
        }

        $limit = 100;
        
        if( !$list=$this->getlist('*',$filter,$offset*$limit,$limit) ) return false;
        
        $shopRow = array();

        foreach( $list as $aFilter ){

              $shopRow['*:店铺名称'] = $aFilter['shop_name'];
              $shopRow['*:日期'] = $aFilter['time']?$aFilter['time']:'-';
              $shopRow['*:销售单数'] = $aFilter['sale_order'];
              $shopRow['*:销售货品数'] = $aFilter['sale_num'];
              $shopRow['*:销售金额'] = $aFilter['sale_amount'];
              $shopRow['*:售后单数'] = $aFilter['aftersale_order'];
              $shopRow['*:售后货品数'] = $aFilter['aftersale_num'];
              $shopRow['*:退款金额'] = $aFilter['aftersale_amount'];
              $shopRow['*:合计金额'] = $aFilter['total_amount'];

            $data['content']['shop'][] = mb_convert_encoding('"'.implode('","',$shopRow).'"', 'GBK', 'UTF-8');
        }

        $data['name'] = $this->export_name.date("YmdHis");

        return true;
    }

    /**
     * exportName
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function exportName(&$data){
        $data['name'] = $_POST['time_from'].'到'.$_POST['time_to'].$this->export_name;
    }


    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema(){
        $schema = array (
            'columns' => array (
                'shop_name' => array (
                    'label' => '店铺名称',
                    'width' => 130,
                    'editable' => false,
                    'orderby' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'order'=>1,
                ),
                /*'shop_type'=>array(
                    'type' => 'varchar(32)',
                    'label' => '店铺类型',
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => '70'
                ),*/
                'time' => array (
                    'type' => 'varchar(200)',
                    'pkey' => true,
                    'label' => '日期',
                    'width' => 90,
                    'orderby' => false,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'realtype' => 'mediumint(8) unsigned',
                    'order'=>2,
                ),
                'sale_order' => array (
                    'type' => 'number',
                    'label' => '销售单数',
                    'width' => 75,
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => 'true',
                    'in_list' => true,
                    'is_title' => true,
                    'default_in_list' => true,
                    'realtype' => 'varchar(50)',
                    'orderby' => false,
                    'order'=>3,
                ),
                'sale_num' => array (
                    'type' => 'number',
                    'label' => '销售货品数',
                    'width' => 75,
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => 'true',
                    'in_list' => true,
                    'is_title' => true,
                    'default_in_list' => true,
                    'realtype' => 'varchar(50)',
                    'orderby' => false,
                    'order'=>4,
                ),                
                'sale_amount' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '销售金额',
                    'width' => 80,
                    'editable' => false,
                    'filtertype' => 'number',
                    'in_list' => true,
                    'default_in_list' => true,
                    'realtype' => 'mediumint(8) unsigned',
                    'orderby' => false,
                    'order'=>5,
                ), 
                'aftersale_order' => array (
                    'type' => 'number',
                    'label' => '售后单数',
                    'width' => 75,
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => 'true',
                    'in_list' => true,
                    'is_title' => true,
                    'default_in_list' => true,
                    'realtype' => 'varchar(50)',
                    'orderby' => false,
                    'order'=>6,
                ),
                'aftersale_num' => array (
                    'type' => 'number',
                    'label' => '售后货品数',
                    'width' => 75,
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => 'true',
                    'in_list' => true,
                    'is_title' => true,
                    'default_in_list' => true,
                    'realtype' => 'varchar(50)',
                    'orderby' => false,
                    'order'=>7,
                ),                
                'aftersale_amount' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '退款金额',
                    'width' => 80,
                    'editable' => false,
                    'filtertype' => 'number',
                    'in_list' => true,
                    'default_in_list' => true,
                    'realtype' => 'mediumint(8) unsigned',
                    'orderby' => false,
                    'order'=>8,
                ),
                'total_amount' => array (
                    'type' => 'money',
                    'default' => 0,
                    'required' => true,
                    'label' => '合计金额',
                    'width' => 80,
                    'editable' => false,
                    'filtertype' => 'number',
                    'in_list' => true,
                    'default_in_list' => true,
                    'realtype' => 'mediumint(8) unsigned',
                    'orderby' => false,
                    'order'=>9,
                ),                               
            ),
            'idColumn' => 'time',
            'in_list' => array (
                0 => 'shop_name',
                1 => 'time',
                2 => 'sale_order',
                3 => 'sale_num',
                4 => 'sale_amount',
                5 => 'aftersale_order',
                6 => 'aftersale_num',
                7 => 'aftersale_amount',
                8 => 'total_amount',
                //9=>'shop_type',
            ),
            'default_in_list' => array (
                0 => 'shop_name',
                1 => 'time',
                2 => 'sale_order',
                3 => 'sale_num',
                4 => 'sale_amount',
                5 => 'aftersale_order',
                6 => 'aftersale_num',
                7 => 'aftersale_amount',
                8 => 'total_amount',
                //9=>'shop_type',                
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
            $type .= '_salesReport_shopDayAnalysis';
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
            $type .= '_salesReport_shopDayAnalysis';
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
        
        $shopRow = array();
        foreach( $list as $aFilter ){
            $shopRow['shop_name'] = $aFilter['shop_name'];
            $shopRow['time'] = $aFilter['time']?$aFilter['time']:'-';
            $shopRow['sale_order'] = $aFilter['sale_order'];
            $shopRow['sale_num'] = $aFilter['sale_num'];
            $shopRow['sale_amount'] = $aFilter['sale_amount'];
            $shopRow['aftersale_order'] = $aFilter['aftersale_order'];
            $shopRow['aftersale_num'] = $aFilter['aftersale_num'];
            $shopRow['aftersale_amount'] = $aFilter['aftersale_amount'];
            $shopRow['total_amount'] = $aFilter['total_amount'];

            $exptmp_data = array();
            foreach (explode(',', $fields) as $key => $col) {
                if(isset($shopRow[$col])){
                    $shopRow[$col] = mb_convert_encoding($shopRow[$col], 'GBK', 'UTF-8');
                    $exptmp_data[] = $shopRow[$col];
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