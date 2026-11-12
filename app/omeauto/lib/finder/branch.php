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

class omeauto_finder_branch {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    //var $addon_cols = "oid,config,memo,disabled,defaulted";
    var $column_confirm = '操作';
    var $column_confirm_width = "100";

    function column_confirm($row) {
        $btn = '';
        $btn .= "<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=omeauto&ctl=branchbind&act=setBind&p[0]={$row['branch_id']}&finder_id={$_GET['_finder']['finder_id']}',{width:760,height:400,title:'绑定备货仓库'}); \">设置</a>";
        return $btn;
    }
}