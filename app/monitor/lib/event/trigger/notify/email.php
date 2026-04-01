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
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/18
 * @Describe: 预警通知邮件发送
 */
class monitor_event_trigger_notify_email extends monitor_event_trigger_notify_common
{
    public function send($notifyInfo)
    {
        $eventReceiverMdl = app::get('monitor')->model('event_receiver');
        $eventGroupMdl = app::get('monitor')->model('event_group');
        $eventGroupTempMdl = app::get('monitor')->model('event_group_template');
    
        $emailConfig = app::get('monitor')->getConf('email.config');
        $usermail    = $emailConfig['usermail'];     //发件账户
        $smtpport    = $emailConfig['smtpport'];     //端口号
        $smtpssl     = $emailConfig['smtpssl'];     //是否启用SSL方式
        $smtpserver  = $emailConfig['smtpserver']; //邮件服务器
        $smtpuname   = $emailConfig['usermail'];   //账户名称
        $smtppasswd  = $emailConfig['smtppasswd'];//账户密码
        $fromName    = $emailConfig['fromname'] ?: 'OMS系统';//发送账户名称
        
        if (empty($notifyInfo)) {
            return ['rsp' =>'fail','msg'=> '发送失败，发送内容为空'];
        }
        if ($notifyInfo['status'] == '1' ) {
            return ['rsp' =>'fail','msg'=> '已发送不能重复发送'];
        }
        if (!$usermail || !$smtpserver || !$smtpuname || !$smtppasswd) {
            return ['rsp' =>'fail','msg'=> '配置信息异常'];
        }
        
        if (!$notifyInfo['receiver']) {
            $orgWhere = '';
            if ($notifyInfo['org_id']) {
                $orgWhere = " 1 AND FIND_IN_SET('".$notifyInfo['org_id']."',org_id) ";
            }
//            $filter = ['filter_sql' => "FIND_IN_SET('".$notifyInfo['event_type']."',event_type) ".$orgWhere,'send_type'=>$notifyInfo['send_type']];
//            $receiverInfo = $eventReceiverMdl->getList('receiver',$filter);
            $filter = ['filter_sql' => "FIND_IN_SET('".$notifyInfo['event_type']."',event_type) "];
            $groupTempInfo = $eventGroupTempMdl->getList('group_id',$filter);
            if ($groupTempInfo) {
                $where = ['group_id'=>array_column($groupTempInfo,'group_id')];
                if ($orgWhere) {
                    $where['filter_sql'] = $orgWhere;
                }
                $groupInfo = $eventGroupMdl->getList('receiver_id',$where);
                $receiverId = [];
                if ($groupInfo) {
                    foreach ($groupInfo as $key => $val) {
                        $receiverId = array_merge($receiverId,explode(',',$val['receiver_id']));
                    }
                }
                $receiverInfo = $eventReceiverMdl->getList('receiver',['id'=>$receiverId]);
            }
        }else{
            $receiverInfo = [];
            if(is_array($notifyInfo['receiver'])) {
                foreach($notifyInfo['receiver'] as $receiver) {
                    $receiverInfo[] = ['receiver'=>$receiver];
                }
            }else{
                $receiverInfo[] = ['receiver'=>$notifyInfo['receiver']];
            }
        }
        // 安全检查：确保$receiverInfo有值且包含有效的receiver字段
        if (empty($receiverInfo) || !is_array($receiverInfo)) {
            return ['rsp' =>'fail','msg'=> '接收者信息为空'];
        }
        
        $receivers = array_column($receiverInfo, 'receiver');
        // 过滤掉空的receiver值
        $receivers = array_filter($receivers, function($receiver) {
            return !empty($receiver);
        });
        
        if (empty($receivers)) {
            return ['rsp' =>'fail','msg'=> '没有有效的接收者邮箱地址'];
        }
        
        $sendEmails = implode(',<br>', $receivers);
        $eventNotifyMdl = app::get('monitor')->model('event_notify');
        $eventNotifyMdl->update(['mailing_address' => $sendEmails], ['notify_id' => $notifyInfo['notify_id']]);
    
        $emailLib          = new console_email_email;
        $emailLib->CharSet = "UTF-8";
        $emailLib->IsSMTP();
        $emailLib->SMTPAuth = true;
        
        $emailLib->SMTPSecure = $smtpssl ? 'ssl' : '';
        
        $emailLib->isHTML(true);
        
        $emailLib->Host     = $smtpserver;
        $emailLib->Port     = $smtpport;
        $emailLib->Username = $smtpuname;
        $emailLib->From     = $usermail;
        $emailLib->FromName = $fromName;
        $emailLib->Password = $smtppasswd;
        
        foreach ($receiverInfo as $val) {
            $emailLib->AddAddress($val['receiver']);
        }
        if ($notifyInfo['file_path']) {
            foreach (json_decode($notifyInfo['file_path']) as $path) {
                $attachment =  $path;
                if ($attachment && file_exists($path)) {
                    $emailLib->AddAttachment($attachment);
                }
            }
        }
        $eventTemplateLib = kernel::single('monitor_event_template');
        $eventType        = $eventTemplateLib->getEventType();
        
        $emailLib->Subject = $notifyInfo['template_name'] ?? $eventType[$notifyInfo['event_type']];
        $emailLib->Body    = $notifyInfo['send_content'];
        
        $res = $emailLib->Send();
        if ($res) {
            return ['rsp' =>'succ','msg'=>'发送成功'];
        }
        return ['rsp' =>'fail','msg'=> $emailLib->ErrorInfo];
    
    }
}
