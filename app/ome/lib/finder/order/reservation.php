<?php
/**
 * 待预约订单finder
 *
 * @author wangbiao@shopex.cn
 * @version 2025.06.20
 */
class ome_finder_order_reservation
{
    static $_reSellerNameList = array();
    
    private $_appName = 'ome';
    public $addon_cols = 'id,order_id,custom_reserved_status,ship_status,seller_id';
    
    public $column_edit = '操作';
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    public function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $id = $row[$this->col_prefix.'id'];
        $url = 'index.php?app=%s&ctl=admin_order_reservation&act=apply&p[0]=%s&finder_id=%s';
        $url = sprintf($url, $this->_appName, $id, $finder_id);
        
        // order
        $orderInfo = app::get('ome')->model('orders')->dump($row[$this->col_prefix.'order_id'], 'order_id,order_bn,status,pay_status,process_status,ship_status');
        
        // 订单付款状态不是：已支付、部分退款
        if(!in_array($orderInfo['pay_status'], ['1','3','4'])){
            return '';
        }elseif(!in_array($orderInfo['status'], ['active'])){
            return '';
        }elseif(!in_array($orderInfo['process_status'], ['unconfirmed','confirmed'])){
            return '';
        }elseif(!in_array($orderInfo['ship_status'], ['0'])){
            return '';
        }
        
        // button
        $button = '';
        if(in_array($row[$this->col_prefix.'custom_reserved_status'], ['0'])){
            $button .= '<a href="'. $url .'" target="dialog::{width:650,height:500,title:\'预约订单号：'. $orderInfo['order_bn'] .'\'}">预约</a>';
        }elseif(in_array($row[$this->col_prefix.'custom_reserved_status'], ['1']) && $row[$this->col_prefix.'ship_status'] == '0'){
            $button .= '<a href="'. $url .'" target="dialog::{width:900,height:700,title:\'重新预约订单号：'. $orderInfo['order_bn'] .'\'}">重新预约</a>';
        }elseif(in_array($row[$this->col_prefix.'custom_reserved_status'], ['3']) && $row[$this->col_prefix.'ship_status'] == '0'){
            $button .= '<a href="'. $url .'" target="dialog::{width:900,height:700,title:\'重新预约订单号：'. $orderInfo['order_bn'] .'\'}">重新预约</a>';
        }
        
