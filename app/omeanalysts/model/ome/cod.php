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
class omeanalysts_mdl_ome_cod extends dbeav_model{

    var $has_export_cnf = true;

    var $export_name = '货到付款统计';

    /**
     * 须加密字段
     *
     * @var string
     **/
    private $__encrypt_cols = array(
        'ship_name'   => 'simple',
        'ship_tel'    => 'phone',
        'ship_mobile' => 'phone',
    );

    public function table_name($real=false){
        $table_name = 'ome_delivery';
        if($real){
            return kernel::database()->prefix.$table_name;
        }else{
            return 'ome_cod';
        }
    }
    public function get_cod($filter=null){
        $wsql = 'sdb_ome_delivery as D LEFT JOIN '.
            'sdb_ome_delivery_order as DO ON D.delivery_id=DO.delivery_id LEFT JOIN '.
            'sdb_ome_orders as O ON DO.order_id=O.order_id LEFT JOIN '.
            'sdb_ome_order_extend as OE ON OE.order_id=O.order_id '.
            'where D.parent_id=0 and D.is_cod=\'true\' and D.process=\'true\' and O.is_fail=\'false\' and O.ship_status!=\'4\' and '.
            'D.delivery_time >='.strtotime($filter['time_from']).' and D.delivery_time <'.(strtotime($filter['time_to'])+86400);
        if(!empty($filter['type_id'])){
            $wsql .=' and D.branch_id =\''.addslashes($filter['type_id']).'\'';
        }
        if(!empty($filter['logi_id'])){
            $wsql .=' and D.logi_id ='.$filter['logi_id'];
        }

        if(!empty($filter['shop_id'])){
            $wsql .=' and D.shop_id ="'.$filter['shop_id'].'"';
        }
    
    
        if(isset($filter['org_id']) && $filter['org_id']){
            $wsql .= " and O.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }
        
        //计算补打物流单的快递费
        $sql1 = 'SELECT D.delivery_id,D.delivery_cost_actual,OE.receivable as receivables FROM '.$wsql;
        $rowarr = $this->db->select($sql1);
        $receivables = $num = $delivery_cost_actual = 0;
        if($rowarr){
            foreach($rowarr as $k=>$v){
                #重复发货单，只算一次
                if(empty($deliveryIds[$v['delivery_id']])){
                    $deliveryIds[$v['delivery_id']] = $v['delivery_id'];
                    $num++;#订单数量
                    $delivery_cost_actual += $v['delivery_cost_actual'];#物流费用
                }
				$receivables += $v['receivables'];#应收金额
            }
            if($deliveryIds){
                $dlyBillObj = app::get('ome')->model("delivery_bill");
                $billFilter = array(
                    'delivery_id'=>$deliveryIds,
                    'status'=>1,
                );
                $billdata = $dlyBillObj->getList('*',$billFilter,0,-1);
                if($billdata){
                    $billnum = $billcost = 0;
                    foreach($billdata as $billvalue){
                        $billnum++;
                        $billcost += $billvalue['delivery_cost_actual'];
                        unset($billvalue);
                    }
                }
                unset($deliveryIds,$billdata);
            }
            unset($rowarr);
        }

       /*  $sql2 = 'SELECT count(DISTINCT D.delivery_id) as num, sum(delivery_cost_actual) as cost,sum(OE.receivable) as receivables FROM '.$wsql;
        $row = $this->db->select($sql2);
        $row[0]['num'] += $billnum;
        $row[0]['cost'] += $billcost; */
        $row['cost'] =  $delivery_cost_actual;
        $row['receivables'] =  $receivables;
        $row['num'] = $num;

        return $row;
    }

    public function count($filter=null){
        $sql = 'SELECT count(DISTINCT sod.delivery_id) as _count FROM sdb_ome_delivery as sod'.
            ' LEFT JOIN sdb_ome_delivery_order as DO ON sod.delivery_id=DO.delivery_id'.
            ' LEFT JOIN sdb_ome_orders as O ON DO.order_id=O.order_id '.
            ' WHERE O.ship_status!=\'4\' and O.is_fail=\'false\' and sod.parent_id=0 AND sod.is_cod=\'true\' and sod.process=\'true\' and '.$this->_filter($filter);

        $row = $this->db->select($sql);
        return intval($row[0]['_count']);
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        $sql = 'SELECT DISTINCT sod.delivery_bn,sod.branch_id,sod.shop_id,sod.logi_name,sod.logi_no,sod.delivery_time,sod.weight,
            sod.ship_area,sod.ship_name,sod.delivery_cost_actual,sod.cost_protect,sod.delivery_id as receivables '.
            'FROM sdb_ome_delivery as sod'.
            ' LEFT JOIN sdb_ome_delivery_order as DO ON sod.delivery_id=DO.delivery_id'.
            ' LEFT JOIN sdb_ome_orders as O ON DO.order_id=O.order_id '.
            ' WHERE O.ship_status!=\'4\' and O.is_fail=\'false\' and sod.parent_id=0 AND sod.is_cod=\'true\' and sod.process=\'true\' and '.$this->_filter($filter);

        if($orderType)$sql.=' ORDER BY '.(is_array($orderType)?implode($orderType,' '):$orderType);
        $sql = str_replace('sdb_ome_delivery.','sod.',$sql);

        $rows = $this->db->selectLimit($sql,$limit,$offset);
        $this->tidy_data($rows, $cols);
        if ($rows){
            $deliOrderObj = app::get('ome')->model("delivery_order");
            $orderObj = app::get('ome')->model("orders");
            $dlyBillObj = app::get('ome')->model("delivery_bill");

            foreach($rows as $key=>$val){
                
                // 数据解密
                foreach ($this->__encrypt_cols as $field => $type) {
                    if (isset($val[$field])) {
                        $rows[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($val[$field],$type);
                    }
                }

                //计算应收金额
                $sql = 'SELECT sum(receivable) as receivable FROM sdb_ome_delivery_order as DO LEFT JOIN sdb_ome_order_extend as OE ON DO.order_id=OE.order_id WHERE DO.delivery_id='.$val['receivables'];
                $extend = $this->db->select($sql);

                $rows[$key]['receivables'] = $extend[0]['receivable'];
                $rows[$key]['delivery_id'] = $val['receivables'];
                $rows[$key]['balance'] = ($extend[0]['receivable']-$val['delivery_cost_actual']-$val['cost_protect']);
                $tmp_area = array();
                if ( preg_match("/:(.*):/", $val['ship_area'], $tmp_area) ){
                    $rows[$key]['ship_area'] = $tmp_area[1];
                }

                // begin 获取订单相关信息
                $orders = $deliOrderObj->getList('order_id',array('delivery_id'=>$val['receivables']));
                $orderId[] = 0;
                foreach($orders as $vv){
                    $orderId[] = $vv['order_id'];
                }
                $orderBn = $orderObj->getList('order_bn,is_cod,ship_tel,ship_mobile,ship_addr,total_amount',array('order_id'=>$orderId));
                foreach($orderBn as $vv){
                    if ($rows[$key]['order_bn']) {
                        $rows[$key]['order_bn'] .= ','.$vv['order_bn'];
                    }else{
                        $rows[$key]['order_bn'] = $vv['order_bn']."\t";
                    }
                    $rows[$key]['is_cod'] = $vv['is_cod'];
                    $rows[$key]['ship_tel'] = $vv['ship_tel'];
                    $rows[$key]['ship_mobile'] = $vv['ship_mobile']."\t";
                    $rows[$key]['ship_addr'] = $vv['ship_addr'];
                    $rows[$key]['total_amount'] += $vv['total_amount'];
                }
                unset($order_bn,$orderId);
                // end 获取订单相关信息
            }

            //加入子表数据显示
            //wujian@shopex.cn
            //2012年3月26日
            $rowbill = array();
            foreach($rows as $k=>$v){
                $rowbill[] = $v;
                $dlyBillInfo = $dlyBillObj->getList('logi_no,weight,delivery_time,delivery_cost_actual',array('delivery_id'=>$rows[$k]['delivery_id'],'status'=>1));
                if($dlyBillInfo){
                    foreach($dlyBillInfo as $kk=>$vv){
                        $v['logi_no'] = $dlyBillInfo[$kk]['logi_no'];
                        $v['weight'] = $dlyBillInfo[$kk]['weight'];
                        $v['delivery_time'] = $dlyBillInfo[$kk]['delivery_time'];
                        $v['delivery_cost_actual'] = $dlyBillInfo[$kk]['delivery_cost_actual'];
                        $v['balance'] = -$dlyBillInfo[$kk]['delivery_cost_actual'];
                        $v['receivables'] = 0;
                        $rowbill[] = $v;
                    }
                }
            }
        }
        return $rowbill;
    }

    public function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'order_bn'=>app::get('base')->_('订单号'),
        );
        return $Options = array_merge($parentOptions,$childOptions);
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        $where = array(1);
        if(isset($filter['time_from']) && $filter['time_from']){
            $where[] = ' sod.delivery_time >='.strtotime($filter['time_from']);
        }
        if(isset($filter['time_to']) && $filter['time_to']){
            $where[] = ' sod.delivery_time <'.(strtotime($filter['time_to'])+86400);
        }
        if(isset($filter['own_branches']) && $filter['own_branches']){
            $where[]= ' branch_id in ('.implode(',',$filter['own_branches']).')';
        }
        unset($filter['own_branches']);
        if(isset($filter['type_id']) && $filter['type_id']){
            $where[] = ' branch_id =\''.addslashes($filter['type_id']).'\'';
        }
        if(isset($filter['shop_id']) && $filter['shop_id']){
            $where[] = ' sdb_ome_delivery.shop_id =\''.addslashes($filter['shop_id']).'\'';
        }
        unset($filter['shop_id']);

        if(isset($filter['logi_no']) && $filter['logi_no']){
            $where[] = ' sod.logi_no =\''.$filter['logi_no'].'\'';
        }
        unset($filter['logi_no']);

        if(isset($filter['order_bn']) && $filter['order_bn']){
            $orderObj = app::get('ome')->model("orders");
            $rows = $orderObj->getList('order_id',array('order_bn|has'=>$filter['order_bn']));
            $orderId[] = 0;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }

            $deliOrderObj = app::get('ome')->model("delivery_order");
            $rows = $deliOrderObj->getList('delivery_id',array('order_id'=>$orderId));
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }

