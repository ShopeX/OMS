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
 * 订单催发货
 * 
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 0.1
 */
class erpapi_shop_response_process_delivergoods extends erpapi_shop_response_abstract
{
    /**
     * 根据 service_tags 分流普通催发货与淘宝加急发货处理。
     *
     * @param array $order
     * @return array
     */
    public function urgent($order)
    {
        if ($this->isUrgentShip($order)) {
            return $this->processUrgentShip($order);
        }

        return $this->processLegacyRemindShip($order);
    }

    /**
     * 兼容老的催发货逻辑，仅更新催发货标记和返回平台所需状态。
     *
     * @param array $order
     * @return array
     */
    protected function processLegacyRemindShip($order)
    {
        //更新订单为"催发货"状态
        $orderMdl       = app::get('ome')->model('orders');
        $deliveryMdl    = app::get('ome')->model('delivery');
        $branchMdl      = app::get('ome')->model('branch');

        $order_bool_type = $order['order_bool_type'] | ome_order_bool_type::__URGENT_DELIVERY;

        $result = $orderMdl->update(array('order_bool_type'=>$order_bool_type), array('order_id'=>$order['order_id']));
        if(!$result){
            return array('rsp'=>'fail', 'msg'=>'催发货: 订单更新为催发货,失败!');
        }
        
        $logisticTime = ''; $processCode = 30;
        if (in_array($order['process_status'], ['splitting', 'splited'])){
            $processCode = 50;
            $deliveryList = $deliveryMdl->getDeliversByOrderId($order['order_id']);
            $delivery = array_shift($deliveryList);

            $branch = $branchMdl->dump((int)$delivery['branch_id'], 'latest_delivery_time');

            if ($branch['latest_delivery_time']){
                $logisticTime = strtotime(substr($branch['latest_delivery_time'],0,2).':'.substr($branch['latest_delivery_time'],2,2));

                $logisticTime = $logisticTime>time()?:$logisticTime+86400;

                $logisticTime = date('Y-m-d H:i:s',$logisticTime);
            }

            if ($delivery['logi_no']) {
                $processCode = 10;
            }

            if ($delivery['expre_status'] == 'true'){
                $processCode = 90;
            }

            if ($delivery['verify'] == 'true'){
                $processCode = 70;
            }

            if ($delivery['process'] == 'true'){
                $processCode = 99;
            }
        }

        $seller_name = $order['seller_name'];
        if (!$seller_name) {
            $shop = app::get('ome')->model('shop')->dump($order['shop_id'], 'addon');

            $seller_name = $shop['addon']['nickname'];
        }
        

        $data = [
            'tid'           => $order['order_bn'],
            'logisticTime'  => $logisticTime,
            'sellerNick'    => $seller_name,
            'processCode'   => $processCode,
        ];

        
        //日志
        $memo = '催发货: 买家催发货，时间：'.$order['logistics_time'];
        app::get('ome')->model('operation_log')->write_log('order_modify@ome', $order['order_id'], $memo);
        
        return array('rsp'=>'succ','msg'=>'催发货: 订单更新为催发货成功!','data' => $data);
    }

    /**
     * 处理淘宝加急发货通知：订单/发货单打标、加速审单，并投递状态回告任务。
     *
     * @param array $order
     * @return array
     */
    protected function processUrgentShip($order)
    {
        try {
            if (!$this->isUrgentShip($order)) {
                return $this->fail('PARAM_ERROR', 'service_tags非法', false, $order['order_bn']);
            }

            $labelLib      = kernel::single('ome_bill_label');
            $deliveryMdl   = app::get('ome')->model('delivery');
            $deliveryIds   = $deliveryMdl->getDeliverIdByOrderId($order['order_id']);
            $alreadyMarked = $labelLib->existLabel($order['order_id'], 'SOMS_URGENT_SHIP');

            if (!$alreadyMarked) {
                $err        = '';
                $markResult = $labelLib->markBillLabel($order['order_id'], '', 'SOMS_URGENT_SHIP', 'order', $err);
                if (!$markResult) {
                    return $this->fail('SYSTEM_ERROR', $err ?: '订单加急标签写入失败', true, $order['order_bn']);
                }
            }
            if ($deliveryIds) {
                foreach ((array)$deliveryIds as $deliveryId) {
                    $labelLib->orderToDeliveryLabel($order['order_id'], $deliveryId, 'ome_delivery');
                }
            }

            if (!$alreadyMarked) {
                $memo = '淘宝加急发货: 平台加急发货通知';
                if (!empty($order['notify_time'])) {
                    $memo .= '，通知时间：' . $order['notify_time'];
                }
                app::get('ome')->model('operation_log')->write_log('order_modify@ome', $order['order_id'], $memo);
            }

            $this->accelerateTimingConfirm($order);

            $urgentLib     = kernel::single('ome_order_urgent');
            $statusPayload = $urgentLib->buildUrgentOrderStatus($order['order_id']);
            if (empty($statusPayload['orderStatus'])) {
                return $this->fail('SYSTEM_ERROR', '订单状态计算失败', true, $order['order_bn']);
            }

            $this->enqueueUrgentReport($order['order_id'], 'notify');

            $responseParams = $urgentLib->buildUrgentNotifyResponseParams($order['order_id'], $order['order_bn']);
            if (empty($responseParams['urgent_delivery_notify_response'])) {
                $dtoJson = json_encode(
                    $urgentLib->formatUrgentOrderReportDto($statusPayload, $order['order_bn']),
                    JSON_UNESCAPED_UNICODE
                );
                if ($dtoJson !== false) {
                    $responseParams = ['urgent_delivery_notify_response' => $dtoJson];
                }
            }

            return [
                'rsp'  => 'succ',
                'msg'  => $alreadyMarked ? '淘宝加急发货: 幂等成功' : '淘宝加急发货: 打标成功',
                'data' => $responseParams,
            ];
        } catch (Exception $e) {
            return $this->fail('SYSTEM_ERROR', $e->getMessage(), true, $order['order_bn']);
        }
    }

