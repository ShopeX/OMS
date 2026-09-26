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

/**
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/18
 * @Describe: 预警通知邮件发送
 */
class monitor_event_trigger_notify_workwx extends monitor_event_trigger_notify_common
{
    public function send($notifyInfo)
    {
        if (!$notifyInfo['send_content']) {
            return ['rsp' => 'fail', 'msg' => '发送失败，发送内容为空'];
        }
        if ($notifyInfo['status'] == '1') {
            return ['rsp' => 'fail', 'msg' => '已发送不能重复发送'];
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];
        $data = [
            'msgtype'  => 'markdown',
            'markdown' => [
                'content' => $notifyInfo['send_content'],
            ],
        ];

        $params = array();
        if (!empty($notifyInfo['params'])) {
            $params = json_decode($notifyInfo['params'], true);
            if (!is_array($params)) {
                $params = array();
            }
        }

        $ruleWebhook = kernel::single('monitor_workwx_router')->resolveWebhook(
            $notifyInfo['event_type'],
            $params
        );
        $workwx  = app::get('monitor')->getConf('workwx.config');
        $webhook = $ruleWebhook ?: ($workwx['webhook'] ?? '');
        if (!$webhook) {
            return ['rsp' => 'fail', 'msg' => '缺少webhook地址'];
        }

        try {
            $response = $this->curl($webhook, json_encode($data, JSON_UNESCAPED_UNICODE), $headers);
            $response = json_decode($response, true);

            return ['rsp' => $response['errcode'] == '0' ? 'succ' : 'fail', 'msg' => $response['errmsg']];
        } catch (Exception $e) {
            return ['rsp' => 'fail', 'msg' => $e->getMessage()];
        }
    }
}
