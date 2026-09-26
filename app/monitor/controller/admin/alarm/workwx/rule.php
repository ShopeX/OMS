<?php

class monitor_ctl_admin_alarm_workwx_rule extends desktop_controller
{
    public function index()
    {
        $actions = array(
            array(
                'label'  => '新增',
                'href'   => 'index.php?app=monitor&ctl=admin_alarm_workwx_rule&act=add',
                'target' => 'dialog::{width:900,height:720,title:\'新建企微通知规则\'}',
            ),
        );
        $this->finder('monitor_mdl_workwx_rule', array(
            'title'               => '企微通知规则',
            'actions'             => $actions,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => true,
            'use_buildin_filter'  => true,
            'finder_aliasname'    => 'monitor_workwx_rule_v1',
            'finder_cols'         => 'column_edit,rule_name,column_store_count,workwx_webhook,event_types,config,disabled,at_time',
        ));
    }

    public function add()
    {
        $this->_edit('add');
    }

    public function edit($id)
    {
        $this->_edit('edit', $id);
    }

    private function _edit($action, $id = 0)
    {
        $data = array(
            'weight'   => 0,
            'config'   => array('roles' => array()),
            'org_id'   => array(),
            'role_items' => array(),
        );
        if ($id) {
            $data = app::get('monitor')->model('workwx_rule')->dump(array('rule_id' => $id), '*');
            $data['event_type'] = $data['event_types'] ? explode(',', $data['event_types']) : array();
            if (!is_array($data['config'])) {
                $data['config'] = array('roles' => array());
            }
            if (!empty($data['org_id'])) {
                $data['org_id'] = explode(',', $data['org_id']);
            } else {
                $data['org_id'] = array();
            }
            $data['role_items'] = kernel::single('monitor_workwx_config')->getRuleRoleItems($id);
        } else {
            $data['event_type'] = array();
        }

        $this->pagedata['data']            = $data;
        $this->pagedata['action']          = $action;
        $eventTypes = kernel::single('monitor_workwx_config')->getWorkwxEventTypes();
        if ($id && !empty($data['event_type'])) {
            $allEventMap = kernel::single('monitor_event_template')->getEventType();
            foreach ((array) $data['event_type'] as $type) {
                if ($type !== '' && !isset($eventTypes[$type])) {
                    $eventTypes[$type] = isset($allEventMap[$type]) ? $allEventMap[$type] : $type;
                }
            }
        }
        $this->pagedata['event_type'] = $eventTypes;
        $this->pagedata['condition_types'] = kernel::single('monitor_workwx_rule_type')->getTypeOptions();
        $this->pagedata['o2o_installed']   = app::get('o2o')->is_installed();
        $this->display('admin/alarm/workwx/add_rule.html');
    }

    public function addcondition()
    {
        if (!empty($_REQUEST['role'])) {
            $role = json_decode($_REQUEST['role'], true);
        } else {
            $role = array();
        }
        $this->pagedata['uid']  = $_REQUEST['uid'];
        $this->pagedata['role'] = base64_encode($_REQUEST['role']);
        $this->pagedata['init'] = $role;
        $this->pagedata['rule_id'] = intval($_REQUEST['rule_id']);
        $this->pagedata['condition_types'] = kernel::single('monitor_workwx_rule_type')->getTypeOptions();
        $this->display('admin/alarm/workwx/add_condition.html');
    }

    public function ajax()
    {
        $params = $_POST['p'];
        $typeName = array_shift($params);
        $method   = array_shift($params);
        echo kernel::single('monitor_workwx_rule_type')->doMethod($typeName, $method, $params);
    }

    public function createCondition()
    {
        header('Content-Type: application/json; charset=utf-8');
        echo kernel::single('monitor_workwx_rule_type')->createRole();
        exit;
    }

    public function save()
    {
        $this->begin('index.php?app=monitor&ctl=admin_alarm_workwx_rule&act=index');
        $errMsg = '';
        $res = kernel::single('monitor_workwx_config')->saveRule($_POST, $errMsg);
        if ($res === false) {
            $this->end(false, $errMsg);
        }
        $this->end(true, '保存成功');
    }
}
