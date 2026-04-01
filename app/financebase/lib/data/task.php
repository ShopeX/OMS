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
 * 任务类
 *
 * @author 334395174@qq.com
 * @version 0.1
 */
class financebase_data_task
{

    // 分片数据
    /**
     * _spliteData
     * @param mixed $file_name file_name
     * @param mixed $file_type file_type
     * @param mixed $shop_id ID
     * @param mixed $task_name task_name
     * @param mixed $task_info task_info
     * @return mixed 返回值
     */

    public function _spliteData($file_name, $file_type, $shop_id, $task_name, $task_info)
    {
        $oFunc           = kernel::single('financebase_func');
        $ioType          = kernel::single('financebase_io_' . $file_type);
        $mdlQueue        = app::get('financebase')->model('queue');
        $mdlShop         = app::get('ome')->model('shop');
        $task_name_array = explode('_', $task_name);
        $bill_type       = isset($task_name_array[1]) ? $task_name_array[1] : '';

        
        $shopInfo = $mdlShop->getList('name,shop_type', array('shop_id' => $shop_id), 0, 1);
        
        if (!$shopInfo) {
            return false;
        }

        $queue_mode = 'billImport';
        $shop_name  = $shopInfo[0]['name'];
        $shop_type = $task_info['queue_data']['shop_type'] ?? '';
        $financebaseBillLib = 'financebase_data_bill_' . $shop_type;
        if (!ome_func::class_exists($financebaseBillLib)) {
            return [false, '处理类不存在：' . $financebaseBillLib];
        }
        $oProcess   = kernel::single($financebaseBillLib);
        $page_size = $oFunc->getConfig('page_size');

        $file_prefix = md5($task_name . time());
        
        list($checkRs, $errmsg, $title) = $oProcess->checkFile($file_name, $file_type);
        
        if ($checkRs) {
            $columnData = $oProcess->getImportDateColunm($title);

            $ioType->setWriteFile(true);
            $ioType->setFilePrefix(md5(KV_PREFIX . uniqid() . time()));
            $ioType->setPageSize($page_size);
            $ioType->setCellDateCnt($columnData['column'], $columnData['time_diff']);
            
            // 支持指定工作表名称（针对多工作表Excel文件，如天猫优品）
            $sheet = isset($oProcess->sheet_name) ? $oProcess->sheet_name : 0;
            $ioType->getData($file_name, $sheet, -1, 0, true);

            if ($ioType->file_data) {
                $storageLib = kernel::single('taskmgr_interface_storage');
                $i          = 1;
                $c          = count($ioType->file_data);
                
                foreach ($ioType->file_data as $local_file) {
                    $offset   = ($i - 1) * $page_size;
                    $remote_url = '';
                    $move_res = $storageLib->save($local_file, basename($local_file), $remote_url);
                    
                    if ($move_res) {
                        $queueData                             = array();
                        $queueData['queue_mode']               = $queue_mode;
                        $queueData['queue_no']                 = $task_info['queue_no'];
                        $queueData['create_time']              = time();
                        $queueData['queue_name']               = sprintf("%s_导入任务_%d", $task_name, $i);
                        $queueData['queue_data']['shop_id']    = $shop_id;
                        $queueData['queue_data']['shop_name']  = $shop_name;
                        $queueData['queue_data']['bill_type']  = $bill_type;
                        $queueData['queue_data']['bill_date']  = $task_info['queue_data']['bill_date'];
                        $queueData['queue_data']['shop_type']  = $shop_type;
                        $queueData['queue_data']['offset']     = $offset;
                        $queueData['queue_data']['remote_url'] = $remote_url;
                        $queueData['queue_data']['title']      = $title; 
                        $queueData['queue_data']['is_last']    = $i == $c ? true : false;

                        $queue_id = $mdlQueue->insert($queueData);

                        financebase_func::addTaskQueue(array('queue_id' => $queue_id), 'billimport');

                        $oFunc->writelog('支付单对账单-导入队列', 'settlement', "page_" . $i);

                    }
                    $i++;
                    
                    //删除文件
                    unlink($local_file);
                }
            }

            return array(true);
        } else {
            $oFunc->writelog('支付单对账单-导入队列', 'settlement', $errmsg);

            return array(false, $errmsg);
        }

    }


