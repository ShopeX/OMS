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
 * @author ykm 2016-03-23
 * @describe 批量设置备注
 */

class brush_ctl_admin_memo extends desktop_controller {

    public function batch_order() {
        $params = kernel::single('base_component_request')->get_post();
        #不支持全部备注
        if($params['isSelectedAll'] == '_ALL_'){
            echo '暂不支持全部备注!';exit;
        }
        if(empty($params['order_id'])){
            echo '请选择订单!';exit;
        }
        #统计批量支付订单数量
        $this->pagedata['order_id'] = serialize($params['order_id']);
        $this->display('admin/memo/batch_order.html');
    }

    public function do_batch_order() {
        $url = '';
        $arrOrderId = unserialize($_POST['order_id']);
        if(empty($arrOrderId)){
            $this->splash('error', $url, '缺少订单ID');
        }
        $markText = htmlspecialchars($_POST['mark_text']);
        if(empty($markText)) {
            $this->splash('error', $url, '备注信息未填写');
        }
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $newMemo = array('op_name'=>$opInfo['op_name'], 'op_time'=>date('Y-m-d H:i:s',time()), 'op_content'=>$markText);
        $oOrders = app::get('ome')->model('orders');
        $orderData = $oOrders->getList('order_id,order_bn,mark_text', array('order_id'=>$arrOrderId));
        $opLogData = array();
        foreach($orderData as $order) {
            $orderMemo = unserialize($order['mark_text']);
            $upMemo = array();
            foreach($orderMemo as $val) {
                $upMemo[] = $val;
            }
            $upMemo[] = $newMemo;
            $oOrders->update(array('mark_text'=>$upMemo), array('order_id'=>$order['order_id']));
            $opLogData[] = array(
                'obj_id' => $order['order_id'],
                'obj_name' => $order['order_bn'],
                'operation' => 'order_modify@ome',
                'memo' => '批量修改订单备注'
            );
            //订单留言 API
            foreach(kernel::servicelist('service.order') as $object=>$instance){
                if(method_exists($instance, 'update_memo')){
                    $instance->update_memo($order['order_id'], $newMemo);
                }
            }
        }
        $oOperationLog = app::get('ome')->model('operation_log');
        $oOperationLog->batch_write_log2($opLogData);
        $this->splash('success', $url);
    }
}