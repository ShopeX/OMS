<?php
/**
 * @describe WSDL SOAP短信接口请求
 */
class erpapi_sms_openapi_soap_request_sms extends erpapi_sms_request_sms
{
    public function sendOne($sdf,$queue=false)
    {
        $args = func_get_args();array_pop($args);
        $channel_id = $this->__channelObj->channel['channel_id'];
        $_in_mq = $this->__caller->caller_into_mq('sms_sendOne','sms',$channel_id,$args,$queue);
        if ($_in_mq) {
            return $this->succ('成功放入队列');
        }

        $account = $this->__channelObj->channel['account'];
        $batchno = date('Ymd');
        try {
            $soap = $this->createSoapClient($account['wsdl_url'] . '?WSDL');
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->writeSmslog($sdf['phones'], $sdf['content'], $msg, 0, $batchno, $sdf['smslog_id']);
            return $this->error($msg, 'SMS');
        }
        $soap->soap_defencoding = 'utf-8';
        $soap->decode_utf8 = false;

        $params = array(
            'phoneNo' => $sdf['phones'],
            'message' => $sdf['content'],
            'userName' => $account['username'],
            'password' => $account['password'],
            'companyCode' => $account['companyCode'],
            'timeMsg' => $account['timeMsg'],
        );
        $result = (array)$soap->SendMessage($params);

        if($result['SendMessageResult'] === '发送成功'){
            $this->writeSmslog($sdf['phones'], $sdf['content'], $result['SendMessageResult'], 1, $batchno, $sdf['smslog_id']);
            return $this->succ($result['SendMessageResult'], '', $result);
        }

        $msg = $result['SendMessageResult'];
        $this->writeSmslog($sdf['phones'], $sdf['content'], $msg, 0, $batchno, $sdf['smslog_id']);
        return $this->error($msg, 'SMS', $result);
    }

    protected function createSoapClient($wsdlUrl)
    {
        return new soapclient($wsdlUrl);
    }
}
