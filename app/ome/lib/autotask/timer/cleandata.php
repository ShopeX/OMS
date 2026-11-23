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
 * 定期清理数据处理类
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

class ome_autotask_timer_cleandata
{
    public function process($params, &$error_msg=''){
        set_time_limit(0);
        ignore_user_abort(1);

        //每天检测日志表，将超过7天的数据清除（放到副表，不实际删除）
        kernel::single('ome_sync_api_log')->clean();

        //防并发表数据清理
        kernel::single('ome_concurrent')->clean();

        //[清除]复审日志任务计划
        kernel::single('ome_retrial')->clean();

        //清除过期的kv信息
        base_kvstore::delete_expire_data();
        
        //定期清理 退款未退货 半年前已完结的单据
        kernel::single('ome_refund_noreturn')->clean();
        
        // 定期清理 预警通知
        kernel::single('monitor_event_notify')->clean('7');
        
        // 定期清理WMS发货单回传结果（六个月前已经完成的记录）
        kernel::single('console_wms_delivery')->cleanWmsDelivery();
        
        // 定期清理WMS退货单回传结果（六个月前已经完成的记录）
        kernel::single('console_wms_delivery')->cleanWmsReship();
        
        return true;
    }
}