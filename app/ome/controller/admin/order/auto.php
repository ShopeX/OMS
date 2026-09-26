<?php
/**
 * 订单获取
 */
class ome_ctl_admin_order_auto extends desktop_controller {

    /**
     * 订单获取模块名称
     * @var String
     */
    const __ORDER_APP = 'ome';

    /**
     * 获取订单，其中隐藏自动确认规则
     *
     * @param void
     * @return void
     */
    function index() {
        if(app::get('replacesku')->is_installed()){
            $oOrders = app::get('ome')->model('orders');
            $order_list = $oOrders->getlist('order_id',array('is_fail'=>'true'));
            $order_list_count = count($order_list);
            $sku_tran = new replacesku_order;
            echo '共有符合条件的待转换订单数:'.count($order_list).'条记录<br>';
            $tran_mess=$sku_tran->transform_sku($order_list);
            echo '失败订单:'.$tran_mess['fail'].' 成功:'.$tran_mess['succ'].' 其它:'.$tran_mess['other'];
        }
        // 判断是否有执行中的
        $batchLogModel = app::get('ome')->model('batch_log');
        $batchLog = $batchLogModel->dump(array('log_type'=>'ordertaking','source'=>'direct','status'=>array('0','2')));
        if ($batchLog) {
            $log_text = unserialize($batchLog['log_text']);
            $orderGroup = array();
            foreach ($log_text as $val) {
                $idx = $val['hash'].'||'.$val['idx'];
                $orderGroup[$idx]['orders'] = implode(',',$val['orders']);
                $orderGroup[$idx]['cnt'] = count($val['orders']);
            }

            $this->pagedata['process_id'] = $batchLog['log_id'];
        } else {
            $orderAuto = new omeauto_auto_combine();
            $orderGroup = $orderAuto->getBufferGroup();            
        }

        $orderCnt = 0;
        $orderGroupCnt = 0;
        $orderGroupOrdCnt = 0;

        //计数
        foreach ($orderGroup as $key=>$group) {
            if ($group['cnt'] > 1) {
                $orderGroupCnt++;
                $orderGroupOrdCnt += $group['cnt'];
            }
            $orderCnt += $group['cnt'];
        }

        $bufferOrderCnt = app::get('ome')->model('orders')->count(array('order_confirm_filter' => '(op_id IS NULL AND group_id IS NULL AND (is_cod=\'true\' or pay_status in (\'1\',\'4\',\'5\')))', 'status' => 'active', 'ship_status' => '0', 'f_ship_status' => '0', 'confirm' => 'N', 'abnormal' => 'false', 'is_auto' => 'false', 'is_fail' => 'false', 'pause'=>'false', 'order_type|in' => kernel::single('ome_order_func')->get_normal_order_type(),'is_delivery'=>'Y'));

        $this->pagedata['bufferTime'] = omeauto_auto_combine::getCnf('bufferTime');
        $this->pagedata['bufferOrderCnt'] = $bufferOrderCnt;
        $this->pagedata['orderCnt'] = $orderCnt;
        $this->pagedata['orderGroup'] = json_encode($orderGroup);
        $this->pagedata['orderGroupOrdCnt'] = $orderGroupOrdCnt;
        $this->pagedata['orderGroupCnt'] = $orderGroupCnt;
        #全境判断
        $all_dlycorp = kernel::single('logistics_dly_corp')->fetchDefaultRoles();
        #是否提醒
        $allDlycorpnotify = app::get('ome')->getConf('allDlycorp.status');

        /* 操作时间间隔 start */
        $lastGetOrder = app::get('ome')->getConf('lastGetOrder'); //上次获取订单信息(key为execTime表示执行时间，key为pageBn表示页面编号),
        $getOrderIntervalTime = app::get('ome')->getConf('ome.getOrder.intervalTime'); //每次操作的时间间隔

        if(($lastGetOrder['execTime']+60*$getOrderIntervalTime)<time()){
            $this->pagedata['is_allow'] = true;
        }else{
            $this->pagedata['is_allow'] = false;
        }
        $this->pagedata['lastGetOrderTime'] = !empty($lastGetOrder['execTime']) ? date('Y-m-d H:i:s',$lastGetOrder['execTime']) : '';
        $this->pagedata['getOrderIntervalTime'] = $getOrderIntervalTime;
        $this->pagedata['currentTime'] = time();
        /* 操作时间间隔 end */

        $shopList = app::get('ome')->model('shop')->getList('*', array('s_type'=>1), 0, -1);
        $this->pagedata['shopList'] = $shopList;

        $this->pagedata['allDlycorpnotify'] = $allDlycorpnotify;

        $this->pagedata['all_dlycorp'] = $all_dlycorp;

        //增加所需配置检查
        $config = $this->chkConfig();
        if(count($config)>0){
            $this->pagedata['config'] = $config;
        }
        $this->display('admin/order/auto.html');
    }

