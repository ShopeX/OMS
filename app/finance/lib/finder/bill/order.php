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

class finance_finder_bill_order{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

	var $type_money = array();

    var $column_trade = "销售收支款";
    var $column_trade_order = 200;
    function column_trade($row){
        $order_bn = $row['order_bn'];
        $time_from = strtotime($_POST['time_from']." 00:00:00");
        $time_to = strtotime($_POST['time_to']." 23:59:59");
        $data = kernel::single('finance_bill')->get_total_money_order_bn($order_bn,$time_from,$time_to);
        $this->money_type = $data;
        return "￥".number_format($this->money_type['trade'],2,'.','');
    }

    var $column_plat = "平台费用";
    var $column_plat_order = 201;
    function column_plat($row){
        return "￥".number_format($this->money_type['plat'],2,'.','');
    }

    var $column_branch = "仓储费用";
    var $column_branch_order = 202;
    function column_branch($row){
        return "￥".number_format($this->money_type['branch'],2,'.','');
    }

    var $column_delivery = "物流费用";
    var $column_delivery_order = 203;
    function column_delivery($row){
        return "￥".number_format($this->money_type['delivery'],2,'.','');
    }

    var $column_other = "其他费用";
    var $column_other_order = 204;
    function column_other($row){
        return "￥".number_format($this->money_type['other'],2,'.','');
    }

    var $column_total = "合计金额";
    var $column_total_order = 205;
    function column_total($row){
        return "￥".number_format($this->money_type['total'],2,'.','');
    }

    function detail_bill($row){
        $bill_id = $row;
        $time_from = strtotime($_GET['time_from']." 00:00:00");
        $time_to = strtotime($_GET['time_to']." 23:59:59");
        $billObj= &app::get('finance')->model('bill');
        $orderdata = $billObj->getList('order_bn,crc32_order_bn',array('bill_id'=>$bill_id));
        $tmp = $billObj->getList('bill_id,order_bn,trade_time,fee_item,fee_obj,money,credential_number,charge_status',array('crc32_order_bn'=>$orderdata[0]['crc32_order_bn'],'charge_status'=>1,'trade_time|between'=>array($time_from,$time_to )));
        $data = array();
        foreach($tmp as $k=>$v){
            if($orderdata[0]['order_bn'] == $v['order_bn']){
                $data[] = $v;
            }
        }
        $render = app::get('finance')->render();
        foreach($data as $k=>$v){
            $money += $v['money'];
            if($v['money'] > 0){
                $data[$k]['plus'] += $v['money'];
            }else{
                $data[$k]['minus'] += abs($v['money']);
            }
        }
        $render->pagedata['data'] = $data;
        $render->pagedata['money'] = $money;
        $render->pagedata['finder_id'] = $_GET['_finder']['finder_id'];
        return $render->fetch('bill/detail.html');
    }
    
}
?>