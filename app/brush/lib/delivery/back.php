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
 * @author ykm 2015-12-30
 * @describe 特殊订单发货完成，进行发货回传
 */

class brush_delivery_back
{
    private $deliveryOrder;
    private $shipItemUseOrderItem = array('shopex_b2b');

    public function back($cursor_id,$params,$errormsg)
    {
        $this->backRequest($params['order_id']);
        
        return false;
    }

    public function backRequest($orderIds)
    {
        set_time_limit(0);
        
        $orderIds = $this->_getUnSyncOrderIds($orderIds);
        if(empty($orderIds)) {
            return false;
        }
        
        $orderDelivery = app::get('brush')->model('delivery_order')->getList('*', array('order_id'=>$orderIds));
        foreach($orderDelivery as $k => $val) {
            $deliveryIds[] = $val['delivery_id'];
            $this->deliveryOrder[$val['delivery_id']] = $val['order_id'];
        }
        
        $deliveryModel = app::get('brush')->model('delivery');
        $deliveryRows = $deliveryModel->getList('*', array('delivery_id'=>$deliveryIds, 'status'=>'succ'));
        $virtualBack = $normalBack = $logiDelivery = array();
        foreach($deliveryRows as $row) {
            if(empty($row['logi_id'])) {
                $virtualBack[$row['delivery_id']] = $row;
            } else {
                $normalBack[$row['delivery_id']] = $row;
                $logiDelivery[$row['logi_id']][$row['delivery_id']] = $row;
            }
        }
        
        if($virtualBack) {
            $this->_createVirtualRequest($virtualBack);
        }
        
        if($normalBack) {
            $fields = 'corp_id,type,name,channel_id,tmpl_type';
            $corpRows = app::get('ome')->model('dly_corp')->getList($fields, array('corp_id'=>array_keys($logiDelivery)));
            $arrCorp = $channel = array();
            foreach ($corpRows as $corp)
            {
                $arrCorp[$corp['corp_id']] = $corp;
                $channel[$corp['channel_id']] = 1;
            }
            
            $channelRows = app::get('logisticsmanager')->model('channel')->getList('*', array('channel_id' => array_keys($channel)));
            $arrChannel = array();
            foreach ($channelRows as $channelRow)
            {
                $arrChannel[$channelRow['channel_id']] = $channelRow;
            }
            
            foreach ($logiDelivery as $k => $val)
            {
                if ($arrCorp[$k]['tmpl_type'] == 'electron') {
                    $channelId = $arrCorp[$k]['channel_id'];
                    $eleChaClass = 'brush_electron_' . $arrChannel[$channelId]['channel_type'];
                    
                    try {
                        if (class_exists($eleChaClass) && method_exists($eleChaClass, 'delivery')) {
                            $objEleCha = kernel::single($eleChaClass);
                            $objEleCha->init(array('delivery' => $val, 'channel' => $arrChannel[$channelId]));
                            $objEleCha->delivery();
                        }
                    } catch (Exception $e) {

                    }
                }
            }
            
            $this->_createNormalRequest($normalBack);
        }
    }

    private function _getUnSyncOrderIds($orderIds)
    {
        $rows = app::get('ome')->model('orders')->getList('order_id', array('order_id'=>$orderIds, 'sync|noequal'=>'succ'));
        
        $arrRet = array();
        foreach($rows as $row) {
            $arrRet[] = $row['order_id'];
        }
        
        return $arrRet;
    }

    private function _createVirtualRequest($virtualBack)
    {
        $virtualIds = array_keys($virtualBack);
        
        $orderIds = $orderDelivery = array();
        foreach($this->deliveryOrder as $k => $val)
        {
            if(in_array($k, $virtualIds)) {
                $orderIds[] = $val;
                $orderDelivery[$val] = $k;
            }
        }
        
        $orderData = app::get('ome')->model('orders')->getList('order_id,order_bn,shop_id,ship_status', array('order_id'=>$orderIds));
        foreach($orderData as $order)
        {
            $delivery = $virtualBack[$orderDelivery[$order['order_id']]];
            
            $sdf = array();
            $sdf['is_virtual'] = true;
            $sdf['delivery_id'] = $delivery['delivery_id'];
            $sdf['delivery_bn'] = $delivery['delivery_bn'];
            $sdf['status'] = $delivery['status'];
            $sdf['orderinfo']['order_id'] = $order['order_id'];
            $sdf['orderinfo']['order_bn'] = $order['order_bn'];
            $sdf['orderinfo']['ship_status'] = $order['ship_status'];
            
            kernel::single('erpapi_router_request')->set('shop',$order['shop_id'])->delivery_confirm($sdf);
        }
    }

