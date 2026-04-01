<?php
/**
 * 待预约订单
 *
 * @author wangbiao@shopex.cn
 * @version 2025.06.19
 */
class ome_ctl_admin_order_reservation extends desktop_controller
{
    var $title = '待预约订单';
    var $workground = 'order_center';
    private $_appName = 'ome';
    
    private $_mdl = null; //model类
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get($this->_appName)->model('order_reservation');
        
        //primary_id
        $this->_primary_id = 'id';
        
        //primary_bn
        $this->_primary_bn = 'order_id';
    }
    
    public function index()
    {
        $user = kernel::single('desktop_user');
        $actions = array();
        
        //filter
        $base_filter = $this->getFilters();
        
        //button
        $buttonList = array();
        $buttonList['batchConfirmed'] = array(
            'label' => '客服批量确认',
            'submit' => $this->url.'&act=batchConfirmed',
            'target' => 'dialog::{width:600,height:230,title:\'批量操作预约时间不一致的订单为：客服已确认\'}'
        );
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        switch ($_GET['view'])
        {
            case '0':
            case '2':
                $actions[] = $buttonList['batchConfirmed'];
                break;
        }
        
        //导出权限
        $use_buildin_export = true;
        
        //params
        $orderby = 'at_time DESC';
        $params = array(
            'title' => $this->title,
            'base_filter' => $base_filter,
            'actions'=> $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => $use_buildin_export,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => $orderby,
        );
        
        $this->finder('ome_mdl_order_reservation', $params);
    }
    
    public function _views()
    {
        //filter
        $base_filter = $this->getFilters();
        
        //menu
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'), 'filter'=>$base_filter, 'optional'=>false),
            1 => array('label'=>app::get('base')->_('待预约'), 'filter'=>['custom_reserved_status'=>'0', 'order_status'=>'active', 'pay_status'=>['1','3','4']], 'optional'=>false),
            2 => array('label'=>app::get('base')->_('预约成功'), 'filter'=>['custom_reserved_status'=>'1', 'order_status'=>'active', 'pay_status'=>['1','3','4'], 'ship_status'=>'0'], 'optional'=>false),
            3 => array('label'=>app::get('base')->_('预约中'), 'filter'=>['custom_reserved_status'=>'2', 'order_status'=>'active', 'pay_status'=>['1','3','4'], 'ship_status'=>'0'], 'optional'=>false),
            4 => array('label'=>app::get('base')->_('预约失败'), 'filter'=>['custom_reserved_status'=>'3', 'order_status'=>'active', 'pay_status'=>['1','3','4'], 'ship_status'=>'0'], 'optional'=>false),
            5 => array('label'=>app::get('base')->_('已发货'), 'filter'=>['ship_status'=>'1'], 'optional'=>false),
            6 => array('label'=>app::get('base')->_('退款订单'), 'filter'=>['pay_status'=>['3','4','5']], 'optional'=>false),
        );
        
        foreach($sub_menu as $k => $v)
        {
            if (isset($v['filter'])){
                $v['filter'] = array_merge($base_filter, $v['filter']);
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['href'] = 'index.php?app='. $_GET['app'] .'&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$k;
            
            //第一个TAB菜单没有数据时显示全部
            if($k == 0){
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->count($v['filter']);
                if($sub_menu[$k]['addon'] == 0){
                    unset($sub_menu[$k]);
                }
            }else{
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->viewcount($v['filter']);
            }
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
        $base_filter = array();
        
        //check shop permission
//        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
//        if($organization_permissions){
//            $base_filter['org_id'] = $organization_permissions;
//        }
        
        return $base_filter;
    }
    
    /**
     * 开始预约
     *
     * @param $id
     * @return void
     */
    public function apply($id)
    {
        $sellerMdl = app::get('material')->model('seller');
        $orderMdl = app::get('ome')->model('orders');
        
        // info
        $reservationInfo = $this->_mdl->dump($id, '*');
        
        // check
        if(empty($reservationInfo)){
            $error_msg = '无效的预约订单,请检查';
            die($error_msg);
        }
        
        $order_id = $reservationInfo['order_id'];
        
        // order
        $orderInfo = app::get('ome')->model('orders')->dump($reservationInfo['order_id'], '*', array("order_objects"=>array("*", array("order_items"=>array("*")))));
        if(empty($orderInfo)){
            $error_msg = '预约订单不存在,请检查';
            die($error_msg);
        }
        
        if($orderInfo['ship_status'] != '0'){
            $error_msg = '订单发货状态不允许操作,请检查订单!';
            die($error_msg);
        }
        
        // check
        if($reservationInfo['custom_reserved_status'] == '1'){
            //$error_msg = '已预约成功，不能重新预约';
            //die($error_msg);
        }elseif($reservationInfo['custom_reserved_status'] == '4'){
            $error_msg = '预约已取消，不能重新预约';
            die($error_msg);
        }
        
        // 检测审批状态
        if(app::get('ticket')->is_installed()){
            $approvalInfo = kernel::single('ticket_workflow_case')->getOrderIsApproval($orderInfo['order_bn'], 'order');
            if($approvalInfo){
                $error_msg = '订单正在审批中，请审批完成之后，再进行预约!';
                die($error_msg);
            }
        }
        
        // 是否加密的收货人信息
        $is_encrypt = kernel::single('ome_security_router', $orderInfo['shop_type'])->is_encrypt($orderInfo, 'delivery');
        
        // 获取订单解密信息
        //kernel::single('ome_order')->getEncryptOriginData($orderInfo);
        
        // 默认值
        if(empty($reservationInfo['custom_reserved_name'])){
            $reservationInfo['custom_reserved_name'] = $orderInfo['consignee']['name'];
        }
        
        if(empty($reservationInfo['custom_reserved_mobile'])){
            $reservationInfo['custom_reserved_mobile'] = ($orderInfo['consignee']['mobile'] ? $orderInfo['consignee']['mobile'] : $orderInfo['consignee']['telephone']);
        }
        
        if(empty($reservationInfo['custom_reserved_area'])){
            $reservationInfo['custom_reserved_area'] = $orderInfo['consignee']['area'];
        }
        
        if(empty($reservationInfo['custom_reserved_address'])){
            $reservationInfo['custom_reserved_address'] = $orderInfo['consignee']['addr'];
        }
        
        // 销售人员列表
        $sellerList = $sellerMdl->getList('*', array('disabled'=>'false'));
        
        // reservation_items
        $reservationItems = [];
        if($reservationInfo['reservation_items']){
            $reservationItems = json_decode($reservationInfo['reservation_items'], true);
        }
        
        // 获取预约订单的SAP预约时间
        $itemPropList = kernel::single('ome_order_reservation')->getOrderSapReservedTimes($order_id);
        
        // items
        $orderObjectList = [];
        foreach ($orderInfo['order_objects'] as $objKey => $ObjVal)
        {
            $obj_id = $ObjVal['obj_id'];
            
            // check
            if($ObjVal['delete'] == 'true'){
                continue;
            }
            
            if($ObjVal['pay_status'] == '5'){
                continue;
            }
            
            $orderObjectList[$obj_id] = $ObjVal;
            
            // items
            foreach ($ObjVal['order_items'] as $itemKey => $itemVal)
            {
                $item_id = $itemVal['item_id'];
                $quantity = $itemVal['quantity'];
                
                // check
                if($itemVal['delete'] == 'true'){
                    continue;
                }
                
                if($itemVal['split_num'] == $quantity){
                    continue;
                }
                
                if($itemVal['sendnum'] == $quantity){
                    continue;
                }
                
                // 可预约的数量
                $itemVal['item_quantity'] = $quantity - $itemVal['split_num'];
                
                // 商品预约时间
                if(isset($reservationItems[$item_id]['item_reserved_time']) && $reservationItems[$item_id]['item_reserved_time']){
                    $itemVal['item_reserved_time'] = $reservationItems[$item_id]['item_reserved_time'];
                }elseif($reservationInfo['custom_reserved_time']){
                    $itemVal['item_reserved_time'] = $reservationInfo['custom_reserved_time'];
                }else{
                    $itemVal['item_reserved_time'] = '';
                }
                
                // sap_reserved_times
                if($itemPropList[$item_id]){
                    $itemVal['sap_reserved_times'] = $itemPropList[$item_id]['sap_reserved_times'];
                }else{
                    $itemVal['sap_reserved_times'] = '';
                }
                
                $orderObjectList[$obj_id]['order_items'][$item_id] = $itemVal;
            }
            
            // unset
            if(empty($orderObjectList[$obj_id]['order_items'])){
                unset($orderObjectList[$obj_id]);
            }
        }
        
        // check
        if(empty($orderObjectList)){
            die('订单没有可预约的商品明细，请检查');
        }
        
        $this->pagedata['is_encrypt'] = $is_encrypt;
        $this->pagedata['sellerList'] = $sellerList;
        $this->pagedata['orderInfo'] = $orderInfo;
        $this->pagedata['reservationInfo'] = $reservationInfo;
        $this->pagedata['orderObjectList'] = $orderObjectList;
        
        $this->display('admin/order/reservation_apply.html');
    }
    
    /**
     * 保存订单预约数据
     *
     * @return void
     */
    public function save()
    {
        $this->begin($this->url.'&act=index');
        
        $operLogMdl = app::get('ome')->model('operation_log');
        
        // post
        $order_id = intval($_POST['order_id']);
        $reserved_time = $_POST['reserved_time'];
        
        $custom_reserved_name = addslashes($_POST['custom_reserved_name']);
        $custom_reserved_mobile = addslashes($_POST['custom_reserved_mobile']);
        $custom_reserved_area = addslashes($_POST['custom_reserved_area']);
        $custom_reserved_address = addslashes($_POST['custom_reserved_address']);
        
        $seller_id = intval($_POST['seller_id']);
        $is_whole = ($_POST['is_whole'] == 'false' ? 'false' : 'true');
        $today = strtotime(date('Y-m-d', time()).' 00:00:00');
        
        // check
        if(empty($order_id)){
            $error_msg = '无效的预约订单...';
            $this->end(false, $error_msg);
        }
        
        if(empty($reserved_time)){
            $error_msg = '预约时间不能为空';
            $this->end(false, $error_msg);
        }
        
        $custom_reserved_time = strtotime($reserved_time . ' 00:00');
        if(empty($custom_reserved_time)){
            $error_msg = '预约时间无效,请检查';
            $this->end(false, $error_msg);
        }
        
        if($custom_reserved_time < $today && empty($_POST['order_item_ids'])){
            $error_msg = '订单预约时间不能小于今天,请检查';
            $this->end(false, $error_msg);
        }
        
        if(empty($custom_reserved_name)){
            $error_msg = '收货人姓名不能为空';
            $this->end(false, $error_msg);
        }
        
        if(empty($custom_reserved_mobile)){
            $error_msg = '预约手机号不能为空';
            $this->end(false, $error_msg);
        }
        
        if(empty($custom_reserved_area)){
            $error_msg = '收货地区不能为空';
            $this->end(false, $error_msg);
        }
        
        if(empty($custom_reserved_address)){
            $error_msg = '收货地址不能为空';
            $this->end(false, $error_msg);
        }
        
        if(false !== strpos($custom_reserved_name, '*') || false !== strpos($custom_reserved_mobile, '*')) {
            $error_msg = '收货人姓名、预约手机号中不允许带“*”，请检查';
            $this->end(false, $error_msg);
        }
        
        if(false !== strpos($custom_reserved_address, '*')) {
            $error_msg = '收货地址中不允许带“*”，请检查';
            $this->end(false, $error_msg);
        }
        
        // 验证手机号否则收不到提货短信
        $is_mobile = kernel::single('ome_func')->isMobile($custom_reserved_mobile);
        if(!$is_mobile){
            $this->end(false, '手机号不正确，请正确填写后再提交！');
        }
        
        if(empty($seller_id)){
            $error_msg = '销售人员不能为空';
            $this->end(false, $error_msg);
        }
        
        $reservationInfo = $this->_mdl->dump(array('order_id'=>$order_id), '*');
        if(empty($reservationInfo)){
            $error_msg = '预约记录不存在,请检查';
            $this->end(false, $error_msg);
        }
        
        // reservation_items
        $reservationItems = [];
        if($reservationInfo['reservation_items']){
            $reservationItems = json_decode($reservationInfo['reservation_items'], true);
        }
        
        // order
        $orderInfo = app::get('ome')->model('orders')->dump($order_id, '*', array("order_objects"=>array("*", array("order_items"=>array("*")))));
        
        // 订单付款状态不是：已支付、部分退款
        if(!in_array($orderInfo['pay_status'], ['1','4'])){
            $error_msg = '订单付款状态不允许操作,请检查';
            $this->end(false, $error_msg);
        }elseif(!in_array($orderInfo['status'], ['active'])){
            $error_msg = '订单状态不允许操作,请检查';
            $this->end(false, $error_msg);
        }elseif(!in_array($orderInfo['process_status'], ['unconfirmed','confirmed'])){
            $error_msg = '订单确认状态不允许操作,请检查';
            $this->end(false, $error_msg);
        }
        
        if($orderInfo['ship_status'] != '0'){
            $error_msg = '订单已发货不允许操作,请检查订单发货状态!';
            $this->end(false, $error_msg);
        }
        
        // 检测审批状态
        if(app::get('ticket')->is_installed()){
            $approvalInfo = kernel::single('ticket_workflow_case')->getOrderIsApproval($orderInfo['order_bn'], 'order');
            if($approvalInfo){
                $error_msg = '订单正在审批中，请审批完成之后，再进行预约。';
                $this->end(false, $error_msg);
            }
        }
        
        // items
        $orderObjectList = [];
        $orderItemList = [];
        foreach ($orderInfo['order_objects'] as $objKey => $ObjVal)
        {
            $obj_id = $ObjVal['obj_id'];
            
            // check
            if($ObjVal['delete'] == 'true'){
                continue;
            }
            
            if($ObjVal['pay_status'] == '5'){
                continue;
            }
            
            $orderObjectList[$obj_id] = $ObjVal;
            
            // items
            foreach ($ObjVal['order_items'] as $itemKey => $itemVal)
            {
                $item_id = $itemVal['item_id'];
                $quantity = $itemVal['quantity'];
                
                $itemVal['goods_id'] = $ObjVal['goods_id'];
                $itemVal['goods_bn'] = $ObjVal['bn'];
                
                // 可预约的数量
                $itemVal['item_quantity'] = $quantity - $itemVal['split_num'];
                
                // merge
                $orderItemList[$item_id] = $itemVal;
                
                // check
                if($itemVal['delete'] == 'true'){
                    continue;
                }
                
                if($itemVal['split_num'] == $quantity){
                    continue;
                }
                
                if($itemVal['sendnum'] == $quantity){
                    continue;
                }
                
                $orderObjectList[$obj_id]['order_items'][$item_id] = $itemVal;
            }
            
            // unset
            if(empty($orderObjectList[$obj_id]['order_items'])){
                unset($orderObjectList[$obj_id]);
            }
        }
        
        // check
        if(empty($orderObjectList)){
            die('订单没有可预约的商品明细，请检查');
        }
        
        // item_products
        if($_POST['order_item_ids'] && $_POST['item_reserved_time']){
            // 重新预约
            $logProductList = [];
            foreach ($_POST['order_item_ids'] as $itemKey => $order_item_id)
            {
                $orderItemInfo = (isset($orderItemList[$order_item_id]) ? $orderItemList[$order_item_id] : []);
                $product_bn = $orderItemInfo['bn'];
                $order_item_id  = $orderItemInfo['item_id'];
                
                // check
                if(empty($orderItemInfo)){
                    $error_msg = '基础物料编码预约无效,请检查';
                    $this->end(false, $error_msg);
                }
                
                if($_POST['item_reserved_time'][$itemKey]){
                    $item_reserved_time = strtotime($_POST['item_reserved_time'][$itemKey] . ' 00:00');
                }else{
                    //$item_reserved_time = $custom_reserved_time;
                    
                    // 重新预约时,预约时间必须选择
                    $error_msg = '基础物料编码：'. $product_bn .'未选择预约时间,请检查';
                    $this->end(false, $error_msg);
                }
                
                // check
                if($item_reserved_time < $today){
                    $error_msg = '基础物料编码：'. $product_bn .'预约时间不能小于今天,请检查';
                    $this->end(false, $error_msg);
                }
                
                // is_edit
                $is_edit = 'false';
                if(isset($reservationItems[$order_item_id]) && $reservationItems[$order_item_id]){
                    if($reservationItems[$order_item_id]['item_reserved_time'] != $item_reserved_time){
                        $is_edit = 'true';
                    }
                }
                
                // merge商品预约信息
                $reservationItems[$order_item_id]['order_obj_id'] = $orderItemInfo['obj_id'];
                $reservationItems[$order_item_id]['goods_id'] = $orderItemInfo['goods_id'];
                $reservationItems[$order_item_id]['goods_bn'] = $orderItemInfo['goods_bn'];
                
                $reservationItems[$order_item_id]['order_item_id'] = $orderItemInfo['item_id'];
                $reservationItems[$order_item_id]['product_id'] = $orderItemInfo['product_id'];
                $reservationItems[$order_item_id]['product_bn'] = $product_bn;
                $reservationItems[$order_item_id]['quantity'] = $orderItemInfo['item_quantity'];
                $reservationItems[$order_item_id]['item_reserved_time'] = $item_reserved_time;
                $reservationItems[$order_item_id]['is_edit'] = $is_edit;
                
                // date
                $logProductList[] = $product_bn .'：'. date('Y-m-d', $item_reserved_time);
            }
            
            $log_str = implode('、', $logProductList);
        }else{
            // 首次预约
            foreach ($orderItemList as $itemKey => $orderItemInfo)
            {
                $order_item_id  = $orderItemInfo['item_id'];
                $product_bn = $orderItemInfo['bn'];
                $is_edit = 'false';
                
                // 商品预约信息
                $reservationItems[$order_item_id]['order_obj_id'] = $orderItemInfo['obj_id'];
                $reservationItems[$order_item_id]['goods_id'] = $orderItemInfo['goods_id'];
                $reservationItems[$order_item_id]['goods_bn'] = $orderItemInfo['goods_bn'];
                
                $reservationItems[$order_item_id]['order_item_id'] = $orderItemInfo['item_id'];
                $reservationItems[$order_item_id]['product_id'] = $orderItemInfo['product_id'];
                $reservationItems[$order_item_id]['product_bn'] = $product_bn;
                $reservationItems[$order_item_id]['quantity'] = $orderItemInfo['item_quantity'];
                $reservationItems[$order_item_id]['item_reserved_time'] = $custom_reserved_time;
                $reservationItems[$order_item_id]['is_edit'] = $is_edit;
            }
            
            $log_str = '预约时间：'. date('Y-m-d', $custom_reserved_time);
        }
        
        //data
        $saveData = [
            'custom_reserved_status' => '2',
            'reserved_result' => 'none',
            'custom_reserved_name' => $custom_reserved_name,
            'custom_reserved_mobile' => $custom_reserved_mobile,
            'custom_reserved_area' => $custom_reserved_area,
            'custom_reserved_address' => $custom_reserved_address,
            'seller_id' => $seller_id,
            'custom_reserved_time' => $custom_reserved_time,
            'is_whole' => $is_whole,
            'reservation_items' => json_encode($reservationItems, JSON_UNESCAPED_UNICODE),
            'op_id' => kernel::single('desktop_user')->get_id(),
            'reserved_version' => date('YmdHis', time()), // 预约版本号
        ];
        
        // update
        $rs = $this->_mdl->update($saveData, array($this->_primary_id=>$reservationInfo['id']));
        if(is_bool($rs)) {
            $error_msg = '更新预约记录失败';
            $this->end(false, $error_msg);
        }
        
        $sdf = ['order_id'=>$order_id];
        
        //[SAP创建]调用service进行SAP创建
        foreach(kernel::servicelist('ome.service.order.reservation') as $object)
        {
            if(method_exists($object, 'start_reservation')){
                $object->start_reservation($sdf);
            }
        }
        
        // log
        $log_msg = '预约成功，'. $log_str .'，预约版本号：'. $saveData['reserved_version'];
        $operLogMdl->write_log('order_reserved_edit@ome', $reservationInfo['id'], $log_msg);
        
        $this->end(true, '更新预约记录成功');
    }
    
    /**
     * 批量操作预约时间不一致的订单为：客服已确认
     *
     * @return void
     */
    public function batchConfirmed()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        //post
        $ids = $_POST[$this->_primary_id];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($ids)){
            die('请选择需要操作的订单!');
        }
        
        if(count($ids) > 500){
            die('每次最多只能选择500条!');
        }
        
        //data
        $filter = [$this->_primary_id=>$ids, 'is_reserved_same'=>['false','no_confirmed']];
        $dataList = $this->_mdl->getList('id,order_id', $filter, 0, -1);
        if(empty($dataList)){
            die('没有可操作的订单，请检查!');
        }
        
        $editIds = array_column($dataList, 'id');
        
        $this->pagedata['GroupList'] = json_encode($editIds);
        $this->pagedata['request_url'] = $this->url .'&act=ajaxConfirmed';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch($this->_appName . '_mdl_order_reservation', false, 50, 'incr');
    }
    
    public function ajaxConfirmed()
    {
        $operLogMdl = app::get('ome')->model('operation_log');
        
        //setting
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取查询条件
        parse_str($_POST['primary_id'], $postdata);
        
        //check
        if(empty($postdata['f'])) {
            echo 'Error: 请选择需要操作的订单!';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        //check
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到订单';
            exit;
        }
        
        //mf_order_id
        $ids = array_column($dataList, 'id');
        
        //running
        $this->_mdl->update(array('is_reserved_same'=>'confirmed'), array('id'=>$ids));
        
        foreach($dataList as $key => $val)
        {
            // log
            $log_msg = '客服已确认';
            $operLogMdl->write_log('order_reserved_modify@ome', $val['id'], $log_msg);
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //succ
        $retArr['isucc'] = count($dataList);
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
}