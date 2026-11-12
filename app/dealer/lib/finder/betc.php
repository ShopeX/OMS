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

class dealer_finder_betc
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = "betc_id,status";

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
        $finder_id = $_GET['_finder']['finder_id'];
        // $betcId     = $row[$this->col_prefix . 'betc_id'];
        $betcId = $row['betc_id'];
        $button = '<a href="index.php?app=dealer&ctl=admin_betc&act=edit&p[0]=' . $betcId . '&finder_id=' . $finder_id . '" target="dialog::{width:500,height:360,title:\'编辑贸易公司\'}">编辑</a>';
        return $button;
    }

    public $column_status       = "公司状态";
    public $column_status_width = 80;
    public $column_status_order = 70;
    /**
     * column_status
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_status($row)
    {
        $status = $row[$this->col_prefix . 'status'];
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
     * @param int $betc_id
     * @return string
     */
    public $detail_show_log = '操作记录';
    /**
     * detail_show_log
     * @param mixed $betc_id ID
     * @return mixed 返回值
     */
    public function detail_show_log($betc_id)
    {
        $omeLogMdl = app::get('ome')->model('operation_log');
        $logList   = $omeLogMdl->read_log(array('obj_id' => $betc_id, 'obj_type' => 'betc@dealer'), 0, -1);
        $finder_id = $_GET['_finder']['finder_id'];
        foreach ($logList as $k => $v) {
            $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);

            if ($v['operation'] == '贸易公司编辑') {
                $logList[$k]['memo'] = "<a href='index.php?app=dealer&ctl=admin_betc&act=show_history&p[0]={$v['log_id']}&finder_id={$finder_id}' onclick=\"window.open(this.href, '_blank', 'width=500,height=290'); return false;\">查看快照</a>";
            }
        }
        $render                   = app::get('dealer')->render();
        $render->pagedata['logs'] = $logList;
        return $render->fetch('admin/bbb_show_log.html');
    }

}
