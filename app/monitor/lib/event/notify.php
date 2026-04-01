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
 * @Vsersion: 2022/10/13
 * @Describe: 预警通知Lib类
 */
class monitor_event_notify
{
    /**
     * 添加预警通知
     * @Author: xueding
     * @Vsersion: 2022/10/17 下午4:06
     * @param $eventType
     * @param array $params
     * @param string $send_type
     * @param string $is_sync
     * @return bool
     */
    public function addNotify($eventType, $params = array(), $is_sync = false)
    {
        $eventTemplateMdl = app::get('monitor')->model('event_template');
        $eventNotifyMdl   = app::get('monitor')->model('event_notify');
        $templateList     = $eventTemplateMdl->getList('*',[
            'event_type' => $eventType,
            'status'     => '1',
            'disabled'   => 'false',
        ]);
        if ($templateList) {
            foreach ($templateList as $key => $templateInfo) {
                $notifyData = array();
                //插入数据
                $notifyData['template_id']      = $templateInfo['template_id'];
                $notifyData['event_type']       = $eventType;
                $notifyData['original_content'] = $templateInfo['content'];
                $notifyData['send_content']     = ($templateInfo['send_type'] == 'workwx' ? '**域名:'.kernel::base_url(1)."**\n" : '').$this->getNotifyParams($templateInfo, $params);
                $notifyData['send_type']        = $templateInfo['send_type'];
                $notifyData['params']           = json_encode($params);
                $notifyData['file_path']        = json_encode($params['file_path'],JSON_UNESCAPED_SLASHES);
                $notifyData['org_id']           = $params['org_id'];
                if(kernel::database()->isInTransaction() && !$is_sync) {
                    register_shutdown_function(function() use($notifyData) {
                        $eventNotifyMdl   = app::get('monitor')->model('event_notify');
                        $eventNotifyMdl->insert($notifyData);
                    }, $notifyData);
                    $result = false;
                } else {
                    $result = $eventNotifyMdl->insert($notifyData);
                }
                //保存发送内容
                if (!$result) {
                    continue;
                }
//                $push_params = array(
//                    'data' => [
//                        'task_type' => 'sendnotify',
//                        'notify_id' => $result,
//                    ],
//                    'url' => kernel::openapi_url('openapi.autotask','service')
//                );
//
//                kernel::single('taskmgr_interface_connecter')->push($push_params);
                //同步发送
                if ($is_sync) {
                    //调用发送方法
                    if($params['receiver']) {
                        $notifyData['receiver'] = $params['receiver'];
                    }
                    $this->sendNotify($result, $notifyData);
                }
            }
            return true;
        } else {
            return true;
        }
    }
    
    public function sendNotify($notifyId, $notifyInfo = array())
    {
        if (!$notifyId) {
            return false;
        }
        $eventNotifyMdl = app::get('monitor')->model('event_notify');
        //通知内容
        if (!$notifyInfo) {
            $notifyInfo = $eventNotifyMdl->db_dump($notifyId);
        }
        
        if (!$notifyInfo) {
            return false;
        }
        
        if ($notifyInfo['status'] == '1' || $notifyInfo['status'] == '3' ) {
            return ['rsp' =>'fail','msg'=> '已发送不能重复发送'];
        }
        //关闭模板不发送内容
        $eventTemplateMdl = app::get('monitor')->model('event_template');
        $eventTemplateInfo = $eventTemplateMdl->db_dump(['template_id'=>$notifyInfo['template_id']]);
        if ($eventTemplateInfo['disabled'] == 'true') {
            $msg = '模板已关闭取消发送';
            $eventNotifyMdl->update(['status' => '2','send_result'=>$msg], ['notify_id' => $notifyInfo['notify_id']]);
            return ['rsp' =>'fail','msg'=> $msg,'notify_id'=>$notifyInfo['notify_id']];
        }
        $notifyInfo['template_name'] = $eventTemplateInfo['template_name'];
        // 更新为处理中
        $eventNotifyMdl->update(['status' => '3'], ['notify_id' => $notifyId]);
        
        $res = kernel::single('monitor_event_trigger_notify_router')
            ->set_send_type($notifyInfo['send_type'])
            ->send($notifyInfo);
        
        //更新发送状态
        if ($res && $res['rsp'] == 'succ') {
            $status = '1';
        } else {
            $status = '2';
        }
        $eventNotifyMdl->update(['status' => $status,'send_result'=>$res['msg']], ['notify_id' => $notifyInfo['notify_id']]);
    
        $res['notify_id'] = $notifyInfo['notify_id'];
        return $res;
    }
    