    private function _createNormalRequest($normalBack)
    {
        $corpId = $memberId = $orderId = $shopId = array();
        
        foreach($normalBack as $delivery) {
            $corpId[] = $delivery['logi_id'];
            $memberId[] = $delivery['member_id'];
            $shopId[] = $delivery['shop_id'];
            $orderId[] = $this->deliveryOrder[$delivery['delivery_id']];
        }
        
        $arrMember = $this->_getMember(array_unique($memberId));
        $arrCorp = $this->_getCorp(array_unique($corpId));
        $arrOrder = $this->_getOrderCorrelation(array_unique($orderId));
        $arrShop = $this->_getShop(array_unique($shopId));
        
        $arrDItems = $this->_getDeliveryItems($normalBack, $arrOrder, $arrShop);
        
        foreach($normalBack as $deliveryId => $delivery)
        {
            $shop_type = $arrOrder[$this->deliveryOrder[$delivery['delivery_id']]]['shop_type'];
            
            $params = array();
            
            //分銷王发货单回寫发货单号不支持字母
            if(!is_numeric($delivery['delivery_bn']) && $shop_type=='shopex_b2b'){
                $delivery['delivery_bn'] = ltrim($delivery['delivery_bn'],'B');
            }
            
            $dlyItems = array();
            foreach ($arrDItems[$delivery['delivery_id']] as $val) {
                $val['logi_no'] = $delivery['logi_no'];
                $val['logi_type'] = $arrCorp[$delivery['logi_id']]['type'];
                $dlyItems[] = $val;
            }
            
            $params['delivery_id'] = $delivery['delivery_id'];
            $params['delivery_bn'] = $delivery['delivery_bn'];
            $params['status'] = $delivery['status'];
            $params['logi_name'] = $arrCorp[$delivery['logi_id']]['name'];
            $params['logi_no'] = $delivery['logi_no'];
            $params['logi_type'] = $arrCorp[$delivery['logi_id']]['type'];
            $params['is_cod'] = $delivery['is_cod'];
            $params['itemNum'] = 1;
            $params['delivery_time'] = $delivery['delivery_time'];
            $params['last_modified'] = $delivery['last_modified'];
            $params['delivery_cost_actual'] = $delivery['delivery_cost_actual'];
            $params['create_time'] = $delivery['create_time'];
            $params['delivery'] = $delivery['delivery'];
            $params['memo'] = $delivery['memo'];
            $params['is_virtual'] = false;
            $params['consignee'] = $this->_getConsignee($delivery);
            $params['delivery_items'] = $dlyItems;
            $params['memberinfo'] = array('uname'=>$arrMember[$delivery['member_id']]['uname']);
            $params['orderinfo'] = $arrOrder[$this->deliveryOrder[$delivery['delivery_id']]];
            
            //[阿里巴巴]格式化回写oid商品数据
            if(in_array($shop_type, array('alibaba')))
            {
                $this->_compatible_order_sync($params);
            }
            
            $objErpApi = kernel::single('erpapi_router_request');
            $objErpApi->set('shop',$delivery['shop_id'])->delivery_add($params);
            $objErpApi->set('shop',$delivery['shop_id'])->delivery_logistics_update($params);
            $objErpApi->set('shop',$delivery['shop_id'])->delivery_confirm($params);
        }
    }

    private function _getMember($memberId)
    {
        $member = app::get('ome')->model('members')->getList('member_id,uname', array('member_id'=>$memberId));
        
        $arrMember = array();
        foreach($member as $val) {
            $arrMember[$val['member_id']] = $val;
        }
        
        return $arrMember;
    }

    private function _getCorp($corpId)
    {
        $corp = app::get('ome')->model('dly_corp')->getList('corp_id,name,type', array('corp_id'=>$corpId));
        
        $arrCorp = array();
        foreach($corp as $k => $val) {
            $arrCorp[$val['corp_id']] = $val;
        }
        
        return $arrCorp;
    }

    private function _getShop($shopId)
    {
        $shop = app::get('ome')->model('shop')->getList('shop_id,node_type,shop_type', array('shop_id'=>$shopId));
        
        $arrShop = array();
        foreach($shop as $k => $val)
        {
            $arrShop[$val['shop_id']] = $val;
        }
        
        return $arrShop;
    }

