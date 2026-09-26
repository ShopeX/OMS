<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
/**
 * @author ykm 2016-01-19
 * @describe 短信接口请求
 */
class erpapi_sms_request_sms extends erpapi_sms_request_abstract
{

    #发送一条短信
    /**
     * sendOne
     * @param mixed $sdf sdf
     * @param mixed $queue queue
     * @return mixed 返回值
     */

    public function sendOne($sdf,$queue=false)
    {
        $args = func_get_args();array_pop($args);
        $channel_id = $this->__channelObj->channel['channel_id'];
        $_in_mq = $this->__caller->caller_into_mq('sms_sendOne','sms',$channel_id,$args,$queue);
        if ($_in_mq) {
            return $this->succ('成功放入队列');
        }
        $this->primaryBn = $sdf['phones'];
        //短信签名验证
        preg_match('/\【(.*?)\】$/',$sdf['replace']['msgsign'],$filtcontent1);
        if ($filtcontent1) {
            $signRs = $this->newOauth(array('sms_sign'=>$filtcontent1[0]));
            if ($signRs['rsp'] == 'fail') {
                $msg = '短信签名错误，原因：'.$signRs['data'].($signRs['res']=='1000'?',请重新解绑登陆':'').'！';
                $this->writeSmslog($sdf['phones'], $sdf['content'], $msg, 0, '', $sdf['smslog_id']);
                return $signRs;
            }
        }
        //剩余短信条数,
        $info = $this->getUserInfo();
        $smsAmount = ceil(taoexlib_utils::utf8_strlen($sdf['content']) / taoexlib_utils::SINGLE_SMS_LENGTH);
        if ('succ' == $info['rsp']) {
            if($info['data']['month_residual'] >= $smsAmount) {
                if($sdf['no_tpl'] == 'true') {
                    $result = $this->sendByNoTpl($sdf);
                } else {
                    $result = $this->sendByTmpl($sdf);
                }
                return $result;
            }
            $msg = '剩余短信不足';
        } else {
            $msg = '获取剩余短信失败';
        }
        $this->writeSmslog($sdf['phones'], $sdf['content'], $msg, 0, '', $sdf['smslog_id']);
        
        return $this->error($msg,'SMS');
    }

    /**
     * 获取ServerTime
     * @return mixed 返回结果
     */
    public function getServerTime() {
        static $serverTimestamp, $localTimestamp;
        if (null === $serverTimestamp || null === $localTimestamp) {
            $this->title = '短信服务器时间';

            $param = array(
                'certi_app' => 'sms.servertime',
                'version' => '1.0',
                'format' => 'json',
            );
            $param['certi_ac'] = $this->makeShopexAc($param, 'SMS_TIME');
            $result = $this->requestCall(SMS_SERVER_TIME, $param);
            $serverTimestamp = ('succ' == $result['rsp']) ? $result['data'] : time();
            $localTimestamp = time();
            return $serverTimestamp;
        } else {
            return $serverTimestamp + time() - $localTimestamp;
        }
    }

    /**
     * 获取UserInfo
     * @return mixed 返回结果
     */
    public function getUserInfo() {
        $account = $this->__channelObj->channel['account'];
        $param = array(
            'certi_app' => 'sms.info',
            'entId' => $account['entid'],
            'entPwd' => md5($account['password'] . 'ShopEXUser'),
            'source' => APP_SOURCE,
            'version' => '1.0',
            'format' => 'json',
            'timestamp' => $this->getServerTime(),
        );
        
        $this->title = '短信用户信息';
        $param['certi_ac'] = $this->baseMakeShopexAc($param, APP_TOKEN);
        $result = $this->requestCall(SMS_USER_INFO, $param);
        return $result;
    }

    protected function _newOauthRequest($sdf) {
        $this->title = '短信签名验证';

        $name = $sdf['sms_sign'];
        $account = $this->__channelObj->channel['account'];
        $client_id = defined('SMS_OAUTH_CLIENT_ID') && SMS_OAUTH_CLIENT_ID ? SMS_OAUTH_CLIENT_ID : '';
        $secret = defined('SMS_OAUTH_SECRET') && SMS_OAUTH_SECRET ? SMS_OAUTH_SECRET : '';
        if (empty($client_id) || empty($secret)) {
            return $this->error('短信OAuth密钥未配置，请检查 config/sms_secrets.php 或环境变量', 'SMS');
        }
        $params = array(
            'shopexid' => $account['entid'],
            'passwd' => md5($account['password'] . 'ShopEXUser'),
            'content' => $name,
            'client_id' => $client_id,
            'secret' => $secret,
            'signUrl' => false
        );
        $result = $this->requestCall(SMS_NEW_OAUTH, $params);
        return $result;
    }

