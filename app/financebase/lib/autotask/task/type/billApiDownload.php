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
 * 账单接口下载任务
 *
 * 统一调用 financebase_rpc_request_bill::process(shop_type)；矩阵直连落 CSV（当前场景：360buy + biz_type=360buy_bills）由 RPC 返回 csv_files。
 *
 * @author 334395174@qq.com
 * @version 0.1
 */
class financebase_autotask_task_type_billApiDownload extends financebase_autotask_task_init
{

    /**
     * 处理
     * @param mixed $task_info task_info
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */

    public function process($task_info,&$error_msg)
    {
        $shop_id   = $task_info['queue_data']['shop_id'];
        $shop_type = $task_info['queue_data']['shop_type'] ? $task_info['queue_data']['shop_type'] : 'alipay';
        $bill_date = $task_info['queue_data']['bill_date'] ? $task_info['queue_data']['bill_date'] : date("Y-m-d",strtotime("-1 day"));
        $node_type = $task_info['queue_data']['node_type'] ? $task_info['queue_data']['node_type'] : '';
        $node_id   = $task_info['queue_data']['node_id'] ? $task_info['queue_data']['node_id'] : '';
        $page      = $task_info['queue_data']['page'] ? $task_info['queue_data']['page'] : '1';

        if(!$node_id || !$node_type)
        {
            $error_msg[] = '没有node_type||node_id'; 
            return false;
        }

        $oRpc       = kernel::single('financebase_rpc_request_bill');
        $storageLib = kernel::single('taskmgr_interface_storage');

        $params = array ();
        $params['bill_date']  = $bill_date;
        $params['node_id']    = $node_id;
        $params['node_type']  = $node_type;
        $params['shop_id']    = $shop_id;
        $params['channel_id'] = $task_info['queue_data']['channel_id'];
        $params['page']      = $page;
        $params['queue_no'] = $task_info['queue_no'];
        $params['download_id'] = $task_info['download_id'];
        $params['queue_id']    = $task_info['queue_id'];
        if (isset($task_info['queue_data']['biz_type'])) {
            $params['biz_type'] = $task_info['queue_data']['biz_type'];
        }
        if (!empty($task_info['queue_data']['startTime'])) {
            $params['startTime'] = $task_info['queue_data']['startTime'];
        }
        if (!empty($task_info['queue_data']['endTime'])) {
            $params['endTime'] = $task_info['queue_data']['endTime'];
        }

        $rpcRes = $oRpc->process($shop_type, $params);
        $this->oFunc->writelog($shop_type.'对账单-获取对账单结果','settlement',$rpcRes);

        if(!$rpcRes || (isset($rpcRes['rsp']) && $rpcRes['rsp'] == 'fail'))
        {
            $error_msg[] = ($rpcRes && $rpcRes['rsp'] == 'fail') ? $rpcRes['err_msg'] : '没有远程地址';
            return false;
        }

        $csv_files = array();

        if ($rpcRes['downloadurl'] === false) {
            $csv_files = (array)$rpcRes['csv_files'];

            if ($rpcRes['rsp'] == 'fail') {
                $this->oFunc->writelog($shop_type.'对账单同步失败','settlement',$rpcRes);
            }

        } else {
            $url = $rpcRes['bill_download_url'];

            $path_dir = sprintf(DATA_DIR.'/financebase/settlement/%s', md5(KV_PREFIX.$shop_id.$params['bill_date']));
            if(!is_dir($path_dir)){
                utils::mkdir_p($path_dir);
            }

            $this->oFunc->writelog($shop_type.'对账单-远程下载链接','settlement',$url);

            // 下载文件
            $write_file = sprintf("%s/%s_%s",$path_dir,$shop_type,$params['bill_date']);

            $this->oFunc->download($url,$write_file);

            $this->oFunc->writelog($shop_type.'对账单-下载成功','settlement',$write_file);
    
            // 检测文件是否为压缩文件
            $is_compressed = $this->oFunc->isCompressedFile($write_file);
    
            if ($is_compressed) {
                $this->oFunc->unZip($write_file,$path_dir,1);
    
                $this->oFunc->writelog($shop_type.'对账单-解压成功','settlement',$path_dir);
    
                // 删除下载文件
                if (file_exists($write_file)) unlink($write_file);
    
                $csv_files = glob($path_dir.'/*.csv');
    
            } else {
                $csv_files[] = $write_file;
                // 如果不是压缩文件，直接处理
                $this->oFunc->writelog($shop_type . '对账单-非压缩文件，直接处理', 'settlement', $write_file);
            }
        }

        // 读取文件
        foreach ((array)$csv_files as $file_name) {
            $path_parts = pathinfo($file_name);
            $ext        = isset($path_parts['extension']) ? strtolower($path_parts['extension']) : 'csv';
            $file_type  = ($ext === 'json') ? 'json' : 'csv';

            $task_name = sprintf($shop_type."对账单（ %s ）",$params['bill_date']);
            $move_res = $storageLib->save($file_name, $path_parts['filename'], $remote_url);
            if($move_res)
            {
                $queueData                             = array();
                $queueData['queue_mode']               = 'billAssign';
                $queueData['queue_no']                 = $task_info['queue_no'];
                $queueData['create_time']              = time();
                $queueData['queue_name']               = sprintf("%s_%s_%s账单分派任务",$task_info['queue_data']['shop_name'],$bill_date,$shop_type);
                $queueData['queue_data']['shop_id']    = $shop_id;
                $queueData['queue_data']['shop_name']  = $task_info['queue_data']['shop_name'];
                $queueData['queue_data']['bill_date']  = $bill_date;
                $queueData['queue_data']['shop_type']  = isset($task_info['queue_data']['import_shop_type'])
                    ? $task_info['queue_data']['import_shop_type']
                    : $shop_type;
                if (!empty($task_info['queue_data']['biz_type'])) {
                    $queueData['queue_data']['biz_type'] = $task_info['queue_data']['biz_type'];
                }
                $queueData['queue_data']['task_name']  = base64_encode(basename($file_name));
                $queueData['queue_data']['file_type']  = $file_type;
                $queueData['queue_data']['remote_url'] = base64_encode($remote_url);

                $queue_id = $this->oQueue->insert($queueData);
                $queue_id and financebase_func::addTaskQueue(array('queue_id'=>$queue_id),'billassign');  
            }

            unlink($file_name);
        }

        return true;
    }
}