        return $button;
    }
    
    var $detail_basic = '基本信息';
    public function detail_basic($id)
    {
        $render = app::get($this->_appName)->render();
        $orderMdl = app::get('ome')->model('orders');
        $objExtendMdl = app::get('ome')->model('order_objects_extend');
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        //post
        if($_POST){
            die('请到订单栏目里操作，这里不允许提交数据!');
        }
        
        //order
        $reservationInfo = $reservationMdl->dump(array('id'=>$id), '*');
        $order_id = $reservationInfo['order_id'];
        
        //detail
        $order_detail = $orderMdl->dump($order_id,"*",array("order_items"=>array("*")));
        
        //订单object层商品定制信息
        $tempList = $objExtendMdl->getList('*', array('order_id'=>$order_id), 0, -1, 'obj_id DESC');
        $itemList = [];
        if($tempList){
            foreach ($tempList as $tempKey => $tempVal)
            {
                $obj_order_id = $tempVal['order_id'];
                
                if(empty($tempVal['customization'])){
                    continue;
                }
                
                $customInfo = json_decode($tempVal['customization'], true);
                if(empty($customInfo['items'])){
                    continue;
                }
                
                $itemList = array_merge($itemList, $customInfo['items']);
            }
        }
        
        //销售价权限验证
        $showSalePrice = true;
        if (!kernel::single('desktop_user')->has_permission('sale_price')) {
            $showSalePrice = false;
        }
        
        // 判断是否加密
        $order_detail['is_encrypt'] = kernel::single('ome_security_router', $order_detail['shop_type'])->show_encrypt($order_detail, 'order');
        
        $order_detail['mark_text'] = kernel::single('ome_func')->format_memo($order_detail['mark_text']);
        $order_detail['custom_mark'] = kernel::single('ome_func')->format_memo($order_detail['custom_mark']);
        
        $render->pagedata['total_amount'] = floatval($order_detail['total_amount']);
        $render->pagedata['payed'] = floatval($order_detail['payed']);
        
        //$render->pagedata['is_hied_receiver_copy'] = true;
        $render->pagedata['show_sale_price'] = $showSalePrice;
        $render->pagedata['total_amount'] = floatval($order_detail['total_amount']);
        $render->pagedata['payed'] = floatval($order_detail['payed']);
        $render->pagedata['order'] = $order_detail;
        $render->pagedata['itemList'] = $itemList;
        
        return $render->fetch('admin/order/detail_basic.html');
    }
    
    var $detail_reserved = '预约信息';
    public function detail_reserved($id)
    {
        $render = app::get($this->_appName)->render();
        $orderMdl = app::get('ome')->model('orders');
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        //order
        $reservationInfo = $reservationMdl->dump(array('id'=>$id), '*');
        $order_id = $reservationInfo['order_id'];
        
        //detail
        $orderInfo = $orderMdl->dump($order_id, '*');
        
        // items
        $item_list = $orderMdl->getItemList($order_id, true);
        if(empty($item_list)){
            die('没有订单商品明细...');
        }
        
        $item_list = ome_order_func::add_getItemList_colum($item_list);
        ome_order_func::order_sdf_extend($item_list);
        
        // items
        $orderObjectList = [];
        $orderItemList = [];
        foreach ($item_list as $objType => $objTypeVal)
        {
            foreach ($objTypeVal as $objKey => $ObjVal)
            {
                $obj_id = $ObjVal['obj_id'];
                
                // merge
                $orderObjectList[$obj_id] = $ObjVal;
                
                // items
                foreach ($ObjVal['order_items'] as $itemKey => $itemVal)
                {
                    $item_id = $itemVal['item_id'];
                    $quantity = $itemVal['quantity'];
                    
                    // 可预约的数量
                    $itemVal['item_quantity'] = $quantity - $itemVal['split_num'];
                    
                    // merge
                    $orderItemList[$item_id] = $itemVal;
                }
            }
        }
        
        // 商品预约信息
        $reservationItems = [];
        if($reservationInfo['reservation_items']){
            $reservationItems = json_decode($reservationInfo['reservation_items'], true);
            
            // format
            foreach ($reservationItems as $reKey => $reVal)
            {
                $order_item_id = $reVal['order_item_id'];
                
                $orderItemInfo = (isset($orderItemList[$order_item_id]) ? $orderItemList[$order_item_id] : []);
                
                $obj_id = $orderItemInfo['obj_id'];
                $prodcut_name = $orderItemInfo['name'];
                $sap_reserved_times = $orderItemInfo['sap_reserved_times'];
                
                $reservationItems[$reKey]['goods_bn'] = $orderObjectList[$obj_id]['bn'];
                $reservationItems[$reKey]['product_name'] = $prodcut_name;
                $reservationItems[$reKey]['sap_reserved_times'] = $sap_reserved_times;
            }
        }
        
        $render->pagedata['order'] = $orderInfo;
        $render->pagedata['data'] = $reservationInfo;
        $render->pagedata['reservationItems'] = $reservationItems;
        
        return $render->fetch('admin/order/detail_reserved.html');
    }
    
    var $detail_goods = '订单明细';
    public function detail_goods($id)
    {
        $render = app::get('ome')->render();
        $orderMdl = app::get('ome')->model('orders');
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        // order
        $reservationInfo = $reservationMdl->dump(array('id'=>$id), '*');
        $order_id = $reservationInfo['order_id'];
        
        $orders = $orderMdl->getRow(array('order_id'=>$order_id),'order_id,shop_type,order_source,process_status');
        $is_consign = false;
        
        //detail
        $item_list = $orderMdl->getItemList($order_id,true);
        if(empty($item_list)){
            die('没有订单商品明细...');
        }
        
        $item_list = ome_order_func::add_getItemList_colum($item_list);
        ome_order_func::order_sdf_extend($item_list);
        
        //是否超级管理员
        $isSuper = kernel::single('desktop_user')->is_super();
        
        //平台订单金额明细
        $couponList = array();
        if($orders['shop_type'] == 'luban' && $isSuper){
            $couponMdl = app::get('ome')->model('order_coupon');
            $couponList = $couponMdl->getList('*', array('order_id'=>$order_id));
            if($couponList){
                $oidList = array();
                foreach ($item_list as $obj_type => $objects){
                    foreach ($objects as $obj_id => $items){
                        $oid = $items['oid'];
                        $oidList[$oid]['bn'] = $items['bn'];
                    }
                }
                
                foreach ($couponList as $key => $val){
                    $oid = $val['oid'];
                    
                    $couponList[$key]['material_bn'] = $oidList[$oid]['bn'];
                }
            }
        }
        
        $psRows = app::get('ome')->model('order_platformsplit')->getList('split_oid, bn', ['order_id'=>$order_id]);
        $split_oid = [];
        foreach ($psRows as $key => $value) {
            $split_oid[$value['split_oid']][] = $value['bn'];
        }
        
        //销售价权限判断
        $showSalePrice = true;
        if (!kernel::single('desktop_user')->has_permission('sale_price')) {
            $showSalePrice = false;
        }
        
        //check
        $is_host = false;
        $lucky_flag = false;
        $is_hold_time = false;
        $is_presale_status = false;
        foreach ($item_list as $obj_type => $objects)
        {
            foreach ($objects as $obj_id => $objInfo)
            {
                //author_id
                if ($objInfo['author_id']) {
                    $is_host = true;
                }
                
                //presale_status
                if ($objInfo['presale_status'] == 1) {
                    $is_presale_status = true;
                }
                
                //obj_type
                if ($objInfo['obj_type'] == 'lkb') {
                    $lucky_flag = true;
                }
                
                //estimate_con_time
                if ($objInfo['estimate_con_time'] > 1) {
                    $is_hold_time = true;
                }
            }
        }
        
        $render->pagedata['is_host'] = $is_host;
        $render->pagedata['lucky_flag'] = $lucky_flag;
        $render->pagedata['is_hold_time'] = $is_hold_time;
        $render->pagedata['is_presale_status'] = $is_presale_status;
        $render->pagedata['show_sale_price'] = $showSalePrice;
        $render->pagedata['order'] = $orders;
        $render->pagedata['couponList'] = $couponList;
        $render->pagedata['maxHoldTime'] = kernel::single('omeauto_auto_hold')->getMaxHoldTime();
        $render->pagedata['is_consign'] = ($is_consign > 0)?true:false;
        $render->pagedata['configlist'] = array();
        $render->pagedata['item_list'] = $item_list;
        $render->pagedata['shop_type'] = $orders['shop_type'];
        $render->pagedata['split_oid'] = $split_oid;
        $render->pagedata['object_alias'] = $orderMdl->getOrderObjectAlias($order_id);
        
        return $render->fetch('admin/order/detail_goods.html');
    }
    
    var $detail_mark = '商家备注';
    public function detail_mark($id)
    {
        $render = app::get('ome')->render();
        $oOrders = app::get('ome')->model('orders');
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        // order
        $reservationInfo = $reservationMdl->dump(array('id'=>$id), '*');
        $order_id = $reservationInfo['order_id'];
        
        if($_POST){
            die('禁止提交数据!');
        }
        
        $order_detail = $oOrders->dump($order_id);
        $render->pagedata['base_dir'] = kernel::base_url();
        $order_detail['mark_text'] = unserialize($order_detail['mark_text']);
        if ($order_detail['mark_text'])
            foreach ($order_detail['mark_text'] as $k=>$v){
                if (!strstr($v['op_time'], "-")){
                    $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                    $order_detail['mark_text'][$k]['op_time'] = $v['op_time'];
                }
            }
        $order_detail['custom_mark'] = unserialize($order_detail['custom_mark']);
        if ($order_detail['custom_mark'])
            foreach ($order_detail['custom_mark'] as $k=>$v){
                if (!strstr($v['op_time'], "-")){
                    $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                    $order_detail['custom_mark'][$k]['op_time'] = $v['op_time'];
                }
            }
        $order_detail['mark_type_arr'] = ome_order_func::order_mark_type();
        $render->pagedata['order']  = $order_detail;
        
        //常用备注
        $commonRemarks = ['需发货质检', '需优先回传物流单', '指定物流', '指定不能用默认物流'];
        $render->pagedata['commonRemarks']  = $commonRemarks;
        $render->pagedata['is_prohibit_submit']  = 'true';
        
        return $render->fetch('admin/order/detail_mark.html');
    }
    
    var $detail_custom_mark = '客户备注';
    var $detail_custom_mark_width = 100;
    public function detail_custom_mark($id)
    {
        $render = app::get('ome')->render();
        $oOrders = app::get('ome')->model('orders');
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        // order
        $reservationInfo = $reservationMdl->dump(array('id'=>$id), '*');
        $order_id = $reservationInfo['order_id'];
        
        if($_POST){
            die('禁止提交数据...');
        }
        
        $order_detail = $oOrders->dump($order_id);
        $render->pagedata['base_dir'] = kernel::base_url();
        $order_detail['custom_mark'] = unserialize($order_detail['custom_mark']);
        if ($order_detail['custom_mark'])
            foreach ($order_detail['custom_mark'] as $k=>$v){
                if (!strstr($v['op_time'], "-")){
                    $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                    $order_detail['custom_mark'][$k]['op_time'] = $v['op_time'];
                }
            }
        $render->pagedata['order']  = $order_detail;
        $render->pagedata['is_prohibit_submit'] = 'true';
        
        return $render->fetch('admin/order/detail_custom_mark.html');
    }
    
    // 销售人员名称
    var $column_seller_name = '销售人员名称';
    var $column_seller_name_width = 180;
    var $column_seller_name_order = 42;
    public function column_seller_name($row, $list)
    {
        //load
        $this->_getSellerNameList($list);
        
        $seller_id = $row[$this->col_prefix .'seller_id'];
        
        $seller_name = isset(self::$_reSellerNameList[$seller_id]) ? self::$_reSellerNameList[$seller_id]['seller_name'] : '';
        
        return $seller_name;
    }
    
    /**
     * 批量获取销售人员名称
     *
     * @param $list
     * @return boolean
     */
    private function _getSellerNameList($list)
    {
        //check
        if(self::$_reSellerNameList){
            return true;
        }
        
        $sellerMdl = app::get('material')->model('seller');
        
        // seller_id
        $sellerIds = array_column($list, 'seller_id');
        if(empty($sellerIds)){
            $sellerIds = array_column($list, $this->col_prefix .'seller_id');
        }
        
        //items
        $itemList = $sellerMdl->getList('id,seller_code,seller_name', array('id'=>$sellerIds));
        if(empty($itemList)){
            return false;
        }
        
        self::$_reSellerNameList = array_column($itemList, null, 'id');
        
        return true;
    }
    
    /**
     * 订单操作记录
     * @param int $order_id
     * @return string
     */
    var $detail_history = '操作日志';
    public function detail_history($id)
    {
        $render = app::get('ome')->render();
        
        $operLogMdl = app::get('ome')->model('operation_log');
        
        /* 本订单日志 */
        $historyList = $operLogMdl->read_log(array('obj_id'=>$id, 'obj_type'=>'order_reservation@ome'), 0, -1);
        
        $render->pagedata['logList'] = $historyList;
        
        return $render->fetch('admin/common/detail_log.html');
    }
}