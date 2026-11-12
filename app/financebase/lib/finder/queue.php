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

class financebase_finder_queue
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'is_file_ready';

    public $column_edit       = "操作";
    public $column_edit_width = "150";
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $ret       = '';

        switch ($row['status']) {
            case 'error':
                $ret .= "&nbsp;&nbsp;<a href='index.php?app=financebase&ctl=admin_shop_settlement_queue&act=showErrorMsg&p[0]={$row['queue_id']}&finder_id={$finder_id}' target='_blank'>查看原因</a>";
                break;
            case 'ready':
                $ret .= <<<EOF
            <a onclick="javascript:new Request({
                url:'index.php?app=financebase&ctl=admin_shop_settlement_queue&act=doTask&p[0]={$row['queue_id']}',
                data:'',
                method:'post',
                onSuccess:function(response){
                    alert(response);
                    finder = finderGroup['{$finder_id}'];
                    finder.refresh.delay(100, finder);
                }
            }).send();" href="javascript:;" >执行任务</a>
EOF;
            default:
                break;
        }

        $buttons = [];

        if ($row[$this->col_prefix . 'is_file_ready'] == '0') {
            $buttons[] = <<<BTN
                <a href='index.php?app=financebase&ctl=admin_shop_settlement_queue&act=downloadUrl&p[0]={$row["queue_id"]}'>下载文件</a>
BTN;
        }

        $ret = implode('&nbsp;&nbsp;', $buttons) . $ret;
    
        return $ret;
    }

    public $detail_queue = '任务详情';
    /**
     * detail_queue
     * @param mixed $queue_id ID
     * @return mixed 返回值
     */
    public function detail_queue($queue_id)
    {
        $render = app::get('financebase')->render();

        $queueMdl = app::get('financebase')->model('queue');

        $queue = $queueMdl->db_dump($queue_id);

        $render->pagedata['queue'] = $queue;

        return $render->fetch('admin/finder/queue/detail.html');
    }
}
