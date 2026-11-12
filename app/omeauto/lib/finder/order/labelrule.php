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

/**
 * 订单标记规则
 *
 * @author wangbiao@shopex.cn
 * @version $Id: Z
 */
class omeauto_finder_order_labelrule
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = 'memo,disabled';
    
    //操作
    var $column_confirm = '操作';
    var $column_confirm_width = '90';
    var $column_confirm_order = 1;
    function column_confirm($row)
    {
        $btn = '';
        $url = "index.php?app=omeauto&ctl=order_labelrule&act=setStatus&p[0]={$row['id']}&p[1]={$row[$this->col_prefix.'disabled']}&finder_id={$_GET['_finder']['finder_id']}";
        
        if($row[$this->col_prefix.'disabled'] == 'true'){
            $str = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"%s\")){href=\"%s\"}'>启用</a>";
            $btn .= sprintf($str, "你确定要启用指定规则的使用吗？？？\\n\\n注意：你还可以随时暂停指定规则。", $url);
        } else {
            $str = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"%s\")){href=\"%s\"}'>暂停</a>";
            $btn .= sprintf($str, "你确定要暂时停止指定规则的使用吗？？？\\n\\n注意：你还可以随时启用指定规则。", $url);
        }
        
        //修改
        $url = "index.php?app=omeauto&ctl=order_labelrule&act=edit&p[0]={$row['id']}&finder_id={$_GET['_finder']['finder_id']}";
        $str = "&nbsp;|&nbsp;<a href='javascript:void(0);' target='download' onclick=\"new Dialog('%s', {width:730,height:550,title:'修改规则'}); \">修改</a>";
        $btn .= sprintf($str, $url);
        
        return $btn;
    }
    
    //标记规则
    var $column_autoconfirm = '标记规则';
    var $column_autoconfirm_width = '130';
    var $column_autoconfirm_order = 70;
    function column_autoconfirm($row)
    {
        return "";
    }
    
    //简单说明
    var $column_memo = '简单说明';
    var $column_memo_width = '230';
    var $column_memo_order = 80;
    function column_memo($row)
    {
        return $row[$this->col_prefix.'memo'];
    }
    
    //是否启用
    var $column_disabled = '是否启用';
    var $column_disabled_width = '90';
    var $column_disabled_order = 40;
    function column_disabled($row)
    {
        if ($row[$this->col_prefix.'disabled'] == 'true') {
            return "<span style='color:red;'>停用</span>";
        } else {
           return "<span style='color:green;'>启用</span>"; 
        }
    }
}