<?php

class monitor_mdl_workwx_rule extends dbeav_model
{
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
