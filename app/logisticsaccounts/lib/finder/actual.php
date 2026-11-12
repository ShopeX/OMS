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
class logisticsaccounts_finder_actual{

    private $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

     function __construct(){
        if($_GET['ctl'] == 'admin_actual'){
            //nothing
            if (($_GET['flt']=='accounted') && ($_GET['status']!='4')){
                unset($this->column_task_name);
            }
        }else{
           unset($this->column_view);
        }
    }

    var $addon_cols = 'aid,status,confirm,logi_no,task_id';
    var $column_view = '操作';
    var $column_view_width = '60';
    function column_view($row){

         $find_id = $_GET['_finder']['finder_id'];
         $button='';
         if($_GET['flt']=='accounted'){

         if($_GET['status']=='0' || $_GET['status']=='4'){

$button .= <<<EOF
<a href="index.php?app=logisticsaccounts&ctl=admin_actual&act=detail_basic&p[0]={$row['aid']}&finder_id={$find_id}" target="dialog::{width:600,height:500,title:'查看'}">查看</a>
EOF;
         }else{

        

             if($row['confirm']=='0'){
             $button .= <<<EOF
<a href="index.php?app=logisticsaccounts&ctl=admin_actual&act=edit&p[0]={$row['aid']}&finder_id={$find_id}&action=accounted" target="dialog::{width:600,height:500,title:'记账'}">记账</a>
EOF;
             }else if($row['confirm']=='1' && ($row['status']!='4' || $row['status']!='0')){
                $button .= <<<EOF
<a href="index.php?app=logisticsaccounts&ctl=admin_actual&act=edit&oper=doedit&p[0]={$row['aid']}&finder_id={$find_id}&action=accounted" target="dialog::{width:600,height:500,title:'编辑'}">编辑</a>
EOF;

             }
         }
        }

        if($_GET['flt']=='confirm'){

        if($row['status']=='0' || $row['status']=='4'){

$button .= <<<EOF
<a href="index.php?app=logisticsaccounts&ctl=admin_actual&act=detail_basic&p[0]={$row['aid']}&finder_id={$find_id}" target="dialog::{width:600,height:500,title:'查看'}">查看</a>
EOF;
}else{

         if($row['confirm']=='1'){
$button .= <<<EOF
<a href="index.php?app=logisticsaccounts&ctl=admin_actual&act=edit&p[0]={$row['aid']}&finder_id={$find_id}&action=confirm" target="dialog::{width:600,height:500,title:'审核'}">审核</a>
EOF;
         }
         if($row['confirm']=='2'){
             $button .= <<<EOF
<a href="index.php?app=logisticsaccounts&ctl=admin_actual&act=edit&oper=backconfirm&p[0]={$row['aid']}&finder_id={$find_id}&action=confirm" target="dialog::{width:600,height:500,title:'反审核'}">反审核</a>
EOF;
         }
}
        }

        if($_GET['flt']=='confirmview'){
            $button .= <<<EOF
<a href="index.php?app=logisticsaccounts&ctl=admin_actual&act=detail_basic&p[0]={$row['aid']}&finder_id={$find_id}" target="dialog::{width:600,height:500,title:'查看'}">查看</a>
EOF;
        }
         return $button;
    }
    var $column_task_name = '对账任务名称';
    var $column_task_name_width = '100';
    function column_task_name($row){
       
        $db = kernel::database();
        $SQL = "SELECT t.task_bn FROM sdb_logisticsaccounts_actual_task as t LEFT JOIN sdb_logisticsaccounts_actual as a ON t.task_id=a.task_id WHERE a.logi_no='".$row['logi_no']."' AND a.confirm in ('1','2','3') AND a.status!='4'";
        $task = $db->selectrow($SQL);
        return $task['task_bn'];
    }


     
}

?>