    /**
     * 检查自动审单的配置
     *
     * @param void
     * @return void
     */
    private function chkConfig() {
        $result = array();
        //检查订单分组
        $otCount = app::get('omeauto')->model('order_type')->count(array('disabled' => 'false'));
        if ($otCount == 0) {
            $result['noOrderDefined'] = true;
        }
        //检查自动确认规则
        $acCount = app::get('omeauto')->model('autoconfirm')->count(array('defaulted' => 'true','disabled' => 'false'));
        if ($acCount == 0) {
            //没有设置审单规则，自动增加默认规则
            $result['noAutoConfirmDefaultRole'] = true;
        }
        /*else if (!$this->chkDefaultAutoConfirmRole()) {
            //有设置审单规则，但没有增加默认规则，自动增加
            $result['noAutoConfirmDefaultRole'] = true;
            $result['aId'] = $this->addDefaultAutoConfirmRole();
        }*/
        //检查自动分派规则
        /*$adCount = app::get('omeauto')->model('autodispatch')->count(array('disabled' => 'false'));
        if ($adCount == 0) {
            $result['noAutoDispatchRole'] = true;
        }*/
        //检查仓库分配规则
        $abCount = app::get('ome')->model('branch')->count(array('disabled' => 'false', 'online' => 'true'));
        if ($abCount == 0) {
            //没有设置线上仓库
            //$result['noDlyConfig'] = $this->chkDlyCorp(true);
            $result['noAutoBranchRole'] = true;
        }
        /*else if ($abCount == 1){
            //一个线上仓库
            //默认发货，可不提醒
            $result['noDlyConfig'] = $this->chkDlyCorp(true);
        } else {
            //有多仓
            $result['noAutoMutiBranchRole'] = true;
            $result['noDlyConfig'] = $this->chkDlyCorp(false);
        }*/

        return $result;
    }

    /**
     * 检查物流公司
     *
     * @param boolean $singleBranch
     * @return void
     */
    private function chkDlyCorp($singleBranch) {

    }

    /**
     * 检查是否有默认审单规则
     *
     * @param void
     * @return void
     */
    private function chkDefaultAutoConfirmRole() {

        $config = omeauto_auto_group::fetchDefaultRoles();

        if (empty($config)) {

            return false;
        } else {

            return true;
        }
    }

    /**
     * 增加默认自动审单规则
     *
     * @param void
     * @return void
     */
    private function addDefaultAutoConfirmRole() {

        $config = kernel::single('omeauto_auto_group')->getDefaultRoles();
        $sdf = array(
            'name' => '默认审单规则',
            'config' => $config,
            'memo' => '默认审单规则',
            'disabled' => 'false',
            'defaulted' => 'true'
        );
        app::get('omeauto')->model('autoconfirm')->save($sdf);

        return $sdf['oid'];
    }

    /**
     * 获取处理结果
     *
     * @return void
     * @author 
     **/
    public function getResult($process_id)
    {
        $batchLogModel = app::get('ome')->model('batch_log');
        $batchLog = $batchLogModel->dump($process_id);
        $log_text = unserialize($batchLog['log_text']);
        $all = 0;
        foreach ($log_text as $val) {
            $all += count($val['orders']);
        }
        
        if (time()>($batchLog['createtime']+600) ) {
            $batchLogModel->update(array('status'=>'1'),array('log_id'=>$process_id));

            $batchLog['status'] = '1';
        }
        
        $result['all']    = $all;
        $result['total']  = $batchLog['batch_number'];
        $result['fail']   = $batchLog['fail_number'];
        $result['succ']   = $batchLog['succ_number'];
        $result['status'] = $batchLog['status']=='1' ? 'finish' : 'running';

        echo json_encode($result);exit;
    }


