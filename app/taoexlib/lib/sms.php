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
 * @description 应用层短信发送调用触发类
 * @access public
 * @param void
 * @return void
 */
class  taoexlib_sms
{
    /**
     *
     * 静态私有变量事件类型
     * @var array
     */
    static private $__eventType = '';

    /**
     *
     * 静态私有变量事件对象
     * @var array
     */
    static private $__eventObj = null;

    /**
     *
     * 静态私有变量事件类型白名单定义
     * @var array
     */
    static private $__eventTypes = array(
        'delivery'=>array('type' => 'delivery','name' => "发货"),
        'o2opickup'=>array('type' => 'o2opickup','name' => "门店自提"),
        'o2oship'=>array('type' => 'o2oship','name' => "门店配送"),
        'einvoice'=>array('type' => 'einvoice','name' => "电子发票"),
        'express'=>array('type' => 'express','name' => "发货揽收"),
        'received'=>array('type' => 'received','name' => "发货签收"),
        'login'=>array('type' => 'login','name' => "登录"),
    );

    /**
     * 获取发送短信的触发事件类型列表
     *
     * @param void
     * @return void
     */
    static public function getEventTypes(){
        return self::$__eventTypes;
    }

    public function getTmplConfByEventType($type){
        $params['event_type'] = $type;

        //初始化服务
        if(!$this->initService($params)){
            return false;
        }

        return $this->getTmplConf();
    }

    private function getTmplConf(){
        return self::$__eventObj->getTmplConf();
    }

    /**
     * 发送短信的触发调用方法
     *
     * @param void
     * @return void
     */
    function sendSms($params, &$error_msg){

        //初始化服务
        if(!$this->initService($params)){
            return false;
        }

        //检查相应参数
        if(!$this->checkParams($params, $error_msg)){
            return false;
        }

        //组织发送数据
        if(!$this->formatContent($params, $sendParams, $error_msg)){
            return false;
        }
        $sendParams['direct_send'] = isset($params['direct_send']) && $params['direct_send'] === true;

        //执行短信发送
        return $this->doSendSms($sendParams, $error_msg);
    }

    /**
     * 初始化短信发送服务
     *
     * @param array $params
     * @return boolean true/false
     */
    private function initService($params){
        //必要参数触发的事件类型不能为空
        if(!isset($params['event_type']) || empty($params['event_type']) || !isset(self::$__eventTypes[$params['event_type']])){
            return false;
        }else{
            self::$__eventType = $params['event_type'];
        }

        $class_name = sprintf('taoexlib_sms_event_%s', self::$__eventType);
        try{
            if (class_exists($class_name)){
                self::$__eventObj = kernel::single($class_name);
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 检查短信发送的参数
     *
     * @param array $params
     * @return boolean true/false
     */
    private function checkParams(&$params, &$error_msg){
        return self::$__eventObj->checkParams($params, $error_msg);
    }

    /**
     * 格式化组织发送的短信内容及参数
     *
     * @param array $params
     * @param array $sendParams
     * @return boolean true/false
     */
    private function formatContent($params, &$sendParams, &$error_msg){
        return self::$__eventObj->formatContent($params, $sendParams, $error_msg);
    }

    /**
     * 最终发送短信动作
     *
     * @param array $sendParams
     * @param string $error_msg
     * @return boolean true/false
     */
    private function doSendSms($sendParams, &$error_msg){
        //临时变量定义
        $phone = $sendParams["phones"];
        $content = $sendParams['content'];

        //检查短信发送开关是否开启
        $switch=app::get("taoexlib")->getConf('taoexlib.message.switch');
        if($switch == 'on'){

            //检查发送短信的手机号是否在黑名单中
            if($this->checkBlackTel($phone)){
                $smsresult=taoexlib_utils::send_notice($param, $sendParams);
                if($smsresult){
                    return true;
                }else{
                    return false;
                }
            }else{
                $error_msg = '该手机号处于免打扰列表中';
                $this->writeSmslog($phone,$content,$error_msg,0);
                return false;
            }
        }else{
            return true;
        }
    }

    /*
     * 检测是否在免打扰手机号列表中
     * 将手机号放进去验证，检查该手机号是否处于免打扰列表中
     */
     private function checkBlackTel($tel){
        $blacklist=app::get('taoexlib')->getConf("taoexlib.message.blacklist");
        $blarr=explode("##",$blacklist);
        if(!in_array($tel,$blarr)){
            return true;
        }else{
            return false;
        }
     }

    /*
    * wujian@shopex.cn
    * 短信日志
    * 2012年2月21日
    * @param $phonearr 电话号码
    * @param $delivery_bn 发货单号
    * @param $logo 快递单号
    * @param $content 发送内容
    * @param $msg 短信状态信息
    * @param $status 短信状态
    */
    public function writeSmsLog($phone,$content,$msg,$status){
        $messlog = app::get('taoexlib')->model("log");
        $messlogdata = array(
            'mobile'=>$phone,
            'batchno'=>'',
            'content'=>$content,
            'sendtime'=>time(),
            'msg'=>$msg,
            'status' =>$status,
        );

        $messlog->insert($messlogdata);		
    }
}
