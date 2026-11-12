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
 * 下载中心/导出任务列表 Vue JSON 契约（SCRUM-7959）。
 *
 * 读链路复用 Finder 框架（list/columns/filters/cols）；写链路（删除/下载）为控制器
 * 独立 act：legacy delTask 是「物理删除任务 + 清缓存 + 删本地/远程导出文件」的复合
 * 动作，与框架 delete.php 的 recycle 语义不等价，禁走 finderDispatch delete。
 *
 * 路由：index.php?app=taoexlib&ctl=api_ietask&act=<act>
 */
class taoexlib_ctl_api_ietask extends desktop_ctl_api_base
{
    protected static $objectName = 'taoexlib_mdl_ietask';

    private const FINDER_ALIAS = 'taoexlib-ietask-vue';

    /**
     * Finder 读链路（list/columns/filters/cols/...）。
     *
     * GET ?app=taoexlib&ctl=api_ietask&act=index&action=<finder_action>
     */
    #[RequirePermission('taoexlib_ietask')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'             => static::$objectName,
            'finderAlias'            => self::FINDER_ALIAS,
            'title'                  => '导出任务列表',
            'base_filter'            => $this->opBaseFilter(),
            'use_buildin_filter'     => true,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'orderBy'                => 'task_id desc',
            // parity: legacy ietask 页工具栏「删除」按钮(delTask 选中项);Vue 侧 toolbar
            // action=delete 走控制器 delete act(物理删除语义,不走框架 recycle)。
            'actions'                => [
                [
                    'id'     => 'delete-ietask',
                    'label'  => '删除',
                    'action' => 'delete',
                    'target' => ['type' => 'vue-action'],
                    'type'   => 'danger',
                ],
            ],
        ]);
    }

    /**
     * POST - 删除导出任务（对齐 legacy ietask::delTask 复合语义）。
     *
     * ?app=taoexlib&ctl=api_ietask&act=delete
     * Body(JSON): { ids: number[] } 或 { selection_mode: "_ALL_", filters: {...} }
     */
    #[RequirePermission('taoexlib_ietask')]
    public function delete(): void
    {
        $input = $this->getRequestInput();
        $model = app::get('taoexlib')->model('ietask');

        $ids = [];
        $selectionMode = $_POST['selection_mode'] ?? $input['selection_mode'] ?? null;
        if ($selectionMode === '_ALL_') {
            $filters = $this->buildFilterConditions($input['filters'] ?? []);
            if ($filters === []) {
                $this->fail('请选择筛选条件后再执行全部删除');
                return;
            }
            // 「全选删除」与列表同一匹配语义：列表按 filter_use_like 让 dbschema 模糊条件走 LIKE
            $model->filter_use_like = true;
            try {
                $rows = $model->getList('task_id', $filters, 0, -1);
            } finally {
                $model->filter_use_like = false;
            }
            foreach ((array) $rows as $row) {
                $ids[] = (int) $row['task_id'];
            }
            // 非超管仅能删自己的任务：全选命中集也按 op_id 再收敛一层
            $baseFilter = $this->opBaseFilter();
            if (isset($baseFilter['op_id'])) {
                $ownRows = $model->getList('task_id', ['task_id' => $ids, 'op_id' => $baseFilter['op_id']], 0, -1);
                $own = [];
                foreach ((array) $ownRows as $row) {
                    $own[] = (int) $row['task_id'];
                }
                $ids = $own;
            }
        } else {
            $raw = $input['ids'] ?? $_POST['task_id'] ?? [];
            if (!is_array($raw)) {
                $raw = [$raw];
            }
            foreach ($raw as $id) {
                $ids[] = (int) $id;
            }
            $ids = array_values(array_filter($ids));
        }

        if ($ids === []) {
            $this->fail('请选择要删除的记录');
            return;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->removeTask($model, (int) $id)) {
                $deleted++;
            }
        }

        $this->succ(['message' => '删除成功', 'deleted' => $deleted]);
    }

    /**
     * GET - 下载导出文件（对齐 legacy ietask::download：权限/归属校验 + export_history
     * 记录 + taskmgr_interface_storage 取文件后流式输出）。
     *
     * ?app=taoexlib&ctl=api_ietask&act=download&id=<task_id>
     */
    #[RequirePermission('taoexlib_ietask')]
    public function download(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->fail('缺少任务 ID');
            return;
        }

        // parity legacy checkDownload：下载前必须验证图形验证码（base_vcode::verify('desktop')，
        // gen_vcode 时已将答案写入 kvstore，验证成功后即销毁，一次性）。
        $vcode = (string) ($_GET['vcode'] ?? '');
        if ($vcode === '' || !base_vcode::verify('desktop', (int) $vcode)) {
            $this->fail('验证码错误');
            return;
        }

        $model = app::get('taoexlib')->model('ietask');
        $taskInfo = $model->dump(['task_id' => $id], 'file_name,task_name,filter_data,op_id,status,export_ver');
        if (!$taskInfo || empty($taskInfo['file_name'])) {
            $this->fail('任务不存在或文件已过期');
            return;
        }
        if ($taskInfo['status'] !== 'finished' || (int) ($taskInfo['export_ver'] ?? 0) !== 2) {
            $this->fail('任务未完成，暂无文件可下载');
            return;
        }

        $isSuper = kernel::single('desktop_user')->is_super();
        if (!$isSuper && (int) $taskInfo['op_id'] !== (int) kernel::single('desktop_user')->get_id()) {
            $this->fail('您无权操作');
            return;
        }

        $filter = @unserialize((string) $taskInfo['filter_data']);
        $isXls = is_array($filter) && (($filter['_io_type'] ?? '') === 'xls');

        // 记录导出用户记录（legacy 同款）。dbeav_model::insert(&$data) 是引用传参，
        // PHP8 下字面量数组直接传入会 Fatal（cannot be passed by reference），先落变量。
        $exportData = [
            'task_name'   => $taskInfo['task_name'],
            'file_name'   => $taskInfo['file_name'],
            'op_id'       => kernel::single('desktop_user')->get_id(),
            'create_time' => time(),
        ];
        app::get('taoexlib')->model('export_history')->insert($exportData);

        $storageLib = kernel::single('taskmgr_interface_storage');
        $localFile = DATA_DIR . '/export/tmp_local/' . md5(microtime() . KV_PREFIX) . $id . ($isXls ? '.xlsx' : '.csv');
        if (!$storageLib->get($taskInfo['file_name'], $localFile)) {
            $this->fail('导出文件读取失败，可能已过期');
            return;
        }

        $fileSize = filesize($localFile);
        $fileName = $taskInfo['task_name'] . '-' . $id . ($isXls ? '.xlsx' : '.csv');
        if ($isXls) {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename=' . $fileName);
            header('Cache-Control: max-age=0');
        } else {
            header('Content-Type: application/octet-stream');
            header('Accept-Ranges: bytes');
            header('Accept-Length: ' . (string) $fileSize);
            header('Content-Disposition: attachment; filename=' . $fileName);
        }
        header('Content-Length: ' . (string) $fileSize);

        echo file_get_contents($localFile);
        @unlink($localFile);
        exit;
    }

    /**
     * GET - 导出确认协议文本（parity legacy predownload：下载前弹窗展示 export.protocol）。
     *
     * ?app=taoexlib&ctl=api_ietask&act=protocol
     */
    #[RequirePermission('taoexlib_ietask')]
    public function protocol(): void
    {
        $text = is_file(ROOT_DIR . '/export.protocol')
            ? (string) file_get_contents(ROOT_DIR . '/export.protocol')
            : '';
        $this->succ(['protocol' => $text]);
    }

    /**
     * 单任务复合删除：清本地缓存目录 / 未完成任务置删除标记 / 删本地与远程导出文件 /
     * 删任务行（对齐 legacy ietask::delTask 每行处理逻辑）。
     */
    private function removeTask(object $model, int $taskId): bool
    {
        $task = $model->dump(['task_id' => $taskId], 'task_id,status,file_name');
        if (!$task) {
            return false;
        }

        $storageLib = kernel::single('taskmgr_interface_storage');
        $isLocalStorage = $storageLib->isLocalMode();
        $ietaskLib = kernel::single('taoexlib_ietask');

        $cacheLib = kernel::single('taskmgr_interface_cache', $taskId);
        if ($cacheLib->isLocalMode()) {
            $ietaskLib->delDirAndFile(DATA_DIR . '/export/cache/' . $taskId, false);
        }

        if (($task['status'] ?? '') !== 'finished') {
            $cacheLib->store('exp_task_' . $taskId . '_status', 'del', 600);
        }

        if ($isLocalStorage && !empty($task['file_name'])) {
            @unlink(DATA_DIR . '/export/file/' . $task['file_name']);
        }

        if (!empty($task['file_name'])) {
            $storageLib->delete($task['file_name']);
        }

        $before = (int) $model->count(['task_id' => $taskId]);
        $model->remove($taskId);
        $after = (int) $model->count(['task_id' => $taskId]);

        return $after < $before;
    }

    /**
     * 非超管只能看/删自己的导出任务（对齐 legacy index 的 base_filter 语义）。
     */
    private function opBaseFilter(): array
    {
        if (kernel::single('desktop_user')->is_super()) {
            return [];
        }

        return ['op_id' => kernel::single('desktop_user')->get_id()];
    }
}
