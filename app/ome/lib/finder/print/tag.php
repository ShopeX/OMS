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

class ome_finder_print_tag{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_confirm = "操作";
    var $column_confirm_width = "60";
    function column_confirm($row){
        $id = $row['tag_id'];
        $finder_id = $_GET['_finder']['finder_id'];
        $button = <<<EOF
        <a href="index.php?app=ome&ctl=admin_print_termini&act=edit&p[0]=$id&finder_id=$finder_id" class="lnk" target="dialog::{width:600,height:430,title:'编辑大头笔'}">编辑</a>
EOF;
        $string = $button;
        return $string;
    }

}
?>