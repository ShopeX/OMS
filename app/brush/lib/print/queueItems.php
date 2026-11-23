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
 * @author ykm 2015-12-25
 * @describe print_queue_items打印数据整理
 */

class brush_print_queueItems{

    public function queueItems(&$oriData, $corp, $field) {
        $pre = __FUNCTION__ . '.';
        foreach($oriData as $key => $value) {
            foreach($field as $f) {
                $oriData[$key][$pre . $f] = '';
            }
        }
    }
}