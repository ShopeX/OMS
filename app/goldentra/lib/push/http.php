<?php

/**
 * OMS→SAP HTTP 客户端（Basic Auth）
 */
class goldentra_push_http
{
    /**
     * @param string $url
     * @param array $payload 已是 CONTINFO+BODY 结构
     * @param array $cfg
     * @return array succ, msg, http_code, body, sap_status, sap_msgtxt, keep_pending?
     */
    public function postJson($url, array $payload, array $cfg)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return array(
                'succ' => false,
                'keep_pending' => true,
                'msg' => 'SAP推送地址未配置',
            );
        }

        $username = isset($cfg['username']) ? (string) $cfg['username'] : '';
        $password = isset($cfg['password']) ? (string) $cfg['password'] : '';
        $timeout = !empty($cfg['timeout']) ? intval($cfg['timeout']) : 60;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return array(
                'succ' => false,
                'msg' => '报文 JSON 编码失败',
            );
        }

        if (!function_exists('curl_init')) {
            return array(
                'succ' => false,
                'msg' => 'PHP curl 扩展不可用',
            );
        }

        $ch = curl_init($url);
        $headers = array(
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
        );
        if ($username !== '') {
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(15, $timeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        if ($errno) {
            return array(
                'succ' => false,
                'msg' => 'SAP HTTP 请求失败: [' . $errno . '] ' . $err,
                'http_code' => $httpCode,
                'body' => is_string($raw) ? $raw : '',
            );
        }

        $parsed = $this->parseResponse($raw);
        $parsed['http_code'] = $httpCode;
        $parsed['body'] = is_string($raw) ? $raw : '';

        if ($httpCode < 200 || $httpCode >= 300) {
            $parsed['succ'] = false;
            if (empty($parsed['msg'])) {
                $parsed['msg'] = 'SAP HTTP 状态码 ' . $httpCode;
            }
            return $parsed;
        }

        return $parsed;
    }

    /**
     * 解析 SAP 返回 BODY[].STATUS / MSGTXT
     *
     * @param string $raw
     * @return array
     */
    public function parseResponse($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array(
                'succ' => false,
                'msg' => 'SAP 返回为空',
            );
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return array(
                'succ' => false,
                'msg' => 'SAP 返回非 JSON: ' . mb_substr($raw, 0, 200),
            );
        }

        $rows = array();
        if (isset($data['BODY']) && is_array($data['BODY'])) {
            $rows = $data['BODY'];
        } elseif (isset($data['body']) && is_array($data['body'])) {
            $rows = $data['body'];
        } elseif (isset($data[0]) && is_array($data[0])) {
            $rows = $data;
        }

        if (!$rows) {
            // 兼容扁平 STATUS
            $status = $this->pick($data, array('STATUS', 'status'));
            $msgtxt = $this->pick($data, array('MSGTXT', 'msgtxt', 'MSG', 'msg'));
            return $this->statusResult($status, $msgtxt, $data);
        }

        $first = null;
        foreach ($rows as $row) {
            if (is_array($row)) {
                $first = $row;
                break;
            }
        }
        if (!$first) {
            return array(
                'succ' => false,
                'msg' => 'SAP 返回 BODY 为空',
            );
        }

        $status = $this->pick($first, array('STATUS', 'status'));
        $msgtxt = $this->pick($first, array('MSGTXT', 'msgtxt', 'MSG', 'msg'));
        $result = $this->statusResult($status, $msgtxt, $first);
        $result['items'] = $rows;
        return $result;
    }

    protected function statusResult($status, $msgtxt, array $extra = array())
    {
        $status = trim((string) $status);
        $msgtxt = trim((string) $msgtxt);
        $ok = $this->isSuccStatus($status);
        return array(
            'succ' => $ok,
            'msg' => $msgtxt !== '' ? $msgtxt : ($ok ? 'SAP处理成功' : ('SAP处理失败 STATUS=' . $status)),
            'sap_status' => $status,
            'sap_msgtxt' => $msgtxt,
            'sap_row' => $extra,
        );
    }

    /**
     * SAP 成功态：S / 0 / succ / success / 成功
     */
    public function isSuccStatus($status)
    {
        $status = strtoupper(trim((string) $status));
        return in_array($status, array('S', '0', 'SUCC', 'SUCCESS', 'OK', '成功'), true);
    }

    protected function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '') {
                return $row[$k];
            }
        }
        return '';
    }
}
