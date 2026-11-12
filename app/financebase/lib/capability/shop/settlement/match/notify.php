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

/**
 * 店铺收支明细匹配失败通知配置（financebase / admin_shop_settlement_match_notify）读写 capability
 *
 * 单一实现：legacy 控制器 `financebase_ctl_admin_shop_settlement_match_notify` 与 Vue JSON api
 * `financebase_ctl_api_shop_settlement_match_notify` 共用本类，避免两处漂移。
 *
 * 配置键（值语义对齐 legacy 模板 `app/financebase/view/admin/shop/settlement/match/notify.html`）：
 *  - bill.match_fail_notify.enabled   ← 启用通知：'true' | 'false'（字符串，非 1/0）
 *  - bill.match_fail_notify.cron_time ← 发送时间：HH:MM，须为整点（分钟 00），默认 10:00
 *
 * 读取面沿用既有业务助手 `financebase_bill_match_notify`（getCronTime/checkMonitorConfig/
 * getTodayStatusSummary），capability 只做归一化与派生字段拼装。
 *
 * 写面语义与 legacy save() 一致：校验 + 归一化 + 逐键 getConf 比对（值未变不写）+ 时间变更清缓存。
 * conf 走 kvstore 无事务接口 ⇒ 以「先快照、异常整组回滚」保护，等价于单组写的事务边界。
 */
class financebase_capability_shop_settlement_match_notify
{
    private const CONF_ENABLED   = 'bill.match_fail_notify.enabled';
    private const CONF_CRON_TIME = 'bill.match_fail_notify.cron_time';

    /** 发送时间缺省值（对齐 legacy save() 的兜底与业务助手 getCronTime()） */
    private const DEFAULT_CRON_TIME = '10:00';

    private object $app;

    private object $notifyLib;

    public function __construct(?object $app = null)
    {
        $this->app       = $app ?? app::get('financebase');
        $this->notifyLib = kernel::single('financebase_bill_match_notify');
    }

    /**
     * 读取页面数据（legacy index 与 api index 共用）
     *
     * 返回结构与 legacy `index()` 注入的 pagedata 逐字段对齐。
     *
     * @return array{config: array<string, mixed>, today_status: array<string, mixed>, configured_hour_passed: bool, monitor_config: array<string, mixed>, monitor_links: array<string, string>}
     */
    public function loadPageData(): array
    {
        $cronTime = $this->notifyLib->getCronTime();

        $todayStatus = $this->notifyLib->getTodayStatusSummary();
        $todayStatus['pending_dates_text'] = !empty($todayStatus['pending_dates'])
            ? implode('、', $todayStatus['pending_dates'])
            : '';
        $todayStatus['pending_dates_rows'] = !empty($todayStatus['pending_dates'])
            ? array_chunk($todayStatus['pending_dates'], 8)
            : array();
        $todayStatus['sent_all'] = (
            (int) $todayStatus['failure_days'] > 0
            && (int) $todayStatus['pending_count'] === 0
        );
        $todayStatus['daily_sent'] = (int) $todayStatus['sent_today_count'] > 0;

        return array(
            'config' => array(
                'enabled'   => $this->app->getConf(self::CONF_ENABLED),
                'cron_time' => $cronTime,
            ),
            'today_status'           => $todayStatus,
            'configured_hour_passed' => $this->isConfiguredHourPassed($cronTime),
            'monitor_config'         => $this->notifyLib->checkMonitorConfig(),
            'monitor_links'          => self::monitorLinks(),
        );
    }

    /**
     * 保存配置（legacy save 与 api save 共用）—— 校验 / 归一化 / 持久化集中此处
     *
     * @param array<string, mixed> $input 形如 ['enabled' => 'true'|'false', 'cron_time' => 'HH:MM']
     *
     * @return string 成功提示文案（与 legacy 一致，含「整点已过」附加说明）
     *
     * @throws InvalidArgumentException 发送时间非整点（对齐 legacy end(false, …)）
     */
    public function saveConfig(array $input): string
    {
        $oldCronTime = $this->notifyLib->getCronTime();

        $enabled  = (!empty($input['enabled']) && $input['enabled'] === 'true') ? 'true' : 'false';
        $cronTime = trim((string) ($input['cron_time'] ?? ''));
        if ($cronTime === '' || !preg_match('/^\d{2}:\d{2}$/', $cronTime)) {
            $cronTime = self::DEFAULT_CRON_TIME;
        } elseif (!preg_match('/^\d{2}:00$/', $cronTime)) {
            throw new InvalidArgumentException('发送时间须为整点（分钟须为 00），例如 14:00、15:00');
        }

        $targets = array(
            self::CONF_ENABLED   => $enabled,
            self::CONF_CRON_TIME => $cronTime,
        );

        // 快照（conf 无事务能力 ⇒ 异常时整组回滚，避免半保存）
        $snapshot = array();
        foreach ($targets as $key => $value) {
            $snapshot[$key] = $this->app->getConf($key);
        }

        try {
            foreach ($targets as $key => $value) {
                // 对齐 legacy：与当前值相同则不写
                if ($snapshot[$key] != $value) {
                    $this->app->setConf($key, $value);
                }
            }
        } catch (Throwable $exception) {
            foreach ($snapshot as $key => $old) {
                $this->app->setConf($key, $old);
            }

            throw $exception;
        }

        if ($cronTime !== $oldCronTime) {
            $this->notifyLib->clearTodayCache();
        }

        $message = '保存成功';
        if (preg_match('/^(\d{2}):00$/', $cronTime, $matched) && (int) date('G') >= (int) $matched[1]) {
            $message .= '；今日 ' . $cronTime . ' 整点已过，将于明日该时刻自动发送';
        }

        return $message;
    }

    /**
     * 配置指引链接（对齐 legacy pagedata['monitor_links']）
     *
     * @return array<string, string>
     */
    public static function monitorLinks(): array
    {
        return array(
            'template' => 'index.php?app=monitor&ctl=admin_alarm_template&act=index',
            'group'    => 'index.php?app=monitor&ctl=admin_alarm_group&act=index',
            'receiver' => 'index.php?app=monitor&ctl=admin_alarm_receiver&act=index',
            'email'    => 'index.php?app=monitor&ctl=admin_setting&act=index',
        );
    }

    /**
     * 配置的发送整点是否已过（对齐 legacy index() 的 configuredHourPassed）
     */
    private function isConfiguredHourPassed(string $cronTime): bool
    {
        if (preg_match('/^(\d{2}):00$/', $cronTime, $matched)) {
            return (int) date('G') >= (int) $matched[1];
        }

        return false;
    }
}
