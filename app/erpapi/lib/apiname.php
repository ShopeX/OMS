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

define('TAOBAO_COMMON_TOP_SEND', 'store.common.top.send'); //淘宝透传接口
define('JD_COMMON_TOP_SEND', 'store.commons.send'); //京东透传接口

// 向WMS推送创建发货单通知
define("WMS_SALEORDER_CREATE",'store.wms.saleorder.create');
// 向WMS推送取消发货单通知
define("WMS_SALEORDER_CANCEL",'store.wms.saleorder.cancel');
define("WMS_SALEORDER_CALLBACK", 'store.wms.order.callback'); // 向WMS推送发货单截单通知
// 向WMS推送获取发货单通知
define("WMS_SALEORDER_GET",'store.wms.saleorder.get');
// 向WMS推送添加物料通知
define("WMS_ITEM_ADD",'store.wms.item.add');
// 向WMS推送更新物料通知
define("WMS_ITEM_UPDATE",'store.wms.item.update');
// 向WMS推送添加退换单通知
define("WMS_RETURNORDER_CREATE",'store.wms.returnorder.create');
// 向WMS推送取消退换单通知
define("WMS_RETURNORDER_CANCEL",'store.wms.returnorder.cancel');
// 向WMS推送获取退换单通知
define("WMS_RETURNORDER_GET",'store.wms.returnorder.get');
// 向WMS推送创建转储单通知
define("WMS_TRANSFERORDER_CREATE",'store.wms.transferorder.create');
// 向WMS推送取消转储单通知
define("WMS_TRANSFERORDER_CANCEL",'store.wms.transferorder.cancel');
// 向WMS推送创建入库单通知
define("WMS_INORDER_CREATE",'store.wms.inorder.create');
// 向WMS推送取消入库单通知
define("WMS_INORDER_CANCEL",'store.wms.inorder.cancel');
// 向WMS推送获取入库单通知
define("WMS_INORDER_GET",'store.wms.inorder.get');
// 向WMS推送创建出库单通知
define("WMS_OUTORDER_CREATE",'store.wms.outorder.create');
// 向WMS推送取消出库单通知
define("WMS_OUTORDER_CANCEL",'store.wms.outorder.cancel');
// 向WMS推送获取出库单通知
define("WMS_OUTORDER_GET",'store.wms.outorder.get');
// 向WMS获取物料库存
define("WMS_STOCK_QUERY",'store.wms.stock.query');
// 向WMS推送获取仓库列表
define("WMS_WAREHOUSE_LIST_GET",'store.wms.warehouse.list.get');
// 向WMS推送获取物流公司列表
define("WMS_LOGISTICS_COMPANIES_GET",'store.wms.logistics.companies.get');
// 向WMS推送创建供应商列表
define("WMS_VENDORS_GET",'store.vendors.get');
// 向wms请求新建加工单
define('WMS_STOREPROCESS_CREATE', 'store.wms.storeprocess.create');
//付尾款通知

define('WMS_PRESALES_PACKAGE_CONSIGN', 'store.wms.presalespackage.consign');

// BMS
define('WMS_BMS_ORDER_CREATE', 'store.bms.order.create');                       // 创建菜鸟BMS订单
define('WMS_BMS_SNINFO_QUERY', 'store.wlb.wms.sninfo.query');                   // 查询单据序列号信息
define('WMS_BMS_STOCKIN_BILL_GET', 'store.wlb.wms.stockin.bill.get');                   // 获取入库收货信息
define('WMS_BMS_STOCKOUT_BILL_GET', 'store.wlb.wms.stockout.bill.get');              // 获取出库发货信息
define('WMS_BMS_RETURN_BILL_GET', 'store.wlb.wms.return.bill.get');                // 获取销退收货信息
define('WMS_BMS_INVENTORY_PROFITLOSS_GET', 'store.wlb.wms.inventory.profitloss.get');       // 获取盘点信息
define('WMS_BMS_BILL_QUERY', 'store.wlb.wms.bill.query');                           // 查询全部已完成单据列表

// 菜鸟保税
define('WMS_ITEM_INVENTORY_QUERY','store.wms.item.inventory.query');                       // WMS库存查询
define('WMS_TRADEORDER_CONSIGN','store.wms.tradeorder.consign');                       // 驱动保税发货
define('WMS_ITEM_GET','store.wms.item.get');

define('WMS_ITEM_PRICE_GET','store.item.sku.price.get');                                   // 获取价格
define('WMS_ITEM_DETAIL_GET','store.wms.item.detail.get');                                   // 获取商品详情

define('WMS_CATEGORIES_FIRST_LEVEL_GET', 'store.categories.first.level.get');
define('WMS_CATEGORIES_SECODE_LEVEL_GET', 'store.categories.second.level.get');
define('WMS_CATEGORIES_THIRD_LEVEL_GET', 'store.categories.third.level.get');
define('WMS_AREA_ADDRESS_GET','store.wms.area.address.get');
define('WMS_AREA_ADDRESS_EXCHANGE','store.wms.area.address.exchange');
define("WMS_LOGISTICS_CREATE", 'store.wms.carrier.create'); // 向WMS推送创建物流公司
define("WMS_SHOP_UPDATE", 'store.wms.update.shop');
define("WMS_VENDORS_UPDATE", 'store.wms.modify.supplier');


// 单句取消
define("WMS_ORDER_CANCEL",'store.wms.order.cancel');
define("WMS_SHOP_CREATE",'store.wms.add.shop');                                            // 向WMS推送创建店铺
define('SHOP_LOGISTICS_PUB','store.trade.pub.appreciation');                                     // 华强宝订阅
//define('SHOP_LOGISTICS_BIND','erp.logistics.bind');                                     // ERP自己定义的
define('SHOP_PRINT_THIRD_BILL', 'store.print.invoice');                                 //唯品会打印三单
define('SHOP_GET_DLY_INFO', 'store.get.delivery.info');                                 //唯品会获取发货单信息
define('SHOP_REFUND_CHECK', 'store.refund.check');                                      //退款审核接口
define('SHOP_OUT_BRANCH', 'store.dropship.dps.outbound');                               //货品出库接口
//短信平台接口
define('SMS_SERVER_TIME', 'erp.sms.server.time');                                       //获取短信平台时间
define('SMS_USER_INFO', 'erp.sms.user.info');                                           //获取短信平台用户信息
define('SMS_NEW_OAUTH', 'erp.sms.api.addcontent.new');                                  //验证短信签名
define('SMS_SEND_TMPL', 'erp.sms.api.sms-tpl.sendByTmpl');                              //根据模板发送短信