    // 分片数据
    /**
     * _spliteCainiaoData
     * @param mixed $file_name file_name
     * @param mixed $file_type file_type
     * @param mixed $shop_id ID
     * @param mixed $task_info task_info
     * @param mixed $remote_url remote_url
     * @param mixed $mode mode
     * @return mixed 返回值
     */
    public function _spliteCainiaoData($file_name, $file_type, $shop_id, $task_info, $remote_url, $mode)
    {
        $oFunc           = kernel::single('financebase_func');
        $ioType          = kernel::single('financebase_io_' . $file_type);
        $mdlQueue        = app::get('financebase')->model('queue');

        switch ($mode) {
            case 'cainiaoorderimport':
                $queue_mode = 'cainiaoOrderImport';
                $oProcess   = kernel::single('financebase_data_cainiao_order');
                break;
            case 'cainiaoskuimport':
                $queue_mode = 'cainiaoSkuImport';
                $oProcess   = kernel::single('financebase_data_cainiao_sku');
                break;
            case 'cainiaosaleimport':
                $queue_mode = 'cainiaoSaleImport';
                $oProcess   = kernel::single('financebase_data_cainiao_sale');
                break;
            case 'cainiaojztimport':
                //京准通账单导入
                $queue_mode = 'cainiaoJztImport';
                $oProcess = kernel::single('financebase_data_jingzhuntong_jzt');
                break;
            case 'cainiaojdbillimport':
                //京准通账单导入
                $queue_mode = 'cainiaoJdbillImport';
                $oProcess = kernel::single('financebase_data_jingzhuntong_jdbill');
                break;
            default:
                return array(false, 'mode 不存在');
                break;
        }
        $shop_name  = '';
        $page_size = $oFunc->getConfig('page_size');
        
        $file_prefix = md5($mode . time());

        list($checkRs, $errmsg, $title) = $oProcess->checkFile($file_name, $file_type);
        
        if ($checkRs) {
            $columnData = $oProcess->getImportDateColunm($title);
            
            $ioType->setWriteFile(true);
            $ioType->setFilePrefix(md5(KV_PREFIX . uniqid() . time()));
            $ioType->setPageSize($page_size);
            
            //格式化日期字段
            if($columnData['column']){
                $ioType->setCellDateCnt($columnData['column'], $columnData['time_diff']);
            }
            
            $ioType->getData($file_name, 0, -1, 0, true);
            
            if ($ioType->file_data) {
                $storageLib = kernel::single('taskmgr_interface_storage');
                $i          = 1;
                $c          = count($ioType->file_data);
                
                foreach ($ioType->file_data as $local_file) {
                    $offset   = ($i - 1) * $page_size;
                    $remote_url = '';
                    $move_res = $storageLib->save($local_file, basename($local_file), $remote_url);
                    
                    if ($move_res) {
                        $queueData                             = array();
                        $queueData['queue_mode']               = $queue_mode;
                        $queueData['queue_no']                 = $task_info['queue_no'];
                        $queueData['create_time']              = time();
                        $queueData['queue_name']               = sprintf("菜鸟根据单号_导入任务_%d", $i);
//                        $queueData['queue_data']['bill_type']  = $bill_type;
                        $queueData['queue_data']['bill_date']  = $task_info['queue_data']['bill_date'];
                        $queueData['queue_data']['offset']     = $offset;
                        $queueData['queue_data']['remote_url'] = $remote_url;
                        $queueData['queue_data']['title']      = $title;
                        $queueData['queue_data']['is_last']    = $i == $c ? true : false;
                        $queueData['queue_data']['import_id']    = $task_info['queue_data']['import_id'];

                        $queue_id = $mdlQueue->insert($queueData);
                        
                        financebase_func::addTaskQueue(array('queue_id' => $queue_id), $mode);

                        $oFunc->writelog('菜鸟根据单号-导入队列', 'settlement', "page_" . $i);

                    }
                    $i++;
                    
                    //删除文件
                    unlink($local_file);
                }
            }

            return array(true);
        } else {
            $oFunc->writelog('菜鸟根据单号-导入队列', 'settlement', $errmsg);

            return array(false, $errmsg);
        }

    }