    /**
     * newOauth
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function newOauth($sdf) {
        $name = $sdf['sms_sign'];
        $sms_signObj = app::get('taoexlib')->model('sms_sign');
        $sms_sign = $sms_signObj->dump(array('name'=>$name),'*');
        if (!$sms_sign || !$sms_sign['extend_no']) {
            $result = $this->_newOauthRequest($sdf);
            if ($result['rsp'] == 'succ') {
                $extend_no = $result['data']['extend_no'];
                $sign_data = array(
                    'name'  =>$name,
                    'extend_no'=>$extend_no,
                );
                if ($sms_sign && $extend_no) {
                    $sms_signObj->db->exec("UPDATE sdb_taoexlib_sms_sign SET extend_no='".$extend_no."' WHERE s_id=".$sms_sign['s_id']);
                }else{
                    $sms_signObj->save($sign_data);
                }
            }
            return $result;
        }
        return true;
    }


    /**
     * signUpdate
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function signUpdate($sdf)
    {
        $this->title = '短信签名更新';

        $account = $this->__channelObj->channel['account'];
        $client_id = defined('SMS_OAUTH_CLIENT_ID') && SMS_OAUTH_CLIENT_ID ? SMS_OAUTH_CLIENT_ID : '';
        $secret = defined('SMS_OAUTH_SECRET') && SMS_OAUTH_SECRET ? SMS_OAUTH_SECRET : '';
        if (empty($client_id) || empty($secret)) {
            return $this->error('短信OAuth密钥未配置，请检查 config/sms_secrets.php 或环境变量', 'SMS');
        }
        $params = array(
            'shopexid'  => $account['entid'],
            'passwd'    => md5($account['password'] . 'ShopEXUser'),
            'new_content'   => $sdf['sms_sign'],
            'old_content' => $sdf['old_sign'],
            'extend_no' => $sdf['extend_no'],
            'client_id' => $client_id,
            'secret'    => $secret,
            'signUrl'   => false
        );
        $result = $this->requestCall(SMS_UPDATE_OAUTH, $params);

        return $result;
    }


    /**
     * sendByTmpl
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function sendByTmpl($sdf) {
        $this->title = '短信发送';

        $account = $this->__channelObj->channel['account'];
        $params = array(
            'tplid'     =>  $sdf['tplid'],
            'product'   =>  APP_SOURCE,
            'phones'    =>  $sdf['phones'],
            'replace'   =>  json_encode($sdf['replace']),
            'timestamp' =>  strval($this->getServerTime()),
            'license'   =>  base_certificate::get('certificate_id') ? base_certificate::get('certificate_id') : 1,
            'entid'     =>  $account['entid'],
            'entpwd'    =>  md5($account['password'] . 'ShopEXUser'),
            'use_reply'=>'',
            'use_backlist'=>'',
            'signUrl' => true
        );
        #client 中的补齐参数
        $params['client_id'] = defined('SMS_ISHOPEX_KEY') && SMS_ISHOPEX_KEY ? SMS_ISHOPEX_KEY : '';
        $params['secret'] = defined('SMS_ISHOPEX_SECRET') && SMS_ISHOPEX_SECRET ? SMS_ISHOPEX_SECRET : '';
        if (empty($params['client_id']) || empty($params['secret'])) {
            return $this->error('短信服务密钥未配置，请检查 config/sms_secrets.php 或环境变量', 'SMS');
        }
        $gateway = '';
        if ($sdf['is_encrypt']) {
            $params['order_bns'] = $sdf['order_bn'];
            $params['s_node_type']  = $sdf['shop_type'];
            $params['s_node_id']    = $sdf['s_node_id'];
            $params['from_node_id'] = base_shopnode::node_id('ome');
            $params['method']       = 'hufu.sms.iprism.send';

            $gateway = $sdf['shop_type'];
        }

        $result = $this->requestCall(SMS_SEND_TMPL, $params, array (), $gateway);

        $this->sendSMSBack($result, $sdf);
        return $result;
    }

    /**
     * sendByNoTpl
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function sendByNoTpl($sdf) {
        $msg = '云起账户才可以使用直接发送短信';
        $this->writeSmslog($sdf['phones'], $sdf['content'], $msg, 0);
        return $this->error($msg);
    }
}
