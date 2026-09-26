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


class invoice_finder_order{
    public $addon_cols = 'id,type_id,order_id,mode,is_status,sync,ship_area,ship_addr,shop_id,dateline,is_print,print_num,batch_number,delivery_id,shop_type,changesdf,is_make_invoice,invoice_no';//调用字段
    private $orderCreateTimeList = null;
    
    //操作
    var $column_edit  = '操作';
    var $column_edit_order = 5;
    var $column_edit_width = 300;
    function column_edit($row, $list){
        $id        = $row['id'];
        $order_id  = $row[$this->col_prefix . 'order_id'];
        $mode      = intval($row[$this->col_prefix.'mode']);
        $type_id      = intval($row[$this->col_prefix.'type_id']);
        $is_status = intval($row[$this->col_prefix.'is_status']);
        $sync      = intval($row[$this->col_prefix.'sync']);
        $shop_id   = $row[$this->col_prefix.'shop_id'];
        $finder_id = $_GET['_finder']['finder_id'];
        $finder_vid = $_GET['finder_vid'];
        $is_make_invoice   = $row[$this->col_prefix.'is_make_invoice'];

        $is_billing = false;

        // 开票触发动作
        if( $is_make_invoice == '1'){
            $is_billing = true;
        }

        $cancelText = $mode == 1 && $is_status == 1 ? '冲红' : '作废';

        //作废
        $cancelBtn = sprintf('<a href="index.php?app=invoice&ctl=admin_order&act=batchCancel&p[0]=%s&finder_id=%s" target="dialog::{width:690,height:250,title:\'%s\'}">%s</a>', $id, $finder_id, $cancelText, $cancelText);
        if (!kernel::single('desktop_user')->has_permission('rush_red_invoice')) {
            $cancelBtn = '';
        }
        
        // 默认为空，不允许冲红按钮
        $cancelBtn = '';
        
        //开票
        $billingBtn = sprintf('<a href="javascript:if (confirm(\'是否确认进行开票操作？开票信息可在列表查看列展开处进行查看。\')){W.page(\'index.php?app=invoice&ctl=admin_order&act=doBilling&id=%s&order_id=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">开票</a>', $id, $order_id, $finder_id);
        if (!kernel::single('desktop_user')->has_permission('make_invoice')) {
            $billingBtn = '';
        }
        //纸质专票不能作废和开票
        if ($mode == '0') {
            $cancelBtn = '';
            $billingBtn = '';
        }
        
        //编辑
        $editBtn = '<a href="index.php?app=invoice&ctl=admin_order&act=edit&id='.$id.'&finder_id='.$finder_id.'&finder_vid='.$finder_vid.'" target="_blank">编辑</a>';

        //新建发票信息
        $addSameBtn = '<a href="index.php?app=invoice&ctl=admin_order&act=addNewSame&id='.$id.'&finder_id='.$finder_id.'&finder_vid='.$finder_vid.'" >新建发票信息</a>';

        //改票
        $changeBtn = '<a href="index.php?app=invoice&ctl=admin_order&act=addChangeTicket&id='.$id.'&finder_id='.$finder_id.'&finder_vid='.$finder_vid.'" target="_blank">改票</a>';
        //纸票不展示改票按钮
//        if ($mode == '0') {
//            $changeBtn = '';
//        }
        //改票确认
        $checkChangeTicket = '<a href="index.php?app=invoice&ctl=admin_order&act=addChangeTicket&id='.$id.'&finder_id='.$finder_id.'&type=checkChangeTicket" target="dialog::{width:900,height:630,title:\'改票确认\'}">改票确认</a>';
        
        // 预览
        $electronic = $this->_getElectronicItem($id.'-'.($is_status=='1'?'1':'2'), $list);
        if ($electronic['url']) {
            $electronic['invoice_url'] = $electronic['url'];
            
            // url
            $urlScheme  = parse_url($electronic['url']);

            parse_str($urlScheme['fragment'],$fragment);

            if ($fragment['expire'] && strtotime($fragment['expire']) < time()) {
                unset($electronic['url']);
            }
        }
        
        $previewBtn =$electronic['file_id'] ? '<a href="index.php?app=invoice&ctl=admin_order&act=show_preview_pdf&p[0]='.$electronic['file_id'].'&finder_id='.$finder_id.'" target="_blank">预览</a>' : '';
        
        // pdf_url
        $pdfUrlButton = '';
        if ($electronic['pdf_url']) {
            $pdfUrlButton = '<a href="'.$electronic['pdf_url'].'" target="_blank">PDF下载</a>';
        }
        
        $xmlUrlButton = '';
        if ($electronic['xml_url']) {
            $xmlUrlButton = '<a href="'.$electronic['xml_url'].'" target="_blank">XML下载</a>';
        }
        
        $ofdUrlButton = '';
        if ($electronic['ofd_url']) {
            $ofdUrlButton = '<a href="'.$electronic['ofd_url'].'" target="_blank">OFD下载</a>';
        }

        $sendSmsBtn = '';
        if($this->canSendInvoiceSms($row)){
            $sendSmsBtn = sprintf('<a href="javascript:if (confirm(\'是否确认发送电子发票短信？\')){W.page(\'index.php?app=invoice&ctl=admin_order&act=sendEinvoiceSms\', {method: \'post\', data: \'id=%s&finder_id=%s\'}, this);}void(0);" target="">发送短信</a>', $id, $finder_id);
        }

        $receiptRepushBtn = '';
        if($this->canReceiptRepush($row)){
            $receiptRepushBtn = sprintf(
                '<a href="index.php?app=invoice&ctl=admin_order&act=receiptRepush&id=%s&finder_id=%s" target="dialog::{width:520,height:260,title:\'收票补推\'}">收票补推</a>',
                $id,
                $finder_id
            );
        }
        
        // shop
        $shop = app::get('ome')->model('shop')->getShopById($shop_id);

        // 上传
        $uploadBtn = '';
        if (in_array($shop['node_type'],array('360buy','taobao','wesite','d1mwestore','luban','ecos.ecshopx')) && in_array($sync,array(3,6)) && $mode == 1 && $electronic['upload_tmall_status'] == '1' ) {
            $uploadBtn = '<a href="index.php?app=invoice&ctl=admin_order&act=batchUpload&id='.$id.'&finder_id='.$finder_id.'" target="dialog::{width:550,height:250,title:\'上传电子发票\'}">上传</a>';
        }

        // 兼容纸票回流
        if (in_array($shop['node_type'], array('wesite', 'd1mwestore','ecos.ecshopx')) && in_array($sync, array(3, 6)) && $electronic['upload_tmall_status'] == '1') {
            $uploadBtn = '<a href="index.php?app=invoice&ctl=admin_order&act=batchUpload&id=' . $id . '&finder_id=' . $finder_id . '" target="dialog::{width:550,height:250,title:\'上传电子发票\'}">上传</a>';
        }
        
        //显示逻辑
        $items = $this->_getInvoiceItems($id,$list);
        $link_arr = array();
        switch ($is_status){
            case 0:                 // 开票状态:未开票
                // 未开票/开票失败/纸质开票允许编辑|作废
                if ($sync == 0 || $sync == 2 || $mode == 0) {
                    $link_arr[] = $editBtn;
                    $link_arr[] = $cancelBtn;

                    // 审核后可开票
                    if ($is_billing || $mode == 0) $link_arr[] = $billingBtn;
                } 

                break;
            case 1:                         // 开票状态:已经开票
                // 开蓝成功，开红失败，纸票可冲红
                if ( in_array($sync, [3, 5, 8, 9]) || $mode == 0) $link_arr[] = $cancelBtn;
    
                if ($sync == 3) $link_arr[] = $changeBtn;

                // 电子蓝票预览
                $link_arr[] = $previewBtn;

                // 电子蓝票上传
                $link_arr[] = $uploadBtn;
                
                // 下载按钮
                if($pdfUrlButton){
                    $link_arr[] = $pdfUrlButton;
                }
                
                if($xmlUrlButton){
                    $link_arr[] = $xmlUrlButton;
                }
                
                if($ofdUrlButton){
                    $link_arr[] = $ofdUrlButton;
                }

                if($sendSmsBtn){
                    $link_arr[] = $sendSmsBtn;
                }

                if($receiptRepushBtn){
                    $link_arr[] = $receiptRepushBtn;
                }
                
                break;
            case 2:                 // 开票状态:作废/冲红

                // 新建相似
                if (isset($items['addSameStatus']) && $items['addSameStatus'] == 'true' && $items['checkAddSame'] == 'true') {
                    $link_arr[] = $addSameBtn;
                }

//                if ($changeStatus == '1' && kernel::single('invoice_check')->checkCreate($order_id)) $link_arr[] = $checkChangeTicket;
                // 冲红预览
                $link_arr[] = $previewBtn;

                // 开票上传
                $link_arr[] = $uploadBtn;

                break;
        }
        
        return implode(" | ", array_filter($link_arr));
        
    }

