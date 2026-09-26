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

class ome_ctl_admin_batch_order extends desktop_controller{
    var $name = "订单中心";
    var $workground = "order_center";

    /**
     * 批量审核订单
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function batchConfirm(){

        $fltId = $_GET['fltId'];
        if ($fltId) {
            $filterRow = app::get('desktop')->model('filter')->dump($fltId, 'filter_query');

            parse_str($filterRow['filter_query'], $filterQuery);

            if ($_POST['isSelectedAll'] == '_ALL_' && is_array($filterQuery)) {
                $_POST = array_merge($_POST, $filterQuery);
            }
        } else {

        }

        // 查询订单
        $_POST['process_status'] = array('unconfirmed','confirmed','splitting');
        $_POST['assigned']       = 'assigned';
        $_POST['abnormal']       = 'false';
        $_POST['is_fail']        = 'false';
        $_POST['pause']          = 'false';
        $_POST['is_auto']        = 'false';
        $_POST['status']         = 'active';
        $_POST['archive']        = '0';
        $_POST['ship_status']    = array('0','2');
        $_POST['pay_status']     = array('0','1');

        if(!kernel::single('desktop_user')->is_super()){
            $_POST['op_id'] = kernel::single('desktop_user')->get_id();
        }

        $object = app::get('ome')->model('orders');
        $object->filter_use_like = true;
        $orders = $object->getList('order_id,order_combine_hash,order_combine_idx,order_bool_type,shop_type',$_POST,0,5000);

        $this->pagedata['splitSeting'] = kernel::single('ome_order_split')->get_delivery_seting();
        $this->pagedata['combineSeting'] = app::get('ome')->getConf('ome.combine.select');

        $branchMdl = app::get('ome')->model('branch');

        $branchFilter = array('disabled'=>'false','is_deliv_branch'=>'true','check_permission'=>'false');

        $this->pagedata['branchList']  = $branchMdl->getList('branch_id,name',$branchFilter,0,-1,'weight desc');
        $splitFilter = array(
            'split_type' => app::get('omeauto')->model('order_split')->getBatchConfirmSplitType()
        );
        $this->pagedata['order_split']  = app::get('omeauto')->model('order_split')->getList('sid,name',$splitFilter,0,-1);
        //天猫物流升级服务增加提示信息
        $order_id = $orders ? array_column($orders, 'order_id') : [0];

        $orderExt = app::get('ome')->model('order_extend')->getList('cpup_service,promise_service,extend_field',array('order_id'=>$order_id));
        $cpup = array();
        $promise_service = [];
        $is_duocang = false;
        if ($orderExt) {
            foreach ($orderExt as $val) {
                $val['extend_field'] && $val['extend_field'] = json_decode($val['extend_field'], 1);
                $cpup = array_merge($cpup,explode(',',$val['cpup_service']));
                if ($val['promise_service']) {
                    // 将promise_service按英文逗号分割后合并到数组中
                    $services = explode(',', $val['promise_service']);
                    $promise_service = array_merge($promise_service, $services);
                }
                if ($val['extend_field']['performance_type'] == '3') {
                    $is_duocang = true;
                }
            }
            // 对promise_service数组去重
            $promise_service = array_unique(array_filter($promise_service));
        }
        $cpup_service = 'false';
        $dewuBrandDuocang = false; // 得物品牌直发多仓发货订单

        foreach ($orders as $orderVal) {
            if (kernel::single('ome_order_bool_type')->isCPUP($orderVal['order_bool_type'])) {
                $cpup_service = 'true';
                // break;
            }
            if (strtolower($orderVal['shop_type']) == 'dewu' && kernel::single('ome_order_bool_type')->isDWBrand($orderVal['order_bool_type']) && $is_duocang) {
                $dewuBrandDuocang = true;
            }
        }

        $branchList = $branchMdl->getList('branch_id,name,branch_bn,b_type',$branchFilter,0,-1,'weight desc');
        if ($dewuBrandDuocang) {
            // 获取得物多仓的发货仓列表
            $oAddress = app::get('ome')->model('return_address');
            $dewuBrandList = $oAddress->getList('distinct branch_bn', ['shop_type'=>'dewu']);
            $dewuBrandList = array_column($dewuBrandList, 'branch_bn');
            foreach ($branchList as $k => $v) {
                if (!in_array($v['branch_bn'], $dewuBrandList)) {
                    unset($branchList[$k]);
                }
            }
        }

        // 门店仓仅展示参加 O2O（is_o2o=1）的；非门店仓保持原样
        if (app::get('o2o')->is_installed() && !empty($branchList)) {
            $o2oBranchIdMap = array();
            $o2oStores = app::get('o2o')->model('store')->getList('branch_id', array('is_o2o' => '1'));
            foreach ($o2oStores as $o2oStore) {
                if (!empty($o2oStore['branch_id'])) {
                    $o2oBranchIdMap[intval($o2oStore['branch_id'])] = true;
                }
            }
            foreach ($branchList as $k => $v) {
                if (isset($v['b_type']) && intval($v['b_type']) == 2
                    && empty($o2oBranchIdMap[intval($v['branch_id'])])) {
                    unset($branchList[$k]);
                }
            }
            $branchList = array_values($branchList);
        }

        // todo maxiaochen 如果含有得物品牌直发的多仓发货订单，branchList只展示得物的发货仓列表
        $this->pagedata['branchList']  = $branchList;
        $this->pagedata['cpup'] = $cpup;
        $this->pagedata['cpup_service'] = $cpup_service;
        $this->pagedata['promise_service'] = implode(',', $promise_service);


        $orderGroup = array();
        foreach ($orders as $value) {
            $idx = sprintf('%s||%s', $value['order_combine_hash'], $value['order_combine_idx']);
            $orderGroup[$idx][] = $value['order_id'];
        }

        $order_id = array();
        $countGroup = 0;
        $countOrder = 0;
        $countOrders = count($orders);
        foreach ($orderGroup as $k => $v) {
            if(count($v) > 1) {
                $countGroup += 1;
                $countOrder += count($v);
            }
            $order_id[] = implode('|', $v);
        }
        $_POST['order_id'] = $order_id;
        if ($_POST['isSelectedAll'] == '_ALL_') {
            unset($_POST['isSelectedAll']);
        }

        $pageData =[
            'billName' => "订单组，共计{$countOrders}个订单，其中{$countOrder}个订单可以合并成{$countGroup}个发货单",
            'maxProcessNum' => 1,
            'queueNum' => 5,
            // 'close' => true,
            'custom_html' => $this->fetch('admin/batch/confirm.html'),
            'request_url' => 'index.php?app=ome&ctl=admin_batch_order&act=ajaxDoAuto'
        ];
        parent::selectToPageRequest($object, $pageData);
    }

    /**
     * 获取CorpByBarnch
     * @return mixed 返回结果
     */
    public function getCorpByBarnch(){

        if(!isset($_POST['branch_id']) || empty($_POST['branch_id'])){
            $this->splash('error','','请选择仓库信息');
        }
        $branchId = (int)$_POST['branch_id'];
        $filter = array(
            'disabled'   => 'false',
            'filter_sql' => "( FIND_IN_SET ( $branchId, branch_id ) OR all_branch='true')",
        );
        // todo maxiaochen 得物品牌直发 物流公司限制 https://open.dewu.com/#/api/body?apiId=1020&id=1&title=%E8%AE%A2%E5%8D%95%E6%9C%8D%E5%8A%A1
        $corps = app::get('ome')->model('dly_corp')->getList('branch_id, all_branch, corp_id, name, type, is_cod, weight, channel_id, shop_id, tmpl_type',$filter,0, -1, 'weight DESC');
        
        // 仓库信息
        $branchInfo = app::get('ome')->model('branch')->dump(array('branch_id'=>$branchId), '*');
        
        // 仓库物流关系列表
        $branchCorps = app::get('ome')->model('branch_corp')->getList('*', array('branch_id'=>$branchId));
        $selectCorps = array();
        foreach ($branchCorps as $val)
        {
            $selectCorps[] = $val['corp_id'];
        }
        
        // 获取仓库关联的指定物流公司
        if($corps){
            $trueCorps = array();
            foreach ($corps as $key => $val)
            {
                $corp_id = $val['corp_id'];
                
                // 过滤掉我的仓库
                if($branchInfo['name'] == '我的仓库' || $branchInfo['branch_bn'] == 'stockhouse'){
                    $trueCorps[] = $val;
                    
                    continue;
                }
                
                // 是否已选择
                if(in_array($corp_id, $selectCorps)){
                    $corps[$key]['is_selected'] = true;
                    
                    $trueCorps[] = $val;
                }else{
                    $corps[$key]['is_selected'] = false;
                }
            }
            
            // reset
            $corps = $trueCorps;
        }
        
        if(!$corps){
            $this->splash('error','','仓库没有设置物流公司');
        }
        $this->splash('success','','请求成功','',array('data'=>$corps));
    }
    
