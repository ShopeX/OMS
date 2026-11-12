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

class finance_finder_bill_confirm{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $column_operator = '操作';
    var $column_operator_width = 65;
    var $column_operator_order = 1;
    function column_operator($row){
        $confirm_id = $row['confirm_id'];
        $finder_id = $_GET['_finder']['finder_id'];        
        
        $but1 = "<a href=\"index.php?app=finance&ctl=bill_confirm&act=confirm&p[0]={$confirm_id}&finder_id={$finder_id}\" target=\"dialog::{title:'记账', width:450, height:300}\">记账</a>";

        $but2 = sprintf('<a href="javascript:if (confirm(\'确定要作废当前账单吗,作废后将不可恢复？\')){W.page(\'index.php?app=finance&ctl=bill_confirm&act=do_cancel&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">作废</a>',$confirm_id,$finder_id);
        
        return $but1.' '.$but2;
    }

}
