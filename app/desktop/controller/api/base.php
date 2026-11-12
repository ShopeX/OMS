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

class desktop_ctl_api_base extends desktop_controller
{
    protected $checkCSRF = true;

    protected static $objectName = '';
    protected static $sensitiveFields = [];

    /**
     * Finder actions that should be dispatched through vue context
     * Only these actions go through finderDispatch; all other actions are handled directly
     */
    protected static $finderActions = ['cols', 'columns', 'filters', 'delete', 'export', 'async_export', 'list', 'tabs', 'detail', 'templates', 'refs', 'import', 'favstar'];

    public function __construct($app)
    {
        parent::__construct($app);
    }

    protected function finderDispatch(array $params): void
    {
        $action = $_GET['action'] ?? 'list';

        $finderActions = static::$finderActions;

        if (!in_array($action, $finderActions, true)) {
            $this->fail('Invalid finder action: ' . $action);
            return;
        }

        $context = new desktop_finder_vue_context($params);

        $handlerClass = 'desktop_finder_vue_' . $action;

        if (!class_exists($handlerClass)) {
            $this->fail('Invalid action: ' . $action);
            return;
        }

        $handler = new $handlerClass($context);
        $input = $this->getRequestInput();

        try {
            $result = $handler->handle($input);
        } catch (\Exception $e) {
            // 处理器抛出的业务异常必须仍以 JSON 返回：框架默认会把异常渲染成 HTML 错误页
            // （HTTP 200 + <title>Error: Track</title>），前端 axios 解析不到 message，
            // 只会弹通用兜底「接口返回值异常，请联系管理员」，用户拿到零信息报错（SCRUM-7862）。
            // 引擎级 \Error 不在此兜住，仍交框架错误页（保留 track 便于定位真 bug）。
            $this->fail($e->getMessage() !== '' ? $e->getMessage() : '操作失败');
            return;
        }

        // Import handler returns its own rsp field — propagate directly without double-wrapping
        if (isset($result['rsp']) && in_array($result['rsp'], ['fail', 'failure'], true)) {
            $this->fail($result['message'] ?? '操作失败');
            return;
        }
        if (isset($result['rsp']) && in_array($result['rsp'], ['success', 'succ'], true)) {
            $this->outputJson(['rsp' => 'succ', 'message' => $result['message'] ?? '操作成功', 'data' => $result['data'] ?? []], 200);
            return;
        }

        $this->respond($result);
    }

    protected function getCurrentUserId(): ?int
    {
        $userId = desktop_auth_user::currentUserId();
        return $userId > 0 ? $userId : null;
    }

    protected function outputJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function respond(array $data, int $status = 200): void
    {
        $this->succ($data, $status);
    }

    protected function succ(array $data = [], int $status = 200): void
    {
        $this->outputJson(['rsp' => 'succ', 'data' => $data], $status);
    }

    protected function fail(string $message, int $status = 400, array $extra = []): void
    {
        $this->outputJson(['rsp' => 'fail', 'message' => $message, 'data' => $extra], $status);
    }

    protected function getJsonBody(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return $data ?? [];
    }

