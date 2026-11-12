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
 +----------------------------------------------------------
 * 发票内容管理
 +----------------------------------------------------------
 * Time: 2016-05-31 $
 +----------------------------------------------------------
 */
class invoice_finder_content{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_edit  = '操作';
    var $column_edit_order = 5;
    var $column_edit_width = '50';
    function column_edit($row){
         if(intval($row['content_id']) != 1){
             return '<a href="index.php?app=invoice&ctl=admin_content&act=edit&content_id='.$row['content_id'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="dialog::{width:400,height:100,title:\'编辑发票内容\'}">编辑</a>';
         }
    }
}