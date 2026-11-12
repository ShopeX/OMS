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
 * 邮件组配置 Vue API 控制器（SCRUM-7646）
 *
 * 继承 desktop_ctl_api_base，为前端 Vue 页面提供 finder 列表分发、双表事务保存、
 * 编辑前全量加载与下拉数据接口。旧 HTML 控制器
 * app/monitor/controller/admin/alarm/group.php 保留作回退。
 */
class monitor_ctl_api_alarm_group extends desktop_ctl_api_base
{
    protected static $objectName = 'monitor_mdl_event_group';

    /**
     * finder 分发入口（list/columns/filters/tabs/detail/delete/cols 等 finder 动作）
     * ?app=monitor&ctl=api_alarm_group&act=index&action=list|columns|filters|detail|...
     *
     * @return void
     */
    #[RequirePermission('alarm_group')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'          => 'monitor_mdl_event_group',
            'finderAlias'         => 'vue',
            'actions'             => [
                ['label' => '新增', 'id' => 'add', 'type' => 'primary'],
            ],
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => true,
            'use_buildin_filter'  => true,
        ]);
    }

    /**
     * 新增/编辑保存（显式事务跨 event_group 主表与 event_group_template 侧表写入）
     * ?app=monitor&ctl=api_alarm_group&act=save
     *
     * @return void
     */
    #[RequirePermission('alarm_group')]
    public function save(): void
    {
        $input       = $this->getRequestInput();
        $groupName   = isset($input['group_name']) ? trim((string) $input['group_name']) : '';
        $groupId     = isset($input['group_id']) ? intval($input['group_id']) : 0;
        $receiverIds = $this->joinCsv($input['receiver_id'] ?? '');
        $orgIds      = $this->joinCsv($input['org_id'] ?? '');
        $eventTypes  = $this->joinCsv($input['event_type'] ?? '');
        $disabled    = (isset($input['disabled']) && in_array($input['disabled'], ['true', true, 1, '1'], true)) ? 'true' : 'false';

        // 补 legacy 缺失的服务端校验；org_id / event_type 允许为空（与 legacy 行为一致）
        if ($groupName === '') {
            $this->fail('请输入邮件组名称', 422);
            return;
        }
        if ($receiverIds === '') {
            $this->fail('请选择收件人', 422);
            return;
        }

        $eventGroupMdl     = app::get('monitor')->model('event_group');
        $eventGroupTempMdl = app::get('monitor')->model('event_group_template');
        $db                = kernel::database();
        $db->beginTransaction();
        try {
            $groupData = [
                'group_name'  => $groupName,
                'receiver_id' => $receiverIds,
                'org_id'      => $orgIds,
                'disabled'    => $disabled,
            ];
            if ($groupId > 0) {
                $groupData['group_id'] = $groupId;
            }

            $res = $eventGroupMdl->save($groupData);
            if (!$res) {
                $db->rollBack();
                $this->fail('更新失败：' . $groupName, 422);
                return;
            }

            // 新增时由 model->save() 回填自增主键；为空则回滚，避免写入孤儿侧表
            $savedGroupId = isset($groupData['group_id']) ? intval($groupData['group_id']) : 0;
            if ($savedGroupId <= 0) {
                $db->rollBack();
                $this->fail('保存失败', 500);
                return;
            }

            // 侧表 group_id 为 varchar(10)，写入前转字符串；pkey 即 group_id，save 为 upsert
            $templateData = [
                'group_id'   => (string) $savedGroupId,
                'event_type' => $eventTypes,
            ];
            $tempRes = $eventGroupTempMdl->save($templateData);
            if (!$tempRes) {
                $db->rollBack();
                $this->fail('保存失败', 500);
                return;
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $this->fail('保存失败', 500);
            return;
        }

        $this->succ(['message' => '保存成功', 'group_id' => $savedGroupId]);
    }

    /**
     * 编辑前全量加载（主表字段 + 侧表 event_type 拆分为数组）
     * ?app=monitor&ctl=api_alarm_group&act=getGroup&id=<group_id>
     *
     * @return void
     */
    #[RequirePermission('alarm_group')]
    public function getGroup(): void
    {
        $input = $this->getRequestInput();
        $id    = isset($input['id']) ? intval($input['id']) : 0;
        if ($id <= 0) {
            $id = isset($input['group_id']) ? intval($input['group_id']) : 0;
        }
        if ($id <= 0) {
            $id = (int) ($_GET['p'][0] ?? 0);
        }
        if ($id <= 0) {
            $this->fail('group_id is required');
            return;
        }

        $eventGroupMdl     = app::get('monitor')->model('event_group');
        $eventGroupTempMdl = app::get('monitor')->model('event_group_template');

        $row = $eventGroupMdl->db_dump(['group_id' => $id], '*');
        if (empty($row)) {
            $this->fail('邮件组不存在');
            return;
        }

        // 多值字段拆分为数组，空值统一返回 [] 而非 ['']
        $event                  = $eventGroupTempMdl->db_dump(['group_id' => $id]);
        $row['event_type']      = !empty($event['event_type']) ? explode(',', $event['event_type']) : [];
        $row['receiver_id']     = !empty($row['receiver_id']) ? explode(',', $row['receiver_id']) : [];
        $row['org_id']          = !empty($row['org_id']) ? explode(',', $row['org_id']) : [];

        $this->succ(['group' => $row]);
    }

    /**
     * 触发事件下拉数据（event_type → 中文名，转 el-select 所需 {value,label}）
     * ?app=monitor&ctl=api_alarm_group&act=getEventType
     *
     * @return void
     */
    #[RequirePermission('alarm_group')]
    public function getEventType(): void
    {
        $eventType = kernel::single('monitor_event_template')->getEventType();
        $options   = [];
        foreach ($eventType as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        $this->succ(['eventType' => $options]);
    }

    /**
     * 运营组织下拉数据
     * ?app=monitor&ctl=api_alarm_group&act=getOrgList
     *
     * @return void
     */
    #[RequirePermission('alarm_group')]
    public function getOrgList(): void
    {
        $operationOrg = app::get('ome')->model('operation_organization')->getList('org_id,name', ['status' => '1']);
        $this->succ(['orgList' => $operationOrg]);
    }

    /**
     * 收件人下拉数据
     * ?app=monitor&ctl=api_alarm_group&act=getReceiverList
     *
     * @return void
     */
    #[RequirePermission('alarm_group')]
    public function getReceiverList(): void
    {
        $receiverList = app::get('monitor')->model('event_receiver')->getList('id,receiver');
        $this->succ(['receiverList' => $receiverList]);
    }

    /**
     * 将数组或逗号串归一化为逗号分隔字符串（过滤空值）。
     *
     * @param mixed $value 待归一化的数组或标量
     * @return string
     */
    protected function joinCsv($value): string
    {
        if (is_array($value)) {
            $value = array_filter($value, static function ($item) {
                return $item !== '' && $item !== null;
            });
            return implode(',', $value);
        }

        return trim((string) $value);
    }
}
