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

class wap_event_trigger_cloudprint_common
{
   

    protected function getDeliveryOrder($deliveryId)
    {
        $db     = kernel::database();
        $where  = is_array($deliveryId) ? 'd.delivery_id in (' . (implode(',', $deliveryId)) . ')' : 'd.delivery_id = "' . $deliveryId . '"';
        $field  = 'o.order_id, o.order_bn, o.total_amount, o.shop_id, o.createway, d.delivery_id';
        $sql    = 'select ' . $field . ' from sdb_ome_delivery_order as d left join sdb_ome_orders as o using(order_id) where ' . $where;
        $result = $db->select($sql);
        return $result;
    }

    //获取两条订单明细
    protected function getOrderItems($order_id)
    {
        static $orderItems = array();
        if (!$orderItems[$order_id]) {
            $orderItems[$order_id] = app::get('ome')->model('order_items')->getList('*', array('order_id' => $order_id), 0, 100);
        }
        return $orderItems[$order_id];
    }

    //获取两条发货单明细
    /**
     * 获取DeliveryItems
     * @param mixed $delivery_id ID
     * @return mixed 返回结果
     */
    public function getDeliveryItems($delivery_id)
    {
        static $deliveryItems = array();
        if (!$deliveryItems[$delivery_id]) {
            $deliveryItems[$delivery_id] = app::get('ome')->model('delivery_items')->getList('*', array('delivery_id' => $delivery_id), 0, 100);
        }
        return $deliveryItems[$delivery_id];
    }

    /**
     * 获取DirectSdf
     * @param mixed $delivery delivery
     * @return mixed 返回结果
     */
    public function getDirectSdf($delivery)
    {
        $items = app::get('ome')->model('delivery_items')->getList('*', array('delivery_id' => $delivery['delivery_id']));

        
        $delivery_orders = app::get('ome')->model('delivery_order')->getList('*', array('delivery_id' => $delivery['delivery_id']));
        $branch_id = $delivery['branch_id'];
        $wap_deliveryLib = kernel::single('wap_delivery');

        $stores = $wap_deliveryLib->getBranchShopInfo($branch_id);
        $order_id = array_column($delivery_orders, 'order_id');
      
        $store_id = $stores['store_id'];
        $cloudprint = $this->getCloudprint($store_id);
        $orders = app::get('ome')->model('orders')->getList('total_amount,order_bn', array('order_id' => $order_id));
       
        $total_amount = 0;
        foreach ($orders as $v) {
            $total_amount += $v['total_amount'];
        }
        $delivery['order_createtime'] = date('Y-m-d H:i:s',$delivery['order_createtime']);
        $sdf                  = array();
        $sdf['primary_bn']    = $delivery['delivery_bn'];
        $sdf['delivery']      = $delivery;
        $sdf['delivery']['delivery_item'] = $items;
        $sdf['order']        = $orders[0];
        $sdf['stores']          = $stores;
        $sdf['dly_corp']      = $corp;
        $sdf['total_amount']  = $total_amount;
        $sdf['cloudprint']  = $cloudprint;
        return $sdf;
    }

    #设置子单的请求的订单号
    /**
     * 设置ChildRqOrdNo
     * @param mixed $deliveryBn deliveryBn
     * @param mixed $billId ID
     * @return mixed 返回操作结果
     */
    public function setChildRqOrdNo($deliveryBn, $billId){
        $deliveryBn = $deliveryBn."cd".$billId;
        return $deliveryBn;
    }


    /**
     * 获取Cloudprint
     * @param mixed $store_id ID
     * @return mixed 返回结果
     */
    public function getCloudprint($store_id){
        $cloudprintMdl = app::get('logisticsmanager')->model('cloudprint');
        $cloudprint = $cloudprintMdl->dump(array('store_id'=>$store_id),'machine_code,channel_id');
        $channel_id = $cloudprint['channel_id'];
        $channelMdl = app::get('channel')->model('channel');
        $channel = $channelMdl->dump(array('channel_id'=>$channel_id),'app_key,secret_key');
        $channel['machine_code'] = $cloudprint['machine_code'];
        return $channel;
    }

    /**
     * 门店是否已配置云打印（电子面单打印）
     */
    public function isCloudprintConfigured($store_id)
    {
        if (empty($store_id) || !app::get('logisticsmanager')->is_installed()) {
            return false;
        }

        $cloudprintMdl = app::get('logisticsmanager')->model('cloudprint');
        $cloudprint    = $cloudprintMdl->dump(
            array('store_id' => $store_id),
            'machine_code,channel_id,disabled'
        );
        if (empty($cloudprint) || empty($cloudprint['channel_id']) || $cloudprint['disabled'] == 'true') {
            return false;
        }

        $channel = app::get('channel')->model('channel')->dump(
            array('channel_id' => $cloudprint['channel_id']),
            'app_key,secret_key'
        );

        return !empty($channel['app_key']) && !empty($channel['secret_key']);
    }
}