    private function canSendInvoiceSms($row)
    {
        if(!$this->isSendInvoiceSmsEnabled($row)){
            return false;
        }

        return $this->isSuccessfulBlueInvoice($row);
    }

    private function canReceiptRepush($row)
    {
        if(!$this->isReceiptRepushEnabled($row)){
            return false;
        }

        return $this->isSuccessfulBlueInvoice($row);
    }

    private function isSuccessfulBlueInvoice($row)
    {
        $mode = intval($row[$this->col_prefix.'mode']);
        $is_status = intval($row[$this->col_prefix.'is_status']);
        $sync = intval($row[$this->col_prefix.'sync']);
        $invoiceNo = $row[$this->col_prefix.'invoice_no'];

        return $mode == 1 && $is_status == 1 && $sync == 3 && $invoiceNo;
    }

    private function isSendInvoiceSmsEnabled($row)
    {
        foreach(kernel::servicelist('ome.service.invoice.index.action') as $object)
        {
            if(method_exists($object, 'check_send_invoice_sms')){
                $checkResult = $object->check_send_invoice_sms($row);
                if(isset($checkResult['data']['is_send_invoice_sms']) && $checkResult['data']['is_send_invoice_sms'] == 'true'){
                    return true;
                }
            }
        }

        return false;
    }

