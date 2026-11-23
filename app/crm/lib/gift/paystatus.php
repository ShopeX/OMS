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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2020/12/29 16:59:59
 * @describe: 类
 * ============================
 */
class crm_gift_paystatus {

    public function process($ruleBase, $sdf) {
        //部分支付
        if($ruleBase['filter_arr']['pay_status'] == '3')
        {
            if($sdf['pay_status'] != '3')
            {
                return [false, '不是部分支付的订单'];
            }
        }
        
        //已支付
        if($ruleBase['filter_arr']['pay_status'] == '1')
        {
            if($sdf['pay_status'] != '1')
            {
                return [false, '不是已支付的订单'];
            }
        }
        return [true];
    }
}