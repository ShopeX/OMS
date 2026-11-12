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
 * @Date: 2023/5/18
 * @Describe: 预警配置邮件组finder类
 */
class monitor_finder_event_group
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_edit = '操作';
    var $column_edit_width = "75";
    var $column_edit_order = "5";
    
    function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'] ?? '';
        $actions = [
            [
                'key'    => 'edit',
                'label'  => '编辑',
                'action' => 'edit',
                'href'   => "index.php?app=monitor&ctl=admin_alarm_group&act=edit&p[0]={$row['group_id']}&finder_id={$finder_id}",
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
    
    var $detail_basic = '详情';
    function detail_basic($group_id)
    {
        $eventGroupMdl              = app::get('monitor')->model('event_group');
        $eventGroupTempMdl          = app::get('monitor')->model('event_group_template');
        $data                       = $eventGroupMdl->db_dump(array('group_id' => $group_id), '*');
        $event                      = $eventGroupTempMdl->db_dump(['group_id' => $group_id]);
        $data['event_type']         = $event['event_type'] ? explode(',', $event['event_type']) : [];
        $data['receiver_id']        = $data['receiver_id'] ? explode(',', $data['receiver_id']) : [];
        $data['org_id']             = $data['org_id'] ? explode(',', $data['org_id']) : [];

        $eventType = kernel::single('monitor_event_template')->getEventType();
        $eventList = [];
        foreach ($data['event_type'] as $val) {
            $eventList[] = $eventType[$val] ?? $val;
        }

        $receiverList = app::get('monitor')->model('event_receiver')->getList('id,receiver', ['id' => $data['receiver_id']]);
        $receiverNames = array_column($receiverList, 'receiver');

        // Vue mode: return structured array for row expand
        if ($this->vue_mode) {
            // 查运营组织名称
            $orgName = '';
            if ($data['org_id']) {
                $orgList = app::get('ome')->model('operation_organization')
                    ->getList('name', ['org_id' => $data['org_id']]);
                $orgName = implode('，', array_column($orgList, 'name'));
            }

            return [
                'group_name'    => $data['group_name'],
                'disabled'      => $data['disabled'],
                'event_type'    => $eventList,
                'receiver_list' => $receiverNames,
                'org_name'      => $orgName,
            ];
        }

        // Legacy: return HTML
        $render = app::get('ome')->render();
        $render->pagedata['detail'] = $data;
        $render->pagedata['event_type_list'] = implode('<br>', $eventList);
        $render->pagedata['receiver_list'] = implode('<br>', $receiverNames);

        return $render->fetch('admin/alarm/event/group_detail_basic.html', 'monitor');
    }
}