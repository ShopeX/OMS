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

class desktop_finder_vue_async_export
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private $context;

    public function __construct($context)
    {
        $this->context = $context;
    }

    public function handle(array $request): array
    {
        $model = $this->context->getModel();
        $objectName = $this->context->getObjectName();
        $parts = explode('_', $objectName);

        // 验证白名单
        $whitelist = ome_export_whitelist::allowed_lists();
        if (!isset($whitelist[$objectName])) {
            return ['success' => false, 'message' => '该模块不支持后台导出'];
        }

        // Transform filters (same as desktop_finder_vue_export)
        $filter = $this->transformFilters($request);

        // Handle selected IDs
        $ids = $request['ids'] ?? '';
        if (!empty($ids)) {
            $idArr = is_array($ids) ? $ids : explode(',', $ids);
            $idArr = array_map('intval', $idArr);
            $primaryKey = $whitelist[$objectName]['primary_key'] ?? 'id';
            $filter[$primaryKey] = $idArr;
        }

        // Handle export fields - save as filter_data for queue
        $exportFields = $request['export_fields'] ?? '';
        $exportType = strtolower($request['export_type'] ?? 'csv');
        $needDetail = (int) ($request['need_detail'] ?? ($filter['need_detail'] ?? 1)) > 1 ? 2 : 1;
        if (!empty($exportFields)) {
            $filter['_export_mb'] = 1;
            $filter['export_fields'] = is_string($exportFields) ? $exportFields : implode(',', (array)$exportFields);
            $filter['need_detail'] = $needDetail;
        }
        $filter['_io_type'] = $exportType;

        // Count records for queue type selection
        $count = 0;
        if (method_exists($model, 'fcount_csv')) {
            $count = $model->fcount_csv($filter);
        } else {
            $count = $model->count($filter);
        }

        $queueType = ($count <= 1000) ? 'quick' : 'normal';

        // Create task record
        $taskName = $model->export_name . '导出-' . date('Y-m-d');
        $opId = kernel::single('desktop_user')->get_id();

        $taskData = [
            'app' => $parts[0] ?? 'sales',
            'model' => substr($objectName, strlen(($parts[0] ?? '') . '_mdl_')),
            'task_name' => $taskName,
            'op_id' => $opId,
            'create_time' => time(),
            'file_name' => '',
            'status' => 'sleeping',
            'filter_data' => serialize($filter),
            'total_count' => 0,
            'finish_count' => 0,
            'use_slave_db' => 1,
            'export_ver' => 2,
        ];

        $ietaskModel = app::get('taoexlib')->model('ietask');
        $ietaskModel->save($taskData);
        $taskId = $taskData['task_id'];

        // Push to queue
        $pushParams = [
            'data' => [
                'app' => $taskData['app'],
                'model' => $taskData['model'],
                'task_id' => $taskId,
                'filter_data' => $taskData['filter_data'],
                'op_id' => $taskData['op_id'],
                'queue_type' => $queueType,
                'has_detail' => $needDetail,
                'task_type' => 'exportsplit',
            ],
            'url' => kernel::openapi_url('openapi.autotask', 'service'),
        ];
        kernel::single('taskmgr_interface_connecter')->push($pushParams);

        return [
            'success' => true,
            'task_id' => $taskId,
            'status' => 'queued',
            'total' => $count,
            'queue_type' => $queueType,
        ];
    }

    private function transformFilters(array $request): array
    {
        $filters = $request['filters'] ?? [];
        $result = [];
        foreach ($filters as $key => $value) {
            if (strpos($key, '|') !== false) {
                [$field, $op] = explode('|', $key);
                $result[$field . '@' . $op] = $value;
            } else {
                $result[$key] = $value;
            }
        }
        $baseFilter = $this->context->getBaseFilter();
        if ($baseFilter) {
            $result = array_merge($baseFilter, $result);
        }
        return $result;
    }
}