    private function isReceiptRepushEnabled($row)
    {
        foreach(kernel::servicelist('ome.service.invoice.index.action') as $object)
        {
            if(!method_exists($object, 'check_invoice_receipt_repush') || !method_exists($object, 'send_invoice_receipt_email')){
                continue;
            }

            $checkResult = $object->check_invoice_receipt_repush($row);
            if(isset($checkResult['data']['is_invoice_receipt_repush']) && $checkResult['data']['is_invoice_receipt_repush'] == 'true'){
                return true;
            }
        }

        return false;
    }
    
    var $column_ship_area = "客户收货地区";
    var $column_ship_area_width = "150";
    function column_ship_area($row){
        $ship_area = $row[$this->col_prefix.'ship_area'];
        $areaArr = explode(':',$ship_area);
        return str_replace('/',' ',$areaArr[1]);
    }

    var $column_order_create_time = '订单创建时间';
    var $column_order_create_time_width = 130;
    var $column_order_create_time_order = 22;
    function column_order_create_time($row, $list)
    {
        if ($this->orderCreateTimeList === null) {
            $this->loadOrderCreateTimeList($list);
        }

        $orderId = (int) $row[$this->col_prefix . 'order_id'];
        if (empty($this->orderCreateTimeList[$orderId])) {
            return '';
        }

        return date('Y-m-d H:i:s', $this->orderCreateTimeList[$orderId]);
    }

    private function loadOrderCreateTimeList($list)
    {
        $this->orderCreateTimeList = array();
        $orderIds = array();
        foreach ($list as $item) {
            $orderId = (int) $item[$this->col_prefix . 'order_id'];
            if ($orderId > 0) {
                $orderIds[$orderId] = $orderId;
            }
        }

        if (!$orderIds) {
            return;
        }

        $orderRows = app::get('ome')->model('orders')->getList(
            'order_id,createtime',
            array('order_id' => $orderIds)
        );
        foreach ($orderRows as $orderRow) {
            $this->orderCreateTimeList[$orderRow['order_id']] = $orderRow['createtime'];
        }

        $archiveOrderIds = array();
        foreach ($orderIds as $orderId) {
            if (!array_key_exists($orderId, $this->orderCreateTimeList)) {
                $archiveOrderIds[$orderId] = $orderId;
            }
        }

        if (!$archiveOrderIds) {
            return;
        }

        $archiveOrderRows = app::get('archive')->model('orders')->getList(
            'order_id,createtime',
            array('order_id' => $archiveOrderIds)
        );
        foreach ($archiveOrderRows as $archiveOrderRow) {
            $this->orderCreateTimeList[$archiveOrderRow['order_id']] = $archiveOrderRow['createtime'];
        }
    }
    
