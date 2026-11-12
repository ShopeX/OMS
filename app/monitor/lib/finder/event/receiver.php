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
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/13
 * @Describe: 预警配置finder类
 */
class monitor_finder_event_receiver
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_edit = '操作';
    var $column_edit_width = "75";
    var $column_edit_order = "10";
    function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'] ?? '';
        $actions = [
            [
                'key'    => 'edit',
                'label'  => '编辑',
                'action' => 'edit',
                'href'   => "index.php?app=monitor&ctl=admin_alarm_receiver&act=edit&p[0]={$row['id']}&finder_id={$finder_id}",
            ],
        ];

        if ($this->vue_mode) {
            return $actions;
        }

        // Legacy: convert array to HTML
        $html = '';
        foreach ($actions as $action) {
            $html .= '<a href="' . $action['href'] . '" target="dialog::{width:800,height:630,title:\'编辑收件人\'}">' . $action['label'] . '</a>&nbsp;&nbsp;';
        }
        return $html;
    }
}