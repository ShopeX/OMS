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

class console_wms_delivery
{
    /**
     * retryWmsDelivery
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function retryWmsDelivery($id)
    {
        $deliveryData = $this->formatDeliveryData($id);
        
        $node_id = $deliveryData['node_id'];
        $method = 'wms.delivery.status_update';
        
        //response
        $result = kernel::single('erpapi_router_response')->set_node_id($node_id)->set_api_name($method)->dispatch($deliveryData);
        
        return $result;
    }
    
    /**
     * formatDeliveryData
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function formatDeliveryData($id)
    {
        $wdMdl = app::get('console')->model('wms_delivery');

        $deliverys = $wdMdl->dump(array('id'=>$id),'*');

        if(empty($deliverys)) return true;
        
        $wdMdl->update(array('delivery_status'=>'2'),array('id'=>$id));
        
        $inData = [];
        $inData['delivery_bn'] = $deliverys['delivery_bn'];
        $inData['out_delivery_bn'] = $deliverys['out_delivery_bn'];
        $inData['status'] = $deliverys['wms_status'];
        $inData['node_id'] = $deliverys['wms_node_id'];
        $inData['logistics'] = $deliverys['logistics'];
        $inData['logi_no'] = $deliverys['logi_no'];
        $inData['weight'] = $deliverys['weight'];
        $inData['remark'] = $deliverys['remark'];
        $inData['volume'] = $deliverys['volume'];
        $inData['extend_props'] = $deliverys['extend_props'];
        $inData['operate_time'] = $deliverys['operate_time'];
        $inData['warehouse'] = $deliverys['warehouse'];
        $inData['oid'] = $deliverys['oid'];
        $inData['other_list_0'] = $deliverys['other_list_0'];
        $inData['packages'] = $deliverys['packages'];
        
        return $inData;
    }
    
    /**
     * [定时] 自动重试未处理完成的 WMS 发货回传
     * 条件：delivery_status=2，wms_status=DELIVERY，1 小时内且至少 5 分钟前
     * @todo：解决WMS回传发货单完成结果时，OMS系统发货完成，并发导致库存冻结死锁；
     *
     * @return bool
     */
    public function retryPendingWmsDelivery()
    {
        $wdMdl = app::get('console')->model('wms_delivery');
        $dlyMdl = app::get('ome')->model('delivery');
        
        $start_date = date('Y-m-d H:i:s', time() - 3600); // 一小时内
        $end_date = date('Y-m-d H:i:s', time() - 300);     // 至少 5 分钟前
        
        // 获取[已匹配未处理完成]的WMS发货单
        $filter = array(
            'delivery_status' => array('2'),
            'at_time|betweenstr' => array($start_date, $end_date),
            'wms_status' => 'DELIVERY',
        );
        $rows = $wdMdl->getList('id,delivery_id,delivery_bn,delivery_status', $filter, 0, 100, 'at_time ASC');
        if (empty($rows)) {
            return false;
        }
        
        // exec
        foreach ($rows as $row)
        {
            try {
                $deliveryBn = $row['delivery_bn'];
                
                // check只处理[已匹配未处理完成]状态的单据
                if(!in_array($row['delivery_status'], array('2'))){
                    continue;
                }
                
                // 获取OMS发货单信息
                $deliveryInfo = null;
                if (!empty($row['delivery_id'])) {
                    $deliveryInfo = $dlyMdl->dump(array('delivery_id' => $row['delivery_id']), 'delivery_id,delivery_bn,status,process');
                }
                
                if (empty($deliveryInfo) && $deliveryBn) {
                    $deliveryInfo = $dlyMdl->dump(array('delivery_bn' => $deliveryBn), 'delivery_id,delivery_bn,status,process');
                }
                
                // check
                if(empty($deliveryInfo)){
                    continue;
                }
                
                // OMS 已发货成功：只纠正 wms_delivery 状态，避免重复 consign
                if ($deliveryInfo['status'] === 'succ' && (string) $deliveryInfo['process'] === 'true') {
                    $update = array('delivery_status' => '3');
                    
                    if (empty($row['delivery_id']) && !empty($deliveryInfo['delivery_id'])) {
                        $update['delivery_id'] = $deliveryInfo['delivery_id'];
                    }
                    
                    $wdMdl->update($update, array('id' => $row['id'], 'delivery_status' => array('1', '2')));
                    
                    // log
                    app::get('ome')->model('operation_log')->write_log('wms_delivery@console', $row['id'], 'OMS发货单已发货，自动标记处理完成!');
                    
                    continue;
                }elseif(in_array($deliveryInfo['status'], array('cancel', 'back', 'return_back'))){
                    $updateSdf = array('delivery_status' => '3');
                    $wdMdl->update($updateSdf, array('id' => $row['id'], 'delivery_status' => array('1', '2')));
                    
                    // log
                    app::get('ome')->model('operation_log')->write_log('wms_delivery@console', $row['id'], 'OMS发货单已经取消，自动标记处理完成!');
                    
                    continue;
                }elseif(in_array($deliveryInfo['status'], array('ready', 'progress', 'failed'))){
                    // 重试
                    $this->retryWmsDeliveryForAutoRetry($row['id']);
                    
                    // 降低同分同秒多单重试再次撞死锁的概率
                    usleep(200000);
                }
            } catch (Throwable $e) {
                try {
                    // log
                    $error_log = '自动重试异常：' . mb_substr($e->getMessage(), 0, 200);
                    app::get('ome')->model('operation_log')->write_log('wms_delivery@console', $row['id'], $error_log);
                } catch (Throwable $ignore) {
                    // ignore
                }
            }
        }
        
        return true;
    }
    
