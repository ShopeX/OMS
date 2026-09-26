<?php

class ome_mdl_payments extends dbeav_model{
    var $has_export_cnf = true;
    var $export_name = '销售收款单';
    var $export_flag = false;
    var $defaultOrder = array('t_begin DESC');

    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        if (isset($filter['order_bn'])){
            $orderObj = $this->app->model("orders");
            $rows = $orderObj->getList('order_id',array('order_bn'=>$filter['order_bn']));
            $orderId[] = 0;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }
            $archive_ordObj = kernel::single('archive_interface_orders');

            $archives = $archive_ordObj->getOrder_list(array('order_bn'=>$filter['order_bn']),'order_id');

            foreach ($archives  as $archive ) {
                $orderId[] = $archive['order_id'];
            }
            $where .= '  AND order_id IN ('.implode(',', $orderId).')';
            unset($filter['order_bn']);
        }
        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }

    /* create_payments 添加付款单
     * @param sdf $sdf
     * @return sdf
     */
    function create_payments(&$sdf){
        $payment_bn = $this->dump(array('payment_bn'=>$sdf['payment_bn'],'shop_id'=>$sdf['shop_id']),'payment_id,payment_bn');
        if(!empty($payment_bn)){
            $sdf['payment_id'] = $payment_bn['payment_id'];
            return true;
        }
        $order_id = $sdf['order_id'];
        $payment_money = $sdf['money'];
        $addon['currency'] = $sdf['currency'];
        $addon['paytime'] = $sdf['t_begin'];
        $save_result = false;
        if ($sdf['is_orderupdate'] != 'false'){
            if ($this->_updateOrder($order_id,$payment_money,$addon)){
                if ($this->save($sdf)){
                    $save_result = true;
                }else{
                    $sql = " UPDATE `sdb_ome_orders` SET `payed`=`payed`-".$sdf['money']." WHERE `order_id`='".$order_id."'";
                    $this->db->exec($sql,true);
                }
            }
        }else{
            if ($this->save($sdf)){
                $save_result = true;
            }
        }

        if ($save_result){
            $objOrder = $this->app->model('orders');
            //如果有OME自动确认订单插件的话，会按照自动确认规则来自动确认订单
            /*if($oAuto = kernel::service('do_autodispatch')){
                $order_sdf = $objOrder->dump($order_id,"*",array("order_objects"=>array("*",array("order_items"=>array("*")))));
                if($order_sdf['shipping']['is_cod'] == 'false' && $order_sdf['pay_status'] == 1){
                    if(method_exists($oAuto,'autodispatch')){
                        $oAuto->autodispatch($order_sdf);
                    }
                }
            }*/
            //如果有KPI考核插件，会增加客服的考核
            if($oKpi = kernel::service('omekpi_servicer_incremental')){
                $kpi_sdf = $objOrder->dump($order_id);
                if(method_exists($oKpi,'getOrderIncremental')){
                    $oKpi->getOrderIncremental($kpi_sdf);
                }
            }


            foreach(kernel::servicelist('ome.service.order.payment.after') as $service) {
                if(method_exists($service,'after_payment')) {
                    $service->after_payment($sdf);
                }
            }
            return true;
        }else{
            return false;
        }
    }

    /*
     * 更新订单状态及金额
     * @param string order_id
     * @param money payment_money
     * @param array $addon 附加参数
     */
    private function _updateOrder($order_id,$payment_money,$addon=array()){

        $update_fileds = "`payed`=IF(`payed`+{$payment_money}>`total_amount`, `payed`, `payed`+{$payment_money}),`currency`='".$addon['currency']."',`paytime`='".$addon['paytime']."'";
        $sql ="UPDATE `sdb_ome_orders` SET {$update_fileds} WHERE `order_id`='".$order_id."'";

        if ($payment_money == '0' || ($this->db->exec($sql,true) && $this->db->affect_row())) {
            //更新订单支付状态
            if (kernel::single('ome_order_func')->update_order_pay_status($order_id, true, __CLASS__.'::'.__FUNCTION__)){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * 发起前端店铺支付单请求
     * @param mixed $sdf 请求数据
     * @return boolean
     */
    function payment_request($sdf=NULL){
        if (IS_NULL($sdf)) return false;
        
        $objOrder = $this->app->model('orders');
        $orderdata['pay_status'] = '8';//订单支付状态:支付中
        $filter = array('order_id'=>$sdf['order_id']);
        $objOrder->update($orderdata,$filter);
        kernel::database()->commit();

        //支付单支付请求
        if ($service_payment = kernel::servicelist('service.payment')){
            foreach($service_payment as $object=>$instance){
                if(method_exists($instance,'payment_request')){
                    $instance->payment_request($sdf);
                }
            }
        }

        return true;
    }

    function getMethods($type=''){
        if($type=="online"){
            $sql = ' AND pay_type NOT IN(\'OFFLINE\',\'DEPOSIT\')';
        }else{
            if ($type){
                $sql = ' AND pay_type=\''.$type.'\'';
            }
        }
        return $this->db->select('SELECT * FROM sdb_ome_payment_cfg WHERE disabled = \'false\''.$sql,PAGELIMIT);
    }
    function getAccount(){
        $account = $this->app->model('bank_account')->getList('bank,account');
        return $account;
    }

    /*
     * 生成付款单号
     *
     *
     * @return 付款单号
     */
    function gen_id(){
        $prefix = date("YmdH");
        $payment_bn = kernel::single('eccommon_guid')->incId('payments', $prefix, 7, true);
        return $payment_bn;
        
        /*
        $i = rand(0,9999);
        do{
            if(9999==$i){
                $i=0;
            }
            $i++;
            $payment_bn = date("YmdH").'12'.str_pad($i,6,'0',STR_PAD_LEFT);
            $row = $this->db->selectrow('select payment_id from sdb_ome_payments where payment_bn =\''.$payment_bn.'\'');
        }while($row);
        return $payment_bn;
        */
    }

    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'order_bn'=>app::get('base')->_('订单号'),
        );
        return $Options = array_merge($parentOptions,$childOptions);
    }

    function io_title( $filter=null,$ioType='csv' ){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['payments'] = array(
                    'bn:支付单号' => 'payment_bn',
                    'col:订单号'=>'order_id',
                    'col:收款账号' => 'account',
                    'col:收款银行' => 'bank',
                    'col:支付账户' => 'pay_account',
                    'col:货币' => 'currency',
                    'col:支付金额' => 'money',
                    'col:支付网关费用' => 'paycost',
                    'col:支付类型' => 'pay_type',
                    'col:操作员' => 'op_id',
                    'col:支付IP' => 'ip',
                    'col:支付开始时间' => 't_begin',
                    'col:支付完成时间' => 't_end',
                    'col:支付状态' => 'status',
                    'col:备注' => 'memo',
                    'col:来源店铺' => 'shop_id',
                );
                break;
        }
        $this->ioTitle[$ioType]['payments'] = array_keys( $this->oSchema[$ioType]['payments'] );
        return $this->ioTitle[$ioType][$filter];
     }

     //csv导出
     function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        if( !$data['title']['payments'] ){
            $title = array();
            foreach( $this->io_title('payments') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['payments'] = '"'.implode('","',$title).'"';
        }
        $limit = 100;
        //if( $filter[''] )
        if( !$list=$this->getList('payment_id',$filter,$offset*$limit,$limit) )return false;
        foreach( $list as $aFilter ){
            $aOrder = $this->dump($aFilter['payment_id']);
            $aOrder['payment_bn'] = "=\"\"".$aOrder['payment_bn']."\"\"";

            //处理时间
            $aOrder['t_begin'] = $aOrder['t_begin']?date('Y-m-d H:i:s',$aOrder['t_begin']):'';
            $aOrder['t_end'] = $aOrder['t_end']?date('Y-m-d H:i:s',$aOrder['t_end']):'';

            //处理支付类型
            switch ($aOrder['pay_type']){
                case 'online':
                    $aOrder['pay_type'] = '在线支付';
                    break;
                case 'offline':
                    $aOrder['pay_type'] = '线下支付';
                    break;
                case 'deposit':
                    $aOrder['pay_type'] = '预存款支付';
                    break;
            }

            //处理支付状态
            switch ($aOrder['status']){
                case 'succ':
                    $aOrder['status'] = '支付成功';
                    break;
                case 'failed':
                    $aOrder['status'] = '支付失败';
                    break;
                case 'cancel':
                    $aOrder['status'] = '未支付';
                    break;
               case 'error':
                    $aOrder['status'] = '处理异常';
                    break;
              case 'invalid':
                    $aOrder['status'] = '非法参数';
                    break;
              case 'progress':
                    $aOrder['status'] = '处理中';
                    break;
              case 'timeout':
                    $aOrder['status'] = '超时';
                    break;
              case 'ready':
                    $aOrder['status'] = '准备中';
                    break;
            }

            //处理操作员
            $po = app::get('pam')->model('account')->dump($aOrder['op_id']);
            $aOrder['op_id'] = $po['login_name'];

            //处理订单号
            $po = app::get('ome')->model('orders')->dump($aOrder['order_id']);
            $aOrder['order_id'] = "=\"\"".$po['order_bn']."\"\"";
            $shop = app::get('ome')->model('shop')->dump($aOrder['shop_id']);
            $aOrder['shop_id'] = $shop['name'];
            $aOrder['payment_bn'] = "=\"\"".$aOrder['payment_bn']."\"\"";
            //处理备注
            /*
              $aOrder['memo'] = kernel::single('ome_func')->format_memo($aOrder['memo']);
              if(!empty($aOrder['memo'])){
                foreach($aOrder['memo'] as $k => $v){
                    $arr[]= $v['op_content']." BY ".$v['op_name']." ".$v['op_time'];
                  }
                  $aOrder['memo'] = implode(',',$arr);
              }
              */
            

            foreach( $this->oSchema['csv']['payments'] as $k => $v ){
                $orderRow[$k] = $this->charset->utf2local(utils::apath( $aOrder,explode('/',$v) ));
            }
            $data['content']['payments'][] = '"'.implode('","',$orderRow).'"';
        }
        $data['name'] = '付款单'.date("Ymd");
        return true;
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();
            foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
        echo implode("\n",$output);
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
        $type = 'bill';
        if ($logParams['app'] == 'ome' && $logParams['ctl'] == 'admin_payment') {
            $type .= '_salesBill_receipts';
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
        $type = 'bill';
        if ($logParams['app'] == 'ome' && $logParams['ctl'] == 'admin_payment') {
            $type .= '_salesBill_receipts';
        }
        $type .= '_import';
        return $type;
    }

    /**
     * 单据来源.
     * @param   archive
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function modifier_archive($row)
    {
        
        if($row == '1'){
           $row = sprintf("<div style='background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", 'red', '归档', '归档', '归档');
        }else{
            $row = '-';
        }
        return $row;
    }
}
?>