    var $column_is_print = "打印";
    var $column_is_print_width = "50";
    function column_is_print($row){
        $mode = intval($row[$this->col_prefix.'mode']);
        if($mode == 1){
            //电子发票没有此项
            return "-";
        }else{
            //纸质发票返回打印状态
            $is_print = $row[$this->col_prefix.'is_print'];
            $return_value = "否";
            if(intval($is_print) == 1){
                $return_value = "是";
            }
            return $return_value;
        }
    }
    
    var $column_print_num = "打印次数";
    var $column_print_num_width = "80";
    function column_print_num($row){
        $mode = intval($row[$this->col_prefix.'mode']);
        if($mode == 1){
            //电子发票没有此项
            return "-";
        }else{
            //纸质发票返回打印状态
            $print_num = $row[$this->col_prefix.'print_num'];
            return $print_num;
        }
    }
    
    //额外显示字段 提醒项
    var $column_remind = '提醒项';
    var $column_remind_width = '80';
    function column_remind($row){
        $is_status = intval($row[$this->col_prefix.'is_status']);
        if($is_status == "1"){
            //已开票状态
            $mdlOmeRePr = app::get('ome')->model('return_product');
            $order_id = $row[$this->col_prefix . 'order_id'];
            $billing_time = $row[$this->col_prefix . 'dateline']; //开票时间
            $rs_repr = $mdlOmeRePr->dump(array("order_id"=>$order_id,"status"=>4,"last_modified|than"=>$billing_time));
            if(!empty($rs_repr)){
                //有已完成的售后操作
                return "<div style='width:24px;padding:2px;height:16px;background-color:green;float:left;'><span style='color:#eeeeee;' title='有已完成的售后操作' alt='有已完成的售后操作'>售后</span></div>";
            }
        }
    }
    
