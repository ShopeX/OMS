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
 +----------------------------------------------------------
 * 复审订单列表
 +----------------------------------------------------------
 * 
 * Time: 2014-04-22 $
 * [Ecos!] (C)2003-2014 Shopex Inc.
 +----------------------------------------------------------
 */
class ome_finder_order_retrial
{
	/*------------------------------------------------------ */
    //-- 操作
    /*------------------------------------------------------ */
    var $column_edit    = '操作';
    var $column_edit_order  = '5';
    var $column_edit_width  = '70';
    function column_edit($row)
    {
    	$str   = '<a href="index.php?app=ome&ctl=admin_order_retrial&act=normal&id='.$row['id'].'&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'" 
        target="_blank">复审</a>';
    	
        if($row['status'] == '4')
        {
            $str    = '已取消';
        }
        elseif($row['status'] == '1' || $row['status'] == '2' || $row['status'] == '3')
        {
            $str    = '已审核';
        }
        
        return $str;
    }
    /*------------------------------------------------------ */
    //-- 订单详情
    /*------------------------------------------------------ */
    var $detail_basic    = '订单详情';
    function detail_basic($id)
    {
        $render     = app::get('ome')->render();
        $result     = array();
        
        //retrial
        $oItem      = app::get('ome')->model('order_retrial');
        $result     = $oItem->getList('*', array('id'=>$id), 0, 1);
        $result     = $result[0];
        
        //type
        $columns        = $oItem->schema;
        $retrial_type   = $columns['columns']['retrial_type']['type'];
        $status_arr     = $columns['columns']['status']['type'];
        
        $result['retrial_val']      = $retrial_type[$result['retrial_type']];
        $result['status_val']       = $status_arr[$result['status']];
        
        //orders
        $oOrders        = app::get('ome')->model('orders');
        $order_detail   = $oOrders->dump($result['order_id'], "*", array("order_items"=>array("*")));
        $order_detail['mark_text']      = kernel::single('ome_func')->format_memo($order_detail['mark_text']);
        $order_detail['custom_mark']    = kernel::single('ome_func')->format_memo($order_detail['custom_mark']);

        $render->pagedata['result']     = $result;
        $render->pagedata['order']      = $order_detail;
        return $render->fetch('admin/order/retrial_detail.html');
    }
    /*------------------------------------------------------ */
    //-- 订单明细
    /*------------------------------------------------------ */
    var $detail_goods     = '订单明细';
    function detail_goods($id)
    {
    	$render     = app::get('ome')->render();
        
        //retrial
        $oItem      = app::get('ome')->model('order_retrial');
        $row        = $oItem->getList('order_id', array('id'=>$id), 0, 1);
        $order_id   = $row[0]['order_id'];

        //goods_list
        $oOrder     = app::get('ome')->model('orders');

        $item_list = $oOrder->getItemList($order_id, true);
        $item_list = ome_order_func::add_getItemList_colum($item_list);
        ome_order_func::order_sdf_extend($item_list);
        $orders = $oOrder->getRow(array('order_id'=>$order_id),'shop_type,order_source');
        $is_consign = false;
        
        #淘宝代销订单增加代销价
        if($orders['shop_type'] == 'taobao' && $orders['order_source'] == 'tbdx' )
        {
            kernel::single('ome_service_c2c_taobao_order')->order_sdf_extend($item_list);
            $is_consign = true;
        }

        $configlist = array();
        if ($servicelist = kernel::servicelist('ome.service.order.products'))
        foreach ($servicelist as $object => $instance)
        {
            if (method_exists($instance, 'view_list')){
                $list = $instance->view_list();
                $configlist = array_merge($configlist, is_array($list) ? $list : array());
            }
        }

        $render->pagedata['is_consign'] = ($is_consign > 0)?true:false;
        $render->pagedata['configlist'] = $configlist;
        $render->pagedata['item_list'] = $item_list;
        $render->pagedata['object_alias'] = $oOrder->getOrderObjectAlias($order_id);
        return $render->fetch('admin/order/detail_goods.html');
        
        $render->pagedata['datalist']     = $goodslog;
        return $render->fetch('admin/order/retrial_detail_log.html');
    }
    /*------------------------------------------------------ */
    //-- 订单操作记录
    /*------------------------------------------------------ */
    var $detail_history = '订单操作记录';
    function detail_history($id)
    {
    	$render     = app::get('ome')->render();
        
        //retrial
        $oItem      = app::get('ome')->model('order_retrial');
        $row        = $oItem->getList('order_id', array('id'=>$id), 0, 1);
        $order_id   = $row[0]['order_id'];

        #订单
        $orderObj = app::get('ome')->model('orders');
        $logObj = app::get('ome')->model('operation_log');
        $deliveryObj = app::get('ome')->model('delivery');
        $ooObj = app::get('ome')->model('operations_order');

        /* 本订单日志 */
        $history = $logObj->read_log(array('obj_id'=>$order_id,'obj_type'=>'orders@ome'),0,-1);
        foreach($history as $k=>$v){
            $data = $ooObj->getList('operation_id',array('log_id'=>$v['log_id']));
            if(!empty($data)){
                $history[$k]['flag'] ='true';
            }else{
                $history[$k]['flag'] ='false';
            }
            $history[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }

        /* 发货单日志 */
        $delivery_ids = $deliveryObj->getDeliverIdByOrderId($order_id);
        $deliverylog = $logObj->read_log(array('obj_id'=>$delivery_ids,'obj_type'=>'delivery@ome'), 0, -1);
        foreach($deliverylog as $k=>$v){
            $deliverylog[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }

        /* “失败”、“取消”、“打回”发货单日志 */
        $history_ids = $deliveryObj->getHistoryIdByOrderId($order_id);
        $deliveryHistorylog = array();
        foreach($history_ids as $v){
            $delivery = $deliveryObj->dump($v,'delivery_id,delivery_bn');
            $deliveryHistorylog[$delivery['delivery_bn']] = $logObj->read_log(array('obj_id'=>$v,'obj_type'=>'delivery@ome'), 0, -1);
            foreach($deliveryHistorylog[$delivery['delivery_bn']] as $k=>$v){
                $deliveryHistorylog[$delivery['delivery_bn']][$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
            }
        }

        /* 同批处理的订单日志 */
        $order_ids = $deliveryObj->getOrderIdByDeliveryId($delivery_ids);
        $orderLogs = array();
        foreach($order_ids as $v){
            if($v != $order_id){
                $order = $orderObj->dump($v,'order_id,order_bn');
                $orderLogs[$order['order_bn']] = $logObj->read_log(array('obj_id'=>$v,'obj_type'=>'orders@ome'), 0, -1);
                foreach($orderLogs[$order['order_bn']] as $k=>$v){
                    if($v)
                        $orderLogs[$order['order_bn']][$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
                }
            }
        }

        $render->pagedata['history'] = $history;
        $render->pagedata['deliverylog'] = $deliverylog;
        $render->pagedata['deliveryHistorylog'] = $deliveryHistorylog;
        $render->pagedata['orderLogs'] = $orderLogs;
        $render->pagedata['order_id'] = $order_id;

        return $render->fetch('admin/order/detail_history.html');
    }
    /*------------------------------------------------------ */
    //-- 复审日志
    /*------------------------------------------------------ */
    var $detail_retrial_log     = '复审日志';
    function detail_retrial_log($id)
    {
    	$render     = app::get('ome')->render();
    	
    	#retrial
        $oItem      = app::get('ome')->model('order_retrial');
        $row        = $oItem->getList('*', array('id'=>$id), 0, 1);
        $row        = $row[0];
        
        #快照
        $oSnapshot  = app::get('ome')->model('order_retrial_snapshot');
        $snapList   = $oSnapshot->getList('tid, dateline', array('order_id'=>$row['order_id']), 0, -1);
        
        $order_snap = array();
        foreach ($snapList as $key=> $val)
        {
        	$order_snap[$val['dateline']]  = $val;
        }

        #log
        $logObj     = app::get('ome')->model('operation_log');
        $goodslog   = $logObj->read_log(array('obj_id'=>$row['order_id'], 'obj_type'=>'orders@ome', 'operation'=>'order_retrial@ome'), 0, -1);
        foreach($goodslog as $k=>$v)
        {
        	$operate_time  = $v['operate_time'];
        	if(!empty($order_snap[$operate_time]))
        	{
        		$goodslog[$k]['snap']     = $order_snap[$operate_time]['tid'];
        	}
        	$goodslog[$k]['operate_time'] = date('Y-m-d H:i:s', $operate_time);
        }
        
    	$render->pagedata['datalist']     = $goodslog;
        return $render->fetch('admin/order/retrial_detail_log.html');
    }
    /*------------------------------------------------------ */
    //-- 格式化字段
    /*------------------------------------------------------ */
    #订单总额
    var $column_total_amount    = '订单总额';
    var $column_total_amount_width  = '80';
    var $column_total_amount_order  = 50;
    function column_total_amount($row)
    {
    	return '<span style="font-weight:bold;color:#ff0000;">¥'.$row['total_amount'].'</span>';
    }
    
    #确认状态
    var $column_process_status = "确认状态";
    var $column_process_status_width = '80';
    var $column_process_status_order = 80;
    function column_process_status($row)
    {
    	$order_Obj         = app::get('ome')->model('orders');
    	$columns            = $order_Obj->schema;
        $process_status     = $columns['columns']['process_status']['type'];

        return ($row['process_status'] ? $process_status[$row['process_status']] : '');
    }
    
    #货到付款
    var $column_is_cod = "货到付款";
    var $column_is_cod_width = '80';
    var $column_is_cod_order = 80;
    function column_is_cod($row)
    {
        return ($row['is_cod'] == 'true' ? '<span style="font-weight:bold;color:green;">货到付款</span>' : 
        '<span style="font-weight:bold;color:#ff0000;">款到发货</span>');
    }
    
    #付款状态
    var $column_pay_status = "付款状态";
    var $column_pay_status_width = '80';
    var $column_pay_status_order = 90;
    function column_pay_status($row)
    {
    	$order_Obj     = app::get('ome')->model('orders');
        $columns        = $order_Obj->schema;
        $pay_status     = $columns['columns']['pay_status']['type'];
        
        return ($row['pay_status'] != 1 ? '<span style="font-weight:bold;color:#ff0000;">'.$pay_status[$row['pay_status']].'</span>' 
                                        : '<span style="font-weight:bold;color:green;">'.$pay_status[$row['pay_status']].'</span>');
    }
    
    #发货状态
    var $column_ship_status = "发货状态";
    var $column_ship_status_width = '80';
    var $column_ship_status_order = 100;
    function column_ship_status($row)
    {
    	$order_Obj     = app::get('ome')->model('orders');
        $columns       = $order_Obj->schema;
    	$ship_status   = $columns['columns']['ship_status']['type'];
    	
        return $ship_status[$row['ship_status']];
    }
    
    #下单时间
    var $column_createtime = "下单时间";
    var $column_createtime_width = '130';
    var $column_createtime_order = 110;
    function column_createtime($row)
    {
        return date('Y-m-d H:i:s', $row['createtime']);
    }
    /*------------------------------------------------------ */
    //-- 显示行样式
    /*------------------------------------------------------ */
    function row_style($row)
    {
        $style  = '';
        if($row['status'] == '1')
        {
        	$style .= ' list-even highlight-row ';
        }
        elseif($row['status'] == '2')
        {
        	$style .= ' highlight-row ';
        }
        elseif($row['status'] == '3')
        {
        	$style .= ' selected ';
        }
        
        return $style;
    }
    
    var $column_shop_name = "来源店铺";
    var $column_shop_name_width = '130';
    var $column_shop_name_order = 120;
    
    function column_shop_name($row)
    {
        $shop = $this->_getShop($row['shop_id']);
        return $shop['name'] ?: '';
    }
    
    /**
     * 获取店铺信息
     * @param $shop_id
     * @param $list
     * @return array|mixed
     * @author db
     * @date 2024-04-23 2:11 下午
     */
    protected $__shopList = [];
    
    private function _getShop($shop_id)
    {
        $data = $this->__shopList;
        if (isset($data[$shop_id])) {
            return $data[$shop_id] ?: [];
        }
        
        $rows = app::get('ome')->model('shop')->getList('shop_id,shop_bn,name,shop_type');
        foreach ($rows as $row) {
            $this->__shopList[$row['shop_id']] = $row;
        }
        
        return $this->__shopList[$shop_id] ?: [];
    }
}
