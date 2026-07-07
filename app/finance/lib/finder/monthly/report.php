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


class finance_finder_monthly_report{

    var $column_edit = "操作";
    var $column_edit_width = "240";
    var $column_edit_order=5;
    function column_edit($row){
        $confhref = '';
        $finder_id = $_GET['_finder']['finder_id'];
        $return_view = isset($_GET['view']) ? $_GET['view'] : 0;
        $return_page = isset($_GET['page']) ? $_GET['page'] : 1;

        

        if($row['status'] == 2)
        {
            $confhref .= '<a href="index.php?app=finance&ctl=monthend_detail&act=index&p[0]='.$row['monthly_id'].'">账期明细</a>';
        }elseif ($row['status'] == 1) {
            $now_time = time();

            if($now_time > $row['end_time'])
            {
                $confhref = '<a target="dialog::{title:\'关账确认页面\',width:400,height:400}" href="index.php?app=finance&ctl=monthend&act=closebook&_finder[finder_id]='.$_GET['_finder']['finder_id'].'&p[0]='.$row['monthly_id'].'">关账</a>&nbsp;&nbsp;&nbsp;&nbsp;';
            }
            $confhref .= '<a href="index.php?app=finance&ctl=monthend_verification&act=index&p[0]='.$row['monthly_id'].'&finder_id='.$finder_id.'&finder_vid='.$_GET['finder_vid'].'&return_view='.$return_view.'&return_page='.$return_page.'">核销列表</a>&nbsp;&nbsp;&nbsp;&nbsp;';
            // $confhref .= '<a href="index.php?app=finance&ctl=monthend_uncharge&act=index&p[0]='.$row['monthly_id'].'&finder_vid='.$_GET['finder_vid'].'">往期单据</a>&nbsp;&nbsp;&nbsp;&nbsp;';//核销更改， 该功能不可用 

            // $confhref .= '<a href="index.php?app=finance&ctl=monthend&act=reverify&p[0]='.$row['monthly_id'].'">重新核销</a>';//核销更改， 该功能不可用 

        }

        return $confhref;
    }
}
