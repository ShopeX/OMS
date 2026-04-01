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

class monitor_autotask_timer_checkorderisdelivery {


    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '1024M');
        $now = time() - 180;
        $last = strtotime('-1 week');
        $sql = "select order_bn,order_bool_type,pay_status from sdb_ome_orders where is_delivery='N' and status='active' and createtime<{$now} and createtime>{$last} and process_status in ('unconfirmed','confirmed')";
        $list = kernel::database()->select($sql);
        foreach($list as $k => $order) {
            if($order['order_bool_type'] & ome_order_bool_type::__RISK_CODE) {
                unset($list[$k]);
            }
            if($order['pay_status'] != 1) {
                unset($list[$k]);
            }
        }
        if($list) {
            kernel::single('monitor_event_notify')->addNotify('order_360buy_delivery_error', [
                'order_bn' => implode(', ', array_column($list, 'order_bn'))
            ]);
        }
        return true;
    }
}