    /**
     * [定时自动重试] 重试单条 WMS 发货回传
     *
     * @param int $id sdb_console_wms_delivery.id
     * @return array|false
     */
    public function retryWmsDeliveryForAutoRetry($id)
    {
        // deliveryData
        $deliveryData = $this->formatDeliveryDataForAutoRetry($id);
        if (empty($deliveryData) || !is_array($deliveryData) || empty($deliveryData['node_id'])) {
            return false;
        }
        
        $opLog = app::get('ome')->model('operation_log');
        
        // node_id
        $node_id = $deliveryData['node_id'];
        
        // method
        $method = 'wms.delivery.status_update';
        
        // response
        $result = kernel::single('erpapi_router_response')->set_node_id($node_id)->set_api_name($method)->dispatch($deliveryData);
        
        // rsp
        $rsp = is_array($result) ? ($result['rsp'] ?? '') : '';
        if ($rsp === 'succ' || $rsp === 'success') {
            // log
            $opLog->write_log('wms_delivery@console', $id, 'task自动重试回传成功');
        } else {
            $log_msg = is_array($result) ? ($result['msg'] ?? json_encode($result, JSON_UNESCAPED_UNICODE)) : (string) $result;
            $log_msg = mb_substr($log_msg, 0, 200);
            
            // log
            $opLog->write_log('wms_delivery@console', $id, 'task自动重试回传失败：' . $log_msg);
        }
        
        return $result;
    }
    
