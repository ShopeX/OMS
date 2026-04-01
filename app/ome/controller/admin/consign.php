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

class ome_ctl_admin_consign extends desktop_controller{

    var $name = "发货处理";
    var $workground = "delivery_center";

    function _views() {

        $mdl_order = $this->app->model('orders');

        $base_filter = $this->getFilters();

        $sub_menu[0] = array('label' => app::get('base')->_('发货失败'), 'filter' => array_merge($base_filter, array('createway'=>'matrix','sync' => 'fail')), 'optional' => false,'addon' => '_FILTER_POINT_');
        
        $sub_menu[1] = array('label' => app::get('base')->_('换货订单回写失败'),'filter' => array_merge($base_filter, array('createway' => array('after'),'sync'=>'fail','order_type'=>'normal')),'optional' => false);
        
        $sub_menu[2] = array('label' => app::get('base')->_('回写未发起'),'filter' => array_merge($base_filter,array('createway' => 'matrix','sync' => 'none' ,'ship_status' => '1')),'optional' => false);
        $sub_menu[3] = array('label' => app::get('base')->_('发货中'), 'filter' => array_merge($base_filter, array('createway'=>'matrix','sync' => 'run')), 'optional' => false);
        $sub_menu[4] = array('label' => app::get('base')->_('全部'), 'filter' => $base_filter, 'optional' => false);
        //$sub_menu[5] = array('label' => app::get('base')->_('京东发货失败'), 'filter' => array_merge($base_filter, array('createway'=>'matrix','sync' => 'fail','shop_type'=>'360buy')), 'optional' => false);
        //$sub_menu[6] = array('label' => app::get('base')->_('回写参数错误'), 'filter' => array_merge($base_filter, array('createway' => 'matrix','sync' => 'fail','sync_fail_type' => 'params')), 'optional' => false);
        //$sub_menu[7] = array('label' => app::get('base')->_('前端已发货'), 'filter' => array_merge($base_filter, array('createway' => 'matrix','sync' => 'fail','sync_fail_type' => 'shipped')), 'optional' => false);
        $sub_menu[8] = array('label' => app::get('base')->_('发货成功'), 'filter' => array_merge($base_filter, array('createway' => 'matrix','sync' => 'succ')), 'optional' => false);
        $sub_menu[9] = array('label' => app::get('base')->_('不予回写'),'filter' => array_merge($base_filter, array('createway' => array('local','import'))),'optional' => false);
        
        $sub_menu[11] = array('label' => app::get('base')->_('物流错误'), 'filter' => array_merge($base_filter, array('createway'=>'matrix', 'sync'=>'fail', 'sync_fail_type'=>'logistics')), 'optional'=>false);
        $sub_menu[12] = array('label' => app::get('base')->_('补寄申请单发货失败'), 'filter' => array_merge($base_filter, array('createway'=>'after', 'sync'=>'fail', 'order_type'=>'bufa')), 'optional'=>false);
        
        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $v['addon'] ? $v['addon'] : $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&flt=' . $_GET['flt'] . '&view=' . $k;
        }