    /**
     * AJAX 调用过程，用来处理指定数量的订单组
     *
     * @author hzjsq (2011/3/24)
     * @param void
     * @return void
     */
    function ajaxDoAuto() {
        //获取参数
        //danny_freeze_stock_log
        define('FRST_TRIGGER_OBJECT_TYPE','发货单：获取订单自动生成发货单');
        define('FRST_TRIGGER_ACTION_TYPE','ome_ctl_admin_order_auto：index');
        $params = $this->_parseAjaxParams($_POST['ajaxParams']);
        if (empty($params)) {
            echo $this->_ajaxRespone();
            exit;
        }

        /* 执行时间判断 start */
        $pageBn = intval($_POST['pageBn']);
        $lastGetOrder = app::get('ome')->getConf('lastGetOrder'); //上次获取订单信息(key为execTime表示执行时间，key为pageBn表示页面编号),
        $getOrderIntervalTime = app::get('ome')->getConf('ome.getOrder.intervalTime'); //每次操作的时间间隔

        if($pageBn !=$lastGetOrder['pageBn'] && ($lastGetOrder['execTime']+60*$getOrderIntervalTime)>time()){
            echo $this->_ajaxRespone();
            exit;
        }
        if($pageBn !=$lastGetOrder['pageBn'] && $pageBn<$lastGetOrder['execTime']){
            echo $this->_ajaxRespone();
            exit;
        }

        $sign = md5($_POST['ajaxParams']);
        if ($pageBn == $lastGetOrder['pageBn'] && $sign == $lastGetOrder['sign']) {
            echo $this->_ajaxRespone();
            exit;
        }
        // // 判断是否正在进行中的
        $batchLogModel = app::get('ome')->model('batch_log');
        $batchLogExist = $batchLogModel->count(array('log_type'=>'ordertaking','status'=>array('0','2'),'createtime|than'=>strtotime('-5min')));
        if ($batchLogExist) {
            echo $this->_ajaxRespone();
            exit;
        }

        //记录本次获取订单时间
        $currentGetOrder = array(
            'execTime'=>time(),
            'pageBn'=>$pageBn,
            'sign'     => $sign,
        );

        app::get('ome')->setConf('lastGetOrder',$currentGetOrder);
        /* 执行时间判断 end */
        $batch_number = 0;
        foreach ($params as $val) {
            $batch_number += count($val['orders']);
        }
        // 保存
        $op = kernel::single('ome_func')->getDesktopUser();
        $batchLog = array(
            'createtime'   => time(),
            'op_id'        => $op['op_id'],
            'op_name'      => $op['op_name'],
            'batch_number' => $batch_number,
            'fail_number'  => 0,
            'status'       => '0',
            'log_type'     => 'ordertaking',
            'log_text'     => serialize($params),
        );
        $batchLogModel->save($batchLog);
        // 手动获取进队列

        if ('true'!=app::get('ome')->getConf('ome.order.is_auto_combine')) {
            if (defined('SAAS_COMBINE_MQ') && SAAS_COMBINE_MQ == 'true') {

                $mq = kernel::single('base_queue_mq');
                $mq->connect($GLOBALS['_MQ_COMBINE_CONFIG'], 'TG_COMBINE_EXCHANGE', 'TG_COMBINE_QUEUE');

                foreach (array_chunk($params, 5) as $param) {
                    $push_params = array(
                        'orderidx'  => json_encode($param),
                        'task_type' => 'ordertaking',
                        'log_id'    => $batchLog['log_id'],
                    );
                    $push_params['taskmgr_sign']      =  taskmgr_rpc_sign::gen_sign($push_params);

                    $postAttr = array();
                    foreach ($push_params as $key => $val) {
                        $postAttr[] = $key . '=' . urlencode($val);
                    }

                    $data = array();
                    $data['spider_data']['url'] = kernel::openapi_url('openapi.autotask','service');

                    $data['spider_data']['params']    = empty($postAttr) ? '' : join('&', $postAttr);
                    $data['relation']['to_node_id']   = base_shopnode::node_id('ome');
                    $data['relation']['from_node_id'] = '0';
                    $data['relation']['tid']          = 'ordertaking'.$batchLog['log_id'];
                    $data['relation']['to_url']       = $data['spider_data']['url'];
                    $data['relation']['time']         = time();

                    $routerKey = 'tg.order.combine.'.$data['nodeId'];

                    $message = json_encode($data);
                    $mq->publish($message, $routerKey);
                }
                $mq->disconnect();

                echo $this->_ajaxRespone(array('status'=>'running','process_id'=>$batchLog['log_id']));exit;
            } else {
                foreach (array_chunk($params, 5) as $param) {
                    $push_params = array(
                        'data' => array(
                            'orderidx'  => json_encode($param),
                            'log_id'    => $batchLog['log_id'],
                            'task_type' => 'ordertaking'
                        ),
                        'url' => kernel::openapi_url('openapi.autotask','service')
                    );            
                    kernel::single('taskmgr_interface_connecter')->push($push_params);
                }

                echo $this->_ajaxRespone(array('status'=>'running','process_id'=>$batchLog['log_id']));exit;
            }
        }

        echo $this->_ajaxRespone();exit;

        //订单预处理
        // $preProcessLib = new ome_preprocess_entrance();
        // $preProcessLib->process($params,$msg);

        // //开始自动确认
        // $orderAuto = new omeauto_auto_combine();
        // //开始处理
        // $result = $orderAuto->process($params);
        // echo $this->_ajaxRespone($result);
    }

