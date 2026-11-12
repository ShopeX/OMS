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

class tbo2o_finder_store{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols = "store_id,outer_store_id,sync";
    var $column_edit = "操作";
    var $column_edit_width = 120;
    var $column_edit_order = 1;
    function column_edit($row){
        $finder_id = $_GET['_finder']['finder_id'];
        $store_id = $row[$this->col_prefix.'store_id'];
        $outer_store_id = $row[$this->col_prefix.'outer_store_id'];
        $sync = $row[$this->col_prefix.'sync'];

        $button = '<a href="index.php?app=tbo2o&ctl=admin_store&act=edit&p[0]='.$store_id.'&finder_id='.$finder_id.'" target="_blank">编辑</a>';
        if(!$outer_store_id && ($sync ==1 || $sync ==2)){
            $button .= "&nbsp;|&nbsp;".sprintf('<a href="javascript:if (confirm(\'你确定同步该门店信息吗？\')){W.page(\'index.php?app=tbo2o&ctl=admin_store&act=addStore&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">同步</a>', $store_id,$finder_id);
        }elseif($outer_store_id && ($sync ==1 || $sync ==2)){
            $button .= "&nbsp;|&nbsp;".sprintf('<a href="javascript:if (confirm(\'你确定更新该门店信息吗？\')){W.page(\'index.php?app=tbo2o&ctl=admin_store&act=updateStore&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">更新</a>', $store_id,$finder_id);
        }elseif($outer_store_id && $sync == 3){
            $button .= "&nbsp;|&nbsp;".sprintf('<a href="javascript:if (confirm(\'你确定删除该门店信息吗？\')){W.page(\'index.php?app=tbo2o&ctl=admin_store&act=delStore&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">删除</a>', $store_id,$finder_id);
        }

        return $button;
    }

}