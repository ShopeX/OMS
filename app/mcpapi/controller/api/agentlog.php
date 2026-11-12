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

use app\desktop\lib\annotation\RequirePermission;

/**
 * MCP Agent 调用日志 Vue 控制器（SPA 数据接口，finder 分发）。
 *
 * ctl 落点：菜单 menu_path 为 `app=mcpapi&ctl=admin_agentlog&act=index`，
 * desktop_user 对含 `admin_` 的 menu_path 派生 api_ 版本，运行时 ctl = `api_agentlog`。
 * 列定义来自 mcpapi_mdl_agent_log 的 dbschema 元数据（label/in_list/filtertype/searchtype）。
 * 查询逻辑与 legacy admin 控制器共享（mcpapi_agent_log_query）。
 */
class mcpapi_ctl_api_agentlog extends desktop_ctl_api_base
{
    protected static $objectName = 'mcpapi_mdl_agent_log';

    /**
     * 列表/列/筛选/tabs/cols 统一分发入口。
     * 默认排序 created_at desc，默认时间窗近 7 天（对齐 ome_ctl_api_api_log）。
     */
    #[RequirePermission('mcpapi.agentlog.list')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'             => 'mcpapi_mdl_agent_log',
            'finderAlias'            => 'vue',
            'title'                  => 'MCP调用日志', // 视图标题由前端按 tab 覆盖
            'orderBy'                => 'created_at',
            'orderType'              => 'desc',
            'base_filter'            => $this->_defaultTimeFilter(),
            'actions'                => [],
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
        ]);
    }

    /**
     * 详情：单条日志（params / response_digest 原文，前端 pretty 展示）。
     */
    #[RequirePermission('mcpapi.agentlog.list')]
    public function detail(): void
    {
        $input = $this->getRequestInput();
        $logId = $input['id'] ?? ($_GET['p'][0] ?? '');

        if ($logId === '' || $logId === null) {
            $this->fail('无效操作！', 422);
            return;
        }

        $row = app::get('mcpapi')->model('agent_log')->getList('*', ['id' => (int) $logId], 0, 1);
        $log = is_array($row[0] ?? null) ? $row[0] : null;
        if ($log === null) {
            $this->fail('日志不存在', 404);
            return;
        }

        $this->succ([
            'log' => $log,
            'params_pretty' => $this->prettyJson($log['params'] ?? null),
            'response_pretty' => $this->prettyJson($log['response_digest'] ?? null),
        ]);
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

    /**
     * 默认时间窗：用户未显式指定 created_at 时补近 7 天（对齐 ome_ctl_api_api_log）。
     */
    protected function _defaultTimeFilter(): array
    {
        $input = $this->getRequestInput();
        $filters = isset($input['filters']) && is_array($input['filters']) ? $input['filters'] : [];

        if ($this->hasExplicitCreatedFilter($input, $filters)) {
            return [];
        }

        return ['created_at|than' => strtotime('-7 days')];
    }

    private function hasExplicitCreatedFilter(array $input, array $filters): bool
    {
        if (!empty($input['created_at'])) {
            return true;
        }
        foreach ($filters as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            if ($key === 'created_at' || strpos((string) $key, 'created_at|') === 0) {
                return true;
            }
        }
        return false;
    }
}
