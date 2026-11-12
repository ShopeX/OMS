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

class tgkpi_finder_reason{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols = "reason_id";
    var $column_edit = "操作";
    var $column_edit_width = "100";
    function column_edit($row){
        $finder_id = $_GET['_finder']['finder_id'];
        if($row[$this->col_prefix.'reason_id'] == 1){
            return '默认不可操作';
        }else{
            return '<a href="index.php?app=tgkpi&ctl=admin_setting&act=editreason&p[0]='.$row[$this->col_prefix.'reason_id'].'&finder_id='.$finder_id.' " target="dialog::{width:450,height:150,title:\'编辑校验失败原因\'}">编辑</a>';
        }
    }
}
?>