    /**
     * 自动审核订单.
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function ajaxDoAuto(){
        set_time_limit(0);
        $order_id  = array_filter(explode(';', $_POST['ajaxParams']));
        $corp_id   = $_POST['corp_id'];
        $branch_id = $_POST['branch_id'];
        $is_split  = $_POST['combine_or_split'] == '1' ? true : false;
        $is_combine  = $_POST['combine_or_split'] == '2' ? true : false;
        $splitId = $_POST['split_id'];
        $retArr = array(
            'total'  => count($order_id),
            'succ'   => 0,
            'fail'   => 0,
            'err_msg' => array(),
        );
        if (!$order_id)  { 
            $retArr['fail'] = $retArr['total'];
            $retArr['fail_msg'][] = ['msg'=>'请选择订单'];
            echo json_encode($retArr);exit;
        }
        if (!$corp_id)   { 
            $retArr['fail'] = $retArr['total'];
            $retArr['fail_msg'][] = ['msg'=>'请选择物流公司'];
            echo json_encode($retArr);exit;
        }
        if (!$branch_id) { 
            $retArr['fail'] = $retArr['total'];
            $retArr['fail_msg'][] = ['msg'=>'请选择仓库'];
            echo json_encode($retArr);exit;
        }

        $corpMdl    = app::get('ome')->model('dly_corp');
        $branchMdl  = app::get('ome')->model('branch');
        if($corp_id == 'auto') {
            $corp = ['corp_id'=>'auto'];
        } else {
            $corp = $corpMdl->dump($corp_id, 'corp_id, name, type, is_cod, weight, channel_id');
        }
        if (!$corp) {
            $retArr['fail'] = $retArr['total'];
            $retArr['fail_msg'][] = ['msg'=>'选择物流不存在'];
            echo json_encode($retArr);exit;
        }
        if($branch_id == 'auto') {
            $branch = ['branch_id'=>'auto'];
        } else {
            $branch = $branchMdl->dump(array('branch_id'=>$branch_id,'check_permission'=>'false'),'branch_id,name,area,address,branch_bn,wms_id');
        }
        if (!$branch) {
            $retArr['fail'] = $retArr['total'];
            $retArr['fail_msg'][] = ['msg'=>'选择仓库不存在'];
            echo json_encode($retArr);exit;
        }
        foreach ($order_id as $v) {
            $data = array(
                'is_combine' => $is_combine,
                'is_split' => $is_split,
                'branch' => $branch,
                'corp' => $corp,
                'split_id' => $splitId,
            );
            $tmpArr = kernel::single('ome_batch_order')->ajaxDoAutoOne(explode('|', $v), $data);
            if($tmpArr['ifail']){
                $retArr['fail'] += 1;
                foreach ($tmpArr['err_msg'] as $k => $v) {
                    $retArr['fail_msg'][] = ['msg'=>$v];
                }
            }
        }
        $retArr['succ'] = $retArr['total'] - $retArr['fail'];
        echo json_encode($retArr);exit;
    }
    /**
     * 批量取消订单.
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function batchCancel(){
       $order_ids = $_POST['order_id'];
       $orderObj    = app::get('ome')->model('orders');
       $orders = $orderObj->getList('order_id',array('order_id'=>$order_ids,'payed'=>0,'status|notin'=>array('dead')));
       $orderGroup = array();
       foreach ( $orders as $order ) {
           $orderGroup[] = $order['order_id'];
       }
       $this->pagedata['finder_id'] = $_GET['finder_id'];
       $this->pagedata['ordercount'] = count($orderGroup);
       $this->pagedata['orderGroup'] = json_encode($orderGroup);
       $this->pagedata['currentTime'] = time();
       $this->pagedata['ordertotal'] = count($order_ids);

       unset($orderGroup);
       $this->display("admin/batch/cancel.html");
    }

    /**
     * 批量取消订单.
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function doBatchCancel(){
        $oOrder = app::get('ome')->model('orders');
        $oShop = $this->app->model('shop');
        $result = array('total' => 0, 'succ' => 0, 'fail' => 0);
        $ajaxParams = explode(';',$_POST['ajaxParams']);
        $rows = $oOrder->getList('shop_id,order_id,source', array('order_id' => $ajaxParams,'payed'=>0,'status|notin'=>array('dead')));
        $orders = array();
        foreach ($rows as $order) {
            $orders[$order['order_id']] = $order;

        }
        unset($rows);
        foreach ( $orders as $order ) {
        $order_id = $order['order_id'];
        $result['total'] ++;

        $memo = "批量取消订单: ".$_POST['memo'];
        $mod = 'sync';

        $c2c_shop_list = ome_shop_type::shop_list();
        $shop_detail = $oShop->dump(array('shop_id'=>$order['shop_id']),'node_id,node_type');
        if(!$shop_detail['node_id'] || in_array($shop_detail['node_type'],$c2c_shop_list) || $order['source'] == 'local'){
            $mod = 'async';
        }

        $sync_rs = $oOrder->cancel($order_id,$memo,true,$mod, false);
        if($sync_rs['rsp'] == 'success')
        {
            $result['succ'] ++;
        }else{
            $result['fail']++;
        }



        }
        echo json_encode($result,true);
    }
   
    /**
     * 批量开发票.
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function BatchTax(){
        $this->_request = kernel::single('base_component_request');
        $obj_order = app::get('ome')->model('orders');
        $all_order_ids = array();
        if($_GET['is_tax'] == 'no'){
            $is_tax = 'true';
        }elseif($_GET['is_tax'] == 'yes'){
            $is_tax = 'false';
        }
      
        if(isset($_POST['isSelectedAll']) && $_POST['isSelectedAll']){
           
            if($_GET['type'] == 'active'){
                $filter = array(
                    'disabled' => 'false',
                    'is_fail' => 'false',
                    'status' => 'active',
                    'archive' => 0,
                    'process_status|noequal' => 'is_declare',
                );
            }elseif($_GET['type'] == 'unmyown'){
            
                $filter = array (
                    'assigned' => 'assigned',
                    'abnormal' => 'false',
                    'is_fail' => 'false',
                    'status' => 'active',
                    'process_status' =>array (
                        0 => 'unconfirmed',
                        1 => 'confirmed',
                        2 => 'splitting',
                    ),
                    'archive' => 0,
                );
            }
            $_order_ids = $obj_order->getList('order_id',$filter);
            foreach($_order_ids as $val){
                $all_order_ids[] = $val['order_id'];
            }
        }else{
            $order_ids = $_POST['order_id'];
            foreach($order_ids as $order_id){
                $all_order_ids[] = $order_id;
            }
        }
        if(app::get('invoice')->is_installed()){
            $this->pagedata['invoice'] =  'true'; 
        }else{
            $this->pagedata['invoice'] =  'false';
        }
        $this->pagedata['is_tax'] =  $is_tax;
    
        $this->pagedata['order_num'] = count($all_order_ids);
        $this->pagedata['order_id'] = $all_order_ids;
        $this->pagedata['all_order_ids'] = json_encode($all_order_ids);
        $this->display('admin/order/batch/tax.html');
    }
    
    /**
     * 批量开发票.
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function doBatchTax(){
        $order_id = $_POST['order_id'];
        $is_tax = $_POST['is_tax'];

        $orderObj = app::get('ome')->model('orders');
        $oOperation_log = app::get('ome')->model('operation_log');
        if(app::get('invoice')->is_installed()){
            $order_is_tax_part = $is_tax == 'true' ? "开发票" : "不开发票";
            //更新订单is_tax字段 并记下log
            $update_arr = array("is_tax"=>$is_tax);
            $filter_arr = array("order_id"=>$order_id);
            $rs_is_tax = $orderObj->update($update_arr,$filter_arr);
            $order_is_tax_log = "批量设置订单更新为".$order_is_tax_part;
            if(!is_bool($rs_is_tax)){
                $oOperation_log->write_log('order_modify@ome',$order_id,$order_is_tax_log);
                $arr_create_invoice = array(
                    'order_id'=>$order_id,
                    'is_tax' => $is_tax
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
        }else{
            //未安装app:invoice的 只对ome_orders表做相应字段更新
            $data = array(
                'order_id' => $order_id,
                'is_tax' => $_POST['is_tax'],
            );
            $orderObj->save($data);
            $oOperation_log->write_log('order_modify@ome',$order_id,'录入及变更发票号');
        }
        
        echo json_encode(array('status'=>'success')); die;
    }

    /**
     * 批量操作对话框
     *
     * @return void
     * @author 
     **/
    public function batchDialog($act='',$flt='',$view=0)
    {
        if ($act == 'renew') {
            $this->pagedata['dailogform'] = false;
        }
        
        $orderModel = app::get('ome')->model('orders');
        
        $orderCtl = kernel::single('ome_ctl_admin_order');
        
        // 条件
        switch ($act) {
            case 'dopause':
                $base_filter = array('pause'=>'false','archive'=>'0');
                if ($flt == 'unmyown') {
                    $base_filter['op_id']          = kernel::single('desktop_user')->get_id();
                    $base_filter['assigned']       = 'assigned';
                    $base_filter['abnormal']       = "false";
                    $base_filter['is_fail']        = 'false';
                    $base_filter['status']         = 'active';
                    $base_filter['process_status'] = array('unconfirmed', 'confirmed', 'splitting');

                    if ($view == 989898) {
                        $base_filter['is_cod'] = 'true';
                    } elseif ($view && $view<999000) {
                        $base_filter['order_confirm_filter'] = sprintf("(sdb_ome_orders.auto_status & %s = %s)", $view, $view);
                    }

                } elseif ($flt == 'active') {
                    $sub_menu = $orderCtl->_view_active();
                    foreach ($sub_menu as $key => $value) {
                        if($view == $key){
                            $base_filter = array_merge((array)$value['filter'], $base_filter);
                        }
                    }
                }

                if(kernel::single('desktop_user')->is_super()){
                    unset($base_filter['op_id']);
                }

                $filter = array_merge((array)$_POST , (array)$base_filter);

                break;
            case 'renew':
                // 一单一单处理
                $base_filter = array('pause'=>'true','archive'=>'0');
                if ($flt == 'unmyown') {
                    $sub_menu = $orderCtl->_views_unmyown();
                    foreach ($sub_menu as $key => $value) {
                        if($view == $key){
                            $base_filter = array_merge((array)$value['filter'], $base_filter);
                        }
                    }

                } elseif ($flt == 'active') {
                    $sub_menu = $orderCtl->_view_active();
                    foreach ($sub_menu as $key => $value) {
                        if($view == $key){
                            $base_filter = array_merge((array)$value['filter'], $base_filter);
                        }
                    }
                }

                if(kernel::single('desktop_user')->is_super()){
                    unset($base_filter['op_id']);
                }

                $filter = array_merge((array)$_POST , (array)$base_filter);

                break;
            case 'doabnormal': //批量设置为异常
                $base_filter = array('archive'=>'0');
                if ($flt == 'unmyown') {
                    $base_filter['op_id']          = kernel::single('desktop_user')->get_id();
                    $base_filter['assigned']       = 'assigned';
                    $base_filter['abnormal']       = "false";
                    $base_filter['is_fail']        = 'false';
                    $base_filter['status']         = 'active';
                    $base_filter['process_status'] = array('unconfirmed', 'confirmed', 'splitting');

                    if($view>=999000){
                          //app::get('desktop')->model('filter')->getFilter() 方法不存在 会报错
//                        $filter_parames = array('model'=>'ome_mdl_orders','user_id'=>$base_filter['op_id'],'app'=>'ome','ctl'=>'admin_order','act'=>'confirm');
//                        $filterobj=app::get('desktop')->model('filter');
//                        $confirm_filter = $filterobj->getFilter($filter_parames,$view);
//                        $base_filter = array_merge((array)$base_filter,(array)$confirm_filter);
                    }else{
                        $sub_menu = $orderCtl->_views_unmyown();
                        foreach ($sub_menu as $key => $value) {
                            if($view == $key){
                                $base_filter = array_merge((array)$value['filter'], $base_filter);
                            }
                        }
                    }
                     
                } elseif ($flt == 'active') { 
                    $base_filter['disabled']       = 'false';
                    $base_filter['is_fail']        = 'false';
                    $base_filter['status']         = 'active';
                    if($view>=999000){
//                        $filter_parames = array('model'=>'ome_mdl_orders','user_id'=>$base_filter['op_id'],'app'=>'ome','ctl'=>'admin_order','act'=>'active');
//                        $filterobj=app::get('desktop')->model('filter');
//                        $confirm_filter = $filterobj->getFilter($filter_parames,$view);
//                        $base_filter = array_merge((array)$base_filter,(array)$confirm_filter);
                    }else{
                        $sub_menu = $orderCtl->_view_active();
                        foreach ($sub_menu as $key => $value) {
                            if($view == $key){
                                $base_filter = array_merge((array)$value['filter'], $base_filter);
                            }
                        }
                    }
                }
                $op_id = kernel::single('desktop_user')->get_id();
                $groupObj = $this->app->model("groups");
                $op_group = $groupObj->get_group($op_id);
                if(kernel::single('desktop_user')->is_super()){
                    unset($base_filter['op_id']);
                    unset($op_group['group_id']);
                    unset($op_id);
                }
                $filter = array_merge((array)$_POST,(array)$base_filter);
                $this->pagedata['dialogform'] = true;
                $abnormalTypeModel = $this->app->model('abnormal_type');
                $abnormal_type = $abnormalTypeModel->getList("*");
                $this->pagedata['abnormal_type'] = $abnormal_type;
                break;
            case 'dispose_abnormal':
                $base_filter = array('archive'=>'0');
                $base_filter['op_id'] = kernel::single('desktop_user')->get_id();
                $base_filter['abnormal'] = 'true';
                $base_filter['is_fail'] = 'false';
                $base_filter['status'] = 'active';
                $base_filter['process_status'] = array('unconfirmed', 'confirmed', 'splitting');
                
                $op_id = kernel::single('desktop_user')->get_id();
                
                $groupObj = $this->app->model("groups");
                $op_group = $groupObj->get_group($op_id);
                if(kernel::single('desktop_user')->is_super()){
                    unset($base_filter['op_id']);
                    unset($op_group['group_id']);
                    unset($op_id);
                }
                
                $filter = array_merge((array)$_POST,(array)$base_filter);
                
                $this->pagedata['dialogform'] = true;
                
                //abnormal_type
                $abnormalTypeModel = $this->app->model('abnormal_type');
                $abnormal_type = $abnormalTypeModel->getList("*");
                $this->pagedata['abnormal_type'] = $abnormal_type;
                
                //取第一个订单信息
                $orderInfo = $orderModel->dump($filter, '*');
                $this->pagedata['orderInfo'] = $orderInfo;
                
                //取第一个订单异常信息
                $sql = "SELECT * FROM sdb_ome_abnormal WHERE order_id='". $orderInfo['order_id']."'";
                $abnoramlInfo = $orderModel->db->selectrow($sql);
                $this->pagedata['abnoramlInfo'] = $abnoramlInfo;
                
            break;
            default:
                echo "系统无法识别你的批量操作";exit;
                break;
        }

        //count
        $count = $orderModel->count($filter);

        $this->pagedata['total'] = $count;
        $this->pagedata['filter'] = http_build_query($filter);
        $this->pagedata['act']  = $act;
        $this->pagedata['flt']  = $flt;
        $this->pagedata['view'] = $view;

        $this->display('admin/order/batch/dialog.html');
    }