    private function _getDeliveryItems($arrDelivery, $arrOrder, $arrShop)
    {
        $items = app::get('brush')->model('delivery_items')->getList('item_id,delivery_id,bn,product_name,number', array('delivery_id'=>array_keys($arrDelivery)));
        
        $arrDeliveryIdItems = array();
        foreach($items as $item) {
            $arrDeliveryIdItems[$item['delivery_id']][$item['item_id']] = $item;
        }
        
        $order_ids = array();
        $arrDItems = array();
        foreach($arrDeliveryIdItems as $deliveryId => $DItems)
        {
            $shop_type = $arrShop[$arrDelivery[$deliveryId]['shop_id']]['shop_type'];
            $nodeType = $arrShop[$arrDelivery[$deliveryId]['shop_id']]['node_type'];
            
            if(in_array($nodeType, $this->shipItemUseOrderItem)) {
                foreach($arrOrder[$this->deliveryOrder[$deliveryId]]['order_objects'] as $orderObject)
                {
                    if($orderObject['obj_type'] == 'pkg') {
                        $arrDItems[$deliveryId][] = array(
                            'bn' => $orderObject['bn'],
                            'name' => $orderObject['name'],
                            'number' => $orderObject['quantity']
                        );
                    } else {
                        foreach($orderObject['order_items'] as $item) {
                            $arrDItems[$deliveryId][] = array(
                                'bn' => $item['bn'],
                                'name' => $item['product_name'],
                                'number' => $item['nums'],
                            );
                        }
                    }
                }
            } else {
                foreach ($DItems as $DItem) {
                    $arrDItems[$deliveryId][] = array(
                        'bn' => $DItem['bn'],
                        'name' => $DItem['product_name'],
                        'number' => $DItem['number'],
                    );
                }
                
                //需要回传oid的平台
                if(in_array($shop_type, array('alibaba'))){
                    $order_id = $arrOrder[$this->deliveryOrder[$deliveryId]]['order_id'];
                    
                    $order_ids[$order_id] = $deliveryId;
                }
            }
        }
        
        //[兼容]需要回传oid的平台
        if($order_ids){
            $oidBns = array();
            foreach($order_ids as $order_id => $delivery_id)
            {
                if(empty($arrOrder[$order_id]['order_objects'])){
                    continue;
                }
                
                foreach($arrOrder[$order_id]['order_objects'] as $objKey => $objVal)
                {
                    $obj_bn = $objVal['bn'];
                    $obj_oid = $objVal['oid'];
                    $obj_type = ($objVal['obj_type']=='pkg' ? 'pkg' : 'normal');
                    
                    if(empty($obj_oid)){
                        continue;
                    }
                    
                    foreach ($objVal['order_items'] as $itemKey => $itemVal)
                    {
                        $item_bn = $itemVal['bn'];
                        
                        $oidBns[$delivery_id][$item_bn][$obj_type] = $obj_oid;
                    }
                }
            }
            
            //格式化
            foreach ($arrDItems as $delivery_id => $items)
            {
                foreach ($items as $itemKey => $itemVal)
                {
                    $item_bn = $itemVal['bn'];
                    
                    //oid
                    $oid = '';
                    if($oidBns[$delivery_id][$item_bn]['normal']){
                        $oid = $oidBns[$delivery_id][$item_bn]['normal'];
                    }elseif($oidBns[$delivery_id][$item_bn]['pkg']){
                        $oid = $oidBns[$delivery_id][$item_bn]['pkg'];
                    }
                    
                    //push
                    if($oid){
                        $arrDItems[$delivery_id][$itemKey]['oid'] = $oid;
                    }
                }
            }
        }
        
        return $arrDItems;
    }

    private function _getConsignee($delivery)
    {
        $ret = array(
            'name' => $delivery['ship_name'],
            'area' => $delivery['ship_area'],
            'addr' => $delivery['ship_addr'],
            'zip' => $delivery['ship_zip'],
            'email' => $delivery['ship_email'],
            'mobile' => $delivery['ship_mobile'],
            'telephone' => $delivery['ship_tel'],
        );
        
        return $ret;
    }

