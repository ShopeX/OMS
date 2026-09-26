<?php

class openapi_response
{

    public function __construct()
    {
        $this->format = kernel::single('openapi_format_abstract');
    }

    public function send_result($data, $charset, $type, $root = 'response')
    {
        $res = array(
            $root => $data,
        );
    
     
        
        $kafkaMsg = '';
//        if (isset($kafkaMsg[512000])) {
//            $kafkaMsg = '数据太大，不存储返回结果';
//        }

        $logsdf = array(
            'status' => 'success',
            'params' => $_POST,
            'response' => ['因rdkafka内存溢出暂不存数据'],
            'msg' => $kafkaMsg,
        );
        $this->_log($logsdf, 'response');

        $this->format->process($res, $charset, $type);
    }

    public function send_error($code, $charset, $type, $sub_msg=null)
    {
        $error_arr = openapi_errorcode::get($code);
        $res       = array(
            'error_response' => array(
                'code'    => $error_arr['code'],
                'msg'     => $error_arr['msg'],
                'sub_msg' => self::formatSubMsg($sub_msg),
            ),
        );

        $kafkaMsg = '';
        if (isset($kafkaMsg[512000])) {
            $kafkaMsg = '数据太大，不存储返回结果';
        }

        $logsdf = array(
            'status' => 'fail',
            'response' => $res,
            'params' => $_POST,
            'msg' => $kafkaMsg,
        );
        $this->_log($logsdf, 'response');

        $this->format->process($res, $charset, $type);
    }

    /**
     * 失败 sub_msg 统一为可读字符串，禁止 JSON 结构
     */
    public static function formatSubMsg($sub_msg)
    {
        if ($sub_msg === null || $sub_msg === '') {
            return $sub_msg;
        }
        if (is_array($sub_msg)) {
            return self::_flattenSubMsgArray($sub_msg);
        }
        if (is_string($sub_msg)) {
            $trimmed = trim($sub_msg);
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return self::_flattenSubMsgArray($decoded);
                }
            }
            return $sub_msg;
        }
        return (string) $sub_msg;
    }

    private static function _flattenSubMsgArray(array $data)
    {
        $parts = array();
        if (!empty($data['fail_detail']) && is_array($data['fail_detail'])) {
            foreach ($data['fail_detail'] as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $parts[] = $item;
                }
            }
        }
        if ($parts) {
            return implode('；', $parts);
        }
        if (!empty($data['message'])) {
            return trim((string) $data['message']);
        }
        if (!empty($data['msg'])) {
            return trim((string) $data['msg']);
        }
        return '';
    }

    public function getFormatType($type)
    {
        if (in_array($type, $this->format->type_lists)) {
            return $type;
        } else {
            return '';
        }
    }

    public function getFormatCharset($charset)
    {
        if (in_array($charset, $this->format->charset_lists)) {
            return $charset;
        } else {
            return '';
        }
    }

    public function _log($logsdf, $step = 'request')
    {
        $apilogModel = app::get('ome')->model('api_log');
        $log_id      = uniqid('openapi');

        $logsdf = array(
            'log_id'        => $log_id,
            'task_name'     => $_POST['method'],
            'status'        => $logsdf['status'],
            'worker'        => '',
            'params'        => json_encode($logsdf['params'], JSON_UNESCAPED_UNICODE),
            'msg'           => $logsdf['msg'],
            'response'      => json_encode($logsdf['response'], JSON_UNESCAPED_UNICODE),
            'log_type'      => '',
            'api_type'      => 'response',
            'memo'          => '',
            'original_bn'   => 'openapi',
            'createtime'    => time(),
            'last_modified' => time(),
        );

        $apilogModel->insert($logsdf);
    }
}