//韵达电子面单获取接口
define('STORE_YD_ORDERSERVICE', 'store.yd.orderservice');
//申通电子面单获取接口
define('STORE_WAYBILL_MAILNO_GET', 'store.waybill.mailno.get');
//申通大头笔获取接口
define('STORE_WAYBILL_DATA_ADD', 'store.waybill.data.add');
//EMS电子面单获取接口
define('STORE_WAYBILLPRINTDATA_GET', 'store.waybillprintdata.get');
//EMS电子面单物流回传接口
define('STORE_PRINT_DATA_CREATE', 'store.print.data.create');
//京东电子面单获取接口
define('STORE_ETMS_WAYBILLCODE_GET', 'store.etms.waybillcode.get');
//京东电子面单物流回传接口
define('STORE_ETMS_WAYBILL_SEND', 'store.etms.waybill.send');
//京东判断是否京配
define('STORE_ETMS_RANGE_CHECK', 'store.etms.range.check');
// 京东更新包裹数
define('STORE_ETMS_PACKAGE_UPDATE', 'store.etms.package.update');
//京东获取签约地址
define('STORE_ETMS_SIGNSUCCESS_INFO', 'store.trade.signsuccess.info.get');
//快递鸟电子面单获取接口
define('STORE_HQEPAY_ORDERSERVICE', 'store.waybill.search');
//顺丰电子面单获取接口
define('STORE_SF_ORDERSERVICE', 'store.sf.orderservice');
//顺丰电子面单检索接口
define('STORE_SF_ORDERSEARCHSERVICE', 'store.sf.ordersearchservice');
//顺丰access_tocken
define('STORE_SF_ACCESS_TOKEN', 'store.sfapi.gettoken');
//淘宝云栈电子面单获取接口
define('STORE_WLB_WAYBILL_I_GET', 'store.wlb.waybill.i.get');
//淘宝云栈电子面单物流回传
define('STORE_WLB_WAYBILL_PRINT', 'store.wlb.waybill.print');

define('STORE_WLB_WAYBILL_CANCEL','store.wlb.waybill.cancel');
//抖音电子面单
define('STORE_WAYBILL_ADRESS', 'store.waybill.i.search'); //抖音订购地址
define('STORE_WAYBILL_GET', 'store.waybill.i.get'); //电子面单获取
define('STORE_STANDARD_DY_TEMPLATE', 'store.waybill.template.get'); //抖音获取模板
define('STORE_WAYBILL_PRINTDATA', 'store.waybill.printdata'); //抖音获取打印数据

// 小红书电子面单
define('STORE_STANDARD_XHS_TEMPLATE', 'store.waybill.stdtemplates.get'); //小红书获取模板
define('STORE_STANDARD_XHS_SEARCH', 'store.waybill.search'); //小红书查询已开通的快递服务

// 微信视频号电子面单
define('STORE_WAYBILL_USER_TEMPLATE', 'store.waybill.mystdtemplates.get'); //电子面单自定义模板列表
define('STORE_WAYBILL_PRE_GET', 'store.waybill.i.pre.get'); //电子面单预取号

//快手电子面单
define('STORE_WAYBILL_KS_GET', 'open.express.ebill.get'); //快手电子面单获取抖
define('STORE_KS_USER_TEMPLATE', 'store.waybill.search'); //快手获取所有标准电子面单模板
define('STORE_KS_ADDRESS', 'store.express.subscribe.query'); //快手获取订购地址
define('STORE_KS_WAYBILL_GET', 'store.express.ebill.get'); //快手获取电子面单
define('STORE_KS_WAYBILL_CANCEL', 'store.express.ebill.cancel'); //快手取消电子面单
define('STORE_KUAISHOU_TECHUAN', 'store.kuaishou.common'); //快手获取所有标准电子面单模板
define('STORE_KS_SUB_RETURNINFO', 'store.rp.returngoods.refill'); // 快手商家代客填写退货单号

define('STORE_WAYBILL_STANDARD_TEMPLATE', 'store.waybill.stdtemplates.get');           //获取标准模板接口
define('STORE_WAYBILL_PRINT', 'store.waybill.print');                                  //获取获取电子面单
define('STORE_WAYBILL_CANCEL', 'store.waybill.cancel');                                //获取取消电子面单
define('STORE_USER_DEFINE_TEMPLATE', 'store.cn.customares.get');                       //获取用户自定义部分接口
define('STORE_USER_DEFINE_AREA', 'store.waybill.customares.get');                      //获取用户自定义部分接口
//电子发票开票（开蓝和冲红）
define("EINVOICE_CREATEREQ",'store.einvoice.createreq');
//电子发票开票后获取开票结果
define("EINVOICE_CREATE_RESULT_GET",'store.einvoice.create.result.get');
//电子发票回流天猫
define("EINVOICE_DETAIL_UPLOAD",'store.einvoice.detail.upload');
//获取电子发票url地址
define("EINVOICE_URL_GET",'store.einvoice.url.get');
//查询已回传淘宝的电子发票
define("EINVOICE_INVOICE_GET",'store.einvoice.invoice.get');
//电子发票状态更新
define("EINVOICE_INVOICE_PREPARE",'store.einvoice.invoice.prepare');
//电子发票开票申请数据获取接口
define("EINVOICE_APPLY_GET",'store.einvoice.apply.get');
//电子发票订阅消息添加分组
define("EINVOICE_ADD_TMC_GROUP",'store.tmc.group.add');
//电子发票订阅消息删除分组
define("EINVOICE_DEL_TMC_GROUP",'store.tmc.group.delete');

// 金税电子
define('STORE_JINSHUI_INVOICE_FILE_CREATE', 'store.jinshui.invoice.file.create');
define('STORE_JINSHUI_INVOICE_RESULT_QUERY', 'store.jinshui.invoice.result.query');
define('STORE_JINSHUI_INVOICE_QUERY', 'store.jinshui.invoice.query');

// 百望电子
define('STORE_BW_INVOICE_REQUEST','store.bw.invoice.request');#电子发票开蓝或冲红 
define('STORE_BW_INVOICE_FILE_GET','store.bw.invoice.file.get');#
define('STORE_BW_INVOICE_FILE_CREATE','store.bw.invoice.file.create');#
define('STORE_BW_EINVOICE_FORMAT_CREATE','store.einvoice.format.create');#金四板式文件生成

// 银联金税
define('STORE_INVOICE_ISSUE', 'store.invoice.issue');                       //电子发票开蓝或冲红
define("INVOICE_REVERSE_APPLICATION_CREATE", 'store.einvoice.red.add');     //数电专票红冲申请单
define("INVOICE_RED_APPLY_QUERY", 'store.einvoice.red.formdetail');         //数电专票红冲申请单
define("INVOICE_HEARTBEAT", 'store.einvoice.basicinfo.confirmFaceSwiping'); // 开票心跳检查

define('STORE_WLB_WAYBILL_I_SEARCH','store.wlb.waybill.i.search');
define('STORE_TRADE_CANCEL','store.trade.cancel');

define('STORE_JITX_WAYBILL_GET', 'store.jitx.get.delivery.info');                      //获取运单号服务
define('STORE_JITX_WAYBILL_PRINT', 'store.jitx.waybill.search');                       //生成并且返回面单

define('STORE_LOGISTICS_TRACE_GET','store.logistics.trace.get');                        //银联物流跟踪接口

define('STORE_CN_RULE', 'store.cn.rule.sync');                                        //菜鸟流转订单处理规则同步
define('STORE_CN_SMARTDELIVERY_GET', 'store.smartdelivery.i.get');                      //智选物流获取推荐物流
define('STORE_CN_SMARTDELIVERY_ISSUBSCRIBE', 'store.smartdelivery.is.subscribe');       //检测商家是否订购智能发货服务
define('STORE_CN_SMARTDELIVERY_STRATEGY_QUERY', 'store.smartdelivery.strategy.query');  //智选物流仓维度策略查询
define('STORE_CN_SMARTDELIVERY_STRATEGY_DELETE', 'store.smartdelivery.strategy.delete');  //智选物流仓维度策略查询
define('STORE_CN_SMARTDELIVERY_STRATEGY_UPDATE', 'store.smartdelivery.strategy.update');  //智能发货引擎发货策略设置仓维度
define('STORE_CN_SMARTDELIVERY_CPQUERY', 'store.smartdelivery.cp.query');               //智能发货引擎支持的合作物流公司
define('STORE_CN_SMARTDELIVERY_PRICEOFFERQUERY', 'store.smartdelivery.priceoffer.query');

