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

declare(strict_types=1);

/**
 * MCP Agent 调用审计日志 writer：写 sdb_mcpapi_agent_log 专表。
 *
 * - 参数/响应经 redactor 脱敏后入库，超长截断（64KB）
 * - 写失败不阻断业务（由调用方 try/catch 兜底）
 */
final class mcpapi_agent_log_writer
{
    private const MAX_TEXT_BYTES = 65536;

    /** biz_keys 提取白名单：参数 JSON 中的已知业务键 */
    private const BIZ_KEY_WHITELIST = [
        'id', 'order_id', 'order_bn', 'po_id', 'po_bn', 'reship_id', 'aftersale_id',
        'delivery_id', 'shipment_id', 'branch_id', 'shop_id', 'supplier_id',
    ];

    /** action_type 推导规则（工具名后缀/形态 → 业务动作），未命中保守记 query */
    private const ACTION_RULES = [
        'delete' => 'delete', 'remove' => 'delete', 'cancel' => 'delete', 'revoke' => 'delete',
        'create' => 'create', 'add' => 'create', 'import' => 'create', 'register' => 'create',
        'update' => 'update', 'edit' => 'update', 'set' => 'update', 'sort' => 'update', 'rename' => 'update', 'apply' => 'update',
    ];

    private $model;
    private mcpapi_api_log_redactor $redactor;
    private ?int $now;

    public function __construct($model = null, ?mcpapi_api_log_redactor $redactor = null, ?int $now = null)
    {
        $this->model = $model ?? app::get('mcpapi')->model('agent_log');
        $this->redactor = $redactor ?? new mcpapi_api_log_redactor();
        $this->now = $now;
    }

    /**
     * @param array $context 审计上下文（router writeApiLog 组装）
     */
    public function write(array $context): void
    {
        $data = [
            'request_id' => substr((string) ($context['request_id'] ?? ''), 0, 64),
            'session_id' => isset($context['session_id']) ? substr((string) $context['session_id'], 0, 64) : null,
            'trace_id' => isset($context['trace_id']) ? substr((string) $context['trace_id'], 0, 64) : null,
            'client_id' => substr((string) ($context['client_id'] ?? ''), 0, 128),
            'client_mode' => isset($context['client_mode']) ? substr((string) $context['client_mode'], 0, 16) : null,
            'agent_name' => isset($context['agent_name']) ? substr((string) $context['agent_name'], 0, 128) : null,
            'agent_version' => isset($context['agent_version']) ? substr((string) $context['agent_version'], 0, 64) : null,
            'model' => isset($context['model']) ? substr((string) $context['model'], 0, 128) : null,
            'user_id' => (int) ($context['user_id'] ?? 0) > 0 ? (int) $context['user_id'] : null,
            'tenant_id' => isset($context['tenant_id']) ? substr((string) $context['tenant_id'], 0, 32) : null,
            'tool_name' => isset($context['tool_name']) ? substr((string) $context['tool_name'], 0, 128) : null,
            'action_type' => $actionType = $this->deriveActionType((string) ($context['tool_name'] ?? '')),
            'biz_keys' => $this->extractBizKeys($context['params'] ?? null),
            'confirm_used' => !empty($context['confirm_used']) ? 'true' : 'false',
            'path' => substr((string) ($context['path'] ?? ''), 0, 255),
            'method' => substr((string) ($context['method'] ?? ''), 0, 10),
            'write_flag' => in_array($actionType, ['create', 'update', 'delete'], true) ? 'true' : 'false',
            'success' => !empty($context['success']) ? 'true' : 'false',
            'error_code' => isset($context['error_code']) ? substr((string) $context['error_code'], 0, 64) : null,
            'http_status' => (int) ($context['http_status'] ?? 0) > 0 ? (int) $context['http_status'] : null,
            'params' => $this->truncate($this->encode($context['params'] ?? null)),
            'response_digest' => $this->truncate($this->encode($context['response_digest'] ?? null)),
            'spendtime_ms' => (int) ($context['spendtime_ms'] ?? 0),
            'ip' => isset($context['ip']) ? substr((string) $context['ip'], 0, 45) : null,
            'user_agent' => isset($context['user_agent']) ? substr((string) $context['user_agent'], 0, 255) : null,
            'created_at' => $this->now ?? time(),
        ];
        $this->model->insert($data);
    }

    private function deriveActionType(string $toolName): string
    {
        $lower = strtolower($toolName);
        foreach (self::ACTION_RULES as $needle => $action) {
            if (str_contains($lower, $needle)) {
                return $action;
            }
        }
        return 'query';
    }

    /**
     * 从请求参数提取业务对象标识（白名单键，浅递归深度 ≤2，去重逗号拼接）
     */
    private function extractBizKeys(mixed $params): ?string
    {
        if (!is_array($params)) {
            return null;
        }
        $found = [];
        $walk = static function (array $data, int $depth) use (&$walk, &$found): void {
            foreach ($data as $key => $value) {
                if (is_string($key) && in_array($key, self::BIZ_KEY_WHITELIST, true)) {
                    if (is_scalar($value) && (string) $value !== '') {
                        $found[$key . '=' . (string) $value] = true;
                    }
                    continue;
                }
                if (is_array($value) && $depth < 2) {
                    $walk($value, $depth + 1);
                }
            }
        };
        $walk($params, 0);
        $keys = array_keys($found);
        $joined = implode(',', $keys);
        return $joined === '' ? null : substr($joined, 0, 512);
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $json = json_encode(
            is_array($value) ? $this->redactor->redact($value) : $this->redactor->redactString((string) $value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        return $json === false ? null : $json;
    }

    private function truncate(?string $value): ?string
    {
        if ($value === null || strlen($value) <= self::MAX_TEXT_BYTES) {
            return $value;
        }
        return substr($value, 0, self::MAX_TEXT_BYTES) . '...[truncated]';
    }
}
