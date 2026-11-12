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
class logisticsaccounts_finder_statements{

    private $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = "sid,status";
    var $column_edit = "操作";
    var $column_edit_width = "100";
    //var $detail_basic = "详情";
    var $detail_log='操作日志';
    function column_edit($row){
        $find_id = $_GET['_finder']['finder_id'];
        $sid = $row['sid'];
    if($row['status']=='0'){
    $button= <<<EOF
    <a href="index.php?app=logisticsaccounts&ctl=admin_statements&act=edit&sid=$sid&finder_id=$find_id" class="lnk" " target="_blank">编辑</a>&nbsp;
    &nbsp;
EOF;

    }else{
        $button= <<<EOF
    <a href="index.php?app=logisticsaccounts&ctl=admin_statements&act=edit&sid=$sid&view=1&finder_id=$find_id" class="lnk" " target="_blank">明细</a>&nbsp;
    &nbsp;
EOF;
    }
    return $button;
    }
//
//    function detail_basic($sid){
//        $render = app::get('logisticsaccounts')->render();
//        $statementsObj = app::get('logisticsaccounts')->model('statements');
//        $statements = $statementsObj->get_statements($sid);
//
//
//        $render->pagedata['statements'] = $statements;
//        unset($statements);
//        return $render->fetch('detail_statements.html');
//    }

    function detail_log($sid){
        $render = app::get('logisticsaccounts')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$sid,'obj_type'=>'statements@logisticsaccounts'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['log'] = $logdata;
        return $render->fetch('operation_log.html');
    }


}
?>
