INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('rpc_warning', 'RPC调用失败报警', 'rpc_warning', 'email', 'RPC调用失败报警
    >业务：<font color=\"warning\">{title}</font>
    >单据：<font color=\"warning\">{bill_bn}</font>
    >接口名：<font color=\"warning\">{method}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-08-13 00:00:00', '2024-08-13 00:00:00');


INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('wms_consign_notify', 'WMS发货失败报警', 'wms_delivery_consign', 'email', 'WMS发货失败报警
    >发货单：<font color=\"warning\">{delivery_bn}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('wms_reship_notify', 'WMS退货失败报警', 'wms_reship_finish', 'email', 'WMS退货失败报警
    >退货单：<font color=\"warning\">{reship_bn}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('wms_stockchange_notify', 'WMS异动失败报警', 'wms_stock_change', 'email', 'WMS异动失败报警
    >单据：<font color=\"warning\">{order_code}</font>
    >类型：<font color=\"warning\">{order_type}</font>
    >批次：<font color=\"warning\">{batch_code}</font>
    >仓库：<font color=\"warning\">{warehouse}</font>
    >物料：<font color=\"warning\">{product_bn}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('wms_stockin_notify', 'WMS入库失败报警', 'wms_stockin_finish', 'email', 'WMS入库失败报警
    >入库单：<font color=\"warning\">{io_bn}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('wms_stockout_notify', 'WMS出库失败报警', 'wms_stockout_finish', 'email', 'WMS出库失败报警
    >入库单：<font color=\"warning\">{io_bn}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('wms_stockprocess_notify', 'WMS加工单确认失败报警', 'wms_stockprocess_confirm', 'email', 'WMS加工单确认失败报警
    >加工单：<font color=\"warning\">{mp_bn}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('wms_transferorder_notify', 'WMS移库失败报警', 'wms_transferorder_finish', 'email', 'WMS移库失败报警
    >移库单：<font color=\"warning\">{stockdump_bn}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('pos_stock_notify', 'POS库存同步通知', 'pos_stock_sync', 'email', 'POS库存同步失败通知
    >门店：<font color=\"warning\">{store_bn}</font>
    >仓库：<font color=\"warning\">{branch_bn}</font>
    >PageNo：<font color=\"warning\">{page_no}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('pos_o2oundelivery_notify', 'POS现货订单未发货报警', 'pos_o2oundelivery', 'email', 'POS现货订单未发货报警
>现货未发货订单号为：<font color=\"warning\">{order_bns}</font>', '1', 'system', '2024-03-07 00:00:00',
        '2024-03-07 00:00:00');

INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('process_undelivery_notify', '未发货订单通知', 'process_undelivery', 'email', '{content}', '1', 'system',
        '2024-03-07 00:00:00', '2024-03-07 00:00:00');


INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('under_safty_inventory_notify', '低于安全库存报警', 'under_safty_inventory', 'email',
        '<{仓库名称：<font color=\"warning\">{branch_name}</font>，商品编码：<font color=\"warning\">{bn}</font>，商品名称：<font color=\"warning\">{goods_name}</font>，库存数量：<font color=\"warning\">{store}</font>，安全库存：<font color=\"warning\">{safe_store}</font>}>',
        '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('stock_diff_alarm', '实物库存差异报警', 'stock_diff_alarm', 'email', '实物库存差异报警
    >日期：<font color=\"warning\">{stock_date}</font>
    >渠道：<font color=\"warning\">{channel_bn}</font>
    >仓库：<font color=\"warning\">{warehouse_code}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('stock_sync_notify', '平台库存同步失败报警', 'stock_sync', 'email', '平台库存同步失败报警
    >日期：<font color=\"warning\">{stock_date}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('system_message_notify', '系统消息通知', 'system_message', 'email', '系统消息通知
    >消息内容：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-03-07 00:00:00', '2024-03-07 00:00:00');

-- 新增发货单取消成功通知模板
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('delivery_cancel_success_notify', '发货单取消成功通知', 'delivery_cancel_success', 'email', '发货单取消成功通知
    >发货单号：<font color=\"warning\">{delivery_bn}</font>
    >仓库：<font color=\"warning\">{branch_name}</font>
    >取消时间：<font color=\"warning\">{cancel_time}</font>
    >原因：<font color=\"warning\">{memo}</font>', '1', 'system', '2024-12-19 00:00:00', '2024-12-19 00:00:00');

-- 新增退货单取消成功通知模板
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('reship_cancel_success_notify', '退货单取消成功通知', 'reship_cancel_success', 'email', '退货单取消成功通知
    >退货单号：<font color=\"warning\">{reship_bn}</font>
    >仓库：<font color=\"warning\">{branch_name}</font>
    >取消时间：<font color=\"warning\">{cancel_time}</font>
    >原因：<font color=\"warning\">{memo}</font>', '1', 'system', '2024-12-19 00:00:00', '2024-12-19 00:00:00');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`,
                                         `source`, `at_time`, `up_time`)
VALUES ('invoice_result_error_notify', '发票处理失败报警', 'invoice_result_error', 'workwx', '发票处理失败报警
    >发票操作类型：<font color=\"warning\">{invoice_type}</font>
    >订单号：<font color=\"warning\">{order_bn}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', '2024-05-08 10:22:23', '2024-05-08 10:22:23');

INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('store_freeze_abnormal', '库存或冻结消费异常报警', 'store_freeze_abnormal', 'workwx', '**仓库存冻结差异报警**
    >{errmsg}', '1', 'system', '2024-10-11 00:00:00', '2024-10-11 00:00:00');

INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('process_accountorders_notify', '实销实结未同步报警', 'rpc_accountorders', 'email', '实销实结未同步报警
>未同步订单号为：<font color=\"warning\">{order_bns}</font>', '1', 'system', '2025-05-09 00:00:00',
        '2025-05-09 00:00:00');

INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`,
                                         `source`, `at_time`, `up_time`)
VALUES ('order_360buy_delivery_error', '【京东】订单挂起不可以发货', 'order_360buy_delivery_error', 'workwx', '以下订单挂起不可以发货：
    >订单号：<font color=\"warning\">{order_bn}</font>', '1', 'system', '2024-05-08 10:22:23', '2024-05-08 10:22:23');

INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`,
                                         `source`, `disabled`, `at_time`, `up_time`)
VALUES ('order_ship_refund_apply', '订单已发货仅退款发起申请', 'order_ship_refund_apply', 'email', '订单[{order_bn}]已发货，不可以退款。退款单号：{refund_apply_bn}，退款金额：{refund_fee}', '1', 'system', 'true', '2025-06-18 10:22:23', '2025-06-18 10:22:23');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`,
                                         `source`, `disabled`, `at_time`, `up_time`)
VALUES ('order_unship_refund_apply', '订单未发货退款发起申请', 'order_unship_refund_apply', 'email', '订单[{order_bn}]未生成发货单，可以退款。退款单号：{refund_apply_bn}，退款金额：{refund_fee}', '1', 'system', 'true', '2025-06-18 10:22:23', '2025-06-18 10:22:23');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`,
                                         `source`, `disabled`, `at_time`, `up_time`)
VALUES ('order_refund_apply_reback_fail', '订单退款发起申请发货单撤销失败', 'order_refund_apply_reback_fail', 'email', '订单[{order_bn}]对应发货单[{delivery_bn}]撤销失败，失败原因：{msg}。退款单号[{refund_apply_bn}]不可退款，退款金额：{refund_fee}', '1', 'system', 'true', '2025-06-18 10:22:23', '2025-06-18 10:22:23');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`,
                                         `source`, `disabled`, `at_time`, `up_time`)
VALUES ('order_part_ship_refund_apply', '订单部分发货仅退款发起申请', 'order_part_ship_refund_apply', 'email', '订单[{order_bn}]对应发货单[{delivery_bn}]发货完成。退款单号[{refund_apply_bn}]不可退款，退款金额：{refund_fee}', '1', 'system', 'true', '2025-06-18 10:22:23', '2025-06-18 10:22:23');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`,
                                         `source`, `disabled`, `at_time`, `up_time`)
VALUES ('order_refund_apply_reback_succ', '退款申请对应发货单均撤回', 'order_refund_apply_reback_succ', 'email', '退款单号[{refund_apply_bn}]对应子单号都已叫回，可退款。退款金额：{refund_fee}，订单号：{order_bn}', '1', 'system', 'true', '2025-06-18 10:22:23', '2025-06-18 10:22:23');
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`,
                                         `source`, `disabled`, `at_time`, `up_time`)
VALUES ('order_refund_apply_force_refund', '退款单强制退款', 'order_refund_apply_force_refund', 'email', '订单[{order_bn}]平台小二已介入强制退款，请尽快联系客户上门取件。', '1', 'system', 'true', '2025-06-18 10:22:23', '2025-06-18 10:22:23');

INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('order_delivery_timeliness', '订单发货时效提醒', 'order_delivery_timeliness', 'email', '订单发货时效提醒
    >订单号：<font color=\"warning\">{order_bn}</font>
    >平台：<font color=\"warning\">{shop_type}</font>
    >支付时间：<font color=\"warning\">{paytime}</font>
    >剩余时间：<font color=\"warning\">{remaining_hours}</font>小时', '1', 'system', '2025-01-20 00:00:00', '2025-01-20 00:00:00');
    -- 库存回写监控模板
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('inventory_calc_error', '库存计算异常报警', 'inventory_calc_error', 'email', '库存计算异常报警
    >时间：<font color=\"warning\">{datetime}</font>
    >商品编码：<font color=\"warning\">{product_bn}</font>
    >店铺ID：<font color=\"warning\">{shop_id}</font>
    >店铺名称：<font color=\"warning\">{shop_name}</font>
    >异常信息：<font color=\"warning\">{error_message}</font>
    >异常位置：<font color=\"warning\">{error_location}</font>
    >规则信息：<font color=\"warning\">{regulation_info}</font>', '1', 'system', '2024-12-19 00:00:00', '2024-12-19 00:00:00');

INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('order_delivery_platform_sync_error', '订单发货回写平台失败', 'order_delivery_platform_sync_error', 'email', '订单发货回写平台失败
    >订单号：<font color=\"warning\">{order_bn}</font>
    >发货单号：<font color=\"warning\">{delivery_bn}</font>
    >平台：<font color=\"warning\">{platform}</font>
    >店铺：<font color=\"warning\">{shop_name}</font>
    >错误信息：<font color=\"warning\">{errmsg}</font>', '1', 'system', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('delivery_cancel_wms_notify', '发货单撤销WMS通知', 'delivery_cancel_wms', 'email', '{shop_type}平台{shop_name}店铺发货单号（{delivery_bn}）已撤销，明细如下，请WMS端及时取消出货，谢谢。
    {detail_list}', '1', 'system', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- 订单缺货通知模板
INSERT INTO `sdb_monitor_event_template`(`template_bn`, `template_name`, `event_type`, `send_type`, `content`, `status`, `source`, `at_time`, `up_time`)
VALUES ('order_lack_notify', '订单缺货通知', 'order_lack_notify', 'email', '订单缺货通知
    >订单号：<font color=\"warning\">{order_bn}</font>
    >仓库：<font color=\"warning\">{branch_name}</font>
    >缺货商品数量：<font color=\"warning\">{lack_count}</font>
    >缺货商品详情：
{lack_products}', '1', 'system', '2024-12-19 00:00:00', '2024-12-19 00:00:00');

