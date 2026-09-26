<?php

/**
 * 微信 / 企业微信 JS-SDK 扫码签名
 */
class wap_wechat_sign
{
    public function getSign($url, &$msg = '')
    {
        if (!defined('SCAN_APP_SECRET')) {
            $msg = '请联系管理员配置此功能';
            return false;
        }

        if ($this->_isWorkMode()) {
            return $this->_getWorkSign($url, $msg);
        }

        if (!defined('SCAN_APP_ID')) {
            $msg = '请联系管理员配置此功能';
            return false;
        }

        return $this->_getMpSign($url, $msg);
    }

    public function isWorkMode()
    {
        return $this->_isWorkMode();
    }

    private function _isWorkMode()
    {
        return defined('SCAN_AGENT_ID') && SCAN_AGENT_ID;
    }

    private function _getCorpId()
    {
        if (defined('SCAN_CORP_ID') && SCAN_CORP_ID) {
            return SCAN_CORP_ID;
        }
        if (defined('SCAN_APP_ID') && SCAN_APP_ID) {
            return SCAN_APP_ID;
        }
        return '';
    }

    private function _buildSign($ticket, $url)
    {
        $nonceStr  = $this->_createNonceStr();
        $timestamp = time();
        $string    = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        return array(
            'timestamp' => $timestamp,
            'nonceStr'  => $nonceStr,
            'signature' => sha1($string),
        );
    }

    private function _getWorkSign($url, &$msg)
    {
        $corpId = $this->_getCorpId();
        if (!$corpId) {
            $msg = '请在 config 中配置 SCAN_CORP_ID（企业微信企业ID）';
            return false;
        }

        $apiMsg = '';
        $access_token = $this->_getWorkAccessToken($corpId, $apiMsg);
        if (!$access_token) {
            $msg = $apiMsg ? $apiMsg : '获取企业微信 access_token 失败，请检查 CorpID、Secret 及服务器 IP 白名单';
            return false;
        }

        $jsapi_ticket = $this->_getWorkJsapiTicket($access_token, $apiMsg);
        if (!$jsapi_ticket) {
            $msg = $apiMsg ? $apiMsg : '获取企业微信 jsapi_ticket 失败';
            return false;
        }

        $corpSign = $this->_buildSign($jsapi_ticket, $url);
        $data = array(
            'scanMode'  => 'work',
            'appId'     => $corpId,
            'timestamp' => $corpSign['timestamp'],
            'nonceStr'  => $corpSign['nonceStr'],
            'signature' => $corpSign['signature'],
            'agentId'   => SCAN_AGENT_ID,
        );

        $agent_ticket = $this->_getWorkAgentConfigTicket($access_token, $apiMsg);
        if (!$agent_ticket) {
            $msg = $apiMsg ? $apiMsg : '获取企业微信 agent_config ticket 失败';
            return false;
        }

        $agentSign = $this->_buildSign($agent_ticket, $url);
        $data['agentTimestamp'] = $agentSign['timestamp'];
        $data['agentNonceStr']  = $agentSign['nonceStr'];
        $data['agentSignature'] = $agentSign['signature'];

        return $data;
    }

    private function _getMpSign($url, &$msg)
    {
        $apiMsg = '';
        $access_token = $this->_getMpAccessToken($apiMsg);
        if (!$access_token) {
            $msg = $apiMsg ? $apiMsg : '网络异常，请重试';
            return false;
        }

        $jsapi_ticket = $this->_getMpJsapiTicket($access_token, $apiMsg);
        if (!$jsapi_ticket) {
            $msg = $apiMsg ? $apiMsg : '网络异常，请重试';
            return false;
        }

        $corpSign = $this->_buildSign($jsapi_ticket, $url);
        return array(
            'scanMode'  => 'mp',
            'appId'     => SCAN_APP_ID,
            'timestamp' => $corpSign['timestamp'],
            'nonceStr'  => $corpSign['nonceStr'],
            'signature' => $corpSign['signature'],
        );
    }

