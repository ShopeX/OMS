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
 * @author ykm 2016-01-19
 * @describe 短信发送接口
 */
class erpapi_channel_sms extends erpapi_channel_abstract
{
    public $channel;

    /**
     * 初始化
     * @param mixed $node_id ID
     * @param mixed $channel_id ID
     * @return mixed 返回值
     */

    public function init($node_id,$channel_id)
    {
        $channel = app::get('channel')->model('channel')->db_dump($channel_id, '*');
        if (!$channel || $channel['channel_type'] != 'sms' || $channel['disabled'] == 'true') { return false; }

        $adapter = app::get('channel')->model('adapter')->db_dump(array('channel_id' => $channel_id), '*');
        if (!$adapter || !$adapter['config']) { return false; }

        $account = unserialize($adapter['config']);
        if (!$account) { return false; }

        $this->__adapter = $adapter['adapter'];
        $this->__platform = $channel['node_type'];
        $this->channel = $channel;
        $this->channel['channel_id'] = $channel_id;
        $this->channel['account'] = $account;
        return true;
    }
}
