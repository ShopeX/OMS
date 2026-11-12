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
 * 第三方仓储管理（wmsmgr / admin_wms）Vue JSON 契约
 *
 * legacy 页 `wmsmgr_ctl_admin_wms`（app/wmsmgr/controller/admin/wms.php）是
 * **Finder 列表页 Format 2**：
 *  - 工具栏 `actions`：「添加WMS」（FE Vue 弹窗 → act=save）、「查看绑定关系」（外部 iframe）、「删除」
 *  - `base_filter = channel_type='wms'`（模型 `wmsmgr_mdl_wms`，表 `sdb_channel_channel`）
 *  - `use_buildin_new_dialog=false` / `use_buildin_set_tag=false` / `use_buildin_recycle=false`
 *    / `use_buildin_export=false` / `use_buildin_import=false`
 *  - 行内函数列（app/wmsmgr/lib/finder/wms.php::column_edit）：编辑 + 绑定/解绑 + 初始化 + 同步基础数据
 *  - 无 `_views()` ⇒ 无 Tab
 *
 * 写链路（唯一认可链路）：Vue `ctl=api_wms&act=*`（JSON body）
 *   → 本控制器 → wmsmgr_capability_wms（校验 / 归一化 / 持久化 / 事务的唯一实现）；
 * legacy `saveWms()` / `deleteChannel()` / `doInitializeLogi()` 与 finder `detail_*` 的 $_POST 分支
 * 委托同一 capability（消除双写出口）。
 *
 * 路由：index.php?app=wmsmgr&ctl=api_wms&act=<index|editInfo|adapterList|platformList|platformParams|relationUrl|genPrivateKey|syncBaseOptions|save|deleteChannel|saveDetail*|bindShop|setNodeId|unbindShop|finishUnbind|doSyncBase>
 * Vue：/m-wmsmgr/wms/index
 */
