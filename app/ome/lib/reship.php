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
* 换货类
*
* @author chenping<chenping@shopex.cn>
*/
class ome_reship{

    function __construct($app){
        $this->app = $app;
    }

    /**
     * 取消退款申请
     * @param String $apply_id 退款申请ID
     * @param String $memo 取消理由
     * @return void
     * @author
     * */

    public function cancelRefundApply($apply_id,$memo=''){
        $refundApplyModel = $this->app->model('refund_apply');
        $applyUpdate = array(
            'status' => '3',
            'memo'=>$memo
        );
        $refundApplyModel->update($applyUpdate,array('apply_id'=>$apply_id));
    }

    /**
     * @description 退换货申请退款生成退款单据
     * @access public
     */
    public function createRefund($refundApply,$order){
        # 更新退款金额
        $orderModel = $this->app->model('orders');
        $payed = $order['payed'] - $refundApply['money'];
        $payed = ( $payed > 0 ) ? $payed : 0;
        $orderModel->update(array('payed'=>$payed),array('order_id'=>$order['order_id']));

        $opLogModel = $this->app->model('operation_log');
        $opLogModel->write_log('order_modify@ome',$order['order_id'],"售后退款成功，更新订单退款金额。系统自动操作，退款金额用于支付新订单。");

        # 退款申请单处理
        $refundApplyUpdate = array(
            'status' => '4',
            'refunded' => $refundApply['money'],
            'last_modified' => time(),
            'account' => $refundApply['account'],
            'pay_account' => $refundApply['pay_account'],
        );
        $refundApplyModel = $this->app->model('refund_apply');
        $refundApplyModel->update($refundApplyUpdate,array('apply_id'=>$refundApply['apply_id']));

        $opLogModel->write_log('refund_apply@ome',$refundApply['apply_id'],"售后退款成功，更新退款申请状态。系统自动操作，退款金额用于支付新订单。");

        # 退款单处理
        $paymethods = ome_payment_type::pay_type();
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $refunddata = array(
            'refund_bn' => $refundApply['refund_apply_bn'],
            'order_id' => $order['order_id'],
            'shop_id' => $order['shop_id'],
            'account' => $refundApply['account'],
            'bank' => $refundApply['bank'],
            'pay_account' => $refundApply['pay_account'],
            'currency' => $order['currency'],
            'money' => $refundApply['money'],
            'paycost' => 0,
            'cur_money' => $refundApply['money'],
            'pay_type' => $refundApply['pay_type'],
            'payment' => $refundApply['payment'],
            'paymethod' => $paymethods[$refundApply['pay_type']],
            'op_id' => $opInfo['op_id'],
            't_ready' => time(),
            't_sent' => time(),
            'memo' => $refundApply['memo'],
            'status' => 'succ',
            'refund_refer' => '1',
            'return_id' => $refundApply['return_id'],
        );
        if ($refundApply['archive'] && $refundApply['archive']=='1') {
            $refunddata['archive'] = '1';
        }
        $oRefund = $this->app->model('refunds');
        $oRefund->save($refunddata);

        // 更新订单支付状态
        kernel::single('ome_order_func')->update_order_pay_status($order['order_id'], true, __CLASS__.'::'.__FUNCTION__);
        $opLogModel->write_log('refund_accept@ome',$refunddata['refund_id'],"售后退款成功，生成退款单".$refunddata['refund_bn']."，退款金额用于支付新订单。");
    }

    /**
     * 取消补差价订单
     * @param Int $order_id 订单ID
     * @param String $shop_id 店铺ID
     * */
    public function cancelDiffOrder($order_id,$shop_id,$memo=''){
        define('FRST_TRIGGER_OBJECT_TYPE','订单：订单作为补差价订单取消');
        define('FRST_TRIGGER_ACTION_TYPE','ome_ctl_admin_order：do_cancel');

        $c2c_shop_list = ome_shop_type::shop_list();

        $node_type = $this->app->model('shop')
                        ->select()->columns('node_type')
                        ->where('shop_id=?',$shop_id)
                        ->instance()->fetch_one();

        $mod = in_array($node_type,$c2c_shop_list) ? 'async' : 'sync';

        return $this->app->model('orders')->cancel($order_id,$memo,true,$mod, false);
    }

    /**
     * 对换货订单进行支付操作
     * @param Array $order 订单信息
     * */
    public function payChangeOrder($order){
        $mathLib      = kernel::single('eccommon_math');
        $orderModel   = $this->app->model('orders');
        $paymentModel = $this->app->model('payments');

        $orderdata = array(
            'order_id' => $order['order_id'],
            'pay_status' => $order['pay_status'],
            'paytime' => time(),
        );

        # 支付配置
        //$cfg = $this->app->model('payment_cfg')->dump();
        $cfg = array();

        $orderdata['pay_bn'] = $cfg['pay_bn'];

        $orderdata['payed'] = $mathLib->getOperationNumber($order['pay_money']);

        $orderdata['payment'] = '线下支付';

        $orderModel->update($orderdata,array('order_id'=>$order['order_id']));

        //日志
        $memo = '做质检时连带操作;订单付款操作,用订单('.$order['reship_order_bn'].')的退款金额作支付金额';
        $oOperation_log = $this->app->model('operation_log');
        $oOperation_log->write_log('order_modify@ome',$order['order_id'],$memo);

        //生成支付单
        $payment_bn = $paymentModel->gen_id();
        $paymentdata = array();
        $paymentdata['payment_bn']  = $payment_bn;
        $paymentdata['order_id']    = $order['order_id'];
        $paymentdata['shop_id']     = $order['shop_id'];
        $paymentdata['account']     = '';
        $paymentdata['bank']        = '';
        $paymentdata['pay_account'] = '';
        $paymentdata['currency']    = $order['currency'];
        $paymentdata['money']       = $order['pay_money'];
        $paymentdata['paycost']     = 0;
        $curr_time                  = time();
        $paymentdata['t_begin']     = $curr_time;//支付开始时间
        $paymentdata['t_end']       = $curr_time;//支付结束时间
        $paymentdata['trade_no']    = '';//支付网关的内部交易单号，默认为空
        $paymentdata['cur_money']   = $paymentdata['money'];
        $paymentdata['pay_type']    = 'offline';
        $paymentdata['payment']     = $order['payment'] ? $order['payment'] : 0;
        $paymentdata['paymethod']   = '线下支付';
        $paymentdata['payment_refer'] = '1';

        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $paymentdata['op_id'] = $opInfo['op_id'];
        if ($order['archive'] && $order['archive'] == '1') {
            //$paymentdata['archive'] = '1';
        }
        $paymentdata['ip'] = kernel::single("base_request")->get_remote_addr();
        $paymentdata['status'] = 'succ';
        $paymentdata['memo'] = '做质检时连带操作;系统生成换货订单支付单据;通过退款金额进行支付;补换货的订单:'.$order['reship_order_bn'];
        $paymentdata['is_orderupdate'] = 'false';
        $paymentModel->create_payments($paymentdata);

        //日志
        if($paymentdata['payment_id']){
            $oOperation_log->write_log('payment_create@ome',$paymentdata['payment_id'],'生成支付单');
        }
        
    }

    /**
     * @description 判断是否为反审单据
     * @access public
     */
    public function is_precheck_reship($is_check,$need_sv='true'){
        return ($is_check=='0' && $need_sv == 'false') ? true : false;
    }

    /**
     * @description 更新订单的状态,并把发货状态同步到前端
     * */
    function updatediffOrder($order_bn){
        $mdl_ome_orders = $this->app->model('orders');
        $mdl_ome_delivery_order = app::get('ome')->model('delivery_order');
        $order_detail = $mdl_ome_orders->dump(array('order_bn'=>$order_bn),'order_id,shop_id,order_bn,ship_name,ship_time,ship_mobile,ship_zip,ship_area,ship_tel,ship_email,ship_addr');
        $rs_ome_delivery_order = $mdl_ome_delivery_order->dump(array('order_id'=>$order_detail["order_id"]));
        if(!empty($rs_ome_delivery_order)){ //有发货单直接返回
            return;
        }
        //补差价订单生成发货单 不生成出入库明细和销售单
        $this->deliver_by_diff_order($order_detail);
    }
    
    //补差价订单生成发货单 不生成出入库明细和销售单
    function deliver_by_diff_order($diff_order_info){
        $mdl_ome_orders = $this->app->model('orders');
        $mdl_ome_reship = app::get('ome')->model('reship');
        $rs_reship = $mdl_ome_reship->dump(array("diff_order_bn"=>$diff_order_info["order_bn"]));
        //直接添加已发货的发货单 组参数
        $mdl_ome_delivery = app::get('ome')->model("delivery");
        $mdl_order_objects = app::get('ome')->model('order_objects');
        $mdl_order_items = app::get('ome')->model('order_items');
        $mdl_operation_log = app::get('ome')->model('operation_log');
        $delivery = array('consignee' => $diff_order_info["consignee"]);
        $itemFilter = array('order_id'=>$diff_order_info["order_id"], 'delete'=>'false');
        $object_list = $mdl_order_objects->getList('*',$itemFilter);
        $delivery_params = array();
        foreach($object_list as $object){
            $itemFilter['obj_id'] = $object['obj_id'];
            $orderItems = $mdl_order_items->getList('*', $itemFilter);
            foreach($orderItems as $item){
                if($item['product_id'] > 0){
                    if($item['nums'] > 0){
                        $delivery_params['branch_id'] = $rs_reship['branch_id'];
                        $delivery_params['logi_no'] = $rs_reship['logi_no'];
                        $delivery_params['logi_name'] = $rs_reship['logi_name'];
                        $delivery_params['items'][] = array(
                            'product_id' => $item['product_id'],
                            'shop_product_id' => $item['shop_product_id'],
                            'bn' => $item['bn'],
                            'number' => $item['nums'],
                            'product_name' => $item['name'],
                            'spec_info' => $item['addon'],
                        );
                        $delivery_params['order_items'][] = array(
                            'product_id' => $item['product_id'],
                            'bn' => $item['bn'],
                            'number' => $item['nums'],
                            'product_name' => $item['name'],
                            'item_id' => $item['item_id'],
                            'obj_id'  => $item['obj_id'],
                        );
                    }
                }else{
                    $mdl_operation_log->write_log('order_confirm@ome',$diff_order_info["order_id"],'明细未修复，无法生成发货单');
                    return false;
                }
            }
        }
        $delivery["branch_id"] = $delivery_params["branch_id"];
        $delivery["delivery_waybillCode"] = $diff_order_info["order_bn"]; //logi_no给order_bn
        $delivery['delivery_items'] = $delivery_params['items'];
        $order_items = $delivery_params['order_items'];
        $split_status = '';
        $delivery_id = $mdl_ome_delivery->addDelivery($diff_order_info["order_id"],$delivery,array(),$order_items,$split_status,true);
        if ($delivery_id) {
            //更新补差价订单状态
            $update_arr = array(
                'ship_status' => '1',
                'status' => 'finish',
                'archive' => '1',
                'process_status' => 'confirmed',
                'confirm' => 'Y',
                'is_delivery' => 'Y',
                'logi_no' => $diff_order_info["order_bn"], //logi_no给order_bn
                'splited_num_upset_sql' => 'IF(`splited_num` IS NULL, 1, `splited_num` + 1)',
            );
            $mdl_ome_orders->update($update_arr,array('order_bn'=>$diff_order_info["order_bn"]));
            //标记已发货数
            $sql = 'UPDATE `sdb_ome_order_items` SET sendnum=nums WHERE `delete`="false" AND order_id='.$diff_order_info['order_id'];
            $mdl_ome_orders->db->exec($sql);
            $mdl_operation_log->write_log('order_confirm@ome',$diff_order_info['order_id'],"售后补差价订单，自动生成发货单成功。");
            //发货状态回传 参照class ome_event_trigger_shop_delivery的public function delivery_confirm_retry($orderids)
            $deliveryList = $mdl_ome_delivery->getList('*',array('delivery_id'=>$delivery_id));
            if(!$deliveryList){
                return;
            }
            $delivery = $deliveryList[0];
            $mdl_ome_delivery_order = app::get('ome')->model('delivery_order');
            $deliveryOrderList = $mdl_ome_delivery_order->getList('delivery_id,order_id',array('delivery_id'=>$delivery_id));
            $order_ids = array(); $delivery_orders = array();
            foreach ($deliveryOrderList as $key => $value) {
                $order_ids[] = $value['order_id'];
                $delivery_orders[$value['delivery_id']] = &$orderList[$value['order_id']];
            }
            $rows = $mdl_ome_orders->getList('*',array('order_id'=>$order_ids));
            foreach($rows as $key => $value) {
                $orderList[$value['order_id']] = $value;
            }
            $sdf = kernel::single('ome_event_trigger_shop_data_delivery_router')
                    ->set_shop_id($delivery['shop_id'])
                    ->init($deliveryList,$delivery_orders)
                    ->get_sdf($delivery['delivery_id']);
            if (!$sdf){
                return;
            }
            kernel::single('erpapi_router_request')->set('shop',$delivery['shop_id'])->delivery_add($sdf);
            kernel::single('erpapi_router_request')->set('shop',$delivery['shop_id'])->delivery_logistics_update($sdf);
            kernel::single('erpapi_router_request')->set('shop',$delivery['shop_id'])->delivery_confirm($sdf);
        }else{
            $mdl_operation_log->write_log('order_confirm@ome',$diff_order_info['order_id'],"生成发货单失败");
        }
    }
    
