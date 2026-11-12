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

class tbo2o_finder_shop_skus
{

    private $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols    = 'id,product_id,is_bind,bind_time';
    
    //操作
    var $column_edit  = '操作';
    var $column_edit_width = 80;
    var $column_edit_order = 10;
    function column_edit($row)
    {
        $finder_id       = $_GET['_finder']['finder_id'];
        $id              = $row[$this->col_prefix.'id'];
        $product_id      = $row[$this->col_prefix.'product_id'];
        $is_bind         = intval($row[$this->col_prefix.'is_bind']);
        
        if(empty($product_id))
        {
            $button    = '<a href="index.php?app=tbo2o&ctl=admin_shop_skus&act=bind_product&p[0]='.$id.'&finder_id='.$finder_id.'" target="_blank">关联</a>';
        }
        elseif($is_bind == 1) 
        {
            $href    = 'index.php?app=tbo2o&ctl=admin_shop_skus&act=unbind_scitem_map&p[0]='. $id .'&finder_id='. $finder_id;
            $button  .= "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定绑定宝贝吗？\")){href=\"{$href}\";}'>解绑</a>";
        }
        else 
        {
            $href    = 'index.php?app=tbo2o&ctl=admin_shop_skus&act=bind_scitem_map&p[0]='. $id .'&finder_id='. $finder_id;
            
            $button    = '<a href="index.php?app=tbo2o&ctl=admin_shop_skus&act=bind_product&p[0]='.$id.'&finder_id='.$finder_id.'" target="_blank">关联</a>';
            $button    .= "&nbsp;|&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定绑定宝贝吗？\")){href=\"{$href}\";}'>绑定</a>";
        }
        
        return $button;
    }

    var $column_is_bind = '绑定状态';
    var $column_is_bind_width = 80;
    var $column_is_bind_order = 90;
    function column_is_bind($row)
    {
        $is_bind    = intval($row[$this->col_prefix.'is_bind']);
        if($is_bind == 1)
        {
            return '已绑定';
        }
        else
        {
            return '未绑定';
        }
    }

    var $column_bind_time = '绑定时间';
    var $column_bind_time_width = 120;
    var $column_bind_time_order = 95;
    function column_bind_time($row)
    {
        $bind_time    = $row[$this->col_prefix.'bind_time'];
        if($bind_time)
        {
            return date('Y-m-d H:i', $bind_time);
        }
        else 
        {
            return '';
        }
    }
}