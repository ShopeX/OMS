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

class console_ctl_admin_email extends desktop_controller {

    var $workground = 'setting_tools';
    public function index(){
        $rs_mail = app::get('console')->getConf('email.config');
        $this->pagedata['data'] = $rs_mail;
        $this->page('admin/email/config.html');
    }

    function save(){
        $this->begin('index.php?app=console&ctl=admin_email&act=index');
        $post = $_POST['mail'];
        $result = app::get('console')->setConf('email.config', $post);
        if($result){
            $this->end(true,app::get('desktop')->_('保存成功'));
        }else{
            $this->end(false,app::get('desktop')->_('保存失败'));
        }
    }

    function testEmail(){
        $this->display('admin/email/testemail.html');
    }

     function doTestemail(){
        $subject = app::get('desktop')->_("测试邮件");
        $body = app::get('desktop')->_("这是一封测试邮箱配置的邮件，您的网店能正常发送邮件。");
        list($rs, $msg) = kernel::single('console_email')->send($_POST['acceptor'],$subject,$body,[],4);
        if($rs) {
            //echo "已成功发送一封邮件，请查收";
        } else {
            echo $msg;
        }
    }
}