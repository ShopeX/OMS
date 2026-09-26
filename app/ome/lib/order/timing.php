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

class ome_order_timing {

    /**
     * confirm
     * @param mixed $arrOrderId ID
     * @return mixed 返回值
     */
    public function confirm($arrOrderId) {
        $holdOrderBns = [];
        $combine_select = app::get('ome')->getConf('ome.combine.select');
        $orders = app::get('ome')->model('orders')->getList('order_id, order_combine_hash, order_combine_idx, pay_status, is_cod, createtime, paytime, shop_id, order_bn,timing_confirm', array('order_id' => $arrOrderId), 0, -1, 'createtime ASC');
        foreach ($orders as $key => $row) {
            if($combine_select== '1') {
                list($row['order_combine_hash'],$row['order_combine_idx']) = array(md5($row['order_bn'].'-'.$row['shop_id']),sprintf("%u",crc32($row['order_bn'].'-'.$row['shop_id'])));
            }
            $idx = sprintf('%s||%s', $row['order_combine_hash'], $row['order_combine_idx']);
            $orderGroup[$idx]['orders'][$key] = $row['order_id'];
            $orderGroup[$idx]['cnt'] += 1;
            
            // 是否定时审单（hold单）
            if($row['timing_confirm'] > 0){
                $temp_order_id = $row['order_id'];
                $holdOrderBns[$temp_order_id] = $row['order_bn'];
            }
        }
        
        // hold单是否自动审核
        if($holdOrderBns){
            if(app::get('ome')->getConf('ome.order.timing.auto') === 'Off'){
                $operationLogMdl = app::get('ome')->model('operation_log');
                
                // log
                $log_msg = '[hold单是否自动审核]配置未开启，不能自动审核。';
                foreach ($holdOrderBns as $temp_order_id => $temp_order_bn)
                {
                    $operationLogMdl->write_log('order_preprocess@ome', $temp_order_id, $log_msg);
                }
            }
        }
        
        //合并订单条数限制
        $orderAuto = new omeauto_auto_combine();
        $orderGroup = $orderAuto->_restrictCombineLimit($orderGroup);

        $params = array();
        $allNum = 0;
        foreach($orderGroup as $k => $val) {
            list($hash, $idx) = explode('||', $k);
            $params[] = array(
                'idx' => $idx,
                'hash' => $hash,
                'orders' => $val['orders'],
            );
            $allNum += $val['cnt'];
        }
        $batchLogModel = app::get('ome')->model('batch_log');
        $op = kernel::single('ome_func')->getDesktopUser();
        $batchLog = array(
            'createtime'   => time(),
            'op_id'        => $op['op_id'],
            'op_name'      => $op['op_name'],
            'batch_number' => $allNum,
            'succ_number'  => '0',
            'fail_number'  => '0',
            'status'       => '0',
            'log_type'     => 'ordertaking',
            'log_text'     => serialize($params),
            'source'       => 'task'
        );
        $batchLogModel->save($batchLog);
        if (defined('SAAS_COMBINE_MQ') && SAAS_COMBINE_MQ == 'true') {
            foreach (array_chunk($params, 5) as $param) {
                $push_params = array(
                    'orderidx'  => json_encode($param),
                    'task_type' => 'ordertaking',
                    'log_id'    => $batchLog['log_id'],
                    'uniqid'    => 'combine_'.$batchLog['log_id'],
                );
                taskmgr_func::multiQueue($GLOBALS['_MQ_COMBINE_CONFIG'], 'TG_COMBINE_EXCHANGE', 'TG_COMBINE_QUEUE','tg.order.combine.*',$push_params);
            }
        } else {
           foreach (array_chunk($params, 5) as $param) {
               $push_params = array(
                   'data' => array(
                       'orderidx'  => json_encode($param),
                       'log_id'    => $batchLog['log_id'],
                       'task_type' => 'ordertaking'
                   ),
                   'url' => kernel::openapi_url('openapi.autotask','service')
               );
               kernel::single('taskmgr_interface_connecter')->push($push_params);
           }

       }
    }
}