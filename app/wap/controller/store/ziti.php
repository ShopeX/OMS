<?php

class wap_ctl_store_ziti extends wap_controller{

    var $ziti_link    = array();

    function __construct($app)
    {
        parent::__construct($app);


        //获取自提订单信息
        $this->ziti_link['getZitiOrdInfo']  = app::get('wap')->router()->gen_url(array('ctl'=>'store_ziti','act'=>'getZitiOrdInfo'), true);
        $this->ziti_link['doVerify']  = app::get('wap')->router()->gen_url(array('ctl'=>'store_ziti','act'=>'do_verify'), true);

        $this->pagedata['ziti_link']   = $this->ziti_link;
    }

    function _views($curr_view){

        $ordMdl = app::get('ome')->model("orders");

        $page = intval($_POST['page']) ? intval($_POST['page'])-1 : 0;
        $limit = 10; //默认显示10条
        $offset = $limit * $page;

        $base_filter = array(
            "order_type" => "selfpickup",
        );
        $is_super    = kernel::single('desktop_user')->is_super();
        $store_list = array("0" => "请选择门店");
        if(!$is_super)
        {
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids))
            {
                $branch_ids = [0];
            }
            $storeMdl = app::get("o2o")->model("store");
            $storeList = $storeMdl->getList("store_id,name", array("branch_id" => $branch_ids));
            $store_ids = array_column($storeList, "store_id");
            $base_filter['selfpickup_store_id'] = $store_ids ?: [0];
            $store_l = array_column($storeList, "name", "store_id");
            $store_list = $store_list+$store_l;
        }

        $wap_router = app::get('wap')->router();
        $sub_menu = array(
            'all' => array('label'=>app::get('base')->_('全部'),'filter'=>$base_filter,'href'=>$wap_router->gen_url(array('ctl'=>'store_ziti','act'=>'index'), true)),
            'wait_pickup' => array('label'=>app::get('base')->_('待提货'),'filter'=>array_merge($base_filter,array("ziti_verify_status"=>'1', 'pay_status' => '1', 'ship_status' => '0')),'href'=>$wap_router->gen_url(array('ctl'=>'store_ziti','act'=>'index?view=wait_pickup'), true)),
            'pickuped' => array('label'=>app::get('base')->_('已提货'),'filter'=>array_merge($base_filter,array("ziti_verify_status"=>'2')),'href'=>$wap_router->gen_url(array('ctl'=>'store_ziti','act'=>'index?view=pickuped'), true)),
            'refunded' => array('label'=>app::get('base')->_('已退款'),'filter'=>array_merge($base_filter,array("pay_status"=>['4','5'])),'href'=>$wap_router->gen_url(array('ctl'=>'store_ziti','act'=>'index?view=refunded'), true)),
        );

        foreach($sub_menu as $k=>$v){
            //Ajax加载下一页数据,只处理本页
            if($_POST['flag'] == 'ajax' && $curr_view != $k){
                continue;
            }

            $orderby = 'createtime asc';
            $orderby_word = "createtime";

            $sub_menu[$k]['offset']    = $offset;
            $sub_menu[$k]['limit']     = $limit;
            $sub_menu[$k]['orderby']   = $orderby; //排序
            $sub_menu[$k]['store_list']   = $store_list; //门店列表


            //时间筛选
            if($_POST['dateType']){
                switch ($_POST['dateType']){
                    case "today":
                        $toDayTime = strtotime(date("Y-m-d"));
                        $v['filter'][$orderby_word.'|bthan'] = $toDayTime;
                        break;
                    case "yesterday":
                        $startTime = strtotime(date("Y-m-d", strtotime("-1 day")));
                        $endTime = strtotime(date("Y-m-d"));
                        $v['filter'][$orderby_word.'|bthan'] = $startTime;
                        $v['filter'][$orderby_word.'|sthan'] = $endTime;
                        break;
                    case "month":
                        $startTime = strtotime(date("Y-m"));
                        $v['filter'][$orderby_word.'|bthan'] = $startTime;
                        break;
                    case "custom":
                        $startTime = strtotime($_POST['start_time']);
                        $endTime = strtotime($_POST['end_time']);
                        $v['filter'][$orderby_word.'|bthan'] = $startTime;
                        $v['filter'][$orderby_word.'|sthan'] = $endTime;
                        break;
                }
            }

            //搜索条件
            $sel_keywords = htmlspecialchars(trim($_POST['sel_keywords']));
            $mdl_ome_orders = app::get('ome')->model("orders");
            if($_POST['sel_type'] && $sel_keywords){
                switch ($_POST['sel_type']){
                    case 'order_bn':
                        //先获取order_ids
                        $rs_order = $mdl_ome_orders->getList("order_id",array("order_bn|foot"=>$sel_keywords));
                        if(!empty($rs_order)){
                            $order_ids = array();
                            foreach ($rs_order as $var_o){
                                $order_ids[] = $var_o["order_id"];
                            }
                            $v['filter']['order_id'] = $order_ids;
                        }else{
                            $not_show_datalist = true;
                        }
                        break;
                    case "channel":
                        if($sel_keywords != 'none'){
                            $v['filter']['shop_type'] = $sel_keywords;
                        }
                        break;
                    case "branch_id":
                        if($sel_keywords != '0'){
                            $v['filter']['selfpickup_store_id'] = $sel_keywords;
                        }
                        break;
                    case 'buyer_mobile':
                        //先获取order_ids
                        $rs_order = $mdl_ome_orders->getList("order_id",array("consigner_mobile|foot"=>$sel_keywords));
                        if(!empty($rs_order)){
                            $order_ids = array();
                            foreach ($rs_order as $var_o){
                                $order_ids[] = $var_o["order_id"];
                            }
                            $v['filter']['order_id'] = $order_ids;
                        }else{
                            $not_show_datalist = true;
                        }
                        break;
                    case 'receiver_mobile':
                        //先获取order_ids
                        $rs_order = $mdl_ome_orders->getList("order_id",array("ship_mobile|foot"=>$sel_keywords));
                        if(!empty($rs_order)){
                            $order_ids = array();
                            foreach ($rs_order as $var_o){
                                $order_ids[] = $var_o["order_id"];
                            }
                            $v['filter']['order_id'] = $order_ids;
                        }else{
                            $not_show_datalist = true;
                        }
                        break;
                }
                if ($not_show_datalist){//搜索不满足条件的不显示列表
                    $sub_menu[$k]['not_show_datalist'] = true;
                }
                $sub_menu[$k]['sel_type'] = $_POST['sel_type'];
                $sub_menu[$k]['sel_keywords'] = $sel_keywords;
            }
            //搜索并入
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : array();
            $count = 0;
            if ($k == 'wait_pickup') {
                $count = $ordMdl->count($v['filter']);
            }
            $sub_menu[$k]['count']  = $count;
            $pageSize = ceil($count / $limit);
            if($pageSize <= $page){
                $sub_menu[$k]['hasMore']  = false;
            }
            $sub_menu[$k]['pageSize'] = $pageSize;

            $sub_menu[$k]['curr_view'] = false;
            if($k == $curr_view){//选中状态
                $sub_menu[$k]['curr_view'] = true;
            }
        }

        return $sub_menu;
    }

    public function index(){
        //$post = file_get_contents('php://input');
        //$_POST = json_decode($post, true);
        if ($_GET['view'] || $_POST['menu_type']){
            $menu_type = $_GET['view'] ?: $_POST['menu_type'];
            $this->common($menu_type);
        }else{
            $this->common("all");
        }
    }

    //列表公共加载方法
    private function common($menu_type){
        //标签Tabs处理
        $sub_menu = $this->_views($menu_type);
        $this->pagedata['sub_menu'] = $sub_menu;
        $this->pagedata['title'] = $sub_menu[$menu_type]['label'];

        $filter = $sub_menu[$menu_type]["filter"];
        $offset = $sub_menu[$menu_type]['offset'];
        $limit = $sub_menu[$menu_type]['limit'];
        $orderby = $sub_menu[$menu_type]['orderby'];
        $store_list = $sub_menu[$menu_type]['store_list'];
        $this->pagedata['store_list'] = $store_list;

        //ajax点击加载参数
        $this->pagedata['pageSize'] = $sub_menu[$menu_type]['pageSize'];
        $this->pagedata['link_url']    = $sub_menu[$menu_type]['href'];
        $this->pagedata['menu_type']    = $menu_type;
        $channelList = array(
            'none' => '请选择渠道',
            'xhs' => '小红书',
            'luban' => '抖音',
            '360buy' => '京东',
            'wxshipin' => '微信视频号',
            'ecos.ecshopx' => '微信小程序',
        );
        $this->pagedata['channelList'] = $channelList;

        if ($sub_menu[$menu_type]['not_show_datalist']){//搜索不满足条件的不显示列表
        }else{
            //默认获取列表数据
            $wap_ziti_lib = kernel::single('wap_ziti');
            $dataList = $wap_ziti_lib->getList($filter, $offset, $limit,$orderby, $menu_type);
            if(!empty($dataList)){//有列表数据
                //操作按钮链接
                $this->delivery_link['doVerify'] = app::get('wap')->router()->gen_url(array('ctl'=>'store_ziti','act'=>'doVerify'), true);
                $this->pagedata["dataList"] = $dataList;
            }
        }

        //搜索选择状态保存
        if ($sub_menu[$menu_type]['sel_type'] && $sub_menu[$menu_type]['sel_keywords']){
            $this->pagedata["sel_type"] = $sub_menu[$menu_type]['sel_type'];
            $this->pagedata["sel_keywords"] = $sub_menu[$menu_type]['sel_keywords'];
        }

        //给公共footer附上link 带上所以的父类构造方法的link
        $this->pagedata['delivery_link'] = $this->delivery_link;

        if($_POST['flag'] == 'ajax'){//Ajax加载更多
            $this->display('ziti/order_list_more.html');
        }else{
            $this->display('ziti/order_list.html');
        }
    }

    public function verify_ziti(){
        $code = $_GET['code'];
        $code_arr = explode("_", $code);
        $ziti_code = $code_arr[1];
        $order_bn = $code_arr[2];
        $orderInfo = $this->gePlatformtOrdInfo($order_bn);

        if($orderInfo){
            $this->pagedata['order_info'] = $orderInfo;
        }else{
            $this->pagedata['err_msg'] = "未获取到订单信息，请核实";
        }
        $this->pagedata['ziti_code'] = $ziti_code;
        $this->pagedata['order_bn'] = $order_bn;
        $this->pagedata['verify_return_url'] = app::get('wap')->router()->gen_url(array('ctl'=>'store_ziti','act'=>'index?view=wait_pickup'), true);;
        $this->pagedata['do_verify_url'] = app::get('wap')->router()->gen_url(array('ctl'=>'store_ziti','act'=>'do_verify'), true);;
        $this->display('ziti/verify.html');
    }

    public function gePlatformtOrdInfo($order_bn){
        $ordMdl = app::get("ome")->model("orders");
        $ordInfo = $ordMdl->dump(array("order_bn" => $order_bn), "*");
        if (empty($ordInfo)) {
            return false;
        }
        $shop_id = $ordInfo['shop_id'];
        $err_msg = '';
        $data = kernel::single("ome_ecapi_order")->getPlatformOrdInfo($order_bn, $shop_id, $err_msg);

        if ($data && isset($data['trade'])) {
            return kernel::single("wap_ziti")->formatOrdData($ordInfo);
        }
        // 无前端店铺接口或本地自提单：使用 OMS 订单数据展示
        if ($ordInfo['order_type'] == 'selfpickup') {
            return kernel::single("wap_ziti")->formatOrdData($ordInfo);
        }
        return false;
    }

    public function do_verify(){
        $post = file_get_contents('php://input');
        $post = json_decode($post, true);
        $params = array_merge((array)$_POST, (array)$post);
        $ziti_code = $params['ziti_code'];
        $order_bn = $params['order_bn'];
        $code_type = $params['code_type'] ?: '';
        $err_msg = '';
        $res = kernel::single("ome_ecapi_order")->ziti_verify($order_bn, $ziti_code, $err_msg, $code_type);
        if(!$res){
            echo json_encode(array('rsp' => 'fail', 'msg' => '核销失败：'.$err_msg, 'err_msg' => $err_msg));exit;
        }
        //执行自动发货，签收
        $result = kernel::single("wap_ziti")->autoConsignZiti($order_bn, $err_msg);
        if($result){
            echo json_encode(array('rsp' => 'succ', 'msg' => '核销成功'));exit;
        }
        echo json_encode(array('rsp' => 'fail', 'msg' => 'OMS内部逻辑处理失败：'.$err_msg, 'err_msg' => 'OMS内部逻辑处理失败：'.$err_msg));exit;
    }

    public function getZitiOrdInfo(){
        $order_bn = $_POST['order_bn'];
        $orderInfo = $this->gePlatformtOrdInfo($order_bn);
        if($orderInfo){
            echo json_encode(array('rsp' => 'succ', 'data' => $orderInfo));exit;
        }
        echo json_encode(array('rsp' => 'fail', 'msg' => '获取订单信息失败'));exit;
    }

    /**
     * 获取微信签名
     */
    public function getWxSign() {
        $url = $_POST['url'];

        // 获取access_token
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            $this->error('网络异常，请重试');
        }

        // 检查SCAN_APP_ID是否已定义
        if (!defined('SCAN_APP_ID')) {
            $this->error('请联系管理员配置此功能');
        }

        // 获取jsapi_ticket
        $jsapi_ticket = $this->getJsapiTicket($access_token);
        if (!$jsapi_ticket) {
            $this->error('网络异常，请重试');
        }

        // 生成签名
        $nonceStr = $this->createNonceStr();
        $timestamp = time();
        $string = "jsapi_ticket={$jsapi_ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($string);

        $ziti_url = app::get('wap')->router()->gen_url(array('ctl'=>'store_ziti','act'=>'verify_ziti'), true);
        $this->success('签名成功', array(
            'appId' => SCAN_APP_ID,
            'timestamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => $signature,
            'ziti_url' => $ziti_url,
        ));
    }

    /**
     * 获取access_token
     */
    private function getAccessToken() {
        // 先从缓存获取
        $kvstore = base_kvstore::instance('weixin');
        $kvstore->fetch('access_token', $access_token);

        if ($access_token) {
            return $access_token;
        }

        // 缓存不存在则重新获取
        $appid = SCAN_APP_ID;
        $secret = SCAN_APP_SECRET;

        // 使用稳定版接口
        $url = "https://api.weixin.qq.com/cgi-bin/stable_token";

        // 准备POST数据
        $postData = array(
            'grant_type' => 'client_credential',
            'appid' => $appid,
            'secret' => $secret,
            'force_refresh' => false,
        );

        // 发起POST请求
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type: application/json',
                'content' => json_encode($postData)
            )
        );
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $data = json_decode($response, true);

        if (isset($data['access_token'])) {
            // 写入缓存,有效期设为比expires_in少200秒,确保在token过期前刷新
            $expires = isset($data['expires_in']) ? $data['expires_in'] : 7200;
            $kvstore->store('access_token', $data['access_token'], $expires);
            return $data['access_token'];
        }
        return false;
    }

    /**
     * 获取jsapi_ticket
     */
    private function getJsapiTicket($access_token) {
        // 先从缓存获取
        base_kvstore::instance('weixin')->fetch('jsapi_ticket', $ticket);

        if ($ticket) {
            return $ticket;
        }

        // 缓存不存在则重新获取
        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token={$access_token}";

        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (isset($data['ticket'])) {
            // 写入缓存,有效期7000秒
            base_kvstore::instance('weixin')->store('jsapi_ticket', $data['ticket'], 6000);
            return $data['ticket'];
        }
        return false;
    }

    /**
     * 生成随机字符串
     */
    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

}