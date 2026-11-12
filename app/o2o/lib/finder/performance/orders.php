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

class o2o_finder_performance_orders {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols = 'print_status,confirm,dt_begin,status,process_status,tax_no,ship_status,op_id,group_id,mark_text,auto_status,custom_mark,mark_type,tax_company,createtime,paytime,sync,pay_status,is_cod,source,order_type';
    var $overtime    = 0;
    var $detail_basic = '基本信息';
    var $detail_goods = '订单明细';
    var $detail_pmt = '优惠方案';
    var $detail_bill = '收退款记录';
    var $detail_refund_apply = '退款申请记录';
    var $detail_delivery = '退发货记录';
    var $detail_mark = '订单备注';
    var $detail_abnormal = '订单异常备注';
    var $detail_history = '订单操作记录';
    var $detail_custom_mark = '订单附言';
    var $detail_shipment = '发货日志';

    function __construct()
    {
        //履约超时时间设置(分钟)
        $minute    = app::get('o2o')->getConf('o2o.delivery.dly_overtime');
        $this->overtime    = intval($minute);
    }

    /**
     * row_style
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function row_style($row){
        $style = '';
        return $style;
    }

    //[新增]是否超时
    var $column_overtime        = '是否超时';
    var $column_overtime_width  = '60';
    var $column_overtime_order  = '299';
    function column_overtime($row)
    {
        //已设置超时时间&&订单已拆分完&&未发货
        if($this->overtime && $row[$this->col_prefix .'process_status'] == 'splited' && $row[$this->col_prefix .'ship_status'] == '0')
        {
            //履约超时时间
            $second       = $this->overtime * 60;
            $diff_time    = time() - $row[$this->col_prefix .'dispatch_time'];
            $html         = '<div style="width:18px;padding:2px;height:16px;background-color:red;float:left;">';
            $html        .= '<span alt="发货超时" title="发货超时" style="color:#eeeeee;">&nbsp;超&nbsp;</span>';
            $html        .= '</div>';
            
            return ($diff_time > $second ? $html : '');
        }
        
        return '';
    }

    var $column_status='处理状态';
    var $column_status_width = "120";
    function column_status($row){
        $orderExtendObj = app::get('ome')->model("order_extend");
        $process_status = $orderExtendObj->getList('store_process_status',array('order_id'=>$row['order_id']));
        switch($process_status[0]['store_process_status']){
            case '1':
                return '已分派未接单';
                break;
            case '2':
                return '已拒绝';
                break;
            case '3':
                return '已接单';
                break;
            case '4':
                return '已发货未核销';
                break;
            case '5':
                return '已核销';
                break;
        }
    }

    function detail_basic($order_id){
        $render = app::get('ome')->render();
        $oOrders = app::get('ome')->model('orders');
        $oOperation_log = app::get('ome')->model('operation_log');

        $order_detail = $oOrders->dump($order_id,"*",array("order_items"=>array("*")));
        $oRefund = app::get('ome')->model('refund_apply');
        $refunddata = $oRefund->getList('*',array('order_id'=>$order_id),0,-1);
        $amount = 0;
        foreach ($refunddata as $row){
            if ($row['status'] != '3' && $row['status'] != '4'){
                $render->pagedata['isrefund'] = 'false';//如果退款申请没有处理完成
            }
        }
        if ($render->pagedata['isrefund'] == ''){
            if ($order_detail['pay_status'] == '5'){
                $render->pagedata['isrefund'] = 'false';//订单已全额退货
            }
        }
        $render->pagedata['is_c2cshop'] = in_array($order_detail['shop_type'],ome_shop_type::shop_list()) ?true:false;
        $render->pagedata['shop_name'] = ome_shop_type::shop_name($order_detail['shop_type']);
        $order_detail['mark_text'] = kernel::single('ome_func')->format_memo($order_detail['mark_text']);
        $order_detail['custom_mark'] = kernel::single('ome_func')->format_memo($order_detail['custom_mark']);
        $render->pagedata['total_amount'] = floatval($order_detail['total_amount']);
        $render->pagedata['payed'] = floatval($order_detail['payed']);
        $oMembers = app::get('ome')->model('members');
        $member_id = $order_detail['member_id'];
        $render->pagedata['member'] = $oMembers->dump($member_id);
        $render->pagedata['url'] = kernel::base_url()."/app/".$render->app->app_id;


        //订单代销人会员信息
        $oSellagent = app::get('ome')->model('order_selling_agent');
        $sellagent_detail = $oSellagent->dump(array('order_id'=>$order_id));
        if (!empty($sellagent_detail['member_info']['uname'])){
            $render->pagedata['sellagent'] = $sellagent_detail;
        }
        //发货人信息
        $order_consigner = false;
        if ($order_detail['consigner']){
            foreach ($order_detail['consigner'] as $shipper){
                if (!empty($shipper)){
                    $order_consigner = true;
                    break;
                }
            }
        }
        if ($order_consigner == false){
            //读取店铺发货人信息
            $oShop = app::get('ome')->model('shop');
            $shop_detail = $oShop->dump(array('shop_id'=>$order_detail['shop_id']));
            $order_detail['consigner'] = array(
                'name' => $shop_detail['default_sender'],
                'mobile' => $shop_detail['mobile'],
                'tel' => $shop_detail['tel'],
                'zip' => $shop_detail['zip'],
                'email' => $shop_detail['email'],
                'area' => $shop_detail['area'],
                'addr' => $shop_detail['addr'],
            );
        }
        $sh_base_url = kernel::base_url(1);
        $render->pagedata['base_url'] = $sh_base_url;


     	$is_edit_view = 'true';//
        if ($order_add_service = kernel::service('service.order.'.$order_detail['shop_type'])){
            if (method_exists($order_add_service, 'is_edit_view')){
                $order_add_service->is_edit_view($order_detail, $is_edit_view);
            }
        }

        if($order_detail['shipping']['is_cod'] == 'true'){
            $orderExtendObj = app::get('ome')->model('order_extend');
            $extendInfo = $orderExtendObj->dump($order_id);
            $order_detail['receivable'] = $extendInfo['receivable'];
        }

        $render->pagedata['is_edit_view'] = $is_edit_view;
        
        //发票相关 获取是否有订单相关的发票信息 有的话取最新一条发票信息
        $rs_invoice_info = kernel::single('invoice_common')->getInvoiceInfoByOrderId($order_detail['order_id']);
        if($rs_invoice_info){
            //有过订单的发票信息
            $order_detail['has_invoice'] = true;
            $order_detail['invoice_status_text'] = kernel::single('invoice_common')->getIsStatusText($rs_invoice_info[0]['is_status']);
            $order_detail['invoice_mode_text'] = kernel::single('invoice_common')->getModeText($rs_invoice_info[0]['mode']);
        }else{
            //没有过订单的发票信息
            $order_detail['has_invoice'] = false;
        }
        //没有过发票信息的 或者 有过发票信息的&&最新一条发票记录为已作废状态的 可以去选择纸质/电子生成新的此订单的发票信息
        $order_detail['add_invoice'] = false;
        if( !$order_detail['has_invoice'] || ($order_detail['has_invoice'] && intval($rs_invoice_info[0]['is_status']) == 2) ){
            $order_detail['add_invoice'] = true;
        }
        
        $render->pagedata['order'] = $order_detail;
        if(in_array($_GET['act'],array('confirm','abnormal'))){
            $render->pagedata['operate'] = true;
            $render->pagedata['act_'.$_GET['act']] = true;
        }
        if(($_GET['act'] == 'dispatch' && $_GET['flt'] == 'buffer') || ($_GET['ctl'] == 'admin_order' && ($_GET['act'] == 'active' || $_GET['act'] == 'index'))){
            $render->pagedata['operate'] = true;
            $render->pagedata['act_confirm'] = true;
        }

        #复审订单 OR 跨境申报订单 OR 部分拆分&&部分发货&&全额退款订单_禁止操作按钮
        if($order_detail['process_status'] == 'is_retrial' || $order_detail['process_status'] == 'is_declare' || ($order_detail['process_status'] == 'splitting' && $order_detail['ship_status'] == '2' && $order_detail['pay_status'] == '5'))
        {
            $render->pagedata['operate'] = false;
        }
        
        return $render->fetch('admin/order/detail_basic.html');
    }

    function detail_goods($order_id){
        $render = app::get('ome')->render();
        $oOrder = app::get('ome')->model('orders');

        $item_list = $oOrder->getItemList($order_id,true);
        $item_list = ome_order_func::add_getItemList_colum($item_list);
        ome_order_func::order_sdf_extend($item_list);
        $orders = $oOrder->getRow(array('order_id'=>$order_id),'shop_type,order_source');
        $is_consign = false;
        #淘宝代销订单增加代销价
        if($orders['shop_type'] == 'taobao' && $orders['order_source'] == 'tbdx' ){
            kernel::single('ome_service_c2c_taobao_order')->order_sdf_extend($item_list);
            $is_consign = true;
        }

        $configlist = array();
        if ($servicelist = kernel::servicelist('ome.service.order.products'))
        foreach ($servicelist as $object => $instance){
            if (method_exists($instance, 'view_list')){
                $list = $instance->view_list();
                $configlist = array_merge($configlist, is_array($list) ? $list : array());
            }
        }

        $render->pagedata['is_consign'] = ($is_consign > 0)?true:false;
        $render->pagedata['configlist'] = $configlist;
        $render->pagedata['item_list'] = $item_list;
        $render->pagedata['object_alias'] = $oOrder->getOrderObjectAlias($order_id);
        return $render->fetch('admin/order/detail_goods.html');
    }

    function detail_pmt($order_id){
        $render = app::get('ome')->render();
        $oOrder_pmt = app::get('ome')->model('order_pmt');

        $pmts = $oOrder_pmt->getList('pmt_amount,pmt_describe',array('order_id'=>$order_id));

        $render->pagedata['pmts'] = $pmts;
        return $render->fetch('admin/order/detail_pmt.html');
    }

    function detail_bill($order_id){
        $render = app::get('ome')->render();
        $oPayments = app::get('ome')->model('payments');
        $oRefunds = app::get('ome')->model('refunds');

        $payments = $oPayments->getList('payment_id,payment_bn,t_begin,download_time,money,paymethod',array('order_id'=>$order_id));
        $refunds = $oRefunds->getList('refund_bn,t_ready,download_time,money,paymethod,payment',array('order_id'=>$order_id));
        
        $paymentCfgModel = app::get('ome')->model('payment_cfg');
        foreach ($refunds as $key=>$refund) {
            if ($refund['paymethod']) {
                $paymentCfg = $paymentCfgModel->getList('custom_name',array('id'=>$refund['payment']),0,1);
                $refunds[$key]['paymethod'] = $paymentCfg[0]['custom_name'] ? $paymentCfg[0]['custom_name'] : '';
            }
        }

		foreach($payments as $k=>$v){
			$payments[$k]['t_begin'] = date('Y-m-d H:i:s',$v['t_begin']);
			if($v['download_time']) $payments[$k]['download_time'] = date('Y-m-d H:i:s',$v['download_time']);
		}

        $render->pagedata['payments'] = $payments;
        $render->pagedata['refunds'] = $refunds;

        return $render->fetch('admin/order/detail_bill.html');
    }

    function detail_refund_apply($order_id){
        $render = app::get('ome')->render();
        $oRefund_apply = app::get('ome')->model('refund_apply');

        $refund_apply = $oRefund_apply->getList('create_time,status,money,refund_apply_bn,refunded',array('order_id'=>$order_id));
        if($refund_apply){
            foreach($refund_apply as $k=>$v){
                $refund_apply[$k]['status_text'] = ome_refund_func::refund_apply_status_name($v['status']);
            }
        }

        $render->pagedata['refund_apply'] = $refund_apply;

        return $render->fetch('admin/order/detail_refund_apply.html');
    }

    function detail_delivery($order_id){
        $render = app::get('ome')->render();
        $oDelivery = app::get('ome')->model('delivery');
        $oReship = app::get('ome')->model('reship');
        $oWms_delivery = app::get('wms')->model('delivery');
        $obj_order = app::get('ome')->model('orders');
        $wms_delivery = $oWms_delivery->getDeliveryByOrder($order_id);
        $oBranch = app::get('ome')->model('branch');
        $delivery = $oDelivery->getDeliveryByOrder('branch_id,create_time,delivery_id,delivery_bn,logi_id,logi_no,logi_name,ship_name,delivery,branch_id,stock_status,deliv_status,expre_status,status,weight',$order_id);
        $reship = $oReship->getList('t_begin,reship_id,reship_bn,logi_no,ship_name,delivery',array('order_id'=>$order_id));
        $wms_id = kernel::single('wms_branch')->getBranchByselfwms();
        $order_info = $obj_order->dump($order_id,'order_bn');
        #检测是否开启华强宝物流
        $is_hqepay_on =  app::get('ome')->getConf('ome.delivery.hqepay');
        if($is_hqepay_on == 'false'){
            $is_hqepay_on = false;
        }else{
            $is_hqepay_on = true;
        }
        foreach($delivery as $k=>$v){
            //判断是否第三方
            $branch_list = $oBranch->getList('branch_id', array('wms_id'=>$wms_id,'branch_id'=>$v['branch_id']), 0, -1);
           if ($branch_list) {
               $delivery[$k]['selfwms'] = 1;
           }
			$delivery[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
			
		}
		$render->pagedata['order_bn'] = $order_info['order_bn'];
		$render->pagedata['is_hqepay_on'] = $is_hqepay_on;
        $render->pagedata['delivery'] = $delivery;
        $render->pagedata['wms_delivery'] = $wms_delivery;
        $render->pagedata['reship'] = $reship;

        return $render->fetch('admin/order/detail_delivery.html');
    }

    function detail_mark($order_id){
        $render = app::get('ome')->render();
        $oOrders = app::get('ome')->model('orders');

        if($_POST){
            $order_id = $_POST['order']['order_id'];
            //取出原备注信息
            $oldmemo = $oOrders->dump(array('order_id'=>$order_id), 'mark_text');
            $oldmemo= unserialize($oldmemo['mark_text']);
            $op_name = kernel::single('desktop_user')->get_name();
            if ($oldmemo)
            foreach($oldmemo as $k=>$v){
                $memo[] = $v;
            }
            $newmemo =  htmlspecialchars($_POST['order']['mark_text']);
            $newmemo = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i:s',time()), 'op_content'=>$newmemo);
            $memo[] = $newmemo;
            $_POST['order']['mark_text'] = serialize($memo);
            $plainData = $_POST['order'];
            $oOrders->save($plainData);
            //写操作日志
            $memo = "订单备注修改";

            //订单留言 API
            foreach(kernel::servicelist('service.order') as $object=>$instance){
                if(method_exists($instance, 'update_memo')){
                    $instance->update_memo($order_id, $newmemo);
                }
            }

            $oOperation_log = app::get('ome')->model('operation_log');
            $oOperation_log->write_log('order_modify@ome',$order_id,$memo);
        }

        $order_detail = $oOrders->dump($order_id);
        $render->pagedata['base_dir'] = kernel::base_url();
        $order_detail['mark_text'] = unserialize($order_detail['mark_text']);
        if ($order_detail['mark_text'])
        foreach ($order_detail['mark_text'] as $k=>$v){
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $order_detail['mark_text'][$k]['op_time'] = $v['op_time'];
            }
        }
        $order_detail['custom_mark'] = unserialize($order_detail['custom_mark']);
        if ($order_detail['custom_mark'])
        foreach ($order_detail['custom_mark'] as $k=>$v){
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $order_detail['custom_mark'][$k]['op_time'] = $v['op_time'];
            }
        }
        $order_detail['mark_type_arr'] = ome_order_func::order_mark_type();
        $render->pagedata['order']  = $order_detail;

        return $render->fetch('admin/order/detail_mark.html');
    }

    /*买家留言*/
    function detail_custom_mark($order_id){
        $render = app::get('ome')->render();
        $oOrders = app::get('ome')->model('orders');

        if($_POST){
            $order_id = $_POST['order']['order_id'];
            //取出原留言信息
            $oldmemo = $oOrders->dump(array('order_id'=>$order_id), 'custom_mark');
            $oldmemo= unserialize($oldmemo['custom_mark']);
            $op_name = kernel::single('desktop_user')->get_name();
            if ($oldmemo)
            foreach($oldmemo as $k=>$v){
                $memo[] = $v;
            }
            $newmemo =  htmlspecialchars($_POST['order']['custom_mark']);
            $newmemo = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i:s',time()), 'op_content'=>$newmemo);
            $memo[] = $newmemo;
            $_POST['order']['custom_mark'] = serialize($memo);
            $plainData = $_POST['order'];
            $oOrders->save($plainData);
            //写操作日志
            $memo = "买家留言修改";

            //买家留言 API
            foreach(kernel::servicelist('service.order') as $object=>$instance){
                if(method_exists($instance, 'add_custom_mark')){
                    $instance->add_custom_mark($order_id, $newmemo);
                }
            }

            $oOperation_log = app::get('ome')->model('operation_log');
            $oOperation_log->write_log('order_modify@ome',$order_id,$memo);
        }

        $order_detail = $oOrders->dump($order_id);
        $render->pagedata['base_dir'] = kernel::base_url();
        $order_detail['custom_mark'] = unserialize($order_detail['custom_mark']);
        if ($order_detail['custom_mark'])
        foreach ($order_detail['custom_mark'] as $k=>$v){
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $order_detail['custom_mark'][$k]['op_time'] = $v['op_time'];
            }
        }
        $render->pagedata['order']  = $order_detail;

