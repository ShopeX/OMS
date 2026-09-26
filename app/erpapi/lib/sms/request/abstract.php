<?php
/**
 * @author ykm 2016-01-19
 * @describe 短信发送请求抽象类
 */
abstract class erpapi_sms_request_abstract
{
    protected $__channelObj;
    protected $__resultObj;
    protected $title = '短信平台接口请求';
    protected $timeOut = 30;
    protected $primaryBn = '';
    protected $writeLog = true;

    final public function init(erpapi_channel_abstract $channel, erpapi_config $config, erpapi_result $result)
    {
        $this->__channelObj = $channel;
        
        $this->__resultObj = $result;

        // 默认以JSON格式返回
        $callerObj = new erpapi_caller();
        $this->__caller = $callerObj
                            ->set_config($config)
                            ->set_channel($channel)
                            ->set_result($result);
    }

    final protected function requestCall($method, $params, $callback = array(), $gateway='') {
        return $this->__caller->call($method, $params, $callback, $this->title, $this->timeOut, $this->primaryBn, $this->writeLog, $gateway);
    }

    final public function succ($msg='', $msgcode='', $data=null)
    {
        return array('rsp'=>'succ', 'msg'=>$msg, 'msg_code'=>$msgcode, 'data'=>$data);
    }

    final public function error($msg, $msgcode = '', $data=null)
    {
        return array('rsp'=>'fail','msg'=>$msg,'err_msg'=>$msg,'msg_code'=>$msgcode,'data'=>$data);
    }

    /**
     * 生成唯一键
     *
     * @return string
     * @author 
     **/
    final public function uniqid(){
        $microtime  = utils::microtime();
        $unique_key = str_replace('.','',strval($microtime));
        $randval    = uniqid('', true);
        $unique_key .= strval($randval);
        return md5($unique_key);
    }

    /**
     * 回调
     * @param $response Array
     * @param $callback_params Array
     * @return array
     **/
    public function callback($response, $callback_params)
    {
        return $response;
    }

    public function makeShopexAc($arr, $token) {
        $temp_arr = $arr;
        ksort($temp_arr);
        $str = '';
        foreach ($temp_arr as $key => $value) {
            if ($key != 'certi_ac') {
                $str .= $value;
            }
        }
        return md5($str . md5($token));
    }

    public function baseMakeShopexAc($arr, $token) {
        ksort($arr);
        $str = '';
        foreach ($arr as $key => $value) {
            if ($key != 'ac') {
                $str .= $value;
            }
        }
        return strtolower(md5($str . strtolower(md5($token))));
    }

    #同步请求 需要写日志的处理方法
    protected function sendSMSBack($result, $sdf) {
        if ($result['rsp'] == 'succ') {
            if(is_array($result['data'])) {
                $batchno = $result['data']['msgid'];
                $msg = $result['data']['msg'];
            } else {
                $batchno = '';
                $msg = $result['data'];
            }
            $this->writeSmslog($sdf['phones'], $sdf['content'], $msg, 1, $batchno, $sdf['smslog_id']);
        } else {
            $this->writeSmslog($sdf['phones'], $sdf['content'], '请求api失败,' . $result['data'], 0, '-1', $sdf['smslog_id']);
        }
    }

    /**
     * @param $phone 电话号码
     * @param $content string 发送内容
     * @param $msg string 短信状态信息
     * @param $status string 短信状态
     * @param $batchno
     * @return bool
     */
    public function writeSmslog($phone, $content, $msg, $status, $batchno = '', $smslog_id = 0){
        $messlog = app::get('taoexlib')->model("log");

        $messlogdata = array(
            'mobile'    =>$phone,
            'batchno'   =>$batchno,
            'content'   =>$content,
            'sendtime'  =>time(),
            'msg'       =>mb_substr($msg, 0, 100, 'UTF-8'),
            'status'    =>$status,
            'smslog_id' =>$smslog_id,
        );

        // 判断是否存在
        if(intval($smslog_id)>0) $row = $messlog->dump(array('smslog_id'=>$smslog_id),'id,retry_times');

        if ($smslog_id && $row) {
            $messlogdata['retry_times'] = $messlogdata['retry_times']+1;
            $messlog->update($messlogdata,array('id'=>$row['id']));
        } else {
            $messlog->insert($messlogdata);
        }

    }
}