// 店铺接口
define('SHOP_ORDER_SPLIT','store.order.split');                     // 主动拆单
define('SHOP_LOGISTICS_ONLINE_SEND','store.logistics.online.send');                     // 线上物流
define('SHOP_LOGISTICS_OFFLINE_SEND','store.logistics.offline.send');                   // 线下物流
define('SHOP_LOGISTICS_OFFICIAL_PICKUP','store.logistics.official.pickup');             // 官方物流提货
define('SHOP_LOGISTICS_RECOMMEND', 'store.logistics.available.company.recommend'); // 推荐物流
define('SHOP_TRADE_SHIPPING_ADD','store.trade.shipping.add');                           // 添加发货单
define('SHOP_TRADE_SHIPPING_STATUS_UPDATE','store.trade.shipping.status.update');       // 更新发货单状态
define('SHOP_TRADE_OPERATION_IN_WAREHOUSE','store.trade.operation.in.warehouse');       // 库存作业操作状态更新
define('SHOP_TRADE_SHIPPING_UPDATE','store.trade.shipping.update');                     // 更新物流信息
define('SHOP_WLB_ORDER_JZPARTNER_QUERY','store.wlb.order.jzpartner.query');             // 家装服务商
define('SHOP_WLB_ORDER_JZWITHINS_CONSIGN','store.wlb.order.jzwithins.consign');         // 家装发货
define('SHOP_WLB_THREEPL_OFFLINE_SEND','store.wlb.threepl.offline.send');               //天猫国际发货
define('SHOP_WLB_THREEPL_RESOUCE_GET','store.wlb.threepl.resource.get');                //3PL直邮线下发货
define('SHOP_TRADE_OUTSTORAGE','store.trade.outstorage');                               // 出库操作
define("SHOP_LOGISTICS_CONSIGN_RESEND", 'store.logistics.consign.resend');              // 修改配送信息
define("SHOP_LOGISTICS_RESEND_CONFIRM", 'store.logistics.resend.confirm');              // vjia发货确认
define("SHOP_TMC_MESSAGE_PRODUCE", "store.tmc.message.produce");                        // 淘宝全链路接口
define("SHOP_UPLOAD_ORDER_RECORD", "store.upload.order.record");                        // 抖音订单链路监控接口
define('SHOP_LOGISTICS_DUMMY_SEND','store.logistics.dummy.send');                       // 无需物流（虚拟）发货处理
define('SHOP_REFUND_DETAIL_GET','store.refund.detail.get');                             // 退款详情按钮
define('SHOP_REFUND_INTERCEPT','store.rp.refund.intercept');                            // 退款发起拦截
define('SHOP_REFUND_NEGOTIATERETURN_RENDER','store.refund.negotiatereturn.render');     // 协商退货退款详情页
define('SHOP_REFUND_NEGOTIATERETURN','store.refund.negotiatereturn');                   // 协商退货退款
define('SHOP_REFUND_NEGOTIATE_CANAPPLY_GET','store.refund.negotiation.canapply');       // 查询是否可发起协商
define('SHOP_REFUND_NEGOTIATION_CREATE', 'store.refund.negotiation.create');            //协商退货退款接口
define('SHOP_REFUND_NOTIFY_GET','store.tmc.refundcreated.get');                         // 退款消息
define('SHOP_REFUND_STATUS_GET','store.refund.status.get');                             // 退款状态查询
define('SHOP_COMPENSATE_REFUND_GET','store.special.refunds.receive.get');               // 赔付单获取
define('SHOP_TRADE_REFUND_GET', 'store.trade.refund.get');                             // 查询售后单详情

