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
class crm_finder_gift{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

//    var $addon_cols = "status";
//    var $column_control = '操作';
//    var $column_control_width = "80";
//    function column_control($row){
//        $find_id = $_GET['_finder']['finder_id'];
//        if($row[$this->col_prefix . 'status'] == 2){
//            $btn = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要启用当前的赠品吗？\")) {href=\"index.php?app=crm&ctl=admin_gift&act=setStatus&p[0]={$row[gift_id]}&p[1]=true&finder_id={$_GET[_finder][finder_id]}\";}'>启用</a>";
//        }else{
//            $btn = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要暂停当前的赠品吗？\")) {href=\"index.php?app=crm&ctl=admin_gift&act=setStatus&p[0]={$row[gift_id]}&p[1]=false&finder_id={$_GET[_finder][finder_id]}\";}'>暂停</a>";
//        }
//
//        return $btn;
//    }

//    var $column_status = '当前状态';
//    var $column_status_width = "100";
//    var $column_status_order = "200";
//    function column_status($row){
//        if($row[$this->col_prefix . 'status'] == 2){
//            return '关闭';
//        }else{
//            return '启用';
//        }
//    }

    var $column_edit = '操作';
    var $column_edit_width = 120;
    function column_edit($row)
    {
        $gift_id = $row[$this->col_prefix.'gift_id'];

        // Vue 模式：下发结构化行操作（FE 用 #column-_actions 渲染，见 FinderTable.vue）
        if ($this->vue_mode) {
            return array(
                array(
                    'key'    => 'gift-set',
                    'label'  => '设置赠品',
                    'action' => 'edit',
                    'id'     => (int)$gift_id,
                ),
            );
        }

        $finder_id = isset($_GET['_finder']['finder_id']) ? $_GET['_finder']['finder_id'] : '';
        $button1 = '<a href="index.php?app=crm&ctl=admin_gift&act=edit&p[0]='.$gift_id.'&finder_id='.$finder_id.'" target="dialog::{width:600,height:350,title:\'赠品设置\'}">设置赠品</a>';

        return $button1;
    }

}
?>
