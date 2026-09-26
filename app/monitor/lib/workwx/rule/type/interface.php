<?php

interface monitor_workwx_rule_type_interface
{
    public function checkParams($params);

    public function roleToString($params);
}