    /**
     * 转换发送内容
     * @Author: xueding
     * @Vsersion: 2022/10/17 下午3:58
     * @param $templateInfo
     * @param $params
     * @return string|string[]
     */
    public function getNotifyParams($templateInfo, $params)
    {
        /**
         * 示例
         * $params = ['order_bn'=>['1234','567'],'list'=>[['aaa'='111','bbb'=>'222'],['aaa'='333','bbb'=>'444']],'file_path'=>['export/tmp_local/111.txt','export/tmp_local/readme.txt']];
         * $templateInfo['content']： 订单有问题订单号为：{order_bn} <{ 循环内容1{aaa},循环内容2{bbb}}>
         */
        if ($params) {
            $find    = array();
            $replace = array();
            if ($params['list']) {
                $sendContent = $templateInfo['content'];
                //开始结尾数据
                $tmpData = [];
                $matches = [];
                preg_match_all('|<{(.*)}>|',$sendContent,$matches,PREG_PATTERN_ORDER);
                $startEndStr = explode($matches[0][0],$sendContent);
                //中间循环数据
                $oldForStr = $matches[0][0];
                $oldReplaceForStr = $matches[1][0];
    
                $list = $params['list'];
                unset($params['list']);
                //开始结尾内容替换
                foreach ($params as $key => $val) {
                    $startFind[] = '{'.$key.'}';
                    if (is_array($val)) {
                        $val = implode(',', $val);
                    }
                    $replace[] = $val;
                }
                foreach ($startEndStr as $val) {
                    $tmpData[$val] = str_replace($startFind, $replace, $val);
                }
                //中间循环内容替换
                foreach ($list as $lk => $lv) {
                    $replace = array();
                    foreach ($lv as $key => $val) {
                        $bodyFind[] = '{'.$key.'}';
                        if (is_array($val)) {
                            $val = implode(',', $val);
                        }
                        $replace[] = $val;
                    }
                    $tmpData[$oldForStr] .= str_replace($bodyFind, $replace, $oldReplaceForStr). "<br>";
                }
                //组装发送内容
                if ($tmpData) {
                    foreach ($tmpData as $key => $val) {
                        $sendContent = str_replace($key,$val,$sendContent);
                    }
                }
                return $sendContent;
            }else{
                foreach ($params as $key => $val) {
                    $find[] = '{'.$key.'}';
                    if (is_array($val)) {
                        $val = implode(',', $val);
                    }
                    $replace[] = $val;
                }
                $content = str_replace($find, $replace, $templateInfo['content']);
                return $content;
            }
        }
    }
    
    /**
     * 自动清除同步日志
     * 每天检测将超过30天的发送数据清除
     */
    public function clean($clean_time = 30){
        $db = kernel::database();
        
        $time = time();
        
        $where = " WHERE `at_time`<'".date('Y-m-d',$time-$clean_time*24*60*60).' 00:00:00'."' ";
        
//        // 只查询一条数据，判断是否有数据
//        $select_sql = "SELECT notify_id FROM sdb_monitor_event_notify ". $where ." LIMIT 0, 1";
//        $dataList = $db->select($select_sql);
//        if(empty($dataList)){
//            return true;
//        }
        
        // 删除数据
        $del_sql = " DELETE FROM `sdb_monitor_event_notify` $where ";
        $db->exec($del_sql);
        
        return true;
    }
}