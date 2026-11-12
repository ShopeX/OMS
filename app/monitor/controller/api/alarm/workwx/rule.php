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
 * 企微通知规则 Vue API 控制器（SCRUM-7656）
 *
 * 继承 desktop_ctl_api_base，为前端 Vue 页面提供 finder 列表分发、编辑前原始全量回填、
 * 表单选项、保存与门店条件生成接口。保存业务逻辑整体复用 monitor_workwx_config::saveRule()，
 * 旧 HTML 控制器 app/monitor/controller/admin/alarm/workwx/rule.php 保留作回退。
 */
class monitor_ctl_api_alarm_workwx_rule extends desktop_ctl_api_base
{
    protected static $objectName = 'monitor_mdl_workwx_rule';

    /**
     * finder 分发入口（list/columns/filters/tabs/detail/delete/cols 等 finder 动作）
     * ?app=monitor&ctl=api_alarm_workwx_rule&act=index&action=list|columns|filters|detail|...
     *
     * @return void
     */
    #[RequirePermission('alarm_workwx_rule')]
    public function index(): void
    {
        // legacy finder 的 column_edit 会读取 _finder.finder_id 生成操作链接，
        // Vue finder 分发不带该参数，补默认值避免 PHP notice（不改动 finder 本身）。
        if (!isset($_GET['_finder']['finder_id'])) {
            $_GET['_finder']['finder_id'] = 'vue';
        }

        $this->finderDispatch([
            'objectName'          => 'monitor_mdl_workwx_rule',
            'finderAlias'         => 'vue',
            'title'               => '企微通知规则',
            'actions'             => [
                ['label' => '新增', 'id' => 'add', 'type' => 'primary'],
            ],
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => true,
            'use_buildin_filter'  => true,
        ]);
    }

    /**
     * 按主键加载规则原始全量数据（webhook 不脱敏，供编辑回填）
     * ?app=monitor&ctl=api_alarm_workwx_rule&act=getRule&rule_id=<id>
     *
     * @return void
     */
    #[RequirePermission('alarm_workwx_rule')]
    public function getRule(): void
    {
        $input  = $this->getRequestInput();
        $ruleId = isset($input['rule_id']) ? intval($input['rule_id']) : 0;
        if ($ruleId <= 0) {
            $ruleId = (int) ($_GET['p'][0] ?? 0);
        }
        if ($ruleId <= 0) {
            $this->fail('rule_id is required');
            return;
        }

        // db_dump 直读数据表原始值，绕过 model 的 maskWebhook modifier；
        // 编辑回填必须是完整 webhook，否则脱敏串写回库会导致企微预警发送失败。
        $ruleMdl = app::get('monitor')->model('workwx_rule');
        $row     = $ruleMdl->db_dump(['rule_id' => $ruleId], '*');
        if (empty($row)) {
            $this->fail('企微通知规则不存在');
            return;
        }

        $row['event_type'] = !empty($row['event_types']) ? explode(',', (string) $row['event_types']) : [];

        // config 为 serialize 字段，兼容 db_dump 返回原串或数组两种情况
        $config = $row['config'] ?? [];
        if (is_string($config)) {
            $config = @unserialize($config);
        }
        $roles = (is_array($config) && !empty($config['roles']) && is_array($config['roles']))
            ? array_values($config['roles'])
            : [];
        $row['roles'] = $roles;

        // 逐字兼容旧页 ||| 分隔 JSON 串契约，前端可直接拼接后保存
        $roleParts = [];
        foreach ($roles as $role) {
            $roleParts[] = json_encode($role, JSON_UNESCAPED_UNICODE);
        }
        $row['roles_string'] = implode('|||', $roleParts);

        // 旧页条件列表结构（uid=m_item_N），复用既有构造方法
        $row['role_items'] = kernel::single('monitor_workwx_config')->getRuleRoleItems($ruleId);

        $this->succ(['rule' => $row]);
    }

