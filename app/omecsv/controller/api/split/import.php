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

class omecsv_ctl_api_split_import extends desktop_ctl_api_base
{
    protected $checkCSRF = false;

    public function treat(): void
    {
        @ini_set('memory_limit', '512M');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->fail('仅支持 POST 请求', 405);
            return;
        }

        $type = trim((string)($_POST['type'] ?? ''));
        if ($type === '') {
            $this->fail('缺少导入类型 type', 400);
            return;
        }

        $billType = kernel::single('omecsv_split_whitelist')->getBillType($type);
        if (!$billType) {
            $this->fail('请先配置白名单！', 400, ['type' => $type]);
            return;
        }

        $className = $billType['class'];
        if (!ome_func::class_exists($className)) {
            $this->fail('白名单类配置异常', 500, ['class' => $className]);
            return;
        }

        try {
            $processor = kernel::single($className);
            if (!$processor instanceof omecsv_data_split_interface) {
                throw new Exception("{$className} not instanceof omecsv_data_split_interface");
            }
        } catch (Exception $e) {
            $this->fail($e->getMessage(), 500);
            return;
        }

        $file = $_FILES['import_file'] ?? $_FILES['file'] ?? null;
        if (!($file && $file['name'] && (int)$file['error'] === UPLOAD_ERR_OK)) {
            $this->fail('上传失败', 400);
            return;
        }

        $pathInfo = pathinfo($file['name']);
        $fileType = strtolower((string)($pathInfo['extension'] ?? ''));
        $filename = (string)($pathInfo['filename'] ?? '');
        if (!in_array($fileType, ['csv', 'xls', 'xlsx'], true)) {
            $this->fail('不支持此文件', 400, ['file_type' => $fileType]);
            return;
        }

        $postData = array_merge((array)($_POST['queue_data'] ?? []), $pathInfo);
        $postData['type'] = $type;

        $this->processImport($processor, $file, $fileType, $filename, $postData, (string)$billType['name']);
    }

    private function processImport($processor, array $file, string $fileType, string $filename, array $postData, string $queueName): void
    {
        [$checkRs, $errmsg] = $processor->checkFile($file['tmp_name'], $fileType, $postData);
        if (!$checkRs) {
            $this->fail('上传文件数据不对,' . $errmsg, 400);
            return;
        }

        $ioType = kernel::single('omecsv_io_split_' . $fileType);
        $listData = $ioType->getData($file['tmp_name'], 0, -1, 0, true);
        $splitCount = count($listData);

        $maxDirectCount = 0;
        if (method_exists($processor, 'getConfig')) {
            $config = $processor->getConfig();
            $maxDirectCount = (int)($config['max_direct_count'] ?? 0);
        }

        if ($splitCount <= $maxDirectCount && $maxDirectCount > 0) {
            $this->processDirectly($processor, $listData, $fileType, $postData, $splitCount);
            return;
        }

        $this->processWithQueue($file, $fileType, $postData, $filename, $splitCount, $queueName);
    }

    private function success(string $message, array $data = []): void
    {
        $this->outputJson([
            'rsp' => 'succ',
            'message' => $message,
            'data' => $data,
        ]);
    }

    private function processDirectly($processor, array $listData, string $fileType, array $postData, int $splitCount): void
    {
        try {
            $errmsg = [];
            $params = [
                'data' => $listData,
                'title' => $listData[0] ?? [],
                'file_type' => $fileType,
                'queue_data' => $postData,
                'type' => $postData['type'] ?? '',
                'io' => $postData['io'] ?? null,
            ];
            $result = $processor->process(0, $params, $errmsg);
            if ($result[0]) {
                $message = '导入成功！';
                if ($errmsg) {
                    $message .= ' 部分数据有误：' . implode('；', $errmsg);
                }
                $this->success($message, [
                    'mode' => 'direct',
                    'split_count' => $splitCount,
                ]);
                return;
            }
            $this->fail('导入失败：' . implode('；', $errmsg), 400, ['mode' => 'direct']);
        } catch (Exception $e) {
            $this->fail('导入处理异常：' . $e->getMessage(), 500, ['mode' => 'direct']);
        }
    }

    private function processWithQueue(array $file, string $fileType, array $postData, string $filename, int $splitCount, string $queueName): void
    {
        $storageLib = kernel::single('taskmgr_interface_storage');
        $remoteUrl = '';
        $moveRes = $storageLib->save($file['tmp_name'], md5($filename . time()) . '.' . $fileType, $remoteUrl);
        if (!$moveRes) {
            $this->fail('文件上传失败', 500, ['mode' => 'queue']);
            return;
        }

        $queueData = [
            'queue_no' => omecsv_func::gen_id(),
            'queue_mode' => 'assign',
            'create_time' => time(),
            'queue_name' => sprintf('%s_导入文件_分派任务', $filename),
            'queue_data' => [
                'file_type' => $fileType,
                'type' => $postData['type'] ?? '',
            ],
            'parent_id' => '0',
            'remote_url' => $remoteUrl,
            'bill_type' => $queueName,
            'split_count' => $splitCount,
        ];
        $queueData['queue_data'] = json_encode(array_merge($queueData['queue_data'], $postData), JSON_UNESCAPED_UNICODE);

        $queueId = app::get('omecsv')->model('queue')->insert($queueData);
        if (!$queueId) {
            $this->fail('文件上传失败', 500, ['mode' => 'queue']);
            return;
        }

        omecsv_func::addTaskQueue(['queue_id' => $queueId], 'assign');
        $this->success('上传成功，已加入队列，系统会自动处理', [
            'mode' => 'queue',
            'queue_id' => $queueId,
            'queue_no' => $queueData['queue_no'],
            'split_count' => $splitCount,
        ]);
    }
}