    //发票详情
    var $detail_invoice    = '发票详情';
    function detail_invoice($id){
        $render = app::get('invoice')->render();
        
        $finder_id = $_GET['_finder']['finder_id'];
        
        //发票信息
        $mdlInOrder = app::get('invoice')->model('order');
        $rs_invoice_order = $mdlInOrder->dump(array("id"=>$id));
        
        $columnsInOrder = $mdlInOrder->schema;
        //显示开票方式
        $arr_mode = $columnsInOrder['columns']['mode']['type'];
        $rs_invoice_order["mode"] = $arr_mode[$rs_invoice_order["mode"]];
        //显示开票状态
        $arr_is_status = $columnsInOrder['columns']['is_status']['type'];
        $rs_invoice_order["is_status"] = $arr_is_status[$rs_invoice_order["is_status"]];
        //地区
        if($rs_invoice_order['ship_area']){
            $areaArr = explode(':', $rs_invoice_order['ship_area']);
            $areaArr = $areaArr[1];
            $rs_invoice_order['ship_area']  = str_replace('/', ' ', $areaArr);
        }
        //操作人
        $rs_operator = kernel::single('invoice_common')->getUserNameByUserID($rs_invoice_order["operator"]);
        $rs_invoice_order["operator"] = $rs_operator["name"];
        //发票内容
        $mdlInContent = app::get('invoice')->model('content');

        // 判断是否加密
        $rs_invoice_order['is_encrypt'] = kernel::single('ome_security_router',$rs_invoice_order['shop_type'])->show_encrypt($rs_invoice_order,'invoice');
        if (strpos($rs_invoice_order['order_bn'],',')) {
            $rs_invoice_order['order_bn'] = implode("<br>",explode(',',$rs_invoice_order['order_bn']));
        }
        $render->pagedata['invoice_order'] = $rs_invoice_order;
        
        //查看电子发票
        $mdlInOrderElIt = app::get('invoice')->model('order_electronic_items');
        $render->pagedata['electronic_items'] = $mdlInOrderElIt->getList("*",array("id"=>$id));
        if(!empty($render->pagedata['electronic_items'])){
            foreach ($render->pagedata['electronic_items'] as $key_el_it=>&$var_el_it){
                if(intval($var_el_it["create_time"]) == 0){
                    unset($render->pagedata['electronic_items'][$key_el_it]);
                    continue;                    
                }
                $var_el_it["create_time"] = date("Y-m-d H:i:s",$var_el_it["create_time"]);
                if(intval($var_el_it["billing_type"]) == 2){
                    $var_el_it["billing_type_text"] = "红票";
                    $type = "red";
                }else{
                    $var_el_it["billing_type_text"] = "蓝票";
                    $type = "blue";
                }
                
                if ($var_el_it['file_id'] > 0) {
                    $var_el_it["preview_url"] = "index.php?app=invoice&ctl=admin_order&act=show_preview_pdf&p[0]=".$var_el_it['file_id']."&finder_id=". $finder_id ."&type=$type";
                }
                
                // file_path
                if($var_el_it['file_path']){
                    $filePathList = json_decode($var_el_it['file_path'], true);
                    
                    // pdf_url
                    if($filePathList['pdf_url']){
                        $var_el_it["pdf_url"] = $filePathList['pdf_url'];
                    }
                    
                    // xml_url
                    if($filePathList['xml_url']){
                        $var_el_it["xml_url"] = $filePathList['xml_url'];
                    }
                    
                    // ofd_url
                    if($filePathList['ofd_url']){
                        $var_el_it["ofd_url"] = $filePathList['ofd_url'];
                    }
                }
                
                $var_el_it["upload_tmall_status_text"]  = $mdlInOrderElIt->_columns()['upload_tmall_status']['type'][$var_el_it["upload_tmall_status"]];
                $var_el_it["invoice_status_text"]  = $mdlInOrderElIt->_columns()['invoice_status']['type'][$var_el_it["invoice_status"]];
                $var_el_it["red_confirm_status_text"]  = $mdlInOrderElIt->_columns()['red_confirm_status']['type'][$var_el_it["red_confirm_status"]];
            }
            unset($var_el_it);
        }
    
        $orderItemsList =  $this->_getInvoiceItems($id,[['id'=>$id]])['items'];
        if ($rs_invoice_order['invoice_type'] == 'merge') {
            $orderItemsList = kernel::single('invoice_order')->showMergeInvoiceItems($orderItemsList);
        }

        if ($orderItemsList) {
            $render->pagedata['order_items'] = $orderItemsList;
        }

        
        return $render->fetch('admin/order_detail.html');
        
    }
    
