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
 * @author chenping 2016/8/22
 * @describe alipay/JD钱包
 *
 * __adapter 固定 matrix（矩阵、config/result 解析依赖）；请求类物理路径由路由单独拼为 lib/ipay/{node_type}/request/*.php
 * node_type → __platform；biz_type → __platform_business（路由插入 biz 段）
 */
class erpapi_channel_ipay extends erpapi_channel_abstract
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
        $channelMdl = app::get('channel')->model('channel');
        $filter = $channel_id ? array('channel_id'=>$channel_id) : array('node_id'=>$node_id);
        $channel = $channelMdl->db_dump($filter);

        if (!$channel || $channel['channel_type'] != 'ipay') return false;

        $this->__platform = $channel['node_type'];
        $this->__adapter  = '';
        $this->__platform_business = isset($channel['biz_type']) ? (string) $channel['biz_type'] : '';

        $this->channel = $channel;

        return true;
    }
}