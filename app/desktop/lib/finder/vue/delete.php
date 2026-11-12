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

class desktop_finder_vue_delete
{
    protected $vue_mode = false;

    private const DELETE_ALL_FILTER_REQUIRED_MESSAGE = '请选择筛选条件后再执行全部删除';

    /** 「全选删除」分片大小（前端可传 chunk_size 覆盖，上限 MAX_CHUNK_SIZE） */
    private const DEFAULT_CHUNK_SIZE = 200;

    private const MAX_CHUNK_SIZE = 500;

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
        $model = $this->context->getModel();
        $pkey = $model->idColumn ?? 'id';
        $selectionMode = $_POST['selection_mode'] ?? $request['selection_mode'] ?? null;
        $filters = $this->context->buildFilterConditions($request['filters'] ?? []);

        if ($selectionMode === '_ALL_') {
            if (!$this->hasEffectiveFilter($filters)) {
                // 业务守卫必须以 rsp=fail（HTTP 400 + JSON）返回，禁止 throw：
                // 抛 \Exception 会被框架渲染成 HTML 错误页（HTTP 200 + <title>Error: Track</title>），
                // 前端 axios 解析不到 message ⇒ 只弹通用兜底「接口返回值异常，请联系管理员」，
                // 用户拿到零信息报错且误判为系统故障（SCRUM-7862 人工复测反馈）。
                return [
                    'rsp' => 'fail',
                    'message' => self::DELETE_ALL_FILTER_REQUIRED_MESSAGE,
                ];
            }

            $filter = $filters;
        } else {
            $ids = $_POST[$pkey] ?? $request['ids'] ?? null;
            if (empty($ids) || !is_array($ids)) {
                // 同上：可预期的业务分支一律 rsp=fail，不走异常。
                return [
                    'rsp' => 'fail',
                    'message' => '请选择要删除的记录',
                ];
            }
            $filter = [$pkey => $ids];
        }

        $recycle = kernel::single('desktop_system_recycle');

        // 「全选删除」大数据量分片：前端传 chunk_offset/chunk_size 时，本轮只删「当前剩余集的前 L 条」，
        // 并回 {chunkUpdated,nextOffset,done} 供前端驱动进度条（与 app/material/controller/api/material/basic.php::batchVisible 同一约定）。
        // 目的：① 每片只取主键 ⇒ 内存有界（不再 getList('*',$filter,0,-1) 把命中行全量载入而 OOM）；
        //       ② 单请求耗时可控 ⇒ 不再出现「前端超时而后端仍在删」的半完成态。
        if ($selectionMode === '_ALL_' && $this->wantsChunked($request)) {
            return $this->deleteAllChunk($recycle, $filters, $request);
        }

        // 「全选删除」须与列表同一匹配语义：列表(app/desktop/lib/finder/vue/list.php)以 filter_use_like
        // 让 dbschema 里的模糊条件走 LIKE；此处不置该标记，同一批 filters 会退化为等值匹配，
        // 表现为「提示删除成功但静默漏删」（列表 N 条 / 实删 M 条）。与 legacy 批量删除一致：
        // app/desktop/lib/finder/builder/dorecycle.php 也是先置该标记再交给 recycle。
        if ($selectionMode === '_ALL_') {
            $model->filter_use_like = true;
        }

        try {
            $result = $recycle->dorecycle($this->context->getObjectName(), $filter);
        } finally {
            if ($selectionMode === '_ALL_') {
                $model->filter_use_like = false;
            }
        }

        if ($result) {
            return [
                'success' => true,
                'message' => '删除成功',
            ];
        }

        return [
            'success' => false,
            'message' => $this->recycleFailureMessage($model),
        ];
    }

    /** 前端是否要求分片删除（chunk_offset / chunk_size 任一在场即视为分片） */
    private function wantsChunked(array $request): bool
    {
        foreach (['chunk_offset', 'chunk_size'] as $key) {
            if (array_key_exists($key, $request) || array_key_exists($key, $_POST)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 分片删除一片（仅「全选删除」走这里）。
     *
     * 每轮取「当前剩余集」的前 $limit 条主键再删这一片：删除后命中集合会缩小，所以不能按 offset 翻页
     * （会跳行），必须每轮都取前 N 条（与 material::batchVisible 同款）。$offset 只作为进度游标回传。
     */
    private function deleteAllChunk(object $recycle, array $filters, array $request): array
    {
        $model = $this->context->getModel();
        $pkey = $model->idColumn ?? 'id';

        $limit = (int) ($request['chunk_size'] ?? $_POST['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE);
        $limit = max(1, min(self::MAX_CHUNK_SIZE, $limit));
        $offset = max(0, (int) ($request['chunk_offset'] ?? $_POST['chunk_offset'] ?? 0));

        $model->filter_use_like = true;

        try {
            $rows = $model->getList($pkey, $filters, 0, $limit, $pkey . ' asc');

            $ids = [];
            foreach ((array) $rows as $row) {
                $id = $row[$pkey] ?? null;
                if ($id !== null && $id !== '') {
                    $ids[] = $id;
                }
            }

            if ($ids === []) {
                return [
                    'success' => true,
                    'message' => '删除成功',
                    'chunkUpdated' => 0,
                    'nextOffset' => $offset,
                    'done' => true,
                ];
            }

            if (!$recycle->dorecycle($this->context->getObjectName(), [$pkey => $ids])) {
                return [
                    'success' => false,
                    'message' => $this->recycleFailureMessage($model),
                    'chunkUpdated' => 0,
                    'nextOffset' => $offset,
                    'done' => false,
                ];
            }

            $deleted = count($ids);

            return [
                'success' => true,
                'message' => '删除成功',
                'chunkUpdated' => $deleted,
                'nextOffset' => $offset + $deleted,
                'done' => $deleted < $limit,
            ];
        } finally {
            $model->filter_use_like = false;
        }
    }

    private function recycleFailureMessage(object $model): string
    {
        if (property_exists($model, 'recycle_msg') && $model->recycle_msg) {
            return (string) $model->recycle_msg;
        }

        return '删除失败';
    }

    private function hasEffectiveFilter(?array $filters): bool
    {
        if (empty($filters)) {
            return false;
        }

        foreach ($filters as $value) {
            if (is_array($value)) {
                if ($this->hasEffectiveFilter($value)) {
                    return true;
                }

                continue;
            }

            if ($value === null || $value === '' || $value === '_ALL_' || $value === '_ANY_') {
                continue;
            }

            return true;
        }

        return false;
    }
}
