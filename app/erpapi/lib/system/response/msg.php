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

class erpapi_system_response_msg extends erpapi_system_response_abstract
{
    /**
     * 消息类通知
     *
     * @return void
     * @author 
     */
    public function notify($params)
    {
        $this->__apilog['title']       = '系统消息类通知';
        $this->__apilog['original_bn'] = $params['to_node_id'];

        if (!$params['data']) {
            $this->__apilog['result']['msg'] = '消息内容不能为空';
            return false;
        }

        $content = is_array($params['data']) ? $params['data'] : json_decode($params['data'], true);
        if (!is_array($content)) {
            $this->__apilog['result']['msg'] = '消息内容格式错误';
            return false;
        }

        $content['node_id'] = $params['to_node_id'];
        $sdf = array(
            'node_id'    => $params['node_id'],
            'date'       => $params['date'],
            'content'    => $content,
        );

        return $sdf;
    }
}
