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

/*
 * 单据打印
 */

class ome_ctl_admin_receipts_print extends desktop_controller {

    var $name = "发货中心";
    var $workground = "delivery_center";
    var $dlyCorp_tab = 'show';
    var $deliveryOrderModel = null;

    function __construct($app){
        if(in_array($_GET['act'], ['toMergePrint','toPrintShip','toPrintExpre','toPrintDelivExpre','toPrintMerge','toPrintStock','toPrintMergeNew','toPrintStockNew','addPrintShip','addPrintShip'])) {
            $this->checkCSRF = false;
        }
        parent::__construct($app);
    }
    function _views() {
        if($this->dlyCorp_tab == 'hidden'){
           return '';
        }

        $status = kernel::single('base_component_request')->get_get('status');
        $sku = kernel::single('base_component_request')->get_get('sku');

        $query = array(
            'app'    => 'ome',
            'ctl'    => 'admin_receipts_print',
            'act'    => 'index',
            'status' => $status,
            'sku'    => $sku,
        );

        //所有自建仓
        $ownerBranch = array();
        $ownerBranch = kernel::single('ome_branch_type')->getOwnBranchIds();

        $sub_menu = $this->getView($status);
        $i = 0;
        $mdl_order = $this->app->model('delivery');
        foreach ($sub_menu as $k => $v) {
            //非管理员取管辖仓与自建仓的交集
            $v['filter']['ext_branch_id'] = $v['filter']['ext_branch_id'] ? array_intersect($v['filter']['ext_branch_id'], $ownerBranch) : $ownerBranch;
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $mdl_order->viewcount($v['filter']);
            $query['view'] = $i++;
            $query['logi_id'] = urlencode($v['filter']['logi_id']);
            $sub_menu[$k]['href'] = 'index.php?' . http_build_query($query);
            unset($v);
        }
        return $sub_menu;
    }

