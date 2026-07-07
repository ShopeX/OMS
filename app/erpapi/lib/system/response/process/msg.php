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

class erpapi_system_response_process_msg
{
    const SESSION_EXPIRE_WARNING_DAYS = 7;

    public function notify($sdf)
    {
        if (!$sdf['node_id']) {
            return array('rsp' => 'fail', 'msg' => '节点不能为空');
        }
        
        $shopMdl = app::get('ome')->model('shop');
        
        $shop = $shopMdl->dump(array('node_id' => $sdf['node_id']), 'shop_id,addon,name');

        if (!$shop) {
            return array('rsp' => 'fail', 'msg' => '店铺未绑定');
        }

        if (isset($sdf['content']['access_token_available'])) {
            return $this->processAccessTokenNotify($shopMdl, $shop, $sdf);
        }
        
        $rpcNotifyMdl = app::get('base')->model('rpcnotify');
        $sdf['content']['info'] = '【' . $shop['name'] . '】' . $sdf['content']['info'];
        $data         = [
            'callback'   => '',
            'rsp'        => 'succ',
            'msg'        => json_encode($sdf['content'],JSON_UNESCAPED_UNICODE),
            'notifytime' => strtotime($sdf['date'])
        ];
        $rpcNotifyMdl->insert($data);
       
        // 店铺到期主动提醒
        kernel::single('monitor_event_notify')->addNotify('system_message', [
            'errmsg'         => $sdf['content']['info'],
        ]);

        return array('rsp' => 'succ', 'msg' => '消息已接收');
    }

    protected function processAccessTokenNotify($shopMdl, $shop, $sdf)
    {
        $tokenAvailable = (string) $sdf['content']['access_token_available'];
        if ($tokenAvailable === '-1') {
            $warnMsg = '【' . $shop['name'] . '】 access_token 已失效，请及时处理，'.(isset($sdf['content']['info']) && !empty($sdf['content']['info']) ?  $sdf['content']['info'] : '') ;
            $this->sendSystemNotify($warnMsg);

            return array('rsp' => 'succ', 'msg' => '消息已接收');
        }

        if ($tokenAvailable !== '1') {
            return array('rsp' => 'succ', 'msg' => '消息已接收');
        }

        $expireTime = trim((string) $sdf['content']['access_token_expire_in']);
        if (!$expireTime) {
            return array('rsp' => 'fail', 'msg' => 'access_token_expire_in不能为空');
        }

        $expireTimestamp = strtotime($expireTime);
        if (!$expireTimestamp) {
            return array('rsp' => 'fail', 'msg' => 'access_token_expire_in格式错误');
        }

        $addon = is_array($shop['addon']) ? $shop['addon'] : array();
        $addon['session_expire_time'] = $expireTimestamp;
        $shopMdl->update(array('addon' => $addon), array('shop_id' => $shop['shop_id']));

        if (($expireTimestamp - time()) < self::SESSION_EXPIRE_WARNING_DAYS * 86400) {
            $warnMsg = '【' . $shop['name'] . '】 access_token 将于7天内过期，请及时处理，到期时间：'.$expireTime.(isset($sdf['content']['info']) && !empty($sdf['content']['info']) ?  $sdf['content']['info'] : '') ;
            $this->sendSystemNotify($warnMsg);
        }

        return array('rsp' => 'succ', 'msg' => '消息已接收');
    }

    protected function sendSystemNotify($message)
    {
        kernel::single('monitor_event_notify')->addNotify('system_message', [
            'errmsg' => $message,
        ]);
    }
}