    /**
     * 解析AJAX传过来的信息
     *
     * @param String $string 原始内容
     * @return mixed
     * @author hzjsq (2011/3/25)
     */
    private function _parseAjaxParams($string) {

        $string = trim($string);
        //分解成数组
        if (strpos($string, ';')) {

            $params = explode(';', $string);
        } else {

            $params = array($string);
        }

        //继续分解成可以处理的数组内容
        $result = array();
        foreach ($params as $key => $param) {

            $tmp = explode('||', $param);

            $result[] = array('idx' => $tmp[1], 'hash' => $tmp[0], 'orders' => explode(',', $tmp[2]));
        }
        return $result;
    }

    /**
     *  对输入的内容进行格式化输出至AJAX
     *
     * @author hzjsq (2011/3/24)
     * @param Mixed $param 要转换的内容
     * @return String
     */
    private function _ajaxRespone($param = array()) {

        if (empty($param)) {

            return json_encode(array('total' => 0, 'succ' => 0, 'fail' => 0,'status'=>'finish'));
        } else {

            return json_encode($param);
        }
    }

    /**
     * 匹配所有未对应的商品
     *
     * @param void
     * @return void
     */
    public function product() {

        echo "<h1>正在开发中……</h1>";
    }

    public function notify_allDlycorp(){
        $this->page('admin/order/notify_allDlycorp.html');
    }

    /**
    * 不再提醒
    */
    function notify(){
        $is_super = kernel::single('desktop_user')->is_super();
        if($is_super){
            echo app::get('ome')->setConf('allDlycorp.status', 2);

        }
    }

    /**
     * 获取自动处理的数据
     */
    public function ajaxGetAutoData(){
        $filter['shop_id'] = $_POST['shopId'];
        
        //订单类型
        $order_type    = ($_POST['order_type'] ? $_POST['order_type'] : 'all');
        if($order_type == 'normal')
        {
            $filter['order_type'] = 'normal';
        }
        elseif($order_type == 'presale')
        {
            $filter['order_type'] = 'presale';
        }
        
        $orderAuto = new omeauto_auto_combine();
        $orderGroup = $orderAuto->getBufferGroup($filter);

        $orderCnt = 0; //本次操作订单
        $orderGroupOrdCnt = 0; //合并前的订单数
        $orderGroupCnt = 0; //合并后的发货单数
        foreach ($orderGroup as $key=>$group) {
            if ($group['cnt'] > 1) {
                $orderGroupCnt++;
                $orderGroupOrdCnt += $group['cnt'];
            }
            $orderCnt += $group['cnt'];
        }

        //缓存区可操作订单
        $bufferFilter = array('order_confirm_filter' => '(op_id IS NULL AND group_id IS NULL AND (is_cod=\'true\' or pay_status in (\'1\',\'4\',\'5\')))', 'status' => 'active', 'ship_status' => '0', 'f_ship_status' => '0', 'confirm' => 'N', 'abnormal' => 'false', 'is_auto' => 'false', 'is_fail' => 'false', 'pause'=>'false','is_delivery'=>'Y');
        if($filter['shop_id'] && $filter['shop_id'] != 'all'){
            $bufferFilter['shop_id'] = $filter['shop_id'];
        }
        
        if($filter['order_type'])
        {
            $bufferFilter['order_type'] = $filter['order_type'];
        }
        
        $bufferOrderCnt = app::get('ome')->model('orders')->count($bufferFilter);

        $data = array(
            'OrderGroups'=>$orderGroup,
            'currentTime'=>time(),
            'bufferOrderCnt'=>$bufferOrderCnt,
            'orderCnt'=>$orderCnt,
            'orderGroupOrdCnt'=>$orderGroupOrdCnt,
            'orderGroupCnt'=>$orderGroupCnt,
        );
        echo json_encode($data);
    }

