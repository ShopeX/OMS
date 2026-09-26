<?php
/**
 * 审批流实例Finder类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_finder_workflow_case {
    public $addon_cols = "id,status,original_bn,scene_type,current_node_type,bpm_status";
    
    public $column_edit = '操作';
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    public function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        $id = $row[$this->col_prefix.'id'];
        $scene_type = ($row[$this->col_prefix.'scene_type'] ? $row[$this->col_prefix.'scene_type'] : $row['scene_type']);
        
        // audit button
        $audit_button = '';
        if ($scene_type == 'add_gift') {
            $url = sprintf('index.php?app=ticket&ctl=admin_workflow_case&act=audit&p[0]=%s&finder_id=%s', $id, $finder_id);
            $audit_button = '<a href="'. $url .'" target="dialog::{width:800,height:550,title:\'审批单据号：'. $row[$this->col_prefix.'original_bn'] .'\'}">审批</a>';
        }else{
            $url = sprintf('index.php?app=ticket&ctl=admin_workflow_case&act=audit_bufa&p[0]=%s&finder_id=%s', $id, $finder_id);
            $audit_button = '<a href="'. $url .'" target="dialog::{width:800,height:550,title:\'审批补发单据号：'. $row[$this->col_prefix.'original_bn'] .'\'}">审批补发单</a>';
        }
        
        // button
        if($row[$this->col_prefix.'current_node_type'] == 'end'){
            $button = '';
        }elseif(in_array($row[$this->col_prefix.'status'], ['rejected','cancelled','source_cancelled'])){
            $button = '';
        }elseif(($row[$this->col_prefix.'bpm_status'] ?? '') == 'pending'){
            $button = '';
        }else{
            $button = $audit_button;
        }
        
        return $button;
    }
    
    var $detail_basic = '详细信息';
    public function detail_basic($id) {
        $render = app::get('ticket')->render();

        $model = app::get('ticket')->model('workflow_case');
        $row = $model->dump($id);

        // 解析 items_context JSON 数据
        if (!empty($row['items_context'])) {
            $items_data = json_decode($row['items_context'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($items_data)) {
                $row['items_list'] = $items_data;
            } else {
                $row['items_list'] = array();
            }
        } else {
            $row['items_list'] = array();
        }

        if (($row['scene_type'] ?? '') == 'approve_orders') {
            $workflowCaseLib = kernel::single('ticket_workflow_case');
            $result = $workflowCaseLib->getOrderDetails($row);
            if ($result['rsp'] == 'succ') {
                $row['items_list'] = $result['data']['approvalItemList'] ?? array();
                $row['order_retail_amount'] = $result['data']['order_retail_amount'] ?? ($row['order_retail_amount'] ?? 0);
            }
        }
        
        // 去除补发原因中的固定文字
        if($row['reason']){
            $row['reason'] = str_replace('零元订单补发：', '', $row['reason']);
        }
        
        $render->pagedata['data'] = $row;
        return $render->fetch('admin/workflow/case/detail.html');
    }
    
    var $detail_order_basic = '订单基本信息';
    function detail_order_basic($id)
    {
        $render = app::get('ome')->render();
        $orderMdl = app::get('ome')->model('orders');
        $objExtendMdl = app::get('ome')->model('order_objects_extend');
        $oOperation_log = app::get('ome')->model('operation_log');
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        //post
        if($_POST){
            die('请到订单栏目里操作，这里不允许提交数据!');
        }
        
        //order
        $caseInfo = $caseMdl->dump($id, '*');
        $order_id = $caseInfo['original_id'];
        
        //detail
        //$order_detail = $orderMdl->dump($order_id,"*",array("order_items"=>array("*")));
        $order_detail = $orderMdl->dump($order_id, "*");
        
//        //订单object层商品定制信息
//        $tempList = $objExtendMdl->getList('*', array('order_id'=>$order_id), 0, -1, 'obj_id DESC');
//        $itemList = [];
//        if($tempList){
//            foreach ($tempList as $tempKey => $tempVal)
//            {
//                $obj_order_id = $tempVal['order_id'];
//
//                if(empty($tempVal['customization'])){
//                    continue;
//                }
//
//                $customInfo = json_decode($tempVal['customization'], true);
//                if(empty($customInfo['items'])){
//                    continue;
//                }
//
//                $itemList = array_merge($itemList, $customInfo['items']);
//            }
//        }
        
        //销售价权限验证
        $showSalePrice = true;
        if (!kernel::single('desktop_user')->has_permission('sale_price')) {
            $showSalePrice = false;
        }
        
        // 判断是否加密
        $order_detail['is_encrypt'] = kernel::single('ome_security_router', $order_detail['shop_type'])->show_encrypt($order_detail, 'order');
    
        $order_detail['mark_text'] = kernel::single('ome_func')->format_memo($order_detail['mark_text']);
        $order_detail['custom_mark'] = kernel::single('ome_func')->format_memo($order_detail['custom_mark']);
    
        $render->pagedata['total_amount'] = floatval($order_detail['total_amount']);
        $render->pagedata['payed'] = floatval($order_detail['payed']);
        
        //$render->pagedata['is_hied_receiver_copy'] = true;
        $render->pagedata['show_sale_price'] = $showSalePrice;
        $render->pagedata['total_amount'] = floatval($order_detail['total_amount']);
        $render->pagedata['payed'] = floatval($order_detail['payed']);
        $render->pagedata['order'] = $order_detail;
        //$render->pagedata['itemList'] = $itemList;
        
        return $render->fetch('admin/order/detail_basic.html');
    }
    
    var $detail_goods = '订单明细';
    public function detail_goods($id)
    {
        $render = app::get('ome')->render();
        $oOrder = app::get('ome')->model('orders');
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        //order
        $caseInfo = $caseMdl->dump($id, '*');
        $order_id = $caseInfo['original_id'];
        
        //detail
        $item_list = $oOrder->getItemList($order_id,true);
        if(empty($item_list)){
            die('没有订单商品明细...');
        }
        
        $item_list = ome_order_func::add_getItemList_colum($item_list);
        ome_order_func::order_sdf_extend($item_list);
        $orders = $oOrder->getRow(array('order_id'=>$order_id),'order_id,shop_type,order_source,process_status');
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
        
        $render->pagedata['is_host'] = $is_host;
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
    
    var $detail_log = '操作日志';
    public function detail_log($id)
    {
        $render = app::get('ticket')->render();
        
        //log
        $operLogMdl = app::get('ome')->model('operation_log');
        $logList = $operLogMdl->read_log(array('obj_id'=>$id, 'obj_type'=>'workflow_case@ticket'), 0, -1);
        
        $render->pagedata['logList'] = $logList;
        
        return $render->fetch('admin/detail_log.html');
    }
}