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

class financebase_finder_bill{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


	var $detail_basic = "原始数据";
    function detail_basic($id){
        $render = app::get('financebase')->render();
        $mdlBill = app::get('financebase')->model("bill");
        $mdlBillBase = app::get('financebase')->model("bill_base");

        $bill_row = $mdlBill->getList('unique_id,shop_id,platform_type',array('id'=>$id));
        $base_bill_row = $mdlBillBase->getList('content',array('shop_id'=>$bill_row[0]['shop_id'],'unique_id'=>$bill_row[0]['unique_id']));

        $class_name = 'financebase_data_bill_'.$bill_row[0]['platform_type'];
        if (!ome_func::class_exists($class_name)){
        	die($bill_row[0]['platform_type'].'无此类型的方法');
        }

        $instance = kernel::single($class_name);

        $array_title = $instance->getTitle();

        $array_content = json_decode($base_bill_row[0]['content'],1);

        $info = array();

        foreach ($array_title as $key => $value) {
        	$info[$key] = array('title'=>$value,'content'=>$array_content[$key]);
        }

 		$render->pagedata['info'] = $info;

        return $render->fetch("admin/bill/detail.html");
    }

    var $detail_oplog = "操作日志";
    function detail_oplog($id){
        $render = app::get('financebase')->render();
        $mdlBill = app::get('financebase')->model("bill");
        $mdlOpLog = app::get('finance')->model("bill_op_logs");


        $bill_info= $mdlBill->getList('bill_bn',array('id'=>$id));
        
        $list = $mdlOpLog->getList('*',['log_bn'=>$bill_info[0]['bill_bn']],0,-1,' log_id desc');

 		$render->pagedata['list'] = $list;

        return $render->fetch("admin/bill/op_logs.html");
    }


    var $column_edit = "操作";
    var $column_edit_width = "80";
    function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        $ret = '';
        if($row['status'] < 2)
        {
        	$ret .= '<a href="index.php?app=financebase&ctl=admin_shop_settlement_bill&act=resetOrderBn&p[0]='.$row['id'].'&_finder[finder_id]=' . $finder_id . '&finder_id=' . $finder_id . '" target="_blank">重置订单号</a>';
        }

        return $ret;
    }
}