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

class finance_finder_bill{
    

    /*var $column_edit = "操作";
    var $column_edit_width = "80";
    var $column_edit_order = "2";
    function column_edit($row){
        $bill_id = $row['bill_id'];
        $status = $row['status'];#判断核销状态
        $charge_status = $row['charge_status'];#判断核销状态
        $render = app::get('finance')->render();
        $render->pagedata['bill_id'] = $bill_id;
        $render->pagedata['finder_id'] = $_GET['_finder']['finder_id'];
        // if($charge_status == 0){
        //     return $render->fetch('bill/do_cancel.html');
        // }
        $href = '';
        if($status <> 2 and $row['order_bn']){
            $href .= sprintf('<a href="index.php?app=finance&ctl=settlement&act=detailVerification&finder_id=%s&bill_id=%s" target="_blank">核销</a>&nbsp;&nbsp;&nbsp;&nbsp;',$_GET['_finder']['finder_id'],$row['bill_id']);
            // $href .= '<a href="index.php?app=finance&ctl=settlement&act=resetOrderBn&p[0]='.$row['bill_id'].'&_finder[finder_id]=' . $_GET['_finder']['finder_id'] . '&finder_id=' . $_GET['_finder']['finder_id'] . '" target="_blank">重置订单号</a>';
            return $href;
        }
    }*/

    var $addon_cols = "monthly_id";

    var $detail_basic = "原始数据";
    function detail_basic($id){

        $render = app::get('financebase')->render();
        $mdlBill = app::get('financebase')->model("bill");
        $mdlBillBase = app::get('financebase')->model("bill_base");

        $finance_bill_row = app::get('finance')->model('bill')->getList('unique_id,channel_id',array('bill_id'=>$id));
        $filter = array('unique_id'=>$finance_bill_row[0]['unique_id'],'shop_id'=>$finance_bill_row[0]['channel_id']);

        $bill_row = $mdlBill->getList('platform_type',$filter);
        $base_bill_row = $mdlBillBase->getList('content',$filter);

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

    // var $detail_verification = "核销详情";
    // function detail_verification($id){

    //     $render = app::get('finance')->render();
    //     $mdlBill = app::get('finance')->model("bill");
    //     $mdlBillVerificationRelation = app::get('finance')->model('bill_verification_relation');

   
    //     $bill_row = $mdlBill->getList('bill_bn',array('bill_id'=>$id));


    //     $list = $mdlBillVerificationRelation->getList('*',array('bill_bn'=>$bill_row[0]['bill_bn']));

    //     $render->pagedata['list'] = $list;


    //     return $render->fetch("settlement/verification_relation_detail.html");
    // }

    var $detail_oplog = "操作记录";
    function detail_oplog($id){

        $render = app::get('finance')->render();
        $mdlBill = app::get('finance')->model("bill");
        $mdlBillLogs = app::get('finance')->model("bill_op_logs");

        $bill_row = $mdlBill->getList('bill_bn',array('bill_id'=>$id));
 
        $list = $mdlBillLogs->getList('*',array('log_bn'=>$bill_row[0]['bill_bn']),0,-1,'log_id desc');

        $render->pagedata['list'] = $list;


        return $render->fetch("settlement/verification_logs.html");
    }

    public $column_monthly_id = '账期名称';
    public $column_monthly_id_order = 1;
    public $column_monthly_id_width = 180;
    /**
     * column_monthly_id
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_monthly_id($row, $list) {
        if(!$this->monthly_id) {
            $miRows = app::get('finance')->model('monthly_report')->getList('monthly_id, monthly_date', ['monthly_id'=> array_column($list, $this->col_prefix.'monthly_id')]);
            $this->monthly_id = array_column($miRows, null, 'monthly_id');
        }
        return $this->monthly_id[$row[$this->col_prefix.'monthly_id']]['monthly_date'] ? : '';
    }
}
