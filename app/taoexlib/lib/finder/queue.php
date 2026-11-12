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

class taoexlib_finder_queue{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
	var $addon_cols = "status,is_resume";
    var $column_edit='操作';
    var $column_edit_width = "80";

    function column_edit($row){
        $queue_id = $row['queue_id'];

        if ($this->vue_mode) {
            $actions = [];
            if ($row[$this->col_prefix.'status'] == 'failed' && $row[$this->col_prefix.'is_resume'] == 'true') {
                $actions[] = [
                    'label'  => '重试',
                    'action' => 'retry',
                    'type'   => 'primary',
                    'data'   => ['queue_id' => $queue_id],
                ];
            }
            return $actions;
        }

        // Legacy HTML mode
        $find_id  = $_GET['_finder']['finder_id'];
        $button = <<<EOF
        <a href="index.php?app=taoexlib&ctl=queue&act=retry&p[0]=$queue_id&find_id=$find_id">重试</a>
EOF;
        if ($row[$this->col_prefix.'status'] == 'failed' && $row[$this->col_prefix.'is_resume'] == 'true'){
            return $button;
        }
    }
	
  /* 详情
     *
     */
    function detail_log($queue_id){
        $render = app::get('taoexlib')->render();
        $queueObj = app::get('taoexlib')->model("queue");
        $queue = $queueObj->dump($queue_id);
		$queue['params'] = var_export($queue['params'],true);
		$errmsg = unserialize($queue['errmsg']);
		if(is_array($errmsg)){
			$queue['errmsg'] = implode('<br />', $errmsg);
		}else{
			$queue['errmsg'] = $errmsg;
		}
		
        $render->pagedata['queue'] = $queue;
        return $render->fetch("queue/detail.html");
    }
}