    public function timingConfirm() {
        if($_POST['isSelectedAll'] == '_ALL_') {
            $baseFilter = array(
                'assigned' => 'buffer',
                'abnormal' => 'false',
                'ship_status' => '0',
                'is_fail' => 'false',
                'process_status' => array('unconfirmed', 'is_retrial'),
                'status' => 'active',
                'is_auto' => 'false',
                'order_confirm_filter' => '( op_id IS NULL AND group_id IS NULL)'
            );
            $view = $_POST['view'];
            if ($view == 1) {
                $baseFilter['is_cod'] = 'true';
            } else if ($view == 2) {
                $baseFilter['pay_status'] = array('0', '3');
            } else if ($view == 3) {
                $baseFilter['pay_status'] = 1;
            }
            $param = array_merge($baseFilter, $_POST);
            $objOrder = app::get('ome')->model('orders');
            $objOrder->defaultOrder = '';
            $objOrder->filter_use_like = true;
            $selOrder = $objOrder->getList('order_id', $param, 0, -1);
            $arrOrderId = array();
            foreach($selOrder as $val) {
                $arrOrderId[] = $val['order_id'];
            }
        } else {
            $arrOrderId = $_POST['order_id'];
        }
        $this->pagedata['orderIds'] = $arrOrderId;
        $this->page('admin/order/timing.html');
    }

    public function doTimingConfirm() {
        $timingConfirmTime = strtotime($_POST['timing_confirm_time']);
        $arrOrderId = $_POST['order_id'];
        $modelOrder = app::get('ome')->model('orders');
        $modelOrderObjects = app::get('ome')->model('order_objects');
        //$modelExtend = app::get('ome')->model('order_extend');
        $modelMisc = app::get('ome')->model('misc_task');
        /*$order = $modelOrder->getList('order_id, order_bool_type', array('order_id' => $arrOrderId));
        $arrOrderBool = array();
        foreach($order as $val) {
            $arrOrderBool[$val['order_id']] = $val['order_bool_type'];
        }*/
        foreach($arrOrderId as $orderId) {
            $data = array(
                'order_id' => $orderId,
                'timing_confirm' => $timingConfirmTime
            );
            $modelOrder->db_save($data);
            $modelOrderObjects->update(array('estimate_con_time'=>$timingConfirmTime), array('order_id'=>$orderId));
            $data = array(
                'obj_id' => $orderId,
                'obj_type' => 'timing_confirm_order',
                'exec_time' => $timingConfirmTime
            );
            $modelMisc->saveMiscTask($data);
        }
        
        // 是否允许自动审单
        if($_POST['is_auto_timing_confirm_order'] == 'false') {
            $prop_error_msg = '';
            $orderIds = $arrOrderId;
            $propSdf = ['props_col'=>'auto_timing_confirm_order', 'props_value'=>'false'];
            $propsSaved = kernel::single('ome_order_props')->saveOrderProps($orderIds, $propSdf, $prop_error_msg);
        }
        
        app::get('ome')->model('operation_log')->batch_write_log('order_edit@ome', array('order_id' => $arrOrderId),'设置定时审单时间：' . $_POST['timing_confirm_time'], time());
        $this->splash('success');
    }
}