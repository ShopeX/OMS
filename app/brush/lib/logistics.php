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
 * @author ykm 2016-01-07
 * @describe 特殊订单物流相关处理
 */

class brush_logistics {

    /**
     * @param $data require(logi_id, logi_no)
     * @param $delivery require(delivery_id,status,logi_no,ship_area,net_weight)
     * @return array
     */
    public function checkChangeLogistics(&$data, $delivery) {
        if(!in_array($delivery['status'], array('ready','progress'))) {
            return array('result' => false, 'msg' => '该单已经完结不能再变更物流公司');
        }
        $corp = app::get('ome')->model('dly_corp')->dump($data['logi_id']);
        $order_id = app::get('brush')->model('delivery_order')->dump(array('delivery_id' => $delivery['delivery_id']), 'order_id');
        $orderData = app::get('ome')->model('orders')->dump(array('order_id' => $order_id['order_id']), 'ship_status,order_bn,shop_type,self_delivery');
        if($data['logi_no'] == $delivery['logi_no']) {
            if ($corp['type'] == 'DANGDANG') {
                $data['logi_no'] = $orderData['order_bn'];
            } else {
                $data['logi_no'] = null;
            }
        }
        if ($corp['type'] == 'DANGDANG') {
            if ($orderData['shop_type']!='dangdang') {
                return array('result' => false, 'msg' => '非当当店铺订单,不可以选择当当物流!');
            }
        }
        if ( $corp['type'] == 'AMAZON' && $orderData['shop_type']!='amazon' ) {
            return array('result' => false, 'msg' => '此发货单是非亚马逊店铺订单,不可以选择亚马逊物流!');
        }
        $arrArea = explode(':', $delivery['ship_area']);
        $area_id = $arrArea[2];
        $data['delivery_cost_expect'] = app::get('ome')->model('delivery')->getDeliveryFreight($area_id,$data['logi_id'],$delivery['net_weight']);
        if(isset($data['weight'])) {
            $data['delivery_cost_actual'] = app::get('ome')->model('delivery')->getDeliveryFreight($area_id,$data['logi_id'],$data['weight']);
        }
        $data['status'] = 'ready';
        $data['expre_status'] = 'false';
        return array('result' => true);
    }

    public function changeLogistics($data, $deliveryId, $logMsg = '', $extendFilter = array()) {
        $filter = array('delivery_id' => $deliveryId);
        if($extendFilter) {
            $filter = array_merge($filter, $extendFilter);
        }
        $ret = app::get('brush')->model('delivery')->update($data, $filter);
        if(is_bool($ret)) {
            return false;
        }
        $db = kernel::database();
        $orderUp = array();
        $data['logi_id'] && $orderUp[] = 'o.`logi_id`= '.$db->quote($data['logi_id']);
        $data['expre_status'] == 'false' && $orderUp[] = 'o.`print_status` = 0, o.`print_finish` = "false"';
        if(in_array('logi_no', array_keys($data))){
            if(is_null($data['logi_no'])) {
                $orderUp[] = 'o.`logi_no` = null ';
            } else {
                $orderUp[] = 'o.`logi_no`= ' . $db->quote($data['logi_no']);
            }
        }

        if(!empty($orderUp)) {
            $sql = 'UPDATE sdb_ome_orders AS o INNER JOIN sdb_brush_delivery_order AS d ON(o.order_id = d.order_id AND d.delivery_id = ' . $db->quote($deliveryId) . ') SET ' . implode(',', $orderUp);
            if(!$db->exec($sql)) {
                return false;
            }
        }
        $logMsg || $logMsg = '修改发货单详情' . (empty($data['logi_no']) ? '' : '，物流单号：'.$data['logi_no']);
        $opObj = app::get('ome')->model('operation_log');
        $ret = $opObj->write_log('delivery_brush_modify@brush', $deliveryId, $logMsg);
        if(!$ret) {
            return false;
        }
        return true;
    }
}