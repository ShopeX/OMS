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
class crm_gift_memo {

    public function process($ruleBase, $sdf) {
        //指定商家备注
        if($ruleBase['filter_arr']['order_remark'])
        {
            $no_find = true;
            foreach (explode('|', $ruleBase['filter_arr']['order_remark']) as $key => $val)
            {
                if(stripos($sdf['mark_text'], $val) !== false)
                {
                    $no_find = false; //备注符合条件
                    break;
                }
            }
            
            //没有找到
            if($no_find)
            {
                return [false, '不符合指定商家备注'];
            }
        }
        
        //指定客户备注
        if($ruleBase['filter_arr']['member_remark'])
        {
            $no_find = true;
            foreach (explode('|', $ruleBase['filter_arr']['member_remark']) as $key => $val)
            {
                if(stripos($sdf['custom_mark'], $val) !== false)
                {
                    $no_find = false; //备注符合条件
                    break;
                }
            }
            
            //没有找到
            if($no_find)
            {
                return [false, '不符合指定客户备注'];
            }
        }
        return [true];
    }
}