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
 * 报价系统授权（wmsmgr / admin_smart）Vue JSON 契约
 *
 * legacy 页 `wmsmgr_ctl_admin_smart`（app/wmsmgr/controller/admin/smart.php）是
 * **Finder 列表页 Format 2**：
 *  - 工具栏 `actions`：只有「添加报价系统授权」（legacy act=add dialog 600x450）
 *  - `base_filter = channel_type='smart'`（模型 `wmsmgr_mdl_smart`，表 `sdb_channel_channel`）
 *  - `use_buildin_new_dialog=false` / `use_buildin_set_tag=false` / `use_buildin_recycle=false`
 *    / `use_buildin_export=false` / `use_buildin_import=false`（`use_buildin_filter` 未声明 ⇒ false）
 *  - 行内函数列（app/wmsmgr/lib/finder/smart.php::column_edit）：「编辑」；
 *    adapter∈{matrixwms} 时另有「申请绑定」（node_id 空）/「已绑定」（node_id 非空）
 *  - 无 `_views()` ⇒ 无 Tab
 *
 * 写链路（唯一认可链路）：Vue `ctl=api_smart&act=save`（JSON body）
 *   → 本控制器 → wmsmgr_capability_smart（校验 / 归一化 / 持久化 / 事务的唯一实现）；
 * legacy `savesmart()` 委托同一 capability（消除双写出口）。
 *
 * ⚠ 非本菜单入口（保留 legacy 专有，不由本页暴露）：
 *   `deleteChannel` / `bind_shop` / `setNodeId` / `unbind_shop` / `unbind_verify` / `finish_unbind`
 *   —— legacy 列表无删除入口（`use_buildin_recycle=false` 且 finder 无 `column_delete`），
 *   绑定副作用由外部报价系统回调触发，不属本页可操作面。
 *
 * 路由：index.php?app=wmsmgr&ctl=api_smart&act=<index|editInfo|adapterList|platformList|platformParams|save|applyRelationUrl|viewRelationUrl|genPrivateKey>
 * Vue：/m-wmsmgr/smart/index
 */
class wmsmgr_ctl_api_smart extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/wmsmgr/desktop.xml:14） */
    private const PERMISSION = 'setting_smart';

    private const OBJECT_NAME = 'wmsmgr_mdl_smart';

    private const CHANNEL_TYPE = 'smart';

    private wmsmgr_capability_smart $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new wmsmgr_capability_smart();
    }

    /**
     * 报价系统授权列表（Finder 契约：action=columns / filters / list / cols 由框架处理）
     * GET ?app=wmsmgr&ctl=api_smart&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'            => self::OBJECT_NAME,
            'title'                 => app::get('base')->_('报价系统授权'),
            'base_filter'           => ['channel_type' => self::CHANNEL_TYPE],
            'actions'               => [
                [
                    'id'    => 'add',
                    'label' => '添加报价系统授权',
                    // 新建/编辑由 FE Vue 弹窗 → act=save（capability-backed），不再走 legacy dialog
                    'type'  => 'primary',
                ],
            ],
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'orderBy'                => 'channel_id desc',
        ]);
    }

    /**
     * 编辑回填（Vue 弹窗打开时取表单字段，取库中权威行）
     * GET ?app=wmsmgr&ctl=api_smart&act=editInfo&id=<channel_id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function editInfo(): void
    {
        $channelId = (int) ($_GET['id'] ?? $_GET['channel_id'] ?? $_GET['p'][0] ?? 0);

        try {
            $result = $this->capability->loadSmart($channelId);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '读取失败', 422);
            return;
        }

        $this->succ($result);
    }

    /**
     * 系统适配器清单（legacy `_edit()` 的 `adapter_list`）
     * GET ?app=wmsmgr&ctl=api_smart&act=adapterList
     */
    #[RequirePermission(self::PERMISSION)]
    public function adapterList(): void
    {
        $this->succ(['list' => kernel::single('wmsmgr_smart_config')->getAdapterList()]);
    }

    /**
     * 授权平台清单（legacy `confightml()` 的 `platform_list`）
     * GET ?app=wmsmgr&ctl=api_smart&act=platformList&adapter=openapiwms
     */
    #[RequirePermission(self::PERMISSION)]
    public function platformList(): void
    {
        $adapter = (string) ($_GET['adapter'] ?? '');
        $this->succ(['list' => kernel::single('wmsmgr_smart_config')->getPlatformList($adapter)]);
    }

    /**
     * 授权平台参数清单（legacy `platformconfig()` 的 `platform_params`）
     * GET ?app=wmsmgr&ctl=api_smart&act=platformParams&platform=publicwms
     */
    #[RequirePermission(self::PERMISSION)]
    public function platformParams(): void
    {
        $platform = (string) ($_GET['platform'] ?? '');
        $params   = kernel::single('wmsmgr_smart_config')->getPlatformParam($platform);

        $this->succ(['params' => is_array($params) ? $params : []]);
    }

    /**
     * 新建 / 编辑保存（独立 public act）
     * POST ?app=wmsmgr&ctl=api_smart&act=save
     *      body JSON：{wms:{channel_id?,channel_bn,channel_name,adapter},config:{node_type,...}}
     */
    #[RequirePermission(self::PERMISSION)]
    public function save(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->saveSmart($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ([
            'success' => true,
            'message' => $result['message'] ?? '保存成功',
            'id'      => (int) ($result['id'] ?? 0),
        ]);
    }

    /**
     * 申请绑定关系 URL（供 FE 以 iframe 弹窗承载外部报价系统）
     * GET ?app=wmsmgr&ctl=api_smart&act=applyRelationUrl&app_id=ome&callback=..&api_url=..
     */
    #[RequirePermission(self::PERMISSION)]
    public function applyRelationUrl(): void
    {
        $appId    = (string) ($_GET['app_id'] ?? 'ome');
        $callback = (string) ($_GET['callback'] ?? '');
        $apiUrl   = (string) ($_GET['api_url'] ?? '');

        $this->succ(['url' => $this->capability->buildApplyRelationUrl($appId, $callback, $apiUrl)]);
    }

    /**
     * 查看绑定关系 URL（legacy `view_bindrelation()`）
     * GET ?app=wmsmgr&ctl=api_smart&act=viewRelationUrl
     */
    #[RequirePermission(self::PERMISSION)]
    public function viewRelationUrl(): void
    {
        $this->succ(['url' => $this->capability->buildViewRelationUrl()]);
    }

    /**
     * 生成私钥（legacy `gen_private_key()`：echo md5(uniqid())）
     * GET ?app=wmsmgr&ctl=api_smart&act=genPrivateKey
     */
    #[RequirePermission(self::PERMISSION)]
    public function genPrivateKey(): void
    {
        $this->succ(['private_key' => md5(uniqid())]);
    }
}
