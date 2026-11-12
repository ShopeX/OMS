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
 * Vue Finder 筛选条件：筛选定义 + 「保存筛选条件」的读/存/删
 *
 * 前端入口：`?action=filters`（base.php 的 $finderActions 白名单 / finderDispatch），
 * body 用 filterAction 区分动作（与 favstar 的 favAction 同一约定）：
 *   - get   （默认 / GET）→ filters + savedFilters + filterContext
 *   - save  （POST）      → ['filter_id' => n, 'filter_name' => 'xxx']
 *   - delete（POST）      → ['deleted' => n]
 *
 * 数据源：desktop_filter 表 —— 与 legacy 后台「保存筛选条件」同一张表、同一 user 维度，
 * 所以 Vue 页保存的条件在 legacy 页可见，反之亦然（不做双写、不改 legacy 控制器）。
 *
 * 读取口径与 legacy desktop_finder_builder_prototype::get_views / vue_tabs::getSavedFilters 对齐：
 * 按 model + user_id 取；不额外用 app/ctl/act 收窄，原因是 Vue 页的 vueMenuPath（如 ctl=api_users）
 * 与 legacy 菜单保存时的 ctl/act 不一定同名，收窄会漏掉 legacy 已存的条件（表现为「保存过的筛选消失」）。
 * 写入时带上 app/ctl/act，保持与 legacy 行结构一致，便于 legacy 侧读取。
 */

class desktop_finder_vue_filters
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
        $action = $request['filterAction'] ?? 'get';

        switch ($action) {
            case 'save':
                return $this->save($request);
            case 'delete':
                return $this->delete($request);
            case 'get':
            default:
                return $this->getFilters();
        }
    }

    /**
     * 筛选定义 + 当前用户在当前 model 下保存的筛选条件
     */
    private function getFilters(): array
    {
        $filters = $this->context->buildFilterDefinitions();
        $optionsMap = $this->context->getFilterOptions();

        if (!empty($optionsMap)) {
            foreach ($filters as &$filter) {
                $field = $filter['field'] ?? '';
                if ($field !== '' && isset($optionsMap[$field])) {
                    $filter['options'] = $optionsMap[$field];
                }
            }
            unset($filter);
        }

        return [
            'filters' => $filters,
            'savedFilters' => $this->fetchSavedFilters(),
            'filterContext' => [
                'model' => $this->context->getObjectName(),
            ],
        ];
    }

    /**
     * 保存筛选条件（同名同条件去重，与 legacy desktop_ctl_filter::tab_save 一致）
     */
    private function save(array $request): array
    {
        $userId = $this->context->getCurrentUserId();
        if (!$userId) {
            return ['rsp' => 'fail', 'message' => '登录状态已过期，请重新登录'];
        }

        $filterName = trim((string)($request['filter_name'] ?? ''));
        $filterQuery = trim((string)($request['filter_query'] ?? ''));

        if ($filterName === '') {
            return ['rsp' => 'fail', 'message' => '请填写筛选器名称'];
        }
        if ($filterQuery === '') {
            return ['rsp' => 'fail', 'message' => '当前没有可保存的筛选条件'];
        }
        // desktop_filter.filter_name 为 varchar(20)
        if (mb_strlen($filterName, 'UTF-8') > 20) {
            $filterName = mb_substr($filterName, 0, 20, 'UTF-8');
        }

        $objectName = $this->context->getObjectName();
        $model = app::get('desktop')->model('filter');

        $exists = $model->getList(
            'filter_id,filter_name',
            ['model' => $objectName, 'user_id' => $userId, 'filter_query' => $filterQuery],
            0,
            1
        );
        if (!empty($exists)) {
            return ['rsp' => 'fail', 'message' => '标签中存在相同的筛选：' . $exists[0]['filter_name']];
        }

        // 注意：框架 dbeav_model::insert(&$data) 形参为引用，必须传变量，不能传数组字面量（PHP8 会 Fatal）
        $row = [
            'filter_name' => $filterName,
            'user_id' => $userId,
            'model' => $objectName,
            'filter_query' => $filterQuery,
            'app' => (string)($request['app'] ?? ''),
            'ctl' => (string)($request['ctl'] ?? ''),
            'act' => (string)($request['act'] ?? ''),
            'create_time' => time(),
        ];
        $model->insert($row);

        $filterId = 0;
        $savedRows = $model->getList(
            'filter_id',
            ['model' => $objectName, 'user_id' => $userId, 'filter_query' => $filterQuery],
            0,
            1,
            'filter_id desc'
        );
        if (!empty($savedRows)) {
            $filterId = (int)$savedRows[0]['filter_id'];
        }

        return ['filter_id' => $filterId, 'filter_name' => $filterName];
    }

    /**
     * 删除当前用户名下的筛选条件
     */
    private function delete(array $request): array
    {
        $userId = $this->context->getCurrentUserId();
        if (!$userId) {
            return ['rsp' => 'fail', 'message' => '登录状态已过期，请重新登录'];
        }

        $filterId = (int)($request['filter_id'] ?? 0);
        if ($filterId <= 0) {
            return ['rsp' => 'fail', 'message' => '参数错误：缺少 filter_id'];
        }

        $model = app::get('desktop')->model('filter');
        $rows = $model->getList('filter_id', ['filter_id' => $filterId, 'user_id' => $userId], 0, 1);
        if (empty($rows)) {
            return ['rsp' => 'fail', 'message' => '筛选条件不存在或无权删除'];
        }

        $model->delete(['filter_id' => $filterId, 'user_id' => $userId]);

        return ['deleted' => $filterId];
    }

    /**
     * 按 model + user_id 取行，并把 legacy query string 解析成数组
     */
    private function fetchSavedFilters(): array
    {
        $userId = $this->context->getCurrentUserId();
        if (!$userId) {
            return [];
        }

        $objectName = $this->context->getObjectName();
        $model = app::get('desktop')->model('filter');
        $rows = $model->getList(
            'filter_id,filter_name,filter_query,create_time',
            ['model' => $objectName, 'user_id' => $userId],
            0,
            -1,
            'create_time asc'
        );

        $result = [];
        foreach ($rows as $row) {
            $filterArray = [];
            if (!empty($row['filter_query'])) {
                parse_str($row['filter_query'], $filterArray);
            }
            $result[] = [
                'filter_id' => (int)$row['filter_id'],
                'filter_name' => $row['filter_name'],
                'filter' => $filterArray,
                'filter_query' => (string)$row['filter_query'],
            ];
        }
        return $result;
    }
}