            $where[] = ' sod.delivery_id IN ('.implode(',', $deliveryId).')';
            unset($filter['order_bn']);
        }
    
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " sod.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }
        
        return parent::_filter($filter,$tableAlias,$baseWhere)." AND ".implode(' AND ', $where);
    }

    public function get_schema(){
        $schema = array (
            'columns' => array (
                'delivery_bn' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '发货单号',
                    'comment' => '配送流水号',
                    'editable' => false,
                    'width' =>140,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'is_title' => true,
                ),
                'branch_id' => array (
                        'type' => 'table:branch@ome',
                        'editable' => false,
                        'label' => '发货仓库',
                        'width' => 110,
                ),
                'shop_id' => array (
                        'type' => 'table:shop@ome',
                        'label' => '来源店铺',
                        'width' => 75,
                        'editable' => false,
                ),
                'logi_name' => array (
                        'type' => 'varchar(100)',
                        'label' => '物流公司',
                        'comment' => '物流公司名称',
                        'editable' => false,
                        'width' =>75,
                ),                    
     

                'logi_no' => array (
                    'type' => 'varchar(50)',
                    'label' => '快递单号',
                    'comment' => '物流单号',
                    'editable' => false,
                    'searchtype' => 'tequal',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'width' =>110,
                ),
                'delivery_time' => array (
                    'type' => 'time',
                    'label' => '发货时间',
                    'comment' => '单据生成时间',
                    'width' =>130,
                    'editable' => false,
                ),
                'weight' => array (
                    'type' => 'number',
                    'editable' => false,
                    'label' => '包裹重量',
                    'comment' => '包裹重量',
                    'width' =>85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'ship_area' => array (
                    'type' => 'region',
                    'label' => '收货地区',
                    'comment' => '收货人地区',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'width' =>130,
                    'sdfpath' => 'consignee/area',
                ),
                'ship_name' => array (
                    'type' => 'varchar(50)',
                    'label' => '收货人',
                    'comment' => '收货人姓名',
                    'editable' => false,
                    'searchtype' => 'tequal',
                    'width' =>75,
                    'sdfpath' => 'consignee/name',
                ),
                'delivery_cost_actual' => array (
                    'type' => 'money',
                    'editable' => false,
                    'label' => '配送费用',
                    'comment' => '物流费用(包裹重量计算的费用)',
                    'width' =>85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'cost_protect' => array (
                    'type' => 'money',
                    'default' => '0',
                    'label' => '保价费用',
                    'width' =>85,
                    'required' => false,
                    'editable' => false,
                ),
                'receivables' => array (
                    'type' => 'money',
                    'editable' => false,
                    'label' => '应收货款',
                    'comment' => '应收货款',
                    'width' =>110,
                ),
                'balance' => array (
                    'type' => 'money',
                    'editable' => false,
                    'label' => '结算金额',
                    'comment' => '结算金额',
                    'width' =>110,
                ),


                'order_bn' => array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '订单号',
                    'comment' => '订单号',
                    'editable' => false,
                    'width' =>200,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'is_title' => true,
                ),
                'is_cod' => array (
                    'type' => 'bool',
                    'required' => true,
                    'editable' => false,
                    'label' => '货到付款',
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => 60,
                    'is_title' => true,
                ),
                'ship_tel' => array (
                    'type' => 'varchar(30)',
                    'label' => '收货人电话',
                    'width' => 75,
                    'editable' => false,
                    'in_list' => true,
                    'is_title' => true,
                ),
                'ship_mobile' => array (
                    'label' => '收货人手机',
                    'hidden' => true,
                    'type' => 'varchar(50)',
                    'editable' => false,
                    'width' => 85,
                    'in_list' => true,
                    'is_title' => true,
                ),
                'ship_addr' => array (
                    'type' => 'varchar(100)',
                    'label' => '收货地址',
                    'width' => 180,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'in_list' => true,
                    'is_title' => true,
                ),
                'total_amount' => array (
                    'type' => 'money',
                    'default' => '0',
                    'label' => '订单总额',
                    'width' => 70,
                    'editable' => false,
                    'filtertype' => 'number',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => false,
                    'is_title' => true,
                ),
            ),
            'idColumn' => 'delivery_bn',
            'in_list' => array (
                0 => 'delivery_bn',
                1 => 'branch_id',
                2 => 'shop_id',
                3 => 'logi_name',
                4 => 'logi_no',
                5 => 'delivery_time',
                6 => 'weight',
                7 => 'ship_area',
                8 => 'ship_name',
                9 => 'delivery_cost_actual',
                10 => 'cost_protect',
                11 => 'receivables',
                12 => 'balance',
                13 => 'order_bn',
                14 => 'is_cod',
                15 => 'ship_tel',
                16 => 'ship_mobile',
                17 => 'ship_addr',
                18 => 'total_amount',
                
            ),
            'default_in_list' => array (
                0 => 'delivery_bn',
                1 => 'branch_id',
                2 => 'shop_id',
                3 => 'logi_name',
                4 => 'logi_no',
                5 => 'delivery_time',
                6 => 'weight',
                7 => 'ship_area',
                8 => 'ship_name',
                9 => 'delivery_cost_actual',
                10 => 'cost_protect',
                11 => 'receivables',
                12 => 'balance',
                13 => 'order_bn',
                14 => 'is_cod',
                15 => 'ship_tel',
                16 => 'ship_mobile',
                17 => 'ship_addr',
                18 => 'total_amount',
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
            $type .= '_purchaseReport_codAnalysis';
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
            $type .= '_purchaseReport_codAnalysis';
        }
        $type .= '_import';
        return $type;
    }

    public function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        @ini_set('memory_limit','1024M');
        if( !$data['title'] ){
            $title = array();
            foreach( $this->io_title() as $k => $v ){
                $title[] = $v;
            }
            $data['title']['omecod'] = mb_convert_encoding('"'.implode('","',$title).'"', 'GBK', 'UTF-8');
        }
        $oShop = app::get('ome')->model('shop');
        $rs = $oShop->getList('shop_id,name');
        foreach($rs as $v) {
            $shops[$v['shop_id']] = $v;
        }
        unset($rs);

        $Obranch = app::get('ome')->model('branch');
        $branchs = $Obranch->getList('branch_id,name',array('is_deliv_branch'=>'true'));
        foreach ($branchs as $v) {
            $branch[$v['branch_id']] = $v['name'];
        }
        unset($branchs);

        $limit = 100;
        if(!$omecods = $this->getlist('*',$filter,$offset*$limit,$limit)) return false;
        foreach ($omecods as $aFilter) {
            $omecod['*:发货单号'] = "=\"\"".$aFilter['delivery_bn']."\"\"";
            $omecod['*:发货仓库'] = $branch[$aFilter['branch_id']]['name'];
            $omecod['*:来源店铺'] = $shops[$aFilter['shop_id']]['name'];
            $omecod['*:物流公司'] = $aFilter['logi_name'];
            $omecod['*:快递单号'] = $aFilter['logi_no'];
            $omecod['*:发货时间'] = date('Y-m-d H:i:s',$aFilter['delivery_time']);
            $omecod['*:包裹重量'] = $aFilter['weight'];
            $omecod['*:收货地区'] = $aFilter['ship_area'];
            $omecod['*:收货人'] = $aFilter['ship_name'];
            $omecod['*:配送费用'] = $aFilter['delivery_cost_actual'];
            $omecod['*:保价费用'] = $aFilter['cost_protect'];
            $omecod['*:应收货款'] = $aFilter['receivables'];
            $omecod['*:结算金额'] = $aFilter['balance'];
            $omecod['*:订单号'] = "=\"\"".$aFilter['order_bn']."\"\"";
            $omecod['*:货到付款'] = '是';
            $omecod['*:收货人电话'] = $aFilter['ship_tel'];
            $omecod['*:收货人手机'] = $aFilter['ship_mobile'];
            $omecod['*:收货地址'] = $aFilter['ship_addr'];
            $omecod['*:订单总额'] = $aFilter['total_amount'];
            
            $data['content']['omecod'][] = mb_convert_encoding('"'.implode('","',$omecod).'"', 'GBK', 'UTF-8');
        }
        return true;
    }

    public function io_title($filter=null,$ioType='csv'){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
               '*:发货单号' => 'delivery_bn',
               '*:发货仓库' => 'branch_id',
               '*:来源店铺' => 'shop_id',
               '*:物流公司' => 'logi_name',
               '*:快递单号' => 'logi_no',
               '*:发货时间' => 'delivery_time',
               '*:包裹重量' => 'weight',
               '*:收货地区' => 'ship_area',
               '*:收货人' => 'ship_name',
               '*:配送费用' => 'delivery_cost_actual',
               '*:保价费用' => 'cost_protect',
               '*:应收货款' => 'receivables',
               '*:结算金额' => 'balance',
               '*:订单号' => 'order_bn',
               '*:货到付款' => 'is_cod',
               '*:收货人电话' => 'ship_tel',
               '*:收货人手机' => 'ship_mobile',
               '*:收货地址' => 'ship_addr',
               '*:订单总额' => 'total_amount',
                );
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType][$filter];
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();
        $output[] = $data['title']['omecod']."\n".implode("\n",(array)$data['content']['omecod']);
        echo implode("\n",$output);
    }

    //根据过滤条件获取导出发货单的主键数据数组
    public function getPrimaryIdsByCustom($filter, $op_id){
        $rows = array();
        $sql = 'SELECT DISTINCT sod.delivery_id '.
            'FROM sdb_ome_delivery as sod'.
            ' LEFT JOIN sdb_ome_delivery_order as DO ON sod.delivery_id=DO.delivery_id'.
            ' LEFT JOIN sdb_ome_orders as O ON DO.order_id=O.order_id '.
            ' WHERE O.ship_status!=\'4\' and O.is_fail=\'false\' and sod.parent_id=0 AND sod.is_cod=\'true\' and sod.process=\'true\' and '.$this->_filter($filter);

        $sql = str_replace('sdb_ome_delivery.','sod.',$sql);

        $rows = $this->db->select($sql);

        $ids = array();
        foreach ($rows as $k => $row){
            $ids[] = $row['delivery_id'];
        }

        return $ids;
    }

    //根据主键id获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id){

        $ids = $filter['delivery_id'];

        $sql = "SELECT sod.delivery_bn,sod.branch_id,sod.shop_id,sod.logi_name,sod.logi_no,sod.delivery_time,sod.weight,
            sod.ship_area,sod.ship_name,sod.delivery_cost_actual,sod.cost_protect,sod.delivery_id as receivables ".
            "FROM sdb_ome_delivery as sod".
            " WHERE sod.delivery_id in (".implode(',',$ids).")";

        $rows = $this->db->select($sql);
        $this->tidy_data($rows, $cols);
        if ($rows){
            $deliOrderObj = app::get('ome')->model("delivery_order");
            $orderObj = app::get('ome')->model("orders");
            $dlyBillObj = app::get('ome')->model("delivery_bill");

            foreach($rows as $key=>$val){
                
                // 数据解密
                foreach ($this->__encrypt_cols as $field => $type) {
                    if (isset($val[$field])) {
                        $rows[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($val[$field],$type);
                    }
                }

                //计算应收金额
                $sql = 'SELECT sum(receivable) as receivable FROM sdb_ome_delivery_order as DO LEFT JOIN sdb_ome_order_extend as OE ON DO.order_id=OE.order_id WHERE DO.delivery_id='.$val['receivables'];
                $extend = $this->db->select($sql);

                $rows[$key]['receivables'] = $extend[0]['receivable'];
                $rows[$key]['delivery_id'] = $val['receivables'];
                $rows[$key]['balance'] = ($extend[0]['receivable']-$val['delivery_cost_actual']-$val['cost_protect']);
                $tmp_area = array();
                if ( preg_match("/:(.*):/", $val['ship_area'], $tmp_area) ){
                    $rows[$key]['ship_area'] = $tmp_area[1];
                }

                // begin 获取订单相关信息
                $orders = $deliOrderObj->getList('order_id',array('delivery_id'=>$val['receivables']));
                $orderId[] = 0;
                foreach($orders as $vv){
                    $orderId[] = $vv['order_id'];
                }
                $orderBn = $orderObj->getList('order_bn,is_cod,ship_tel,ship_mobile,ship_addr,total_amount',array('order_id'=>$orderId));
                foreach($orderBn as $vv){
                    if ($rows[$key]['order_bn']) {
                        $rows[$key]['order_bn'] .= '、'.$vv['order_bn'];
                    }else{
                        $rows[$key]['order_bn'] = $vv['order_bn']."\t";
                    }
                    $rows[$key]['is_cod'] = $vv['is_cod'];
                    $rows[$key]['ship_tel'] = $vv['ship_tel'];
                    $rows[$key]['ship_mobile'] = $vv['ship_mobile']."\t";
                    $rows[$key]['ship_addr'] = $vv['ship_addr'];
                    $rows[$key]['total_amount'] += $vv['total_amount'];
                }
                unset($order_bn,$orderId);
                // end 获取订单相关信息
            }

            //加入子表数据显示
            //wujian@shopex.cn
            //2012年3月26日
            $rowbill = array();
            foreach($rows as $k=>$v){
                $rowbill[] = $v;
                $dlyBillInfo = $dlyBillObj->getList('logi_no,weight,delivery_time,delivery_cost_actual',array('delivery_id'=>$rows[$k]['delivery_id'],'status'=>1));
                if($dlyBillInfo){
                    foreach($dlyBillInfo as $kk=>$vv){
                        $v['logi_no'] = $dlyBillInfo[$kk]['logi_no'];
                        $v['weight'] = $dlyBillInfo[$kk]['weight'];
                        $v['delivery_time'] = $dlyBillInfo[$kk]['delivery_time'];
                        $v['delivery_cost_actual'] = $dlyBillInfo[$kk]['delivery_cost_actual'];
                        $v['balance'] = -$dlyBillInfo[$kk]['delivery_cost_actual'];
                        $v['receivables'] = 0;
                        $rowbill[] = $v;
                    }
                }
            }
        }

        //根据选择的字段定义导出的第一行标题
        if($curr_sheet == 1){
            $data['content']['main'][] = $this->getExportTitle($fields);
        }

        $oShop = app::get('ome')->model('shop');
        $rs = $oShop->getList('shop_id,name');
        foreach($rs as $v) {
            $shops[$v['shop_id']] = $v;
        }
        unset($rs);

        $Obranch = app::get('ome')->model('branch');
        $branchs = $Obranch->getList('branch_id,name',array('is_deliv_branch'=>'true'));
        foreach ($branchs as $v) {
            $branch[$v['branch_id']] = $v['name'];
        }
        unset($branchs);

        if(!$omecods = $rowbill) return false;
        foreach ($omecods as $aFilter) {
            $omecod['delivery_bn'] = $aFilter['delivery_bn'];
            $omecod['branch_id'] = $branch[$aFilter['branch_id']];
            $omecod['shop_id'] = $shops[$aFilter['shop_id']]['name'];
            $omecod['logi_name'] = $aFilter['logi_name'];
            $omecod['logi_no'] = $aFilter['logi_no'];
            $omecod['delivery_time'] = date('Y-m-d H:i:s',$aFilter['delivery_time']);
            $omecod['weight'] = $aFilter['weight'];
            $omecod['ship_area'] = $aFilter['ship_area'];
            $omecod['ship_name'] = $aFilter['ship_name'];
            $omecod['delivery_cost_actual'] = $aFilter['delivery_cost_actual'];
            $omecod['cost_protect'] = $aFilter['cost_protect'];
            $omecod['receivables'] = $aFilter['receivables'] ? $aFilter['receivables'] : '0.00';
            $omecod['balance'] = $aFilter['balance'];
            $omecod['order_bn'] = $aFilter['order_bn'];
            $omecod['is_cod'] = '是';
            $omecod['ship_tel'] = $aFilter['ship_tel'];
            $omecod['ship_mobile'] = $aFilter['ship_mobile'];
            $omecod['ship_addr'] = $aFilter['ship_addr'];
            $omecod['total_amount'] = $aFilter['total_amount'];
            
            $exptmp_data = array();
            foreach (explode(',', $fields) as $key => $col) {
                if(isset($omecod[$col])){
                    //过滤地址里的特殊字符
                    $omecod[$col] = str_replace('&nbsp;', '', $omecod[$col]);
                    $omecod[$col] = str_replace(array("\r\n","\r","\n"), '', $omecod[$col]);
                    $omecod[$col] = str_replace(',', '', $omecod[$col]);

                    $omecod[$col] = mb_convert_encoding($omecod[$col], 'GBK', 'UTF-8');
                    $exptmp_data[] = $omecod[$col];
                }
                else
                {
                    $exptmp_data[]    = '';
                }
            }

            $data['content']['main'][] = implode(',', $exptmp_data);
        }

        return $data;
    }}
