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
        $queueName = $billType['name'];
        $className = $billType['class'];

        if (!ome_func::class_exists($className)) {
            $this->end(false, app::get('base')->_('白名单类配置异常'));
        }

        try {
            $obj = kernel::single($className);
            if (!$obj instanceof omecsv_data_split_interface) {
                throw new Exception("{$className} not instanceof omecsv_data_split_interface");
            }
        } catch (Exception $e) {
            $this->end(false, app::get('base')->_($e->getMessage()));
        }

        if (!($_FILES['import_file']['name'] && $_FILES['import_file']['error'] == 0)) {
            $this->end(false, '上传失败');
        }

        $pathInfo = pathinfo($_FILES['import_file']['name']);
        $fileType = strtolower($pathInfo['extension']);
        $filename = $pathInfo['filename'];

        if (!in_array($fileType, ['csv', 'xls', 'xlsx'])) {
            $this->end(false, app::get('base')->_('不支持此文件'));
        }

        $oProcess = kernel::single($className);
        $postData = array_merge((array)$_POST['queue_data'], $pathInfo);
        list($checkRs, $errmsg) = $oProcess->checkFile($_FILES['import_file']['tmp_name'], $fileType, (array)$postData);
        if (!$checkRs) {
            $this->end(false, app::get('base')->_('上传文件数据不对,' . $errmsg));
        }

        $ioType = kernel::single('omecsv_io_split_' . $fileType);
        $listData = $ioType->getData($_FILES['import_file']['tmp_name'], 0, -1, 0, true);
        $splitCount = count($listData);

        $maxDirectCount = 0;
        if (method_exists($oProcess, 'getConfig')) {
            $config = $oProcess->getConfig();
            $maxDirectCount = $config['max_direct_count'] ?? 0;
        }

        if ($splitCount <= $maxDirectCount && $maxDirectCount > 0) {
            $this->processDirectly($oProcess, $listData, $fileType, $postData);
        } else {
            $this->processWithQueue($oProcess, $listData, $fileType, $postData, $filename, $splitCount, $queueName);
        }
    }

    private function processDirectly($oProcess, $listData, $fileType, $postData)
    {
        $isVue = $this->wantsJson();

        try {
            $errmsg = [];
            $postData['opInfo'] = kernel::single('ome_func')->getDesktopUser();
            $params = [
                'data' => $listData,
                'title' => $listData[0],
                'file_type' => $fileType,
                'queue_data' => $postData
            ];

            $result = $oProcess->process(0, $params, $errmsg);

            if ($result[0]) {
                $this->endonly(true);
                $successMsg = '导入成功！';
                if ($errmsg) {
                    $successMsg .= ' 部分数据有误：' . implode('；', $errmsg);
                }
                $this->outputResponse($isVue, 'success', $successMsg);
            } else {
                $failMsg = '导入失败：' . implode('；', $errmsg);
                $this->outputResponse($isVue, 'fail', $failMsg);
            }
        } catch (Exception $e) {
            $failMsg = '导入处理异常：' . $e->getMessage();
            $this->outputResponse($isVue, 'fail', $failMsg);
        }
    }

    private function processWithQueue($oProcess, $listData, $fileType, $postData, $filename, $splitCount, $queueName)
    {
        $isVue = $this->wantsJson();

        $storageLib = kernel::single('taskmgr_interface_storage');
        $remoteUrl = '';

        $moveRes = $storageLib->save($_FILES['import_file']['tmp_name'], md5($filename . time()) . '.' . $fileType, $remoteUrl);

        if (!$moveRes) {
            $this->outputResponse($isVue, 'fail', '文件上传失败');
            return;
        }

        $mdlQueue = app::get('omecsv')->model('queue');
        $queueData = [
            'queue_no' => omecsv_func::gen_id(),
            'queue_mode' => 'assign',
            'create_time' => time(),
            'queue_name' => sprintf("%s_导入文件_分派任务", $filename),
            'queue_data' => [
                'file_type' => $fileType,
                'type' => $_POST['type'],
                'opInfo' => kernel::single('ome_func')->getDesktopUser(),
            ],
            'parent_id' => '0',
            'remote_url' => $remoteUrl,
            'bill_type' => $queueName,
            'split_count' => $splitCount,
        ];
        $queueData['queue_data'] = json_encode(array_merge($queueData['queue_data'], (array)$_POST['queue_data']));

        $queueId = $mdlQueue->insert($queueData);

        if ($queueId) {
            omecsv_func::addTaskQueue(['queue_id' => $queueId], 'assign');
            $this->endonly(true);
            $this->outputResponse($isVue, 'success', '上传成功，已加入队列，系统会自动处理');
        } else {
            $this->outputResponse($isVue, 'fail', '文件上传失败');
        }
    }

    private function outputResponse(bool $isVue, string $status, string $message): void
    {
        if ($isVue) {
            $this->splash($status === 'success' ? 'success' : 'fail', null, $message);
        } else {
            if ($status === 'success') {
                echo <<<JS
                    <script>parent.\$('iMsg').setText('{$message}');parent.\$('import-form').getParent('.dialog').retrieve('instance').close();parent.finderGroup['{$_GET['finder_id']}'].refresh();</script>
                JS;
            } else {
                echo <<<JS
                    <script>parent.\$('iMsg').setText('{$message}');</script>
                JS;
            }
            flush();
            ob_flush();
            exit;
        }
    }
}
