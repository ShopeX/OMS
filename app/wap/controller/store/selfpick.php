<?php

/**
 * 门店自提订单（数据来源：sdb_wap_delivery，物流方式为 o2o_pickup）
 */
class wap_ctl_store_selfpick extends wap_controller
{
    var $delivery_type = 'pending';

    var $selfpick_link = array();

    function __construct($app)
    {
        parent::__construct($app);
        $this->_initOrderActionLinks();
        $this->pagedata['delivery_link'] = $this->delivery_link;
        $this->pagedata['selfpick_link']  = $this->selfpick_link;
    }

    /**
     * 列表内操作仍走 order 控制器（确认/发货等与门店配送共用）
     */
    private function _initOrderActionLinks()
    {
        $router = app::get('wap')->router();
        $this->delivery_link['doRefuse']              = $router->gen_url(array('ctl' => 'order', 'act' => 'doRefuse'), true);
        $this->delivery_link['doConfirm']             = $router->gen_url(array('ctl' => 'order', 'act' => 'doConfirm'), true);
        $this->delivery_link['doConsign']             = $router->gen_url(array('ctl' => 'order', 'act' => 'doConsign'), true);
        $this->delivery_link['doCancelDelivery']      = $router->gen_url(array('ctl' => 'order', 'act' => 'doCancelDelivery'), true);

        $this->selfpick_link['index']          = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'index', 'view' => 'all'), true);
        $this->selfpick_link['pending']        = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'index', 'view' => 'pending'), true);
        $this->selfpick_link['verified']       = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'index', 'view' => 'verified'), true);
        $this->selfpick_link['getDeliveryInfo'] = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'getDeliveryInfo'), true);
        $this->selfpick_link['doVerify']       = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'do_verify'), true);
        $this->selfpick_link['getWxSign']      = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'getWxSign'), true);
        $this->selfpick_link['verifyPage']     = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'verify_pickup'), true);
        $this->selfpick_link['checkPickupCode'] = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'checkPickupCode'), true);
        $this->selfpick_link['queryPickupOrder'] = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'queryPickupOrder'), true);
        $this->selfpick_link['getDeliverySerialInfo'] = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'getDeliverySerialInfo'), true);
        $this->selfpick_link['scanDeliverySerial']    = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'scanDeliverySerial'), true);
        $this->selfpick_link['releaseDeliverySerialHold'] = $router->gen_url(array('ctl' => 'store_selfpick', 'act' => 'releaseDeliverySerialHold'), true);
    }

    /**
     * 自提单 Tab
     */
    private function _getSelfpickSubMenu()
    {
        return array(
            'all'      => array(
                'label'  => app::get('base')->_('全部'),
                'filter' => array(),
                'href'   => $this->selfpick_link['index'],
            ),
            'pending'  => array(
                'label'  => app::get('base')->_('待提货'),
                'filter' => array('is_received|notin' => array(2)),
                'href'   => $this->selfpick_link['pending'],
            ),
            'verified' => array(
                'label'  => app::get('base')->_('已提货'),
                'filter' => array('is_received' => 2),
                'href'   => $this->selfpick_link['verified'],
            ),
        );
    }

    private function _applyDateFilter(array $filter)
    {
        $dateType = $_POST['dateType'];
        switch ($dateType) {
            case 'today':
                $filter['order_createtime|than']  = strtotime(date('Y-m-d'));
                $filter['order_createtime|lthan'] = time();
                break;
            case 'yesterday':
                $filter['order_createtime|than']  = strtotime(date('Y-m-d', strtotime('-1 day')));
                $filter['order_createtime|lthan'] = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
                break;
            case 'month':
                $filter['order_createtime|than']  = strtotime(date('Y-m-01'));
                $filter['order_createtime|lthan'] = time();
                break;
            case 'custom':
                if ($_POST['start_time']) {
                    $filter['order_createtime|than'] = strtotime($_POST['start_time']);
                }
                if ($_POST['end_time']) {
                    $filter['order_createtime|lthan'] = strtotime($_POST['end_time'] . ' 23:59:59');
                }
                break;
        }
        return $filter;
    }

    function _views_tab($curr_view)
    {
        $base_filter = array_merge(
            kernel::single('wap_delivery')->getPickupOnlyFilter(),
            array('status|notin' => array(1))
        );
        $wapDeliveryObj = app::get('wap')->model('delivery');

        $page   = intval($_POST['page']) ? intval($_POST['page']) - 1 : 0;
        $limit  = $_POST['pageSize'] ? $_POST['pageSize'] : 10;
        $offset = $limit * $page;

        $store_list = array('0' => '请选择门店');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branchObj  = kernel::single('o2o_store_branch');
            $branch_ids = $branchObj->getO2OBranchByUser(true);
            if (empty($branch_ids)) {
                $this->pagedata['link_url']  = $this->selfpick_link['index'];
                $this->pagedata['error_msg'] = '操作员没有管辖的仓库';
                echo $this->fetch('auth_error.html');
                exit;
            }
            $base_filter['branch_id'] = $branch_ids;

            $storeMdl  = app::get('o2o')->model('store');
            $storeList = $storeMdl->getList('branch_id,name', array('branch_id' => $branch_ids));
            $store_list = $store_list + array_column($storeList, 'name', 'branch_id');
            $this->pagedata['store_list'] = $store_list;
        }

        $sub_menu = $this->_getSelfpickSubMenu();

        foreach ($sub_menu as $k => $v) {
            if ($_POST['flag'] == 'ajax' && $curr_view != $k) {
                continue;
            }

            if (!IS_NULL($v['filter'])) {
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }

            if ($_POST['dateType']) {
                $v['filter'] = $this->_applyDateFilter($v['filter']);
            }

            if ($_POST['sel_type'] && $_POST['sel_keywords'] !== '') {
                switch ($_POST['sel_type']) {
                    case 'order_bn':
                        $v['filter']['order_bn|foot'] = htmlspecialchars(trim($_POST['sel_keywords']));
                        break;
                    case 'ship_mobile':
                        $v['filter']['ship_mobile|has'] = htmlspecialchars(trim($_POST['sel_keywords']));
                        break;
                    case 'channel':
                        $shop = app::get('ome')->model('shop')->getList('shop_id', array('shop_type' => $_POST['sel_keywords']));
                        $v['filter']['shop_id'] = array_column($shop, 'shop_id');
                        break;
                    case 'branch_id':
                        if ($_POST['sel_keywords'] != '0') {
                            $v['filter']['branch_id'] = intval($_POST['sel_keywords']);
                        }
                        break;
                }
            }

            $count = 0;
            if ($k == 'pending') {
                $count = $wapDeliveryObj->count($v['filter']);
            }

            $sub_menu[$k]['filter']    = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['count']     = $count;
            $sub_menu[$k]['pageSize']  = ceil($count / $limit);
            $sub_menu[$k]['offset']    = $offset;
            $sub_menu[$k]['limit']     = $limit;
            $sub_menu[$k]['orderby']   = ($k == 'pending') ? 'order_createtime asc' : 'order_createtime desc';
            $sub_menu[$k]['curr_view'] = ($k == $curr_view);
            $sub_menu[$k]['store_list'] = $store_list;
        }

        return $sub_menu;
    }

    public function index()
    {
        $requestParams = kernel::request()->request_params;
        $view = !empty($requestParams['view']) ? $requestParams['view'] : (isset($_GET['view']) ? $_GET['view'] : '');
        if ($view || !empty($_POST['menu_type'])) {
            $this->delivery_type = $view ?: $_POST['menu_type'];
        } else {
            $this->delivery_type = 'pending';
        }
        if ($this->delivery_type === 'refunded') {
            $this->delivery_type = 'pending';
        }

        $sub_menu = $this->_views_tab($this->delivery_type);
        $this->pagedata['sub_menu'] = $sub_menu;

        $filter = $sub_menu[$this->delivery_type]['filter'];
        if ($this->delivery_type == 'pending') {
            kernel::single('wap_delivery')->syncStaleWapWaitDeliveryFromOme($filter, 100);
        }

        $title  = $sub_menu[$this->delivery_type]['label'];
        $offset = $sub_menu[$this->delivery_type]['offset'];
        $limit  = $sub_menu[$this->delivery_type]['limit'];

        $dataList = kernel::single('wap_delivery')->getList(
            $filter,
            $offset,
            $limit,
            $sub_menu[$this->delivery_type]['orderby'],
            $this->delivery_type
        );

        $this->pagedata['title']       = $title;
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['pageSize']    = $sub_menu[$this->delivery_type]['pageSize'];
        $this->pagedata['link_url']    = $sub_menu[$this->delivery_type]['href'];
        $this->pagedata['menu_type']   = $this->delivery_type;
        $this->pagedata['dateType']    = $_POST['dateType'] ? $_POST['dateType'] : '';
        $this->pagedata['store_list']  = $sub_menu[$this->delivery_type]['store_list'];

        if (isset($_POST['page'])) {
            $this->display('selfpick/order_list_more.html');
        } else {
            $shopTypeList    = app::get('ome')->model('shop')->getList('shop_type');
            $shopTypeListMap = ome_shop_type::get_shop_type();
            $channelListTmp  = array();
            foreach ($shopTypeList as $v) {
                if ($v['shop_type'] && $shopTypeListMap[$v['shop_type']]) {
                    $channelListTmp[$v['shop_type']] = $shopTypeListMap[$v['shop_type']];
                }
            }
            $channel = array('none' => '请选择渠道');
            foreach ($channelListTmp as $k => $v) {
                $channel[$k] = $v;
            }
            $this->pagedata['channelList'] = $channel;
            $this->pagedata['require_pickup_code'] = app::get('o2o')->getConf('o2o.delivery.confirm.code') == 'true';

            $this->display('selfpick/order_list.html');
        }
    }

    /**
     * 核销弹窗：获取发货单详情
     */
    public function getDeliveryInfo()
    {
        $delivery_id = intval($_POST['delivery_id']);
        if (!$delivery_id) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '参数错误'));
            exit;
        }

        $filter = array('delivery_id' => $delivery_id);
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branchObj  = kernel::single('o2o_store_branch');
            $branch_ids = $branchObj->getO2OBranchByUser(true);
            if (empty($branch_ids)) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '操作员没有管辖的仓库'));
                exit;
            }
            $filter['branch_id'] = $branch_ids;
        }

        $wapDeliveryLib = kernel::single('wap_delivery');
        $rows = $wapDeliveryLib->getList($filter, 0, 1, '', 'pending');
        if (empty($rows[0])) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '未找到发货单'));
            exit;
        }

        $item       = $rows[0];
        try {
            $orderItems = $wapDeliveryLib->formatPickupVerifyOrderItemsForModal($item);
        } catch (Exception $e) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '获取商品明细失败：' . $e->getMessage()));
            exit;
        }

        $createtime = !empty($item['order_createtime']) ? $item['order_createtime'] : $item['create_time'];
        echo json_encode(array(
            'rsp'  => 'succ',
            'data' => array(
                'delivery_id'        => $item['delivery_id'],
                'order_bn'           => $item['order_bn'],
                'store_name'         => $item['store_name'],
                'shop_type_name'     => $item['shop_type_name'],
                'createtime_format'  => date('Y-m-d H:i:s', $createtime),
                'pickup_status_name' => $item['pickup_status_name'],
                'order_items'        => $orderItems,
            ),
        ));
        exit;
    }

    /**
     * 确认核销（前端核销 → 校验提货码/唯一码 → 本地签收）
     */
    public function do_verify()
    {
        $post = file_get_contents('php://input');
        $post = json_decode($post, true);
        $params = array_merge((array) $_POST, (array) $post);

        $delivery_id = intval($params['delivery_id']);
        $pickup_code = trim($params['pickup_code'] ?: $params['ziti_code']);
        $err_msg     = '';

        if (!$delivery_id) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '参数错误', 'err_msg' => '参数错误'));
            exit;
        }

        $filter = array('delivery_id' => $delivery_id);
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branchObj  = kernel::single('o2o_store_branch');
            $branch_ids = $branchObj->getO2OBranchByUser(true);
            if (empty($branch_ids)) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '操作员没有管辖的仓库', 'err_msg' => '操作员没有管辖的仓库'));
                exit;
            }
            $filter['branch_id'] = $branch_ids;
        }

        $wapDeliveryObj = app::get('wap')->model('delivery');
        $deliveryInfo   = $wapDeliveryObj->dump($filter, '*');
        if (empty($deliveryInfo)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '未找到发货单', 'err_msg' => '未找到发货单'));
            exit;
        }

        if ((int) $deliveryInfo['status'] === 1) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '订单已取消', 'err_msg' => '订单已取消'));
            exit;
        }
        if ((int) $deliveryInfo['confirm'] === 2) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '订单已拒绝', 'err_msg' => '订单已拒绝'));
            exit;
        }
        if ((int) $deliveryInfo['is_received'] === 2) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '订单已提货', 'err_msg' => '订单已提货'));
            exit;
        }

        if (app::get('o2o')->getConf('o2o.delivery.confirm.code') == 'true') {
            if ($pickup_code === '') {
                echo json_encode(array('rsp' => 'fail', 'msg' => '请填写提货码', 'err_msg' => '请填写提货码'));
                exit;
            }
            $codeInfo = app::get('wap')->model('delivery_code')->dump(
                array('delivery_bn' => $deliveryInfo['delivery_bn'], 'code' => $pickup_code, 'status' => 2),
                'delivery_bn'
            );
            if (empty($codeInfo)) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '提货码不正确', 'err_msg' => '提货码不正确'));
                exit;
            }
        }

        $serialLib         = kernel::single('wap_delivery_serial');
        $formattedSerial   = array();
        $serialInfo        = $serialLib->getDeliverySerialInfo(
            $delivery_id,
            $serialErr,
            wap_delivery_serial::MODE_PICKUP_VERIFY
        );
        if ($serialInfo === false) {
            echo json_encode(array('rsp' => 'fail', 'msg' => $serialErr ?: '唯一码校验失败', 'err_msg' => $serialErr ?: '唯一码校验失败'));
            exit;
        }
        if (!empty($serialInfo['has_serial'])) {
            $serialItems = array();
            if (!empty($params['serial_items'])) {
                $serialItems = json_decode($params['serial_items'], true);
                if (!is_array($serialItems)) {
                    $serialItems = array();
                }
            }
            $formattedSerial = $serialLib->validateSerialItemsForPickupVerify($delivery_id, $serialItems, $serialErr);
            if ($formattedSerial === false) {
                echo json_encode(array('rsp' => 'fail', 'msg' => $serialErr ?: '唯一码校验失败', 'err_msg' => $serialErr ?: '唯一码校验失败'));
                exit;
            }
        }

        $frontErr   = '';
        $frontExtra = array();
        if (!empty($formattedSerial)) {
            $frontExtra['serial_items'] = $formattedSerial;
        }
        if (!kernel::single('wap_selfpick_pickup_verify')->verify($deliveryInfo, $pickup_code, $frontExtra, $frontErr)) {
            echo json_encode(array(
                'rsp'     => 'fail',
                'msg'     => $frontErr ?: '前端核销失败',
                'err_msg' => $frontErr ?: '前端核销失败',
            ));
            exit;
        }

        $wapDeliveryLib = kernel::single('wap_delivery');
        $deliveryInfo   = $wapDeliveryLib->preparePickupForVerify($deliveryInfo, $err_msg, $formattedSerial);
        if ($deliveryInfo === false) {
            echo json_encode(array('rsp' => 'fail', 'msg' => $err_msg, 'err_msg' => $err_msg));
            exit;
        }

        $res = kernel::single('wap_delivery_process')->sign($deliveryInfo);
        if ($res) {
            echo json_encode(array('rsp' => 'succ', 'msg' => '核销成功'));
            exit;
        }
        echo json_encode(array('rsp' => 'fail', 'msg' => '核销失败', 'err_msg' => '核销失败'));
        exit;
    }

    /**
     * 扫/输入 → 前端订单查询 → 返回 OMS 订单号（再经 checkPickupCode 跳转确认核销页）
     */
    public function queryPickupOrder()
    {
        $post = file_get_contents('php://input');
        $post = json_decode($post, true);
        $params = array_merge((array) $_POST, (array) $post);

        $code = trim($params['code'] ?? '');
        if ($code === '') {
            echo json_encode(array('rsp' => 'fail', 'msg' => '请扫描或输入订单号/提货码'));
            exit;
        }

        $err_msg = '';
        $result  = array();
        $passed  = kernel::single('wap_selfpick_pickup_query')->query($code, $err_msg, $result);
        if (!$passed) {
            echo json_encode(array('rsp' => 'fail', 'msg' => $err_msg ?: '订单查询失败'));
            exit;
        }

        echo json_encode(array(
            'rsp'      => 'succ',
            'msg'      => '查询成功',
            'order_bn' => $result['order_bn'],
        ));
        exit;
    }

    /**
     * 扫/输入提货码前置校验（通过后前端再跳转确认提货页）
     */
    public function checkPickupCode()
    {
        $post = file_get_contents('php://input');
        $post = json_decode($post, true);
        $params = array_merge((array) $_POST, (array) $post);

        $code = trim($params['code'] ?? '');
        if ($code === '') {
            echo json_encode(array('rsp' => 'fail', 'msg' => '请输入提货码'));
            exit;
        }

        $err_msg = '';
        $result  = array();
        $passed  = kernel::single('wap_selfpick_pickup_code')->verify($code, $err_msg, $result);
        if (!$passed) {
            echo json_encode(array('rsp' => 'fail', 'msg' => $err_msg ?: '提货码校验失败'));
            exit;
        }

        $msg = !empty($result['resumed']) ? '平台已核销，请继续完成本地提货' : '校验成功';
        echo json_encode(array(
            'rsp'         => 'succ',
            'msg'         => $msg,
            'delivery_id' => intval($result['delivery_id'] ?? 0),
        ));
        exit;
    }

    /**
     * 扫码/输入提货码 → 确认提货页
     * 参数：code（提货码、提货码_订单号、订单号）或 delivery_id（列表进入）
     */
    public function verify_pickup()
    {
        $code        = trim($_GET['code']);
        $delivery_id = intval($_GET['delivery_id']);
        $pickup_code = '';
        $err_msg     = '';

        $delivery = $this->_resolveVerifyDelivery($code, $delivery_id, $pickup_code, $err_msg);

        $this->pagedata['err_msg']            = $err_msg;
        $this->pagedata['delivery']           = $delivery;
        $this->pagedata['pickup_code']        = $pickup_code;
        $this->pagedata['require_pickup_code'] = app::get('o2o')->getConf('o2o.delivery.confirm.code') == 'true';
        $this->pagedata['order_items']        = array();

        if (!empty($delivery)) {
            try {
                $this->pagedata['order_items'] = kernel::single('wap_delivery')->formatPickupVerifyOrderItemsForModal($delivery);
            } catch (Exception $e) {
                $this->pagedata['err_msg'] = '获取商品明细失败：' . $e->getMessage();
                $this->pagedata['delivery'] = null;
            }

            $serialLib = kernel::single('wap_delivery_serial');
            $serialErr = '';
            $serialInfo = $serialLib->getDeliverySerialInfo(
                $delivery['delivery_id'],
                $serialErr,
                wap_delivery_serial::MODE_PICKUP_VERIFY
            );
            if ($serialInfo === false) {
                $this->pagedata['err_msg'] = $serialErr ?: '获取唯一码信息失败';
                $this->pagedata['delivery'] = null;
            } else {
                $this->pagedata['serial_info'] = $serialInfo;
                $this->pagedata['has_serial']  = !empty($serialInfo['has_serial']);
                $this->pagedata['serial_info_json'] = json_encode($serialInfo);
            }
        } else {
            $this->pagedata['serial_info'] = null;
            $this->pagedata['has_serial']  = false;
            $this->pagedata['serial_info_json'] = 'null';
        }

        $this->pagedata['return_url']    = $this->selfpick_link['pending'];
        $this->pagedata['do_verify_url'] = $this->selfpick_link['doVerify'];
        $this->pagedata['scan_serial_url'] = $this->selfpick_link['scanDeliverySerial'];
        $this->pagedata['release_serial_url'] = $this->selfpick_link['releaseDeliverySerialHold'];
        $this->pagedata['wx_sign_url'] = $this->selfpick_link['getWxSign'];
        $this->display('selfpick/verify.html');
    }

    /**
     * 获取自提核销唯一码明细
     */
    public function getDeliverySerialInfo()
    {
        $delivery_id = intval($_POST['delivery_id']);
        $serialLib   = kernel::single('wap_delivery_serial');
        $err_msg     = '';
        $info        = $serialLib->getDeliverySerialInfo($delivery_id, $err_msg, wap_delivery_serial::MODE_PICKUP_VERIFY);

        if ($info === false) {
            echo json_encode(array('res' => 'error', 'msg' => $err_msg ?: '获取失败'));
            exit;
        }

        echo json_encode(array_merge(array('res' => 'succ'), $info));
        exit;
    }

    /**
     * 自提核销扫描唯一码
     */
    public function scanDeliverySerial()
    {
        $delivery_id = intval($_POST['delivery_id']);
        $sn          = trim($_POST['sn']);
        $scannedList = array();
        if (!empty($_POST['scanned_list'])) {
            $scannedList = json_decode($_POST['scanned_list'], true);
            if (!is_array($scannedList)) {
                $scannedList = array();
            }
        }

        $serialLib = kernel::single('wap_delivery_serial');
        $err_msg   = '';
        $result    = $serialLib->scanSerial(
            $delivery_id,
            $sn,
            $scannedList,
            $err_msg,
            wap_delivery_serial::MODE_PICKUP_VERIFY
        );

        if ($result === false) {
            echo json_encode(array('res' => 'error', 'msg' => $err_msg ?: '扫描失败'));
            exit;
        }

        echo json_encode(array_merge(array('res' => 'succ'), $result));
        exit;
    }

    /**
     * 释放自提核销唯一码预占
     */
    public function releaseDeliverySerialHold()
    {
        $delivery_id = intval($_POST['delivery_id']);
        $delivery    = app::get('wap')->model('delivery')->dump(array('delivery_id' => $delivery_id), 'delivery_id');
        if (empty($delivery)) {
            echo json_encode(array('res' => 'error', 'msg' => '发货单不存在'));
            exit;
        }

        kernel::single('machinecenter_sn_hold')->releaseByBill('delivery', $delivery_id);
        echo json_encode(array('res' => 'succ', 'msg' => '已释放'));
        exit;
    }

    /**
     * 自提核销基础筛选（门店权限 + 自提物流）
     */
    private function _getSelfpickVerifyBaseFilter()
    {
        $filter = array_merge(
            kernel::single('wap_delivery')->getPickupOnlyFilter(),
            array('status|notin' => array(1))
        );
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = kernel::single('o2o_store_branch')->getO2OBranchByUser(true);
            if (!empty($branch_ids)) {
                $filter['branch_id'] = $branch_ids;
            }
        }
        return $filter;
    }

    /**
     * 解析待提货订单（提货码 / 扫码内容 / 列表 delivery_id）
     */
    private function _resolveVerifyDelivery($code, $delivery_id, &$pickup_code, &$err_msg)
    {
        $wapDeliveryLib = kernel::single('wap_delivery');
        $baseFilter     = $this->_getSelfpickVerifyBaseFilter();

        if ($delivery_id > 0) {
            $filter = array_merge($baseFilter, array('delivery_id' => $delivery_id));
            $rows   = $wapDeliveryLib->getList($filter, 0, 1, '', 'pending');
            if (empty($rows[0])) {
                $err_msg = '未找到自提订单';
                return null;
            }
            if (!$rows[0]['can_pickup_verify']) {
                $err_msg = '该订单当前不可提货';
                return null;
            }
            return $rows[0];
        }

        if ($code === '') {
            $err_msg = '请输入提货码';
            return null;
        }

        $order_bn = $code;
        $pickup_code = '';
        if (strpos($code, '_') !== false) {
            $parts       = explode('_', $code, 2);
            $pickup_code = trim($parts[0]);
            $order_bn    = trim($parts[1]);
            if ($order_bn === '') {
                $err_msg = '扫码内容无效';
                return null;
            }
        } elseif (preg_match('/^\d{6}$/', $code)) {
            $codeInfo = app::get('wap')->model('delivery_code')->dump(
                array('code' => $code, 'status' => 2),
                'delivery_bn'
            );
            if (!empty($codeInfo['delivery_bn'])) {
                $pickup_code = $code;
                $filter      = array_merge($baseFilter, array('delivery_bn' => $codeInfo['delivery_bn']));
                $rows        = $wapDeliveryLib->getList($filter, 0, 1, 'order_createtime desc', 'pending');
                if (!empty($rows[0])) {
                    if (!$rows[0]['can_pickup_verify']) {
                        $err_msg = '该订单当前不可提货';
                        return null;
                    }
                    return $rows[0];
                }
            }
        }

        $filter = array_merge($baseFilter, array('order_bn|foot' => $order_bn));
        $rows   = $wapDeliveryLib->getList($filter, 0, 1, 'order_createtime desc', 'pending');
        if (empty($rows[0])) {
            $err_msg = '未找到自提订单，请核对提货码';
            return null;
        }
        if (!$rows[0]['can_pickup_verify']) {
            $err_msg = '该订单当前不可提货';
            return null;
        }
        return $rows[0];
    }

    /**
     * 微信扫码签名
     */
    public function getWxSign()
    {
        $url = $_POST['url'];
        $msg = '';
        $wxSign = kernel::single('wap_wechat_sign')->getSign($url, $msg);
        if (!$wxSign) {
            $this->error($msg ? $msg : '网络异常，请重试');
        }
        if (isset($this->selfpick_link['verifyPage'])) {
            $wxSign['ziti_url'] = $this->selfpick_link['verifyPage'];
        }
        $this->success('签名成功', $wxSign);
    }
}
