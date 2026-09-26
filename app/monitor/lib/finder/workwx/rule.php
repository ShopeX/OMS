<?php

class monitor_finder_workwx_rule
{
    public $column_edit = '操作';
    public $column_edit_width = '75';
    public $column_edit_order = '5';

    public function column_edit($row)
    {
        return '<a href="index.php?app=monitor&ctl=admin_alarm_workwx_rule&act=edit&p[0]='
            . $row['rule_id'] . '&finder_id=' . $_GET['_finder']['finder_id']
            . '" target="dialog::{width:900,height:720,title:\'编辑企微通知规则\'}">编辑</a>';
    }

    public $column_store_count = '门店数';
    public $column_store_count_width = '80';
    public $column_store_count_order = '22';

    public function column_store_count($row)
    {
        return app::get('monitor')->model('workwx_rule_store')->count(array('rule_id' => $row['rule_id']));
    }

    public $detail_basic = '详情';

    public function detail_basic($ruleId)
    {
        $render = app::get('monitor')->render();
        $rule   = app::get('monitor')->model('workwx_rule')->dump($ruleId, '*');
        $stores = app::get('monitor')->model('workwx_rule_store')->getList(
            'store_bn,store_name',
            array('rule_id' => $ruleId),
            0,
            5000,
            'store_bn asc'
        );
        $render->pagedata['rule']  = $rule;
        $render->pagedata['stores'] = $stores;
        $render->pagedata['event_type_names'] = app::get('monitor')->model('workwx_rule')->modifier_event_types($rule['event_types']);
        $render->pagedata['config_text'] = app::get('monitor')->model('workwx_rule')->modifier_config($rule['config']);
        return $render->fetch('admin/alarm/workwx/rule_detail.html', 'monitor');
    }
}