    /**
     * 京东钱包多工作表数据分片
     * @param string $file_name 文件名
     * @param string $file_type 文件类型
     * @param int $shop_id 店铺ID
     * @param array $task_info 任务信息
     * @return array
     */
    public function _spliteJdWalletData($file_name, $file_type, $shop_id, $task_info)
    {
        $oFunc = kernel::single('financebase_func');
        $mdlShop = app::get('ome')->model('shop');
        
        $shopInfo = $mdlShop->getList('name,shop_type', array('shop_id' => $shop_id), 0, 1);
        
        if (!$shopInfo) {
            return array(false, '店铺信息不存在');
        }

        $page_size = $oFunc->getConfig('page_size');
        
        // 使用jdwallet处理类进行文件检查
        $oProcess = kernel::single('financebase_data_bill_jdwallet');
        list($checkRs, $errmsg, $title) = $oProcess->checkFile($file_name, $file_type);
        
        if (!$checkRs) {
            $oFunc->writelog('京东钱包文件检查失败', 'settlement', $errmsg);
            return array(false, $errmsg);
        }
        
        try {
            // 1. 读取多工作表Excel文件
            $workbook = $this->readMultiSheetExcel($file_name);
            if (!$workbook) {
                return array(false, '无法读取多工作表Excel文件');
            }

            // 2. 获取两个工作表的标题
            $settlement_title = $workbook['settlement'][0]; // 结算表标题（第一行）
            $fund_title = $workbook['fund'][0]; // 资金表标题（第一行）

            // 3. 分别处理结算表和资金表，使用各自对应的标题
            $settlement_result = $this->splitSettlementData($workbook['settlement'], $shop_id, $task_info, $page_size, $settlement_title, $settlement_has_data);
            $fund_result = $this->splitFundData($workbook['fund'], $shop_id, $task_info, $page_size, $fund_title, $fund_has_data);

            // 检查两个工作表是否都为空
            if (!$settlement_has_data && !$fund_has_data) {
                // 更新导入记录状态为失败
                if (isset($task_info['queue_data']['import_id'])) {
                    $db = kernel::database();
                    $import_id = intval($task_info['queue_data']['import_id']);
                    $sql = "UPDATE sdb_financebase_bill_import_jdwallet 
                            SET status = 'failed', 
                                error_msg = '结算表和资金表都为空，无法导入' 
                            WHERE id = {$import_id}";
                    $db->exec($sql);
                }
                return array(false, '结算表和资金表都为空，无法导入');
            }

            if (!$settlement_result || !$fund_result) {
                // 更新导入记录状态为失败
                if (isset($task_info['queue_data']['import_id'])) {
                    $db = kernel::database();
                    $import_id = intval($task_info['queue_data']['import_id']);
                    $sql = "UPDATE sdb_financebase_bill_import_jdwallet 
                            SET status = 'failed', 
                                error_msg = '工作表数据分片失败' 
                            WHERE id = {$import_id}";
                    $db->exec($sql);
                }
                return array(false, '工作表数据分片失败');
            }

            // 计算总分片数并更新导入记录
            if (isset($task_info['queue_data']['import_id'])) {
                $db = kernel::database();
                $import_id = intval($task_info['queue_data']['import_id']);
                
                // 计算结算表总分片数
                $settlement_data_rows = array_slice($workbook['settlement'], 1); // 去掉标题行
                $settlement_chunks_total = ceil(count($settlement_data_rows) / $page_size);
                
                // 计算资金表总分片数
                $fund_data_rows = array_slice($workbook['fund'], 1); // 去掉标题行
                $fund_chunks_total = ceil(count($fund_data_rows) / $page_size);
                
                // 更新导入记录状态为已分片，并记录总分片数
                $sql = "UPDATE sdb_financebase_bill_import_jdwallet 
                        SET status = 'chunked',
                            settlement_chunks_total = {$settlement_chunks_total},
                            fund_chunks_total = {$fund_chunks_total},
                            settlement_chunks_completed = 0,
                            fund_chunks_completed = 0
                        WHERE id = {$import_id}";
                $db->exec($sql);
            }

            return array(true, '分片处理完成');

        } catch (Exception $e) {
            // 更新导入记录状态为失败
            if (isset($task_info['queue_data']['import_id'])) {
                $db = kernel::database();
                $import_id = intval($task_info['queue_data']['import_id']);
                $error_msg = mysql_real_escape_string('分片处理异常：' . $e->getMessage());
                $sql = "UPDATE sdb_financebase_bill_import_jdwallet 
                        SET status = 'failed', 
                            error_msg = '{$error_msg}' 
                        WHERE id = {$import_id}";
                $db->exec($sql);
            }
            $oFunc->writelog('京东钱包分片处理失败', 'settlement', $e->getMessage());
            return array(false, '分片处理异常：' . $e->getMessage());
        }
    }

