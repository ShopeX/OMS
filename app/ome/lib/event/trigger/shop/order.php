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
 * 订单请求
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class ome_event_trigger_shop_order
{
    const URGENT_LABEL_CODE = 'SOMS_URGENT_SHIP';

    /**
     * 淘宝全链路
     *
     * @return void
     * @author 
     **/
    public function order_message_produce($orderIds,$status=array())
    {
        // if (!defined('MESSAGE_PRODUCE') || !MESSAGE_PRODUCE)  return;

        if (is_string($status) || is_numeric($status)) {
            $status = [$status];
        }

        $orderModel = app::get('ome')->model('orders');
        $orderList = $orderModel->getList('order_id,order_bn,source,shop_id,last_modified',array('order_id'=>$orderIds));

        if (!$orderList) return ;

        $orderObjectModel = app::get('ome')->model('order_objects');
        $rows = $orderObjectModel->getList('order_id,oid,obj_id,quantity',array('order_id'=>$orderIds));
        $orderObjectList = array();

        $orderItemModel = app::get('ome')->model('order_items');
        $items = $orderItemModel->getList('order_id,obj_id,shop_goods_id,shop_product_id,nums', ['order_id'=>$orderIds]);

        foreach ($rows as $row) {
            $row['items'] = [];
            foreach ($items as $ik => $iv) {
                if ($iv['order_id'] == $row['order_id'] && $iv['obj_id'] == $row['obj_id']) {
                    $row['items'][] = $iv;
                    unset($items[$ik]);
                }
            }
            $orderObjectList[$row['order_id']][] = $row;
        }

        $sdfs = [];
        foreach ($orderList as $order) {
            if ($order['source'] != 'matrix') continue;

            foreach ($status as $s) {
                if (!isset($sdfs[$order['shop_id']])) {
                    $sdfs[$order['shop_id']] = [];
                }
                $sdfs[$order['shop_id']][] = array(
                    'order_bn'               => $order['order_bn'],
                    'last_modified'          => $order['last_modified'],
                    'message_produce_status' => $s,
                    'remark'                 => '',
                    'order_objects'          => $orderObjectList[$order['order_id']],
                );
                
            }
        }
        if ($sdfs) {
            foreach ($sdfs as $shop_id => $sdf) {
                kernel::single('erpapi_router_request')->set('shop',$shop_id)->order_message_produce($sdf,false);
            }
        }
        //淘宝加急发货状态上传
        $this->urgent_logistics_report($orderIds);
    }

    /**
     * 遍历加急订单并向淘宝/天猫补发当前物流状态回告。
     *
     * @param array|int $orderIds
     * @param string|array $scene
     * @return void
     */
    public function urgent_logistics_report($orderIds, $scene = '')
    {
        $orderIds = (array)$orderIds;
        if (is_array($scene)) {
            if (!empty($scene['order_ids']) && is_array($scene['order_ids'])) {
                $orderIds = $scene['order_ids'];
            }
            $scene = $scene['scene'];
        }
        if (!$orderIds) {
            return;
        }

        $orderModel = app::get('ome')->model('orders');
        $labelLib   = kernel::single('ome_bill_label');
        $urgentLib  = kernel::single('ome_order_urgent');
        $orderList  = $orderModel->getList('order_id,order_bn,shop_id,shop_type,source,createway', ['order_id' => $orderIds]);

        foreach ((array)$orderList as $order) {
            if ($order['source'] != 'matrix' || $order['createway'] != 'matrix') {
                continue;
            }
            if (!in_array($order['shop_type'], ['taobao', 'tmall'])) {
                continue;
            }
            if (!$labelLib->existLabel($order['order_id'], self::URGENT_LABEL_CODE)) {
                continue;
            }

            $statusPayload = $urgentLib->buildUrgentOrderStatus($order['order_id']);
            if (empty($statusPayload['orderStatus'])) {
                continue;
            }
            if (in_array($statusPayload['orderStatus'], ['SHIPPED', 'CANCELLED']) && $this->isUrgentTerminalReported($order['order_id'], $statusPayload['orderStatus'])) {
                continue;
            }

            $sdf = [
                'tid'                => $order['order_bn'],
                'orderStatus'        => $statusPayload['orderStatus'],
                'scene'              => $scene,
                'status_update_time' => $statusPayload['status_update_time'] ?: '',
                'order_details'      => $statusPayload['order_details'] ?: [],
                'tracking_number'    => $statusPayload['tracking_number'] ?: '',
                'exception_reason'   => $statusPayload['exception_reason'] ?: '',
                'is_split'           => (bool)($statusPayload['is_split'] ?? false),
                'order_id'           => $order['order_id'],
            ];

            $result = kernel::single('erpapi_router_request')->set('shop', $order['shop_id'])->logistics_order_report($sdf, false);
            if (($result['rsp'] == 'succ' || $result['rsp'] == 'success') && in_array($statusPayload['orderStatus'], ['SHIPPED', 'CANCELLED'])) {
                app::get('ome')->model('operation_log')->write_log('order_modify@ome', $order['order_id'], '淘宝加急发货状态回告终态：' . $statusPayload['orderStatus']);
            }
        }
    }

    /**
     * 通过操作日志判断终态回告是否已经成功落库，避免重复回告。
     *
     * @param int $orderId
     * @param string $status
     * @return bool
     */
    protected function isUrgentTerminalReported($orderId, $status)
    {
        $pattern = '淘宝加急发货状态回告终态：' . $status;
        $sql     = "SELECT log_id FROM sdb_ome_operation_log WHERE obj_id=" . intval($orderId) . " AND obj_type='order_modify@ome' AND memo LIKE '%" . addslashes($pattern) . "%' LIMIT 1";
        $rows    = kernel::database()->select($sql);
        return !empty($rows);
    }

    public function received($orderId) {
        $order = app::get('ome')->model('orders')->db_dump($orderId, 'shop_id, order_bn');
        if(empty($order)) {
            return;
        }
        $orderExtend = app::get('ome')->model('order_extend')->db_dump($orderId, 'extend_field');
        $sdf = [
            'order'=>$order,
            'order_extend'=>$orderExtend
        ];
        return kernel::single('erpapi_router_request')->set('shop', $order['shop_id'])->order_received($sdf);
    }

    public function reject($order, $address_id, $reverse_type) {
        $return_address = app::get('ome')->model('return_address')->db_dump(['address_id'=>$address_id]);
        $orderExtend = app::get('ome')->model('order_extend')->db_dump($order['order_id'], 'extend_field');
        $dlyOrderIds = app::get('ome')->model('delivery_order')->getList('delivery_id', ['order_id'=>$order['order_id']]);
        $delivery = app::get('ome')->model('delivery')->db_dump(['delivery_id'=>array_column($dlyOrderIds, 'delivery_id'), 'process'=>'true'],
            'delivery_id,delivery_bn,branch_id,logi_name,logi_no,logi_id');
        $branch = app::get('ome')->model('branch')->db_dump(['branch_id'=>$delivery['branch_id'], 'check_permission'=> 'false'], 
            'branch_id,branch_bn');
        $corp = app::get('ome')->model('dly_corp')->db_dump($delivery['logi_id'], 
            'corp_id,type,name');
        $order_objects = app::get('ome')->model('order_objects')->getList(
            'obj_id,order_id,oid,shop_goods_id,quantity', ['order_id'=>$order['order_id'], 'delete'=>'false']);
        $sdf = [
            'order'=>$order,
            'delivery'=>$delivery,
            'branch'=>$branch,
            'reverse_type'=>$reverse_type,
            'corp'=>$corp,
            'order_extend'=>$orderExtend,
            'order_objects'=>$order_objects,
            'return_address'=>$return_address
        ];
        $rs = kernel::single('erpapi_router_request')->set('shop', $order['shop_id'])->order_reject($sdf);
        return [$rs['rsp']=='succ', ['msg'=>$rs['msg']]];
    }

    public function order_serial_sync($orderId) {
        $serialNumber = app::get('ome')->model('product_serial_history')->getList(
            'bill_id,bill_no,serial_number', 
            ['bill_id'=>$orderId, 'bill_type'=>'3', 'sync|noequal'=>'3']);
        if(empty($serialNumber)) {
            return ['rsp'=>'succ'];
        }
        $order = app::get('ome')->model('orders')->db_dump($orderId, 'shop_id');
        return kernel::single('erpapi_router_request')->set('shop',$order['shop_id'])->order_serial_sync($serialNumber);
    }

    #京东子单拆同步
    public function split_oid_sync($orderId) {
        $order = app::get('ome')->model('orders')->db_dump(['order_id'=>$orderId]);
        if($order['process_status'] != 'splited') {
            return [false, ['msg'=>'未拆分完成']];
        }
        $split_oid = app::get('ome')->model('order_platformsplit')->getList('obj_id, split_oid, num', ['order_id'=>$orderId]);
        if(empty($split_oid)) {
            return [false, ['msg'=>'未拆分子单']];
        }
        $order_objects = app::get('ome')->model('order_objects')->getList('*', ['order_id'=>$orderId, 'delete'=>'false']);
        $order_items = app::get('ome')->model('order_items')->getList('obj_id, shop_product_id', ['order_id'=>$orderId]);
        $objSkuId = array_column($order_items, 'shop_product_id', 'obj_id');
        foreach ($order_objects as $key => $value) {
            $value['sku_id'] = $objSkuId[$value['obj_id']];
            $order_objects[$key] = $value;
        }
        $sdf = [
            'order'=>$order,
            'split_oid'=>$split_oid,
            'order_objects'=>$order_objects
        ];
        $return = kernel::single('erpapi_router_request')->set('shop',$order['shop_id'])->order_oid_sync($sdf );
        $updata = [
            'sync' => $return['rsp']
        ];
        app::get('ome')->model('orders')->update($updata, ['order_id'=>$orderId]);
        return [$return['rsp']=='succ', ['msg'=>json_encode($return, JSON_UNESCAPED_UNICODE)]];
    }

    /**
     * 闪购订单确认
     * 调用store.trade.confirm接口确认订单
     * 
     * @param int $order_id 订单ID
     * @return bool
     */
    public function confirmFlashOrder($order_id)
    {
        // 获取订单信息，只查询必要的字段
        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->db_dump($order_id, 'order_bn,createway,shop_id');
        
        // 验证订单存在且createway=matrix
        if (!$order || $order['createway'] != 'matrix') {
            return false;
        }
        
        // 使用erpapi_router_request调用订单确认接口
        kernel::single('erpapi_router_request')
            ->set('shop', $order['shop_id'])
            ->order_confirm($order);
        
        return true;
    }
}
