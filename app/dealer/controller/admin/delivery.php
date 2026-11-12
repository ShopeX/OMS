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
 * 分销发货单列表
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.28
 */
class dealer_ctl_admin_delivery extends desktop_controller
{
    var $name = "代发发货单";
    var $workground = "order_center";
    
    private $_businessLib = null;
    
    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */

    public function __construct($app)
    {
        parent::__construct($app);
        
        //lib
        $this->_businessLib = kernel::single('dealer_business');
    }
    
    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $this->title = $this->name;
        $base_filter = $this->getFilters();
        $actions = array();
        
        $user = kernel::single('desktop_user');
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        
        switch ($_GET['view']) {
            case '0':
            case '7':
                $actions[] = array(
                    'label' => '发送至第三方',
                    'submit' => $this->url.'&act=batchSyncWms',
                    'target'=>'dialog::{width:600,height:200,title:\'批量对勾选的发货单发送至WMS仓储\'}"',
                );
                
                $actions[] = array(
                    'label' => '批量撤销发货单',
                    'submit' => $this->url.'&act=batchDeliveryCancel',
                    'target'=>'dialog::{width:600,height:200,title:\'撤销当前发货单后,订单的确认状态将随之改变,确定要取消吗？\'}"',
                );
                break;
            case '6':
                $actions[] = array(
                    'label'   => app::get('ome')->_('查询发货单'),
                    'submit'  => "index.php?app=console&ctl=admin_delivery&act=toBatchSearch",
                    'target'  => 'dialog::{width:600,height:250,title:\'查询发货单\'}',
                );
                break;
            case '1':
                $actions[] = array(
                    'label' => '更新WMS签收状态',
                    'submit' => 'index.php?app=console&ctl=admin_delivery&act=batch_sign',
                    'confirm' => '你确定要对勾选的发货单更新WMS发货状态为签收吗？',
                    'target' => 'refresh',
                );
                break;
        }
        
        if($user->has_permission('console_process_receipts_print_export')){
            if ($_GET['view'] == '0') {
                $query_status = 'progress';
            }elseif($_GET['view'] == '2'){
                $query_status = 'succ';
            }
            
            $actions[] =  array(
                'label'=>'导出',
                'submit'=>'index.php?app=omedlyexport&ctl=ome_delivery&act=index&action=export&status='.$query_status,
                'target'=>'dialog::{width:600,height:300,title:\'导出\'}'
            );
        }
        
        //params
        $params = array(
            'title' => $this->title,
            'actions' => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'use_view_tab' => true,
            'base_filter' => $base_filter,
        );
        
