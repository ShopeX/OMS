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

use PHPUnit\Util\Json;

/**
 * 订单中心
 */
class wap_ctl_order extends wap_controller
{
    var $delivery_link    = array();

    function __construct($app)
    {
        parent::__construct($app);

        //确认拒单
        $this->delivery_link['doRefuse']   = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'doRefuse'), true);

        //立即接单
        $this->delivery_link['doConfirm']   = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'doConfirm'), true);

        //发货
        $this->delivery_link['doConsign']    = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'doConsign'), true);
        $this->delivery_link['getDeliverySerialInfo'] = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'getDeliverySerialInfo'), true);
        $this->delivery_link['scanDeliverySerial']    = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'scanDeliverySerial'), true);
        $this->delivery_link['getPrepareStockInfo']   = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'getPrepareStockInfo'), true);
        $this->delivery_link['scanPrepareStockSerial'] = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'scanPrepareStockSerial'), true);
        $this->delivery_link['removePrepareStockSerial'] = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'removePrepareStockSerial'), true);
        $this->delivery_link['finishPrepareStock'] = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'finishPrepareStock'), true);
        $this->delivery_link['getConsignPrepareInfo'] = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'getConsignPrepareInfo'), true);

        //签收
        $this->delivery_link['signPage']    = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'signPage'), true);
        $this->delivery_link['doSign']         = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'doSign'), true);
        $this->delivery_link['uploadimage']         = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'uploadimage'), true);
        $this->delivery_link['sendMsg']        = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'sendMsg'), true);
        $this->delivery_link['showOrderInfo']  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'showOrderInfo'), true);

        #打印
        $this->delivery_link['doPrint']  = app::get('wap')->router()->gen_url(array('ctl'=>'logistics','act'=>'doPrint'), true);
       
        # 补录快递单号
        $this->delivery_link['doAddLogiNo']  = app::get('wap')->router()->gen_url(array('ctl'=>'logistics','act'=>'doAddLogiNo'), true);
        $this->delivery_link['doUpdateLogiNo']  = app::get('wap')->router()->gen_url(array('ctl'=>'logistics','act'=>'doUpdateLogiNo'), true);

        # 呼叫快递
        $this->delivery_link['onlineDelivery']  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'onlineDelivery'), true);
        # 取消发货
        $this->delivery_link['doCancelDelivery']  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'doCancelDelivery'), true);
        $this->delivery_link['getWxSign']  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'getWxSign'), true);

        //待发货
        $this->delivery_link['orderWaitDelivery']  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'index?view=wait_delivery'), true);
        // 待揽件
        $this->delivery_link['orderWaitPickup']  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'index?view=wait_pickup'), true);
        // 已签收
        $this->delivery_link['orderDelivered']  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'index?view=delivered'), true);
        //已揽件
        $this->delivery_link['orderPicked']  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'index?view=picked'), true);
        //已取消
        $this->delivery_link['orderCancel']  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'index?view=cancel'), true);
    
        $this->pagedata['delivery_link']   = $this->delivery_link;
    }

    /**
     * 条件
     * 
     * status 0:未处理  1:打回  2:暂停 3:已发货
     */
    function _views_confirm($curr_view)
    {
        $wapDeliveryLib = kernel::single('wap_delivery');
        $base_filter = $wapDeliveryLib->getExcludePickupFilter();
        $wapDeliveryObj    = app::get('wap')->model('delivery');

        $page              = intval($_POST['page']) ? intval($_POST['page']) : 0;
        $limit             = 10;//每页显示数量
        $offset            = $limit * $page;

        $is_super    = kernel::single('desktop_user')->is_super();
        if(!$is_super)
        {
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids))
            {
                $this->pagedata['link_url']     = $this->delivery_link['order_index'];
                $this->pagedata['error_msg']    = '操作员没有管辖的仓库';
                echo $this->fetch('auth_error.html');
                exit;
            }
            $base_filter['branch_id']    = $branch_ids;
        }

        $dly_overtime    = 0;

        //超时订单
        if($curr_view == 'overtime')
        {

            //履约超时时间设置(分钟)
            $minute    = app::get('o2o')->getConf('o2o.delivery.dly_overtime');
            $minute    = intval($minute);

            if($minute)
            {
                $second          = $minute * 60;
                $dly_overtime    = time() - $second;//现在时间 减去 履约时间
            }
        }

        //menu
        $sub_menu = array(
                'all' => array('label'=>app::get('base')->_('订单查看'), 'filter'=>array('status'=>array(0, 3), 'confirm'=>array(1, 3)), 'href'=>$this->delivery_link['order_index']),
                'confirm' => array('label'=>app::get('base')->_('订单确认') ,'filter'=>array('status'=>0, 'confirm'=>3), 'href'=>$this->delivery_link['order_confirm']),
                'consign' => array('label'=>app::get('base')->_('订单发货') ,'filter'=>array('status'=>0, 'confirm'=>1), 'href'=>$this->delivery_link['order_consign']),
                'sign' => array('label'=>app::get('base')->_('签收核销') ,'filter'=>array('status'=>3, 'confirm'=>1, 'process_status'=>7, 'is_received'=>1), 'href'=>$this->delivery_link['order_sign']),
                'overtime' => array('label'=>app::get('base')->_('超时订单') ,'filter'=>array('status'=>0, 'confirm'=>array(1, 3), 'create_time|lthan'=>$dly_overtime), 'href'=>$this->delivery_link['overtimeOrders']),
        );

        foreach($sub_menu as $k=>$v)
        {
            //Ajax加载下一页数据,只处理本页
            if($_POST['flag'] == 'ajax' && $curr_view != $k)
            {
                continue;
            }

            if (!IS_NULL($v['filter']))
            {
                $v['filter']    = array_merge($v['filter'], $base_filter);
            }

            //搜索条件
            if($_POST['sel_type'] && $_POST['sel_keywords'])
            {
                switch ($_POST['sel_type'])
                {
                    case 'delivery_bn':
                        $v['filter']['delivery_bn']    = htmlspecialchars(trim($_POST['sel_keywords']));
                    break;
                    case 'order_bn':
                        $v['filter']['order_bn']    = htmlspecialchars(trim($_POST['sel_keywords']));
                    break;
                    case 'ship_mobile':
                        $v['filter']['ship_mobile']    = htmlspecialchars(trim($_POST['sel_keywords']));
                    break;
                    case 'ship_name':
                        $v['filter']['ship_name']    = htmlspecialchars(trim($_POST['sel_keywords']));
                    break;
                }
            }

            $count    = $wapDeliveryObj->count($v['filter']);

            $sub_menu[$k]['filter']    = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['count']     = $count;
            $sub_menu[$k]['pageSize']  = ceil($count / $limit);

            $sub_menu[$k]['offset']    = $offset;
            $sub_menu[$k]['limit']     = $limit;
            $sub_menu[$k]['orderby']   = 'delivery_id desc';#排序

            if($k == $curr_view){
                $sub_menu[$k]['curr_view'] = true;
            }else{
                $sub_menu[$k]['curr_view'] = false;
            }
        }

        return $sub_menu;
    }

    /**
     * 条件
     * 
     * status 0:未处理  1:打回  2:暂停 3:已发货
     */
    function _views_tab($curr_view)
    {
        $wapDeliveryLib = kernel::single('wap_delivery');
        $base_filter = $wapDeliveryLib->getExcludePickupFilter();
        $wapDeliveryObj    = app::get('wap')->model('delivery');

        $page              = intval($_POST['page']) ? intval($_POST['page'])-1 : 0;
        $limit             = $_POST['pageSize'] ? $_POST['pageSize'] : 10;//每页显示数量
        $offset            = $limit * $page;

        $is_super    = kernel::single('desktop_user')->is_super();
        if(!$is_super)
        {
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids))
            {
                $this->pagedata['link_url']     = $this->delivery_link['order_index'];
                $this->pagedata['error_msg']    = '操作员没有管辖的仓库';
                echo $this->fetch('auth_error.html');
                exit;
            }
            $base_filter['branch_id']    = $branch_ids;

            $storeMdl = app::get("o2o")->model("store");
            $storeList = $storeMdl->getList("branch_id,name", array("branch_id" => $branch_ids));
            $store_list = array_column($storeList, "name", "branch_id");
            $this->pagedata['store_list'] = $store_list;
        }

        //menu
        $sub_menu = array(
            'all' => array('label' => app::get('base')->_('全部'), 'filter' => array(), 'href' => $this->delivery_link['order_index']),
            'wait_delivery' => array('label' => app::get('base')->_('待发货'), 'filter' => array('status' => 0), 'href' => $this->delivery_link['orderWaitDelivery']),
           
            'picked' => array('label' => app::get('base')->_('待签收'), 'filter' => array('status' => 3, 'is_received' => array('1')), 'href' => $this->delivery_link['orderPicked']),
            'delivered' => array('label' => app::get('base')->_('已签收'), 'filter' => array('status' => 3, 'is_received' => array('2')), 'href' => $this->delivery_link['orderDelivered']),
            'cancel' => array('label' => app::get('base')->_('已取消'), 'filter' => array('status' => 1), 'href' => $this->delivery_link['orderCancel']),
        );

        foreach($sub_menu as $k=>$v)
        {
            //Ajax加载下一页数据,只处理本页
            if($_POST['flag'] == 'ajax' && $curr_view != $k)
            {
                continue;
            }

            if (!IS_NULL($v['filter']))
            {
                $v['filter']    = array_merge($v['filter'], $base_filter);
            }

            //搜索条件
            if($_POST['sel_type'] && $_POST['sel_keywords'])
            {
                switch ($_POST['sel_type'])
                {
                  
                    case 'order_bn':
                        $v['filter']['order_bn|foot']    = htmlspecialchars(trim($_POST['sel_keywords']));
                    break;
                    case 'ship_mobile':
                        $v['filter']['ship_mobile|has']    = htmlspecialchars(trim($_POST['sel_keywords']));
                    break;
                   
                    case "channel":
                        $shop = app::get('ome')->model('shop')->getList('shop_id',array('shop_type'=>$_POST['sel_keywords']));
                        $shopIds = array_column($shop,'shop_id');
                        $v['filter']['shop_id'] = $shopIds;
                    break;
                }
            }

            $count = 0;
            if ($k == 'wait_delivery' || $k == 'picked') {

                $count = $wapDeliveryObj->count($v['filter']);
            }

            $sub_menu[$k]['filter']    = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['count']     = $count;
            $sub_menu[$k]['pageSize']  = ceil($count / $limit);

            $sub_menu[$k]['offset']    = $offset;
            $sub_menu[$k]['limit']     = $limit;

            if ($curr_view == 'wait_delivery') {
                $sub_menu[$k]['orderby']   = 'order_createtime asc'; #排序
            } else {
                $sub_menu[$k]['orderby']   = 'order_createtime desc'; #排序
            }

            if($k == $curr_view){
                $sub_menu[$k]['curr_view'] = true;
            }else{
                $sub_menu[$k]['curr_view'] = false;
            }
        }

        return $sub_menu;
    }

    /**
     * 获取CreateTimeByDateType
     * @param mixed $filter filter
     * @return mixed 返回结果
     */
    public function getCreateTimeByDateType($filter)
    {
        $dateType = $_POST['dateType'];
        switch ($dateType) {
            case 'today':
                $filter['create_time|than'] = strtotime(date('Y-m-d'));
                $filter['create_time|lthan'] = time();
                break;
            case 'yesterday':
                $filter['create_time|than'] = strtotime(date('Y-m-d', strtotime('-1 day')));
                $filter['create_time|lthan'] = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
                break;
            case 'month':
                $filter['create_time|than'] = strtotime(date('Y-m-01'));
                $filter['create_time|lthan'] = time();
                break;
            case 'custom':
                if ($_POST['start_time']) {
                    $filter['create_time|than'] = strtotime($_POST['start_time']);
                }
                if ($_POST['end_time']) {
                    $filter['create_time|lthan'] = strtotime($_POST['end_time'] . ' 23:59:59');
                }
                break;
        }

        $this->pagedata['dateType'] = $_POST['dateType'] ? $_POST['dateType'] : 'custom';

        return $filter;
    }

    /**
     * 解析订单列表 Tab（支持路径参数 /view/xxx 与 query ?view=xxx）
     */
    protected function _resolveOrderView()
    {
        $requestParams = kernel::request()->request_params;
        if (!empty($requestParams['view'])) {
            return $requestParams['view'];
        }
        if (!empty($_GET['view'])) {
            return $_GET['view'];
        }
        if (!empty($_POST['menu_type'])) {
            return $_POST['menu_type'];
        }

        return '';
    }

    /**
     * 订单查看
     */
    function index()
    {
        $view = $this->_resolveOrderView();
        $this->delivery_type = $view ?: 'all';
        $sub_menu = $this->_views_tab($this->delivery_type);

        $this->pagedata['sub_menu'] = $sub_menu;

        $filter      = $sub_menu[$this->delivery_type]['filter'];

        $filter = $this->getCreateTimeByDateType($filter);

        if ($this->delivery_type == 'wait_delivery') {
            kernel::single('wap_delivery')->syncStaleWapWaitDeliveryFromOme($filter, 100);
        }

        $title       = $sub_menu[$this->delivery_type]['label'];

        $offset            = $sub_menu[$this->delivery_type]['offset'];
        $limit             = $sub_menu[$this->delivery_type]['limit'];

        #仓库对应_发货单列表
        $wapDeliveryLib    = kernel::single('wap_delivery');

        $dataList          = $wapDeliveryLib->getList($filter, $offset, $limit, $sub_menu[$this->delivery_type]['orderby'], $this->delivery_type);
     
       
        $this->pagedata['title']       = $title;
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['pageSize']    = $sub_menu[$this->delivery_type]['pageSize'];

        $this->pagedata['link_url']    = $sub_menu[$this->delivery_type]['href'];

        //baidu map button show or not
        //$baidu_map_show = app::get('o2o')->getConf('o2o.baidumap.show');
        if($baidu_map_show=="true"){
           //$this->pagedata["baidu_map_show"] = true;
        }


        $this->pagedata['wait_receiver_url'] = $this->delivery_link['orderPicked'];
        $this->pagedata['do_sign_url'] = $this->delivery_link['doSign'];
        $this->pagedata['uploadimage'] = $this->delivery_link['uploadimage'];
         $this->pagedata['sign_url'] = $this->delivery_link['orderDelivered'];
        if(isset($_POST['page']))
        {
            //Ajax加载更多
            $this->display('order/order_list_more.html');
        }
        else
        {
            $corpList = kernel::database()->select("select channel_id,type,name,tmpl_type from sdb_ome_dly_corp where disabled='false' and type not in ('o2o_ship','o2o_pickup')");
            $this->pagedata['corpList'] = json_encode($corpList);

            $shopTypeList = app::get('ome')->model('shop')->getList('shop_type');
            $shopTypeListMap = ome_shop_type::get_shop_type();
            $channelListTmp = array();
            foreach($shopTypeList as $k=>$v){
                if ($v['shop_type'] && $shopTypeListMap[$v['shop_type']]) {
                    $channelListTmp[$v['shop_type']] = $shopTypeListMap[$v['shop_type']];
                }
            }
            $channel = array();
            foreach($channelListTmp as $k=>$v){
                $channel[] = array(
                    'value' => $k,
                    'label' => $v
                );
            }

            $this->pagedata['channelList'] = $channel;

            //门店核单拒绝原因
            $reasonObj    = app::get('o2o')->model('refuse_reason');
            $refuse_reasons  = $reasonObj->getList('*', array('disabled'=>'false'), 0, 100);
            $this->pagedata['refuse_reasons']    = $refuse_reasons;

            $this->display('order/order_list.html');
        }
    }

    function getCorpList(){
        $store_id = $_POST['store_id'];

        $storeObj = app::get('o2o')->model('store');
        $storeInfo = $storeObj->dump(array('store_id'=>$store_id), 'is_default_month_accoun');
        $storeCorpList = $storeObj->db->select("SELECT * FROM sdb_o2o_store_corp WHERE store_id=" . $store_id);
        $corpCodeArr = array();
        $default_corp_code = '';
        if ($storeCorpList) {
            foreach($storeCorpList as $row) {

                $corpCodeArr[] = $row['corp_code'];

                if ($row['is_default'] == '1') {
                    // 默认的物流公司
                    $default_corp_code = $row['corp_code'];
                }

                $defaultCorp[$row['corp_code']]['product_type'] = $row['corp_product'];
                $defaultCorp[$row['corp_code']]['corp_month_account'] = $row['corp_month_account'];
                if ($row['corp_month_account_default'] == '小镇') {
                    $defaultCorp[$row['corp_code']]['default_corp_month_account'] = 'default';
                } else {
                    $defaultCorp[$row['corp_code']]['default_corp_month_account'] = $row['corp_month_account'];
                }
            }
        }

        if ($corpCodeArr) {
            // 获取物流公司
            $corpList = kernel::database()->select("select channel_id,type,name,tmpl_type from sdb_ome_dly_corp where disabled='false' and tmpl_type='electron' and `type` in ('" . implode("','", $corpCodeArr) . "')");
            foreach ($corpList as $k => $row) {
                $product_type_list = kernel::single('logisticsmanager_waybill_func')->corpCode2ChannelService();
                $corpList[$k]['product_type'] = $product_type_list[$row['type']]['product_type'];
            }
        } else {
            $corpList = null;
        }

        echo json_encode(array('rsp' => 'succ', 'storeInfo' => $storeInfo, 'corpList' => $corpList, 'default_corp_code' => $default_corp_code, 'default_corp' => $defaultCorp));exit;
    }

    function decryptAddress()
    {
        $orderId = $_POST['order_id'];
        $type = $_POST['action'];
        $field = 'order_bn,shop_id,shop_type,ship_tel,ship_mobile,ship_addr,ship_name,ship_area';
        $data = app::get('ome')->model('orders')->db_dump(array('order_id' => $orderId), $field);
        if (!$data) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '订单号不存在'));exit;
        }

        if ($data['shop_type'] == 'luban') {
            $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($orderId, 'order', 'XJJY');
            if ($jyInfo) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '中转订单请联系客服修改直邮后再进行操作'));exit;
            }
        }

        // mainland:北京/顺义区/后沙峪地区:3268
        $ship_area_str = '';
        if ($data['ship_area']) {
            $ship_area = explode(":", $data['ship_area']);
            $ship_area_str = str_replace("/", "", $ship_area[1]);
        }

        if ($type == 'show') {
            // 解密
            $decrypt_data = kernel::single('ome_security_router', $data['shop_type'])->decrypt(array(
                'ship_tel'    => $data['ship_tel'],
                'ship_mobile' => $data['ship_mobile'],
                'ship_addr'   => $data['ship_addr'],
                'shop_id'     => $data['shop_id'],
                'order_bn'    => $data['order_bn'],
                'ship_name' => $data['ship_name'],
            ), 'order', true);

            if ($decrypt_data['rsp'] && $decrypt_data['rsp'] == 'fail') {
                $errArr = json_decode($decrypt_data['err_msg'], true);
                $msg = $errArr['data']['decrypt_infos'][0]['err_msg'] ? $errArr['data']['decrypt_infos'][0]['err_msg'] : '解密失败,订单已关闭或者解密额度不足';
                $result = [
                    'rsp' => 'fail',
                    'err_data' => $decrypt_data,
                    'msg' => $msg
                ];
                exit(json_encode($result, JSON_UNESCAPED_UNICODE));
            }

            $res = [
                'rsp' => 'succ',
                'data' => [
                    'ship_name' => $decrypt_data['ship_name'],
                    'ship_tel' => $decrypt_data['ship_tel'],
                    'ship_mobile' => $decrypt_data['ship_mobile'],
                    'ship_addr' => $ship_area_str.$decrypt_data['ship_addr']
                ]
            ];
        } else {
            $res = [
                'rsp' => 'succ',
                'data' => [
                    'ship_name' => $data['ship_name'],
                    'ship_tel' => $data['ship_tel'],
                    'ship_mobile' => $data['ship_mobile'],
                    'ship_addr' => $ship_area_str.$data['ship_addr']
                ]
            ];
        }
        echo json_encode($res);exit;
    }

    /**
     * 订单确认
     */
    function confirm()
    {
        $this->delivery_type    = 'confirm';

        $sub_menu    = $this->_views_confirm($this->delivery_type);
        $filter      = $sub_menu[$this->delivery_type]['filter'];
        $title       = $sub_menu[$this->delivery_type]['label'];

        $offset            = $sub_menu[$this->delivery_type]['offset'];
        $limit             = $sub_menu[$this->delivery_type]['limit'];

        #仓库对应_发货单列表
        $wapDeliveryLib    = kernel::single('wap_delivery');

        $dataList          = $wapDeliveryLib->getList($filter, $offset, $limit, $sub_menu[$this->delivery_type]['orderby'], $this->delivery_type);

        $this->pagedata['title']       = $title;
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['pageSize']    = $sub_menu[$this->delivery_type]['pageSize'];

        $this->pagedata['link_url']    = $sub_menu[$this->delivery_type]['href'];

        //baidu map button show or not
        $baidu_map_show = app::get('o2o')->getConf('o2o.baidumap.show');
        if($baidu_map_show=="true"){
            $this->pagedata["baidu_map_show"] = true;
        }

        if($offset > 0)
        {
            //Ajax加载更多
            $this->display('order/order_list_more.html');
        }
        else
        {
            //门店核单拒绝原因
            $reasonObj    = app::get('o2o')->model('refuse_reason');
            $refuse_reasons  = $reasonObj->getList('*', array('disabled'=>'false'), 0, 100);
            $this->pagedata['refuse_reasons']    = $refuse_reasons;

            $this->display('order/order_list.html');
        }
    }

    /**
     * 订单发货
     */
    function consign()
    {
        $this->delivery_type    = 'consign';

        $sub_menu    = $this->_views_confirm($this->delivery_type);
        $filter      = $sub_menu[$this->delivery_type]['filter'];
        $title       = $sub_menu[$this->delivery_type]['label'];

        $offset            = $sub_menu[$this->delivery_type]['offset'];
        $limit             = $sub_menu[$this->delivery_type]['limit'];

        #仓库对应_发货单列表
        $wapDeliveryLib    = kernel::single('wap_delivery');

        $dataList          = $wapDeliveryLib->getList($filter, $offset, $limit, $sub_menu[$this->delivery_type]['orderby'], $this->delivery_type);

        $this->pagedata['title']       = $title;
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['pageSize']    = $sub_menu[$this->delivery_type]['pageSize'];

        $this->pagedata['link_url']    = $sub_menu[$this->delivery_type]['href'];

        //baidu map button show or not
        $baidu_map_show = app::get('o2o')->getConf('o2o.baidumap.show');
        if($baidu_map_show=="true"){
            $this->pagedata["baidu_map_show"] = true;
        }

        if($offset > 0)
        {
            //Ajax加载更多
            $this->display('order/order_list_more.html');
        }
        else
        {
            $this->display('order/order_list.html');
        }
    }

    /**
     * 确认拒单
     * 
     * @param intval  $delivery_id
     * @return json
     */
    function doRefuse()
    {
        $delivery_id    = intval($_POST['delivery_id']);
        $redirect_url   = ($_POST['backUrl'] ? $_POST['backUrl'] : $this->delivery_link['order_confirm']);
        if(empty($delivery_id))
        {
            echo json_encode(array('res'=>'error', 'msg'=>'无效操作'));
            exit;
        }

        $wapDeliveryObj    = app::get('wap')->model('delivery');
        $deliveryInfo      = $wapDeliveryObj->dump(array('delivery_id'=>$delivery_id), '*');
        if(empty($deliveryInfo))
        {
            echo json_encode(array('res'=>'error', 'msg'=>'没有相关发货单'));
            exit;
        }
        elseif($deliveryInfo['status'] > 0 || $deliveryInfo['confirm'] != 3)
        {
            echo json_encode(array('res'=>'error', 'msg'=>'该发货单无法继续操作'));
            exit;
        }

        $dlyProcessLib = kernel::single('wap_delivery_process');

        //组织参数
        $params = array_merge(array('delivery_id'=>$delivery_id), $deliveryInfo);

        $refuse_reason_id    = intval($_POST['refuse_reason_id']);
        if(empty($refuse_reason_id))
        {
            echo json_encode(array('res'=>'error', 'msg'=>'请选择拒单理由'));
            exit;
        }

        //拒绝原因
        $params['reason_id']   = $refuse_reason_id;

        if($dlyProcessLib->refuse($params)){

            //task任务更新统计数据
            $wapDeliveryLib    = kernel::single('wap_delivery');
            $wapDeliveryLib->taskmgr_statistic('refuse');

            echo json_encode(array('res'=>'succ', 'status'=>'已拒绝', 'msg'=>'已拒绝成功'));
            exit;
        }else{
            echo json_encode(array('res'=>'error', 'msg'=>'门店拒绝失败'));
            exit;
        }
    }

    /**
     * 立即接单
     * 
     * @return json
     */
    function doConfirm()
    {
        $delivery_id    = intval($_POST['delivery_id']);
        $redirect_url   = ($_POST['backUrl'] ? $_POST['backUrl'] : $this->delivery_link['order_confirm']);
        if(empty($delivery_id))
        {
            echo json_encode(array('res'=>'error', 'msg'=>'无效操作'));
            exit;
        }

        $wapDeliveryObj    = app::get('wap')->model('delivery');
        $deliveryInfo      = $wapDeliveryObj->dump(array('delivery_id'=>$delivery_id), '*');
        if(empty($deliveryInfo))
        {
            echo json_encode(array('res'=>'error', 'msg'=>'没有相关发货单'));
            exit;
        }
        elseif($deliveryInfo['status'] > 0 || $deliveryInfo['confirm'] != 3)
        {
            echo json_encode(array('res'=>'error', 'msg'=>'该发货单无法继续操作'));
            exit;
        }

        $dlyProcessLib = kernel::single('wap_delivery_process');

        //组织参数
        $params = array_merge(array('delivery_id'=>$delivery_id), $deliveryInfo);

        if($dlyProcessLib->accept($params)){

            //task任务更新统计数据
            $wapDeliveryLib    = kernel::single('wap_delivery');
            $wapDeliveryLib->taskmgr_statistic('confirm');

            echo json_encode(array('res'=>'succ', 'status'=>'已确认', 'msg'=>'订单已接收', 'delivery_bn'=>$deliveryInfo['delivery_bn']));
            exit;
        }else{
            echo json_encode(array('res'=>'error', 'msg'=>'门店确认失败'));
            exit;
        }
    }

    /**
     * 获取ConfirmDeliveryItems
     * @return mixed 返回结果
     */
    public function getConfirmDeliveryItems(){
        $delivery_id = $_POST['delivery_id'];

        #仓库对应_发货单列表
        $wapDeliveryLib    = kernel::single('wap_delivery');

        $deliveryObj = app::get('wap')->model('delivery');
        $delivery = $deliveryObj->dump(array('delivery_id' => $delivery_id), 'delivery_id,delivery_bn,outer_delivery_bn,status');

        $delivery_items = $wapDeliveryLib->getDeliveryItemList($delivery['outer_delivery_bn']);

        echo json_encode($delivery_items);exit;
    }

    /**
     * 获取发货单唯一码明细
     */
    public function getDeliverySerialInfo()
    {
        $delivery_id = intval($_POST['delivery_id']);
        $serialLib   = kernel::single('wap_delivery_serial');
        $err_msg     = '';
        $info        = $serialLib->getDeliverySerialInfo($delivery_id, $err_msg);

        if ($info === false) {
            echo json_encode(['res' => 'error', 'msg' => $err_msg ?: '获取失败']);
            exit;
        }

        echo json_encode(array_merge(['res' => 'succ'], $info));
        exit;
    }

    /**
     * 扫描唯一码
     */
    public function scanDeliverySerial()
    {
        $delivery_id  = intval($_POST['delivery_id']);
        $sn           = trim($_POST['sn']);
        $op_no        = trim($_POST['op_no'] ?? '');
        if ($op_no === '') {
            echo json_encode(['res' => 'error', 'msg' => '请先录入工号']);
            exit;
        }
        $scannedList  = [];
        if (!empty($_POST['scanned_list'])) {
            $scannedList = json_decode($_POST['scanned_list'], true);
            if (!is_array($scannedList)) {
                $scannedList = [];
            }
        }

        $serialLib = kernel::single('wap_delivery_serial');
        $err_msg   = '';
        $result    = $serialLib->scanSerial($delivery_id, $sn, $scannedList, $err_msg);

        if ($result === false) {
            echo json_encode(['res' => 'error', 'msg' => $err_msg ?: '扫描失败']);
            exit;
        }

        echo json_encode(array_merge(['res' => 'succ'], $result));
        exit;
    }

    /**
     * 备货：获取唯一码明细及已扫记录
     */
    public function getPrepareStockInfo()
    {
        $delivery_id = intval($_POST['delivery_id']);
        $serialLib   = kernel::single('wap_delivery_serial');
        $err_msg     = '';
        $info        = $serialLib->getPrepareStockInfo($delivery_id, $err_msg);

        if ($info === false) {
            echo json_encode(['res' => 'error', 'msg' => $err_msg ?: '获取失败']);
            exit;
        }

        echo json_encode(array_merge(['res' => 'succ'], $info));
        exit;
    }

    /**
     * 备货：扫描并保存唯一码
     */
    public function scanPrepareStockSerial()
    {
        $delivery_id = intval($_POST['delivery_id']);
        $sn          = trim($_POST['sn']);
        $op_no       = trim($_POST['op_no'] ?? '');

        $serialLib = kernel::single('wap_delivery_serial');
        $err_msg   = '';
        $result    = $serialLib->scanPrepareSerial($delivery_id, $sn, $op_no, $err_msg);

        if ($result === false) {
            echo json_encode(['res' => 'error', 'msg' => $err_msg ?: '扫描失败']);
            exit;
        }

        echo json_encode(array_merge(['res' => 'succ'], $result));
        exit;
    }

    /**
     * 备货：删除已扫唯一码
     */
    public function removePrepareStockSerial()
    {
        $delivery_id     = intval($_POST['delivery_id']);
        $item_serial_id  = intval($_POST['item_serial_id']);

        $serialLib = kernel::single('wap_delivery_serial');
        $err_msg   = '';
        $result    = $serialLib->removePrepareSerial($delivery_id, $item_serial_id, $err_msg);

        if ($result === false) {
            echo json_encode(['res' => 'error', 'msg' => $err_msg ?: '删除失败']);
            exit;
        }

        echo json_encode(array_merge(['res' => 'succ'], $result));
        exit;
    }

    /**
     * 备货完成：更新 WAP 发货单为已打印
     */
    public function finishPrepareStock()
    {
        $delivery_id = intval($_POST['delivery_id']);
        $serialLib   = kernel::single('wap_delivery_serial');
        $err_msg     = '';
        $result      = $serialLib->finishPrepareStock($delivery_id, $err_msg);

        if ($result === false) {
            echo json_encode(['res' => 'error', 'msg' => $err_msg ?: '备货失败']);
            exit;
        }

        echo json_encode(array_merge(['res' => 'succ', 'msg' => '备货成功'], $result));
        exit;
    }

    /**
     * 确认发货：获取备货唯一码及物流录入信息
     */
    public function getConsignPrepareInfo()
    {
        $delivery_id = intval($_POST['delivery_id']);
        $serialLib   = kernel::single('wap_delivery_serial');
        $err_msg     = '';
        $info        = $serialLib->getConsignPrepareInfo($delivery_id, $err_msg);

        if ($info === false) {
            echo json_encode(['res' => 'error', 'msg' => $err_msg ?: '获取失败']);
            exit;
        }

        $bill = app::get('wap')->model('delivery_bill')->dump(
            ['delivery_id' => $delivery_id],
            'logi_no'
        );
        $info['has_logi_no'] = !empty($bill['logi_no']);

        $delivery = app::get('wap')->model('delivery')->dump(
            ['delivery_id' => $delivery_id],
            'order_bn,is_cod'
        );
        if (!empty($delivery['order_bn'])) {
            $orderInfo = kernel::single('wap_delivery')->get_order_info($delivery['order_bn'], $delivery['is_cod']);
            $info['source_status'] = !empty($orderInfo['source_status']) ? $orderInfo['source_status'] : '';
        }

        echo json_encode(array_merge(['res' => 'succ'], $info));
        exit;
    }

    /**
     * 释放发货单唯一码预占
     */
    public function releaseDeliverySerialHold()
    {
        $delivery_id = intval($_POST['delivery_id']);
        $delivery    = app::get('wap')->model('delivery')->dump(['delivery_id' => $delivery_id], 'delivery_id');
        if (empty($delivery)) {
            echo json_encode(['res' => 'error', 'msg' => '发货单不存在']);
            exit;
        }

        kernel::single('machinecenter_sn_hold')->releaseByBill('delivery', $delivery_id);
        echo json_encode(['res' => 'succ', 'msg' => '已释放']);
        exit;
    }

    /**
     * 立即发货
     *
     * @return json
     */

    function doConsign()
    {
        $delivery_id    = intval($_POST['delivery_id']);
        $redirect_url   = ($_POST['backUrl'] ? $_POST['backUrl'] : $this->delivery_link['order_consign']);
        if(empty($delivery_id))
        {
            echo json_encode(array('res'=>'error', 'msg'=>'无效操作'));
            exit;
        }
        $filter    = array('delivery_id'=>$delivery_id);
        
        #管理员对应仓库
        $is_super = kernel::single('desktop_user')->is_super();
        if(!$is_super){
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids))
            {
                $error_msg    = '操作员没有管辖的仓库';
                
                echo json_encode(array('res'=>'error', 'msg'=>$error_msg));
                exit;
            }

            $filter['branch_id'] = $branch_ids;
        }
     
        $wapDeliveryObj    = app::get('wap')->model('delivery');
        $deliveryInfo      = $wapDeliveryObj->dump($filter, '*');


        $deliveryInfo['status']    = intval($deliveryInfo['status']);
      
        if(empty($deliveryInfo))
        {
            $error_msg    = '没有此发货单或没仓库权限,请检查';
        }
        elseif($deliveryInfo['confirm'] != 1)
        {
            //$error_msg = "该发货单还未确认,不能进行操作";
            
            if($deliveryInfo['confirm'] == 2){
                $error_msg = "该发货单已被拒绝,不能进行操作";
            }
        }
        elseif($deliveryInfo['status'] !== 0)
        {
            $error_msg    = '该发货单状态不正确,不能进行操作';
            
            if($deliveryInfo['status'] == 3){
                $error_msg    = '该发货单已发货,不能进行操作'.$deliveryInfo['status'];
            }
        }
        
        //错误提示
        if($error_msg)
        {
            echo json_encode(array('res'=>'error', 'msg'=>$error_msg));
            exit;
        }
        
        $deliveryInfo['order_number']  = 1;

        $deliveryBill = app::get('wap')->model('delivery_bill')->dump(
            array('delivery_id' => $delivery_id),
            'logi_no'
        );
        $postLogiNo   = trim($_POST['logi_no'] ?? '');
        $postLogiCode = trim($_POST['logi_code'] ?? '');
        if (empty($deliveryBill['logi_no'])) {
            if ($postLogiNo === '' || $postLogiCode === '') {
                echo json_encode(array('res' => 'error', 'msg' => '请填写快递公司和运单号'));
                exit;
            }
            $saveErr = '';
            if (!kernel::single('wap_delivery_process')->saveLogiNoForConsign(
                $delivery_id,
                $postLogiNo,
                $postLogiCode,
                $saveErr
            )) {
                echo json_encode(array('res' => 'error', 'msg' => $saveErr ?: '保存运单号失败'));
                exit;
            }
        }

        $serialLib      = kernel::single('wap_delivery_serial');
        $formattedItems = [];
        $serialInfo = $serialLib->getDeliverySerialInfo($delivery_id, $serialErr);
        if ($serialInfo === false) {
            echo json_encode(['res' => 'error', 'msg' => $serialErr ?: '唯一码校验失败']);
            exit;
        }
        if (!empty($serialInfo['has_serial'])) {
            $serialItems = [];
            if (!empty($_POST['serial_items'])) {
                $serialItems = json_decode($_POST['serial_items'], true);
            }
            if (empty($serialItems)) {
                $serialItems = $serialLib->buildSerialItemsPayloadFromPrepare($delivery_id);
            }
            $formattedItems = $serialLib->validateSerialItemsForConsign($delivery_id, $serialItems, $serialErr);
            if ($formattedItems === false) {
                echo json_encode(['res' => 'error', 'msg' => $serialErr ?: '唯一码校验失败']);
                exit;
            }
            $deliveryInfo['serial_items'] = $formattedItems;
        }

        $consignOp = kernel::single('wap_delivery_process')->getConsignOp();
        $opNo = trim($_POST['op_no'] ?? '');
        if ($opNo === '' && !empty($serialInfo['has_serial'])) {
            $savedPrepare = $serialLib->loadPrepareSerialRows($delivery_id);
            $opNo = trim($savedPrepare['op_no'] ?? '');
        }
        if (!empty($serialInfo['has_serial']) && $opNo === '') {
            echo json_encode(['res' => 'error', 'msg' => '请先录入工号']);
            exit;
        }
        if ($opNo !== '') {
            $consignOp['op_no'] = $opNo;
            $deliveryInfo['scanned_op_no'] = $opNo;
        }
        $deliveryInfo = array_merge($deliveryInfo, $consignOp);
        
        //执行发货（machinecenter SN 主数据走 OME 回写；门店发货唯一码记录需 scanned_op_no）
        $dlyProcessLib  = kernel::single('wap_delivery_process');
        $res            = $dlyProcessLib->consign($deliveryInfo);
      
        if($res){
            
            //task任务更新统计数据
            $wapDeliveryLib    = kernel::single('wap_delivery');
            $wapDeliveryLib->taskmgr_statistic('consign');
            
            echo json_encode(array('res'=>'succ', 'msg'=>'发货成功'));
            exit;
        }else { 
            if (!empty($formattedItems)) {
                kernel::single('machinecenter_sn_hold')->releaseByBill('delivery', $delivery_id);
            }
            echo json_encode(array('res'=>'error', 'msg'=>'发货失败'));
            exit;
        }
    }
    

    /**
     * 重发提货校验码
     * 
     * @return json
     */
    function sendMsg()
    {
        //开启销单校验码才能重新生成校验码
        if(app::get('o2o')->getConf('o2o.delivery.confirm.code') != "true"){
            echo json_encode(array('res'=>'error', 'msg'=>'请开启销单校验码'));
            exit;
        }

        $delivery_bn    = $_POST['delivery_bn'];

        if(empty($delivery_bn))
        {
            echo json_encode(array('res'=>'error', 'msg'=>'无效操作'));
            exit;
        }

        $wapDeliveryObj    = app::get('wap')->model('delivery');
        $deliveryInfo      = $wapDeliveryObj->dump(array('delivery_bn'=>$delivery_bn), '*');
        if(empty($deliveryInfo))
        {
            echo json_encode(array('res'=>'error', 'msg'=>'没有相关发货单'));
            exit;
        }
        elseif($deliveryInfo['status'] != 3 || $deliveryInfo['confirm'] != 1 || $deliveryInfo['process_status'] != 7)
        {
            echo json_encode(array('res'=>'error', 'msg'=>'发货单还没有发货'));
            exit;
        }
        elseif($deliveryInfo['is_received'] == 2)
        {
            echo json_encode(array('res'=>'error', 'msg'=>'发货单已签收完成'));
            exit;
        }

        $dlyProcessLib = kernel::single('wap_delivery_process');
        $res    = $dlyProcessLib->reSendMsg($deliveryInfo);
        if($res)
        {
            echo json_encode(array('res'=>'succ', 'msg'=>'提货校验码发送成功'));
            exit;
        }
        else
        {
            echo json_encode(array('res'=>'error', 'msg'=>'提货校验码发送失败'));
            exit;
        }
    }

    /**
     * 显示订单详情
     */
    function showOrderInfo()
    {
        $filehtml    = 'order/sign_order_info.html';

        $delivery_bn    = $_POST['delivery_bn'];
        $flag           = $_POST['flag'];
        $error_msg      = '';

        //先检查发货单号 和 管理员对应仓库
        if(empty($delivery_bn))
        {
            $error_msg    = '请填写发货单号';

            $this->pagedata['error_msg']    = $error_msg;
            $this->display($filehtml);
            exit;
        }

        $filter    = array('delivery_bn'=>$delivery_bn);

        #管理员对应仓库
        $is_super = kernel::single('desktop_user')->is_super();
        if(!$is_super)
        {
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids)){
                $error_msg    = '操作员没有管辖的仓库';

                $this->pagedata['error_msg']    = $error_msg;
                $this->display($filehtml);
                exit;
            }

            $filter['branch_id'] = $branch_ids;
        }

        $wapDeliveryObj    = app::get('wap')->model('delivery');
        $deliveryInfo      = $wapDeliveryObj->dump($filter, '*');

        if(empty($deliveryInfo))
        {
            $error_msg    = '没有此发货单,请检查';

            $this->pagedata['error_msg']    = $error_msg;
            $this->display($filehtml);
            exit;
        }

        #获取发货仓库对应的门店店铺信息
        $wapDeliveryLib    = kernel::single('wap_delivery');
        $dlyProcessLib     = kernel::single('wap_delivery_process');
        $branchShopInfo    = $wapDeliveryLib->getBranchShopInfo($deliveryInfo['branch_id']);

        $deliveryInfo['order_number']  = 1;

        #合并数据
        $result    = array_merge($deliveryInfo, $branchShopInfo);
        unset($data['wms_id'], $data['store_id'], $data['area'], $data['confirm'], $data['branch_id']);

        //显示发货单信息
        $result['dly_status']    = $wapDeliveryLib->formatDeliveryStatus('status', $result['status']);
        $result['dly_confirm']   = $wapDeliveryLib->formatDeliveryStatus('confirm', $result['confirm']);

        #获取订单信息
        $result['order_info']    = $wapDeliveryLib->get_order_info($result['order_bn'], $result['is_cod']);

        #获取发货单明细
        $result['delivery_items']    = $wapDeliveryLib->getDeliveryItemList($result['outer_delivery_bn']);

        #履约超时时间
        $result['dly_overtime']    = $wapDeliveryLib->getDeliveryOvertime($result['create_time']);

        //百度地图
        $baidu_map_show = app::get('o2o')->getConf('o2o.baidumap.show');
        if($baidu_map_show == 'true'){
            $result['show_map'] = true;
        }

        $this->pagedata['dlyinfo']    = $result;
        $this->display($filehtml);
        exit;
    }

    /**
     * 签收核销列表
     */
    function sign()
    {
        $this->delivery_type    = 'sign';

        $sub_menu    = $this->_views_confirm($this->delivery_type);
        $filter      = $sub_menu[$this->delivery_type]['filter'];
        $title       = $sub_menu[$this->delivery_type]['label'];

        $offset            = $sub_menu[$this->delivery_type]['offset'];
        $limit             = $sub_menu[$this->delivery_type]['limit'];

        #仓库对应_发货单列表
        $wapDeliveryLib    = kernel::single('wap_delivery');

        $dataList          = $wapDeliveryLib->getList($filter, $offset, $limit, $sub_menu[$this->delivery_type]['orderby'], $this->delivery_type);

        $this->pagedata['title']       = $title;
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['pageSize']    = $sub_menu[$this->delivery_type]['pageSize'];

        $this->pagedata['link_url']    = $sub_menu[$this->delivery_type]['href'];

        //baidu map button show or not
        $baidu_map_show = app::get('o2o')->getConf('o2o.baidumap.show');
        if($baidu_map_show=="true"){
            $this->pagedata["baidu_map_show"] = true;
        }

        if($offset > 0)
        {
            //Ajax加载更多
            $this->display('order/order_list_more.html');
        }
        else
        {
            $this->display('order/order_list.html');
        }
    }

    /**
     * 核销页面
     */
    function signPage()
    {
        $wapDeliveryObj    = app::get('wap')->model('delivery');

        $delivery_id    = intval($_GET['delivery_id']);

        //发货单
        if($delivery_id)
        {
            $deliveryInfo      = $wapDeliveryObj->dump(array('delivery_id'=>$delivery_id), '*');
        }

        $this->pagedata['deliveryInfo']    = $deliveryInfo;

        //销单校验码开关 关闭状态不显示相关校验码input/button
        if(app::get('o2o')->getConf('o2o.delivery.confirm.code') == "true"){
            $this->pagedata["code_html_show"] = true;
        }

        $this->display('order/sign.html');
    }

    /**
     * 最终签收
     * 
     * @return json
     */
    function doSign()
    {
        $delivery_id    = $_POST['delivery_id'];
        $flag           = $_POST['flag'];
        $error_msg      = '';

        //先检查发货单号 和 管理员对应仓库
        if(empty($delivery_id))
        {
            $error_msg    = '请填写发货单号';

            echo json_encode(array('error'=>true, 'message'=>$error_msg, 'redirect'=>null));
            exit;
        }

        //[防并发]防止重复点击
        $cacheKeyName = sprintf("do_delivery_%s", $_POST['delivery_id']);
        $cacheData = cachecore::fetch($cacheKeyName);
        if($cacheData !== false) {
            echo json_encode(array('res'=>'error', 'msg'=>'请勿重复操作'));
            exit;
        }
        //[防并发]判断重复请求(3秒之内不能重复)
        cachecore::store($cacheKeyName, date('YmdHis', time()), 3);


        $filter    = array('delivery_id'=>$delivery_id);

        #管理员对应仓库
        $is_super = kernel::single('desktop_user')->is_super();
        if(!$is_super){
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids)){
                $error_msg    = '操作员没有管辖的仓库';

                echo json_encode(array('res'=>'error', 'message'=>$error_msg, 'redirect'=>null));
                exit;
            }

            $filter['branch_id'] = $branch_ids;
        }

        $wapDeliveryObj    = app::get('wap')->model('delivery');
        $deliveryInfo      = $wapDeliveryObj->dump($filter, '*');

        if(empty($deliveryInfo))
        {
            $error_msg    = '没有此发货单,请检查';
        }
        elseif($deliveryInfo['status'] != 3 || $deliveryInfo['confirm'] != 1 || $deliveryInfo['process_status'] != 7)
        {
            $error_msg    = '发货单还没有发货';
        }
        elseif($deliveryInfo['is_received'] == 2)
        {
            $error_msg    = '发货单已签收完成';
        }

        //错误提示
        if($error_msg)
        {
            echo json_encode(array('res'=>'error', 'msg'=>$error_msg, 'redirect'=>null));
            exit;
        }

        $deliveryInfo['order_number']  = 1;
        if(count($_FILES['deliveryImage']['tmp_name'])>3){
            echo json_encode(array('res'=>'error','msg'=>'最多上传3张图片'));
            exit;
        }

        
        //上传图片
        
        $post = file_get_contents('php://input');
        $post = json_decode($post, true);
       
        $msg = '';

        if(!$_FILES['deliveryImage']){
            echo json_encode(array('res'=>'error', 'msg'=>'请上传配送单图片'));
            exit;
        }
        $deliveryImage = [];
        // 获取图片模型
        $imageModel = app::get('image')->model('image');
        //上传并保存图片
        if($_FILES['deliveryImage']){
            $file_obj = app::get("base")->model("files");
            $fileList = kernel::single('base_storager');
            foreach($_FILES['deliveryImage']['tmp_name'] as $k => $v){
                $img = array(
                    'name' => $v,
                    'full_path' => $_FILES['deliveryImage']['full_path'][$k],
                    'type' => $_FILES['deliveryImage']['type'][$k],
                    'tmp_name' => $_FILES['deliveryImage']['tmp_name'][$k],
                    'error' => $_FILES['deliveryImage']['error'][$k],
                    'size' => $_FILES['deliveryImage']['size'][$k],
                );

                $rs = kernel::single('wap_deliveryimg')->uploadImage($delivery_id, $img['tmp_name'], $img['name']);
               
                if($rs['error']){
                    echo json_encode(array('res'=>'error', 'msg'=>$rs['error']));
                    exit;
                }
               
            }
        }
       
        //执行签收
        $dlyProcessLib    = kernel::single('wap_delivery_process');
        $res              = $dlyProcessLib->sign($deliveryInfo);

        if($res){

            echo json_encode(array('res'=>'succ', 'msg'=>'操作成功'));
            exit;
        }else {
            echo json_encode(array('res'=>'error', 'msg'=>'操作失败 '.$msg));
            exit;
        }
    }


    function uploadimage()
    {
        $delivery_id    = $_POST['delivery_id'];
        $flag           = $_POST['flag'];
        $error_msg      = '';

        //先检查发货单号 和 管理员对应仓库
        if(empty($delivery_id))
        {
            $error_msg    = '请填写发货单号';

            echo json_encode(array('res'=>'error', 'message'=>$error_msg, 'redirect'=>null));
            exit;
        }

        //[防并发]防止重复点击
        $cacheKeyName = sprintf("do_delivery_%s", $_POST['delivery_id']);
        $cacheData = cachecore::fetch($cacheKeyName);
        if($cacheData !== false) {
            echo json_encode(array('res'=>'error', 'msg'=>'请勿重复操作'));
            exit;
        }
        //[防并发]判断重复请求(3秒之内不能重复)
        cachecore::store($cacheKeyName, date('YmdHis', time()), 3);


        $filter    = array('delivery_id'=>$delivery_id);

        #管理员对应仓库
        $is_super = kernel::single('desktop_user')->is_super();
        if(!$is_super){
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids)){
                $error_msg    = '操作员没有管辖的仓库';

                echo json_encode(array('res'=>'error', 'message'=>$error_msg, 'redirect'=>null));
                exit;
            }

            $filter['branch_id'] = $branch_ids;
        }

        $wapDeliveryObj    = app::get('wap')->model('delivery');
        $deliveryInfo      = $wapDeliveryObj->dump($filter, '*');

        if(empty($deliveryInfo))
        {
            $error_msg    = '没有此发货单,请检查';
        }elseif($deliveryInfo['is_received'] != 2)
        {
            $error_msg    = '发货单没有签收';
        }
        $wap_delivery_id = $deliveryInfo['delivery_id'];
        //
        $wapdeliveryimages = kernel::single('wap_deliveryimg')->getwapdeliveryImages($wap_delivery_id);
        if(count($wapdeliveryimages)<=0){

            $error_msg    = '请上传签收图片';
        }
        if(count($wapdeliveryimages)>3){

            $error_msg    = '签收图片限定三张';
        }
        if((count($wapdeliveryimages)+count($_FILES['deliveryImage']['tmp_name']))>3){
            $error_msg    = '签收图片超过三张';

        }
        //错误提示
        if($error_msg)
        {
            echo json_encode(array('res'=>'error','msg'=>$error_msg, 'redirect'=>null));
            exit;
        }

        $deliveryInfo['order_number']  = 1;

        
        //上传图片
        
        $post = file_get_contents('php://input');
        $post = json_decode($post, true);
       
        $msg = '';

        if(!$_FILES['deliveryImage']){
            echo json_encode(array('res'=>'error', 'msg'=>'请上传配送单图片'));
            exit;
        }
        $deliveryImage = [];
        $imageModel = app::get('image')->model('image');
        //上传并保存图片
        if($_FILES['deliveryImage']){
            $file_obj = app::get("base")->model("files");
            $fileList = kernel::single('base_storager');
            foreach($_FILES['deliveryImage']['tmp_name'] as $k => $v){
                $img = array(
                    'name' => $v,
                    'full_path' => $_FILES['deliveryImage']['full_path'][$k],
                    'type' => $_FILES['deliveryImage']['type'][$k],
                    'tmp_name' => $_FILES['deliveryImage']['tmp_name'][$k],
                    'error' => $_FILES['deliveryImage']['error'][$k],
                    'size' => $_FILES['deliveryImage']['size'][$k],
                );

                $rs = kernel::single('wap_deliveryimg')->uploadImage($delivery_id, $img['tmp_name'], $img['name']);
                if($rs['error']){
                    echo json_encode(array('res'=>'error', 'msg'=>$rs['error']));
                    exit;
                }
                
            }
        }

        echo json_encode(array('res'=>'succ', 'msg'=>'上传成功'));
        exit;
    }
   

   
    /**
     * onlineDelivery
     * @return mixed 返回值
     */
    public function onlineDelivery() {
        $delivery_id = $_REQUEST['delivery_id'];
        #仓库对应_发货单列表
        $wapDeliveryLib    = kernel::single('wap_delivery');
        $deliveryObj = app::get('wap')->model('delivery');
        $delivery = $deliveryObj->dump(array('delivery_id' => $delivery_id), 'delivery_id,delivery_bn,outer_delivery_bn,status,branch_id');


        $storeObj       = app::get('o2o')->model('store');
        $storeInfo      = $storeObj->dump(array('branch_id' => $delivery['branch_id']), 'store_id');
        $this->pagedata['store_id'] = $storeInfo['store_id'];

        $delivery_items = $wapDeliveryLib->getDeliveryItemList($delivery['outer_delivery_bn']);

        $this->pagedata['title'] = '打印配货单';
        $this->pagedata['delivery_items'] = $delivery_items;
        $this->pagedata['delivery_id'] = $delivery_id;
       
        $action = $_REQUEST['action'];
        $this->pagedata['action'] = $action;
        $sdf = kernel::single('wap_event_trigger_cloudprint')->getPrintData($delivery_id);

        if($sdf['rsp']=='fail'){
            
            $this->pagedata['msg'] = $sdf['msg'];
            $this->pagedata['order_url'] = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'index'), true);
            $this->display('order/order_print_error.html');
            exit;
        }
        $dly_tmpl_id = $sdf['dly_tmpl_id'];


        $data = kernel::single('wap_event_trigger_cloudprint')->processprintdata($sdf);

        require_once(APP_DIR.'/wap/lib/generate_template_image.php');
     
        // 创建生成器实例
        $generator = new TemplateImageGenerator();
        $base64_image = $generator->getBase64Image($dly_tmpl_id, $data);

        $this->pagedata['base64_image'] = $base64_image;
        $this->display('order/order_print_delivery.html');
    }


   

   
    /**
     * 获取微信签名
     */
    public function getWxSign() {
        $url = $_POST['url'];

        $msg = '';
        $wxSign = kernel::single('wap_wechat_sign')->getSign($url, $msg);
        if (!$wxSign) {
            $this->error($msg ? $msg : '网络异常，请重试');
        }

        $this->success('签名成功', $wxSign);
    }

    
}