    //发票操作日志
    var $detail_operate_logs = '发票操作日志';
    function detail_operate_logs($id){
        $render = app::get('invoice')->render();
        $logObj = app::get('ome')->model('operation_log');
        $logData = $logObj->read_log(array('obj_id'=>$id, 'obj_type'=>'order@invoice'), 0, -1, 'log_id desc');
        foreach($logData as $k=>$v){
            $logData[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['datalist'] = $logData;
        return $render->fetch('admin/detail_log.html');
    }
    #批次号(显示完整四段)
    var $column_batch_number = '批次号';
    var $column_batch_number_width = '120';
    var $column_batch_number_order = 28;
    function column_batch_number($row){
     $delivery_id = $row[$this->col_prefix.'delivery_id'];
     $batch_number = $row[$this->col_prefix.'batch_number'];
       if($delivery_id && $batch_number){
          $oDelivery      = app::get('ome')->model('print_queue_items');
          $delivery_arr   = $oDelivery->getList('ident_dly', array('delivery_id' => $delivery_id,
            'ident' => $batch_number), 0, 1);
        
          $batch_number    .= '_'.$delivery_arr[0]['ident_dly'];
          
       }
       return $batch_number;
    }
    

    /**
     * summary
     *
     * @return void
     * @author 
     */
    private function _getElectronicItem($id, $list)
    {
        static $items;

        if (isset($items)) return $items[$id];
        $items = array();

        $invoiceIdArr = array();
        foreach ($list as $value) {
            $invoiceIdArr[] = $value['id'];
        }

        $itemModel = app::get('invoice')->model('order_electronic_items');
        foreach ($itemModel->getList('url,id,billing_type,upload_tmall_status,file_id,file_path',array('id'=>$invoiceIdArr)) as $value) {
            
            // file_path
            if($value['file_path']){
                $fileInfo = json_decode($value['file_path'], true);
                if($fileInfo['pdf_url']){
                    // 原来的url
                    $value['original_url'] = $fileInfo['url'];
                    
                    // PDF发票地址
                    $value['pdf_url'] = $fileInfo['pdf_url'];
                    //$value['url'] = $fileInfo['pdf_url'];
                    
                    // 其它地址
                    $value['xml_url'] = $fileInfo['xml_url'];
                    $value['ofd_url'] = $fileInfo['ofd_url'];
                }
            }
            
            $items[$value['id'].'-'.$value['billing_type']] = $value;
        }

        return $items[$id];
    }

    var $detail_memo = '发票备注';
    function detail_memo($id)
    {
        $render  = app::get('invoice')->render();
        $oOrders = app::get('invoice')->model('order');
        
        if ($_POST) {
            $id = $_POST['id'];
            //取出原留言信息
            $oldmemo = $oOrders->dump(array('id' => $id), 'memo');
            $oldmemo = unserialize($oldmemo['memo']);
            $op_name = kernel::single('desktop_user')->get_name();
            if ($oldmemo) {
                foreach ($oldmemo as $k => $v) {
                    $memo[] = $v;
                }
            }
            $newmemo = htmlspecialchars($_POST['memo']);
            $newmemo = array('op_name' => $op_name, 'op_time' => date('Y-m-d H:i:s', time()), 'op_content' => $newmemo);
            $memo[]  = $newmemo;
            $memo    = serialize($memo);
            $oOrders->update(['memo' => $memo], ['id' => $id]);
            //写操作日志
            $memo           = "增加备注";
            $oOperation_log = app::get('ome')->model('operation_log');
            $oOperation_log->write_log('invoice_edit@invoice', $id, $memo);
        }
        
        $order_detail                 = $oOrders->dump($id);
        $render->pagedata['base_dir'] = kernel::base_url();
        $order_detail['memo']         = unserialize($order_detail['memo']);
        if ($order_detail['memo']) {
            foreach ($order_detail['memo'] as $k => $v) {
                if (!strstr($v['op_time'], "-")) {
                    $v['op_time']                        = date('Y-m-d H:i:s', $v['op_time']);
                    $order_detail['memo'][$k]['op_time'] = $v['op_time'];
                }
            }
        }
        $render->pagedata['order'] = $order_detail;
        
        return $render->fetch('admin/detail_memo.html');
    }
    
    /**
     * 获取开票明细
     * @Author: xueding
     * @Vsersion: 2023/6/5 下午3:10
     * @param $id
     * @param $list
     * @return mixed
     */
    private function _getInvoiceItems($id, $list)
    {
        static $items;
        
        if (isset($items)) return $items[$id];
        $items = array();
        
        $invoiceIdArr = array();
        foreach ($list as $value) {
            $invoiceIdArr[] = $value['id'];
        }
        
        $itemModel = app::get('invoice')->model('order_items');
        $itemList = $itemModel->getList('*',array('id'=>$invoiceIdArr));
        
        //check
        if(empty($itemList)){
            return [];
        }
        
        $of_id = array_column($itemList,'of_id');
        $items = ome_func::filter_by_value($itemList,'id');
        $newData = [];
        
        //check
        if(empty($of_id)){
            return [];
        }
    
        $sql = "SELECT o.id,o.order_bn,oi.of_id FROM sdb_invoice_order AS o LEFT JOIN sdb_invoice_order_items AS oi on oi.id=o.id WHERE oi.of_id in (".implode(',',array_unique($of_id)).") AND o.is_status != '2' ";
        $res = kernel::database()->select($sql);
        $newMain = [];
        foreach ($res as $key => $val) {
            $newMain[$val['of_id']][$val['id']][] = $val;
        }
        
        $sql = "SELECT id FROM sdb_invoice_order_front WHERE id in (".implode(',',array_unique($of_id)).") AND status != 'close' ";
        $front = kernel::database()->select($sql);
        $front = array_column($front,null,'id');

        foreach ($items as $key => $val) {
            $newData[$key]['items'] = $val;
            foreach ($val as $k => $v) {
                if (isset($front[$v['of_id']])) {
                    $newData[$key]['addSameStatus'] = 'true';
                }
                if (!isset($newMain[$v['of_id']]) ) {
                    $newData[$key]['checkAddSame'] = 'true';
                }
            }
        }

        $items = $newData;
        return $items[$id];
    }
}
