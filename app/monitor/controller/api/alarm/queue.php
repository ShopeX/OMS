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
 * 队列监控 Vue API 控制器（SCRUM-7656）
 *
 * 继承 desktop_ctl_api_base，为已存在的 Vue 页面提供队列列表轮询接口。
 * 队列数据直接复用 monitor_queue::getQueues()，旧 HTML 控制器
 * app/monitor/controller/admin/alarm/queue.php 保留作回退。
 */
class monitor_ctl_api_alarm_queue extends desktop_ctl_api_base
{
    /**
     * 队列列表别名入口，转调 getQueues()
     * ?app=monitor&ctl=api_alarm_queue&act=index
     *
     * @return void
     */
    #[RequirePermission('monitor_alarm_queue')]
    public function index(): void
    {
        $this->getQueues();
    }

    /**
     * 获取队列列表（前端定时轮询）
     * ?app=monitor&ctl=api_alarm_queue&act=getQueues
     *
     * @return void
     */
    #[RequirePermission('monitor_alarm_queue')]
    public function getQueues(): void
    {
        $queues = kernel::single('monitor_queue')->getQueues();

        $this->succ(['queues' => $queues]);
    }
}