    /**
     * 判断当前通知是否属于淘宝加急发货。
     *
     * @param array $order
     * @return bool
     */
    protected function isUrgentShip($order)
    {
        if (!is_array($order['service_tags'])) {
            $serviceTags = trim((string)$order['service_tags']);
            return $serviceTags !== '' && $serviceTags === '加急发货';
        }

        return in_array('加急发货', $order['service_tags'], true);
    }

    /**
     * 统一构造加急发货处理失败响应。
     *
     * @param string $errorCode
     * @param string $msg
     * @param bool $retry
     * @param string $tid
     * @return array
     */
    protected function fail($errorCode, $msg, $retry = false, $tid = '')
    {
        return [
            'rsp'        => 'fail',
            'error_code' => $errorCode,
            'msg'        => $msg,
            'retry'      => $retry ? 'true' : 'false',
            'data'       => ['tid' => $tid],
        ];
    }

    /**
     * 对满足自动审单条件的加急订单，直接将定时审单时间改写到当前调度时间。
     *
     * @param array $order
     * @return void
     */
    protected function accelerateTimingConfirm($order)
    {
        $now = time()+30;
        if ($order['pause'] !== 'false' || $order['abnormal'] !== 'false') {
            return;
        }
        if (!in_array($order['process_status'], ['unconfirmed', 'confirmed', 'splitting'])) {
            return;
        }
        if (!in_array($order['ship_status'], ['0', '2'])) {
            return;
        }
        $isAuto = ($order['pay_status'] == '1' || $order['is_cod'] == 'true')
            && $order['status'] == 'active'
            && in_array($order['order_type'], kernel::single('ome_order_func')->get_normal_order_type());
        if (!$isAuto) {
            return;
        }

        app::get('ome')->model('orders')->update(['timing_confirm' => $now], ['order_id' => $order['order_id']]);
        app::get('ome')->model('misc_task')->saveMiscTask([
            'obj_id'    => $order['order_id'],
            'obj_type'  => 'timing_confirm_order',
            'exec_time' => $now,
            'addon'     => json_encode(['urgent_delivery' => true]),
        ]);
        app::get('ome')->model('operation_log')->write_log('order_edit@ome', $order['order_id'], '淘宝加急发货: 提前定时审单时间至当前');
    }

    /**
     * 根据场景补投加急发货状态回告任务。
     *
     * @param int $orderId
     * @param string $scene
     * @return bool
     */
    protected function enqueueUrgentReport($orderId, $scene)
    {
        if (!$orderId) {
            return false;
        }
        $taskTypeMap = [
            'notify'          => 'urgent_logistics_notify',
            'delivery_status' => 'urgent_logistics_dly',
            'consign_sync'    => 'urgent_logistics_csg',
        ];
        return app::get('ome')->model('misc_task')->saveMiscTask([
            'obj_id'    => intval($orderId),
            'obj_type'  => $taskTypeMap[$scene] ?: 'urgent_logistics_notify',
            'exec_time' => time(),
            'addon'     => json_encode(['scene' => $scene], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function promise($sdf)
    {
        if ($sdf['event_type'] == 'latest_delivery_time') {
            // check
            if (empty($sdf['pick_date'])) {
                return ['rsp' => 'fail', 'msg' => '缺少最晚发货时间'];
            }
            
            $latestDeliveryTime = kernel::single('ome_func')->date2time($sdf['pick_date']);
            
            // date2time 空值会原样返回 ''，整型字段不能写入空字符串
            if ($latestDeliveryTime === '' || $latestDeliveryTime === false || $latestDeliveryTime === null) {
                return ['rsp' => 'fail', 'msg' => '最晚发货时间格式错误'];
            }
            
            $orderExtendObj = app::get('ome')->model('order_extend');
            $extendinfo     = [
                'order_id'             => $sdf['order']['order_id'],
                'latest_delivery_time' => intval($latestDeliveryTime),
            ];
            $orderExtendObj->save($extendinfo);
            
            return ['rsp'=>'succ', 'msg'=>'最晚发货时间更新成功'];
        }
        
        return ['rsp'=>'fail', 'msg'=>'缺少类型'];
    }
}
