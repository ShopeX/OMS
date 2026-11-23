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

/**
 * Created by PhpStorm.
 * User: yaokangming
 * Date: 2019/3/19
 * Time: 18:04
 */

class crm_gift_buygoods
{

    public function process($ruleBase, $sdf, &$suite) {
        #限量赠送
        if($ruleBase['filter_arr']['buy_goods']['limit_type'] == 1 ){
            #判断已经送出的订单数
            $sql = "select count(distinct order_bn) as total_orders from sdb_ome_gift_logs where gift_rule_id=".$ruleBase['rule_id']." AND rule_base_id=".intval($ruleBase['id']);
            #查询当天
            if($ruleBase['filter_arr']['buy_goods']['limit_time_day'] == 'day'){
                $from_time = strtotime(date('Y-m-d'));
                $to_time = strtotime(date('Y-m-d'."23:59:59"));
                $sql = "select count(distinct order_bn) as total_orders from sdb_ome_gift_logs where ( create_time>=".$from_time." and create_time<=".$to_time." ) and gift_rule_id=".$ruleBase['rule_id']." AND rule_base_id=".intval($ruleBase['id']);
            }
            $rs_temp = kernel::database()->selectRow($sql);
            if($rs_temp) {
                if($rs_temp['total_orders'] >= $ruleBase['filter_arr']['buy_goods']['limit_orders']){
                    $reason = '超过送出数量限制';
                    return array(false, $reason);
                }
            }
        }
        #购买指定商品的数量或金额
        $has_buy = false;
        $item_nums = $this->get_buy_goods_num($ruleBase, $sdf, $has_buy);

        if($has_buy == false){
            $reason = '不符合指定商品购买条件';
            return array(false, $reason);
        }


        #计算赠品数量
        if($ruleBase['filter_arr']['buy_goods']['num_rule'] == 'auto'){
            $ratio = intval($item_nums/$ruleBase['filter_arr']['buy_goods']['per_num']);
            $suite = $ruleBase['filter_arr']['buy_goods']['send_suite']*$ratio;
            $suite = min($suite, $ruleBase['filter_arr']['buy_goods']['max_send_suite']);
            if($suite >= 1){

            }else{
                #数量不符合要求
                $reason = '不符合商品数量购买条件';
                return array(false, $reason);
            }
        }else{

            if($ruleBase['filter_arr']['buy_goods']['rules_sign']=='nequal'){
                if($item_nums!=$ruleBase['filter_arr']['buy_goods']['min_num']){
                    $reason = '不等于指定数量';
                    return array(false, $reason);
                }
            }elseif($ruleBase['filter_arr']['buy_goods']['rules_sign']=='between'){
                if($item_nums<$ruleBase['filter_arr']['buy_goods']['min_num'] or $item_nums>=$ruleBase['filter_arr']['buy_goods']['max_num']){
                    $reason = '不在数量范围内';
                    return array(false, $reason);
                }
            }else{
                if($item_nums<$ruleBase['filter_arr']['buy_goods']['min_num']){
                    $reason = '小于指定数量';
                    return array(false, $reason);
                }
            }
        }
        return array(true);
    }

    #有效的商品数量
    protected function get_buy_goods_num($ruleBase, $sdf, &$has_buy){
        $item_bns = array();
        $buy_goods_bns = $ruleBase['filter_arr']['buy_goods']['goods_bn'];
        if( ! is_array($buy_goods_bns)){
            $buy_goods_bns = array(strtoupper($buy_goods_bns));
        }

        #清理空数据
        $buy_goods_bns = $this->clear_value($buy_goods_bns);#这里需要确认是不是还需要
        $type = $ruleBase['filter_arr']['buy_goods']['type'];
        if($type == '2') {
            $order_items = $sdf['items'];
        } else {
            $order_items = $sdf['objects'];
        }
        #购买指定商品
        if(in_array($type, array('1', '2'))) {
            foreach ($order_items as $item) {
                $item['bn'] = strtoupper($item['bn']);
                #购买了全部指定商品
                if ($ruleBase['filter_arr']['buy_goods']['buy_type'] == 'all') {
                    if (in_array($item['bn'], $buy_goods_bns)) {
                        if (!in_array($item['bn'], $item_bns)) $item_bns[] = $item['bn'];
                    }
                } #排除购买的指定商品
                elseif ($ruleBase['filter_arr']['buy_goods']['buy_type'] == 'none') {
                    if (!in_array($item['bn'], $buy_goods_bns)) {
                        if (!in_array($item['bn'], $item_bns)) $item_bns[] = $item['bn'];
                        $has_buy = true;
                    }
                } #购买了任意一个指定商品
                else {
                    if (in_array($item['bn'], $buy_goods_bns)) {
                        if (!in_array($item['bn'], $item_bns)) $item_bns[] = $item['bn'];
                        $has_buy = true;
                    }
                }
            }
            if($ruleBase['filter_arr']['buy_goods']['buy_type'] == 'all'){
                #此处为空表示购买全部所配置商品
                $diff = array_diff($buy_goods_bns, $item_bns);
                if(empty($diff)){
                    $has_buy = true;
                }
            }
        }else{
            $has_buy = true;
        }
        if($has_buy === true) {
            return $this->calculate_goods_num($ruleBase, $sdf, $item_bns);
        }
        return 0;
    }

