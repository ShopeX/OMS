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

class crm_gift_orderamount
{

    public function process($ruleBase, $sdf) {
        $payed = floatval($sdf['payed']);
        $total_amount = floatval($sdf['total_amount']);
        #按付款总额判断
        if($ruleBase['filter_arr']['order_amount']['type'] == 1){
            if($ruleBase['filter_arr']['order_amount']['sign']=='bthan'){
                if($payed<$ruleBase['filter_arr']['order_amount']['max_num']){
                    $reason = '不满足最低付款';
                    return array(false, $reason);
                }
            }else{
                if($payed<$ruleBase['filter_arr']['order_amount']['min_num'] or $payed>=$ruleBase['filter_arr']['order_amount']['max_num']){
                    $reason = '不满足付款区间';
                    return array(false, $reason);
                }
            }
        }
        #按订单总额判断
        if($ruleBase['filter_arr']['order_amount']['type'] == 2){
            if($ruleBase['filter_arr']['order_amount']['sign']=='bthan'){
                if($total_amount<$ruleBase['filter_arr']['order_amount']['max_num']){
                    $reason = '订单总额不满足最低付款';
                    return array(false, $reason);
                }
            }else{
                if($total_amount<$ruleBase['filter_arr']['order_amount']['min_num'] or $total_amount>=$ruleBase['filter_arr']['order_amount']['max_num']){
                    $reason = '订单总额不满足付款区间';
                    return array(false, $reason);
                }
            }
        }
        return array(true);
    }
}