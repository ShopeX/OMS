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
 

class desktop_ctl_email extends desktop_controller{
    var $workground = 'desktop_ctl_system';
    
     public function __construct($app)
    {
        parent::__construct($app);
        header("cache-control: no-store, no-cache, must-revalidate");
    }
    
    function setting(){
        #print_r($this->app->getConf('email.config.sendway'));exit;
        $this->pagedata['options'] = $this->getOptions();
        $this->pagedata['messengername'] = "messenger";
        $this->display('email/config.html');
    }
    
     function getOptions(){
        return array(
            'sendway'=>array('label'=>app::get('desktop')->_('发送方式'),'type'=>'radio','options'=>array('mail'=>app::get('desktop')->_("使用本服务器发送"),'smtp'=>app::get('desktop')->_("使用外部SMTP发送")),'value'=>$this->app->getConf('email.config.sendway')?$this->app->getConf('email.config.sendway'):"mail"),
            'usermail'=>array('label'=>app::get('desktop')->_('发信人邮箱'),'type'=>'input','value'=>$this->app->getConf('email.config.sendway')?$this->app->getConf('email.config.usermail'):'yourname@domain.com'),
            'smtpserver'=>array('label'=>app::get('desktop')->_('smtp服务器地址'),'type'=>'input','value'=>$this->app->getConf('email.config.smtpserver')?$this->app->getConf('email.config.smtpserver'):'mail.domain.com'),
            'smtpport'=>array('label'=>app::get('desktop')->_('smtp服务器端口'),'type'=>'input','value'=>$this->app->getConf('email.config.smtpport')?$this->app->getConf('email.config.smtpport'):'25'),
            'smtpuname'=>array('label'=>app::get('desktop')->_('smtp用户名'),'type'=>'input','value'=>$this->app->getConf('email.config.smtpuname')?$this->app->getConf('email.config.smtpuname'):''),
            'smtppasswd'=>array('label'=>app::get('desktop')->_('smtp密码'),'type'=>'password','value'=>$this->app->getConf('email.config.smtppasswd')?$this->app->getConf('email.config.smtppasswd'):'')
        );
    }
    
    function saveCfg(){
       # $this->begin('index.php?app=desktop&ctl=email&act=setting');
       $this->begin();
           foreach($_POST['config'] as $key=>$value){
            $this->app->setConf('email.config.'.$key,$value);
        } 
        $this->end(true,app::get('desktop')->_('配置保存成功'));
    }
      function testEmail(){
        $this->pagedata['options'] = $_GET['config'];
        if ($_GET['config']['sendway']=="mail")
            $this->pagedata['acceptor']=$_GET['config']['usermail'];
        $this->display('email/testemail.html');
    }
    
    
     function doTestemail(){
        $usermail = $_POST['usermail'];     //发件账户
        $smtpport = $_POST['smtpport'];     //端口号
        $smtpserver = $_POST['smtpserver']; //邮件服务器
        $smtpuname = $_POST['smtpuname'];   //账户名称
        $smtppasswd  = $_POST['smtppasswd'];//账户密码
        $acceptor = $_POST['acceptor'];     //收件人邮箱

        $subject = app::get('desktop')->_("来自[").app::get('site')->getConf('site.name').app::get('desktop')->_("]网店的测试邮件");
        $body = app::get('desktop')->_("这是一封测试邮箱配置的邮件，您的网店能正常发送邮件。");

        switch ($_POST['sendway']){
            case 'smtp':
                $email = kernel::single('desktop_email_email');
                $loginfo = app::get('desktop')->_("无法发送测试邮件，下面是出错信息：");
                if ($email->ready($_POST)){
                    $res = $email->send($acceptor,$subject,$body,$_POST);
                    if ($res)
                        $loginfo = app::get('desktop')->_("已成功发送一封测试邮件，请查看接收邮箱。");
                    if ($email->errorinfo){
                        $err=$email->errorinfo;
                        $loginfo .= "<br>".$err['error'];
                    }
                }
                else{
                    $loginfo .= "<br>".var_export($email->smtp->error,true);
                }
                echo $loginfo;
                break;
            case 'mail':
                ini_set('SMTP',$smtpserver);
                ini_set('smtp_port',$smtpport);
                ini_set('sendmail_from',$usermail);
                $email = kernel::single('desktop_email_email');
                $subject=$email->inlineCode($subject);
                $header = array(
                    'Return-path'=>'<'.$usermail.'>',
                    'Date'=>date('r'),
                    'From'=>$email->inlineCode($this->app->getConf('system.shopname')).'<'.$usermail.'>',
                    'MIME-Version'=>'1.0',
                    //'Subject'=>$subject,
                    //'To'=>$acceptor,
                    'Content-Type'=>'text/html; charset=UTF-8; format=flowed',
                    'Content-Transfer-Encoding'=>'base64'
                );
                $body=chunk_split(base64_encode($body));
                $header=$email->buildHeader($header);
                if(mail($acceptor, $subject, $body, $header)){
                    echo app::get('desktop')->_("发送成功！");
                }
                else{
                    echo app::get('desktop')->_("发送失败，请检查邮箱配置！");
                }
                break;
        }
    }
}
?>
