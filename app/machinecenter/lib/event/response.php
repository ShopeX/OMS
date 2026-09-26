<?php

class machinecenter_event_response
{

    private $_response = '';

    function __construct() {}

    public function send_succ($msg = '')
    {
        $rs = array (
            'rsp'      => 'succ',
            'msg'      => $msg,
            'msg_code' => null,
            'data'     => null,
        );
        return $rs;
    }

    public function send_error($msg, $msg_code = null, $data = null)
    {
        $rs = array (
            'rsp'      => 'fail',
            'msg'      => $msg,
            'msg_code' => $msg_code,
            'data'     => $data,
        );
        return $rs;
    }
}
