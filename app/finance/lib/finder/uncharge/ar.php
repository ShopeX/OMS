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

class finance_finder_uncharge_ar{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }



	var $column_edit = "操作";
    var $column_edit_width = "100";
    var $column_edit_order=5;
    function column_edit($row){

        $confhref .= sprintf('<a href="index.php?app=finance&ctl=monthend_uncharge&act=reset&p[0]=%s&p[1]=%s&finder_id=%s" target="_blank">更改账期</a>&nbsp;&nbsp;&nbsp;&nbsp;',$row['ar_id'],$_GET['p'][0],$_GET['_finder']['finder_id']);

        return $confhref;
    }
}