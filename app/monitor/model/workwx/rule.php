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

class monitor_mdl_workwx_rule extends dbeav_model
{
    /**
     * 删除规则前清理门店绑定（框架回收站钩子），避免残留绑定导致门店被已删除规则占用
     */
    public function pre_recycle($rows)
    {
        $ruleIds = array();
        foreach ((array) $rows as $row) {
            $ruleId = intval($row['rule_id']);
            if ($ruleId) {
                $ruleIds[$ruleId] = $ruleId;
            }
        }
        if ($ruleIds) {
            app::get('monitor')->model('workwx_rule_store')->delete(array('rule_id' => $ruleIds));
        }
        return true;
    }

    public function modifier_workwx_webhook($val)
    {
        return kernel::single('monitor_workwx_config')->maskWebhook($val);
    }

    public function modifier_disabled($val)
    {
        return $val == 'true' ? '禁用' : '启用';
    }

    public function modifier_event_types($val)
    {
        if ($val === '' || $val === null) {
            return '-';
        }
        $map = kernel::single('monitor_event_template')->getEventType();
        $list = array();
        foreach (explode(',', $val) as $type) {
            $type = trim($type);
            if ($type === '') {
                continue;
            }
            $list[] = isset($map[$type]) ? $map[$type] : $type;
        }
        return $list ? implode('，', $list) : $val;
    }

    public function modifier_config($config)
    {
        if (!is_array($config)) {
            $config = @unserialize($config);
        }
        $parts = array();
        if (!empty($config['roles']) && is_array($config['roles'])) {
            foreach ($config['roles'] as $role) {
                if (!empty($role['caption'])) {
                    $parts[] = $role['caption'];
                }
            }
        }
        return $parts ? implode('；', $parts) : '-';
    }
}
