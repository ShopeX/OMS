<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class mcpapi_api_log_writer implements mcpapi_api_log_writer_contract
{
    private object $apiLogModel;
    private bool $supportsTopLevelMethod;
    private mcpapi_api_log_redactor $redactor;

    public function __construct(?object $apiLogModel = null, ?mcpapi_api_log_redactor $redactor = null)
    {
        $this->apiLogModel = $apiLogModel ?? app::get('ome')->model('api_log');
        $this->supportsTopLevelMethod = $this->detectTopLevelMethodSupport($this->apiLogModel);
        $this->redactor = $redactor ?? new mcpapi_api_log_redactor();
    }

    public function write(array $context): string
    {
        $logId = method_exists($this->apiLogModel, 'gen_id') ? (string) $this->apiLogModel->gen_id() : md5(uniqid('', true));
        $requestPayload = [
            'request_id' => (string) ($context['request_id'] ?? ''),
            'client_id' => (string) ($context['client_id'] ?? ''),
            'subject_id' => (string) ($context['subject_id'] ?? ''),
            'controller' => (string) ($context['controller'] ?? ''),
            'action' => (string) ($context['action'] ?? ''),
            'method' => (string) ($context['method'] ?? ''),
            'path' => (string) ($context['path'] ?? ''),
            'request' => $this->redactor->redact(is_array($context['request'] ?? null) ? $context['request'] : []),
        ];
        $requestJson = $this->encodeBounded($requestPayload);
        $responseJson = $this->encodeBounded($this->redactor->redact($context['response_body'] ?? []));

        $data = [
            'log_id' => $logId,
            'task_name' => $this->taskName((string) ($context['path'] ?? ''), (string) ($context['action'] ?? '')),
            'status' => (string) ($context['status'] ?? 'success'),
            'worker' => 'mcpapi_router:dispatch',
            'params' => $requestJson,
            'response' => $responseJson,
            'msg' => $this->redactor->redactString((string) ($context['message'] ?? '')),
            'log_type' => 'mcpapi',
            'api_type' => 'response',
            'memo' => 'MCPAPI request log',
            'original_bn' => (string) ($context['original_bn'] ?? ''),
            'createtime' => time(),
            'last_modified' => time(),
            'msg_id' => (string) ($context['request_id'] ?? ''),
            'spendtime' => round((float) ($context['duration'] ?? 0), 5),
            'url' => (string) ($context['path'] ?? ''),
        ];

        if ($this->supportsTopLevelMethod) {
            $data['method'] = (string) ($context['method'] ?? '');
        }

        if (method_exists($this->apiLogModel, 'insert')) {
            $this->apiLogModel->insert($data);
        }

        return $logId;
    }

    private function taskName(string $path, string $action): string
    {
        return $path !== '' ? 'MCPAPI ' . $path : 'MCPAPI ' . $action;
    }

    private function encodeBounded(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '';
        }

        return strlen($json) < 256000 ? $json : '';
    }

    private function detectTopLevelMethodSupport(object $apiLogModel): bool
    {
        if ($apiLogModel instanceof ome_mdl_api_log_elk) {
            return true;
        }

        if ($apiLogModel instanceof ome_mdl_api_log && method_exists($apiLogModel, 'getClass')) {
            $backend = $apiLogModel->getClass();
            return $backend instanceof ome_mdl_api_log_elk;
        }

        return false;
    }
}
