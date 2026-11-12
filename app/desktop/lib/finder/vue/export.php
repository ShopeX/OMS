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

class desktop_finder_vue_export
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

    public function handle(array $request): void
    {
        $model = $this->context->getModel();
        $objectName = $this->context->getObjectName();
        $ioType = strtolower($request['export_type'] ?? 'csv');
        if (!in_array($ioType, ['csv', 'xls'], true)) {
            $ioType = 'csv';
        }

        $filter = $this->transformFilters($request);

        $ids = $request['ids'] ?? '';
        if (!empty($ids)) {
            $idArr = is_array($ids) ? $ids : explode(',', $ids);
            $schema = $model->get_schema();
            $idColumn = $schema['idColumn'] ?? 'id';
            $filter[$idColumn] = $idArr;
        }

        $exportFields = $request['export_fields'] ?? '';
        if (!empty($exportFields)) {
            $filter['export_fields'] = is_string($exportFields) ? $exportFields : implode(',', (array)$exportFields);
            $filter['_export_mb'] = 1;
        }
        $filter['_export_type'] = $ioType;

        $this->doLog($model, $filter);

        $oIo = kernel::servicelist('desktop_io');
        $oImportType = null;
        foreach ($oIo as $aIo) {
            if ($aIo->io_type_name == $ioType) {
                $oImportType = $aIo;
                break;
            }
        }
        unset($oIo);

        if (!$oImportType) {
            echo '不支持的导出格式';
            exit;
        }

        $oImportType->init($model);

        $oName = substr($objectName, strpos($objectName, '_mdl_') + 5);
        $data = ['name' => $oName];
        if (method_exists($model, 'exportName')) {
            $model->exportName($data, []);
        }

        if (method_exists($model, 'fgetlist_csv')) {
            $oImportType->export_header($data, $model, $ioType);
            $offset = 0;
            while ($listFlag = $model->fgetlist_csv($data, $filter, $offset, $ioType)) {
                $offset++;
                $oImportType->export($data, $offset, $model, $ioType);
                $data = [];
            }
            if ($data) {
                $oImportType->export($data, $offset, $model, $ioType);
            }
        } else {
            $oImportType->export_header($data, $model, $ioType);
            $offset = 0;
            while ($listFlag = $oImportType->fgetlist($data, $model, $filter, $offset, $ioType)) {
                $offset++;
                $oImportType->export($data, $offset, $model, $ioType);
                $data = [];
            }
        }

        exit;
    }

    private function doLog($model, $params)
    {
        $logParams = [
            'app' => trim($_GET['app'] ?? ''),
            'ctl' => trim($_GET['ctl'] ?? ''),
            'act' => trim($_GET['act'] ?? ''),
            'modelFullName' => get_class($model),
            'type' => 'export',
            'params' => $params,
        ];

        if ($model->isDoLog()) {
            $type = $model->getLogType($logParams);
            if (app::get('ome')->is_installed()) {
                ome_operation_log::insert($type, $logParams);
            }
        }
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
