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
    * ShopEx licence
    *
    * @copyright  Copyright (c) 2005-2012 ShopEx Technologies Inc. (http://www.shopex.cn)
    * @license  http://ecos.shopex.cn/ ShopEx License
    * @date 2012-08-22
    * 售后单
*/
class sales_mdl_aftersale extends dbeav_model{

    //是否有导出配置
    var $has_export_cnf = true;
    
      var $export_name = '售后单';

    var $defaultOrder = array('aftersale_time DESC');

    var $has_many = array(
       'aftersale_items' => 'aftersale_items'
    );

    var $return_type = array(
                            'return' => '退货',
                            'change' => '换货',
                            'refund' => '退款',
                            'refuse' => '拒绝收货',
                            'refunded' => '退款',
                        );

    var $pay_type = array(
                            'online' => '在线支付',
                            'offline' => '线下支付',
                            'deposit' => '预存款支付',
                        );

    var $common_type = array(
                            '0'=>'common',
                            '1'=>'return',
                            '2'=>'change',
                            '3'=>'refund',
                        );
    public $appendCols = 'order_id';

    public function searchOptions(){

        $ext_columns = array(
          'order_bn'=>$this->app->_('订单号'),
          'reship_bn'=>$this->app->_('退换货单号'),
          'return_apply_bn'=>$this->app->_('退款申请单号'),
        );
        
        return $ext_columns;
    }

    public function io_title( $filter=null,$ioType='csv',$return_type ){
      switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['aftersale'] = $this->get_return_type('aftersale',$return_type);
                $this->oSchema['csv']['aftersale_items'] = $this->get_return_type('aftersale_items',$return_type);
                break;
        }
        $this->ioTitle[$ioType]['aftersale'] = array_keys( $this->oSchema[$ioType]['aftersale'] );
        $this->ioTitle[$ioType]['aftersale_items'] = array_keys( $this->oSchema[$ioType]['aftersale_items'] );
        return $this->ioTitle[$ioType][$filter];

    }

    /**
     * 导出title方法 根据传入type类型,显示相应的title信息
     * @return void
     * @param main aftersale 主表字段 aftersale_items 明细字段
     * @param type 1 退货单(return) 2 换货单(change) 3 拒收退货单(refuse) 4 退款单(refund)     
     * @author 
     **/
    public function get_return_type($main,$type){
      
      return kernel::single('sales_export_aftersale')->io_title($main,$this->common_type[$type]);

    }

     //csv导出
    public function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){

        $type = $_GET['view']?$_GET['view']:'0';

        if( !$data['title']['aftersale'] ){
          $title = array();
          foreach( $this->io_title('aftersale','csv',$type) as $k => $v ){
              $title[] = $this->charset->utf2local($v);
          }
          $data['title']['aftersale'] = '"'.implode('","',$title).'"';
        }

        $limit = 100;

        if(!$list = $this->getList('*',$filter,$offset*$limit,$limit)) return false;

        foreach ($list as $v) {
          $aftersaleIds[] = $v['aftersale_id'];
          $orderIds[] = $v['order_id'];
          $memberIds[] = $v['member_id']; 
          $shopIds[] = $v['shop_id'];  
          $returnIds[] = $v['return_id'];
          $reshipIds[] = $v['reship_id'];  
          $returnapplyIds[] = $v['return_apply_id'];  
                                     
        }

        $Oshop = app::get('ome')->model('shop');
        $Oorder = app::get('ome')->model('orders');     
        $Oreturn_products = app::get('ome')->model('return_product');           
        $Oreship = app::get('ome')->model('reship');  
        $Orefund_apply = app::get('ome')->model('refund_apply');        
        $Oaccount = app::get('pam')->model('account'); 
        $Oaftersale_items = $this->app->model('aftersale_items');
        $Omembers = app::get('ome')->model('members'); 
        $payment_cfgObj = app::get('ome')->model('payment_cfg');
        $Obranch = app::get('ome')->model('branch'); 

        #店铺信息
        $shop = $Oshop->getList('name,shop_id',array('shop_id'=>$shopIds)); 

        foreach ($shop as $v) {
          $shops[$v['shop_id']] = $v['name'];
        }

        #仓库信息
        $branch = $Obranch->getList('name,branch_id'); 

        foreach ($branch as $v) {
          $branchs[$v['branch_id']] = $v['name'];
        }

        #订单信息
        $order = $Oorder->getList('order_bn,order_id',array('order_id'=>$orderIds));  
        
        foreach ($order as $v) {
          $orders[$v['order_id']] = $v['order_bn'];
        }

        #售后申请信息
        $return_product = $Oreturn_products->getList('return_bn,return_id',array('return_id'=>$returnIds));   

        foreach ($return_product as $v) {
          $return_products[$v['return_id']] = $v['return_bn'];
        }

        #退换货信息
        $reship = $Oreship->getList('reship_bn,reship_id',array('reship_id'=>$reshipIds));   

        foreach ($reship as $v) {
          $reships[$v['reship_id']] = $v['reship_bn'];
        }

        #退款申请信息
        $refund_apply = $Orefund_apply->getList('refund_apply_bn,apply_id',array('apply_id'=>$returnapplyIds)); 

        foreach ($refund_apply as $v) {
          $refund_applys[$v['apply_id']] = $v['refund_apply_bn'];
        }

        #操作员信息
        $account = $Oaccount->getList('login_name,account_id'); 
        
        foreach ($account as $v) {
          $accounts[$v['account_id']] = $v['login_name'];
        }

        #会员信息
        $member = $Omembers->getList('uname,member_id',array('member_id'=>$memberIds));  
        
        foreach ($member as $v) {
          $members[$v['member_id']] = $v['uname'];
        }
        
        #支付方式信息
        $payment_cfg = $payment_cfgObj->getList('id,custom_name');
        
        foreach ($payment_cfg as $v) {
          $payment_cfgs[$v['id']] = $v['custom_name'];
        }

        //所有的子售后单据数据
        $rs = $Oaftersale_items->getList('*',array('aftersale_id'=>$aftersaleIds));

        foreach($rs as $v) {
            $sales_items[$v['aftersale_id']][] = $v;
        }

        foreach( $list as $aFilter ){

          $aOrderRow = array();
          $check_op_id = $accounts[$aFilter['check_op_id']];
          $op_id = $accounts[$aFilter['op_id']];
          $refund_op_id = $accounts[$aFilter['refund_op_id']];

          $rows = array(
              'shop_id'               => $shops[$aFilter['shop_id']],
              'order_id'              => "=\"\"".$orders[$aFilter['order_id']]."\"\"",
              'aftersale_bn'          => $aFilter['aftersale_bn']."\t",
              'return_id'             => $return_products[$aFilter['return_id']]."\t",
              'reship_id'             => "=\"\"".$reships[$aFilter['reship_id']]."\"\"",
              'diff_order_bn'         => $aFilter['diff_order_bn']."\t",
              'change_order_bn'       => $aFilter['change_order_bn']."\t",                    
              'return_apply_id'       => $refund_applys[$aFilter['return_apply_id']]."\t",
              'return_type'           => $this->return_type[$aFilter['return_type']],
              'refundmoney'           => $aFilter['refundmoney']?$aFilter['refundmoney']:'-',
              'paymethod'             => $aFilter['paymethod']?$aFilter['paymethod']:'-',
              'refund_apply_money'    => $aFilter['refund_apply_money']?$aFilter['refund_apply_money']:'-',
              'member_id'             => $members[$aFilter['member_id']],
              'ship_mobile'           => $aFilter['ship_mobile']?$aFilter['ship_mobile']:'-',
              'pay_type'              => $aFilter['pay_type']?$this->pay_type[$aFilter['pay_type']]:'-',
              'account'               => $aFilter['account']?$aFilter['account']:'-',
              'bank'                  => $aFilter['bank']?$aFilter['bank']:'-',
              'pay_account'           => $aFilter['pay_account']?$aFilter['pay_account']:'-',
              'refund_apply_time'     => !empty($aFilter['refund_apply_time'])?date('Y-m-d H:i:s',$aFilter['refund_apply_time']):'-',
              'check_op_id'           => $check_op_id?$check_op_id:'-',
              'op_id'                 => $op_id?$op_id:'-',
              'refund_op_id'          => $refund_op_id?$refund_op_id:'-',
              'add_time'              => !empty($aFilter['add_time'])?date('Y-m-d H:i:s',$aFilter['add_time']):'-',
              'check_time'            => !empty($aFilter['check_time'])?date('Y-m-d H:i:s',$aFilter['check_time']):'-',
              'acttime'               => !empty($aFilter['acttime'])?date('Y-m-d H:i:s',$aFilter['acttime']):'-',
              'refundtime'            => !empty($aFilter['refundtime'])?date('Y-m-d H:i:s',$aFilter['refundtime']):'-',
              'aftersale_time'        => !empty($aFilter['aftersale_time'])?date('Y-m-d H:i:s',$aFilter['aftersale_time']):'-',
          );

          $aOrderRow = kernel::single('sales_export_aftersale')->io_contents('aftersale',$this->common_type[$type],$rows);

          $data['content']['aftersale'][]  = $this->charset->utf2local('"'.implode( '","', $aOrderRow ).'"');

          $objects = $sales_items[$aFilter['aftersale_id']];

          if ($objects){

            if( !$data['title']['aftersale_items'] ){
              $title = array();
              foreach( $this->io_title('aftersale_items','csv',$type) as $k => $v ){
                  $title[] = $this->charset->utf2local($v);
              }
              $data['title']['aftersale_items'] = '"'.implode('","',$title).'"';
            }

            foreach ($objects as $obj){
              $orderObjRow = array();
              if($obj['return_type'] == 'refunded'){
                 $pay_type = $this->pay_type[$obj['pay_type']];
                 $num = $price = '-';
              }else{
                 $num = $obj['num']?$obj['num']:'-';
                 $price = $obj['price']?$obj['price']:'-';
                 $pay_type = '-';
              }

              $branch_id = $obj['branch_id'];

              $rowsobj = array(
                  'aftersale_bn'         => $aFilter['aftersale_bn']."\t",
                  'pay_type'             => $pay_type,
                  'account'              => $obj['account']?$obj['account']:'-',
                  'bank'                 => $obj['bank']?$obj['bank']:'-',
                  'pay_account'          => $obj['pay_account']?$obj['pay_account']:'-',
                  'money'                => $obj['money']?$obj['money']:'-',
                  'refunded'             => $obj['refunded']?$obj['refunded']:'-',
                  'payment'              => $obj['payment']?$payment_cfgs[$obj['payment']]:'-',
                  'create_time'          => !empty($obj['create_time'])?date('Y-m-d H:i:s',$obj['create_time']):'-',
                  'last_modified'        => !empty($obj['last_modified'])?date('Y-m-d H:i:s',$obj['last_modified']):'-',                
                  'bn'                   => $obj['bn']?$obj['bn']:'-',
                  'product_name'         => $obj['product_name']?$obj['product_name']:'-',
                  'num'                  => $num,
                  'price'                => $price,
                  'branch_id'            => $branchs[$obj['branch_id']],
                  'return_type'          => $this->return_type[$obj['return_type']],
              );

              $orderObjRow = kernel::single('sales_export_aftersale')->io_contents('aftersale_items',$this->common_type[$type],$rowsobj);
              $data['content']['aftersale_items'][] = $this->charset->utf2local('"'.implode( '","', $orderObjRow ).'"');
            }
          }
        }
        $data['name'] = 'aftersale'.date("YmdHis");
        return true;
    }

    public function export_csv($data,$exportType = 1 ){
        $output = array();
         foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
        echo implode("\n",$output);
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){
       $Obj = app::get('ome');
       $where = '1 ';
       if(isset($filter['order_bn'])){
             $orders = array(0);
             $Oorder = $Obj->model("orders");
          $order = $Oorder->getList('order_id',array('order_bn|head'=>$filter['order_bn']));
          foreach ($order as $v) {
               $orders[] = $v['order_id'];
          }
          $archive_ordObj = kernel::single('archive_interface_orders');
            $archiveorder =$archive_ordObj->getOrders(array('order_bn'=>$filter['order_bn']),'order_id');
           
            if ($archiveorder){
                $orders[] = $archiveorder['order_id'];
            }
            
          $where .= 'and order_id in ('.implode(',',$orders).')';
          unset($filter['order_bn']);
       }

       if(isset($filter['reship_bn'])){
             $reships = array(0);
             $Oreship = $Obj->model("reship");
          $reship = $Oreship->getList('reship_id',array('reship_bn|head'=>$filter['reship_bn']));
          foreach ($reship as $v) {
               $reships[] = $v['reship_id'];
          }
          $where .= ' and reship_id in ('.implode(',',$reships).')';
          unset($filter['reship_bn']);
       }

       if(isset($filter['member_uname'])){
             $members = array(0);
             $Omember = $Obj->model("members");
          $member = $Omember->getList('member_id',array('uname|head'=>$filter['member_uname']));
          foreach ($member as $v) {
               $members[] = $v['member_id'];
          }
          $where .= ' and member_id in ('.implode(',',$members).')';
          unset($filter['member_uname']);
       }

       if(isset($filter['ship_mobile'])){
             $reships = array(0);
             $Oreship = $Obj->model("reship");
          $reship = $Oreship->getList('reship_id',array('ship_mobile|head'=>$filter['ship_mobile']));
          foreach ($reship as $v) {
               $reships[] = $v['member_id'];
          }
          $where .= ' and reship_id in ('.implode(',',$reships).')';
          unset($filter['ship_mobile']);
       }

       if(isset($filter['return_bn'])){
          $return_products = array(0);
          $Oreturn = $Obj->model("return_product");
          $return_product = $Oreturn->getList('return_id',array('return_bn|head'=>$filter['return_bn']));

          foreach ($return_product as $v) {
               $return_products[] = $v['return_id'];
          }
          $where .= ' and return_id in ('.implode(',',$return_products).')';
          unset($filter['return_bn']);
       }

       if(isset($filter['payment'])){
          $payment_cfgObj = app::get('ome')->model('payment_cfg');
          $payment_cfg = $payment_cfgObj->dump(array('id'=>$filter['payment']), 'custom_name');
          $where .= ' and paymethod = "'.$payment_cfg['custom_name'].'"';
          unset($filter['payment']);
       }

       if(isset($filter['problem_id'])){
          $problemObj = app::get('ome')->model('return_product_problem');
          $problemdata = $problemObj->dump(array('problem_id'=>$filter['problem_id']), 'problem_name');
          $where .= ' and problem_name = "'.$problemdata['problem_name'].'"';
          unset($filter['problem_id']);
       }

       

       return $where.' and '.parent::_filter($filter,$tableAlias,$baseWhere);
    }

    /**
     * 得到唯一的aftersale id
     * @params null
     * @return string aftersale id
     */
    public function get_aftersale_bn()
    {
        /***
        $i = rand(0,9999);
        do{
            if(9999==$i){
                $i=0;
            }
            $i++;
            $aftersale_bn = 'A'.time().str_pad($i,4,'0',STR_PAD_LEFT);
            $row = $this->dump($aftersale_bn, 'aftersale_bn');
        }while($row);
        return $aftersale_bn;
        ***/
        
        //防止并发同分同秒生成同样的售后单号
        $prefix = 'A' . date('ymd', time());
        $aftersale_bn = kernel::single('eccommon_guid')->incId('aftersale', $prefix, 7, true);
        
        return $aftersale_bn;
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
        if ($logParams['app'] == 'sales' && $logParams['ctl'] == 'admin_aftersale') {
            $type .= '_salesBill_afterSales';
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
        if ($logParams['app'] == 'sales' && $logParams['ctl'] == 'admin_aftersale') {
            $type .= '_salesBill_afterSales';
        }
        $type .= '_import';
        return $type;
    }

    /**
     * 来源
     * @param   
     * @return  string
     * @access  public
     * @author cyyr24@sina.cn
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
    
    //获取导出明细数据
    public function getexportdetail($fields,$filter,$offset=0,$limit=1,$has_title=false){
        $mdl_sa = app::get('sales')->model('aftersale');
        $mdl_sai = app::get('sales')->model('aftersale_items');
        $payment_cfgObj = app::get('ome')->model('payment_cfg');
        $payment_cfg = $payment_cfgObj->getList('id,custom_name'); //获取支付方式信息
        foreach ($payment_cfg as $v) {
            $payment_cfgs[$v['id']] = $v['custom_name'];
        }
        $Obranch = app::get('ome')->model('branch');
        $branch = $Obranch->getList('name,branch_id'); //获取仓库信息
        foreach ($branch as $v) {
            $branchs[$v['branch_id']] = $v['name'];
        }
        $aftersale_arr = $mdl_sa->getList('aftersale_bn,aftersale_id', array('aftersale_id' => $filter['aftersale_id']), 0, -1);
        $aftersale_bn = array();
        foreach($aftersale_arr as $var_aftersale){
            $aftersale_bn[$var_aftersale["aftersale_id"]] = $var_aftersale["aftersale_bn"];
        }
        $aftersale_items_arr = $mdl_sai->getList('*',array('aftersale_id'=>$filter['aftersale_id']), 0, -1,"aftersale_id desc");
        $row_num = 1;
        if($aftersale_items_arr){
            foreach ($aftersale_items_arr as $key => $aftersale_item){
                //这里参照原有的fgetlist_csv获取明细数据方法
                if($aftersale_item['return_type'] == 'refunded'){
                    $pay_type = $this->pay_type[$aftersale_item['pay_type']];
                    $apply_num = $num = $price = $normal_num = $defective_num = '-';
                }else{
                    $pay_type = '-';
                    $apply_num =  $aftersale_item['apply_num'] ? $aftersale_item['apply_num'] : '-';
                    $num = $aftersale_item['num'] ? $aftersale_item['num'] : '-';
                    $price = $aftersale_item['price'] ? $aftersale_item['price'] : '-';

                    $normal_num     = $aftersale_item['normal_num'];
                    $defective_num  = $aftersale_item['defective_num'];
                }
//                $current_aftersale_bn = mb_convert_encoding($aftersale_bn[$aftersale_item['aftersale_id']], 'GBK', 'UTF-8');
//                $aftersaleItemRow['*:销售单号'] = $current_aftersale_bn;
                $aftersaleItemRow['pay_type'] = $pay_type;
                $aftersaleItemRow['account'] = $aftersale_item['account'] ?  : '-';
                $aftersaleItemRow['bank'] = $aftersale_item['bank'] ?  : '-';
                $aftersaleItemRow['pay_account'] = $aftersale_item['pay_account'] ? : '-';
                $aftersaleItemRow['money'] = $aftersale_item['money'] ? : '-';
                $aftersaleItemRow['refunded'] = $aftersale_item['refunded'] ?  : '-';
                $payment = $aftersale_item['payment'];
                if($payment){
                    $payment = $payment_cfgs[$payment];
                }
                $aftersaleItemRow['payment'] = $payment ? $payment : '-';
                $aftersaleItemRow['create_time'] = !empty($aftersale_item['create_time'])?date('Y-m-d H:i:s',$aftersale_item['create_time']):'-';
                $aftersaleItemRow['last_modified'] = !empty($aftersale_item['last_modified'])?date('Y-m-d H:i:s',$aftersale_item['last_modified']):'-';
                $aftersaleItemRow['bn'] = $aftersale_item["bn"] ?  : '-';
                $aftersaleItemRow['product_name'] = $aftersale_item["product_name"] ? $aftersale_item["product_name"] : '-';
                $aftersaleItemRow['num'] = $num;
                $aftersaleItemRow['apply_num'] = $apply_num;
                $aftersaleItemRow['price'] = $price;
                
                //销售金额
                $aftersaleItemRow['saleprice'] = $aftersale_item['saleprice'];
                
                $aftersaleItemRow['branch_id'] = $branchs[$aftersale_item['branch_id']];
                $aftersaleItemRow['item_return_type'] = $this->return_type[$aftersale_item['return_type']];
                
                $aftersaleItemRow['normal_num'] = $normal_num;
                $aftersaleItemRow['defective_num'] = $defective_num;
                $aftersaleItemRow['settlement_amount'] = $aftersale_item['settlement_amount'];
                $aftersaleItemRow['platform_amount'] = $aftersale_item['platform_amount'];
                $aftersaleItemRow['actually_amount'] = $aftersale_item['actually_amount'];
                $aftersaleItemRow['platform_pay_amount'] = $aftersale_item['platform_pay_amount'];


                $data[$aftersale_item['aftersale_id']][] = $aftersaleItemRow;
                $row_num++;
            }
        }
        //明细标题处理
        if($data && $has_title){
            $title = array(
                '*:售后单号',
                '*:支付类型',
                '*:退款帐号',
                '*:退款银行',
                '*:收款帐号',
                '*:申请退款金额',
                '*:已退款金额',
                '*:付款方式',
                '*:退款申请时间',
                '*:退款完成时间',
                '*:货品',
                '*:货品名称',
                '*:数量',
                '*:单价',
                '*:销售价',
                '*:仓库名称',
                '*:售后类型',
            );
            foreach ((array)$title as $key => $value) {
                $title[$key] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }
            $data[0] = implode(',', $title);
        }
        if($data){
            ksort($data);
        }
        
        return $data;
    }
    
    /**
     * 根据查询条件获取导出数据
     * @Author: xueding
     * @Vsersion: 2022/5/25 上午10:35
     * @param $fields
     * @param $filter
     * @param $has_detail
     * @param $curr_sheet
     * @param $start
     * @param $end
     * @param $op_id
     */
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id)
    {
        $params = [
            'fields'     => $fields,
            'filter'     => $filter,
            'has_detail' => $has_detail,
            'curr_sheet' => $curr_sheet,
            'op_id'      => $op_id,
        ];
    
        $aftersalesListData = kernel::single('ome_func')->exportDataMain(__CLASS__,$params);
        if (!$aftersalesListData) {
            return false;
        }
        
        //根据选择的字段定义导出的第一行标题
        if ($curr_sheet == 1) {
            $data['content']['main'][] = $this->getCustomExportTitle($aftersalesListData['title']);
        }


        $aftersales_items_value = array_values($this->aftersalesItemsExportTitle());
        $main_columns = array_values($aftersalesListData['title']);

        $aftersaleIds = array_column($aftersalesListData['content'],'aftersale_id');
        //所有的子售后单据数据
        $aftersalesItems = $this->getexportdetail('', array('aftersale_id' => $aftersaleIds));
        $aftersaleList = $aftersalesListData['content'];
        foreach ($aftersaleList as $aftersaleRow) {
            $aftersaleRow['order_id']           = $aftersaleRow['order_id'] . "\t";
            $aftersaleRow['return_id']          = $aftersaleRow['return_id'] . "\t";
            $aftersaleRow['reship_id']          = $aftersaleRow['reship_id'] . "\t";
            $aftersaleRow['return_apply_id']    = $aftersaleRow['return_apply_id'] . "\t";
            $aftersaleRow['refundmoney']        = $aftersaleRow['refundmoney'] ? $aftersaleRow['refundmoney'] : '-';
            $aftersaleRow['paymethod']          = $aftersaleRow['paymethod'] ? $aftersaleRow['paymethod'] : '-';
            $aftersaleRow['refund_apply_money'] = $aftersaleRow['refund_apply_money'] ? $aftersaleRow['refund_apply_money'] : '-';
            $aftersaleRow['ship_mobile']        = $aftersaleRow['ship_mobile'] ? $aftersaleRow['ship_mobile'] : '-';
            $aftersaleRow['pay_type']           = $aftersaleRow['pay_type'] ? $aftersaleRow['pay_type'] : '-';
            $aftersaleRow['account']            = $aftersaleRow['account'] ? $aftersaleRow['account'] : '-';
            $aftersaleRow['bank']               = $aftersaleRow['bank'] ? $aftersaleRow['bank'] : '-';
            $aftersaleRow['pay_account']        = $aftersaleRow['pay_account'] ? $aftersaleRow['pay_account'] : '-';
            $aftersaleRow['check_op_id']        = $aftersaleRow['check_op_id'] ? $aftersaleRow['check_op_id'] : '-';
            $aftersaleRow['op_id']              = $aftersaleRow['op_id'] ? $aftersaleRow['op_id'] : '-';
            $aftersaleRow['refund_op_id']       = $aftersaleRow['refund_op_id'] ? $aftersaleRow['refund_op_id'] : '-';
            
            $objects = $aftersalesItems[$aftersaleRow['aftersale_id']];
            
            //title
            $items_fields = implode(',', $aftersales_items_value);
            $all_fields   = implode(',', $main_columns) . ',' . $items_fields;
            
            //没有退款商品明细,直接输出售后单主信息
            if(empty($objects)){
                $aftersalesDataRow = $aftersaleRow;
                $exptmp_data = array();
                foreach (explode(',', $all_fields) as $key => $col)
                {
                    if (isset($aftersalesDataRow[$col])) {
                        $aftersalesDataRow[$col] = mb_convert_encoding($aftersalesDataRow[$col], 'GBK', 'UTF-8');
                        
                        $exptmp_data[] = $aftersalesDataRow[$col];
                    } else {
                        $exptmp_data[] = '';
                    }
                }
                
                $data['content']['main'][] = implode(',', $exptmp_data);
                
                continue;
            }
            
            //items
            if ($objects) {
                foreach ($objects as $obj) {
                    $aftersalesDataRow = array_merge($aftersaleRow, $obj);
                    $exptmp_data       = [];
                    foreach (explode(',', $all_fields) as $key => $col) {
                        if (isset($aftersalesDataRow[$col])) {
                            $aftersalesDataRow[$col] = mb_convert_encoding($aftersalesDataRow[$col], 'GBK', 'UTF-8');
                            
                            $exptmp_data[]           = $aftersalesDataRow[$col];
                        } else {
                            $exptmp_data[] = '';
                        }
                    }
                    $data['content']['main'][] = implode(',', $exptmp_data);
                }
            }
        }
        return $data;
    }
    
    public function getCustomExportTitle($main_title)
    {
        $main_title = array_keys($main_title);
        $aftersaleItems_title = array_keys($this->aftersalesItemsExportTitle());
        $title           = array_merge($main_title, $aftersaleItems_title);
        return mb_convert_encoding(implode(',', $title), 'GBK', 'UTF-8');
    }
    
    
    public function aftersalesItemsExportTitle()
    {
        $items_title = array(
            '详情支付类型'   => 'pay_type',
            '详情退款帐号'   => 'account',
            '详情退款银行'   => 'bank',
            '详情收款帐号'   => 'pay_account',
            '详情申请退款金额' => 'money',
            '详情已退款金额'  => 'refunded',
            '详情付款方式'   => 'payment',
            '详情退款申请时间' => 'create_time',
            '详情退款完成时间' => 'last_modified',
            '详情基础物料编码'     => 'bn',
            '详情基础物料名称'   => 'product_name',
            '详情申请数量'     => 'apply_num',
            '详情数量'     => 'num',
            '详情单价'     => 'price',
            '详情销售价'    => 'saleprice',
            '详情仓库名称'   => 'branch_id',
            '详情售后类型'   => 'item_return_type',
            '详情良品'     => 'normal_num',
            '详情不良品'     => 'defective_num',
            '平台承担'   => 'platform_amount',
            '结算金额'   => 'settlement_amount',
            '客户实付'   => 'actually_amount',
            '支付优惠金额' => 'platform_pay_amount',
        );
        return $items_title;
    }
    
    public function modifier_member_id($member_id, $list, $row)
    {
        static $get_from_db, $order_list;
        
        if ($get_from_db === true) return $order_list[$row['order_id']]['uname'];
        
        $member_list = array();
        foreach ($list as $value) {
            $order_list[$value['order_id']]['member_id'] = $value['member_id'];
            
            $member_list[$value['member_id']] = array();
        }
        
        
        if ($mid = array_keys($member_list)) {
            $m1Mdl = app::get('ome')->model('members');
            foreach ($m1Mdl->getList('uname,member_id', array('member_id' => $mid)) as $value) {
                $member_list[$value['member_id']]['uname'] = $value['uname'];
            }
        }
        
        foreach ($order_list as $order_id => $value) {
            $value['uname'] = $member_list[$value['member_id']]['uname'];
            
            if ($this->is_export_data) {
                if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                    $value['uname'] = kernel::single('ome_view_helper2')->modifier_ciphertext($value['uname'], 'aftersale', 'uname');
                }
                $order_list[$order_id] = $value;
                continue;
            }
            
            $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($value['uname']);
            
            if ($value['uname'] && $is_encrypt) {
                $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($value['uname'], 'aftersale', 'uname');
                
                $value['uname'] = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_member&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="uname">{$encrypt}</span></span>
HTML;
            }
            
            $order_list[$order_id] = $value;
        }
        
        $get_from_db = true;
        
        return $order_list[$row['order_id']]['uname'];
    }
    
    public function modifier_ship_mobile($mobile, $list, $row)
    {
        if ($this->is_export_data) return $mobile;
        
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($mobile);
        
        if (!$is_encrypt) return $mobile;
        
        $base_url      = kernel::base_url(1);
        $aftersale_id  = $row['aftersale_id'];
        $encryptMobile = kernel::single('ome_view_helper2')->modifier_ciphertext($mobile, 'aftersale', 'ship_mobile');
        
        $return = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=sales&ctl=admin_aftersale&act=showSensitiveData&p[0]={$aftersale_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_mobile">{$encryptMobile}</span></span>
HTML;
        return $mobile ? $return : $mobile;
    }
    
}
