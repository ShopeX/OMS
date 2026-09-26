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


class wap_event_receive_delivery extends wap_event_response{

    /**
     * 发货单创建事件
     * @param array $data
     */
    public function create($data){
        //检查发货单数据信息

        //创建发货单
        $res = kernel::single('wap_receipt_delivery')->create($data, $msg);
        if($res){
            $this->recordDispatchCreate($data);
            $this->sendDeliveryCreateNotify($data);
            return $this->send_succ();
        }else{
            return $this->send_error($msg, $msg_code);
        }
    }

    /**
     * 发货单取消事件处理
     * @param array $data
     */
    public function cancel($data){

        $deliveryLib = kernel::single('wap_receipt_delivery');

        if(!isset($data['outer_delivery_bn']) || empty($data['outer_delivery_bn'])){
            return $this->send_error('必要参数丢失', $msg_code, $data);
        }

        //检查发货单是否存在
        if(!$deliveryLib->checkOuterExist($data['outer_delivery_bn'])){
            return $this->send_error('发货单不存在', $msg_code, $data);
        }

        //检查发货单当前状态是否有效，可操作
        if(!$deliveryLib->checkDlyStatusByOuterDlyBn($data['outer_delivery_bn'],wap_receipt_delivery::__CANCEL,$msg)){
            if($msg == '发货单已取消') {
                kernel::single('wap_delivery_serial')->releasePrepareSerialByDelivery(
                    0,
                    $data['outer_delivery_bn']
                );
                return $this->send_succ();
            }
            return $this->send_error($msg, $msg_code, $data);
        }

        //执行发货单取消
        $deliveryLib->cancelDlyByOuterDlyBn($data['outer_delivery_bn']);
        $this->recordDispatchCancel($data['outer_delivery_bn']);
        return $this->send_succ();

    }

    /**
     * 发货单签收事件处理
     * @param array $data
     */
    public function sign($data)
    {
        if (!isset($data['outer_delivery_bn']) || empty($data['outer_delivery_bn'])) {
            return $this->send_error('必要参数丢失', $msg_code, $data);
        }

        $deliveryLib = kernel::single('wap_receipt_delivery');
        if (!$deliveryLib->checkOuterExist($data['outer_delivery_bn'])) {
            return $this->send_error('发货单不存在', $msg_code, $data);
        }

        $err_msg = '';
        if (!$deliveryLib->signDlyLocalByOuterDlyBn($data['outer_delivery_bn'], $err_msg)) {
            return $this->send_error($err_msg ?: '签收失败', $msg_code, $data);
        }

        return $this->send_succ();
    }

    /**
     * 发货单暂停事件处理
     * @param array $data
     */
    public function pause($data){

        $deliveryLib = kernel::single('wap_receipt_delivery');

        if(!isset($data['outer_delivery_bn']) || empty($data['outer_delivery_bn'])){
            return $this->send_error('必要参数丢失', $msg_code, $data);
        }

        //检查发货单是否存在
        if(!$deliveryLib->checkOuterExist($data['outer_delivery_bn'])){
            return $this->send_error('发货单不存在', $msg_code, $data);
        }

        //检查发货单当前状态是否有效，可操作
        if(!$deliveryLib->checkDlyStatusByOuterDlyBn($data['outer_delivery_bn'],wap_receipt_delivery::__PAUSE,$msg)){
            return $this->send_error($msg, $msg_code, $data);
        }

        //执行发货单暂停
        $deliveryLib->pauseDlyByOuterDlyBn($data['outer_delivery_bn']);
        return $this->send_succ();
    }

