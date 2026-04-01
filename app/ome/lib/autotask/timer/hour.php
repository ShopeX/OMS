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
 * 每小时执行任务处理类
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

class ome_autotask_timer_hour
{
    public function process($params, &$error_msg=''){
        @ini_set('memory_limit','1024M');
        set_time_limit(0); ignore_user_abort(1);

        // 每小时触发回写 在misctask中
        //kernel::single('erpapi_misc_task')->minute();
        
        // 按小时/天的数据汇总
        kernel::single('eccommon_analysis_task')->analysis_hour();
        kernel::single('eccommon_analysis_task')->analysis_day();

        // 每小时清理48小时前未释放发货单取消人工占冻结
        kernel::single('ome_delivery_freeze')->releaseStockFreeze();
        
        // 每小时判断操作员是否锁定
        kernel::single('desktop_user')->lockUser();
        
        // 每小时拉取猫超商品信息
        kernel::single('inventorydepth_shop_maochao_skus')->syncMaterial();

        // 每小时获取赔付单
        kernel::single('ome_compensate_record')->timeSync();
        
        //[京东云交易]每小时定时拉取退货寄件地址
        //$keplerLib = kernel::single('ome_reship_kepler');
        //$keplerLib->getReshipAddress();
        
        //[兼容]重试推送回传平台发货状态是"发货中"的订单
        //todo：最近发现回传抖音平台发货状态,矩阵没有响应结果
        $orderLib = kernel::single('ome_order');
        $orderLib->push_sync_delivery_confirm();
        
        //每小时触发回写 在misctask中
        //kernel::single('erpapi_misc_task')->hour();
        
        // 每小时触发：库存增加自动审核订单
        kernel::single('ome_autotask_timer_autoconfirmorderstockinc')->process($params, $error_msg);
        
        return true;
    }
}