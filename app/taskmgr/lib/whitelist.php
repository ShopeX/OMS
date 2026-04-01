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

class taskmgr_whitelist
{
    static private $whiteObj = [];

    public static function initWhitelist($taskName)
    {
        if(empty(self::$whiteObj)) {
            $path = __DIR__.'/whitelist'; 
            foreach (glob($path.'/*.php') as $file_name) {
                require_once $file_name;
                $class_name = 'taskmgr_whitelist_'.basename($file_name,'.php');
                try{
                    self::$whiteObj[] = new $class_name();
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            }
        }
        $task = [];
        foreach (self::$whiteObj as $obj) {
            if(method_exists($obj,$taskName)) {
                $task = array_merge($task,$obj->{$taskName}());
            }
        }
        return $task;
    }

    //进队列业务逻辑处理任务
    public static function task_list()
    {
        $task = self::initWhitelist('task_list');
        $_tasks = array(
            'autochk'       => array(
                'method' => 'wms_autotask_task_check', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  120,
            ),
            'autodly'       => array(
                'method' => 'wms_autotask_task_consign', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  360,
            ),
            'autorder'      => array(
                'method' => 'ome_autotask_task_combine', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  120,
                'name' => '审单队列',
            ), //自动审单
            'pre_select_branch'      => array(
                'method' => 'ome_autotask_task_preselectbranch', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  120,
                'name' => '提前选仓',
            ), //自动审单
            'autoretryapi'  => array(
                'method' => 'erpapi_autotask_task_retryapi', 
                'threadNum' => 5,
                                'retry'     => true,
                'timeout' =>  120,
            ),
            'autowapdly'    => array('method' => 'o2o_autotask_task_statistic', 'threadNum' => 1), //wap统计发货单数据
            'ordertaking'   => array(
                'method' => 'ome_autotask_task_ordertaking', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  120,
            ),
            'confirmreship' => array(
                'method' => 'ome_autotask_task_confirmreship', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  180,
            ), //售后退货单
            'confirminventory' => array(
                'method' => 'ome_autotask_task_confirminventory', 
                'threadNum' => 1,
                'retry'     => true,
                'timeout' =>  180,
            ), //盘点确认
            // 'stocksync'     => array('method' => 'ome_autotask_task_stocksync', 'threadNum' => 5), // 库存检查
            'dailyinventory' => array(
                'method'        => 'console_autotask_timer_dailyinventory', 
                'threadNum'     => 1,
                'timeout'       => 180,
            ),
            'syncaoxiang' => array(
                'method' => 'dchain_autotask_syncproduct', //请求同步翱象商品
                'threadNum' => 1,
                'timeout' => 180,
            ),
            'mappingaoxiang' => array(
                'method' => 'dchain_autotask_mappingproduct', //请求同步翱象商品关系
                'threadNum' => 1,
                'timeout' => 180,
            ),
            'aoxiangdelivery' => array(
                'method' => 'dchain_autotask_syncdelivery', //请求同步翱象发货单
                'threadNum' => 1,
                'timeout' => 180,
            ),
            'inventorydepthproduct' => array(
                'method' => 'ome_autotask_task_inventorydepthproduct', //具体商品发起库存回写
                'threadNum' => 5,
                'timeout' => 180,
            ),
        );
        return array_merge($_tasks, $task);
    }

    //定时任务，线程数不允许修改
    public static function timer_list()
    {
        $task = self::initWhitelist('timer_list');
        $_tasks = array(
            'misctask'          => array('method' => 'ome_autotask_timer_misctask', 'threadNum' => 1),  // 系统定时任务分、时、天、周、月任务
            'inventorydepth'    => array('method' => 'ome_autotask_timer_inventorydepth', 'threadNum' => 1),    // 库存回写
            // 'batchfill'         => array('method' => 'ome_autotask_timer_batchfill', 'threadNum' => 1),  // 订单补单任务
            'cleandata'         => array('method' => 'ome_autotask_timer_cleandata', 'threadNum' => 1),     // 清理数据
            'hour'              => array('method' => 'ome_autotask_timer_hour', 'threadNum' => 1),      //  按小时执行的任务，里面很杂
            'cancelorder'       => array('method' => 'ome_autotask_timer_cancelorder', 'threadNum' => 1),   // 自动取消过期未支付且未确认的订单(除货到付款)
            // 'ordersprice'       => array('method' => 'ome_autotask_timer_ordersprice', 'threadNum' => 1),   // 客单价分布情况
            // 'orderstime'        => array('method' => 'ome_autotask_timer_orderstime', 'threadNum' => 1),    // 订单时间分布情况
            // 'rmatype'           => array('method' => 'ome_autotask_timer_rmatype', 'threadNum' => 1),   // 售后类型分布统计
            'sale'              => array('method' => 'ome_autotask_timer_sale', 'threadNum' => 1),       // 销售情况/退货率统计
            // 'storestatus'       => array('method' => 'ome_autotask_timer_storestatus', 'threadNum' => 1),   // 仓库状态统计
            'stockcost'         => array('method' => 'tgstockcost_autotask_timer_stockcost', 'threadNum' => 1), // OMS库存快照
            'wms_sync_inv'    => array('method' => 'console_autotask_timer_invsnapshot', 'threadNum' => 1),     // WMS库存快照
            'logistestimate'    => array('method' => 'ome_autotask_timer_logistestimate', 'threadNum' => 1),    // 物流预估账单
            'queue'             => array('method' => 'ome_autotask_timer_queue', 'threadNum' => 1, 'name'=>'系统任务队列'),     // 系统任务队列
            // 'stocknsale'        => array('method' => 'ome_autotask_timer_stocknsale', 'threadNum' => 1, 'retry' => false),  // 呆滞库存统计
            // 'productnsale'      => array('method' => 'ome_autotask_timer_productnsale', 'threadNum' => 1, 'retry' => false),    // 不动销商品统计
            // 'storedaliy'        => array('method' => 'o2o_autotask_timer_storedaliy', 'threadNum' => 1),    // 门店订单统计
            'vopurchase'        => array('method' => 'ome_autotask_timer_vopurchase', 'threadNum' => 1),    // VOP采购单下载
            'vopick'            => array('method' => 'ome_autotask_timer_vopick', 'threadNum' => 1),    // VOP拣货单下载
            'vopickinventory'   => array('method' => 'ome_autotask_timer_vopickinventory', 'threadNum' => 1),   // VOP销售单下载
            // 'logisticsanalysts' => array('method' => 'ome_autotask_timer_logisticsanalysts', 'threadNum' => 1), // 仓储物流配送统计
            // 'syncdlystatus'     => array('method' => 'ome_autotask_timer_syncdlystatus', 'threadNum' => 1),
            // 'syncwms'           => array('method' => 'erpapi_autotask_task_sync', 'threadNum' => 1),
            'financebasejob'    => array('method' => 'financebase_autotask_timer_job', 'threadNum' => 1),   // 财务基础数据定时任务
            'sysordertaking'    => array('method' => 'ome_autotask_timer_sysordertaking', 'threadNum' => 1),    // 定时审单任务
            'delaymisc'         => array('method' => 'ome_autotask_timer_delaymisc', 'threadNum' => 1),     // 延时定时审单任务
            'batchchannelmaterial' => array('method' => 'material_autotask_timer_batchchannelmaterial', 'threadNum' => 1),  // 定时同步物料至WMS
            'orderdiscounts'    => array('method'=>'omeanalysts_autotask_timer_orderdiscounts', 'threadNum'=>1),    // 订单优惠明细统计

            'vopbill'              => array('method' => 'vop_autotask_timer_bill', 'threadNum' => 1),    // 唯品会账单
            'vopreturn'            => array('method' => 'ome_autotask_timer_vopreturn', 'threadNum' => 1),  // 唯品会退货单
            'aoxiangsync' => array('method'=>'dchain_autotask_aoxiangsync', 'threadNum'=>1), // 翱象同步商品
            'aoxiangmapping' => array('method'=>'dchain_autotask_aoxiangmapping', 'threadNum'=>1), // 翱象同步商品关系
            'dailyinvmonitor' => array('method' => 'console_autotask_timer_dailyinvmonitor', 'threadNum' => 1), // 库存核对报警通知
            'sendnotify'    => array('method'=>'monitor_autotask_timer_sendnotify', 'threadNum'=>1),    // 报警通知
            'invoice_makeoutinvoice'       => array('method' => 'invoice_autotask_timer_makeoutinvoice', 'threadNum' => 1), // 开具发票
            'invoice_redapply_sync' => array('method' => 'invoice_autotask_timer_redapply_sync', 'threadNum' => 1),     // 红冲申请同步
            'jit_vreturn_diff' => array('method' => 'vop_autotask_timer_vreturndiff', 'threadNum' => 1), // 唯品会退供差异单

            'sync_branch_freeze_decr' => array('method'=>'ome_autotask_timer_branchfreezedecr', 'threadNum' => 1), // 后置仓更新冻结
            'sync_sku_freeze_decr' => array('method'=>'ome_autotask_timer_skufreezedecr', 'threadNum' => 1), // 后置商品更新冻结
            'check_freeze_store' => array('method'=>'monitor_autotask_timer_checkfreezestore', 'threadNum' => 1, 'retry' => false, 'timeout' => 600), // 对比ome_branch_product和basic_material_stock_freeze
            // 'clean_freeze_queue' => array('method'=>'monitor_autotask_timer_cleanfreezequeue', 'threadNum' => 1),   // 清理冻结队列

            'ediws_accountorders'=>array('method'=>'ediws_autotask_timer_accountorders', 'threadNum' => 1),// EDI实销实结明细
            'ediws_accountsettlement'=>array('method'=>'ediws_autotask_timer_accountsettlement', 'threadNum' => 1),// EDI结算单
            'ediws_shppackage'=>array('method'=>'ediws_task_shippackage', 'threadNum' => 1),// EID退供
            'ediws_reship'=>array('method'=>'ediws_task_reship', 'threadNum' => 1),// EDI主库退货单

            'o2o_syncproduct'=>array('method'=>'o2o_autotask_timer_syncproduct','threadNum' => 1), // 批量同步商品到O2O
            'ediws_refundinfo'=>array('method'=>'ediws_task_refundinfo', 'threadNum' => 1),// EDI退货

            'sync_shop_skus'=>array('method'=>'inventorydepth_autotask_timer_shopskus', 'threadNum' => 1, 'timeout' => 180),// 下载缓存商品
            'check_order_is_delivery' => array('method'=>'monitor_autotask_timer_checkorderisdelivery', 'threadNum' => 1), 
            'o2oundelivery'    => array('method'=>'monitor_autotask_timer_o2oundelivery', 'threadNum'=>1),
            'retry_delivery_cancel_to_wms'=>array('method'=>'ome_autotask_timer_retrydeliverycancel', 'threadNum' => 1),// 重试推送wms发货单取消
            'ediws_fixaccountorders'=>array('method'=>'ediws_autotask_timer_fixaccountorders', 'threadNum' => 1),// EDI实销实结明细补拉任务
            'invoice_queryinvoicelist'       => array('method' => 'invoice_autotask_timer_queryinvoicelist', 'threadNum' => 1),
            'platform_timeliness' => array('method' => 'ome_autotask_timer_platform_timeliness', 'threadNum' => 1),// 平台时效检查
        );
        return array_merge($_tasks, $task);
    }

    //初始化域名进任务队列,这里的命名规范就是实际连的队列任务+domainqueue生成这个初始化任务的数组值，线程数不允许修改
    public static function init_list()
    {
        $task = self::initWhitelist('init_list');
        $_tasks = array(
            'misctaskdomainqueue'    => array(
                'threadNum' => 1,
                'rule'      => '*/30 * * * * *',
            ),
            'inventorydepthdomainqueue'    => array(
                'threadNum' => 1,
                'rule'      => '0 */5 * * * *',
            ),
            // 'batchfilldomainqueue'         => array(
            //     'threadNum' => 1,
            //     'rule' => '0 */30 * * * *',
            // ),
            'cleandatadomainqueue'         => array(
                'threadNum' => 1,
                'rule' => '0 0 1 * * *',
            ),
            'hourdomainqueue'              => array(
                'threadNum' => 1,
                'rule' => '0 0 * * * *',
            ),
            'cancelorderdomainqueue'       => array(
                'threadNum' => 1,
                'rule' => '0 */10 * * * *',
            ),
            // 'orderspricedomainqueue'       => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 1 * * *',
            // ),
            // 'orderstimedomainqueue'        => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 1 * * *',
            // ),
            // 'rmatypedomainqueue'           => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 2 * * *',
            // ),
            'saledomainqueue'              => array(
                'threadNum' => 1,
                'rule' => '0 0 2 * * *',
            ),
            // 'catsalestatisdomainqueue'     => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 3 * * *',
            // ),
            // 'productsalerankdomainqueue'   => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 3 * * *',
            // ),
            // 'storestatusdomainqueue'       => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 4 * * *',
            // ),
            'stockcostdomainqueue'         => array(
                'threadNum' => 1,
                'rule' => '0 0 1 * * *',//OMS库存快照生成时间要早于wms_sync_invdomainqueue获取WMS物料库存执行时间
            ),
            'wms_sync_invdomainqueue'         => array(
                'threadNum' => 1,
                'rule' => '0 0 2 * * *',
            ),
            'logistestimatedomainqueue'    => array(
                'threadNum' => 1,
                'rule' => '0 0 5 * * *',
            ),
            // 'stocknsaledomainqueue'        => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 1 * * *',
            // ),
            // 'productnsaledomainqueue'      => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 2 * * *',
            // ),
            // 'storedaliydomainqueue'        => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 3 * * *',
            // ),
            'vopurchasedomainqueue'        => array(
                'threadNum' => 1,
                'rule' => '0 0 * * * *',
            ),
            'vopickdomainqueue'            => array(
                'threadNum' => 1,
                'rule' => '0 */5 * * * *',
            ),
            'vopickinventorydomainqueue'   => array(
                'threadNum' => 1,
                'rule' => '0 */2 * * * *',
            ),
            // 'logisticsanalystsdomainqueue' => array(
            //     'threadNum' => 1,
            //     'rule' => '0 0 3 * * *',
            // ),
            'financebasejobdomainqueue'    => array(
                'threadNum' => 1,
                'rule' => '0 * * * * *',
            ),
            'sysordertakingdomainqueue'    => array(
                'threadNum' => 1,
                'rule' => '0 */1 * * * *',
            ),
            'delaymiscdomainqueue'         => array(
                'threadNum' => 1,
                'rule' => '*/10 * * * * *',
            ),
            'batchchannelmaterialdomainqueue'   => array(
                'threadNum' => 1,
                'rule' => '0 */10 * * * *',
            ),
            'orderdiscountsdomainqueue'     => array(
                'threadNum' => 1,
                'rule' => '0 */30 * * * *',
            ),
            'vopbilldomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '0 */30 * * * *',
            ),
            'vopreturndomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '0 */30 * * * *',
            ),
            'aoxiangsyncdomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '0 0 * * * *',
            ),
            'aoxiangmappingdomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '0 0 * * * *',
            ),
            'dailyinvmonitordomainqueue'         => array(
                'threadNum' => 1,
                'rule' => '0 0 8 * * *',
            ),
            'sendnotifydomainqueue'         => array(
                'threadNum' => 1,
                'rule' => '0 */5 * * * *',
            ),
            'invoice_makeoutinvoicedomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '0 */10 * * * *',
            ),
            'invoice_redapply_sync' => array(
                'threadNum' => 1,
                'rule' => '0 */30 * * * * ',
            ),
            'jitsaledomainqueue' => array(
                'threadNum' => 1,
                'rule' => '0 */5 * * * * ',
            ),
            'jit_vreturn_diff' => array(
                'threadNum' => 1,
                'rule' => '0 0 * * * * ',
            ),
            'sync_branch_freeze_decrdomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '*/1 * * * * *',
            ),
            'sync_sku_freeze_decrdomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '*/1 * * * * *',
            ),
            'check_freeze_storedomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '*/60 * * * * *',
            ),
            // 'clean_freeze_queuedomainqueue'=>array(
            //     'threadNum' => 1,
            //     'rule' => '0 6 0 * * *', // 00:06:00
            // ),
            'ediws_accountorders'=>array(
                'threadNum' => 1,
                'rule' => '0 */30 * * * *',
            ),
            'ediws_accountsettlement'=>array(
                'threadNum' => 1,
                'rule' => '0 0 2 * * *',
            ),
            'ediws_shppackage'=>array(
                'threadNum' => 1,
                'rule' => '0 */30 * * * *',
            ),
            'ediws_reship'=>array(
                'threadNum' => 1,
                'rule' => '0 */30 * * * *',
            ),
            'o2o_syncproductdomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '0 */30 * * * * ',

            ),
            'ediws_refundinfo'=>array(
                'threadNum' => 1,
                'rule' => '0 */30 * * * *',
            ),
            'sync_shop_skus'=>array(
                'threadNum' => 1,
                'rule' => '0 0 * * * *',
            ),
            'check_order_is_deliverydomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '0 */20 * * * *',
            ),
            'o2oundeliverydomainqueue'=>array(
                'threadNum' => 1,
                'rule' => '0 0 * * * *',
            ),
            'retry_delivery_cancel_to_wms'=>array(
                'threadNum' => 1,
                'rule' => '0 */5 * * * *',
            ),
            'ediws_fixaccountorders'=>array(
                'threadNum' => 1,
                'rule' => '0 0 3 * * *',
            ),
            // 'retry_reship_cancel_to_wms'=>array(
            //     'threadNum' => 1,
            //     'rule' => '0 */5 * * * *',
            // ),
            'invoice_queryinvoicelist'=>array(
                'threadNum' => 1,
                'rule' => '0 */15 * * * *',
            ),
            'platform_timelinessdomainqueue' => array(
                'threadNum' => 1,
                'rule' => '0 */20 * * * *',
            ),
        );
        return array_merge($_tasks, $task);
    }

    //导出任务
    public static function export_list()
    {
        $task = self::initWhitelist('export_list');
        $_tasks = array(
            'exportsplit'           => array(
                'method' => 'ome_autotask_export_exportsplit', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  180,
                'name' => '导出数据分片队列',
            ),
            'dataquerybysheet'      => array(
                'method' => 'ome_autotask_export_dataquerybysheet', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  600,
                'name' => '分页导出队列',
            ),
            'dataquerybyquicksheet' => array(
                'method' => 'ome_autotask_export_dataquerybyquicksheet', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  600,
                'name' => '快速导出队列',
            ),
            'dataquerybywhole'      => array(
                'method' => 'ome_autotask_export_dataquerybywhole', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  3600,
                'name' => '完整导出队列',
            ),
            'createfile'            => array(
                'method' => 'ome_autotask_export_createfile', 
                'threadNum' => 5,
                'retry'     => true,
                'timeout' =>  3600,
                'name' => '导出文件生成队列',
            ),
        );
        return array_merge($_tasks, $task);
    }

    //rpc任务
    public static function rpc_list()
    {
        $task = self::initWhitelist('rpc_list');
        $_tasks = array(
            'omecallback' => array('method' => 'ome_autotask_rpc_omecallback', 'threadNum' => 5),
            'wmscallback' => array('method' => 'ome_autotask_rpc_wmscallback', 'threadNum' => 5),
            'wmsrpc'      => array(
                'method' => 'ome_autotask_rpc_wmsrpc', 
                'threadNum' => 5,
                'name' => 'WMS发货通知队列',
            ),
            'orderrpc'    => array(
                'method' => 'ome_autotask_rpc_orderrpc', 
                'threadNum' => 5,
                'name' => '收单队列',
            ),
        );
        return array_merge($_tasks, $task);
 }

    public static function finance_list(){
        $task = self::initWhitelist('finance_list');
        $_tasks = array(
            'billapidownload'       => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //财务下载任务
            'billassign'            => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //账单导入分派任务
            'billjdwalletassign'    => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //京东钱包导入分派任务
            'cainiaoassignorder'    => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //菜鸟导入分派任务
            'cainiaoassignsku'      => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //菜鸟导入分派任务
            'cainiaoassignsale'     => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //账单导入分派任务
            'billimport'            => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //账单导入
            'cainiaoorderimport'    => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //菜鸟根据订单导入
            'cainiaoskuimport'      => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //菜鸟根据sku导入
            'cainiaosaleimport'     => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //菜鸟根据销售周期导入
            'syncaftersales'        => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //同步售后单
            'syncsales'             => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //同步销售单
            // 'verificationassign'    => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //分派流水单核销
            // 'verificationprocess'   => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //账单核算自动对账任务
            'initmonthlyreport'     => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //生成新账期任务
            'expensessplit'         => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //费用拆分
            'cainiaoassignjzt'      => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //京准通导入分派任务
            'cainiaojztimport'      => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //京准通导入保存数据任务
            'cainiaoassignjdbill'   => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //京东钱包流水导入分派任务
            'cainiaojdbillimport'   => array('method'=>'financebase_autotask_task_process', 'threadNum'=>1), //京东钱包流水导入保存数据任务
            
            'assign'                => array('method'=>'omecsv_autotask_task_process', 'threadNum' => 1), //分片导入任务
            'import'                => array('method'=>'omecsv_autotask_task_process', 'threadNum' => 1), //分片导入
            'jitsale'               => array('method'=>'billcenter_autotask_timer_sales', 'threadNum' => 1), // JIT销售单
            'jitaftersale'          => array('method'=>'billcenter_autotask_timer_aftersales', 'threadNum' => 1), // JIT售后单

        );
        return array_merge($_tasks, $task);
    }

    //全部任务
    public static function get_all_task_list()
    {
        return array_merge(self::task_list(), self::timer_list(), self::export_list(), self::rpc_list(), self::finance_list());
    }

    public static function get_task_types()
    {
        return array('task', 'timer', 'init', 'export', 'rpc', 'finance');
    }
}