define('SHOP_LOGISTICS_SUBSCRIBE','store.trade.pub.appreciation');                                    // 增强版华强宝订阅（订单分发的信息：分给了的网点，业务员的信息）
define('SHOP_LOGISTICS_BIND','erp.logistics.bind');                                     // ERP自己定义的
define("SHOP_PAYMETHOD_RPC",'store.shop.payment_type.list.get');                        // 获取支付方式
define('SHOP_LOGISTICS_ADDRESS_SEARCH','store.logistics.address.search');               //卖家地址库
define("SHOP_TRADE_FULLINFO_RPC",'store.trade.fullinfo.get');                          // 单拉订单接口
define("SHOP_FENXIAO_TRADE_FULLINFO_RPC", 'store.fenxiao.trade.fullinfo.get');         // 淘分销单拉
define("SHOP_BILL_BOOK_BILL_GET", 'store.bill.book.bills.get');                         // 获取虚拟账户明细数据
define("SHOP_BILL_BILL_GET", 'store.bills.get');                                        //获取费用明细
define("SHOP_USER_TRADE_SEARCH", 'store.user.trade.search');                            //支付宝交易记录实时获取
define("SHOP_TOPATS_RESULT_GET", 'store.topats.result.get');                           //支付宝交易任务号结果获取
define("SHOP_TOPATS_USER_ACCOUNTREPORT_GET", 'store.topats.user.accountreport.get');    //支付宝交易任务号获取
define("SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC",'store.items.quantity.list.update');       // 更新库存 直销
define("SHOP_UPDATE_FENXIAO_ITEMS_QUANTITY_LIST_RPC",'store.fenxiao.items.quantity.list.update'); // 更新库存 分销
define("SHOP_GET_DANGDANG_SHOP_CATEGORYLIST", "store.shop.getstorecategory.get");       //获取当当网类目
define("SHOP_UPDATE_DANGDANG_QUANTITY_LIST_RPC",'store.books.quantity.list.update');    //当当网库存更新
define("SHOP_ADD_PAYMENT_RPC",'store.trade.payment.add');                               //添加支付单
define("SHOP_ADD_AFTERSALE_RPC",'store.trade.aftersale.add');                           //添加售后申请单
define("SHOP_UPDATE_AFTERSALE_STATUS_RPC",'store.trade.aftersale.status.update');       //自由体系更新售后申请状态
define('SHOP_CHECK_REFUND_GOOD','store.refund.good.return.check');                      //确认退货
define("SHOP_MEILISHUO_REFUND_GOOD_RETURN_AGREE", "store.refund.good.return.agree");    //美丽说同意退款、退货（退款退货用的同一个接口）
define('SHOP_AGREE_RETURN_GOOD','store.refund.good.return.agree');                      //同意退货
define('SHOP_REFUSE_REFUND','store.refund.refuse');                                     //拒绝退款单
define('SHOP_RESHIP_AUDIT', 'store.order.brand.deliver.audit.return');                  //退货审核
define('SHOP_AGREE_RETURN_I_GOOD_TMALL','store.tmall.refund.i.good.return.agree');      //同意退货
define('SHOP_REFUND_NEGOTIATION_GET','store.refund.negotiation.get');                   //获取协商详情
define('SHOP_REFUSE_RETURN_I_GOOD_TMALL','store.tmall.refund.i.good.return.refuse');    //拒绝退货
define('SHOP_REFUSE_RETURN_GOOD','store.refund.good.return.refuse');                    //拒绝退货
define("SHOP_ADD_REFUND_RPC",'store.trade.refund.add');                                 // 添加退款单
define("SHOP_ADD_RESHIP_RPC",'store.trade.reship.add');                                 // 添加退货单
define("SHOP_RETURN_GOOD_SIGN", 'store.order.brand.deliver.seller.confirm');            // 签收
define("SHOP_RETURN_GOOD_CHECK", 'store.order.brand.deliver.check.return');             // 质检
define("SHOP_RETURN_GOOD_CONFIRM", 'store.return.good.confirm'); // 确认收货
define("SHOP_UPDATE_RESHIP_STATUS_RPC",'store.trade.reship.status.update');             // 更改退货单状态(未使用)
define("SHOP_UPDATE_PAYMENT_STATUS_RPC",'store.trade.payment.status.update');           //更改支付状态(未使用)
define("SHOP_UPDATE_REFUND_STATUS_RPC",'store.trade.refund.status.update');             // 更改退款单状态(未使用)
define("SHOP_IFRAME_TRADE_EDIT_RPC",'iframe.tradeEdit');                                // 订单编辑
define("SHOP_UPDATE_TRADE_RPC",'store.trade.update');                                   // 订单更新
define("SHOP_UPDATE_TRADE_STATUS_RPC",'store.trade.status.update');                     // 订单状态更新
define("SHOP_UPDATE_TRADE_TAX_RPC",'store.trade.tax.update');                           // 更新订单发票信息(未使用)
define("SHOP_UPDATE_TRADE_SHIP_STATUS_RPC",'store.trade.ship_status.update');           // 更新订单发货状态(未使用)
define("SHOP_UPDATE_TRADE_PAY_STATUS_RPC",'store.trade.pay_status.update');             // 更新订单支付状态(未使用)
define("SHOP_UPDATE_TRADE_MEMO_RPC",'store.trade.memo.update');                         // 更新订单备注
define("SHOP_ADD_TRADE_MEMO_RPC",'store.trade.memo.add');                               // 添加订单备注(未使用)
define("SHOP_ADD_TRADE_BUYER_MESSAGE_RPC",'store.trade.buyer_message.add');             // 添加客户备注
define("SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC",'store.trade.shippingaddress.update');  // 更新交易收货人信息
define("SHOP_CONFIRM_ADDRESS_MODIFY", 'store.trade.address.modify.confirm');            // 买家修改地址确认
define("SHOP_UPDATE_TRADE_SHIPPER_RPC",'store.trade.shipper.update');                   // 更新发货人信息(未使用)
define("SHOP_UPDATE_TRADE_SELLING_AGENT_RPC",'store.trade.selling_agent.update');       // 更新代销人信息(未使用)
define("SHOP_UPDATE_TRADE_ORDER_LIMITTIME_RPC",'store.trade.order_limit_time.update');  // 更新订单失效时间(未使用)
define("SHOP_GET_TRADES_SOLD_RPC",'store.trades.sold.get');                             // 拉取某个时间段的订单
define("SHOP_SERIALNUMBER_UPDATE",'store.serial.number.update');                        // 更新唯一码
define("SHOP_UPDATE_TRADE_ITEM_FREEZSTORE_RPC",'store.trade.item.freezstore.update');   // 更新预占(未使用)
define("SHOP_GET_ITEMS_ALL_RPC", 'store.items.all.get');                                // 获取前端商品
define("SHOP_GET_ITEMS_LIST_RPC", 'store.items.list.get');                              // 通过IID获取多个前端商品
define("SHOP_GET_FENXIAO_PRODUCTS", 'store.fenxiao.products.get');                      // 淘分销商品下载
define("SHOP_GET_SUPPLIER_PRODUCTS", 'store.supplier.item.list.info.query');            // 供应商商品下载
define("SHOP_ITEM_GET",'store.item.get');                                               // 通过IID获取单个商品
define("SHOP_ITEM_SKU_GET",'store.item.sku.get');                                       // 单拉商品SKU
define("SHOP_ORDER_SETTLE_GET",'store.trade.settle.get');                               //获取抖音订单明细
define("SHOP_ITEM_SKU_LIST",'store.item.sku.list');                               //获取快手sku库存明细
define("SHOP_UPDATE_FENXIAO_PRODUCT", 'store.fenxiao.product.update');                  // 淘分销单个商品上下架更新
define("SHOP_UPDATE_ITEM_APPROVE_STATUS_RPC", 'store.item.approve_status.update');      // 单个商品上下架更新
define("SHOP_UPDATE_ITEM_APPROVE_STATUS_LIST_RPC", 'store.item.approve_status_list.update'); // 批量上下架
define("SHOP_GET_TRADE_INVOICE_RPC", 'store.trade.invoice.get');                        // 获取发票抬头
define("SHOP_EINVOICE_APPLY_GET", 'store.einvoice.apply.get');                        // 获取发票信息
define("SHOP_INVOICE_QUERY",'store.invoice.query');                                     //发票信息
define("SHOP_EINVOICE_QUERY",'store.einvoice.query');                                     //发票信息 仅包含金4.0专普票。
define("SHOP_INVOICE_STATUS_UPDATE",'store.invoice.status.update');                     //发票上传或者发票更新
define('SHOP_GET_REFUND_MESSAGE','store.refund.message.get');                           //退款凭证获取
define('SHOP_GET_REFUND_I_MESSAGE_TMALL','store.tmall.refund.i.message.get');           //天猫获取退款凭证
define('SHOP_ADD_REFUND_MESSAGE','store.refund.message.add');                           //回写退款留言和凭证
define('SHOP_REFUND_GOOD_RETURN_CHECK','store.eai.order.refund.good.return.check');     //回填退货物流信息(未使用)
define('SHOP_GET_TRADE_REFUND_RPC','store.eai.order.refund.get');                       //单拉退款单信息
define('SHOP_GET_TRADE_REFUND_I_RPC','store.eai.order.refund.i.get');                   //天猫单拉退款单信息
define('SHOP_REFUNSE_REFUND_I_TMALL','store.tmall.refund.i.refuse');                    //天猫获取拒绝退款
define('SHOP_AGREE_REFUND_I_TMALL','store.tmall.trade.refund.i.examine');               //天猫同意退款
define("SHOP_GET_ITEMS_CUSTOM", 'store.items.custom.get');                              //获取商品信息
define("SHOP_GET_CLOUD_STACK_PRINT_TAG","store.wlb.waybill.i.distributeinfo");          //获取云栈大头笔
define('SHOP_GET_ORDER_STATUS', 'store.trade.status.get');                              //获取订单状态
define('SHOP_GET_TRADE_INVOICE_SUMMARY', 'store.trade.invoice.summary.get');            //获取订单发票汇总信息
define('SHOP_SUPPLIER_ORDER_CONFIRM', 'store.supplier.consignorder.notify.received');   //接单回传
define('SHOP_SUPPLIER_ORDER_LACK_APPLY', 'store.supplier.consignorder.outofstock.callback');   //缺货申请
define('SHOP_SUPPLIER_ORDER_REJECT_APPLY', 'store.supplier.reverseorder.create');   //拒收创建
define('SHOP_SUPPLIER_ORDER_CANCEL_BACK', 'store.supplier.consignorder.cancel.feedback');   //取消回传
define('SHOP_SUPPLIER_RETURN_GOOD_CONFIRM', 'store.supplier.reverseorder.instorage.feedback');   //确认收货
define('SHOP_SUPPLIER_RETURN_GOOD_INSTORAGE_RESULT', 'store.supplier.reverseorder.instorage.result');   //逆向订单入库结果
define('SHOP_EXCHANGE_NOTIFY','store.exchange.result.notify');//PUBLICB2C售后更新
define('SHOP_COMMONS_VOP_JIT', 'store.commons.jit.send');                               //唯品会JIT通用接口
define('SHOP_VOP_DOWNLOAD', 'store.jit.download');                               //唯品会JIT下载文件
define('SHOP_VOP_INVENTORY', 'store.get.inventoryoccupiedorders');               //唯品会JIT实时销售订单查询
define('SHOP_VOP_FEEFBACK', 'store.get.occupiedorderfeedback');                  //唯品会JIT时效订单结果反馈
define('SHOP_BRANCH_FEEDBACK', 'store.jitx.delivery.result.feedback');     
define('SHOP_JITX_WAREHOUSES_GET', 'store.jitx.warehouses.get');                 //唯品会获取可用JITX仓库配置
define('SHOP_GET_COOPERATIONNOLIST', 'store.get.cooperationnolist');			 //获取合作编码信息接口