        $this->finder('dealer_mdl_delivery', $params);
    }
    
    /**
     * _views
     * @return mixed 返回值
     */
    public function _views()
    {
        //filter
        $base_filter = $this->getFilters();
        
        //menu
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('待发货'),'filter'=>array('process'=>array('false'),'status'=>array('progress','ready')),'optional'=>false),
            1 => array('label'=>app::get('base')->_('全部'),'filter'=>array('status' => array('ready','progress','succ')),'optional'=>false),
        );
        
        $branch_ids = $this->getSearchBranchids();
        if($branch_ids) {
            $sub_menu[6] = array('label'=>app::get('base')->_('京仓待发货'),'filter'=>array('process'=>array('false'),'status'=>array('progress','ready'),'branch_id'=>$branch_ids),'optional'=>false);
            $sub_menu[7] = array('label'=>app::get('base')->_('京仓失败'),'filter'=>array('process'=>array('false'),'status'=>array('progress','ready'),'branch_id'=>$branch_ids,'sync_status'=>array('9')),'optional'=>false);
        }
        
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }
        
        foreach($sub_menu as $k=>$v)
        {
            if (isset($v['filter'])){
                $v['filter'] = array_merge($base_filter, $v['filter']);
            }
            
            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = 'showtab';
            $sub_menu[$k]['href'] = 'index.php?app='. $_GET['app'] .'&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$k;
        }
        
        return $sub_menu;
    }
    
    /**
     * 公共filter条件
     * 
     * @return array
     */
    public function getFilters()
    {
        $base_filter = array(
            'type' => array('normal'),
            'pause' => 'false',
            'parent_id' => 0,
            'disabled' => 'false',
        );
        
        //获取操作人员的企业组织架构ID权限
        $cosData = $this->_businessLib->getOperationCosIds();
        if($cosData[1]){
            $base_filter['cos_id'] = $cosData[1];
        }else{
            $base_filter['cos_id|than'] = 0; //组织权限
            //$base_filter['betc_id|than'] = 0; //贸易公司ID
        }
        
        return $base_filter;
    }
    
    /**
     * 发送至第三方
     */
    function batch_sync()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $this->begin('');
        kernel::database()->exec('commit');
        
        $deliveryObj = app::get('ome')->model('delivery');
        
        $ids = $_POST['delivery_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false, '不能使用全选功能,每次最多选择500条!');
        }
        
        if(count($ids) > 500){
            $this->end(false, '每次最多只能选择500条!');
        }
        
        if (!empty($ids)) {
            //获取京东一件代发的仓库列表
            $branchLib = kernel::single('ome_branch');
            $wms_type = 'yjdf';
            $error_msg = '';
            $yjdfBranchList = $branchLib->getWmsBranchIds($wms_type, $error_msg);
            
            //list
            $error_msg = '';
            $existRefund = array();
            $syncDlyIds = array();
            foreach ($ids as $deliveryid) {
                $filter = array(
                    'delivery_id'=>$deliveryid,
                    'stock_status'=>'false',
                    'deliv_status'=>'false',
                    'expre_status'=>'false',
                    'pause'=>'false',
                    'process'=>'false',
                    'status'=>array('progress','ready'),
                );
                $delivery = $deliveryObj->dump($filter, 'delivery_bn,sync_status,branch_id,original_delivery_bn');
                if(empty($delivery)){
                    continue;
                }
                
                //京东一件代发WMS仓库
                $branch_id = $delivery['branch_id'];
                $isYjdfFlag = ($yjdfBranchList[$branch_id] ? true : false);
                
                //已经有第三方订单号,不允许重复推送
                if($isYjdfFlag && $delivery['original_delivery_bn']){
                    continue;
                }
                
                //推送失败的发货单,检查订单是否已经申请退款
                if($delivery['sync_status'] == '2' || $isYjdfFlag){
                    //订单信息
                    //@todo：发货单未同步第三方仓储，并且申请退款的订单直接取消发货单
                    $sql = "SELECT b.order_id, b.process_status, b.status, b.pay_status FROM `sdb_ome_delivery_order` AS a
                            LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.delivery_id=". $deliveryid;
                    $orderInfo = $deliveryObj->db->selectrow($sql);
                    if(in_array($orderInfo['pay_status'], array('5', '6', '7'))){
                        $existRefund[] = $delivery['delivery_bn'];
                    }
                }
                
                //推送的发货单
                $syncDlyIds[] = $deliveryid;
                
                //request
                ome_delivery_notice::create($deliveryid);
            }
            
            //错误信息
            if($existRefund){
                $error_msg .= '发货单号：'.  implode(',', $existRefund) .'已经申请退款或已退款,请检查!';
            }
            
            //没有可推送的发货单
            if(empty($syncDlyIds)){
                $error_msg .= '没有可推送的发货单(默认会过滤已有第三方单号、有退款的发货单)';
            }
            
            if($error_msg){
                $this->end(false, $error_msg);
            }
        }
        
        $this->end(true, '命令已经被成功发送！！');
    }
    
    /**
     * 撤销订单
     */
    function pauseorder()
    {
        $is_super = kernel::single('desktop_user')->is_super();
        if ($is_super) {
            
            $this->page('admin/pauseorder.html');
        }else{
            echo '非管理员不可操作';
        }
    }
    
    /**
     * back
     * @return mixed 返回值
     */
    public function back(){
        $this->begin();
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $this->end(false, '非超级管理员不可操作');
        }
        if (empty($_POST['select_bn']) && empty($_POST['bn_select'])){
            $this->end(false, '请输入正确的单号');
        }
        $autohide = array('autohide'=>3000);
        $Objdly  = app::get('ome')->model('delivery');
        $OiObj  = app::get('ome')->model('delivery_items');
        $ObjdlyOrder  = app::get('ome')->model('delivery_order');
        
        if($_POST['select_bn']=='order_bn'){
            $select_type = 'order_bn';
            $detail = $Objdly->getDeliveryByOrderBn($_POST['bn_select']);
            if (!$detail) {
                $this->end(false, '发货单未生成 不走此流程', '', $autohide);
            }
            
            $detail['consignee']['name'] = $detail['ship_name'];
            $detail['consignee']['area'] = $detail['ship_area'];
            $detail['consignee']['province'] = $detail['ship_province'];
            $detail['consignee']['city'] = $detail['ship_name'];
            $detail['consignee']['district'] = $detail['ship_district'];
            $detail['consignee']['addr'] = $detail['ship_addr'];
            $detail['consignee']['zip'] = $detail['ship_zip'];
            $detail['consignee']['telephone'] = $detail['ship_telephone'];
            $detail['consignee']['mobile'] = $detail['ship_mobile'];
            $detail['consignee']['email'] = $detail['ship_email'];
            $detail['consignee']['r_time'] = $detail['ship_name'];
        }
        
        $items = $OiObj->getList('*',array('delivery_id'=>$detail['delivery_id']));
        
        if(empty($detail)){
            $this->end(false, '没有该单号的发货单', '', $autohide);
        }
        
        if($detail['status'] == 'back'){
            $this->end(false, '该发货单已经被打回，无法继续操作', '', $autohide);
        }
        
        #获取订单
        $order_bn = $ObjdlyOrder->getOrderInfo('order_bn',$detail['delivery_id']);
        
        if($detail['status'] == 'cancel'){
            $this->end(false, '该发货单已经被取消，无法继续操作'."<br>".'订单号:'.$order_bn[0]['order_bn'], '', $autohide);
        }
        
        if($detail['delivery_logi_number'] > 0){
            $this->end(false, '该发货单已部分发货，无法继续操作', '', $autohide);
        }
        
        if($detail['pause'] == 'true'){
            $this->end(false, '该发货单已暂停，无法继续操作', '', $autohide);
        }
        
        if($detail['process'] == 'true'){
            $this->end(false, '该发货单已经发货，无法继续操作', '', $autohide);
        }
        
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        foreach($items as $k=>$value)
        {
            $barcode    = $basicMaterialLib->getBasicMaterialCode($value['product_id']);
            $items[$k]['barcode'] = $barcode;
        }
        
        if(($detail['stock_status']=='true') || ($detail['deliv_status']=='true') || ($detail['expre_status']=='true')){
            $this->pagedata['is_confirm'] = true;
        }
        if($detail['is_bind']=='true'){
            $countinfo = $Objdly->getList('count(parent_id)',array('parent_id'=>$detail['delivery_id']));
            $count = $countinfo[0]['count(parent_id)'];
            $this->pagedata['height'] = 372+26*$count;
        }
        $this->pagedata['select_type'] = $select_type;
        $this->pagedata['bn_select']   = $_POST['bn_select'];
        $this->pagedata['items']       = $items;
        $this->pagedata['detail']      = $detail;
        
        $this->page('admin/pauseorder.html');
    }
    
    /**
     * 打回操作
     */
    public function doReback()
    {
        $autohide = array('autohide'=>3000);
        $this->begin('index.php?app=ome&ctl=admin_delivery&showmemo&p[0]='.$_POST['id']);
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $this->end(false, '非超级管理员不可操作');
        }
        if (empty($_POST['id']) && !empty($_POST['flag'])){
            $this->end(false, '请选择至少一张发货单', '', $autohide);
        }
        if (empty($_POST['memo'])){
            $this->end(false, '备注请不要留空', '', $autohide);
        }
        
        $delivery_id = $_POST['delivery_id'];
        $dlyObj = app::get('ome')->model("delivery");
        $orderObj = app::get('ome')->model("orders");
        $oOperation_log = app::get('ome')->model('operation_log');
        
        $deliveryInfo = $dlyObj->dump($delivery_id,'*',array('delivery_items'=>array('*')));
        $tmpdly = array(
            'delivery_id' => $deliveryInfo['delivery_id'],
            'status' => 'cancel',
            'logi_id' => '0',
            'logi_name' => '',
            'logi_no' => NULL,
        );
        $dlyObj->save($tmpdly);
        $oOperation_log->write_log('delivery_modify@ome',$deliveryInfo['delivery_id'],'发货单撤销');
        
        //库存管控
        $storeManageLib      = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id'=>$deliveryInfo['branch_id']));
        
        $order_ids = $dlyObj->getOrderIdByDeliveryId($deliveryInfo['delivery_id']);
        $err_msg = '';
        
        //是否是合并发货单
        if($deliveryInfo['is_bind'] == 'true'){
            //取关联发货单号进行暂停
            $delivery_ids = $dlyObj->getItemsByParentId($deliveryInfo['delivery_id'],'array');
            if($delivery_ids){
                foreach ($delivery_ids as $id){
                    $tmpdly = array(
                        'delivery_id' => $id,
                        'status' => 'cancel',
                        'logi_id' => '0',
                        'logi_name' => '',
                        'logi_no' => NULL,
                    );
                    $dlyObj->save($tmpdly);
                    $oOperation_log->write_log('delivery_modify@ome',$id,'发货单撤销');
                    
                    $delivery = $dlyObj->dump($id,'delivery_id,branch_id,shop_id',array('delivery_items'=>array('*'),'delivery_order'=>array('*')));
                    
                    $de = $delivery['delivery_order'];
                    $or = array_shift($de);
                    $ord_id = $or['order_id'];
                    
                    //仓库库存处理
                    $params['params'] = array_merge($delivery,array('order_id'=>$ord_id));
                    $params['node_type'] ='cancelDly';
                    $processResult = $storeManageLib->processBranchStore($params, $err_msg);
                    
                    kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($id);
                }
            }
            
            //取关联订单号进行还原
            if($order_ids){
                foreach ($order_ids as $id){
                    $order['order_id'] = $id;
                    $order['confirm'] = 'N';
                    $order['process_status'] = 'unconfirmed';
                    $orderObj->save($order);
                    $oOperation_log->write_log('order_modify@ome',$id,'发货单撤销,订单还原需重新审核,备注:'.$_POST['memo']);
                }
            }
        }else{
            //还原当前订单
            $order_id = $order_ids[0];
            $order['order_id'] = $order_id;
            $order['confirm'] = 'N';
            $order['process_status'] = 'unconfirmed';
            
            $orderObj->save($order);
            $oOperation_log->write_log('order_modify@ome',$order_id,'发货单撤销,订单还原需重新审核,备注:'.$_POST['memo']);
            
            //仓库库存处理
            $params['params'] = array_merge($deliveryInfo,array('order_id'=>$order_id));
            $params['node_type'] ='cancelDly';
            $processResult = $storeManageLib->processBranchStore($params, $err_msg);
            
            kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($deliveryInfo['delivery_id']);
        }
        
        //冻结库存释放
        $this->end(true, '操作成功', 'index.php?app=console&ctl=admin_delivery&act=pauseorder', $autohide);
    }
    
    /**
     * 填写打回备注
     * 
     * @param bigint $dly_id
     */
    public function showmemo($dly_id)
    {
        $deliveryObj  = app::get('ome')->model("delivery");
        $dly = $deliveryObj->dump($dly_id,'is_bind,delivery_bn');
        
        if ($dly['is_bind'] == 'true'){
            $ids = $deliveryObj->getItemsByParentId($dly_id, 'array');
            $returnids = implode(',', $ids);
            $idd = array();
            if ($ids){
                foreach ($ids as $v){
                    $delivery = $deliveryObj->dump($v, 'delivery_bn');
                    $order_id = $deliveryObj->getOrderBnbyDeliveryId($v);
                    $idd[$v]['delivery_bn'] = $delivery['delivery_bn'];
                    $idd[$v]['order_bn'] = $order_id['order_bn'];
                    $idd[$v]['delivery_id'] = $v;
                }
            }
            $this->pagedata['returnids'] = $returnids;
            $this->pagedata['ids'] = $ids;
            $this->pagedata['idd'] = $idd;
        }
        
        $this->pagedata['delivery_id'] = $dly_id;
        $this->pagedata['delivery_bn'] = $dly['delivery_bn'];
        $this->display("admin/delivery_showmemo.html");
    }
    
    /**
     * 强制撤销第三方仓储发货单
     * 
     * return
     */
    public function deliveryCancel()
    {
        $this->begin('');
        
        $operLogObj = app::get('ome')->model('operation_log');
        
        $limit = 500;
        $deliveryIds = $_POST['delivery_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false, '不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($deliveryIds)){
            $this->end(false, '请选择需要操作的订单!');
        }
        
        if(count($deliveryIds) > $limit){
            $this->end(false, '一次最多可执行500单!');
        }
        
        if (!empty($deliveryIds)) {
            $delivery_list = kernel::database()->select("SELECT delivery_id,branch_id,delivery_bn from sdb_ome_delivery where delivery_id in (" . join(',', $deliveryIds) . " ) AND status in('ready','progress') AND parent_id=0");
            foreach ((array) $delivery_list as $delivery)
            {
                $delivery_id = $delivery['delivery_id'];
                
                $res = ome_delivery_notice::cancel($delivery, true);
                if ($res['rsp'] == 'success' || $res['rsp'] == 'succ') {
                    $data = array(
                        'status'=>'cancel',
                        'memo'=>'批量取消请求!',
                        'delivery_bn'=>$delivery['delivery_bn'],
                    );
                    kernel::single('ome_event_receive_delivery')->update($data);
                    
                    //log
                    $operLogObj->write_log('delivery_back@ome', $delivery_id, '手工批量取消发货单');
                }else{
                    //log
                    $operLogObj->write_log('delivery_back@ome', $delivery_id, '手工批量取消发货单,失败：'.$res['msg']);
                }
            }
        }
        
        $this->end(true, '批量撤销发货单成功');
    }
    
    /**
     * toBatchSearch
     * @return mixed 返回值
     */
    public function toBatchSearch()
    {
        $base_filter = array(
            'type' => array('normal'),
            'pause' => 'false',
            'parent_id' => 0,
            'disabled' => 'false',
            'status' => array('ready','progress'),
        );
        $branch_ids = $this->getSearchBranchids();
        if ($branch_ids){
            $base_filter['branch_id'] = $branch_ids;
        }
        
        $_POST = array_merge($_POST, $base_filter);
        
        $this->pagedata['request_url'] = $this->url.'&act=doBatchSearch';
        
        parent::dialog_batch('console_mdl_delivery',true);
    }
    
    /**
     * 查询京东沧海发货结果
     *  sunjing
     */
    public function doBatchSearch()
    {
        parse_str($_POST['primary_id'], $postdata);
        
        if (!$postdata['f']) { echo 'Error: 请先选择发货单';exit;}
        
        $retArr  = array(
            'itotal'    => 0,
            'isucc'     => 0,
            'ifail'     => 0,
            'err_msg'   => array(),
        );
        
        $deliveryMdl = app::get('ome')->model('delivery');
        
        $deliverys = $deliveryMdl->getList('delivery_id,delivery_bn,branch_id', $postdata['f'], $postdata['f']['offset'], $postdata['f']['limit']);
        
        $deliverys = array_column($deliverys, null, 'delivery_id');
        
        if (!$deliverys) {echo 'Error: 未查询到发货单';exit;}
        
        $retArr['itotal'] = count($deliverys);
        foreach ($deliverys as  $delivery)
        {
            $wms_id = kernel::single('ome_branch')->getWmsIdById($delivery['branch_id']);
            $notice_params = array(
                'delivery_bn'   =>  $delivery['delivery_bn'],
                'delivery_id'   =>  $delivery['delivery_id'],
                'branch_id'     =>  $delivery['branch_id'],
                'wms_id'        =>  $wms_id,
            
            );
            
            $result = ome_delivery_notice::search($notice_params, true);
            if($result['rsp'] == 'succ'){
                $retArr['isucc']++;
            }else{
                $retArr['ifail']++;
            }
        }
        
        echo json_encode($retArr),'ok.';exit;
    }
    
    /**
     * 获取SearchBranchids
     * @return mixed 返回结果
     */
    public function getSearchBranchids()
    {
        $channelObj = app::get('channel')->model('channel');
        $channel_list = $channelObj->getlist('channel_id',array('node_type'=>array('yph','jd_wms_cloud')));
        if(empty($channel_list)) {
            return array();
        }
        $channel_ids = array_map('current', $channel_list);
        
        $branch_list = $channelObj->db->select("SELECT branch_id FROM sdb_ome_branch WHERE wms_id in (".implode(',',$channel_ids).")");
        
        $branch_ids = array_map('current', $branch_list);
        
        return $branch_ids;
    }
    
    /**
     * 加密字段显示明文
     * 
     * @return void
     * @author
     * */
    public function showSensitiveData($delivery_id, $fieldType='')
    {
        $deliveryMdl = app::get('console')->model('delivery');
        
        $delivery = $deliveryMdl->db_dump($delivery_id,'shop_id,shop_type,ship_name,ship_tel,ship_mobile,ship_addr,delivery_id,delivery_bn,member_id,ship_province,ship_city,ship_district,memo');
        if ($delivery['member_id']) {
            $member = app::get('ome')->model('members')->db_dump($delivery['member_id'],'uname');
            
            $delivery['uname'] = $member['uname'];
        }
        
        $order_ids = $deliveryMdl->getOrderIdByDeliveryId($delivery['delivery_id']);
        
        $order = app::get('ome')->model('orders')->db_dump(array ('order_id' => $order_ids),'order_bn');
        $delivery['order_bn'] = $order['order_bn'];
        
        // 处理加密
        $delivery['encrypt_body'] = kernel::single('ome_security_router',$delivery['shop_type'])->get_encrypt_body($delivery, 'delivery', $fieldType);
        
        $this->splash('success',null,null,'redirect',$delivery);
    }
    
    /**
     * 查询京东包裹发货状态
     */
    public function selectPackageStatus($delivery_id)
    {
        header("cache-control:no-store,no-cache,must-revalidate");
        
        $deliveryObj = app::get('ome')->model('delivery');
        $rePackageObj = app::get('ome')->model('reship_package');
        
        $deliveryLib = kernel::single('console_delivery');
        $branchLib = kernel::single('ome_branch');
        
        $delivery_id = intval($delivery_id);
        $reship_id = intval($_GET['reship_id']);
        
        //[兼容]通过退货单号查询
        $show_cancel = false;
        if($reship_id && empty($delivery_id)){
            $tempInfo = $rePackageObj->dump(array('reship_id'=>$reship_id), '*');
            $delivery_id = $tempInfo['delivery_id'];
            
            $show_cancel = true;
        }
        
        //发货单信息
        $deliveryInfo = $deliveryObj->dump(array('delivery_id'=>$delivery_id), '*');
        if(empty($deliveryInfo)){
            die('发货单不存在.');
        }
        
        //wms_id
        $channel_id = $branchLib->getWmsIdById($deliveryInfo['branch_id']);
        
        //branch_bn
        $branch_bn = $branchLib->getBranchBnById($deliveryInfo['branch_id']);
        
        //package
        $error_msg = '';
        $packageList = $deliveryLib->getDeliveryPackage($delivery_id, $error_msg);
        if(empty($packageList)){
            die('没有找到包裹或者包裹已追回。');
        }
        
        //获取京东包裹发货状态
        $shipping_status = $deliveryLib->getShippingStatus();
        
        //查询配送状态
        $dataList = array();
        foreach ($packageList as $key => $val)
        {
            $package_bn = $val['package_bn'];
            
            $params = array(
                'delivery_id' => $val['delivery_id'],
                'delivery_bn' => $deliveryInfo['delivery_bn'],
                'branch_bn' => $branch_bn,
                'package_id' => $val['package_id'],
                'package_bn' => $package_bn,
            );
            $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->delivery_package_status($params);
            $ship_status = '';
            if($result['rsp'] == 'succ'){
                $ship_status = $result['data']['orderStatus'];
                $result['data']['status_str'] = ($ship_status ? $shipping_status[$ship_status] : '没有发货状态值');
            }
            
            //是否需要拦截包裹
            if(in_array($ship_status, array('-100', '8', '16', '18'))){
                $show_cancel = false;
            }
            
            $dataList[$package_bn] = $result;
        }
        
        $this->pagedata['dataList'] = $dataList;
        $this->pagedata['show_cancel'] = $show_cancel;
        $this->pagedata['reship_id'] = $reship_id;
        $this->singlepage("admin/delivery/package_status.html");
    }
    
    /**
     * 通知京东云交易平台提前发货
     */
    public function batch_makedly()
    {
        $this->begin('');
        kernel::database()->exec('commit');
        
        $deliveryObj = app::get('ome')->model('delivery');
        $packageObj = app::get('ome')->model('delivery_package');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $ids = $_POST['delivery_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false, '不能使用全选功能,每次最多选择500条!');
        }
        
        if(count($ids) > 500){
            $this->end(false, '每次最多只能选择500条!');
        }
        
        //获取京东一件代发WMS仓储
        $sql = "SELECT channel_id FROM `sdb_channel_channel` WHERE node_type='yjdf'";
        $tempList = $deliveryObj->db->select($sql);
        if(empty($tempList)){
            $this->end(false, '没有获取到[京东一件代发]WMS仓储!');
        }
        
        $channel_ids = array();
        foreach ($tempList as $key => $val)
        {
            $channel_id = $val['channel_id'];
            
            $channel_ids[$channel_id] = $channel_id;
        }
        
        //仓库
        $branchList = array();
        $sql = "SELECT branch_id,branch_bn FROM `sdb_ome_branch` WHERE wms_id IN(". implode(',', $channel_ids) .")";
        $tempList = $deliveryObj->db->select($sql);
        if(empty($tempList)){
            $this->end(false, '没有获取到[京东一件代发]关联的仓库!');
        }
        
        foreach ($tempList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            
            $branchList[$branch_id] = $val['branch_bn'];
        }
        
        //发货单
        $deliveryList = $deliveryObj->getList('delivery_id,delivery_bn,branch_id,sync_status', array('delivery_id'=>$ids, 'status'=>array('progress','ready')));
        if(empty($deliveryList)){
            $this->end(false, '没有获取到可操作的发货单!');
        }
        
        //list
        $syncDlyIds = array();
        $syncErrorDly = array();
        foreach ($deliveryList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            $branch_id = $val['branch_id'];
            
            //只处理指定仓库
            if(empty($branchList[$branch_id])){
                continue;
            }
            
            //过滤通知发货成功
            if($val['sync_status'] == '12'){
                continue;
            }
            
            //推送创建发货单失败的场景
            if($val['sync_status'] == '2'){
                $packageInfo = $packageObj->dump(array('delivery_id'=>$delivery_id), 'package_id');
                if(empty($packageInfo)){
                    continue; //没有京东订单号(包裹号),则跳过
                }
            }
            
            //delivery_id
            $syncDlyIds[] = $delivery_id;
            
            //request
            $error_msg = '';
            $result = ome_delivery_notice::notification($delivery_id, $error_msg);
            if($result === false){
                $syncErrorDly[] = $val['delivery_bn'];
                
                $error_msg = '手动通知云交易发货失败：'. $error_msg;
                $operLogObj->write_log('delivery_modify@ome', $delivery_id, $error_msg);
            }
        }
        
        if(empty($syncDlyIds)){
            $this->end(false, '没有需要通知的发货单(默认已过滤通知成功、没有京东订单号的记录)。');
        }
        
        $this->end(true, '命令已经被成功发送！！');
    }
    
    /**
     * [京东云交易]更新WMS发货单为签收状态
     */
    public function batch_sign()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $this->begin('');
        kernel::database()->exec('commit');
        
        $deliveryObj = app::get('ome')->model('delivery');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $deliveryLib = kernel::single('console_delivery');
        
        $ids = $_POST['delivery_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false, '不能使用全选功能,每次最多选择500条!');
        }
        
        if(count($ids) > 500){
            $this->end(false, '每次最多只能选择500条!');
        }
        
        //获取京东一件代发WMS仓储
        $sql = "SELECT channel_id FROM `sdb_channel_channel` WHERE node_type='yjdf'";
        $channelInfo = $deliveryObj->db->selectrow($sql);
        if(empty($channelInfo)){
            $this->end(false, '没有获取到[京东一件代发]WMS仓储!');
        }
        $channel_id = $channelInfo['channel_id'];
        
        //[京东一件代发]仓库
        $branchList = array();
        $sql = "SELECT branch_id,branch_bn FROM `sdb_ome_branch` WHERE wms_id=". $channel_id;
        $tempList = $deliveryObj->db->select($sql);
        if(empty($tempList)){
            $this->end(false, '没有获取到[京东一件代发]关联的仓库!');
        }
        
        foreach ($tempList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            
            $branchList[$branch_id] = $val['branch_bn'];
        }
        
        //发货单
        $tempList = $deliveryObj->getList('delivery_id,delivery_bn,branch_id', array('delivery_id'=>$ids, 'status'=>'succ'));
        if(empty($tempList)){
            $this->end(false, '没有获取到可操作的发货单!');
        }
        
        $deliveryList = array();
        $delivery_ids = array();
        foreach ($tempList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            $branch_id = $val['branch_id'];
            
            //只处理指定仓库
            if(empty($branchList[$branch_id])){
                continue;
            }
            
            //仓库信息
            $val['branch_bn'] = $branchList[$branch_id];
            
            $delivery_ids[$delivery_id] = $delivery_id;
            
            $deliveryList[$delivery_id] = $val;
        }
        
        if(empty($deliveryList)){
            $this->end(false, '没有获取到可操作的发货单。');
        }
        
        //关联订单号
        $sql = "SELECT a.delivery_id,b.order_id,b.order_bn,b.process_status,b.status,b.pay_status,b.ship_status FROM `sdb_ome_delivery_order` AS a
                LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.delivery_id IN(". implode(',', $delivery_ids) .")";
        $tempList = $deliveryObj->db->select($sql);
        
        //检查订单状
        $orderList = array();
        foreach ($tempList as $key => $val)
        {
            $order_id = $val['order_id'];
            $delivery_id = $val['delivery_id'];
            
            //发货单信息
            $deliveryInfo = $deliveryList[$delivery_id];
            
            //check
            if($val['process_status'] != 'splited'){
                unset($deliveryList[$delivery_id]);
                
                $this->end(false, '订单号：'.$val['order_bn'].'不是已拆分状态');
            }
            
            if($val['pay_status'] != '1'){
                unset($deliveryList[$delivery_id]);
                
                $this->end(false, '订单号：'.$val['order_bn'].'不是已支付状态');
            }
            
            if($val['ship_status'] != '1'){
                unset($deliveryList[$delivery_id]);
                
                $this->end(false, '订单号：'.$val['order_bn'].'不是已发货状态');
            }
            
            $val['branch_bn'] = $deliveryInfo['branch_bn'];
            
            $orderList[$order_id] = $val;
        }
        
        //list
        $is_flag = false;
        foreach ($orderList as $key => $val)
        {
            $order_id = $val['order_id'];
            
            //request
            $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->delivery_confirm($val);
            if($result['rsp'] != 'succ'){
                //log
                $error_msg = '订单推送WMS确认收货失败：'.$result['err_msg'];
                $operLogObj->write_log('order_confirm@ome', $order_id, $error_msg);
            }else{
                $is_flag = true;
                
                //log
                $error_msg = '订单推送WMS确认收货成功!';
                $operLogObj->write_log('order_confirm@ome', $order_id, $error_msg);
            }
        }
        
        if(!$is_flag){
            $this->end(true, '命令已经被成功发送!');
        }
        
        //查询京东订单状态
        foreach ($deliveryList as $dlyKey => $dlyVal)
        {
            $delivery_id = $dlyVal['delivery_id'];
            $branch_bn = $dlyVal['branch_bn'];
            
            //package
            $error_msg = '';
            $packageList = $deliveryLib->getDeliveryPackage($delivery_id, $error_msg);
            if(empty($packageList)){
                continue;
            }
            
            //查询配送状态
            foreach ($packageList as $packKey => $packVal)
            {
                $package_bn = $packVal['package_bn'];
                
                //params
                $params = array(
                    'delivery_id' => $packVal['delivery_id'],
                    'delivery_bn' => $dlyVal['delivery_bn'],
                    'branch_bn' => $branch_bn,
                    'package_id' => $packVal['package_id'],
                    'package_bn' => $package_bn,
                );
                
                //request
                $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->delivery_package_status($params);
            }
        }
        
        unset($tempList, $branchList, $deliveryList, $orderList);
        
        $this->end(true, '命令已经被成功发送。');
    }
    
    /**
     * 批量推送发货单至WMS仓储
     */
    public function batchSyncWms()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $deliveryIds = $_POST['delivery_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($deliveryIds)){
            die('请选择需要操作的发货单!');
        }
        
        if(count($deliveryIds) > 500){
            die('每次最多只能选择500条!');
        }
        
        $this->pagedata['GroupList'] = json_encode($deliveryIds);
        
        $this->pagedata['request_url'] = $this->url .'&act=ajaxSyncWms';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch('ome_mdl_delivery', false, 50, 'incr');
    }
    
    /**
     * ajaxSyncWms
     * @return mixed 返回值
     */
    public function ajaxSyncWms()
    {
        $deliveryObj = app::get('ome')->model('delivery');
        
        $branchLib = kernel::single('ome_branch');
        
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取发货单号
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择发货单';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data
        $dataList = $deliveryObj->getList('delivery_id,delivery_bn,branch_id,sync_status,original_delivery_bn', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到发货单';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //获取京东一件代发的仓库列表
        $wms_type = 'yjdf';
        $error_msg = '';
        $yjdfBranchList = $branchLib->getWmsBranchIds($wms_type, $error_msg);
        
        //list
        foreach ($dataList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            $branch_id = $val['branch_id'];
            
            //京东一件代发WMS仓库
            $isYjdfFlag = ($yjdfBranchList[$branch_id] ? true : false);
            
            //[开普勒]已经有第三方订单号,不允许重复推送
            if($isYjdfFlag && $val['original_delivery_bn']){
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = $val['delivery_bn'].'已经有第三方单号,不允许重复推送';
                
                continue;
            }
            
            //推送失败的发货单,检查订单是否已经申请退款
            if($val['sync_status'] == '2' || $isYjdfFlag){
                //@todo：发货单未同步第三方仓储，并且申请退款的订单直接取消发货单
                $sql = "SELECT b.order_id, b.process_status, b.status, b.pay_status FROM `sdb_ome_delivery_order` AS a
                        LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.delivery_id=". $delivery_id;
                $orderInfo = $deliveryObj->db->selectrow($sql);
                if(in_array($orderInfo['pay_status'], array('5', '6', '7'))){
                    //error
                    $retArr['ifail'] += 1;
                    $retArr['err_msg'][] = $val['delivery_bn'].'已经申请退款或已退款,请检查;';
                    
                    continue;
                }
            }
            
            //request
            ome_delivery_notice::create($delivery_id);
            
            //succ
            $retArr['isucc'] += 1;
        }
        
        echo json_encode($retArr),'ok.';
        exit;
    }
    
    /**
     * 批量推送发货单至WMS仓储
     */
    public function batchDeliveryCancel()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $deliveryObj = app::get('ome')->model('delivery');
        
        $deliveryIds = $_POST['delivery_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($deliveryIds)){
            die('请选择需要操作的发货单!');
        }
        
        if(count($deliveryIds) > 500){
            die('每次最多只能选择500条!');
        }
        
        //data
        $dataList = $deliveryObj->getList('delivery_id', array('delivery_id'=>$deliveryIds, 'status'=>array('ready','progress'), 'parent_id'=>0));
        if(empty($dataList)){
            die('没有可撤消的发货单!');
        }
        
        $deliveryIds = array_column($dataList, 'delivery_id');
        
        $this->pagedata['GroupList'] = json_encode($deliveryIds);
        
        $this->pagedata['request_url'] = $this->url .'&act=ajaxDeliveryCancel';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch('ome_mdl_delivery', false, 50, 'incr');
    }
    
    /**
     * ajaxDeliveryCancel
     * @return mixed 返回值
     */
    public function ajaxDeliveryCancel()
    {
        $deliveryObj = app::get('ome')->model('delivery');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取发货单号
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择发货单';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data
        $dataList = $deliveryObj->getList('delivery_id,branch_id,delivery_bn,status', $filter, $offset, $limit);
        
        //check
        if(empty($dataList)){
            echo 'Error: 没有获取到发货单';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //list
        foreach ($dataList as $key => $delivery)
        {
            $delivery_id = $delivery['delivery_id'];
            
            if(!in_array($delivery['status'], array('ready','progress'))){
                continue;
            }
            
            //cancel
            $res = ome_delivery_notice::cancel($delivery, true);
            if ($res['rsp'] == 'success' || $res['rsp'] == 'succ') {
                //succ
                $retArr['isucc'] += 1;
                
                //update
                $data = array(
                    'status'=>'cancel',
                    'memo'=>'批量取消请求!',
                    'delivery_bn'=>$delivery['delivery_bn'],
                );
                kernel::single('ome_event_receive_delivery')->update($data);
                
                //log
                $operLogObj->write_log('delivery_back@ome', $delivery_id, '手工批量取消发货单');
            }else{
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = $delivery['delivery_bn'].'请求取消失败：'. $res['msg'];
                
                //log
                $operLogObj->write_log('delivery_back@ome', $delivery_id, '手工批量取消发货单,失败：'.$res['msg']);
            }
        }
        
        echo json_encode($retArr),'ok.';
        exit;
    }
    
    /**
     * 设置为请求失败状态
     */
    public function batch_updateFail()
    {
        $this->begin('');
        kernel::database()->exec('commit');
        
        $deliveryObj = app::get('ome')->model('delivery');
        
        $ids = $_POST['delivery_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false, '不能使用全选功能,每次最多选择500条!');
        }
        
        if(count($ids) > 500){
            $this->end(false, '每次最多只能选择500条!');
        }
        
        //发货单
        $deliveryList = $deliveryObj->getList('delivery_id,delivery_bn,branch_id,sync_status', array('delivery_id'=>$ids, 'status'=>array('progress','ready')));
        if(empty($deliveryList)){
            $this->end(false, '没有获取到可操作的发货单!');
        }
        
        //list
        foreach ($deliveryList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            $sync_status = $val['sync_status'];
            
            //check
            if($sync_status != '1'){
                continue;
            }
            
            $deliveryObj->update(array('sync_status'=>'2'), array('delivery_id'=>$delivery_id));
        }
        
        $this->end(true, '已完成操作!');
    }
    
    /**
     * 强制修复发货单
     */
    public function repairDelivery()
    {
        $this->begin();
        
        $keplerLib = kernel::single('console_delivery_kepler');
        
        //post
        $delivery_id = intval($_POST['delivery_id']);
        if(empty($delivery_id)){
            $this->end(false, '无效的操作');
        }
        
        //dispose
        $error_msg = '';
        $result = $keplerLib->repairDelivery($delivery_id, $error_msg);
        if(!$result){
            $this->end(false, '执行失败：'. $error_msg);
        }
        
        $this->end(true, '强制修复发货单成功');
    }
}
