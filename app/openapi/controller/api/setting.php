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
 * WITHOUT WARRANTIES OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use app\desktop\lib\annotation\RequirePermission;

/**
 * 基础设置（openapi/admin_setting）Vue 写链路 API 控制器。
 *
 * 背景：前端页 src/views/openapi/setting/index.vue 与 finder vue_mode 早已在位，但写链路
 * 控制器在 release 同步提交 3a2dbdf526 中被删除（app/openapi/controller/api/setting.php），
 * 导致页面「添加 / 编辑 / 暂停启用」全部打不通。本文件按被删基线契约重建。
 *
 * 约定：
 *   - 读链路（list/columns/filters/cols/detail/tabs 等）统一走框架 finderDispatch，不手搓分支；
 *   - 写链路为独立 act（save / setStatus），请求体由 getRequestInput() 解析（JSON 与表单双兼容）；
 *   - 权限判断只在控制器层，权限点取自菜单 9017 permission=setting_openapi_basic；
 *   - 校验与落库统一落 openapi_capability_setting（与 legacy admin_setting 同一份语义），
 *     控制器只做「权限 / 入参 / 出参」，不碰模型；legacy 控制器冻结不改。
 *
 * 端点（app=openapi&ctl=api_setting）：
 *   GET  act=index          &action=list|columns|filters|cols|detail|tabs|favstar
 *   GET  act=getConfigLists 访问权限分组（复用 openapi_conf::getMethods）
 *   GET  act=getSetting     &s_id=<id> 编辑回填
 *   POST act=save           {s_id?, name, code, interfacekey, status, is_data_mask, config}
 *   POST act=setStatus      {s_id, status} 暂停 / 启用
 */
class openapi_ctl_api_setting extends desktop_ctl_api_base
{
    private ?openapi_capability_setting $capability = null;

    /**
     * Finder 列表：列表 / 列配置 / 筛选器 / 视图统一交给框架 vue 管线。
     */
    #[RequirePermission('setting_openapi_basic')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'             => 'openapi_mdl_setting',
            'title'                  => '基础设置',
            'orderBy'                => 's_id desc',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => true,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'actions'                => [
                ['id' => 'add', 'label' => '添加', 'action' => 'add', 'type' => 'primary'],
                [
                    // 与 legacy admin_setting（use_buildin_recycle=true）等价：删除入回收站
                    'id'      => 'delete',
                    'label'   => '删除',
                    'action'  => 'delete',
                    'type'    => 'danger',
                    'confirm' => '确定删除选中项？删除后可进入回收站恢复',
                ],
            ],
        ]);
    }

    /**
     * 表单「访问权限」分组数据。
     */
    #[RequirePermission('setting_openapi_basic')]
    public function getConfigLists(): void
    {
        $configLists = [];
        foreach (openapi_conf::getMethods() as $methodKey => $method) {
            $groupKey = empty($method['group']) ? 'openapiuse' : $method['group'];
            $configLists[$groupKey][$methodKey] = $method;
        }
        $this->respond($configLists);
    }

    /**
     * 编辑回填：单条配置详情。
     */
    #[RequirePermission('setting_openapi_basic')]
    public function getSetting(): void
    {
        $input = $this->getRequestInput();
        $sId   = $input['s_id'] ?? null;
        if (empty($sId)) {
            $this->fail('参数错误：缺少 s_id');
            return;
        }

        $settingInfo = $this->app->model('setting')->dump((int) $sId);
        if (!$settingInfo) {
            $this->fail('配置信息不存在');
            return;
        }

        $this->respond($settingInfo);
    }

    /**
     * 新增 / 编辑保存。
     */
    #[RequirePermission('setting_openapi_basic')]
    public function save(): void
    {
        $input = $this->getRequestInput();

        try {
            $this->respond($this->capability()->save(is_array($input) ? $input : []));
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
        } catch (RuntimeException $exception) {
            $this->fail($exception->getMessage());
        }
    }

    /**
     * 暂停 / 启用。
     */
    #[RequirePermission('setting_openapi_basic')]
    public function setStatus(): void
    {
        $input = $this->getRequestInput();

        try {
            $this->respond($this->capability()->setStatus((int) ($input['s_id'] ?? 0), (int) ($input['status'] ?? 0)));
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
        } catch (RuntimeException $exception) {
            $this->fail($exception->getMessage());
        }
    }

    private function capability(): openapi_capability_setting
    {
        if ($this->capability === null) {
            // 新增 app lib 文件不在框架 lib 索引内，须显式引入（否则报 Don't find lib file）
            if (!class_exists('openapi_capability_setting', false)) {
                require_once dirname(__FILE__) . '/../../lib/capability/setting.php';
            }
            $this->capability = new openapi_capability_setting();
        }

        return $this->capability;
    }
}