    /**
     * 针对订单
     * 所有的批量操作都应该放这里
     *
     * @return void
     * @author 
     **/
    public function batchopt($act,$flt,$view)
    {
       
        $result = true; $msg = '操作成功';
        // 订单
        $orderModel = app::get('ome')->model('orders');
        $filter = array();
        switch ($act) {
            //批量暂停
            case 'dopause':
                $page_no = intval($_GET['page_no']) ? intval($_GET['page_no']) : 1;
                $page_size = 10;
                $offset = 0;
                $total = intval($_GET['total']);
                parse_str($_POST['filter'],$filter);

                $orders = $orderModel->getList('order_id',$filter, $offset, $page_size);

                $succ_num = $fail_num = 0;
                if ($orders) {
                    foreach ((array) $orders as $order) {
                        $rs = $orderModel->pauseOrder($order['order_id'], false, '');

                        if ($rs) $succ_num++; else $fail_num++;
                    }
                }

                $result = array('status'=>'running','data'=>array('succ_num'=>$succ_num,'fail_num'=>$fail_num));

                if ( ($page_size * $page_no) >= $total) {
                    $result['status'] = 'complete';
                    $result['data']['rate'] = '100';
                } else {
                    $result['data']['rate'] =  $page_no * $page_size / $total * 100;
                }

                echo json_encode($result);exit;

                break;
            //批量恢复
            case 'renew':
                $page_no = intval($_GET['page_no']) ? intval($_GET['page_no']) : 1;
                $page_size = 10;
                $offset = 0;
                $total = intval($_GET['total']);
                parse_str($_POST['filter'],$filter);

                $orders = $orderModel->getList('order_id',$filter,$offset,$page_size);

                $succ_num = $fail_num = 0;
                if ($orders) {
                    foreach ((array) $orders as $order) {
                        $rs = $orderModel->renewOrder($order['order_id']);
                        if ($rs) $succ_num++; else $fail_num++;
                    } 
                }

                $result = array('status'=>'running','data'=>array('succ_num'=>$succ_num,'fail_num'=>$fail_num));

                if ( ($page_size * $page_no) >= $total) {
                    $result['status'] = 'complete';
                    $result['data']['rate'] = '100';
                } else {
                    $result['data']['rate'] =  $page_no * $page_size / $total * 100;
                }

                echo json_encode($result);exit;

                break;
            //批量设置为异常
            case 'doabnormal':
                $page_no = intval($_GET['page_no']) ? intval($_GET['page_no']) : 1;
                $page_size = 10;
                if($flt == 'unmyown'){
                    $offset = 0;
                }elseif ($flt == 'active'){
                    $offset = ($page_no-1)*$page_size;
                }
                $total = intval($_GET['total']);
                parse_str($_POST['filter'],$filter);
                $abnormal_data = $_POST['abnormal'];
                $orders = $orderModel->getList('order_id',$filter,$offset,$page_size);
                $succ_num = $fail_num = 0;
                $abnormalModel = $this->app->model('abnormal');
                if ($orders) {
                    foreach ((array) $orders as $order) {
                        $abnormal_data['order_id'] = $order['order_id'];
                        $abnormal = $abnormalModel->dump(array("order_id"=>$order['order_id']),"abnormal_id");
                        if(!empty($abnormal)) $abnormal_data['abnormal_id'] = $abnormal['abnormal_id'];
                        $orderModel->set_abnormal($abnormal_data);
                        unset($abnormal_data['order_id']);
                        unset($abnormal_data['abnormal_id']);
                        //取消发货单
                        define('FRST_TRIGGER_OBJECT_TYPE','发货单：订单异常取消发货单');
                        define('FRST_TRIGGER_ACTION_TYPE','ome_ctl_admin_order：do_abnormal');
                        $orderModel->cancel_delivery($order['order_id']);
                        $succ_num++;
                    } 
                }

                $result = array('status'=>'running','data'=>array('succ_num'=>$succ_num,'fail_num'=>$fail_num));

                if ( ($page_size * $page_no) >= $total) {
                    $result['status'] = 'complete';
                    $result['data']['rate'] = '100';
                } else {
                    $result['data']['rate'] =  $page_no * $page_size / $total * 100;
                }

                echo json_encode($result);exit;

            break;
            case 'dispose_abnormal':
                //批量处理异常
                $abnormalModel = $this->app->model('abnormal');
                
                $page_no = intval($_GET['page_no']) ? intval($_GET['page_no']) : 1;
                $page_size = 10;
                //$offset = ($page_no-1)*$page_size;
                $offset = 0; //每次处理后,都要从0开始
                
                $total = intval($_GET['total']);
                
                parse_str($_POST['filter'], $filter);
                
                $abnormal_data = $_POST['abnormal'];
                $orders = $orderModel->getList('order_id',$filter,$offset,$page_size);
                $succ_num = $fail_num = 0;
                
                //开始处理
                if ($orders){
                    foreach ((array) $orders as $order)
                    {
                        $abnormal_data['order_id'] = $order['order_id'];
                        $abnormal = $abnormalModel->dump(array("order_id"=>$order['order_id']), "abnormal_id");
                        
                        if(!empty($abnormal)) $abnormal_data['abnormal_id'] = $abnormal['abnormal_id'];
                        
                        $orderModel->set_abnormal($abnormal_data);
                        
                        unset($abnormal_data['order_id']);
                        unset($abnormal_data['abnormal_id']);
                        
                        //取消发货单
                        //define('FRST_TRIGGER_OBJECT_TYPE','发货单：订单异常取消发货单');
                        //define('FRST_TRIGGER_ACTION_TYPE','ome_ctl_admin_order：do_abnormal');
                        //$orderModel->cancel_delivery($order['order_id']);
                        
                        $succ_num++;
                    }
                }
                
                //result
                $result = array('status'=>'running', 'data'=>array('succ_num'=>$succ_num, 'fail_num'=>$fail_num));
                
                if(($page_size * $page_no) >= $total){
                    $result['status'] = 'complete';
                    $result['data']['rate'] = '100';
                } else {
                    $result['data']['rate'] =  $page_no * $page_size / $total * 100;
                }
                
                echo json_encode($result);
                exit;
            default:
                # code...
                break;
        }

    }
    
