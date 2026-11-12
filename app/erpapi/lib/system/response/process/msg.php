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
            $sdf['msg_type'] = 'system'; // 消息类型
            $sdf['event_type'] = $event_type;
            $sdf['shop_type'] = ($shopInfo['node_type'] ? $shopInfo['node_type'] : $shopInfo['shop_type']);
            $sdf['shop_bn'] = $shopInfo['shop_bn'];
            $sdf['org_id'] = intval($shopInfo['org_id']);
            
            return $this->processAccessTokenNotify($shopMdl, $shopInfo, $sdf);
        }
        
        $rpcNotifyMdl = app::get('base')->model('rpcnotify');
        $sdf['content']['info'] = '【' . $shop['name'] . '】' . $sdf['content']['info'];
        $data         = [
            'callback'   => '',
            'rsp'        => 'succ',
            'msg'        => json_encode($sdf['content'],JSON_UNESCAPED_UNICODE),
            'notifytime' => strtotime($sdf['date'])
        ];
        $insert_id = $rpcNotifyMdl->insert($data);
        
        // check
        if($event_type == 'system_auth_expire'){
            // 店铺即将到期提醒
            $data['rpcnotify_id'] = $insert_id;
            $data['content'] = $sdf['content']; // 完整 content，供到期天数/占位符解析
            kernel::single('ome_system_rpcnotify')->addShopEventNotify($data, $error_msg);
        }else{
            // 店铺服务提醒
            $notifyParams = [
                'rpcnotify_id' => $insert_id,
                'event_type' => $data['event_type'],
                'shop_type' => $data['shop_type'],
                'shop_bn' => $data['shop_bn'],
                'errmsg' => $sdf['content']['info'],
            ];
            kernel::single('monitor_event_notify')->addNotify($event_type, $notifyParams);
            
            // 授权已过期：回写店铺 is_expired / expired_time（已过期则跳过）
            if (isset($sdf['content']['info']) && mb_strpos($sdf['content']['info'], '授权已过期') !== false) {
                if((string) $shopInfo['is_expired'] !== 'true'){
                    // 店铺到期时间
                    $deadLine = isset($sdf['content']['dead_line']) ? trim((string) $sdf['content']['dead_line']) : '';
                    $expiredTime = $deadLine !== '' ? strtotime($deadLine) : false;
                    
                    // 是否需要更新到期时间
                    $is_update_expired_time = true;
                    if (!$expiredTime) {
                        // 矩阵未传过期时间，只有店铺未存在到期时间时，才更新到期时间
                        $expiredTime = time();
                        $is_update_expired_time = false;
                    }
                    
                    // data
                    $updateShopData = [
                        'is_expired' => 'true',
                    ];
                    
                    // 是否需要更新到期时间
                    if(empty($shopInfo['expired_time']) || $is_update_expired_time){
                        $updateShopData['expired_time'] = $expiredTime;
                    }
                    
                    // 更新店铺到期
                    $shopMdl->update($updateShopData, array('shop_id' => $shopInfo['shop_id']));
                }
            }
        }
        
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
