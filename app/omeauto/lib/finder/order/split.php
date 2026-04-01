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

class omeauto_finder_order_split {
    
    var $addon_cols = "";
    
    var $column_edit = '操作';
    var $column_edit_width = "80";
    var $column_edit_order = "5";
    function column_edit($row) {
        $btn = "<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=omeauto&ctl=order_split&act=edit&p[0]={$row['sid']}&finder_id={$_GET['_finder']['finder_id']}',{width:760,height:530,title:'修改拆单规则'}); \">修改</a>";
        return $btn;
    }
}