        return $render->fetch('admin/order/detail_custom_mark.html');
    }

    function detail_abnormal($order_id){
        $render = app::get('ome')->render();
        $oAbnormal = app::get('ome')->model('abnormal');
        $oOrder = app::get('ome')->model('orders');
        $ordersdetail = $oOrder->dump(array('order_id'=>$order_id));
        //组织分派所需的参数
        $render->pagedata['op_id'] = $ordersdetail['op_id'];
        $render->pagedata['group_id'] = $ordersdetail['group_id'];
        $render->pagedata['dt_begin'] = strtotime(date('Y-m-d',time()));
        $render->pagedata['dispatch_time'] = strtotime(date('Y-m-d',time()));
        $render->pagedata['ordersdetail'] = $ordersdetail;
        //增加一个标识
        $render->pagedata['is_flag'] = 'true';
        if($ordersdetail['shop_type'] == 'vjia'){
            $outstorageObj = app::get('ome')->model('order_outstorage');
            $outstorage = $outstorageObj->dump(array('order_id'=>$order_id),'order_id');
            if(is_array($outstorage) && !empty($outstorage)) {
                $render->pagedata['outstorage'] = 'fail';
            }
        }

        if($_POST){
            $abnormal_data = $_POST['abnormal'];
            if($abnormal_data['is_done']=='vjia') {
                $outstorageObj->delete(array('order_id'=>$order_id));
                $abnormal_data['is_done'] = 'true';
            }
            $oOrder->set_abnormal($abnormal_data);
        }

        $abnormal = $oAbnormal->getList("*",array("order_id"=>$order_id),0,-1,'abnormal_id desc');
        if($abnormal){
            $oAbnormal_type = app::get('ome')->model('abnormal_type');

            $abnormal_type = $oAbnormal_type->getList("*");

            $abnormal[0]['abnormal_memo'] = unserialize($abnormal[0]['abnormal_memo']);
            $render->pagedata['abnormal'] = $abnormal[0];
            $render->pagedata['abnormal_type'] = $abnormal_type;
            $render->pagedata['order_id'] = $order_id;
            $render->pagedata['set_abnormal'] = true;
        }else{
            $render->pagedata['set_abnormal'] = false;
        }

        return $render->fetch('admin/order/detail_abnormal.html');
    }

    function detail_history($order_id){

        $render = app::get('ome')->render();
        $orderObj = app::get('ome')->model('orders');
        $logObj = app::get('ome')->model('operation_log');
        $deliveryObj = app::get('ome')->model('delivery');
        $ooObj = app::get('ome')->model('operations_order');

        /* 本订单日志 */
        $history = $logObj->read_log(array('obj_id'=>$order_id,'obj_type'=>'orders@ome'),0,-1);
		foreach($history as $k=>$v){
            $data = $ooObj->getList('operation_id',array('log_id'=>$v['log_id']));
            if(!empty($data)){
                $history[$k]['flag'] ='true';
            }else{
                $history[$k]['flag'] ='false';
            }
			$history[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
		}

        /* 发货单日志 */
        $delivery_ids = $deliveryObj->getDeliverIdByOrderId($order_id);
        if ($delivery_ids) {
            $deliverylog = $logObj->read_log(array('obj_id'=>$delivery_ids,'obj_type'=>'delivery@ome'), 0, -1);
        }
        
        # [拆单]多个发货单 格式化分开显示
        $dly_log_list   = array();
        foreach((array) $deliverylog as $k=>$v)
        {
            $deliverylog[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
            
            $obj_id     = $v['obj_id'];
            $dly_log_list[$obj_id]['obj_name']  = $v['obj_name'];
            $dly_log_list[$obj_id]['list'][]    = $deliverylog[$k];
        }
        $render->pagedata['dly_log_list'] = $dly_log_list;

        /* “失败”、“取消”、“打回”发货单日志 */
        $history_ids = $deliveryObj->getHistoryIdByOrderId($order_id);
        $deliveryHistorylog = array();
        foreach($history_ids as $v){
            $delivery = $deliveryObj->dump($v,'delivery_id,delivery_bn,status');
            $deliveryHistorylog[$delivery['delivery_bn']] = $logObj->read_log(array('obj_id'=>$v,'obj_type'=>'delivery@ome'), 0, -1);
            
            
            foreach($deliveryHistorylog[$delivery['delivery_bn']] as $k=>$v){
                $deliveryHistorylog[$delivery['delivery_bn']][$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
                $deliveryHistorylog[$delivery['delivery_bn']][$k]['status'] =$delivery['status'];
            }
        }
        
        /* 同批处理的订单日志 */
        $order_ids = $deliveryObj->getOrderIdByDeliveryId($delivery_ids);
        $orderLogs = array();
        foreach($order_ids as $v){
            if($v != $order_id){
                $order = $orderObj->dump($v,'order_id,order_bn');
                $orderLogs[$order['order_bn']] = $logObj->read_log(array('obj_id'=>$v,'obj_type'=>'orders@ome'), 0, -1);
                foreach($orderLogs[$order['order_bn']] as $k=>$v){
                    if($v)
                        $orderLogs[$order['order_bn']][$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
                }
            }
        }

        $render->pagedata['history'] = $history;
        $render->pagedata['deliverylog'] = $deliverylog;
        $render->pagedata['deliveryHistorylog'] = $deliveryHistorylog;
        $render->pagedata['orderLogs'] = $orderLogs;
        $render->pagedata['order_id'] = $order_id;


        return $render->fetch('admin/order/detail_history.html');
    }

    function detail_shipment($order_id) {
        $render = app::get('ome')->render();
        $orderObj = app::get('ome')->model('orders');
        $shipmentObj = app::get('ome')->model('shipment_log');
        $userObj = app::get('desktop')->model('users');

        $order = $orderObj->dump($order_id);
        if ($order) {

            $orderBn = $order['order_bn'];
            $shipmentLogs = $shipmentObj->getList('*', array('orderBn' => $orderBn));
            foreach ($shipmentLogs as $k=>$log) {
                if ($shipmentLogs[$k]['receiveTime']) {
                    $shipmentLogs[$k]['receiveTime'] = date('Y-m-d H:i:s', $shipmentLogs[$k]['receiveTime']);
                } else {
                    $shipmentLogs[$k]['receiveTime'] = '&nbsp;';
                }
                if ($shipmentLogs[$k]['updateTime']) {
                    $shipmentLogs[$k]['updateTime'] = date('Y-m-d H:i:s', $shipmentLogs[$k]['updateTime']);
                } else {
                    $shipmentLogs[$k]['updateTime'] = '&nbsp;';
                }
                switch ($shipmentLogs[$k]['status']) {
                    case 'succ':
                        $shipmentLogs[$k]['status'] = '<font color="green">成功</font>';
                        break;
                    case 'fail':
                        $shipmentLogs[$k]['status'] = '<font color="red">失败</font>';
                        break;
                    default:
                        $shipmentLogs[$k]['status'] = '<font color="#000">运行中……</font>';
                        break;
                }

                if($log['ownerId'] == 16777215){
                    $shipmentLogs[$k]['ownerId'] = 'system';
                }else{
                    $user = $userObj->dump($log['ownerId'],'name');
                    $shipmentLogs[$k]['ownerId'] = $user['name'];
                }
            }
            $render->pagedata['order'] = $order;
            $render->pagedata['shipmentLogs'] = $shipmentLogs;
        }

        return $render->fetch('admin/order/detail_shipment.html');
    }

    var $column_tax_no='是否录入发票号';
    var $column_tax_no_width = "100";
    function column_tax_no($row){
    	if($row[$this->col_prefix.'tax_no']){
    		return '是';
    	}else{
    		return '否';
    	}
    }

    var $column_custom_add='买家备注';
    var $column_custom_add_width = "100";
    function column_custom_add($row){
        $order_id = $row['order_id'];
        //$oObj = app::get('ome')->model('orders');
        //$custom_mark = $oObj->dump($order_id,'custom_mark');
        $custom_mark = $row[$this->col_prefix.'custom_mark'];
        $custom_mark = kernel::single('ome_func')->format_memo($custom_mark);
        foreach ((array)$custom_mark as $k=>$v){
        	$html .= $v['op_content'].' '.$v['op_time'].' by '.$v['op_name']."<br />";
        }
        $html = strip_tags(htmlspecialchars($html));
        return "<div onmouseover='bindFinderColTip(event)' rel='{$html}'>".strip_tags(htmlspecialchars($custom_mark[$k]['op_content']))."<div>";
    }

    var $column_customer_add='客服备注';
    var $column_customer_add_width = "100";
    function column_customer_add($row){
        $order_id = $row['order_id'];
        //$oObj = app::get('ome')->model('orders');
        //$mark_text = $oObj->dump($order_id,'mark_text');
        $mark_text = $row[$this->col_prefix.'mark_text'];
        $mark_text = kernel::single('ome_func')->format_memo($mark_text);
        foreach ((array)$mark_text as $k=>$v){
            $html .= $v['op_content'].' '.$v['op_time'].' by '.$v['op_name']."<br />";
        }
        $html = strip_tags(htmlspecialchars($html));
        return "<div onmouseover='bindFinderColTip(event)' rel='{$html}'>".strip_tags(htmlspecialchars($mark_text[$k]['op_content']))."<div>";
    }

    //新增
    var $column_fail_status = '注意事项';
    var $column_fail_status_width = "130";

    function column_fail_status($row) {

        //$order_id = $row['order_id'];
        //$oObj = app::get('ome')->model('orders');
        //$row = $oObj->dump($order_id,'*');
        foreach ($row as $key => $val) {

            $key = str_replace('_0_', '', $key);
            $row[$key] = $val;
        }

        $auto_status = $row['auto_status'];

        $msgs = kernel::single('omeauto_auto_combine')->fetchAlertMsg($auto_status, $row);

        if (empty($msgs)) {

            return '';
        } else {

            $ret = '';
            foreach ($msgs as $msg) {

                $ret .= $this->getViewPanel($msg['color'], $msg['msg'], $msg['flag']);
            }

            return $ret;
        }
    }

    var $column_deff_time = '下单距今';
    var $column_deff_time_width = "100";
    var $column_deff_time_order_field = "createtime";

    function column_deff_time($row) {
        if ($row['_0_is_cod'] == 'true') {
            $difftime = kernel::single('ome_func')->toTimeDiff(time(), $row['_0_createtime']);
        } else {
            if ($row['_0_paytime'] > 0) {
                $difftime = kernel::single('ome_func')->toTimeDiff(time(), $row['_0_paytime']);
            } else {
                //return '<span style="color:red;font-weight:700;">未支付</span>';
                return '';
            }
        }
        return $difftime['d'] . '天' . $difftime['h'] . '小时' . $difftime['m'] . '分';
    }

    /**
     * 获取ViewPanel
     * @param mixed $color color
     * @param mixed $msg msg
     * @param mixed $title title
     * @return mixed 返回结果
     */
    public function getViewPanel($color, $msg, $title) {

        return sprintf("<div onmouseover='bindFinderColTip(event)' rel='%s' style='width:18px;padding:2px;height:16px;background-color:%s;float:left;color:#ffffff;'>&nbsp;%s&nbsp;</div>", $msg, $color, $title);
    }

    var $column_tax_company='发票抬头';
    var $column_tax_company_width = "150";
    function column_tax_company($row){
        if(empty($row[$this->col_prefix.'tax_company'])){
            return '-';
        }
        return $row[$this->col_prefix.'tax_company'];
    }
}