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
class ome_finder_orders
{
    static $_syncErrorMsgs = null;
    
    var $detail_basic = '基本信息';
    var $detail_goods = '订单明细';
    var $detail_pmt = '优惠方案';
    var $detail_bill = '收退款记录';
    var $detail_delivery = '发货记录';
    var $detail_mark = '商家备注';
    var $detail_abnormal = '订单异常备注';
    var $detail_history = '订单操作记录';
    var $detail_custom_mark = '客户备注';
    var $detail_shipment = '发货日志';
    var $detail_prodcut_store = '库存明细';
    var $detail_platform_info = '平台建议信息';
    var $detail_freeze = '订单冻结流水';

    function __construct(){
        if(($_GET['ctl'] == 'admin_order'
                && ($_GET['act'] == 'confirm' || $_GET['act'] == 'index' || $_GET['flt'] == 'buffer' || $_GET['flt'] == 'assigned'))
            || $_GET['ctl']=='admin_order_lack'){
            //nothing
        }else{
            unset($this->column_confirm);
        }
        
        //剔除复审操作按扭
        if($_GET['ctl'] == 'admin_order' && $_GET['act'] == 'retrial'){
            //nothing
        }else{
            unset($this->column_abnormal_status);
            unset($this->column_mark_text);
        }
    }

    function detail_basic($order_id){
        $render = app::get('ome')->render();
        $oOrders = app::get('ome')->model('orders');
        $oOperation_log = app::get('ome')->model('operation_log');
        
        // order_id
        $order_id = intval($order_id);
        if (!empty($_POST) && isset($_POST['order']['order_id'])) {
            if($_POST['order']['order_id']){
                $order_id = $_POST['order']['order_id'];
            }else{
                $order_id = 0;
            }
        }
        
        // filter
        $base_filter = array('order_id'=>$order_id);
        
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }
        
        $order_detail = $oOrders->dump($base_filter,"*",array("order_items"=>array("*")));
        if(empty($order_detail)){
            $error_msg = '订单不存在，或者账号无权访问该订单';
            return '<div style="padding:16px;color:#c00;">' . htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        
        if($_POST){
            if($_POST['is_flag']){
                //开票提交业务处理
                $this->submit_invoice($_POST);
            }else{
                $order_id = $_POST['order']['order_id'];

                $memo = "";
                if(isset($_POST['order_action'])){
                    switch($_POST['order_action']){
                        case "cancel" :
                            $memo = "订单被取消";
                            break;
                        case "order_limit_time" :
                            $plainData = $_POST['order'];
                            $plainData['order_limit_time'] = strtotime($plainData['order_limit_time']);
                            $oOrders->save($plainData);

                            $memo = "订单的有效时间被设置为".date("Y-m-d",$plainData['order_limit_time']);
                            $oOperation_log->write_log('order_modify@ome',$order_id,$memo);
                            break;
                        case "order_payed" :
                            $memo = "确认订单付款";
                            $orderinfo = $oOrders->order_detail($order_id);
                            if ($orderinfo['payed'] == $orderinfo['total_amount'])
                            {
                                $plainData['pay_status'] = 1;
                                $oOrders->save($plainData);
                                $oOperation_log->write_log('order_modify@ome',$order_id,$memo);
                            }
                            break;
                        case "order_pause":
                            $memo = "订单暂停";
                            $oOrders->pauseOrder($order_id);
                            break;
                        case "order_renew":
                            $memo = "订单恢复";
                            $oOrders->renewOrder($order_id);
                            break;
                        case  'order_refuse_refund';
                        #检查原始的订单支付状态
                            $order_info = $oOrders->dump($order_id,'pay_status,payed,total_amount,is_cod');
                            $memo = "订单拒绝退款";
                            $plainData['order_id'] =  $order_id;

                            if( $order_info['pay_status'] == '4' ||($order_info['total_amount'] > $order_info['payed'])){
                                $plainData['pay_status'] = 3;#订单原状态是部分退款的，修改为部分付款
                            }else{
                                if($order_info['shipping']['is_cod'] == 'true'){
                                    $plainData['pay_status'] = 0;#货到付款的，修改为未支付
                                }else{
                                    $plainData['pay_status'] = 1;#款到发货，原状态是退款中的，修改为已支付
                                }
                            }
                            $plainData['pause'] = 'false';
                            $oOrders->save($plainData);
                            $oOperation_log->write_log('order_modify@ome',$order_id,$memo);
                        break;
                        case  'recover_delivery';
                           
                            $memo = "订单恢复发货状态为可发货";
                            $plainData = array();
                            $plainData['order_id'] =  $order_id;

                            $plainData['is_delivery'] = 'Y';
                            $oOrders->save($plainData);
                            $oOperation_log->write_log('order_modify@ome',$order_id,$memo);
                            break;
                        default:
                            $memo = "订单内容修改";
                            $oOperation_log->write_log('order_modify@ome',$order_id,$memo);
                            break;
                    }
                }else{
                    $memo = "订单内容修改";
                    $oOperation_log->write_log('order_modify@ome',$order_id,$memo);
                }

            }

            //写操作日志
        }
        
        // 判断是否加密
        $order_detail['is_encrypt'] = kernel::single('ome_security_router',$order_detail['shop_type'])->show_encrypt($order_detail, 'order');
        $invoiceMdl = app::get('ome')->model('order_invoice');
        $invoiceOrder = $invoiceMdl->db_dump(array('order_id'=>$order_id));
        $invoiceOrder && $order_detail = array_merge($order_detail, $invoiceOrder);

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
        $member = $oMembers->dump($order_detail['member_id']);
        // 会员解密
        $member['is_encrypt'] = kernel::single('ome_security_router',$order_detail['shop_type'])->show_encrypt($member, 'member');
        $render->pagedata['member'] = $member;


        $render->pagedata['url'] = kernel::base_url()."/app/".$render->app->app_id;

        if ($order_detail['order_bool_type']){
            $bool_type_text = kernel::single('ome_order_bool_type')->getBoolTypeDescribe($order_detail['order_bool_type'],$order_detail['shop_type']);
            $order_detail['order_bool_type_text'] = $bool_type_text;
        }

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
        
        $is_edit_view = 'true';
        if ($order_add_service = kernel::service('service.order.'.$order_detail['shop_type'])){
            if (method_exists($order_add_service, 'is_edit_view')){
                $order_add_service->is_edit_view($order_detail, $is_edit_view);
            }
        }

        //订单扩展信息
        $orderExtendObj = app::get('ome')->model('order_extend');
        $extendInfo = $orderExtendObj->dump($order_id);

        $order_detail['cert_id'] = $extendInfo['cert_id'];

        //指定快递
        if($extendInfo['assign_express_code']){
            $corpObj = app::get('ome')->model('dly_corp');
            $sql = "SELECT corp_id, name FROM sdb_ome_dly_corp WHERE type='". $extendInfo['assign_express_code'] ."' AND disabled='false' ORDER BY weight DESC, corp_id DESC";
            $corpInfo = $corpObj->db->selectrow($sql);
            $render->pagedata['assign_express_name'] = $corpInfo['name'];
        }

        //货到付款
        if($order_detail['shipping']['is_cod'] == 'true'){
            $orderExtendObj = app::get('ome')->model('order_extend');
            $extendInfo = $orderExtendObj->dump($order_id);
            $order_detail['receivable'] = $extendInfo['receivable'];
        }

        $render->pagedata['is_edit_view'] = $is_edit_view;

        //开票提交显示
        $render->pagedata['invoice_app_install'] = false;
        if(app::get('invoice')->is_installed()){
            $this->submit_invoice_show($order_detail);
            $render->pagedata['invoice_app_install'] = true;
        }
        
        //销售价权限验证
        $showSalePrice = true;
        if (!kernel::single('desktop_user')->has_permission('sale_price')) {
            $showSalePrice = false;
        }
        $render->pagedata['show_sale_price'] = $showSalePrice;
        
        $render->pagedata['order'] = $order_detail;
        if(in_array($_GET['act'],array('confirm','abnormal'))){
            $render->pagedata['operate'] = true;
            $render->pagedata['act_'.$_GET['act']] = true;
        }
        if(($_GET['act'] == 'dispatch' && $_GET['flt'] == 'buffer') || ($_GET['ctl'] == 'admin_order' && ($_GET['act'] == 'active' || $_GET['act'] == 'index'))){
            $render->pagedata['operate'] = true;
            $render->pagedata['act_confirm'] = true;
        }

        //复审订单 OR 跨境申报订单 OR 部分拆分&&部分发货&&全额退款订单_禁止操作按钮
        if($order_detail['process_status'] == 'is_retrial' || $order_detail['process_status'] == 'is_declare' || ($order_detail['process_status'] == 'splitting' && $order_detail['ship_status'] == '2' && $order_detail['pay_status'] == '5'))
        {
            $render->pagedata['operate'] = false;
        }
        
        $support_refund_shop = array('amazon','dangdang','paipai','qqbuy','taobao','tmall','yihaodian');
        $shop_shop_type = ome_shop_type::shopex_shop_type();
        #已支持退款的平台，屏蔽拒绝退款按钮
        $oRefund = app::get('ome')->model('refund_apply');
        if($order_detail['source'] == 'matrix' && (!in_array($order_detail['shop_type'],$shop_shop_type)) && (!in_array($order_detail['shop_type'], $support_refund_shop))){
            #订单状态是退款申请中
            $can_refuse_status = array(4,6,7);#部分退款、退款申请中、退款中
            if(in_array($order_detail['pay_status'] , $can_refuse_status)){
                #所有退款申请单状态，属于已拒绝，才可以做拒绝退款
                $refund_info = $oRefund->getlist('order_id,status',array('order_id'=>$order_id,'status|noequal'=>'3'));
                if(empty($refund_info)){
                    $render->pagedata['can_refuse_refund'] = 'true';
                }
            }
        }

        //订单标记
        $sql = "SELECT a.*, b.label_code, b.label_color FROM sdb_ome_bill_label AS a LEFT JOIN sdb_omeauto_order_labels AS b ON a.label_id=b.label_id ";
        $sql .= " WHERE a.bill_type='order' and a.bill_id='" . $order_id . "'";
        $labelList = $oOrders->db->select($sql);
        $render->pagedata['labelList'] = $labelList;
        $render->pagedata['extends'] = $extendInfo;

        // 检测京东订单是否有微信支付先用后付的单据
        $use_before_payed = false;
        if ($order_detail['shop_type'] == '360buy') {
            $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($order_detail['order_id']);
            $labelCode = array_column($labelCode, 'label_code');
            $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
        }
        $render->pagedata['use_before_payed'] = $use_before_payed;

        // 服务订单
        $serviceObj = app::get('ome')->model('order_service');
        $service_list = $serviceObj->getList('*',array('order_id'=>$order_id));
        $render->pagedata['service_list'] = $service_list;
        $ordLabelObj = app::get('ome')->model('bill_label');

        if($order_detail['is_delivery']=='N'){
            $orderbills = $ordLabelObj->dump(array('label_code'=>array('SOMS_CHANGE_CANCEL','SOMS_JDZD'),'bill_type'=>'order','bill_id'=>$order_id),'bill_id');

            if($orderbills){
                //变成可发货
                $render->pagedata['can_recover_delivery'] = 'true';
            }
        }
        
        // 取消订单权限
        $is_allow_cancel = true;
        if (!kernel::single('desktop_user')->has_permission('ome.action.order.cancel')) {
            $is_allow_cancel = false;
        }
        $render->pagedata['is_allow_cancel'] = $is_allow_cancel;
        
        return $render->fetch('admin/order/detail_basic.html');
    }

