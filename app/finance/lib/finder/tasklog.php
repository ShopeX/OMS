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

class finance_finder_tasklog{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    /* 详情
     */
    function detail_log($log_id){
        
        $render = app::get('finance')->render();
        $oApilog = &app::get('finance')->model("tasklog");
        $apilog = $oApilog->dump($log_id);

        $apilog['params'] = unserialize($apilog['params']);
        foreach ($apilog['params'] as $key=>$val){
            if (is_array($val)) $params .= $key.'='.serialize($val)."<br/>";
            else $params .= $key."=".$val."<br/>";
        }
        $apilog['send_api_params'] = $params;

        $apilog_msg = @json_decode($apilog['msg'],true);
        $msg = '';
        if (is_array($apilog_msg)){
            ob_start();
            echo "<pre>";
            var_export($apilog_msg);
            $msg = ob_get_contents();
            ob_clean();
        }else{
            $msg = htmlspecialchars($apilog['msg']);
        }
        $apilog['msg'] = $msg;
        $render->pagedata['apilog'] = $apilog;
        return $render->fetch("tasklog/detail.html");
    }

    var $column_retry='操作';
    var $column_retry_width = 130;
    var $column_retry_order = 1;
    var $addon_cols = "status";
    function column_retry($row){
        $log_id = $row['log_id'];
        $finder_id = $_GET['_finder']['finder_id'];

        $but1 = "<a href=\"index.php?app=finance&ctl=tasklog&act=retry&p[0]={$log_id}&finder_id={$finder_id}\" target=\"dialog::{title:'日志重试', width:550, height:300}\">重试</a>";

        $but2 = sprintf('<a href="javascript:if (confirm(\'当前任务正在运行中，是否确定强制失败？\')){W.page(\'index.php?app=finance&ctl=tasklog&act=abort_fail&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">强制失败</a>',$log_id,$finder_id);
        
        $return_but = array();
        if ($row[$this->col_prefix.'status'] == 'fail'){
            $return_but[] = $but1;
        }
        if ($row[$this->col_prefix.'status'] == 'running'){
            $return_but[] = $but2;
        }

        return @implode(' | ',$return_but);
    }

}
?>