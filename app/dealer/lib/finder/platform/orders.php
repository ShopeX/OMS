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
 * 经销商订单finder
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.10
 */
class dealer_finder_platform_orders
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    //model
    private $_appName = 'dealer';
    private $_modelName = 'platform_orders';
    private $_primary_id = 'plat_order_id';
    
    public $addon_cols = "plat_order_id,abnormal_status";
    
    static $_businessList = array();
    
    var $detail_basic = '基本信息';
    /**
     * detail_basic
     * @param mixed $plat_order_id ID
     * @return mixed 返回值
     */

    public function detail_basic($plat_order_id)
    {
        $render = app::get($this->_appName)->render();
        
        $memberMdl = app::get('ome')->model('members');
        $jxOrderLib = kernel::single('dealer_platform_orders');
        
        //获取订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $jxOrderLib->getOrderDetail($filter);
        
        $memberInfo = array();
        if($orderInfo['member_id']){
            $memberInfo = $memberMdl->dump($orderInfo['member_id']);
            
            //会员是否加密
            $memberInfo['is_encrypt'] = kernel::single('ome_security_router', ['shop_type'])->show_encrypt($memberInfo, 'member');
        }
        
        $render->pagedata['member'] = $memberInfo;
        $render->pagedata['order'] = $orderInfo;
        
        return $render->fetch('admin/order/detail_basic.html');
    }
    
    var $detail_goods = '订单明细';
    /**
     * detail_goods
     * @param mixed $plat_order_id ID
     * @return mixed 返回值
     */
    public function detail_goods($plat_order_id)
    {
        $render = app::get($this->_appName)->render();
        
        $jxOrderLib = kernel::single('dealer_platform_orders');
        $businessLib = kernel::single('dealer_business');
        
        //获取订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $jxOrderLib->getOrderDetail($filter);
        
        //获取贸易公司列表
        $betcList = $businessLib->getAssignBetcs();
        
        //订单明细
        $item_list = array();
        $goodsList = $pkgList = $giftList= array();
        foreach($orderInfo['order_objects'] as $objKey => $objVal)
        {
            $obj_type = $objVal['obj_type'];
            
            $order_items = $objVal['order_items'];
            unset($objVal['order_items']);
            
            //支付状态名称
            $objVal['pay_name'] = $jxOrderLib->getPayStatusName($objVal['pay_status']);
            
            //子订单状态名称
            $objVal['ship_name'] = $jxOrderLib->getShipStatusName($objVal['ship_status']);
            
            //object
            $item_list[$obj_type][$objKey] = $objVal;
            
            //check
            if(empty($order_items)){
                continue;
            }
            
            //items
            foreach($order_items as $itemKey => $itemVal)
            {
                $plat_item_id = $itemVal['plat_item_id'];
                $betc_id = $itemVal['betc_id'];
                
                //贸易公司名称
                if($betc_id){
                    $itemVal['betc_name'] = $betcList[$betc_id]['betc_name'];
                }else{
                    $itemVal['betc_name'] = ' - ';
                }
                
                //子订单状态名称
                $itemVal['ship_name'] = $jxOrderLib->getShipStatusName($itemVal['ship_status']);
                
                $item_list[$obj_type][$objKey]['order_items'][$plat_item_id] = $itemVal;
            }
            
            //obj_type
            if($obj_type == 'pkg'){
                $pkgList[$objKey] = $item_list[$obj_type][$objKey];
            }elseif($obj_type == 'gift'){
                $giftList[$objKey] = $item_list[$obj_type][$objKey];
            }else{
                $goodsList[$objKey] = $item_list[$obj_type][$objKey];
            }
        }
        
        //获取基础物料单位和规格
        //$item_list = ome_order_func::add_getItemList_colum($item_list);
        
        //销售价权限验证
//        if (!kernel::single('desktop_user')->has_permission('sale_price')) {
//            $showSalePrice = false;
//        }
        $showSalePrice = true;
        $render->pagedata['show_sale_price'] = $showSalePrice;
        
        $render->pagedata['order'] = $orderInfo;
        $render->pagedata['shop_type'] = $orderInfo['shop_type'];
        $render->pagedata['item_list'] = $item_list;
        
        $render->pagedata['pkgList'] = $pkgList;
        $render->pagedata['giftList'] = $giftList;
        $render->pagedata['goodsList'] = $goodsList;
        
        return $render->fetch('admin/order/detail_order_items.html');
    }
    
