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


class pam_finder_log{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $column_event_time = '时间';
    public $column_event_time_width = '160';
    public $column_event_time_order = '10';
    /**
     * column_event_time
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_event_time($row, $list) {

        return date('Y-m-d H:i:s', $row['event_time']);
    }

    public $column_action = '动作';
    public $column_action_width = '220';
    public $column_action_order = '20';
    /**
     * column_action
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_action($row, $list) {
        return $row['event_data'];
    }

}
