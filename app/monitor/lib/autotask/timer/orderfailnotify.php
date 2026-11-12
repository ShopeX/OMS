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
 * 失败订单批量通知定时任务（SCRUM-7468）
 * 按模板 digest_times 整点触发；按组织拆多封；CSV 附件（正文不列订单号）。
 */
class monitor_autotask_timer_orderfailnotify
{
    const EVENT_TYPE = 'order_fail_notify';
    const LOCK_KEY   = 'order_fail_notify_timer_lock';
    /** 快照保留天数：超过则在定时任务结束后清理 */
    const SNAPSHOT_KEEP_DAYS = 7;

    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '512M');

        $now = time();

        // 简易防并发：60 秒内不重入
        base_kvstore::instance('monitor')->fetch(self::LOCK_KEY, $lockUntil);
        if ($lockUntil && intval($lockUntil) > $now) {
            $error_msg = '任务执行中';
            return false;
        }

        base_kvstore::instance('monitor')->store(self::LOCK_KEY, $now + 55);

        try {
            $ok = $this->runDigest($now, $error_msg);
            $this->purgeExpiredSnapshots($now, $error_msg);
            return $ok;
        } finally {
            base_kvstore::instance('monitor')->store(self::LOCK_KEY, 0);
        }
    }

    /**
     * 删除 entered_at 早于保留期的失败订单快照
     *
     * @param int $now
     * @param string $error_msg
     * @return void
     */
    protected function purgeExpiredSnapshots($now, &$error_msg)
    {
        $cutoff = intval($now) - self::SNAPSHOT_KEEP_DAYS * 86400;
        if ($cutoff <= 0) {
            return;
        }

        try {
            $sql = sprintf(
                'DELETE FROM sdb_monitor_order_fail_snapshot WHERE entered_at < %d',
                $cutoff
            );
            
            kernel::database()->exec($sql);
        } catch (Throwable $e) {
            $suffix = '快照清理失败:' . $e->getMessage();
            $error_msg = $error_msg ? ($error_msg . ';' . $suffix) : $suffix;
        }
    }

    /**
     * @param int $now
     * @param string $error_msg
     * @return bool
     */
    protected function runDigest($now, &$error_msg)
    {
        $slot = date('H:00', $now);
        $windowEnd = strtotime(date('Y-m-d H:00:00', $now));

        $templateMdl = app::get('monitor')->model('event_template');
        $templates = $templateMdl->getList('*', array(
            'event_type' => self::EVENT_TYPE,
            'status'     => '1',
            'disabled'   => 'false',
            'send_type'  => 'email',
        ));
        if (!$templates) {
            $error_msg = '无启用的失败订单批量通知模板';
            return true;
        }

        $orderFailLib = kernel::single('monitor_event_orderfail');
        $processed = 0;

        foreach ($templates as $tpl) {
            $config = $this->parseConfig($tpl);
            $times = isset($config['digest_times']) ? (array) $config['digest_times'] : array();
            if (!in_array($slot, $times, true)) {
                continue;
            }

            $windowStart = $this->resolveWindowStart($config, $windowEnd);
            if ($windowStart === null) {
                continue;
            }

            // 已处理过本窗口
            if ($windowStart >= $windowEnd) {
                continue;
            }

            $db = kernel::database();
            $sql = sprintf(
                "SELECT order_bn, org_id, entered_at, order_createtime, paytime
                 FROM sdb_monitor_order_fail_snapshot
                 WHERE entered_at > %d AND entered_at <= %d
                 ORDER BY entered_at DESC",
                intval($windowStart),
                intval($windowEnd)
            );
            $rows = $db->select($sql);

            $grouped = array();
            foreach ((array) $rows as $row) {
                $orgId = ($row['org_id'] !== '' && $row['org_id'] !== null) ? (string) $row['org_id'] : '0';
                if (!isset($grouped[$orgId])) {
                    $grouped[$orgId] = array();
                }
                $grouped[$orgId][] = $row;
            }

            $startText = date('Y-m-d H:i:s', $windowStart);
            $endText = date('Y-m-d H:i:s', $windowEnd);
            $sendOk = true;

            if ($grouped) {
                foreach ($grouped as $orgId => $orgRows) {
                    $orderCount = count($orgRows);
                    if ($orderCount <= 0) {
                        continue;
                    }

                    $csv = $orderFailLib->buildDigestCsv($orgId, $orgRows);
                    if ($csv === false) {
                        $sendOk = false;
                        break;
                    }
                    list($filePath, $fileName) = $csv;

                    $mailParams = array(
                        'org_id'      => $orgId,
                        'start_time'  => $startText,
                        'end_time'    => $endText,
                        'order_count' => $orderCount,
                        'csv_file'    => $fileName,
                        'file_path'   => array($filePath),
                    );

                    if (!$orderFailLib->dispatchDigestMail($tpl, $mailParams)) {
                        $sendOk = false;
                        break;
                    }
                }
            }

            if (!$sendOk) {
                $error_msg = '部分组织通知发送失败，水位未推进 template_id=' . $tpl['template_id'];
                return false;
            }

            $config['last_check_time'] = $endText;
            $tpl['template_config'] = $config;
            if (!$templateMdl->save($tpl)) {
                $error_msg = '水位保存失败 template_id=' . $tpl['template_id'];
                return false;
            }
            $processed++;
        }

        $error_msg = $processed ? ('已处理模板数:' . $processed) : ('当前整点 ' . $slot . ' 无需处理');
        return true;
    }

    /**
     * @param array $tpl
     * @return array
     */
    protected function parseConfig(array $tpl)
    {
        $config = isset($tpl['template_config']) ? $tpl['template_config'] : array();
        if (is_string($config)) {
            $un = @unserialize($config);
            $config = is_array($un) ? $un : array();
        }

        if (!is_array($config)) {
            $config = array();
        }

        return $config;
    }

    /**
     * 窗口起点：last_check_time > enabled_at；已处理完则返回 null/与 end 相等
     *
     * @param array $config
     * @param int $windowEnd
     * @return int|null unix timestamp
     */
    protected function resolveWindowStart(array $config, $windowEnd)
    {
        if (!empty($config['last_check_time'])) {
            $ts = strtotime($config['last_check_time']);
            return $ts !== false ? $ts : null;
        }

        if (!empty($config['enabled_at'])) {
            $ts = strtotime($config['enabled_at']);
            return $ts !== false ? $ts : null;
        }

        // 无启用时间时：首次仅看本整点前一小时，避免全量历史
        return $windowEnd - 3600;
    }
}
