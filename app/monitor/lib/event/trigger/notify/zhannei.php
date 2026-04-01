<?php

/**
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/18
 * @Describe: 预警通知邮件发送
 */
class monitor_event_trigger_notify_zhannei extends monitor_event_trigger_notify_common
{
    public function send($notifyInfo)
    {
        if (!$notifyInfo['send_content']) {
            return ['rsp' => 'fail', 'msg' => '发送失败，发送内容为空'];
        }
        if ($notifyInfo['status'] == '1') {
            return ['rsp' => 'fail', 'msg' => '已发送不能重复发送'];
        }
        // 系统消息推送到 service（monitor.service.notify.zhannei），以便业务自定义扩展行为
        foreach (kernel::servicelist('monitor.service.notify.zhannei') as $object) {
            if (method_exists($object, 'send')) {
                $object->send($notifyInfo);
            }
        }

        try {
            // 获取rpcnotify模型
            $rpcNotifyMdl = app::get('base')->model('rpcnotify');
            
            // 准备插入数据
            $data = [
                'callback'   => '', // 空回调地址
                'rsp'        => 'succ', // 默认成功状态
                'msg'        => $notifyInfo['send_content'], // 发送内容作为消息
                'notifytime' => time(), // 当前时间戳
                'status'     => 'false', // 默认未读状态
            ];
            
            // 插入数据到rpcnotify表
            $result = $rpcNotifyMdl->insert($data);
            
            if ($result) {
                return ['rsp' => 'succ', 'msg' => '数据已成功写入rpcnotify表'];
            } else {
                return ['rsp' => 'fail', 'msg' => '数据写入失败'];
            }
        } catch (Exception $e) {
            return ['rsp' => 'fail', 'msg' => $e->getMessage()];
        }
    }
}
