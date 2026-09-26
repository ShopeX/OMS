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
 * 门店售后 退货单 20170525 by wangjianjun@shopex.cn
 */
class wap_ctl_aftersale_returnproduct extends wap_controller{
    
    function _views($curr_view){

        $mdl_wap_return = app::get('wap')->model("return");

        $page = intval($_POST['page']) ? intval($_POST['page']) : 0;
        $limit = 10; //默认显示10条
        $offset = $limit * $page;

        $base_filter = array('return_type'=>'return');
        $is_super    = kernel::single('desktop_user')->is_super();
        $store_list = array("0" => "请选择门店");
        if(!$is_super)
        {
           
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            $base_filter['branch_id']    = $branch_ids;
          
        }

        $wap_router = app::get('wap')->router();
        $sub_menu = array(
                'all' => array('label'=>app::get('base')->_('全部'),'filter'=>$base_filter,'href'=>$wap_router->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'index'), true)),
                'pending' => array('label'=>app::get('base')->_('待退货'),'filter'=>array_merge($base_filter,array("status"=>1)),'href'=>$wap_router->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'pending'), true)),
               
                'already' => array('label'=>app::get('base')->_('已退货'),'filter'=>array_merge($base_filter,array("status"=>3)),'href'=>$wap_router->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'already'), true)),
                 'refused' => array('label'=>app::get('base')->_('退货取消'),'filter'=>array_merge($base_filter,array("status"=>2)),'href'=>$wap_router->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'refused'), true)),
        );

        foreach($sub_menu as $k=>$v){
            //Ajax加载下一页数据,只处理本页
            if($_POST['flag'] == 'ajax' && $curr_view != $k){
                continue;
            }

            $sub_menu[$k]['offset']    = $offset;
            $sub_menu[$k]['limit']     = $limit;
            $sub_menu[$k]['orderby']   = 'return_id desc'; //排序
            if ($_POST['dateType']) {
                $dateType = $_POST['dateType'];
                $datefilter = $this->getCreateTimeByDateType($dateType);
                $v['filter'] = array_merge($v['filter'],$datefilter);
            }
            //搜索条件
            $sel_keywords = htmlspecialchars(trim($_POST['sel_keywords']));
            $mdl_ome_orders = app::get('ome')->model("orders");
            if($_POST['sel_type'] && $sel_keywords){
                switch ($_POST['sel_type']){
                    case 'order_no':
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
            if ($k == 'pending') {
                $count = $mdl_wap_return->count($v['filter']);
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
    
    //退货单“全部”的列表显示
    /**
     * index
     * @return mixed 返回值
     */

    public function index(){
        $post = file_get_contents('php://input');
        $_POST = json_decode($post, true);
        if ($_GET['view'] || $_POST['menu_type']){
            $menu_type = $_GET['view'] ?: $_POST['menu_type'];
          
            $this->common($menu_type);
        }else{
            $this->common("all");
        }
    }
    //退货单“待处理”的列表显示
    function pending(){
        $this->common("pending");
    }
    
    //退货单“已拒绝”的列表显示
    function refused(){
        $this->common("refused");
    }
    
    //退货单“已处理”的列表显示
    function already(){
        $this->common("already");
    }
    
    //列表公共加载方法
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
        

        if ($sub_menu[$menu_type]['not_show_datalist']){//搜索不满足条件的不显示列表
        }else{
            //默认获取列表数据
           $wap_aftersale_lib = kernel::single('wap_aftersale');
           
            $dataList = $wap_aftersale_lib->getList($filter, $offset, $limit,$orderby);
            if(!empty($dataList)){//有列表数据
                //操作按钮链接
                 //拒绝操作
                    $this->delivery_link['doRefuse'] = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'doRefuse'), true);
                    //确认退货操作
                    $this->delivery_link['doConfirm'] = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'doConfirm'), true);
                   
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
            $channel[$k] = $v;
        }

        $this->pagedata['channel_list'] = $channel;


        if($_POST['flag'] == 'ajax'){//Ajax加载更多
            $this->display('return/return_product_more.html');
        }else{
            $this->display('aftersale/return_product.html');
        }
    }
    
    //退货拒单操作
    function doRefuse(){
        if (!$_POST["return_id"] || !$_POST["refuse_reason_text"]){
            echo json_encode(array('res'=>'error', 'msg'=>'无效操作'));
            exit;
        }
        
        $mdl_ome_operation_log = app::get('ome')->model('operation_log');
        $mdl_wap_return = app::get('wap')->model('return');
        $mdl_ome_reship = app::get('ome')->model('reship');
        
        //获取原始退换货单号
        $rs_wap_return = $mdl_wap_return->dump(array("return_id"=>$_POST["return_id"]),"original_reship_bn");
        //原始退换货单信息
        $rs_ome_reship = $mdl_ome_reship->dump(array("reship_bn"=>$rs_wap_return["original_reship_bn"]),"reship_id");
        //更新门店退换单为已拒绝状态 
        $mdl_wap_return->update(array("status"=>2),array("return_id"=>$_POST["return_id"]));
        //参考oms端收货质检拒绝操作 收货异常
        $refuseMemo = array("refuse"=>$_POST["refuse_reason_text"]);
        $refuse = array(
            'reason' => serialize($refuseMemo),
            'is_check' => 12
        );
        $mdl_ome_reship->update($refuse,array('reship_id'=>$rs_ome_reship["reship_id"]));
        //获取主键记录原始换货货单日志
        $mdl_ome_operation_log->write_log('reship@ome',$rs_ome_reship["reship_id"],'门店拒绝退货');
        echo json_encode(array('res'=>'succ', 'status'=>'已拒绝', 'msg'=>'已拒绝成功'));
        exit;
    }
    
    //确认退货操作
    function doConfirm(){
        if (!$_POST["return_id"]){
            echo json_encode(array('res'=>'error', 'msg'=>'无效操作'));
            exit;
        }
        //获取当前的退货单数据
        $mdl_wap_return = app::get('wap')->model('return');
        $mdl_wap_return_items = app::get('wap')->model('return_items');
        $rs_wap_return = $mdl_wap_return->dump(array("return_id"=>$_POST["return_id"],"return_type"=>"return"),"*");
        $rs_wap_return_items = $mdl_wap_return_items->getList("*",array("return_id"=>$_POST["return_id"],"return_type"=>"return"));
        if (empty($rs_wap_return) || empty($rs_wap_return_items)){
            echo json_encode(array('res'=>'error', 'msg'=>'退货单数据缺失'));
            exit;
        }
        //获取原始退货单数据
        $mdl_ome_reship = app::get('ome')->model('reship');
        $rs_ome_reship = $mdl_ome_reship->dump(array("reship_bn"=>$rs_wap_return["original_reship_bn"],"is_check"=>1),"*");
        if(empty($rs_ome_reship)){
            echo json_encode(array('res'=>'error', 'msg'=>'原始退货单必须是已审核状态'));
            exit;
        }
        //先更新原始退货单状态 做并发处理
        if ($rs_ome_reship['status'] == "succ") {
            echo json_encode(array('res'=>'error', 'msg'=>'请不要重复点击'));
            exit;
        }
        $mdl_ome_reship->update(array("status"=>"succ"),array('reship_id'=>$rs_ome_reship["reship_id"]));
        //更新原始退货单 normal_num良品字段数据
        $mdl_ome_reship_items = app::get('ome')->model('reship_items');
        foreach ($rs_wap_return_items as $var_w_r_i){
            $mdl_ome_reship_items->update(array("normal_num"=>$var_w_r_i["num"]),array("reship_id"=>$rs_ome_reship["reship_id"],"bn"=>$var_w_r_i["bn"]));
        }
        //库存管控 退货：门店货品实际库存增加
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id'=>$rs_ome_reship['branch_id']));
        $params_stock = array(
            "params" => $rs_ome_reship,
            "node_type" => "confirmReshipReturn",
        );
        $storeManageLib->processBranchStore($params_stock, $err_msg);
        //添加退货单api请求
        $mdl_ome_reship->request_reship_creat_api($rs_ome_reship["shop_id"],$rs_ome_reship["reship_id"]);
        //是否归档
        $is_archive = kernel::single('archive_order')->is_archive($rs_ome_reship['source']);
        //订单明细退货处理
        $orders = $mdl_ome_reship->do_order_items_return($rs_ome_reship,$is_archive);
        //判断是否要生成一张支付单
        if ($rs_ome_reship['diff_order_bn']) {//新增补差订单 发货状态改为已发货 并把状态回打给前端。
            kernel::single('ome_reship')->updatediffOrder($rs_ome_reship['diff_order_bn']);
        }
        //生成退款申请单
        $memo = "";
        $is_generate_aftersale = true; //是否生成售后单
        $totalmoney = (float)$rs_ome_reship['totalmoney']; # 实际需要退款的金额 退货$totalmoney不可能小于0 oms端审核时会拦掉的
        if($totalmoney == 0){//退款过程中当实际需要退款的金额为0时不生成退款申请单
        }else{//生成并更新退款申请单
            $mdl_refund_apply = app::get('ome')->model('refund_apply');
            $refund_apply_bn = $mdl_refund_apply->gen_id();
            //新建退款申请单时的申请退款金额
            $money = (float)$rs_ome_reship['tmoney']+(float)$rs_ome_reship['diff_money']+(float)$rs_ome_reship['bcmoney']-(float)$rs_ome_reship['bmoney'];
            $refundMoney = (float)$rs_ome_reship['tmoney']; # 退款金额
            $refund_sdf = $mdl_ome_reship->create_refund_apply_record($refund_apply_bn,$rs_ome_reship,$money,$is_archive);
            //满足条件直接更新刚刚新增的退款申请单的memo和实际退款金额
            if($refundMoney > $totalmoney){//多退 退换货生成的退款申请单，退换货单号为:201301251613000368。应退金额(12)扣除折旧费邮费后，实际应退金额为(2)
                $memo = $refund_sdf['memo'].'应退金额('.$refundMoney.')扣除折旧费邮费后，实际应退金额为('.$totalmoney.')';
                $mdl_refund_apply->update(array("money"=>$totalmoney,"memo"=>$memo),array('refund_apply_bn'=>$refund_apply_bn));
                $is_generate_aftersale = false;
            }elseif($totalmoney > 0 && $totalmoney > $refundMoney){//少退的
                $memo = $refund_sdf['memo'].'应退商品金额('.$refundMoney.'),';
                if ($rs_ome_reship['cost_freight_money'] < 0) {
                    $memo .= '加上相应的邮费,';
                }
                $memo .= '实际应退金额为('.$totalmoney.')';
                $mdl_refund_apply->update(array("money"=>$totalmoney,"memo"=>$memo),array('refund_apply_bn'=>$refund_apply_bn));
                $is_generate_aftersale = false;
            }
        }
        //更新当前退货单状态及原始退货单状态
        $mdl_wap_return->update(array("status"=>3),array("return_id"=>$rs_wap_return["return_id"]));
        $mdl_ome_reship->update(array('is_check'=>'7','t_end'=>time()),array('reship_id'=>$rs_ome_reship["reship_id"]));
        $memo .= '操作完成。';
        $mdl_operation_log = app::get('ome')->model('operation_log');
        if($rs_ome_reship['return_id']){//存在售后申请单的，更新为完成状态、是否收货和是否质检的字段。
            $mdl_return_product = app::get('ome')->model('return_product');
            $mdl_return_product->update(array('status'=>'4','money'=>$totalmoney,'recieved'=>'true','verify'=>'true'),array('return_id'=>$rs_ome_reship['return_id']));
            $mdl_operation_log->write_log('return@ome',$rs_ome_reship['return_id'],$memo);
            kernel::single('ome_service_aftersale')->update_status($rs_ome_reship['return_id'],'','async','');
        }
        $mdl_operation_log->write_log('reship@ome',$rs_ome_reship["reship_id"],$memo);
        //生成售后单
        if($is_generate_aftersale){
            kernel::single('sales_aftersale')->generate_aftersale($rs_ome_reship["reship_id"],$rs_ome_reship['return_type']);
        }
        echo json_encode(array('res'=>'succ', 'status'=>'已确认', 'msg'=>'已确认退货'));
    }


    /**
     * 验货退款
     */
    public function addReship(){
        $return_id = $_GET['return_id'];
        $reship_id = $_GET['reship_id'];
        
        $returnMdl = app::get('wap')->model('return');
        $returns = $returnMdl->dump(array("return_id"=>$return_id,"return_type"=>"return"),"*");
        $this->pagedata['returns'] = $returns;
        $itemsMdl = app::get('wap')->model('return_items');
       
        $items = $itemsMdl->getList("*",array("return_id"=>$return_id,"return_type"=>"return"));
        $materialLib = kernel::single('material_basic_material');
        foreach($items as $k=>$v){
            $mainImage = $materialLib->getBasicMaterialMainImage($v['product_id']);

            if ($mainImage) {
                $items[$k]['goods_img_url'] = $mainImage['full_url'];
            }
            $items[$k]['need_serial'] = kernel::single('material_serial')->checkSerialById($v['product_id']) ? 'true' : 'false';
        }
        // 与退货列表一致：传 original_reship_bn，展示发货出库唯一码，避免落到在库 SN
        $items = kernel::single('wap_delivery')->enrichItemsByBnByService($items, 'bn', array(
            'original_reship_bn' => $returns['original_reship_bn'] ?? '',
            'return_id'          => $returns['return_id'] ?? $return_id,
            'order_id'           => $returns['order_id'] ?? '',
            'wap_return_status'  => $returns['status'] ?? '',
        ));
        $this->pagedata['items'] = $items;

        $this->pagedata['wait_return_url'] = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'pending'));
        $this->pagedata['do_reship_url'] = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'doReship'), true);
        $this->pagedata['scan_return_serial_url'] = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'scanReturnSerial'), true);
        $this->pagedata['wx_sign_url'] = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'getWxSign'), true);
        $this->display('return/reship.html');
    }

    /**
     * 扫描退货唯一码
     */
    public function scanReturnSerial()
    {
        $return_id   = intval($_POST['return_id']);
        $sn          = trim($_POST['sn']);
        $scannedList = [];
        if (!empty($_POST['scanned_list'])) {
            $scannedList = json_decode($_POST['scanned_list'], true);
            if (!is_array($scannedList)) {
                $scannedList = [];
            }
        }

        $expected_bn = trim($_POST['bn'] ?? '');
        $serialLib = kernel::single('wap_return_serial');
        $err_msg   = '';
        $result    = $serialLib->scanReturnSerial($return_id, $sn, $scannedList, $err_msg, $expected_bn);
        if ($result === false) {
            echo json_encode(['res' => 'error', 'msg' => $err_msg ?: '扫描失败']);
            exit;
        }
        echo json_encode(array_merge(['res' => 'succ'], $result));
        exit;
    }
    
    /**
     * doReship
     * @return mixed 返回值
     */
    public function doReship(){

        $return_id = $_POST['return_id'];
        $returnMdl = app::get('wap')->model('return');
        $returns = $returnMdl->dump(array("return_id"=>$_POST["return_id"],"return_type"=>"return"),"*");
        if (empty($returns)) {
            echo json_encode(array('res'=>'error', 'msg'=>'退货单不存在'));
            exit;
        }
        if (intval($returns['status']) !== 1) {
            echo json_encode(array('res'=>'error', 'msg'=>'退货单状态不正确'));
            exit;
        }

        $items = $_POST['items'];
        if (empty($items) || !is_array($items)) {
            echo json_encode(array('res'=>'error', 'msg'=>'退货明细不能为空'));
            exit;
        }

        $serialLib = kernel::single('wap_return_serial');
        $serialItems = [];
        if (!empty($_POST['serial_items'])) {
            $serialItems = json_decode($_POST['serial_items'], true);
            if (!is_array($serialItems)) {
                $serialItems = [];
            }
        }
        $snMap = $serialLib->validateSerialItemsForReship($return_id, $items, $serialItems, $serialErr);
        if ($snMap === false) {
            echo json_encode(array('res'=>'error', 'msg'=>$serialErr ?: '唯一码校验失败'));
            exit;
        }

        $reshipdata = [
            'reship_bn'=>$returns['original_reship_bn'],
            'status'   =>'FINISH',
            'demo'     => "门店退货单回传",
        ];
        $reship_items = [];
        foreach ($items as $item) {
            $bn = trim($item['bn']);
            $num = intval($item['num']);
            if ($bn === '' || $num <= 0) {
                continue;
            }
            $row = array(
                'product_bn' => $bn,
                'normal_num' => $num,
            );
            if (!empty($snMap[$bn])) {
                $row['sn_list'] = $snMap[$bn];
            }
            $reship_items[] = $row;
        }
        if (empty($reship_items)) {
            echo json_encode(array('res'=>'error', 'msg'=>'退货明细无效'));
            exit;
        }
        $reshipdata['item'] = json_encode($reship_items);

        $store_id = kernel::single('ome_branch')->isStoreBranch($returns['branch_id']);
        $rs = kernel::single('erpapi_router_response')->set_channel_id($store_id)->set_api_name('store.reship.status_update')->dispatch($reshipdata);
        if ($rs['rsp'] == 'succ') {
            $returnMdl->update(array("status"=>3), array("return_id"=>$return_id));
            echo json_encode(array('res'=>'succ', 'status'=>'已确认', 'msg'=>'退货入库成功'));
            exit;
        }
        echo json_encode(array('res'=>'error', 'msg'=>($rs['msg'] ?: '退货入库失败')));
        exit;
    }
    

    /**
     * 获取CreateTimeByDateType
     * @param mixed $dateType dateType
     * @return mixed 返回结果
     */
    public function getCreateTimeByDateType($dateType)
    {
       
        switch ($dateType) {
            case 'today':
                $filter['createtime|than'] = strtotime(date('Y-m-d'));
                $filter['createtime|lthan'] = time();
                break;
            case 'yesterday':
                $filter['createtime|than'] = strtotime(date('Y-m-d', strtotime('-1 day')));
                $filter['createtime|lthan'] = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
                break;
            case 'month':
                $filter['createtime|than'] = strtotime(date('Y-m-01'));
                $filter['createtime|lthan'] = time();
                break;
            case 'custom':
                if ($_POST['start_time']) {
                    $filter['createtime|than'] = strtotime($_POST['start_time']);
                }
                if ($_POST['end_time']) {
                    $filter['createtime|lthan'] = strtotime($_POST['end_time'] . ' 23:59:59');
                }
                break;
        }

     

        return $filter;
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
                $this->error($msg);
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
}