    /**
     * [定时自动重试] 组装回传参数（含明细）
     *
     * @param int $id
     * @return array|false
     */
    public function formatDeliveryDataForAutoRetry($id)
    {
        $wdMdl = app::get('console')->model('wms_delivery');
        
        $deliverys = $wdMdl->dump(array('id' => $id), '*');
        if (empty($deliverys)) {
            return false;
        }
        
        // 锁定
        $wdMdl->update(array('delivery_status' => '2'), array('id' => $id));
        
        // data
        $inData = [];
        $inData['delivery_bn'] = $deliverys['delivery_bn'];
        $inData['out_delivery_bn'] = $deliverys['out_delivery_bn'];
        $inData['status'] = $deliverys['wms_status'];
        $inData['node_id'] = $deliverys['wms_node_id'];
        $inData['logistics'] = $deliverys['logistics'];
        $inData['logi_no'] = $deliverys['logi_no'];
        $inData['weight'] = $deliverys['weight'];
        $inData['remark'] = $deliverys['remark'];
        $inData['volume'] = $deliverys['volume'];
        $inData['extend_props'] = $deliverys['extend_props'];
        $inData['operate_time'] = $deliverys['operate_time'];
        $inData['warehouse'] = $deliverys['warehouse'];
        $inData['oid'] = $deliverys['oid'];
        $inData['other_list_0'] = $deliverys['other_list_0'];
        $inData['packages'] = $deliverys['packages'];
        
        // 明细：落库时 sn_list/batch 已 json_encode，回传接口要求 item 为 JSON 字符串
        $wdiMdl = app::get('console')->model('wms_delivery_items');
        $itemRows = $wdiMdl->getList('product_bn,num,sn_list,batch,wms_item_id', array('wd_id' => $id));
        if ($itemRows) {
            $items = [];
            foreach ($itemRows as $row) {
                $item = [
                    'product_bn' => $row['product_bn'],
                    'num'        => $row['num'],
                    'item_id'    => $row['wms_item_id'],
                ];
                
                if ($row['sn_list'] !== '' && $row['sn_list'] !== null) {
                    $sn = json_decode($row['sn_list'], true);
                    $item['sn_list'] = ($sn !== null) ? $sn : $row['sn_list'];
                }
                
                if ($row['batch'] !== '' && $row['batch'] !== null) {
                    $batch = json_decode($row['batch'], true);
                    $item['batch'] = ($batch !== null) ? $batch : $row['batch'];
                }
                
                // items
                $items[] = $item;
            }
            
            $inData['item'] = json_encode($items, JSON_UNESCAPED_UNICODE);
        }
        
        return $inData;
    }

    /**
     * [定时清理] 删除六个月前已处理完成的 WMS 发货回传主表及明细
     *
     * @return bool
     */
    public function cleanWmsDelivery()
    {
        $db = kernel::database();
        $sixMonthsAgo = date('Y-m-d H:i:s', strtotime('-6 months'));
        $batchSize = 500;
        $maxBatches = 50;

        for ($i = 0; $i < $maxBatches; $i++) {
            $sql = "SELECT `id` FROM `sdb_console_wms_delivery` "
                . " WHERE `delivery_status` = '3' "
                . " AND `at_time` < " . $db->quote($sixMonthsAgo)
                . " ORDER BY `id` ASC"
                . " LIMIT " . $batchSize;
            $rows = $db->select($sql);
            if (empty($rows)) {
                break;
            }
            
            $ids = array();
            foreach ($rows as $row) {
                $ids[] = (int) $row['id'];
            }
            
            $ids = array_filter($ids);
            if (empty($ids)) {
                break;
            }
            
            // ids
            $idList = implode(',', $ids);
            
            // delete
            $db->exec("DELETE FROM `sdb_console_wms_delivery_items` WHERE `wd_id` IN (" . $idList . ")");
            $db->exec("DELETE FROM `sdb_console_wms_delivery` WHERE `id` IN (" . $idList . ")");
            
            // check
            if (count($ids) < $batchSize) {
                break;
            }
        }
        
        return true;
    }

    /**
     * [定时清理] 删除六个月前已处理完成的 WMS 退货回传主表及明细
     *
     * @return bool
     */
    public function cleanWmsReship()
    {
        $db = kernel::database();
        
        $sixMonthsAgo = date('Y-m-d H:i:s', strtotime('-6 months'));
        $batchSize = 500;
        $maxBatches = 50;
        
        for ($i = 0; $i < $maxBatches; $i++) {
            $sql = "SELECT `id` FROM `sdb_console_wms_reship` "
                . " WHERE `reship_status` = '3' "
                . " AND `at_time` < " . $db->quote($sixMonthsAgo)
                . " ORDER BY `id` ASC"
                . " LIMIT " . $batchSize;
            $rows = $db->select($sql);
            if (empty($rows)) {
                break;
            }
            
            $ids = array();
            foreach ($rows as $row) {
                $ids[] = (int) $row['id'];
            }
            
            $ids = array_filter($ids);
            if (empty($ids)) {
                break;
            }
            
            // ids
            $idList = implode(',', $ids);
            
            // delete
            $db->exec("DELETE FROM `sdb_console_wms_reship_items` WHERE `wr_id` IN (" . $idList . ")");
            $db->exec("DELETE FROM `sdb_console_wms_reship` WHERE `id` IN (" . $idList . ")");
            
            // check
            if (count($ids) < $batchSize) {
                break;
            }
        }
        
        return true;
    }
}
