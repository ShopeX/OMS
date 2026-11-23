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

class crm_gift_ordertype
{

    public function process($ruleBase, $sdf) {
        if ($ruleBase['filter_arr']['order_types']) {
            $normal_selected  = false;
            $presale_selected = false;
            foreach ($ruleBase['filter_arr']['order_types'] as $order_t) {
                if ($order_t == "normal") {
                    $normal_selected = true;
                }
                if ($order_t == "presale") {
                    $presale_selected = true;
                }
            }
            //$ruleBase['filter_arr']['order_type']普通订单normal和预售订单presale都选的情况下认为是全部订单 不考虑此条件
            if ($normal_selected && $presale_selected) {
            } else {
                if (!in_array($sdf['order_type'], $ruleBase['filter_arr']['order_types'])) {
                    return [false, '不符合指定订单类型'];
                }
            }
        }
        return array(true);
    }
}