    //开票提交显示
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
        $oOrders = app::get('ome')->model('orders');
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
            $order = app::get('ome')->model('orders')->dump($_POST['order_id'], 'shop_id,order_id,pay_status,total_amount,source_status,member_id,createway');
            $memeberMdl = app::get('ome')->model("members");
            $arr_create_invoice = array(
                'order_id'=>$_POST['order_id'],
                'is_tax' => $_POST['is_tax'],
                'source_status' => $order['source_status']
            );
            if(isset($_POST['invoice_mode'])) {
                $arr_create_invoice['invoice_kind'] = $_POST['invoice_mode'] == '1' ? '1' : ($_POST['invoice_mode'] == '0' ? '2' : '3');
            }
            //本地创建的订单使用用户的title和税号
            $memeberInfo = $memeberMdl->db_dump($order['member_id']);
            if ($memeberInfo && $order['createway'] == 'local') {
                $arr_create_invoice['title']    = $memeberInfo['title'];
                $arr_create_invoice['ship_tax'] = $memeberInfo['ship_tax'];
            }
            kernel::single('invoice_order_front_router', 'b2c')->operateTax($arr_create_invoice);
        }
    }


    function detail_goods($order_id){
        $render = app::get('ome')->render();
        $oOrder = app::get('ome')->model('orders');
        
        // order_id
        $order_id = intval($order_id);
        
        // filter
        $base_filter = array('order_id'=>$order_id);
        
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }
        
        // orderInfo
        $orders = $oOrder->dump($base_filter,'order_id,shop_type,order_source,process_status,settlement_amount');
        if(empty($orders)){
            $error_msg = '订单不存在，或者账号无权访问该订单明细';
            return '<div style="padding:16px;color:#c00;">' . htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        
        $item_list = $oOrder->getItemList($order_id,true);
        $item_list = ome_order_func::add_getItemList_colum($item_list);
        ome_order_func::order_sdf_extend($item_list);
        
        $is_consign = false;
        
        //淘宝代销订单增加代销价
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
        
        //是否超级管理员
        $isSuper = kernel::single('desktop_user')->is_super();
        
        //平台订单金额明细
        $couponList = array();
        if($orders['shop_type'] == 'luban' && $isSuper){
            $couponMdl = app::get('ome')->model('order_coupon');
            $couponList = $couponMdl->getList('*', array('order_id'=>$order_id));
            
            if($couponList){
                $oidList = array();
                foreach ($item_list as $obj_type => $objects){
                    foreach ($objects as $obj_id => $items){
                        $oid = $items['oid'];
                        $oidList[$oid]['bn'] = $items['bn'];
                    }
                }
                
                foreach ($couponList as $key => $val){
                    $oid = $val['oid'];
                    
                    $couponList[$key]['material_bn'] = $oidList[$oid]['bn'];
                }
            }
        }

        $psRows = app::get('ome')->model('order_platformsplit')->getList('split_oid, bn', ['order_id'=>$order_id]);
        $split_oid = [];
        foreach ($psRows as $key => $value) {
            $split_oid[$value['split_oid']][] = $value['bn'];
        }
        //销售价权限判断
        $showSalePrice = true;
        if (!kernel::single('desktop_user')->has_permission('sale_price')) {
            $showSalePrice = false;
        }

        if($orders['shop_type'] == 'vop'){
            $obCheckItemsMdl = app::get('ome')->model('order_objects_check_items');
            $check_items     = $obCheckItemsMdl->getList('*', ['order_id'=>$order_id]);
            $check_items     = array_column($check_items, null, 'bn');

            $mdl = app::get('purchase')->model('pick_bill_check_items'); 
            foreach ($check_items as $cik => $civ) {
                $check_items[$cik]['delete'] = 'false';
                if ($mdl->order_label[$civ['order_label']]) {
                    $check_items[$cik]['order_label'] = $mdl->order_label[$civ['order_label']];
                }
            }

            foreach ($item_list as $k => $v) {
                foreach ($v as $kk => $vv) {
                    if ($vv['delete'] == 'true' && $check_items[$vv['bn']]) {
                        $check_items[$vv['bn']]['delete'] = 'true';
                    }
                }
            }
            $render->pagedata['check_items'] = array_values($check_items);
        }

        //check
        $is_host = false;
        $lucky_flag = false;
        $is_hold_time = false;
        $is_presale_status = false;
        foreach ($item_list as $obj_type => $objects){
            foreach ($objects as $obj_id => $objInfo){
                //author_id
                if ($objInfo['author_id']) {
                    $is_host = true;
                }
                
                //presale_status
                if ($objInfo['presale_status'] == 1) {
                    $is_presale_status = true;
                }
                
                //obj_type
                if ($objInfo['obj_type'] == 'lkb') {
                    $lucky_flag = true;
                }
                
                //estimate_con_time
                if ($objInfo['estimate_con_time'] > 1) {
                    $is_hold_time = true;
                }
            }
        }
        
        //获取福袋使用记录
        if($lucky_flag){
            $luckyBagLib = kernel::single('ome_order_luckybag');
            
            //obj_id
            $luckyObjIds = array();
            foreach ($item_list as $obj_type => $objects)
            {
                foreach ($objects as $objKey => $objVal)
                {
                    $obj_id = $objVal['obj_id'];
                    
                    //check
                    if($objVal['obj_type'] != 'lkb'){
                        continue;
                    }
                    
                    $luckyObjIds[$obj_id] = $obj_id;
                }
            }
            
            //福袋使用日志记录
            $luckyList = $luckyBagLib->getOrderLuckyBagList($order_id, $luckyObjIds);
            if(empty($luckyList)){
                //换货生成的新订单--获取福袋使用日志
                $luckyList = $luckyBagLib->getChangeOrderLuckyBagList($order_id, $luckyObjIds);
            }
            
            //format
            foreach ($item_list as $obj_type => $objects)
            {
                foreach ($objects as $obj_id => $objInfo)
                {
                    //check
                    if(empty($objInfo['order_items'])){
                        continue;
                    }
                    
                    //check
                    if($objInfo['obj_type'] != 'lkb'){
                        continue;
                    }
                    
                    // 重新组装ITEM
                    $order_items = [];
                    foreach ($objInfo['order_items'] as $itemKey => $itemVal)
                    {
                        $item_id = $itemVal['item_id'];
                        $luckybag_id = $itemVal['luckybag_id'] ?: 0;
                        
                        //福袋使用日志信息
                        $luckyBagInfo = $luckyList[$item_id];
                        
                        //初始化福袋组合信息
                        if(!isset($order_items[$luckybag_id])){
                            $order_items[$luckybag_id] = $luckyBagInfo;
                            
                            //福袋实付金额
                            $order_items[$luckybag_id]['divide_order_fee'] = $itemVal['divide_order_fee'];
                            
                            //福袋优惠分摊
                            $order_items[$luckybag_id]['part_mjz_discount'] = $itemVal['part_mjz_discount'];

                            //福袋客户实付
                            $order_items[$luckybag_id]['actually_amount'] = $itemVal['actually_amount'];

                            //福袋商家结算
                            $order_items[$luckybag_id]['settlement_amount'] = $itemVal['settlement_amount'];
                        }else{
                            //福袋实付金额
                            $order_items[$luckybag_id]['divide_order_fee'] += $itemVal['divide_order_fee'];
                            
                            //福袋优惠分摊
                            $order_items[$luckybag_id]['part_mjz_discount'] += $itemVal['part_mjz_discount'];

                            //福袋客户实付
                            $order_items[$luckybag_id]['actually_amount'] += $itemVal['actually_amount'];

                            //福袋商家结算
                            $order_items[$luckybag_id]['settlement_amount'] += $itemVal['settlement_amount'];
                        }
                        
                        //基础物料价格贡献比
                        $itemVal['price_rate'] = $luckyBagInfo['price_rate'];
                        
                        //基础物料选中比例
                        $itemVal['real_ratio'] = $luckyBagInfo['real_ratio'];
                        
                        //items
                        $order_items[$luckybag_id]['items'][] = $itemVal;
                    }

                    $item_list[$obj_type][$obj_id]['order_items'] = $order_items;
                }
            }
        }
        
        // 订单明细格式化：把PKG组合放到数组末尾显示；
        if(isset($item_list['pkg'])){
            $pkgItems = $item_list['pkg'];
            unset($item_list['pkg']);
            
            // pkg
            $item_list['pkg'] = $pkgItems;
        }
        
        $render->pagedata['is_host'] = $is_host;
        $render->pagedata['lucky_flag'] = $lucky_flag;
        $render->pagedata['is_hold_time'] = $is_hold_time;
        $render->pagedata['is_presale_status'] = $is_presale_status;
        $render->pagedata['show_sale_price'] = $showSalePrice;
        $render->pagedata['order'] = $orders;
        $render->pagedata['couponList'] = $couponList;
        $render->pagedata['maxHoldTime'] = kernel::single('omeauto_auto_hold')->getMaxHoldTime();
        $render->pagedata['is_consign'] = ($is_consign > 0)?true:false;
        $render->pagedata['configlist'] = $configlist;
        $render->pagedata['item_list'] = $item_list;
        $render->pagedata['shop_type'] = $orders['shop_type'];
        $render->pagedata['split_oid'] = $split_oid;
        $render->pagedata['object_alias'] = $oOrder->getOrderObjectAlias($order_id);
        return $render->fetch('admin/order/detail_goods.html');
    }

    function detail_pmt($order_id){
        $render = app::get('ome')->render();
        $oOrder_pmt = app::get('ome')->model('order_pmt');
        $ordersObj = app::get('ome')->model('orders');
        
        // order_id
        $order_id = intval($order_id);
        
        // filter
        $base_filter = array('order_id'=>$order_id);
        
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }
        
        //订单信息
        $orderInfo = $ordersObj->dump($base_filter, 'order_bn,shop_type,api_version');
        if(empty($orderInfo)){
            $error_msg = '订单不存在，或者账号无权访问该订单优惠信息';
            return '<div style="padding:16px;color:#c00;">' . htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        
        $render->pagedata['orderInfo'] = $orderInfo;

        //优惠券信息
        $pmts = $oOrder_pmt->getList('*',array('order_id'=>$order_id));
        $render->pagedata['pmts'] = $pmts;
        //if(in_array($orderInfo['shop_type'], ['luban','taobao','360buy'])) {
            $couponOrder = app::get('ome')->model('order_coupon')->getList('type,type_name as pmt_describe,total_amount as pmt_amount, oid,material_bn,amount', array('order_id'=>$order_id));
            $title = ['oid'=>'子单号','material_bn'=>'物料编号'];
            $pmts = [];
            foreach($couponOrder as $v) {
                $index = $v['oid'].$v['material_bn'];
                $pmtIndex = $v['pmt_describe'] . ($v['type'] ? '('.$v['type'].')' : '');
                $pmt_amount = in_array($orderInfo['shop_type'], ['luban']) ? $v['amount'] : $v['pmt_amount'];
                $pmts[$index]['oid'] = $v['oid'];
                $pmts[$index]['material_bn'] = $v['material_bn'];
                $pmts[$index][$pmtIndex] = ($pmts[$index][$pmtIndex] ? $pmts[$index][$pmtIndex] . ' | ' : '') . $pmt_amount;
                $title[$pmtIndex] = $pmtIndex;
            }
            if($pmts) {
                $render->pagedata['coupon']['title'] = $title;
                $render->pagedata['coupon']['pmts'] = $pmts;
            }
        //}
        if(in_array($orderInfo['shop_type'], ['360buy']) && $orderInfo['api_version'] < 3) {
            $title = ['oid'=>'子单号','material_bn'=>'物料编号'];
            foreach(app::get('ome')->model('order_coupon')->getList('type,type_name as pmt_describe', array('order_id'=>$order_id)) as $v) {
                $pmtIndex = $v['pmt_describe'] . ($v['type'] ? '('.$v['type'].')' : '');
                $title[$v['type']] = $pmtIndex;
            }
            $couponOrder = app::get('ome')->model('order_objects_coupon')->getList('addon, num as quantity,oid,material_bn', array('order_id'=>$order_id));
            $pmts = []; 
            foreach($couponOrder as $v) {
                $addon = unserialize($v['addon']);
                $tmp = [
                    'oid' => $v['oid'],
                    'material_bn' => $v['material_bn'],
                ];
                if($addon) {
                    $pmts[] = array_merge($tmp, $addon);
                }
            }
            $render->pagedata['coupon']['title'] = $title;
            $render->pagedata['coupon']['pmts'] = $pmts;
        }
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

        $oRefund_apply = app::get('ome')->model('refund_apply');
        
        $refundBoolTypeLib = kernel::single('ome_refund_bool_type');

        $refund_apply = $oRefund_apply->getList('create_time,status,money,refund_apply_bn,refunded,bool_type',array('order_id'=>$order_id));
        if($refund_apply){
            foreach($refund_apply as $k=>$v)
            {
                $bool_type_str = '';
                
                //单据种类
                if($v['bool_type']){
                    $isPriceProtectRefund = $refundBoolTypeLib->isPriceProtectRefund($v['bool_type']);
                    if($isPriceProtectRefund){
                        $bool_type_str = '<span style="color:red;">【价保】</span>';
                    }
                }
                
                $refund_apply[$k]['status_text'] = $bool_type_str . ome_refund_func::refund_apply_status_name($v['status']);
            }
        }

        $render->pagedata['refund_apply'] = $refund_apply;


        return $render->fetch('admin/order/detail_bill.html');
    }

    // function detail_refund_apply($order_id){
    //     $render = app::get('ome')->render();
    //     $oRefund_apply = app::get('ome')->model('refund_apply');
        
    //     $refundBoolTypeLib = kernel::single('ome_refund_bool_type');

    //     $refund_apply = $oRefund_apply->getList('create_time,status,money,refund_apply_bn,refunded,bool_type',array('order_id'=>$order_id));
    //     if($refund_apply){
    //         foreach($refund_apply as $k=>$v)
    //         {
    //             $bool_type_str = '';
                
    //             //单据种类
    //             if($v['bool_type']){
    //                 $isPriceProtectRefund = $refundBoolTypeLib->isPriceProtectRefund($v['bool_type']);
    //                 if($isPriceProtectRefund){
    //                     $bool_type_str = '<span style="color:red;">【价保】</span>';
    //                 }
    //             }
                
    //             $refund_apply[$k]['status_text'] = $bool_type_str . ome_refund_func::refund_apply_status_name($v['status']);
    //         }
    //     }

    //     $render->pagedata['refund_apply'] = $refund_apply;

    //     return $render->fetch('admin/order/detail_refund_apply.html');
    // }

    function detail_delivery($order_id){
        $render = app::get('ome')->render();
        $oDelivery = app::get('ome')->model('delivery');
        $oReship = app::get('ome')->model('reship');
        $oWms_delivery = app::get('wms')->model('delivery');
        $obj_order = app::get('ome')->model('orders');
        
        // order_id
        $order_id = intval($order_id);
        
        // filter
        $base_filter = array('order_id'=>$order_id);
        
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }
        
        // orderInfo
        $orderInfo = $obj_order->dump($order_id, '*');
        if(empty($orderInfo)){
            $error_msg = '订单不存在，或者账号无权访问该订单发货信息';
            return '<div style="padding:16px;color:#c00;">' . htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        
        $wms_delivery = $oWms_delivery->getDeliveryByOrder($order_id);
        
        $oBranch = app::get('ome')->model('branch');
        $delivery = $oDelivery->getDeliveryByOrder('branch_id,create_time,delivery_id,delivery_bn,logi_id,logi_no,logi_name,ship_name,delivery,branch_id,stock_status,deliv_status,expre_status,status,weight',$order_id);
        $reship = $oReship->getList('t_begin,reship_id,reship_bn,logi_no,ship_name,delivery',array('order_id'=>$order_id));
        $wms_id = kernel::single('wms_branch')->getBranchByselfwms();
        
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
            $delivery[$k]['delivery_bn'] = '<a class="lnk" target="_blank" href="index.php?app=desktop&act=alertpages&goto='.urlencode('index.php?app=ome&ctl=admin_order&act=getDelivery&p[0]='.$v['delivery_id'].'&finder_id='.$_GET['_finder']['finder_id']).'">'.$v['delivery_bn'].'</a>';
        }
        
        //同城配
        $isSeller = false;
        foreach ((array)$wms_delivery as $wmsKey => $wmsVal)
        {
            if($wmsVal['delivery_model'] == 'seller'){
                $isSeller = true;
            }
        }
        $render->pagedata['isSeller'] = $isSeller;
        
        //获取京东物流包裹明细
        $deliveryPackage = $this->getOrderDeliveryPackage($order_id);
        $render->pagedata['order_bn'] = $orderInfo['order_bn'];
        $render->pagedata['is_hqepay_on'] = $is_hqepay_on;
        $render->pagedata['delivery'] = $delivery;
        $render->pagedata['wms_delivery'] = $wms_delivery;
        $render->pagedata['reship'] = $reship;
        $render->pagedata['deliveryPackage'] = $deliveryPackage;

        return $render->fetch('admin/order/detail_delivery.html');
    }

    /**
     * 获取京东物流包裹明细
     * @param $order_id
     * @return array
     */
    public function getOrderDeliveryPackage($order_id)
    {
        $consoleDeliveryLib = kernel::single('console_delivery');
        $oDelivery = app::get('ome')->model('delivery');
        $oDeliveryOrder = app::get('ome')->model('delivery_order');
        $deliveryOrderIds = $oDeliveryOrder->getList('delivery_id',array('order_id' => $order_id));
        $delivery_ids = array_column($deliveryOrderIds,'delivery_id');
        if(!$delivery_ids){
            return [];
        }
        $oDeliveryPackage = app::get('ome')->model('delivery_package');
        $deliveryPackageList = $oDeliveryPackage->getList('*',array('delivery_id' => $delivery_ids,'status|noequal'=>'cancel'));

        //发货单列表关联明细表，渠道ID，基础物料名称，商品采购价格
        $_deliveryList = array();
        if($delivery_ids){
            $sql = "SELECT d.delivery_id,d.wms_channel_id,di.bn,di.product_name,di.purchase_price,d.logi_id,d.logi_name,d.logi_no,d.delivery_time,d.logi_status,di.number,d.branch_id,d.delivery_bn 
                        FROM sdb_ome_delivery_items di
                        LEFT JOIN sdb_ome_delivery AS d ON d.delivery_id = di.delivery_id WHERE di.delivery_id IN(". implode(',', $delivery_ids) .")";
            $tempList = $oDelivery->db->select($sql);
            if($tempList){
                foreach ($tempList as $key => $val)
                {
                    $delivery_id = $val['delivery_id'];
                    $bn = $val['bn'];
                    $_deliveryList[$delivery_id.'_'.$bn] = $val;
                }
            }
        }

        //获取发货单明细为子单号的数据
        $_deliveryPackageList = array();
        if($delivery_ids){
            $sql = "SELECT d.delivery_id,d.delivery_bn,cde.original_delivery_bn FROM sdb_ome_delivery d 
                        LEFT JOIN sdb_console_delivery_extension AS cde ON d.delivery_bn = cde.delivery_bn
                        WHERE  d.delivery_id IN(". implode(',', $delivery_ids) .")";
            $tempList = $oDelivery->db->select($sql);
            if($tempList){
                foreach($tempList as $key => $val){
                    $_deliveryPackageList[$val['delivery_id']] = $val;
                }
            }
        }

        //发货单明细关联订单对象表，达人ID，达人名称
        $_orderObjectsInfo = array();
        if($order_id){
            $oOrderObjects = app::get('ome')->model('order_objects');
            $_orderObjectsInfo = $oOrderObjects->dump(array('order_id'=>$order_id),'author_id,author_name,order_id');
        }

        $data = array();
        foreach($deliveryPackageList as $key => $val){
            $delivery_id = $val['delivery_id'];
            $bn = $val['bn'];
            $dly_bn = $delivery_id.'_'.$bn;
            
            //create_time
            if($val['create_time']){
                $data[$key]['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
            }else{
                $data[$key]['create_time'] = '';
            }
            
            $data[$key]['delivery_bn']       = $_deliveryList[$delivery_id.'_'.$bn]['delivery_bn'];
            $data[$key]['bn']                   = $val['bn'];
            $data[$key]['product_name']         = $_deliveryList[$delivery_id.'_'.$bn]['product_name'];
            $data[$key]['is_wms_gift']          = ($val['is_wms_gift'] == 'true') ? '是' : '否';
            $data[$key]['number']               = $val['number'];
            $data[$key]['logi_bn']              = $val['logi_bn'];
            $data[$key]['logi_no']              = $val['logi_no'] ? $val['logi_no'] : $_deliveryList[$dly_bn]['logi_no'];
            $data[$key]['logi_name']            = isset($_deliveryList[$dly_bn]['logi_name']) ? $_deliveryList[$dly_bn]['logi_name'] : '';
            $data[$key]['status']               = $consoleDeliveryLib->getPackageStatus($val['status']);//包裹状态
            $data[$key]['shipping_status']      = $val['shipping_status'] ? $consoleDeliveryLib->getShippingStatus($val['shipping_status']) : '';//配送状态
            $data[$key]['wms_channel_id']       = isset($_deliveryList[$dly_bn]['wms_channel_id']) ? $_deliveryList[$dly_bn]['wms_channel_id'] : '';
            $data[$key]['author_id']            = isset($_orderObjectsInfo['author_id']) ? $_orderObjectsInfo['author_id'] : '';
            $data[$key]['author_name']          = isset($_orderObjectsInfo['author_name']) ? $_orderObjectsInfo['author_name'] : '';
            $data[$key]['jd_package_bn']        = $val['package_bn'];
            if ($_deliveryPackageList[$delivery_id]['original_delivery_bn'] != $val['package_bn']) {
                $data[$key]['father_package_bn'] = $_deliveryPackageList[$delivery_id]['original_delivery_bn'];
            }else{
                $data[$key]['father_package_bn'] = ' - ';
            }
        }
        return $data;
    }

    function detail_mark($order_id){
        $render = app::get('ome')->render();
        $oOrders = app::get('ome')->model('orders');

        if($_POST){
            $order_id = $_POST['order']['order_id'];
            $op_name = kernel::single('desktop_user')->get_name();
            
            // 使用ome_order_marktext::add方法添加备注
            list($success, $message) = kernel::single('ome_order_marktext')->add($order_id, $_POST['order']['mark_text'], $op_name);
            
            if (!$success) {
                // 可以在这里处理错误，比如显示错误消息
                // 这里暂时不做特殊处理，因为原逻辑也没有错误处理
            }
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
    
        //常用备注
        $commonRemarks = ['需发货质检', '需优先回传物流单', '指定物流', '指定不能用默认物流'];
        $render->pagedata['commonRemarks']  = $commonRemarks;

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

    /**
     * 订单操作记录
     * @param int $order_id
     * @return string
     */
    function detail_history($order_id)
    {
        //订单信息
        $orderType = app::get('ome')->model('orders')->dump(array('order_id'=>$order_id), 'order_type');
        
        //加载模板
        if($orderType['order_type'] == 'brush') {
            //brush特殊订单
            return $this->__brush_log_history($order_id);
        } else {
            //普通订单
            return $this->__normal_log_history($order_id);
        }
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
    
    var $addon_cols = "print_status,confirm,dt_begin,status,process_status,tax_no,ship_status,op_id,group_id,mark_text,auto_status,custom_mark,mark_type,tax_company,createtime,paytime,sync,pay_status,is_cod,source,order_type,order_bool_type,timing_confirm,shop_type,tostr,itemnum,delivery_time,abnormal_status,shipping,order_source,is_delivery,step_trade_status,is_not_combine";
    var $column_confirm='操作';
    var $column_confirm_width = "120";

    function column_confirm($row){

        if ($_GET['ctl']=='admin_order') {
            if($_GET['act'] == 'index') {
                return $this->_get_index_btn($row);
            }
            
            
            return $this->_get_confirm_btn($row);
        } elseif ($_GET['ctl']=='admin_order_lack') {
            return $this->_get_lack_btn($row);
        } else {

            return $this->_get_sync_btn($row);
        }
    }

    private function _get_index_btn($row) {
        $order_id = $row['order_id'];
        $find_id = $_GET['_finder']['finder_id'];
        if($row[$this->col_prefix.'shop_type'] == 'taobao' 
            && $row[$this->col_prefix.'order_source'] == 'maochao'
            && $row[$this->col_prefix.'ship_status'] == '1' ) {
            if(app::get('ome')->model('return_product')->db_dump(['order_id'=>$order_id, 'status|noequal'=>'5'], 'return_id')) {
                return '';
            }
            return '<a href="index.php?app=ome&ctl=admin_order_maochao&act=reject&order_id='.$order_id.'&finder_id='.$find_id.'" target="dialog::{width:800,height:500,title:\'客户拒收\'}">客户拒收</a>';
        }
        return '';
    }

    private function _get_lack_btn($row) {
        $order_id = $row['order_id'];
        $find_id = $_GET['_finder']['finder_id'];
        if($row[$this->col_prefix.'shop_type'] == 'taobao' 
            && $row[$this->col_prefix.'order_source'] == 'maochao'
            && $row[$this->col_prefix.'is_delivery'] != 'N' ) {
            return '<a href="index.php?app=ome&ctl=admin_order_lack&act=apply&order_id='.$order_id.'&finder_id='.$find_id.'" target="_blank">缺货申请</a>';
        }
        return '';
    }

    private function _get_sync_btn($row) {

        if ($_GET['view']==3 || $_GET['view']==5 ||  $_GET['view']==4) {

            unset($this->column_confirm);
            return;
        }
        $find_id = $_GET['_finder']['finder_id'];

        $result = '';
        $order_id = $row['order_id'];

        switch ($row[$this->col_prefix.'sync']) {
            case 'none':
                $result = "<a href='index.php?app=ome&ctl=admin_consign&act=do_sync&p[0]={$order_id}&finder_id=$find_id' target='download'>发货</a>";
                break;
            case 'fail':
            case 'run':
                $result = "<a href='index.php?app=ome&ctl=admin_consign&act=do_sync&p[0]={$order_id}&finder_id=$find_id' target='download'>重试</a>";
                break;
        }

        return $result;
    }

    private function _get_confirm_btn($row) {

        //条件过滤
        $filter_data = array();
        if ($_POST)
        foreach ($_POST as $key=>$v){
            if (preg_match("/^_+/i",$key)) continue;
            $filter_data[$key] = $v;
        }
        $filter = urlencode(serialize($filter_data));
        $find_id = $_GET['_finder']['finder_id'];
        $order_id = $row['order_id'];

        $button = <<<EOF
            <a href="index.php?app=ome&ctl=admin_order&act=do_confirm&p[0]=$order_id&filter=$filter&find_id=$find_id&finder_id=$find_id" target="_blank">订单确认</a>
EOF;

        $button2 = <<<EOF
            <a href="index.php?app=ome&ctl=admin_order&act=do_confirm&p[0]=$order_id&filter=$filter&find_id=$find_id&finder_id=$find_id" target="_blank">订单拆分</a>
EOF;

        $button_platform_split = <<<EOF
            <a href="index.php?app=ome&ctl=admin_order_platformsplit&act=do_confirm&p[0]=$order_id&filter=$filter&find_id=$find_id&finder_id=$find_id" target="_blank">京东拆</a>
EOF;

        $remain_order_cancel_but = <<<EOF
            <a href="index.php?app=ome&ctl=admin_order&act=remain_order_cancel_confirm&order_id=$order_id&find_id=$find_id&finder_id=$find_id&from=order_button" target="_blank">余单撤销</a>
EOF;

        $button_batch = <<<EOF
            <a href="index.php?app=ome&ctl=admin_order&act=do_confirm&p[0]=$order_id&filter=$filter&find_id=$find_id&finder_id=$find_id" target="_blank">审核</a>
EOF;

        $button_dispatch = <<<EOF
            <a href="index.php?app=ome&ctl=admin_order&act=dispatchSingle&finder_id=$find_id&p[0]=$order_id&single=is" target="dialog::{width:400,height:200,title:'订单分派'}">分派</a>
EOF;
        $reback_delivery_but = <<<EOF
        <a href="index.php?app=ome&ctl=admin_delivery&act=back&status=1&order_id=$order_id&find_id=$find_id" target="_blank">撤销发货单</a>


EOF;
        #订单编辑同步状态
        $shop_id = $row['shop_id'];
        $order_bn = $row['order_bn'];
        $oOrder_sync = app::get('ome')->model('order_sync_status');
        $sync_status = $oOrder_sync->getList('order_id,type,sync_status',array('order_id'=>$order_id),0,1);
        if ($sync_status[0]['sync_status'] == '1' && $row[$this->col_prefix.'source'] == 'matrix'){
            $button2 = $button = <<<EOF
            <a onclick="javascript:new Request({
                url:'index.php?app=ome&ctl=admin_shop&act=sync_order',
                data:'order_id={$order_bn}&shop_id={$shop_id}',
                method:'post',
                onSuccess:function(response){
                    var resp = JSON.decode(response);
                    if (resp.rsp == 'fail'){
                        alert(resp.msg);
                    }else{
                        new Request({
                            url:'index.php?app=ome&ctl=admin_order&act=set_sync_status&p[0]={$order_id}&p[1]=success',
                            method:'get',
                            onSuccess:function(rs){
                                alert('同步成功');
                                finder = finderGroup['{$find_id}'];
                                finder.refresh.delay(100, finder);
                            }
                        }).send();
                    }
                }
            }).send();" href="javascript:;" >重新同步</a>
EOF;
            $re_sync = true;
        }

        // 订单确认 - 本组的订单
        if ($_GET['flt'] == 'ourgroup')
        {
            if (empty($row[$this->col_prefix.'op_id']) && in_array($row[$this->col_prefix.'process_status'],array('unconfirmed','confirmed','splitting')))
            {
                $button_3 = sprintf('<a href="javascript:if (confirm(\'是否确认领取？如果领取相关订单将同时被领取！\')){W.page(\'index.php?app=ome&ctl=admin_order&act=claim&order_id[0]=%s&filter=%s&find_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">领取</a>', $order_id, $filter, $find_id);
                return $button_3;
            }
        }
        // 订单确认 - 我的待确认订单
        elseif ($_GET['flt'] == 'unmyown')
        {
            // 检测京东订单是否有微信支付先用后付的单据
            $labelCode = [];
            if ($row[$this->col_prefix.'shop_type'] == '360buy') {
                $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($row['order_id']);
                $labelCode = array_column($labelCode, 'label_code');
            }

            if (($row[$this->col_prefix.'pay_status'] == 1 || $row[$this->col_prefix.'pay_status'] == 4 || ($row[$this->col_prefix.'is_cod'] == 'true' && ($row[$this->col_prefix.'pay_status'] == 0 || $row[$this->col_prefix.'pay_status'] == 3)) || ($row[$this->col_prefix.'pay_status'] == 3 && $row[$this->col_prefix.'step_trade_status'] == 'FRONT_PAID_FINAL_NOPAID' && kernel::single('ome_order_func')->checkPresaleOrder())|| kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode) ) && in_array($row[$this->col_prefix.'process_status'], array('unconfirmed', 'confirmed','splitting')) && $row[$this->col_prefix.'ship_status'] == '0')
            {
                if ($row[$this->col_prefix.'confirm'] == 'N' && !in_array($row[$this->col_prefix.'process_status'],array('splited','cancel','remain_cancel')) && $row[$this->col_prefix.'status'] == 'active' && $row[$this->col_prefix.'is_not_combine'] == 0){
                    if($row[$this->col_prefix.'shop_type'] == '360buy' && $row[$this->col_prefix.'source'] == 'matrix') {
                        return $button_batch . ' | ' . $button_platform_split;
                    }
                    
                    return $button_batch;
                }

                if (!in_array($row[$this->col_prefix.'process_status'],array('splited','unconfirmed','cancel','remain_cancel')) && $row[$this->col_prefix.'status'] == 'active' && $row[$this->col_prefix.'is_not_combine'] == 0){
                    if($row[$this->col_prefix.'shop_type'] == '360buy' && $row[$this->col_prefix.'source'] == 'matrix') {
                        return $button_batch . ' | ' . $button_platform_split;
                    }
                    
                    return $button_batch;
                }
            }
            elseif (($row[$this->col_prefix.'pay_status'] == 1 || $row[$this->col_prefix.'is_cod'] == 'true') && $row[$this->col_prefix.'process_status'] == 'splitting' && ($row[$this->col_prefix.'ship_status'] == '2' || $row[$this->col_prefix.'ship_status'] == '3'))
            {
                return sprintf("%s | %s | %s", $button2, $remain_order_cancel_but,$reback_delivery_but);//已支付-部分拆分-部分发货-部分退货
            }
            elseif ($row[$this->col_prefix.'pay_status'] == 1 && $row[$this->col_prefix.'process_status'] == 'splitting' && $row[$this->col_prefix.'ship_status'] == '4')
            {
                return sprintf("%s ", $remain_order_cancel_but);//已支付-部分拆分-已退货-可余单撤销
            }
            elseif ($row[$this->col_prefix.'pay_status'] == 4 && $row[$this->col_prefix.'process_status'] == 'splitting' && ($row[$this->col_prefix.'ship_status'] == '2' || $row[$this->col_prefix.'ship_status'] == '3'))
            {
                return sprintf("%s | %s", $button2, $remain_order_cancel_but);//部分退款-部分退款-部分拆分-部分发货的订单可继续操作
            }
            elseif($row[$this->col_prefix.'pay_status'] == 5 && ($row[$this->col_prefix.'process_status'] == 'splitting' || $row[$this->col_prefix.'ship_status'] == '2'))
            {
                return sprintf("%s", $remain_order_cancel_but);//全额退款-部分拆分-部分发货-可余单撤销
            }
        } elseif($_GET['flt'] == 'buffer') {
            //缓冲区
            return $button_dispatch;
        } elseif($_GET['flt'] == 'assigned') {
            //缓冲区
            $deliveryObj = app::get('ome')->model('delivery');
            $deliveryIds = $deliveryObj->getDeliverIdByOrderId($row['order_id']);
            if(count($deliveryIds)==0){
                return $button_dispatch;
            }
        } else {
            // 检测京东订单是否有微信支付先用后付的单据
            $labelCode = [];
            if ($row[$this->col_prefix.'shop_type'] == '360buy') {
                $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($row['order_id']);
                $labelCode = array_column($labelCode, 'label_code');
            }

            // 余单撤销(只有已支付，或是货到付款并且部分发货的才会出现余单撤销按钮)
            if (($row['pay_status'] == 1 || $row['is_cod'] == 'true' || kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode) ) && $row[$this->col_prefix.'ship_status'] == '2'){
                return $button = $remain_order_cancel_but.$reback_delivery_but;
            }
        }
        if ($_GET['flt'] == 'myown' && $row[$this->col_prefix.'ship_status'] != '1'){
            return  $reback_delivery_but;
        }
        if($re_sync == true){
            return $button;
        }
    }

    function row_style($row, $list){
        $time = time();
        $limit = (app::get("ome")->getConf('ome.order.unconfirmtime'))*60;
        $style='';
        if($row[$this->col_prefix.'confirm'] == 'N'
            && ($time - $row[$this->col_prefix.'dt_begin'] > $limit)
            && ($row[$this->col_prefix.'op_id'] || $row[$this->col_prefix.'group_id'])
            && $row[$this->col_prefix.'process_status'] == 'unconfirmed'){
            $style .= ' highlight-row ';
        }
        if($row[$this->col_prefix.'is_cod'] == 'true'){
            $style .= " list-even ";
        }
        elseif($row['process_status'] == 'splitting' && $row['pay_status'] == '4')
        {
            $style  = 'list-warning'; //部分退款--颜色显示
        }
        elseif($row['process_status'] == 'is_retrial' || $row['process_status'] == 'is_declare')
        {
            $style  = ' selected '; //复审订单 OR 跨境申报订单
        }
        elseif($row[$this->col_prefix .'order_type'] == 'presale'){
            $style = ' list-presale';
        }
        if(in_array($row['process_status'],array('splitting','confirmed','unconfirmed'))) {
            $extend = $this->__getOrderExtend($list);
            $time = $extend[$row['order_id']]['push_time'];
            $now = time();
            if($time > $now - 432000) { //5天内
                if ($now > $time) {
                    // $style .= 'list-warn';
                } elseif ($now > $time - 1800) {
                    $style .= 'list-warning';
                }
            }
        }
        if(in_array($row['process_status'],array('splited','splitting','confirmed','unconfirmed'))
            && $row[$this->col_prefix.'status'] == 'active') {
            $extend = $this->__getOrderExtend($list);
            $time = $extend[$row['order_id']]['latest_delivery_time'];
            $now = time();
            if($time > 0) { //有最晚发货时间
                if ($now > $time) {
                    $style .= 'list-warn';
                } elseif ($now > $time - 1800) {
                    $style .= 'list-warning';
                }
            }
        }
        return $style;
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

    // var $column_custom_add='买家备注';
    var $column_custom_add='客户备注';
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

    // var $column_customer_add='客服备注';
    var $column_customer_add='商家备注';
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
        foreach ($row as $key => $val) {
            $key = str_replace($this->col_prefix, '', $key);
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
        if ($row[$this->col_prefix.'is_cod'] == 'true') {
            $difftime = kernel::single('ome_func')->toTimeDiff(time(), $row[$this->col_prefix.'createtime']);
        } else {
            if ($row[$this->col_prefix.'paytime'] > 0) {
                $difftime = kernel::single('ome_func')->toTimeDiff(time(), $row[$this->col_prefix.'paytime']);
            } else {
                //return '<span style="color:red;font-weight:700;">未支付</span>';
                return '';
            }
        }
        return $difftime['d'] . '天' . $difftime['h'] . '小时' . $difftime['m'] . '分';
    }

    public function getViewPanel($color, $msg, $title) {

        return sprintf("<div onmouseover='bindFinderColTip(event)' rel='%s' style='width:18px;padding:2px;height:16px;background-color:%s;float:left;color:#ffffff;'>&nbsp;%s&nbsp;</div>", $msg, $color, $title);
    }


    //显示状态
    var $column_print_status = "打印状态";
    var $column_print_status_width = "80";

    function column_print_status($row) {

        $stockColor = (($row[$this->col_prefix.'print_status'] & 0x02) == 0x02) ? 'green' : '#eeeeee';
        $delivColor = (($row[$this->col_prefix.'print_status'] & 0X04) == 0X04) ? 'red' : '#eeeeee';
        $expreColor = (($row[$this->col_prefix.'print_status'] & 0x01) == 0x01) ? 'gold' : '#eeeeee';
        $ret = $this->_getViewPanel('备货单', $stockColor);
        $ret .= $this->_getViewPanel('发货单', $delivColor);
        $ret .= $this->_getViewPanel('快递单', $expreColor);
        return $ret;
    }

    public function _getViewPanel($caption, $color) {
        if ($color == '#eeeeee')
            $caption .= '未打印';
        else
            $caption .= '已打印';
        return sprintf("<div style='width:18px;padding:2px;height:16px;background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", $color, $caption, $caption, substr($caption, 0, 3));
    }
    #订单异常类型
    /*
    var $column_abnormal_type_name ='异常类型';
    var $column_abnormal_type_name_width = "80";

    function column_abnormal_type_name($row){
        $obj_abnormal = app::get('ome')->model('abnormal');
        $arr = $obj_abnormal->getList('abnormal_type_name',array('order_id'=>$row['order_id']));
        return $arr[0]['abnormal_type_name'];
    }
    */
    var $column_tax_company='发票抬头';
    var $column_tax_company_width = "150";
    function column_tax_company($row){
        //$oObj = app::get('ome')->model('orders');
        //$tax_info = $oObj->dump($row['order_id'],'tax_company');
        if(empty($row[$this->col_prefix.'tax_company'])){
            return '-';
        }
        return $row[$this->col_prefix.'tax_company'];
    }

    var $column_timing_confirm='定时审单';
    var $column_timing_confirm_width = "150";
    function column_timing_confirm($row){
        $timeConfirm = $row[$this->col_prefix.'timing_confirm'];
        if(!$timeConfirm){
            return '-';
        }
        if($timeConfirm == kernel::single('omeauto_auto_hold')->getMaxHoldTime()) {
            return '手工审核';
        }
        return date('Y-m-d H:i:s', $timeConfirm);
    }
    
    var $column_abnormal_status = '复审操作';
    var $column_abnormal_status_width = '110';
    var $column_abnormal_status_order = '10';
    function column_abnormal_status($row)
    {
        $find_id = $_GET['_finder']['finder_id'];
        $order_id = $row['order_id'];
        
        //不是复审订单,直接返回
        if($row[$this->col_prefix.'process_status'] != 'is_retrial'){
            return '';
        }
        
        $sql    = "SELECT id, retrial_type, status FROM ".DB_PREFIX."ome_order_retrial WHERE order_id='".$order_id."' AND status in('0', '2') ORDER BY dateline DESC";
        $result = kernel::database()->select($sql);
        
        $str    = '<a href="index.php?app=ome&ctl=admin_order&act=view_edit&p[0]='.$order_id.'&finder_id='.$find_id.'&oldsource=active" target="_blank">编辑</a>';
        if($result[0]['status'] == '2' && $result[0]['retrial_type'] == 'normal')
        {
            return $str.' | <a href="index.php?app=ome&ctl=admin_order&act=retrial_rollback&p[0]='.$order_id.'&finder_id='.$find_id.'&oldsource=retrial" target="_blank" style="color:red;">恢复原订单</a>';
        }
        elseif($result[0]['status'] == '2')
        {
            return $str.'<span style="color:#999">(价格复审)</span>';
        }
        else
        {
            return '<span style="color:#999">未审核</span>';
        }
    }
    
    var $column_mark_text = '复审备注';
    var $column_mark_text_width = '130';
    var $column_mark_text_order = '15';
    function column_mark_text($row)
    {
        $order_id = $row['order_id'];
        
        //不是复审订单,直接返回
        if($row[$this->col_prefix.'process_status'] != 'is_retrial'){
            return '';
        }
        
        $sql = "SELECT id, remarks, lastdate FROM ".DB_PREFIX."ome_order_retrial WHERE order_id='".$order_id."' AND status in('0', '2') ORDER BY dateline DESC";
        $result = kernel::database()->select($sql);
        
        $html   = strip_tags(htmlspecialchars($result[0]['remarks']));
        return "<div onmouseover='bindFinderColTip(event)' rel='".$html.' by '.date('Y-m-d H:i:s', $result[0]['lastdate'])."'>".$html."<div>";
    }
    
    var $column_order_combined_confirm = '已合并审单';
    var $column_order_combined_confirm_width = "60";
    function column_order_combined_confirm($row) {
        $ret = "否";
        $mdl_ome_dl_or = app::get('ome')->model('delivery_order');
        $rs_dl_or = $mdl_ome_dl_or->getList("delivery_id",array("order_id"=>$row['order_id']),0,-1,"delivery_id desc");
        if(!empty($rs_dl_or)){
            $mdl_ome_dl = app::get('ome')->model('delivery');
            foreach($rs_dl_or as $var_d_o){
                $rs_dl = $mdl_ome_dl->dump(array("delivery_id"=>$var_d_o["delivery_id"]),"is_bind");
                if($rs_dl["is_bind"] == "true"){
                    $ret = "是";
                    break;
                }
            }
        }
        return $ret;
    }

    private function __getOrderExtend($list) {
        static $arrExtend;
        if(isset($arrExtend)) {
            return $arrExtend;
        }
        $orderId = array();
        foreach($list as $val) {
            $orderId[] = $val['order_id'];
        }
        $extendData = app::get('ome')->model('order_extend')->getList('*', array('order_id'=>$orderId));
        foreach($extendData as $val) {
            $arrExtend[$val['order_id']] = $val;
        }
        return $arrExtend;
    }

    var $column_bool_type='订单标识';
    function column_bool_type($row)
    {
        return kernel::single('ome_order_bool_type')->getBoolTypeIdentifier($row[$this->col_prefix.'order_bool_type'],$row[$this->col_prefix.'shop_type']);
    }

    public $column_push_time = '推单时间';
    public $column_push_time_width = '120';
    public function column_push_time($row, $list) {
        $extend = $this->__getOrderExtend($list);
        $time = $extend[$row['order_id']]['push_time'];
        $img = '';
        if (kernel::single('ome_order_bool_type')->isCnService($row[$this->col_prefix . 'order_bool_type'])) {
            if($extend[$row['order_id']]['cn_service'] == 'dang') {
                $img = '<img src="' . app::get('ome')->res_url . '/images/nonstop_1.png"/>';
            } elseif ($extend[$row['order_id']]['cn_service'] == 'ci') {
                $img = '<img src="' . app::get('ome')->res_url . '/images/nonstop_2.png"/>';
            } elseif ($extend[$row['order_id']]['cn_service'] == 'shi') {
                $img = '<div style="line-height: 9px;">预约时效服务</div>';
            } elseif ($extend[$row['order_id']]['cn_service'] == 'yue') {
                $img = '<img src="' . app::get('ome')->res_url . '/images/nonstop_4.png"/>';
            } elseif ($extend[$row['order_id']]['cn_service'] == 'duo') {
                $img = '<div style="line-height: 9px;">菜鸟多日达</div>';
            }
        }
        return $time ? '<span style="color:green">' . date('Y-m-d H:i', $time) . '</span>  ' . $img : '';
    }
    
    public $column_collect_time = '揽收时间';
    public $column_collect_time_width = '120';
    public function column_collect_time($row, $list) {
        $extend = $this->__getOrderExtend($list);
        $time = $extend[$row['order_id']]['collect_time'];
        $txt = '<div style="line-height: 9px;">'.$extend[$row['order_id']]['es_time'].'日达</div>';
        return $time ? '<span style="color:green">' . date('Y-m-d H:i', $time) . '</span>  ' . $txt : '';
    }

    public $column_added_serivces = '增值服务';
    public function column_added_serivces($row, $list){
        $extend = $this->__getOrderExtend($list);
        $img = '';
        $cn_service = explode(',', $extend[$row['order_id']]['cpup_service']);

        $str = '';

        if (kernel::single('ome_order_bool_type')->isCPUP($row[$this->col_prefix . 'order_bool_type'])) {
            if (in_array('201', $cn_service)) {
                $str .= "<span style='color:#6666ff'>按需配送</span><br>";

            }
            if (in_array('202', $cn_service)) {
                $str .= "<span style='color:#c64ae2'>顺丰配送</span><br>";
            }
            if (in_array('203', $cn_service)) {
                $str .= "<span style='color:#4ae25e'>承诺发货</span><br>";
            }
            if (in_array('204', $cn_service)) {
                $str .= "<span style='color:#e2bc4a'>承诺送达</span><br>";
            }
            if (in_array('210', $cn_service)) {
                $str .= "<span style='color:#c64ae2'>极速上门</span><br>";
            }
            if (in_array('sug_home_deliver', $cn_service)) {
                $str .= "<a style='color:#FF8800;text-decoration:none;' target='_blank' href='https://school.jinritemai.com/doudian/web/article/aHL7CAWFuopG'>建议使用音尊达</a><br>";
            }
        }

        if (!empty($str)) {
            $img .= "<div>$str</div>";
        }
        return $img ? $img : '';
    }

    public $column_latest_delivery_time = '最晚发货时间';
    public $column_latest_delivery_time_width = '120';
    public function column_latest_delivery_time($row, $list) {
        $extend = $this->__getOrderExtend($list);
        $time = (isset($extend[$row['order_id']]['latest_delivery_time']) ? $extend[$row['order_id']]['latest_delivery_time'] : 0);
        if(!$time) {
            return '';
        }
        
        $diff = $time - time();
        if ($diff > 0) {
            $days = floor($diff / 86400);
            $hours = floor(($diff % 86400) / 3600);
            $mins = floor(($diff % 3600) / 60);
            $secs = $diff % 60;
            $remain = ($days > 0 ? $days . '天' : '')
                . $hours . '小时' . $mins . '分钟' . $secs . '秒';
            $tip = '剩余：' . $remain . ' 将发货超时';
        } else {
            if($row['pay_status'] == '1' && $row['ship_status'] == '0'){
                $tip = '当前时间已超过最晚发货时间！';
            }else{
                $tip = '';
            }
        }
        
        $html = '<span style="color:green" title="' . htmlspecialchars($tip) . '">' . date('Y-m-d H:i', $time) . '</span>';
        if($row[$this->col_prefix . 'order_bool_type'] & ome_order_bool_type::__SHI_CODE) {
            return $html . ' ';
        }
        
        $img = '';
        if(kernel::single('ome_order_bool_type')->isCnService($row[$this->col_prefix . 'order_bool_type'])) {
            if($extend[$row['order_id']]['cn_service'] == 'dang') {
                $img = '<img style="width:74px" src="' . app::get('ome')->res_url . '/images/tmzs.png"/>';
            } elseif ($extend[$row['order_id']]['cn_service'] == 'ci') {
                $img = '<img style="width:74px" src="' . app::get('ome')->res_url . '/images/tmzs.png"/>';
            } elseif ($extend[$row['order_id']]['cn_service'] == 'shi') {
                $img = '<img style="width:74px" src="' . app::get('ome')->res_url . '/images/tmzs.png"/>';
            } elseif ($extend[$row['order_id']]['cn_service'] == 'yue') {
                $img = '<img style="width:74px" src="' . app::get('ome')->res_url . '/images/tmzs.png"/>';
            } elseif ($extend[$row['order_id']]['cn_service'] == 'duo') {
                $img = '<img style="width:74px" src="' . app::get('ome')->res_url . '/images/tmzs.png"/>';
            }
        }
        
        return $html . '  ';
    }
    
    public $column_promised_collect_time = '承诺最晚揽收时间';
    public $column_promised_collect_time_width = '120';
    public function column_promised_collect_time($row, $list) {
        $extend = $this->__getOrderExtend($list);
        $time = $extend[$row['order_id']]['promised_collect_time'];
        return $time ? '<span style="color:green">' . date('Y-m-d H:i', $time) . '</span>' : '';
    }
    
    public $column_promised_sign_time = '承诺最晚送达时间';
    public $column_promised_sign_time_width = '120';
    public function column_promised_sign_time($row, $list) {
        $extend = $this->__getOrderExtend($list);
        $time = $extend[$row['order_id']]['promised_sign_time'];
        return $time ? '<span style="color:green">' . date('Y-m-d H:i', $time) . '</span>' : '';
    }
    /**
     * 订单标记
     */
    public $column_order_label = '订单标记';
    public $column_order_label_width = 260;
    public $column_order_label_order = 30;
    public function column_order_label($row, $list)
    {
        $order_id = $row['order_id'];
        
        //获取订单标记列表
        $labelList = $this->__getOrderLabel($list);
        $dataList = $labelList[$order_id];
        if(empty($dataList)){
            return '';
        }
        
        //只显标记列表
        $str = [];
        foreach ($dataList as $key => $val)
        {
            $label_desc = $val['label_desc'];
            if($label_desc){
                $str[] =
                sprintf("<span onmouseover='bindFinderColTip(event)' rel='%s'  style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;' >%s</span>", $val['label_desc'],$val['label_color'], $val['label_color'], $val['label_name']);
            }else{
                $str[] =
                sprintf("<span  style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;' >%s</span>", $val['label_color'], $val['label_color'], $val['label_name']);
            }
            
        }
        
        $str = '<div style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">'.implode("", $str).'</div>';
        
        return $str;
    }
    
    /**
     * 订单标记列表
     * 
     * @param array $list
     * @return null
     */
    private function __getOrderLabel($list)
    {
        static $arrOrderLabel;
        
        if(isset($arrOrderLabel)){
            return $arrOrderLabel;
        }
        
        $arrOrderLabel = [];
        $orderIds = array();
        foreach($list as $val) {
            $orderIds[] = $val['order_id'];
        }
        
        //获取订单标记列表
        $orderLabelObj = app::get('ome')->model('bill_label');
        $labelData = $orderLabelObj->getBIllLabelList($orderIds);
        foreach($labelData as $val)
        {
            $order_id = $val['bill_id'];
            
            $arrOrderLabel[$order_id][] = array(
                    'label_id' => $val['label_id'],
                    'label_name' => $val['label_name'],
                    'label_color' => $val['label_color'],
                    'label_desc'=>$val['label_desc'],
            );
        }
        
        unset($orderIds, $labelData);
        
        return $arrOrderLabel;
    }

    /**
     * 回传给前端店铺发货状态失败
     */
    public $column_delivery_errormsg = '发货失败信息';
    public $column_delivery_errormsg_width = 300;
    public $column_delivery_errormsg_order = 99;
    public function column_delivery_errormsg($row, $list)
    {
        //不是已发货状态,直接返回
        if($row[$this->col_prefix.'ship_status'] != '1'){
            return '';
        }

        //不是回传失败,直接返回
        if($row[$this->col_prefix.'sync'] != 'fail'){
            return '';
        }

        $order_bn = $row['order_bn'];

        if(empty(self::$_syncErrorMsgs)){
            $shipmentObj = app::get('ome')->model('shipment_log');

            $order_bns = array();
            foreach ($list as $key => $val)
            {
                $order_bns[] = $val['order_bn'];
            }

            //list
            $shipmentLogs = $shipmentObj->getList('log_id,orderBn,message', array('orderBn'=>$order_bns));
            if($shipmentLogs){
                foreach ($shipmentLogs as $key => $val)
                {
                    $orderBn = $val['orderBn'];

                    $val['message'] = strip_tags(htmlspecialchars($val['message']));

                    self::$_syncErrorMsgs[$orderBn] = substr($val['message'], 0, 96);
                }
            }

            unset($order_bns, $shipmentLogs);
        }

        return self::$_syncErrorMsgs[$order_bn];
    }

    var $column_tostr = "商品名称";
    var $column_tostr_width = "160";
    function column_tostr($row) {
        $tostr = $row[$this->col_prefix . 'tostr'];
        if($tostr){
            $tostr = json_decode($tostr,true);
            if(!is_array($tostr)) {
                return '';
            }
            $num=0;
            foreach ($tostr as $value){
                $num+= $value['num'];
            }
            return sprintf("<div style='word-wrap:break-word; word-break:normal;'>%s</div>",implode(',',array_column($tostr,'name')));
        }

        return '';
    }

    var $column_itemnum = "商品数量";
    var $column_itemnum_width = "160";
    function column_itemnum($row) {
        $tostr = $row[$this->col_prefix . 'tostr'];
        if($tostr){
            $tostr = json_decode($tostr,true);
            $num=0;
            foreach ($tostr as $value){
                $num+= $value['num'];
            }
            return sprintf("<div style='word-wrap:break-word; word-break:normal;'>%s</div>",$num);
        }

        return '';
    }
    
    var $column_abnormal_mark = '异常标识';
    function column_abnormal_mark($row)
    {
        return kernel::single('ome_preprocess_const')->getBoolTypeIdentifier($row[$this->col_prefix.'abnormal_status'], $row[$this->col_prefix.'shop_type']);
    }
    
    public function detail_prodcut_store($order_id)
    {
        $render = app::get('ome')->render();
        $branchIds = kernel::single('ome_order_branch')->getBranchIdByOrder($order_id);
        if(empty($branchIds)) {
            return '<div class="tableform"><div class="division">缺少推荐仓库</div></div>';
        }
        $items = app::get('ome')->model('order_items')->getList('product_id,bn,name,nums,split_num', ['order_id'=>$order_id, 'delete'=>'false']);
        $bp = app::get('ome')->model('branch_product')->getList('branch_id,product_id,store,store_freeze', ['branch_id'=>$branchIds, 'product_id'=>array_column($items, 'product_id')]);
        if(empty($bp)) {
            $bp = app::get('o2o')->model('product_store')->getList('branch_id,bm_id as product_id,store,store_freeze', ['branch_id'=>$branchIds, 'bm_id'=>array_column($items, 'product_id')]);
        }
        // $orderFreeze = app::get('material')->model('basic_material_stock_freeze')->getList('*', array('obj_type'=>1, 'obj_id'=>$order_id));
        // $branchIds = array_merge($branchIds, array_column($orderFreeze, 'branch_id'));
        $branch = app::get('ome')->model('branch')->getList('branch_id, name', ['branch_id'=>$branchIds, 'check_permission'=>'false']);
        $branch = array_column($branch, null, 'branch_id');
        $pidItems = [];
        foreach($items as $v) {
            if($pidItems[$v['product_id']]) {
                $pidItems[$v['product_id']]['undly_num'] += $v['nums'] - $v['split_num'];
            } else {
                $pidItems[$v['product_id']] = $v;
                $pidItems[$v['product_id']]['undly_num'] = $v['nums'] - $v['split_num'];
            }
        }
        $pidBp = [];
        foreach($bp as $v) {
            $pidBp[$v['product_id']][$v['branch_id']] = $v;
        }
        $store_list = [];
        foreach($pidItems as $v) {
            if($pidBp[$v['product_id']]) {
                foreach($pidBp[$v['product_id']] as $vv) {
                    $tmp = $v;
                    if($vv['store']-$vv['store_freeze'] < $v['undly_num']) {
                        $tmp['is_less'] = true;
                    }
                    $tmp['store'] = $vv['store'];
                    $tmp['store_freeze'] = $vv['store_freeze'];
                    $tmp['branch_name'] = $branch[$vv['branch_id']]['name'];
                    $store_list[] = $tmp;
                }
            } else {
                $v['is_less'] = true;
                $store_list[] = $v;
            }
        }
        $render->pagedata['store_list'] = $store_list;

        // foreach ($orderFreeze as $key => $value) {
        //     $orderFreeze[$key]['bn'] = $pidItems[$value['bm_id']]['bn'];
        //     $orderFreeze[$key]['name'] = $pidItems[$value['bm_id']]['name'];
        //     $orderFreeze[$key]['bill_type'] = $value['bill_type'] == 2 ? '预占' : ($value['bill_type'] == 1 ? '缺货' : '未处理/已释放');
        //     $orderFreeze[$key]['branch_name'] = $branch[$value['branch_id']]['name'];
        // }
        // $render->pagedata['order_freeze'] = $orderFreeze;

        return $render->fetch('admin/order/detail_product_store.html');
    }

    public function detail_freeze($order_id)
    {
        $render = app::get('ome')->render();



        $orderFreeze = app::get('material')->model('basic_material_stock_freeze')->getList('*', array('obj_type'=>1, 'obj_id'=>$order_id, 'num|than' => 0));

        $deliveryOrder = app::get('ome')->model('delivery_order')->getList('*', array('order_id'=>$order_id));
        if ($deliveryOrder) {
            $delivery_id = array_column($deliveryOrder, 'delivery_id');
            $orderFreeze2 = app::get('material')->model('basic_material_stock_freeze')->getList('*', array('obj_type'=>2, 'obj_id'=>$delivery_id, 'bill_type'=> '1', 'num|than' => 0));

            if ($orderFreeze2) {
                $orderFreeze = array_merge((array)$orderFreeze, (array)$orderFreeze2);
            }
        }

        if ($orderFreeze) {
            $branchIds = array_filter(array_column($orderFreeze, 'branch_id'));
            $branchList = [];
            if ($branchIds) {
                $branchList = app::get('ome')->model('branch')->getList('branch_id,name', [
                    'branch_id'         =>$branchIds,
                    'check_permission'  => 'false',
                ]);
    
                $branchList = array_column($branchList, 'name', 'branch_id');
            }
    
            $bmIds = array_filter(array_column($orderFreeze, 'bm_id'));
            $bmList = app::get('material')->model('basic_material')->getList('bm_id,material_bn,material_name', [
                'bm_id' => $bmIds,
            ]);
            $bmList = array_column($bmList, null, 'bm_id');
    
            $billType = app::get('console')->model('basic_material_stock_freeze')->get_type(1);
            foreach ($orderFreeze as $key => $value) {
                $orderFreeze[$key]['bn']        = $bmList[$value['bm_id']]['material_bn'];
                $orderFreeze[$key]['name']      = $bmList[$value['bm_id']]['material_name'];
                $orderFreeze[$key]['branch_name'] = $branchList[$value['branch_id']];
                if ($value['obj_type'] == '2') {
                    $orderFreeze[$key]['bill_type'] = '仓库冻结';
                    $orderFreeze[$key]['obj_type'] = '发货单';
                } else {
                    $orderFreeze[$key]['obj_type'] = '订单';
                    $orderFreeze[$key]['bill_type'] = $billType[$value['bill_type']];
                }
            }
        }

        

        $render->pagedata['order_freeze'] = $orderFreeze;

        return $render->fetch('admin/order/detail_freeze.html');
    }
    
    /**
     * [普通]订单操作记录
     * 
     * @param int $order_id
     * @return string
     */
    private function __normal_log_history($order_id)
    {
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
            
            // 为长文本准备数据，HTML由模板处理
            $memo = $history[$k]['memo'];
            $memoLength = mb_strlen($memo);
            
            if ($memoLength > 400) {
                $history[$k]['short_memo'] = mb_substr($memo, 0, 400);
                $history[$k]['is_long'] = true;
            } else {
                $history[$k]['is_long'] = false;
            }
        }

        /* 发货单日志 */
        $delivery_ids = $deliveryObj->getDeliverIdByOrderId($order_id);
        if ($delivery_ids) {
            $deliverylog = $logObj->read_log(array('obj_id'=>$delivery_ids,'obj_type'=>'delivery@ome'), 0, -1);
        }

        //[拆单]多个发货单 格式化分开显示
        $dly_log_list   = array();
        foreach((array) $deliverylog as $k=>$v)
        {
            $deliverylog[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
            
            // 为长文本准备数据，HTML由模板处理
            $memo = $deliverylog[$k]['memo'];
            $memoLength = mb_strlen($memo);
            
            if ($memoLength > 400) {
                $deliverylog[$k]['short_memo'] = mb_substr($memo, 0, 400);
                $deliverylog[$k]['is_long'] = true;
            } else {
                $deliverylog[$k]['is_long'] = false;
            }
            
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
                
                // 为长文本准备数据，HTML由模板处理
                $memo = $deliveryHistorylog[$delivery['delivery_bn']][$k]['memo'];
                $memoLength = mb_strlen($memo);
                
                if ($memoLength > 400) {
                    $deliveryHistorylog[$delivery['delivery_bn']][$k]['short_memo'] = mb_substr($memo, 0, 400);
                    $deliveryHistorylog[$delivery['delivery_bn']][$k]['is_long'] = true;
                } else {
                    $deliveryHistorylog[$delivery['delivery_bn']][$k]['is_long'] = false;
                }
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
                    if($v) {
                        $orderLogs[$order['order_bn']][$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
                        
                        // 为长文本准备数据，HTML由模板处理
                        $memo = $orderLogs[$order['order_bn']][$k]['memo'];
                        $memoLength = mb_strlen($memo);
                        
                        if ($memoLength > 400) {
                            $orderLogs[$order['order_bn']][$k]['short_memo'] = mb_substr($memo, 0, 400);
                            $orderLogs[$order['order_bn']][$k]['is_long'] = true;
                        } else {
                            $orderLogs[$order['order_bn']][$k]['is_long'] = false;
                        }
                    }
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

    /**
     * [brush特殊订单]订单操作记录
     *
     * @param int $order_id
     * @return string
     */
    private function __brush_log_history($order_id)
    {
        $render = app::get('ome')->render();
        $logObj = app::get('ome')->model('operation_log');
        $ooObj = app::get('ome')->model('operations_order');
        
        /* 本订单日志 */
        $history = $logObj->read_log(array('obj_id'=>$order_id,'obj_type'=>'orders@ome'),0,-1);
        foreach($history as $k => $v){
            $data = $ooObj->getList('operation_id',array('log_id'=>$v['log_id']));
            if(!empty($data)){
                $history[$k]['flag'] ='true';
            }else{
                $history[$k]['flag'] ='false';
            }
            $history[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
            
            // 为长文本准备数据，HTML由模板处理
            $memo = $history[$k]['memo'];
            $memoLength = mb_strlen($memo);
            
            if ($memoLength > 400) {
                $history[$k]['short_memo'] = mb_substr($memo, 0, 400);
                $history[$k]['is_long'] = true;
            } else {
                $history[$k]['is_long'] = false;
            }
        }
        
        /* 发货单日志 */
        $deliveryId = kernel::database()->select('select delivery_id from sdb_brush_delivery_order where order_id = "' . $order_id . '"');
        $deliveryIds = array();
        foreach($deliveryId as $val) {
            $deliveryIds[] = $val['delivery_id'];
        }
        
        if($deliveryIds) {
            $deliverylog = $logObj->read_log(array('obj_id'=>$deliveryIds,'obj_type'=>'delivery@brush'), 0, -1);
            
            $dly_log_list = $deliveryHistorylog = array();
            foreach((array)$deliverylog as $k=>$v)
            {
                static $deliveryData = array();
                if(empty($deliveryData[$v['obj_id']])) {
                    $tmpDeliveryData = kernel::database()->select('select delivery_bn, status from sdb_brush_delivery where delivery_id="' . $v['obj_id'] . '"');
                    $deliveryData[$v['obj_id']] = $tmpDeliveryData[0];
                }
                
                if($deliveryData[$v['obj_id']]['status'] == 'cancel') {
                    $v['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
                    
                    // 为长文本准备数据，HTML由模板处理
                    $memo = $v['memo'];
                    $memoLength = mb_strlen($memo);
                    
                    if ($memoLength > 500) {
                        $v['short_memo'] = mb_substr($memo, 0, 500);
                        $v['is_long'] = true;
                    } else {
                        $v['is_long'] = false;
                    }
                    
                    $deliveryHistorylog[$deliveryData[$v['obj_id']]['delivery_bn']][] = $v;
                } else {
                    $deliverylog[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
                    
                    // 为长文本准备数据，HTML由模板处理
                    $memo = $deliverylog[$k]['memo'];
                    $memoLength = mb_strlen($memo);
                    
                    if ($memoLength > 500) {
                        $deliverylog[$k]['short_memo'] = mb_substr($memo, 0, 500);
                        $deliverylog[$k]['is_long'] = true;
                    } else {
                        $deliverylog[$k]['is_long'] = false;
                    }
                    
                    $obj_id = $v['obj_id'];
                    $dly_log_list[$obj_id]['obj_name'] = $v['obj_name'];
                    $dly_log_list[$obj_id]['list'][] = $deliverylog[$k];
                }
            }
            
            $render->pagedata['dly_log_list'] = $dly_log_list;
        }
        
        $render->pagedata['order_id'] = $order_id;
        $render->pagedata['history'] = $history;
        $render->pagedata['deliverylog'] = $deliverylog;
        $render->pagedata['deliveryHistorylog'] = $deliveryHistorylog;
        
        return $render->fetch('admin/order/detail_history.html');
    }
    
    var $column_promise_service = '物流服务标签';
    var $column_promise_service_width = 320;
    public function column_promise_service($row, $list)
    {
        $extend = $this->__getOrderExtend($list);
        
        //check
        if(empty($extend[$row['order_id']]['promise_service'])){
            return '';
        }
        
        $str = '';
        $colorList = array('#6666ff', '#336600', '#FF0000', '#FF8800', '#c64ae2', '#4ae25e', '#e2bc4a', '#668800');
        $promise_services = explode(',', $extend[$row['order_id']]['promise_service']);
        foreach ($promise_services as $key => $val)
        {
            $color = ($colorList[$key] ? $colorList[$key] : $colorList[0]);
            
            $str .= '<span class="tag-label" style="color:'. $color .'"> '. $val .'</span>';
        }
        
        return $str;
    }
    
    var $column_is_not_combine = '是否允许审核';
    var $column_is_not_combine_width = 230;
    public function column_is_not_combine($row)
    {
        if($row[$this->col_prefix.'is_not_combine'] > 0){
            // 加载不允许审核标签
            $str = kernel::single('ome_order_combine_const')->getIdentifier($row[$this->col_prefix.'is_not_combine']);
            
            $prefix_str = sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#ffffff;'>%s</span>", '不允许审核', '#FF1493', '否');
            
            return $prefix_str . $str;
        }else{
            $str = sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#ffffff;'>%s</span>", '', '#00CD66', '是');
            
            return $str;
        }
    }
    
    /**
     * 配送方式映射展示
     */
    var $column_shipping_name = '配送方式';
    var $column_shipping_name_width = 120;
    var $column_shipping_name_order = 35;
    public function column_shipping_name($row, $list)
    {
        $shipping_code = $row[$this->col_prefix.'shipping'];
        if(empty($shipping_code)){
            return '-';
        }
        
        // 从 corp.php 获取配送方式映射
        $corpObj = app::get('ome')->model('dly_corp');
        
        // 尝试从不同配送模式中查找配送方式名称
        $shippingName = $corpObj->corp_default('instatnt'); // 先查找同城配送
        if(isset($shippingName[$shipping_code])){
            return $shippingName[$shipping_code]['name'];
        }
        
        $shippingName = $corpObj->corp_default('seller'); // 再查找商家配送
        if(isset($shippingName[$shipping_code])){
            return $shippingName[$shipping_code]['name'];
        }
        
        // 如果都找不到，直接返回原始配送代码
        return $shipping_code;
    }
    
    /**
     * 平台建议信息
     *
     * @param $order_id
     * @return string
     */
    public function detail_platform_info($order_id)
    {
        $render = app::get('ome')->render();
        
        $orderMdl = app::get('ome')->model('orders');
        $orderObjMdl = app::get('ome')->model('order_objects');
        $orderExtendMdl = app::get('ome')->model('order_extend');
        
        $logiLib = kernel::single('logisticsmanager_waybill_pdd');
        
        // order_id
        $order_id = intval($order_id);
        
        // filter
        $base_filter = array('order_id'=>$order_id);
        
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }
        
        // orderInfo
        $orderInfo = $orderMdl->dump($base_filter, '*');
        if(empty($orderInfo)){
            $error_msg = '订单不存在，或者账号无权访问该订单平台建议信息';
            return '<div style="padding:16px;color:#c00;">' . htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        
        //订单明细信息
        $orderObjList = $orderObjMdl->getList('*', array('order_id'=>$order_id));
        
        //订单扩展信息
        $extendInfo = $orderExtendMdl->dump($order_id);
        
        //extend_field
        $extend_fields = array();
        if($extendInfo['extend_field']){
            $extend_fields = json_decode($extendInfo['extend_field'], true);
        }
        
        //预估发货快递白名单
        if($extendInfo['white_delivery_cps']){
            $logiCodes = json_decode($extendInfo['white_delivery_cps'], true);
            
            foreach ($logiCodes as $key => $logi_code){
                $logiInfo = $logiLib->logistics($logi_code);
                
                //[兼容]物流公司编码不正确
                if(empty($logiInfo)){
                    $logiInfo = array('code'=>$logi_code, 'name'=>'未定义');
                }
                
                $extend_fields['white_logis'][] = $logiInfo;
            }
        }
        
        //预估发货快递黑名单
        if($extendInfo['black_delivery_cps']){
            $logiCodes = json_decode($extendInfo['black_delivery_cps'], true);
            
            foreach ($logiCodes as $key => $logi_code){
                $logiInfo = $logiLib->logistics($logi_code);
                
                //[兼容]物流公司编码不正确
                if(empty($logiInfo)){
                    $logiInfo = array('code'=>$logi_code, 'name'=>'未定义');
                }
                
                $extend_fields['black_logis'][] = $logiInfo;
            }
        }
        
        $render->pagedata['orderInfo'] = $orderInfo;
        $render->pagedata['orderObjList'] = $orderObjList;
        $render->pagedata['extendInfo'] = $extendInfo;
        $render->pagedata['extend_fields'] = $extend_fields;
        
        return $render->fetch('admin/order/detail_platform_info.html');
    }
    
    private function _getShop($shop_id, $list)
    {
        static $shopList;
        
        if (isset($shopList)) {
            return $shopList[$shop_id];
        }
        
        $shopList = app::get('ome')->model('shop')->getList('shop_id,shop_bn',[
            'shop_id' => array_column($list, $this->col_prefix.'shop_id'),
        ]);
        $shopList = array_column($shopList, null, 'shop_id');
        
        return $shopList[$shop_id];
    }
    
    private function _getMemberMobile($order_bn, $list)
    {
        static $epList;
        
        if (isset($epList)) {
            return $epList[$order_bn];
        }
        
        $epList = [];
        
        $memberList = app::get('ome')->model('members')->getList('member_id,uname,mobile', [
            'member_id' => array_column($list, $this->col_prefix.'member_id'),
        ]);
        $memberList = array_column($memberList, null, 'member_id');
        
        
        $syOrderList = app::get('epassport')->model('syorders')->getList('order_sn,plat_account', [
            'order_sn' => array_column($list, $this->col_prefix.'order_bn'),
        ]);
        $syOrderList = array_column($syOrderList, null, 'order_sn');
        
        foreach ($list as $key => $value) {
            
            switch ($value[$this->col_prefix.'shop_type']) {
                case 'taobao':
                    $epList[$value[$this->col_prefix.'order_bn']] = $syOrderList[$value[$this->col_prefix.'order_bn']]['plat_account'];
                    break;
                case '360buy':
                    $epList[$value[$this->col_prefix.'order_bn']] = $memberList[$value[$this->col_prefix.'member_id']]['uname'];
                    break;
                default:
                    $epList[$value[$this->col_prefix.'order_bn']] = $memberList[$value[$this->col_prefix.'member_id']]['mobile'];
                    break;
            }
        }
        
        return $epList[$order_bn];
    }
}
?>
