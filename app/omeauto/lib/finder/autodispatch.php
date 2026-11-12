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

class omeauto_finder_autodispatch {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    static $orderTypes = null;

    function __construct() {

        if (self::$orderTypes === null) {

            $types = app::get('omeauto')->model('order_type')->getList('tid,name,disabled');
            foreach ((array) $types as $t) {
                self::$orderTypes[$t['tid']] = $t;
            }
        }
    }

    var $addon_cols = "oid,config,memo,disabled,defaulted";
    var $column_confirm = '操作';
    var $column_confirm_width = "100";

    function column_confirm($row) {

        $btn = '';
        if ($row['_0_defaulted'] == 'false') {
            if ($row['_0_disabled'] == 'true') {
                $btn .= "&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要启用指定规则的使用吗？？？\\n\\n注意：你还可以随时暂停指定规则。\")) {href=\"index.php?app=omeauto&ctl=autodispatch&act=setStatus&p[0]={$row['oid']}&p[1]=true&finder_id={$_GET['_finder']['finder_id']}\";}'>启用</a>";
            } else {
                $btn .= "&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要暂时停止指定规则的使用吗？？？\\n\\n注意：你还可以随时启用指定规则。\")) {href=\"index.php?app=omeauto&ctl=autodispatch&act=setStatus&p[0]={$row['oid']}&p[1]=false&finder_id={$_GET['_finder']['finder_id']}\";}'>暂停</a>";
            }
            $btn .= "&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要把当前规则设为默认分配规则？？？\")) {href=\"index.php?app=omeauto&ctl=autodispatch&act=setDefaulted&p[0]={$row['oid']}&finder_id={$_GET['_finder']['finder_id']}\";}'>默认</a>";
        } else {
            $btn .= "&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要取消当前规则作为默认分配规则的存在吗？？？\")) {href=\"index.php?app=omeauto&ctl=autodispatch&act=removeDefaulted&p[0]={$row['oid']}&finder_id={$_GET['_finder']['finder_id']}\";}'>取消默认</a>";
        }

        $btn .= "&nbsp;<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=omeauto&ctl=autodispatch&act=edit&p[0]={$row['oid']}&finder_id={$_GET['_finder']['finder_id']}',{width:760,height:400,title:'修改自动分派规则'}); \">修改</a>";
        return $btn;
    }

    var $column_order = '订单分组';
    var $column_order_width = "150";

    function column_order($row) {

        $html = '';
        $title = '';
        
         if ($row['_0_defaulted'] == 'true') {

            $title = '所有未分组订单';
            $html = '<a href="javascript:void(0);">所有未分组订单</a>';
        } elseif (!empty($row['_0_config'])) { 

            foreach ($row['_0_config']['autoOrders'] as $tid) {
                if (self::$orderTypes[$tid]['disabled'] == 'false') {
                    $title .= self::$orderTypes[$tid]['name'] . "<br/>";
                    $html .= sprintf("<a href=\"javascript:voide(0);\" onclick=\"new Dialog('index.php?app=omeauto&ctl=order_type&act=edit&p[0]=%s&finder_id=%s',{width:760,height:480,title:'修改分组规则'}); \">%s</a>&nbsp;&nbsp;", $tid, $_GET['_finder']['finder_id'], self::$orderTypes[$tid]['name']);
                } else {
                    $html .= "<span style='color:#DDDDDD;' title='该规则已经暂停使用'>" . self::$orderTypes[$tid]['name'] . "</span>";
                }
            }
        }
        if ($title <> '') {
            return "<div onmouseover='bindFinderColTip(event)' rel='{$title}'>" . $html . "<div>";
        } else {
            return $html;
        }
    }

    var $column_disabled = '是否启用';
    var $column_disabled_width = "80";

    function column_disabled($row) {
        if ($row['_0_disabled'] == 'true') {
            return "<span style='color:red;'>停用</span>";
        } else {
            return "<span style='color:green;'>启用</span>";
        }
    }

}