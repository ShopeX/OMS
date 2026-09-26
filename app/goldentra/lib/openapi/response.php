<?php

class goldentra_openapi_response
{
    public static function succ($msg = '')
    {
        return array(
            'rsp' => 'succ',
            'msg' => (string) $msg,
        );
    }

    /**
     * 批量失败时的 sub_msg（纯文本，优先输出明细）
     */
    public static function failSubMsg($message, array $fail_msgs = array())
    {
        $parts = array();
        foreach ($fail_msgs as $msg) {
            $msg = trim((string) $msg);
            if ($msg !== '') {
                $parts[] = $msg;
            }
        }
        if ($parts) {
            return implode('；', $parts);
        }
        return trim((string) $message);
    }
}
