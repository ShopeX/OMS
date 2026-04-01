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
 * 系统自动审单
 *
 * @Time: 2015-03-09
 * @version 0.1
 */

class ome_autotask_task_combine
{
    function __construct($app)
    {
        $this->app = $app;
        $this->db = kernel::database();
    }
    
    /**
     * 执行批量自动审单
     *
     * @param $params
     * @param $error_msg
     * @return bool
     */
    public function process($params, &$error_msg='') 
    {
        if( (!$params['log_id']) || (!$params['log_text']) ){
            return false;
        }else{
            $params['log_text'] = unserialize($params['log_text']);
        }
        
        set_time_limit(240);
        
        $this->exec_combine($params['log_id'], $params['log_text']);
        
        return  true;
    }
    
    /**
     * 系统自动审单订单
     *
     * @param $log_id
     * @param $logiNoList
     * @param $loginfo
     * @return array|false|int[]|mixed|null
     */
    public function exec_combine($log_id, $logiNoList, $loginfo = array()) 
    {
        if (empty($logiNoList) || !is_array($logiNoList) || !$log_id){
            return false;
        }
        
        $logiNoList = array_filter($logiNoList);
        
        //[批量日志]处理中
        $deliBatchLog = $this->app->model('batch_log');
        $deliBatchLog->update(array('status'=>'2'),array('log_id'=>$log_id));
        
        //数据参数处理
        $params    = array();
        foreach ($logiNoList as $key => $val)
        {
            $order_id   = intval($val);
            
            //[获取所有可操作的订单组]合并识别号_合并索引号[order_combine_hash、order_combine_idx]
            $row = app::get('ome')->model('orders')->db_dump(array('order_id'=>$order_id),'order_id,process_status,shop_type,is_fail,order_combine_hash,order_combine_idx,op_id,group_id');
            
            //只处理未确认订单 && 失败订单不处理
            if(!$row ||
                !in_array($row['process_status'], array('unconfirmed','confirmed','splitting')) ||
                $row['is_fail'] == 'true' || 
                //$row['op_id'] ||
                //$row['group_id'] ||
                !$row['order_combine_hash'] ||
                !$row['order_combine_idx']
                )
            {
                //[批量日志]已处理
                $fail    = 1;
                $deliBatchLog->update(array('status'=>'1','fail_number'=>$fail),array('log_id'=>$log_id));
                
                return array(false, '订单状态不对' . var_export($row, 1));
            }
            
            //是否允许审核
            if($row['is_not_combine'] > 0){
                //[批量日志]已处理
                $fail = 1;
                $deliBatchLog->update(array('status'=>'1','fail_number'=>$fail), array('log_id'=>$log_id));
                
                return array(false, '订单已被标记：不允许审核。' . var_export($row, 1));
            }
            
            $params[]['orders'][]    = $order_id;
        }
        
        //订单预处理
        $preProcessLib = new ome_preprocess_entrance();
        $preProcessLib->process($params, $msg);
        
        //开始自动确认
        $orderAuto = new omeauto_auto_combine('combine');
        $result = $orderAuto->process($params);
        
        //[批量日志]已处理
        $deliBatchLog->update(array('status'=>'1','fail_number'=>$result['fail'],'succ_number' => $result['succ']),array('log_id'=>$log_id));
        
        return $result;
    }

    /**
     * @description
     * @access public
     * @param void
     * @return void
     */
    public function error($log_id,$logi_no,$msg,$failNum)
    {
    
    }
    
    /**
     * @description
     * @access public
     * @param void
     * @return void
     */
    public function success($log_id,$logi_no,$succNum)
    {
    
    }
}