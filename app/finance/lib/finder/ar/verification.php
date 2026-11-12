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

class finance_finder_ar_verification{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_edit = '操作';
    var $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row){
        $order_bn = $row['order_bn'];
        $time_from = strtotime($_POST['time_from']." 00:00:00");
        $time_to = strtotime($_POST['time_to']." 23:59:59");
        $href = sprintf('<a href="index.php?app=finance&ctl=ar_verification&act=verificate&finder_id=%s&order_bn=%s&time_from=%s&time_to=%s" target="dialog::{width:960,height:460}">应收对冲</a>',$_GET['_finder']['finder_id'],$order_bn,$time_from,$time_to);
        return $href;
    }
}