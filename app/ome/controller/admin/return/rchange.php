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
class ome_ctl_admin_return_rchange extends desktop_controller
{
    public $name       = "退换货单";
    public $workground = "aftersale_center";

    //显示列表
    public function index($is_jingxiao = false)
    {
        $use_buildin_import = false;
        
        //setting
        $batch_approve = array(
            'label'  => '批量审核',
            'submit' => 'index.php?app=ome&ctl=admin_return_rchange&act=batch_approve',
            'target' => "dialog::{width:700,height:490,title:'批量审核'}",
        );

        $modifyReshipBranch = array(
            'label'  => '修改退货仓',
            'submit' => 'index.php?app=ome&ctl=admin_return_rchange&act=modifyReshipBranch',
            'target' => "dialog::{width:700,height:490,title:'修改退货仓'}",
        );
        
        $batch_refuse = array(
            'label'  => '批量拒绝',
            'submit' => 'index.php?app=ome&ctl=admin_return_rchange&act=batch_refuse',
            'target' => "dialog::{width:600,height:350,title:'批量拒绝'}",
        );
        
        $batch_sync = array(
            'label'   => '推送至WMS',
            'submit'  => 'index.php?app=ome&ctl=admin_return_rchange&act=batch_sync',
            'confirm' => '你确定要对勾选的退货单发送至第三方吗？',
            'target'  => 'refresh',
        );
        
        $batch_search = array(
            'label'  => '查询WMS状态',
            'submit' => $this->url . '&act=batch_search',
            'target' => 'refresh',
        );
        
        $batchCancelWms = array(
            'label' => '请求WMS取消',
            'submit' => $this->url."&act=batchCancelWms",
            'target' => 'dialog::{width:690,height:350,title:\'批量请求WMS取消退换货单\'}"'
        );
        
        $batchModifyReason = array(
            'label' => app::get('ome')->_('批量修改售后原因'),
            'submit' => $this->url."&act=batchModifyReason",
            'target' => 'dialog::{width:690,height:350,title:\'批量修改售后原因\'}"'
        );
        
        $batchGetAddress = array(
            'label' => app::get('ome')->_('批量获取寄件地址'),
            'submit' => $this->url."&act=batchGetAddress",
            'target' => 'dialog::{width:690,height:350,title:\'批量获取寄件地址\'}"'
        );
        
        $batch_sync_logic = array(
            'label'  => '批量同步WMS物流单号',
            'submit'  => 'index.php?app=ome&ctl=admin_return_rchange&act=batch_sync_logic',
            'confirm' => '你确定要对勾选的退货单发送至第三方吗？',
            'target'  => 'refresh',
        );
        
        $doBatchCheck = array(
            'label'  => '批量最终收货',
            // 'submit'  => 'index.php?app=ome&ctl=admin_return_rchange&act=doBatchCheck',
            'submit'  => 'index.php?app=ome&ctl=admin_return_rchange&act=dispatchDailog',
            // 'confirm' => '你确定要对勾选的退货单进行批量最终售后操作吗？',
            'target'  => 'dialog::{width:600,height:300,title:\'批量最终收货\'}',
        );
        
        $exportTemplate = array(
            'label'  => '导出模板',
            'href'   => 'index.php?app=ome&ctl=admin_return_rchange&act=exportTemplate',
            'target' => '_blank',
        );
        
        //禁止使用此按钮，防止客户批量误操作
//        $forceCancel = array(
//            'label' => app::get('ome')->_('强制取消退换货单'),
//            'submit' => $this->url."&act=forceCancel",
//            'target' => 'dialog::{width:700,height:450,title:\'强制取消退、换货单（直接取消单据，不会同步平台状态）\'}"'
//        );
        
        //action
        $actions = [];
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        
        if(empty($_GET['view']) || $_GET['view'] == '0'){
            $use_buildin_import = true;
            
            $actions[] = array(
                'label'  => '新建退换货单',
                'href'   => 'index.php?app=ome&ctl=admin_return_rchange&act=rchange',
            );
            
            $actions[] = $batch_approve;

            $actions[] = $modifyReshipBranch;
            
            $actions[] = $batch_refuse;
            
            $actions[] = array(
                'label' => 'WMS操作',
                'group' => array($batchCancelWms),
            );
            
            $actions[] = array(
                'label' => '批量操作',
                'group' => array($batchModifyReason, $batchGetAddress)
            );
            
            $actions[] = $exportTemplate;
        }
        
        if (in_array($_GET['view'], array('2','3','11'))) {
            $actions[] = $batch_refuse;
            
            $actions[] = array(
                'label' => 'WMS操作',
                'group' => array($batch_sync, $batch_search, $batchCancelWms),
            );
            
            $actions[] = array(
                'label' => '批量操作',
                'group' => array($batchModifyReason, $batchGetAddress)
            );
        } elseif (in_array($_GET['view'], ['1', '18'])) {
            $actions[] = $batch_approve;
            
            $actions[] = $batch_refuse;
            $actions[] = $modifyReshipBranch;
            
            $actions[] = array(
                'label' => 'WMS操作',
                'group' => array($batchCancelWms),
            );
            
            $actions[] = array(
                'label' => '批量操作',
                'group' => array($batchModifyReason, $batchGetAddress)
            );
        }elseif($_GET['view'] == '16') {
            $actions[] = $batch_sync_logic;
        }elseif($_GET['view'] == '8') {
            $actions[] = array(
                'label' => 'WMS操作',
                'group' => array($batch_search),
            );
        }elseif($_GET['view'] == '13'){
            $actions[] = $doBatchCheck;
        }
        
        //params
        $params = array(
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_import'     => $use_buildin_import,
            'use_buildin_filter'     => true,
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ]
        );
        
        $params['use_buildin_export'] = true;
        $params['title'] = '退换货单';
        $params['actions'] = $actions;
        
        $this->workground  = "aftersale_center";
        
