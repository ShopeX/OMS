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
 * @author ykm 2015-11-19
 * @describe 特殊订单发货公用类
 */
class brush_delivery
{
    public $log_msg = '发货完成';
    private $deliveryOrderSucc = array();

    public function generateBrushDeliveryBn()
    {
        $conObj = app::get('ome')->model('concurrent');
        
        list($msec, $sec) = explode(" ",microtime());
        
        $sec = substr($sec, 6);
        $msec = substr((string) $msec,(strpos((string) $msec, '.')+1),6);
        $msec = str_pad($msec,6,0);
        $bn = 'B' . date('ymd') . $sec . $msec;
        
        if($conObj->is_pass($bn,'brush_delivery')){
            return $bn;
        } else {
            return $this->generateBrushDeliveryBn();
        }
    }

    /**
     * 获取订单对应的发货数据
     * 
     * @param array $order_id
     * @param int $crop_id
     * @return array
     */
    public function orderToDelivery($order_id, $crop_id=0)
    {
        if (!$order_id) {
            return false;
        }
        
        $fields = 'order_id,member_id,is_cod,shipping,ship_name,ship_area,ship_addr,ship_zip,ship_tel,ship_mobile,ship_email,ship_time,weight,shop_id,paytime,createtime,shop_type';
        $order = app::get('ome')->model('orders')->getList($fields, array('order_id'=>$order_id));
        if(empty($crop_id)) {
            $order_id = $this->_checkVirtalDelivery($order);
            if(empty($order_id)){
                return false;
            }
        }
        
        $objectData = app::get('ome')->model('order_objects')->getList('order_id,obj_id,obj_type,goods_id,price', array('order_id' => $order_id));
        $itemData = app::get('ome')->model('order_items')->getList('*', array('order_id'=>$order_id, 'delete'=>'false'));
        
        $delivery = array();
        if (is_array($order_id)) {
            $orderObject = array();
            $orderItem = array();
            
            foreach($objectData as $val) {
                $orderObject[$val['order_id']][] = $val;
            }
            
            foreach($itemData as $val){
                $orderItem[$val['order_id']][] = $val;
            }
            
            foreach($order as $val)
            {
                $delivery[] = $this->orderDataToDeliveryData($val, $orderObject[$val['order_id']], $orderItem[$val['order_id']], $crop_id);
            }
        } else {
            $delivery[] = $this->orderDataToDeliveryData($order[0], $objectData, $itemData, $crop_id);
        }
        
        return $delivery;
    }

    /**
     * 组织发货单数据
     * 
     * @param array $order
     * @param array $objectData
     * @param array $itemData
     * @param int $crop_id
     * @return array
     */
    private function orderDataToDeliveryData($order, $objectData, $itemData, $crop_id)
    {
        $data = array();
        $objData = array();
        
        foreach($objectData as $obj)
        {
            $objData[$obj['obj_id']] = $obj;
        }
        
        $delItem = array();
        $delItemDetail = array();
        $itemNum = 0;
        
        foreach($itemData as $val)
        {
            $itemNum += $val['nums'];
            
            //delivery_items 数据
            if($delItem[$val['bn']]) {
                $delItem[$val['bn']]['number'] += $val['nums'];
            } else {
                $delItem[$val['bn']]['product_id'] = $val['product_id'];
                $delItem[$val['bn']]['shop_product_id'] = $val['shop_product_id'];
                $delItem[$val['bn']]['bn'] = $val['bn'];
                $delItem[$val['bn']]['product_name'] = $val['name'];
                $delItem[$val['bn']]['number'] = $val['nums'];
            }
            
            //delivery_items_detail 数据
            $delItemDetail[$val['item_id']]['order_id'] = $val['order_id'];
            $delItemDetail[$val['item_id']]['order_item_id'] = $val['item_id'];
            $delItemDetail[$val['item_id']]['order_obj_id'] = $val['obj_id'];
            
            $objType = $objData[$val['obj_id']]['obj_type'] == 'goods' ? 'product' : $objData[$val['obj_id']]['obj_type'];
            
            $delItemDetail[$val['item_id']]['item_type'] = $objType;
            $delItemDetail[$val['item_id']]['product_id'] = $val['product_id'];
            $delItemDetail[$val['item_id']]['bn'] = $val['bn'];
            $delItemDetail[$val['item_id']]['number'] = $val['nums'];
            
            if($objType == 'pkg') {
                $goods_id = $objData[$val['obj_id']]['goods_id'];
                
                $selSql = "SELECT sum(number) AS sumNums FROM sdb_material_sales_basic_material WHERE sm_id=". $goods_id;
                $objCount = kernel::database()->select($selSql);
                
                $price = $objCount[0]['sum'] ? $objData[$val['obj_id']]['price'] / $objCount[0]['sumNums'] : 0;
            } else {
                $price = $objData[$val['obj_id']]['price'];
            }
            
            $delItemDetail[$val['item_id']]['price'] = floatval($price);
            $delItemDetail[$val['item_id']]['amount'] = $price * $val['nums'];
            
            unset($objType);
            unset($price);
            
            $bns['bn'][] = $val['bn'];
        }
        
        //delivery 数据
        $bns['skuNum']  = count($itemData);
        $bns['itemNum'] = $itemNum;
        $data['skuNum'] = $bns['skuNum'];
        $data['itemNum'] = $bns['itemNum'];
        $data['bnsContent'] = serialize($bns);
        $data['order_id'] = $order['order_id'];
        $data['member_id'] = $order['member_id'];
        $data['is_cod'] = $order['is_cod'] ? $order['is_cod'] : 'false';
        $data['delivery'] = $order['shipping'];
        $data['logi_id'] = $crop_id;
        $data['ship_name'] = $order['ship_name'];
        $data['ship_area'] = $order['ship_area'];
        
        list($area_prefix,$area_chs,$area_id) = explode(':', $order['ship_area']);
        
        list($data['ship_province'],$data['ship_city'],$data['ship_district']) = explode('/',$area_chs);
        
        $data['ship_addr'] = $order['ship_addr'];
        $data['ship_zip'] = $order['ship_zip'];
        $data['ship_tel'] = $order['ship_tel'];
        $data['ship_mobile'] = $order['ship_mobile'];
        $data['ship_email'] = $order['ship_email'];
        $data['ship_time'] = $order['ship_time'];
        $data['create_time'] = time();
        $data['status'] = 'ready';
        $data['net_weight'] = $order['weight'];
        $data['delivery_cost_expect'] = $data['logi_id'] == 0 ? 0 : app::get('ome')->model('delivery')->getDeliveryFreight($area_id,$data['logi_id'], $data['net_weight']);
        $data['shop_id'] = $order['shop_id'];
        $data['shop_type'] = $order['shop_type'];
        $data['order_createtime'] = ($order['paytime'] && $data['is_cod'] == 'false') ? $order['paytime'] : $order['createtime'];
        
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        
        $data['op_id']   = $opInfo['op_id'];
        $data['op_name'] = $opInfo['op_name'];
        
        return array('main'=>$data, 'item'=>$delItem, 'itemDetail'=>$delItemDetail);
    }

