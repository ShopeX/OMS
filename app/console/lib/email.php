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

class console_email{

    #邮件发送
    public function send($receiveMail, $subject, $body, $attachment = [], $SMTPDebug = 0){
        $config = $this->get_config();
        //收件信息
        $receive_mail = explode(';',$receiveMail);
        $to = array();
        foreach($receive_mail as $receive){
            if(!$receive){
                continue;
            }
            $receive = explode('#',$receive);
            if($receive[1]) {
                $to[] = array('email'=>$receive[0],'name'=>$receive[1]);
            }
        }
        if(!$to || !$subject || !$body || !$config){
            $err_msg = '邮件发送的必要信息缺失';
            return [false, $err_msg];
        }
        
        //init lib
        $emailLib = new console_email_email;
        $emailLib->SMTPDebug = $SMTPDebug;
        $emailLib->CharSet = "UTF-8";
        $emailLib->IsSMTP();
        $emailLib->SMTPAuth = true;

        if($config['smtp_ssl'] == '1'){
            $emailLib->SMTPSecure = 'ssl';
        }

        $emailLib->isHTML(true);

        $emailLib->Host = $config['smtp_server'];
        $emailLib->Port = $config['smtp_port'];
        $emailLib->Username = $config['email'];
        $emailLib->From = $config['email'];
        $emailLib->FromName = $config['name'];
        $emailLib->Password = $config['psw'];

        //set send list
        foreach($to as $send){
            if($send['email'] && $send['name']){
                $emailLib->AddAddress($send['email'], $send['name']);
            }
        }

        //add attachment
        if($attachment){
            $emailLib->AddAttachment($attachment);
        }

        $emailLib->Subject = $subject;
        $emailLib->Body = $body;

        //发送邮件
        try {
            $emailLib->Send();
            return [true];
        } catch (Exception $e) {
            $err_msg = $emailLib->ErrorInfo;
            return [false, $err_msg];
        }
    }

    //获取发送邮件设置
    public function get_config(){
        $rs_mail = app::get('console')->getConf('email.config');
        return $rs_mail;
    }

    //获取邮件附件全路径
    public function get_send_email_attachement_path($type){
        //获取邮件附件存放目录
        $file_dir = DATA_DIR.'/email_attachement';
        if(!is_dir($file_dir)){
            utils::mkdir_p($file_dir, 0777, ture);
        }
        //获取邮件附件名
        $filename = date("Y-m-d",time()).$type.'.csv';
        $attachement_path = $file_dir.'/'.$filename;
        return $attachement_path;
    }
    
}