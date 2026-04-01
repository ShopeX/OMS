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
class ome_batch_log{
    function getStatus($status){
        $status_array = array(
          '0' => '等待中',
          '1' => '已处理',
          '2' => '处理中',

        );
        return $status_array[$status];

    }

    function get_List($log_type,$log_id,$status){
        $db = kernel::database();
        $log_id = implode(',',$log_id);
        $sqlstr = '';
        if($log_type){
            $sqlstr[]=' log_type=\''.$log_type.'\'';
        }
        if($log_id){
            $sqlstr[]=' log_id in ('.$log_id.')';
        }
//        if($status){
//            $sqlstr[]=' status in ('.$status.')';
//        }
        if($sqlstr){
            $sqlstr= 'WHERE '.implode(' AND ',$sqlstr);
        }

        $sql = 'SELECT * FROM sdb_ome_batch_log '.$sqlstr.' ORDER BY log_id DESC';

        return $db->select($sql);
    }

    public function combineAgain($arrOrderId) {
        $allNum = count($arrOrderId);
        $orders = app::get('ome')->model('orders')->getList('order_id,process_status,order_combine_idx,order_combine_hash', array('order_id'=>$arrOrderId));
        $hashOrder = array();
        foreach ($orders as $key => $order) {
            if(!in_array($order['process_status'], array('unconfirmed', 'confirmed', 'splitting'))){
                continue;
            }
            // $hashOrder[$order['order_combine_hash']]['hash'] = $order['order_combine_hash'];
            // $hashOrder[$order['order_combine_hash']]['idx'] = $order['order_combine_idx'];
            // $hashOrder[$order['order_combine_hash']]['orders'][] = $order['order_id'];
            $idx = sprintf('%s||%s', $order['order_combine_hash'], $order['order_combine_idx']);
            $hashOrder[$idx]['orders'][$key] = $order['order_id'];
            $hashOrder[$idx]['cnt'] += 1;
        }
        //合并订单条数限制
        $orderAuto = new omeauto_auto_combine();
        $hashOrder = $orderAuto->_restrictCombineLimit($hashOrder);

        $params = array();
        foreach ($hashOrder as $k=>$v) {
            list ($hash, $idx) = explode('||', $k);
            $params[] = array(
                'idx' => $idx,
                'hash' => $hash,
                'orders' => $v['orders']
            );
        }
        $this->insertLogMq($params, array('all_num'=>$allNum, 'source'=>'combineagain'));
    }

    public function split($arrOrderId) {
        $allNum = count($arrOrderId);
        $orders = app::get('ome')->model('orders')->getList('order_id,process_status,order_combine_idx,order_combine_hash', array('order_id'=>$arrOrderId));
        $hashOrder = array();
        foreach ($orders as $key => $order) {
            if(!in_array($order['process_status'], array('unconfirmed', 'confirmed', 'splitting'))){
                continue;
            }
            // $hashOrder[$order['order_combine_hash']]['hash'] = $order['order_combine_hash'];
            // $hashOrder[$order['order_combine_hash']]['idx'] = $order['order_combine_idx'];
            // $hashOrder[$order['order_combine_hash']]['orders'][] = $order['order_id'];
            $idx = sprintf('%s||%s', $order['order_combine_hash'], $order['order_combine_idx']);
            $hashOrder[$idx]['orders'][$key] = $order['order_id'];
            $hashOrder[$idx]['cnt'] += 1;
        }
        //合并订单条数限制
        $orderAuto = new omeauto_auto_combine();
        $hashOrder = $orderAuto->_restrictCombineLimit($hashOrder);

        $params = array();
        foreach ($hashOrder as $k=>$v) {
            list ($hash, $idx) = explode('||', $k);
            $params[] = array(
                'idx' => $idx,
                'hash' => $hash,
                'orders' => $v['orders']
            );
        }
        $this->insertLogMq($params, array('all_num'=>$allNum, 'source'=>'split'));
    }

    public function insertLogMq($params, $data) {
        if(empty($params)){
            return;
        }
        $allNum = $data['all_num'];
        $taskType = $data['task_type'] ? $data['task_type'] : 'ordertaking';
        $source = $data['source'] ? $data['source'] : 'direct';
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
            'log_type'     => $taskType,
            'log_text'     => serialize($params),
            'source'       => $source
        );
        $batchLogModel->save($batchLog);
        if (defined('SAAS_COMBINE_MQ') && SAAS_COMBINE_MQ == 'true') {

            foreach (array_chunk($params, 5) as $param) {
                $push_params = array(
                    'orderidx'  => json_encode($param),
                    'task_type' => $taskType,
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
