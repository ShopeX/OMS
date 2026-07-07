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
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/13
 * @Describe: 监控预警模板Lib类
 */
class monitor_event_template
{
    /**
     * 获取预警事件类型
     * @Author: xueding
     * @Vsersion: 2022/10/13 下午4:57
     * @return string[]
     */
    public function getEventType()
    {
        $eventType = array(
            // 'wms_bill_fail_warning'    => 'WMS单据请求失败报警', // 已经废弃
            // 'wms_delivery_consign'     => 'WMS发货失败报警',
            // 'wms_reship_finish'        => 'WMS退货失败报警',
            // 'wms_stockin_finish'       => 'WMS入库失败报警',
            // 'wms_stockout_finish'      => 'WMS出库失败报警',
             'wms_stock_change'         => 'WMS异动失败报警',
            // 'wms_stockprocess_confirm' => 'WMS加工单确认失败报警',
            // 'wms_transferorder_finish' => 'WMS移库失败报警',

            'process_undelivery'       => '未发货订单通知',

            'stock_sync'               => '平台库存同步失败报警',
            'under_safty_inventory'    => '低于安全库存报警',
            'stock_diff_alarm'         => '实物库存差异报警',

            'pos_stock_sync'           => 'POS库存同步失败报警',
            'pos_o2oundelivery'        => 'POS现货订单未发货报警',

            'system_message'           => '系统消息通知',
            'invoice_result_error'     => '发票处理失败报警',
            'order_360buy_delivery_error' => '【京东】订单挂起不可以发货',
            'store_freeze_abnormal'    => '库存或冻结消费异常报警',
            'rpc_warning'              => 'RPC调用失败报警',
            'store_freeze_abnormal'    => '库存或冻结消费异常报警',
            'inventory_calc_error'     => '库存计算异常报警',
            'order_ship_refund_apply'  => '订单已发货仅退款发起申请',
            'order_unship_refund_apply'  => '订单未发货退款发起申请',
            'order_refund_apply_reback_fail' => '订单退款发起申请发货单撤销失败',
            'order_part_ship_refund_apply' => '订单部分发货仅退款发起申请',
            'order_refund_apply_reback_succ' => '退款申请对应发货单均撤回',
            'order_refund_apply_force_refund' => '退款单强制退款',
            'order_delivery_timeliness' => '订单发货时效提醒',
            'order_delivery_platform_sync_error' => '订单发货回写平台失败',
            'inventory_calc_error'     => '库存计算异常报警',
            // 新增事件类型
            'delivery_cancel_success'  => '发货单取消成功通知',
            'reship_cancel_success'    => '退货单取消成功通知',
            'delivery_cancel_wms'      => '发货单撤销WMS通知',
            'sap_sync_error'            => 'SAP同步异常报警',
            'order_lack_notify'         => '订单缺货通知',
        );
        foreach(kernel::servicelist('monitor.service.event.template.get.after') as $object) {
            if(method_exists($object, 'getEventType')){
                $rs = $object->getEventType();
                if(is_array($rs) && $rs) {
                    $eventType = array_merge($eventType, $rs);
                }
            }
        }
        return $eventType;
    }

