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
 * MCP Agent 调用日志 finder 扩展：
 * - 虚拟列（column_*）：时间格式化、结果展示
 * - detail 页（detail_*）：单条日志详情（基本信息 + 脱敏参数 + 响应摘要）
 * 由 services.xml 注册：desktop_finder.mcpapi_mdl_agent_log
 */
class mcpapi_finder_agent_log
{
    private $_appName = 'mcpapi';

    public $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    // ---- 虚拟列 ----

    public $column_createdat = '调用时间';
    public $column_createdat_order = 10;
    public $column_createdat_order_field = 'created_at';
    public $column_createdat_width = 160;
    public function column_createdat($row)
    {
        // dbeav 列表输出已将 time 列格式化为字符串，直接返回
        return (string) ($row['created_at'] ?? '—');
    }

    public $column_result = '结果';
    public $column_result_order = 20;
    public $column_result_width = 100;
    public function column_result($row)
    {
        $success = ($row['success'] ?? 'true') === 'true';
        return $success ? '成功' : ('失败 ' . ($row['error_code'] ?? ''));
    }

    public $column_duration = '耗时(ms)';
    public $column_duration_order = 25;
    public $column_duration_width = 90;
    public function column_duration($row)
    {
        return (string) ($row['spendtime_ms'] ?? '—');
    }

    // ---- detail 页：finder detail 机制（detail_ 前缀 → finderview=view） ----

    public $detail_view = '详情';

    public function detail_view($id)
    {
        $agentLogMdl = app::get('mcpapi')->model('agent_log');
        $rows = $agentLogMdl->getList('*', ['id' => (int) $id], 0, 1);
        $log = is_array($rows[0] ?? null) ? $rows[0] : null;
        if ($log === null) {
            // vue_mode：空数组 → 前端显示无数据；legacy：HTML 提示
            if ($this->vue_mode) {
                return [];
            }
            return '日志不存在或已被清理';
        }

        $log['created_at_fmt'] = !empty($log['created_at']) ? date('Y-m-d H:i:s', (int) $log['created_at']) : '—';
        $paramsDecoded = json_decode((string) ($log['params'] ?? ''), true);
        $log['params_pretty'] = $this->prettyJson($log['params'] ?? null);
        $log['response_pretty'] = $this->prettyJson($log['response_digest'] ?? null);

        // legacy desktop 框架（vue_mode=false）：渲染 HTML 详情页
        if (!$this->vue_mode) {
            $render = app::get($this->_appName)->render();
            $render->pagedata['log'] = $log;
            $render->pagedata['params_pretty'] = $log['params_pretty'];
            $render->pagedata['response_pretty'] = $log['response_pretty'];
            return $render->fetch('admin/agent/log_detail.html');
        }

        // vue_mode（SPA）：返回结构化数组，由 FinderDetailTemplate 渲染
        return [
            'request_id' => (string) ($log['request_id'] ?? ''),
            'trace_id' => (string) ($log['trace_id'] ?? ''),
            'client_id' => (string) ($log['client_id'] ?? ''),
            'client_mode' => (string) ($log['client_mode'] ?? ''),
            'agent_name' => (string) ($log['agent_name'] ?? ''),
            'agent_version' => (string) ($log['agent_version'] ?? ''),
            'model' => (string) ($log['model'] ?? ''),
            'user_id' => (int) ($log['user_id'] ?? 0),
            'tool_name' => (string) ($log['tool_name'] ?? ''),
            'action_type' => (string) ($log['action_type'] ?? ''),
            'write_flag' => ($log['write_flag'] ?? 'false') === 'true',
            'success' => ($log['success'] ?? 'true') === 'true',
            'error_code' => (string) ($log['error_code'] ?? ''),
            'http_status' => (int) ($log['http_status'] ?? 0),
            'ip' => (string) ($log['ip'] ?? ''),
            'created_at_fmt' => !empty($log['created_at']) ? date('Y-m-d H:i:s', (int) $log['created_at']) : '—',
            'spendtime_ms' => (int) ($log['spendtime_ms'] ?? 0),
            'params_data' => $this->decodeParams($log['params'] ?? null),
            'params_pretty' => $this->prettyJson($log['params'] ?? null),
            'response_pretty' => $this->prettyJson($log['response_digest'] ?? null),
        ];
    }

    /**
     * params JSON → 关联数组（空数组/非法 JSON → null，前端据此显示"无请求参数"）
     */
    private function decodeParams($json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    private function prettyJson($json): string
    {
        if ($json === null || $json === '') {
            return '（无）';
        }
        $decoded = json_decode((string) $json, true);
        return $decoded === null && json_last_error() !== JSON_ERROR_NONE
            ? (string) $json
            : (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