class wmsmgr_ctl_api_wms extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/wmsmgr/desktop.xml:10） */
    private const PERMISSION = 'setting_wms';

    private const OBJECT_NAME = 'wmsmgr_mdl_wms';

    private const CHANNEL_TYPE = 'wms';

    private wmsmgr_capability_wms $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new wmsmgr_capability_wms();
    }

    /**
     * 第三方仓储列表（Finder 契约：action=columns / filters / list / cols / detail 由框架处理）
     * GET ?app=wmsmgr&ctl=api_wms&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'             => self::OBJECT_NAME,
            'title'                  => 'WMS管理',
            'base_filter'            => ['channel_type' => self::CHANNEL_TYPE],
            'actions'                => [
                [
                    'id'    => 'add',
                    'label' => '添加WMS',
                    // 新建/编辑由 FE Vue 弹窗 → act=save（capability-backed），不再走 legacy dialog
                    'type'  => 'primary',
                ],
                [
                    'id'     => 'view-bind-relation',
                    'label'  => '查看绑定关系',
                    'href'   => 'index.php?app=wmsmgr&ctl=admin_wms&act=view_bindrelation',
                    'target' => ['type' => 'dialog', 'width' => '100%', 'height' => '100%'],
                ],
                [
                    'id'      => 'delete',
                    'label'   => '删除',
                    'action'  => 'delete',
                    'confirm' => '确定删除选中项？',
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
     * GET ?app=wmsmgr&ctl=api_wms&act=editInfo&id=<channel_id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function editInfo(): void
    {
        $channelId = (int) ($_GET['id'] ?? $_GET['channel_id'] ?? $_GET['p'][0] ?? 0);

        try {
            $this->succ($this->capability->loadWms($channelId));
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '读取失败', 422);
        }
    }

    /**
     * 系统适配器清单（legacy `_edit()` 的 `adapter_list`）
     * GET ?app=wmsmgr&ctl=api_wms&act=adapterList
     */
    #[RequirePermission(self::PERMISSION)]
    public function adapterList(): void
    {
        $this->succ(['list' => kernel::single('wmsmgr_auth_config')->getAdapterList()]);
    }

    /**
     * 授权平台清单（legacy `confightml()` 的 `platform_list`）
     * GET ?app=wmsmgr&ctl=api_wms&act=platformList&adapter=openapiwms
     */
    #[RequirePermission(self::PERMISSION)]
    public function platformList(): void
    {
        $adapter = (string) ($_GET['adapter'] ?? '');
        $this->succ(['list' => kernel::single('wmsmgr_auth_config')->getPlatformList($adapter)]);
    }

    /**
     * 授权平台参数清单（legacy `platformconfig()` 的 `platform_params`）
     * GET ?app=wmsmgr&ctl=api_wms&act=platformParams&platform=publicwms
     */
    #[RequirePermission(self::PERMISSION)]
    public function platformParams(): void
    {
        $platform = (string) ($_GET['platform'] ?? '');
        $params   = kernel::single('wmsmgr_auth_config')->getPlatformParam($platform);

        $this->succ(['params' => is_array($params) ? $params : []]);
    }

    /**
     * 申请 / 查看绑定关系 iframe URL（legacy `apply_bindrelation()` / `view_bindrelation()`）
     * GET ?app=wmsmgr&ctl=api_wms&act=relationUrl&mode=apply|view
     */
    #[RequirePermission(self::PERMISSION)]
    public function relationUrl(): void
    {
        $mode = (string) ($_GET['mode'] ?? 'apply');

        $this->succ(['url' => $this->capability->relationUrl($mode, $_GET)]);
    }

    /**
     * 生成私钥（legacy `gen_private_key()`：echo md5(uniqid())）
     * GET ?app=wmsmgr&ctl=api_wms&act=genPrivateKey
     */
    #[RequirePermission(self::PERMISSION)]
    public function genPrivateKey(): void
    {
        $this->succ(['private_key' => $this->capability->genPrivateKey()]);
    }

    /**
     * 同步基础数据选项（legacy `syncBase()` 的 `base_data`）
     * GET ?app=wmsmgr&ctl=api_wms&act=syncBaseOptions&wms_id=<channel_id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function syncBaseOptions(): void
    {
        $wmsId = (int) ($_GET['wms_id'] ?? $_GET['id'] ?? $_GET['p'][0] ?? 0);

        $this->succ(['base_data' => $this->capability->syncBaseOptions($wmsId)]);
    }

    /**
     * 新建 / 编辑保存（独立 public act）
     * POST ?app=wmsmgr&ctl=api_wms&act=save
     *      body JSON：{wms:{channel_id?,channel_bn,channel_name,adapter},config:{...},shipper,divisionCode}
     */
    #[RequirePermission(self::PERMISSION)]
    public function save(): void
    {
        $input = $this->payload();

        try {
            $result = $this->capability->saveWms($input);
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
     * 删除仓储（独立 public act；legacy act=deleteChannel 委托同一 capability）
     * POST ?app=wmsmgr&ctl=api_wms&act=deleteChannel
     *      body JSON：{channel_id:[...]} 或 {selection_mode:'_ALL_',filters:{...}}
     */
    #[RequirePermission(self::PERMISSION)]
    public function deleteChannel(): void
    {
        $input = $this->payload();

        try {
            $result = $this->capability->deleteChannel($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '删除失败', 422);
            return;
        }

        $this->succ([
            'success' => true,
            'message' => $result['message'] ?? '删除成功',
        ]);
    }

    /**
     * 物流公司编码配置（独立 public act）
     * POST ?app=wmsmgr&ctl=api_wms&act=saveDetailCompany
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveDetailCompany(): void
    {
        $this->writeDetail('saveDetailCompany');
    }

    /**
     * 店铺售达方编号配置（独立 public act）
     * POST ?app=wmsmgr&ctl=api_wms&act=saveDetailShop
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveDetailShop(): void
    {
        $this->writeDetail('saveDetailShop');
    }

    /**
     * 仓库编码配置（独立 public act）
     * POST ?app=wmsmgr&ctl=api_wms&act=saveDetailBranch
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveDetailBranch(): void
    {
        $this->writeDetail('saveDetailBranch');
    }

    /**
     * 月结号配置（独立 public act）
     * POST ?app=wmsmgr&ctl=api_wms&act=saveDetailMonthaccount
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveDetailMonthaccount(): void
    {
        $this->writeDetail('saveDetailMonthaccount');
    }

    /**
     * 基础配置（独立 public act）
     * POST ?app=wmsmgr&ctl=api_wms&act=saveDetailBasic
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveDetailBasic(): void
    {
        $this->writeDetail('saveDetailBasic');
    }

    /**
     * 供应商编码配置（独立 public act）
     * POST ?app=wmsmgr&ctl=api_wms&act=saveDetailSupplier
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveDetailSupplier(): void
    {
        $this->writeDetail('saveDetailSupplier');
    }

    /**
     * 仓储绑定店铺（legacy `bind_shop()`）
     * POST ?app=wmsmgr&ctl=api_wms&act=bindShop
     */
    #[RequirePermission(self::PERMISSION)]
    public function bindShop(): void
    {
        $input = $this->payload();

        try {
            $result = $this->capability->bindShop($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '绑定失败', 422);
            return;
        }

        $this->succ(['success' => true, 'message' => $result['message'] ?? '绑定成功']);
    }

    /**
     * 填写 node_id 绑定（legacy `setNodeId()`）
     * POST ?app=wmsmgr&ctl=api_wms&act=setNodeId
     */
    #[RequirePermission(self::PERMISSION)]
    public function setNodeId(): void
    {
        $input = $this->payload();

        try {
            $result = $this->capability->setNodeId($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '绑定失败', 422);
            return;
        }

        $this->succ(['success' => true, 'message' => $result['message'] ?? '绑定成功']);
    }

    /**
     * 仓储解绑店铺（legacy `unbind_shop()`）
     * POST ?app=wmsmgr&ctl=api_wms&act=unbindShop
     */
    #[RequirePermission(self::PERMISSION)]
    public function unbindShop(): void
    {
        $input = $this->payload();

        try {
            $result = $this->capability->unbindShop($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '解绑失败', 422);
            return;
        }

        $this->succ(['success' => true, 'message' => $result['message'] ?? '解绑成功']);
    }

    /**
     * 最终解除绑定（legacy `finish_unbind()`）
     * POST ?app=wmsmgr&ctl=api_wms&act=finishUnbind
     */
    #[RequirePermission(self::PERMISSION)]
    public function finishUnbind(): void
    {
        $input = $this->payload();

        try {
            $result = $this->capability->finishUnbind($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '解除绑定失败', 422);
            return;
        }

        $this->succ(['success' => true, 'message' => $result['message'] ?? '解除绑定成功']);
    }

    /**
     * 同步基础数据（legacy `doSyncBase()`）：外部同步，只做参数校验与分派
     * POST ?app=wmsmgr&ctl=api_wms&act=doSyncBase
     */
    #[RequirePermission(self::PERMISSION)]
    public function doSyncBase(): void
    {
        $input = $this->payload();

        try {
            $result = $this->capability->doSyncBase($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '同步失败', 422);
            return;
        }

        $this->succ(['success' => true, 'message' => $result['message'] ?? 'ok.']);
    }

    /**
     * 表单类写 act 的统一入口：解析 JSON body → capability → 统一 succ。
     * controller 只做入参解析与响应格式化；校验/事务在 capability。
     */
    private function writeDetail(string $capabilityMethod): void
    {
        $input = $this->payload();

        try {
            /** @var array{message?:string} $result */
            $result = $this->capability->{$capabilityMethod}($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ(['success' => true, 'message' => $result['message'] ?? '保存成功']);
    }

    /**
     * 写 act 入参：优先 JSON body，回退 request（GET + form）解析。
     */
    private function payload(): array
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        return is_array($input) ? $input : [];
    }
}
