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
 * 京东钱包导入分派任务
 * 负责将上传的文件分片并创建处理任务
 *
 * @author AI Assistant
 * @version 1.0
 */
class financebase_autotask_task_type_billJdWalletAssign extends financebase_autotask_task_init
{
    public $oFunc;

    /**
     * __construct
     * @return mixed 返回值
     */

    public function __construct()
    {
        parent::__construct();
        $this->oFunc = kernel::single('financebase_func');
    }

    /**
     * 处理
     * @param mixed $task_info task_info
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */
    public function process($task_info, &$error_msg)
    {
        $this->oFunc->writelog('京东钱包导入分派任务-开始', 'settlement', '任务ID:' . $task_info['queue_id']);

        $remote_url = base64_decode($task_info['queue_data']['remote_url']);
        $file_type = $task_info['queue_data']['file_type'];
        $shop_id = $task_info['queue_data']['shop_id'];
        $import_id = $task_info['queue_data']['import_id'];

        // 下载文件到本地
        $storageLib = kernel::single('taskmgr_interface_storage');
        $local_file = DATA_DIR . '/financebase/tmp_local/' . basename($remote_url);
        $getfile_res = $storageLib->get($remote_url, $local_file);
        
        if (!$getfile_res) {
            $error_msg = serialize('文件下载失败');
            $this->oFunc->writelog('京东钱包导入分派任务-文件下载失败', 'settlement', '任务ID:' . $task_info['queue_id']);
            return false;
        }

        $local_file = is_bool($getfile_res) ? $local_file : $getfile_res;

        try {
            // 调用分片方法
            $oTask = kernel::single('financebase_data_task');
            list($status, $errmsg) = $oTask->_spliteJdWalletData($local_file, $file_type, $shop_id, $task_info);
            
            if (!$status) {
                $error_msg = serialize($errmsg);
                $this->oFunc->writelog('京东钱包导入分派任务-分片失败', 'settlement', '任务ID:' . $task_info['queue_id'] . ', 错误:' . $errmsg);
                return false;
            }

            // 更新导入记录状态
            $mdlImport = app::get('financebase')->model('bill_import_jdwallet');
            $mdlImport->update(array('status' => 'processing'), array('id' => $import_id));

            $this->oFunc->writelog('京东钱包导入分派任务-完成', 'settlement', '任务ID:' . $task_info['queue_id']);

        } catch (Exception $e) {
            $error_msg = serialize('分派任务异常：' . $e->getMessage());
            $this->oFunc->writelog('京东钱包导入分派任务-异常', 'settlement', '任务ID:' . $task_info['queue_id'] . ', 错误:' . $e->getMessage());
            return false;
        } finally {
            // 清理本地文件
            if (file_exists($local_file)) {
                unlink($local_file);
            }
        }

        return true;
    }
}