    protected function getRequestInput(): array
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $rawInput = file_get_contents('php://input');
            if (strpos($contentType, 'application/json') !== false) {
                $data = json_decode($rawInput, true) ?? [];
                return array_merge($_GET, $data);
            }
            parse_str($rawInput, $postData);
            return array_merge($_GET, $postData);
        }
        return $_GET;
    }

    protected function buildParams(): array
    {
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        $pageSize = (int)($_GET['page_size'] ?? 20);
        if ($pageSize < 1) {
            $pageSize = 20;
        }
        $pageSize = min(200, $pageSize);

        $orderBy = $_GET['orderBy'] ?? null;
        $orderType = strtolower($_GET['orderType'] ?? 'desc');
        if (!in_array($orderType, ['asc', 'desc'], true)) {
            $orderType = 'desc';
        }

        $reserved = ['app', 'ctl', 'act', 'page', 'page_size', 'orderBy', 'orderType', 'action'];
        $filters = array_diff_key($_GET, array_flip($reserved));
        $filters = array_filter($filters, static function ($value) {
            return $value !== '' && $value !== null;
        });
        unset($filters['filter_sql']);

        return [
            'page' => $page,
            'page_size' => $pageSize,
            'orderBy' => $orderBy,
            'orderType' => $orderType,
            'filters' => $filters,
        ];
    }

    protected function getSchema(): array
    {
        $model = $this->getModel();
        return $model->get_schema();
    }

    protected function getModel()
    {
        [$appId, $modelName] = $this->parseObjectName();
        return app::get($appId)->model($modelName);
    }

    protected function parseObjectName(): array
    {
        $objectName = static::$objectName;
        $parts = explode('_mdl_', $objectName, 2);
        if (count($parts) !== 2) {
            throw new Exception('Invalid object name: ' . $objectName);
        }
        return [$parts[0], $parts[1]];
    }

    protected function buildFilterConditions(array $filters): array
    {
        $result = [];
        foreach ($filters as $key => $value) {
            if ($key === 'filter_sql') {
                continue;
            }
            if (strpos($key, '|') !== false) {
                [$field, $op] = explode('|', $key);
                $result[$field . '@' . $op] = $value;
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    protected function runQuery(array $params): array
    {
        $model = $this->getModel();
        $page = $this->normalizePage($params['page'] ?? 1);
        $pageSize = $this->normalizePageSize($params['page_size'] ?? 20);
        $orderClause = $this->buildOrderClause($params['orderBy'] ?? null, $params['orderType'] ?? 'desc');
        $filter = $this->buildFilterConditions($params['filters'] ?? []);

        $list = $model->getList('*', $filter, ($page - 1) * $pageSize, $pageSize, $orderClause);
        $total = $model->count($filter);

        $list = $this->annotateSensitiveFields($list);

        return [
            'list' => $list,
            'total' => (int) $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    protected function buildColumnsMeta(array $schema): array
    {
        $result = [];
        $columns = $schema['columns'] ?? [];
        $sensitiveFields = static::$sensitiveFields;
        foreach ($columns as $key => $column) {
            if (empty($column['in_list']) || empty($column['default_in_list'])) {
                continue;
            }
            $result[] = [
                'field' => $key,
                'label' => $column['label'] ?? $key,
                'width' => $column['width'] ?? null,
                'sensitive' => in_array($key, $sensitiveFields, true),
            ];
        }
        return $result;
    }

    protected function buildFilterDefinitions(array $schema): array
    {
        $result = [];
        foreach (($schema['columns'] ?? []) as $key => $column) {
            $filtertype = $column['filtertype'] ?? null;
            $type = $column['type'] ?? '';
            
            // Skip if neither filtertype nor type (table:xxx) is set
            if (!$filtertype && (!is_string($type) || strpos($type, 'table:') !== 0)) {
                continue;
            }
            
            // Cross-table reference (type starts with 'table:')
            if (is_string($type) && strpos($type, 'table:') === 0) {
                $filterType = $type;
            } else {
                $filterType = $filtertype;
            }
            
            $result[] = [
                'field' => $key,
                'label' => $column['label'] ?? $key,
                'type' => $filterType,
                'default' => !empty($column['filterdefault']),
            ];
        }
        return $result;
    }

    protected function buildViewDefinitions(): array
    {
        return [[
            'id' => 'all',
            'label' => '全部',
            'filter' => [],
        ]];
    }

    /**
     * Vue Finder tabs（finderDispatch.views 与全选批量 view_id 解析共用）。子类按需覆盖；基类默认无 tab。
     *
     * @return list<array{id:string,label:string,filter:array}>
     */
    protected function _finderViews(): array
    {
        return [];
    }

    /**
     * 按 view_id 取 tab 的 filter，并可合并额外字段（如批量操作限定 visibled）。
     */
    protected function finderTabFilter(string $viewId, string $field, $fieldValue): array
    {
        $byId = [];
        foreach ($this->_finderViews() as $tab) {
            if (!isset($tab['id'])) {
                continue;
            }
            $byId[$tab['id']] = $tab['filter'] ?? [];
        }

        $filter = $byId[$viewId] ?? [];

        if ($field && $fieldValue !== null) {
            $filter[$field] = $fieldValue;
        }

        return $filter;
    }

    /**
     * 全选批量：与 finder 提交一致——有 filters 则 buildFilterConditions；否则按 view_id 与 _finderViews 取 tab 条件，再合并 $constraints。
     *
     * @param array<string, mixed> $constraints 与列表条件取交集的等式约束，如批量在售前限定在售状态 ['visibled' => 2]
     */
    protected function resolveBatchAllFilter(array $input, array $constraints = []): array
    {
        $filtersInput = $input['filters'] ?? [];
        if (is_array($filtersInput) && count($filtersInput) > 0) {
            $filter = $this->buildFilterConditions($filtersInput);
        } else {
            $viewId = $input['view_id'] ?? 'all';
            if (!is_string($viewId)) {
                $viewId = 'all';
            }
            $filter = $this->finderTabFilter($viewId, '', null);
        }

        foreach ($constraints as $field => $value) {
            if (!is_string($field) || $field === '') {
                continue;
            }
            $filter[$field] = $value;
        }

        return $filter;
    }

    protected function searchOptions(): array
    {
        return [];
    }

    protected function annotateSensitiveFields(array $list): array
    {
        $sensitiveFields = static::$sensitiveFields;
        $security = class_exists('ome_security_hash') ? kernel::single('ome_security_hash') : null;
        
        foreach ($list as &$row) {
            foreach ($sensitiveFields as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }
                if ($security) {
                    $row[$field . '_encrypted'] = $security->check_encrypt($row[$field]);
                } else {
                    $row[$field . '_encrypted'] = false;
                }
            }
        }
        return $list;
    }

    protected function normalizePage($page): int
    {
        $page = (int) $page;
        return $page > 0 ? $page : 1;
    }

    protected function normalizePageSize($pageSize): int
    {
        $pageSize = (int) $pageSize;
        if ($pageSize < 1) {
            $pageSize = 20;
        }
        // 与 legacy finder 分页口径统一：desktop/lib/finder/builder/prototype.php::$plimit_in_sel = (500,200,100,50,20,10)，max_plimit 默认 500
        return min(500, $pageSize);
    }

    protected function buildOrderClause($column, $direction): ?string
    {
        if (!$column) {
            return null;
        }
        if (!$this->isOrderColumnAllowed($column)) {
            return null;
        }
        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }
        return $column . ' ' . $direction;
    }

    /**
     * 排序列白名单校验：只允许 dbschema 中真实存在的列（防非法列名拼进 ORDER BY 导致 500）
     */
    protected function isOrderColumnAllowed($column): bool
    {
        try {
            $schema = $this->getModel()->get_schema();
        } catch (\Throwable $e) {
            return false;
        }
        $columns = isset($schema['columns']) && is_array($schema['columns']) ? $schema['columns'] : [];
        return array_key_exists((string) $column, $columns);
    }
}