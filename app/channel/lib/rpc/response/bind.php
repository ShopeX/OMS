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

class channel_rpc_response_bind
{
    /**
     * callback
     * @param mixed $result result
     * @return mixed 返回值
     */
    public function callback($result)
    {
        // 验证签名
        $data = $_POST;
        if (empty($data['certi_ac'])) {
            die('0'); // certi_ac 不存在
        }
        
        $certi_ac = $data['certi_ac'];
        unset($data['certi_ac']);
        $sign = base_certificate::getCertiAC($data);
        
        if ($certi_ac != $sign) {
            die('0'); // 签名错误
        }
        
        $channel_id = $result['channel_id'];

        $nodes     = $_POST;
        $status    = $nodes['status'];
        $node_id   = $nodes['node_id'];
        $node_type = $nodes['node_type'];
        $api_v     = $nodes['api_v'];
        $app_key   = isset($nodes['app_key']) ? $nodes['app_key'] : '';
        $app_secret = isset($nodes['app_secret']) ? $nodes['app_secret'] : '';

        $filter = array('channel_id' => $channel_id);

        $objStorage = app::get('channel')->model('channel');
        $channel    = $objStorage->db_dump(array('node_id' => $node_id), 'node_id');

        if ($status == 'bind' and !$channel['node_id']) {
            if ($node_id) {
                $upData = array(
                    'node_id'     => $node_id,
                    'node_type'   => $node_type,
                    'api_version' => $api_v,
                    'addon'       => $nodes,
                );
                
                // 存储 app_key 和 app_secret
                if ($app_key) {
                    $upData['app_key'] = $app_key;
                }
                if ($app_secret) {
                    $upData['secret_key'] = $app_secret;
                }
                
                $objStorage->update($upData, $filter);
                die('1');
            }
        } elseif ($status == 'unbind') {
            $objStorage->update(array('node_id' => ''), $filter);
            die('1');
        }
        die('0');
    }

    /**
     * 获取_params
     * @param mixed $channel_id ID
     * @param mixed $show_type show_type
     * @param mixed $bind_type bind_type
     * @param mixed $source source
     * @return mixed 返回结果
     */
    public function get_params($channel_id, $show_type = '', $bind_type = '', $source = '')
    {
        $apply = array(
            'certi_id' => base_certificate::get('certificate_id'),
            'node_id'  => base_shopnode::node_id('ome'),
            'sess_id'  => kernel::single('base_session')->sess_id(),
        );
        $callbackParams = array('channel_id' => (int) $channel_id);

        $apply['certi_ac'] = base_certificate::getCertiAC($apply);
        $apply['callback']       = kernel::openapi_url('openapi.channel.bind', 'callback', $callbackParams);
        $apply['api_url']        = urlencode(kernel::base_url(true) . kernel::url_prefix() . '/api?sess_id=' . $apply['sess_id']);
        $apply['show_type']      = $show_type;
        $apply['bind_type']      = $bind_type;
        $apply['version_source'] = 'onex-oms';
        $apply['source']         = $source;

        if ($channel_id) {
            $channel = app::get('channel')->model('channel')->dump($channel_id,'node_type,channel_type,channel_bn,shipper');
            $apply['show_type'] = $channel['channel_type'];
            
            // 判断是否是奇门聚石塔内外互通
            $channelObj = kernel::single('channel_channel');
            if ($channelObj->isQimenJushitaErp($channel['channel_type'], $channel['channel_bn'])) {
                $apply['source'] = 'qimenBind';
            }
            
            if ($channel['node_type'] == 'kdn') {
                $apply['kdn_type'] = 'self';
                
                //接入方式
                if($channel['shipper']){
                    // $channelModes = $this->getBindKdMode();
                    // $channel_mode = $channelModes[$channel['shipper']];
                    $apply['kdn_type'] = $channel['shipper'];
                }
            } elseif ($channel['node_type'] == 'sf') {
                // 顺丰仅支持自有key：show_type=kuaidi&sf_type=self
                $apply['show_type'] = 'kuaidi';
                $apply['sf_type']   = 'self';
            }
        }

        return $apply;
    }
    
    /**
     * 获取绑定快递的接入方式
     * @param string|null $type 指定 key 时返回文案；为空返回可选列表
     * @param string|null $node_type 节点类型：sf 仅自有key；其它（如快递鸟）保留商派Key+自有key
     */
    public function getBindKdMode($type=null, $node_type=null)
    {
        if ($node_type === 'sf') {
            $channelModes = array(
                'self' => '自有key对接',
            );
        } else {
            $channelModes = array(
                'default' => '商派Key对接',
                'self'    => '自有key对接',
            );
        }

        if ($type) {
            // 文案回退：历史数据可能仍是 default
            $all = array(
                'default' => '商派Key对接',
                'self'    => '自有key对接',
            );
            return isset($channelModes[$type]) ? $channelModes[$type] : ($all[$type] ?? '');
        }

        return $channelModes;
    }
}
