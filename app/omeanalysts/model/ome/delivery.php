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
class omeanalysts_mdl_ome_delivery extends dbeav_model{

    var $has_export_cnf = true;

    var $export_name = '快递费结算表';

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
            return $table_name;
        }
    }

    public function get_delivery($filter=null){

        $nums = 0;
        $cost = 0;
        //包裹数量、快递成本
        $sql = 'SELECT sum(logi_number) as nums,sum(delivery_cost_actual) as sum_cost_actual FROM sdb_ome_delivery where parent_id=0 and is_cod=\'false\' and process=\'true\' and '.$this->_filter($filter);
        $rows = $this->db->select($sql); 
        $cost += $rows[0]['sum_cost_actual']; 
        $nums += $rows[0]['nums'];

        $sql = 'SELECT delivery_id FROM sdb_ome_delivery where logi_number>1 and parent_id=0 and is_cod=\'false\' and process=\'true\' and '.$this->_filter($filter);
        $rows = $this->db->select($sql);  
        
        $dlyBillObj = app::get('ome')->model("delivery_bill");

        foreach($rows as $k=>$v){
            $deliveryids[] = $v['delivery_id'];          
        }
        
        unset($rows);

        if($deliveryids){

            $bills = $this->db->select('select sum(delivery_cost_actual) as sum_cost_actual from sdb_ome_delivery_bill where status=\'1\' and delivery_id in('.implode(',',$deliveryids).') limit 1');
            
            $cost += $bills[0]['sum_cost_actual'];
        }

        //买家支付运费
        //$sql_costfreight = 'SELECT sum(sdb_ome_orders.cost_freight) as sum_freight FROM sdb_ome_orders,sdb_ome_delivery,sdb_ome_delivery_order where sdb_ome_delivery.delivery_id=sdb_ome_delivery_order.delivery_id and sdb_ome_orders.order_id=sdb_ome_delivery_order.order_id and sdb_ome_delivery.parent_id=0 and sdb_ome_delivery.is_cod=\'false\' and sdb_ome_delivery.process=\'true\' and '.$this->_filter($filter);
        $sql_costfreight = 'SELECT sum(sdb_ome_orders.cost_freight) as sum_freight FROM sdb_ome_delivery INNER JOIN sdb_ome_delivery_order ON sdb_ome_delivery.delivery_id=sdb_ome_delivery_order.delivery_id INNER JOIN sdb_ome_orders ON sdb_ome_orders.order_id=sdb_ome_delivery_order.order_id WHERE sdb_ome_delivery.parent_id=0 and sdb_ome_delivery.is_cod=\'false\' and sdb_ome_delivery.process=\'true\' and '.$this->_filter($filter);

        $costfreights = $this->db->select($sql_costfreight);

        return array('num'=>$nums,'cost'=>$cost,'costfreight'=>$costfreights[0]['sum_freight']);
    }

    public function count($filter=null){
        $sql = 'SELECT count(*) as _count FROM sdb_ome_delivery WHERE parent_id=0 and '.
            'is_cod=\'false\' and process=\'true\' and '.$this->_filter($filter);

        $row = $this->db->select($sql);
        return intval($row[0]['_count']);
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        if(isset($filter['logi_no'])){
            $obj_delivery_bill = $deliOrderObj = app::get('ome')->model("delivery_bill");
            #获取子表物流单号
            $delivery_id = $obj_delivery_bill->dump(array('logi_no'=>$filter['logi_no']),'delivery_id');
            if(!empty($delivery_id['delivery_id'])){
                unset($filter['logi_no']);
                $filter['delivery_id'] = $delivery_id['delivery_id'];
            }
        }
        $sql = 'SELECT delivery_bn,logi_name,logi_no,delivery_time,weight,ship_area,ship_name,delivery_cost_actual,cost_protect,(delivery_cost_actual+cost_protect) as balance,shop_id,delivery_id,branch_id,status,ship_province,ship_city,ship_district
            FROM sdb_ome_delivery WHERE 
            parent_id=0 AND is_cod=\'false\' and process=\'true\' and '.$this->_filter($filter);

        if($orderType)$sql.=' ORDER BY '.(is_array($orderType)?implode($orderType,' '):$orderType);

        $rows = $this->db->selectLimit($sql,$limit,$offset);

        foreach($rows as $key=>$arr){

            // 数据解密
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($arr[$field])) {
                    $rows[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($arr[$field],$type);
                }
            }

            $rows[$key]['balance'] = $arr['delivery_cost_actual']+$arr['cost_protect'];
        }
        $this->tidy_data($rows, $cols);
        if ($rows){
            $deliOrderObj = app::get('ome')->model("delivery_order");
            $orderObj = app::get('ome')->model("orders");
            $dlyBillObj = app::get('ome')->model("delivery_bill");
            $dlyItemsMdl = app::get('ome')->model("delivery_items");
    
            //查询订单
            $deliveryIds      = array_column($rows, 'delivery_id');
            $ordersIdList     = $deliOrderObj->getList('order_id,delivery_id', ['delivery_id' => $deliveryIds]);
            $newDeliveryOrder = [];
            foreach ($ordersIdList as $d_o) {
                $newDeliveryOrder[$d_o['delivery_id']][] = $d_o['order_id'];
            }
    
            $orderIds  = array_column($ordersIdList, 'order_id');
            $orderList = $orderObj->getList('cost_freight,order_bn,is_cod,ship_tel,ship_mobile,ship_addr,total_amount,shop_type,order_id,order_type', ['order_id' => $orderIds]);
            $orderList = array_column($orderList, null, 'order_id');
            
            $deliveryids = array();
            foreach ($rows as $key=>$val){
                $deliveryids[] = $val['delivery_id'];
                $tmp_area = array();
                if ( preg_match("/:(.*):/", $val['ship_area'], $tmp_area) ){
                    $rows[$key]['ship_area'] = $tmp_area[1];
                }

                //$rows[$key]['delivery_bn'] = "=\"\"".$rows[$key]['delivery_bn']."\"\"";
                $rows[$key]['delivery_bn'] .= "\t";
                $rows[$key]['logi_no'] .= "\t";

                // begin 获取订单相关信息
                $orderIds = $newDeliveryOrder[$val['delivery_id']] ?? [];
                $newOrderList = [];
                foreach($orderIds as $order_id){
                    $newOrderList[] = $orderList[$order_id];
                }

                $cost_freight =0;
                foreach($newOrderList as $vv){
                    
                    if ($rows[$key]['order_bn']) {
                        $rows[$key]['order_bn'] .= ','.$vv['order_bn'];
                        $cost_freight+=$vv['cost_freight'];
                    }else{
                        $rows[$key]['order_bn'] = $vv['order_bn']."\t";
                        $cost_freight = $vv['cost_freight'];
                    }
                    $rows[$key]['is_cod'] = $vv['is_cod'] == 'true' ? '是' :'否';
                    $rows[$key]['ship_tel'] = $vv['ship_tel']."\t";
                    $rows[$key]['ship_mobile'] = $vv['ship_mobile']."\t";
                    $rows[$key]['ship_addr'] = $vv['ship_addr'];
                    $rows[$key]['cost_freight'] = $cost_freight;
                    $rows[$key]['total_amount'] += $vv['total_amount'];
                    $rows[$key]['shop_type'] = kernel::single('omeanalysts_shop')->getShopType($vv['shop_type']);
                    $rows[$key]['order_type'] = $vv['order_type'];
                }
                unset($order_bn,$orderId);
                // end 获取订单相关信息
            }

            if($deliveryids){
                $dlyBillList = $rowAll = $billInfo = $deliveryItemList = array();
                $dlyBillInfo = $dlyBillObj->getList('delivery_id,logi_no,weight,delivery_time,delivery_cost_actual',array('delivery_id|in'=>$deliveryids,'status'=>1));
                foreach($dlyBillInfo as $k=>$v){
                    $dlyBillList[$v['delivery_id']][] = $v;
                }
                unset($dlyBillInfo);
                
                //查询发货单明细
                $deliveryItems = $dlyItemsMdl->getList('delivery_id,number,verify_num,bn',['delivery_id'=>$deliveryids]);
                foreach($deliveryItems as $item){
                    $deliveryItemList[$item['delivery_id']][] = $item;
                }
                unset($deliveryItems);
                
                foreach($rows as $rk=>$rv){
                    $billWeight = array_sum(array_column((array)$dlyBillList[$rv['delivery_id']], 'weight'));
                    $rv['weight'] -= $billWeight;
                    $rv['sum_number'] = array_sum(array_column((array)$deliveryItemList[$rv['delivery_id']], 'number'));

                    $rowAll[] = $rv;
                    if(isset($dlyBillList[$rv['delivery_id']]) && count($dlyBillList[$rv['delivery_id']])>0){
                        foreach($dlyBillList[$rv['delivery_id']] as $dk=>$dv){
                            $rv['logi_no'] = $dv['logi_no']."\t";
                            $rv['weight'] = $dv['weight'] ?? 0;
                            $rv['delivery_time'] = $dv['delivery_time'];
                            $rv['delivery_cost_actual'] = $dv['delivery_cost_actual'];
                            $rv['balance'] = $dv['delivery_cost_actual'];
                            $rv['sum_number'] = 0;
                            $rowAll[] = $rv;
                        }
                    }
                }
            }
        }
        unset($rows,$dlyBillList,$deliveryItemList);
        return $rowAll;
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
            $where[] = ' sdb_ome_delivery.delivery_time >='.strtotime($filter['time_from']);
        }
        if(isset($filter['time_to']) && $filter['time_to']){
            $where[] = ' sdb_ome_delivery.delivery_time <'.(strtotime($filter['time_to'])+86400);
        }
        
        if(isset($filter['own_branches']) && $filter['own_branches']){
            $where[]= ' sdb_ome_delivery.branch_id in ('.implode(',',$filter['own_branches']).')';
        }
        unset($filter['own_branches']);

        if(isset($filter['branch_id']) && $filter['branch_id']){
            $where[] = ' sdb_ome_delivery.branch_id =\''.addslashes($filter['branch_id']).'\'';
            
        }
        unset($filter['branch_id']);
        if(isset($filter['logi_id']) && $filter['logi_id']){
            $where[] = ' sdb_ome_delivery.logi_id ='.addslashes($filter['logi_id']);
            
        }
        unset($filter['logi_id']);
        if(isset($filter['shop_id']) && $filter['shop_id']){
            if(is_array($filter['shop_id'])) {
                $shopIds = array_filter($filter['shop_id']);
                if($shopIds){
                    // 对数组中每个元素进行 addslashes 转义
                    $escapedShopIds = array_map('addslashes', $shopIds);
                    $where[] = ' sdb_ome_delivery.shop_id IN (\'' . implode("','", $escapedShopIds) . '\')';
                }
            } else {
                $where[] = ' sdb_ome_delivery.shop_id =\''.addslashes($filter['shop_id']).'\'';
            }
        }
        unset($filter['shop_id']);
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

            $where[] = ' delivery_id IN ('.implode(',', $deliveryId).')';
            unset($filter['order_bn']);
        }
        if(isset($filter['delivery_id'])){
            $where[] = ' sdb_ome_delivery.delivery_id ='.$filter['delivery_id'];
            unset($filter['delivery_id']);
        }

        if (isset($filter['shop_type']) && $filter['shop_type']){
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            $shop_ids = $shopList[$filter['shop_type']];

            if ($shop_ids){
                $where[] = " sdb_ome_delivery.shop_id in ('".implode('\',\'',$shop_ids)."')";
            }
        }
    
        if(isset($filter['org_id']) && $filter['org_id']){
            $where[] = " sdb_ome_delivery.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }
        
        unset($filter['shop_type']);
        return parent::_filter($filter,'sdb_ome_delivery',$baseWhere)." AND ".implode(' AND ', $where);
    }

    public function get_schema(){
        $ordersColumns   = app::get('ome')->model('orders')->get_schema();
        $deliveryColumns = app::get('ome')->model('delivery')->get_schema();
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
                'logi_id' => array (
                    'type' => 'table:dly_corp@ome',
                    'comment' => '物流公司ID',
                    'editable' => false,
                    'label' => '物流公司',
                    'deny_export' => true,
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
                    'label' => '物流单号',
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
                ),
                'ship_name' => array (
                    'type' => 'varchar(50)',
                    'label' => '收货人',
                    'comment' => '收货人姓名',
                    'editable' => false,
                    'searchtype' => 'tequal',
                    'width' =>75,
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
                'balance' => array (
                    'type' => 'money',
                    'editable' => false,
                    'label' => '结算金额',
                    'comment' => '结算金额',
                    'width' =>110,
                ),
                'shop_id' => array (
                    'type' => 'table:shop@ome',
                    'label' => '来源店铺',
                    'width' => 75,
                    'editable' => false,
                ),
                'shop_type'=>array(
                    'type' => 'varchar(32)',
                    'label' => '店铺类型',
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => '70'
                ),
                'branch_id' => array (
                    'type' => 'table:branch@ome',
                    'editable' => false,
                    'label' => '发货仓库',
                    'width' => 110,
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
                    'width' => 70,
                    'is_title' => true,
                ),
                'ship_tel' => array (
                    'type' => 'varchar(30)',
                    'label' => '收货人电话',
                    'width' => 80,
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
                'cost_freight' =>
                array (
                  'type' => 'money',
                  'default' => '0',
                  'required' => true,
                  'label' => '收取运费',
                  'width' => 70,
                  'editable' => false,
                  'filtertype' => 'number',
                  'sdfpath' => 'shipping/cost_shipping',
                  'default_in_list' => true,
                  'in_list' => true,
                  'orderby' => false,
                ),                
                'total_amount' => array (
                    'type' => 'money',
                    'default' => '0',
                    'label' => '订单总额',
                    'width' => 70,
                    'editable' => false,
                    //'filtertype' => 'number',
                    //'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => false,
                    'is_title' => true,
                ),
                'ship_province' => array (
                    'type' => 'varchar(100)',
                    'label' => '省',
                    'width' => 100,
                    'editable' => false,
                ),
                'ship_city' => array (
                    'type' => 'varchar(100)',
                    'label' => '市',
                    'width' => 100,
                    'editable' => false,
                ),
                'ship_district' => array (
                    'type' => 'varchar(100)',
                    'label' => '区/县',
                    'width' => 100,
                    'editable' => false,
                ),
                'sum_number' =>
                    array(
                        'type'     => 'number',
                        'default'  => 0,
                        'editable' => false,
                        'label' => '商品数量',
                        'comment'  => '商品数量',
                    ),

            ),
            'idColumn' => 'delivery_bn',
            'in_list' => array (
                0 => 'delivery_bn',
                1 => 'logi_name',
                2 => 'logi_no',
                3 => 'delivery_time',
                4 => 'weight',
                5 => 'ship_area',
                6 => 'ship_name',
                7 => 'delivery_cost_actual',
                8 => 'cost_protect',
                9 => 'balance',
                10 => 'shop_id',
                11 => 'order_bn',
                12 => 'is_cod',
                13 => 'ship_tel',
                14 => 'ship_mobile',
                15 => 'ship_addr',
                16 => 'total_amount',
                17 => 'branch_id',
                18 => 'cost_freight',
                19 =>'shop_type',
                20 =>'order_type',
                21 =>'status',
                22 =>'ship_province',
                23 =>'ship_city',
                24 =>'ship_district',
                25 =>'sum_number',
            ),
            'default_in_list' => array (
                0 => 'delivery_bn',
                1 => 'logi_name',
                2 => 'logi_no',
                3 => 'delivery_time',
                4 => 'weight',
                5 => 'ship_area',
                6 => 'ship_name',
                7 => 'delivery_cost_actual',
                8 => 'cost_protect',
                9 => 'balance',
                10 => 'shop_id',
                11 => 'order_bn',
                12 => 'is_cod',
                13 => 'ship_tel',
                14 => 'ship_mobile',
                15 => 'ship_addr',
                16 => 'total_amount',
                17 => 'branch_id',
                18 => 'cost_freight',
                19 => 'shop_type',
                20 => 'order_type',
                21 => 'status',
                22 =>'ship_province',
                23 =>'ship_city',
                24 =>'ship_district',
                25 =>'sum_number',
            ),
        );
        $schema['columns']['order_type'] = $ordersColumns['columns']['order_type'];
        $schema['columns']['status'] = $deliveryColumns['columns']['status'];
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
            $type .= '_purchaseReport_expressAnalysis';
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
            $type .= '_purchaseReport_expressAnalysis';
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

        if(!$omedelivery = $this->getList('*',$filter,$start,$end)) return false;
        
        //error_log(var_export($new_rows,true),3,'/www/newr.txt');

        $new_omedelivery = array();
        foreach ($omedelivery as $k => $aFilter) {
            foreach (explode(',', $fields) as $key => $col) {
                if(isset($aFilter[$col])){
                    $new_omedelivery[$k][$col] = $aFilter[$col];
                }
            }
        }

        $new_rows = $this->formatCsvExport($new_omedelivery);
        foreach ($new_rows as $k => $aFilter) {
            $exptmp_data = array();
            foreach ($aFilter as $col => $val) {
                $aFilter[$col] = mb_convert_encoding($aFilter[$col], 'GBK', 'UTF-8');
                $exptmp_data[] = ($aFilter[$col] ? $aFilter[$col] : '-');
            }
            
            $data['content']['main'][] = implode(',', $exptmp_data);
        }

        return $data;
    }
}