    /**
     * 发货单恢复事件处理
     * @param array $data
     */
    public function renew($data){

        $deliveryLib = kernel::single('wap_receipt_delivery');

        if(!isset($data['outer_delivery_bn']) || empty($data['outer_delivery_bn'])){
            return $this->send_error('必要参数丢失', $msg_code, $data);
        }

        //检查发货单是否存在
        if(!$deliveryLib->checkOuterExist($data['outer_delivery_bn'])){
            return $this->send_error('发货单不存在', $msg_code, $data);
        }

        //检查发货单当前状态是否有效，可操作
        if(!$deliveryLib->checkDlyStatusByOuterDlyBn($data['outer_delivery_bn'],wap_receipt_delivery::__RENEW,$msg)){
            return $this->send_error($msg, $msg_code, $data);
        }

        //执行发货单暂停
        $deliveryLib->renewDlyByOuterDlyBn($data['outer_delivery_bn']);
        return $this->send_succ();
    }

    /**
     * 门店发货单创建成功预警
     */
    protected function recordDispatchCreate(array $data)
    {
        try {
            $branchId   = intval($data['branch_id'] ?? 0);
            $outerBn    = trim((string) ($data['outer_delivery_bn'] ?? ''));
            $createTime = intval($data['create_time'] ?? 0);

            if (!empty($data['delivery_id'])) {
                $row = app::get('wap')->model('delivery')->dump(
                    array('delivery_id' => $data['delivery_id']),
                    'branch_id,outer_delivery_bn,create_time'
                );
                if (!empty($row)) {
                    $branchId   = intval($row['branch_id']);
                    $outerBn    = trim((string) $row['outer_delivery_bn']);
                    $createTime = intval($row['create_time']);
                }
            } elseif ($branchId <= 0 && $outerBn !== '') {
                $row = app::get('wap')->model('delivery')->dump(
                    array('outer_delivery_bn' => $outerBn),
                    'branch_id,outer_delivery_bn,create_time'
                );
                if (!empty($row)) {
                    $branchId   = intval($row['branch_id']);
                    $createTime = intval($row['create_time']);
                }
            }

            if ($branchId <= 0 || $outerBn === '') {
                return;
            }
            kernel::single('wap_branch_daily_counter')->incrDispatch($branchId, $outerBn, $createTime);
        } catch (Exception $e) {
            // 统计失败不影响发货单创建
        }
    }

    /**
     * OME 撤销门店发货单后回滚派单量
     */
    protected function recordDispatchCancel($outerDeliveryBn)
    {
        try {
            kernel::single('wap_branch_daily_counter')->decrDispatchByOuterBn($outerDeliveryBn);
        } catch (Exception $e) {
            // 统计失败不影响撤销
        }
    }

    /**
     * 门店发货单创建成功预警
     */
    protected function sendDeliveryCreateNotify(array $data)
    {
        if (empty($data['delivery_id'])) {
            return;
        }
        try {
            $deliveryRow = app::get('wap')->model('delivery')->dump(
                array('delivery_id' => $data['delivery_id']),
                'delivery_bn,outer_delivery_bn,branch_id,order_bn,skuNum,itemNum'
            );
            if (empty($deliveryRow)) {
                return;
            }
            $notifyParams = array(
                'delivery_bn'       => $deliveryRow['delivery_bn'],
                'outer_delivery_bn' => $deliveryRow['outer_delivery_bn'],
                'order_bn'          => $deliveryRow['order_bn'],
                'branch_id'         => $deliveryRow['branch_id'],
                'sku_num'           => $deliveryRow['skuNum'],
                'item_num'          => $deliveryRow['itemNum'],
            );
            if (app::get('o2o')->is_installed() && !empty($deliveryRow['branch_id'])) {
                $storeRow = app::get('o2o')->model('store')->dump(
                    array('branch_id' => $deliveryRow['branch_id'], 'status' => 1),
                    'store_id,store_bn,name'
                );
                if ($storeRow) {
                    $notifyParams['store_id']   = $storeRow['store_id'];
                    $notifyParams['store_bn']   = $storeRow['store_bn'];
                    $notifyParams['store_name'] = $storeRow['name'];
                }
            }
            kernel::single('monitor_event_notify')->addNotify('wap_store_delivery_create', $notifyParams, true);
        } catch (Exception $e) {
            // 预警失败不影响发货单创建
        }
    }
}

?>
