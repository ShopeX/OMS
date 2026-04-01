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
class ome_sync_api_log{

    /**
     * 自动重试同步请求
     * API同步日志每1分钟触发一次：以5分钟为基数将所有正在运行中的请求自动发起重试（最多3次）,超过3次的设置为失败)
     */
    public function auto_retry(){

        $now = time();//当前时间
        $every_time = 60*5;//重试间隔基数（秒数）
        $max_retry = '3';//最多重试次数
        // 需要模拟失败的callback的接口
        $callback_methods = array('store.trade.payment.add','store.trade.refund.add','store.items.quantity.list.update');
        $log_ids = array();

        //--获取所有超过5分钟未响应且重试次数小于3的正在运行中或发起中的同步日志记录
        //--采用分页读取，防止大数据并发量
        $oQueue = app::get('base')->model('queue');
        $sql_counter = " SELECT count(*) ";
        $sql_list = " SELECT `log_id`,`retry`,`params` ";
        $sql_base = " FROM `sdb_ome_api_log`
                 WHERE (`status`='running' OR `status`='sending') AND `api_type`='request'
                 AND `retry`<'$max_retry' AND `last_modified`<({$now}-IF(`retry`<'1','1',`retry`+'1')*{$every_time}) ";
        //$sql = $sql_counter . $sql_base;
        $apiObj = app::get('ome')->model('api_log');
        $filter = array(
            'status' => array('running','sending'),
            'api_type' => 'request',
            'retry|<' => (string)$max_retry,
            'last_modified|<' => (int)($now-$every_time),
        );
        $count = $apiObj->count($filter);
        //$count = kernel::database()->count($sql);
        if ($count){
            $page = 1;
            $limit = 50;
            $pagecount = ceil($count/$limit);
            for ($i=$page;$i<=$pagecount;$i++){
                $lim = ($i-1) * $limit;
                //$sql = $sql_list . $sql_base . " LIMIT " . $lim . "," . $limit;
                //$data = kernel::database()->select($sql);
                $data = $apiObj->getList('*',$filter,$lim,$limit);
                if ($data){
                    $sdfdata['log_id'] = array();
                    foreach ($data as $k=>$v){
                        $log_id = $v['log_id'];
                        $v = $apiObj->dump($log_id);
                        // 将超过10分钟的支付或者退款请求的且在运行中的任务自身模拟失败callback
                        if ($v['retry'] >= ($max_retry-1)){
                            $params = $callback_params = $callback = array();
                            if (!is_array($v['params'])){
                                $params = unserialize($v['params']);
                            }else{
                                $params = $v['params'];
                            }
                            $method = $params[0];
                            if (in_array($method, $callback_methods)){
                                $callback = $params[2];
                                // 模拟result返回结果类
                                $callback_params['log_id'] = $v['log_id'];
                                if(isset($callback[2]['shop_id'])){
                                    $callback_params['shop_id'] = $callback[2]['shop_id'];
                                }
                                $response = array('rsp'=>'fail','res'=>'请求超时');
                                $resultObj = kernel::single('ome_rpc_result', $response);
                                $resultObj->set_callback_params($callback_params);
                                // 调用同步任务的callback
                                if (kernel::single($callback[0])->$callback[1]($resultObj)){
                                    $log_ids[] = $v['log_id'];
                                }
                            }
                        }
                        if (!in_array($v['log_id'], $log_ids)){
                            $sdfdata['log_id'][] = $v['log_id'];
                        }
                    }
                    if ($sdfdata['log_id']){
                        $queueData = array(
                            'queue_title'=>'API同步自动重试'.$i.',共'.count($sdfdata['log_id']).'条)',
                            'start_time'=>$now,
                            'params'=>array(
                                'sdfdata'=>$sdfdata['log_id'],
                                'app' => 'ome',
                                'mdl' => 'api_log'
                            ),
                            'status' => 'hibernate',
                            'worker'=> 'ome_api_log_to_api.retry',
                        );
                        $oQueue->save($queueData);
                    }
                }
            }
        }

        $msg = '请求超时';
        //$where = " (`status`='running' OR `status`='sending') AND `api_type`='request' AND `retry`>='$max_retry' AND `last_modified`<'".($now-$every_time)."' ";
        //$sql = " UPDATE `sdb_ome_api_log` SET `last_modified`='".$now."',`status`='fail',`msg`='".$msg."' WHERE ";

        // 将所有重试次数超过3次且正在运行中或发起中的同步日志设置为失败
        //kernel::database()->exec($sql.$where);
        $updateSdf = array(
            'status' => 'fail',
            'msg' => $msg,
        );
        $updateFilter = array(
            'status' => array('running','sending'),
            'api_type' => 'request',
            'retry|>=' => $max_retry,
            'last_modified|<' => (int)($now-$every_time),
        );
        $apiObj->update($updateSdf,$updateFilter);

        // 将支付或者退款请求的同步任务设置为失败
        //@todo：下面的$sql语句未定义，因为上面已经注释掉$sql变量；
//        if (!empty($log_ids)){
//            $log_ids = implode(',', $log_ids);
//            $where = " `log_id` in ($log_ids) ";
//            kernel::database()->exec($sql.$where);
//        }
        
        return true;
    }

