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

class finance_finder_monthly_report_items{
    var $addon_cols = "order_bn,monthly_id,sales_gap,refund_gap";

	var $column_edit = "操作";
    var $column_edit_width = "150";
    var $column_edit_order=5;
    
    function column_edit($row){
        $confhref = '';
        $row['monthly_id'] = $row[$this->col_prefix.'monthly_id'];
        $row['order_bn'] = $row[$this->col_prefix.'order_bn'];
        if ($_GET['view'] == 0) {
        	$confhref .= sprintf('<a href="index.php?app=finance&ctl=monthend_verification&act=detailVerification&p[0]=%s&p[1]=%s&finder_id=%s" target="_blank">核销</a>&nbsp;&nbsp;&nbsp;&nbsp;',$row['monthly_id'],$row['order_bn'],$_GET['_finder']['finder_id']);
        } else if ($_GET['view'] == 1) {
            $confhref .= sprintf('<a href="index.php?app=finance&ctl=monthend_verification&act=dialog_memo&p[0]=%s&p[1]=%s&finder_id=%s" target="dialog::{title:\'备注\', width:550, height:300}">备注</a>&nbsp;&nbsp;&nbsp;&nbsp;',$row['monthly_id'],$row['order_bn'],$_GET['_finder']['finder_id']);
        }

        $confhref .= sprintf('<a href="index.php?app=finance&ctl=monthend_verification&act=dialog_gap_type&p[0]=%s&p[1]=%s&finder_id=%s" target="dialog::{title:\'设置差异类型\', width:550, height:300}">设置差异类型</a>&nbsp;&nbsp;&nbsp;&nbsp;',$row['monthly_id'],$row['order_bn'],$_GET['_finder']['finder_id']);

        return $confhref;
    }

    public $detail_basic = '基本信息';
    /**
     * detail_basic
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_basic($id) {
        $render = app::get('finance')->render();
        $arList = app::get('finance')->model('ar')->getList('*', ['monthly_item_id'=>$id]);
        foreach($arList as $k => $v) {
            $arList[$k]['type'] = kernel::single('finance_ar')->get_name_by_type($v['type']);
        }
        $billList = app::get('finance')->model('bill')->getList('*', ['monthly_item_id'=>$id]);
        $render->pagedata['ar_list'] = $arList;
        $render->pagedata['bill_list'] = $billList;
        return $render->fetch('monthed/items/detail.html');
    }
}