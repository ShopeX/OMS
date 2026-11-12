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

use app\desktop\lib\annotation\RequirePermission;

/**
 * 失败重试（erpapi/api_fail）Vue JSON 契约
 *
 * legacy 页 = `erpapi_ctl_admin_api_fail`（app/erpapi/controller/admin/api/fail.php）：
 *   列表 `retry()`：finder('erpapi_mdl_api_fail', $params)，title=失败请求 / orderBy='id desc' /
 *     use_buildin_filter=true / 其余 use_buildin_* 全 false，顶部 panel apifail_finder_top，
 *     工具栏动作仅「批量重试」（submit=act=retry_view，dialog 690x200）。
 *   预检 `retry_view()`：无选中且非 _ALL_ ⇒ 「没有选择执行的记录!」；status|noequal=succ；
 *     命中数 >500 ⇒ 「每次最多可执行500条记录!」（迁移页按业务「一次 100/1000 条」的规模放宽到 RETRY_LIMIT=1000）；
 *     否则列出 id 交给模板**逐条** POST retry_do —— Vue 页把同一份 id 快照切片，每片一个 retryChunk 请求。
 *   单条 `retry_do()`：id + status|noequal=succ 取 1 行；无行 ⇒ err_msg=没有相关记录；
 *     status=running 先置 fail；再 kernel::single('erpapi_autotask_task_retryapi')->process($row, $err)。
 *   完成后 `refresh()` 回列表。
 *   legacy **没有** recycle（删除）、没有 detail、没有单行重试入口 —— 本页 FE 原有的
 *   「删除 / 查看详情」（写直连 legacy ctl）属半迁移遗留的越权功能，按「不复刻 legacy 缺陷」收敛掉。
 *
 * 读链路全部走框架 finderDispatch（action = list / columns / filters / cols / detail / favstar），
 * 不在 retry() 里手搓 action 分发（白名单由 desktop_ctl_api_base::$finderActions 把关）。
 * 写动作（预检 / 分块重试 / 单条重试）落 erpapi_capability_api_fail，与 legacy 入口共用同一份写实现。
 * 权限点沿用 legacy 菜单行 permission=`setting_api_log`。
 *
 * 注意：`*->process()` 会真实外呼（触发 WMS 单据创建事件），自动化只做请求形状与拒绝契约校验。
 *
 * 路由：index.php?app=erpapi&ctl=api_api_fail&act=retry&action=<list|columns|filters|cols|detail|favstar>
 * 预检：POST index.php?app=erpapi&ctl=api_api_fail&act=retryCheck  {ids?:[], isSelectedAll?:'_ALL_', obj_type?}
 * 分块：POST index.php?app=erpapi&ctl=api_api_fail&act=retryChunk  {ids: int[], offset: int, chunkSize?: int}
 * 单条：POST index.php?app=erpapi&ctl=api_api_fail&act=retryDo     {id}
 */
class erpapi_ctl_api_api_fail extends desktop_ctl_api_base
{
    protected static $objectName = 'erpapi_mdl_api_fail';

    private ?erpapi_capability_api_fail $capability = null;

    /**
     * Finder 列表（action=list / columns / filters / cols / detail / favstar 全部交由框架分发）。
     */
    #[RequirePermission('setting_api_log')]
    public function retry(): void
    {
        $this->finderDispatch([
            'objectName'             => static::$objectName,
            'title'                  => '失败请求',
            'orderBy'                => 'id desc',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'actions'                => [
                ['id' => 'batch-retry', 'label' => '批量重试', 'action' => 'batch-retry', 'type' => 'primary'],
            ],
        ]);
    }

    /**
     * 批量重试预检（等价 legacy retry_view 的校验，返回待执行 id 列表）。
     *
     * POST ?app=erpapi&ctl=api_api_fail&act=retryCheck  {ids:[] | isSelectedAll:'_ALL_', ...筛选}
     */
    #[RequirePermission('setting_api_log')]
    public function retryCheck(): void
    {
        $input = $this->getJsonBody();
        $err = '';
        $count = 0;

        try {
            $ids = $this->capability()->collectRetryIds(is_array($input) ? $input : [], $count, $err);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '没有选择执行的记录!');
            return;
        }

        if ($err !== '') {
            $this->fail($err);
            return;
        }

        $this->succ(['ids' => $ids, 'count' => $count]);
    }

    /**
     * 单条重试（等价 legacy retry_do）。
     *
     * POST ?app=erpapi&ctl=api_api_fail&act=retryDo  {id}
     */
    #[RequirePermission('setting_api_log')]
    public function retryDo(): void
    {
        $input = $this->getJsonBody();
        $id = (int) ($input['id'] ?? 0);
        $err = '';

        try {
            $result = $this->capability()->retryOne($id, $err);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '重试失败');
            return;
        }

        if (!is_array($result)) {
            $this->fail($err !== '' ? $err : '重试失败');
            return;
        }

        if ((bool) ($result['succ'] ?? false) !== true) {
            $this->fail($err !== '' ? $err : '重试失败');
            return;
        }

        $this->succ([
            'message' => '重试成功',
            'id'      => (int) ($result['id'] ?? $id),
            'obj_bn'  => (string) ($result['obj_bn'] ?? ''),
        ]);
    }

    /**
     * 分块批量重试（等价 legacy retry_do 的逐条语义，改为「一次请求处理一片、前端循环驱动进度条」）。
     *
     * POST ?app=erpapi&ctl=api_api_fail&act=retryChunk  {ids: int[], offset: int, chunkSize?: int}
     * 返回 {processed, succ, fail, nextOffset, total, done, details}；前端按 nextOffset 续跑直到 done。
     */
    #[RequirePermission('setting_api_log')]
    public function retryChunk(): void
    {
        $input = $this->getJsonBody();
        $input = is_array($input) ? $input : [];

        $ids = $input['ids'] ?? [];
        $offset = (int) ($input['offset'] ?? 0);
        $chunkSize = (int) ($input['chunkSize'] ?? 0);
        $err = '';

        try {
            $result = $this->capability()->retryChunk(
                is_array($ids) ? $ids : [],
                $offset,
                $chunkSize,
                $err
            );
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '重试失败');
            return;
        }

        if ($err !== '') {
            $this->fail($err);
            return;
        }

        $this->succ($result);
    }

    private function capability(): erpapi_capability_api_fail
    {
        if ($this->capability === null) {
            $this->capability = new erpapi_capability_api_fail();
        }

        return $this->capability;
    }
}