    function getView($status) {
        $oDlycorp = $this->app->model('dly_corp');
        $submenu = $oDlycorp->getList('corp_id,name',array('disabled'=>'false'));

        if (empty($submenu))
            return $submenu;

        $tmp_filter = array('type' => 'normal');
        $s_filter = $this->analyseStatus($status);
        $tmp_filter = array_merge($tmp_filter, $s_filter);

        //获取操作员管辖仓库
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $tmp_filter['ext_branch_id'] = $_POST['branch_id'] ? $_POST['branch_id'] : $branch_ids;
            } else {
                $tmp_filter['ext_branch_id'] = 'false';
            }
        }

        $c = 1;
        $sub_menu[0] = array(
            'label' => app::get('base')->_($tmp_filter['_title_']),
            'filter' => $tmp_filter,
            'optional' => false
        );
        #第三方发货时，显示已发货、未发货
        if($_GET['ctl'] == 'admin_receipts_outer') {
            $c = 3;#物流公司view在未发货之后
            $outer_filter = $tmp_filter;
            #让已发货、未发货显示在全部之后
            $outer = $this->shipStatus();
            foreach($outer as $key=>$v){
                if($key == 'succ'){
                    $outer_filter['status'] = array('succ');
                }else{
                    #所有不属于发货成功的，都是未发货
                    $outer_filter['status'] = array('ready','progress');
                    //unset($tmp_filter['status']);
                }
                $sub_menu[] = array(
                        'label' => app::get('base')->_($v),
                        'filter' => $outer_filter,
                        'optional' => false
                );
            }
        }
        foreach ($submenu as $keys => $values) {
            $sub_menu[$c] = array(
                'label' => app::get('base')->_($values['name']),
                'filter' => array_merge($tmp_filter, array('logi_id' => $values['corp_id'])),
                'optional' => false
            );
            $c++;
        }
        return $sub_menu;
    }

    public function notice()
    {
        $this->page('admin/receipts/notice.html');
    }

    function index() {
        if($_GET['status']=='' || $_GET['status']==5){
            $this->dlyCorp_tab = 'hidden';
        }
        # 操作员ID号
        $op_id  = $this->user->get_id();
        $sku = kernel::single('base_component_request')->get_get('sku');

        $cfgr = $this->app->getConf('ome.delivery.cfg.radio');

        if(empty($cfgr) && $_GET['status'] == 0) $cfgr = 1;
        if ($cfgr == 2 && isset($sku) && $sku == '') {

            $jumpto = $this->app->base_url(1).'index.php?app=ome&ctl=admin_receipts_print&act=index&status=0&sku=single';
            $this->pagedata['msg'] = '对不起！由于您设置了按品类打印，请去单品，多品打印！将单品和多品分开打印有助于提升效率！';
            //$this->pagedata['msg'] = "对不起！由于您设置了按品类打印，请去单品，多品列打印！<script>setTimeout(function(){window.location.href='{$jumpto}';},3000);</script>";
            $this->pagedata['jumpto'] = $jumpto;
            $this->pagedata['wait'] = 3;
            $this->display('splash/notice.html','desktop');
            exit;
        }

        if ($cfgr == 1 && isset($sku) && in_array($sku,array('single','multi'))) {
            $jumpto = $jumpto = $this->app->base_url(1).'index.php?app=ome&ctl=admin_receipts_print&act=index&status=0&sku=';
            $this->pagedata['msg'] = '对不起！由于您设置了经典打印，请去待打印打印！';
            //$this->pagedata['msg'] = "对不起！由于您设置了简单打印，请去待打印列打印！<script>setTimeout(function(){window.location.href='{$jumpto}';},3000);</script>";

            $this->pagedata['jumpto'] = $jumpto;
            $this->pagedata['wait'] = 3;
            $this->display('splash/notice.html','desktop');
            exit;
        }

        # 发货配置
        $deliCfgLib = kernel::single('ome_delivery_cfg');

        $title = '';
        if (isset($_POST['delivery_bn']) && $_POST['delivery_bn']) {
            $deliveryObj = $this->app->model('delivery');
            $rows = $deliveryObj->getParentIdBybn($_POST['delivery_bn']);
            if ($rows) {
                foreach ($rows as $val) {
                    $deliveryId[] = $val['parent_id'];
                }
                $filter['extend_delivery_id'] = $deliveryId;
            }
        }
        $filter['type'] = 'normal';

        //分析status的filter条件
        $tmp_filter = $this->analyseStatus($_GET['status']);
        $filter = array_merge($filter, $tmp_filter);

        //所有自建仓
        $ownerBranch = array();
        $ownerBranch = kernel::single('ome_branch_type')->getOwnBranchIds();

        //获取操作员管辖仓库
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
			$oBranch = app::get('ome')->model('branch');
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $filter['ext_branch_id'] = $_POST['branch_id'] ? $_POST['branch_id'] : $branch_ids;
                $filter['ext_branch_id'] = array_intersect($filter['ext_branch_id'], $ownerBranch); //取管辖仓与自建仓的交集
            } else {
                $filter['ext_branch_id'] = 'false';
            }
        } else {
            $filter['ext_branch_id'] = $ownerBranch;
        }

        $attach = '&status=' . $_GET['status'] . '&logi_id=' . $_GET['logi_id'];
        if(isset($sku)) $attach .= '&sku='.$sku;

        $params = array(
            'title' => $filter['_title_'],
            'actions' => array(
                'stock' => array(
                    'label' => '打印备货单',
                    'submit' => 'index.php?app=ome&ctl=admin_receipts_print&act=toPrintStock' . $attach,
                    'target' => "_blank",
                ),
                'delie' => array(
                    'label' => '打印发货单',
                    'submit' => 'index.php?app=ome&ctl=admin_receipts_print&act=toPrintMerge' . $attach,
                    'target' => '_blank',
                ),
                'merge' => array(
                    'label' => '联合打印',
                    'submit' => 'index.php?app=ome&ctl=admin_receipts_print&act=toMergePrint' . $attach,
                    'target' => '_blank',
                ),
                'expre' => array(
                    'label' => '打印快递单',
                    'submit' => 'index.php?app=ome&ctl=admin_receipts_print&act=toPrintShip' . $attach,
                    'target' => '_blank', //"dialog::{width:800,height:600,title:'设置标签'}",//
                ),
            ),
            'base_filter' => $filter,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'max_actions' => 8,
            'use_view_tab' => true,
            'finder_aliasname' => 'delivery_print' . $op_id,
            //从载方法 以解决 发货中未录入快递单号不能过滤的bug
            'object_method' => array('count' => 'count_logi_no', 'getlist' => 'getlist_logi_no'),
        );

        //发货模板配置
        $delivery_cfg = $this->app->getConf('ome.delivery.status.cfg');
        //发货单控件风格
        if (isset($delivery_cfg['set']['ome_delivery_print_mode']) && $delivery_cfg['set']['ome_delivery_print_mode'] == 1) {
            $params['actions']['delie']['submit'] = 'index.php?app=ome&ctl=admin_receipts_print&act=toPrintMergeNew' . $attach;
        }

        //备货控件风格
        if (isset($delivery_cfg['set']['ome_stock_print_mode']) && $delivery_cfg['set']['ome_stock_print_mode'] == 1) {
            $params['actions']['stock']['submit'] = 'index.php?app=ome&ctl=admin_receipts_print&act=toPrintStockNew' . $attach;
        }
        if (app::get('logisticsmanager')->is_installed()) {
            $params['actions']['newexpre'] = array(
                'label' => '打印快递单',
                'submit' => 'index.php?app=ome&ctl=admin_receipts_print&act=toPrintExpre' . $attach,
                'target' => '_blank',
            );
            unset($params['actions']['expre']);
        }

        //判断未打印的列表页可以设置列表分页到500
        if($_GET['status'] == 0){
            $this->max_plimit = 200;
            $params['plimit_in_sel'] = array(200,100,50,20,10);

            if(app::get('ome')->getConf('delivery.bycreatetime'.$op_id) == 1){
                $label = '按默认排序显示';
                $order_val = 0;
            }else{
                $label = '按成单时间显示';
                $order_val = 1;
            }
            $params['actions']['orderbycreatetime'] = array('label'=>$label,'href'=>'index.php?app=ome&ctl=admin_receipts_print&act=orderbycreatetime&p[0]='.(isset($sku) ? $sku : 'page_all').'&p[1]='.$order_val.'&p[2]='.$op_id);
        }

        //选择显示打印的按钮
        $showStockBtn = $deliCfgLib->analyse_btn_status('stock',$sku);
        if ($showStockBtn == false) {
            unset($params['actions']['stock']);
        }
        $showDelieBtn = $deliCfgLib->analyse_btn_status('delie',$sku);
        if ($showDelieBtn == false) {
            unset($params['actions']['delie']);
        }
        $showMergeBtn = $deliCfgLib->analyse_btn_status('merge',$sku);
        if ($showMergeBtn == false) {
            unset($params['actions']['merge']);
        }

        //暂停列表不显示按钮
        if ($_GET['status'] == 6 ||$_GET['status'] == 7) {
            unset($params['actions']['stock']);
            unset($params['actions']['delie']);
            unset($params['actions']['style']);
            unset($params['actions']['expre']);
            unset($params['actions']['merge']);
        }

        # 如果是第一个TAB 弹出对话框
        if ($_GET['view'] == 0 || empty($_GET['view'])) {
            foreach ($params['actions'] as $key => $act) {
                $act['confirm'] = "我们强烈建议打印任务都在进入各物流公司的分页夹后进行，在全部里只进行查找等操作及显示结果的处理，以避免出现不该发生的错误。你还确定要进行打印操作吗？\n\n注意：操作前请确认打印机中的面单和要打印的单据相匹配。";
                $params['actions'][$key] = $act;
            }
        }

        # 在列表上方添加搜索
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('delivery_finder_top');
            $panel->setTmpl('admin/finder/finder_panel_filter.html');
            $panel->show('ome_mdl_delivery', $params);
        }

        # 多打印模板--独立按钮
        if ($_GET['status'] != 6) {
            $otmplModel = $this->app->model('print_otmpl');
            $filter = array('disabled'=>'false','aloneBtn'=>'true','open'=>'true','type'=>array('delivery','stock'));
            $aloneBtnList = $otmplModel->getList('id,btnName,type',$filter);

            $typeAct = array('delivery'=>'toPrintMerge','stock'=>'toPrintStock');
            foreach ($aloneBtnList as $key=>$value) {
                $params['actions']['aloneBtn'.$key] = array(
                    'label' => $value['btnName'],
                    'submit' => 'index.php?app=ome&ctl=admin_receipts_print&act='. $typeAct[$value['type']] . $attach.'&otmplId='.$value['id'],
                    'target' => '_blank',
                );
            }
        }

        # 批量更换物流按钮
        $params['actions']['changeDly'] = array(
            'label' => '批量更换物流',
            'submit' => 'index.php?app=ome&ctl=admin_receipts_print&act=toChangeDly' . $attach,
            'target' => 'dialog::{title:\'批量更换物流公司\',width:680,height:500}',
        );
        #已签收的，不能再更换物流
        if ($_GET['status'] == 7) {
            unset($params['actions']['changeDly']);
            unset($params['actions']['newexpre']);
        }
        $this->finder('ome_mdl_delivery', $params);
    }

    /*
     * 分析状态
     */

    function analyseStatus($status, $type = 'normal') {
        $sku = kernel::single('base_component_request')->get_get('sku');
        $deliCfgLib = kernel::single('ome_delivery_cfg');
        if ($type == 'normal') {
            switch ($status) {
                case '':
                    $title = '全部';
                    $filter = array();
                    $filter['pause'] = "FALSE";
                    break;
                case 0:
                    if ($sku == 'single') {
                        $title = '单品打印';
                    }elseif ($sku == 'multi') {
                        $title = '多品打印';
                    }else{
                        $title = '待打印';
                        #$filter['deli_cfg|notin'] = array('single','multi');
                    }

                    $btncombi = $deliCfgLib->btnCombi($sku);
                    switch ($btncombi) {
                        case '1_1':
                            $filter['todo'] = "1";
                            $filter['pause'] = "FALSE";
                            $filter['verify'] = 'FALSE';
                            $filter['process'] = "FALSE";
                            break;
                        case '1_0':
                            $filter['todo'] = "2";
                            $filter['pause'] = "FALSE";
                            $filter['verify'] = 'FALSE';
                            $filter['process'] = "FALSE";
                            break;
                        case '0_1':
                            $filter['todo'] = "3";
                            $filter['pause'] = "FALSE";
                            $filter['verify'] = 'FALSE';
                            $filter['process'] = "FALSE";
                            break;
                        case '0_0':
                            $filter['todo'] = "4";
                            $filter['pause'] = "FALSE";
                            $filter['verify'] = 'FALSE';
                            $filter['process'] = "FALSE";
                            break;
                    }
                    break;
                case 1:
                    $title = '已打印';
                    $filter = array(
                        'expre_status' => 'TRUE',
                        'pause' => 'FALSE',
                        'verify' => 'FALSE',
                        'process' => 'FALSE',
                    );
                    $btncombi_single = $deliCfgLib->btnCombi('single');
                    $btncombi_multi = $deliCfgLib->btnCombi('multi');
                    $btncombi_basic = $deliCfgLib->btnCombi();
                    $filter['print_finish'] = array(
                        ''=> $btncombi_basic,
                        'single' => $btncombi_single,
                        'multi' => $btncombi_multi,
                    );
                    break;
                case 2:
                    $title = '未录入物流单号';
                    $filter['no_logi_no'] = 'NULL';
                    $filter['pause'] = "FALSE";
                    break;
                case 3:
                    $title = '已校验';
                    $filter['verify'] = 'TRUE';
                    $filter['process'] = "FALSE";
                    $filter['pause'] = "FALSE";
                    break;
                case 4:
                    $title = '未发货';
                    $filter['process'] = "FALSE";
                    $filter['pause'] = "FALSE";
                    break;
                case 5:
                    $title = '已发货';
                    $filter['process'] = "TRUE";
                    $filter['pause'] = "FALSE";
                    break;
                case 6:
                    $title = '暂停列表';
                    $filter['pause'] = "TRUE";
                    break;
                case 7:
                    $title = '已签收';
                    $filter['process'] = "TRUE";
                    $filter['pause'] = "FALSE";
                    $filter['is_received'] = "1";
                    break;
            }
        } elseif ($type == 'refunded') {
            switch ($status) {
                case '':
                    $title = '未发货';
                    $filter['process'] = "FALSE";
                    $filter['pause'] = "FALSE";
                    break;
                case 1:
                    $title = '未发货';
                    $filter['process'] = "FALSE";
                    $filter['pause'] = "FALSE";
                    break;
                case 2:
                    $title = '已发货';
                    $filter['process'] = "TRUE";
                    $filter['pause'] = "FALSE";
                    break;
            }
        }
        // 打印类型
        if ($sku) {
            if ($sku == 'single') {
                $filter['skuNum'] = 1;
            }elseif ($sku == 'multi') {
                $filter['skuNum|than'] = 1;
            }
        }

        //默认条件
        $filter['parent_id'] = 0;
        $filter['disabled'] = 'false';
        $filter['status'] = array('ready', 'progress', 'succ');

        $schema = $this->app->model('delivery')->schema;
        if(isset($_POST['status']) && $schema['columns']['status']['type'][$_POST['status']]){
            $filter['status'] = $_POST['status'];
        }

        $filter['_title_'] = $title;

        return $filter;
    }

    function processFilter() {
        //来源于
        if ($_GET['from'] && $_GET['from'] == 'refunded') {
            //原样寄回
            $filter['type'] = 'reject';
            //TODO 可能通过getView()方法实现 （待升到1.3 moontools）
            $tmp = $this->analyseStatus($_GET['status'], 'refunded'); //判断为哪种列表
            $filter = array_merge($filter, $tmp);
        } else {
            //正常发货
            $filter['type'] = 'normal';
            //TODO 可能通过getView()方法实现 （待升到1.3 moontools）
            $tmp = $this->analyseStatus($_GET['status']); //判断为哪种列表
            $filter = array_merge($filter, $tmp);
        }
        //解析 view条件
        if ($_GET['logi_id']) {
            $filter['logi_id'] = urldecode($_GET['logi_id']);
        }
        /*
         * 可扩展条件
         */

        return $filter;
    }

    /*
     * 处理发货单ID
     */

    function processDeliveryId() {
        $delivery_ids = $_REQUEST['delivery_id'];
        $isSelectAll = $_REQUEST['isSelectedAll'];
        $filter = $this->processFilter();

        $printShip = false;
        $logi = array();
        if ($_GET['act'] == 'toPrintShip' || $_GET['act'] == 'toPrintExpre') {
            $printShip = true;
        }
        $dlyObj = $this->app->model('delivery');
        $filter_sql = null;
        #待打印,为避免重复打印，在后台，把相关打印字段加入到过滤条件中
        if(($_GET['status']==='0') && ($_GET['sku'] == '')){
            $filter_sql =  $this->getfiltersql();
        }
        if ($isSelectAll == '_ALL_') {
            if($filter_sql){
                $filter['filter_sql'] = $filter_sql;
            }
            //所有数据
            $ids = $dlyObj->getList('delivery_id,logi_id,branch_id', $filter, 0, -1);
            $dly_ids = array();
            $branch = array();
            if ($ids) {
                foreach ($ids as $id) {
                    if ($printShip)
                        $logi[$id['logi_id']]++;
                    //$this->checkOrderStatus($id['delivery_id']);
                    $dly_ids[] = $id['delivery_id'];
                    $branch[$id['branch_id']] = $id['delivery_id'];
                }
                if (count($logi) > 1)
                    $this->headerErrorMsgDisply("当前系统不支持同时打印两种不同快递类型的单据，请重新选择后再试。");
                if (count($branch) > 1)
                    $this->headerErrorMsgDisply("当前系统不支持同时打印两个仓库的单据，请重新选择后再试。");
                return $dly_ids;
            }
            exit("无数据");
        }else {
            $delivery_ids = array_filter($delivery_ids); //去除值 为空，null，FALSE的key和value
            //选择的数据
            if ($delivery_ids) {
                if (is_array($delivery_ids)) {
                    $filter_['delivery_id'] = $delivery_ids;
                    if($filter_sql){
                        $filter_['filter_sql'] = $filter_sql;
                    }
                    $ids = $dlyObj->getList('delivery_id,logi_id,branch_id', $filter_, 0, -1);
                    $dly_ids = array();
                    $branch = array();
                    if ($ids) {
                        foreach ($ids as $id) {
                            if ($printShip)
                                $logi[$id['logi_id']]++;
                            //$this->checkOrderStatus($id['delivery_id']);
                            //$dly_ids[] = $id['delivery_id'];
                            $branch[$id['branch_id']] = $id['delivery_id'];
                        }
                        if (count($logi) > 1)
                            $this->headerErrorMsgDisply("当前系统不支持同时打印两种不同快递类型的单据，请重新选择后再试。");
                        if (count($branch) > 1)
                            $this->headerErrorMsgDisply("当前系统不支持同时打印两个仓库的单据，请重新选择后再试。");
                        return $delivery_ids;
                    }
                    $this->headerErrorMsgDisply("无数据");
                }else {
                    $this->checkOrderStatus($delivery_ids);
                    return array($delivery_ids);
                }
            } else {
                $this->headerErrorMsgDisply("请选择数据");
            }
        }
    }
    #为避免重复打印，在后台，把打印相关字段加入到过滤条件中
    function getfiltersql(){
        $deliCfgLib = kernel::single('ome_delivery_cfg');
        $btncombi = $deliCfgLib->btnCombi($_GET['sku']);
        #根据发货配置，只筛选未打印的
        switch ($btncombi) {
            case '1_1':
                $filter_sql= "(stock_status='false' or deliv_status='false' or expre_status='false')";
                break;
            case '1_0':
                $filter_sql= "(stock_status='false' or expre_status='false')";
                break;
            case '0_1':
                $filter_sql= "(deliv_status='false' or expre_status='false')";
                break;
            case '0_0':
                $filter_sql= "(expre_status='false')";
                break;
        }
        return $filter_sql;
    }

    /**
     * 显示信息
     */
    public function headerErrorMsgDisply($msg) {
        header("Content-type: text/html; charset=utf-8");
        exit($msg);
    }
    /*
     * 联合打印
     */
    function toMergePrint() {
        $_err = 'false';
        //单品、多品标识
        $sku = kernel::single('base_component_request')->get_get('sku');
        $sku = $sku ? $sku : '';

        $now_print_type = 'merge';

        //获取当前待打印的发货单过滤条件
        $filter_condition = $this->getPreparePrintIds();

        $PrintLib = kernel::single('ome_delivery_print');
        $print_data = $PrintLib->getPrintDatas($filter_condition,$now_print_type,$sku,true,$msg);
        if(isset($msg['error_msg']) && $msg['error_msg']){
            $this->headerErrorMsgDisply($msg['error_msg']);
        }

        if(isset($msg['warn_msg']) && $msg['warn_msg']){
            $this->message($msg['warn_msg']);
            exit;
        }

        $PrintMergeLib = kernel::single('ome_delivery_print_merge');
        $format_data = $PrintMergeLib->format($print_data, $sku,$_err);
        $this->pagedata = $format_data;

        //发货单拼接
        $this->pagedata['vid'] = implode(',', $print_data['ids']);
        //是否存在错误信息
        $this->pagedata['err'] = $_err;
        $this->pagedata['errBns'] = $print_data['errBns'];
        $this->pagedata['errIds'] = $print_data['errIds'];
        $this->pagedata['errInfo'] = $print_data['errInfo'];
        $this->pagedata['idents'] = $print_data['identInfo']['items'];
        $this->pagedata['ident'] = join(',', $print_data['identInfo']['idents']);
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['time'] = date("Y-m-d H:i:s");
        $this->pagedata['title'] = '联合打印单打印';
        $this->pagedata['sku'] = $sku;

        kernel::single('ome_print_otmpl')->printOTmpl($_GET['otmplId'],$now_print_type,$this);
    }

    /**
     * 打印发货单
     *
     */
    function toPrintMerge() {
        $_err = 'false';

        # 多品单品标识
        $sku = kernel::single('base_component_request')->get_get('sku');
        $sku = $sku ? $sku : '';

        $now_print_type = 'delivery';

        //获取当前待打印的发货单过滤条件
        $filter_condition = $this->getPreparePrintIds();

        $PrintLib = kernel::single('ome_delivery_print');
        $print_data = $PrintLib->getPrintDatas($filter_condition,$now_print_type,$sku,true,$msg);
        if(isset($msg['error_msg']) && $msg['error_msg']){
            $this->headerErrorMsgDisply($msg['error_msg']);
        }

        if(isset($msg['warn_msg']) && $msg['warn_msg']){
            $this->message($msg['warn_msg']);
            exit;        }

        $PrintDlyLib = kernel::single('ome_delivery_print_delivery');
        $format_data = $PrintDlyLib->format($print_data, $sku,$_err);
        $this->pagedata = $format_data;

        $this->pagedata['errBns'] = $print_data['errBns'];
        $this->pagedata['sku'] = $sku;
        $this->pagedata['err'] = $_err;
        $this->pagedata['idents'] = $print_data['identInfo']['items'];
        $this->pagedata['ident'] = join(',', $print_data['identInfo']['idents']);
        $this->pagedata['errIds'] = $print_data['errIds'];
        $this->pagedata['errInfo'] = $print_data['errInfo'];
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['time'] = date("Y-m-d H:i:s");
        $this->pagedata['title'] = '发货单打印';  

        //改用新打印模板机制 chenping
        kernel::single('ome_print_otmpl')->printOTmpl($_GET['otmplId'],$now_print_type,$this);
    }

    /**
     * 新版打印发货单
     *
     */
    function toPrintMergeNew() {
        $_err = 'false';
        
        //多品单品标识
        $sku = kernel::single('base_component_request')->get_get('sku');
        $sku = $sku ? $sku : '';

        $now_print_type = 'delivery';
        $now_print_mode = 'new';

        //获取当前待打印的发货单过滤条件
        $filter_condition = $this->getPreparePrintIds();

        $PrintLib = kernel::single('ome_delivery_print');
        $print_data = $PrintLib->getPrintDatas($filter_condition,$now_print_type,$sku,true,$msg);
        if(isset($msg['error_msg']) && $msg['error_msg']){
            $this->headerErrorMsgDisply($msg['error_msg']);
        }

        if(isset($msg['warn_msg']) && $msg['warn_msg']){
            $this->message($msg['warn_msg']);
            exit;
        }
        
        $PrintDlyLib = kernel::single('ome_delivery_print_delivery');
        $format_data = $PrintDlyLib->format($print_data, $sku,$_err,$now_print_mode);
        $this->pagedata = $format_data;

        $jsondata = kernel::single('ome_delivery_print_delivery')->arrayToJson($this->pagedata['items'], $print_data['identInfo']['items']);

        $this->pagedata['errBns'] = $print_data['errBns'];
        $this->pagedata['sku'] = $sku;
        $this->pagedata['err'] = $_err;
        $this->pagedata['idents'] = $print_data['identInfo']['items'];
        $this->pagedata['ident'] = join(',', $print_data['identInfo']['idents']);
        $this->pagedata['errIds'] = $print_data['errIds'];
        $this->pagedata['errInfo'] = $print_data['errInfo'];
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['time'] = date("Y-m-d H:i:s");
        $this->pagedata['title'] = '发货单打印';  
        //打印数量
        $this->pagedata['count'] = sizeof($print_data['ids']);
        //随机数
        $this->pagedata['uniqid'] = uniqid();
        //组织控件打印数据
        $this->pagedata['jsondata'] = $jsondata;
        $this->pagedata['totalPage'] = count($this->pagedata['items']);

        ome_print_controltmpl::instance($now_print_type, $this)->printOTmpl($_GET['otmplId']);
    }

    /**
     * 新版发货单
     */
    function toPrintMergeNew_back()
    {
        $basicMaterialLib    = kernel::single('material_basic_material');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');#基础物料条形码
        
        $_err = 'false';

        //备注显示方式
        $markShowMethod = app::get('ome')->getConf('ome.order.mark');

        # 多品单品标识
        $sku = kernel::single('base_component_request')->get_get('sku');
        $sku = $sku ? $sku : '';
        $ids = $this->processDeliveryId();
        # 打印排序
        $dlyObj = $this->app->model('delivery');
        $ids = $dlyObj->printOrderByByIds($ids);

        //批次号 by sy
        $idents = $this->_getPrintQueue($ids);
        $idsAll = $this->parsePrintIds($ids);
        $ids = $idsAll['ids'];

        # 给发货单加上相应配置项
        if($ids) {
            $this->updateDeliCfg($ids,$sku);
        }

        //初始化物流信息
        $logi_name = '';
        $allItems = array();

        if ($ids) {
            $orderObj = $this->app->model('orders');
            $orderExtendObj = app::get('ome')->model('order_extend');
            
            $oGoods = $this->app->model('goods');
            
            $libBranchProductPos    = kernel::single('ome_branch_product_pos');
            
            $didObj = $this->app->model('delivery_items_detail');
            $orderObjectObj = $this->app->model('order_objects');
            $dlyorderObj = app::get('ome')->model('delivery_order');
            
            //获取仓库名称
            $branchObj = $this->app->model('branch');
            $branch_list = array();
            foreach ($ids as $id) {
                $data = $dlyObj->dump($id, '*', array('delivery_items' => array('*'), 'delivery_order' => array('*')));
                
                $branch= $branchObj->dump(array('branch_id'=>$data['branch_id']),'name');
                $branch_list[$data['branch_id']] = $branch['name'];
                if ($data['parent_id'] != 0) {
                    $_err = 'true'; continue;
                }

                $dly_order = $dlyorderObj->getlist('*',array('delivery_id'=>$id),0,-1);

                $pmt_orders = $dlyObj->getPmt_price($dly_order);
                $sale_orders = $dlyObj->getsale_price($dly_order);
                //订单数量
                $data['order_number'] = count($dly_order);
                if ($data) {
                    # 批次号
                    $allItems[$data['delivery_id']] = $data;
                    $shop = $dlyObj->getShopInfo($data['shop_id']);
                    $data['shop_name'] = $shop['name'];
                    #新增收货人信息
                    $data['sender_name'] = $shop['default_sender'];
                    $data['sender_mobile'] = $shop['mobile'];
                    $data['sender_tel'] = $shop['tel'];
                    $data['sender_addr'] = $shop['addr'];
                    $sender_area = $shop['area'];
                    kernel::single('ome_func')->split_area($sender_area);
                    $data['sender_area'] = implode('-',$sender_area);

                    $pmt_order_total=0;
                    $objIds = array();

                    foreach ($data['delivery_items'] as $k => $item) {
                        $didArrs = $didObj->dump(array('delivery_id' => $item['delivery_id'], 'delivery_item_id' => $item['item_id']),'item_type,order_obj_id,order_item_id');

                        $orders = $orderObj->db->selectrow('select o.shop_type,o.order_source,o.shop_id from sdb_ome_orders o left join sdb_ome_order_objects oo on o.order_id = oo.order_id where oo.obj_id = '.$didArrs['order_obj_id']);
                        
                        $p    = $basicMaterialLib->getBasicMaterialBybn($item['bn']);
                        
                        #查询关联的条形码
                        $p['barcode']    = $basicMaterialBarcode->getBarcodeById($p['bm_id']);
                        
                        #基础物料_无goods
                        //$p['goods_id']    = $p['bm_id'];
                        //$goods = $oGoods->dump($p['goods_id'],'bn,type_id,brand_id,picurl');
                        
                        #区分货品
                        $data['delivery_items'][$k]['bncode'] = md5($orders['shop_id'].trim($item['bn']));
                        $store_position = $libBranchProductPos->get_product_pos($item['product_id'], $data['branch_id']);
                        #start新增PKG类型展示
                        $data['delivery_items'][$k]['item_type'] = $didArrs['item_type'];
                        $data['delivery_items'][$k]['order_obj_id'] = $didArrs['order_obj_id'];
                        $data['delivery_items'][$k]['order_item_id'] = $didArrs['order_item_id'];
                        #end
                        if ($p['specifications']) {
                            $picurl = $p['picurl'];
                        } else {
                            $picurl = '';
                        }
                        $data['delivery_items'][$k]['picurl'] = $picurl;
                        $data['delivery_items'][$k]['spec_info'] = $p['specifications'];
                        $data['delivery_items'][$k]['name'] = $p['material_name'];
                        $data['delivery_items'][$k]['store_position'] = $store_position;
                        $data['delivery_items'][$k]['addon'] = $p['specifications'];
                        $data['delivery_items'][$k]['pmt_price'] = $pmt_orders[$item['bn']]['pmt_price'];
                        
                        $data['delivery_items'][$k]['barcode'] = $p['barcode']; //商品条形码
                        $data['delivery_items'][$k]['goods_bn'] = '';//商品编码
                        $data['delivery_items'][$k]['product_weight'] = $p['weight'];//商品重量
                        $data['delivery_items'][$k]['unit'] = $p['unit'];//商品单位
                        
                        //品牌
                        $brand = $this->app->model('brand')->dump(array('brand_id'=>$p['brand_id']),'brand_name');
                        
                        //物料类型
                        $goods_type = $this->app->model('goods_type')->getlist('name',array('type_id'=>$p['cat_id']),0,1);

                        $data['delivery_items'][$k]['brand_name'] = $brand['brand_name'];//品牌
                        $data['delivery_items'][$k]['type_name'] = $goods_type[0]['name'];//类型
                        /*
                        $didArr = $didObj->getList('amount,order_obj_id',array('delivery_id' => $item['delivery_id'], 'delivery_item_id' => $item['item_id']));
                        foreach ($didArr as $key=>$didVal) {
                            $data['order_cost_item'] += $didVal['amount'];
                            $objIds[$didArr['order_obj_id']] = $didVal['order_obj_id'];
                        }
                        */

                        // 如果items_detail表为空，从products取单价,2011.10.21
                        // ome_order_items还有另外两个价格字段：cost,amount，这里取price
                        // 订单总金额：order_total_amount
                        
                        if($orders['order_source'] == 'tbdx' && $orders['shop_type'] == 'taobao'){
                            $tbitemObj = $this->app->model('tbfx_order_items');

                            $tbfx_filter = array('obj_id'=>$didArrs['order_obj_id'],'item_id'=>$didArrs['order_item_id']);
                            
                            $ext_item_info = $tbitemObj->getOrderByOrderId($tbfx_filter);
                            $data['delivery_items'][$k]['price'] = round(($ext_item_info[0]['buyer_payment']/$item['number']),2);
                            $data['delivery_items'][$k]['amount'] = $ext_item_info[0]['buyer_payment'];
                            $data['delivery_items'][$k]['sale_price'] = $ext_item_info[0]['buyer_payment'];                          
                        }else{
                            $sql = 'SELECT a.price,a.amount,a.name,a.bn
                            FROM sdb_ome_order_items AS a
                            LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id
                            LEFT JOIN sdb_ome_delivery_order AS c ON b.order_id=c.order_id
                            WHERE a.product_id=' . $item['product_id'] . ' AND c.delivery_id=' . $item['delivery_id'] . '
                            LIMIT 1';
                            $ext_item_info = kernel::database()->select($sql);
                            $data['delivery_items'][$k]['price'] = $ext_item_info[0]['price'];
                            $data['delivery_items'][$k]['amount'] = $ext_item_info[0]['amount'];
                            $data['delivery_items'][$k]['sale_price'] = ($sale_orders[$item['bn']]*$item['number']);
                        }
                    }
                    /*
                    $orderObjects = $orderObjectObj->getList('*',array('obj_id'=>$objIds));
                    foreach ($orderObjects as $object) {
                        if($object['obj_type'] == 'pkg'){
                            $data['order_cost_item'] += $object['amount'];
                        }
                    }
                    */

                    $tmp = $this->app->model('members')->dump($data['member_id'], 'uname,name,mobile,tel');
                    $data['member_name'] = $tmp['account']['uname'];
                    $t_tel = array();
                    if ($tmp['contact']['phone']['telephone']) {
                        $t_tel[] = $tmp['contact']['phone']['telephone'];
                    }
                    if ($tmp['contact']['phone']['mobile']) {
                        $t_tel[] = $tmp['contact']['phone']['mobile'];
                    }
                    $order_bn = array();
                    $shop_type = array();
                    $order_source = array();
                    if ($data['delivery_order'])
                        $total_receivable = 0;
                        foreach ($data['delivery_order'] as $v) {
                            $order = $orderObj->dump($v['order_id'], 'shop_type,order_source,order_bn,custom_mark,mark_text,cost_freight,pmt_order,tax_no,tax_company,cost_item,is_cod,paytime');
                            if( $order['order_source'] == 'tbdx' && $order['shop_type'] == 'taobao' ){
                                $tbitemObj = $this->app->model('tbfx_order_items');
                                $cost_item = $tbitemObj->getCostitemByOrderId($v['order_id']);
                                $data['order_cost_item'] += $cost_item[0]['cost_items'];
                            }else{
                                $data['order_cost_item'] += $order['cost_item'];
                            }

                            $pmt_order_total+=$order['pmt_order'];
                            //不是合并发货单取订单的前端物流费用
                            //if (count($data['delivery_order']) == 1) {
                                // 运费累加
                                $data['front_cost_freight'] += $order['shipping']['cost_shipping'];
                            //}

                            if ($order['custom_mark']) {
                                $mark = unserialize($order['custom_mark']);
                                if (is_array($mark) || !empty($mark)){
                                    if($markShowMethod == 'all'){
                                        foreach ($mark as $im) {
                                            $data['_mark'][$order['order_bn']][] = $im;
                                        }
                                    }else{
                                        $data['_mark'][$order['order_bn']][] = array_pop($mark);
                                    }
                                }
                            }

                            if ($order['mark_text']) {
                                $mark = unserialize($order['mark_text']);
                                if (is_array($mark) || !empty($mark)){
                                    if($markShowMethod == 'all'){
                                        foreach ($mark as $im) {
                                            $data['_mark_text'][$order['order_bn']][] = $im;
                                        }
                                    }else{
                                        $data['_mark_text'][$order['order_bn']][] = array_pop($mark);
                                    }
                                }
                            }

                            if($order['tax_no'] || $order['tax_title']){
                                $data['_tax_info'][] = array(
                                    'order_bn'=>$order['order_bn'],
                                    'tax_no'=>$order['tax_no'],
                                    'tax_title'=>$order['tax_title'],
                                );
                            }

                            $order_bn[] = $order['order_bn'];

                            if(!in_array($order['shop_type'],$shop_type)){
                                $shop_type[] = $order['shop_type'];
                            }

                            if(!in_array($order['order_source'],$order_source)){
                                $order_source[] = $order['order_source'];
                            }

                            if($order['shipping']['is_cod'] == 'true'){
                                $extendInfo = array();
                                $extendInfo = $orderExtendObj->dump($v['order_id']);
                                $total_receivable += $extendInfo['receivable'];
                            }
                            if(!empty($order['paytime'])){
                                $data['paytime'] =  date('Y-m-d H:i:s',$order['paytime']);
                            }
                        }
                    $data['total_receivable'] = $total_receivable;
                    $data['order_bn'] = implode(" , ", $order_bn);

                     //有两级价格的发货单不显示价格
                    $show_delivery_price = true;
                    //all:代表一张发货关联多个前端店铺
                    $data['shop_type'] = 'all';
                    $data['order_source'] = 'all';
                    if(count($shop_type) == 1 && count($order_source) == 1){
                        $data['shop_type'] = $shop_type[0];
                        $data['order_source'] = $order_source[0];
                        if($shop_type[0] == 'shopex_b2b'){
                            $arr = array('fxjl','b2c','taofenxiao');
                            if(in_array($order_source[0], $arr)){
                                 $show_delivery_price = false;
                            }
                        }
                    }
                    $data['show_delivery_price'] = $show_delivery_price;

                    if ($t_tel) $data['member_tel'] = implode(" / ", $t_tel);
                    //去除多余的三级区域
                    $reg = preg_quote(trim($data['consignee']['province']));
                    if (!empty($data['consignee']['city'])) {
                        $reg .= '.*?' . preg_quote(trim($data['consignee']['city']));
                    }
                    if (!empty($data['consignee']['district'])) {
                        $reg .= '.*?' . preg_quote(trim($data['consignee']['district']));
                    }

                    $data['consignee']['addr'] = preg_replace('/' . $reg . '/is', '', $data['consignee']['addr']);
                    $data['shopname'] = $data['shop_name'];#因京东会直接替掉，所以新增显示
                    //京东订单添加打印标示
                    if($data['shop_type']=='360buy'){
                        $logo_url = kernel::base_url(1)."/app/ome/statics/360buylogo.png";
                        $data['shop_name']      = '<img src="'.$logo_url.'" width="129" height="25" alt="京东商城">&nbsp;';
                        $data['shop_logo_url']  = $logo_url;
                        $data['shop_logo_html'] = '<img src="'.$logo_url.'" width="129" height="25" alt="京东商城">';
                    }
                    //物流信息
                    if ($logi_name == '') {
                        $logi_name = $data['logi_name'];
                    }
                    $items[] = $data;
                    $this->checkOrderStatus($id);
                } else {
                    $_err = 'true';
                }
            }
        }

        function cmp($a, $b) {
            return strcmp($a["store_position"], $b["store_position"]);
        }

        $deliCfgLib = kernel::single('ome_delivery_cfg');
        $is_print_front = (1 == $deliCfgLib->getValue('ome_delivery_is_printdelivery',$sku)) ? true : false;

        foreach ($items as $k => $item) {
            usort($item['delivery_items'], "cmp");

            if (!$is_print_front) {
                foreach ($item['delivery_items'] as $i => $di) {
                    $item['delivery_items'][$i]['product_name'] = $di['name'];
                }
            }

            $items[$k] = $item;
        }
        
       
        //商品名称和规格取前台,是合并发货单,取第一个订单的货品名称
        if ($ids && $is_print_front) {

            // $arrPrintProductName = $dlyObj->getPrintFrontProductName($ids);
            $arrPrintProductName = $dlyObj->getPrintOrderName($items);
            if (!empty($arrPrintProductName)) {
                $productPos = $dlyObj->getPrintProductPos($items);

                foreach ($items as $k => $rows) {
                    foreach ($rows['delivery_items'] as $k2 => $v) {
                        $rows['delivery_items'][$k2]['name'] = $arrPrintProductName[$rows['delivery_id']][$v['bn']]['name'];
                        $rows['delivery_items'][$k2]['product_name'] = $arrPrintProductName[$rows['delivery_id']][$v['bn']]['name'];
                        $rows['delivery_items'][$k2]['addon'] = $arrPrintProductName[$rows['delivery_id']][$v['bn']]['addon'];
                        $rows['delivery_items'][$k2]['spec_info'] = $arrPrintProductName[$rows['delivery_id']][$v['bn']]['addon'];
                        $rows['delivery_items'][$k2]['store_position'] = $productPos[$v['product_id']];
                        // if (!$v['bncode']){
                        //     $bncode = md5($rows['shop_id'].trim($v['bn']));
                        // }else{
                        //     $bncode = $v['bncode'];
                        // }
                        // $rows['delivery_items'][$k2]['product_name'] = $arrPrintProductName[$bncode]['name'];
                        //$rows['delivery_items'][$k2]['store_position'] = $arrPrintProductName[$bncode]['store_position'];
                        // $rows['delivery_items'][$k2]['name'] = $arrPrintProductName[$bncode]['name'];
                        // $rows['delivery_items'][$k2]['addon'] = $arrPrintProductName[$bncode]['addon'];
                        // $rows['delivery_items'][$k2]['spec_info'] = $arrPrintProductName[$bncode]['addon'];
                    }
                    $items[$k] = $rows;
                }
            }
        }

        if ($ids) $vid = implode(',', $ids);
        $oiObj = $this->app->model('order_items');
        foreach ($items as $k => $rows) {
            $pmt_order_total=0;
            $pmt_order_discount = 0;
            foreach($rows['delivery_order'] as $val){
                $order_data = $orderObj->dump($val['order_id'], 'pmt_order,pmt_goods,discount');
                $pmt_order_total+=$order_data['pmt_order'];
                $pmt_order_total+=$order_data['pmt_goods'];
                $pmt_order_discount+=$order_data['discount'];
            }

            $delivery_total_nums = 0;
            foreach ($rows['delivery_items'] as $v) {
                $delivery_total_nums += $v['number'];
            }

            $rows['delivery_total_nums'] = $delivery_total_nums;
            $rows['pmt_order_total'] = $pmt_order_total;
            //订单折扣金额
            $rows['pmt_order_discount'] = $pmt_order_discount;
             //有两级价格的发货单不显示价格
            if(!$rows['show_delivery_price']){
                 foreach ($rows['delivery_items'] as $kk => $item) {
                    $rows['delivery_items'][$kk]['price'] = '-';
                 }
                $rows['order_total_amount'] = '-';
                $rows['order_cost_item'] = '-';
            }else{
                #add taobao fenxiao
                if($rows['order_source'] == 'tbdx' && $rows['shop_type'] == 'taobao'){
                    $rows['order_total_amount'] = $rows['front_cost_freight'] + $rows['order_cost_item'];
                }else{
                    $rows['order_total_amount'] = $dlyObj->getAllTotalAmountByDelivery($rows['delivery_order']);
                }
            }

            $items[$k] = $rows;
        }
        
        $errDly = $dlyObj->getList('delivery_id,delivery_bn', array('delivery_id' => $idsAll['errIds']));
        foreach($errDly as $val){
            $errBns[$val['delivery_id']] = $val['delivery_bn'];
        }
        $this->pagedata['errBns'] = $errBns;
        #添加打印模式

        $this->pagedata['sku'] = $sku;
        $this->pagedata['err'] = $_err;
        $this->pagedata['vid'] = $vid;
        #获取当前设置打印版本$deliCfgLib
        $print_version = $deliCfgLib->getprintversion();
        #获取当前打印模式
        $print_style = $deliCfgLib->getprintstyle();

        $this->pagedata['print_style'] = $print_style;

        //unset($print_style);
        #打印订单将捆绑商品分类展示
        #根据版本标识

        if($print_version=='1'){
            $order_objectsobj = $this->app->model('order_objects');
            
            foreach($items as $k=>$v){
                if ($print_style == '0') {
                    // 清单模式
                    $items[$k] = $this->format_print_delivery($v);    
                } else {
                    // 拣货模式
                    foreach($v['delivery_items'] as $key=>$val){
                        $order_objects = $order_objectsobj->dump($val['order_obj_id'],'bn,name,price,amount,quantity,pmt_price,sale_price,obj_type');
                        $obj_type = $order_objects['obj_type'];
                        if(!isset($items[$k]['delivery_items'][$obj_type][$val['order_obj_id']])){
                            
                            if($obj_type=='pkg'){
                                $pkg = array();
                                if (!$is_print_front) {
                                    $order_objects['product_name'] = $pkg[0]['name'];
                                }else{
                                    $order_objects['product_name'] = $order_objects['name'];
                                }
                                

                                $order_objects_sum = $this->app->model('delivery_items_detail')->getOrderobjQuantity($val['item_id'],$val['delivery_id'],$order_objects['bn']);
                                $order_objects['quantity'] = $order_objects_sum['quantity'];
                                
                            }
                            $items[$k]['delivery_items'][$obj_type][$val['order_obj_id']] = $order_objects;
                        }
                        $items[$k]['delivery_items'][$obj_type][$val['order_obj_id']]['order_items'][$key]=$val;

                        unset($items[$k]['delivery_items'][$key]);
                    }
                }
                #判断是否开启按货位排序
                if($delivery_cfg['set']['print_order'] == 1){
                    if(is_array($items[$k]['delivery_items']['goods'])){
                        $new_items = array();
                        foreach( $items[$k]['delivery_items']['goods'] as $_bn=>$_v){
                            #记录goods中的货位顺序
                            $store_position_order[$_bn] = trim($_v['order_items'][$_bn]['store_position']);
                        }
                        #对货位进行排序
                        asort($store_position_order);
                        #生成一个经过货位排序后的新数组
                
                        foreach($store_position_order as $bn=>$v){
                            $new_items[$bn] = $items[$k]['delivery_items']['goods'][$bn];
                        }
                        $store_position_order = array();
                        $items[$k]['delivery_items']['goods'] = $new_items;
                    }
              }
              
              #新版本发货单中，新增一层拣货数据
              
              #普通商品
              if($items[$k]['delivery_items']['goods']){
                  foreach($items[$k]['delivery_items']['goods'] as $bn=>$goods){
                      foreach($goods['order_items'] as $goods_val){
                          $pick_mode_data[$goods_val['bn']] = $goods_val;
                          $all_store_position[$goods_val['bn']] = $goods_val['store_position'];
                      }
                  }
              }

              #捆绑商品
              if($items[$k]['delivery_items']['pkg']){
                  foreach($items[$k]['delivery_items']['pkg'] as $pkg){
                      foreach($pkg['order_items'] as $pkg_val){
                          #把捆绑商品和普通商品合并
                          if(array_key_exists($pkg_val['bn'],$pick_mode_data)){
                              $pick_mode_data[$pkg_val['bn']]['number'] += $pkg_val['number'];
                          }else{
                              $pick_mode_data[$pkg_val['bn']] = $pkg_val;
                              $all_store_position[$pkg_val['bn']] = $pkg_val['store_position'];
                          }
                          
                      }
                  }
              }
              #赠品
              if($items[$k]['delivery_items']['gift']){
                  foreach($items[$k]['delivery_items']['gift'] as $gift){
                      foreach($gift['order_items'] as $gift_val){
                          if(array_key_exists($gift_val['bn'],$pick_mode_data)){
                              $pick_mode_data[$gift_val['bn']]['number'] += $gift_val['number'];
                          }else{
                              $pick_mode_data[$gift_val['bn']] = $gift_val;
                              $all_store_position[$gift_val['bn']] = $gift_val['store_position'];
                          }
                      }
                  }
              }
              #giftpackage
              if($items[$k]['delivery_items']['giftpackage']){
                  foreach($items[$k]['delivery_items']['giftpackage'] as $giftpackage){
                      foreach($giftpackage['order_items'] as $giftpackage_val){
                          if(array_key_exists($giftpackage_val['bn'],$pick_mode_data)){
                              $pick_mode_data[$giftpackage_val['bn']]['number'] += $giftpackage_val['number'];
                          }else{
                              $pick_mode_data[$giftpackage_val['bn']] = $giftpackage_val;
                              $all_store_position[$giftpackage_val['bn']] = $giftpackage_val['store_position'];
                          }
                      }
                  }
              }
              #按货位排序
              if($delivery_cfg['set']['print_order']){
                  #货位存在
                  if($all_store_position){
                      asort($all_store_position);
                      foreach($all_store_position as $bn=>$val){
                          $pick_mode_position_data[] = $pick_mode_data[$bn];
                      }
                      $items[$k]['delivery_items']['pick_mode_data'] = $pick_mode_position_data;
                  }else{
                      #货位不存在，则默认按货号排序
                      ksort($pick_mode_data);
                      $items[$k]['delivery_items']['pick_mode_data'] = $pick_mode_data;
                  }
              }else{
                  #按货号排序
                  ksort($pick_mode_data);
                  $items[$k]['delivery_items']['pick_mode_data'] = $pick_mode_data;
              }
              unset($pick_mode_data,$pick_mode_position_data,$all_store_position);         
            }          
        }else{
            #开启按货位排序后,在老版本中增加货位排序功能
            if($delivery_cfg['set']['print_order'] == 1){
                foreach($items as $k1=>$v1){
                    foreach($v1['delivery_items'] as $k2=>$v2){
                        $store_position_order[$k2] = trim($v2['store_position']);
                    }
                    #对货位进行排序
                    asort($store_position_order);
                    #生成一个经过货位排序后的新数组
                    foreach($store_position_order as $_k3=>$_v3){
                        $new_items['delivery_items'][] = $items[$k1]['delivery_items'][$_k3];
                    }
                    $items[$k1]['delivery_items']= $new_items['delivery_items'];
                    $store_position_order = $new_items =  array();
                }
            }
        }

        $this->covertNullToString($items);

        $printData = $this->formatPrintItems($items, $idents['items']);

        $jsondata = '';
        if ($printData) {
            $jsondata = json_encode($printData);

        }

        //批次号
        $this->pagedata['allItems'] = $allItems;
        $this->pagedata['idents'] = $idents['items'];
        $this->pagedata['ident'] = $idents['ident'];
        $this->pagedata['errIds'] = $idsAll['errIds'];
        $this->pagedata['errInfo'] = $idsAll['errInfo'];
        $this->pagedata['branch_list'] = $branch_list;
        unset($branch_list);
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['time'] = date("Y-m-d H:i:s");
        $this->pagedata['title'] = '发货单打印';  
        $this->pagedata['items'] = $items;

        //物流公司
        $this->pagedata['logi_name'] = $logi_name;
        //打印数量
        $this->pagedata['count'] = sizeof($ids);
        //随机数
        $this->pagedata['uniqid'] = uniqid();
        //组织控件打印数据
        $this->pagedata['jsondata'] = $jsondata;
        $this->pagedata['totalPage'] = count($printData);
        ome_print_controltmpl::instance('delivery', $this)->printOTmpl($_GET['otmplId']);
    }
    
    function formatPrintItems($printData, $idents) {
        $nbsp = "　";
        $byText = 'by';
        foreach ($printData as $k1 => $v1) {
            //买家留言
            $buyWord = '';
            //订单备注
            $orderMark = '';
            foreach ($v1 as $k2 => $v2) {
                //发货单ID信息
                if ($k2 == 'delivery_order') {
                    unset($printData[$k1][$k2]);
                }
                //买家留言
                if ($k2 == '_mark') {
                    foreach ($v2 as $kob => $vob) {
                        foreach ($vob as $vob1) {
                            $buyWord .= $vob1['op_content'] . $nbsp . $vob1['op_time'] . $nbsp . $byText . $nbsp . $vob1['op_name'];
                        }
                        $buyWord .= "\r\n";
                    }
                    if ($buyWord) {
                        $printData[$k1]['buyWord'] = rtrim($buyWord, "\r\n");
                    }
                    unset($printData[$k1][$k2]);
                }
                //订单备注
                if ($k2 == '_mark_text') {
                    foreach ($v2 as $kob => $vob) {
                        foreach ($vob as $vob1) {
                            $orderMark .= $vob1['op_content'] . $nbsp . $vob1['op_time'] . $nbsp . $byText . $nbsp . $vob1['op_name'];
                        }
                        $orderMark .= "\r\n";
                    }
                    if ($orderMark) {
                        $printData[$k1]['orderMark'] = rtrim($orderMark, "\r\n");
                    }
                    unset($printData[$k1][$k2]);
                }
                
                if ($k2 == 'consignee') {
                    foreach ($v2 as $k3 => $v3) {
                        $printData[$k1][$k2 . '_' . $k3] = $v3;
                    }
                    unset($printData[$k1][$k2]);
                }
                
                //发货人区域格式
                if ($k2 == 'sender_area') {
                    $area = explode('-', $v2);
                    $printData[$k1]['sender_province'] = $area[0];
                    $printData[$k1]['sender_city'] = $area[1];
                    $printData[$k1]['sender_district'] = $area[2];
                }
                //发货单数据
                if ($k2 == 'delivery_items') {
                    if (isset($v2['goods']) && $v2['goods']) {
                        $deliveryItems = $this->getGoodsDeliveryItems($v2['goods']);
                        $printData[$k1][$k2] = $deliveryItems;
                    }
                }
                //删除项
                if ($k2 == 'pick_mode_data') {
                    unset($printData[$k1][$k2]);
                }
            }
            //合计发货信息
            $printData[$k1]['countDeliveryMsg']['total'] = '累计件数：' . $v1['itemNum'] . $nbsp . $nbsp . '累计品种：' . $v1['skuNum'] .
                                                 $nbsp . $nbsp . '总重量：' . sprintf("%d", $v1['net_weight']);
            $printData[$k1]['countDeliveryMsg']['empty'] = '';
            //当前年月日
            $printData[$k1]['date_y'] = date('Y');
            $printData[$k1]['date_m'] = date('m');
            $printData[$k1]['date_d'] = date('d');
            $printData[$k1]['date_ymd'] = date('Ymd');
            //批次号
            $printData[$k1]['batch_number'] = isset($idents[$v1['delivery_id']]) ? $idents[$v1['delivery_id']] : '';
        }
        return $printData;
    }
    
    /**
     * 获得商品发货数据
     */
    public function getGoodsDeliveryItems($data) {
        $deliveryItems = array();
        foreach ($data as $v) {
            foreach ($v['order_items'] as  $v1) {
                $deliveryItems[] = $v1;
            }
        }
        return $deliveryItems;
    }

    /**
     * 打印新版本数据结构重组
     *
     * @return void
     * @author chenping<chenping@shopex.cn>
     **/
    private function format_print_delivery($delivery)
    {
        $format_delivery_items = array();
        foreach ($delivery['delivery_items'] as $key => $value) {
            $format_delivery_items[$value['item_id']] = $value;
        }

        $format_order_objects = array();
        foreach ($delivery['orders'] as $key => $order) {
            foreach($order['order_objects'] as $ook => $obj){
                $format_order_objects[$obj['obj_id']] = $obj;
            }
        }

        $format_order_items = array();
        foreach ($delivery['orders'] as $key => $order) {
            foreach($order['order_objects'] as $ook => $obj){
                foreach ($obj['order_items'] as $oik => $item) {
                    $item['number'] = $item['nums'];
                    $format_order_items[$item['item_id']] = $item;
                }
            }
        }
        
        //是否打印前端商品名称
        $deliCfgLib = kernel::single('ome_delivery_cfg');
        $is_print_front = (1 == $deliCfgLib->getValue('ome_delivery_is_printdelivery',$sku)) ? true : false;

        $deliItemDetailModel = app::get('ome')->model('delivery_items_detail');
        $deliItemDetailList = $deliItemDetailModel->getList('*',array('delivery_id'=>$delivery['delivery_id']));
        $data = array();
        foreach ($deliItemDetailList as $key => $value) {
            $order_object = $format_order_objects[$value['order_obj_id']];
            $order_item = $format_delivery_items[$value['delivery_item_id']];
            if (!$order_item) { continue; }

            $order_item = array_merge($order_item,$format_order_items[$value['order_item_id']]);

            if (isset($data[$order_object['obj_type']][$order_object['bn']])) {
                $obj_id_list = $data[$order_object['obj_type']][$order_object['bn']]['obj_id_list'];
                if (!in_array($order_object['obj_id'], $obj_id_list)) {
                    $obj_id_list[] = $order_object['obj_id'];
                    $data[$order_object['obj_type']][$order_object['bn']]['obj_id_list'] = $obj_id_list;
                    $data[$order_object['obj_type']][$order_object['bn']]['quantity'] += $order_object['quantity'];
                    $data[$order_object['obj_type']][$order_object['bn']]['amount'] += $order_object['amount'];
                    $data[$order_object['obj_type']][$order_object['bn']]['sale_price'] += $order_object['sale_price'];
                    $data[$order_object['obj_type']][$order_object['bn']]['pmt_price'] += $order_object['pmt_price'];
                }

                if (isset($data[$order_object['obj_type']][$order_object['bn']]['order_items'][$value['bn']])) {
                    $data[$order_object['obj_type']][$order_object['bn']]['order_items'][$value['bn']]['number'] += $order_item['number'];
                    $data[$order_object['obj_type']][$order_object['bn']]['order_items'][$value['bn']]['pmt_price'] += $order_item['pmt_price'];
                    $data[$order_object['obj_type']][$order_object['bn']]['order_items'][$value['bn']]['sale_price'] += $order_item['sale_price'];
                    $data[$order_object['obj_type']][$order_object['bn']]['order_items'][$value['bn']]['amount'] += $order_item['amount'];
                } else {
                    $data[$order_object['obj_type']][$order_object['bn']]['order_items'][$value['bn']] = $order_item;
                }
            } else {
                $order_object['obj_id_list'][] = $order_object['obj_id'];
                $data[$order_object['obj_type']][$order_object['bn']] = $order_object;
                $data[$order_object['obj_type']][$order_object['bn']]['order_items'][$value['bn']] = $order_item;

                if($order_object['obj_type']=='pkg'){
                    $pkg = array();
                    if (!$is_print_front) {
                        $data[$order_object['obj_type']][$order_object['bn']]['product_name'] = $pkg[0]['name'];
                        $data[$order_object['obj_type']][$order_object['bn']]['name'] = $pkg[0]['name'];
                    }else{
                        $data[$order_object['obj_type']][$order_object['bn']]['product_name'] = $order_object['name'];
                    }
                }
            }
        }      
        $delivery['delivery_items'] = $data;

        return $delivery;
    }

    /**
     * 打印快递单
     *
     * 修改 加了一个补打快递单的开关 wujian@shopex.cn 2012年3月14日
     */
    function toPrintShip($afterPrint=true) {
        
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $delivery_cfg = $this->app->getConf('ome.delivery.status.cfg');
        $dlyObj = $this->app->model('delivery');
        $orderObj = $this->app->model('orders');
        $dly_corpObj = $this->app->model('dly_corp');
        
        $order_sellagentObj = $this->app->model('order_selling_agent');
        $_err = 'false';

        $ids = $this->processDeliveryId();

        //打印排序
        if($afterPrint){
            $ids = $dlyObj->printOrderByByIds($ids);
        }
        //批次号 by sy
        if($afterPrint){
            $idents = $this->_getPrintQueue($ids);
        }
        $idsAll = $this->parsePrintIds($ids);

        $allItems = array();

        $ids = $idsAll['ids'];

        # 单品、多品标识
        $sku = kernel::single('base_component_request')->get_get('sku');
        $sku = $sku ? $sku : '';
        
        # 给发货单加上相应配置项
        if($ids) {
            $this->updateDeliCfg($ids,$sku);
        }
        //备注显示方式
        $markShowMethod = app::get('ome')->getConf('ome.order.mark');
        $express_company_no = '';
        $hasPrint = array();
        if ($ids) {
            //sort($ids);

            $idds = $ids;
            unset($ids);
            $rows = array();
            foreach ($idds as $id) {
                $data = $dlyObj->dump($id, '*', array('delivery_order' => array('*'), 'delivery_items' => array('*')));
                if ($data['parent_id'] != 0) {
                    $_err = 'true';
                    continue;
                }
                $num = 0;
                $err = '';
                if ($data) {
                    //批次号
                    $allItems[$data['delivery_id']] = $data;

                    //统计已打印单据
                    if ($data['expre_status'] == 'true') {
                        $hasPrint[] = $data['delivery_bn'];
                    }

                    foreach ($data['delivery_items'] as $k => $i) {

                        $num += $i['number'];
                        
                        $bMaterialRow    = $basicMaterialLib->getBasicMaterialExt($product['bm_id']);
                        
                        //拷贝订单明细   liaoyu 2013-12-5
                        $data['delivery_items'][$k]['front_product_name'] = $i['product_name'];
                        $data['delivery_items'][$k]['product_name'] = $bMaterialRow['material_name'];
                        $data['delivery_items'][$k]['addon'] = $bMaterialRow['specifications'];
                        $data['delivery_items'][$k]['bn_dbvalue'] = $data['delivery_items'][$k]['bn'];
                    }

                    $o_bn = array();
                    $mark_text = array();
                    $custom_mark = array();
                    $total_amount = array();
                    foreach ($data['delivery_order'] as $v) {
                        $order = $orderObj->dump($v['order_id'], 'order_bn,mark_text,custom_mark,total_amount,order_source');

                        if ($order['mark_text']) {
                            $mark = unserialize($order['mark_text']);
                            if (is_array($mark) || !empty($mark)){
                                if($markShowMethod == 'all'){
                                    foreach ($mark as $im) {
                                        $mark_text[] = $im['op_content'];
                                    }
                                }else{
                                    $mark = array_pop($mark);

                                    $mark_text[] = $mark['op_content'];
                                }
                            }
                        }

                        if ($order['custom_mark']) {
                            $custommark = unserialize($order['custom_mark']);
                            if (is_array($custommark) || !empty($custommark)){
                                if($markShowMethod == 'all'){
                                    foreach ($custommark as $im) {
                                        if($order['order_source'] == 'tbdx'){
                                            $im['op_content']= $this->fomate_tbfx_memo($im['op_content'],$markShowMethod);
                                            $custom_mark[] = $im['op_content'];
                                        }else{
                                            $custom_mark[] = $im['op_content'];
                                        }
                                    }
                                }else{
                                    if($order['order_source'] == 'tbdx'){
                                        $mark = array_pop($custommark);
                                        $memo['op_content']= $this->fomate_tbfx_memo($mark['op_content'],$markShowMethod);
                                        $custom_mark[] = $memo['op_content'];
                                    }else{
                                        $mark = array_pop($custommark);
                                        $custom_mark[] = $mark['op_content'];
                                    }
                                }
                            }
                        }
                        $o_bn[] = $order['order_bn'];
                        $total_amount[] = $order['total_amount'];
                    }

                    $shop = $dlyObj->getShopInfo($data['shop_id']);
                    
                    
                    #分销王订单新增代销人收货信息
                    if($shop['node_type'] == 'shopex_b2b'){
                        #开启分销王代销人发货信息
                        if($delivery_cfg['set']['ome_delivery_sellagent']){
                            #订单扩展表上的状态是1
                            foreach($data['delivery_order'] as $val){
                                $oSellagent = app::get('ome')->model('order_selling_agent');
                                $sellagent_detail = $oSellagent->dump(array('order_id'=>$val['order_id']));
                                #订单扩展表上的状态是1  (只有代销人发货人与发货地址都存在，状态才会是1)
                                if($sellagent_detail['print_status'] == '1'){
                                    $shop['name'] = $sellagent_detail['website']['name'];
                                    $shop['default_sender'] = $sellagent_detail['seller']['seller_name'];
                                    $shop['mobile'] = $sellagent_detail['seller']['seller_mobile'];
                                    $shop['tel'] = $sellagent_detail['seller']['seller_phone'];
                                    $shop['zip'] = $sellagent_detail['seller']['seller_zip'];
                                    $shop['addr'] =  $sellagent_detail['seller']['seller_address'];
                                    $shop['area'] = $sellagent_detail['seller']['seller_area'];
                                }
                            }
                        }
                    }

                    $row = $dly_corpObj->getCorpInfo($data['logi_id'],'prt_tmpl_id,type');
                    $data['prt_tmpl_id'] = $row['prt_tmpl_id'];
                    $data['shopinfo'] = $shop;
                    $data['order_memo'] = implode(',', $mark_text);
                    $data['order_custom'] = implode(',', $custom_mark);
                    $data['order_count'] = $num;
                    $data['order_bn'] = implode(',', $o_bn);
                    $data['order_total_amount'] = implode(',', $total_amount);
                    //去除多余的三级区域
                    $reg = preg_quote(trim($data['consignee']['province']));
                    if (!empty($data['consignee']['city'])) {
                        $reg .= '.*?' . preg_quote(trim($data['consignee']['city']));
                    }
                    if (!empty($data['consignee']['district'])) {
                        $reg .= '.*?' . preg_quote(trim($data['consignee']['district']));
                    }

                    $data['consignee']['addr'] = preg_replace('/' . $reg . '/is', '', $data['consignee']['addr']);

                    //快递公式
                    if (!$express_company_no) {
                        $express_company_no = strtoupper($row['type']);
                        $logi_name = $data['logi_name'];
                    }
                    $rows['delivery'][] = $data;
                    $itm['delivery_id'] = $id;
                    $itm['delivery_bn'] = $data['delivery_bn'];
                    $idd[] = $itm;
                    $logid[$id] = $data['logi_no'];
                    $ids[] = $id;
                    $this->checkOrderStatus($id);
                } else {
                    $_err = 'true';
                }
            }

            if ($ids) $name = implode(',', $ids);
        }

        $rows['dly_tmpl_id'] = $data['prt_tmpl_id'];
        $rows['order_number'] = count($ids);
        $rows['name'] = $name;
        //物流公司标识
        $this->pagedata['print_logi_id'] = $data['logi_id'];
        //商品名称和规格取前台,是合并发货单,取第一个订单的货品名称
        $deliCfgLib = kernel::single('ome_delivery_cfg');
        $is_print_front = (1 == $deliCfgLib->getValue('ome_delivery_is_printship')) ? true : false;

        $productPos = $dlyObj->getPrintProductPos($rows['delivery']);
        if ($ids && $is_print_front) {
            $arrPrintProductName = $dlyObj->getPrintOrderName($rows['delivery']);
            if (!empty($arrPrintProductName)) {
                
                foreach ($rows['delivery'] as $k => $row) {
                    foreach ($row['delivery_items'] as $k2 => $v) {
                        $row['delivery_items'][$k2]['product_name'] = $arrPrintProductName[$row['delivery_id']][$v['bn']]['name'];
                        $row['delivery_items'][$k2]['addon'] = $arrPrintProductName[$row['delivery_id']][$v['bn']]['addon'];
                        $row['delivery_items'][$k2]['spec_info'] = $arrPrintProductName[$row['delivery_id']][$v['bn']]['addon'];
                        $row['delivery_items'][$k2]['store_position'] = $productPos[$v['product_id']];
                    }
                    $rows['delivery'][$k] = $row;
                }
            }

            unset($arrPrintProductName,$productPos);
        } elseif($ids) {
            // 货位的获取
            // $tmp_product_ids = array();
            foreach ($rows['delivery'] as $k => $row) {
                foreach ($row['delivery_items'] as $k2 => $v) {
                    // $tmp_product_ids[] = $v['product_id'];
                    
                    // $bpro_key = $row['branch_id'].$v['product_id'];
                    $rows['delivery'][$k]['delivery_items'][$k2]['store_position'] = $productPos[$v['product_id']];
                }
            }
        }

        #检测是否开启打印捆绑商品按钮
        $delivey_order = array();
        if($delivery_cfg['set']['print_pkg_goods']){
            foreach($rows['delivery'] as $key=>&$delivery){
                #获取发货单上有捆绑商品item_id
                $pkg_item_id = $orderObj->getPkgItemId($delivery['delivery_id']);
                foreach( $pkg_item_id as $item_id){
                    if(isset($delivery['delivery_items'][$item_id])){
                        #删除这批打印数据中包含捆绑商品的货品信息
                        unset($delivery['delivery_items'][$item_id]);
                    }
                }
            }
        }
        if ($rows['delivery'])
            foreach ($rows['delivery'] as $val) {
                //获取快递单打印模板的servivce定义
                $data = array();
                foreach (kernel::servicelist('ome.service.template') as $object => $instance) {
                    if (method_exists($instance, 'getElementContent')) {
                        $tmp = $instance->getElementContent($val);
                    }
                    $data = array_merge($data, $tmp);
                }
                $mydata[] = $data;
            }
        //$xmltool = $this->app->model('utility_xml');
        $printTmpl = $this->app->model('print_tmpl');

        $hasPrintStr = implode(',',array_slice($hasPrint,0,4));
        $hasPrintStr .= (count($hasPrint)>4) ? '……' : '';
        $this->pagedata['hasOnePrint'] = json_encode(count($hasPrint));
        $this->pagedata['hasPrintStr'] = json_encode($hasPrintStr);

        $errDly = $dlyObj->getList('delivery_id,delivery_bn', array('delivery_id' => $idsAll['errIds']));
        foreach($errDly as $val){
            $errBns[$val['delivery_id']] = $val['delivery_bn'];
        }
        $this->pagedata['errBns'] = $errBns;

        $this->pagedata['data'] = addslashes($dlyObj->array2xml2($mydata, 'data'));
        $this->pagedata['order_number'] = $rows['order_number'];
        $this->pagedata['prt_tmpl'] = $printTmpl->dump($rows['dly_tmpl_id'], 'prt_tmpl_width,prt_tmpl_offsety,prt_tmpl_offsetx,prt_tmpl_height,prt_tmpl_data,file_id');
        /* 修改的地方 */
        if ($this->pagedata['prt_tmpl']['file_id']) {
            $this->pagedata['tmpl_bg'] = 'index.php?app=ome&ctl=admin_delivery_print&act=showPicture&p[0]=' . $this->pagedata['prt_tmpl']['file_id'];
        }
        $this->pagedata['err'] = $_err;
        $this->pagedata['vid'] = $rows['name'];

        //批次号
        $this->pagedata['allItems'] = $allItems;
        $this->pagedata['idents'] = $idents['items'];
        $this->pagedata['ident'] = join(',', $idents['idents']);
        $this->pagedata['errIds'] = $idsAll['errIds'];
        $this->pagedata['errInfo'] = $idsAll['errInfo'];
        $items = array();
        foreach ($rows['delivery'] as $row) {
            $items[$row['delivery_id']] = $row;
        }
        
        $this->pagedata['items'] = $items;
        $this->pagedata['sku'] = $sku;//单品 多品标识
        $this->pagedata['dpi'] = 96;
        $this->pagedata['count'] = sizeof($ids);
        $this->pagedata['ids'] = $ids;
        $this->pagedata['idd'] = $idd;
        $this->pagedata['logid'] = $logid;
        $this->pagedata['logi_name'] = $logi_name;

        $this->pagedata['express_company_no'] = $express_company_no;
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['title'] = '快递单打印';

        //延迟判断
        $set_time_func_status = 'false';
        $set_time_func_domain = array(
            'lanpad.tg.taoex.com'
        );

        if (in_array($_SERVER['SERVER_NAME'], $set_time_func_domain)) {
            $set_time_func_status = 'true';
        }
        $this->pagedata['set_time_func_status'] = $set_time_func_status;

        if(!$afterPrint){
            $this->pagedata['log_id'] = $_REQUEST['log_id'];

            $dlyBillObj = $this->app->model('delivery_bill');
            $billFilter = array(
                'log_id'=>$_REQUEST['log_id'],
            );
            $this->pagedata['bill_logi_no'] = $dlyBillObj->getList('log_id,logi_no',$billFilter);
            $this->pagedata['delibery_bill_flag'] = 'delibery_bill_flag';
        }

        $logicfg = kernel::single('ome_print_logicfg')->getLogiCfg();
        if($logicfg[$express_company_no]){
            $logiVersionFlag = 1;
            $this->pagedata['logicfg'] = $logicfg[$express_company_no];
            $print_logi_version = app::get('ome')->getConf('print_logi_version_'.$this->pagedata['print_logi_id']);

            $this->pagedata['print_logi_version'] = intval($print_logi_version);
        }
        $this->pagedata['logiVersionFlag'] = $logiVersionFlag;
        $params = array('order_bn'=>$o_bn);
        ome_print_tmpl_express::instance($express_company_no,$this)->setParams($params)->getTmpl();

        }
    
    /*
     * 补打物流单
     * wujian@shopex.cn
     * 2012年3月13日
     */
     public function addPrintShip(){
        if(count($_REQUEST['log_id']) > 0){
            $this->addPrintShipNoData();
            exit;
        }

         $num = $_REQUEST['num'];
         $str = $_REQUEST['delivery_id'];

        //写入日志
        $opObj = $this->app->model('operation_log');
        $opObj->write_log('delivery_bill_print@ome', $_REQUEST['delivery_id'], '补打快递单('.$num.')份');

        // 1,增加子数据
        // 2,更新主物流单delivery  中logi_number，delivery_logi_number信息
        $dlyObj = $this->app->model('delivery');
        $delivery_bill = app::get('ome')->model('delivery_bill');
        $_REQUEST['delivery_id'] = $log_ids = array();
        for($i=0;$i<$num;$i++){
            $data = array('delivery_id' => $str,'create_time'=>time());
            $log_id = $delivery_bill->insert($data);
            $log_ids[] = $log_id;
            //发货单ID数据的key用子单ID
            $_REQUEST['delivery_id'][$log_id]=$str;
            unset($log_id);
        }
        $_REQUEST['log_id'] = $log_ids;

        //更新主发货单的物流单据数
        $delivery = app::get('ome')->model('delivery');
        $sql = "update sdb_ome_delivery set logi_number=logi_number+".$num." where delivery_id=".$str;
        $delivery->db->exec($sql);

        //屏蔽快递单的“打印排序”和“批次号”
        if (app::get('logisticsmanager')->is_installed()) {
            $this->toPrintExpre(false);
        } else {
            $this->toPrintShip(false);
        }
     }

    /*
     * 补打物流单(无需更新数据)
     * wujian@shopex.cn
     * 2012年3月13日
     */
     public function addPrintShipNoData(){
         if(is_array($_REQUEST['log_id'])){
            if(count($_REQUEST['log_id']) == 1){
                $tmp_str = $_REQUEST['log_id'][0];
                unset($_REQUEST['log_id']);
                $_REQUEST['log_id'] = $tmp_str;
            }else{
                $tmp_arr = $_REQUEST['log_id'];
                unset($_REQUEST['log_id']);
                foreach($tmp_arr as $k =>$val){
                    $_REQUEST['log_id'] .= $val.",";
                }
                $_REQUEST['log_id'] = substr($_REQUEST['log_id'],0,-1);
            }
         }
         $str = $_REQUEST['delivery_id'];
        $log_id = explode(',',$_REQUEST['log_id']);

        $dlyObj = $this->app->model('delivery');
        $delivery_bill = app::get('ome')->model('delivery_bill');

        $flag = $dlyObj->dump(array('delivery_id' => $str));
        $filter = array(
            'log_id' => $log_id,
            'delivery_id'=>$str
        );
        $datanum = $delivery_bill->count($filter);
        if(count($log_id)>$datanum||!$flag){
            die('错误');
        }

        $_REQUEST['log_id'] = $log_id;
        $_REQUEST['delivery_id'] = array();
        foreach($log_id as $val){
            $_REQUEST['delivery_id'][$val]=$str;
            unset($val);
        }

        //屏蔽快递单的“打印排序”和“批次号”
        if (app::get('logisticsmanager')->is_installed()) {
            $this->toPrintExpre(false);
        } else {
            $this->toPrintShip(false);
        }
     }

    /*
     * 关联物流单号
     * wujian@shopex.cn
     * 2012年3月19日
     */
     function insertAfterExpress(){
         $arr = $_POST;
        $arrc = count($arr["id"]);
        $uniquec = count(array_unique($arr["id"]));
        $opObj = app::get('ome')->model('operation_log');


        if($arrc>$uniquec){
            echo '物流号中有重复的值';
        }else{
             //保存对应物流公司所用打印版本

            $flag = true;
            $delivery_bill = app::get('ome')->model('delivery_bill');
            $delivery = app::get('ome')->model('delivery');
            foreach($arr["id"] as $key=>$value){
                $filter['logi_no'] = $value;
                $deliveryCount = $delivery->count($filter);
                $deliveryBillCount = $delivery_bill->count($filter);
                if ($deliveryCount > 0 || $deliveryBillCount > 0){
                    echo '已有此物流号:'.$value;
                    $flag = false;
                    die;
                }
            }
            if($flag){
                app::get('ome')->setConf('print_logi_version_'.$arr['print_logi_id'], intval($arr['logi_version']));

                foreach($arr["id"] as $key=>$value){
                    $delivery_bill->update(array("logi_no"=>$value),array('log_id'=>$key));
                    $dlybillinfo = $delivery_bill->dump(array('log_id'=>$key));
                    $logstr = '录入快递单号:'.$value;
                    $opObj->write_log('delivery_bill_add@ome', $dlybillinfo['delivery_id'], $logstr);
                }
                echo 'SUCC';
            }
        }
     }

    /**
     * 批量更改物流
     *
     * @param void
     * @return void
     */
    function toChangeDly() {
        $ids = $_POST['delivery_id'];
        if (empty($ids)) {
            die('没有选择任何可操作的发货单。');
        }

        $dlyCrop = app::get('ome')->model('dly_corp')->getList('corp_id, name, type, is_cod, weight', array('disabled' => 'false'), 0, -1, 'weight DESC');

        $deliveryObj = app::get('ome')->model('delivery');
        $branchObj = app::get('ome')->model('branch');

        $deliverys = $deliveryObj->getList('delivery_id, branch_id, delivery_bn', array('delivery_id' => $ids));
        $branchDatas = array();
        foreach ($deliverys as $delivery) {
            $branchDatas[$delivery['branch_id']]['delivery'][$delivery['delivery_id']] = $delivery['delivery_bn'];
            $branchDatas[$delivery['branch_id']]['count']++;
        }
        foreach ($branchDatas as $key => $branchData) {
            $branchDatas[$key]['branch'] = $branchObj->dump($key, 'branch_id,name');
            $branchDatas[$key]['corp'] = $branchObj->get_corp($key);
        }

        $this->pagedata['ids'] = join(',', $ids);
        $this->pagedata['branchDatas'] = $branchDatas;
        $this->pagedata['dlyCorp'] = $dlyCrop;
        $this->pagedata['orderCnt'] = count($ids);
        $this->display('admin/delivery/change_dly.html');
    }

    /**
     * 更换物流公司
     *
     */
    function doChangeDly() {
        $this->begin();
        $branchDatas = $_POST['branchData'];
        $corpObj = app::get('ome')->model('dly_corp');
        $deliveryObj = app::get('ome')->model('delivery');
        $opObj = app::get('ome')->model('operation_log');
        $waybillObj = kernel::single('logisticsmanager_service_waybill');

        $rows = $corpObj->getList();
        foreach($rows as $val) {
            $corpList[$val['corp_id']] = $val;
        }

        foreach ($branchDatas as $branch) {
            $branch_id = $branch['branch'];
            $newCorp = $branch['newCorp'];
            $deliveryIds = $branch['delivery'];
            if (!$branch_id || !$newCorp || !$deliveryIds) {
                $this->end(false, '请确定新的物流！');
            } else {
                $corp = $corpList[$newCorp];

                $data['logi_id'] = $newCorp;
                $data['logi_name'] = $corp['name'];
                $data['logi_no'] = null;
                $data['status'] = 'ready';
                $data['stock_status'] = 'false';
                $data['deliv_status'] = 'false';
                $data['expre_status'] = 'false';
                $filter = array();
                $filter['delivery_id'] = $deliveryIds;
                $filter['branch_id'] = $branch_id;
                $filter['verify'] = 'false';
                $filter['process'] = 'false';
                $filter['pause'] = "false";
                if ($corp['tmpl_type'] == 'electron' && ($channel['channel_type']=='wlb' || $channel['channel_type']=='360buy')) {
                    $filter['shop_id'] = $corp['shop_id'];
                }

                $dly_data_temp = $deliveryObj->getList('*', $filter, 0, -1);
                //得物急速现货发货单不允许更新物流公司
                foreach($dly_data_temp as $key => $val){
                    if(kernel::single('ome_delivery_bool_type')->isJISU($val['bool_type'])){
                        $this->end(false, '急速现货发货单物流公司不能切换！');
                    }
                }

                $logs = array();
                if(is_array($deliveryIds)){
                    foreach ($deliveryIds as $did) {
                        $dly_data = $deliveryObj->dump($did);
                        //回收电子面单
                        if ($dly_data['logi_no'] && $corpList[$dly_data['logi_id']]['tmpl_type'] == 'electron') {
                            $waybillObj->recycle_waybill($dly_data['logi_no']);
                        }
                        $logs[] = array('did' => $did, 'cnt' =>  '修改物流:' .$dly_data['logi_name']. ' => ' .$corp['name']);
                    }
                }
                if ($deliveryObj->update($data, $filter)) {

                    foreach($logs as $log) {
                        $opObj->write_log('delivery_logi@ome', $log['did'], $log['cnt']);
                    }
                }
            }
        }
        $this->end(true, '更换物流成功！');
    }

    /**
     * 更新发货单的打印状态
     *
     * @param string $type
     * @param int $dly_id
     *
     */
    function setPrintStatus() {
        set_time_limit(0);
        $current_otmpl_name = $_POST['current_otmpl_name'] ? $_POST['current_otmpl_name'] : '默认';
        $type = $_POST['type'];
        $str_id = $_POST['str'];
        $dlyObj = $this->app->model('delivery');
        $tmp_id = array_filter(explode(',', $str_id));

        if (!$this->_checkPrintQueue($tmp_id, $msg)) {
            echo $msg; exit;
        }

        $dlys = $dlyObj->getList('*', array('delivery_id' => $tmp_id), 0, -1);
        $dly = array();   $arr_s = array('cancel', 'back', 'stop','return_back');
        foreach ($dlys as $k => $delivery) {
            if (in_array($delivery['status'], $arr_s)) continue;
            if ($delivery['status'] == 'ready')  $dly[$k]['status'] = 'progress';
            $dly[$k]['delivery_id'] = $delivery['delivery_id'];
            $send_flag = false;
            switch ($type) {
                case 'express':
                    if ($delivery['expre_status'] == 'false') {
                        $send_flag = true;
                    }
                    $dly[$k]['expre_status'] = 'true';
                    $dly[$k]['_log_'] = 'delivery_expre@ome';
                    $dly[$k]['_memo_'] = '快递单打印';
                    //如果是当当物流订单，将订单号更新为物流单号
                    $logi_id = $delivery['logi_id'];
                    $dly_corpObj = $this->app->model('dly_corp');
                    $orderObj = app::get('ome')->model('orders');
                    $dly_corp = $dly_corpObj->dump($logi_id,'type');
                    if ($dly_corp['type'] == 'DANGDANG') {
                        $orderIds = $dlyObj->getOrderIdByDeliveryId($delivery['delivery_id']);
                        if ($orderIds)
                        $ids = implode(',', $orderIds);
                        if ($orderIds)
                        foreach ($orderIds as $oid)
                        {
                            $order = $orderObj->dump($oid,'order_bn');
                            $order_bn= $order['order_bn'];
                        }
                        $dly[$k]['logi_no'] = $order_bn;
                    }
                    //
                    break;
                case 'stock':
                    if ($delivery['stock_status'] == 'false') {
                        $send_flag = true;
                    }
                    $dly[$k]['stock_status'] = 'true';
                    $dly[$k]['_log_'] = 'delivery_stock@ome';
                    $dly[$k]['_memo_'] = "备货单打印（打印模板： $current_otmpl_name ）";
                    break;
                case 'delivery':
                    if ($delivery['deliv_status'] == 'false') {
                        $send_flag = true;
                    }
                    $dly[$k]['deliv_status'] = 'true';
                    $dly[$k]['_log_'] = 'delivery_deliv@ome';
                    $dly[$k]['_memo_'] = "发货单商品信息打印（打印模板： $current_otmpl_name ）";
                    break;
                case 'stock_dly':
                    if ($delivery['deliv_status'] == 'false' && $delivery['stock_status']) {
                        $send_flag = true;
                    }
                    $dly[$k]['deliv_status'] = 'true';
                    $dly[$k]['stock_status'] = 'true';
                    $dly[$k]['_isMerge_'] = true;
                    $dly[$k]['_log_'][0] = 'delivery_deliv@ome';
                    $dly[$k]['_log_'][1] = 'delivery_stock@ome';
                    $dly[$k]['_memo_'][0] = "发货单商品信息打印（打印模板： $current_otmpl_name ）";
                    $dly[$k]['_memo_'][1] = "备货单打印（打印模板： $current_otmpl_name ）";
                    break;
            }
        }
        $opObj = $this->app->model('operation_log');
        foreach ($dly as $k => $v) {
            $_dly = $v;
            $dlyObj->save($_dly);
            $this->sendMessageProduce($send_flag, $type, $v['delivery_id']);

            $delivery = $dlyObj->dump($v['delivery_id'], 'expre_status,stock_status,deliv_status');
            //更新交易发货状态 API
            if ($delivery['expre_status'] == 'true' && $delivery['stock_status'] == 'true' && $delivery['deliv_status'] == 'true') {
                if ($send_flag == true) {
                    foreach (kernel::servicelist('service.delivery') as $object => $instance) {
                        if (method_exists($instance, 'update_status')) {
                            $instance->update_status($v['delivery_id'], 'progress');
                        }
                    }
                }
            }

            if ($v['_isMerge_']) {//联合打印
                foreach ($v['_log_'] as $key => $val) {
                    $opObj->write_log($val, $v['delivery_id'], $v['_memo_'][$key]);
                }
                $dlyObj->updateOrderPrintFinish($v['delivery_id']);
            } else {
                $opObj->write_log($v['_log_'], $v['delivery_id'], $v['_memo_']);
                $dlyObj->updateOrderPrintFinish($v['delivery_id']);
            }
        }

        echo 'true';
    }

    /**
     * 淘宝全链路
     */
    public function sendMessageProduce($send_flag, $printType, $delivery_id) {
        //是否第一次打印，打印备货单，发货单，物流单
        $list = array('stock' => 5, 'delivery' => 6, 'stock_dly' => 6, 'express' => 7);
        if (!in_array($send_flag, array_keys($list))) {
            return false;
        }
        if ($send_flag) {
            if (empty($this->deliveryOrderModel)) {
                $this->deliveryOrderModel = $this->app->model('delivery_order');
            }
            $deliveryOrderInfo = $this->deliveryOrderModel->getList('*', array('delivery_id' => $delivery_id));
            foreach ($deliveryOrderInfo as $delivery_order) {
                kernel::single('ome_order')->sendMessageProduce($list[$printType], $delivery_order['order_id']);
            }
        }
    }
    
    /**
     * 打印备货单
     *
     */
    function toPrintStock() {
        $_err = 'false';
        # 发货配置类型
        $sku = kernel::single('base_component_request')->get_get('sku');
        $sku = $sku ? $sku : '';

        $now_print_type = 'stock';

        //获取当前待打印的发货单过滤条件
        $filter_condition = $this->getPreparePrintIds();

        $PrintLib = kernel::single('ome_delivery_print');
        $print_data = $PrintLib->getPrintDatas($filter_condition,$now_print_type,$sku,true,$msg);
        if(isset($msg['error_msg']) && $msg['error_msg']){
            $this->headerErrorMsgDisply($msg['error_msg']);
        }

        if(isset($msg['warn_msg']) && $msg['warn_msg']){
            $this->message($msg['warn_msg']);
            exit;
        }
        
        $PrintStockLib = kernel::single('ome_delivery_print_stock');
        $format_data = $PrintStockLib->format($print_data, $sku,$_err);
        $this->pagedata = $format_data;

        $this->pagedata['errBns'] = $print_data['errBns'];
        $this->pagedata['err'] = $_err;
        $this->pagedata['allItems'] = $print_data['deliverys'];
        $this->pagedata['idents'] = $print_data['identInfo']['items'];
        $this->pagedata['ident'] = join(',', $print_data['identInfo']['idents']);
        $this->pagedata['errIds'] = $print_data['errIds'];
        $this->pagedata['errInfo'] = $print_data['errInfo'];
        $this->pagedata['time'] = date("Y-m-d H:i:s");
        $this->pagedata['sku'] = $sku;
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['title'] = '备货单打印';

        

        kernel::single('ome_print_otmpl')->printOTmpl($_GET['otmplId'],$now_print_type,$this);
    }

    /**
     * 备货单新版
     */
    function toPrintStockNew() {
        $_err = 'false';
        # 发货配置类型
        $sku = kernel::single('base_component_request')->get_get('sku');
        $sku = $sku ? $sku : '';

        $now_print_type = 'stock';

        //获取当前待打印的发货单过滤条件
        $filter_condition = $this->getPreparePrintIds();

        $PrintLib = kernel::single('ome_delivery_print');
        $print_data = $PrintLib->getPrintDatas($filter_condition,$now_print_type,$sku,true,$msg);
        if(isset($msg['error_msg']) && $msg['error_msg']){
            $this->headerErrorMsgDisply($msg['error_msg']);
        }

        if(isset($msg['warn_msg']) && $msg['warn_msg']){
            $this->message($msg['warn_msg']);
            exit;
        }

        $PrintStockLib = kernel::single('ome_delivery_print_stock');
        $format_data = $PrintStockLib->format($print_data, $sku,$_err);
        $this->pagedata = $format_data;

        //备货打印json数据
         $jsondata = $PrintStockLib->arrayToJson($format_data['rows'], $print_data['identInfo']['idents'], $this->pagedata);

        $this->pagedata['errBns'] = $print_data['errBns'];
        $this->pagedata['err'] = $_err;
        $this->pagedata['allItems'] = $print_data['deliverys'];
        $this->pagedata['idents'] = $print_data['identInfo']['items'];
        $this->pagedata['ident'] = join(',', $print_data['identInfo']['idents']);
        $this->pagedata['errIds'] = $print_data['errIds'];
        $this->pagedata['errInfo'] = $print_data['errInfo'];
        $this->pagedata['time'] = date("Y-m-d H:i:s");
        $this->pagedata['sku'] = $sku;
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['title'] = '备货单打印';
        $this->pagedata['jsondata'] = $jsondata;
        $this->pagedata['count'] = sizeof($print_data['ids']);
        $this->pagedata['totalPage'] = count($printData);

        ome_print_controltmpl::instance($now_print_type, $this)->printOTmpl($_GET['otmplId']);
    }

    /**
     * 录入快递单号
     *
     */
    function insertExpress() {
        if (empty($_POST['id'])) {
            exit("请录入快递单号");
        }
        $ids = $_POST['id'];
        $dlyObj = $this->app->model('delivery');
        $errmsg = '';
        if ($ids)
            foreach ($ids as $k => $i) {
                $i = $i ? trim($i) : null;
                $delivery = $dlyObj->dump($k,'delivery_bn,status,process');
                $bn = $delivery['delivery_bn'];
                $arr_s = array('succ', 'cancel', 'back', 'stop','return_back');
                if (in_array($delivery['status'], $arr_s) || $delivery['process'] == 'true') {
                    $errmsg .= "发货单" . $bn . "相关信息不能修改\n";
                    unset($ids[$k]);

                }
                unset($delivery);
                if ($dlyObj->existExpressNo($i, $k)) {
                    exit("物流单号已存在，发货单为" . $bn);
                }

                if(empty($i)){
                    exit("物流单号不能为空，发货单为" . $bn);
                }
            }
        $opObj = $this->app->model('operation_log');
        $dlyLogObj = $this->app->model('delivery_log');
        if ($ids)
            foreach ($ids as $key => $item) {
                $dlyLog = array();
                $item = $item ? trim($item) : null;
                $data['delivery_id'] = $key;
                $data['logi_no'] = $item;

                $dlyObj->save($data);

                if ($item && $key) {
                    $delivery = $dlyObj->dump($key);
                    $dlyLog['delivery_id'] = $key;
                    $dlyLog['logi_id'] = $delivery['logi_id'];
                    $dlyLog['logi_no'] = $item;
                    $dlyLog['logi_name'] = $delivery['logi_name'];
                    $dlyLog['create_time'] = time();

                    if (!$dlyLogObj->dump(array('delivery_id' => $key, 'logi_no' => $item))) {
                        $dlyLogObj->save($dlyLog);
                    }
                }

                $opObj->write_log('delivery_logi_no@ome', $key, '录入快递单号:'.$item);
            }

        app::get('ome')->setConf('print_logi_version_'.$_POST['print_logi_id'], intval($_POST['logi_version']));
        if($errmsg && !empty($errmsg)){
            $errmsg .= "\n请将以上报错的打印单据作废，其它单据保存成功";
            exit($errmsg);
        }
        echo "SUCC";
    }

    /**
     * 合并发货单列表
     *
     * @param bigint $dly_id
     */
    function merge($dly_id) {
        $this->begin('index.php?app=ome&ctl=admin_receipts_print');
        if (!isset($dly_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        if ($this->checkOrderStatus($dly_id))
            exit("发货单已无法操作，请到订单处理中心处理");
        $Objdly = $this->app->model('delivery');
        $dly = $Objdly->dump($dly_id);
        $data = $Objdly->getSameKeyList($dly_id);
        $tmp = $data;
        if ($data)
            foreach ($data as $k => $v) {
                if ($v['delivery_id'] == $dly_id) {
                    $tmp_dly = $v;
                    unset($tmp[$k]);
                }
            }
        empty($dly['branch_id']) ? $branch_id = 0 : $branch_id = $dly['branch_id'];
        $dly_corpObj = $this->app->model('dly_corp');
        $braObj = app::get('ome')->model('branch');

        $this->pagedata['dly_corp'] = $braObj->get_corp($branch_id);
        $this->pagedata['dly'] = $tmp;
        $this->pagedata['olddly'] = $tmp_dly;
        $this->singlepage("admin/delivery/delivery_merge.html");
    }

    /**
     * 拆分发货单列表
     *
     * @param bigint $dly_id
     */
    function split($dly_id) {
        $this->begin('index.php?app=ome&ctl=admin_receipts_print');
        if (!isset($dly_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        $Objdly = $this->app->model('delivery');
        $dly = $Objdly->dump($dly_id, 'delivery_bn');
        $data = $Objdly->getItemsByParentId($dly_id, 'array', '*');

        $this->pagedata['parent_id'] = $dly_id;
        $this->pagedata['parent_bn'] = $dly['delivery_bn'];
        $this->pagedata['count'] = sizeof($data);
        $this->pagedata['dly'] = $data;
        $this->singlepage("admin/delivery/delivery_split.html");
    }

    /**
     * 合并发货单操作
     *
     */
    function doMerge() {
        $this->begin('index.php?app=ome&ctl=admin_receipts_print&act=merge');

        if (empty($_POST['check']) || sizeof($_POST['check']) < 2) {
            $this->end(false, '请选择至少两张发货单');
        }
        $checkbox = $_POST['check'];
        $is_bind = $_POST['is_bind'];
        $order_status = $_POST['order_status'];

        if ($checkbox)
            foreach ($checkbox as $item) {
                $is_choice['is_bind'][$item] = $is_bind[$item];
            }
        if ($checkbox)
            foreach ($checkbox as $item) {
                $is_choice['order_status'][$item] = $order_status[$item];
            }
        if (in_array("ERROR", $is_choice['order_status'])) {
            $this->end(false, '您选择的发货单中，某张订单有问题');
        }
        if ($_POST['logi_id'] == '') {
            $this->end(false, '请选择物流公司');
        }
        $ordersObj = $this->app->model('orders');
        $Objdly = $this->app->model('delivery');
        if ($Objdly->existIsMerge($checkbox)) {
            $this->end(false, '选择的发货单中已合并过，请返回列表重新操作');
        }

        $orders = $ordersObj->getList('order_id, order_bn, status, ship_status, process_status', array('order_id|in' => $checkbox));
        foreach ($orders as $os) {
            if ($os['status'] != 'active' || $os['ship_status'] != 0 || ($os['process_status'] != 'splited' && $os['process_status'] != 'splitting')) {
                $this->end(false, '订单编号' . $os['order_bn'] . '不符合条件');
            }
        }

        $Objdly_corp = $this->app->model('dly_corp');
        $corp = $Objdly_corp->dump($_POST['logi_id'], 'corp_id,name');

        $delivery['logi_id'] = $corp['corp_id'];
        $delivery['logi_name'] = $corp['name'];

        $result = $Objdly->merge($checkbox, $delivery);
        if ($result) {
            $this->end(true, '合并操作已成功', 'index.php?app=ome&ctl=admin_receipts_print');
        }

        $this->end(false, '合并操作失败');
    }

    /**
     * 拆分发货单操作
     *
     */
    function doSplit() {
        $this->begin('index.php?app=ome&ctl=admin_receipts_print&act=split');
        
        if (empty($_POST['check'])) {
            $this->end(false, '请选择至少一张发货单');
        }
        $Objdly = $this->app->model('delivery');
        if (($_POST['count'] - sizeof($_POST['check'])) <= 1) {
            $result = $Objdly->splitDelivery($_POST['parent_id'], '', false);
            if ($result) {
                $this->end(true, '拆分操作已成功', 'index.php?app=ome&ctl=admin_receipts_print&act=index');
            }
        } else {
            $result = $Objdly->splitDelivery($_POST['parent_id'], $_POST['check'], false);
            if ($result) {
                $this->end(true, '拆分操作已成功', 'index.php?app=ome&ctl=admin_receipts_print&act=index');
            }
        }

        $this->end(false, '拆分操作失败');
    }

    /**
     * 保存发货单详情信息
     *
     */
    function doDetail() {
        $status = $_POST['status'] ? $_POST['status'] : 0;
        $ctl = $_POST['ctl'];
        $this->begin();
        if (empty($_POST['dly'])) {
            $this->end(false, '保存失败');
        }
        if ($_POST['dly']['logi_id'] == '' || empty($_POST['dly']['logi_id'])) {
            $this->end(false, '请选择物流公司');
        }
        //print_r($_POST['dly']);
        $Objdly = $this->app->model('delivery');
        $delivery = $Objdly->dump($_POST['dly']['delivery_id']);

        $arr_s      = array('cancel', 'back','stop','return_back');
        if (in_array($delivery['status'], $arr_s) && $_POST['dly']['logi_no'] ){
            $this->end(false,'发货单已撤销不能修改');
        }

        //物流公司改变 物流单号不改变     将物流单号置空 重新计算物流费用
        $doObj = $this->app->model('delivery_order');
        $oObj = $this->app->model('orders');
        $Objdly_corp = $this->app->model('dly_corp');
        $corp = $Objdly_corp->dump($_POST['dly']['logi_id']);
        $order_id = $doObj->dump(array('delivery_id' => $_POST['dly']['delivery_id']), 'order_id');
        //获取发货单是由几个订单组合的
        $order_ids = $doObj->getlist('order_id',array('delivery_id' => $_POST['dly']['delivery_id']));
        $orders = $oObj->dump(array('order_id' => $order_id['order_id']), 'ship_status,order_bn,shop_type,self_delivery');        if ($delivery['logi_id'] != $_POST['dly']['logi_id']) {
            if ($delivery['logi_no'] == $_POST['dly']['logi_no']) {
                $_POST['dly']['logi_no'] = NULL;
                $data['logi_no'] = NULL;
                if ($corp['type'] == 'DANGDANG') {
                    $data['logi_no'] = $orders['order_bn'];
                }
            } else {
                $data['logi_no'] = $_POST['dly']['logi_no'];
            }

            //如果切换了物流公司且用的是当当的判断是否合并发货单
            if ($corp['type'] == 'DANGDANG') {
                if (count($order_ids)>1){
                    $this->end(false, '此发货单是合并发货单,不可以选择当当物流!');
                }
                if ($orders['shop_type']!='dangdang') {
                    $this->end(false, '非当当店铺订单,不可以选择当当物流!');
                }

            }


            if ( $corp['type'] == 'AMAZON' && $orders['shop_type']!='amazon' ) {
                $this->end(false, '此发货单是非亚马逊店铺订单,不可以选择亚马逊物流!');

            } //todo            //计算预计物流费用
            $area = $_POST['dly_count'];
            $arrArea = explode(':', $delivery['consignee']['area']);
          
            $area_id = $arrArea[2];

            $price = $Objdly->getDeliveryFreight($area_id,$_POST['dly']['logi_id'],$delivery['net_weight']);
            $data['delivery_cost_expect'] = $price;

            if ($delivery['logi_id']) {
                $dly_corp = $Objdly_corp->dump($_POST['dly']['logi_id']);
                $logi_name = $dly_corp['name'];
                //计算保价费用
                $protect = $dly_corp['protect'];
                if ($protect == 'true') {
                    $is_protect = 'true';
                    $protect_rate = $dly_corp['protect_rate']; //保价费率
                    $protect_price = $protect_rate * $delivery['net_weight'];
                    $minprice = $dly_corp['minprice']; //最低报价费用
                    if ($protect_price < $minprice) {
                        $cost_protect = $minprice;
                    } else {
                        $cost_protect = $protect_price;
                    }
                }
            }
            $data['cost_protect'] = $cost_protect ? $cost_protect : 0;
            $data['is_protect'] = $is_protect ? $is_protect : 'false';
        }
        if ($_POST['dly']['logi_no'] == '') {
            $_POST['dly']['logi_no'] = NULL;
        } else {
            if ($Objdly->existExpressNo($_POST['dly']['logi_no'], $_POST['dly']['delivery_id'])) {

                $this->end(false, '已有此物流单号');
            }
        }
        $_POST['dly']['logi_no'] = $_POST['dly']['logi_no'] ? trim($_POST['dly']['logi_no']) : null;
        $dly['logi_id'] = $_POST['dly']['logi_id'];
        $dly['logi_no'] = $_POST['dly']['logi_no'];
        $dly['logi_name'] = $corp['name'];
        $dly['memo'] = $_POST['dly']['memo'];

        $result = $Objdly->updateDelivery($dly, array('delivery_id' => $_POST['dly']['delivery_id']));

        if ($_POST['dly']['logi_no'] && $_POST['dly']['delivery_id']) {
            $dlyLog['delivery_id'] = $_POST['dly']['delivery_id'];
            $dlyLog['logi_id'] = $_POST['dly']['logi_id'];
            $dlyLog['logi_no'] = $_POST['dly']['logi_no'];
            $dlyLog['logi_name'] = $corp['name'];
            $dlyLog['create_time'] = time();
            $dlyLogObj = $this->app->model('delivery_log');
            if (!$dlyLogObj->dump(array('delivery_id' => $dlyLog['delivery_id'], 'logi_no' => $dlyLog['logi_no']))) {
                $dlyLogObj->save($dlyLog);
            }
        }
        //物流单号 或是 物流公司改变的时候 判断发货状态
        if ($delivery['logi_id'] != $_POST['dly']['logi_id'] || (!empty($delivery['logi_no']) && $delivery['logi_no'] != $_POST['dly']['logi_no'])) {
            //未发货的发货单变更物流单号应重新打印快递单
            if ($delivery['process'] == 'false') {
                $msg = '物流信息已改变，您应该从新打印快递单';
            }
        }
        $data['net_weight'] = $delivery['net_weight'];
        $data['delivery_id'] = $_POST['dly']['delivery_id'];
        //weight
        if($_POST['weight']) {
            $arrArea = explode(':', $delivery['consignee']['area']);
          
            $area_id = $arrArea[2];
            $data['delivery_cost_actual'] = $Objdly->getDeliveryFreight($area_id,$_POST['dly']['logi_id'],$_POST['weight']);//修改重量时更新物流费用
        }        $data['weight']=$_POST['weight'];//新增修改重量

        if( $Objdly->save($data) && $_POST['weight'] ){
            
            //更新销售单上的物流费用
            kernel::single('sales_sales')->update_deliverycost($_POST['dly']['delivery_id']);

        }

        if ($result) {
            // 是否修改发货单状态信息 (不修改)
            if ($result === 1) {
                //[拆单]修改发货单详情加入发货单号_物流运单号
                $log_msg       = '修改发货单详情';
                $log_msg       .= (empty($delivery['delivery_bn']) ? '' : '，发货单号：'.$delivery['delivery_bn']);
                $log_msg       .= (empty($delivery['delivery_bn']) ? '' : '，物流单号：'.$_POST['dly']['logi_no']);
                
                $opObj = $this->app->model('operation_log');
                $opObj->write_log('delivery_modify@ome', $_POST['dly']['delivery_id'], $log_msg);
            }
            $this->end(true, '保存成功' . $msg);
        }
        $this->end(false, '保存失败');
    }

    /**
     * 保存货品货位详情信息
     *
     */
    function doItemDetail() {
        $this->begin();
        if (empty($_POST['num']) || empty($_POST['pos'])) {
            $this->end(false, '请填写数量');
        }
        $dly_id = $_POST['delivery_id'];
        $number = $_POST['num'];
        $pos = $_POST['pos'];
        $Objdly = $this->app->model('delivery');
        $delivery = $Objdly->dump($dly_id);
        $arr_s = array('succ', 'cancel', 'back', 'stop','return_back');
        if (in_array($delivery['status'], $arr_s) || $delivery['process'] == 'true') {
            $this->end(false, '发货单相关信息不能修改');
        }
        if ($number)
            foreach ($number as $key => $item) {
                $count = $item;
                foreach ($pos[$key] as $k => $i) {
                    $total += $i;
                }
                if ($total != $count) {
                    $this->end(false, '保存失败，填写总数不正确');
                }
                $count = 0;
                $total = 0;
            }

        $Objpos = $this->app->model('dly_items_pos');
        if ($pos)
            foreach ($pos as $id => $row) {
                $Objpos->delete(array('item_id' => $id)); //更新前先做删除
            }
        //插入货品货位
        if ($pos)
            foreach ($pos as $key => $item) {
                foreach ($item as $k => $i) {
                    if ($i <= 0) {
                        continue;
                    }
                    $data['item_id'] = $key;
                    $data['num'] = $i;
                    $data['pos_id'] = $k;
                    $Objpos->save($data);
                    $data = '';
                }
            }
        $opObj = $this->app->model('operation_log');
        $opObj->write_log('delivery_position@ome', $_POST['delivery_id'], '发货单货位录入');
        $this->end(true, '保存成功', 'index.php?app=ome&ctl=admin_receipts_print&act=index');
    }

    /**
     * 打印页面初始化 获取打印批次号
     *
     * @return Array
     */
    function _getPrintQueue($ids) {
        if (!$result = $this->_checkPrintQueue($ids, $msg)) {
            $this->message($msg);
            exit;
        }
        $queueObj = kernel::single('ome_queue');
        $queue = $queueObj->fetchPrintQueue($ids);

        return $queue;
    }

    /**
     * 检查是否能同批次打印
     *
     * @return bool
     */
    function _checkPrintQueue($ids, &$msg) {
        if (!empty($ids)) sort($ids);

        # 批量打印限制数量
        $deliCfgLib = kernel::single('ome_delivery_cfg');
        $sku = kernel::single('base_component_request')->get_get('sku');
        if($sku==''){
            $sku = kernel::single('base_component_request')->get_post('sku');
        }

        $batch_print_nums = $deliCfgLib->getValue('ome_batch_print_nums',$sku);
        if (count($ids) > $batch_print_nums) {
            $msg = "所选发货单号数量已超过批量打印数量 (" . $batch_print_nums . ")！";
            return false;
        }

        $delivery_check_ident = app::get('ome')->getConf('ome.delivery.check_ident');
        $delivery_check_ident = $delivery_check_ident ? $delivery_check_ident : 'on';
        $queueObj = kernel::single('ome_queue');
        if ($queueObj->isExistsQueueItems($ids, $existsQueueItems)) {
            if (count($ids) != count($existsQueueItems)) {
                $msg = "已生成批次号的发货单不能和未生成的发货单一起打印！";
            } else {
                $error = array();
                foreach ($existsQueueItems as $k => $v) {
                    if (!in_array($v, $error)) {
                        $error[] = $v;
                    }
                }
                $msg = "发货单号已存在有不相同的批次号：<br/>" . join('<br/>',$error);
            }
            if ($delivery_check_ident == 'on') {
                return false;
            } else {
                $this->pagedata['existsIdents'] = str_replace($msg,'<br/>','&nbsp;&nbsp;');
            }
        }
        return true;
    }

    function message($msg) {

        $this->pagedata['err'] = 'true';
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['time'] = date("Y-m-d H:i:s");
        $this->pagedata['msg'] = $msg;
        $this->singlepage('admin/delivery/message.html');
        $this->display('admin/delivery/print.html');
    }

    function parsePrintIds($ids) {
        $result = array(
            'ids' => array(), //可用于打印的ID
            'errIds' => array(), //不能胜于打印的数据
            'errInfo' => array(), //所有错误信息
        );

        $dlyObj = & app::get('ome')->model('delivery');
        foreach ($ids as $id) {
            $hasError = false;
            //检查当前订单的状态是不是可以打印
            if (!$this->checkOrderStatusEx($id, $errMsg)) {
                //状态有问题的订单是肯定不要打印的
                $result['errIds'][] = $id;
                $result['errInfo'][$id] = $errMsg;
                $hasError = true;
            } else {
                $result['ids'][] = $id;
            }
            if (!$hasError) {
                //检查库存(除原样寄回发货单)
                $dly = $dlyObj->dump($id, '*', array('delivery_items' => array('*')));
                if ($dly['type'] == 'normal') {
                    foreach ($dly['delivery_items'] as $item) {
                        $re = $dlyObj->existStockIsPlus($item['product_id'], $item['number'], $item['item_id'], $dly['branch_id'], $err, $item['bn']);
                        if (!$re) {
                            $result['errIds'][] = $id;
                            $result['errInfo'][$id] .= $err . "&nbsp;,&nbsp;";
                            $hasError = true;
                        }
                    }
                }
                //库存有问题的单据认为是要打印的
                if (!in_array($id, $result['ids']))
                    $result['ids'][] = $id;
            }
        }

        if (empty($result['ids'])) {
            if (!empty($result['errIds'])) {
                $msg = sprintf("你所选择的 %d 张单据状态异常，无法打印，本次操作中止！", count($result['errIds']));
            } else {
                $msg = '你没有选择要打印的单据，请重新选择后再试！';
            }
            $this->message($msg);
            exit;
        }

        return $result;
    }

    /**
     * 检查发货单是否可操作
     *
     * @param Integer $dly_id
     * @param String $errMsg  引用，用于返回错误信息
     * @return Boolean
     */
    function checkOrderStatusEx($dly_id, &$errMsg) {
        $Objdly = app::get('ome')->model('delivery');
        $delivery = $Objdly->dump($dly_id);

        //检查发货单状态，如是合并而成的发货单位
        if (!$Objdly->existOrderStatus($dly_id, $delivery['is_bind'])) {
            $errMsg = "发货单已无法操作，请到订单处理中心处理";
            return false;
        }
        if (!$Objdly->existOrderPause($dly_id, $delivery['is_bind'])) {
            $errMsg = "发货单相关订单存在异常，请到订单处理中心处理";
            return false;
        }

        return true;
    }

    /**
     * 防止重复发货和多发货
     * 核对订单中的数量和发货单数量是否匹配
     * 只拦截发货数量大于订单数量的情况
     */
    function checkOrderSendnum($delivery_id){
        $oDelivery = app::get('ome')->model('delivery');
        $oDeliveryOrder = app::get('ome')->model('delivery_order');
        $oOrders = app::get('ome')->model('orders');
        $oDeliveryItems = app::get('ome')->model('delivery_items');

        # 获取订单类型(如果是原样寄回，不对发货数量进行验证)
        $delivery = $oDelivery->getList('type',array('delivery_id'=>$delivery_id),0,1);
        if ($delivery[0]['type'] == 'reject') return true;

        // 查询delivery_id对应的全部订单
        $rs = $oDeliveryOrder->getList('order_id',array('delivery_id|nequal'=>intval($delivery_id)));
        foreach((array)$rs as $v) {
            $order_ids[] = $v['order_id'];

        }
        
        $rs = $oDeliveryOrder->getList('delivery_id',array('order_id'=>$order_ids));
        foreach((array)$rs as $v) {
            $delivery_ids[] = $v['delivery_id'];
        }

        $rs = $oDeliveryOrder->getList('order_id',array('delivery_id'=>$delivery_ids));
        foreach((array)$rs as $v) {
            $order_ids[] = $v['order_id'];
        }

        $order_id = implode(',',$order_ids);

        // 订单的购买数量
        $nums = kernel::database()->selectRow('SELECT SUM(nums) as nums FROM sdb_ome_order_items WHERE order_id IN ('.$order_id.')');
        $nums = $nums['nums'];

        // 实际发货数量
        // 获取所有已经打印的delivery_id
        $rs = $oDelivery->getList('delivery_id',array('delivery_id'=>$delivery_ids,'parent_id'=>0,'status'=>array('succ','progress','ready')));
        unset($delivery_ids);
        foreach((array)$rs as $v) {
            $delivery_ids[] = $v['delivery_id'];
        }
        // $delivery_ids[] = $delivery_id;
        // 获取实际发货数量
        $rs = $oDeliveryItems->getList('number',array('delivery_id'=>$delivery_ids));
        $sendnum = 0;
        foreach((array)$rs as $v) {
            $sendnum += intval($v['number']);
        }

        if($nums<$sendnum) {
            $order_bn = $oOrders->dump(array('order_id'=>$order_ids),'order_bn');
            $order_bn = $order_bn['order_bn'];
            exit("发货数量错误，订单号：$order_bn ；订单数量：$nums 错误的发货单数量：$sendnum ");
        }
    }

    /**
     * 判断发货单号状态是否处于取消或异常或超时或失败或disabled
     *
     * @param bigint $dly_id
     * @return null
     */
    function checkOrderStatus($dly_id) {
        $Objdly = $this->app->model('delivery');
        $delivery = $Objdly->dump($dly_id);

        $this->checkOrderSendnum($dly_id);

        if (!$Objdly->existOrderStatus($dly_id, $delivery['is_bind'])) {
            return true;
        }
        if (!$Objdly->existOrderPause($dly_id, $delivery['is_bind'])) {
            return true;
        }
        return false;
    }

    /**
     * 设置订单样式
     * @param null
     * @return null
     */
    public function showPrintStyle() {
        $this->path[] = array('text' => app::get('ome')->_('订单打印格式设置'));
        $dbTmpl = $this->app->model('print_tmpl_diy');
        $stockPrintTxt = $dbTmpl->get('ome', '/admin/delivery/stock_print');
        $deliveryPrintTxt = $dbTmpl->get('ome', '/admin/delivery/delivery_print');
        $mergePrintTxt = $dbTmpl->get('ome', '/admin/delivery/merge_print');
        $contentPurchase = $dbTmpl->get('purchase', '/admin/purchase/purchase_print');
        $contentPurchaseEo = $dbTmpl->get('purchase', '/admin/eo/eo_print');
        $contentPurchaseReturn = $dbTmpl->get('purchase', '/admin/returned/return_print');

        $this->pagedata['styleContent'] = $stockPrintTxt;
        $this->pagedata['styleContentDelivery'] = $deliveryPrintTxt;
        $this->pagedata['styleContentMerge'] = $mergePrintTxt;
        $this->pagedata['styleContentPurchase'] = $contentPurchase;
        $this->pagedata['styleContentPurchaseEo'] = $contentPurchaseEo;
        $this->pagedata['styleContentPurchaseReturn'] = $contentPurchaseReturn;
        $this->page('admin/delivery/printstyle.html');
    }

    /**
     * 保存订单打印样式
     * @param null
     * @return null
     */
    public function savePrintStyle() {
        $current_print = $_POST['current_print'];
        $dbTmpl = $this->app->model('print_tmpl_diy');
        switch ($current_print) {
            case 'txtContent':
                $dbTmpl->set('ome', '/admin/delivery/stock_print', $_POST["txtContent"]);
                break;
            case 'txtContentDelivery':
                $dbTmpl->set('ome', '/admin/delivery/delivery_print', $_POST["txtContentDelivery"]);
                break;
            case 'txtContentMerge':
                $dbTmpl->set('ome', '/admin/delivery/merge_print', $_POST["txtContentMerge"]);
                break;
            case 'txtContentPurchase':
                $dbTmpl->set('purchase', '/admin/purchase/purchase_print', $_POST["txtContentPurchase"]);
                break;
            case 'txtContentPurchaseEo':
                $dbTmpl->set('purchase', '/admin/eo/eo_print', $_POST["txtContentPurchaseEo"]);
                break;
            case 'txtContentPurchaseReturn':
                $dbTmpl->set('purchase', '/admin/returned/return_print', $_POST["txtContentPurchaseReturn"]);
                break;
        }

        echo "<script>$$('.dialog').getLast().retrieve('instance').close();</script>";
    }

    /**
     * rebackPrintStyle
     *
     * @access public
     * @return void
     */
    public function rebackPrintStyle() {
        $current_print = $_POST['current_print'];
        $dbTmpl = $this->app->model('print_tmpl_diy');
        switch ($current_print) {
            case 'txtContent':
                $dbTmpl->clear('ome', '/admin/delivery/stock_print');
                break;
            case 'txtContentDelivery':
                $dbTmpl->clear('ome', '/admin/delivery/delivery_print');
                break;
            case 'txtContentMerge':
                $dbTmpl->clear('ome', '/admin/delivery/merge_print');
                break;
            case 'txtContentPurchase':
                $dbTmpl->clear('purchase', '/admin/purchase/purchase_print');
                break;
            case 'txtContentPurchaseEo':
                $dbTmpl->clear('purchase', '/admin/eo/eo_print');
                break;
            case 'txtContentPurchaseReturn':
                $dbTmpl->clear('purchase', '/admin/returned/return_print');
                break;
        }

        echo "<script>$$('.dialog').getLast().retrieve('instance').close();</script>";
    }

    /**
     * 根据发货单ID修改它对映的发货配置
     * 一旦打印了任何一种单据就不
     *
     * @author chenping<chenping@shopex.cn>
     */
    private function updateDeliCfg($deliIds,$sku='') {
        $filter = array(
            'delivery_id' => $deliIds,
            'stock_status' => 'false',
            'deliv_status' => 'false',
            'expre_status' => 'false',
        );
        $data = array(
            'deli_cfg' => $sku,
        );
        $deliModel = $this->app->model('delivery');
        $deliModel->update($data,$filter);
    }

    public function orderbycreatetime($sku,$order_val,$op_id){
        $base_url    = 'index.php?app=ome&ctl=admin_receipts_print&act=index';
        $base_url    .= ($sku == 'page_all' ? '&status=' : '&status=0&sku='.$sku);
        
        $this->begin($base_url);

        if($order_val == 1){
            $value = 1;
        }else{
            $value = 0;
        }
        app::get('ome')->setConf('delivery.bycreatetime'.$op_id,$value);
        $this->end(true,'设置成功');
    }

    /**
     * @description 外部物流单号导入页
     * @access public
     * @param void
     * @author chenping<chenping@shopex.cn>
     * @return void
     */
    public function outerLogiIO() 
    {
        $this->display('admin/delivery/outer_logi_io.html');
    }

    /**
     * @description 导出外部运单号模板
     * @access public
     * @param void
     * @author chenping<chenping@shopex.cn>
     * @return void
     */
    public function outerLogiTemplate() 
    {
        $filename = "外部运单号模板".date('Y-m-d').".csv";
        $encoded_filename = urlencode($filename);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        $ua = $_SERVER["HTTP_USER_AGENT"];
        header("Content-Type: text/csv");
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox$/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $obj = $this->app->model('delivery_outerlogi');
        $title = $obj->io_title();
        foreach ($title as $key=>&$value) {
            $value = kernel::single('base_charset')->utf2local($value);
        }
        echo '"'.implode('","',$title).'"';
    }

    public function shipStatus(){
        return array('succ'=> '已发货','unsucc'=> '未发货');#已发货状态以外的，都是未发货
    }

    /**
     * 打印新版快递单
     *
     * 修改 加了一个补打快递单的开关 wujian@shopex.cn 2012年3月14日
     */
    function toPrintExpre($afterPrint=true) {
        $_err = 'false';

        /* 单品、多品标识 */
        $sku = kernel::single('base_component_request')->get_get('sku');
        $sku = $sku ? $sku : '';

        $now_print_type = 'ship';

        //获取当前待打印的发货单过滤条件
        $filter_condition = $this->getPreparePrintIds();

        $PrintLib = kernel::single('ome_delivery_print');
        $print_data = $PrintLib->getPrintDatas($filter_condition,$now_print_type,$sku,$afterPrint,$msg);
        if(isset($msg['error_msg']) && $msg['error_msg']){
            $this->headerErrorMsgDisply($msg['error_msg']);
        }

        if(isset($msg['warn_msg']) && $msg['warn_msg']){
            $this->message($msg['warn_msg']);
            exit;
        }
        
        $waybillObj = kernel::single('logisticsmanager_service_waybill');
        $channel_extObj =  app::get("logisticsmanager")->model("waybill_extend");
        $deliveryObj = $this->app->model('delivery');
        $dlyBillObj = $this->app->model('delivery_bill');
        $ids = $print_data['ids'];

        //防止并发打印重复获取运单号
        $_inner_key = sprintf("print_ids_%s", md5(implode(',',$ids)));
        $aData = cachecore::fetch($_inner_key);
        if ($aData === false) {
            cachecore::store($_inner_key, 'printed', 5);
        }else{
            $this->message("选中的发货单已在打印快递单中，请不要重复打印！！！如没有打印完成，请稍后重试！！！");
            exit;
        }

        //电子面单处理流程
        if ($ids) {
            $channel_info = kernel::single('ome_delivery_print_ship')->getWaybillType($ids[0]);
            //非补打处理流程
            if($afterPrint){
                //如果是补打的不走直连一个个取，因为是以订单号为唯一的，需人工添加运动号
                //如果是直连电子面单，判断运单号是否足够，不够的直连去取运单号,只能单个单个取，取完后重新刷新当前页面重新走当前控制器
                if ($channel_info['type'] == 'direct') {
                    //检查要打的快递单运单号是否都有了
                    $zlFinish = kernel::single('ome_delivery_print_ship')->checkAllHasLogiNo($ids);
                    if ($zlFinish == false && !isset($_GET['isdown'])) {
                        $this->getElectronLogiNo($_GET, $ids, $channel_info);
                    }
                }

                //判断当前发货单非直连取运单，从缓存池取到后立即更新
                foreach($print_data['deliverys'] as $k => $dly) {
                    if (!$dly['logi_no'] && $channel_info['type'] != 'normal') {
                        if (in_array($channel_info['channel_type'],array('sto'))) {
                            //从缓存库获取电子面单
                            $tmp_logi_no = '';
                            $wbParams = array(
                                'channel_id' => $channel_info['channel_id'],
                            );
                            $tmp_logi_no = $waybillObj->get_waybill($wbParams);
                            if($tmp_logi_no){
                                $deliveryObj->db->exec("update sdb_ome_delivery set logi_no='".$tmp_logi_no."' where delivery_id =".$dly['delivery_id']." and (logi_no is null or logi_no ='')");
                                $logiUpdate = $deliveryObj->db->affect_row();
                                if($logiUpdate > 0){
                                    $deliveryObj->updateOrderLogi($dly['delivery_id'],array('logi_no'=>$tmp_logi_no));
                                    $print_data['deliverys'][$k]['logi_no'] = $tmp_logi_no;
                                    $dly['logi_no'] = $tmp_logi_no;
                                }else{
                                    //如果取物流单号更新失败，则标记该发货单不打印，记录错误信息
                                    $print_data['errIds'][] = $dly['delivery_id'];
                                    $print_data['errBns'][$dly['delivery_id']] = $dly['delivery_bn'];
                                    $print_data['errInfo'][$dly['delivery_id']] = '更新电子面单号:'.$tmp_logi_no.'失败，请检查此单号是否已使用！';
                                    unset($print_data['deliverys'][$k]);
                                    if($key = array_search($dly['delivery_id'],$ids)){
                                        unset($print_data['ids'][$key]);
                                    }
                                    continue;
                                }
                                usleep(1000);
                            }
                            unset($wbParams);
                        
                            //判断是否有大头笔申通，否则去获取
                            if ($tmp_logi_no && $logiUpdate) {
                                $channel_ext = $channel_extObj->get_position($tmp_logi_no);
                                if ($channel_ext['position']=='') {
                                    $channelTypeObj = kernel::single('logisticsmanager_service_' . $channel_info['channel_type']);
                                    $delivery_params = array('delivery_id'=>$dly['delivery_id'],'logi_no'=>$tmp_logi_no);
                                    $channelTypeObj->delivery($delivery_params);
                                }
                            }
                        }else{
                            //获取店铺信息
                            $shopObj = app::get("ome")->model('shop');
                            $shopInfo = $shopObj->dump(array('shop_id' => $dly['shop_id']), 'shop_type,addon');
                            if ($channel_info['channel_type']=='ems'  || ($channel_info['channel_type']=='wlb' && $channel_info['shop_id'] == $dly['shop_id']) || ($channel_info['channel_type'] == '360buy' && $shopInfo['addon']['type'] == 'SOP')) {
                                $wbParams = array(
                                    'channel_id' => $channel_info['channel_id'],
                                );
                                //打一次接口获取电子面单
                                if(!$waybillRpc[$channel_info['channel_id']]) {
                                    $waybillObj->request_waybill($wbParams);
                                    $waybillRpc[$channel_info['channel_id']] = true;
                                }
                                //从缓存库获取电子面单
                                $logi_no = '';
                                $logi_no = $waybillObj->get_waybill($wbParams);
                                if($logi_no){
                                    $deliveryObj->db->exec("update sdb_ome_delivery set logi_no='".$logi_no."' where delivery_id =".$dly['delivery_id']." and (logi_no is null or logi_no ='')");
                                    $logiUpdate = $deliveryObj->db->affect_row();
                                    if($logiUpdate > 0){
                                        $deliveryObj->updateOrderLogi($dly['delivery_id'],array('logi_no'=>$logi_no));
                                        $print_data['deliverys'][$k]['logi_no'] = $logi_no;
                                        $dly['logi_no'] = $logi_no;
                                    }else{
                                        $print_data['errIds'][] = $dly['delivery_id'];
                                        $print_data['errBns'][$dly['delivery_id']] = $dly['delivery_bn'];
                                        $print_data['errInfo'][$dly['delivery_id']] = '更新电子面单号:'.$logi_no.'失败，请检查此单号是否已使用！';
                                        unset($print_data['deliverys'][$k]);
                                        if($key = array_search($dly['delivery_id'],$ids)){
                                            unset($print_data['ids'][$key]);
                                        }
                                        continue;
                                    }
                                    usleep(1000);
                                } else {
                                    $print_data['errIds'][] = $dly['delivery_id'];
                                    $print_data['errBns'][$dly['delivery_id']] = $dly['delivery_bn'];
                                    $print_data['errInfo'][$dly['delivery_id']] = '获取电子面单号失败！';
                                    unset($print_data['deliverys'][$k]);
                                    if($key = array_search($dly['delivery_id'],$ids)){
                                        unset($print_data['ids'][$key]);
                                    }
                                    continue;
                                }
                                unset($wbParams);
                            }elseif ($channel_info && ($channel_info['channel_type'] == 'taobao')) {
                                //直连电子面单
                                $wbParams = array(
                                    'channel_id' => $channel_info['channel_id'],
                                );
                            }else {
                                $print_data['errIds'][] = $dly['delivery_id'];
                                $print_data['errBns'][$dly['delivery_id']] = $dly['delivery_bn'];
                                $print_data['errInfo'][$dly['delivery_id']] = '未找到获取电子面单号的来源！';
                                unset($print_data['deliverys'][$k]);
                                if($key = array_search($dly['delivery_id'],$ids)){
                                    unset($print_data['ids'][$key]);
                                }
                                continue;
                            }
                        }
                    }

                    //如果运单号有，是申通没大头笔信息就去获取一次
                    if (in_array($channel_info['channel_type'],array('sto'))) {
                        $channel_ext = $channel_extObj->get_position($dly['logi_no']);
                        if ($channel_ext['position']=='') {
                            $channelTypeObj = kernel::single('logisticsmanager_service_sto' );
                            $delivery_params = array('delivery_id'=>$dly['delivery_id'],'logi_no'=>$dly['logi_no']);
                            $channelTypeObj->delivery($delivery_params);
                        }
                    }
                }
            }elseif(!$afterPrint && count($_REQUEST['log_id'])>0){
                $childrenIds = $_REQUEST['log_id'];
                $main_dly_id = $ids[0];
                $main_dly_bn = $print_data['deliverys'][$main_dly_id]['delivery_bn'];
                $now_shop_id = $print_data['deliverys'][$main_dly_id]['shop_id'];

                //直连的其中顺丰和韵达也取补打物流单
                if ($channel_info['type'] == 'direct') {
                    //检查要打的快递单运单号是否都有了
                    $zlFinish = kernel::single('ome_delivery_print_ship')->checkAllHasLogiNo($main_dly_id,$afterPrint,$childrenIds);
                    if ($zlFinish == false && !isset($_GET['isdown'])) {
                        $this->getElectronLogiNo($_GET, $ids, $channel_info,$afterPrint,$childrenIds);
                    }
                }
                
                foreach((array)$childrenIds as $k => $childrenId){
                    $bill_id = $main_dly_id."-".$childrenId;
                    $bill_bn = $main_dly_bn."-".$childrenId;
                    
                    $children = $dlyBillObj->dump($childrenId,'log_id,logi_no,status');
                    //检查子单状态
                    if($children['status'] && $children['status'] != '0') {
                        $print_data['errIds'][] = $bill_id;
                        $print_data['errBns'][$bill_id] = $bill_bn;
                        $print_data['errInfo'][$bill_id] = '补打的物流单号状态不对，请检查此单号是否为未发货！';
                        continue;
                    }
                    
                    if (!$children['logi_no'] && $channel_info['type'] != 'normal') {
                        if (in_array($channel_info['channel_type'],array('sto'))) {
                            //从缓存库获取电子面单
                            $tmp_logi_no = '';
                            $wbParams = array(
                                'channel_id' => $channel_info['channel_id'],
                            );
                            $tmp_logi_no = $waybillObj->get_waybill($wbParams);
                            if($tmp_logi_no){
                                $logiUpdate = $dlyBillObj->update(array('logi_no'=>$tmp_logi_no), array('log_id'=>$childrenId,'delivery_id'=>$main_dly_id));
                                if(!$logiUpdate) {
                                    $print_data['errIds'][] = $bill_id;
                                    $print_data['errBns'][$bill_id] = $bill_bn;
                                    $print_data['errInfo'][$bill_id] = '更新电子面单号:'.$tmp_logi_no.'失败，请检查此单号是否已使用！';
                                    continue;
                                }
                                usleep(1000);
                            }
                            unset($wbParams);
                        
                            //判断是否有大头笔申通，否则去获取
                            if ($tmp_logi_no) {
                                $channel_ext = $channel_extObj->get_position($tmp_logi_no);
                                if ($channel_ext['position']=='') {
                                    $channelTypeObj = kernel::single('logisticsmanager_service_' . $channel_info['channel_type']);
                                    $delivery_params = array('delivery_id'=>$main_dly_id,'logi_no'=>$tmp_logi_no);
                                    $channelTypeObj->delivery($delivery_params);
                                }
                            }
                        }else{
                            //获取店铺信息
                            $shopObj = app::get("ome")->model('shop');
                            $shopInfo = $shopObj->dump(array('shop_id' => $now_shop_id), 'shop_type,addon');
                            if ($channel_info['channel_type']=='ems'  || ($channel_info['channel_type']=='wlb' && $channel_info['shop_id'] == $now_shop_id) || ($channel_info['channel_type'] == '360buy' && $shopInfo['addon']['type'] == 'SOP')) {
                                $wbParams = array(
                                    'channel_id' => $channel_info['channel_id'],
                                );
                                //打一次接口获取电子面单
                                if(!$waybillRpc[$channel_info['channel_id']]) {
                                    $waybillObj->request_waybill($wbParams);
                                    $waybillRpc[$channel_info['channel_id']] = true;
                                }
                                //从缓存库获取电子面单
                                $tmp_logi_no = '';
                                $tmp_logi_no = $waybillObj->get_waybill($wbParams);
                                if($tmp_logi_no){
                                    $logiUpdate = $dlyBillObj->update(array('logi_no'=>$tmp_logi_no), array('log_id'=>$childrenId,'delivery_id'=>$main_dly_id));
                                    if(!$logiUpdate) {
                                        $print_data['errIds'][] = $bill_id;
                                        $print_data['errBns'][$bill_id] = $bill_bn;
                                        $print_data['errInfo'][$bill_id] = '更新电子面单号:'.$tmp_logi_no.'失败，请检查此单号是否已使用！';
                                        continue;
                                    }
                                    usleep(1000);
                                } else {
                                    $print_data['errIds'][] = $bill_id;
                                    $print_data['errBns'][$bill_id] = $bill_bn;
                                    $print_data['errInfo'][$bill_id] = '获取电子面单号失败！';
                                    continue;
                                }
                                unset($wbParams);
                            }elseif ($channel_info && ($channel_info['channel_type'] == 'taobao')) {
                                //to do
                            }else {
                                $print_data['errIds'][] = $bill_id;
                                $print_data['errBns'][$bill_id] = $bill_bn;
                                $print_data['errInfo'][$bill_id] = '未找到获取电子面单号的来源！';
                                continue;
                            }
                        }
                    }

                    //如果运单号有，是申通没大头笔信息就去获取一次
                    if (in_array($channel_info['channel_type'],array('sto'))) {
                        $channel_ext = $channel_extObj->get_position($children['logi_no']);
                        if ($channel_ext['position']=='') {
                            $channelTypeObj = kernel::single('logisticsmanager_service_sto' );
                            $delivery_params = array('delivery_id'=>$main_dly_id,'logi_no'=>$children['logi_no']);
                            $channelTypeObj->delivery($delivery_params);
                        }
                    }
                    //复制子单重的发货单信息，并记录赋值获取到的当前运单号
                    $print_data['deliverys'][$bill_id] = $print_data['deliverys'][$main_dly_id];
                    $print_data['deliverys'][$bill_id]['logi_no'] = isset($children['logi_no']) ? $children['logi_no'] : $tmp_logi_no;
                    unset($childrenId,$children);
                }
                //子单循环结束将原有主物流单信息删除
                unset($print_data['deliverys'][$main_dly_id]);
            }
        }
        
        $PrintShipLib = kernel::single('ome_delivery_print_ship');
        $format_data = $PrintShipLib->format($print_data, $sku,$_err);
        $this->pagedata = $format_data;

        if ($format_data['delivery']) {
            foreach ($format_data['delivery'] as $val) {
                //获取快递单打印模板的servivce定义
                $data = array();
                foreach (kernel::servicelist('ome.service.template') as $object => $instance) {
                    if (method_exists($instance, 'getElementContent')) {
                        $tmp = $instance->getElementContent($val);
                    }
                    $data = array_merge($data, $tmp);
                }
                $mydata[] = $data;
            }
        }

        $jsondata = $PrintShipLib->arrayToJson($mydata);

        //组织控件打印数据
        $this->pagedata['jsondata'] = $jsondata;
        $this->pagedata['data'] = addslashes($deliveryObj->array2xml2($mydata, 'data'));
        $this->pagedata['totalPage'] = count($mydata);
        
        $templateObj = app::get("logisticsmanager")->model('express_template');
        //获取快递面单
        $this->pagedata['printTmpl'] = $templateObj->dump($format_data['dly_tmpl_id']);
        /* 修改的地方 */
        if ($this->pagedata['printTmpl']['file_id']) {
            $this->pagedata['tmpl_bg'] = 'index.php?app=ome&ctl=admin_delivery_print&act=showPicture&p[0]=' . $this->pagedata['printTmpl']['file_id'];
        }

        //获取有问题的单据号
        $this->pagedata['errBns'] = $print_data['errBns'];
        $this->pagedata['err'] = $_err;

        //批次号
        $this->pagedata['idents'] = $print_data['identInfo']['items'];
        $this->pagedata['ident'] = join(',', $print_data['identInfo']['idents']);
        $this->pagedata['errIds'] = $print_data['errIds'];
        $this->pagedata['errInfo'] = $print_data['errInfo'];
        $items = array();
        foreach ($format_data['delivery'] as $row) {
            $items[$row['delivery_id']] = $row;
        }

        $this->pagedata['items'] = $items;
        $this->pagedata['sku'] = $sku;//单品 多品标识
        $this->pagedata['dpi'] = 96;
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['title'] = '快递单打印';
        $this->pagedata['uniqid'] = uniqid();

        if(!$afterPrint){
            $this->pagedata['log_id'] = $_REQUEST['log_id'];

            $billFilter = array(
                'log_id'=>$_REQUEST['log_id'],
            );
            $this->pagedata['bill_logi_no'] = $dlyBillObj->getList('log_id,logi_no',$billFilter);
            $this->pagedata['delibery_bill_flag'] = 'delibery_bill_flag';
        }
        
        //获取打印版本配置信息
        $logicfg = kernel::single('ome_print_logicfg')->getLogiCfg();
        if($logicfg[$express_company_no]){
            $logiVersionFlag = 1;
            $this->pagedata['logicfg'] = $logicfg[$express_company_no];
            $print_logi_version = app::get('ome')->getConf('print_logi_version_'.$this->pagedata['print_logi_id']);

            $this->pagedata['print_logi_version'] = intval($print_logi_version);
        }
        $this->pagedata['logiVersionFlag'] = $logiVersionFlag;
        $params = array('order_bn'=>$this->pagedata['o_bn']);
        ome_print_tmpl_express::instance($express_company_no,$this)->setParams($params)->getTmpl();
    }

    /**
     * 获取电子面单运单号
     */
    public function getElectronLogiNo($params, $ids, $channel,$afterprint = true,$childrens ='') {
        $urlParams = json_encode($params);
        $postIds = json_encode($ids);
        $request_uri = kernel::single('base_component_request')->get_request_uri() . '&isdown=1';
        
        $this->pagedata['urlParams'] = $urlParams;
        $this->pagedata['postIds'] = $postIds;
        //$this->pagedata['count'] = $count;
        $this->pagedata['channel'] = $channel;
        //非补打标记为1，补打为2
        $this->pagedata['afterprint'] = ($afterprint == true) ? 1 : 2;
        $this->pagedata['request_uri'] = base64_encode($request_uri);
        if($afterprint){
            $this->singlepage('admin/delivery/controllertmpl/getelectronlogino.html');exit;
        }else{
            $cIds = json_encode($childrens);
            $this->pagedata['cIds'] = $cIds;
            $this->singlepage('admin/delivery/controllertmpl/getelectronlogino_bill.html');exit;
        }
    }

    /**
     *运单号异步页面
     */
    public function async_logino_page() {
        $channel_id = $_GET['channel_id'];
        $request_uri = base64_decode($_GET['request_uri']);
        $this->pagedata['channel_id'] = $channel_id;
        $this->pagedata['request_uri'] = $request_uri;

        $ids = explode(',', urldecode($_GET['itemIds']));

        //2为补打
        if($_GET['after_print'] == 2){
            $afterprint = $_GET['after_print'];
            $cIds = explode(',', urldecode($_GET['cIds']));
            $count = count($cIds);
            $this->pagedata['count'] = $count;
            $this->pagedata['postIds'] = json_encode($cIds);
            $this->pagedata['delivery_id'] = $ids[0];
            $this->display('admin/delivery/controllertmpl/async_bill_logino_page.html');
        }else{
            $this->pagedata['postIds'] = json_encode($ids);
            $count = count($ids);
            $this->pagedata['count'] = $count;
            $this->display('admin/delivery/controllertmpl/async_logino_page.html');
        }
    }

    public function getWaybillLogiNo() {
        $waybillObj = kernel::single('logisticsmanager_service_waybill');
        $channel_id = $_POST['channel_id'];
        $delivery_id = $_POST['id'];
        #检查发货单是否已经获取运单号
        $params = array(
            'delivery_id' => $delivery_id,
            'channel_id' => $channel_id
        );
        $result  = $waybillObj->getWaybillLogiNo($params);
        if ($result['rsp'] == 'succ' && $result['data'][0]['logi_no']) {
            $result = array(
                'rsp' => 'succ',
                'logi_no' => trim($result['data'][0]['logi_no']),
                'delivery_id' => $delivery_id,
                'delivery_bn' => $result['data'][0]['delivery_bn']
            );
        }
        else {
            $result = array(
                'rsp' => 'fail',
                'delivery_id' => $delivery_id,
                'delivery_bn' => $result['data'][0]['delivery_bn']
            );
        }
        echo json_encode($result);
    }

    public function getExtLogiNo() {
        $waybillObj = kernel::single('logisticsmanager_service_waybill');
        $channel_id = $_POST['channel_id'];
        $c_id = $_POST['cid'];
        $d_id = $_POST['did'];
        #检查发货单是否已经获取运单号
        $params = array(
            'c_id' => $c_id,
            'delivery_id' => $d_id,
            'channel_id' => $channel_id
        );
        $result  = $waybillObj->getWaybillLogiNo($params);
        if ($result['rsp'] == 'succ' && $result['data'][0]['logi_no']) {
            $result = array(
                'rsp' => 'succ',
                'logi_no' => $result['data'][0]['logi_no'],
                'delivery_id' => $d_id,
                'log_id' => $c_id,
            );
        }
        else {
            $result = array(
                'rsp' => 'fail',
                'delivery_id' => $d_id,
                'log_id' => $c_id,
            );
        }
        echo json_encode($result);
    }
    
    /*
     * 获取当前准备打印的发货单号
     */
    public function getPreparePrintIds(){
        $delivery_ids = $_REQUEST['delivery_id'];
        $isSelectAll = $_REQUEST['isSelectedAll'];
        $printIds = array('filter'=>'');

        $filter = $this->processFilter();
        
        //待打印,为避免重复打印，在后台，把相关打印字段加入到过滤条件中
        $filter_sql = null;
        if(($_GET['status']==='0') && ($_GET['sku'] == '')){
            $filter_sql =  $this->getfiltersql();
        }

        if ($isSelectAll == '_ALL_') {
            if($filter_sql){
                $filter['filter_sql'] = $filter_sql;
            }

            $printIds['filter'] = $filter;
            return $printIds;

        }else {
            //去除值 为空，null，FALSE的key和value
            $delivery_ids = array_filter($delivery_ids);
            if ($delivery_ids) {
                if (is_array($delivery_ids)) {
                    $filter['delivery_id'] = $delivery_ids;
                    if($filter_sql){
                        $filter['filter_sql'] = $filter_sql;
                    }

                    $printIds['filter'] = $filter;
                    return $printIds;

                }else {
                    $printIds['filter'] = array('delivery_id'=>$delivery_ids);
                    return $printIds;
                }
            } else {
                $this->headerErrorMsgDisply("请选择数据");
            }
        }
    }

    /**
     * 返回物流公司来源.
     * @param   delivery_id
     * @return  array
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function getDeliveryType($id) {
        $deliveryObj = $this->app->model('delivery');
        $channelObj = app::get("logisticsmanager")->model("channel");
        $dlyCorpObj = $this->app->model('dly_corp');
        $data = $deliveryObj->dump($id, '*');
        $dlyCorp = $dlyCorpObj->dump($data['logi_id'], 'prt_tmpl_id,type,tmpl_type,channel_id,shop_id');
        $tpye = 'normal';
        //获取电子面单渠道
        $type = 'normal';
        $channel = array('type' => 'normal');
        
        if ($dlyCorp['tmpl_type'] == 'electron') {
            $cFilter = array(
                'channel_id' => $dlyCorp['channel_id'],
                'status'=>'true',
            );
            $channel = $channelObj->dump($cFilter);
            
        }
        return $channel;
    }

    /**
     * 获取物流单号
     * @param  
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_bill_no()
    {
        $db = kernel::database();
        $waybillObj = kernel::single('logisticsmanager_service_waybill');
        $sql = 'SELECT * FROM sdb_logisticsmanager_channel WHERE channel_type="sto" AND bind_status="true"';
        $channel = $db->select($sql);
        echo '获取开始<br>';
        if ($channel) {
            foreach ($channel as $info ) {
                if ( $info && in_array($info['channel_type'],array('sto'))) {
                    $limit = 5000;
                    $page = ceil($limit/100);
                    for($i=0;$i<$page;$i++){
                        //获取电子面单后去更新
                        $wbParams = array(
                            'channel_id' => $info['channel_id'],
                        );
                        $waybillObj->request_waybill($wbParams);
                        unset($wbParams);
                        usleep(500000);
                    }
                }
                
            }
            
        }
         echo '获取结束';
    }
    
    //[拆单]显示发货单基础物料详情
    public function show_delivery_items()
    {
        $dly_id    = intval($_REQUEST['id']);
        if(empty($dly_id))
        {
            die('无效操作！');
        }
        
        $basicMaterialObj = app::get('material')->model('basic_material');#基础物料_主表
        $materialExtObj   = app::get('material')->model('basic_material_ext');#基础物料_扩展表
        
        $dlyObj = app::get('ome')->model('delivery');
        
        $items  = $dlyObj->getItemsByDeliveryId($dly_id);
        
        /*获取货品优惠金额*/
        $dlyorderObj = app::get('ome')->model('delivery_order');
        $dly_order = $dlyorderObj->getlist('*',array('delivery_id'=>$dly_id),0,-1);

        $pmt_orders = $dlyObj->getPmt_price($dly_order);
        $sale_orders = $dlyObj->getsale_price($dly_order);

        $pmt_order = array();
        if($items)
        {
            foreach ($items as $key => $item)
            {
                //将商品的显示名称改为后台的显示名称
                $productInfo    = $basicMaterialObj->dump(array('material_bn'=>$items[$key]['bn']), 'bm_id, material_bn, material_name');
                
                $basicMaterialExt    = $materialExtObj->dump(array('bm_id'=>$productInfo['bm_id']), 'specifications');
                
                $items[$key]['spec_info'] = $basicMaterialExt['specifications'];
                $items[$key]['product_name'] = $productInfo['material_name'];
                
                $items[$key]['pmt_price'] = $pmt_order[$items[$key]['bn']]['pmt_price'];
                $items[$key]['sale_price'] = ($sale_orders[$items[$key]['bn']]*$item['number'])-$pmt_order[$items[$key]['bn']]['pmt_price'];
    
                $items[$key]['price'] = $sale_orders[$items[$key]['bn']];
    
            }
        }
        $this->pagedata['items'] = $items;
        $this->singlepage('admin/delivery/show_delivery_items.html');
    }
}