    /**
     * 企微规则表单选项（触发事件 / 门店 / 已占用门店 / 已选门店 / 条件类型）
     * ?app=monitor&ctl=api_alarm_workwx_rule&act=getOptions&rule_id=<id>
     *
     * @return void
     */
    #[RequirePermission('alarm_workwx_rule')]
    public function getOptions(): void
    {
        $input        = $this->getRequestInput();
        $ruleId       = isset($input['rule_id']) ? intval($input['rule_id']) : 0;
        $configLib    = kernel::single('monitor_workwx_config');
        $eventTypeMap = $configLib->getWorkwxEventTypes();

        // 编辑时补齐已选但当前模板已下架的触发事件，避免回填丢失
        if ($ruleId > 0) {
            $ruleRow = app::get('monitor')->model('workwx_rule')->db_dump(['rule_id' => $ruleId], 'event_types');
            if (!empty($ruleRow['event_types'])) {
                $allEventMap = kernel::single('monitor_event_template')->getEventType();
                foreach (explode(',', (string) $ruleRow['event_types']) as $type) {
                    $type = trim($type);
                    if ($type !== '' && !isset($eventTypeMap[$type])) {
                        $eventTypeMap[$type] = isset($allEventMap[$type]) ? $allEventMap[$type] : $type;
                    }
                }
            }
        }

        $eventTypes = [];
        foreach ($eventTypeMap as $value => $label) {
            $eventTypes[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        $conditionTypes = [];
        foreach (kernel::single('monitor_workwx_rule_type')->getTypeOptions() as $value => $label) {
            $conditionTypes[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        $this->succ([
            'eventType'        => $eventTypes,
            'storeList'        => $configLib->getStoreOptions(),
            'occupiedStoreMap' => $configLib->getOccupiedStoreMap($ruleId),
            'selectedStoreIds' => $configLib->getRuleStoreIds($ruleId),
            'conditionTypes'   => $conditionTypes,
            'o2oInstalled'     => app::get('o2o')->is_installed(),
        ]);
    }

    /**
     * 新增/编辑保存（整体复用 saveRule：服务端校验 + 门店唯一校验 + 显式事务）
     * ?app=monitor&ctl=api_alarm_workwx_rule&act=save
     *
     * @return void
     */
    #[RequirePermission('alarm_workwx_rule')]
    public function save(): void
    {
        $input = $this->getRequestInput();

        // 兼容 JSON/form 两种提交：event_type 归一为数组，roles 归一为 legacy ||| 分隔 JSON 串
        if (isset($input['event_type']) && !is_array($input['event_type'])) {
            $input['event_type'] = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $input['event_type'])
            )));
        }
        if (isset($input['roles']) && is_array($input['roles'])) {
            $roleParts = [];
            foreach ($input['roles'] as $role) {
                $roleParts[] = is_string($role) ? $role : json_encode($role, JSON_UNESCAPED_UNICODE);
            }
            $input['roles'] = implode('|||', $roleParts);
        }
        if (isset($input['disabled']) && in_array($input['disabled'], [true, 1, '1'], true)) {
            $input['disabled'] = 'true';
        } elseif (isset($input['disabled']) && in_array($input['disabled'], [false, 0, '0'], true)) {
            $input['disabled'] = 'false';
        }

        // saveRule() 直接读取这些键，补齐默认值避免未提交字段触发 PHP notice
        $input['rule_id']        = isset($input['rule_id']) ? intval($input['rule_id']) : 0;
        $input['rule_name']      = isset($input['rule_name']) ? (string) $input['rule_name'] : '';
        $input['workwx_webhook'] = isset($input['workwx_webhook']) ? (string) $input['workwx_webhook'] : '';
        $input['event_type']     = $input['event_type'] ?? [];
        $input['roles']          = $input['roles'] ?? '';
        $input['org_id']         = $input['org_id'] ?? '';
        $input['weight']         = $input['weight'] ?? 0;
        $input['memo']           = $input['memo'] ?? '';
        $input['disabled']       = $input['disabled'] ?? 'false';

        $errMsg = '';
        $ruleId = kernel::single('monitor_workwx_config')->saveRule($input, $errMsg);
        if ($ruleId === false) {
            $this->fail($errMsg !== '' ? $errMsg : '保存失败', 422);
            return;
        }

        $this->succ(['message' => '保存成功', 'rule_id' => intval($ruleId)]);
    }

    /**
     * 生成单条门店通知条件（替代 legacy act=ajax 的 HTML 片段，返回结构化 JSON）
     * ?app=monitor&ctl=api_alarm_workwx_rule&act=createCondition
     *
     * @return void
     */
    #[RequirePermission('alarm_workwx_rule')]
    public function createCondition(): void
    {
        $input  = $this->getRequestInput();
        $typeId = strtolower(trim((string) ($input['type_id'] ?? '')));
        if ($typeId === '') {
            $this->fail('请选择通知条件类型', 422);
            return;
        }

        // store_id 兼容数组与逗号串；rule_id 用于排除本规则自身的门店占用
        if (!empty($input['store_id']) && !is_array($input['store_id'])) {
            $input['store_id'] = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $input['store_id'])
            )));
        }
        $input['store_id'] = $input['store_id'] ?? [];
        $input['rule_id']  = isset($input['rule_id']) ? intval($input['rule_id']) : 0;

        $ruleType = kernel::single('monitor_workwx_rule_type');
        $check    = $ruleType->doMethod($typeId, 'checkParams', $input);
        if ($check !== true) {
            $this->fail(is_string($check) ? $check : '通知条件校验失败', 422);
            return;
        }

        $roleJson = $ruleType->doMethod($typeId, 'roleToString', $input);
        $role     = is_string($roleJson) ? json_decode($roleJson, true) : null;
        if (!is_array($role)) {
            $this->fail('生成通知条件失败', 422);
            return;
        }

        $this->succ([
            // 保留 legacy {code,msg} 口径，同时给出结构化 role 供 Vue 直接使用
            'code'      => 'SUCC',
            'msg'       => $roleJson,
            'role'      => $role,
            'role_json' => $roleJson,
            'caption'   => isset($role['caption']) ? $role['caption'] : '',
        ]);
    }
}