    /**
     * 批量替换订单明细中的基础物料
     */
    public function batchReplaceMaterial()
    {
        $orderObj = app::get('ome')->model('orders');
        $deliveryObj = app::get('ome')->model('delivery');
        
        if (empty($_POST['order_id']) || $_POST['isSelectedAll']=='_ALL_'){
            die('暂不支持全选');
        }
        
        $filter = array('order_id'=>$_POST['order_id']);
        $tempList = $orderObj->getList('order_id,order_bn,process_status,pay_status,pause', $filter, 0, -1, 'paytime DESC,createtime DESC');
        if (empty($tempList)){
            die('没有可操作的订单!');
        }
        
        // 订单确认状态
        $processStatus = array(
            'unconfirmed'   => '未确认',
            'confirmed'     => '已确认',
            'splitting'     => '部分拆分',
            'splited'       => '已拆分完',
            'cancel'        => '取消',
            'remain_cancel' => '余单撤销',
            'is_retrial'    => '复审订单',
            'is_declare'    => '跨境申报订单',
        );
        
        $orderIds = array();
        foreach($tempList as $val)
        {
            $order_id = $val['order_id'];
            
            //check
            $process_status = $val['process_status'];
            if(!in_array($val['process_status'], array('unconfirmed','confirmed','splitting'))){
                $error_msg = '订单号['. $val['order_bn'] .']状态是：'. $processStatus[$process_status] .'，不允许编辑!';
                die($error_msg);
            }
            
            // 检查订单是否暂停
            //if($val['pause'] == 'true'){
            //    $error_msg = '订单号['. $val['order_bn'] .']是暂停状态，请检查订单是否退款申请中!';
            //    die($error_msg);
            //}
            
            // 检查当前订单支付状态
            if($val['pay_status'] != '1'){
                $error_msg = '订单号['. $val['order_bn'] .']不是：已支付状态，不允许编辑!';
                die($error_msg);
            }
            
            
            // 检查订单未完成发货的发货单
            $deliveryIds = $deliveryObj->getDeliverIdByOrderId($order_id, true);
            if($deliveryIds){
                $error_msg = '订单号['. $val['order_bn'] .']有未完成发货的订单，不能编辑操作!';
                die($error_msg);
            }
            
            $orderIds[] = $val['order_id'];
        }
        
        if (empty($orderIds)){
            die('没有可批量编辑的订单!');
        }
        
        //参考第一个订单，获取所有商品及子商品
        $orderInfo = $orderObj->dump($orderIds[0],'*',array('order_objects'=>array('*',array('order_items'=>array('*')))));
        
        // 过滤出所有商品（不过滤类型，但过滤已删除的）
        $allObjects = array();
        $orderItemIds = [];
        foreach ($orderInfo['order_objects'] as $okey => $object)
        {
            // 过滤掉已删除的商品
            if ($object['delete'] == 'true') {
                continue;
            }
            
            // 过滤掉已删除的子商品
            $validItems = array();
            foreach ($object['order_items'] as $ikey => $item)
            {
                $item_id = $item['item_id'];
                
                // check
                if ($item['delete'] == 'true') {
                    continue;
                }
                
                $orderItemIds[$item_id] = $item_id;
                
                // 检测发货数量
                if($item['nums'] == $item['sendnum']){
                    $item['is_allow_replace'] = false;
                    $item['is_allow_delete'] = false;
                    $item['warning_message'] = '已发货';
                }elseif($item['sendnum'] > 0){
                    $item['is_allow_replace'] = false;
                    $item['is_allow_delete'] = false;
                    $item['warning_message'] = '部分发货';
                }elseif($item['nums'] == $item['split_num']){
                    $item['is_allow_replace'] = false;
                    $item['is_allow_delete'] = false;
                    $item['warning_message'] = '已拆分';
                }elseif($item['split_num'] > 0){
                    $item['is_allow_replace'] = false;
                    $item['is_allow_delete'] = false;
                    $item['warning_message'] = '部分拆分';
                }else{
                    $item['is_allow_replace'] = true;
                    $item['is_allow_delete'] = false;
                    $item['warning_message'] = '';
                }
                
                $validItems[] = $item;
            }
            
            // 如果商品还有有效的子商品，则保留该商品
            if (!empty($validItems)) {
                $object['order_items'] = $validItems;
                $allObjects[] = $object;
            }
        }
        
        // 获取销售物料信息，用于添加新赠品时显示子商品
        $this->pagedata['sales_material_info'] = array();
        if(!empty($allObjects)) {
            $smIds = array_column($allObjects, 'goods_id');
            $smModel = app::get('material')->model('sales_material');
            $salesMaterials = $smModel->getList('sm_id,sales_material_bn,sales_material_name', array('sm_id'=>$smIds));
            $this->pagedata['sales_material_info'] = array_column($salesMaterials, null, 'sm_id');
        }
        
        // 是否提醒报错
        $is_warn_msg = 'false';
        
        // order_objects
        $orderInfo['order_objects'] = $allObjects;
        
        // pagedata
        $this->pagedata['is_warn_msg'] = $is_warn_msg;
        $this->pagedata['orderInfo'] = $orderInfo;
        $this->pagedata['GroupList'] = json_encode($orderIds);
        $this->pagedata['custom_html'] = $this->fetch('admin/order/batch/replace_material.html');
        $this->pagedata['request_url'] = 'index.php?app=ome&ctl=admin_batch_order&act=doBatchReplaceMaterial';
        
        //调用desktop公用进度条
        parent::dialog_batch();
    }
    