    private function _checkVirtalDelivery(&$order)
    {
        $arrShopId = array();
        
        foreach($order as $val) {
            $arrShopId[] = $val['shop_id'];
        }
        
        $rows = app::get('ome')->model('shop')->getList('shop_id, shop_type', array('shop_id'=>array_unique($arrShopId)));
        
        $allowShop = array();
        foreach($rows as $row)
        {
            if(in_array($row['shop_type'], ome_shop_type::virtual_delivery())) {
                $allowShop[] = $row['shop_id'];
            }
        }
        
        $retId = array();
        foreach($order as $key => $value)
        {
            if(in_array($value['shop_id'], $allowShop)) {
                $retId[] = $value['order_id'];
            } else {
                unset($order[$key]);
            }
        }
        
        return $retId;
    }
    
    /**
     * 自动虚拟发货
     * 
     * @param int $delivery_id
     * @param int $order_id
     * @param array $params
     * @return boolean
     */
    public function finishDeliver($delivery_id, $order_id, $params=array(), &$error_msg=null)
    {
        if(empty($delivery_id) || empty($order_id)) {
            return false;
        }
        
        $orderUp = array(
            'process_status'=>'splited',
            'status'=>'finish',
            'ship_status'=>1,
            'print_finish' => 'true',
            'print_status' => 1,
        );
        
        $params['logi_id'] && $orderUp['logi_id'] = $params['logi_id'];
        $params['logi_no'] && $orderUp['logi_no'] = $params['logi_no'];
        
        $ret = app::get('ome')->model('orders')->update($orderUp, array('order_id'=>$order_id));
        if(is_bool($ret)) {
            $error_msg = '更新订单状态失败';
            return false;
        }
        
        $sql = 'update sdb_ome_order_items set sendnum = nums where order_id='. $order_id;
        $ret = kernel::database()->exec($sql);
        if(!$ret) {
            $error_msg = '更新订单明细发货数量失败';
            return false;
        }
        
        $ret = app::get('brush')->model('delivery')->update(array('delivery_time'=>time(), 'status'=>'succ', 'expre_status'=>'true'), array('delivery_id'=>$delivery_id));
        if(!$ret) {
            $error_msg = '更新发货单状态失败';
            return false;
        }
        
        $sql = 'update sdb_brush_delivery_items set verify_num = `number`, verify = "true" where delivery_id='. $delivery_id;
        $ret = kernel::database()->exec($sql);
        if(!$ret) {
            $error_msg = '更新发货单明细发货数量失败';
            return false;
        }
        
        $ret = app::get('ome')->model('operation_log')->write_log('delivery_brush_checkdelivery@brush', $delivery_id, $this->log_msg);
        if(!$ret) {
            $error_msg = '记录log日志失败';
            return false;
        }
        
        if(empty($this->deliveryOrderSucc)) {
            register_shutdown_function(array(&$this, 'sync'));
        }
        
        $this->deliveryOrderSucc[] = $order_id;
        
        return true;
    }

    public function sync()
    {
        if(empty($this->deliveryOrderSucc)) {
            return false;
        }
        
        kernel::single('brush_delivery_back')->backRequest($this->deliveryOrderSucc);

        // $oQueue = app::get('base')->model('queue');
        // $queueData = array(
        //     'queue_title'=>'发货单发货回写',
        //     'start_time'=>time(),
        //     'params'=>array(
        //         'order_id'=>$this->deliveryOrderSucc,
        //     ),
        //     'worker'=>'brush_delivery_back.back',
        // );
        // $oQueue->save($queueData);
        // $oQueue->flush();

    }
}