//    var $detail_fenxiao = '分销订单信息';
//    public function detail_fenxiao($plat_order_id)
//    {
//        $render = app::get($this->_appName)->render();
//
//        $jxOrderLib = kernel::single('dealer_platform_orders');
//        $businessLib = kernel::single('dealer_business');
//
//        //获取订单信息
//        $filter = array('plat_order_id'=>$plat_order_id);
//        $orderInfo = $jxOrderLib->getOrderDetail($filter);
//
//        //获取贸易公司列表
//        $betcList = $businessLib->getAssignBetcs();
//
//        //订单明细
//        $item_list = array();
//        foreach($orderInfo['order_objects'] as $objKey => $objVal)
//        {
//            $obj_type = $objVal['obj_type'];
//
//            $order_items = $objVal['order_items'];
//            unset($objVal['order_items']);
//
//            //支付状态名称
//            $objVal['pay_name'] = $jxOrderLib->getPayStatusName($objVal['pay_status']);
//
//            //子订单状态名称
//            $objVal['ship_name'] = $jxOrderLib->getShipStatusName($objVal['ship_status']);
//
//            //object
//            $item_list[$obj_type][$objKey] = $objVal;
//
//            //items
//            foreach($order_items as $itemKey => $itemVal)
//            {
//                $plat_item_id = $itemVal['plat_item_id'];
//                $betc_id = $itemVal['betc_id'];
//
//                //贸易公司名称
//                if($betc_id){
//                    $itemVal['betc_name'] = $betcList[$betc_id]['betc_name'];
//                }else{
//                    $itemVal['betc_name'] = ' - ';
//                }
//
//                //子订单状态名称
//                $itemVal['ship_name'] = $jxOrderLib->getShipStatusName($itemVal['ship_status']);
//
//                $item_list[$obj_type][$objKey]['order_items'][$plat_item_id] = $itemVal;
//            }
//        }
//
//        $showSalePrice = true;
//        $render->pagedata['show_sale_price'] = $showSalePrice;
//
//        $render->pagedata['order'] = $orderInfo;
//        $render->pagedata['shop_type'] = $orderInfo['shop_type'];
//        $render->pagedata['item_list'] = $item_list;
//
//        return $render->fetch('admin/order/detail_fenxiao.html');
//    }
    
    var $detail_fenxiao = '分销订单信息';
    /**
     * detail_fenxiao
     * @param mixed $plat_order_id ID
     * @return mixed 返回值
     */
    public function detail_fenxiao($plat_order_id)
    {
        $render = app::get($this->_appName)->render();
        
        $jxOrderLib = kernel::single('dealer_platform_orders');
        
        //获取订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $jxOrderLib->getOrderDetail($filter);
        
        //object
        $erpOrders = array();
        foreach($orderInfo['order_objects'] as $objKey => $objVal)
        {
            //check
            if(empty($objVal['order_items'])){
                continue;
            }
            
            //items
            foreach($objVal['order_items'] as $itemKey => $itemVal)
            {
                //check
                if($itemVal['is_delete'] == 'true'){
                    continue;
                }
                
                if(in_array($itemVal['is_shopyjdf_type'], array('2', '3')) && $itemVal['erp_order_bn']){
                    $erpOrders[] = $itemVal['erp_order_bn'];
                }
            }
        }
        
        //获取ERP分销订单信息
        $fenxiaoList = array();
        $erpOrders = array_unique($erpOrders);
        foreach ($erpOrders as $orderKey => $order_bn)
        {
            $filter = array('order_bn'=>$order_bn);
            $fenxiaoInfo = $jxOrderLib->getFenxiaoErpInfo($filter);
            if($fenxiaoInfo){
                $fenxiaoList[$order_bn] = $fenxiaoInfo;
            }
        }
        
        $render->pagedata['order'] = $orderInfo;
        $render->pagedata['fenxiaoList'] = $fenxiaoList;
        
        return $render->fetch('admin/order/fenxiao_orders.html');
    }

    /**
     * 订单操作记录
     *
     * @param int $order_id
     * @return string
     */
    var $detail_history = '订单操作记录';
    function detail_history($plat_order_id)
    {
        //普通订单
        return $this->__normal_log_history($plat_order_id);
    }
    
    var $detail_shipment = '状态回写';
    function detail_shipment($plat_order_id)
    {
        $render = app::get('ome')->render();
        $shipmentObj = app::get('ome')->model('shipment_log');
        
        $jxOrderLib = kernel::single('dealer_platform_orders');
        
        //获取订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $jxOrderLib->getOrderDetail($filter);
        $plat_order_bn = $orderInfo['plat_order_bn'];
        
        $shipmentLogs = array();
        //$shipmentLogs = $shipmentObj->getList('*', array('orderBn' => $orderBn));
        
        $render->pagedata['orderInfo'] = $orderInfo;
        $render->pagedata['shipmentLogs'] = $shipmentLogs;
        
        return $render->fetch('admin/order/detail_shipment.html');
    }
    
    /**
     * [普通]订单操作记录
     * 
     * @param int $order_id
     * @return string
     */
    private function __normal_log_history($plat_order_id)
    {
        $render = app::get($this->_appName)->render();
        
        $logMdl = app::get('ome')->model('operation_log');
        
        /* 本订单日志 */
        $history = $logMdl->read_log(array('obj_id'=>$plat_order_id, 'obj_type'=>'platform_orders@dealer'), 0, -1);
        foreach($history as $k=>$v)
        {
            $history[$k]['flag'] ='false';
            $history[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
        }
        
        $render->pagedata['history'] = $history;
        $render->pagedata['plat_order_id'] = $plat_order_id;
        
        return $render->fetch('admin/order/detail_history.html');
    }
    
    var $column_abnormal_mark = '异常标识';
    var $column_abnormal_mark_width = 130;
    var $column_abnormal_mark_order = 50;
    /**
     * column_abnormal_mark
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_abnormal_mark($row)
    {
        return kernel::single('dealer_operation_const')->getAbnormalTag($row[$this->col_prefix.'abnormal_status']);
    }
    
    //所属贸易公司
    var $column_betc_name = '所属贸易公司';
    var $column_betc_name_width = 130;
    var $column_betc_name_order = 52;
    /**
     * column_betc_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_betc_name($row, $list)
    {
        $this->_getBusiness($list);
        //check
        $bs_id = $row[$this->col_prefix .'bs_id'];
        if(empty($bs_id)){
            return '';
        }
        
        $businessInfo = (isset(self::$_businessList[$bs_id]) ? self::$_businessList[$bs_id] : array());
        
        $betcNames = array();
        if($businessInfo['betcs']){
            $betcNames = array_column($businessInfo['betcs'], 'betc_name');
        }
        
        return ($betcNames ? implode(',', $betcNames) : '');
    }
    
    /**
     * 批量获取指定经销商列表(包含贸易公司信息)
     * 
     * @param $list
     * @return boolean
     */
    private function _getBusiness($list)
    {
        //check
        if(self::$_businessList) {
            return true;
        }
        
        $bsIds = array_column($list, $this->col_prefix .'bs_id');
        if(empty($bsIds)){
            return true;
        }
        
        self::$_businessList = kernel::single('dealer_business')->getAssignBusiness($bsIds);
        
        return true;
    }
}
