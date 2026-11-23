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
 * Created by PhpStorm.
 * User: yaokangming
 * Date: 2019/3/19
 * Time: 18:04
 */

class crm_gift_orderhost
{

    public function process($ruleBase, $sdf) {

        $is_host = false;
        if ($ruleBase['filter_arr']['is_host']['type'] == 'infinite') {
            // 无限制
            $is_host = true;
            $msg     = '';

        } elseif ($ruleBase['filter_arr']['is_host']['type'] == 'all') {
            // 所有达人
            foreach ($sdf['objects'] as $k => $objects) {
                if ($objects['author_id'] || $objects['author_name'] || (is_array($objects['addon']) && $objects['addon']['room_id'])) {
                    $is_host = true;
                    break;
                }
            }
            $msg = '达人订单才赠送';

        } elseif ($ruleBase['filter_arr']['is_host']['type'] == 'assign') {
            // 指定达人
            $author_arr = array_filter(explode(',', $ruleBase['filter_arr']['is_host']['author']));
            $room_arr   = array_filter(explode(',', $ruleBase['filter_arr']['is_host']['room']));
            foreach ($sdf['objects'] as $k => $objects) {
                if ($objects['author_id'] && in_array($objects['author_id'], $author_arr)) {
                    if ($room_arr) {
                        // 指定了直播间ID
                        if (is_array($objects['addon']) && $objects['addon']['room_id'] && in_array($objects['addon']['room_id'], $room_arr)) {
                            $is_host = true;
                        }
                    } else {
                        $is_host = true;
                    }
                }
            }
            $msg = '指定达人订单才赠送';
        }

        if (!$is_host) {
            return array(false, $msg);
        }

        return array(true);
    }
}