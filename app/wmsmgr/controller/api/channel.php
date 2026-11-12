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
 * 渠道管理列表（wmsmgr / admin_channel）Vue JSON 契约
 *
 * legacy 页 `wmsmgr_ctl_admin_channel`（app/wmsmgr/controller/admin/channel.php）是
 * **Finder 列表页 Format 2**：
 *  - 工具栏 `actions`：**仅当 `wmsmgr_channel::checkAddChannel()` 为真**才下发「新增渠道」
 *    （legacy act=add dialog 600x300，表单页 `add_channel.html`）
 *  - `use_buildin_recycle=false` / `use_buildin_selectrow=false`；`orderBy='id ASC'`
 *  - 无 base_filter / 无 `_views()`（无 Tab）/ 无导出 / 无导入
 *  - 行内函数列（app/wmsmgr/lib/finder/channel.php::column_edit）：
 *    「初始化」（legacy dialog → admin_wms&act=yjdf_sync_material）
 *    + 「删除」（**仅当该渠道无关联商品**时才出现）
 *    ⚠ legacy 列表**没有「编辑」入口**（`edit()` 方法存在但列表无链接）⇒ 本页不暴露编辑。
 *
 * 写链路（唯一认可链路）：Vue `ctl=api_channel&act=save|delChannel`（JSON body）
 *   → 本控制器 → wmsmgr_capability_channel（校验 / 归一化 / 持久化 的唯一实现）；
 *   legacy `saveChannel()` / `delChannel()` 委托同一 capability（消除双写出口）。
 *
 * 路由：index.php?app=wmsmgr&ctl=api_channel&act=<index|wmsOptions|editInfo|save|delChannel>
 * Vue：/m-wmsmgr/channel/index
 */
class wmsmgr_ctl_api_channel extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/wmsmgr/desktop.xml:19） */
    private const PERMISSION = 'channel_list';

    private const OBJECT_NAME = 'wmsmgr_mdl_channel';

    private wmsmgr_capability_channel $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new wmsmgr_capability_channel();
    }

    /**
     * 渠道管理列表（Finder 契约：action=columns / filters / list / cols 由框架处理）
     * GET ?app=wmsmgr&ctl=api_channel&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        // 工具栏「新增渠道」受授权服务门控（legacy index() 同款）
        $actions = array();
        if ($this->capability->canAddChannel()) {
            $actions[] = array(
                'id'    => 'add',
                'label' => '新增渠道',
                // 新建由 FE Vue 弹窗 → act=save（capability-backed），不再走 legacy dialog
                'type'  => 'primary',
            );
        }

        $this->finderDispatch(array(
            'objectName'             => self::OBJECT_NAME,
            'title'                  => app::get('base')->_('渠道管理列表'),
            'actions'                => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_selectrow'  => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'orderBy'                => 'id ASC',
        ));
    }

    /**
     * 关联第三方仓储下拉（legacy `_edit()` 的 `$wms`）
     * GET ?app=wmsmgr&ctl=api_channel&act=wmsOptions
     */
    #[RequirePermission(self::PERMISSION)]
    public function wmsOptions(): void
    {
        $this->succ(array('list' => $this->capability->listWmsOptions()));
    }

    /**
     * 编辑回填（legacy `_edit($id)` 的 `$channel`）
     * GET ?app=wmsmgr&ctl=api_channel&act=editInfo&id=<id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function editInfo(): void
    {
        $id = (int) ($_GET['id'] ?? $_GET['p'][0] ?? 0);

        try {
            $this->succ($this->capability->loadChannel($id));
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '读取失败', 422);
        }
    }

    /**
     * 新建渠道（独立 public act；legacy act=saveChannel 委托同一 capability）
     * POST ?app=wmsmgr&ctl=api_channel&act=save
     *      body JSON：{channel:{channel_id,channel_name,wms_id}}
     */
    #[RequirePermission(self::PERMISSION)]
    public function save(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->saveChannel($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ(array(
            'success'    => true,
            'message'    => $result['message'] ?? '保存成功',
            'id'         => (int) ($result['id'] ?? 0),
            'channel_id' => (string) ($result['channel_id'] ?? ''),
        ));
    }

    /**
     * 删除渠道（独立 public act；legacy act=delChannel 委托同一 capability）
     * POST ?app=wmsmgr&ctl=api_channel&act=delChannel
     *      body JSON：{channel_id:"..."}
     */
    #[RequirePermission(self::PERMISSION)]
    public function delChannel(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }
        $channelId = trim((string) ($input['channel_id'] ?? $_GET['channel_id'] ?? ''));

        try {
            $result = $this->capability->deleteChannel($channelId);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '删除失败', 422);
            return;
        }

        $this->succ(array(
            'success' => true,
            'message' => $result['message'] ?? '删除渠道成功',
        ));
    }
}
