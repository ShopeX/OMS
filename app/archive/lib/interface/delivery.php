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

class archive_interface_delivery{
  
    static $branchs = array();
    /**
     * 根据订单ID获取发货单.
     * @param   
     * @return 
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_delivery($order_id)
    {
        $deliveryIds  =$this->_get_deliveryId($order_id);

        if(!$deliveryIds) return array();
        $delivObj = app::get('archive')->model('delivery');
        $deliveryIds_str = implode(',',$deliveryIds);
        
        $delivery_list = $delivObj->db->select("SELECT * FROM sdb_archive_delivery WHERE delivery_id in(".$deliveryIds_str.")");
        $delivery_items = $this->_get_delivery_items($deliveryIds);

        $delivery_logino = $this->_get_delivery_logino($deliveryIds_str);
        
        foreach ( $delivery_list as $k=>$delivery ) {
            $delivery_list[$k]['items'] = $delivery_items[$delivery['delivery_id']];
            
            $delivery_list[$k]['logino'] = $delivery_logino[$delivery['delivery_id']];
            $delivery_list[$k]['branch_name'] = $this->get_branchname($delivery['branch_id']);
        }
        
        return $delivery_list;

    }

    
    /**
     * 根据order_id
     * @param  
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function _get_deliveryId($order_id)
    {
        $delivObj = app::get('archive')->model('delivery');
        $order_delivery = $delivObj->db->select("SELECT * FROM sdb_archive_delivery_order WHERE order_id=".$order_id);
        $ids = array();
        foreach ( $order_delivery as $delivery ) {
            $ids[] = $delivery['delivery_id'];
        }
        return $ids;
    }

    
    /**
     * 发货单明细
     * @param  
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function _get_delivery_items($deliveryIds)
    {
        $delivObj = app::get('archive')->model('delivery');
        $deliveryIds_str = implode(',',$deliveryIds);
        $items = $delivObj->db->select("SELECT * FROM sdb_archive_delivery_items WHERE delivery_id in(".$deliveryIds_str.")");
        $item_list = array();
        foreach ($items as $item ) {
            $item_list[$item['delivery_id']][] = $item;
        }
        return $item_list;
    }

    
    /**
     * 获取发货单物流单号
     * @param  
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function _get_delivery_logino($deliveryIds)
    {
        $delivObj = app::get('archive')->model('delivery');
        $logi_no_list = $delivObj->db->select("SELECT delivery_id,logi_no FROM sdb_archive_delivery_bill WHERE delivery_id in(".$deliveryIds.")");
        $logi_no = array();
        foreach ( $logi_no_list as $logi ) {
            $logi_no[$logi['delivery_id']][] = $logi;
        }
        return $logi_no;
    }

    /*
     * 根据订单id获取发货单信息
     *
     * @param string $cols
     * @param bigint $order_id 订单id
     *
     * @return array $delivery 发货单数组
     */

    function getDeliveryByOrder($cols="*",$order_id){
        $deliveryObj = app::get('archive')->model('delivery');
        $delivery_ids = $this->_get_deliveryId($order_id);
        if($delivery_ids){
            $delivery = $deliveryObj->getList($cols,array('delivery_id'=>$delivery_ids),0,-1);
            if($delivery){
                foreach($delivery as $k=>$v){
                    if(isset($v['branch_id'])){
                      // get_branchname 已返回仓名字符串（同 get_delivery），勿再当数组取 ['name']
                      $delivery[$k]['branch_name'] = $this->get_branchname($v['branch_id']);
                    }
                }
           
                return $delivery;
            }else{
                return array();
            }
        }else{
            return array();
        }
    }
    
    function get_branchname($branch_id){
        if (!isset(self::$branchs[$branch_id])){
            $branchObj = app::get('ome')->model('branch');
            // 展示用：跳过子账号仓权限过滤，避免归档退发货记录发货仓库为空
            $branch = $branchObj->dump(array(
                'branch_id' => $branch_id,
                'check_permission' => 'false',
            ), 'name');
            self::$branchs[$branch_id] = (is_array($branch) && isset($branch['name']))
                ? $branch['name']
                : '';
        }
        return self::$branchs[$branch_id];
    }
    
    function getDeliveryByorderId($order_id){
        $delivObj = app::get('archive')->model('delivery');
        $sql2 = 'select ODB.logi_no from sdb_archive_delivery_bill as ODB left join sdb_archive_delivery_order as ODO on ODB.delivery_id = ODO.delivery_id  where ODO.order_id= '.$order_id;
        return $delivObj->db->select($sql2);
    }

    function getDelivery($filter,$col="*"){
        $deliveryObj =app::get('archive')->model('delivery');
        $deliverys = $deliveryObj->dump($filter,$col);
        return $deliverys;
    }

    function getDelivery_list($filter,$col="*"){
        $deliveryObj =app::get('archive')->model('delivery');
        $delivery_list = $deliveryObj->getList($col, $filter, 0, -1);
        return $delivery_list;
    }

    function getDelivery_order($order_id){
        $oDelivery_order = app::get('archive')->model('delivery_order');
        $delivery_order = $oDelivery_order->getList('delivery_id',array('order_id'=>$order_id));
        return $delivery_order;
    }

    function getOrderBydeliverybn($delivery_bn){
        $oDelivery_order = app::get('archive')->model('delivery_order');
        $delivery_list = $oDelivery_order->db->select("SELECT od.order_id FROM sdb_archive_delivery_order as od left join sdb_archive_delivery as d on od.delivery_id=d.delivery_id WHERE d.delivery_bn='".$delivery_bn."' ");
        
        $orderId = array();
        $orderId[] = 0;
        foreach ($delivery_list as $delivery){
            $orderId[] = $delivery['order_id'];
        }
        return $orderId;
    }
}

?>