    #通过计算方式计算数量
    protected function calculate_goods_num($ruleBase, $sdf, $item_bns) {
        $item_nums = 0;
        $count_type = $ruleBase['filter_arr']['buy_goods']['count_type'];//num or sku or paid
        $calculate_type = $ruleBase['filter_arr']['buy_goods']['calculate_type'];//appoint or all or least
        $type = $ruleBase['filter_arr']['buy_goods']['type'];
        $buy_type = $ruleBase['filter_arr']['buy_goods']['buy_type'];
        if($type == '2') {
            $order_items = $sdf['items'];
        } else {
            $order_items = $sdf['objects'];
        }
        if($count_type == 'num' || empty($count_type)){
            #计算sku数量
            if(in_array($type, array('1', '2'))){
                #购买指定商品，需要以计算方式为准
                if($calculate_type == 'all') {
                    #购买的全部商品之和
                    foreach ($order_items as $item) {
                        $item_nums += $item['nums'];
                    }
                } elseif($calculate_type == 'least' || (empty($calculate_type) && $buy_type == 'all')) {
                    #购买的指定商品最小值
                    $allBnNum = array();
                    foreach ($order_items as $item) {
                        $item['bn'] = strtoupper($item['bn']);
                        if(in_array($item['bn'], $item_bns)) {
                            $allBnNum[$item['bn']] += $item['nums'];
                        }
                    }
                    $item_nums = min($allBnNum);
                } else {
                    #购买的指定商品之和
                    foreach ($order_items as $item) {
                        $item['bn'] = strtoupper($item['bn']);
                        if(in_array($item['bn'], $item_bns)) {
                            $item_nums += $item['nums'];
                        }
                    }
                }
            }else{
                #购买任意商品，以订单全部sku为准
                foreach ($order_items as $item) {
                    $item_nums += $item['nums'];
                }
            }
        }elseif($count_type == 'sku'){
            #计算sku种类
            if(in_array($type, array('1', '2'))){
                #购买指定商品，需要以计算方式为准
                if($calculate_type == 'all') {
                    #购买的全部商品之和
                    $allBn = array();
                    foreach ($order_items as $item) {
                        $item['bn'] = strtoupper($item['bn']);
                        $allBn[$item['bn']] = $item['bn'];
                    }
                    $item_nums = count($allBn);
                } elseif($calculate_type == 'least') {
                    #购买的指定商品最小值
                    $item_nums = 1;
                } else {
                    #购买的指定商品之和
                    $item_nums = count($item_bns);
                }
            }else{
                #购买任意商品，sku种类,以订单全部sku种类为准
                $allBn = array();
                foreach ($order_items as $item) {
                    $item['bn'] = strtoupper($item['bn']);
                    $allBn[$item['bn']] = $item['bn'];
                }
                $item_nums = count($allBn);
            }
        }elseif($count_type == 'paid'){
            #按元统计
            if(in_array($type, array('1', '2'))){
                #购买指定商品，需要以计算方式为准
                if($calculate_type == 'all' || empty($calculate_type)) {
                    #购买的全部商品之和
                    $item_nums = floatval($sdf['payed']);
                } elseif($calculate_type == 'least') {
                    #购买的指定商品最小值
                    $allBnSale = array();
                    foreach ($order_items as $item) {
                        $item['bn'] = strtoupper($item['bn']);
                        if(in_array($item['bn'], $item_bns)) {
                            $salePrice = $item['divide_order_fee'] > 0 ? $item['divide_order_fee'] : $item['price'];
                            $allBnSale[$item['bn']] += $salePrice;
                        }
                    }
                    $item_nums = min($allBnSale);
                } else {
                    #购买的指定商品之和
                    foreach ($order_items as $item) {
                        $item['bn'] = strtoupper($item['bn']);
                        if(in_array($item['bn'], $item_bns)) {
                            $salePrice = $item['divide_order_fee'] > 0 ? $item['divide_order_fee'] : $item['price'];
                            $item_nums += $salePrice;
                        }
                    }
                }
            }else{
                #购买任意商品，以订单支付金额为准
                $item_nums = floatval($sdf['payed']);
            }
        }
        return $item_nums;
    }
    #删除数组里的空元素
    protected function clear_value($arr){
        foreach($arr as $k=>$v){
            if( ! $v) unset($arr[$k]);
        }
        return $arr;
    }
}