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
 * 通知日志 Vue API 控制器（SCRUM-7646）
 *
 * 继承 desktop_ctl_api_base，为前端 Vue 页面提供 finder 列表/详情分发与批量重发接口。
 * 旧 HTML 控制器 app/monitor/controller/admin/alarm/notify.php 保留作回退。
 */
class monitor_ctl_api_alarm_notify extends desktop_ctl_api_base
{
    protected static $objectName = 'monitor_mdl_event_notify';

    /**
     * finder 分发入口（list/columns/filters/tabs/detail/cols 等 finder 动作）
     * ?app=monitor&ctl=api_alarm_notify&act=index&action=list|columns|filters|detail|...
     *
     * @return void
     */
    #[RequirePermission('alarm_notify')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'          => 'monitor_mdl_event_notify',
            'finderAlias'         => 'vue',
            'title'               => '通知日志',
            'orderBy'             => 'notify_id DESC',
            'actions'             => [
                ['label' => '批量重发', 'id' => 'batchSend', 'type' => 'default'],
            ],
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter'  => true,
        ]);
    }

    /**
     * 批量重发通知（复刻旧控制器 doBatchSend 逻辑，改为标准 JSON 响应）
     * ?app=monitor&ctl=api_alarm_notify&act=doBatchSend
     *
     * @return void
     */
    #[RequirePermission('alarm_notify')]
    public function doBatchSend(): void
    {
        $input = $this->getRequestInput();

        // 兼容前端 primary_id（逗号串）与 legacy notify_id（数组）两种入参
        $notifyIds = [];
        if (!empty($input['primary_id'])) {
            $notifyIds = explode(',', (string) $input['primary_id']);
        } elseif (!empty($input['notify_id'])) {
            $notifyIds = (array) $input['notify_id'];
        }

        // 去重、转整型并过滤非法值（legacy 对空串未过滤，属缺陷不照抄）
        $notifyIds = array_map('intval', $notifyIds);
        $notifyIds = array_filter($notifyIds, static function ($id) {
            return $id > 0;
        });
        $notifyIds = array_values(array_unique($notifyIds));

        if (empty($notifyIds)) {
            $this->fail('请先选择重试数据', 422);
            return;
        }

        $retArr = [
            'itotal'  => count($notifyIds),
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => [],
        ];

        foreach ($notifyIds as $id) {
            $msg = '';
            $rs  = kernel::single('monitor_autotask_timer_sendnotify')->process(['notify_id' => $id, 'is_retry' => true], $msg);
            if ($rs) {
                $retArr['isucc']++;
            } else {
                $retArr['ifail']++;
                $retArr['err_msg'][] = $msg;
            }
        }

        $this->succ($retArr);
    }

    /**
     * 事件类型映射（event_type 原值 → 中文名），供前端详情展示映射
     * ?app=monitor&ctl=api_alarm_notify&act=getEventType
     *
     * @return void
     */
    #[RequirePermission('alarm_notify')]
    public function getEventType(): void
    {
        $eventTemplateLib = kernel::single('monitor_event_template');
        $this->succ(['eventType' => $eventTemplateLib->getEventType()]);
    }
}
