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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
class desktop_email_email{
    var $hasTitle = true; //是否有标题
    var $maxtime = 300; //发送超时时间 ,单位:秒
    var $maxbodylength =300; //最多字符
    var $allowMultiTarget=false; //是否允许多目标
    var $targetSplit = ',';
    var $Sendmail          = "/usr/sbin/sendmail";
   
     function ready($config){
        $this->smtp = kernel::single('desktop_email_smtp');
        if($config['sendway']=='smtp'){
            if(!$this->SmtpConnect($config)) return false;
        }
        return true;
    }
    /**
     * finish
     * 可选方法，结束发送时触发
     *
     * @param mixed $config
     * @access public
     * @return void
     */
    function finish($config){
        if($config['sendway']=='smtp'){
            $this->SmtpClose();
        }
    }

    /**
     * send
     * 必有方法,发送时调用
     *
     * config参数为getOptions取得的所有项的配置结果
     *
     * @param mixed $to
     * @param mixed $message
     * @param mixed $config
     * @access public
     * @return void
     */
  function send($to, $subject, $body, $config){
        $this->Sender = $config['usermail'];
        $this->Subject = $this->inlineCode($subject);

        $header = array(
            'Return-path'=>'<'.$config['usermail'].'>',
            'Date'=>date('r'),
            'From'=>$this->inlineCode($config['shopname']).'<'.$config['usermail'].'>',
            #'From' =>'sss',
            'MIME-Version'=>'1.0',
            'Subject'=>$this->Subject,
            'To'=>$to,
            'Content-Type'=>'text/html; charset=UTF-8; format=flowed',
            'Content-Transfer-Encoding'=>'base64'
        );
        $config['sendway']=($config['sendway'])?$config['sendway']:'smtp';
        if($config['sendway'] == 'mail'){
            unset($header['Subject']);
            unset($header['To']);
        }
        $body = chunk_split(base64_encode($body));
        $header = $this->buildHeader($header);
        switch($config['sendway'])
        {
            case "sendmail":
                $result = $this->SendmailSend($to,$header, $body);
                break;
            case "mail":
                $result = $this->MailSend($to,$header, $body);
                break;
            case "smtp":
                $result = $this->SmtpSend($to,$header, $body,$config);
                break;
            default:
               // trigger_error('mailer_not_supported',E_ERROR);
                $result = false;
                break;
        }

        return $result;
    }

    function inlineCode($str){
        $str = trim($str);
        return $str?'=?UTF-8?B?'.base64_encode($str).'?= ':'';
    }

    function buildHeader($headers){
        $ret = '';
        foreach($headers as $k=>$v){
            $ret.=$k.': '.$v."\n";
        }
        return $ret;
    }
    /**
     * Sends mail using the $Sendmail program.
     * @access private
     * @return bool
     */
    function SendmailSend($header, $body) {
        if ($this->Sender != "")
            $sendmail = sprintf("%s -oi -f %s -t", $this->Sendmail, $this->Sender);
        else
            $sendmail = sprintf("%s -oi -t", $this->Sendmail);

        if(!@$mail = popen($sendmail, "w"))
        {
            $this->__maillog();
            return false;
        }

        fputs($mail, $header);
        fputs($mail, $body);

        $result = pclose($mail) >> 8 & 0xFF;
        if($result != 0)
        {
            $this->__maillog();
            return false;
        }

        return true;
    }

    /**
     * Sends mail using the PHP mail() function.
     * @access private
     * @return bool
     */
    function MailSend($to,$header, $body) {

        if (strlen(ini_get("safe_mode"))< 1)
        {

            $old_from = ini_get("sendmail_from");
            ini_set("sendmail_from", $this->Sender);
            $params = sprintf("-oi -f %s", $this->Sender);
            $rt = @mail($to, $this->Subject, $body,
                $header);
        }
        else
            $rt = mail($to, $this->Subject, $body, $header);

        if (isset($old_from))
            ini_set("sendmail_from", $old_from);

        if(!$rt)
        {
            //trigger_error("instantiate",E_ERROR);
            return false;
        }

        return true;
    }

    /**
     * Sends mail via SMTP using PhpSMTP (Author:
     * Chris Ryan).  Returns bool.  Returns false if there is a
     * bad MAIL FROM, RCPT, or DATA input.
     * @access private
     * @return bool
     */
    function __maillog(){
        $this->errorinfo = $this->smtp->error;
        if(MAIL_LOG){
                error_log(var_export($this->smtp->error,true)."\n", 3, DATA_DIR."/mail.log");
        }
    }
    function SmtpSend($to,$header, $body,$config) {
        $smtp_from = $this->Sender;
        if(!$this->smtp->Mail($smtp_from))
        {
            $this->__maillog();
//            trigger_error("from_failed");
            $this->smtp->Reset();
            return false;
        }

        if(!$this->smtp->Recipient($to)){
            $this->__maillog();
//            trigger_error("recipients_failed". $to);
            $this->smtp->Reset();
            return false;
        }

        if(!$this->smtp->Data($header ."\n". $body))
        {
            $this->__maillog();
            $this->smtp->Reset();
            return false;
        }

        $this->smtp->Reset();
        //$this->SmtpClose();
        return true;
    }

    /**
     * Initiates a connection to an SMTP server.  Returns false if the
     * operation failed.
     * @access private
     * @return bool
     */
    function SmtpConnect($config) {
        $this->smtp->do_debug = $this->debug;
        $index = 0;
        $this->smtp = kernel::single('desktop_email_smtp');
        $connection = ($this->smtp->Connected());

        if($this->smtp->Connect($config['smtpserver'], $config['smtpport'],20))
        {
            $this->smtp->Hello($_SERVER['HTTP_HOST']?$_SERVER['HTTP_HOST']:'localhost.localdomain');

            if($config['smtpuname'] && !$this->smtp->Authenticate($config['smtpuname'],$config['smtppasswd'])){
       //         trigger_error("authenticate");
                $this->smtp->Reset();
                $connection = false;
            }
            $connection = true;
        }



        return $connection;
    }

    /**
     * Closes the active SMTP session if one exists.
     * @return void
     */
    function SmtpClose() {
        if($this->smtp != NULL)
        {
            if($this->smtp->Connected())
            {
                $this->smtp->Quit();
                $this->smtp->Close();
            }
        }
    }

}
?>