    /**
     * 读取多工作表Excel文件
     * @param string $file_name 文件名
     * @return array|false
     */
    private function readMultiSheetExcel($file_name)
    {
        try {
            // 检查Vtiful\Kernel\Excel是否可用
            if (!class_exists('Vtiful\Kernel\Excel')) {
                throw new Exception('Vtiful\Kernel\Excel库未安装');
            }

            $path = pathinfo($file_name);
            $excel = new \Vtiful\Kernel\Excel(['path' => $path['dirname']]);
            
            // 读取结算表
            $settlement_data = $this->readSheetData($excel, $path['basename'], '结算表');
            if (!$settlement_data) {
                throw new Exception('无法读取结算表数据');
            }
            
            // 读取资金表
            $fund_data = $this->readSheetData($excel, $path['basename'], '资金表');
            if (!$fund_data) {
                throw new Exception('无法读取资金表数据');
            }
            
            return array(
                'settlement' => $settlement_data,
                'fund' => $fund_data
            );

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 读取指定工作表数据
     * @param object $excel Excel对象
     * @param string $filename 文件名
     * @param string $sheet_name 工作表名
     * @return array|false
     */
    private function readSheetData($excel, $filename, $sheet_name)
    {
        try {
            // 打开文件并切换到指定工作表
            $excel->openFile($filename);
            
            // 获取所有工作表名称
            $sheet_names = $excel->sheetList();
            if (!in_array($sheet_name, $sheet_names)) {
                return false;
            }
            
            // 切换到指定工作表
            $excel->openSheet($sheet_name);
            
            // 读取标题行
            $title = $excel->nextRow();
            if (!$title) {
                return false;
            }
            
            // 读取所有数据
            $data = array();
            $data[] = $title; // 第一行是标题
            
            while (($row = $excel->nextRow()) !== null) {
                // 过滤空行：检查所有字段是否都为空
                $is_empty = true;
                foreach ($row as $value) {
                    if (!empty(trim($value))) {
                        $is_empty = false;
                        break;
                    }
                }
                
                // 只有非空行才添加到数据中
                if (!$is_empty) {
                    $data[] = $row;
                }
            }
            
            return $data;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 结算表数据分片（常规分片）
     * @param array $sheet_data 工作表数据
     * @param int $shop_id 店铺ID
     * @param array $task_info 任务信息
     * @param int $page_size 分片大小
     * @param array $title 标题信息
     * @param bool &$has_data 返回是否有有效数据
     * @return bool
     */
    private function splitSettlementData($sheet_data, $shop_id, $task_info, $page_size, $title, &$has_data)
    {
        $has_data = false;
        
        // 处理结算表数据
        if (empty($sheet_data) || count($sheet_data) < 2) {
            return true; // 允许结算表为空，但标记为无数据
        }
        
        // 第一行是标题，从第二行开始是数据
        $data_rows = array_slice($sheet_data, 1);
        $headers = $sheet_data[0];

        // 检查是否有有效的数据行（非空行）
        foreach ($data_rows as $row) {
            // 检查行中是否有非空值
            foreach ($row as $value) {
                if (!empty(trim($value))) {
                    $has_data = true;
                    break 2; // 跳出两层循环
                }
            }
        }
        
        // 如果没有有效数据，直接返回true（结算表可能为空）
        if (!$has_data) {
            return true;
        }

        $chunk_count = 0;
        $file_prefix = md5(KV_PREFIX . 'settlement_' . time());
        
        // 计算总分片数
        $total_chunks = ceil(count($data_rows) / $page_size);
        
        // 按固定行数分片
        for ($i = 0; $i < count($data_rows); $i += $page_size) {
            $chunk_rows = array_slice($data_rows, $i, $page_size);
            $chunk = array();
            
            // 将行数据与标题对应
            foreach ($chunk_rows as $row) {
                $row_data = array_combine($headers, $row);
                if ($row_data) {
                    $chunk[] = $row_data;
                }
            }
            
            if (!empty($chunk)) {
                $is_last = ($chunk_count + 1) == $total_chunks;
                $result = $this->createChunkTask($chunk, $chunk_count++, $task_info, 'settlement', $file_prefix, $title, $is_last);
                if (!$result) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 资金表数据分片（常规分片）
     * @param array $sheet_data 工作表数据
     * @param int $shop_id 店铺ID
     * @param array $task_info 任务信息
     * @param int $page_size 分片大小
     * @param array $title 标题信息
     * @param bool &$has_data 返回是否有有效数据
     * @return bool
     */
    private function splitFundData($sheet_data, $shop_id, $task_info, $page_size, $title, &$has_data)
    {        
        $has_data = false;
        
        // 处理资金表数据
        if (empty($sheet_data) || count($sheet_data) < 2) {
            // 如果只有标题行或完全为空，也返回true（资金表可能不导入数据）
            return true;
        }
        
        // 第一行是标题，从第二行开始是数据
        $data_rows = array_slice($sheet_data, 1);
        $headers = $sheet_data[0];

        // 检查是否有有效的数据行（非空行）
        foreach ($data_rows as $row) {
            // 检查行中是否有非空值
            foreach ($row as $value) {
                if (!empty(trim($value))) {
                    $has_data = true;
                    break 2; // 跳出两层循环
                }
            }
        }
        
        // 如果没有有效数据，直接返回true（资金表可能不导入数据）
        if (!$has_data) {
            return true;
        }

        $chunk_count = 0;
        $file_prefix = md5(KV_PREFIX . 'fund_' . time());
        
        // 计算总分片数
        $total_chunks = ceil(count($data_rows) / $page_size);
        
        // 按固定行数分片
        for ($i = 0; $i < count($data_rows); $i += $page_size) {
            $chunk_rows = array_slice($data_rows, $i, $page_size);
            $chunk = array();
            
            // 将行数据与标题对应
            foreach ($chunk_rows as $row) {
                $row_data = array_combine($headers, $row);
                if ($row_data) {
                    $chunk[] = $row_data;
                }
            }
            
            if (!empty($chunk)) {
                $is_last = ($chunk_count + 1) == $total_chunks;
                $result = $this->createChunkTask($chunk, $chunk_count++, $task_info, 'fund', $file_prefix, $title, $is_last);
                if (!$result) {
                    return false;
                }
            }
        }

        return true;
    }


    /**
     * 创建分片任务
     * @param array $chunk_data 分片数据
     * @param int $chunk_count 分片序号
     * @param array $task_info 任务信息
     * @param string $sheet_type 工作表类型
     * @param string $file_prefix 文件前缀
     * @param array $title 标题信息
     * @param bool $is_last 是否为最后一个分片
     * @return bool
     */
    private function createChunkTask($chunk_data, $chunk_count, $task_info, $sheet_type, $file_prefix, $title, $is_last = false)
    {
        $oFunc = kernel::single('financebase_func');
        $mdlQueue = app::get('financebase')->model('queue');
        $storageLib = kernel::single('taskmgr_interface_storage');
        
        try {
            // 创建临时文件
            $temp_file = DATA_DIR . '/financebase/tmp_local/' . $file_prefix . '_' . $chunk_count . '.json';
            file_put_contents($temp_file, json_encode($chunk_data, JSON_UNESCAPED_UNICODE));
            
            // 上传到存储
            $remote_url = '';
            $move_res = $storageLib->save($temp_file, basename($temp_file), $remote_url);
            
            if (!$move_res) {
                unlink($temp_file);
                return false;
            }
            
            // 创建队列任务
            $queueData = array();
            $queueData['queue_mode'] = 'billImport';
            $queueData['queue_no'] = $task_info['queue_no'];
            $queueData['create_time'] = time();
            $queueData['queue_name'] = sprintf("京东钱包%s表_导入任务_%d", $sheet_type === 'settlement' ? '结算' : '资金', $chunk_count);
            $queueData['queue_data']['shop_id'] = $task_info['queue_data']['shop_id'];
            $queueData['queue_data']['shop_name'] = $task_info['queue_data']['shop_name'];
            $queueData['queue_data']['shop_type'] = 'jdwallet';
            $queueData['queue_data']['bill_date'] = $task_info['queue_data']['bill_date'];
            $queueData['queue_data']['sheet_type'] = $sheet_type;
            $queueData['queue_data']['remote_url'] = $remote_url;
            $queueData['queue_data']['import_id'] = $task_info['queue_data']['import_id'];
            $queueData['queue_data']['title'] = $title;
            $queueData['queue_data']['is_last'] = $is_last;
            
            $queue_id = $mdlQueue->insert($queueData);
            
            if ($queue_id) {
                financebase_func::addTaskQueue(array('queue_id' => $queue_id), 'billimport');
                $oFunc->writelog('京东钱包分片任务创建成功', 'settlement', "chunk_" . $chunk_count);
            }
            
            // 删除临时文件
            unlink($temp_file);
            
            return true;
            
        } catch (Exception $e) {
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            $oFunc->writelog('京东钱包分片任务创建失败', 'settlement', $e->getMessage());
            return false;
        }
    }

}