    /**
     * 自动清除同步日志
     * 每天检测将超过(默认15,可配置)天的日志数据清除(暂移到一张备份表当中)
     * 采用分批删除方式，避免大数据量时出现锁等待超时问题
     */
    public function clean(){
        $db = kernel::database();
        
        $time = time();
        $clean_time = app::get('ome')->getConf('ome.api_log.clean_time');
        
        if (empty($clean_time)) $clean_time = 15;
        
        $time_threshold = $time - $clean_time * 24 * 60 * 60;
        $batch_size = 2000; // 每批删除的记录数
        $total_deleted = 0; // 总删除记录数
        $batch_count = 0; // 批次计数
        
        // 分批删除，避免锁等待超时
        while (true) {
            try {
                // 查询一批需要删除的记录 ID（使用主键索引，性能更好）
                $select_sql = "SELECT log_id FROM sdb_ome_api_log WHERE createtime<'{$time_threshold}' LIMIT 0, {$batch_size}";
                $logIds = $db->select($select_sql);
                if (empty($logIds)) {
                    // 如果没有更多数据，退出循环
                    break;
                }
                
                // format
                $logIds = array_column($logIds, 'log_id');
                
                // 使用主键删除，减少锁范围
                $log_ids_str = "'". implode("','", $logIds) . "'";
                $del_sql = "DELETE FROM sdb_ome_api_log WHERE log_id IN ({$log_ids_str})";
                $db->exec($del_sql);
                
//                $deleted_count = count($logIds);
//                $total_deleted += $deleted_count;
//                $batch_count++;

//                // 记录执行日志（仅在删除数量较多时记录，避免日志过多）
//                if ($batch_count % 10 == 0 || $deleted_count < $batch_size) {
//                    error_log("API日志清理：第 {$batch_count} 批，删除 {$deleted_count} 条记录，累计删除 {$total_deleted} 条");
//                }
                
                // 短暂延迟，避免对数据库造成过大压力
                usleep(100000); // 0.1秒
            } catch (Exception $e) {
//                // 记录错误日志，但继续执行下一批
//                error_log("API日志清理第 " . ($batch_count + 1) . " 批失败：" . $e->getMessage());
                
                // 短暂延迟后继续
                usleep(100000); // 0.1秒
                continue;
            }
        }
        
//        // 记录最终结果
//        if ($total_deleted > 0) {
//            error_log("API日志清理完成：共执行 {$batch_count} 批，删除 {$total_deleted} 条过期记录");
//        }

        return true;
    }
}
