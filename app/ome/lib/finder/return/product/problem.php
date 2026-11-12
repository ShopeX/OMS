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

class ome_finder_return_product_problem{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "售后问题详情";
    
    function detail_basic($problem_id){
        $render = app::get('ome')->render();
        $oProblem = app::get('ome')->model("return_product_problem");
        $problem = $oProblem->dump($problem_id);
        $render->pagedata['problem'] = $problem;
        return $render->fetch("admin/system/product_problem_detail.html");

    }
    
    var $addon_cols = "problem_id";
    var $column_edit = "操作";
    var $column_edit_width = "100";
    var $column_edit_order = 1;
    function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $url = "index.php?app=ome&ctl=admin_setting&act=%s&p[0]=".$row[$this->col_prefix.'problem_id'].'&finder_id='.$finder_id;
        
        $button= '';
        if($row['defaulted'] == 'true'){
            $button_url = sprintf($url, 'problemWipeDefaulted');
            $button .= "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定设置要取消默认么？\")) {href=\"{$button_url}\";}'>取消默认</a>";
        }else{
            $button_url = sprintf($url, 'problemSetDefaulted');
            $button .= "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定设置为默认么？\")) {href=\"{$button_url}\";}'>默认</a>";
        }
        
        $button_url = sprintf($url, 'editproblem');
        $button .= '&nbsp;|&nbsp;<a href="'. $button_url .'" target="dialog::{width:500,height:300,title:\'编辑售后原因\'}">编辑</a>';
        
        return $button;
    }
}
?>
