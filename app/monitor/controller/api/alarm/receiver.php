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
 * 收件人配置 Vue API 控制器（SCRUM-7646）
 *
 * 继承 desktop_ctl_api_base，为前端 Vue 页面提供 finder 列表分发、保存与编辑前全量加载接口。
 * 旧 HTML 控制器 app/monitor/controller/admin/alarm/receiver.php 保留作回退。
 */
class monitor_ctl_api_alarm_receiver extends desktop_ctl_api_base
{
    protected static $objectName = 'monitor_mdl_event_receiver';

    /**
     * finder 分发入口（list/columns/filters/tabs/detail/delete/cols 等 finder 动作）
     * ?app=monitor&ctl=api_alarm_receiver&act=index&action=list|columns|filters|detail|...
     *
     * @return void
     */
    #[RequirePermission('alarm_receiver')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'          => 'monitor_mdl_event_receiver',
            'finderAlias'         => 'vue',
            'title'               => '收件人配置',
            'actions'             => [
                ['label' => '新增', 'id' => 'add', 'type' => 'primary'],
            ],
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => true,
            'use_buildin_filter'  => true,
        ]);
    }

    /**
     * 编辑前全量加载（替换 legacy 不存在的 act=getReceiver）
     * ?app=monitor&ctl=api_alarm_receiver&act=get&id=<id>
     *
     * @return void
     */
    #[RequirePermission('alarm_receiver')]
    public function get(): void
    {
        $input = $this->getRequestInput();
        $id    = isset($input['id']) ? intval($input['id']) : 0;
        if ($id <= 0) {
            $id = (int) ($_GET['p'][0] ?? 0);
        }
        if ($id <= 0) {
            $this->fail('id is required');
            return;
        }

        $eventReceiverMdl = app::get('monitor')->model('event_receiver');
        $row              = $eventReceiverMdl->dump(['id' => $id], '*');
        if (empty($row)) {
            $this->fail('收件人不存在');
            return;
        }

        // 多值字段拆分为数组，空值统一返回 [] 而非 ['']
        $row['event_type'] = !empty($row['event_type']) ? explode(',', $row['event_type']) : [];
        $row['org_id']     = !empty($row['org_id']) ? explode(',', $row['org_id']) : [];

        $this->succ(['receiver' => $row]);
    }

    /**
     * 新增/编辑保存（复刻旧控制器校验与文案，修正 event_type 写入口径）
     * ?app=monitor&ctl=api_alarm_receiver&act=save
     *
     * @return void
     */
    #[RequirePermission('alarm_receiver')]
    public function save(): void
    {
        $input = $this->getRequestInput();
        $id    = isset($input['id']) ? intval($input['id']) : 0;

        // 收件地址支持数组（新增可多地址）与单个字符串
        $receiverList = isset($input['receiver']) && is_array($input['receiver']) ? $input['receiver'] : [];
        $receiverList = array_values(array_filter(array_map('trim', $receiverList), static function ($val) {
            return $val !== '';
        }));
        if (empty($receiverList)) {
            $this->fail('请填写接收信息地址，请检查!', 422);
            return;
        }

        // D2 同规则：编辑分支仅在提交 org_id 时才覆盖，避免未提交时把历史值刷成空串
        // （Vue 收件人表单无「运营组织」控件；不设保护会把存量 org_id 抹除，与 legacy 同类隐患）
        $orgSubmitted = array_key_exists('org_id', $input);
        $orgIds       = '';
        if ($orgSubmitted && !empty($input['org_id'])) {
            $orgIds = is_array($input['org_id']) ? implode(',', $input['org_id']) : (string) $input['org_id'];
        }

        // D2：编辑分支仅在提交 event_type 时才覆盖，避免未提交时把历史值刷成空串
        $eventTypeSubmitted = array_key_exists('event_type', $input);
        $eventTypes         = $eventTypeSubmitted && is_array($input['event_type'])
            ? implode(',', $input['event_type'])
            : ($eventTypeSubmitted ? (string) $input['event_type'] : '');

        $eventReceiverMdl = app::get('monitor')->model('event_receiver');

        foreach ($receiverList as $val) {
            $sendType = '';
            $isRight  = false;
            if (preg_match('/^1[3456789]\d{9}$/', $val)) {
                $isRight  = true;
                $sendType = 'sms';
            }
            if (preg_match('/^([0-9A-Za-z\-_\.]+)@([0-9a-z]+\.[a-z]{2,3}(\.[a-z]{2})?)$/i', $val)) {
                $isRight  = true;
                $sendType = 'email';
            }
            if (!$isRight) {
                $this->fail('接收信息地址内容不正确，请填写正确的手机号或者邮箱，请检查!' . $val, 422);
                return;
            }

            // 编辑按主键定位；新增按地址查重，已存在则更新（与 legacy 行为一致）
            $row = $id > 0
                ? $eventReceiverMdl->dump(['id' => $id], '*')
                : $eventReceiverMdl->dump(['receiver' => $val], '*');

            // 编辑：提交地址若已被其它行占用，先友好拦截（否则 UPDATE 撞唯一索引 -> 500）
            if ($id > 0 && !empty($row)) {
                $occupied = $eventReceiverMdl->dump(['receiver' => $val], 'id');
                if (!empty($occupied) && (int) $occupied['id'] !== (int) $row['id']) {
                    $this->fail('该接收信息地址已存在，请检查!' . $val, 422);
                    return;
                }
            }

            $saveData = [
                'receiver'  => $val,
                'send_type' => $sendType,
            ];
            // 未提交的字段不写入，避免 UPDATE 把存量值抹成空串（insert 时按 legacy 写空）
            if ($id <= 0 || $orgSubmitted) {
                $saveData['org_id'] = $orgIds;
            }
            if ($id <= 0 || $eventTypeSubmitted) {
                $saveData['event_type'] = $eventTypes;
            }

            try {
                if (!empty($row)) {
                    $res = $eventReceiverMdl->update($saveData, ['id' => $row['id']]);
                    if (!$res) {
                        $this->fail('更新失败：' . $val, 422);
                        return;
                    }
                } else {
                    $res = $eventReceiverMdl->insert($saveData);
                    if (!$res) {
                        $this->fail('保存失败：' . $val, 422);
                        return;
                    }
                }
            } catch (Throwable $e) {
                // 用户输入类写入失败分流为 422 友好文案，避免前端一律显示「服务器内部错误」
                // mysqli_sql_exception 的 code 即 MySQL errno：1062 唯一键冲突、1406 数据超长
                $dbErrno = (int) $e->getCode();
                if ($dbErrno === 1062) {
                    $this->fail('该接收信息地址已存在，请检查!' . $val, 422);
                    return;
                }
                if ($dbErrno === 1406) {
                    $this->fail('接收信息地址长度超出限制，请检查!' . $val, 422);
                    return;
                }
                $this->fail('保存失败：' . $val, 500);
                return;
            }
        }

        $this->succ(['message' => '保存成功']);
    }
}
