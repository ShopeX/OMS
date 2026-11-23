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
 * CONFIG
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class channel_ticket_config 
{
    public function formatConfig($config, $channel)
    {

        // 如果有channel_id 则补充channel_adapter
        if(isset($channel['channel_id']) && $channel['channel_id']){
            $channelMdl = app::get('channel')->model('channel');
            $oldChannel = $channelMdl->db_dump($channel['channel_id'], '*');
            $channel['channel_adapter'] = $oldChannel['channel_adapter'];
        }
        
        if(!isset($channel['channel_adapter']) || !$channel['channel_adapter']){
            return $channel;
        }
        
        // 如果没有节点id,则自动补充
        if ( !$channel['node_id'] ){
            switch ($channel['channel_adapter']) {
                case 'openapi':
                    $channel['node_id'] = sprintf('o%u', crc32(utils::array_md5($config) . kernel::base_url()));
                    break;
                default:
                    break;
            }
        }
        
        $channel['config'] = serialize($config);
        
        return $channel;
   }
}
