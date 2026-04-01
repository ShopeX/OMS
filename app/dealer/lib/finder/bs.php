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

class dealer_finder_bs
{
    function __construct(){
        if(in_array($_REQUEST['action'], ['exportcnf', 'to_export', 'export'])){
            unset($this->column_edit);
        }
    }

    public $addon_cols = "bs_id,status,betc_id";

    static $betcList = [];

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
        // $bsId     = $row[$this->col_prefix . 'bs_id'];
        $bsId   = $row['bs_id'];
        $button = '<a href="index.php?app=dealer&ctl=admin_bs&act=edit&p[0]=' . $bsId . '&finder_id=' . $finder_id . '" target="dialog::{width:780,height:635,title:\'编辑经销商\'}">编辑</a>';
        return $button;
    }

    public $column_status       = "状态";
    public $column_status_width = 60;
    public $column_status_order = 40;
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

    public $column_betc_name       = "所属贸易公司";
    public $column_betc_name_width = 160;
    public $column_betc_name_order = 30;
    /**
     * column_betc_name
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_betc_name($row)
    {
        if (!self::$betcList) {
            $betcMdl        = app::get('dealer')->model('betc');
            self::$betcList = array_column($betcMdl->getList('*'), null, 'betc_id');
        }
        $betcName  = [];
        $betcIdArr = explode(',', $row[$this->col_prefix . 'betc_id']);
        foreach ($betcIdArr as $k => $betcId) {
            if (isset(self::$betcList[$betcId])) {
                $betcName[$betcId] = self::$betcList[$betcId]['betc_name'];
            } else {
                $betcName[$betcId] = $betcId ? ('ID:' . $betcId) : '';
            }
        }
        // return implode(' | ', $betcName);
        $count   = count($betcName);
        $endorse = implode('、', $betcName);
        $detail  = implode('<br>', $betcName);

        return '<span style="color:#0000ff"><div class="desc-tip" onmouseover="bindFinderColTip(event);">' . $endorse . '<textarea style="display:none;"><h4>所属贸易公司('.$count.')</h4>' . $detail . '</textarea></div></span>';
    }

    /**
     * 订单操作记录
     * @param int $bs_id
     * @return string
     */
    public $detail_show_log = '操作记录';
    /**
     * detail_show_log
     * @param mixed $bs_id ID
     * @return mixed 返回值
     */
    public function detail_show_log($bs_id)
    {
        $omeLogMdl = app::get('ome')->model('operation_log');
        $logList   = $omeLogMdl->read_log(array('obj_id' => $bs_id, 'obj_type' => 'bs@dealer'), 0, -1);
        $finder_id = $_GET['_finder']['finder_id'];
        foreach ($logList as $k => $v) {
            $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);

            if ($v['operation'] == '经销商编辑') {
                $logList[$k]['memo'] = "<a href='index.php?app=dealer&ctl=admin_bs&act=show_history&p[0]={$v['log_id']}&finder_id={$finder_id}' onclick=\"window.open(this.href, '_blank', 'width=801,height=570'); return false;\">查看快照</a>";
            }
        }
        $render                   = app::get('dealer')->render();
        $render->pagedata['logs'] = $logList;
        return $render->fetch('admin/bbb_show_log.html');
    }

}
