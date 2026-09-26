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

class archive_finder_orders{
    var $detail_basic = '基本信息';
    var $detail_goods = '订单明细';
    var $detail_pmt = '优惠方案';
    
    var $detail_bill = '收退款记录';
    var $detail_delivery = '退发货记录';
    var $detail_history = '订单操作记录';
    var $detail_abnormal = '订单异常备注';
  
    var $detail_refund_apply = '退款申请记录';
 

    var $addon_cols = "custom_mark,mark_text";

    var $column_custom_add='买家备注';
    var $column_custom_add_width = "100";
    function column_custom_add($row){
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
        $mark_text = $row[$this->col_prefix.'mark_text'];
        $mark_text = kernel::single('ome_func')->format_memo($mark_text);
        foreach ((array)$mark_text as $k=>$v){
            $html .= $v['op_content'].' '.$v['op_time'].' by '.$v['op_name']."<br />";
        }
        $html = strip_tags(htmlspecialchars($html));
        return "<div onmouseover='bindFinderColTip(event)' rel='{$html}'>".strip_tags(htmlspecialchars($mark_text[$k]['op_content']))."<div>";
    }

    function detail_basic($order_id){

        $render = app::get('archive')->render();
        $oOrders = app::get('archive')->model('orders');
        $order_detail = $oOrders->dump(array('order_id'=>$order_id),'*');
        $order_detail['is_encrypt'] = kernel::single('ome_security_router',$order_detail['shop_type'])->show_encrypt($order_detail, 'order');
        if($_POST) {
            if ($_POST['is_flag']) {
                //开票提交业务处理
                $this->submit_invoice($_POST);
            }
        }
        $oRefund = app::get('ome')->model('refund_apply');
        
        $render->pagedata['shop_name'] = ome_shop_type::shop_name($order_detail['shop_type']);
        $order_detail['mark_text'] = kernel::single('ome_func')->format_memo($order_detail['mark_text']);
        $order_detail['custom_mark'] = kernel::single('ome_func')->format_memo($order_detail['custom_mark']);
        $render->pagedata['total_amount'] = floatval($order_detail['total_amount']);
        $render->pagedata['payed'] = floatval($order_detail['payed']);
        $oMembers = app::get('ome')->model('members');
        $member_id = $order_detail['member_id'];
        $member = $oMembers->dump($member_id);
        $member['is_encrypt'] = kernel::single('ome_security_router',$order_detail['shop_type'])->show_encrypt($member, 'member');
        
        $render->pagedata['member'] = $member;
        if($order_detail['shipping']['is_cod'] == 'true'){
            $orderExtendObj = app::get('ome')->model('order_extend');
            $extendInfo = $orderExtendObj->dump($order_id);
            $order_detail['receivable'] = $extendInfo['receivable'];
        }
        $render->pagedata['invoice_app_install'] = false;
        if(app::get('invoice')->is_installed()){
            $this->submit_invoice_show($order_detail);
            $render->pagedata['invoice_app_install'] = true;
        }
        
        $render->pagedata['order'] = $order_detail;
        $render->pagedata['is_show_button'] = app::get('ome')->getConf('archive_order_detail_show_button');
        return $render->fetch('order/detail_basic.html');
    }
    
    private function submit_invoice_show(&$order_detail){
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
    }
    
    //开票提交业务处理
    private function submit_invoice($post_data){
        $oOrders = app::get('archive')->model('orders');
        $oOperation_log = app::get('ome')->model('operation_log');
        //更新订单is_tax字段 并记下log
        $update_arr = array('order_id'=>$_POST['order_id'],"is_tax"=>$_POST['is_tax']);
        if($_POST['is_tax'] == 'true'){
            $order_is_tax_part = "要开票";
            $invoiceMdl = app::get('ome')->model('order_invoice');
            $oldInvoice = $invoiceMdl->db_dump(array('order_id'=>$_POST['order_id']));
            if(($_POST['tax_no']!=$oldInvoice['tax_no'])||($_POST['tax_title']!=$oldInvoice[0]['tax_company'])){
                $order_is_tax_part .= '，录入及变更发票号或抬头';
            }
            $upInvoice = array('order_id'=>$_POST['order_id']);
            if(isset($_POST['tax_title'])){
                $upInvoice['tax_title'] =  $_POST['tax_title'];
            }
            if(isset($_POST['tax_no'])){
                $upInvoice['tax_no'] = $_POST['tax_no'];
            }
            if(isset($_POST['invoice_mode'])) {
                $upInvoice['invoice_kind'] = $_POST['invoice_mode'];
            }
            if($oldInvoice) {
                $invoiceMdl->update($upInvoice, array('id'=>$oldInvoice['id']));
            } else {
                $upInvoice['create_time'] = time();
                $invoiceMdl->insert($upInvoice);
            }
        }else{
            $order_is_tax_part = "不要开票";
        }
        $oOrders->save($update_arr);
        $rs_is_tax = $order_is_tax_log = "订单更新为".$order_is_tax_part;
        if($rs_is_tax){
            $oOperation_log->write_log('order_modify@ome',$_POST['order_id'],$order_is_tax_log);
            $arr_create_invoice = array(
                'order_id'=>$_POST['order_id'],
                'is_tax' => $_POST['is_tax'],
                'source_status' => 'TRADE_FINISHED'
            );
            
            // 发票来源
            $invoice_source = 'b2c';
            
            // 注册service，获取发票来源
            foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
            {
                if(method_exists($object, 'get_invoice_source')){
                    // 获取项目上定制的发票来源
                    $custom_invoice_source = $object->get_invoice_source();
                    if($custom_invoice_source){
                        $invoice_source = $custom_invoice_source;
                    }
                }
            }
            
            // 发票来源
            $arr_create_invoice['invoice_source'] = $invoice_source;
            
            kernel::single('invoice_order_front_router', $invoice_source)->operateTax($arr_create_invoice);
        }
    }

