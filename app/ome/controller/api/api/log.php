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
 * API 同步日志 Vue 控制器（SCRUM-7662）
 *
 * 继承 desktop_ctl_api_base，为 Vue 版「日志列表」提供 finder 分发（列表/列/筛选/tabs/cols）
 * 与详情接口。
 *
 * ctl 落点说明（D7 定稿）：菜单 menu_path 为 `app=ome&ctl=admin_api_log&act=index`，
 * desktop_user 对含 `admin_` 的 menu_path 做 str_replace('admin_','api_') 派生 vue_menu_path，
 * 因此运行时 ctl = `api_api_log`，类名 ome_ctl_api_api_log 经 autoload `_`→`/` 映射到
 * app/ome/controller/api/api/log.php（同族先例：monitor_ctl_api_alarm_queue）。
 *
 * 旧 HTML 控制器 app/ome/controller/admin/api/log.php 保留作回退，不改动。
 * 前端详情硬编码 act=detail（src/api/ome/api-log.ts），故方法名固定为 detail()。
 * 重试入口已下线（Vue 页不再提供重试），故本控制器无 retry()/batchRetry()；
 * 老 HTML 页重试走 app/ome/view/admin/api/retry.html -> ctl=admin_api_log&act=retry_do。
 */
class ome_ctl_api_api_log extends desktop_ctl_api_base
{
    protected static $objectName = 'ome_mdl_api_log';

    /**
     * 列表/列/筛选/tabs/cols 统一分发入口（action=list|columns|filters|tabs|cols）。
     * 默认排序 createtime desc，默认时间窗近 7 天（见 _defaultTimeFilter()）。
     */
    #[RequirePermission('setting_api_log')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'             => 'ome_mdl_api_log',
            'finderAlias'            => 'vue',
            'title'                  => '所有同步日志', // 视图标题由前端按 tab 覆盖
            // 列表取数列 = 页面真实展示列（dbschema in_list/default_in_list ∩ 前端 index.vue）。
            // 显式声明后 vue list 只 SELECT 这些列，剔除 params/response/transfer/original_params
            // 4 个 longtext，避免 500/页 响应体积随大字段膨胀（SCRUM-7920）。
            // 主键 log_id 由框架 idColumn 兜底补首，无需列出；详情/retry 走独立接口不受影响。
            'finderCols'             => 'createtime,original_bn,task_name,status,worker,msg,api_type,msg_id,last_modified,spendtime',
            'orderBy'                => 'createtime',
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
     * 默认时间窗：用户未显式指定 createtime 时补近 7 天（等效老页 log.php:76-82）。
     * 客户端日期筛选经 buildFilterParams 下发的键形如 `createtime|bthan` / `createtime|sthan`，
     * 一并识别，避免用户自选时间范围时再叠加 7 天窗。
     */
    protected function _defaultTimeFilter(): array
    {
        $input   = $this->getRequestInput();
        $filters = isset($input['filters']) && is_array($input['filters']) ? $input['filters'] : [];

        if ($this->hasExplicitCreatetimeFilter($input, $filters)) {
            return [];
        }

        return ['createtime|than' => strtotime('-7 days')];
    }

    /**
     * 判断请求是否已带 createtime 条件（顶层或 filters 内的 createtime / createtime|xxx）。
     */
    protected function hasExplicitCreatetimeFilter(array $input, array $filters): bool
    {
        if (!empty($input['createtime'])) {
            return true;
        }

        foreach ($filters as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            if ($key === 'createtime' || strpos((string) $key, 'createtime|') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 详情（前端 act=detail，硬编码于 src/api/ome/api-log.ts）。
     * 复用 ome_api_func::processApiLogData()：三 Tab pretty JSON，msg/response 走 htmlentities。
     */
    #[RequirePermission('setting_api_log')]
    public function detail(): void
    {
        $input = $this->getRequestInput();
        $logId = $input['log_id'] ?? ($_GET['p'][0] ?? '');

        if ($logId === '' || $logId === null) {
            $this->fail('无效操作！', 422);
            return;
        }

        $apilog = $this->getModel()->dump($logId);
        if (empty($apilog) || !is_array($apilog)) {
            $this->fail('日志不存在', 404);
            return;
        }

        $apilog = kernel::single('ome_api_func')->processApiLogData($apilog);

        // processApiLogData() 内对 msg/response 做了 htmlentities()（老 HTML 页按实体渲染），
        // Vue 详情弹窗按原文展示且前端按 JSON.parse 解析，故出口处按实体还原。
        foreach (array('msg', 'response') as $field) {
            if (isset($apilog[$field]) && is_string($apilog[$field]) && $apilog[$field] !== '') {
                $apilog[$field] = html_entity_decode($apilog[$field], ENT_QUOTES, 'UTF-8');
            }
        }

        // 前端 fetchApiLogDetail(): Promise<ApiLogItem> 直接取 data，故平铺返回而非包 apilog 层
        $this->succ($apilog);
    }


}