    /**
     * 获取各事件类型的默认模板内容
     * 内容格式参考 initial/monitor.event_template.sql
     * @return array<string,string>
     */
    public function getEventDefaultContent()
    {
        return array(
            'rpc_warning' => <<<TPL
RPC调用失败报警
    >业务：<font color="warning">{title}</font>
    >单据：<font color="warning">{bill_bn}</font>
    >接口名：<font color="warning">{method}</font>
    >错误信息：<font color="warning">{errmsg}</font>
TPL
,
            'pos_stock_sync' => <<<TPL
POS库存同步失败通知
    >门店：<font color="warning">{store_bn}</font>
    >仓库：<font color="warning">{branch_bn}</font>
    >PageNo：<font color="warning">{page_no}</font>
    >错误信息：<font color="warning">{errmsg}</font>
TPL
,
            'pos_o2oundelivery' => <<<TPL
POS现货订单未发货报警
>现货未发货订单号为：<font color="warning">{order_bns}</font>
TPL
,
            'process_undelivery' => <<<TPL
{content}
TPL
,
            'under_safty_inventory' => <<<TPL
<{仓库名称：<font color="warning">{branch_name}</font>，商品编码：<font color="warning">{bn}</font>，商品名称：<font color="warning">{goods_name}</font>，库存数量：<font color="warning">{store}</font>，安全库存：<font color="warning">{safe_store}</font>}> 
TPL
,
            'stock_diff_alarm' => <<<TPL
实物库存差异报警
    >日期：<font color="warning">{stock_date}</font>
    >渠道：<font color="warning">{channel_bn}</font>
    >仓库：<font color="warning">{warehouse_code}</font>
    >错误信息：<font color="warning">{errmsg}</font>
TPL
,
            'stock_sync' => <<<TPL
平台库存同步失败报警
    >日期：<font color="warning">{stock_date}</font>
    >错误信息：<font color="warning">{errmsg}</font>
TPL
,
            'system_message' => <<<TPL
系统消息通知
    >消息内容：<font color="warning">{errmsg}</font>
TPL
,
            'invoice_result_error' => <<<TPL
发票处理失败报警
    >发票操作类型：<font color="warning">{invoice_type}</font>
    >订单号：<font color="warning">{order_bn}</font>
    >错误信息：<font color="warning">{errmsg}</font>
TPL
,
            'store_freeze_abnormal' => <<<TPL
**仓库存冻结差异报警**
    >{errmsg}
TPL
,
            'rpc_accountorders' => <<<TPL
实销实结未同步报警
>未同步订单号为：<font color="warning">{order_bns}</font>
TPL
,
            'order_360buy_delivery_error' => <<<TPL
以下订单挂起不可以发货：
    >订单号：<font color="warning">{order_bn}</font>
TPL
,
            'inventory_calc_error' => <<<TPL
库存计算异常报警
    >时间：<font color="warning">{datetime}</font>
    >商品编码：<font color="warning">{product_bn}</font>
    >店铺ID：<font color="warning">{shop_id}</font>
    >店铺名称：<font color="warning">{shop_name}</font>
    >异常信息：<font color="warning">{error_message}</font>
    >异常位置：<font color="warning">{error_location}</font>
TPL
,
            'sap_sync_error' => <<<TPL
SAP同步异常报警
    >业务：<font color="warning">{title}</font>
    >单据：<font color="warning">{original_bn}</font>
    >同步时间：<font color="warning">{sync_time}</font>
    >同步状态：<font color="warning">{sync_status}</font>
    >接口名：<font color="warning">{method}</font>
    >错误信息：<font color="warning">{errmsg}</font>
TPL
,
            'order_delivery_platform_sync_error' => <<<TPL
订单发货回写平台失败
    >订单号：<font color="warning">{order_bn}</font>
    >发货单号：<font color="warning">{delivery_bn}</font>
    >平台：<font color="warning">{platform}</font>
    >店铺：<font color="warning">{shop_name}</font>
    >错误信息：<font color="warning">{errmsg}</font>
TPL
,
            'delivery_cancel_wms' => <<<TPL
{shop_type}平台{shop_name}店铺发货单号（{delivery_bn}）已撤销，明细如下，请WMS端及时取消出货，谢谢。
    {detail_list}
TPL
,
            'order_lack_notify' => <<<TPL
订单缺货通知
    >订单号：<font color="warning">{order_bn}</font>
    >仓库：<font color="warning">{branch_name}</font>
    >缺货商品数量：<font color="warning">{lack_count}</font>
    >缺货商品详情：
{lack_products}
TPL
,
        );
    }
}