    function detail_goods($order_id){
        $render = app::get('archive')->render();
        $archive_ordObj = kernel::single('archive_interface_orders');
        $item_list = $archive_ordObj->getItemList($order_id,true);
    
        //销售价权限验证
        $showSalePrice = true;
        if (!kernel::single('desktop_user')->has_permission('sale_price')) {
            $showSalePrice = false;
        }
    
        $configlist = array();
        if ($servicelist = kernel::servicelist('ome.service.order.products'))
        foreach ($servicelist as $object => $instance){
            if (method_exists($instance, 'view_list')){
                $list = $instance->view_list();
                $configlist = array_merge($configlist, is_array($list) ? $list : array());
            }
        }
        
        $render->pagedata['show_sale_price'] = $showSalePrice;
        $render->pagedata['configlist'] = $configlist;
    
        $render->pagedata['item_list'] = $item_list;
        
        return $render->fetch('order/detail_goods.html');
    }
    
    function detail_pmt($order_id){
        $render = app::get('archive')->render();
        $oOrder_pmt = app::get('ome')->model('order_pmt');
        $ordersObj = app::get('archive')->model('orders');
        
        //订单信息
        $orderInfo = $ordersObj->dump(array('order_id'=>$order_id), 'order_bn,shop_type');
        $render->pagedata['orderInfo'] = $orderInfo;
        
        //优惠券信息
        $pmts = $oOrder_pmt->getList('*',array('order_id'=>$order_id));
        $render->pagedata['pmts'] = $pmts;
        if(in_array($orderInfo['shop_type'], ['taobao','360buy'])) {
            $couponOrder = app::get('ome')->model('order_coupon')->getList('type,type_name as pmt_describe,total_amount as pmt_amount, oid,material_bn', array('order_id'=>$order_id));
            $title = ['oid'=>'子单号','material_bn'=>'物料编号'];
            $pmts = [];
            foreach($couponOrder as $v) {
                $index = $v['oid'].$v['material_bn'];
                $pmtIndex = $v['pmt_describe'] . ($v['type'] ? '('.$v['type'].')' : '');
                $pmts[$index]['oid'] = $v['oid'];
                $pmts[$index]['material_bn'] = $v['material_bn'];
                $pmts[$index][$pmtIndex] = $v['pmt_amount'];
                $title[$pmtIndex] = $pmtIndex;
            }
            $render->pagedata['title'] = $title;
            $render->pagedata['pmts'] = $pmts;
            return $render->fetch('order/tmjd/detail_pmt.html');
        }
        return $render->fetch('order/detail_pmt.html');
    }
    
    function detail_delivery($order_id){
        $render = app::get('archive')->render();
        $oDelivery =kernel::single('archive_interface_delivery');
        $delivery_detail = $oDelivery->get_delivery($order_id);

        $status_text = array ('succ' => '已发货','failed' => '发货失败','cancel' => '已取消','progress' => '等待配货','timeout' => '超时','ready' => '等待配货','stop' => '暂停','back' => '打回','return_back'=>'追回');
        foreach ($delivery_detail as &$delivery ) {
           
            $delivery['status_text']  = $status_text[$delivery['status']];
        }
       
        $render->pagedata['delivery_detail'] = $delivery_detail;
        $oReship = app::get('ome')->model('reship');
        $reship = $oReship->getList('t_begin,reship_id,reship_bn,return_logi_no,ship_name,delivery',array('order_id'=>$order_id));

        $arReshipMdl = app::get('archive')->model('reship');

        $arreship = $arReshipMdl->getList('t_begin,reship_id,reship_bn,return_logi_no,ship_name,delivery',array('order_id'=>$order_id));
        if($arreship){
            foreach($arreship as $v){
                $reship[] = $v;
            }
        }
        
        $render->pagedata['reship'] = $reship;

        return $render->fetch('order/detail_delivery.html');
    }

    function detail_abnormal($order_id){
        $render = app::get('archive')->render();
        $oAbnormal = app::get('ome')->model('abnormal');
        $abnormal = $oAbnormal->getList("*",array("order_id"=>$order_id),0,-1,'abnormal_id desc');
        if($abnormal){
            $oAbnormal_type = app::get('ome')->model('abnormal_type');
            $abnormal_type = $oAbnormal_type->getList("*");
            $abnormal[0]['abnormal_memo'] = unserialize($abnormal[0]['abnormal_memo']);
            $render->pagedata['abnormal'] = $abnormal[0];
            $render->pagedata['abnormal_type'] = $abnormal_type;
            $render->pagedata['order_id'] = $order_id;
        }else{
            $render->pagedata['set_abnormal'] = false;
        }
        return $render->fetch('order/detail_abnormal.html');
    }
    
    
 
    function detail_bill()
    {
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

   

}

?>