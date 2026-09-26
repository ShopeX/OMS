<?php

class monitor_workwx_rule_type
{
    public static $TYPE_LIST = array('store');

    public function getTypeOptions()
    {
        return array(
            'store' => '门店',
        );
    }

    public function doMethod($tagName, $method, $params)
    {
        $tagName = strtolower($tagName);
        if (!in_array($tagName, self::$TYPE_LIST)) {
            return sprintf('未知类型 %s。', $tagName);
        }
        $obj = kernel::single(sprintf('monitor_workwx_rule_type_%s', $tagName));
        if (!method_exists($obj, $method)) {
            return sprintf('方法 %s 不存在。', $method);
        }
        return $obj->$method($params);
    }

    public function createRole()
    {
        if (empty($_POST)) {
            return json_encode(array('code' => 'FAIL', 'msg' => '请使用 POST 方式进行数据提交'));
        }
        $tagName = strtolower(trim($_REQUEST['type_id']));
        return $this->doMethod($tagName, 'createRole', $_POST);
    }
}