define('SHOP_COMMONS_VOP_BILL','store.commons.send');
define('SHOP_BILL_LIST_GET', 'store.bill.list.get');
define('SHOP_BILL_DETAIL_GET', 'store.bill.detail.get');

define("SHOP_AG_SEND_CANCEL",'store.ag.sendgoods.cancel');                              // 更改退款单状态

define('SHOP_GET_ADDRESS_PROVINCE','store.address.province.get');   //店铺省获取
define('STORE_ADDRESS_GETBY_PROVINCE','store.address.getby.province');   //根据省级id获取市区街道
define('SHOP_BIND_WAREHOUSE_ADDR','store.warehouse.addr.bind');    //区域仓绑定地址
define('SHOP_UNBIND_WAREHOUSE_ADDR','store.warehouse.addr.unbind'); //区域仓解绑
define('SHOP_CREATE_WAREHOUSE','store.warehouse.create');//区域仓创建
define('SHOP_EDIT_WAREHOUSE','store.warehouse.edit');//区域仓编辑
define('SHOP_SET_WAREHOUSE_PRIORITY','store.warehouse.priority.set');//地址下的仓的优先级

define('SHOP_ITEM_STOCK_GET','store.item.stock.get');
define('SHOP_REFUND_LIST_SEARCH', 'store.refund.list.search');

//菜鸟电子面单取消接口
define('STORE_CN_WAYBILL_II_SEARCH', 'store.cn.waybill.ii.search');
define('STORE_CAINIAO_WAYBILL_I_GET', 'store.cn.waybill.ii.get');                      //菜鸟获取电子面单
define('STORE_CAINIAO_WAYBILL_CANCEL', 'store.cn.waybill.ii.cancel');                  //菜鸟电子面单取消接口
define('STORE_STANDARD_TEMPLATE', 'store.cn.stdtemplates.get');                        //获取标准模板接口
define('STORE_USER_TEMPLATE', 'store.cn.mystdtemplates.get');                          //获取用户模板接口
// define('STORE_USER_DEFINE_TEMPLATE', 'store.cn.customares.get');

//品骏电子面单接口
define('STORE_PJ_WAYBILL_II_GET','store.pj.waybill.ii.get');
define('STORE_PJ_WAYBILI_CANCEL','store.pj.waybill.ii.cancel');

//淘宝o2o全渠道
//门店新增接口
define("QIMEN_STORE_CREATE",'taobao.qimen.store.create');
//查询门店主营类目信息接口
define("QIMEN_STORECATEGORY_GET",'taobao.qimen.storecategory.get');
//更新门店信息接口
define("QIMEN_STORE_UPDATE",'taobao.qimen.store.update');
//删除线下门店数据接口
define("QIMEN_STORE_DELETE",'taobao.qimen.store.delete');
//查询门店信息接口
define("QIMEN_STORE_QUERY",'taobao.qimen.store.query');
//新建/删除商品和门店的绑定关系
define("QIMEN_ITEMSTORE_BANDING",'taobao.qimen.itemstore.banding');
//查询线上商品所关联的门店列表
define("QIMEN_ITEMSTORE_QUERY",'taobao.qimen.itemstore.query');
//查询门店所关联的线上商品列表
define("QIMEN_STOREITEM_QUERY",'taobao.qimen.storeitem.query');
//货品映射关系
define("SCITEM_ADD",'taobao.scitem.add');
//修改后端商品
define("SCITEM_UPDATE",'taobao.scitem.update');
//查询后端商品
define("SCITEM_QUERY",'taobao.scitem.query');
//宝贝和货品的关联
define("SCITEM_MAP_ADD",'taobao.scitem.map.add');
//解绑指定用户的商品与后端商品的映射关系
define("SCITEM_MAP_DELETE",'taobao.scitem.map.delete');
//全量更新电商仓或门店库存
define("QIMEN_STOREINVENTORY_ITEMINITIAL",'taobao.qimen.storeinventory.iteminitial');
//增量更新门店或电商仓库存
define("QIMEN_STOREINVENTORY_ITEMUPDATE",'taobao.qimen.storeinventory.itemupdate');
//查询门店或电商仓库存
define("QIMEN_STOREINVENTORY_ITEMQUERY",'taobao.qimen.storeinventory.itemquery');
//调整所占用的门店/电商仓库存
define("QIMEN_STOREINVENTORY_ITEMADJUST",'taobao.qimen.storeinventory.itemadjust');
//ISV同步派单结果到星盘
define("OMNIORDER_ALLOCATEDINFO_SYNC",'taobao.omniorder.allocatedinfo.sync');
//门店接单（店掌柜）
define("QIMEN_OMNIORDER_ACCPETED",'taobao.qimen.omniorder.accpeted');
//门店拒单（店掌柜）
define("QIMEN_OMNIORDER_STORE_REFUSE",'taobao.qimen.omniorder.store.refuse');
//门店发货（店掌柜）
define("QIMEN_OMNIORDER_STORE_CONSIGNED",'taobao.qimen.omniorder.store.consigned');

// 安全
define('HCHSAFE_UPLOAD_COMPUTE_RISK','store.upload.compute.risk');                          // 淘宝风控
define('HCHSAFE_UPLOAD_LOGIN_LOG','store.upload.login.log');
define('HCHSAFE_UPLOAD_ORDER_LOG','store.upload.order.log');
define('HCHSAFE_UPLOAD_SQL_LOG','store.upload.sql.log');
define('HCHSAFE_JD_AUTH_LOGIN_LOG','shopex.jd.user.unified.authentication');               //京东风控

