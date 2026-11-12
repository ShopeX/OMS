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

class dealer_finder_bbu
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = "bbu_id,status";

    public $column_edit       = "操作";
    public $column_edit_width = 60;
    public $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row)
    {
        // ⚠️ vue 模式下 col_prefix 未设置（仅 legacy builder 注入）⇒ 优先读 addon_cols 顺序下发的原始值别名
        $bbuId = $row['_0_bbu_id'] ?? $row['bbu_id'] ?? 0;

        if ($this->vue_mode) {
            // Vue Finder 契约：结构化行操作（legacy HTML 片段不下发，SPA 不解析 legacy 链接）
            // 与 legacy 逐字对齐：编辑入口仍走 legacy `act=edit` 弹窗（写链路本轮不迁）
            return array(
                array(
                    'key'    => 'edit',
                    'label'  => '编辑',
                    'action' => 'index.php?app=dealer&ctl=admin_bbu&act=edit&p[0]=' . $bbuId,
                ),
            );
        }

        $finder_id = $_GET['_finder']['finder_id'];
        $button = '<a href="index.php?app=dealer&ctl=admin_bbu&act=edit&p[0]=' . $bbuId . '&finder_id=' . $finder_id . '" target="dialog::{width:500,height:200,title:\'编辑公司业务组织\'}">编辑</a>';
        return $button;
    }

    public $column_status       = "状态";
    public $column_status_width = 60;
    public $column_status_order = 30;
    /**
     * column_status
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_status($row)
    {
        // vue 模式下 col_prefix 未设置、且 status 可能已被 type modifier 转成显示值
        // ⇒ 优先读 addon_cols 顺序下发的原始值别名 `_1_status`
        $status = $this->vue_mode
            ? ($row['_1_status'] ?? $row['status'] ?? '')
            : $row[$this->col_prefix . 'status'];
        switch ($status) {
            case 'active':
                $status = '活跃';
                break;
            case 'close':
                $status = '关闭';
                break;
            default:
                break;
        }
        return $status;
    }

    /**
     * 订单操作记录
     * @param int $bbu_id
     * @return string
     */
    public $detail_show_log = '操作记录';
    /**
     * detail_show_log
     * @param mixed $bbu_id ID
     * @return mixed 返回值
     */
    public function detail_show_log($bbu_id)
    {
        $omeLogMdl = app::get('ome')->model('operation_log');
        $logList   = $omeLogMdl->read_log(array('obj_id' => $bbu_id, 'obj_type' => 'bbu@dealer'), 0, -1);
        $finder_id = $_GET['_finder']['finder_id'];

        // 前后端分离：结构化日志（不再渲染 legacy admin/bbb_show_log.html，也不拼「查看快照」HTML 链接）
        // 字段对齐模板：操作时间 / 操作人 / 行为 / 操作内容（memo 仅「销售团队编辑」有快照链接）
        if ($this->vue_mode) {
            $logs = array();
            foreach ($logList as $log) {
                $logs[] = array(
                    'operate_time'    => date('Y-m-d H:i:s', $log['operate_time']),
                    'op_name'         => $log['op_name'],
                    'operation'       => $log['operation'],
                    'snapshot_log_id' => $log['operation'] == '销售团队编辑' ? $log['log_id'] : '',
                );
            }
            return $logs;
        }

        foreach ($logList as $k => $v) {
            $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);

            if ($v['operation'] == '销售团队编辑') {
                $logList[$k]['memo'] = "<a href='index.php?app=dealer&ctl=admin_bbu&act=show_history&p[0]={$v['log_id']}&finder_id={$finder_id}' onclick=\"window.open(this.href, '_blank', 'width=500,height=140'); return false;\">查看快照</a>";
            }
        }
        $render                   = app::get('dealer')->render();
        $render->pagedata['logs'] = $logList;

        return $render->fetch('admin/bbb_show_log.html');
    }

}
