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
 * 重试推送wms失败的发货单取消 
 *
 *
 * @author
 * @version 0.1
 */

class ome_autotask_timer_retrydeliverycancel
{
    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);

        // 防重逻辑
        base_kvstore::instance(__CLASS__)->fetch('status', $status);
        if ($status == 'running') {
            $error_msg = '重试推送WMS发货单取消任务正在运行，请勿重复操作！';
            return true;
        }

        base_kvstore::instance(__CLASS__)->store('status', 'running', 3600);

        // 获取取消失败的发货单（6小时之内创建的）
        $sync = kernel::single('console_delivery_bool_sync')->getBoolSync([
            'in' => [console_delivery_bool_sync::__CANCEL_FAIL]
        ]);
        
        $sixHoursAgo = time() - 6 * 3600;
        
        $deliveryList = app::get('console')->model('delivery')->getList(
            'delivery_id,branch_id,delivery_bn,status', 
            [
                'filter_sql' => 'sync in (' . implode(',', $sync) . ') AND sync > 9 AND create_time >= ' . $sixHoursAgo,
                'status' => ['ready', 'progress'],
                'parent_id' => 0
            ],
            0,
            30,
            'last_modified ASC'
        );
        
        if (empty($deliveryList)) {
            $error_msg = 'no delivery to retry';
            base_kvstore::instance(__CLASS__)->delete('status');
            return true;
        }

        $deliveryMdl = app::get('console')->model('delivery');
        
        // 处理每个发货单
        foreach ($deliveryList as $delivery) {
            $delivery_id = $delivery['delivery_id'];
            
            // 取消发货单
            $res = ome_delivery_notice::cancel($delivery, true);
            
            if ($res['rsp'] == 'success' || $res['rsp'] == 'succ') {
                // 更新发货单状态（update方法内已记录日志）
                $data = array(
                    'status' => 'cancel',
                    'memo' => '系统自动重试取消发货单',
                    'delivery_bn' => $delivery['delivery_bn'],
                );
                kernel::single('ome_event_receive_delivery')->update($data);
            } else {
                // 失败时更新 last_modified，让数据流动
                $deliveryMdl->update(['last_modified' => time()], ['delivery_id' => $delivery_id]);
            }
        }

        base_kvstore::instance(__CLASS__)->delete('status');
        return true;
    }

}