define('HCHSAFE_UPLOAD_ORDERSEND_LOG','store.upload.ordersend.log');
define('HCHSAFE_YCH_VERIFY_PASSED','store.ych.verify.passed');
define('HCHSAFE_YCH_GET_VERIFYURL','store.ych.get.verifyurl');
define('HCHSAFE_YCH_RESET_RISK','store.ych.reset.risk');
define('HCHSAFE_VERIFY_URL','store.ych.get.verifyurl');                                     // 淘宝获取二次验证url
define('HCHSAFE_VERIFY_PASSED','store.ych.verify.passed');
define('SHOP_WLB_ORDER_JZ_QUERY','store.wlb.order.jz.query');             // 新家装服务商
define('SHOP_WLB_ORDER_JZ_CONSIGN','store.wlb.order.jz.consign');         //新 家装发货

define('HCHSAFE_JD_ADDISVLOG','store.isv.addisvlog');//京东风控登录
define('STORE_JD_NPS_TOKEN', 'store.npsapi.gettoken'); //京东NPS获取加密Token

define('STORE_AG_SENDGOODS_CANCEL','store.ag.sendgoods.cancel');                                              //淘宝AG未发货仅退款取消发货
define('STORE_AG_LOGISTICS_WAREHOUSE_UPDATE','store.ag.logistics.warehouse.update');               //淘宝AG售后退货入仓状态回传

// 快递鸟
define("LOGISTICS_TRACE_DETAIL_GET",'store.logistics.trace.detail.get');

define('SHOP_AGREE_CHANGE_I_GOOD_TMALL','store.exchange.agree');//天猫同意退货
define('SHOP_REFUSE_CHANGE_I_GOOD_TMALL','store.exchange.refuse');//天猫拒绝退货
define('SHOP_EXCHANGE_CONSIGNGOODS','store.exchange.consigngoods');//卖家发货
define('SHOP_EXCHANGE_CONFIRMCONSIGN','store.exchange.confirm.consign');//卖家确认收货&&卖家发货
define('SHOP_EXCHANGE_MESSAGE_ADD','store.exchange.message.add');//卖家创建换货留言
define('SHOP_EXCHANGE_MESSAGE_GET','store.exchange.messages.get');//查询换货订单留言列表

define('SHOP_EXCHANGE_RETURNGOODS_AGREE','store.exchange.returngoods.agree');//卖家确认收货
define('SHOP_EXCHANGE_RETURNGOODS_REFUSE','store.exchange.returngoods.refuse');//卖家拒绝确认收货
define('SHOP_EXCHANGE_REFUSEREASON_GET','store.exchange.refusereason.get');//获取拒绝换货原因列表
define('SHOP_EXCHANGE_GET','store.exchange.get');

// 补寄相关接口
define('SHOP_RESHIPPING_GET','store.reshipping.get');//查询补寄详情
define('SHOP_RESHIPPING_MESSAGES_GET','store.reshipping.message.get');//查询补寄留言列表
define('SHOP_RESHIPPING_MESSAGE_ADD','store.reshipping.message.add');//创建补寄留言
define('SHOP_RESHIPPING_AGREE','store.reshipping.agree');//同意补寄申请
define('SHOP_RESHIPPING_REFUSE','store.reshipping.refuse');//拒绝补寄申请
define('SHOP_RESHIPPING_CONSIGNGOODS','store.reshipping.consigngoods');//补寄发货
define('SHOP_RESHIPPING_REFUSEREASON_GET','store.reshipping.refusereason.get');//查询拒绝原因列表

define('STORE_LOGISTICS_JDALPHA_WAYBILL_RECEIVE','store.trade.waybill.receive');        //京东接单接口
define('STORE_LOGISTICS_JDALPHA_WAYBILL_UNBIND','store.trade.waybill.unbind');          //京东解除绑定关系接口
define('STROE_LOGISTICS_JDALPHA_WAYBILL_BIGSHOP_QUERY','store.trade.vendor.bigshot.query'); //京东大头笔
define('STORE_LOGISTICS_DUMMY_SEND','store.logistics.dummy.send');//京东自发货
define('STORE_TRADE_COUPONDETAIL_GET', 'store.trade.coupondetail.get'); //京东订单优惠明细数据


define("SHOP_GET_ITEMS_VALID_RPC", 'store.items.valid.get');                                // JD获取前端商品
define("SHOP_ITEM_I_GET",'store.item.i.get');
define("SHOP_ITEM_SKU_I_GET",'store.item.sku.i.get');                                       // 单拉商品SKU

//网易考拉海购售后加的
define("SHOP_AGREE_REFUND",'store.refund.agree'); //同意退款
define("SHOP_AGREE_REFUNDGOODS",'store.refundgoods.agree'); //同意退货
define("SHOP_REFUSE_REFUNDGOODS",'store.refundgoods.refuse'); //拒绝退货

// 售后仅退款
define('SHOP_AGREE_AFTERSALE_REFUND', 'store.refund.agree4no.good.return'); // 同意售后仅退款
define('SHOP_REFUSE_AFTERSALE_REFUND', 'store.refund.refuse4no.good.return'); // 拒绝售后仅退款

define('SHOP_RDC_ORDERMSG_UPDATE', 'store.rdc.orderMsg.update'); //预约退款
define('SHOP_LOGISTICS_RECOMMENDED_DELIVERY', 'store.recommended.and.delivery.express.by.order'); //物流探查

define('LOGISTICS_SERVICE_AREAS_ALL_GET','service.areas.all.get');                           // 物流服务达不达
define('NOAUTH_LOGISTICS_ADDRESS_REACHABLE','store.logistics.address.reachable');               // 物流服务达不达

// 售后
// define('STORE_ASC_RECEIVE_REGISTER', 'store.asc.receive.register');                            // 京东拆包登记
define('SHOP_ASC_AUDIT_REASON_GET', 'store.asc.audit.reason.get');                             // 京东审核原因

// 支付宝/JD钱包账单
define('SHOP_QIANBAO_BILL_DETAIL_QUERY', 'store.qianbao.bill.detail.query');
define('SHOP_ALIPAY_BILL_GET','store.alipay.bill.get');

//抖音账单
define('STORE_DOWNLOAD_SHOP_ACCOUNT_ITEM', 'store.download.shop.account.item');
define('STORE_DOWNLOAD_SHOP_ACCOUNT_ITEM_FILE', 'store.download.shop.account.item.file');

//[菜鸟]商品关系
define("WMS_COMBINE_CREATE",'store.cn.combineitem.create'); //组合商品关系推送
define("WMS_MAP_CREATE",'store.cn.itemmapping.create'); //商品关系映射
define('WMS_MAPPING_QUERY','store.items.all.get'); //前端商品映射

//[京东]一件代发
define("WMS_SALEORDER_SHIPMENT", 'store.wms.saleorder.shipment.get'); //获取配送方式
define("WMS_SALEORDER_SHIPPING", 'store.wms.saleorder.shipping.get'); //获取配送费用
define("WMS_SALEORDER_INFORM", 'store.wms.saleorder.push'); //通知京东云交易平台提前发货
define("WMS_SALEORDER_CONFIRM", 'store.wms.order.status.update'); //通知京东云交易订单确认收货