    /**
     * Ajax执行批量替换订单明细中的基础物料
     */
    public function doBatchReplaceMaterial()
    {
        $orderObj = app::get('ome')->model('orders');
        $orderItemModel = app::get('ome')->model('order_items');
        $deliveryObj = app::get('ome')->model('delivery');
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        $orderEditLib = kernel::single('ome_order_edit');
        
        // 是否替换所有SET下的此赠品
        $is_replace_all_gift = false;
        if(isset($_POST['is_replace_all_gift']) && $_POST['is_replace_all_gift'] == 'true'){
            $is_replace_all_gift = true;
        }
        
        // 新增的销售物料
        $addGoods = array();
        if(isset($_POST['addGoods']) && $_POST['addGoods']){
            $addGoods = (array)$_POST['addGoods'];
            $addGoods = array_filter($addGoods);
        }
        
        $addGoodsNumber = (array)$_POST['addGoodsNumber']; //新增商品数量
        
        // 替换的子商品
        $replaceProduct = (array)$_POST['replaceProduct'];
        $replaceProduct = array_filter($replaceProduct);
        
        $replaceProductNumber = (array)$_POST['replaceProductNumber']; //替换商品数量
        
        $replaceItems = (array)$_POST['replace_items']; //替换的子商品，格式：item_id:new_sm_bn
        $replaceItems = array_filter($replaceItems);
        
        // 删除基础物料已禁用，仅支持替换
        $deletedItems = (array)$_POST['deleted_items'];
        $deletedItems = array_filter($deletedItems);
        
        // primary_id
        $order_ids = explode(',', $_POST['primary_id']);
        $order_ids = array_filter($order_ids);
        
        // 获取当前编辑的订单ID（UI界面中的参考订单）
        $current_order_id = intval($_POST['current_order_id']);
        
        // 验证数据
        if (empty($addGoods) && empty($replaceProduct) && empty($replaceItems)) {
            $error_msg = '订单无需修改操作';
            echo 'Error: '. $error_msg;
            exit;
        }

        if (!empty($deletedItems)) {
            $error_msg = '基础物料不允许删除，只支持替换';
            echo 'Error: '. $error_msg;
            exit;
        }
        
        if (empty($order_ids)){
            $error_msg = '请先选择修改的订单';
            echo 'Error: '. $error_msg;
            exit;
        }
        
        // 验证当前订单ID
        if (!$current_order_id) {
            $error_msg = '缺少当前编辑订单ID';
            echo 'Error: '. $error_msg;
            exit;
        }
        
        // 确保当前订单ID在要处理的订单列表中
        if (!in_array($current_order_id, $order_ids)) {
            $error_msg = '当前编辑订单不在处理列表中';
            echo 'Error: '. $error_msg;
            exit;
        }
        
        // 订单确认状态
        $processStatus = array(
            'unconfirmed'   => '未确认',
            'confirmed'     => '已确认',
            'splitting'     => '部分拆分',
            'splited'       => '已拆分完',
            'cancel'        => '取消',
            'remain_cancel' => '余单撤销',
            'is_retrial'    => '复审订单',
            'is_declare'    => '跨境申报订单',
        );
        
        // 获取当前编辑订单的详细信息，作为其他订单的参考
        $currentOrderInfo = $orderObj->dump($current_order_id, 'order_bn,pause,process_status,pay_status,member_id');
        if(empty($currentOrderInfo)){
            $error_msg = '当前编辑订单不存在';
            echo 'Error: '. $error_msg;
            exit;
        }
        
        // 检查当前订单状态
        $process_status = $currentOrderInfo['process_status'];
        if(!in_array($currentOrderInfo['process_status'], array('unconfirmed','confirmed','splitting'))){
            $error_msg = '订单号['. $currentOrderInfo['order_bn'] .']状态是：'. $processStatus[$process_status] .'，不允许编辑!';
            echo 'Error: '. $error_msg;
            exit;
        }
        
        // 检查当前订单是否暂停
        //if($currentOrderInfo['pause'] == 'true'){
        //    $error_msg = sprintf('当前订单[%s]已暂停，不允许编辑', $currentOrderInfo['order_bn']);
        //    echo 'Error: '. $error_msg;
        //    exit;
        //}
        
        // 检查当前订单支付状态
        if($currentOrderInfo['pay_status'] != '1'){
            $error_msg = sprintf('当前订单[%s]不是：已支付状态，不允许编辑', $currentOrderInfo['order_bn']);
            echo 'Error: '. $error_msg;
            exit;
        }
        
        // 结果数组
        $retArr = array(
            'itotal'  => count($order_ids),
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
            'success_orders' => array(),
            'failed_orders' => array(),
        );
        
        // 格式化order_objects商品层新添加的赠品
        $addObjectGoods = [];
        if($addGoods){
            foreach ($addGoods as $addKey => $goods_bn)
            {
                $quantity = isset($addGoodsNumber[$goods_bn]) ? intval($addGoodsNumber[$goods_bn]) : 0;
                
                // check
                if(empty($quantity)){
                    $error_msg = sprintf('新添加的赠品[%s]：购买数量为空，不允许编辑', $goods_bn);
                    echo 'Error: '. $error_msg;
                    exit;
                }
                
                if($quantity < 1){
                    $error_msg = sprintf('新添加的赠品[%s]：购买数量不能小于1', $goods_bn);
                    echo 'Error: '. $error_msg;
                    exit;
                }
                
                $addObjectGoods[$goods_bn] = ['goods_bn'=>$goods_bn, 'quantity'=>$quantity];
            }
        }
        
        // 格式化order_items子商品层替换的基础物料
        $replaceItemProducts = [];
        if($replaceProduct){
            foreach ($replaceProduct as $repalceKey => $product_bn)
            {
                $quantity = isset($replaceProductNumber[$product_bn]) ? intval($replaceProductNumber[$product_bn]) : 0;
                $replace_item = isset($replaceItems[$repalceKey]) ? $replaceItems[$repalceKey] : '';
                
                // check
                if(empty($quantity)){
                    $error_msg = sprintf('替换的基础物料编码[%s]：购买数量为空，不允许编辑', $product_bn);
                    echo 'Error: '. $error_msg;
                    exit;
                }
                
                if(empty($replace_item)){
                    $error_msg = sprintf('替换的基础物料编码[%s]：未找到原始子赠品，不允许编辑', $product_bn);
                    echo 'Error: '. $error_msg;
                    exit;
                }
                
                $tempData = explode(':', $replace_item);
                $item_id = $tempData[0];
                
                // 订单子商品信息
                $itemInfo = $orderItemModel->dump(['order_id'=>$current_order_id, 'item_id'=>$item_id, 'delete'=>'false'], '*');
                if(empty($itemInfo)){
                    $error_msg = sprintf('替换的基础物料编码[%s]：未找到原订单子商品，不允许编辑', $product_bn);
                    echo 'Error: '. $error_msg;
                    exit;
                }
                
                // 获取替换的基础物料信息
                $bmInfo = $basicMaterialObj->dump(array('material_bn'=>$product_bn), '*');
                if(empty($bmInfo)) {
                    $error_msg = sprintf('替换的基础物料编码[%s]：基础物料信息不存在，不允许编辑', $product_bn);
                    echo 'Error: '. $error_msg;
                    exit;
                }
                
                // 被替换基础物料与新基础物料，不能一样
                if($itemInfo['bn'] == $product_bn){
                    $error_msg = sprintf('替换的基础物料编码[%s]：不能替换为自身，不允许编辑', $product_bn);
                    echo 'Error: '. $error_msg;
                    exit;
                }
                
                // 使用原订单明细上的购买数量
                $quantity = $itemInfo['quantity'];
                
                $replaceItemProducts[$product_bn] = [
                    'original_item_type' => $itemInfo['item_type'], // 原子商品行的类型
                    'original_product_id' => $itemInfo['product_id'], // 原子商品的货品ID
                    'original_product_bn' => $itemInfo['bn'], // 原子商品的货号
                    'original_product_nums' => $quantity, // 原子商品的购买数量
                    'replace_product_id' => $bmInfo['bm_id'], // 替换的新子赠品ID
                    'replace_product_bn' => $product_bn, // 替换的新子赠品货号
                    'replace_product_nums' => $quantity, // 替换的新子赠品购买数量
                ];
            }
        }
        
        // 验证数据
        if (empty($addObjectGoods) && empty($replaceItemProducts)) {
            $error_msg = '订单没有相关修改操作';
            echo 'Error: '. $error_msg;
            exit;
        }
        
        // 准备参数
        $params = array(
            'addObjectGoods' => $addObjectGoods, // 新添加的赠品
            'replaceItemProducts' => $replaceItemProducts, // 替换的子商品
            'is_replace_all_gift' => $is_replace_all_gift, // 是否替换所有赠品
        );
        
        // 处理每个订单
        foreach($order_ids as $order_id)
        {
            $order_id = intval($order_id);
            
            if (!$order_id) continue;
            
            // 获取订单信息
            $orderInfo = $orderObj->dump($order_id, 'order_id,order_bn,pause,process_status,pay_status,member_id');
            if(empty($orderInfo)){
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('ERROR：订单ID[%s]不存在', $order_id);
                $retArr['failed_orders'][] = $order_id;
                continue;
            }
            
            // 检查订单状态
            $process_status = $orderInfo['process_status'];
            if(!in_array($orderInfo['process_status'], array('unconfirmed','confirmed','splitting'))){
                $retArr['ifail']++;
                $retArr['err_msg'][] = '订单号['. $orderInfo['order_bn'] .']状态是：'. $processStatus[$process_status] .'，不能进行编辑操作!';
                $retArr['failed_orders'][] = $order_id;
                continue;
            }
            
            // 检查订单是否暂停
            //if($orderInfo['pause'] == 'true'){
            //    $retArr['ifail']++;
            //    $retArr['err_msg'][] = sprintf('ERROR：订单号[%s]已暂停，不允许编辑', $orderInfo['order_bn']);
            //    $retArr['failed_orders'][] = $order_id;
            //    continue;
            //}
            
            // 检查当前订单支付状态
            if($orderInfo['pay_status'] != '1'){
                $error_msg = sprintf('当前订单[%s]不是：已支付状态，不允许编辑', $orderInfo['order_bn']);
                echo 'Error: '. $error_msg;
                exit;
            }
            
            // 检查订单未完成发货的发货单
            $deliveryIds = $deliveryObj->getDeliverIdByOrderId($order_id, true);
            if($deliveryIds){
                $error_msg = '订单号['. $orderInfo['order_bn'] .']有未完成发货的订单，禁止编辑操作。';
                echo 'Error: '. $error_msg;
                exit;
            }
            
            // 开启事务
            kernel::database()->beginTransaction();
            
            try {
                // 为每个订单创建独立的参数，以当前订单为参考
                $editParams = $params;
                
                // 执行编辑任务
                $err_msg = '';
                $result = $orderEditLib->replaceOrderItems($order_id, $editParams, $err_msg);
                if(!$result){
                    $retArr['ifail']++;
                    $retArr['err_msg'][] = 'ERROR：'.$err_msg;
                    
                    //回滚事务
                    kernel::database()->rollBack();
                    
                    continue;
                }
                
                // 提交事务
                kernel::database()->commit();
                
                // 记录成功
                $retArr['isucc']++;
                $retArr['success_orders'][] = array(
                    'order_id' => $order_id,
                    'order_bn' => $orderInfo['order_bn']
                );
                
            } catch (Exception $e) {
                // 回滚事务
                kernel::database()->rollBack();
                
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('ERROR：订单[%s]处理失败: %s', $orderInfo['order_bn'], $e->getMessage());
                $retArr['failed_orders'][] = $order_id;
            }
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 查找基础物料页面（用于替换选择）
     */
    public function findBasicMaterial()
    {
        $this->view_source = 'dialog';
        
        //只能选择可售的物料
        $base_filter = [];
        $base_filter['visibled'] = 1;
        
        if (in_array($_GET['type'],array('2','3'))) {
            $base_filter['type'] = $_GET['type'];
        }
        
        if ($_GET['type'] == 'giftpackage'){
            $base_filter['type'] = array('1','3');
        }
        
        if (isset($_GET['cos_id'])) {
            $base_filter['cos_id'] = $_GET['cos_id'];
        }
        
        $params = array(
            'title'                  => '基础物料列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'base_filter'            => $base_filter,
        );
        $this->finder('material_mdl_basic_material', $params);
    }
    
    function getSelectBasicMaterial()
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        $bm_id = $_POST['bm_id'];
        $material_bn= $_GET['material_bn'];
        $material_name = $_GET['material_name'];
        
        if (is_array($bm_id)){
            if ($bm_id[0] == "_ALL_"){
                $filter = '';
            }else {
                $filter['bm_id'] = $bm_id;
            }
        }
        
        if($material_bn){
            $filter = array(
                'sales_material_bn'=>$material_bn
            );
        }
        
        if($material_name){
            $filter = array(
                'material_name'=>$material_name
            );
        }
        
        //$filter['use_like'] = 1;
        $data = $basicMaterialObj->getList('*', $filter,0,-1);
        
        if (!empty($data)){
            foreach ($data as $k => &$item)
            {
                $item['num'] = 1;
                $item['price'] = 0;
                $item['product_id'] = $item['bm_id'];
                
                $rows[] = $item;
            }
        }
        
        echo json_encode($rows);
    }
}
?>