    /**
     * 残损确认判断 （来自save_check方法）目前只支持电商仓
     * 
     * @param int $reship_id
     * @param string $is_check
     * @param string $msg
     * @param string $wms_type WMS仓储类型,默认为空
     * @return boolean
     */
    public function check_defective($reship_id,$is_check,&$msg, $wms_type=null)
    {
        //9 => '拒绝质检'  10 => '质检异常' 11 => '待确认'
        if($is_check == '10' || $is_check == '11' || $is_check == '9')
        {
            $oReship_item = app::get('ome')->model('reship_items');
            $normal_reship_item = $oReship_item->getList('*',array('reship_id'=>$reship_id,'normal_num|than'=>0),0,1);
            $reship_item = $oReship_item->getList('*',array('reship_id'=>$reship_id,'defective_num|than'=>0),0,1);
            
            if (count($normal_reship_item)==0 && count($reship_item)==0)
            {
                if($wms_type == 'yjdf'){
                    //[兼容]京东一件代发,有不退货仅退款的场景
                }else{
                    $msg = '良品或不良品数量至少有一种不为0!';
                    return false;
                }
            }
            
            if (count($reship_item)>0)
            {
                $damaged = kernel::single('console_iostockdata')->getDamagedbranch($reship_item[0]['branch_id']);
                if (!$damaged) {
                    $msg = '由于有不良品入库，请设置主仓对应残仓';
                    return false;
                }
            }
        }
        
        return true;
    }
    