        //return_type
        if (isset($_POST['return_type'])) {
            $params['base_filter']['return_type'] = $_POST['return_type'];
        } else {
            //filter_sql
            $filter_sql = array();
            $filter_sql[] = "(return_type IN ('return','change'))"; //修复小Tab栏目里不显示return_type退换货类型
            
            //未录入退回物流号、已录入物流号未审核
            if ($_GET['view'] == '11'){
                $filter_sql[] = "(return_logi_no IS NULL OR return_logi_no='')";
            }elseif ($_GET['view'] == '18'){
                $filter_sql[] = "(return_logi_no!='' AND return_logi_no IS NOT NULL AND is_check IN ('0'))";
            }
            
            //merge
            if ($filter_sql) {
                $params['base_filter']['filter_sql'] = implode(' AND ',$filter_sql);
            }
        }

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $params['base_filter']['org_id'] = $organization_permissions;
        }

        # 权限判定
        if (!$this->user->is_super()) {
            $add_rchange_permission = kernel::single('desktop_user')->has_permission('aftersale_rchange_add');
            if (!$add_rchange_permission) {
                unset($params['actions'][0]);
            }
        }
        
        //如果没有导出权限，则屏蔽导出按钮
        $params['use_buildin_export'] = kernel::single('desktop_user')->has_permission('aftersale_rchange_export');

        if ($is_jingxiao) {
            $params['actions'] = [];
            $params['base_filter']['delivery_mode'] = 'jingxiao';
            $params['use_buildin_import'] = false;
            $params['use_buildin_export'] = false;
            $params['title'] = '平台自发退换货单';
        } else {
            if (!$params['base_filter']['filter_sql']) {
                $params['base_filter']['filter_sql'] = '1';
            }
            $params['base_filter']['filter_sql'] .= ' AND delivery_mode <> "jingxiao"';
        }
        
        $this->finder('ome_mdl_reship', $params);
    }

    public function jingxiao()
    {
        $is_jingxiao = true;
        return $this->index($is_jingxiao);
    }

    //tab展示
    public function _views()
    {
        $sub_menu = $this->_view_all();
        return $sub_menu;
    }

    public function _view_all()
    {
        $mdl_reship  = $this->app->model('reship');
        
        //修复小Tab栏目里不显示return_type退换货类型
        //$base_filter = array('return_type' => array('return', 'change'));
        $base_filter['filter_sql'] =  "(return_type IN ('return','change'))";
        
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $base_filter['org_id'] = $organization_permissions;
        }

        $sub_menu = array(
            0  => array('label' => __('全部'), 'filter' => $base_filter, 'optional' => false),
            1  => array('label' => __('未审核'), 'filter' => array('is_check'=>'0'), 'optional' => false),
            2  => array('label' => __('审核成功'), 'filter' => array('is_check' => '1'), 'optional' => false),
            3  => array('label' => __('审核失败'), 'filter' => array('is_check' => '2'), 'optional' => false),
            //4 => array('label'=>__('收货成功'),'filter'=>array('is_check'=>'3'),'optional'=>false),
            //5 => array('label'=>__('拒绝收货'),'filter'=>array('is_check'=>'4'),'optional'=>false),
            // 6  => array('label' => __('拒绝'), 'filter' => array('is_check' => '5'), 'optional' => false),
            // 7  => array('label' => __('补差价'), 'filter' => array('is_check' => '6'), 'optional' => false),
            8 => array('label' => __('完成'), 'filter' => array('is_check' => '7'), 'optional' => false),
            // 9  => array('label' => __('质检通过'), 'filter' => array('is_check' => '8'), 'optional' => false),
            // 10 => array('label' => __('拒绝质检'), 'filter' => array('is_check' => '9'), 'optional' => false),
            11 => array('label' => __('未录入退回物流号'), 'filter' => array('filter_sql' => '({table}return_logi_no IS NULL OR {table}return_logi_no="")'), 'optional' => false),
            //12 => array('label' => __('质检异常'),    'filter' => array('filter_sql' => '({table}is_check="10" or ({table}need_sv="false" and {table}is_check="0"))', 'optional' => false)),
            13 => array('label' => __('待确认'),      'filter' => array('is_check' => '11')),
            //14 => array('label' => __('待商家处理P'), 'filter' => array('platform_status' => '6'),'addon' => '_FILTER_POINT_'),
            //15 => array('label' => __('待商家收货P'), 'filter' => array('platform_status' => '11', ),'addon' => '_FILTER_POINT_'),
            16 => array('label' => __('异常'), 'filter' => array('is_check|notin' => ['5','7','9'], 'abnormal_status|than'=>'0'),'addon'=>'_FILTER_POINT_'),
            17 => array('label' => __('卖家拒绝退款'), 'filter' => array('platform_status'=>array('SELLER_REFUSE_BUYER', '29')), 'optional' => false,'addon'=>'_FILTER_POINT_'),
            18 => array('label'=>__('已录入物流号未审核'),'filter'=>array('filter_sql'=>'({table}return_logi_no!="" AND {table}return_logi_no IS NOT NULL AND {table}is_check="0")'), 'optional'=>false),
            19 => array('label'=>__('取消失败'),'filter'=>array('sync_status' => '4'), 'optional'=>false),
        );

        $act = 'index';
        foreach ($sub_menu as $k => $v)
        {
            if (!IS_NULL($v['filter'])) {
                //未录入退回物流号、已录入物流号未审核
                if(in_array($k, array(11, 18))){
                    //org_id
                    if($base_filter['org_id']){
                        $v['filter']['org_id'] = $base_filter['org_id'];
                    }
                }else{
                    $v['filter'] = array_merge($v['filter'], $base_filter);
                }
            }

            if ($_GET['act'] == 'jingxiao') {
                $act = $_GET['act'];
                $v['filter']['delivery_mode'] = $act;
            } else {
                if (!$v['filter']['filter_sql']) {
                    $v['filter']['filter_sql'] = '1';
                }
                $v['filter']['filter_sql'] .= ' AND delivery_mode <> "jingxiao"';
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $v['addon'] ? $v['addon'] : $mdl_reship->viewcount($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=ome&ctl=admin_return_rchange&act=' . $act . '&view=' . $k;
        }
        
        return $sub_menu;
    }

    //新增弹窗页
    public function rchange()
    {
        $return_type = array('return' => '退货', 'change' => '换货');
        if ($_GET['type']) {
            if ($_GET['type'] == 'change') {
                $return_type = array('change' => '换货', 'return' => '退货');
            }
            $reship_data['return_type']    = $_GET['type'];
            $this->pagedata['reship_data'] = $reship_data;
        }

        $this->pagedata['return_type']  = $return_type;
        $this->pagedata['order_filter'] = array('pay_status' => '1', 'ship_status' => '1');
        $oProblem                       = $this->app->model('return_product_problem');
        $list                           = $oProblem->getList('problem_id,problem_name', array('disabled' => 'false'));
        $this->pagedata['problem_type'] = $list;

        //退货仓库设置
        $branchtype                   = app::get('wms')->getConf('wms.branchset.type');
        $this->pagedata['branchtype'] = $branchtype;

        //只显示电商仓
        $branchObj                     = app::get('ome')->model('branch');
        $branch_list                   = $branchObj->getlist('branch_id,name', array('disabled' => 'false', 'b_type' => 1));
        $this->pagedata['branch_list'] = $branch_list;
        unset($branch_list);

        $source                   = trim($_GET['source']);
        $this->pagedata['source'] = $source;
        
        // 获取页面配置项
        $this->getPageConfigs();
        
        $this->display('admin/return_product/rchange/rchange.html');
    }

    //新增或者编辑 退换货单ome_reship
    public function add_rchange()
    {
        $this->begin('index.php?app=ome&ctl=admin_return_rchange&act=index&finder_vid=378969');
        
        //获取所有传递参数
        $post = kernel::single('base_component_request')->get_params(true);
       
        $post['flag_type_text']=='kt' ? '' : $post['flag_type_text'];
        //编辑 存在reship_id 获取is_check当前状态
        $Oreship = $this->app->model('reship');
        $post['is_check'] = null;
        $post['flag_type'] = 0;
        if ($post['reship_id']) {
            //当前状态
            $reshipinfo = $Oreship->dump($post['reship_id'], 'is_check,change_status,flag_type');
            if ($reshipinfo['is_check'] == '7') {
                $this->end(false, '此单据已完成!');
            }
            $post['is_check']      = $reshipinfo['is_check'];
            $post['change_status'] = $reshipinfo['change_status'];
            $post['flag_type']     = $reshipinfo['flag_type'];
        }

        //格式化换货数据
        $rchangeObj = kernel::single('ome_return_rchange');
        $post = $rchangeObj->format_rchange_data($post);
        
        if ($post["branch_id"] == "-1") {
            $this->end(false, '门店发货仓不支持售后');
        }

        //换货需验证退入和换出的仓库
        if ($post["return_type"] == "change") {
            $error_msg = '';
            $result_branch_check = kernel::single('o2o_return')->check_reship_branch($post["branch_id"], $post["changebranch_id"], $error_msg);
            if (!$result_branch_check) {
                $this->end(false, $error_msg);
            }
        }

        $mdl_ome_orders = app::get('ome')->model('orders');
        $rs_current_order = $mdl_ome_orders->dump($post["order_id"], 'order_id,order_bn,pay_status,is_cod');
        if ($rs_current_order["pay_status"] == "5" && $post["return_type"] == "change") {
            //全额退款订单 并且是换货的
            $this->end(false, '全额退款订单不能做换货!');
        }
        
        //货到付款订单
        if($rs_current_order['is_cod'] == 'true' || $rs_current_order['shipping']['is_cod'] == 'true'){
            $post['is_cod_order'] = 'true';
        }
        
        //售后申请单信息
        if($post['return_bn']){
            $returnProductObj = $this->app->model('return_product');
            $returnProductInfo = $returnProductObj->dump(array('return_bn'=>$post['return_bn']), '*');
            if($returnProductInfo){
                //平台售后状态
                $post['platform_status'] = $returnProductInfo['platform_status'];
            }
        }
        
        //数据验证(商品、库存等)
        $v_msg = '';
        if (!$Oreship->validate($post, $v_msg)) {
            $this->end(false, $v_msg);
        }
    
        // 验证页面配置项
        $this->validatePageConfigs($post);
        
        //校验发货物流单号是否属于此订单
        if($post['logi_no'] && $rs_current_order){
            $deliveryMdl = app::get('ome')->model('delivery');
            
            //[虚拟发货]物流单号都是：None
            if(strtolower($post['logi_no']) == 'none'){
                //通过订单ID查询发货单信息
                $sql = "SELECT dord.* FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id) ";
                $sql .= " WHERE dord.order_id=". $rs_current_order['order_id'] ." AND d.status IN ('succ', 'return_back')";
                $dlyOrderInfo = $deliveryMdl->db->selectrow($sql);
                if(empty($dlyOrderInfo)){
                    $error_msg = '未找到关联的发货单，请检查！';
                    $this->end(false, $error_msg);
                }
            }else{
                //根据物流单号查询发货单信息
                $deliveryInfo = $deliveryMdl->getList('delivery_id,delivery_bn', array('logi_no'=>$post['logi_no']));
                if(empty($deliveryInfo)){
                    $error_msg = '物流单号：'. $post['logi_no'] .'不是选择订单对应的发货物流单，请检查！';
                    $this->end(false, $error_msg);
                }
                
                //通过发货单ID+订单ID查询发货单信息
                $sql = "SELECT dord.* FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id) ";
                $sql .= " WHERE d.delivery_id in (". implode(',', array_column($deliveryInfo, 'delivery_id')) .") AND dord.order_id=". $rs_current_order['order_id'] ." AND d.status IN ('succ', 'return_back')";
                $dlyOrderInfo = $deliveryMdl->db->selectrow($sql);
                if(empty($dlyOrderInfo)){
                    $error_msg = '物流单号：'. $post['logi_no'] .'不属于选择的订单号，请检查！';
                    $this->end(false, $error_msg);
                }
            }
            $post['delivery_id'] = $deliveryInfo['delivery_id'];
        }
        if($post['flag_type_text'] == 'ydt') {
            $post['flag_type'] = $post['flag_type'] | ome_reship_const::__LANJIE_RUKU;
        } else {
            $post['flag_type'] = $post['flag_type'] & ~ome_reship_const::__LANJIE_RUKU;
        }
    
        $shop_id  = $post['shop_id'] ?: 0;
        $shopInfo = app::get('ome')->model('shop')->db_dump(['shop_id' => $shop_id]);
        if ($shopInfo && $shopInfo['delivery_mode'] != 'jingxiao' && empty($post['brnach_id'])) {
            //取默认退货仓
            $return_auto_branch = app::get('ome')->getConf('return.auto_branch');
            if ($return_auto_branch) {
                $post['brnach_id'] = $return_auto_branch;
            }
            $return_auto_shop_branch = app::get('ome')->getConf('return.auto_shop_branch');
            if ($return_auto_shop_branch[$shop_id]) {
                $post['branch_id'] = $return_auto_shop_branch[$shop_id];
            }
        }
        
        //新增生成退换货单
        $msg = '';
        $reship_bn = $Oreship->create_treship($post, $msg);
        if ($reship_bn == false) {

            $this->end(false, $msg);
        }
        
        //补差价
        if ($reship_bn) {
            if ($rs_current_order["pay_status"] == "5") {
                //全额退款订单
                $post["pay_status"] = "5";
            }
            $rchangeObj->update_diff_amount($post, $reship_bn);
        }

        $reship_id = $post['reship_id'];
        if (!$reship_id) {
            //新增直接审核需要获取新的reship_id js这块需要直接跳转save_check审核方法
            $reship    = $Oreship->getList('reship_id', array('reship_bn' => $reship_bn), 0, 1);
            $reship_id = $reship[0]['reship_id'];
        }
        $params = array("reship_id" => $reship_id);

        //退换货单自动审批(只处理新建的退换货单)
        if (empty($reshipinfo) && $post['is_confirm'] != 'true') {
            $is_auto_approve = app::get('ome')->getConf('return.auto_approve');
            if ($is_auto_approve == 'on') {
                $reshipLib = kernel::single('ome_reship');
                $result = $reshipLib->batch_reship_queue($reship[0]['reship_id']);
            }
        }

        // 商家代客填写退货单号回传
        !$reshipinfo && $reshipinfo = $reship[0];
        if ($reshipinfo['source'] != 'local') {
            kernel::single('ome_service_aftersale')->aftersaleSubmitReturnInfo($reship_id);
        }

        $this->end(true, $msg, null, $params);
    }

    /**
     * 根据order_bn快速获取订单信息（售后申请、退换货单2处加载订单信息）
     *
     * @author yangminsheng
     **/
    public function getOrderinfo()
    {
        $order_bn = trim($_GET['order_bn']);
        $source   = trim($_GET['source']);
        if ($order_bn) {
            //已支付部分退款并且已发货或部分退货的款到发货订单或货到付款已发货或部分退货的订单（包括全额退款的）
            $base_filter = array('disabled'=>'false', 'is_fail'=>'false', 'ship_status'=>array('1', '3','2'), 'order_bn'=>$order_bn);
            $order = $this->app->model('orders');
            $data = $order->getList('order_id,order_bn,pay_status,is_cod,ship_status', $base_filter);
            if ($source) {
                $is_archive = kernel::single('archive_order')->is_archive($source);
                if ($is_archive) {
                    $archive_ordObj = kernel::single('archive_interface_orders');
                    $data           = $archive_ordObj->getOrder_list($base_filter, 'order_id,order_bn');
                }
            }
            
            //检查订单是否符合条件
            foreach($data as $key => $val)
            {
                $pay_status = $val['pay_status'];
                
                //check
                if($val['is_cod']=='true' || $val['shipping']['is_cod']=='true'){
                    
                    //支持货到付款--已发货的订单,创建退货单;
                    
                }elseif(!in_array($pay_status, array('1', '4', '5', '6'))){
                    unset($data[$key]);
                    continue;
                }
            }
            
            echo "window.autocompleter_json=" . json_encode($data);
        }
    }

    //弹窗选择订单带入信息
    public function getOdersById()
    {
        $source   = trim($_GET['source']);
        $order_id = $_POST['id'];
        if ($order_id) {
            if ($source && in_array($source, array('archive'))) {
                $archive_ordObj = kernel::single('archive_interface_orders');
                $base_filter    = array('is_fail' => 'false', 'order_id' => $order_id, 'ship_status' => array('1', '3','2'), 'pay_status' => array('1', '4','6','5'));
                // if (isset($_GET["fullRefund"])) {
                //     //包括全额退款
                //     $base_filter['pay_status'] = array('1', '4', '5');
                // }
                $data = $archive_ordObj->getOrders($base_filter, 'order_id,order_bn');
            } else {
                $orders      = $this->app->model('orders');
                $base_filter = array('disabled' => 'false', 'is_fail' => 'false', 'order_id' => $order_id, 'ship_status' => array('1', '3'), 'pay_status' => array('1', '4', '6', '5'));
                // if (isset($_GET["fullRefund"])) {
                //     //包括全额退款
                //     $base_filter['pay_status'] = array('1', '4', '5');
                // }
                $data = $orders->dump($base_filter, 'order_id,order_bn');
            }
            $res['name'] = $data['order_bn'];
            $res['id']   = $data['order_bn'];
            echo json_encode($res);
            exit;
        }
    }

    //获取订单信息
    public function ajax_getOrderinfo()
    {
        $json_data = array('rsp' => 'fail', 'msg' => '');
        $post      = kernel::single('base_component_request')->get_params(true);
        $source    = $_GET['source'];
        if ($source && in_array($source, array('archive'))) {
            $oOrders   = kernel::single('archive_interface_orders');
            $oDelivery = kernel::single('archive_interface_delivery');
            $order     = $oOrders->getOrders(array('order_id' => $post['order_id']), '*');
        } else {
            $oOrders   = $this->app->model('orders');
            $oDelivery = $this->app->model('delivery');
            $order     = $oOrders->dump(array('order_id' => $post['order_id']), '*');
        }
        $oProduct = $this->app->model('return_product');
        if ($order) {
            $member                     = $this->app->model('members')->dump(array('member_id' => $order['member_id']));
            $json_data['rsp']           = 'succ';
            $this->pagedata['order_id'] = $order['order_id'];;
            if($_POST['dly_logi_no'] && $_POST['dly_logi_no'] != 'undefined') {
                $delivery = app::get('ome')->model('delivery')->getList('*', ['logi_no'=>$_POST['dly_logi_no']]);
                $branch = kernel::database()->selectrow("SELECT * FROM sdb_ome_branch WHERE disabled='false' AND branch_id=" . intval($delivery[0]['branch_id']));
                $delivery[0]['branch_name'] = $branch['name'];
            } else {
                $delivery                   = $oDelivery->getDeliveryByOrder('*', $post['order_id']);
            }
            unset($delivery[0]['shop_id']);
            $order = array_merge($order, (array)$delivery[0]);
            //加入了订单门店仓履约售后 判断是否是门店仓
            $branchLib = kernel::single('ome_branch');
            $store_id  = $branchLib->isStoreBranch($order["branch_id"]);
            if ($store_id) {
                //是门店仓
                $order["branch_list"] = kernel::single('o2o_return')->get_aftersale_o2o_branch($order["branch_id"]);
                //售后设置->退货仓库设置 类型选择：发货仓库的
                $branchtype = app::get('wms')->getConf('wms.branchset.type');
                if ($branchtype == "delivery") {
                    //如果当前门店仓不支持售后的
                    $support_aftersale = false;
                    foreach ($order["branch_list"] as $var_ob) {
                        if ($order["branch_id"] == $var_ob["branch_id"]) {
                            $support_aftersale = true;
                            break;
                        }
                    }
                    if (!$support_aftersale) {
                        $order["branch_id"]   = "-1";
                        $order["branch_name"] = "门店发货仓不支持售后";
                    }
                }
            } else {
                //是电商仓
                $order["branch_list"] = app::get('ome')->model('branch')->getlist('branch_id,name', array('disabled' => 'false', 'b_type' => 1));
            }
            $order['member_uname']       = $member['account']['uname'];
            $order['createtime']         = date('Y-m-d H:i:s', $order['createtime']);
            $this->pagedata['ship_area'] = $order['ship_area'];
            $order['ship_area']          = $this->fetch('admin/return_product/rchange/show_area.html');
            $json_data['msg']            = $order;
        }
        echo json_encode($json_data);
        exit;
    }

    /**
     * @param Int $order_id 订单ID
     * @param String $type 数据类型 return:退货、change:换货
     */
    public function get_data($order_id, $type, $source = '')
    {
        $orderMdl = app::get('ome')->model('orders');
        $orderItemMdl = app::get('ome')->model('order_items');
        $reshipObj = $this->app->model('reship');
        $oReship_item = $this->app->model('reship_items');
        
        //订单信息
        $orderInfo = $orderMdl->dump(array('order_id'=>$order_id), 'order_id,order_bn,pay_status,is_cod,ship_status');
        $is_cod = false;
        if($orderInfo['is_cod'] == 'true' || $orderInfo['shipping']['is_cod'] == 'true'){
            $is_cod = true;
        }
        
        //获取仓库模式
        $newItems       = array();
        $tmp_product    = array();
        $archive_ordObj = kernel::single('archive_interface_orders');
        if ($source == 'archive') {
            $order_object = $archive_ordObj->getOrder_object(array('order_id' => $order_id), '*');
            $items        = $archive_ordObj->getItemList($order_id);
        } else {
            $oOrders_item = $this->app->model('order_items');
            $order_object = $this->app->model('order_objects')->getList('*', array('order_id' => $order_id));
            if($_POST['dly_logi_no'] && $_POST['dly_logi_no'] != 'undefined') {
                $dly = app::get('ome')->model('delivery')->db_dump(['logi_no'=>$_POST['dly_logi_no']], 'delivery_id');
                $didetail = app::get('ome')->model('delivery_items_detail')->getList('order_item_id,number', ['delivery_id'=>$dly['delivery_id']]);
                $items = $oOrders_item->getList('*', array('order_id' => $order_id, 'item_id'=>array_column($didetail, 'order_item_id')), 0, -1, 'obj_id desc');
                $didNum = array_column($didetail, null, 'order_item_id');
                foreach ($items as $k => $v) {
                    $items[$k]['sendnum'] = $didNum[$v['item_id']]['number'];
                }
            } else {
                $items        = $oOrders_item->getList('*', array('order_id' => $order_id), 0, -1, 'obj_id desc');
            }
        }
        
        //obj_type
        $objTypeList = $orderItemMdl->_obj_alias;
        
        //获取订单obj层信息
        $orderItemList = array();
        $orderItemIds = array_column($items, 'item_id');
        if($orderItemIds){
            $orderLib = kernel::single('ome_order');
            $orderItemList = $orderLib->getOrderItemByItemIds($orderItemIds);
        }
        
        foreach ($order_object as $object) {
            $table = '<table><caption>捆绑信息</caption><thead><tr><th>销售物料编码</th><th>销售物料名称</th><th>价格</th><th>数量</th></tr></thead><tbody><tr>';
            $table .= '<td>' . $object['bn'] . '</td><td>' . $object['name'] . '</td><td>' . $object['price'] . '</td><td>' . $object['quantity'] . '</td>';
            $table .= '</tr></tbody></table>';
            $object['ref']              = $table;
            $oObject[$object['obj_id']] = $object;
        }
        
        //reship
        $tmpsale   = $reshipObj->getSalepriceByorderId($order_id);
        
        //format
        $lucky_flag = false;
        $color = array('red', 'blue');
        $objColor = array();
        foreach ($items as $k => $v) {
            $str_spec_value = '';
            $spec_info      = unserialize($v['addon']);
            if (!empty($spec_info['product_attr'])) {
                foreach ($spec_info['product_attr'] as $_val) {
                    $str_spec_value .= $_val['value'] . '|';
                }
                if (!empty($str_spec_value)) {
                    $str_spec_value = substr_replace($str_spec_value, '', -1, 1);
                }
                $items[$k]['spec_value'] = $str_spec_value;
            }

            if (!$objColor[$v['obj_id']]) {
                $objColor[$v['obj_id']] = $c = array_shift($color);
                array_push($color, $c);
            }
            
            if ($source == 'archive') {
                $refund              = $archive_ordObj->Get_refund_count($order_id, $v['bn']);
                $items[$k]['branch'] = $archive_ordObj->getBranchCodeByBnAndOd($v['bn'], $order_id);
            } else {
                $refund              = $oReship_item->Get_refund_count($order_id, $v['bn'], '', $v['item_id']);
                $items[$k]['branch'] = $oReship_item->getBranchCodeByBnAndOd($v['bn'], $order_id);
            }
            $items[$k]['effective'] = $refund > $v['sendnum'] ? $v['sendnum'] : $refund;
            $items[$k]['obj_type']  = $oObject[$v['obj_id']]['obj_type'];
            if ($oObject[$v['obj_id']]['ref']) {
                $items[$k]['ref']   = $oObject[$v['obj_id']]['ref'];
                $items[$k]['color'] = $objColor[$v['obj_id']];
            }
            
            //货到付款订单
            if($is_cod){
                $items[$k]['sale_price'] = 0;
            }else{
                //基础物料销售金额
                if($items[$k]['item_type'] == 'lkb'){
                    //取订单明细上的实付金额(福袋销售物料关联多个福袋会有多个相同的基础物料)
                    $items[$k]['sale_price'] = $items[$k]['divide_order_fee'];
                }else{
                    $items[$k]['sale_price'] = $tmpsale[$oObject[$v['obj_id']]['bn']][$v['bn']] > 0 ? $tmpsale[$oObject[$v['obj_id']]['bn']][$v['bn']] : $items[$k]['sale_price'];
                }
            }
            
            $items[$k]['order_item_id'] = $v['item_id'];
            
            //销售物料类型名称
            $obj_type = $items[$k]['obj_type'];
            $items[$k]['obj_type_name'] = ($obj_type ? $objTypeList[$obj_type] : '');
            
            //关联的订单object层信息
            $order_item_id = $items[$k]['order_item_id'];
            if(isset($orderItemList[$order_item_id])){
                $orderItemInfo = $orderItemList[$order_item_id];
                
                //销售物料编码
                $items[$k]['sales_material_bn'] = $orderItemInfo['sales_material_bn'];
                
                //福袋组合编码
                $items[$k]['combine_bn'] = $orderItemInfo['combine_bn'];
                
                //福袋组合编码
                if($orderItemInfo['combine_bn']){
                    $lucky_flag = true;
                }
            }
            
            $newItems[]                 = $items[$k];
            
            $tmp_product[] = $items[$k]['product_id'];
        }

        $items = $newItems;
        if ($type == 'return') {
            $this->pagedata['total_return_filter'] = implode(',', $tmp_product);
        } else {
            $this->pagedata['total_change_filter'] = implode(',', $tmp_product);
        }

        $branch_mode = app::get('ome')->getConf('ome.branch.mode');

        $this->pagedata['branch_mode'] = $branch_mode;

        $this->pagedata['items'] = $items;
        $this->pagedata['lucky_flag'] = $lucky_flag;
    }

    //获取退入商品信息
    public function ajax_getProductinfo_one()
    {
        $html   = '';
        $source = trim($_GET['source']);
        $this->get_data($_POST['order_id'], 'return', $source);
        $html = $this->fetch('admin/return_product/rchange/rc_html_t.html');
        echo $html;exit;
    }

    //获取换出商品信息
    public function ajax_getProductinfo_two()
    {
        $html = '';
        $this->get_data($_POST['order_id'], 'change');
        $html = $this->fetch('admin/return_product/rchange/rc_html_c.html');
        echo $html;exit;
    }

    //返回支付/退款明细
    public function ajax_paydetail()
    {
        $html = '';
        $this->paydetail($_POST['order_id']);
        $html = $this->fetch('admin/return_product/rchange/paydetail.html');
        echo $html;exit;
    }

    //获取支付信息
    public function paydetail($order_id)
    {
        $Orefunds                   = $this->app->model('refunds');
        $Opayments                  = $this->app->model('payments');
        $refunds                    = $Orefunds->getList('t_ready,refund_bn,money,paymethod,refund_refer', array('order_id' => $order_id));
        $payments                   = $Opayments->getList('t_begin,payment_bn,money,paymethod,payment_refer', array('order_id' => $order_id));
        $this->pagedata['payments'] = $payments;
        $this->pagedata['refunds']  = $refunds;
    }

    //退换货单审核
    public function check($reship_id)
    {
        $obj_return_process       = app::get('ome')->model('return_process');
        $por_id                   = $obj_return_process->getList('por_id', array('reship_id' => $reship_id));
        $this->pagedata['por_id'] = $por_id[0]['por_id'];
        $this->pagedata['act']    = 'save_check';
        $this->common_html(__FUNCTION__, $reship_id);
    }

    //最终收货
    public function endcheck($reship_id)
    {
        $obj_return_process       = app::get('ome')->model('return_process');
        $por_id                   = $obj_return_process->getList('por_id', array('reship_id' => $reship_id));
        $this->pagedata['por_id'] = $por_id[0]['por_id'];
        $this->pagedata['act']    = 'save_endcheck';
        $this->common_html(__FUNCTION__, $reship_id);
    }

    /**
     * 保存审核信息
     * $reship_id 退换货单主键
     * $status 当前状态 对应ome_reship is_check
     * $is_anti 是否是反审
     **/
    public function save_check($reship_id, $status, $is_anti=false)
    {
        $reshipLib = kernel::single('ome_reship');
        
        $url = 'index.php?app=ome&ctl=admin_return_rchange&act=index';
        
        //验证数据
        if(empty($reship_id)){
            $this->splash('error', $url, '没有可操作的退换货单');
        }
        
        //执行审核
        kernel::database()->beginTransaction();

        $error_msg = '';
        $is_rollback = true; //遇到错误,是否回滚更新的数据(默认为:回滚)
        $is_anti = ($is_anti ? true : false);
        $params = array('reship_id'=>$reship_id, 'status'=>$status, 'is_anti'=>$is_anti);
        $confirm = $reshipLib->confirm_reship($params, $error_msg, $is_rollback);
        
        //check
        if(!$is_rollback){
            //不用回滚,直接报错
            kernel::database()->commit();
            if(!$confirm) {
                $this->splash('error', $url, $error_msg);
            }else{
                $this->splash('success', $url, '审核退换货单成功。');
            }
            
        }else{            
            if(!$confirm) {
                kernel::database()->rollBack();

                $this->splash('error', $url, $error_msg);
            }

            kernel::database()->commit();
            $this->splash('success', $url, '审核退换货单成功。');
        }
    }

    //最终收货保存
    public function save_endcheck($reship_id, $status, $is_anti = false)
    {
        $this->begin();

        if ($reship_id) {
            $Oreship      = $this->app->model('reship');
            $oReship_item = $this->app->model('reship_items');
            $reship       = $Oreship->dump(array('reship_id' => $reship_id), 'is_check,reship_bn,return_type,reason,need_sv');
            if ($reship['is_check'] == '7') {
                $this->end(false, '改单据已完成!');
            }
            $normal_reship_item = $oReship_item->getList('*', array('reship_id' => $reship_id, 'normal_num|than' => 0), 0, 1);
            $reship_item        = $oReship_item->getList('*', array('reship_id' => $reship_id, 'defective_num|than' => 0), 0, 1);
            if (count($normal_reship_item) == 0 && count($reship_item) == 0) {
                $this->end(false, '良品或不良品数量至少有一种不为0!');
            }
            if (count($reship_item) > 0) {
                $branch_id = $reship_item[0]['branch_id'];
                $damaged   = kernel::single('console_iostockdata')->getDamagedbranch($branch_id);
                if (!$damaged) {
                    $this->end(false, '由于有不良品入库，请设置主仓对应残仓');
                }
            }
            if ($Oreship->finish_aftersale($reship_id)) {
                $result = kernel::single('console_reship')->siso_iostockReship($reship_id);
                if (!$result) {

                    $this->end(false, '没有生成出入库明细!');
                }
            }
        }
        $this->end(true, '操作成功！');
    } // end func


    public function dispatchDailog()
    {
        @ini_set('memory_limit','256M');

        $_POST['is_check'] ='11';
        $Oreship    = $this->app->model('reship');
        $reshipList = $Oreship->getList('reship_id', $_POST, 0, 500);
        $_POST['reship_id'] = array_column($reshipList, 'reship_id');
        if ($_POST['isSelectedAll'] == '_ALL_') {
            unset($_POST['isSelectedAll']);
        }

        $pageData =[
            'billName' => '退换货单',
            'maxProcessNum' => 500,
            'queueNum' => 5,
            'close' => true,
            'request_url' => 'index.php?app=ome&ctl=admin_return_rchange&act=doBatchCheck'
        ];
        parent::selectToPageRequest($Oreship, $pageData);
    }


    /**
     * 批量确认收货操作
     * @return void
     */
    public function doBatchCheck(){
        $Oreship      = $this->app->model('reship');
        $oReship_item = $this->app->model('reship_items');
        $reship_ids   = explode(';', $_POST['ajaxParams']);

        $retArr = array(
            'total' => count($reship_ids),
            'succ' => 0,
            'fail' => 0,
            'fail_msg' => []
        );

        $dataList = $Oreship->getList('reship_id,reship_bn,is_check,return_type,reason,need_sv', array('reship_id' => $reship_ids));
        foreach ($dataList as $reship){
            if ($reship['is_check'] == '7') {
                $retArr['succ']++;
                $retArr['fail_msg'][] = ['msg'=>'当前状态为已完成'];
                continue;
            }

            $reship_id = $reship['reship_id'];
            $normal_reship_item = $oReship_item->getList('*', array('reship_id' => $reship_id, 'normal_num|than' => 0), 0, 1);
            $reship_item        = $oReship_item->getList('*', array('reship_id' => $reship_id, 'defective_num|than' => 0), 0, 1);
            if (count($normal_reship_item) == 0 && count($reship_item) == 0) {
                $retArr['fail']++;
                $retArr['fail_msg'][] = ['msg'=>'退货单（'.$reship['reship_bn'].'）出现异常：良品或不良品数量至少有一种不为0!'];
                continue;
            }
            if (count($reship_item) > 0) {
                $branch_id = $reship_item[0]['branch_id'];
                $damaged   = kernel::single('console_iostockdata')->getDamagedbranch($branch_id);
                if (!$damaged) {
                    $retArr['fail']++;
                    $retArr['fail_msg'][] = ['msg'=>'退货单（'.$reship['reship_bn'].'）出现异常：由于存在不良品入库，请设置主仓对应残仓'];
                    continue;
                }
            }

            $trans = kernel::database()->beginTransaction();

            if ($Oreship->finish_aftersale($reship_id)) {
                $result = kernel::single('console_reship')->siso_iostockReship($reship_id);
                if (!$result) {
                    kernel::database()->rollBack();
                    $retArr['fail']++;
                    $retArr['fail_msg'][] = ['msg'=>'退货单（'.$reship['reship_bn'].'）出现异常：生成出入库明细失败!'];
                } else {
                    kernel::database()->commit($trans);
                    $retArr['succ']++;
                }
            } else {
                $retArr['fail']++;
                $retArr['fail_msg'][] = ['msg'=>'退货单（'.$reship['reship_bn'].'）质检失败'];
                kernel::database()->rollBack();
            }
        }

        echo json_encode($retArr);exit;

        /*
        $this->begin();

        $Oreship      = $this->app->model('reship');
        $oReship_item = $this->app->model('reship_items');

        $reship_ids = $_POST['reship_id'];

        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false, '不能使用全选功能,每次最多选择500条!');
        }

        if(empty($reship_ids)){
            $this->end(false, '请选择需要操作的退货单!');
        }
        if(count($reship_ids) > 500){
            $this->end(false, '每次最多只能选择500条!');
        }

        $dataList = $Oreship->getList('reship_id,reship_bn,is_check,return_type,reason,need_sv', array('reship_id' => $reship_ids));
        foreach ($dataList as $reship){
            if ($reship['is_check'] == '7') {
                continue;
            }

            $reship_id = $reship['reship_id'];
            $normal_reship_item = $oReship_item->getList('*', array('reship_id' => $reship_id, 'normal_num|than' => 0), 0, 1);
            $reship_item        = $oReship_item->getList('*', array('reship_id' => $reship_id, 'defective_num|than' => 0), 0, 1);
            if (count($normal_reship_item) == 0 && count($reship_item) == 0) {
                $this->end(false, '退货单（'.$reship['reship_bn'].'）出现异常：良品或不良品数量至少有一种不为0!');
            }
            if (count($reship_item) > 0) {
                $branch_id = $reship_item[0]['branch_id'];
                $damaged   = kernel::single('console_iostockdata')->getDamagedbranch($branch_id);
                if (!$damaged) {
                    $this->end(false, '退货单（'.$reship['reship_bn'].'）出现异常：由于存在不良品入库，请设置主仓对应残仓');
                }
            }

            if ($Oreship->finish_aftersale($reship_id)) {
                $result = kernel::single('console_reship')->siso_iostockReship($reship_id);
                if (!$result) {
                    $this->end(false, '退货单（'.$reship['reship_bn'].'）出现异常：生成出入库明细失败!');
                }
            }
        }

        $this->end(true, '操作成功！');
        */
    }

    //退换货单编辑
    public function edit($reship_id)
    {
        $oProblem = app::get('ome')->model('return_product_problem');
        $branchLib = kernel::single('ome_branch');
        
        $return_type                    = array('return' => '退货', 'change' => '换货');
        $this->pagedata['order_filter'] = array('pay_status' => '1', 'ship_status' => '1');
        $Oreship                        = $this->app->model('reship');
        $oOrder_pmt                     = $this->app->model('order_pmt');
        $reship_data                    = $Oreship->getCheckinfo($reship_id, false);

        //格式化费用金额
        $reship_data['bcmoney']            = (empty($reship_data['bcmoney']) ? '0.00' : $reship_data['bcmoney']);
        $reship_data['bmoney']             = (empty($reship_data['bmoney']) ? '0.00' : $reship_data['bmoney']);
        $reship_data['had_refund']             = (empty($reship_data['had_refund']) ? '0.00' : $reship_data['had_refund']);
        $reship_data['cost_freight_money'] = (empty($reship_data['cost_freight_money']) ? '0.00' : $reship_data['cost_freight_money']);

        $this->paydetail($reship_data['order_id']);
        
        //WMS仓储类型
        $wms_type = $branchLib->getNodetypBybranchId($reship_data['branch_id']);
        
        $filter = array();
        if($wms_type == 'yjdf'){
            //[京东一件代发]售后申请原因类型
            $filter = array('wms_type'=>$wms_type);
            
            //[兼容]默认选择"商品与页面描述不符"
            if(empty($reship_data['problem_id'])){
                $problemInfo =$oProblem->dump(array('reason_id'=>'8', 'wms_type'=>$wms_type), 'problem_id');
                $reship_data['problem_id'] = $problemInfo['problem_id'];
            }
        }
        
        //售后服务类型(退货原因)
        $list = $oProblem->getList('problem_id,problem_name', array('disabled'=>'false'));
        $this->pagedata['problem_type']  = $list;
        
        $reship_data['return_type_name'] = $return_type[$reship_data['return_type']];

        //退回物流信息
        $return_id = $reship_data['return_id'];
        if ($return_id && empty($reship_data['return_logi_id'])) {
            $oDly_corp      = app::get('ome')->model('dly_corp');
            $reProductObj   = app::get('ome')->model('return_product');
            $product_detail = $reProductObj->dump(array('return_id' => $return_id), 'process_data,address_id');
            $process_data   = ($product_detail['process_data'] ? unserialize($product_detail['process_data']) : '');
            if ($process_data) {
                $corpinfo = $oDly_corp->dump(array('name' => $process_data['shipcompany']), 'corp_id');

                $reship_data['return_logi_name'] = $corpinfo['corp_id'];
                $reship_data['return_logi_no']   = !empty($reship_data['return_logi_no']) ? $reship_data['return_logi_no'] : $process_data['logino'];
            }
        }

        $this->pagedata['reship_data']         = $reship_data;
        $pmts                                  = $oOrder_pmt->getList('pmt_amount,pmt_describe', array('order_id' => $reship_data['order_id']));
        $this->pagedata['pmts']                = $pmts;
        $this->pagedata['total_return_filter'] = $reship_data['total_return_filter'];
        $this->pagedata['total_change_filter'] = $reship_data['total_change_filter'];

        # 计算差价
        $this->pagedata['diffmoney'] = $reship_data['diff_money'];

        # 订单的支付明细
        $oPayments                  = $this->app->model('payments');
        $this->pagedata['payments'] = $oPayments->getList('payment_id,payment_bn,t_begin,download_time,money,paymethod', array('order_id' => $reship_data['order_id']));

        # 订单的退款明细
        $oRefunds                  = $this->app->model('refunds');
        $this->pagedata['refunds'] = $oRefunds->getList('refund_bn,t_ready,download_time,money,paymethod', array('order_id' => $reship_data['order_id']));
        $oOrders                   = $this->app->model('orders');
        if (in_array($reship_data['archive'], array('1'))) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $oDelivery      = kernel::single('archive_interface_delivery');
            $order          = $archive_ordObj->getOrders(array('order_id' => $reship_data['order_id']), '*');
        } else {
            $oDelivery = $this->app->model('delivery');
            $order     = $oOrders->dump(array('order_id' => $reship_data['order_id']), '*');
        }
        $this->pagedata['order'] = $order;

        //退换货类型
        // if ($order['pay_status'] == '5') {
        //     unset($return_type['change']); //全额退款的订单不允许换货
        // }
        $this->pagedata['return_type'] = $return_type;

        //退货仓库设置
        $branchtype                   = app::get('wms')->getConf('wms.branchset.type');
        $this->pagedata['branchtype'] = $branchtype;

        $delivery = $oDelivery->getDeliveryByOrder('*', $reship_data['order_id']);
        //加入了订单门店仓履约售后 判断是否是门店仓 显示branch_list
        $branchLib = kernel::single('ome_branch');
        $store_id  = $branchLib->isStoreBranch($delivery[0]["branch_id"]);
        if ($store_id) {
//是门店仓
            $this->pagedata["branch_list"] = kernel::single('o2o_return')->get_aftersale_o2o_branch($delivery[0]["branch_id"]);
        } else {
//是电商仓
            $this->pagedata["branch_list"] = app::get('ome')->model('branch')->getlist('branch_id,name', array('disabled' => 'false', 'b_type' => 1));
        }

        $this->pagedata['source'] = $reship_data['source'];

        //[最终收货]禁止编辑数据
        $this->pagedata['not_edit'] = ($reship_data['return_type'] == 'change' && $reship_data['is_check'] == '11') ? true : false;
        //判断编辑时显示未选择明细
        if ($reship_data['is_check'] == '0' || $reship_data['is_check'] == '12') {
            $return_items    = $reship_data['return'];
            $return_bn_array = array();
            foreach ($return_items as $items) {
                $return_bn_array[] = $items['order_item_id'];
            }
            $order_item = $this->get_Orderdata($reship_data['order_id'], $reship_data['source']);

            foreach ($order_item as $ok => $ov) {
                if (in_array($ov['order_item_id'], $return_bn_array) || $ov['effective'] <= 0) {
                    unset($order_item[$ok]);
                }
            }

            $this->pagedata['order_item'] = $order_item;
            unset($order_item);
        }
        
        //获取退货地址列表
        $addressObj = app::get('ome')->model('return_address');
        $addressList = $addressObj->getList('*', array('shop_id'=>$reship_data['shop_id'], 'reship_id'=>0), 0, 100, 'cancel_def ASC');
        $this->pagedata['addressList'] = $addressList;
        $this->pagedata['select_address_id'] = intval($product_detail['address_id']); //已选择的平台货地址ID
        
        $this->display('admin/return_product/rchange/rchange.html');
    }

    //反审核
    public function anti_check($reship_id)
    {
        if (!$reship_id) {
            die("单据号传递错误！");
        }

        $Oreship                     = $this->app->model('reship');
        $reship                      = $Oreship->dump(array('reship_id' => $reship_id), 'reship_bn');
        $this->pagedata['reship_id'] = $reship_id;
        $this->pagedata['reship_bn'] = $reship['reship_bn'];
        $oOperation_log              = $this->app->model('operation_log');
        $memo                        = '进行反审核';
        $oOperation_log->write_log('reship@ome', $reship_id, $memo);
        $this->display('admin/return_product/rchange/anti_check.html');
    }

    //入库单
    public function ruku($reship_id)
    {
        $this->pagedata['act']       = 'save_ruku';
        $this->pagedata['ruku_html'] = true;
        $Oreship                     = $this->app->model('reship');
        $reships                     = $Oreship->dump(array('reship_id' => $reship_id), 'order_id');
        $this->paydetail($reships['order_id']);
        $this->common_html(__FUNCTION__, $reship_id);
    }

    /**
     * 保存入库单信息
     * status 状态：
     *       5: 拒绝 生成一张发货单 商品明细为退入商品中的商品信息
     *       6：补差价，生成一张未付款的支付单
     *       8: 完成
     **/
    public function save_ruku($reship_id, $status)
    {
        if (!$reship_id) {
            die('单据号传递错误!');
        }

        $this->begin();
        $data = kernel::single('base_component_request')->get_params(true);

        $Oreship = $this->app->model('reship');
        $Oreship->saveinfo($reship_id, $data, $status);
        $this->end(true, '操作成功！');
    }

    //保存验收退换货单状态
    public function save_accept_returned($reship_id, $status)
    {
        $this->begin();
        if ($reship_id) {
            $oOperation_log      = $this->app->model('operation_log');
            $Oreship             = $this->app->model('reship');
            $oProduct_pro        = $this->app->model('return_process');
            $oProduct_pro_detail = $oProduct_pro->product_detail($reship_id);
            $reship              = $Oreship->dump(array('reship_id' => $reship_id), 'is_check,return_id,reason');
            if ($reship['is_check'] == '3') {
                $this->end(false, '改单据已验收过!');
            }
            //增加售后收货前的扩展
            foreach (kernel::servicelist('ome.aftersale') as $o) {
                if (method_exists($o, 'pre_sv_charge')) {
                    $memo = '';
                    if (!$o->pre_sv_charge($_POST, $memo)) {
                        $this->end(false, app::get('base')->_($memo));
                    }
                }
            }
            $data['branch_name'] = $oProduct_pro_detail['branch_name'];
            $data['memo']        = $_POST['info']['memo'];
            $data['shipcompany'] = $_POST['info']['shipcompany'];
            $data['shiplogino']  = $_POST['info']['shiplogino'];
            $data['shipmoney']   = $_POST['info']['shipmoney'];
            $data['shipdaofu']   = $_POST['info']['daofu'] == 1 ? 1 : 0;
            $data['shiptime']    = time();
            if ($status == '4') {
                $addmemo     = ',拒绝收货';
                $refuse_memo = unserialize($reship['reason']);
                $refuse_memo .= '#收货原因#' . $_POST['info']['refuse_memo'];
                $prodata = array('reship_id' => $reship_id, 'reason' => serialize($refuse_memo));
                $oProduct_pro->cancel_process($prodata);
            } elseif ($status == '3') {
                $prodata = array('reship_id' => $reship_id, 'process_data' => serialize($data));
                $addmemo = ',收货成功';
                $oProduct_pro->save_return_process($prodata);
            }
            $filter = array(
                'is_check'         => $status,
                'return_logi_name' => $data['shipcompany'],
                'return_logi_no'   => $data['shiplogino'],
            );
            $Oreship->update($filter, array('reship_id' => $reship_id));
            if ($reship['return_id']) {
                $Oproduct = $this->app->model('return_product');
                $recieved = 'false';
                if ($status == '3') {
                    $recieved = 'true';
                }
                $Oproduct->update(array('process_data' => serialize($data), 'recieved' => $recieved), array('return_id' => $reship['return_id']));
            }
            $Oreship_items = $this->app->model('reship_items');
            $oBranch       = $this->app->model('branch');
            $reship_items  = $Oreship_items->getList('branch_id', array('reship_id' => $reship_id, 'return_type' => 'return'));
            $branch_name   = array();
            foreach ($reship_items as $k => $v) {
                $branch_name[] = $oBranch->Get_name($v['branch_id']);
            }
            $add_name       = array_unique($branch_name);
            $memo           = '仓库:' . implode(',', $add_name) . $addmemo;
            $oOperation_log = $this->app->model('operation_log');
            if ($reship['return_id']) {
                $oOperation_log->write_log('return@ome', $reship['return_id'], $memo);
            }
            $oOperation_log->write_log('reship@ome', $reship_id, $memo);

            /*
            if($oProduct_pro_detail['return_id']){
            //售后申请状态更新
            foreach(kernel::servicelist('service.aftersale') as $object=>$instance){
            if(method_exists($instance,'update_status')){
            $instance->update_status($oProduct_pro_detail['return_id']);
            }
            }
            }
             */

            //增加售后收货前的扩展
            foreach (kernel::servicelist('ome.aftersale') as $o) {
                if (method_exists($o, 'after_sv_charge')) {
                    $o->after_sv_charge($_POST);
                }
            }

        }

        $this->end(true, '操作成功！');

    }

    //收货拒绝理由
    public function refuse_reason($reship_id, $status, $type)
    {
        if ($type == 'returned') {
            $refuse_memo = '收货拒绝';
            $ctl         = 'admin_return_rchange';
            $act         = 'save_accept_returned';
        } else {
            $refuse_memo = '质检拒绝';
            $ctl         = 'admin_return_sv';
            $act         = 'tosave';
        }
        $this->pagedata['finder_id']   = $_GET['finder_id'];
        $this->pagedata['refuse_memo'] = $refuse_memo;
        $this->pagedata['from_type']   = $_GET['from_type'];
        $this->pagedata['ctl']         = $ctl;
        $this->pagedata['act']         = $act;
        $this->pagedata['reship_id']   = $reship_id;
        $this->pagedata['status']      = $status;
        $this->display('admin/return_product/rchange/refuse_reason.html');
    }

    //验收退换货单
    public function accept_returned($reship_id)
    {
        $this->pagedata['act'] = 'save_accept_returned';
        $this->common_html(__FUNCTION__, $reship_id);
    }

    //验收退换货和审核公共页面
    public function common_html($display_button = 'check', $reship_id)
    {
        if (!$reship_id) {
            die('单据号传递错误!');
        }

        $Oreship     = $this->app->model('reship');
        $reship_data = $Oreship->getCheckinfo($reship_id);
        if(empty($reship_data)){
            $error_msg = '退换货单号：'. $reship_data['reship_bn'] .'，退换货单不存在!';
            echo "<script>alert('". $error_msg ."');history.go(-1);</script>";
            exit;
        }
        
        if($reship_data['return_type'] == 'change' && empty($reship_data['changebranch_id'])){
            $error_msg = '换货单号：'. $reship_data['reship_bn'] .'，请先编辑选择换货仓库、换出商品!';
            echo "<script>alert('". $error_msg ."');history.go(-1);</script>";
            exit;
        }
        
        $dataList = array();
        
        // 判断是否加密
        $reship_data['is_encrypt'] = kernel::single('ome_security_router', $reship_data['shop_type'])->show_encrypt($reship_data, 'reship');

        $rchangeobj            = kernel::single('ome_return_rchange');
        $changelist            = $rchangeobj->getChangelist($reship_id, $reship_data['changebranch_id']);
        $reship_data['reason'] = unserialize($reship_data['reason']);
        $oProblem              = $this->app->model('return_product_problem');
        $list                  = $oProblem->dump(array('problem_id' => $reship_data['problem_id']), 'problem_name');
        # 支付单
        $this->pagedata['payments'] = $this->app->model('payments')->getList('*', array('order_id' => $reship_data['order_id']));
        # 退款单
        $this->pagedata['refunds']        = $this->app->model('refunds')->getList('*', array('order_id' => $reship_data['order_id']));
        $reship_data['problem_type']      = $list['problem_name'];
        $this->pagedata['changelist']     = $changelist;
        $this->pagedata['reship_data']    = $reship_data;
        $this->pagedata['display_button'] = $display_button;
        $this->pagedata['lucky_flag'] = $reship_data['lucky_flag'];
        
        //[兼容]京东一件代发,用户未签收需要拦截包裹
        $show_cancel = false;
        $branchLib = kernel::single('ome_branch');
        $wms_type = $branchLib->getNodetypBybranchId($reship_data['branch_id']);
        if($wms_type=='yjdf' && in_array($reship_data['is_check'], array('0', '1'))){
            $deliveryObj = app::get('ome')->model('delivery');
            $deliveryLib = kernel::single('console_delivery');
            $rePackageObj = app::get('ome')->model('reship_package');
            
            //退货包裹列表
            $tempList = $rePackageObj->getList('delivery_id', array('reship_id'=>$reship_id));
            if(empty($tempList)){
                die('没有查找到退货包裹');
            }
            
            $delivery_ids = array();
            foreach ($tempList as $key => $val)
            {
                $delivery_ids[] = $val['delivery_id'];
            }
            
            //获取发货单,查询包裹发货状态
            $deliveryInfo = $deliveryObj->dump(array('delivery_id'=>$delivery_ids, 'status'=>'succ'), 'delivery_id,delivery_bn,wms_channel_id');
            if($deliveryInfo){
                //wms_id
                $channel_id = $branchLib->getWmsIdById($reship_data['branch_id']);
                
                //branch_bn
                $branch_bn = $branchLib->getBranchBnById($reship_data['branch_id']);
                
                //获取京东包裹发货状态
                $shipping_status = $deliveryLib->getShippingStatus();
                
                //package
                $error_msg = '';
                $packageList = $deliveryLib->getDeliveryPackage($delivery_ids, $error_msg);
                if(empty($packageList)){
                    die('没有找到发货包裹.');
                }
                
            }
        }
        
        //有包裹未签收,需要拦截
        if($show_cancel){
            $this->pagedata['dataList'] = $dataList;
            $this->pagedata['show_cancel'] = $show_cancel;
            $this->pagedata['reship_id'] = $reship_id;
            $this->display('admin/delivery/package_status.html');
        }else{
            $this->display('admin/return_product/rchange/check.html');
        }
    }

    //获取展示销售物料信息
    public function getProducts(){
        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $ome_orders = app::get('ome')->model('orders');
        
        $libBranchProduct = kernel::single('ome_branch_product');
        $lib_o2o_return = kernel::single('o2o_return');
        $salesMLib = kernel::single('material_sales_material');
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        //post
        $pro_id = $_POST['product_id'];
        $type = $_GET['type'];
        if (is_array($pro_id)){
            $filter['sm_id'] = $pro_id;
        }
        $filter['is_bind'] = 1;

        //选择换货的仓库
        $branch_id = intval($_GET['changebranch_id']);
        //判断是否是门店仓
        $store_id = kernel::single('ome_branch')->isStoreBranch($branch_id);

        $rows = array();
        $dataList = $salesMaterialObj->getList('sm_id, sales_material_name, sales_material_bn, sales_material_type', $filter);
        if($dataList){
            foreach ($dataList as $key => $item){
                $item['num']            = 1;
                $item['product_id']     = $item['sm_id'];
                $item['bn']             = $item['sales_material_bn'];
                $item['name']           = $item['sales_material_name'];
                #销售物料类型
                switch($item['sales_material_type']){
                    case "2":
                        $item['item_type'] = 'pkg';
                        $item['item_type_name'] = '组合'; break;
                    case "3":
                        $item['item_type'] = 'gift';
                        $item['item_type_name'] = '赠品'; break;
                    case "7":
                        $item['item_type'] = 'lkb';
                        $item['item_type_name'] = '福袋'; break;
                    case "5":
                        $item['item_type'] = 'pko';
                        $item['item_type_name'] = '多选一'; break;
                    default:
                        $item['item_type'] = 'product';
                        $item['item_type_name'] = '普通'; break;
                        break;
                }
                $item['type']    = $type;
                #销售物料价格
                $salesMaterialRow    = $salesMaterialExtObj->dump(array('sm_id'=>$item['sm_id']), 'cost, retail_price');
                $item['price']       = ($salesMaterialRow['retail_price'] ? $salesMaterialRow['retail_price'] : 0);

                //关联的基础物料信息
                $temp_sale_store   = array();
                if($item['sales_material_type'] == 7){
                    $orderinfo = $ome_orders->dump(array("order_id"=>$_GET["order_id"]), 'shop_id');
                    $basicMInfos = [];
                    
                    //福袋组合
                    $luckybagParams = [];
                    $luckybagParams['sm_id'] = $item['sm_id'];
                    $luckybagParams['sale_material_nums'] = $item['num']; //换出数量,默认为1
                    $luckybagParams['shop_bn'] = $orderinfo['shop_bn']; //用于获取店铺供货仓可用库存
                    
                    $fdResult = $fudaiLib->process($luckybagParams);
                    if($fdResult['rsp'] == 'succ'){
                        $basicMInfos = $fdResult['data'];
                    }else{
                        //标记福袋分配错误信息
                        $luckybag_error = $fdResult['error_msg'];
                    }
                    
                    if($basicMInfos){
                        foreach($basicMInfos as $basicMInfo)
                        {
                            //福袋组合ID
                            $luckybag_id = ($basicMInfo['combine_id'] ? $basicMInfo['combine_id'] : 0);
                            
                            //获取单仓库-多个基础物料的可用库存
                            $tmp_store = $libBranchProduct->getAvailableStore($branch_id, array($basicMInfo['bm_id']));
                            if(!isset($tmp_store[$basicMInfo['bm_id']])){
                                $bm_store = 0;
                            }else{
                                $bm_store = $tmp_store[$basicMInfo['bm_id']];
                            }
                            
                            //items
                            $item['items'][] = array(
                                "sm_id" => $item['sm_id'],
                                "bm_id" => $basicMInfo['bm_id'],
                                'number' => $basicMInfo['number'],
                                'material_name' => $basicMInfo['material_name'],
                                'material_bn' => $basicMInfo['material_bn'],
                                'type' => $basicMInfo['type'],
                                'type_name' => '',
                                'store' => $bm_store,
                                'change_num' => $basicMInfo['number'],
                                'luckybag_id' => $luckybag_id, //福袋组合ID
                            );
                            
                            //可用库存数量
                            $temp_sale_store[] = floor($bm_store / $item['num']);
                        }
                    }
                    
                }elseif($item['sales_material_type'] == 5){ //多选一
                    //根据order_id拿对应的shop_id
                    $ome_orders = app::get('ome')->model('orders');
                    $rs_order_info = $ome_orders->dump(array("order_id"=>$_GET["order_id"]),"shop_id");
                    $basicMInfos = $salesMLib->get_order_pickone_bminfo($item['sm_id'],"1",$rs_order_info["shop_id"]);
                    foreach($basicMInfos as $var_rl){
                        $tmp_store = $libBranchProduct->getAvailableStore($branch_id, array($var_rl["bm_id"]));
                        $item['items'][] = array(
                                "sm_id" => $item['sm_id'],
                                "bm_id" => $var_rl["bm_id"],
                                'number' => $var_rl['number'], //默认加载的数量
                                'material_name' => $var_rl["material_name"],
                                'material_bn' => $var_rl["material_bn"],
                                'type' => $var_rl["type"],
                                'type_name' => '',
                                'store' => ($tmp_store[$var_rl["bm_id"]] ? $tmp_store[$var_rl["bm_id"]] : 0),
                                'change_num' => $var_rl['number'], //默认加载的数量
                        );
                    }
                }else{
                    $promoItems = $salesBasicMaterialObj->getList('*',array('sm_id'=>$item['sm_id']),0,-1);
                    if($promoItems){
                        foreach($promoItems as $pk => &$promoItem){
                            $product_id    = $promoItem['bm_id'];
                            $tmp_item      = $basicMaterialObj->getList('bm_id, material_name, material_bn, type',array('bm_id'=>$product_id),0,1);
                            $tmp_item[0]['sm_id']    = $item['sm_id'];#销售物料sm_id_改变申请数量JS使用
                            #基础物料属性
                            $tmp_item[0]['type_name']    = '';
                            
                            #基础物料库存
                            if($store_id){//门店仓
                                $arr_stock = $lib_o2o_return->o2o_store_stock($branch_id,$product_id);
                                $tmp_item[0]['store'] = $arr_stock["store"]; //值可能会包括 "-" "x" 或 真实的库存数
                            }else{//电商仓
                                $tmp_store = $libBranchProduct->getAvailableStore($branch_id, array($product_id));
                                $tmp_item[0]['store'] = ($tmp_store[$product_id] ? $tmp_store[$product_id] : 0);
                            }
                            
                            #申请换货的数量
                            $tmp_item[0]['change_num'] = $promoItem['number'];
                            $promoItem = array_merge($promoItem , $tmp_item[0]);
                            
                            //根据销售物料绑定的基础物料计算可用库存
                            if($tmp_item[0]['store'] > 0)
                            {
                                $temp_sale_store[]    = floor($tmp_item[0]['store']/$promoItem['number']);
                            }
                            else
                            {
                                $temp_sale_store[]    = $tmp_item[0]['store'];
                            }
                        }
                        $item['items'] = $promoItems;
                    }
                }
                //[根据选择的换货仓库]获取基础物料库存
                if($item['sales_material_type'] == 5){ //多选一 object层库存是item的基础物料库存叠加
                    $item['sale_store'] = $salesMLib->get_pickone_branch_store($item['sm_id'],$branch_id);
                }else{
                    $item['sale_store'] = min($temp_sale_store);
                }
                $rows[]    = $item;
            }
        }
        
        echo "window.autocompleter_json=".json_encode($rows);exit;
    }
    //获取展示销售物料信息
    public function getBranchProducts()
    {
        $salesMaterialExtObj   = app::get('material')->model('sales_material_ext');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialObj      = app::get('material')->model('basic_material');
        $libBranchProduct      = kernel::single('ome_branch_product');
        $lib_o2o_return        = kernel::single('o2o_return');
        $salesMLib             = kernel::single('material_sales_material');
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        $pro_id                = $_POST['product_id'];
        $type                  = $_GET['type']; //change
        $branchlib = kernel::single('ome_branch');
        if (is_array($pro_id)) {
            foreach ($pro_id as $value) {
                if (strpos($value,'_')!==false) {
                    $filter['sm_id'][] = substr($value, 0,strpos($value, '_'));
                    $filter['branch_id'][] = substr($value, strpos($value, '_')+1);
                    $salesBranch[substr($value, 0,strpos($value, '_'))][] = substr($value, strpos($value, '_')+1);
                }
            }
        }
        
        //order
        $orderInfo = [];
        $shopInfo = [];
        if($_GET['order_id']){
            $orderMdl = app::get('ome')->model('orders');
            $orderInfo = $orderMdl->dump(array('order_id'=>$_GET['order_id']), 'order_id,shop_id');
            if($orderInfo){
                $shopInfo = app::get('ome')->model('shop')->db_dump(array('shop_id'=>$orderInfo['shop_id']), 'shop_id,shop_bn,node_id,node_type');
            }
        }
        
        $filter['is_bind'] = 1;
        $rows     = array();
        $dataList = $salesMLib->getBranchSalesList($filter);
        $smIds = array_unique(array_column($dataList,'sm_id'));
        $salesPrice = array_column($salesMaterialExtObj->getList('sm_id, cost, retail_price', array('sm_id' => $smIds)),null,'sm_id');
        if ($dataList) {
            foreach ($dataList as $key => $item) {
                $item['num']        = 1;
                $item['product_id'] = $item['sm_id'];
                $item['bn']         = $item['sales_material_bn'];
                $item['name']       = $item['sales_material_name'];
                #销售物料类型
                switch ($item['sales_material_type']) {
                    case "2":
                        $item['item_type']      = 'pkg';
                        $item['item_type_name'] = '组合';
                        break;
                    case "3":
                        $item['item_type']      = 'gift';
                        $item['item_type_name'] = '赠品';
                        break;
                    case "7":
                        $item['item_type']      = 'lkb';
                        $item['item_type_name'] = '福袋';
                        break;
                    case "5":
                        $item['item_type']      = 'pko';
                        $item['item_type_name'] = '多选一';
                        break;
                    default:
                        $item['item_type']      = 'product';
                        $item['item_type_name'] = '普通';
                        break;
                }
                $item['type'] = $type;
                #销售物料价格
                $item['price']    = ($salesPrice[$item['sm_id']]['retail_price'] ? $salesPrice[$item['sm_id']]['retail_price'] : 0);
                //判断是否是门店仓
                $store_id = kernel::single('ome_branch')->isStoreBranch($item['branch_id']);
                #关联的基础物料信息
                $temp_sale_store = array();
                if ($item['sales_material_type'] == 7) {
                    $basicMInfos = [];
                    
                    //福袋组合
                    $luckybagParams = [];
                    $luckybagParams['sm_id'] = $item['sm_id'];
                    $luckybagParams['sale_material_nums'] = 1; //换出数量,默认为1
                    $luckybagParams['shop_bn'] = $shopInfo['shop_bn'];
                    
                    $fdResult = $fudaiLib->process($luckybagParams);
                    if($fdResult['rsp'] == 'succ'){
                        $basicMInfos = $fdResult['data'];
                    }else{
                        //标记福袋分配错误信息
                        $luckybag_error = $fdResult['error_msg'];
                    }
                    
                    //items
                    foreach($basicMInfos as $var_bm)
                    {
                        //福袋组合ID
                        $luckybag_id = ($basicMInfo['combine_id'] ? $basicMInfo['combine_id'] : 0);
                        
                        $tmp_item_store = 0;
                        if(isset($var_bm['reality_stock'])){
                            $tmp_item_store = $var_bm['reality_stock'];
                        }
                        
                        $tmp_item = array(
                            'sm_id' => $item['sm_id'],
                            'bm_id' => $var_bm['bm_id'],
                            'material_name' => $var_bm['material_name'],
                            'material_bn' => $var_bm['material_bn'],
                            'type' => 1, //物料属性:1(成品),2(半成品),3(普通),4(礼盒),5(虚拟)
                            'type_name' => '',
                            'number' => $var_bm['number'],
                            'change_num' => $var_bm['number'],
                            'store' => $tmp_item_store,
                            'luckybag_id' => $luckybag_id, //福袋组合ID
                        );
                    }
                    
                } elseif ($item['sales_material_type'] == 5) {
                    //多选一
                    //根据order_id拿对应的shop_id
                    $ome_orders    = app::get('ome')->model('orders');
                    $rs_order_info = $ome_orders->dump(array("order_id" => $_GET["order_id"]), "shop_id");
                    $basicMInfos   = $salesMLib->get_order_pickone_bminfo($item['sm_id'], "1", $rs_order_info["shop_id"]);
                    foreach ($basicMInfos as $var_rl) {
                        $tmp_store       = $libBranchProduct->getAvailableStore($item['branch_id'], array($var_rl["bm_id"]));
                        $item['items'][] = array(
                            "sm_id"         => $item['sm_id'],
                            "bm_id"         => $var_rl["bm_id"],
                            'number'        => $var_rl['number'], //默认加载的数量
                            'material_name' => $var_rl["material_name"],
                            'material_bn'   => $var_rl["material_bn"],
                            'type'          => $var_rl["type"],
                            'type_name'     => '',
                            'store'         => ($tmp_store[$var_rl["bm_id"]] ? $tmp_store[$var_rl["bm_id"]] : 0),
                            'change_num'    => $var_rl['number'], //默认加载的数量
                        );
                    }
                } else {
                    $promoItems = $salesBasicMaterialObj->getList('*', array('sm_id' => $item['sm_id']), 0, -1);
                    if ($promoItems) {
                        foreach ($promoItems as $pk => &$promoItem) {
                            $product_id           = $promoItem['bm_id'];
                            $tmp_item             = $basicMaterialObj->getList('bm_id, material_name, material_bn, type', array('bm_id' => $product_id), 0, 1);
                            $tmp_item[0]['sm_id'] = $item['sm_id']; #销售物料sm_id_改变申请数量JS使用
                            #基础物料属性
                            $tmp_item[0]['type_name'] = '';
                            $branch_detail = $branchlib->getBranchInfo($item['branch_id'],'name');
                            #基础物料库存
                            if ($store_id) {
                                //门店仓
                                $arr_stock            = $lib_o2o_return->o2o_store_stock($item['branch_id'], $product_id);
                                $tmp_item[0]['store'] = $arr_stock["store"]; //值可能会包括 "-" "x" 或 真实的库存数
                                $tmp_item[0]['branch_name'] = $arr_stock['branch_name'];
                            } else {
                                //电商仓
                                $tmp_store            = $libBranchProduct->getAvailableStore($item['branch_id'], array($product_id));
                                $tmp_item[0]['store'] = ($tmp_store[$product_id] ? $tmp_store[$product_id] : 0);

                                $tmp_item[0]['branch_name'] = $branch_detail['name'];
                                $tmp_item[0]['branch_id'] = $item['branch_id'];
                            }

                            #申请换货的数量
                            $tmp_item[0]['change_num'] = $promoItem['number'];
                            $promoItem                 = array_merge($promoItem, $tmp_item[0]);

                            //根据销售物料绑定的基础物料计算可用库存
                            if ($tmp_item[0]['store'] > 0) {
                                $temp_sale_store[] = floor($tmp_item[0]['store'] / $promoItem['number']);
                                $branch_name[floor($tmp_item[0]['store'] / $promoItem['number'])] = $tmp_item[0]['branch_name'];
                            } else {
                                $temp_sale_store[] = $tmp_item[0]['store'];
                                $branch_name[$tmp_item[0]['store']] = $tmp_item[0]['branch_name'];
                            }
                        }
                        $item['items'] = $promoItems;
                    }
                }
                //[根据选择的换货仓库]获取基础物料库存
                if ($item['sales_material_type'] == 5) {
                    //多选一 object层库存是item的基础物料库存叠加
                    $item['sale_store'] = $salesMLib->get_pickone_branch_store($item['sm_id'], $item['branch_id']);
                } else {
                    $item['sale_store'] = min($temp_sale_store);
                }

                $item['branch_name'] = $branch_name[$item['sale_store']];
                $rows[] = $item;
            }
        }
        echo "window.autocompleter_json=" . json_encode($rows);exit;
    }
    
    /**
     * 构造一个商品列表
     *
     * @return void
     * @author
     **/
    function getGoods($product_id){
        #搜索条件
        $base_filter['is_bind'] = 1;#已绑定基础物料
        if($product_id){
            $base_filter['sm_id|notin']    = explode(',', $product_id);
        }

        //销售物料所属店铺
        $order_id    = trim($_GET['order_id']);
        if($order_id){
            $orderObj    = app::get('ome')->model('orders');
            $orderRow    = $orderObj->dump(array('order_id'=>$order_id), 'shop_id');
            if($orderRow){
                $base_filter['shop_id']    = array($orderRow['shop_id'], '_ALL_');
            }
        }

        /**
         * 普通、组合、赠品
        if($_GET['type'] == 1)
        {
            $base_filter['sales_material_type'] = 1;#只显示普通销售物料
        }
        **/

        $params = array(
                'title'=>'销售物料列表',
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_filter'=>true,
                'base_filter' => $base_filter,
        );
        $this->finder('material_mdl_sales_material', $params);
    }

    //取消退换货单
    public function do_cancel($reship_id)
    {
        if (!$reship_id) {
            die("单据号传递错误！");
        }
        
        $Oreship = app::get('ome')->model('reship');
        $shopObj = app::get('ome')->model('shop');
        $oProduct = app::get('ome')->model('return_product');
        $oOperation_log = app::get('ome')->model('operation_log');
        
        $reshipLib = kernel::single('ome_reship');
        $branchLib = kernel::single('ome_branch');
        
        //退货信息
        $reship = $Oreship->dump(array('reship_id'=>$reship_id), '*');
        
        //店铺信息
        $shopInfo = $shopObj->dump(array('shop_id'=>$reship['shop_id']), 'shop_type');
        $reship['shop_type'] = ($shopInfo['shop_type'] ? $shopInfo['shop_type'] : $reship['shop_type']);
        $this->pagedata['shop_type'] = $reship['shop_type'];
        if($reship['shop_type'] == 'luban'){
            $returnModel = app::get('ome')->model('return_product');
            $returninfo = $returnModel->dump(array('return_id'=>$reship['return_id'],'source'=>'matrix'),'return_bn,return_id,shop_id');
            
            $refuseReasons = kernel::single('erpapi_router_request')->set('shop', $returninfo['shop_id'])->aftersale_getRefuseReason($returninfo);
            $refuseReasons = ($refuseReasons['rsp']=='fail' ? array() : $refuseReasons);
            
            $this->pagedata['refuse_reason'] = $refuseReasons;
        }elseif(in_array($reship['shop_type'],array('meituan4medicine'))){
            $this->pagedata['refuse_reason'] = kernel::single('ome_aftersale_request_'.$reship['shop_type'])->getAftersaleReason('return_reship');
        }
        
        //[京东一件代发]抖音平台退货
        $file_url = '';
        $wms_type = $branchLib->getNodetypBybranchId($reship['branch_id']);
        if($wms_type=='yjdf'){
            $is_flag = false;
            
            //check
            if(!in_array($reship['is_check'], array('0','1','2'))){
                die('退换货单状态不允许取消');
            }
            
            //获取退货包裹列表(京东售后申请单号)
            $error_msg = '';
            $tempList = $reshipLib->get_reship_package($reship_id, $error_msg);
            $rePackageList = array();
            if($tempList){
                foreach ($tempList as $key => $val)
                {
                    //已取消
                    if($val['status'] == 'cancel'){
                        continue;
                    }
                    
                    //京东售后申请单号
                    if($val['wms_order_code']){
                        $is_flag = true;
                    }
                    
                    $rePackageList[] = $val;
                }
            }
            
            //京东售后申请单号,需要先取消退货申请
            if($is_flag){
                //获取售后京东服务单号
                $processObj = app::get('ome')->model('return_process');
                $processList = $processObj->getList('*', array('reship_id'=>$reship_id));
                foreach ((array)$processList as $key => $val)
                {
                    if($val['logi_code'] && $val['logi_no']){
                        die('退换货单已经同步给到：京东云交易退货物流单号，不允许取消退货申请!');
                    }
                }
                
                $this->pagedata['processList'] = $processList;
                $this->pagedata['rePackageList'] = $rePackageList;
                $this->pagedata['reship_id'] = $reship['reship_id'];
                $this->pagedata['reship_bn'] = $reship['reship_bn'];
                
                $this->display('admin/return_product/rchange/do_cancel_package.html');
                exit;
            }
        }
        
        //post
        if($_POST){
            //提交
            $reship_id = $_POST['reship_id'];
            
            //判断是否是已确认拒绝如果是需要释放冻结库存
            kernel::single('console_reship')->releaseChangeFreeze($reship_id);

            $memo = $_POST['cancel_type'] == 'part' ? '部分取消,状态:待确认' : '状态:拒绝';
            $memo .= $_POST['cancel_flag'] == '1' ? ',强制取消' : '';
            
            //处理售后申请单
            if ($reship['return_id']) {
                $data = array('return_id' => $reship['return_id'], 'status' => '5', 'last_modified' => time());
                $oProduct->update_status($data);
                
                //log
                $oOperation_log->write_log('return@ome', $reship['return_id'], $memo);
            }
            
            //[京东一件代发]取消退货包裹
            if($wms_type=='yjdf'){
                $result = $reshipLib->cancel_reship_package($reship, $error_msg);
                
                //拉取京东售后审核意见,并同步抖音售后单备注内容
                $wms_id = $branchLib->getWmsIdById($reship['branch_id']);
                $data = array(
                        'reship_id' => $reship['reship_id'],
                        'reship_bn' => $reship['reship_bn'],
                        'order_id' => $reship['order_id'],
                );
                $result = kernel::single('erpapi_router_request')->set('wms', $wms_id)->reship_search($data);
            }
            
            //更新退换货单状态
            if ($_POST['cancel_type'] == 'part') {
                // 部分取消 状态改为待确认
                $Oreship->update( array('is_check' => '11'), array('reship_id' => $reship_id) );
            } else {
                //拒绝
                $Oreship->update(array('is_check'=>'5','t_end'=>time()),array('reship_id'=>$reship_id));

                // 退货单取消成功通知
                $reshipInfo = $Oreship->dump($reship_id, 'reship_bn,branch_id');
                if ($reshipInfo) {
                    kernel::single('ome_reship')->sendReshipCancelSuccessNotify($reshipInfo, $memo);
                }

                // 退货单取消后的service扩展点
                foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
                    if(method_exists($object, 'cancel_reship_after')) {
                        $object->cancel_reship_after($reship_id);
                    }
                }
            }
            
            //log
            $oOperation_log->write_log('reship@ome', $reship_id, $memo);
        }
        
        $this->pagedata['reship_bn'] = $reship['reship_bn'];
        $this->pagedata['reship_id'] = $reship_id;
        $this->pagedata['reship'] = $reship;
        $this->display('admin/return_product/rchange/do_cancel.html');
    }

    //拒绝退货寄回运费
    public function rejectFreight($return_id)
    {
        if (!$return_id) {
            die("单据号传递错误！");
        }
        $rfRow = app::get('ome')->model('return_freight')->db_dump(['return_id'=>$return_id]);
        $this->pagedata['data'] = $rfRow;
        $this->display('admin/return_product/rchange/reject_freight.html');
    }

    public function dealRejectFreight() {
        $this->begin($this->url);
        $return_id = $_POST['return_id'];
        if (!$return_id) {
            $this->end(false,"单据号传递错误！");
        }
        $updateData = ['handling_advice'=>'2'];
        if(empty($_POST['reject_desc'])) {
            $this->end(false, '拒绝原因必填');
        }
        $updateData['reject_desc'] = $_POST['reject_desc'];
        if ($_FILES['reject_images']['size']<=0) {
            $this->end(false, '请上传凭证图片!');
        }
        if ($_FILES ['reject_images'] ['size'] > 512000) {
            $this->end(false, '上传文件不能超过500K!');
        }
        $type = array ('gif','jpg','png');
        $pathinfo = pathinfo($_FILES ['reject_images'] ['name']);
        if (!in_array($pathinfo['extension'], $type)) {
            $this->end(false, "您只能上传以下类型文件".implode('、', $type)."!");
        }
        
        $storager = kernel::single ( 'base_storager' );
        $id = $storager->save_upload ( $_FILES ['reject_images'], "file", "", $msg ); //返回file_id;
        if(!$id) {
            $this->end(false, '上传失败');
        }
        $updateData['reject_images'] = $storager->getUrl ( $id, "file" );
        $rs = app::get('ome')->model('return_freight')->update($updateData, ['return_id'=>$return_id]);
        if(!$rs) {
            $this->end(false, '更新失败');
        }
        $this->end(true, '更新成功');
    }

    //打回发货单方法
    public function do_back($reship_id)
    {
        if (!$reship_id) {
            die("单据号传递错误！");
        }

        $Oreship          = $this->app->model('reship');
        $Oreturn_products = $this->app->model('return_product');
        if ($_POST) {
            $reship_id       = $_POST['reship_id'];
            $memo            = '';
            $delivery_num    = 0; //发货单数量
            $oReship         = $this->app->model('reship');
            $oDelivery       = $this->app->model('delivery');
            $oDelivery_order = $this->app->model('delivery_order');
            $oDelivery_items = $this->app->model('delivery_items');
            $oOrder_items    = $this->app->model('order_items');
            $reshipinfo      = $oReship->dump(array('reship_id' => $reship_id));
            $order_items     = $oOrder_items->getList('*', array('order_id' => $reshipinfo['order_id']));
            $order_id        = $reshipinfo['order_id'];
            $delivery_order  = $oDelivery_order->dump(array('order_id' => $order_id), 'delivery_id');
            $delivery_id     = $delivery_order['delivery_id'];
            $is_archive      = kernel::single('archive_order')->is_archive($reshipinfo['source']);
            if ($is_archive) {
                $archive_delObj = kernel::single('archive_interface_delivery');
                $deliveryinfo   = $archive_delObj->getDelivery(array('delivery_id' => $delivery_id), 'branch_id,delivery_id,logi_id,delivery_cost_actual');
            } else {
                $deliveryinfo = $oDelivery->dump($delivery_id, 'branch_id,delivery_id,logi_id,delivery_cost_actual');
            }
            $delivery_items = $this->app->model('reship_items')->getList('product_id,bn,product_name,num as number, obj_id', array('reship_id' => $reship_id, 'return_type' => 'return'));
            $objIdArr = array_column($delivery_items, 'obj_id');
            $delivery_objs = $this->app->model('reship_objects')->getList('obj_id,product_id', ['obj_id'=>$objIdArr]);
            $delivery_objs = array_column($delivery_objs, null, 'obj_id');
            foreach ($delivery_items as $dik => $div) {
                $delivery_items[$dik]['goods_id'] = $delivery_objs[$div['obj_id']]['product_id'];
            }

            $Process_data                   = array_merge($deliveryinfo, $reshipinfo);
            $Process_data['logi_id']        = $reshipinfo['logi_id'] != '' ? $reshipinfo['logi_id'] : $deliveryinfo['logi_id'];
            $Process_data['logi_name']      = $reshipinfo['logi_name'] != '' ? $reshipinfo['logi_name'] : $deliveryinfo['logi_name'];
            $Process_data['delivery_items'] = $delivery_items;

            define('FRST_TRIGGER_OBJECT_TYPE', '发货单：售后申请原样寄回生成发货单');
            define('FRST_TRIGGER_ACTION_TYPE', 'ome_mdl_return_product：saveinfo');

            $new_delivery_bn = $oReship->create_delivery($Process_data);
            $delivery_memo   = '，发货单号为:' . $new_delivery_bn;
            $delivery_num    = 1;
            $oReship->update(array('is_check' => '5'), array('reship_id' => $reship_id));
            if ($reshipinfo['return_id']) {
                $Oreturn_products->update(array('status' => '5'), array('return_id' => $reshipinfo['return_id']));
            }

            if ($delivery_num != 0) {
                $memo .= '   生成了' . $delivery_num . '张发货单' . $delivery_memo;
            }

            //售后原样寄回埋点
            $new_delivery_info = $oDelivery->dump(array('delivery_bn' => $new_delivery_bn), 'delivery_id');
            //发货单通知单推送仓库
            ome_delivery_notice::create($new_delivery_info['delivery_id']);

            $oOperation_log = $this->app->model('operation_log'); //写日志
            if ($reshipinfo['return_id']) {
                $oOperation_log->write_log('return@ome', $reshipinfo['return_id'], $memo);
            }
            $oOperation_log->write_log('reship@ome', $reship_id, $memo);
            echo '生成退回发货单成功!';exit;
        }
        $reship                      = $Oreship->dump(array('reship_id' => $reship_id), 'reship_bn');
        $this->pagedata['reship_bn'] = $reship['reship_bn'];
        $this->pagedata['reship_id'] = $reship_id;
        $this->display('admin/return_product/rchange/do_back.html');

    }

    //根据仓库ID和货品ID 获取相应的库存数量
    public function ajax_showStore()
    {
        $libBranchProduct = kernel::single('ome_branch_product');
        $branch_id        = $_POST['branch_id'];
        $product_id       = $_POST['product_id'];
        $result           = array('res' => 'fail', 'msg' => 'product_id is empty');
        if ($product_id && $branch_id) {
            $store  = $libBranchProduct->get_product_store($branch_id, $product_id);
            $result = array('res' => 'succ', 'msg' => $store);
        }
        echo json_encode($result);exit;
    }

    //根据订单ID 获取相应的优惠方案信息
    public function ajax_getPmts()
    {
        $order_id = $_POST['order_id'];
        if ($order_id) {
            $oOrder_pmt             = $this->app->model('order_pmt');
            $pmts                   = $oOrder_pmt->getList('pmt_amount,pmt_describe', array('order_id' => $order_id));
            $this->pagedata['pmts'] = $pmts;
            $html                   = $this->fetch('admin/return_product/rchange/show_pmt.html');
            echo $html;exit;
        }
    }

    /**
     * 计算补差价金额
     * @author chenping<chenping@shopex.cn>
     **/
    public function calDiffAmount($reship_id, $return_type)
    {
        $archive_ordObj = kernel::single('archive_interface_orders');
        $post           = kernel::single('base_component_request')->get_post();
        $is_check       = $post['is_check'];

        $reshipObj       = app::get('ome')->model('reship');
        $reship_detail   = $reshipObj->dump(array('reship_id' => $reship_id), 'archive,shop_id');
        $post['archive'] = $reship_detail['archive'];
        # 进行数量判断
        if (isset($post['return']['goods_bn']) && is_array($post['return']['goods_bn'])) {
            foreach ($post['return']['goods_bn'] as $pbn) {
                // 获取基础物料号，如果不存在则使用order_item_id作为后备
                $material_bn = isset($post['return']['bn'][$pbn]) ? $post['return']['bn'][$pbn] : $pbn;
                
                if ($is_check == '11') {
                    if ($post['return']['normal_num'][$pbn] > $post['return']['effective'][$pbn]) {
                        $error = array(
                            'error' => "货品【{$material_bn}】的入库数量大于可退入数量!",
                        );
                        break;
                    }
                } else {
                    if ($post['return']['num'][$pbn] > $post['return']['effective'][$pbn]) {
                        $error = array(
                            'error' => "货品【{$material_bn}】的申请数量大于可退入数量!",
                        );
                        break;
                    }
                }

            }
            if ($error) {
                echo json_encode($error);exit;
            }
        }

        $post["shop_id"] = $reship_detail["shop_id"];
        //格式化换货数据
        $rchangeObj = kernel::single('ome_return_rchange');
        $post       = $rchangeObj->format_rchange_data($post);

        $money      = kernel::single('ome_return_rchange')->calDiffAmount($post);
        $moneyValue = $money;
        $curModel   = app::get('eccommon')->model('currency');
        foreach ($money as $key => &$value) {
            #货币格式化
            if ($key != 'tnums' && $key != 'change_nums') {
                $value = $curModel->changer($value);
            }
        }
        $money['mvalue'] = $moneyValue;
        if (isset($post['return']['goods_bn']) && is_array($post['return']['goods_bn'])) {
            foreach ($post['return']['goods_bn'] as $pbn) {
                $tmpAmount = floatval($post['return']['price'][$pbn]) * floatval($post['return']['num'][$pbn]);
                if($post['return']['amount'][$pbn] > 0 
                    && ($post['return']['amount'][$pbn] - $tmpAmount > 0.1 || $post['return']['amount'][$pbn] - $tmpAmount < -0.1)) {
                    $error = array('error' => $post['return']['bn'][$pbn]."：小计和数量单价乘积相差过大");
                    echo json_encode($error);exit;
                }
            }
        }
        # 判断退款金额是否大于订单金额
        if ($post['order_id']) {
            $source = $_GET['source'];
            if ($source && in_array($source, array('archive')) || $post['archive'] == '1') {
                $orderInfo = $archive_ordObj->getOrder_list(array('order_id' => $post['order_id']), 'total_amount,payed,pay_status');
            } else {
                $orderInfo = $this->app->model('orders')->getList('total_amount,payed,pay_status', array('order_id' => $post['order_id']), 0, 1);
            }

            if ($orderInfo[0]['pay_status'] == '5' && !$money['had_refund']) {
                //全额退款订单
                $money['tmoney']               = '￥0.00';
                $money['totalmoney']           = '￥0.00';
                $money['mvalue']['tmoney']     = 0.00;
                $money['mvalue']['totalmoney'] = 0.00;
                $moneyValue['totalmoney']      = 0.00;
                if ($post['bcmoney'] > $orderInfo[0]['payed']) {
                    $error = array('error' => "订单已经全额退款,补偿费用不能大于订单的已支付金额!");
                    echo json_encode($error);exit;
                }
            }

            if ($moneyValue['totalmoney'] - $post['bcmoney'] > $orderInfo[0]['payed']) {
                $error = array(
                    'error' => "退款金额不能大于订单的已支付金额!",
                );
                echo json_encode($error);exit;
            }
            if ($return_type == 'return' && $moneyValue['totalmoney'] < 0) {
                $error = array(
                    'error' => "退款金额不能小于零!",
                );
                echo json_encode($error);exit;
            }
            if ($is_check != '11' && $return_type == 'change' && $money['change_nums'] > $money['tnums']) {
                $error = array(
                    'error' => "换货总数量不可以大于退货申请数量!",
                );
                echo json_encode($error);exit;
            }
        }

        echo json_encode($money);exit;

    }
    
    /**
     * 更新退回物流公司、退回物流单号
     *
     * @param int $reship_id
     * @return array
     */
    public function update_reship($reship_id)
    {
        $this->begin();
        
        $reshipObj = app::get('ome')->model('reship');
        $corpObj = app::get('ome')->model('dly_corp');
        $oOperation_log = $this->app->model('operation_log');
        
        $reshipLib = kernel::single('ome_reship');
        $keplerLib = kernel::single('ome_reship_kepler');
        $branchLib = kernel::single('ome_branch');
        
        $finder_id = $_GET['finder_id'];
        
        if (!$reship_id) {
            $this->end(false, $this->app->_('退换货单不存在!'));
        }
        
        $post = kernel::single('base_component_request')->get_post();
        
        //退货单信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), '*');
        if(empty($reshipInfo)){
            $this->end(false, $this->app->_('没有找到退货单信息'));
        }
        
        //WMS仓储类型
        $service_bn_logi_no = '';
        $wms_type = $branchLib->getNodetypBybranchId($reshipInfo['branch_id']);
        if($wms_type == 'yjdf'){
            $return_logi_code = '';
            $return_logi_no = '';
            
            //获取售后服务单
            $error_msg = '';
            $serviceList = $keplerLib->get_reship_services($reship_id, false, $error_msg);
            if(empty($serviceList)){
                $this->end(false, $this->app->_('没有服务单,不能保存退回物流信息'));
            }
            
            //[京东一件代发]检查服务单是否填写物流单号
            foreach ($serviceList as $key => $val)
            {
                $service_bn = $val['service_bn'];
                
                $service_bn_logi_code = 'logi_code_'.$service_bn;
                $service_bn_logi_no = 'logi_no_'.$service_bn;
                
                if (empty($post[$service_bn_logi_code])) {
                    $this->end(false, $this->app->_('服务单号：'. $service_bn .',没有选择退回物流公司!'));
                }
                
                if (empty($post[$service_bn_logi_no])) {
                    $this->end(false, $this->app->_('服务单号：'. $service_bn .',没有填写退回物流单号!'));
                }
                
                //[兼容]从服务单号中取退回物流信息
                if(empty($post['return_logi_name'])) {
                    $return_logi_code = $post[$service_bn_logi_code];
                }
                if(empty($post['return_logi_no'])) {
                    $return_logi_no = $post[$service_bn_logi_no];
                }
            }
            
            if($return_logi_code)
            {
                $corpInfo = $corpObj->dump(array('type'=>$return_logi_code), 'corp_id,name');
                $post['return_logi_name'] = $corpInfo['name'];
                $post['return_logi_no'] = $return_logi_no;
            }
        }
        
        //check
        if (empty($post['return_logi_name'])) {
            $this->end(false, $this->app->_('物流公司不能为空!'));
        }
        if (empty($post['return_logi_no'])) {
            $this->end(false, $this->app->_('物流单号不能为空!'));
        }
        
        /***
        $isExit = $reshipObj->getList('reship_id', array('return_logi_no' => $post['return_logi_no'], 'reship_id|noequal' => $reship_id, 'is_check|noequal' => '5'));
        if ($isExit) {
            $this->end(false,$this->app->_('物流单号已经存在!'));
        }
        ***/
        
        $return_logi_no = trim($post['return_logi_no']);
        $return_logi_no = str_replace(array("'", '"'), '', $return_logi_no);
        
        $return_logi_name = trim($post['return_logi_name']);
        $return_logi_name = str_replace(array("'", '"'), '', $return_logi_name);
        
        $reshipUpdate = array(
            'return_logi_no'   => $return_logi_no,
            'return_logi_name' => $return_logi_name,
        );
        
        //check
        $sql = "SELECT corp_id,type FROM sdb_ome_dly_corp WHERE name='". $return_logi_name ."' OR type='". $return_logi_name ."'";
        $corpInfo = $reshipObj->db->selectrow($sql);
        
        //过滤抖音平台的物流公司名称
        if(empty($corpInfo)){
            $return_logi_name = str_replace('(常用)', '', $return_logi_name);
            
            $sql = "SELECT corp_id,type FROM sdb_ome_dly_corp WHERE name='". $return_logi_name ."' OR type='". $return_logi_name ."'";
            $corpInfo = $reshipObj->db->selectrow($sql);
        }
        
        if(empty($corpInfo)){
//            $this->end(false, $this->app->_('退回物流公司名称不存在,请在系统里添加物流公司。'));
        }
        
        //update
        $affect_row = $reshipObj->update($reshipUpdate, array('reship_id'=>$reship_id));
        if (!is_numeric($affect_row) || $affect_row <= 0) {
            
            if($service_bn_logi_no){
                //京东云交易退货物流单号
            }else{
                $this->end(false, $this->app->_('更新物流单号失败或者物流单号没有变化!'));
            }
        }

        // 物流单号重复检测打标（flag_type 位）
        kernel::single('ome_reship')->markDuplicateReturnLogiNo($reship_id, $return_logi_no);
        
        //[京东一件代发]同步退回物流单号给WMS仓储
        if($wms_type == 'yjdf'){
            $processObj = app::get('ome')->model('return_process');
            
            //更新服务单的物流信息
            foreach ($serviceList as $key => $val)
            {
                $service_bn = $val['service_bn'];
                
                $service_bn_logi_code = 'logi_code_'.$service_bn;
                $service_bn_logi_no = 'logi_no_'.$service_bn;
                
                $logi_code = $post[$service_bn_logi_code];
                $logi_no = $post[$service_bn_logi_no];
                
                $processObj->update(array('logi_code'=>$logi_code, 'logi_no'=>$logi_no), array('reship_id'=>$reship_id, 'service_bn'=>$service_bn));
            }
            
            //request
            $error_msg = '';
            $result = ome_return_notice::updateLogistics($reship_id, $error_msg);
            if(!$result){
                $this->end(false, $this->app->_($error_msg));
            }
        }

        // 商家代客填写退货单号回传
        if ($reshipInfo['source'] != 'local') {
            kernel::single('ome_service_aftersale')->aftersaleSubmitReturnInfo($reship_id);
        }
        
        //log
        $memo = '更改退回物流单号(' . $post['return_logi_no'] . '),退回物流公司(' . $post['return_logi_name'] . ')';
        $oOperation_log->write_log('reship@ome', $reship_id, $memo);
        foreach(kernel::servicelist('erpapi.service.aftersale.update.reship.logistics.after') as $object) {
            if(method_exists($object, 'after_update_logistics')){
                $object->after_update_logistics($reship_id);
            }
        }
        $this->end(true, $this->app->_('更新成功!'));
    }

    /**
     * @description 选择补差价订单
     * @param Int $order_id 原订单ID
     * @param Int $page 页码
     */
    public function selectDiffOrder($order_id, $page = 1)
    {
        if (empty($order_id)) {
            $result = array(
                'error' => $this->app->_('退货订单不存在!'),
            );
            echo '退货订单不存在!';exit;
        }

        $pagelimit = 20;

        $orderModel = $this->app->model('orders');
        $order      = $orderModel->getList('member_id,total_amount,shop_id', array('order_id' => $order_id), 0, 1);
        if (!$order) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $order          = $archive_ordObj->getOrder_list(array('order_id' => $order_id), 'member_id,total_amount,shop_id');
            if (!$order) {
                $result = array(
                    'error' => $this->app->_('退货订单信息不存在!'),
                );
                echo '退货订单信息不存在!';exit;
            }
        }

        # 查询该会员的所有订单
        $filter = array(
            'member_id'        => $order[0]['member_id'],
            //'total_amount|lthan' => $order[0][''],
            'pay_status'       => '1',
            'ship_status'      => '0',
            'status'           => 'active',
            'process_status'   => 'unconfirmed',
            'shop_id'          => $order[0]['shop_id'],
            'order_id|noequal' => $order_id,
        );
        $diffOrders = $orderModel->getList('order_id,order_bn,total_amount,cost_freight,cost_protect,tostr,shop_id,member_id,createtime,pay_status,ship_status', $filter, ($page - 1) * $pagelimit, $pagelimit, 'createtime desc');

        if ($diffOrders) {
            foreach ($diffOrders as $key => $diffOrder) {
                $diffOrders[$key]['uname']       = &$members[$diffOrder['member_id']];
                $diffOrders[$key]['shop_name']   = &$shops[$diffOrder['shop_id']];
                $diffOrders[$key]['pay_status']  = $orderModel->schema['columns']['pay_status']['type'][$diffOrder['pay_status']];
                $diffOrders[$key]['ship_status'] = $orderModel->schema['columns']['ship_status']['type'][$diffOrder['ship_status']];

                $member_ids[] = $diffOrder['member_id'];
                $shop_ids[]   = $diffOrder['shop_id'];
            }

            $memberModel = $this->app->model('members');
            $memberList  = $memberModel->getList('member_id,uname,name', array('member_id' => $member_ids));
            foreach ($memberList as $key => $member) {
                $members[$member['member_id']] = $member['uname'];
            }

            $shopModel = $this->app->model('shop');
            $shopList  = $shopModel->getList('shop_id,name', array('shop_id' => $shop_ids));
            foreach ($shopList as $key => $shop) {
                $shops[$shop['shop_id']] = $shop['name'];
            }

            $this->pagedata['diffOrders'] = $diffOrders;

            $count = $orderModel->count($filter);

            $totalpage = ceil($count / $pagelimit);
            $pager     = $this->ui()->pager(array(
                'current' => $page,
                'total'   => $totalpage,
                'link'    => 'javascript:gotopage(%d);',
            ));
            $this->pagedata['pager'] = $pager;
        }

        $this->pagedata['order_id'] = $order_id;

        if ($page == 1) {
            $view = 'admin/return_product/rchange/diff_orders.html';
        } else {
            $view = 'admin/return_product/rchange/diff_orders_container.html';
        }
        $this->display($view);
    }

    /**
     * @description 补差价订单选定确认
     * @access public
     */
    public function diffOrderSelected()
    {
        $post  = kernel::single('base_component_request')->get_post();
        $order = kernel::single('ome_return_rchange')->diffOrderValidate($post, $errormsg);
        if ($order === false) {
            $this->pagedata['errormsg'] = $errormsg;
        }
        $this->pagedata['order'] = $order;
        $this->display('admin/return_product/rchange/diff_orders_confirm.html');
    }

    /**
     * @description 质检异常处理页
     * @access public
     */
    public function processException($reship_id)
    {
        $reshipModel                 = $this->app->model('reship');
        $reason                      = $reshipModel->select()->columns('reason')->where('reship_id=?', $reship_id)->instance()->fetch_one();
        $reason                      = unserialize($reason);
        $this->pagedata['reason']    = $reason['sv'];
        $this->pagedata['reship_id'] = $reship_id;
        $this->display('admin/return_product/rchange/process_exception.html');
    }

    /**
     * @description质检异常确认
     * @access public
     */
    public function doException()
    {
        $this->begin();
        $reship_id = $_POST['reship_id'];
        if (!$reship_id) {
            $this->end(false, $this->app->_('退换货单据不存在!'));
        }
        $op_info     = kernel::single('ome_func')->getDesktopUser();
        $reshipModel = $this->app->model('reship');
        $row         = $reshipModel->select()->columns('reason,is_check')->where('reship_id=?', $reship_id)->instance()->fetch_row();
        if ($row['is_check'] == '10') {
            $this->end(false, '对不起！该单据已经置为异常!');
        }
        $reason         = $row['reason'];
        $reason         = (array) unserialize($reason);
        $reason['sv'][] = array(
            'op_id'      => $op_info['op_id'],
            'op_name'    => $op_info['op_name'],
            'reason'     => $_POST['reason'],
            'createtime' => time(),
        );
        $updateData = array(
            'reason'   => serialize($reason),
            'is_check' => '10',
        );
        $reshipModel->update($updateData, array('reship_id' => $reship_id));
        # 写日志
        $this->app->model('operation_log')->write_log('reship@ome', $reship_id, '质检异常，重新审核');
        $this->end(true);
    }

    /**
     * 发送退货单至第三方
     * @author sunjing@shopex.cn
     */
    public function batch_sync()
    {
        // $this->begin('');
        $ids     = $_POST['reship_id'];
        $oReship = app::get('ome')->model('reship');
        if ($ids) {
            foreach ($ids as $reship_id) {
                $reship_list = $oReship->dump(array($reship_id => $reship_id, 'is_check' => 1), 'reship_id');
                if ($reship_list)
                {
                    $reship_data = kernel::single('ome_receipt_reship')->reship_create(array('reship_id' => $reship_id));
                    $wms_id      = kernel::single('ome_branch')->getWmsIdById($reship_data['branch_id']);
                    kernel::single('console_event_trigger_reship')->create($wms_id, $reship_data, false);
                }
            }
        }
        $this->splash('success', null, '命令已经被成功发送！！');
    }

    /**
     * 获取换货明细
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function getchangeProducts($reship_id)
    {
        $changebranch_id = $_GET['changebranch_id'];
        $rchangeobj      = kernel::single('ome_return_rchange');
        $changelist      = $rchangeobj->getChangelist($reship_id, $changebranch_id);
        echo json_encode($changelist);
    }

    /**
     * @param Int $order_id 订单ID
     * @param array 返回订单明细
     */
    public function get_Orderdata($order_id, $source)
    {

        $newItems       = array();
        $tmp_product    = array();
        $archive_ordObj = kernel::single('archive_interface_orders');
        if ($source == 'archive') {
            $order_object = $archive_ordObj->getOrder_object(array('order_id' => $order_id), '*');
            $items        = $archive_ordObj->getItemList($order_id);
        } else {
            $oOrders_item = $this->app->model('order_items');
            $order_object = $this->app->model('order_objects')->getList('*', array('order_id' => $order_id));
            $items        = $oOrders_item->getList('*', array('order_id' => $order_id), 0, -1, 'obj_id desc');
        }
        $oReship_item = $this->app->model('reship_items');
        foreach ($order_object as $object) {
            $table = '<table><caption>捆绑信息</caption><thead><tr><th>基础物料编码</th><th>基础物料名称</th><th>价格</th><th>数量</th></tr></thead><tbody><tr>';
            $table .= '<td>' . $object['bn'] . '</td><td>' . $object['name'] . '</td><td>' . $object['price'] . '</td><td>' . $object['quantity'] . '</td>';
            $table .= '</tr></tbody></table>';
            $object['ref']              = $table;
            $oObject[$object['obj_id']] = $object;
        }
        $reshipObj = $this->app->model('reship');
        $tmpsale   = $reshipObj->getSalepriceByorderId($order_id);
        $color     = array('red', 'blue');
        foreach ($items as $k => $v) {
            $str_spec_value = '';
            $spec_info      = unserialize($v['addon']);
            if (!empty($spec_info['product_attr'])) {
                foreach ($spec_info['product_attr'] as $_val) {
                    $str_spec_value .= $_val['value'] . '|';
                }
                if (!empty($str_spec_value)) {
                    $str_spec_value = substr_replace($str_spec_value, '', -1, 1);
                }
                $items[$k]['spec_value'] = $str_spec_value;
            }

            if (!$objColor[$v['obj_id']]) {
                $objColor[$v['obj_id']] = $c = array_shift($color);
                array_push($color, $c);
            }

            //if($newItems[$v['bn']] && $newItems[$v['bn']]['bn'] !=''){
            // $newItems[$v['bn']]['nums'] += $items[$k]['nums'];
            //$newItems[$v['bn']]['sendnum'] += $items[$k]['sendnum'];
            //}else{
            if ($source == 'archive') {
                $refund              = $archive_ordObj->Get_refund_count($order_id, $v['bn']);
                $items[$k]['branch'] = $archive_ordObj->getBranchCodeByBnAndOd($v['bn'], $order_id);
            } else {
                $refund              = $oReship_item->Get_refund_count($order_id, $v['bn'], '', $v['item_id']);
                $items[$k]['branch'] = $oReship_item->getBranchCodeByBnAndOd($v['bn'], $order_id);
            }

            $items[$k]['effective'] = $refund;
            $items[$k]['obj_type']  = $oObject[$v['obj_id']]['obj_type'];
            if ($oObject[$v['obj_id']]['ref']) {
                $items[$k]['ref']   = $oObject[$v['obj_id']]['ref'];
                $items[$k]['color'] = $objColor[$v['obj_id']];
            }
            
            //基础物料销售金额
            if($items[$k]['item_type'] == 'lkb'){
                //取订单明细上的实付金额(福袋销售物料关联多个福袋会有多个相同的基础物料)
                $items[$k]['sale_price'] = $items[$k]['divide_order_fee'];
            }else{
                //取销售单明细上的销售金额
                $items[$k]['sale_price'] = $tmpsale[$oObject[$v['obj_id']]['bn']][$v['bn']] > 0 ? $tmpsale[$oObject[$v['obj_id']]['bn']][$v['bn']] : $items[$k]['sale_price'];
            }
            
            $items[$k]['order_item_id'] = $v['item_id'];
            $newItems[]                 = $items[$k];
            // }
        }

        return $newItems;
    }

    public function checkCancel($reship_id)
    {
        $reshipObj = app::get('ome')->model('reship');
        $returnMdl = app::get('ome')->model('return_product');
        $operLobMdl = app::get('ome')->model('operation_log');
        
        $reshipLib = kernel::single('ome_reship');
        $branchLib = kernel::single('ome_branch');
        
        //退换货单信息
        $reship_detail = $reshipObj->dump(array('reship_id' => $reship_id), '*');
        $reship_detail['return_id'] = intval($reship_detail['return_id']);
        
        $this->begin();
        
        //result
        if ($reship_detail['is_check'] == '0' && empty($reship_detail['return_id'])) {
            //直接返回成功
            $result = array('rsp' => 'succ');
        }elseif(in_array($reship_detail['shop_type'] ,array( 'luban','meituan4medicine'))){
            $lubanReutrnInfo = app::get('ome')->model('return_product_luban')->db_dump(array('return_id'=>$reship_detail['return_id']));
            if(!empty($lubanReutrnInfo)){
                if ($_FILES['refuse_proof']['size']<=0) {
                    $this->end(false, '请上传凭证图片!');
                }
                
                if ($_FILES ['refuse_proof'] ['size'] > 512000) {
                    $this->end(false, '上传文件不能超过500K!');
                }
                
                $type = array ('gif','jpg','png');
                $pathinfo = pathinfo($_FILES ['refuse_proof'] ['name']);
                if (!in_array($pathinfo['extension'], $type)) {
                    $this->end(false, "您只能上传以下类型文件".implode('、', $type)."!");
                }
                
                $storager = kernel::single ( 'base_storager' );
                $id = $storager->save_upload ( $_FILES ['refuse_proof'], "file", "", $msg ); //返回file_id;
                $refuse_memo['image'] = $storager->getUrl ( $id, "file" );
                $imagebinary = $refuse_memo['image'];
                
                //memo
                $memo['refuse_proof']   = $imagebinary;
                $memo['reject_reason_code'] = $_POST['seller_refuse_reason_id'];
                $memo['remark'] = $_POST['refuse_message'];
                $memo['parse'] = 'second';
                
                //request
                $result = kernel::single('ome_service_aftersale')->update_status($reship_detail['return_id'],'5','sync',$memo);
                if ($result['rsp'] != 'succ' && !$_POST['forced_cancel']) {
                    $this->end(false, '请求平台拒绝失败：'.$result['msg']);
                }
            }
            
            if($reship_detail['shop_type'] == 'meituan4medicine'){
                $memo['reject_reason_code'] = $_POST['seller_refuse_reason_id'];
                $rs = kernel::single('ome_service_aftersale')->update_status($reship_detail['return_id'],'5','sync',$memo);
                if ($rs['rsp'] != 'succ' && !$_POST['forced_cancel']) {
                    $this->end(false, '请求平台拒绝失败：'.$result['msg']);
                }
            }
    
            //取消第三方WMS退货单
            if ($reship_detail['is_check'] != '0'){
                $wms_id = $branchLib->getWmsIdById($reship_detail['branch_id']);
                $branch = $branchLib->getBranchInfo($reship_detail['branch_id'], 'branch_bn,storage_code,owner_code');
                
                //request
                $data   = array(
                    'order_id' => $reship_detail['reship_bn'],
                    'reship_id' => $reship_detail['reship_id'],
                    'reship_bn' => $reship_detail['reship_bn'],
                    'branch_bn' => $branch['branch_bn'],
                    'owner_code' => $branch['owner_code'],
                    'return_type' => $reship_detail['return_type'],
                    'branch_id'     =>  $reship_detail['branch_id'],
                );
                $result = kernel::single('console_event_trigger_reship')->cancel($wms_id, $data, true);
                if(!in_array($result['rsp'],array('succ','success')) && !$_POST['forced_cancel']){
                    $this->end(false, '拒绝退换货单失败：'.$result['msg']);
                }
            }
            
            //@todo：手工创建的没有售后申请单return_id
            if($reship_detail['return_id']){
                //更新售后申请单拒绝状态
                $data = array('status'=>'5', 'last_modified'=>time());
                $returnMdl->update($data, array('return_id'=>$reship_detail['return_id']));
                
                //log
                $log_msg = '退换货单状态:拒绝'. ($_POST['refuse_message'] ? '('.$_POST['refuse_message'].')' : '');
                $operLobMdl->write_log('return@ome', $reship_detail['return_id'], $log_msg);
            }
            
        }else{
            //取消第三方WMS退货单
            if ($reship_detail['is_check'] != '0'){
                $wms_id = $branchLib->getWmsIdById($reship_detail['branch_id']);
                $branch = $branchLib->getBranchInfo($reship_detail['branch_id'], 'branch_bn,storage_code,owner_code');
                
                //request
                $data   = array(
                    'order_id' => $reship_detail['reship_bn'],
                    'reship_id' => $reship_detail['reship_id'],
                    'reship_bn' => $reship_detail['reship_bn'],
                    'branch_bn' => $branch['branch_bn'],
                    'owner_code' => $branch['owner_code'],
                    'return_type' => $reship_detail['return_type'],
                    'branch_id'     =>  $reship_detail['branch_id'],
                );
                $result = kernel::single('console_event_trigger_reship')->cancel($wms_id, $data, true);
                if($result['rsp'] != 'succ'  && !$_POST['forced_cancel']){
                    $this->end(false, '拒绝退换货单失败：'.$result['msg']);
                }
            }
            
            //其它平台拒绝
            if ($reship_detail['return_id']){
                //记录退货单取消拒绝备注
                $memo = $reship_detail['memo'] . ' 拒绝备注:' . $_POST['refuse_message'];
                $reshipObj->update(['memo' => $memo], ['reship_id' => $reship_detail['reship_id']]);
                $data = array(
                    'return_id'=>$reship_detail['return_id'], 
                    'status'=>'5', 
                    'last_modified'=>time()
                );
                $returnMdl->update_status($data);
                
                //log
                $log_msg = '退换货单状态:拒绝'. ($_POST['refuse_message'] ? '('.$_POST['refuse_message'].')' : '');
                $operLobMdl->write_log('return@ome', $reship_detail['return_id'], $log_msg);
            }
        }
        
        //换货单释放冻结库存
        kernel::single('console_reship')->releaseChangeFreeze($reship_id);
        
        //[京东一件代发]释放退货包裹数量
        $wms_type = $branchLib->getNodetypBybranchId($reship_detail['branch_id']);
        $wms_id = $branchLib->getWmsIdById($reship_detail['branch_id']);
        if($wms_type == 'yjdf' && $wms_id){
            $result = $reshipLib->cancel_reship_package($reship_detail, $error_msg);
            
            //拉取京东售后审核意见,并同步抖音售后单备注内容
            $data = array(
                    'reship_id' => $reship_detail['reship_id'],
                    'reship_bn' => $reship_detail['reship_bn'],
                    'order_id' => $reship_detail['order_id'],
            );
            $result = kernel::single('erpapi_router_request')->set('wms', $wms_id)->reship_search($data);
        }
        
        //更新退货单拒绝状态
        $reshipObj->update(array('is_check'=>'5', 't_end'=>time()), array('reship_id'=>$reship_id));

        // 退货单取消成功报警通知
        kernel::single('ome_reship')->sendReshipCancelSuccessNotify($reship_detail, $_POST['refuse_message'] ?: '');

        
        //log
        $log_msg = '手工拒绝退换货单成功'. ($_POST['refuse_message'] ? '('.$_POST['refuse_message'].')' : '');
        $operLobMdl->write_log('reship@ome', $reship_id, $log_msg);
        // 退货单取消后的service扩展点
        foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
            if(method_exists($object, 'cancel_reship_after')) {
                $object->cancel_reship_after($reship_id);
            }
        }
        $this->end(true, '拒绝成功');
    }

    public function batch_approve()
    {
        $reshipObj = app::get('ome')->model('reship');

        //获取可操作数据
        $filter   = array('reship_id' => $_POST['reship_id'], 'return_type' => array('return', 'change'), 'is_check' => '0', 'status' => 'ready');
        $dataList = $reshipObj->getList('reship_id', $filter, 0, 100);

        $sdf = array();
        if ($dataList) {
            foreach ($dataList as $key => $val) {
                $sdf[] = $val['reship_id'];
            }
        }

        $this->pagedata['need_return_list']       = json_encode($sdf);
        $this->pagedata['need_return_list_count'] = count($sdf);
        $this->pagedata['finder_id']              = $_GET['finder_id'];

        $this->display('admin/return_product/rchange/batch_approve.html');
    }

    /**
     * 请求执行可操作
     * @param
     * @return
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function ajax_batch_approve()
    {
        set_time_limit(0);

        $reshipLib = kernel::single('ome_reship');
        $reshipObj = app::get('ome')->model('reship');

        $result = array('rsp' => 'fail', 'error_msg' => '');

        //post
        $reship_id = intval($_POST['reship_id']);
        if (!$reship_id) {
            $result['error_msg'] = '无效操作!';
            echo (json_encode($result));
            exit;
        }

        //检查物流单号
        $reshipInfo = $reshipObj->dump(array('reship_id' => $reship_id), 'reship_id,reship_bn,return_logi_no');
        if (empty($reshipInfo)) {
            $result['error_msg'] = '退换货单不存在!';
            echo (json_encode($result));
            exit;
        }

        //自动审核是否需要有回退物流单号
        $mstLogiApprove = app::get('ome')->getConf('return.logi_auto_approve');
        if ($mstLogiApprove == 'on') {
            if (empty($reshipInfo['return_logi_no'])) {
                //返回错误信息（包含退货单号）
                $reship_bn = !empty($reshipInfo['reship_bn']) ? $reshipInfo['reship_bn'] : $reship_id;
                $result['error_msg'] = '批量审核失败：缺少回退物流单号 (退换货单号：' . $reship_bn . ')';
                echo (json_encode($result));
                exit;
            }
        }

        //执行审核
        $params  = array('reship_id' => $reship_id, 'status' => '1', 'is_anti' => false, 'exec_type' => 2);
        $confirm = $reshipLib->confirm_reship($params, $error_msg, $is_rollback);
        if (!$confirm) {
            $result['error_msg'] = $error_msg;
            echo (json_encode($result));
            exit;
        }

        //return
        $result = array('rsp' => 'succ', 'error_msg' => '');
        echo (json_encode($result));
        exit;
    }

    public function modifyReshipBranch()
    {
        $reshipObj = app::get('ome')->model('reship');
        $reship_id = $_POST['reship_id'] ? : [];
        $reship_id[] = -1;
        //获取可操作数据
        $filter   = array('reship_id' => $reship_id, 'return_type' => array('return', 'change'), 'is_check' => '0', 'status' => 'ready');
        $dataList = $reshipObj->getList('reship_id', $filter, 0, 100);

        $sdf = array();
        if ($dataList) {
            foreach ($dataList as $key => $val) {
                $sdf[] = $val['reship_id'];
            }
        }
        $branchs = app::get('ome')->model('branch')->getList('branch_id,branch_bn', array('b_type'=>'1'));
        $this->pagedata['branchs'] = array_column($branchs, 'branch_bn', 'branch_id');
        $this->pagedata['GroupList'] = json_encode($sdf);
        $this->pagedata['itemCount'] = count($sdf);
        $this->pagedata['request_url'] = $this->url.'&act=ajaxModifyReshipBranch';
        $this->pagedata['custom_html'] = $this->fetch('admin/return_product/rchange/modifyReshipBranch.html');
        parent::dialog_batch();
    }

    public function ajaxModifyReshipBranch()
    {
        $itemIds = explode(',',$_POST['primary_id']);
        if (!$itemIds) { echo 'Error: 缺少调整单明细';exit;}
        $branchId = $_POST['branch_id'];
        if (!$branchId) { echo 'Error: 缺少仓库';exit;}
        $branch = app::get('ome')->model('branch')->db_dump(['branch_id'=>$branchId], 'branch_id,branch_bn');
        if (!$branch) { echo 'Error: 仓库不存在';exit;}
        $retArr = array(
            'itotal'  => count($itemIds),
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );
        foreach ($itemIds as $itemId) {
            $filter   = array('reship_id' => $itemId, 'return_type' => array('return', 'change'), 'is_check' => '0', 'status' => 'ready');
            $reship = app::get('ome')->model('reship')->db_dump($filter, 'branch_id,reship_bn');
            if($reship['branch_id'] == $branchId) {
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = $reship['reship_bn'].':仓库已修改，无需重复修改';
                continue;
            }
            $oldBranch = app::get('ome')->model('branch')->db_dump(['branch_id'=>$reship['branch_id']], 'branch_id,branch_bn');
            $rs = app::get('ome')->model('reship')->update(['branch_id'=>$branchId], $filter);
            if(is_bool($rs)) {
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = $reship['reship_bn'].':修改失败';
            } else {
                $retArr['isucc'] += 1;
                $itemFilter = array('reship_id'=>$itemId, 'return_type' => array('return'));
                app::get('ome')->model('reship_items')->update(['branch_id'=>$branchId], $itemFilter);
                app::get('ome')->model('operation_log')->write_log('reship@ome', $itemId, '修改仓库:'.$oldBranch['branch_bn'].'->'.$branch['branch_bn']);
            }
        }

        echo json_encode($retArr),'ok.';
    }

    public function exportTemplate()
    {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=Reship" . date('Ymd') . ".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $pObj   = app::get('ome')->model('reship');
        $title1 = $pObj->exportTemplate('import_reship');
        echo '"' . implode('","', $title1) . '"';
        $title2 = $pObj->exportTemplate('import_reship_return_item');
        echo "\n\n";
        echo '"' . implode('","', $title2) . '"';
        $title3 = $pObj->exportTemplate('import_reship_change_item');
        echo "\n\n";
        echo '"' . implode('","', $title3) . '"';
    }

    public function batch_search()
    {
        $this->begin('index.php?app=ome&ctl=admin_return_rchange&act=index');

        $reshipMdl = app::get('ome')->model('reship');

        $_POST['is_check'] = array('1','3','7');

        $channelMdl   = app::get('channel')->model('channel');
        $channel_list = $channelMdl->getList('channel_id', array('node_type' => 'jd_wms_cloud'));

        $_POST['wms_id'] = array_column($channel_list, null, 'channel_id');

        $reship_list = $reshipMdl->getList('reship_id,branch_id,out_iso_bn,reship_bn', $_POST);
        foreach ($reship_list as $reship) {
            $wms_id = kernel::single('ome_branch')->getWmsIdById($reship['branch_id']);
            $data   = array(
                'out_order_code' => $reship['out_iso_bn'],
                'stockin_bn'     => $reship['reship_bn'],
                'reship_id' => $reship['reship_id'],
                'reship_bn' => $reship['reship_bn'],
            );
            
            $result = kernel::single('erpapi_router_request')->set('wms', $wms_id)->reship_search($data);
        }

        $this->end(true, '查询WMS状态,命令已经被成功发送');
    }
    
    /**
     * 查询寄件地址
     * 
     * @param int $reship_id
     * @return html
     */
    public function select_address($reship_id)
    {
        if (!$reship_id) {
            die("单据号传递错误！");
        }
        
        $reshipObj = app::get('ome')->model('reship');
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), 'reship_bn,return_id,return_type,is_check');
        
        $this->pagedata['reship_bn'] = $reshipInfo['reship_bn'];
        $this->pagedata['reship_id'] = $reship_id;
        
        $this->display('admin/return_product/rchange/select_address.html');
    }
    
    /**
     * 查询寄件地址
     */
    public function do_select_address()
    {
        $this->begin('index.php?app=ome&ctl=admin_return_rchange&act=index');
        
        $reship_id = $_POST['reship_id'];
        if(empty($reship_id)) {
            $this->end(false, '无效的操作');
        }
        
        //query
        $error_msg = '';
        $result = ome_return_notice::selectAddress($reship_id, $error_msg);
        if(!$result){
            $this->end(false, $error_msg);
        }
        
        $this->end(true, '查询寄件地址,命令已经被成功发送');
    }
    
    /**
     * 顾客申请退货,京东云交易未发货时,拦截京东包裹
     */
    public function ajax_hold_delivery()
    {
        $reship_id = intval($_POST['reship_id']);
        if(empty($reship_id)){
            $error_msg = '无效的操作。';
            echo json_encode(array('res'=>'error', 'error_msg'=>$error_msg));
            exit;
        }
        
        $reshipObj = app::get('ome')->model('reship');
        $reshipItemObj = app::get('ome')->model('reship_items');
        $deliveryObj = app::get('ome')->model('delivery');
        
        $branchLib = kernel::single('ome_branch');
        $reshipLib = kernel::single('ome_reship');
        $channel_type = 'wms';
        
        //退换货单信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), 'reship_id,reship_bn,return_id,order_id,source,branch_id,shop_type,flag_type');
        $order_id = $reshipInfo['order_id'];
        
        //wms_id
        $channel_id = $branchLib->getWmsIdById($reshipInfo['branch_id']);
        
        //branch_bn
        $branch_bn = $branchLib->getBranchBnById($reshipInfo['branch_id']);
        $reshipInfo['branch_bn'] = $branch_bn;
        
        //查询包裹发货状态
        $error_msg = '';
        $delivery_ids = array();
        $packageList = $reshipLib->get_reship_package($reship_id, $error_msg);
        if(!$packageList){
            $error_msg = '查询包裹发货状态失败：没有包裹信息。';
            echo json_encode(array('res'=>'error', 'error_msg'=>$error_msg));
            exit;
        }
        
        foreach ($packageList as $key => $val)
        {
            $delivery_ids[] = $val['delivery_id'];
        }
        
        //获取发货单,查询包裹发货状态
        $isCheck = $deliveryObj->dump(array('delivery_id'=>$delivery_ids, 'status'=>'succ'), 'delivery_id');
        if(empty($isCheck)){
            echo json_encode(array('res'=>'succ')); //发货单已追回
            exit;
        }
        
        //查询包裹发货状态
        $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->reship_query_delivery($reshipInfo);
        if($result['rsp'] != 'succ'){
            $error_msg = $result['msg'];
            echo json_encode(array('res'=>'error', 'error_msg'=>$error_msg));
            exit;
        }
        
        if(empty($result['data'])){
            $error_msg = '没有查询到包裹的发货状态';
            echo json_encode(array('res'=>'error', 'error_msg'=>$error_msg));
            exit;
        }
        
        //多个包裹,部分用户已签收,还有部分用户未签收
        if($result['data']['delivery'] && $result['data']['accept']){
            $error_msg = '部分包裹用户还没签收,不能审核退货!';
            echo json_encode(array('res'=>'succ', 'error_msg'=>$error_msg));
            exit;
        }elseif($result['data']['accept']){
            //[异步]包裹用户还没有签收的场景(拦截取消包裹)
            $res = $reshipLib->cancel_delivery_package($reshipInfo, $error_msg);
            if(!$res){
                echo json_encode(array('res'=>'error', 'error_msg'=>$error_msg));
                exit;
            }
            
            $error_msg = '已向京东请求拦截取消包裹,请等待京东返回结果!';
            echo json_encode(array('res'=>'succ', 'error_msg'=>$error_msg));
            exit;
        }
        
        $error_msg = '没有包裹需要拦截';
        echo json_encode(array('res'=>'succ', 'error_msg'=>$error_msg));
        exit;
    }
    
    /**
     * 上传拒绝退货图片
     */
    public function upload_file()
    {
        $this->begin('index.php?app=ome&ctl=admin_return_rchange&act=index');
        
        if(empty($_POST)){
            $this->end(false, '无效的提交操作!');
        }
        
        $reshipObj = app::get('ome')->model('reship');
        $returnObj = app::get('ome')->model('return_product');
        
        $reship_id = $_POST['reship_id'];
        if(empty($reship_id)){
            $this->end(false, '无效的操作!');
        }
        
        //退货信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), 'reship_bn,return_id,return_type,changebranch_id,is_check,branch_id,shop_type,shop_id');
        if(empty($reshipInfo['return_id'])){
            $this->end(false, '没有找到关联售后申请单,无法上传图片!');
        }
        
        //check
        if(empty($_FILES['attachment'])){
            $this->end(false, '没有上传附件!');
        }
        
        if($_FILES['attachment']['size'] < 0){
            $this->end(false, '上传文件大小不正确!');
        }
        
        if($_FILES['attachment']['size'] > 314572800){
            $this->end(false, '上传文件不能超过300M!');
        }
        
        $type = array("jpg", "gif", "png", "jpeg");
        if ($_FILES['attachment']['name']) {
            if (!in_array(strtolower(substr(strrchr($_FILES['attachment']['name'], '.'), 1)), $type)) {
                $text = implode(",", $type);
                $this->end(false, "您只能上传以下类型文件{$text}!");
            }
        }
        
        //上传并保存图片
        $fileList = kernel::single('base_storager');
        $id = $fileList->save_upload($_FILES['attachment'], "file", "", $msg);
        if(!$id){
            $this->end(false, "上传图片失败.");
        }
        $returnObj->update(array('attachment'=>$id), array('return_id'=>$reshipInfo['return_id']));
        
        $this->end(true, '上传拒绝图片成功！');
    }
    
    /**
     * 批量拒绝操作页
     */
    public function batch_refuse()
    {
        $reshipObj = app::get('ome')->model('reship');
        
        $ids = $_POST['reship_id'];
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不支持全选');
        }
        
        if(empty($ids)){
            die('无效的操作,没有选择数据');
        }
        
        //获取可操作数据
        $filter = array('reship_id'=>$ids, 'return_type'=>array('return', 'change'), 'is_check'=>'0', 'status'=>'ready');
        $dataList = $reshipObj->getList('reship_id,shop_type', $filter, 0, 500);
        if(empty($dataList)){
            die('没有可操作的数据');
        }
        
        $sdf = array();
        foreach ($dataList as $key => $val)
        {
            if($val['shop_type'] == 'tmall'){
                continue;
            }
    
            $sdf[] = $val['reship_id'];
        }
        
        if(empty($sdf)){
            die('不支持天猫店铺批量拒绝(天猫平台需要上传拒绝凭证图片)');
        }
        
        $this->pagedata['need_return_list'] = json_encode($sdf);
        $this->pagedata['need_return_list_count'] = count($sdf);
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        
        $this->display('admin/return_product/rchange/batch_refuse.html');
    }
    
    /**
     * 批量拒绝最终处理
     */
    public function ajax_batch_refuse()
    {
        set_time_limit(0);
        
        $reshipObj = $this->app->model('reship');
        $oOperation_log = app::get('ome')->model('operation_log');
        
        $result = array('rsp'=>'fail', 'error_msg'=>'');
        
        //post
        $reship_id = intval($_POST['reship_id']);
        if (empty($reship_id)) {
            $result['error_msg'] = '无效操作,没有可操作的数据';
            echo(json_encode($result));
            exit;
        }
        
        //dump
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), 'reship_bn,return_id,return_type,shop_type,is_check');
        if($reshipInfo['shop_type'] == 'tmall'){
            $result['error_msg'] = '不支持批量拒绝天猫平台退换货单';
            echo(json_encode($result));
            exit;
        }
        
        if($reshipInfo['is_check'] != '0'){
            $result['error_msg'] = '退换货单已审核,不能批量拒绝';
            echo(json_encode($result));
            exit;
        }
        
        //update
        $reshipObj->update(array('is_check'=>'5', 't_end'=>time()), array('reship_id'=>$reship_id));

        // 退货单取消成功报警通知
        kernel::single('ome_reship')->sendReshipCancelSuccessNotify($reshipInfo, '批量拒绝退换货单');
        
        /***
        //[已审核]拒绝时,需要释放冻结库存
        if ($reshipInfo['return_type'] == 'change' && $reshipInfo['is_check'] == '1') {
            //库存管控 换货预占 目前只有电商仓
            kernel::single('console_reship')->releaseChangeFreeze($reship_id);
        }
        ***/
        
        //log
        $memo = '批量拒绝退换货单';
        if($reshipInfo['return_id']) {
            $oProduct = app::get('ome')->model('return_product');
            
            $data = array('return_id'=>$reshipInfo['return_id'], 'status'=>'5', 'last_modified'=>time());
            $oProduct->update_status($data);
            
            $oOperation_log->write_log('return@ome', $reshipInfo['return_id'], $memo);
        }
        $oOperation_log->write_log('reship@ome', $reship_id, $memo);
        
        //return
        $result = array('rsp'=>'succ', 'error_msg'=>'');
        echo (json_encode($result));
        exit;
    }
    
    //申请取消售后申请单
    public function do_cancel_package()
    {
        $Oreship = app::get('ome')->model('reship');
        
        $reshipLib = kernel::single('ome_reship');
        $branchLib = kernel::single('ome_branch');
        
        $url = 'index.php?app=ome&ctl=admin_return_rchange&act=index';
        $reship_id = $_POST['reship_id'];
        
        //验证数据
        if(empty($reship_id)){
            $this->splash('error', $url, '没有可操作的退换货单');
        }
        
        //退货信息
        $reshipInfo = $Oreship->dump(array('reship_id'=>$reship_id), 'order_id,reship_id,reship_bn,return_id,return_type,is_check,branch_id,shop_type,shop_id');
        
        //check
        if(!in_array($reshipInfo['is_check'], array('0','1','2'))){
            $this->splash('error', $url, '退换货单状态不允许取消');
        }
        
        $wms_id = $branchLib->getWmsIdById($reshipInfo['branch_id']);
        $branchInfo = $branchLib->getBranchInfo($reshipInfo['branch_id'], 'branch_bn,owner_code');
        
        //request
        $params = array(
                'order_id' => $reshipInfo['order_id'],
                'reship_id' => $reshipInfo['reship_id'],
                'reship_bn' => $reshipInfo['reship_bn'],
                'branch_bn' => $branchInfo['branch_bn'],
                'owner_code' => $branchInfo['owner_code'],
                'return_type' => $reshipInfo['return_type'],
        );
        $res = kernel::single('console_event_trigger_reship')->cancel($wms_id, $params, true);
        if($res['rsp'] != 'succ'){
            $this->splash('error', $url, '取消京东售后单失败：'.$res['error_msg']);
        }
        
        $this->splash('success', $url, '请求取消京东售后单成功,等待京东异步取消结果回传。');
    }
    
    public function batchModifyReason()
    {
        $problemMdl = app::get('ome')->model('return_product_problem');


        $this->pagedata['problem_list'] = $problemMdl->getList('*', ['disabled' => 'false']);

        $this->pagedata['custom_html'] = $this->fetch('admin/reship/modify_reason.html');

        $this->pagedata['request_url'] = $this->url. '&act=ajaxModifyReason';

        $reshipMdl = app::get('ome')->model('reship');
        $reshipMdl->filter_use_like = true;

        $reship_id = array();
        foreach($reshipMdl->getList('reship_id',$_POST) as $val){
            $reship_id[] = $val['reship_id'];
        }

        $this->pagedata['GroupList'] = json_encode($reship_id);

        parent::dialog_batch();
    }

    public function ajaxModifyReason()
    {
        $primary_id = explode(',', $_POST['primary_id']);

        if (!$primary_id) { echo 'Error: 请先选择退换货单';exit;}

        $problem_id = $_POST['problem_id'];

        if (!$problem_id) {
            echo 'Error: 售后原因必填';exit;
        }

        $problemMdl = app::get('ome')->model('return_product_problem');

        $problem = $problemMdl->db_dump($problem_id);
        if (!$problem) {
            echo 'Error: 售后原因不存在';exit;
        }


        $retArr = array(
            'itotal'  => count($primary_id),
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );

        $reshipMdl = app::get('ome')->model('reship');
        $optLogMdl = app::get('ome')->model('operation_log');


        foreach ($reshipMdl->getList('reship_id,problem_id',['reship_id' => $primary_id]) as $reship) {
            $oldProblem = $problemMdl->db_dump(intval($reship['problem_id']));

            $reshipMdl->update(['problem_id' => $problem['problem_id']], ['reship_id' => $reship['reship_id']]);

            $optLogMdl->write_log('reship@ome', $reship['reship_id'], '修改售后原因，前售后原因为：'.$oldProblem['problem_name']);

            $retArr['isucc']++;
        }

        echo json_encode($retArr),'ok.';exit;

    }
    
    public function batch_sync_logic()
    {
        // $this->begin('');
        $reshipObj = app::get('ome')->model('reship');
        $operLogObj = app::get('ome')->model('operation_log');
        $processObj = app::get('ome')->model('return_process');
        
        $reshipLib = kernel::single('ome_reship');
        $keplerLib = kernel::single('ome_reship_kepler');
        
        $reship_ids = $_POST['reship_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->splash('error', null, '不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($reship_ids))
        {
            $this->splash('error', null, '请选择需要操作的退货单!');
        }
        
        if(count($reship_ids) > 500){
            $this->splash('error', null, '每次最多只能选择500条!');
        }
        
        $dataList = $reshipObj->getList('reship_id,reship_bn,is_check,return_logi_no,return_logi_name,abnormal_status', array('reship_id'=>$reship_ids));
        foreach ($dataList as $key => $reshipInfo)
        {
            $reship_id = $reshipInfo['reship_id'];
            
            if(!in_array($reshipInfo['is_check'], array('0','1','2'))){
                continue;
            }
            
            //check
            if(empty($reshipInfo['return_logi_no'])){
                continue;
            }
            
            $reshipInfo['return_logi_name'] = str_replace(array('"', "'"), '', $reshipInfo['return_logi_name']);
            
            //获取售后服务单
            $log_error_msg = '';
            $serviceList = $keplerLib->get_reship_services($reship_id, false, $log_error_msg);
            if(empty($serviceList)){
                $log_error_msg = '没有服务单,不能保存退回物流信息';
                
                //log
                $operLogObj->write_log('reship@ome', $reship_id, $log_error_msg);
                
                continue;
            }
            
            //check
            $sql = "SELECT corp_id,type FROM sdb_ome_dly_corp WHERE name='". $reshipInfo['return_logi_name'] ."' OR type='". $reshipInfo['return_logi_name'] ."'";
            $corpInfo = $reshipObj->db->selectrow($sql);
            if(empty($corpInfo)){
                $error_msg = '退回物流公司名称['. $reshipInfo['return_logi_name'] .']不存在,请在OMS系统里添加物流公司';
                
                //log
                $operLogObj->write_log('reship@ome', $reship_id, $error_msg);
                
                //没有找到物流公司,给个默认值
                $corpInfo['type'] = 'JD';
            }
            
            //更新服务单退货物流信息
            $processObj->update(array('logi_code'=>$corpInfo['type'], 'logi_no'=>$reshipInfo['return_logi_no']), array('reship_id'=>$reship_id));
            
            //request
            $rsp_error_msg = '';
            $result = ome_return_notice::updateLogistics($reship_id, $rsp_error_msg);
            if(!$result){
                $rsp_error_msg = '更新退回物流公司失败：'.$rsp_error_msg;
                
                //log
                $operLogObj->write_log('reship@ome', $reship_id, $rsp_error_msg);
                
                //设置异常：更新退回物流信息失败
                $abnormal_status = ome_constants_reship_abnormal::__LOGISTICS_CODE;
                $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status | ". $abnormal_status ." WHERE reship_id=".$reship_id;
                $reshipObj->db->exec($sql);
            }else{
                //清除异常:同步WMS物流信息失败
                $abnormal_status = ome_constants_reship_abnormal::__LOGISTICS_CODE;
                if(($reshipInfo['abnormal_status'] & $abnormal_status) ==  $abnormal_status){
                    $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status ^ ". $abnormal_status ." WHERE reship_id=".$reship_id;
                    $reshipObj->db->exec($sql);
                }
            }
            
        }
        
        $this->splash('success', null, '命令已经被成功发送！！');
    }
    
    /**
     * [京东云交易]批量获取寄件地址
     */
    public function batchGetAddress()
    {
        $reshipObj = app::get('ome')->model('reship');
        $reshipObj->filter_use_like = true;
        
        $reship_ids = array();
        foreach($reshipObj->getList('reship_id', $_POST) as $val)
        {
            $reship_ids[] = $val['reship_id'];
        }
        
        $this->pagedata['custom_html'] = $this->fetch('admin/reship/batch_get_address.html');
        
        $this->pagedata['request_url'] = $this->url. '&act=ajaxGetAddress';
        
        $this->pagedata['GroupList'] = json_encode($reship_ids);
        
        parent::dialog_batch();
    }
    
    public function ajaxGetAddress()
    {
        $reshipObj = app::get('ome')->model('reship');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $keplerLib = kernel::single('ome_reship_kepler');
        
        $primary_id = explode(',', $_POST['primary_id']);
        
        if (!$primary_id) { echo 'Error: 请先选择退换货单';exit;}
        
        $retArr = array(
                'itotal'  => count($primary_id),
                'isucc'   => 0,
                'ifail'   => 0,
                'err_msg' => array(),
        );
        
        //data
        $dataList = $reshipObj->getList('reship_id,reship_bn', ['reship_id' => $primary_id]);
        foreach ($dataList as $key => $reshipInfo)
        {
            $reship_ids = array();
            $reship_ids[] = $reshipInfo['reship_id'];
            
            $error_msg = '';
            $result = $keplerLib->getReshipAddress($reship_ids, $error_msg);
            if($error_msg){
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('退货单号[%s]失败：%s', $reshipInfo['reship_bn'], $error_msg);
                
                continue;
            }
            
            $retArr['isucc']++;
        }
        
        echo json_encode($retArr),'ok.';
        exit;
    }
    
    public function getOrderIds() {
        $logino = trim($_GET['logi_no']);
        $dlyId = app::get('ome')->model('delivery')->db_dump(['logi_no'=>$logino, 'process'=>'true'], 'delivery_id');
        if(empty($dlyId)) {
            echo json_encode(['rsp'=>'fail', 'msg'=>'没有对应的已发货发货单']);
            exit();
        }
        $do = app::get('ome')->model('delivery_order')->getList('order_id', ['delivery_id'=>$dlyId['delivery_id']]);
        $order = app::get('ome')->model('orders')->getList('order_id,order_bn', ['order_id'=>array_column($do, 'order_id')]);
        if(empty($order)) {
            json_encode(['rsp'=>'fail', 'msg'=>'没有对应的订单']);
            exit();
        }
        echo json_encode(['rsp'=>'succ', 'data'=>$order]);
    }
    
    /**
     * 修改寄件地址
     *
     * @param int $reship_id
     * @return html
     */
    public function editReturnAddress($reship_id)
    {
        if (!$reship_id) {
            die('单据号传递错误！');
        }
        
        $reshipObj = app::get('ome')->model('reship');
        $addressObj = app::get('ome')->model('return_address');
        
        //reship
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), '*');
        if(in_array($reshipInfo['is_check'], array('7','5'))){
            die('退换货单状态不允许修改寄件地址。');
        }
        
        //address
        $addressInfo = $addressObj->dump(array('reship_id'=>$reship_id), '*');
        
        //省、市、区为空则允许编辑
        if(empty($addressInfo['province']) || empty($addressInfo['city']) || empty($addressInfo['country'])){
            $addressInfo['isEdit'] = 'true';
        }else{
            die('退货地址已经有省、市、区，不能重复编辑');
        }
        
        $this->pagedata['reshipInfo'] = $reshipInfo;
        $this->pagedata['addressInfo'] = $addressInfo;
        
        $this->display('admin/return_product/rchange/edit_return_address.html');
    }
    
    /**
     * 修改寄件地址
     */
    public function doEditReturnAddress()
    {
        $reshipObj = app::get('ome')->model('reship');
        $addressObj = app::get('ome')->model('return_address');
        
        $url = 'index.php?app=ome&ctl=admin_return_rchange&act=index';
        $reship_id = $_POST['reship_id'];
        $area = $_POST['reship_area'];
        
        //验证数据
        if(empty($reship_id)){
            $this->splash('error', $url, '没有可操作的退换货单');
        }
        
        if(empty($area)){
            $this->splash('error', $url, '请选择省市区');
        }
        
        //退货信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), '*');
        if(in_array($reshipInfo['is_check'], array('7','5'))){
            $this->splash('error', $url, '退换货单状态不允许修改寄件地址!');
        }
        
        //address
        $addressInfo = $addressObj->dump(array('reship_id'=>$reship_id), '*');
        
        //省、市、区为空则允许编辑
        if($addressInfo['province'] && $addressInfo['city'] && $addressInfo['country'] && $addressInfo['contact_id']){
            $this->splash('error', $url, '寄件地址已经推送平台成功,不允许编辑!');
        }
        
        $address = $addressInfo['addr'];
        
        $tempData = explode(':', $area);
        $tempData = explode('/', $tempData[1]);
        $province = $tempData[0];
        $city = $tempData[1];
        $country = $tempData[2];
        $street = $tempData[3];
        
        //check
        if(empty($province) || empty($city) || empty($country)){
            $this->splash('error', $url, '省、市、区不能为空，请重新选择。');
        }

        /***
        if(strpos($address, $province) === false){
            $this->splash('error', $url, '选择的【省份】不在寄件地址中，请重新选择。');
        }
        ***/
        
        if(strpos($address, $city) === false && strpos($address, $country) === false){
            $this->splash('error', $url, '选择【市 或者 区】至少有一项需要在寄件地址中，请重新选择。');
        }
        
        //save
        $areaSdf = array(
                'province' => $province,
                'city' => $city,
                'country' => $country,
        );
        
        if($street){
            $areaSdf['street'] = $street;
        }
        
        $addressObj->update($areaSdf, array('reship_id'=>$reship_id));
        
        //[抖音平台]请求同意退货(放入queue队列中执行)
        if(in_array($reshipInfo['is_check'], array('1','2'))){
            //todo：没有京东云交易寄件地址之前,推送抖音同意退货都失败了,需要重新推送;
            $queueObj = app::get('base')->model('queue');
            $queueData = array(
                    'queue_title' => '退货单号：'. $reshipInfo['reship_bn'] .'回传平台同意退货状态(补全寄件地址省市区)',
                    'start_time' => time(),
                    'params' => array(
                            'sdfdata' => array('reship_id'=>$reship_id, 'order_id'=>$reshipInfo['order_id']),
                            'app' => 'oms',
                            'mdl' => 'reship',
                    ),
                    'worker' => 'ome_reship_luban.syncAfterSaleStatus',
            );
            $queueObj->save($queueData);
        }
        
        //logs
        $operLogMdl = app::get('ome')->model('operation_log');
        $operLogMdl->write_log('reship@ome', $reship_id, '补全寄件地址省市区：'.implode('/', $areaSdf));
        
        $this->splash('success', $url, '修改寄件地址成功。');
    }
    
    /**
     * 批量请求WMS取消退换货单
     */
    public function batchCancelWms()
    {
        $reshipObj = app::get('ome')->model('reship');
        $reshipObj->filter_use_like = true;
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不支持全选');
        }
        
        //reship_id
        $reship_ids = array();
        foreach($reshipObj->getList('reship_id', $_POST) as $val)
        {
            $reship_ids[] = $val['reship_id'];
        }
        
        $this->pagedata['custom_html'] = $this->fetch('admin/reship/batch_cancel_wms_reship.html');
        
        $this->pagedata['request_url'] = $this->url. '&act=ajaxCancelWms';
        
        $this->pagedata['GroupList'] = json_encode($reship_ids);
        
        parent::dialog_batch();
    }
    
    public function ajaxCancelWms()
    {
        $reshipObj = app::get('ome')->model('reship');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $branchLib = kernel::single('ome_branch');
        
        $primary_ids = explode(',', $_POST['primary_id']);
        
        if (!$primary_ids) { echo 'Error: 请先选择退换货单';exit;}
        
        $retArr = array(
            'itotal'  => count($primary_ids),
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );
        
        //data
        $dataList = $reshipObj->getList('*', array('reship_id'=>$primary_ids));
        foreach ($dataList as $key => $reshipInfo)
        {
            $reship_id = $reshipInfo['reship_id'];
            
            //百秋换货不允许本地取消
            if ($reshipInfo['shop_type'] == "bq") {
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('退货单号[%s]失败：%s', $reshipInfo['reship_bn'], '小程序退换货单不允许批量取消');
                
                continue;
            }
            
            //check
            if($reshipInfo['is_check'] == '0'){
                continue;
            }
            
            //branch
            $wms_id = $branchLib->getWmsIdById($reshipInfo['branch_id']);
            $branch = $branchLib->getBranchInfo($reshipInfo['branch_id'], 'branch_bn,storage_code,owner_code');
            
            //request
            $data   = array(
                'order_id' => $reshipInfo['order_id'],
                'reship_id' => $reshipInfo['reship_id'],
                'reship_bn' => $reshipInfo['reship_bn'],
                'branch_bn' => $branch['branch_bn'],
                'owner_code' => $branch['owner_code'],
            );
            $result = kernel::single('console_event_trigger_reship')->cancel($wms_id, $data, true);
            if($result['rsp'] != 'succ'){
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('退货单号[%s]失败：%s', $reshipInfo['reship_bn'], $result['msg']);
                
                continue;
            }else{
                //换货单释放库存
                if($reshipInfo['return_type'] == 'change') {
                    kernel::single('console_reship')->releaseChangeFreeze($reship_id);
                }
                
                //update
                $reshipObj->update(array('is_check'=>'5', 'status'=>'cancel', 't_end'=>time()), array('reship_id'=>$reship_id));

                // 退货单取消成功报警通知
                kernel::single('ome_reship')->sendReshipCancelSuccessNotify($reshipInfo, '批量手工请求WMS取消退换货单');

                // 退货单取消后的service扩展点
                foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
                    if(method_exists($object, 'cancel_reship_after')) {
                        $object->cancel_reship_after($reship_id);
                    }
                }
            }
            
            //log
            $operLogObj->write_log('reship@ome', $reship_id, '批量手工请求WMS取消退换货单成功');
            
            $retArr['isucc']++;
        }
        
        echo json_encode($retArr),'ok.';
        exit;
    }
    
    /**
     * 强制取消退、换货单
     * @todo：直接取消单据，不会同步平台状态,也不会请求WMS取消;
     *
     * @return void
     */
    public function forceCancel()
    {
        $reshipObj = app::get('ome')->model('reship');
        
        $ids = $_POST['reship_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不支持全选');
        }
        
        if(empty($ids)){
            die('无效的操作,没有选择数据');
        }
        
        if(count($ids) > 100){
            $this->end(false, '每次最多只能选择100条!');
        }
        
        //获取可操作数据
        $filter = array('reship_id'=>$ids);
        $dataList = $reshipObj->getList('reship_id,reship_bn,is_check,status', array('reship_id'=>$ids));
        if(empty($dataList)){
            die('没有可操作的数据');
        }
        
        //list
        $reship_ids = array();
        foreach ($dataList as $key => $val)
        {
            //check
            if(!in_array($val['is_check'], array('0','1','2'))){
                die('退换货单号：'. $val['reship_bn'] .'审核状态不允许强制取消!');
            }
            
            if(!in_array($val['status'], array('ready'))){
                die('退换货单号：'. $val['reship_bn'] .'退货状态不允许强制取消!');
            }
            
            $reship_ids[] = $val['reship_id'];
        }
        
        $this->pagedata['custom_html'] = $this->fetch('admin/reship/batch_force_cancel.html');
        $this->pagedata['request_url'] = $this->url. '&act=ajaxForceCancel';
        $this->pagedata['GroupList'] = json_encode($reship_ids);
        
        parent::dialog_batch();
    }
    
    public function ajaxForceCancel()
    {
        $reshipObj = app::get('ome')->model('reship');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $branchLib = kernel::single('ome_branch');
        
        $primaryIds = explode(',', $_POST['primary_id']);
        if(empty($primaryIds)){
            echo 'Error: 没有可操作的退换货单';
            exit;
        }
        
        //setting
        $retArr = array(
            'itotal'  => count($primaryIds),
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );
        
        //data
        $dataList = $reshipObj->getList('reship_id,reship_bn,is_check,status,return_type,sync_status', ['reship_id'=>$primaryIds]);
        foreach ($dataList as $key => $reshipInfo)
        {
            $reship_id = $reshipInfo['reship_id'];
            
            //check
            if(!in_array($reshipInfo['is_check'], array('0','1','2'))){
                $error_msg = '审核状态不允许强制取消!';
                
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('退货单号[%s]失败：%s', $reshipInfo['reship_bn'], $error_msg);
                
                continue;
            }
            
            if(!in_array($reshipInfo['status'], array('ready'))){
                $error_msg = '退货状态不允许强制取消!';
                
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('退货单号[%s]失败：%s', $reshipInfo['reship_bn'], $error_msg);
                
                continue;
            }
            
            //请求WMS取消
            if(in_array($reshipInfo['is_check'], array('1','2')) || $reshipInfo['sync_status'] == '3'){
                $wms_id = $branchLib->getWmsIdById($reshipInfo['branch_id']);
                $branch = $branchLib->getBranchInfo($reshipInfo['branch_id'], 'branch_bn,storage_code,owner_code');
                
                //request
                $data   = array(
                    'order_id' => $reshipInfo['order_id'],
                    'reship_id' => $reshipInfo['reship_id'],
                    'reship_bn' => $reshipInfo['reship_bn'],
                    'branch_bn' => $branch['branch_bn'],
                    'owner_code' => $branch['owner_code'],
                );
                $result = kernel::single('console_event_trigger_reship')->cancel($wms_id, $data, true);
                if($result['rsp'] != 'succ'){
                    //log
                    $operLogObj->write_log('reship@ome', $reship_id, '[强制取消]请求WMS取消退换货单失败：'. $result['msg']);
                }else{
                    //log
                    $operLogObj->write_log('reship@ome', $reship_id, '[强制取消]请求WMS取消退换货单成功');
                }
            }
            
            //换货单释放库存
            kernel::single('console_reship')->releaseChangeFreeze($reship_id);
            
            //update
            $reshipObj->update(array('is_check'=>'5', 'status'=>'cancel', 't_end'=>time()), array('reship_id'=>$reship_id));

            // 退货单取消成功报警通知
            kernel::single('ome_reship')->sendReshipCancelSuccessNotify($reshipInfo, '人工手动强制取消退换货单');
            
            // 退货单取消后的service扩展点
            foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
                if(method_exists($object, 'cancel_reship_after')) {
                    $object->cancel_reship_after($reship_id);
                }
            }
            //log
            $operLogObj->write_log('reship@ome', $reship_id, '人工手动强制取消退换货单!');
            
            $retArr['isucc']++;
        }
        
        echo json_encode($retArr),'ok.';
        exit;
    }
    
    /**
     * 获取页面配置项
     */
    private function getPageConfigs()
    {
        try {
            $pageConfigModel = app::get('desktop')->model('pagecols_setting');
            if ($pageConfigModel) {
                // 获取退换货页面的配置
                $configs = $pageConfigModel->getTableConfigs('ome_reship');
                $pageConfigs = array();
                
                foreach ($configs as $config) {
                    $pageConfigs[$config['col_key']] = array(
                        'is_required' => $config['is_required'],
                        'default_value' => $config['default_value']
                    );
                }
                
                $this->pagedata['page_configs'] = $pageConfigs;
            }
        } catch (Exception $e) {
            // 如果获取配置失败，使用默认配置
            $this->pagedata['page_configs'] = array();
        }
    }
    
    /**
     * 验证页面配置项
     */
    private function validatePageConfigs($post)
    {
        $pageConfigs = app::get('desktop')->model('pagecols_setting')->getTableConfigs('ome_reship');
        
        // 字段名称映射，将技术字段名转换为用户友好的显示名称
        $fieldNameMap = [];
        $list         = kernel::servicelist('set_pagecols_setting');
        foreach ($list as $k => $obj) {
            if (method_exists($obj, 'get_pagecols_setting')) {
                $settingData = $obj->get_pagecols_setting('ome_reship');
                if (isset($settingData['elements'])) {
                    $fieldNameMap = $settingData['elements'];
                }
            }
        }
        
        // 字段名映射，将配置中的字段名映射到表单中的实际字段名
        $fieldKeyMap = array(
            'flag_type' => 'flag_type_text'
        );
        
        foreach ($pageConfigs as $config) {
            $colKey = $config['col_key'];
            $formFieldKey = isset($fieldKeyMap[$colKey]) ? $fieldKeyMap[$colKey] : $colKey;
            
            if ($config['is_required'] && empty($post[$formFieldKey])) {
                // 使用友好的字段名称
                $fieldName = isset($fieldNameMap[$colKey]) ? $fieldNameMap[$colKey] : $colKey;
                $this->end(false, "{$fieldName} 不能为空");
            }
        }
    }
}
