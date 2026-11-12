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
 * Agent 审计日志查询（legacy admin 与 SPA api 控制器共用）。
 *
 * base_db_model 过滤仅支持等值，时间范围等用白名单拼装的原生 SQL（参数强转防注入）。
 */
final class mcpapi_agent_log_query
{
    private $db;
    private $table;

    public function __construct($db = null)
    {
        $this->db = $db ?? kernel::database();
        $this->table = $this->db->prefix . 'mcpapi_agent_log';
    }

    /**
     * @param array $filters user_id/client_id/tool_name/action_type/success/start/end
     * @return array{total:int, logs:array}
     */
    public function query(array $filters, int $page, int $pageSize): array
    {
        $page = max(1, $page);
        $pageSize = min(100, max(10, $pageSize));
        $offset = ($page - 1) * $pageSize;

        $where = [];
        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'user_id = ' . $userId;
        }
        $clientId = substr((string) ($filters['client_id'] ?? ''), 0, 128);
        if ($clientId !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $clientId) === 1) {
            $where[] = "client_id = '" . $clientId . "'";
        }
        $toolName = substr((string) ($filters['tool_name'] ?? ''), 0, 128);
        if ($toolName !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $toolName) === 1) {
            $where[] = "tool_name = '" . $toolName . "'";
        }
        $actionType = (string) ($filters['action_type'] ?? '');
        if (in_array($actionType, ['query', 'create', 'update', 'delete'], true)) {
            $where[] = "action_type = '" . $actionType . "'";
        }
        $success = (string) ($filters['success'] ?? '');
        if (in_array($success, ['true', 'false'], true)) {
            $where[] = "success = '" . $success . "'";
        }
        $start = (int) ($filters['start'] ?? 0);
        if ($start > 0) {
            $where[] = 'created_at >= ' . $start;
        }
        $end = (int) ($filters['end'] ?? 0);
        if ($end > 0) {
            $where[] = 'created_at <= ' . $end;
        }
        $whereSql = $where === [] ? '1' : implode(' AND ', $where);

        $total = 0;
        $rows = [];
        try {
            $total = (int) ($this->db->select("SELECT COUNT(*) AS c FROM `{$this->table}` WHERE {$whereSql}")[0]['c'] ?? 0);
            if ($total > 0) {
                $rows = (array) $this->db->select("SELECT * FROM `{$this->table}` WHERE {$whereSql} ORDER BY id DESC LIMIT {$pageSize} OFFSET {$offset}");
            }
        } catch (Throwable $e) {
            return ['total' => 0, 'logs' => []];
        }

        $logs = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $logs[] = [
                'id' => (int) $row['id'],
                'request_id' => (string) ($row['request_id'] ?? ''),
                'session_id' => (string) ($row['session_id'] ?? ''),
                'trace_id' => (string) ($row['trace_id'] ?? ''),
                'client_id' => (string) ($row['client_id'] ?? ''),
                'client_mode' => (string) ($row['client_mode'] ?? ''),
                'agent_name' => (string) ($row['agent_name'] ?? ''),
                'agent_version' => (string) ($row['agent_version'] ?? ''),
                'model' => (string) ($row['model'] ?? ''),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'tool_name' => (string) ($row['tool_name'] ?? ''),
                'action_type' => (string) ($row['action_type'] ?? ''),
                'write_flag' => ($row['write_flag'] ?? 'false') === 'true',
                'success' => ($row['success'] ?? 'true') === 'true',
                'error_code' => (string) ($row['error_code'] ?? ''),
                'http_status' => (int) ($row['http_status'] ?? 0),
                'params' => $row['params'] ?? null,
                'spendtime_ms' => (int) ($row['spendtime_ms'] ?? 0),
                'ip' => (string) ($row['ip'] ?? ''),
                'created_at' => (int) ($row['created_at'] ?? 0),
            ];
        }
        return ['total' => $total, 'logs' => $logs];
    }
}