    //质检成功后 拒绝质检 质检异常 待确认 状态的 执行相应的操作  目前只支持电商仓
    /**
     * part_finish_aftersale
     * @param mixed $reship_id ID
     * @param mixed $is_check is_check
     * @param mixed $msg msg
     * @return mixed 返回值
     */
    public function part_finish_aftersale($reship_id,$is_check,&$msg){
        //9 => '拒绝质检'  10 => '质检异常' 11 => '待确认'
        if($is_check == '10' || $is_check == '11' || $is_check == '9'){
            $Oreship = app::get('ome')->model('reship');
            if($Oreship->finish_aftersale($reship_id)){
                $result = kernel::single('console_reship')->siso_iostockReship($reship_id);
                if($is_check == '10'){ //质检异常
                    //反审核质检
                    $process_sql = "UPDATE sdb_ome_return_process_items SET is_check='true' WHERE reship_id=".$reship_id." AND is_check='false'";
                    $Oreship->db->exec($process_sql);
                }
                if(!$result){
                    $msg = '没有生成出入库明细!';
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * 审核退换货单
     * 
     * @param array $params 退换货单相关参数
     * @param string $error_msg
     * @param bool $is_rollback 遇到错误,是否回滚更新的数据(默认为:回滚)
     * 
     * @todo need_sv 审核标识(true：正常审核,false：已经被反审单过的标识)
     * @todo exec_type 审核类型(1:系统自动审核,2:批量审核),默认为0
     * @return bool
     */
    function confirm_reship($params, &$error_msg, &$is_rollback=true)
    {
        $Oreship = app::get('ome')->model('reship');
        $oReship_item = app::get('ome')->model('reship_items');
        $oOperation_log = app::get('ome')->model('operation_log');
        $orderObj = app::get('ome')->model('orders');
        
        $consoleReshipLib = kernel::single('console_reship');
        $reReshipLib = kernel::single('ome_receipt_reship');
        $libBranchProduct = kernel::single('ome_branch_product');
        $branchLib = kernel::single('ome_branch');
        
        $operation = '审核退换货单,';
        $reship_id = $params['reship_id'];
        $is_auto_check = false;
        
        //check
        if(empty($reship_id) || !isset($params['status'])){
            $error_msg = '无效的操作';
            return false;
        }
        
        //审核类型(1:系统自动审核,2:批量审核),默认值0为人工操作
        $execTypeList = array(1=>'系统自动', 2=>'批量');
        $exec_type = $params['exec_type'] ? intval($params['exec_type']) : 0;
        if($exec_type){
            $operation = $execTypeList[$exec_type] . $operation;
            
            $is_auto_check = true;
        }
        
        //审核状态
        $status = $params['status'];
        
        //是否反审核
        $is_anti = ($params['is_anti'] == true) ? true : false;
        
        //filter
        $filter = array('reship_id'=>$reship_id);
        
        //退换货单信息
        $reship = $Oreship->dump($filter, '*');
        if(empty($reship)){
            $error_msg = '没有可操作的退换货单!';
            return false;
        }
        foreach(kernel::servicelist('ome.service.reship.check.before') as $object) {
            if(method_exists($object, 'check_valid')){
                list($rs, $rsData) = $object->check_valid($reship_id);
                if(!$rs) {
                    $error_msg = $rsData['msg'];
                    return false;
                }
            }
        }
        if($reship['flag_type'] & ome_reship_const::__RESHIP_DIFF) {
            list($rs, $rsData) = kernel::single('console_reship_diff')->doCheck($reship);
            if(!$rs) {
                $error_msg = $rsData['msg'];
            }
            return $rs;
        }
        #拦截入库审核打拦截接口
        if($reship['is_check'] == '0'
            && $status == '1'
            && $reship['flag_type'] & ome_reship_const::__LANJIE_RUKU
            && !($reship['flag_type'] & ome_reship_const::__QZ_REFUND_CODE)) {
            $rs = ome_delivery_notice::cut(['logi_no'=>$reship['logi_no']]);
            if($rs['rsp'] != 'succ') {
                //$error_msg = '拦截失败：'.$rs['msg'];
                //return false;
            } else {
                if($reship['delivery_id']) {
                    app::get('ome')->model('delivery')->update(['logi_status'=>'7'], ['delivery_id'=>$reship['delivery_id']]);
                }
            }
        }
        
        $order_id = $reship['order_id'];
        
        //是否归档
        $is_archive = ($reship['archive']==1 ? true : false);
        $source = $reship['source']; //归档来源(archive、matrix)
        
        //[归档]订单实例化
        if($is_archive){
            $orderObj = app::get('archive')->model('orders');
        }
        
        //check
        if(!$is_anti && $reship['is_check'] == '1'){
            $error_msg = '退换货单是已审核状态,不能操作!'; //反审核时,不用判断is_check=1状态
            return false;
        }
        
        if ($reship['is_check']=='7') {
            $error_msg = '退换货单是已完成状态,不能操作!';
            return false;
        }
        
        //全额退款订单判断
        $current_order_arr = $orderObj->dump(array('order_id'=>$reship['order_id']), 'pay_status');
        if($current_order_arr['pay_status'] == '5'){
            if(($reship['tmoney'] - $reship['had_refund']) > 0 && $exec_type == 1){
                // 只有自动审核的时候判断，批量和单独审单不再判断
                $error_msg = '此订单已经全额退款，退款金额不能大于已支付金额!';
                return false;
            }
            if($reship['return_type'] == 'change'){
                $error_msg = '此订单已经全额退款，不能进行换货操作!';
                return false;
            }
        }
        
        //WMS仓储类型
        $wms_type = $branchLib->getNodetypBybranchId($reship['branch_id']);
        
        //判断订单是否是门店仓履约的
        //退入仓为判断依据 因为只有是退入仓是门店仓的情况下 换出仓才可能是门店仓 走wap端  当退入仓为电商仓时，无论换出仓是门店还是电商仓都走电商oms端
        $store_id = kernel::single('ome_branch')->isStoreBranch($reship["branch_id"]);
        
        //换货需验证退入和换出的仓库
        if($reship["return_type"] == "change"){
            $result_branch_check = kernel::single('o2o_return')->check_reship_branch($reship["branch_id"],$reship["changebranch_id"],$error_msg);
            if(!$result_branch_check){
                // $error_msg = '退换货单：'. $reship['reship_bn'] .','. $error_msg;
                return false;
            }
        }
        
        //自动审核时检查金额
        if($is_auto_check){
            //判断退款金额
            $orderInfo = $orderObj->getList('order_bn, total_amount, payed', array('order_id'=>$order_id), 0, 1);
            $orderInfo = $orderInfo[0];
            
            //归档订单信息
            if ($reship['archive'] == '1'){
                $orderInfo = app::get('archive')->model('orders')->dump(array('order_id'=>$order_id),'order_bn, total_amount, payed');
            }
            
            if(empty($orderInfo)){
                // $error_msg = '退换货单：'. $reship['reship_bn'] .',关联订单不存在!';
                $error_msg = '关联订单不存在!';
                return false;
            }
            
            // 只有自动审核的时候判断，批量和单独审单不再判断
            if($reship['totalmoney'] > $orderInfo['payed'] && $exec_type == 1){
                $error_msg = '退款金额不能大于订单的已支付金额!';
                return false;
            }
            
            if ($reship['return_type'] == 'return' && $reship['totalmoney'] < 0) {
                $error_msg = '退款金额不能小于零!';
                return false;
            }
        }
        
        //检查退换货单明细
        if($status == '1'){
            //判断退换货单明细
            $is_fail = $this->check_reship_items($reship_id);
            if($is_fail){
                $error_msg = '退货明细有货号不存在';
                return false;
            }
            
            //[换货]检查退入商品和换出商品的数量
            if($reship['return_type'] == 'change' && $reship['is_check']!='11'){
                $result = array();
                $oReship_item->Get_items_count($reship_id, $result);
                
                if($result['return'] == '0' || ($result['change'] == '0' && $reship['change_status']!='2')){
                    // $error_msg = '退换货单：'. $reship['reship_bn'] .',退入商品与换货商品数量有误!';
                    $error_msg = '退入商品与换货商品数量有误!';
                    return false;
                }
                
                if($reship['change_status']!='2'){
                    // 只有自动审核的时候判断，批量和单独审单不再判断
                    if($result['return'] != $result['change'] && $exec_type == 1){
                        // $error_msg = '退换货单：'. $reship['reship_bn'] .',退入商品数量与换货商品数量不一致!';
                        $error_msg = '退入商品数量与换货商品数量不一致!';
                        return false;
                    }
                }
            }
        }
        
        //[换货]判断库存
        if ((($reship['is_check']=='0' && $reship['need_sv'] == 'true') || $reship['is_check']=='12') && ($reship['return_type'] == 'change')){
            $change_item = $consoleReshipLib->change_items($reship_id);
            $changebranch_id = $reship['changebranch_id'];
            
            $change_store_id = kernel::single('ome_branch')->isStoreBranch($changebranch_id);
            
            foreach ($change_item as $item)
            {
                //是否检查库存数 默认true(门店仓存在不管控库存的情况)
                $check_stock = true;
                
                //[京东云交易]换货不需要验证库存
                if($wms_type=='yjdf'){
                    $check_stock = false;
                }
                
                if($change_store_id)
                {
                    //门店仓换货库存检查
                    $arr_stock = kernel::single('o2o_return')->o2o_store_stock($changebranch_id, $item['product_id']);
                    $could_usable_store = $arr_stock["store"]; //值可能会包括 "-" "x" 或 真实的库存数
                    
                    if ($could_usable_store == "x"){
                        $error_msg = '单据号：'. $reship['reship_bn'] .'; 货号:'.$item['bn'].',与此门店仓无供货关系';
                        return false;
                    }elseif($could_usable_store == "-"){
                        $check_stock = false; //不管控库存
                    }
                }
                else
                {
                    // //电商仓换货库存检查
                    // $usable_store = $libBranchProduct->getAvailableStore($item['changebranch_id'], array($item['product_id']));
                    
                    // $could_usable_store = $usable_store[$item['product_id']] ? $usable_store[$item['product_id']] : 0;

                    // 因为现在退换货单新建的时候就会去冻结，所以审单的时候无需再检测可用库存
                    $check_stock        = false;
                    $could_usable_store = 0;
                }
                
                if ($item['num'] > $could_usable_store && $check_stock) {
                    $error_msg = '单据号：'. $reship['reship_bn'] .'; 货号:'.$item['bn'].',可用库存不足';
                    return false;
                }
            }

            // 新建退换货单的时候，有可能冻结失败，流水会被一起回滚掉，检测是否有冻结流水，没有就去新建
            if ($reship['return_type'] == 'change' || $reship['changebranch_id']) {
                $stockFreezeMdl = app::get('material')->model('basic_material_stock_freeze');
                $_filter = [
                    'obj_id'    =>  $reship_id,
                    // 换货单审核换出商品库存不足会冻结到商品上（不冻仓）, 所以obj_type也有可能是1,所以查询的时候注释掉obj_type
                    // 'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                    'bill_type' =>  material_basic_material_stock_freeze::__RESHIP,
                    'branch_id|in' =>  [$reship["changebranch_id"],0],
                ];
                $stock_freeze = $stockFreezeMdl->db_dump($_filter);
                if (!$stock_freeze) {
                    $error = '';
                    $result = kernel::single('console_reship')->addChangeFreeze($reship_id, $error);
                    if(!$result){
                        $error_msg = '审核补换货预占库存失败'.$error;
                        return false;
                    }
                }
            }
        }
        
        //[电商仓]最终确认时,残损确认判断
        if (!$store_id){
            $result = $this->check_defective($reship_id, $reship['is_check'], $check_error_msg, $wms_type);
            if(!$result){
                $error_msg = '单据号：'. $reship['reship_bn'] .';'. $check_error_msg;
                return false;
            }
        }
        
        //判断重复审核(15秒之内不能重复)
        if($status == '1' && in_array($reship['is_check'], array('0', '2'))){
            $cacheKeyName = sprintf("confirm_reship_id_%s", $reship_id);
            $cacheData = cachecore::fetch($cacheKeyName);
            if ($cacheData === false) {
                cachecore::store($cacheKeyName, date('YmdHis', time()), 15);
            }else{
                // $error_msg = '退换货单：'. $reship['reship_bn'] .',已经在审核中,15秒内请不要重复操作('. $cacheData .')!';
                $error_msg = '已经在审核中,15秒内请不要重复操作('. $cacheData .')!';
                return false;
            }
        }
        
        //是否请求标识
        $is_request_create = false;
        
        //[京东云交易]请求创建京东云交易售后服务单
        if($wms_type=='yjdf' && in_array($reship['is_check'], array('0', '2'))){
            $keplerLib = kernel::single('ome_reship_kepler');
            $reship['action'] = 'confirm';
            $result = $keplerLib->process($reship);
            if($result['rsp'] != 'succ'){
                $error_msg = $result['msg'];
                $oOperation_log->write_log('reship@ome', $reship_id, $error_msg);
                
                //不用回滚更新的数据的标识
                $is_rollback = false;
                
                return false;
            }
            
            $is_request_create = true;
            
        }else{
            //是否通知WMS创建退货单
            if(($reship['is_check']=='0' && $reship['need_sv'] == 'true') || $reship['is_check']=='12'){
                $is_request_create = true;
            }
        }

        //通知WMS创建退货单
        //@todo：推送WMS成功才会更新审单状态
        if($is_request_create){
            //发起通知单
            $error_msg = '';
            $result = ome_return_notice::create($reship_id, $error_msg);
           // if(!$result){
               // $is_rollback = false; //不用回滚更新的数据
                
                //$error_msg = ($error_msg ? $error_msg : '发起通知单失败');
               // return false;
            //}
        }
        
        //update data
        $updateData = array('is_check'=>$status);
        
        //post
        if($_POST){
            //审核原因
            $reason = unserialize($reship['reason']);
            if(isset($_POST['reason'])) {
                $reason['check'] = $_POST['reason'];
            }
            
            //是否反审核(true:正常审核,false：反审核)
            if(isset($_POST['need_sv'])) {
                $updateData['need_sv'] = $_POST['need_sv'];
            }
            
            $updateData['reason'] = serialize($reason);
        }
        
        if ($reship['is_check']=='0' && $reship['need_sv'] == 'true') {
            $updateData['check_time'] = time(); //审核时间
        }
        
        //更新审单状态
        $error_msg = '';
        $affect_row = $Oreship->update($updateData, array('reship_id'=>$reship_id));
        if(!is_numeric($affect_row) || $affect_row <= 0){
            // $error_msg = '退换货单：'. $reship['reship_bn'] .' 审核失败!';
            $error_msg = '审核失败!';
            return false;
        }
        
        //log
        $schema = $Oreship->schema['columns'];
        $memo = $operation. '状态:'.$schema['is_check']['type'][$status];
        $oOperation_log->write_log('reship@ome', $reship_id, $memo);
        foreach(kernel::servicelist('ome.service.reship.check.after') as $object) {
            if(method_exists($object, 'after_check')){
                $object->after_check($reship_id);
            }
        }
        //[电商仓]质检成功后执行相应的操作
        
        $result = $this->part_finish_aftersale($reship_id,$reship['is_check'], $after_error_msg);
        if(!$result){
            // $error_msg = '退换货单：'. $reship['reship_bn'] . $after_error_msg;
            $error_msg = $after_error_msg;
            return false;
        }
        
        
        return true;
    }

    /**
     * 执行队列任务自动审核退货单
     * 
     * @param int $reship_id 售后单ID
     * 
     * @return bool
     */
    function batch_reship_queue($reship_id)
    {
        $reship_ids = array();
        $reship_ids[0] = $reship_id;
        
        $reshipObj = app::get('ome')->model('reship');
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), 'reship_id,reship_bn,return_logi_no,is_check');
        //自动审核是否需要有回退物流单号
        $mstLogiApprove = app::get('ome')->getConf('return.logi_auto_approve');
        if($mstLogiApprove == 'on'){
           if(empty($reshipInfo['return_logi_no'])){
                return false;
            }
        }
        if($reshipInfo['is_check'] == '11'){
            return false;
        }
        
        //获取system账号信息
        $opinfo = kernel::single('ome_func')->get_system();

        //自动审单_批量日志
        $blObj  = app::get('ome')->model('batch_log');

        $batch_number = count($reship_ids);
        $bldata = array(
                'op_id' => $opinfo['op_id'],
                'op_name' => $opinfo['op_name'],
                'createtime' => time(),
                'batch_number' => $batch_number,
                'log_type'=> 'confirm_reship',
                'log_text'=> serialize($reship_ids),
        );
        $result = $blObj->save($bldata);

        //自动审批任务队列(改成多队列多进程)
        if (defined('SAAS_COMBINE_MQ') && SAAS_COMBINE_MQ == 'true') {
            $data = array();
            $data['spider_data']['url'] = kernel::openapi_url('openapi.autotask','service');

            $push_params = array(
                    'log_text'  => $bldata['log_text'],
                    'log_id'    => $bldata['log_id'],
                    'task_type' => 'confirmreship',
            );
            $push_params['taskmgr_sign'] = taskmgr_rpc_sign::gen_sign($push_params);
            foreach ($push_params as $key => $val) {
                $postAttr[] = $key . '=' . urlencode($val);
            }

            $data['spider_data']['params']    = empty($postAttr) ? '' : join('&', $postAttr);
            $data['relation']['to_node_id']   = base_shopnode::node_id('ome');
            $data['relation']['from_node_id'] = '0';
            $data['relation']['tid']          = $bldata['log_id'];
            $data['relation']['to_url']       = $data['spider_data']['url'];
            $data['relation']['time']         = time();

            $routerKey = 'tg.order.reship.'. $data['relation']['from_node_id'];

            $message = json_encode($data);
            $mq = kernel::single('base_queue_mq');
            $mq->connect($GLOBALS['_MQ_COMBINE_CONFIG'], 'TG_COMBINE_EXCHANGE', 'TG_COMBINE_QUEUE');
            $mq->publish($message, $routerKey);
            $mq->disConnect();
        } else {
            $push_params = array(
                    'data' => array(
                            'log_text'  => $bldata['log_text'],
                            'log_id'    => $bldata['log_id'],
                            'task_type' => 'confirmreship',
                    ),
                    'url' => kernel::openapi_url('openapi.autotask','service'),
            );

            kernel::single('taskmgr_interface_connecter')->push($push_params);
        }

        return true;
    }

    static function check_reship_items($reship_id){
        $db = kernel::database();

        $sql = "SELECT reship_id FROM sdb_ome_reship_items WHERE reship_id=".$reship_id." AND (product_id IS NULL OR product_id=0)";


        $items = $db->selectrow($sql);
        return $items;
    }
    
    /**
     * 退换货单上更新了退回物流单号时,同步给第三方仓储
     */
    public function request_wms_returnorder($reship_id, &$error_msg){
        //限制30秒内不重复推送
        $cacheKeyName = 'reship_send_returnlogino_'. $reship_id;
        $is_send = cachecore::fetch($cacheKeyName);
        if($is_send){
            return false;
        }
        
        cachecore::store($cacheKeyName, true, 30);
        
        $oOperation_log = $this->app->model('operation_log');
        $Oreship = app::get('ome')->model('reship');
        
        $reshipInfo = $Oreship->dump(array('reship_id'=>$reship_id), 'reship_id, is_check, reship_bn');
        if (in_array($reshipInfo['is_check'], array('0', '4', '5', '7', '11'))) {
            $error_msg = '无需同步给第三方仓储';
            return false;
        }
        
        $reship_data = kernel::single('ome_receipt_reship')->reship_create(array('reship_id'=>$reship_id));
        
        $wms_id = kernel::single('ome_branch')->getWmsIdById($reship_data['branch_id']);
        
        $rsp_result = kernel::single('console_event_trigger_reship')->create($wms_id, $reship_data, false);
        
        //记录日志
        // $rsp_result = json_encode($rsp_result);
        $memo = '发送至第三方, msg_id:'.(is_array($rsp_result) ? $rsp_result['msg_id']:'');
        $oOperation_log->write_log('reship@ome', $reship_id, $memo.$rsp_result);
        
        return true;
    }
    
    /**
     * [京东云交易]创建退货包裹明细
     * 
     * @param array $reshipInfo
     * @param string $error_msg
     * @return array
     */
    public function create_reship_package($reshipInfo, &$error_msg=null)
    {
        $rePackageObj = app::get('ome')->model('reship_package');
        $rePackageItemObj = app::get('ome')->model('reship_package_items');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $reship_id = $reshipInfo['reship_id'];
        $order_id = $reshipInfo['order_id'];
        
        //通过退货sku,找到对应的发货包裹(每个sku会是一个包裹)
        $returnSkus = array();
        $returnPackages = $this->getApplyReturnPackage($reshipInfo, $returnSkus, $error_msg);
        if(!$returnPackages){
            $error_msg = '获取发货包裹失败：'. $error_msg;
            return false;
        }
        
        //check退货数量 
        foreach ($returnSkus as $key => $val)
        {
            if($val['num'] > 0){
                $error_msg = '货号：'. $val['bn'].'实际可退货数量为'. intval($val['return_nums']) .';';
                return false;
            }
        }
        
        //保存京东包裹与退货单关联明细
        $packag_skus = array();
        foreach($returnPackages as $packageKey => $val)
        {
            $wms_package_id = $val['wms_package_id']; //发货包裹ID
            $wms_channel_id = $val['wms_channel_id']; //发货渠道ID
            
            //items
            $itemList = $val['items'];
            unset($val['items']);
            
            //sdf
            $sdf = array(
                    'reship_id' => $reship_id,
                    'order_id' => $order_id,
                    'delivery_id' => $val['delivery_id'],
                    'delivery_bn' => $val['delivery_bn'],
                    'wms_channel_id' => $wms_channel_id, //发货渠道ID
                    'wms_package_id' => $wms_package_id, //发货包裹ID
                    'wms_package_bn' => $val['wms_package_bn'], //发货包裹单号
                    'create_time' => time(),
                    'last_time' => time(),
            );
            
            //save
            $result = $rePackageObj->save($sdf);
            if(!$result){
                $error_msg = '保存退货包裹失败,package_bn：'.$val['wms_package_bn'];
                return false;
            }
            
            //items
            foreach ($itemList as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                $product_bn = $itemVal['bn'];
                $return_nums = intval($itemVal['return_nums']); //退货数量
                
                //WMS赠品,直接取赠送数量
                if($itemVal['is_wms_gift'] == 'true'){
                    $return_nums = $itemVal['number'];
                }
                
                //item
                $itemSdf = array(
                        'package_id' => $sdf['package_id'],
                        'product_id' => $product_id,
                        'bn' => $product_bn,
                        'outer_sku' => $itemVal['outer_sku'],
                        'return_nums' => $return_nums,
                        'is_wms_gift' => $itemVal['is_wms_gift'], //是否WMS赠品
                        'product_name' => $itemVal['product_name'],
                );
                
                //save
                $result = $rePackageItemObj->save($itemSdf);
                if(!$result){
                    $error_msg = '保存退货包裹明细失败,package_bn：'.$val['wms_package_bn'];
                    return false;
                }
                
                //WMS赠品,不更新申请退货数量
                if($itemVal['is_wms_gift'] == 'true'){
                    continue;
                }
                
                //更新发货单包裹的申请退货数量
                $sql = "UPDATE sdb_ome_delivery_package SET apply_num=apply_num+". $return_nums ." WHERE package_id=". $wms_package_id ." AND bn='". $product_bn ."'";
                $rePackageObj->db->exec($sql);
                
            }
        }
        
        //log
        $operLogObj->write_log('reship@ome', $reship_id, '创建退货包裹成功');
        
        return true;
    }
    
    /**
     * [京东云交易]获取退货包裹明细
     * 
     * @param int $reship_id
     * @param string $error_msg
     * @return array
     */
    public function get_reship_package($reship_id, &$error_msg=null)
    {
        $rePackageObj = app::get('ome')->model('reship_package');
        $rePackageItemObj = app::get('ome')->model('reship_package_items');
        
        //退货包裹列表
        $dataList = array();
        $tempList = $rePackageObj->getList('*', array('reship_id'=>$reship_id));
        if(empty($tempList)){
            return array();
        }
        
        $package_ids = array();
        foreach ($tempList as $key => $val)
        {
            $package_id = $val['package_id'];
            
            //sync_status
            $val['sync_status'] = ($val['sync_status']=='normal' ? '' : $val['sync_status']);
            
            $package_ids[$package_id] = $package_id;
            
            $dataList[$package_id] = $val;
        }
        
        //退货包裹明细列表
        $tempList = $rePackageItemObj->getList('*', array('package_id'=>$package_ids));
        if($tempList){
            foreach ($tempList as $key => $val)
            {
                $package_id = $val['package_id'];
                
                $dataList[$package_id]['items'][] = $val;
            }
        }
        
        return $dataList;
    }
    
    /**
     * 通过包裹号(京东订单号)取消发货单
     * 
     * @param unknown $reship_id
     * @param string $getItems
     * @param string $error_msg
     */
    public function cancel_delivery_package($reshipInfo, &$error_msg=null)
    {
        $rePackageObj = app::get('ome')->model('reship_package');
        $reshipObj = app::get('ome')->model('reship');
        
        $dlyTriggerLib = kernel::single('ome_event_trigger_delivery');
        $branchLib = kernel::single('ome_branch');
        
        $reship_id = $reshipInfo['reship_id'];
        $branch_id = $reshipInfo['branch_id'];
        $flag_type = $reshipInfo['flag_type'];
        
        //wms_id
        $channel_id = $branchLib->getWmsIdById($branch_id);
        
        //branch_bn
        $branch_bn = $branchLib->getBranchBnById($branch_id);
        $branchInfo = $branchLib->getBranchInfo($branch_id,'owner_code');
        
        //打标
        $flag_type = $flag_type | ome_reship_const::__REBACK_DELIVERY;
        
        $reshipObj->update(array('flag_type'=>$flag_type), array('reship_id'=>$reship_id));
        
        //data
        $dataList = array();
        $tempList = $rePackageObj->getList('*', array('reship_id'=>$reship_id));
        if(empty($tempList))
        {
            $error_msg = '拦截包裹失败:没有找到相应京东订单号';
            return false;
        }
        
        //拦截包裹(取消包裹)
        $deliveryList = array();
        foreach ($tempList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            
            $deliveryList[$delivery_id] = $val;
        }
        
        foreach ($deliveryList as $key => $val)
        {
            $requestData = array(
                    'outer_delivery_id' => $val['delivery_id'],
                    'outer_delivery_bn' => $val['delivery_bn'],
                    'branch_bn' => $branch_bn,
                    'owner_code' => $branchInfo['owner_code'],
            );
            
            $res = $dlyTriggerLib->cancel('wms', $channel_id, $requestData);
            if($res['rsp'] != 'succ'){
                //失败打标
                $flag_type = $flag_type | ome_reship_const::__REBACK_FAIL;
                
                $reshipObj->update(array('flag_type'=>$flag_type), array('reship_id'=>$reship_id));
                
                $error_msg = '拦截失败,包裹号:'.$val['package_bn'];
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 自动完成退货单
     */
    public function autoCompleteReship(&$cursor_id, $params, &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $branchObj = app::get('ome')->model('branch');
        $reshipItemObj = app::get('ome')->model('reship_items');
        $logObj = app::get('ome')->model('operation_log');
        
        //material data
        $sdfdata = $params['sdfdata'];
        $reship_id = intval($sdfdata['reship_id']);
        $reship_bn = $sdfdata['reship_bn'];
        
        //退货单信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), 'order_id,reship_id,reship_bn,status,is_check,return_type,change_status,branch_id');
        if(empty($reshipInfo)){
            //$error_msg = '退货单不存在';
            return false;
        }
        
        if(!in_array($reshipInfo['is_check'], array('0', '1'))){
            $error_msg = '退货单审核状态不正确,不能自动完成;';
            return false;
        }
        
        if($reshipInfo['status'] == 'succ'){
            //$error_msg = '退货单已完成,请不要重复操作。';
            return false;
        }
        
        //仓库信息
        $branchInfo = $branchObj->dump(array('branch_id'=>$reshipInfo['branch_id']), 'branch_id,branch_bn,wms_id,name');
        if(empty($branchInfo)){
            $error_msg = '退货单上仓库不存在。';
            return false;
        }
        
        $wms_id = $branchInfo['wms_id'];
        
        //组织参数
        $status = 'finish';
        $params = array(
                'reship_bn' => $reshipInfo['reship_bn'],
                //'logistics' => $params['logi_type'],
                //'logi_no' => $params['logi_no'],
                'warehouse' => $branchInfo['branch_bn'],
                'status' => strtoupper($status), //转换成大写
                'remark' => '追回包裹成功,自动完成退货单',
                'operate_time' => date('Y-m-d H:i:s', time()),
        );
        
        //退货明细
        $fields = 'item_id, product_id, bn, product_name, num, normal_num, defective_num';
        $reshipItems = $reshipItemObj->getList($fields, array('reship_id'=>$reshipInfo['reship_id'], 'return_type'=>'return'));
        if(empty($reshipItems)){
            $error_msg = '退货单明细不存在。';
            return false;
        }
        
        //退货明细(直接读取退货单上明细)
        $itemList = array();
        foreach ($reshipItems as $key => $val)
        {
            $itemList[] = array(
                    'bn' => $val['bn'],
                    'product_bn' => $val['bn'], //货号
                    'normal_num' => $val['num'], //良品数量
                    'defective_num' => 0, //不良品数量
            );
        }
        $params['items'] = $itemList; //自有仓储item不用json_encode
        unset($itemList);
        
        //完成退货
        $result = kernel::single('console_event_receive_reship')->updateStatus($params);
        if($result['rsp'] == 'succ'){
            //log
            $logObj->write_log('reship@ome', $reshipInfo['reship_id'], '追回包裹成功,自动完成退货单!');
        }else {
            //log
            $logObj->write_log('reship@ome', $reshipInfo['reship_id'], '追回包裹成功,自动退货失败('. $result['msg'] .')');
        }
        
        return false;
    }
    
    /**
     * [京东云交易]检查退货明细是否与订单发货商品完全符合
     * 
     * @param int $order_id 订单ID
     * @param array $returnItems 退货明细
     * @return bool
     */
    public function checkReturnPackageSku($order_id, $returnItems)
    {
        $orderItemObj = app::get('ome')->model('order_items');
        
        //订单明细
        $sku_list = array();
        $tempList = $orderItemObj->getList('item_id,obj_id,product_id,bn,nums,return_num,is_wms_gift', array('order_id'=>$order_id, 'delete'=>'false'));
        foreach ($tempList as $key => $val)
        {
            $product_id = $val['product_id'];
            
            //过滤WMS赠品
            if($val['is_wms_gift'] == 'true'){
                continue;
            }
            
            if(empty($sku_list[$product_id])){
                $sku_list[$product_id] = $val['nums'] - $val['return_num'];
            }else{
                $sku_list[$product_id] += ($val['nums'] - $val['return_num']);
            }
        }
        
        //退货明细
        $returnSkus = array();
        foreach ($returnItems as $key => $val)
        {
            $product_id = $val['product_id'];
            
            if(empty($returnSkus[$product_id])){
                $returnSkus[$product_id] = $val['num'];
            }else{
                $returnSkus[$product_id] += $val['num'];
            }
        }
        
        //diff
        $is_diff = array_diff($sku_list, $returnSkus);
        
        return empty($is_diff) ? false : true;
    }
    
    /**
     * [京东云交易]获取可退货的包裹
     * 
     * @param array $reshipInfo 退货单信息
     * @param array $returnSkus 退货货品明细,以product_id为下标
     * @param string $error_msg
     * @return array
     */
    public function getApplyReturnPackage($reshipInfo, &$returnSkus=array(), &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $reshipItemObj = app::get('ome')->model('reship_items');
        $packageObj = app::get('ome')->model('delivery_package');
        
        $reship_id = $reshipInfo['reship_id'];
        $order_id = $reshipInfo['order_id'];
        
        //退货单明细
        $returnSkus = array();
        $reshipItemList = $reshipItemObj->getlist('item_id,product_id,bn,num,product_name', array('reship_id'=>$reship_id, 'return_type'=>array('return', 'refuse')), 0, -1);
        if(empty($reshipItemList)){
            $error_msg = '没有退货单明细';
            return false;
        }
        
        //items
        foreach ($reshipItemList as $key => $val)
        {
            $product_id = $val['product_id'];
            
            if(empty($returnSkus[$product_id])){
                $returnSkus[$product_id] = $val;
            }else{
                $returnSkus[$product_id]['num'] += $val['num'];
            }
        }
        
        //check
        if(empty($returnSkus)){
            $error_msg = '没有退货货品明细';
            return false;
        }
        
        //关联发货单(包含已发货、已追回的发货单)
        $sql = "SELECT b.delivery_id,b.delivery_bn,b.is_wms_gift,wms_channel_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $sql .= " WHERE a.order_id=". $order_id ." AND b.status IN('succ', 'return_back')";
        $dataList = $reshipObj->db->select($sql);
        if(empty($dataList)){
            $error_msg = '没有找到关联发货单';
            return false;
        }
        
        //多个发货单
        $deliveryList = array();
        $giftDeliverys = array();
        foreach ($dataList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            
            $deliveryList[$delivery_id] = $val;
            
            //[京东云交易]有赠品的发货单
            if($val['is_wms_gift'] == 'true'){
                $giftDeliverys[$delivery_id] = $val;
            }
        }
        
        //场景：发货包裹包含赠品,抖音平台必须整单退货,否则异常
        if($giftDeliverys){
            $deliveryIds = array_keys($deliveryList);
            
            //主品和赠品相同,不允许退货
            $is_equal = $this->checkPackageSkuEqual($deliveryIds);
            if($is_equal){
                $error_msg = '异常：京东订单里主品与赠品相同,不支持部分退货;';
                
                //设置异常：主品与赠品相同,不支持部分退货
                $abnormal_status = ome_constants_reship_abnormal::__EQUAL_CODE;
                $sql = "UPDATE sdb_ome_reship SET is_check='2',abnormal_status=abnormal_status | ". $abnormal_status .",sync_msg='". $error_msg ."' WHERE reship_id=".$reship_id;
                $reshipObj->db->exec($sql);
                
                return false;
            }
            
            //必须整单退货
            $is_diff = $this->checkReturnPackageSku($order_id, $reshipItemList);
            if($is_diff){
                $error_msg = '异常：京东订单里有赠品,必须是整单退货,不支持部分退货;';
                
                //设置异常：不是整单退货
                $reshipObj->update(array('is_check'=>'2', 'sync_msg'=>$error_msg), array('reship_id'=>$reship_id));
                
                return false;
            }
        }
        
        //获取已发货的京东包裹单
        $fields = 'package_id,package_bn,delivery_id,product_id,bn,outer_sku,number,return_num,apply_num,logi_no,logi_bn,status,is_wms_gift';
        $dataList = $packageObj->getList($fields, array('delivery_id'=>array_keys($deliveryList), 'status'=>array('delivery', 'return_back')), 0, -1, 'is_wms_gift DESC');
        if(empty($dataList)){
            $error_msg = '没有找到关联的京东包裹!';
            return false;
        }
        
        $returnPackages = array();
        foreach ($dataList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            $product_id = $val['product_id']; //有赠品是0的场景
            $package_bn = $val['package_bn'];
            
            //发货渠道ID
            $val['wms_channel_id'] = $deliveryList[$delivery_id]['wms_channel_id'];
            
            //发货单号
            $val['delivery_bn'] = $deliveryList[$delivery_id]['delivery_bn'];
            
            //WMS赠品,加入到退货包裹明细上(sql查询时按主品已排序,赠品排在后面)
            if($val['is_wms_gift'] == 'true'){
                //退货物料名称
                $val['product_name'] = $val['bn'];
                
                if(empty($returnPackages[$package_bn])){
                    continue; //不是本次退货商品的关联赠品
                }
            }else{
                
                //退货物料名称
                $val['product_name'] = $returnSkus[$product_id]['product_name'];
                
                //过滤不需要的SKU包裹信息
                if(empty($returnSkus[$product_id])){
                    continue;
                }
                
                //申请退货数量
                $return_nums = $returnSkus[$product_id]['num'];
                if($return_nums <= 0){
                    continue;
                }
                
                //包裹可退货数量 = 发货数量 - 已退货数量 - 申请退货数量;
                //$dly_nums = $val['number'] - $val['return_num'] - $val['apply_num'];
                
                //[防止创建退货包裹单失败]包裹可退货数量 = 发货数量 - 已退货数量
                $dly_nums = $val['number'] - $val['return_num'];
                if($dly_nums <= 0){
                    //$error_msg = '货号：'.$val['bn'].'没有退货数量;';
                    continue;
                }
                
                //退货数量
                if($return_nums > $dly_nums){
                    $val['return_nums'] = $dly_nums;
                    
                    $return_nums = $dly_nums;
                }else{
                    $val['return_nums'] = $return_nums;
                }
                
                //更新需要退货的数量
                $returnSkus[$product_id]['num'] -= $return_nums;
                $returnSkus[$product_id]['return_nums'] = $return_nums;
            }
            
            //组织退货的包裹
            if(empty($returnPackages[$package_bn])){
                $returnPackages[$package_bn] = array(
                        'wms_package_id' => $val['package_id'], //发货包裹ID
                        'wms_package_bn' => $val['package_bn'], //发货包裹单号
                        'delivery_id' => $val['delivery_id'],
                        'delivery_bn' => $val['delivery_bn'],
                        'logi_bn' => $val['logi_bn'], //物流编码
                        'logi_no' => $val['logi_no'], //物流单号
                        'status' => $val['status'], //状态
                        'wms_channel_id' => $val['wms_channel_id'], //发货渠道ID
                );
                
                //item
                $returnPackages[$package_bn]['items'][] = array(
                        'product_id' => $val['product_id'],
                        'bn' => $val['bn'],
                        'outer_sku' => $val['outer_sku'],
                        'number' => $val['number'], //sku发货数量
                        'product_name' => $val['product_name'],
                        'return_nums' => $return_nums, //退货数量
                        'is_wms_gift' => $val['is_wms_gift'], //是否WMS赠品
                );
            }else{
                //item
                $returnPackages[$package_bn]['items'][] = array(
                        'product_id' => $val['product_id'],
                        'bn' => $val['bn'],
                        'outer_sku' => $val['outer_sku'],
                        'number' => $val['number'], //sku发货数量
                        'product_name' => $val['product_name'],
                        'return_nums' => $return_nums, //退货数量
                        'is_wms_gift' => $val['is_wms_gift'], //是否WMS赠品
                );
            }
        }
        
        //check
        if(empty($returnPackages)){
            $error_msg = '没有可退的包裹或者可退货数量不足';
            return false;
        }
        
        return $returnPackages;
    }
    
    /**
     * 通过店铺平台售后原因找到WMS售后原因
     * 
     * @param array $reshipInfo
     * @return array
     */
    public function getReturnWmsReason($reshipInfo)
    {
        $problemObj = app::get('ome')->model('return_product_problem');
        
        $reason_id = '';
        $reason = '';
        
        //获取店铺平台售后原因
        if($reshipInfo['problem_id']){
            //已选择售后原因
            $problemInfo = $problemObj->dump(array('problem_id'=>$reshipInfo['problem_id']), 'problem_id,reason_id,problem_name');
            $reason_id = $problemInfo['reason_id'];
            $reason = $problemInfo['problem_name'];
        }else{
            //读取售后申请单上申请原因
            $returnObj = app::get('ome')->model('return_product');
            $returnInfo = $returnObj->dump(array('return_id'=>$reshipInfo['return_id']), 'return_id,content');
            
            $returnInfo['content'] = trim($returnInfo['content']);
            if($returnInfo['content']){
                $problemInfo = $problemObj->dump(array('problem_type'=>'shop', 'problem_name'=>$returnInfo['content']), 'problem_id,reason_id,problem_name');
                $reason_id = $problemInfo['reason_id'];
                $reason = $problemInfo['problem_name'];
            }
        }
        
        //获取WMS售后原因
        $problemInfo = array();
        if($reason_id){
            $problemInfo = $problemObj->dump(array('problem_type'=>'wms', 'rel_reason_id'=>$reason_id), 'problem_id,reason_id,problem_name');
        }elseif($reason){
            $problemInfo = $problemObj->dump(array('problem_type'=>'wms', 'rel_reason_name'=>$reason), 'problem_id,reason_id,problem_name');
        }
        
        //[兼容]使用默认原因
        if(empty($problemInfo)){
            $problemInfo = $problemObj->dump(array('problem_type'=>'wms', 'defaulted'=>'true'), 'problem_id,reason_id,problem_name');
            
            //没有设置默认原因,则用"其他"
            if(empty($problemInfo)){
                $sql = "SELECT problem_id,reason_id,problem_name FROM `sdb_ome_return_product_problem` WHERE problem_type='wms' AND (problem_name='其他' OR problem_name='其它')";
                $problemInfo = $problemObj->db->selectrow($sql);
            }
        }
        
        return $problemInfo;
    }
    
    /**
     * 平台售后状态
     */
    public function get_platform_status($platform_status=null)
    {
        $dataList = array(
                '6' => '待商家处理',
                '7' => '待买家退货',
                '11' => '待商家收货',
                '12' => '商家同意退款',
                '27' => '拒绝售后申请',
                '28' => '售后关闭',
                '29' => '退货后商家拒绝',
                'WAIT_SELLER_AGREE' => '买家已申请退款',
                'WAIT_BUYER_RETURN_GOODS' => '卖家已同意退款',
                'WAIT_SELLER_CONFIRM_GOODS' => '买家已经退货',
                'SELLER_REFUSE_BUYER' => '卖家拒绝退款',
                'CLOSED' => '退款关闭',
                'SUCCESS' => '退款成功',
                'WAIT_BUYER_CONFIRM_GOODS'=>'等待买家确认货品',
        );
        
        //指定获取某个状态值
        if($platform_status){
            return $dataList[$platform_status];
        }
        
        return $dataList;
    }
    
    /**
     * 取消退货包裹信息
     * 
     * @param unknown $reship_id
     * @param string $getItems
     * @param string $error_msg
     */
    public function cancel_reship_package($reshipInfo, &$error_msg=null)
    {
        $rePackageObj = app::get('ome')->model('reship_package');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $reship_id = $reshipInfo['reship_id'];
        $order_id = $reshipInfo['order_id'];
        
        //取消退货包裹单
        $rePackageObj->update(array('status'=>'cancel', 'last_time'=>time()), array('reship_id'=>$reship_id));
        
        //退货单包裹明细
        $sql = "SELECT a.*, b.reship_id, b.delivery_id, b.wms_package_id FROM sdb_ome_reship_package_items AS a ";
        $sql .= " LEFT JOIN sdb_ome_reship_package AS b ON a.package_id=b.package_id WHERE b.reship_id=". $reship_id;
        
        $itemList = $rePackageObj->db->select($sql);
        if(empty($itemList)){
            $error_msg = '没有退货单包裹明细';
            return false;
        }
        
        //释放退货包裹申请数量
        foreach ($itemList as $key => $val)
        {
            $wms_package_id = $val['wms_package_id'];
            $product_id = $val['product_id'];
            $product_bn = $val['bn'];
            $return_nums = $val['return_nums'];
            
            //过滤WMS赠品,不用更新可退货数量
            if($val['is_wms_gift'] == 'true'){
                continue;
            }
            
            //更新发货单包裹的申请退货数量
            $sql = "UPDATE sdb_ome_delivery_package SET apply_num=IF(apply_num<". $return_nums .",0,apply_num-". $return_nums .") ";
            $sql .= " WHERE package_id=". $wms_package_id ." AND bn='". $product_bn ."'";
            $rePackageObj->db->exec($sql);
        }
        
        //log
        $operLogObj->write_log('reship@ome', $reship_id, '取消退货包裹单成功');
        
        return true;
    }
    
    /**
     * 完成退货包裹信息
     * 
     * @param unknown $reship_id
     * @param string $getItems
     * @param string $error_msg
     */
    public function complete_reship_package($data, &$error_msg=null)
    {
        $rePackageObj = app::get('ome')->model('reship_package');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $reship_bn = $data['reship_bn'];
        $service_bn = $data['service_bn'];
        
        $sql = "SELECT a.por_id,a.reship_id,a.service_bn,a.package_bn,a.wms_order_code, b.item_id,b.product_id,b.bn,b.num FROM sdb_ome_return_process AS a ";
        $sql .= " LEFT JOIN sdb_ome_return_process_items AS b ON a.por_id=b.por_id WHERE a.service_bn='". $service_bn ."'";
        $tempList = $rePackageObj->db->select($sql);
        
        foreach ($tempList as $key => $val)
        {
            $reship_id = $val['reship_id'];
            $wms_package_bn = $val['package_bn'];
            $wms_order_code = $val['wms_order_code'];
            $product_bn = $val['bn'];
            $return_nums = $val['num']; //退货数量
            
            //更新发货单包裹的申请退货数量
            $sql = "UPDATE sdb_ome_delivery_package SET apply_num=IF(apply_num<". $return_nums .",0,apply_num-". $return_nums ."), return_num=return_num+". $return_nums ." ";
            $sql .= " WHERE package_bn='". $wms_package_bn ."' AND bn='". $product_bn ."'";
            $rePackageObj->db->exec($sql);
            
            //更新退货包裹上的状态
            $sql = "UPDATE sdb_ome_reship_package SET status='return' WHERE wms_order_code='". $wms_order_code ."'";
            $rePackageObj->db->exec($sql);
        }
        
        return true;
    }
    
    /**
     * [京东云交易]检查退货明细中主品和赠品是否相同
     * 
     * @param array $deliveryIds 发货单ID
     * @return bool
     */
    public function checkPackageSkuEqual($deliveryIds)
    {
        $dlyItemObj = app::get('ome')->model('delivery_items');
        $packageObj = app::get('ome')->model('delivery_package');
        
        if(empty($deliveryIds)){
            return false;
        }
        
        //发货单明细
        $bnsList = array();
        $tempList = $dlyItemObj->getList('item_id,product_id,bn,is_wms_gift', array('delivery_id'=>$deliveryIds));
        foreach ($tempList as $key => $val)
        {
            $product_bn = $val['bn'];
            
            //过滤赠品
            if($val['is_wms_gift'] == 'true'){
                continue;
            }
            
            $bnsList[] = $product_bn;
        }
        
        //京东订单与主品一样的赠品货号
        $isCheck = $packageObj->dump(array('delivery_id'=>$deliveryIds, 'bn'=>$bnsList, 'is_wms_gift'=>'true'), 'package_id');
        if($isCheck){
            return true;
        }
        
        return false;
    }
    
    /**
     * 修改售后换货单上的换货商品信息
     * 
     * @param $sdf
     * @param $error_msg
     * @return false|void
     */
    public function updateReshipExchangeItems($sdf, &$error_msg=null)
    {
        $reshipObjMdl = app::get('ome')->model('reship_objects');
        $reshipItemObj = app::get('ome')->model('reship_items');
        
        $salesMLib = kernel::single('material_sales_material');
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //params
        //$return_items = $sdf['return_items'];
        
        $returninfo = $sdf['return_product'];
        $reshipInfo = $sdf['reship'];
        
        $shop_id = ($sdf['shop_id'] ? $sdf['shop_id'] : $returninfo['shop_id']);
        $order_id = $returninfo['order_id'];
        $return_id = $returninfo['return_id'];
        $reship_id = $reshipInfo['reship_id'];
        $reship_bn = $reshipInfo['reship_bn'];
        $err = '';
        
        //换货单预占库存设置
        $is_create_freeze = true;
        $log_type = 'reship';
        
        //check
        if(empty($returninfo) || empty($reshipInfo) || empty($shop_id)){
            $error_msg = '没有售后申请单或售后换货单信息不存在';
            return false;
        }
        if($reshipInfo['change_status'] != 0) {
            $error_msg = '售后换货单状态不对：'.$reshipInfo['change_status'];
            return false;
        }
        
        //原objects层换货明细(现在每次只会换一个SKU)
        $tempList = $reshipObjMdl->getList('*', array('reship_id'=>$reship_id), 0, 1, 'obj_id DESC');
        if(empty($tempList)){
            $error_msg = '售后换货单object层信息不存在';
            return false;
        }
        
        //取上一次换货商品的金额
        $oldObjectInfo = $tempList[0];
        $change_price = $oldObjectInfo['price'];
        
        //根据平台获取对应的信息
        $platformChangeInfo = array();
        if($returninfo['shop_type'] == 'tmall'){
            $platformChangeInfo = app::get('ome')->model('return_product_tmall')->dump(array('return_id'=>$return_id, 'refund_type'=>'change'), '*');
        }else if($returninfo['shop_type'] == 'luban'){
            $platformChangeInfo = app::get('ome')->model('return_product_luban')->dump(array('return_id'=>$return_id, 'refund_type'=>'change'), '*');
        }
        
        //check
        if(empty($platformChangeInfo)){
            $error_msg = '平台换货单信息不存在';
            return false;
        }
        
        //销售物料信息
        $salesMInfo = $salesMLib->getSalesMByBn($shop_id, $platformChangeInfo['exchange_sku']);
        if(empty($salesMInfo)){
            $error_msg = '换货商品对应OMS销售物料不存在';
            return false;
        }
        
        //shop_id
        $shopInfo = [];
        if($shop_id){
            $shopInfo = app::get('ome')->model('shop')->db_dump(array('shop_id'=>$shop_id), 'shop_id,shop_bn,node_id,node_type');
        }
        
        //material_type
        $obj_type = 'goods';
        if($salesMInfo['sales_material_type'] == 5){
            $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'], $platformChangeInfo['exchange_num'], $shop_id);
            $obj_type = 'pko';
        }elseif($salesMInfo['sales_material_type'] == 7){
            $basicMInfos = [];
            
            //福袋组合
            $luckybagParams = [];
            $luckybagParams['sm_id'] = $salesMInfo['sm_id'];
            $luckybagParams['sale_material_nums'] = intval($platformChangeInfo['exchange_num']);
            $luckybagParams['shop_bn'] = $shopInfo['shop_bn'];
            
            $fdResult = $fudaiLib->process($luckybagParams);
            if($fdResult['rsp'] == 'succ'){
                $basicMInfos = $fdResult['data'];
            }else{
                //标记福袋分配错误信息
                $luckybag_error = $fdResult['error_msg'];
            }
            
            $obj_type = 'lkb';
        }else{
            $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
        }
        
        //check
        if(empty($basicMInfos)){
            $error_msg = '换货商品对应OMS基础物料不存在';
            return false;
        }
        
        //根据促销总价格计算每个物料的贡献金额值
        if($salesMInfo['sales_material_type'] == 2){
            $obj_type = 'pkg';
        }
        
        //基础物料列表
        $productItems = array();
        foreach($basicMInfos as $k => $basicMInfo)
        {
            //福袋组合ID
            $luckybag_id = ($basicMInfo['combine_id'] ? $basicMInfo['combine_id'] : 0);
            
            //福袋组合
            if($salesMInfo['sales_material_type'] == 7){
                $productItems[] = array(
                    'bm_id'         => $basicMInfo['bm_id'],
                    'material_name' => $basicMInfo['material_name'],
                    'material_bn'   => $basicMInfo['material_bn'],
                    'type'          => $basicMInfo['type'],
                    'number'        => $basicMInfo['number'],
                    'change_num'    => $basicMInfo['number'],
                    'price'         => $basicMInfo['price'],
                    'rate_price'    => $basicMInfo['price'],
                    'luckybag_id'   => $luckybag_id, //福袋组合ID
                );
            }else{
                $productItems[] = array(
                    'bm_id'         => $basicMInfo['bm_id'],
                    'material_name' => $basicMInfo['material_name'],
                    'material_bn'   => $basicMInfo['material_bn'],
                    'type'          => $basicMInfo['type'],
                    'number'        => $basicMInfo['number'] * $platformChangeInfo['exchange_num'],
                    'change_num'    => $basicMInfo['number'] * $platformChangeInfo['exchange_num'],
                    'price'         => ($obj_type == 'pkg' ? $basicMInfo['rate_price'] : $change_price), //后面更新时会重新计算
                );
            }
        }
        
        //销售物料列表
        $objects = array();
        $objects[] = array(
            'name' => $salesMInfo['sales_material_name'],
            'num' => $platformChangeInfo['exchange_num'],
            'price' => $change_price,
            'product_id' => $salesMInfo['sm_id'],
            'bn' => $salesMInfo['sales_material_bn'],
            'obj_type' => $obj_type ? $obj_type : 'goods',
            'item_type' => $obj_type ? $obj_type : 'goods',
            'items' => $productItems,
        );
        
        $needChangeFreezeItem = [];
        $oldStockFreezeList = [];
        
        //释放库存预占(原换货商品)，并删除掉
        $oldreshipItems = $reshipItemObj->getList('*', array('obj_id'=>$oldObjectInfo['obj_id'], 'return_type'=>'change'));
        if($oldreshipItems){
            foreach ($oldreshipItems as $itemKey => $itemVal)
            {
                $item_id = $itemVal['item_id'];
                $product_id = $itemVal['product_id'];
                $product_nums = intval($itemVal['num']);
                $changebranch_id = intval($itemVal['changebranch_id']);
                $goods_id = $oldObjectInfo['product_id'];
                $bn = $itemVal['bn'];
                
                //释放库存预占
                if($is_create_freeze){
                    $needChangeFreezeItem[] = [
                        'operate_type' => 'delete',
                        'product_id' => $product_id,
                        'goods_id' => $goods_id,
                        'product_nums' => $product_nums,
                        'changebranch_id' => $changebranch_id,
                        'bn' => $bn,
                    ];
                }
                
                //删除item层原换货商品
                $reshipItemObj->delete(array('item_id'=>$item_id));
            }
            // 查询原预占流水
            $oldStockFreezeList = $basicMStockFreezeLib->getStockFreezeByObj($reship_id, '', material_basic_material_stock_freeze::__RESHIP);
            $oldStockFreezeList = array_column($oldStockFreezeList, null, 'bm_id');
        }
        
        //update
        foreach ($objects as $objKey => $changeobj )
        {
            //更新换货object层信息
            $objSave = array(
                'obj_type' => $changeobj['obj_type'],
                'product_id' => $changeobj['product_id'],
                'bn' => $changeobj['bn'],
                'product_name' => $changeobj['name'],
                'price' => $changeobj['price'],
                'num' => $changeobj['num'],
            );
            $reshipObjMdl->update($objSave, array('obj_id'=>$oldObjectInfo['obj_id']));
            
            //pkg组合商品重新计算货品价格
            $arrBn = array();
            if ($changeobj['obj_type'] == 'pkg'){
                $salesMInfo = $salesMLib->getSalesMByBn($shop_id, $changeobj['bn']);
                $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                $salesMLib->calProSaleMPriceByRate($changeobj['price'], $basicMInfos);
                
                foreach($basicMInfos as $v)
                {
                    $arrBn[$v['material_bn']] = $v['rate_price'];
                }
            }elseif($changeobj['obj_type'] == 'lkb'){
                //items
                foreach ($changeobj['items'] as $itemKey => $itemVal)
                {
                    $arrBn[$itemVal['material_bn']] = $itemVal['price'];
                }
            }
            
            //items
            foreach ($changeobj['items'] as $item)
            {
                $material_bn = $item['material_bn'];
                $price = $changeobj['price'];
                
                //获取均摊后的货品价格
                if($changeobj['obj_type'] == 'pkg' || $changeobj['obj_type'] == 'lkb'){
                    $price = $arrBn[$material_bn] ? $arrBn[$material_bn] : $price;
                }
                
                //save
                $itemSave = array(
                    'operate_type' => 'add',
                    'reship_id' => $reship_id,
                    'obj_id' => $oldObjectInfo['obj_id'],
                    'obj_type' => $changeobj['obj_type'],
                    'product_id' => $item['bm_id'],
                    'bn' => $material_bn,
                    'product_name' => $item['material_name'],
                    'price' => $price,
                    'num' => $item['change_num'],
                    'return_type' => 'change',
                );
                
                if($reshipInfo['changebranch_id']){
                    $itemSave['changebranch_id'] = $reshipInfo['changebranch_id'];
                }
                
                $reshipItemObj->save($itemSave);
    
                //如果不需要冻结库存,则跳过
                if(!$is_create_freeze){
                    continue;
                }
                
                $itemSave['goods_id'] = $salesMInfo['sm_id'];
                $needChangeFreezeItem[] = $itemSave;
            }

            // 查库存，是否足够预占，如果不足，则不冻到仓，冻结到商品上。
            $bpList = app::get('ome')->model('branch_product')->getList('product_id,store,store_freeze', ['branch_id'=>$reshipInfo['changebranch_id'],'product_id|in'=>array_column($changeobj['items'], 'bm_id')]);
            if ($bpList) {
                $bpList = array_column($bpList, null, 'product_id');
            } else {
                $bpList = [];
            }
        }
        
        if($needChangeFreezeItem) {
            uasort($needChangeFreezeItem, [kernel::single('console_iostockorder'), 'cmp_productid']);
            $branchBatchList = [];
            foreach($needChangeFreezeItem as $item) {
                if($item['operate_type'] == 'add') {
                    
                    //添加库存预占
                    $freezeData = array();
                    $freezeData['bm_id'] = $item['product_id'];
                    $freezeData['sm_id'] = $item['goods_id'];
                    // $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
                    $freezeData['bill_type'] = material_basic_material_stock_freeze::__RESHIP;
                    $freezeData['obj_id'] = $reship_id;
                    $freezeData['shop_id'] = $shop_id;
                    // $freezeData['branch_id'] = intval($item['changebranch_id']);
                    $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                    $freezeData['num'] = intval($item['num']);
                    $freezeData['log_type'] = $log_type;
                    $freezeData['obj_bn'] = $reship_bn;

                    $bpInfo = $bpList[$item['product_id']];
                    if ($bpInfo && $bpInfo['store']-$bpInfo['store_freeze']>=$item['num']) {
                        // 可用库存足够，保持原逻辑
                        $freezeData['obj_type']  = material_basic_material_stock_freeze::__BRANCH;
                        $freezeData['branch_id'] = intval($item['changebranch_id']);
                    } else {
                        // 可用库存不够，冻结到商品
                        $freezeData['obj_type']  = material_basic_material_stock_freeze::__AFTERSALE;
                        $freezeData['branch_id'] = 0;
                    }


                    $branchBatchList['+'][] = $freezeData;
                } else {
                    
                    //释放库存预占
                    $_tmp = [
                        'bm_id'     =>  $item['product_id'],
                        'sm_id'     =>  $item['goods_id'],
                        'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                        'bill_type' =>  material_basic_material_stock_freeze::__RESHIP,
                        'obj_id'    =>  $reship_id,
                        'branch_id' =>  $item['changebranch_id'],
                        'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                        'num'       =>  $item['product_nums'],
                        'log_type'  =>  $log_type,
                        'bm_bn'     =>  $item['bn'],
                    ];
                    $oldStockFreeze = $oldStockFreezeList[$_tmp['bm_id']];
                    if (!$oldStockFreeze) {
                        $error_msg = '预释放冻结旧换货商品失败:'.$_tmp['bm_bn'];
                        return false;
                    }
                    // 换货单审核换出商品库存不足会冻结到商品上（不冻仓）, 所以obj_type也有可能是1
                    if ($oldStockFreeze['obj_type'] == material_basic_material_stock_freeze::__AFTERSALE) {
                        $_tmp['obj_type'] = material_basic_material_stock_freeze::__AFTERSALE;
                        $_tmp['branch_id']= 0;
                    }
                    $branchBatchList['-'][] = $_tmp;
                }
            }
            
            $error = '';
            $res = $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
            if (!$res) {
                $error_msg = '冻结新换货商品失败:'.$err;
                return false;
            }
            $res = $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
            if (!$res) {
                $error_msg = '释放冻结旧换货商品失败:'.$err;
                return false;
            }
        }
        return true;
    }
    
    /**
     * 检测换出商品明细,如果销售物料里基础物料有变化,更新为最新的基础物料;
     * 
     * @param $reshipinfo
     * @return void
     */
    public function formatReshipRchangeItems($reshipInfo, &$error_msg=null)
    {
        $reshipMdl = app::get('ome')->model('reship');
        $itemsObj = app::get('ome')->model('reship_items');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $operLogMdl = app::get('ome')->model('operation_log');
        
        //reship_id
        $reship_id = $reshipInfo['reship_id'];
        $branch_id = $reshipInfo['branch_id'];
        $changebranch_id = $reshipInfo['changebranch_id'];
        
        //check
        if(empty($reship_id)){
            $error_msg = 'reship_id没有传值';
            return false;
        }
        
        if(in_array($reshipInfo['is_check'], ['5','7'])){
            $error_msg = '换货单状态不允许操作';
            return false;
        }
        
        //reship_objects
        $sql = "SELECT * FROM sdb_ome_reship_objects WHERE reship_id=". $reship_id;
        $tempObjects = $reshipMdl->db->select($sql);
        if(empty($tempObjects)){
            $error_msg = 'reship_objects没有数据';
            return false;
        }
        
        //reship_items
        $sql = "SELECT item_id,obj_id,bn,product_id,obj_type,num,is_del FROM sdb_ome_reship_items WHERE reship_id=".$reship_id." AND return_type='change'";
        $tempItems = $reshipMdl->db->select($sql);
        if(empty($tempItems)){
            $error_msg = '没有换出商品明细';
            return false;
        }
        
        //format items
        $itemList = [];
        foreach ($tempItems as $itemKey => $itemVal)
        {
            $obj_id = $itemVal['obj_id'];
            $bm_id = $itemVal['product_id'];
            
            //check:此状态不能判断使用
            //if($itemVal['is_del'] == 'true'){
            //    continue;
            //}
            
            //过滤掉福袋类型、多选一类型
            if(!in_array($itemVal['obj_type'], array('product','goods','pkg','gift'))){
                continue;
            }
            
            $itemList[$obj_id][$bm_id] = $itemVal;
        }
        
        //format objects
        $changeObjects = [];
        $smIds = [];
        foreach ($tempObjects as $objKey => $objVal)
        {
            $obj_id = $objVal['obj_id'];
            $sm_id = $objVal['product_id'];
            
            //check
            if(!isset($itemList[$obj_id])){
                continue;
            }
            
            //过滤掉福袋类型、多选一类型
            if(!in_array($objVal['obj_type'], array('product','goods','pkg','gift'))){
                continue;
            }
            
            $smIds[$sm_id] = $sm_id;
            
            $objVal['items'] = $itemList[$obj_id];
            
            $changeObjects[$obj_id] = $objVal;
        }
        
        //check
        if(empty($smIds)){
            $error_msg = '没有可查询的销售物料ID';
            return false;
        }
        
        //获取销售物料及关联的基础物料列表
        $tempItems = $salesBasicMaterialObj->getList('*', array('sm_id'=>$smIds));
        if(empty($tempItems)){
            $error_msg = '没有销售物料关联的基础物料';
            return false;
        }
        
        //format material
        $saleMaterialList = [];
        $bmIds = [];
        foreach ($tempItems as $tempKey => $tempVal)
        {
            $sm_id = $tempVal['sm_id'];
            $bm_id = $tempVal['bm_id'];
            
            $bmIds[$bm_id] = $bm_id;
            
            $saleMaterialList[$sm_id][$bm_id] = $tempVal;
        }
        
        //diff
        $diffList = [
            'add' => [],
            'delete' => [],
        ];
        
        //对比换出商品明细：
        //1、销售物料上已经删除的,是需要删除换出明细;
        //2、销售物料上新增加的,则需要添加换出明细;
        foreach ($changeObjects as $obj_id => $objVal)
        {
            $sm_id = $objVal['product_id'];
            $obj_num = $objVal['num'];
            $obj_type = $objVal['obj_type'];
            $obj_price = $objVal['price'];
            
            //sales_basic_material
            if(!isset($saleMaterialList[$sm_id])){
                continue;
            }
            
            //items
            foreach ($objVal['items'] as $itemKey => $itemVal)
            {
                $bm_id = $itemVal['product_id'];
                
                //换出明细存在于基础物料列表中,则跳过
                if(isset($saleMaterialList[$sm_id][$bm_id])){
                    continue;
                }
                
                //需要删除的换出明细
                $diffList['delete'][$obj_id][$bm_id] = $itemVal;
            }
            
            //sales_basic_material
            foreach ($saleMaterialList[$sm_id] as $bmKey => $bmVal)
            {
                $bm_id = $bmVal['bm_id'];
                
                //换出明细存在于基础物料列表中,则跳过
                if(isset($objVal['items'][$bm_id])){
                    continue;
                }
                
                //info
                $saleMaterialInfo = $saleMaterialList[$sm_id][$bm_id];
                $saleMaterialInfo['obj_id'] = $obj_id;
                $saleMaterialInfo['obj_num'] = $obj_num;
                $saleMaterialInfo['obj_type'] = $obj_type;
                $saleMaterialInfo['obj_price'] = $obj_price;
                
                //需要新添加的换出明细
                $diffList['add'][$obj_id][$bm_id] = $saleMaterialInfo;
            }
        }
        
        //check
        if(empty($diffList['add']) && empty($diffList['delete'])){
            $error_msg = '换出明细没有差异';
            return false;
        }
        
        //delete
        if($diffList['delete']){
            $delMaterialBns = [];
            foreach ($diffList['delete'] as $delKey => $delVal)
            {
                foreach ($delVal as $itemKey => $itemVal)
                {
                    $item_id = $itemVal['item_id'];
                    $material_bn = $itemVal['bn'];
                    
                    //删除不存在的换出明细
                    $delete_sql = "DELETE FROM sdb_ome_reship_items WHERE reship_id=". $reship_id ." AND item_id=". $item_id;
                    $reshipMdl->db->exec($delete_sql);
                    
                    $delMaterialBns[$material_bn] = $material_bn;
                }
            }
            
            //logs
            if($delMaterialBns){
                $log_msg = '销售物料包含基础物料有变化,删除基础物料：'. implode('、', $delMaterialBns);
                $operLogMdl->write_log('reship@ome', $reship_id, $log_msg);
            }
        }
        
        //add
        if($diffList['add']){
            $materialList = $basicMaterialObj->getList('bm_id,material_bn,material_name,type', array('bm_id'=>$bmIds));
            $materialList = array_column($materialList, null, 'bm_id');
            
            //insert
            $objIds = [];
            $addMaterialBns = [];
            foreach ($diffList['add'] as $addKey => $addVal)
            {
                foreach ($addVal as $itemKey => $itemVal)
                {
                    $obj_id = $itemVal['obj_id'];
                    $bm_id = $itemVal['bm_id'];
                    $obj_num = $itemVal['obj_num'];
                    $obj_type = $itemVal['obj_type'];
                    
                    //check
                    if(!isset($materialList[$bm_id])){
                        continue;
                    }
                    
                    $item_num = $obj_num * $itemVal['number'];
                    $material_bn = $materialList[$bm_id]['material_bn'];
                    
                    //data
                    $addItem = [
                        'return_type' => 'change',
                        'reship_id' => $reship_id,
                        'obj_id' => $obj_id,
                        'product_id' => $materialList[$bm_id]['bm_id'],
                        'bn' => $material_bn,
                        'product_name' => $materialList[$bm_id]['material_name'],
                        'num' => $item_num,
                        'quantity' => $item_num,
                        'price' => 0,
                        'amount' => 0,
                        'branch_id' => $branch_id,
                        'changebranch_id' => $changebranch_id,
                        'op_id' => 0,
                        'obj_type' => $obj_type,
                    ];
                    $itemsObj->insert($addItem);
                    
                    //material_bn
                    $addMaterialBns[$material_bn] = $material_bn;
                    
                    //obj_id
                    $objIds[$obj_id] = $obj_id;
                }
            }
            
            //重新计算均摊价格
            $sql = "SELECT * FROM sdb_ome_reship_objects WHERE reship_id=". $reship_id ." AND obj_id IN(". implode(',', $objIds) .")";
            $reshipObjects = $reshipMdl->db->select($sql);
            foreach ((array)$reshipObjects as $objKey => $objVal)
            {
                $obj_id = $objVal['obj_id'];
                $sm_id = $objVal['product_id'];
                $obj_price = $objVal['price'];
                
                //check
                if(empty($obj_price) || $obj_price <= 0){
                    continue;
                }
                
                //reship_items
                $sql = "SELECT item_id,obj_id,bn,product_id,obj_type,num FROM sdb_ome_reship_items WHERE reship_id=".$reship_id." AND obj_id=". $obj_id ." AND return_type='change'";
                $reshipItems = $reshipMdl->db->select($sql);
                if(empty($reshipItems)){
                    continue;
                }
                
                $item_count = count($reshipItems);
                $less_money = $obj_price;
                
                //items
                $line_i = 0;
                foreach ($reshipItems as $itemKey => $itemInfo)
                {
                    $line_i++;
                    
                    $item_id = $itemInfo['item_id'];
                    $bm_id = $itemInfo['product_id'];
                    $num = $itemInfo['num'];
                    
                    //check
                    if(!isset($saleMaterialList[$sm_id][$bm_id])){
                        continue;
                    }
                    
                    $bmInfo = $saleMaterialList[$sm_id][$bm_id];
                    
                    //rate
                    $rate = $bmInfo['rate'];
                    
                    //price
                    if($line_i == $item_count){
                        $rate_price = $less_money;
                    }else{
                        $tmp_rate = $rate / 100;
                        $rate_price = bcmul($obj_price, $tmp_rate, 2);
                        
                        //[兼容]数量大于1时,防止除不尽
                        if($num > 1){
                            $avg_price = bcdiv($rate_price, $num, 2);
                            
                            $rate_price = bcmul($avg_price, $num, 2);
                        }
                        
                        $less_money = bcsub($less_money, $rate_price, 2);
                    }
                    
                    //avg price：按数量平分价格
                    $avg_price = $rate_price / $num;
                    $avg_price = bcmul($avg_price, 1, 2); //保留两位小数
                    
                    //update price：更新基础物料price价格；
                    $update_sql = "UPDATE sdb_ome_reship_items SET price=". $avg_price ." WHERE reship_id=". $reship_id ." AND item_id=". $item_id;
                    $reshipMdl->db->exec($update_sql);
                }
            }
            
            //logs
            if($addMaterialBns){
                $log_msg = '销售物料包含基础物料有变化,新添加基础物料：'. implode('、', $addMaterialBns);
                $operLogMdl->write_log('reship@ome', $reship_id, $log_msg);
            }
        }
        
        return true;
    }
    
    /**
     * 通过退货单退货商品明细获取对应的已退款金额
     * 
     * @param $reshipInfo
     * @return void
     */
    public function getReshipByRefund($reshipInfo, &$error_msg=null)
    {
        $reshipItemMdl = app::get('ome')->model('reship_items');
        $refundApplyMdl = app::get('ome')->model('refund_apply');
        
        $order_id = $reshipInfo['order_id'];
        $reship_id = $reshipInfo['reship_id'];
        
        //退货明细
        $reshipItems = $reshipItemMdl->getList('item_id,product_id,bn,num,oid,is_del', array('reship_id'=>$reship_id, 'return_type'=>'return', 'is_del'=>'false'));
        if(empty($reshipItems)){
            $error_msg = '没有相关退货单商品明细';
            return false;
        }
        
        //bn
        $productBns = array_column($reshipItems, 'bn');
        
        //获取退款申请单列表
        $applyList = $refundApplyMdl->getList('apply_id,refund_apply_bn,money,refunded,memo,status,refund_refer,bn', array('order_id'=>$order_id));
        if(empty($applyList)){
            $error_msg = '没有相关退款申请单';
            return false;
        }
        
        //refunded
        $sum_refunded = 0;
        foreach ($applyList as $applyKey => $applyVal)
        {
            //check
            if($applyVal['status'] != '4'){
                continue;
            }
            
            if(!in_array($applyVal['bn'], $productBns)){
                continue;
            }
            
            //退运费(关键字：退运费、运费1.0元)
            if(strpos($applyVal['memo'], '运费')!== false){
                $sum_refunded += $applyVal['refunded'];
            }
        }
        
        if($sum_refunded <= 0){
            $error_msg = '没有符合条件的退款金额';
            return false;
        }
        
        return $sum_refunded;
    }

    /**
     * 发送退货单取消成功通知
     * @param array $reship 退货单信息
     * @param string $memo 备注
     */
    public function sendReshipCancelSuccessNotify($reship, $memo = '')
    {
        try {
            // 获取仓库信息
            $branchObj = app::get('ome')->model('branch');
            $branchInfo = $branchObj->dump(['branch_id' => $reship['branch_id'], 'check_permission' => 'false'], 'name');
            $branchName = $branchInfo ? $branchInfo['name'] : '未知仓库';

            // 发送通知
            kernel::single('monitor_event_notify')->addNotify('reship_cancel_success', [
                'reship_bn' => $reship['reship_bn'],
                'branch_name' => $branchName,
                'cancel_time' => date('Y-m-d H:i:s'),
                'memo' => $memo ?: '无',
            ]);
        } catch (Exception $e) {
            // 静默处理异常，不影响主流程
        }
    }

    /**
     * 退回物流单号重复检测与标记（flag_type位操作）
     * @param int $reshipId
     * @param string $logiNo
     * @return bool
     */
    public function markDuplicateReturnLogiNo($reshipId, $logiNo)
    {
        if (!$reshipId) {
            return false;
        }

        $reshipObj = app::get('ome')->model('reship');
        $reship = $reshipObj->dump(['reship_id' => $reshipId], 'reship_id,flag_type,order_id');
        if (!$reship) {
            return false;
        }

        $flagType = intval($reship['flag_type']);
        $bit = ome_reship_const::__RETURN_LOGI_DUP;

        // 单号为空，直接清标
        if (empty($logiNo)) {
            $newFlag = $flagType & (~$bit);
            if ($newFlag !== $flagType) {
                $reshipObj->update(['flag_type' => $newFlag], ['reship_id' => $reshipId]);
            }
            return true;
        }

        // 检查条件：排除当前退货单、排除已取消的退货单、排除同一订单的其他退货单
        $exists = $reshipObj->count([
            'return_logi_no'    => $logiNo,
            'reship_id|noequal' => $reshipId,
            'is_check|noequal'  => '5',
            'order_id|noequal'  => $reship['order_id'], // 排除同一订单的其他退货单（同一订单重复不需要打标）
        ]);

        $newFlag = $exists > 0 ? ($flagType | $bit) : ($flagType & (~$bit));
        if ($newFlag !== $flagType) {
            $reshipObj->update(['flag_type' => $newFlag], ['reship_id' => $reshipId]);
        }

        return true;
    }
}