define("WMS_RETURNORDER_REASON_LIST", 'store.wms.returnorder.reason.get'); //获取售后原因列表
define("WMS_RETURNORDER_APPLY_QUERY", 'store.wms.returnorder.apply.query'); //查询是否允许售后申请
define("WMS_RETURNORDER_UPDATE_LOGISTICS", 'store.wms.returnorder.update'); //更新退回物流公司编码、退回物流单号
define("WMS_RETURNORDER_ADDRESS", 'store.wms.returnaddress.get'); //查询寄件地址
define("WMS_SALEORDER_DELIVERY_STATUS", 'store.wms.saleorder.status.get'); //查询包裹发货状态

define("WMS_PUSH_TRADE_ORDERS", 'store.trade.push'); //云交易离线对账接口
define("WMS_PUSH_TRADE_ORDER_SKU", 'store.commons.send'); //云交易离线对账接口

// 订单确认接口
define("STORE_TRADE_CONFIRM", 'store.trade.confirm'); //订单确认接口
// 拣货确认接口（小时达平台配送）
define("STORE_TRADE_PICKUP_CONFIRM", 'store.trade.pickup.confirm'); //拣货确认接口

//[抖音]换货业务
define('SHOP_AFTERSALE_EXCHANGE_AGREE', 'store.exchange2.agree'); //同意换货
define('SHOP_AFTERSALE_EXCHANGE_REFUSE', 'store.exchange2.refuse'); //拒绝换货
define('SHOP_SKU_SET_SELL_TYPE', 'store.item.sku.set'); //设置SKU区域库存发货时效
define('SHOP_GET_ADDRESS_LIST', 'store.address.list.get'); //拉取商家退货地址列表
define('SHOP_AFTERSALE_RETURN_REMARK', 'store.aftersale.order.remark'); //同步京东审核意见给到抖音平台

//[得物]出价列表
define("SHOP_GET_BIDDING_ALL", 'store.bidding.normal.list'); //[得物]获取平台所有出价列表
define("SHOP_UPDATE_BIDDING_QUANTITY",'store.item.quantity.update'); //[得物]更新库存
define("SHOP_GET_BIDDING_BRAND_ALL", 'store.bidding.brand.list'); //[得物]品牌专供出价列表
define("SHOP_GET_BIDDING_BRAND_DELI_ALL", 'store.bidding.brand.deliver.list'); //[得物]品牌直发出价列表
define("SHOP_GET_SKU_PRICE_ALL", 'store.item.sku.price.get'); //[得物]按出价类型获取出价列表（极速现货）

//[得物]急速现货/品牌直发
define('STORE_GET_DISPATCH_NUMBER_BY_ORDERNO', 'store.dispatch.number.by.orderno'); //得物获取虚拟运单号服务
define('STORE_ORDER_BRAND_DELIVER_QUERY_BUYER_ADDRESS', 'store.order.brand.deliver.query.buyer.address'); // 查询买家地址接口
define('STORE_ORDER_BRAND_DELIVER_ACCEPT_ORDER', 'store.order.brand.deliver.accept.order'); // 接单
define('STORE_ORDER_BRAND_DELIVER_QUERY_SELLER_ADDRESS', 'store.order.brand.deliver.query.seller.address'); // 查询卖家发货地址
define('STORE_ORDER_BRAND_DELIVER_CHANGE_DELIVERY_WAREHOUSE', 'store.order.brand.deliver.change.delivery.warehouse'); // 订单发货仓库修改
define('STORE_ORDER_BRAND_DELIVER_LOGISTIC_NO', 'store.order.brand.deliver.logistic.no'); // 获取运单号
define('STORE_ORDER_BRAND_DELIVER_EXPRESS_SHEET', 'store.order.brand.deliver.express.sheet'); // 订单获取打印面单
define('STORE_ORDER_BRAND_DELIVER_DELIVERY', 'store.order.brand.deliver.delivery'); // (商家订单发货到买家


define("SHOP_GET_BIDDING_NORMAL_SKUS", 'store.bidding.normal.realtime.inventory'); //[普通现货]获取在售sku实时库存
define("SHOP_GET_BIDDING_BRAND_SKUS", 'store.bidding.brand.realtime.inventory'); //[品牌专供]获取在售sku实时库存

define("SHOP_GET_BIDDING_NORMAL_DETAIL", 'store.bidding.normal.detail'); //[普通现货]根据出价编号获取出价信息
define("SHOP_GET_BIDDING_BRAND_DETAIL", 'store.bidding.brand.detail'); //[品牌专供]根据出价编号获取出价详细信息

define("SHOP_GET_INVENTORY_QUERY", 'store.inventory.query'); // 获取卖家在售sku实时库存V2（支持按出价类型查询）

define("SHOP_INVENTORY_CACHE_QUERY", 'store.inventory.cache.query');//商品缓存查询

//淘宝更换sku
define("STORE_MODIFYSKU_OPEN",'store.modifysku.open'); //[淘宝]更新sku

# IDAAS
define('IDAAS_ORGANIZATION_CREATE', 'aliyun.idaas.organization.create');
define('IDAAS_ORGANIZATION_GET', 'aliyun.idaas.organization.get');
define('IDAAS_ACCOUNT_CREATE', 'aliyun.idaas.account.create');
define('IDAAS_ACCOUNT_UPDATE', 'aliyun.idaas.account.update');
define('IDAAS_ACCOUNT_DELETE', 'aliyun.idaas.account.delete');
define('IDAAS_ACCOUNT_GET', 'aliyun.idaas.account.get');
define('IDAAS_ACCOUNT_LOGIN', 'aliyun.idaas.account.login');
define('IDAAS_ACCOUNT_VERIFY_CODE', 'aliyun.idaas.account.verifycode');
define('IDAAS_ACCOUNT_CODE_VERIFY', 'aliyun.idaas.account.codeverify');

//优仓接口
define('STORE_TMYC_SCITEM_BATCH_CREATE','store.tmyc.scitem.batch.create'); //货品新建接口
define('STORE_TMYC_SCITEM_BATCH_UPDATE','store.tmyc.scitem.batch.update'); //货品更新接口
define('STORE_TMYC_COMBINESCITEM_BATCH_CREATE','store.tmyc.combinescitem.batch.create'); //组合货品新建接口
define('STORE_TMYC_COMBINESCITEM_BATCH_UPDATE','store.tmyc.combinescitem.batch.update'); //组合货品更新接口
define('STORE_TMYC_INVENTORY_BATCH_UPLOAD','store.tmyc.inventory.batch.upload'); //同步淘系可售库存
define('STORE_TMYC_ITEMMAPPING_BATCH_CREATE','store.tmyc.itemmapping.batch.create');  //创建/更新商货品关联接口

//[淘宝、天猫]商家配送接口列表
define('SHOP_LOGISTICS_SELLER_SEND', 'store.logistics.seller.send'); //商家配送(alibaba.ascp.logistics.seller.send)
define('SHOP_LOGISTICS_SELLER_WRITEOFF', 'store.logistics.seller.writeoff'); //签收核销(alibaba.ascp.logistics.seller.writeoff)
define('SHOP_LOGISTICS_SELLER_RESEND', 'store.logistics.consign.resend'); //修改运单号(alibaba.ascp.logistics.consign.resend)
define('SHOP_LOGISTICS_SELLER_TRACE', 'store.logistics.instant.trace.search'); //查询物流详情(taobao.logistics.instant.trace.search)
define('SHOP_LOGISTICS_SELLER_ORDERS', 'store.logistics.seller.orders.get'); //核销码查询列表(store.logistics.seller.orders.get)
define('SHOP_FX_JZ_LOGISTICS_OFFLINE_SEND', 'store.fenxiao.jz.logistics.offline.send'); //分销采购单发货(taobao.jzfx.logistics.offline.send)

