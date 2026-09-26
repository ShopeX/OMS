<?php

abstract class monitor_workwx_rule_type_abstract
{
    protected $content;

    public function getUI($val)
    {
        $tpl  = kernel::single('base_render');
        $role = array_shift($val);
        $tpl->pagedata['role'] = $role;
        $tpl->pagedata['init'] = $role ? json_decode(base64_decode($role), true) : array();
        if (method_exists($this, '_prepareUI')) {
            $this->_prepareUI($tpl, $val);
        }
        return $tpl->fetch($this->getTemplateName(), 'monitor');
    }

    public function getTemplateName()
    {
        $className = strtolower(get_class($this));
        $className = str_replace('monitor_workwx_rule_type_', '', $className);
        return 'admin/alarm/workwx/rule/type/' . $className . '.html';
    }

    public function createRole($params)
    {
        $result = $this->checkParams($params);
        if ($result === true) {
            return json_encode(array('code' => 'SUCC', 'msg' => $this->roleToString($params)));
        }
        return json_encode(array('code' => 'FAIL', 'msg' => $result));
    }

    public function setRole($params)
    {
        $this->content = $params;
    }
}
