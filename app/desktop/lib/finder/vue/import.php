<?php

class desktop_finder_vue_import
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private desktop_finder_vue_context $context;

    public function __construct(desktop_finder_vue_context $context)
    {
        $this->context = $context;
    }

    public function handle(array $request): array
    {
        $do = $request['do'] ?? '';
        if ($do === 'getTemplate') {
            $this->downloadTemplate($request);
            return ['rsp' => 'succ'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'rsp' => 'fail',
                'message' => '仅支持 POST 请求',
            ];
        }

        $file = $_FILES['file'] ?? $_FILES['import_file'] ?? null;
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'rsp' => 'fail',
                'message' => '请选择要导入的 CSV 文件',
            ];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $model = $this->context->getModel();
        $objectName = $this->context->getObjectName();
        [$appId, $mdl] = $this->parseObjectName($objectName);

        $oIo = kernel::servicelist('desktop_io');
        $oImportType = null;
        foreach ($oIo as $aIo) {
            if ($aIo->io_type_name == $ext) {
                $oImportType = $aIo;
                break;
            }
        }
        unset($oIo);

        if (!$oImportType) {
            return [
                'rsp' => 'fail',
                'message' => '导入格式不正确',
            ];
        }

        $this->doLog($mdl, array_merge($_GET, $_POST));
        $oImportType->prepared_import($appId, $mdl);

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return [
                'rsp' => 'fail',
                'message' => '无法读取上传文件',
            ];
        }

        $contents = [];
        $oImportType->fgethandle($handle, $contents);
        fclose($handle);

        $newFileName = $appId . '_' . $mdl . '_' . $file['name'] . '-' . time();
        base_kvstore::instance($appId . '_' . $mdl)->store(
            $newFileName,
            base64_encode(serialize($contents))
        );
        base_kvstore::instance($appId . '_' . $mdl)->store($newFileName . '_sdf', serialize([]));
        base_kvstore::instance($appId . '_' . $mdl)->store($newFileName . '_error', serialize([]));

        $oo = kernel::single('desktop_finder_builder_to_run_import');
        $msgList = $oo->turn_to_sdf($newFileName, [
            'file_type' => $ext,
            'app' => $appId,
            'mdl' => $mdl,
            'file_name' => $newFileName,
        ]);

        if ($msgList['error']) {
            $echoMsg = app::get('desktop')->_('导入失败 错误如下：') . "\n" . implode("\n", $msgList['error']);
            return [
                'rsp' => 'failure',
                'message' => $echoMsg,
                'data' => [
                    'file_name' => $newFileName,
                ],
            ];
        }

        $oImportType->model = $model;
        $oImportType->finish_import();

        $warnings = [];
        if (!empty($msgList['warning'])) {
            $warnings = $msgList['warning'];
        }

        return [
            'rsp' => 'success',
            'message' => app::get('desktop')->_('上传成功，已加入队列，系统会自动跑完队列'),
            'data' => [
                'file_name' => $newFileName,
                'warnings' => $warnings,
            ],
        ];
    }

    private function parseObjectName(string $objectName): array
    {
        $parts = explode('_mdl_', $objectName, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Invalid object name: ' . $objectName);
        }
        return [$parts[0], $parts[1]];
    }

    private function doLog($modelName, $params)
    {
        [$appId] = $this->parseObjectName($this->context->getObjectName());
        $model = app::get($appId)->model($modelName);
        $logParams = [
            'app' => trim($_GET['app'] ?? ''),
            'ctl' => trim($_GET['ctl'] ?? ''),
            'act' => trim($_GET['act'] ?? ''),
            'modelFullName' => get_class($model),
            'type' => 'import',
        ];
        unset($params['app'], $params['ctl'], $params['act'], $params['_finder'], $params['finder_id']);

        if ($model->isDoLog()) {
            $type = $model->getLogType($logParams);
            if (app::get('ome')->is_installed()) {
                ome_operation_log::insert($type, $logParams);
            }
        }
    }

    private function downloadTemplate(array $request): void
    {
        $model = $this->context->getModel();
        $objectName = $this->context->getObjectName();

        $filter = $request['filter_type'] ?? preg_replace('/^.*_mdl_/', '', $objectName);
        $ioType = $request['io_type'] ?? 'csv';

        if ($ioType === 'xlsx') {
            $permission = $this->context->getImportTemplatePermission();
            if ($permission !== '' && !kernel::single('desktop_user')->has_permission($permission)) {
                echo '无权限下载导入模板';
                exit;
            }

            if (method_exists($model, 'downloadImportTemplate') && $model->downloadImportTemplate($ioType)) {
                exit;
            }

            echo '无法获取模板定义';
            exit;
        }

        if (!method_exists($model, 'io_title')) {
            echo '无法获取模板定义';
            exit;
        }

        $model->io_title($filter, $ioType);
        $oSchema = $model->oSchema;
        if (!isset($oSchema[$ioType][$filter])) {
            echo '无法获取模板定义';
            exit;
        }

        $headers = array_keys($oSchema[$ioType][$filter]);

        if (empty($headers)) {
            echo '无法获取模板定义';
            exit;
        }

        $filename = $filter . '_import_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo "\xEF\xBB\xBF";

        $fp = fopen('php://output', 'w');
        fputcsv($fp, $headers, ',', '"', '\\');
        fclose($fp);
        exit;
    }
}
