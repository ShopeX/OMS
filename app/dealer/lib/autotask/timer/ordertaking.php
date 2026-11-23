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
 * 经销商订单审核task定时任务====>暂时不用此Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.19
 */
class dealer_autotask_timer_ordertaking
{
    /**
     * 系统自动获取经销商订单
     *
     * @param $params
     * @param $error_msg
     * @return true
     */
    public function process($params, &$error_msg='') 
    {
        //参考：kernel::single('ome_autotask_timer_sysordertaking')->process($params, $error_msg);
        $cache_key = 'dealer_autotask_ordertaking';
        
        //配置项
        $cfg_ordertaking = 'true';
        if($cfg_ordertaking != 'true'){
            return true;
        }
        
        //操作员
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        
        //执行任务中
        if (cachecore::fetch($cache_key) == 'running'){
            return true;
        }
        
        //cache缓存五分钟
        cachecore::store($cache_key, 'running', 300);
        
        $batchMdl = app::get('dealer')->model('batch_log');
        
        //重置2小时之前未完成的任务
        $batchMdl->update(array('status'=>'1'), array('log_type'=>'ordertaking', 'source'=>'direct', 'status'=>array('0','2'), 'createtime|sthan'=>strtotime('-2 hour')));
        
        //count
        $count = $batchMdl->count(array('log_type'=>'ordertaking', 'source'=>'direct', 'status'=>array('0','2')));
        if ($count > 0) {
            return true;
        }
    
        $orderCnt = 0; $params = array ();
        
//        $orderAuto = new omeauto_auto_combine();
//        $orderGroup = $orderAuto->getBufferGroup();
//
//        if (!$orderGroup) return true;
//
//        foreach ($orderGroup as $key=>$group) {
//            $orderCnt += $group['cnt'];
//
//            list ($hash, $idx) = explode('||', $key);
//
//            $params[] = array('idx' => $idx, 'hash' => $hash, 'orders' => explode(',', $group['orders']));
//        }

        $batchLog = array(
            'createtime' => time(),
            'op_id' => $opInfo['op_id'],
            'op_name' => $opInfo['op_name'],
            'batch_number' => $orderCnt,
            'succ_number' => '0',
            'fail_number' => '0',
            'status' => '0',
            'log_type' => 'ordertaking',
            'log_text' => serialize($params),
        );
        $batchMdl->save($batchLog);
        
        //MQ队列任务
//        if (defined('SAAS_COMBINE_MQ') && SAAS_COMBINE_MQ == 'true') {
//            foreach (array_chunk($params, 5) as $param) {
//                $push_params = array(
//                    'orderidx'  => json_encode($param),
//                    'task_type' => 'ordertaking',
//                    'log_id'    => $batchLog['log_id'],
//                    'uniqid'    => 'combine_'.$batchLog['log_id'],
//                );
//                taskmgr_func::multiQueue($GLOBALS['_MQ_COMBINE_CONFIG'], 'TG_COMBINE_EXCHANGE', 'TG_COMBINE_QUEUE','tg.order.combine.*',$push_params);
//            }
//        } else {
//           foreach (array_chunk($params, 5) as $param) {
//               $push_params = array(
//                   'data' => array(
//                       'orderidx'  => json_encode($param),
//                       'log_id'    => $batchLog['log_id'],
//                       'task_type' => 'ordertaking'
//                   ),
//                   'url' => kernel::openapi_url('openapi.autotask','service')
//               );
//               kernel::single('taskmgr_interface_connecter')->push($push_params);
//           }
//       }
        
        //注销缓存
        cachecore::store($cache_key, '', 1);
        
        return  true;
    }
}