//翱象接口列表
define('SHOP_AOXIANG_WAREHOUSE_CREATE', 'store.aoxiang.warehouse.create.update'); //仓库对接(alibaba.dchain.aoxiang.warehouse.create.update)
define('SHOP_AOXIANG_LOGISTICS_CREATE', 'store.aoxiang.delivery.create.update'); //物流公司对接(alibaba.dchain.aoxiang.delivery.create.update)
define('SHOP_AOXIANG_GOODS_CREATE_ASYNC', 'store.aoxiang.item.batch.update'); //[异步]销售物料对接(alibaba.dchain.aoxiang.item.batch.update.async)
define('SHOP_AOXIANG_GOODS_COMBINE_CREATE_ASYNC', 'store.aoxiang.combineitem.batch.update'); //[异步]组合货品接口(alibaba.dchain.aoxiang.item.batch.update.notify)
define('SHOP_AOXIANG_GOODS_DELETE_ASYNC', 'store.aoxiang.item.batch.delete'); //[异步]货品删除接口(alibaba.dchain.aoxiang.item.batch.delete.notify)
define('SHOP_AOXIANG_GOODS_DELETE_MAPPING', 'store.aoxiang.itemmapping.delete'); //货品删除商品关系接口(alibaba.dchain.aoxiang.itemmapping.delete)
define('SHOP_AOXIANG_GOODS_MAPPING_ASYNC', 'store.aoxiang.itemmapping.update'); //货品关联关系接口(alibaba.dchain.aoxiang.itemmapping.update.notify)
define('SHOP_AOXIANG_INVENTORY_SYNC', 'store.aoxiang.physics.inventory.batch.upload'); //实仓库存同步接口(alibaba.dchain.aoxiang.physics.inventory.batch.upload)
define('SHOP_AOXIANG_WAREHOUSE_REPORT', 'store.aoxiang.isv.wms.orderprocess.report'); //仓作业信息同步接口(alibaba.dchain.isv.wms.orderprocess.report)
define('SHOP_AOXIANG_LOGISTICS_QUERY', 'store.aoxiang.delivery.decision.query'); //黑白名单快递接口(alibaba.dchain.aoxiang.delivery.decision.query)

// 打印模板查询
define('STORE_WAYBILL_SEARCH', 'store.waybill.search');
// 查询物流公司
define('STORE_LOGISTICS_COMPANIES_GET', 'store.logistics.companies.get');
// 查询增值服务 
define('STORE_WAYBILL_SERVICE_SEARCH', 'store.waybill.service.search');

//D1M小程序
define("D1M_ACCESS_TOKEN_POST", '/api/open/api/token'); //token获取接口
define("D1M_OPEN_DELIVERY_UPDATE_POST", '/api/open/delivery/update'); //更新物流信息
define("D1M_OPEN_UPDATE_STORE_POST", '/api/open/update_store'); //批量库存回写
define("D1M_OPEN_REFUND_NOTICE_POST", '/api/open/refund/notice'); //退款单审核通知

//zkh
define('ZKH_OPEN_DELIVERY_CREATE_POST','store.deliveryorder.create');      //添加发货单(/openPoApi/v1/deliveryOrder/create)
define('ZKH_OPEN_DELIVERY_CONFIRM_POST','store.order.delivery.confirm');      //采购单发货确认(/openPoApi/v1/purchaseOrder/ackDeliveryOrderPart)
define('ZKH_OPEN_GET_DELIVERY_POST','store.order.delivery.get');      //供应商发货单确认详情查询 V1 (/openPoApi /v1/purchaseOrder/getDeliveryOrderPart)
define('ZKH_OPEN_GET_DELIVERY_DETAIL','store.order.delivery.detail.get');      //供应商获取送货单详情 pdf (/openPoApi/v1/purchaseOrder/getDeliveryOrderDetailForPDF)

//jdlvmi对接
define('SHOP_WMS_OUTORDER_NOTIFY','store.vmi.wms.outorder.notify');
define("SHOP_JDLVMI_GET_ITEMS_ALL_RPC", 'store.vc.item.products.find');             // 获取jdlvmi前端商品
define('SHOP_JDLVMI_ITEMS_PRODUCT_GET','store.vc.item.product.get');
define('SHOP_JDVMI_UPDATE_ITEMS_QUANTITY_LIST_RPC','store.vmi.items.quantity.list.update');
define('SHOP_WMI_LOGISTICS_OFFLINE_SEND','store.vmi.logistics.offline.send');
define('SHOP_VMI_RETURN_GOOD_CONFIRM','store.vmi.return.good.confirm');

define('SHOP_INVENTORY_QUERY','store.inventory.query');

define('SHOP_RETURNORDER_DIFF_DETAIL_GET','store.returnorder.diff.detail.get');
define('SHOP_RETURNORDER_DIFF_LIST_GET','store.returnorder.diff.list.get');

define('SHOP_ISV_PAGE_CODE','store.isv.page.code');
define('SHOP_BILL_PAYABLE_SETTLEMENT_QUERY','store.bill.payable.settlement.query');
define('STORE_WMS_TRANSPORTLASWAYBILL_GET', 'store.wms.transportLasWayBill');

//京东工小达
define('SHOP_JDGXD_LOGISTICS_FULFILLMENT_INFO','store.get.fulfillment.info');//平台承运商履约信息查询接口 platformCarrierPerformInfo.query
define('SHOP_JDGXD_CHOICE_LOGISTICS','store.choice.logistics');//回传发货地址--地址预决策接口 jingdong.JosCarrierPlatformService.decision
define('STORE_THIRDPDF_FORVENDER_GET','store.thirdpdf.forvender.get');//获取配送清单
define('SHOP_FX_SERIALNUMBER_UPDATE', 'store.fenxiao.serial.number.update'); // 订单绑定序列号四码信息(唯一码)

//快递鸟-顺丰子母件
define('STORE_WAYBILL_SUB_GET', 'store.waybill.sub.i.get');//电子面单追加子单

// 易连云
define("YLY_OAUTH_OAUTH", '/oauth/oauth'); // 自有型应用
define("YLY_OAUTH_AUTHORIZE", '/oauth/authorize'); // 开放型应用（OAuth2.0授权码模式）
define("YLY_EXPRESSPRINT_INDEX", '/expressprint/index'); // 面单生成并打印
define('YLY_PICTUREPRINT_INDEX', '/pictureprint/index'); // 打印面单图片

// o2o门店
define('STORE_O2O_STORES_GET', 'store.item.o2o.stores.get');

//物流包裹异常查询接口
define('STORE_LOGISTICS_PACKAGE_EXCEPTION_QUERY', 'store.logistics.package.exception.query'); //物流包裹异常查询接口
//物流包裹异常配置查询接口
define('STORE_LOGISTICS_PACKAGE_EXCEPTION_CONFIG_QUERY', 'store.logistics.package.exception.config.query'); //物流包裹异常配置查询接口

// 隐私号G组更新(700虚拟号)
define('STORE_VIRTUAL_NUMBER_GROUP_UPDATE', 'store.virtual.number.group.update');
