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

class ome_finder_api_log
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public static $row = array();

    public $addon_cols = "log_id";
    public $column_edit = "操作";
    public $column_edit_width = "100";

    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row)
    {
        $log_id = $row['log_id'];
        $finder_id = $_GET['_finder']['finder_id'];
        
        $button = '<a href="index.php?app=ome&ctl=admin_api_log&act=show&finder_id=' . $finder_id . '&p[0]=' . $log_id . '" target="dialog::{width:1000,height:500,title:\'API日志详情\'}">详情</a>';
        
        return $button;
    }

    /* 详情
     *
     */

    public function detail_log($log_id)
    {
        
        $render = app::get('ome')->render();

        $apilog = app::get('ome')->model("api_log")->dump($log_id);
        
        // 使用封装的API日志数据处理方法
        $apilog = kernel::single('ome_api_func')->processApiLogData($apilog);

        // 生成唯一的DOM ID
        $dom_id = 'apilog_' . $log_id . '_' . time() . '_' . mt_rand(1000, 9999);
        $render->pagedata['apilog'] = $apilog;
        $render->pagedata['dom_id'] = $dom_id;

        return $render->fetch("admin/api/detail.html");
    }
}
