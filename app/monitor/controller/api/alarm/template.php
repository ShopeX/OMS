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
 * 预警模板 Vue API 控制器（SCRUM-7639）
 *
 * 继承 desktop_ctl_api_base，为前端 Vue 页面提供 finder 列表/保存/详情/事件类型等接口。
 * 旧 HTML 控制器 app/monitor/controller/admin/alarm/template.php 保留作回退。
 */
class monitor_ctl_api_alarm_template extends desktop_ctl_api_base
{
    protected static $objectName = 'monitor_mdl_event_template';

    /**
     * finder 分发入口（list/columns/filters/tabs/detail 等 finder 动作）
     * ?app=monitor&ctl=api_alarm_template&act=index&action=list|columns|filters|detail|...
     */
    #[RequirePermission('alarm_template')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'           => 'monitor_mdl_event_template',
            'finderAlias'          => 'vue',
            'actions'              => [
                ['label' => '新增', 'id' => 'add', 'type' => 'primary'],
            ],
            'use_buildin_set_tag'  => false,
            'use_buildin_recycle'  => false,
        ]);
    }

    /**
     * 新增/编辑保存（六类校验 + normalizeTemplateConfig + 显式事务）
     * 校验文案与旧 HTML 页 save() 完全一致。
     */
    #[RequirePermission('alarm_template')]
    public function save(): void
    {
        $data             = $this->getRequestInput();
        $eventTemplateMdl = app::get('monitor')->model('event_template');
        $eventTemplateLib = kernel::single('monitor_event_template');

        // ① 模板名称 + 模板编号非空
        if (empty($data['template_bn']) || empty($data['template_name'])) {
            $this->fail('请填写模板名称和模板编号，请检查!', 422);
            return;
        }

        // ② 模板内容非空
        if (empty($data['content'])) {
            $this->fail('模板内容不能为空，请检查!', 422);
            return;
        }
        $data['content'] = str_replace('<script>', '', $data['content']);
        $data['content'] = str_replace('</script>', '', $data['content']);

        if (!empty($data['template_id'])) {
            $data['template_id'] = intval($data['template_id']);
        }

        $eventType = isset($data['event_type']) ? $data['event_type'] : '';
        $sendType  = isset($data['send_type']) ? $data['send_type'] : 'email';

        // 失败订单批量通知：锁定邮箱
        if ($eventType === 'order_fail_notify') {
            $sendType = 'email';
            $data['send_type'] = 'email';
        }

        // ③ 模板类型不能重复（event_type + send_type），仅新增时校验
        if (empty($data['template_id'])) {
            $row = $eventTemplateMdl->db_dump(array(
                'event_type' => $eventType,
                'send_type'  => $sendType,
            ));
            if ($row) {
                $this->fail('模板类型不能重复，请检查!', 422);
                return;
            }
        }

        // ④ 模板编码不能重复
        $row = $eventTemplateMdl->db_dump(array('template_bn' => $data['template_bn']), '*');
        if ($row) {
            if (!empty($data['template_id']) && ($row['template_id'] != $data['template_id'])) {
                $this->fail('模板编码不能重复，请检查!', 422);
                return;
            } elseif (empty($data['template_id'])) {
                $this->fail('模板编码不能重复，请检查!', 422);
                return;
            }
        }

        // ⑤ 三类配置（remind_days 1~365 / 至少选一个平台）校验 + 规范化；含 order_fail_notify 频次/时刻
        $configResult = $eventTemplateLib->normalizeTemplateConfig(
            $eventType,
            isset($data['template_config']) ? $data['template_config'] : array()
        );
        if (is_string($configResult)) {
            $this->fail($configResult, 422);
            return;
        }
        if ($eventType === 'order_fail_notify') {
            $oldConfig = array();
            $oldDisabled = 'true';
            if (!empty($data['template_id'])) {
                $oldRow = $eventTemplateMdl->db_dump(array('template_id' => intval($data['template_id'])), 'template_config,disabled');
                if ($oldRow) {
                    $oldDisabled = isset($oldRow['disabled']) ? $oldRow['disabled'] : 'true';
                    $oldConfig = $oldRow['template_config'];
                    if (is_string($oldConfig)) {
                        $un = @unserialize($oldConfig);
                        $oldConfig = is_array($un) ? $un : array();
                    }
                    if (!is_array($oldConfig)) {
                        $oldConfig = array();
                    }
                }
            }
            $disabled = isset($data['disabled']) ? $data['disabled'] : 'false';
            $configResult = $eventTemplateLib->mergeOrderFailNotifyRuntimeConfig(
                $configResult,
                $oldConfig,
                $disabled,
                $oldDisabled
            );
        }
        $data['template_config'] = $configResult;

        // ⑥ 显式事务包裹写操作（G5 修复）
        $db = kernel::database();
        $db->beginTransaction();
        try {
            $result = $eventTemplateMdl->save($data);
            if (!$result) {
                $db->rollBack();
                $this->fail('保存失败', 422);
                return;
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $this->fail('保存失败', 500);
            return;
        }

        $templateId = isset($data['template_id']) ? intval($data['template_id']) : 0;
        $this->succ(array('message' => '保存成功', 'template_id' => $templateId));
    }

    /**
     * 编辑前全量加载（含 template_config 反序列化）
     * ?app=monitor&ctl=api_alarm_template&act=get&template_id=<id>
     */
    #[RequirePermission('alarm_template')]
    public function get(): void
    {
        $input = $this->getRequestInput();
        $id    = isset($input['template_id']) ? intval($input['template_id']) : 0;
        if ($id <= 0) {
            $id = (int) ($_GET['p'][0] ?? 0);
        }
        if ($id <= 0) {
            $this->fail('template_id is required');
            return;
        }

        $eventTemplateMdl = app::get('monitor')->model('event_template');
        $row              = $eventTemplateMdl->dump(array('template_id' => $id), '*');
        if (empty($row)) {
            $this->fail('模板不存在');
            return;
        }

        // 反序列化 template_config（serialize 类型），is_array 兜底
        if (isset($row['template_config']) && is_string($row['template_config'])) {
            $unserialized = @unserialize($row['template_config']);
            if (is_array($unserialized)) {
                $row['template_config'] = $unserialized;
            }
        }
        if (!isset($row['template_config']) || !is_array($row['template_config'])) {
            $row['template_config'] = array();
        }

        $this->succ(array('info' => $row));
    }

    /**
     * 事件类型映射 + 三类平台清单 + 默认内容（T2，一次性给前端配置块）
     * ?app=monitor&ctl=api_alarm_template&act=getEventType
     */
    #[RequirePermission('alarm_template')]
    public function getEventType(): void
    {
        $eventTemplateLib = kernel::single('monitor_event_template');
        $this->succ(array(
            'eventType'             => $eventTemplateLib->getEventType(),
            'eventDefaultContent'   => $eventTemplateLib->getEventDefaultContent(),
            'authExpirePlatforms'   => $eventTemplateLib->getAuthExpirePlatforms(),
            'tokenInvalidPlatforms' => $eventTemplateLib->getTokenInvalidPlatforms(),
            'tokenRecoverPlatforms' => $eventTemplateLib->getTokenRecoverPlatforms(),
        ));
    }

    /**
     * 各事件类型的默认模板内容
     * ?app=monitor&ctl=api_alarm_template&act=getEventDefaultContent
     */
    #[RequirePermission('alarm_template')]
    public function getEventDefaultContent(): void
    {
        $eventTemplateLib = kernel::single('monitor_event_template');
        $this->succ(array('content' => $eventTemplateLib->getEventDefaultContent()));
    }
}