    private function _getOrderCorrelation($orderIds)
    {
        $order = app::get('ome')->model('orders')->getList('shop_type,order_id,order_bn,ship_status,createway,sync,is_cod,self_delivery', array('order_id'=>$orderIds));
        $arrOrder = array();
        foreach($order as $val) {
            $arrOrder[$val['order_id']] = $val;
        }
        
        $orderExtend = app::get('ome')->model('order_extend')->getList('order_id, sellermemberid', array('order_id'=>$orderIds));
        foreach($orderExtend as $extend) {
            $arrOrder[$extend['order_id']]['sellermemberid'] = $extend['sellermemberid'];
        }
        
        $arrOItem = array();
        $orderItems = app::get('ome')->model('order_items')->getList('item_id,order_id,obj_id,shop_goods_id,sendnum,bn,name,item_type,nums,`delete`', array('order_id'=>$orderIds));
        foreach($orderItems as $item)
        {
            $arrOItem[$item['order_id']][$item['obj_id']][] = array(
                'bn' => $item['bn'],
                'shop_goods_id' => $item['shop_goods_id'],
                'sendnum' => $item['sendnum'],
                'product_name' => $item['name'],
                'item_type' => $item['item_type'],
                'nums' => $item['nums'],
                'delete' => $item['delete'],
            );
        }
        
        $orderObject = app::get('ome')->model('order_objects')->getList('obj_id,order_id,shop_goods_id,quantity,name,oid,bn,obj_type', array('order_id'=>$orderIds));
        foreach($orderObject as $object)
        {
            $arrOrder[$object['order_id']]['order_objects'][] = array(
                'bn' => $object['bn'],
                'oid' => $object['oid'],
                'shop_goods_id' => $object['shop_goods_id'],
                'quantity' => $object['quantity'],
                'name' => $object['name'],
                'obj_type' => $object['obj_type'],
                'order_items' => $arrOItem[$object['order_id']][$object['obj_id']]
            );
        }
        
        return $arrOrder;
    }
    
    /**
     * [兼容]发货明细没有可回传平台的oid前端平台商品
     * @todo：[场景]编辑订单删除平台商品后,又添加了新商品;这时回写给平台失败,因为没有oid数据;
     * @todo：目前只支持：
     * 1、订单全部发货后回写;
     * 2、前端平台oid商品都被编辑删除,替换了其它商品;
     * 3、订单是[已支付]状态;
     * 4、本次兼容只会回写已经被删除掉的所有oid商品;
     * 5、仅针对阿里巴巴平台有oid商品;
     */
    public function _compatible_order_sync(&$params)
    {
        $orderObj = app::get('ome')->model('orders');
        
        if(empty($params) || empty($params['delivery_items'])){
            return false;
        }
        
        //判断是否有oid平台商品
        $is_fail = true;
        foreach ($params['delivery_items'] as $key => $item)
        {
            if($item['oid']) {
                $is_fail = false;
            }
        }
        
        if(!$is_fail){
            return false;
        }
        
        //订单信息
        $order_id = intval($params['orderinfo']['order_id']);
        $orderInfo = $orderObj->dump(array('order_id'=>$order_id), 'order_bn,process_status,status,pay_status,ship_status,is_modify');
        if(empty($orderInfo)){
            return false;
        }
        
        //check
        if($orderInfo['is_modify'] != 'true'){
            return false; //订单没有编辑过
        }
        
        if($orderInfo['ship_status'] != '1'){
            return false; //订单不是[全部发货]状态
        }
        
        if($orderInfo['pay_status'] != '1'){
            return false; //订单不是[已支付]状态
        }
        
        if(empty($params['orderinfo']['order_objects'])){
            return false;
        }
        
        //获取被删除的oid商品
        $delivery_items = array();
        foreach ($params['orderinfo']['order_objects'] as $obj_id => $objInfo)
        {
            if(empty($objInfo['oid'])){
                continue;
            }
            
            $nums = ($objInfo['quantity'] ? $objInfo['quantity'] : $objInfo['nums']);
            
            $delivery_items[$obj_id] = array(
                    'name' => trim($objInfo['name']),
                    'bn' => trim($objInfo['bn']),
                    'number' => intval($nums),
                    'item_type' => $objInfo['obj_type'],
                    'shop_goods_id' => $objInfo['shop_goods_id'],
                    'oid' => $objInfo['oid'],
            );
        }
        
        //重新赋值
        if($delivery_items){
            $params['delivery_items'] = $delivery_items;
        }
        
        return true;
    }
}