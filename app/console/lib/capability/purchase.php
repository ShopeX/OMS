<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class console_capability_purchase
{
    private object $poModel;
    private object $supplierModel;
    private object $branchModel;
    private object $branchOpsModel;
    private object $eoModel;
    private object $logModel;
    private console_purchase_import_file_adapter $importFileAdapter;

    public function __construct(
        ?object $poModel = null,
        ?object $supplierModel = null,
        ?object $branchModel = null,
        ?object $branchOpsModel = null,
        ?object $eoModel = null,
        ?object $logModel = null,
        ?console_purchase_import_file_adapter $importFileAdapter = null
    ) {
        $this->poModel = $poModel ?? app::get('purchase')->model('po');
        $this->supplierModel = $supplierModel ?? app::get('purchase')->model('supplier');
        $this->branchModel = $branchModel ?? app::get('ome')->model('branch');
        $this->branchOpsModel = $branchOpsModel ?? app::get('ome')->model('branch_ops');
        $this->eoModel = $eoModel ?? app::get('purchase')->model('eo');
        $this->logModel = $logModel ?? app::get('ome')->model('operation_log');
        $this->importFileAdapter = $importFileAdapter ?? new console_purchase_import_file_adapter();
    }

    public function list(array $request, ?mcpapi_auth_context $context = null): array
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? $request['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $filter = is_array($request['filters'] ?? null) ? $request['filters'] : [];
        $orderBy = (string) ($request['orderBy'] ?? 'purchase_time');
        $orderType = strtoupper((string) ($request['orderType'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $filter = $this->applyBranchScope($filter, $context);

        $rows = $this->poModel->getList('*', $filter, $offset, $pageSize, $orderBy . ' ' . $orderType);
        $total = method_exists($this->poModel, 'count')
            ? (int) $this->poModel->count($filter)
            : count($rows);

        return [
            'list' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(int $poId, ?mcpapi_auth_context $context = null): ?array
    {
        if ($poId < 1) {
            throw new InvalidArgumentException('缺少采购单ID');
        }

        $scope = $this->applyBranchScope([], $context);
        if (($scope['branch_id'] ?? null) === 'false') {
            return null;
        }

        $po = $this->poModel->dump($poId, '*', ['po_items' => ['*']]);
        if (!is_array($po) || $po === []) {
            return null;
        }
        if (isset($scope['branch_id']) && is_array($scope['branch_id']) && !in_array((int) ($po['branch_id'] ?? 0), $scope['branch_id'], true)) {
            return null;
        }

        $supplier = !empty($po['supplier_id']) ? (array) $this->supplierModel->dump($po['supplier_id'], 'name,bn') : [];
        $branch = !empty($po['branch_id']) ? (array) $this->branchModel->dump($po['branch_id'], 'name,branch_bn') : [];
        $eos = $this->eoModel->getList('eo_id,eo_bn,entry_time', ['po_id' => $poId], 0, -1);
        $logs = method_exists($this->logModel, 'read_log') ? (array) $this->logModel->read_log(['obj_id' => $poId], 0, -1) : [];

        $items = array_map(static function (array $item): array {
            if (isset($item['po_item_id'])) {
                $item['po_item_id'] = (int) $item['po_item_id'];
            }
            if (isset($item['num'])) {
                $item['num'] = (string) $item['num'];
            }
            if (isset($item['price'])) {
                $item['price'] = (string) $item['price'];
            }
            return $item;
        }, (array) ($po['po_items'] ?? []));

        return [
            'basic' => [
                'po_id' => (int) ($po['po_id'] ?? 0),
                'po_bn' => (string) ($po['po_bn'] ?? ''),
                'name' => (string) ($po['name'] ?? ''),
                'supplier_id' => (int) ($po['supplier_id'] ?? 0),
                'supplier_name' => (string) ($supplier['name'] ?? ''),
                'supplier_bn' => (string) ($supplier['bn'] ?? ''),
                'branch_id' => (int) ($po['branch_id'] ?? 0),
                'branch_name' => (string) ($branch['name'] ?? ''),
                'branch_bn' => (string) ($branch['branch_bn'] ?? ''),
                'po_type' => (string) ($po['po_type'] ?? ''),
                'po_status' => (string) ($po['po_status'] ?? ''),
                'check_status' => (string) ($po['check_status'] ?? ''),
                'eo_status' => (string) ($po['eo_status'] ?? ''),
                'purchase_time' => $po['purchase_time'] ?? null,
                'arrive_time' => $po['arrive_time'] ?? null,
                'operator' => (string) ($po['operator'] ?? ''),
                'memo' => is_string($po['memo'] ?? null) ? @unserialize($po['memo']) ?: $po['memo'] : ($po['memo'] ?? []),
            ],
            'items' => $items,
            'eo' => array_map(static function (array $eo): array {
                $eo['eo_id'] = (int) ($eo['eo_id'] ?? 0);
                return $eo;
            }, $eos),
            'oplog' => array_map(static function (array $log): array {
                return [
                    'log_id' => $log['log_id'] ?? '',
                    'operate_time' => isset($log['operate_time']) ? date('Y-m-d H:i:s', (int) $log['operate_time']) : '',
                    'op_name' => $log['op_name'] ?? '',
                    'operation' => $log['operation'] ?? '',
                    'memo' => $log['memo'] ?? '',
                    'ip' => $log['ip'] ?? '',
                ];
            }, $logs),
        ];
    }

    public function importTemplate(): array
    {
        $purchaseHeaders = $this->rawTemplateHeaders('purchase');
        $itemHeaders = $this->rawTemplateHeaders('item');

        return [
            'purchase_headers' => $purchaseHeaders,
            'item_headers' => $itemHeaders,
            'sections' => [
                ['name' => 'purchase', 'headers' => $purchaseHeaders],
                ['name' => 'item', 'headers' => $itemHeaders],
            ],
            'csv_header_sections' => [implode(',', $purchaseHeaders), implode(',', $itemHeaders)],
        ];
    }

    public function import(array $request, ?mcpapi_auth_context $context = null): array
    {
        $file = $request['file'] ?? null;
        $sha256 = strtolower((string) ($request['sha256'] ?? ''));
        $filename = is_array($file) ? (string) ($file['name'] ?? '') : '';
        $tmpName = is_array($file) ? (string) ($file['tmp_name'] ?? '') : '';
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_readable($tmpName)) {
            throw new InvalidArgumentException('采购单导入文件缺失或不可读');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{1,128}\.(?:csv|xlsx)$/i', $filename)) {
            throw new InvalidArgumentException('仅支持安全的 CSV 或 XLSX 文件名');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new InvalidArgumentException('文件 SHA-256 格式无效');
        }
        $bytes = file_get_contents($tmpName);
        if (!is_string($bytes) || $bytes === '') throw new InvalidArgumentException('导入文件为空');
        if (strlen($bytes) > 20 * 1024 * 1024 || !hash_equals($sha256, hash('sha256', $bytes))) {
            throw new InvalidArgumentException('文件大小或 SHA-256 校验失败');
        }
        $poBns = $request['po_bns'] ?? [];
        if (is_string($poBns)) $poBns = json_decode($poBns, true);
        if (!is_array($poBns) || count($poBns) !== 1 || !preg_match('/^I[A-Za-z0-9._\/-]{1,31}$/', (string) $poBns[0])) {
            throw new InvalidArgumentException('采购单号范围无效');
        }
        $branchName = $this->extractImportBranchName($tmpName, $filename);
        $branch = $this->branchModel->dump(['name' => $branchName], 'branch_id');
        if (!is_array($branch) || empty($branch['branch_id'])) throw new InvalidArgumentException('到货仓库不存在');
        $scope = $this->applyBranchScope(['branch_id' => (int) $branch['branch_id']], $context);
        if (($scope['branch_id'] ?? null) === 'false' || (is_array($scope['branch_id'] ?? null) && !in_array((int) $branch['branch_id'], array_map('intval', $scope['branch_id']), true))) {
            throw new InvalidArgumentException('当前 MCP 凭证无权操作该到货仓库');
        }

        $importTmpName = $tmpName;
        $importBytes = $bytes;
        $temporaryImportPath = null;
        if (str_ends_with(strtolower($filename), '.csv')) {
            $importBytes = $this->importFileAdapter->toLegacyCsv($bytes);
            $temporaryImportPath = tempnam(sys_get_temp_dir(), 'mcp-po-');
            if (!is_string($temporaryImportPath) || file_put_contents($temporaryImportPath, $importBytes) === false) {
                if (is_string($temporaryImportPath) && is_file($temporaryImportPath)) {
                    unlink($temporaryImportPath);
                }
                throw new InvalidArgumentException('采购单 CSV 临时文件创建失败');
            }
            $importTmpName = $temporaryImportPath;
        }

        $finderContext = new desktop_finder_vue_context([
            'objectName' => 'purchase_mdl_po',
            'finderAlias' => 'console-purchase-vue',
            'use_buildin_import' => true,
            'use_import_template' => false,
        ]);
        $finderImporter = new desktop_finder_vue_import($finderContext);
        $finderImporter->setVueMode(true);
        $previousFiles = $_FILES ?? [];
        $_FILES['file'] = [
            'name' => $filename,
            'tmp_name' => $importTmpName,
            'size' => strlen($importBytes),
            'error' => UPLOAD_ERR_OK,
            'type' => str_ends_with(strtolower($filename), '.xlsx') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv',
        ];
        try {
            $result = $finderImporter->handle(['do' => 'import']);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('采购单文件解析失败：' . $exception->getMessage(), 0, $exception);
        } finally {
            $_FILES = $previousFiles;
            if (is_string($temporaryImportPath) && is_file($temporaryImportPath)) {
                unlink($temporaryImportPath);
            }
        }
        if (!is_array($result) || ($result['rsp'] ?? '') !== 'success') {
            throw new InvalidArgumentException((string) ($result['message'] ?? '采购单文件校验失败'));
        }
        return ['status' => 'accepted', 'po_bn' => (string) $poBns[0], 'write_requests' => 1, 'legacy_import' => 'desktop_finder_vue_import'];
    }

    private function extractImportBranchName(string $path, string $filename): string
    {
        if (str_ends_with(strtolower($filename), '.xlsx')) {
            if (!class_exists('ZipArchive')) throw new InvalidArgumentException('当前 PHP 环境不支持 XLSX 解析');
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) throw new InvalidArgumentException('XLSX 文件无法打开');
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            if (!is_string($sharedXml) || !is_string($sheetXml)) throw new InvalidArgumentException('XLSX 文件结构不完整');
            preg_match_all('/<si\b[^>]*>([\s\S]*?)<\/si>/i', $sharedXml, $sharedMatches);
            $shared = array_map(static fn(string $value): string => trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8')), $sharedMatches[1] ?? []);
            if (!preg_match('/<c\b[^>]*\br=["\']D2["\'][^>]*\bt=["\']s["\'][^>]*>\s*<v>(\d+)<\/v>\s*<\/c>/i', $sheetXml, $match) || !isset($shared[(int) $match[1]]) ) {
                throw new InvalidArgumentException('XLSX 文件缺少到货仓库');
            }
            return trim($shared[(int) $match[1]]);
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new InvalidArgumentException('无法读取采购单文件');
        $header = fgetcsv($handle);
        $master = fgetcsv($handle);
        fclose($handle);
        if (!is_array($header) || !is_array($master)) throw new InvalidArgumentException('采购单文件缺少主表数据');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $index = array_search('*:到货仓库', $header, true);
        if ($index === false || !isset($master[$index])) throw new InvalidArgumentException('采购单文件缺少到货仓库');
        return trim((string) $master[$index]);
    }

    private function normalizeListRow(array $row): array
    {
        foreach (['po_id', 'supplier_id', 'branch_id'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $row;
    }

    private function rawTemplateHeaders(string $filter): array
    {
        if (method_exists($this->poModel, 'io_title')) {
            $headers = $this->poModel->io_title($filter);
            if (is_array($headers)) {
                return array_is_list($headers) ? array_values($headers) : array_keys($headers);
            }
        }

        return array_values((array) $this->poModel->exportTemplate($filter));
    }

    private function applyBranchScope(array $filter, ?mcpapi_auth_context $context): array
    {
        if ($context === null || $context->isSuper()) {
            return $filter;
        }

        $branchOps = $this->branchOpsModel->getList('branch_id', ['op_id' => $context->omsSubjectId()], 0, -1);
        $branchIds = array_values(array_unique(array_map('intval', array_column($branchOps, 'branch_id'))));
        if ($branchIds !== []) {
            $branches = $this->branchModel->getList('branch_id', ['type' => 'main', 'b_type' => 1, 'branch_id' => $branchIds], 0, -1);
            $branchIds = array_values(array_unique(array_map('intval', array_column($branches, 'branch_id'))));
        }
        if ($branchIds === []) {
            $filter['branch_id'] = 'false';
            return $filter;
        }

        if (isset($filter['branch_id'])) {
            $current = $filter['branch_id'];
            if (is_array($current)) {
                $filtered = array_values(array_intersect(array_map('intval', $current), $branchIds));
                $filter['branch_id'] = $filtered === [] ? 'false' : $filtered;
            } else {
                $filter['branch_id'] = in_array((int) $current, $branchIds, true) ? (int) $current : 'false';
            }
            return $filter;
        }

        $filter['branch_id'] = $branchIds;
        return $filter;
    }
}