    private function _createNonceStr($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str   = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function _getWorkAccessToken($corpId, &$apiMsg = '')
    {
        $cacheKey = 'work_access_token_' . $corpId . '_' . SCAN_AGENT_ID;
        $kvstore  = base_kvstore::instance('weixin');
        $kvstore->fetch($cacheKey, $access_token);
        if ($access_token) {
            return $access_token;
        }

        $url      = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=' . urlencode($corpId) . '&corpsecret=' . urlencode(SCAN_APP_SECRET);
        $response = @file_get_contents($url);
        $data     = json_decode($response, true);
        if (isset($data['access_token'])) {
            $expires = isset($data['expires_in']) ? $data['expires_in'] : 7200;
            $kvstore->store($cacheKey, $data['access_token'], $expires);
            return $data['access_token'];
        }
        $apiMsg = $this->_formatApiError($data, '获取 access_token 失败');
        return false;
    }

    private function _getWorkJsapiTicket($access_token, &$apiMsg = '')
    {
        $cacheKey = 'work_jsapi_ticket_' . md5($access_token);
        $kvstore  = base_kvstore::instance('weixin');
        $kvstore->fetch($cacheKey, $ticket);
        if ($ticket) {
            return $ticket;
        }

        $url      = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token={$access_token}";
        $response = @file_get_contents($url);
        $data     = json_decode($response, true);
        if (isset($data['ticket'])) {
            $kvstore->store($cacheKey, $data['ticket'], 7000);
            return $data['ticket'];
        }
        $apiMsg = $this->_formatApiError($data, '获取 jsapi_ticket 失败');
        return false;
    }

    private function _getWorkAgentConfigTicket($access_token, &$apiMsg = '')
    {
        $cacheKey = 'work_agent_ticket_' . md5($access_token);
        $kvstore  = base_kvstore::instance('weixin');
        $kvstore->fetch($cacheKey, $ticket);
        if ($ticket) {
            return $ticket;
        }

        $url      = "https://qyapi.weixin.qq.com/cgi-bin/ticket/get?access_token={$access_token}&type=agent_config";
        $response = @file_get_contents($url);
        $data     = json_decode($response, true);
        if (isset($data['ticket'])) {
            $kvstore->store($cacheKey, $data['ticket'], 7000);
            return $data['ticket'];
        }
        $apiMsg = $this->_formatApiError($data, '获取 agent_config ticket 失败');
        return false;
    }

    private function _getMpAccessToken(&$apiMsg = '')
    {
        $kvstore = base_kvstore::instance('weixin');
        $kvstore->fetch('access_token', $access_token);
        if ($access_token) {
            return $access_token;
        }

        $url      = 'https://api.weixin.qq.com/cgi-bin/stable_token';
        $postData = array(
            'grant_type'    => 'client_credential',
            'appid'         => SCAN_APP_ID,
            'secret'        => SCAN_APP_SECRET,
            'force_refresh' => false,
        );
        $options = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/json',
                'content' => json_encode($postData),
            ),
        );
        $response = @file_get_contents($url, false, stream_context_create($options));
        $data     = json_decode($response, true);
        if (isset($data['access_token'])) {
            $expires = isset($data['expires_in']) ? $data['expires_in'] : 7200;
            $kvstore->store('access_token', $data['access_token'], $expires);
            return $data['access_token'];
        }
        $apiMsg = $this->_formatApiError($data, '获取 access_token 失败');
        return false;
    }

    private function _getMpJsapiTicket($access_token, &$apiMsg = '')
    {
        base_kvstore::instance('weixin')->fetch('jsapi_ticket', $ticket);
        if ($ticket) {
            return $ticket;
        }
        $url      = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token={$access_token}";
        $response = @file_get_contents($url);
        $data     = json_decode($response, true);
        if (isset($data['ticket'])) {
            base_kvstore::instance('weixin')->store('jsapi_ticket', $data['ticket'], 7000);
            return $data['ticket'];
        }
        $apiMsg = $this->_formatApiError($data, '获取 jsapi_ticket 失败');
        return false;
    }

    private function _formatApiError($data, $default = '')
    {
        if (!is_array($data)) {
            return $default;
        }
        if (!empty($data['errmsg'])) {
            $code = isset($data['errcode']) ? $data['errcode'] : '';
            return trim($default . ($code !== '' ? "({$code})" : '') . '：' . $data['errmsg']);
        }
        return $default;
    }
}