        return $sub_menu;
    }
    
    /**
     * 极速发货
     * 
     * @param void
     * @return void
     */
    function fast_consign()
    {
        $_GET['view'] = intval($_GET['view']);
        $op_id = kernel::single('desktop_user')->get_id();
        switch ($_GET['view']) {
            case '0':
                $action = array(
                    array('label' => '批量发货', 'submit' => $this->url.'&act=dailog_delivery_confirm','target'=>'dialog::{width:800,height:200,title:\'批量发货\'}"'),
                    array('label' => '已回写成功', 'submit' => 'index.php?app=ome&ctl=admin_consign&act=batch_sync_succ', 'confirm' => "这些订单系统认为都是在前台(淘宝、京东等)已经发货，请确认这些订单前端已经发货！！！\n\n警告：本操作不会再同步发货状态，并不可恢复，请谨慎使用！！！", 'target' => 'refresh'),
                );
                break;
            case '1':
                $action = array(
                    array('label' => '批量发货', 'submit' => $this->url . 'index.php?app=ome&ctl=admin_consign&act=batch_change_sync', 'confirm' => '你确定要对勾选的订单进行发货操作吗？', 'target' => 'refresh'),
                    array('label' => '已回写成功', 'submit' => 'index.php?app=ome&ctl=admin_consign&act=batch_sync_succ', 'confirm' => "这些订单系统认为都是在前台(淘宝、京东等)已经发货，请确认这些订单前端已经发货！！！\n\n警告：本操作不会再同步发货状态，并不可恢复，请谨慎使用！！！", 'target' => 'refresh'),
                );
                break;
            case '2':
            case '3':
            case '5':
            case '6':
            case '7':
            case '11':
                $action = array(
                    array('label' => '批量发货', 'submit' => $this->url.'&act=dailog_delivery_confirm','target'=>'dialog::{width:800,height:200,title:\'批量发货\'}"'),

                    array('label' => '已回写成功', 'submit' => 'index.php?app=ome&ctl=admin_consign&act=batch_sync_succ', 'confirm' => "这些订单系统认为都是在前台(淘宝、京东等)已经发货，请确认这些订单前端已经发货！！！\n\n警告：本操作不会再同步发货状态，并不可恢复，请谨慎使用！！！", 'target' => 'refresh'),
                );
                break;
            case '12':
                $action = array(
                    array('label' => '批量重试发货', 'submit' => 'index.php?app=ome&ctl=admin_consign&act=batch_retry_reshipping', 'confirm' => '你确定要对勾选的补寄订单进行重试发货操作吗？', 'target' => 'refresh'),
                    array('label' => '已回写成功', 'submit' => 'index.php?app=ome&ctl=admin_consign&act=batch_sync_succ', 'confirm' => "这些订单系统认为都是在前台(淘宝、京东等)已经发货，请确认这些订单前端已经发货！！！\n\n警告：本操作不会再同步发货状态，并不可恢复，请谨慎使用！！！", 'target' => 'refresh'),
                );
                break;
            default:
                break;
        }
        
        //修改物流
        if($_GET['view'] == '11'){
            $action[] = array(
                    'label' => '批量修改物流公司',
                    'submit' => $this->url.'&act=batch_edit_logistics',
                    'target' => 'dialog::{width:700,height:300,title:\'批量修改物流公司\'}"',
            );
        }
        
        //params
        $params = array(
            'title' => '需发货订单',
            'actions' => $action,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'use_view_tab' => true,
            'finder_aliasname' => 'order_consign_fast'.$op_id,
            'finder_cols' => '_func_0,column_confirm,order_bn,column_sync_status,column_print_status,logi_id,logi_no,column_deff_time,member_id, ship_name,ship_area,total_amount',
            'base_filter' => $this->getFilters(),
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ]
        );
        $this->finder('ome_mdl_orders', $params);
    }
    
    /**
     * 批量消除冲突
     *
     * @param void
     * @return void
     */
    function batch_sync_succ() {
        $ids = $_REQUEST['order_id'];

        if (!empty($ids)) {
            $orderObj = $this->app->model('orders');
            $data = array('sync'=>'succ','sync_fail_type'=>'none');
            $filter = array('order_id'=>$ids,'createway' => ['matrix', 'after']);
            $orderObj->update($data,$filter);

            //记录日志
            $logObj = $this->app->model('operation_log');
            $logObj->batch_write_log('order_modify@ome',$filter,'手动设为同步成功',time());
        }
        $this->splash('success', null, '命令已经被成功发送！');
    }

    /**
     * 批量发货
     *
     * @param void
     * @return void
     */
    function batch_sync() {

        $this->begin('');
        $ids = $_REQUEST['order_id'];
        
        if (!empty($ids)) {

            kernel::single('ome_event_trigger_shop_delivery')->delivery_confirm_retry($ids);
        }
        $this->end(true, '命令已经被成功发送！！');
    }
    
    function getFilters()
    {
        $base_filter = array();
        $base_filter['status'] = array('active', 'finish');
        $base_filter['order_confirm_filter'] = "sdb_ome_orders.ship_status IN('1', '2') AND logi_no <> ''";
        
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }
        
        return $base_filter;
    }
    
    /**
     * 天猫换货订单 发货重试
     * @DateTime  2018-02-07T11:50:29+0800
     * @return
     */
    public function batch_change_sync(){
        $ids = $_REQUEST['order_id'];
        
        if (!empty($ids)) {
            foreach($ids as $order_id){
                kernel::single('ome_service_aftersale')->exchange_consigngoods($order_id);
            }
        }
        $this->splash('success', null, '命令已经被成功发送！！');
    }

    public function dailog_delivery_confirm()
    {
        $_POST['sync'] = ['fail', 'run', 'none'];

        $orderMdl = app::get('ome')->model('orders');
        $order_list = $orderMdl->getList('order_id', $_POST);

        $order_id = array_column($order_list, 'order_id');
        
        $this->pagedata['GroupList']   = json_encode($order_id);

        $this->pagedata['request_url'] = $this->url . '&act=ajax_delivery_confirm';

        parent::dialog_batch();
    }
    
    /**
     * Ajax重试回传平台发货状态
     *
     * @return void
     */
    public function ajax_delivery_confirm()
    {
        $order_id = explode(',', $_POST['primary_id']);
        if (!$order_id) { echo 'Error: 请先选择订单';exit;}

        $retArr  = array(
            'itotal'    => count($order_id),
            'isucc'     => count($order_id),
            'ifail'     => 0,
            'err_msg'   => array(),
        );

        kernel::single('ome_event_trigger_shop_delivery')->delivery_confirm_retry($order_id);

        echo json_encode($retArr),'ok.';exit;
    }
    
    /**
     * 批量修改物流公司
     */
    public function batch_edit_logistics()
    {
        $orderObj = app::get('ome')->model('orders');
        
        $ids = $_POST['order_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(count($ids) > 500){
            die('每次最多只能选择500条!');
        }
        
        //物流公司列表
        $sql = "SELECT corp_id,type,name FROM sdb_ome_dly_corp WHERE 1";
        $tempList = $orderObj->db->select($sql);
        
        $logiList = array();
        foreach ($tempList as $key => $val)
        {
            if(in_array($val['type'], array('o2o_pickup', 'o2o_ship'))){
                continue;
            }
            
            $logiList[] = $val;
        }
        
        //pagedata
        $this->pagedata['logiList'] = $logiList;
        $this->pagedata['GroupList'] = $ids;
        $this->pagedata['request_url'] = 'index.php?app=ome&ctl=admin_consign&act=ajaxEditLogistics';
        $this->pagedata['custom_html'] = $this->fetch('admin/order/edit_logistics.html');
        
        //调用desktop公用进度条
        parent::dialog_batch('ome_mdl_orders', false, 10, 10);
    }
    
    /**
     * 修改物流公司
     **/
    public function ajaxEditLogistics()
    {
        $orderObj = app::get('ome')->model('orders');
        $operLogObj = app::get('ome')->model('operation_log');
        
        //获取订单号
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择订单';
            exit;
        }
        
        //物流公司ID
        $corp_id = $_POST['corp_id'];
        if(empty($corp_id)){
            echo 'Error: 请先选择物流公司';
            exit;
        }
        
        $retArr = array(
                'itotal'  => 0,
                'isucc'   => 0,
                'ifail'   => 0,
                'err_msg' => array(),
        );
        
        //物流公司列表
        $sql = "SELECT corp_id,type,name FROM sdb_ome_dly_corp WHERE corp_id=".$corp_id;
        $corpInfo = $orderObj->db->selectrow($sql);
        if(empty($corpInfo)){
            echo 'Error: 没有找到物流公司';
            exit;
        }
        
        //订单列表
        $list = $orderObj->getList('order_id,order_bn,ship_status,createway,sync', $postdata['f'], $postdata['f']['offset'], $postdata['f']['limit']);
        
        //count
        $retArr['itotal'] = count($list);
        
        foreach ((array)$list as $key => $val)
        {
            $order_id = $val['order_id'];
            
            //check
            if($val['ship_status'] != '1'){
                
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = $val['order_bn'].'订单不是已发货状态';
                
                continue;
            }
            
            if($val['createway'] != 'matrix'){
                
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = $val['order_bn'].'订单不是平台下来的';
                
                continue;
            }
            
            if($val['sync'] != 'fail'){
                
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = $val['order_bn'].'订单不是回传失败的状态';
                
                continue;
            }
            
            //关联发货单(只获取已发货的发货单)
            $sql = "SELECT b.delivery_id FROM sdb_ome_delivery_order AS a ";
            $sql .= " LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id  WHERE a.order_id=". $order_id ." AND b.status='succ'";
            $deliveryList = $orderObj->db->select($sql);
            if(empty($deliveryList)){
                
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = $val['order_bn'].'没有找到已发货的发货单';
                
                continue;
            }
            
            $delivery_ids = array_column($deliveryList, 'delivery_id');
            
            //[发货单]更新物流公司
            $update_sql = "UPDATE sdb_ome_delivery SET logi_id='%d', logi_name='%s' WHERE delivery_id IN(". implode(',', $delivery_ids) .")";
            $update_sql = sprintf($update_sql, $corpInfo['corp_id'], $corpInfo['name']);
            $orderObj->db->exec($update_sql);
            
            //[订单]更新物流公司
            $update_sql = "UPDATE sdb_ome_orders SET logi_id='%d' WHERE order_id='%d'";
            $update_sql = sprintf($update_sql, $corpInfo['corp_id'], $order_id);
            $orderObj->db->exec($update_sql);
            
            //log
            $operLogObj->write_log('order_edit@ome', $order_id, '修改订单和发货单的物流公司为：'.$corpInfo['name']);
            
            //succ
            $retArr['isucc'] += 1;
        }
        
        echo json_encode($retArr),'ok.';
        exit;
    }
    
    /**
     * 批量设置不回写
     *
     * @param void
     * @return void
     */
    function batch_nosync() {
        $this->begin('');
        $ids = $_REQUEST['order_id'];

        if (!empty($ids)) {
            $orderObj = $this->app->model('orders');
            $data = array('sync'=>'nosync');
            $filter = array('order_id'=>$ids,'createway' => 'matrix','sync'=>'fail');
            $orderObj->update($data,$filter);
            $logfiter = array('order_id'=>$ids);
            //记录日志
            $logObj = $this->app->model('operation_log');
            $logObj->batch_write_log('order_modify@ome',$logfiter,'不予回写',time());
        }
        $this->end(true, '命令已经被成功发送！');
    }
    
    /**
     * 批量重试补寄发货
     * 参考换货订单回写失败的重试方式
     *
     * @param void
     * @return void
     */
    public function batch_retry_reshipping() {
        $ids = $_REQUEST['order_id'];
        
        if (empty($ids)) {
            $this->splash('error', null, '请选择要重试的订单');
        }
        
        $orderObj = $this->app->model('orders');
        $trigger = kernel::single('ome_event_trigger_reshipping_delivery');
        
        // 获取补寄订单信息
        $orders = $orderObj->getList('order_id,order_bn', array('order_id' => $ids, 'order_type' => 'bufa'));
        if (empty($orders)) {
            $this->splash('error', null, '未找到补寄订单');
        }
        
        foreach ($orders as $order) {
            // 获取发货单ID（参考 exchange_consigngoods 的实现方式）
            $sql = "SELECT d.delivery_id FROM sdb_ome_delivery_order AS dord
                    LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                    WHERE dord.order_id=" . $order['order_id'] . " AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false' AND d.status IN('succ')";
            $delivery_detail = $orderObj->db->selectrow($sql);
            
            if (empty($delivery_detail) || empty($delivery_detail['delivery_id'])) {
                continue;
            }
            
            // 调用 afterDelivery 方法重试
            $trigger->afterDelivery($delivery_detail['delivery_id']);
        }
        
        $this->splash('success', null, '命令已经被成功发送！！');
    }
}
