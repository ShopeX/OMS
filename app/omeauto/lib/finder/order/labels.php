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
 * 订单标签
 *
 * @author wangbiao@shopex.cn
 * @version $Id: Z
 */
class omeauto_finder_order_labels
{
    var $addon_cols = 'label_color';
    
    //操作
    var $column_confirm = '操作';
    var $column_confirm_width = '90';
    var $column_confirm_order = 1;
    function column_confirm($row)
    {
        $url = "index.php?app=omeauto&ctl=order_labels&act=edit&p[0]={$row['label_id']}&finder_id={$_GET['_finder']['finder_id']}";
        
        // 是否显示：修改按钮
        $str = '';
        if($row['source'] != 'system'){
            $str = "<a href='javascript:void(0);' target='download' onclick=\"new Dialog('%s', {width:500,height:300,title:'修改标签'}); \">修改</a>";
        }
        
        return sprintf($str, $url);
    }
    
    //标记规则
    var $column_label_color = '标签颜色';
    var $column_label_color_width = '120';
    var $column_label_color_order = 15;
    function column_label_color($row)
    {
        return '<span style="color:'. $row[$this->col_prefix.'label_color'] .'">'. $row[$this->col_prefix.'label_color'] .'</span>';
    }
}