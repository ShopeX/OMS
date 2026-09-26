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
 * 导入控制层
 * Class omecsv_ctl_admin_split_import
 */
class omecsv_ctl_admin_split_import extends desktop_controller
{
    /**
     * treat
     * @return mixed 返回值
     */

    public function treat()
    {
        @ini_set('memory_limit', '512M');
        
        $this->begin();
        
        $billType = kernel::single('omecsv_split_whitelist')->getBillType($_POST['type']);
        if (!$billType) {
            $this->end(false, app::get('base')->_('请先配置白名单！'));
        }
        $queue_name = $billType['name'];
        $class_name = $billType['class'];//数据处理类
        if (!ome_func::class_exists($class_name)) {
            $this->end(false, app::get('base')->_('白名单类配置异常'));
        }
        
        try {
            $obj = kernel::single($class_name);
            if (!$obj instanceof omecsv_data_split_interface) {
                throw new Exception("{$class_name} not instanceof omecsv_data_split_interface");
            }
        } catch (Exception $e) {
            $this->end(false, app::get('base')->_($e->getMessage()));
        }
        
        if ($_FILES['import_file']['name'] && $_FILES['import_file']['error'] == 0) {
            $path_info = pathinfo($_FILES['import_file']['name']);
            $file_type = $path_info['extension'];//文件后缀
            $filename  = $path_info['filename'];//文件名称不带后缀
            $file_type = strtolower($file_type);
            if (in_array($file_type, array('csv', 'xls', 'xlsx'))) {
                
                $oProcess = kernel::single($class_name);
                $postData = array_merge((array)$_POST['queue_data'], (array)$path_info);
                list($checkRs, $errmsg) = $oProcess->checkFile($_FILES['import_file']['tmp_name'], $file_type,(array)$postData);
                if (!$checkRs) {
                    $this->end(false, app::get('base')->_('上传文件数据不对,' . $errmsg));
                }
                
                $ioType      = kernel::single('omecsv_io_split_' . $file_type);
                $listData    = $ioType->getData($_FILES['import_file']['tmp_name'], 0, -1, 0, true);
                $split_count = count($listData);//导入文件总行数
                
                // 判断是否需要走队列处理
                $max_direct_count = 0;
                if (method_exists($oProcess, 'getConfig')) {
                    $config = $oProcess->getConfig();
                    $max_direct_count = isset($config['max_direct_count']) ? $config['max_direct_count'] : 0;
                }
                
                if ($split_count <= $max_direct_count && $max_direct_count > 0) {
                    // 数据量小，直接处理
                    $this->processDirectly($oProcess, $listData, $file_type, $postData);
                } else {
                    // 走队列处理
                    $this->processWithQueue($oProcess, $listData, $file_type, $postData, $filename, $split_count, $queue_name);
                }
                
            } else {
                $this->end(false, app::get('base')->_('不支持此文件'));
            }
            
        } else {
            $this->end(false, "上传失败");
        }
        
    }
    
    /**
     * 直接处理导入数据（数据量小）
     */
    private function processDirectly($oProcess, $listData, $file_type, $postData)
    {
        try {
            $errmsg = [];
            $postData['opInfo'] = kernel::single('ome_func')->getDesktopUser();
            $params = [
                'data' => $listData,
                'title' => $listData[0],
                'file_type' => $file_type,
                'queue_data' => $postData
            ];
            
            // 直接调用处理类的方法
            $result = $oProcess->process(0, $params, $errmsg);
            
            if ($result[0]) {
                $this->endonly(true);
                $success_msg = '导入成功！';
                if ($errmsg) {
                    $success_msg .= ' 部分数据有误：' . implode('；', $errmsg);
                }
                echo "<script>parent.$('iMsg').setText('{$success_msg}');parent.$('import-form').getParent('.dialog').retrieve('instance').close();parent.finderGroup['" . $_GET['finder_id'] . "'].refresh();</script>";
            } else {
                $this->end(false, app::get('base')->_('导入失败：' . implode('；', $errmsg)));
            }
            
        } catch (Exception $e) {
            $this->end(false, app::get('base')->_('导入处理异常：' . $e->getMessage()));
        }
    }
    
    /**
     * 队列处理导入数据（数据量大）
     */
    private function processWithQueue($oProcess, $listData, $file_type, $postData, $filename, $split_count, $queue_name)
    {
        // 临时文件生成后往ftp服务器迁移
        $storageLib = kernel::single('taskmgr_interface_storage');
        $remote_url = '';
        
        $move_res = $storageLib->save($_FILES['import_file']['tmp_name'], md5($filename . time()) . '.' . $file_type, $remote_url);
        
        if (!$move_res) {
            $this->end(false, app::get('base')->_('文件上传失败'));
        }
        
        $mdlQueue = app::get('omecsv')->model('queue');
        $queueData = array();
        $queueData['queue_no'] = omecsv_func::gen_id();
        $queueData['queue_mode'] = 'assign';
        $queueData['create_time'] = time();
        $queueData['queue_name'] = sprintf("%s_导入文件_分派任务", $filename);
        $queueData['queue_data']['file_type'] = $file_type;
        $queueData['queue_data']['type'] = $_POST['type'];
        $queueData['queue_data']['opInfo'] = kernel::single('ome_func')->getDesktopUser();
        $queueData['parent_id'] = '0';
        $queueData['remote_url'] = $remote_url;
        $queueData['queue_data'] = json_encode(array_merge($queueData['queue_data'], (array)$_POST['queue_data']));
        $queueData['bill_type'] = $queue_name;
        $queueData['split_count'] = $split_count;
        $queue_id = $mdlQueue->insert($queueData);
        
        omecsv_func::addTaskQueue(array('queue_id' => $queue_id), 'assign');
        
        $this->endonly(true);
        
        if ($queue_id) {
            echo "<script>parent.$('iMsg').setText('上传成功 已加入队列 系统会自动跑完队列');parent.$('import-form').getParent('.dialog').retrieve('instance').close();parent.finderGroup['" . $_GET['finder_id'] . "'].refresh();</script>";
            flush();
            ob_flush();
            exit;
        }
    }
}