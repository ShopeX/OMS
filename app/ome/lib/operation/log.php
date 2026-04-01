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


class ome_operation_log{

    /**
     * 定义当前APP下的操作日志的所有操作名称列表
     * type键值由表名@APP名称组成
     * @access public
     * @return Array
     */
    function get_operations(){
        $operations = array(
           //订单
           'order_create' => array('name'=> '订单创建','type' => 'orders@ome'),
           'order_edit' => array('name'=> '订单编辑','type' => 'orders@ome'),
           'order_modify' => array('name'=> '订单修改','type' => 'orders@ome'),
           'order_back' => array('name'=> '订单的发货单打回','type' => 'orders@ome'),
           'order_dispatch' => array('name'=> '订单调度','type' => 'orders@ome'),
           'order_confirm' => array('name'=> '订单确认','type' => 'orders@ome'),
           'order_split' => array('name'=> '订单拆分','type' => 'orders@ome'),
           'order_payment' => array('name'=> '订单支付请求','type' => 'orders@ome'),
           'order_refund' => array('name'=> '订单退款请求','type' => 'orders@ome'),
           'order_pay' => array('name'=> '支付单添加','type' => 'orders@ome'),
           'order_refuse' => array('name'=> '订单发货拒收','type' => 'orders@ome'),
           'order_preprocess' => array('name'=> '订单预处理','type' => 'orders@ome'),
           //售后
           'return' =>array('name'=> '售后服务修改','type' => 'return_product@ome'),
           'reship' =>array('name'=>'售后服务修改','type' => 'reship@ome'),
           'reship_getlist' =>array('name'=>'售后信息获取','type' => 'reship@ome'),
           'reship_print' =>array('name'=>'售后信息打印','type' => 'reship@ome'),
           //补寄
           'return_reshipping' =>array('name'=> '补寄申请单','type' => 'return_reshipping@ome'),
           //退款
           'refund_apply' => array('name'=> '退款申请','type' => 'refund_apply@ome'),
           'refund_accept' => array('name'=> '退款成功','type' => 'refunds@ome'),
           'refund_refuse' => array('name'=> '退款拒绝','type' => 'refund_apply@ome'),
           'refund_verify' => array('name'=> '退款审核中','type' => 'refund_apply@ome'),
           'refund_pass' => array('name'=> '退款审核通过','type' => 'refund_apply@ome'),
           //支付
           'payment_create' => array('name'=> '生成支付单','type' => 'payments@ome'),
           //仓库
           'branch' => array('name'=> '库存导入','type' => 'branch@ome'),
           'branch_pos_del' => array('name'=> '删除货位','type' => 'branch_pos@ome'),
           //发货单
           'delivery_modify' => array('name'=> '发货单详情修改','type' => 'delivery@ome'),
           'delivery_position' => array('name'=> '发货单货位 录入','type' => 'delivery@ome'),
           'delivery_merge' => array('name'=> '发货单合并','type' => 'delivery@ome'),
           'delivery_split' => array('name'=> '发货单拆分','type' => 'delivery@ome'),
           'delivery_stock' => array('name'=> '发货单备货单打印','type' => 'delivery@ome'),
           'delivery_deliv' => array('name'=> '发货单商品信息打印','type' => 'delivery@ome'),
           'delivery_expre' => array('name'=> '发货单快递单打印','type' => 'delivery@ome'),
           'delivery_logi_no' => array('name'=> '发货单快递单 录入','type' => 'delivery@ome'),
           'delivery_check' => array('name'=> '发货单校验','type' => 'delivery@ome'),
           'delivery_process' => array('name'=> '发货单发货处理','type' => 'delivery@ome'),
           'delivery_back' => array('name'=> '发货单打回','type' => 'delivery@ome'),
           'delivery_logi' => array('name'=> '发货单物流公司修改','type' => 'delivery@ome'),
           'delivery_pick' => array('name'=> '发货单拣货','type' => 'delivery@ome'),
            //新增发货称重报警处理
            'delivery_weightwarn' => array('name'=> '发货称重报警处理','type' => 'delivery@ome'),
           //子物流单操作日志
           'delivery_bill_print' => array('name'=> '多包裹物流单 打印','type' => 'delivery@ome'),
           'delivery_bill_delete' => array('name'=> '多包裹物流单 删除','type' => 'delivery@ome'),
           'delivery_bill_add' => array('name'=> '多包裹物流单 录入','type' => 'delivery@ome'),
           'delivery_bill_modify' => array('name'=> '多包裹物流单 修改','type' => 'delivery@ome'),
           'delivery_bill_express' => array('name'=> '多包裹物流单 发货','type' => 'delivery@ome'),
           'delivery_checkdelivery'=>array('name'=>'发货单发货处理','type' => 'delivery@ome'),
           //商品修改
           'goods_modify'=>array('name'=>'商品修改','type'=>'goods@ome'),
           'goods_add'=>array('name'=>'商品添加','type'=>'goods@ome'),
           'goods_hide'=>array('name'=>'商品隐藏','type'=>'goods@ome'),
           'goods_show'=>array('name'=>'商品显示','type'=>'goods@ome'),
           'goods_import'=>array('name'=>'商品导入','type'=>'goods@ome'),
           
            //复审订单
            'order_retrial' => array('name'=> '复审订单','type' => 'orders@ome'),
            
            //跨境申报订单
            'customs_create' => array('name'=> '申报创建','type' => 'orders@customs'),
            'customs_edit' => array('name'=> '申报编辑','type' => 'orders@customs'),
            'customs_api' => array('name'=> '申报接口','type' => 'orders@customs'),
            'crm_on' => array('name'=> '开启CRM赠品应用','type' => 'gift@crm'),
            'crm_off' => array('name'=> '关闭CRM赠品应用','type' => 'gift@crm'),
            'crm_edit'=>array('name'=>'赠品规则修改','type'=>'gift_rule@ome'),
                
            //唯品会JIT
            'create_vopurchase' => array('name'=>'采购单创建', 'type' => 'order@purchase'),
            'update_vopurchase' => array('name'=>'采购单更新', 'type' => 'order@purchase'),
            'create_vopick' => array('name'=>'拣货单创建', 'type' => 'pick_bills@purchase'),
            'update_vopick' => array('name'=>'拣货单更新', 'type' => 'pick_bills@purchase'),
            'check_vopick' => array('name'=>'拣货单审核', 'type' => 'pick_bills@purchase'),
            'create_stockout_bills' => array('name'=>'出库单创建', 'type' => 'pick_stockout_bills@purchase'),
            'update_stockout_bills' => array('name'=>'出库单更新', 'type' => 'pick_stockout_bills@purchase'),
            'edit_stockout_bills' => array('name'=>'出库单编辑', 'type' => 'pick_stockout_bills@purchase'),
            'check_stockout_bills' => array('name'=>'出库单审核', 'type' => 'pick_stockout_bills@purchase'),
                
            //人工库存预占
            'import_artificial_freeze' => array('name'=>'人工库存预占导入', 'type' => 'basic_material_stock_artificial_freeze@console'),
            'add_artificial_freeze' => array('name'=>'人工库存预占新增', 'type' => 'basic_material_stock_artificial_freeze@console'),
            'delete_artificial_freeze' => array('name'=>'人工库存预占删除', 'type' => 'basic_material_stock_artificial_freeze@console'),
            'release_artificial_freeze' => array('name'=>'人工库存预占释放', 'type' => 'basic_material_stock_artificial_freeze@console'),
            
            //库存扩展信息
            'branch_product_extend' => array('name'=>'库存扩展信息', 'type'=>'branch_product_extend@ome'),
            
            // 订单预约时间
            'order_reserved_create' => array('name'=>'创建待预约', 'type'=>'order_reservation@ome'),
            'order_reserved_edit' => array('name'=>'编辑待预约', 'type'=>'order_reservation@ome'),
            'order_reserved_modify' => array('name'=>'修改待预约', 'type'=>'order_reservation@ome'),
        );
        return array('ome'=>$operations);
    }
    /**
     * 获得导入导出类型
     */
    public static function getIOItem($type = '') {

        $itemType = array(
            'order_export' => array('name' => '订单-导出', 'type' => 'export@ome', 'operation' => '0001@ome', 'obj_id' => '100000000001', 'obj_name' => '100000000001-0001'),
            'order_current_export' => array('name' => '订单-当前-导出', 'type' => 'export@ome', 'operation' => '0002@ome', 'obj_id' => '100000000002', 'obj_name' => '100000000002-0002'),
            'order_template_export' => array('name' => '订单-模板-导出', 'type' => 'export@ome', 'operation' => '0003@ome', 'obj_id' => '100000000003', 'obj_name' => '100000000003-0003'),
            'order_history_export' => array('name' => '订单-历史-导出', 'type' => 'export@ome', 'operation' => '0004@ome', 'obj_id' => '100000000004', 'obj_name' => '100000000004-0004'),
            'order_fail_export' => array('name' => '订单-失败-导出', 'type' => 'export@ome', 'operation' => '0005@ome', 'obj_id' => '100000000005', 'obj_name' => '100000000005-0005'),
            'delivery_waitprint_export' => array('name' => '发货-待打印-导出', 'type' => 'export@ome', 'operation' => '0006@delivery', 'obj_id' => '100000000006', 'obj_name' => '100000000006-0006'),
            'delivery_export' => array('name' => '发货-导出', 'type' => 'export@ome', 'operation' => '0007@delivery', 'obj_id' => '100000000007', 'obj_name' => '100000000007-0007'),
            'delivery_simple_return_already_export' => array('name' => '发货-原样寄回-已发货-导出', 'type' => 'export@delivery', 'operation' => '0008@ome', 'obj_id' => '100000000008', 'obj_name' => '100000000008-0008'),
            'afterSale_export' => array('name' => '售后-导出', 'type' => 'export@ome', 'operation' => '0009@ome', 'obj_id' => '100000000009', 'obj_name' => '100000000009-0009'),
            'afterSale_exchange_goods_exchangeList_export' => array('name' => '售后-退换货服务-退货货单-导出', 'type' => 'export@ome', 'operation' => '0010@ome', 'obj_id' => '100000000010', 'obj_name' => '100000000010-0010'),
            'afterSale_exchange_goods_qualityTesting_export' => array('name' => '售后-退换货服务-质检单据-导出', 'type' => 'export@ome', 'operation' => '0011@ome', 'obj_id' => '100000000011', 'obj_name' => '100000000011-0011'),
            'afterSale_onlycode_list_export' => array('name' => '售后-唯一码-列表-导出', 'type' => 'export@ome', 'operation' => '0012@ome', 'obj_id' => '100000000012', 'obj_name' => '100000000012-0012'),
            'finance_export' => array('name' => '财务-导出', 'type' => 'export@ome', 'operation' => '0013@finance', 'obj_id' => '100000000013', 'obj_name' => '100000000013-0013'),
            'finance_purchase_payments_export' => array('name' => '财务-采购财务-付款单-导出', 'type' => 'export@ome', 'operation' => '0014@purchase', 'obj_id' => '100000000014', 'obj_name' => '100000000014-0014'),
            'finance_purchase_credit_sheet_export' => array('name' => '财务-采购财务-赊购单-导出', 'type' => 'export@ome', 'operation' => '0015@purchase', 'obj_id' => '100000000015', 'obj_name' => '100000000015-0015'),
            'finance_purchase_purchaseRefunds_export' => array('name' => '财务-采购财务-采购退款单-导出', 'type' => 'export@ome', 'operation' => '0016@purchase', 'obj_id' => '100000000016', 'obj_name' => '100000000016-0016'),
            'finance_saleReceivable_template_export' => array('name' => '财务-销售应收单-下载模板-导出', 'type' => 'export@ome', 'operation' => '0017@finance', 'obj_id' => '100000000017', 'obj_name' => '100000000017-0017'),
            'finance_saleReceipts_return_export' => array('name' => '财务-销售收款单-收退货单-导出', 'type' => 'export@ome', 'operation' => '0018@finance', 'obj_id' => '100000000018', 'obj_name' => '100000000018-0018'),
            'finance_revExpen_bill_template_export' => array('name' => '财务-收支单导入-账单导入-下载模板-导出', 'type' => 'export@ome', 'operation' => '0019@finance', 'obj_id' => '100000000019', 'obj_name' => '100000000019-0019'),
            'finance_billTemplate_export' => array('name' => '财务-账单模板-导出', 'type' => 'export@ome', 'operation' => '0020@finance', 'obj_id' => '100000000020', 'obj_name' => '100000000020-0020'),
            'finance_financeReport_orderRevExpen_detail_export' => array('name' => '财务-财务报表-订单收支明细-导出', 'type' => 'export@ome', 'operation' => '0021@finance', 'obj_id' => '100000000021', 'obj_name' => '100000000021-0021'),
            'finance_financeReport_saleToAccount_detail_export' => array('name' => '财务-财务报表-销售到账明细-导出', 'type' => 'export@ome', 'operation' => '0022@finance', 'obj_id' => '100000000022', 'obj_name' => '100000000022-0022'),
            'finance_logisticsToAccount_logisticsBill_export' => array('name' => '财务-物流对账-物流账单-导出', 'type' => 'export@ome', 'operation' => '0023@logisticsaccounts', 'obj_id' => '100000000023', 'obj_name' => '100000000023-0023'),
            'finance_logisticsToAccount_toAccountTask_template_export' => array('name' => '财务-物流对账-对账任务-下载模板-导出', 'type' => 'export@ome', 'operation' => '0024@logisticsaccounts', 'obj_id' => '100000000024', 'obj_name' => '100000000024-0024'),
            'goods_goodsMananger_allList_template_export' => array('name' => '商品-商品管理-查看所有商品-成本与重量模板-导出', 'type' => 'export@ome', 'operation' => '0025@ome', 'obj_id' => '100000000025', 'obj_name' => '100000000025-0025'),
            'goods_goodsMananger_allList_export' => array('name' => '商品-商品管理-查看所有商品-导出', 'type' => 'export@ome', 'operation' => '0026@ome', 'obj_id' => '100000000026', 'obj_name' => '100000000026-0026'),
            'goods_export' => array('name' => '商品-导出', 'type' => 'export@ome', 'operation' => '0027@ome', 'obj_id' => '100000000027', 'obj_name' => '100000000027-0027'),
            'goods_goodsConfig_goodsType_export' => array('name' => '商品-商品配置-商品类型-导出', 'type' => 'export@ome', 'operation' => '0028@ome', 'obj_id' => '100000000028', 'obj_name' => '100000000028-0028'),
            'goods_goodsConfig_goodsSpec_export' => array('name' => '商品-商品配置-商品规格-导出', 'type' => 'export@ome', 'operation' => '0029@ome', 'obj_id' => '100000000029', 'obj_name' => '100000000029-0029'),
            'goods_goodsConfig_goodsBrand_export' => array('name' => '商品-商品配置-商品规格-导出', 'type' => 'export@ome', 'operation' => '0030@ome', 'obj_id' => '100000000030', 'obj_name' => '100000000030-0030'),
            'goods_goodsBatProcess_batUpload_export' => array('name' => '商品-商品批量处理-批量上传-导出', 'type' => 'export@ome', 'operation' => '0031@ome', 'obj_id' => '100000000031', 'obj_name' => '100000000031-0031'),
            'delivery_orders_export' => array('name' => '发货-单据-导出', 'type' => 'export@ome', 'operation' => 'delivery_orders_export@ome', 'obj_id' => '100000000032', 'obj_name' => '100000000032-0032'),
            
            'purchase_export' => array('name' => '采购-导出', 'type' => 'export@ome', 'operation' => '0034@purchase', 'obj_id' => '100000000034', 'obj_name' => '100000000034-0034'),
            'purchase_purchaseOrder_export' => array('name' => '采购-采购订单-导出', 'type' => 'export@ome', 'operation' => '0035@purchase', 'obj_id' => '100000000035', 'obj_name' => '100000000035-0035'),
            'purchase_purchaseReturn_template_export' => array('name' => '采购-采购退货单-模板-导出', 'type' => 'export@ome', 'operation' => '0036@purchase', 'obj_id' => '100000000036', 'obj_name' => '100000000036-0036'),
            'purchase_purchaseReturn_export' => array('name' => '采购-采购退货单-导出', 'type' => 'export@ome', 'operation' => '0037@purchase', 'obj_id' => '100000000037', 'obj_name' => '100000000037-0037'),
            'purchase_needRemind_export' => array('name' => '采购-补货提醒-导出', 'type' => 'export@ome', 'operation' => '0038@purchase', 'obj_id' => '100000000038', 'obj_name' => '100000000038-0038'),
            'purchase_supplierManager_supplier_export' => array('name' => '采购-供应商管理-供应商-导出', 'type' => 'export@ome', 'operation' => '0039@purchase', 'obj_id' => '100000000039', 'obj_name' => '100000000039-0039'),
            'purchase_supplierManager_supplier_template_export' => array('name' => '采购-供应商管理-供应商-模板-导出', 'type' => 'export@ome', 'operation' => '0040@purchase', 'obj_id' => '100000000040', 'obj_name' => '100000000040-0040'),
            'taobaogoods_export' => array('name' => '淘宝商品-导出', 'type' => 'export@ome', 'operation' => '0033@taoapi', 'obj_id' => '100000000041', 'obj_name' => '100000000041-0041'),
            'taobaogoods_taobaogoods_goods_export' => array('name' => '淘宝商品-淘宝商品-商品列表-导出', 'type' => 'export@ome', 'operation' => '0042@taoapi', 'obj_id' => '100000000042', 'obj_name' => '100000000042-0042'),
            'taobaogoods_taobaogoods_skuList_export' => array('name' => '淘宝商品-淘宝商品-SKU列表-导出', 'type' => 'export@ome', 'operation' => '0043@taoapi', 'obj_id' => '100000000043', 'obj_name' => '100000000043-0043'),
            'taobaogoods_taobaogoods_goodsAttribute_export' => array('name' => '淘宝商品-淘宝商品-商品属性值-导出', 'type' => 'export@ome', 'operation' => '0044@taoapi', 'obj_id' => '100000000044', 'obj_name' => '100000000044-0044'),
            'warehouse_export' => array('name' => '仓储-导出', 'type' => 'export@ome', 'operation' => '0045@taoguaniostockorder', 'obj_id' => '100000000045', 'obj_name' => '100000000045-0045'),
            'warehouse_enterManager_other_export' => array('name' => '仓储-入库管理-其它入库-导出', 'type' => 'export@ome', 'operation' => '0046@taoguaniostockorder', 'obj_id' => '100000000046', 'obj_name' => '100000000046-0046'),
            'warehouse_enterManager_other_template_export' => array('name' => '仓储-入库管理-其它入库-模板-导出', 'type' => 'export@ome', 'operation' => '0047@taoguaniostockorder', 'obj_id' => '100000000047', 'obj_name' => '100000000047-0047'),
            'warehouse_enterManager_enterFind_export' => array('name' => '仓储-入库管理-入库单查询-导出', 'type' => 'export@ome', 'operation' => '0048@taoguaniostockorder', 'obj_id' => '100000000048', 'obj_name' => '100000000048-0048'),
            'warehouse_outManager_other_export' => array('name' => '仓储-出库管理-其它出库-导出', 'type' => 'export@ome', 'operation' => '0049@taoguaniostockorder', 'obj_id' => '100000000049', 'obj_name' => '100000000049-0049'),
            'warehouse_other_template_export' => array('name' => '仓储-其他(出入)库-模板-导出', 'type' => 'export@ome', 'operation' => '0050@taoguaniostockorder', 'obj_id' => '100000000050', 'obj_name' => '100000000050-0050'),
            'warehouse_outManager_outFind_export' => array('name' => '仓储-出库管理-出库单查询-导出', 'type' => 'export@ome', 'operation' => '0051@taoguaniostockorder', 'obj_id' => '100000000051', 'obj_name' => '100000000051-0051'),
            'warehouse_dailyManager_posManager_export' => array('name' => '仓储-日常管理-货位管理-导出', 'type' => 'export@ome', 'operation' => '0052@ome', 'obj_id' => '100000000052', 'obj_name' => '100000000052-0052'),
            'warehouse_dailyManager_posTidy_export' => array('name' => '仓储-日常管理-货位整理-导出', 'type' => 'export@ome', 'operation' => '0053@ome', 'obj_id' => '100000000053', 'obj_name' => '100000000053-0053'),
            'warehouse_dailyManager_posTidy_template_export' => array('name' => '仓储-日常管理-货位整理-模板-导出', 'type' => 'export@ome', 'operation' => '0054@ome', 'obj_id' => '100000000054', 'obj_name' => '100000000054-0054'),
            'warehouse_stockManager_stockList_export' => array('name' => '仓储-库存管理-仓库库存列表-导出', 'type' => 'export@ome', 'operation' => '0055@ome', 'obj_id' => '100000000055', 'obj_name' => '100000000055-0055'),
            'warehouse_stockManager_totalStockList_export' => array('name' => '仓储-库存管理-总库存列表-导出', 'type' => 'export@ome', 'operation' => '0056@ome', 'obj_id' => '100000000056', 'obj_name' => '100000000056-0056'),
            'warehouse_stockManager_ioStockDetail_export' => array('name' => '仓储-库存管理-出入库明细-导出', 'type' => 'export@ome', 'operation' => '0057@iostock', 'obj_id' => '100000000057', 'obj_name' => '100000000057-0057'),
            'warehouse_checkManager_check_export' => array('name' => '仓储-盘点管理-盘点-导出', 'type' => 'export@ome', 'operation' => '0058@taoguaninventory', 'obj_id' => '100000000058', 'obj_name' => '100000000058-0058'),
            'warehouse_checkManager_checkTemplate_export' => array('name' => '仓储-盘点管理-盘点模板-导出', 'type' => 'export@ome', 'operation' => '0059@taoguaninventory', 'obj_id' => '100000000059', 'obj_name' => '100000000059-0059'),
            'bill_export' => array('name' => '单据-导出', 'type' => 'export@ome', 'operation' => '0060@sales', 'obj_id' => '100000000060', 'obj_name' => '100000000060-0060'),
            'bill_salesBill_sales_export' => array('name' => '单据-销售单据-销售单-导出', 'type' => 'export@ome', 'operation' => '0061@sales', 'obj_id' => '100000000061', 'obj_name' => '100000000061-0061'),
            'bill_salesBill_afterSales_export' => array('name' => '单据-销售单据-售后单-导出', 'type' => 'export@ome', 'operation' => '0062@sales', 'obj_id' => '100000000062', 'obj_name' => '100000000062-0062'),
            'bill_salesBill_receipts_export' => array('name' => '单据-销售单据-收款单-导出', 'type' => 'export@ome', 'operation' => '0063@ome', 'obj_id' => '100000000063', 'obj_name' => '100000000063-0063'),
            'bill_salesBill_refund_export' => array('name' => '单据-销售单据-退款单-导出', 'type' => 'export@ome', 'operation' => '0064@ome', 'obj_id' => '100000000064', 'obj_name' => '100000000064-0064'),
            'bill_delivery_delivery_export' => array('name' => '单据-发货单据-发货单-导出', 'type' => 'export@ome', 'operation' => '0065@ome', 'obj_id' => '100000000065', 'obj_name' => '100000000065-0065'),
            'bill_delivery_afterSales_export' => array('name' => '单据-发货单据-售后入库单-导出', 'type' => 'export@ome', 'operation' => '0066@ome', 'obj_id' => '100000000066', 'obj_name' => '100000000066-0066'),
            'bill_purchase_cash_export' => array('name' => '单据-采购单据-现购单-导出', 'type' => 'export@ome', 'operation' => '0067@purchase', 'obj_id' => '100000000067', 'obj_name' => '100000000067-0067'),
            'bill_purchase_buyOnTally_export' => array('name' => '单据-采购单据-赊购单-导出', 'type' => 'export@ome', 'operation' => '0068@purchase', 'obj_id' => '100000000068', 'obj_name' => '100000000068-0068'),
            'bill_purchase_purchaseRefund_export' => array('name' => '单据-采购单据-采购退款单-导出', 'type' => 'export@ome', 'operation' => '0069@purchase', 'obj_id' => '100000000069', 'obj_name' => '100000000069-0069'),
            'performance_export' => array('name' => '绩效-导出', 'type' => 'export@ome', 'operation' => '0070@tgkpi', 'obj_id' => '100000000070', 'obj_name' => '100000000070-0070'),
            'performance_performanceStatistics_pickStreaming_export' => array('name' => '绩效-绩效统计-捡货流水-导出', 'type' => 'export@ome', 'operation' => '0071@tgkpi', 'obj_id' => '100000000071', 'obj_name' => '100000000071-0071'),
            'performance_performanceStatistics_pickAnalysis_export' => array('name' => '绩效-绩效统计-捡货统计-导出', 'type' => 'export@ome', 'operation' => '0072@tgkpi', 'obj_id' => '100000000072', 'obj_name' => '100000000072-0072'),
            'performance_performanceStatistics_verifyAnalysis_export' => array('name' => '绩效-绩效统计-校验统计-导出', 'type' => 'export@ome', 'operation' => '0073@tgkpi', 'obj_id' => '100000000073', 'obj_name' => '100000000073-0073'),
            'performance_performanceStatistics_deliveryAnalysis_export' => array('name' => '绩效-绩效统计-发货统计-导出', 'type' => 'export@ome', 'operation' => '0074@tgkpi', 'obj_id' => '100000000074', 'obj_name' => '100000000074-0074'),
            'performance_performanceStatistics_verifyFailAnalysis_export' => array('name' => '绩效-绩效统计-校验失败原因统计-导出', 'type' => 'export@ome', 'operation' => '0075@tgkpi', 'obj_id' => '100000000075', 'obj_name' => '100000000075-0075'),
            'report_export' => array('name' => '报表-导出', 'type' => 'export@ome', 'operation' => '0076@omeanalysts', 'obj_id' => '100000000076', 'obj_name' => '100000000076-0076'),
            'report_salesReport_posSales_export' => array('name' => '报表-销售报表-货品销售情况-导出', 'type' => 'export@ome', 'operation' => '0077@omeanalysts', 'obj_id' => '100000000077', 'obj_name' => '100000000077-0077'),
            'report_salesReport_goodsSales_export' => array('name' => '报表-销售报表-商品销售情况-导出', 'type' => 'export@ome', 'operation' => '0078@omeanalysts', 'obj_id' => '100000000078', 'obj_name' => '100000000078-0078'),
            'report_salesReport_orderSales_export' => array('name' => '报表-销售报表-订单销售情况-导出', 'type' => 'export@ome', 'operation' => '0079@omeanalysts', 'obj_id' => '100000000079', 'obj_name' => '100000000079-0079'),
            'report_salesReport_refundAnalysis_export' => array('name' => '报表-销售报表-退货情况统计-导出', 'type' => 'export@ome', 'operation' => '0080@omeanalysts', 'obj_id' => '100000000080', 'obj_name' => '100000000080-0080'),
            'report_salesReport_shopDayAnalysis_export' => array('name' => '报表-销售报表-店铺每日汇总-导出', 'type' => 'export@ome', 'operation' => '0081@omeanalysts', 'obj_id' => '100000000081', 'obj_name' => '100000000081-0081'),
            'report_purchaseReport_orderIncomeAnalysis_export' => array('name' => '报表-财务报表-订单收入统计-导出', 'type' => 'export@ome', 'operation' => '0082@omeanalysts', 'obj_id' => '100000000082', 'obj_name' => '100000000082-0082'),
            'report_purchaseReport_expressAnalysis_export' => array('name' => '报表-财务报表-快递费统计-导出', 'type' => 'export@ome', 'operation' => '0083@omeanalysts', 'obj_id' => '100000000083', 'obj_name' => '100000000083-0083'),
            'report_purchaseReport_codAnalysis_export' => array('name' => '报表-财务报表-货到付款统计-导出', 'type' => 'export@ome', 'operation' => '0084@omeanalysts', 'obj_id' => '100000000084', 'obj_name' => '100000000084-0084'),
            'report_purchaseReport_branchDeliveryAnalysis_export' => array('name' => '报表-财务报表-仓库发货情况统计-导出', 'type' => 'export@ome', 'operation' => '0085@omeanalysts', 'obj_id' => '100000000085', 'obj_name' => '100000000085-0085'),
            'report_purchaseReport_costAnalysis_export' => array('name' => '报表-财务报表-费用统计-导出', 'type' => 'export@ome', 'operation' => '0086@finance', 'obj_id' => '100000000086', 'obj_name' => '100000000086-0086'),
            'report_purchaseReport_stockCostAnalysis_export' => array('name' => '报表-财务报表-库存成本统计-导出', 'type' => 'export@ome', 'operation' => '0087@tgstockcost', 'obj_id' => '100000000087', 'obj_name' => '100000000087-0087'),
            'report_purchaseReport_stockSummaryAnalysis_export' => array('name' => '报表-财务报表-进销存统计-导出', 'type' => 'export@ome', 'operation' => '0088@tgstockcost', 'obj_id' => '100000000088', 'obj_name' => '100000000088-0088'),
            'report_analysisReport_goodsSale_export' => array('name' => '报表-分析报表-商品销售排行-导出', 'type' => 'export@ome', 'operation' => '0089@omeanalysts', 'obj_id' => '100000000089', 'obj_name' => '100000000089-0089'),
            'report_analysisReport_storeStatusAnalysis_export' => array('name' => '报表-分析报表-库存状况分析-导出', 'type' => 'export@ome', 'operation' => '0090@omeanalysts', 'obj_id' => '100000000090', 'obj_name' => '100000000090-0090'),
            'afterSale_reship_refuse_export' => array('name' => '售后-拒收单-导出', 'type' => 'reship@ome', 'operation' => '0091@ome', 'obj_id' => '100000000091', 'obj_name' => '100000000091-0091'),
            'selfwms_delivery_export'=>array('name'=>'自有仓发货单-导出','type'=>'delivery@wms','operation'=>'export@wms','obj_id'=>'100000000092','obj_name'=>'100000000092-0091'),//自有仓发货单导出
            'ome_mdl_analysis_stocknsale_export' => array('name' => '单据报表-库存管理-滞呆库存统计-导出', 'type' => 'export@ome', 'operation' => '0093@iostock', 'obj_id' => '100000000093', 'obj_name' => '100000000093-0093'),
            'ome_mdl_analysis_productnsale_export' => array('name' => '单据报表-分析报表-不动销商品报表-导出', 'type' => 'export@ome', 'operation' => '0094@omeanalysts', 'obj_id' => '100000000094', 'obj_name' => '100000000094-0094'),
            
            'order_import' => array('name'=> '订单-导入', 'type' => 'import@ome', 'operation' => '5001@ome', 'obj_id' => '100000005001', 'obj_name' => '100000005001-5001'),
            'order_groupon_bat_import' => array('name'=> '订单-订单批量处理-团购订单批量-导入', 'type' => 'import@ome', 'operation' => '5002@ome', 'obj_id' => '100000005002', 'obj_name' => '100000005002-5002'),
            'finance_saleReceivable_import' => array('name'=> '财务-销售应收单-导入', 'type' => 'import@ome', 'operation' => '5003@finance', 'obj_id' => '100000005003', 'obj_name' => '100000005003-5003'),
            'finance_revExpen_bill_billConfirm_import' => array('name'=> '财务-收支单导入-账单导入-账单-导入', 'type' => 'import@ome', 'operation' => '5004@finance', 'obj_id' => '100000005004', 'obj_name' => '100000005004-5004'),
            'finance_financeSet_startInit_bill_import' => array('name'=> '财务-财务设置-起初初始化-账单-导入', 'type' => 'import@ome', 'operation' => '5005@finance', 'obj_id' => '100000005005', 'obj_name' => '100000005005-5005'),
            'goods_goodsMananger_costAndWeight_bat_import' => array('name'=> '商品-商品管理-查看所有商品-批量成本与重量-导入', 'type' => 'import@ome', 'operation' => '5006@ome', 'obj_id' => '100000005006', 'obj_name' => '100000005006-5006'),
            'goods_import' => array('name'=> '商品-导入', 'type' => 'import@ome', 'operation' => '5007@ome', 'obj_id' => '100000005007', 'obj_name' => '100000005007-5007'),
            'goods_goodsConfig_goodsType_import' => array('name'=> '商品-商品配置-商品类型-导入', 'type' => 'import@ome', 'operation' => '5008@ome', 'obj_id' => '100000005008', 'obj_name' => '100000005008-5008'),
            'goods_goodsConfig_goodsSpec_import' => array('name'=> '商品-商品配置-商品规格-导入', 'type' => 'import@ome', 'operation' => '5009@ome', 'obj_id' => '100000005009', 'obj_name' => '100000005009-5009'),
            'goods_goodsConfig_goodsBrand_import' => array('name'=> '商品-商品配置-商品品牌-导入', 'type' => 'import@ome', 'operation' => '5010@ome', 'obj_id' => '100000005010', 'obj_name' => '100000005010-5010'),
            'goods_goodsBatProcess_batUpload_import' => array('name'=> '商品-商品批量处理-批量上传-导入', 'type' => 'import@ome', 'operation' => '5011@ome', 'obj_id' => '100000005011', 'obj_name' => '100000005011-5011'),
            
            'delivery_import' => array('name'=> '发货-导入', 'type' => 'import@ome', 'operation' => '5013@ome', 'obj_id' => '100000005013', 'obj_name' => '100000005013-5013'),
            'goods_goodsBingding_bindingGoods_import' => array('name'=> '商品-商品捆绑-捆绑商品-导入', 'type' => 'import@ome', 'operation' => '5067@ome', 'obj_id' => '100000005067', 'obj_name' => '100000005067-5067'),
            'purchase_import' => array('name'=> '采购-导入', 'type' => 'import@ome', 'operation' => '5014@purchase', 'obj_id' => '100000005014', 'obj_name' => '100000005014-5014'),
            'purchase_purchaseOrder_import' => array('name'=> '采购-采购订单-导入', 'type' => 'import@ome', 'operation' => '5015@purchase', 'obj_id' => '100000005015', 'obj_name' => '100000005015-5015'),
            'purchase_purchaseReturn_import' => array('name'=> '采购-采购退货单-导入', 'type' => 'import@ome', 'operation' => '5016@purchase', 'obj_id' => '100000005016', 'obj_name' => '100000005016-5016'),
            'purchase_needRemind_import' => array('name'=> '采购-补货提醒-导入', 'type' => 'import@ome', 'operation' => '5017@purchase', 'obj_id' => '100000005017', 'obj_name' => '100000005017-5017'),
            'purchase_supplierManager_supplier_import' => array('name'=> '采购-供应商管理-供应商-导入', 'type' => 'import@ome', 'operation' => '5018@purchase', 'obj_id' => '100000005018', 'obj_name' => '100000005018-5018'),
            'taobaogoods_taobaogoods_goods_import' => array('name'=> '淘宝商品-淘宝商品-商品列表-导入', 'type' => 'import@ome', 'operation' => '5019@taoapi', 'obj_id' => '100000005019', 'obj_name' => '100000005019-5019'),
            'taobaogoods_import' => array('name'=> '淘宝商品-导入', 'type' => 'import@ome', 'operation' => '5020@taoapi', 'obj_id' => '100000005020', 'obj_name' => '100000005020-5020'),
            'taobaogoods_taobaogoods_skuList_import' => array('name'=> '淘宝商品-淘宝商品-SKU列表-导入', 'type' => 'import@ome', 'operation' => '5021@taoapi', 'obj_id' => '100000005021', 'obj_name' => '100000005021-5021'),
            'taobaogoods_taobaogoods_goodsAttribute_import' => array('name'=> '淘宝商品-淘宝商品-商品属性值-导入', 'type' => 'import@ome', 'operation' => '5022@taoapi', 'obj_id' => '100000005022', 'obj_name' => '100000005022-5022'),
            'warehouse_import' => array('name'=> '仓储-导入', 'type' => 'import@ome', 'operation' => '5023@taoguaniostockorder', 'obj_id' => '100000005023', 'obj_name' => '100000005023-5023'),
            'warehouse_enterManager_other_import' => array('name'=> '仓储-入库管理-其它入库-导入', 'type' => 'import@ome', 'operation' => '5024@taoguaniostockorder', 'obj_id' => '100000005024', 'obj_name' => '100000005024-5024'),
            'warehouse_enterManager_enterFind_import' => array('name'=> '仓储-入库管理-入库单查询-导入', 'type' => 'import@ome', 'operation' => '5025@taoguaniostockorder', 'obj_id' => '100000005025', 'obj_name' => '100000005025-5025'),
            'warehouse_outManager_other_import' => array('name'=> '仓储-出库管理-其它出库-导入', 'type' => 'import@ome', 'operation' => '5026@taoguaniostockorder', 'obj_id' => '100000005026', 'obj_name' => '100000005026-5026'),
            'warehouse_other_import' => array('name'=> '仓储-其他(出入)库-导入', 'type' => 'import@ome', 'operation' => '5027@taoguaniostockorder', 'obj_id' => '100000005027', 'obj_name' => '100000005027-5027'),
            'warehouse_outManager_outFind_import' => array('name'=> '仓储-出库管理-出库单查询-导入', 'type' => 'import@ome', 'operation' => '5028@taoguaniostockorder', 'obj_id' => '100000005028', 'obj_name' => '100000005028-5028'),
            'warehouse_dailyManager_posTidy_import' => array('name'=> '仓储-日常管理-货位整理-导入', 'type' => 'import@ome', 'operation' => '5030@ome', 'obj_id' => '100000005030', 'obj_name' => '100000005030-5030'),
            'warehouse_dailyManager_csv_import' => array('name'=> '仓储-日常管理-CSV文件导入解绑货位-导入', 'type' => 'import@ome', 'operation' => '5031@ome', 'obj_id' => '100000005031', 'obj_name' => '100000005031-5031'),
            'warehouse_stockManager_stockList_import' => array('name'=> '仓储-库存管理-仓库库存列表-导入', 'type' => 'import@ome', 'operation' => '5032@ome', 'obj_id' => '100000005032', 'obj_name' => '100000005032-5032'),
            'warehouse_stockManager_totalStockList_import' => array('name'=> '仓储-库存管理-总库存列表-导入', 'type' => 'import@ome', 'operation' => '5033@ome', 'obj_id' => '100000005033', 'obj_name' => '100000005033-5033'),
            'warehouse_stockManager_ioStockDetail_import' => array('name'=> '仓储-库存管理-出入库明细-导入', 'type' => 'import@ome', 'operation' => '5034@iostock', 'obj_id' => '100000005034', 'obj_name' => '100000005034-5034'),
            'warehouse_checkManager_check_import' => array('name'=> '仓储-盘点管理-盘点-导入', 'type' => 'import@ome', 'operation' => '5035@taoguaninventory', 'obj_id' => '100000005035', 'obj_name' => '100000005035-5035'),
            'bill_import' => array('name'=> '单据-导入', 'type' => 'import@ome', 'operation' => '5036@sales', 'obj_id' => '100000005036', 'obj_name' => '100000005036-5036'),
            'bill_salesBill_sales_import' => array('name'=> '单据-销售单据-销售单-导入', 'type' => 'import@ome', 'operation' => '5037@sales', 'obj_id' => '100000005037', 'obj_name' => '100000005037-5037'),
            'bill_salesBill_afterSales_import' => array('name'=> '单据-销售单据-售后单-导入', 'type' => 'import@ome', 'operation' => '5038@sales', 'obj_id' => '100000005038', 'obj_name' => '100000005038-5038'),
            'bill_salesBill_receipts_import' => array('name'=> '单据-销售单据-收款单-导入', 'type' => 'import@ome', 'operation' => '5039@ome', 'obj_id' => '100000005039', 'obj_name' => '100000005039-5039'),
            'bill_salesBill_refund_import' => array('name'=> '单据-销售单据-退款单-导入', 'type' => 'import@ome', 'operation' => '5040@ome', 'obj_id' => '100000005040', 'obj_name' => '100000005040-5040'),
            'bill_delivery_delivery_import' => array('name'=> '单据-发货单据-发货单-导入', 'type' => 'import@ome', 'operation' => '5041@ome', 'obj_id' => '100000005041', 'obj_name' => '100000005041-5041'),
            'bill_delivery_afterSales_import' => array('name'=> '单据-发货单据-售后入库单-导入', 'type' => 'import@ome', 'operation' => '5042@ome', 'obj_id' => '100000005042', 'obj_name' => '100000005042-5042'),
            'bill_purchase_cash_import' => array('name'=> '单据-采购单据-现购单-导入', 'type' => 'import@ome', 'operation' => '5043@purchase', 'obj_id' => '100000005043', 'obj_name' => '100000005043-5043'),
            'bill_purchase_buyOnTally_import' => array('name'=> '单据-采购单据-赊购单-导入', 'type' => 'import@ome', 'operation' => '5044@purchase', 'obj_id' => '100000005044', 'obj_name' => '100000005044-5044'),
            'bill_purchase_purchaseRefund_import' => array('name'=> '单据-采购单据-采购退款单-导入', 'type' => 'import@ome', 'operation' => '5045@purchase', 'obj_id' => '100000005045', 'obj_name' => '100000005045-5045'),
            'performance_import' => array('name'=> '绩效-导入', 'type' => 'import@ome', 'operation' => '5046@tgkpi', 'obj_id' => '100000005046', 'obj_name' => '100000005046-5046'),
            'performance_performanceStatistics_pickStreaming_import' => array('name'=> '绩效-绩效统计-捡货流水-导入', 'type' => 'import@ome', 'operation' => '5047@tgkpi', 'obj_id' => '100000005047', 'obj_name' => '100000005047-5047'),
            'performance_performanceStatistics_pickAnalysis_import' => array('name'=> '绩效-绩效统计-捡货统计-导入', 'type' => 'import@ome', 'operation' => '5048@tgkpi', 'obj_id' => '100000005048', 'obj_name' => '100000005048-5048'),
            'performance_performanceStatistics_verifyAnalysis_import' => array('name'=> '绩效-绩效统计-捡货统计-导入', 'type' => 'import@ome', 'operation' => '5049@tgkpi', 'obj_id' => '100000005049', 'obj_name' => '100000005049-5049'),
            'performance_performanceStatistics_deliveryAnalysis_import' => array('name'=> '绩效-绩效统计-发货统计-导入', 'type' => 'import@ome', 'operation' => '5050@tgkpi', 'obj_id' => '100000005050', 'obj_name' => '100000005050-5050'),
            'performance_performanceStatistics_verifyFailAnalysis_import' => array('name'=> '绩效-绩效统计-校验失败原因统计-导入', 'type' => 'import@ome', 'operation' => '5051@tgkpi', 'obj_id' => '100000005051', 'obj_name' => '100000005051-5051'),
            'report_import' => array('name'=> '报表-导入', 'type' => 'import@ome', 'operation' => '5052@omeanalysts', 'obj_id' => '100000005052', 'obj_name' => '100000005052-5052'),
            'report_salesReport_posSales_import' => array('name'=> '报表-销售报表-货品销售情况-导入', 'type' => 'import@ome', 'operation' => '5053@omeanalysts', 'obj_id' => '100000005053', 'obj_name' => '100000005053-5053'),
            'report_salesReport_goodsSales_import' => array('name'=> '报表-销售报表-商品销售情况-导入', 'type' => 'import@ome', 'operation' => '5054@omeanalysts', 'obj_id' => '100000005054', 'obj_name' => '100000005054-5054'),
            'report_salesReport_orderSales_import' => array('name'=> '报表-销售报表-订单销售情况-导入', 'type' => 'import@ome', 'operation' => '5055@omeanalysts', 'obj_id' => '100000005055', 'obj_name' => '100000005055-5055'),
            'report_salesReport_refundAnalysis_import' => array('name'=> '报表-销售报表-退货情况统计-导入', 'type' => 'import@ome', 'operation' => '5056@omeanalysts', 'obj_id' => '100000005056', 'obj_name' => '100000005056-5056'),
            'report_salesReport_shopDayAnalysis_import' => array('name'=> '报表-销售报表-店铺每日汇总-导入', 'type' => 'import@ome', 'operation' => '5057@omeanalysts', 'obj_id' => '100000005057', 'obj_name' => '100000005057-5057'),
            'report_purchaseReport_orderIncomeAnalysis_import' => array('name'=> '报表-财务报表-订单收入统计-导入', 'type' => 'import@ome', 'operation' => '5058@omeanalysts', 'obj_id' => '100000005058', 'obj_name' => '100000005058-5058'),
            'report_purchaseReport_expressAnalysis_import' => array('name'=> '报表-财务报表-快递费统计-导入', 'type' => 'import@ome', 'operation' => '5059@omeanalysts', 'obj_id' => '100000005059', 'obj_name' => '100000005059-5059'),
            'report_purchaseReport_codAnalysis_import' => array('name'=> '报表-财务报表-货到付款统计-导入', 'type' => 'import@ome', 'operation' => '5060@omeanalysts', 'obj_id' => '100000005060', 'obj_name' => '100000005060-5060'),
            'report_purchaseReport_branchDeliveryAnalysis_import' => array('name'=> '报表-财务报表-仓库发货情况统计-导入', 'type' => 'import@ome', 'operation' => '5061@omeanalysts', 'obj_id' => '100000005061', 'obj_name' => '100000005061-5061'),
            'report_purchaseReport_costAnalysis_import' => array('name'=> '报表-财务报表-费用统计-导入', 'type' => 'import@ome', 'operation' => '5062@finance', 'obj_id' => '100000005062', 'obj_name' => '100000005062-5062'),
            'report_purchaseReport_stockCostAnalysis_import' => array('name'=> '报表-财务报表-库存成本统计-导入', 'type' => 'import@ome', 'operation' => '5063@tgstockcost', 'obj_id' => '100000005063', 'obj_name' => '100000005063-5063'),
            'report_purchaseReport_stockSummaryAnalysis_import' => array('name'=> '报表-财务报表-进销存统计-导入', 'type' => 'import@ome', 'operation' => '5064@tgstockcost', 'obj_id' => '100000005064', 'obj_name' => '100000005064-5064'),
            'report_analysisReport_goodsSale_import' => array('name'=> '报表-分析报表-商品销售排行-导入', 'type' => 'import@ome', 'operation' => '5065@omeanalysts', 'obj_id' => '100000005065', 'obj_name' => '100000005065-5065'),
            'report_analysisReport_storeStatusAnalysis_import' => array('name'=> '报表-分析报表-库存状况分析-导入', 'type' => 'import@ome', 'operation' => '5066@tgstockcost', 'obj_id' => '100000005066', 'obj_name' => '100000005066-5066'),
    
            //67已经使用


            //调拨单
            'taoguanallocate_appropriation_addtransfer_allocation' => array('name'=> '仓库-调拨管理-调拨单-新建', 'type' => 'allocation@taoguanallocate', 'operation' => '8001@tgstockcost', 'obj_id' => '100000008001', 'obj_name' => '100000008001-8001'),
            
            //地区映射管理
            'region_mapping_create' => array('name'=> '新建地区映射','type' => 'region_mapping@miele'),
            'region_mapping_update' => array('name'=> '编辑地区映射','type' => 'region_mapping@miele'),
            'region_mapping_delete' => array('name'=> '删除地区映射','type' => 'region_mapping@miele'),
            
            'none' => array('name' => '未知类型', 'type' => 'none', 'operation' => 'none', 'obj_id' => '100000009001', 'obj_name' => '100000009001-9001'),
        );
        $item = array();

        if ($type) {
            $item = isset($itemType[$type]) ? $itemType[$type] : '';
        }
        else {
            $item = $itemType;
        }
        return $item;
    }

    /**
     * 插入日志
     * @param String $type 日志类型
     * @param Array $params
     * @param String $memo 备注
     * @param Time $operateTime 操作时间
     */
    public static function insert($type, $params = '', $memo = '', $operateTime = '', $ip = '', $opinfo = array(), $item = array()) {

        if (empty($item)) {
            $item = self::getIOItem($type);
        }
        
        if (empty($item)) {
            $type = 'none';
            $item = self::getIOItem($type);
        }
        
        if (empty($opinfo)) {
            $opinfo = kernel::single('ome_func')->getDesktopUser();
        }
        if (empty($ip)) {
            $ip = kernel::single("base_request")->get_remote_addr();
        }
        if (empty($memo)) {
            $memo = $item['name'];
        }
        if ($params) {
            $memo .= '{|||}' . serialize($params);
        }
        $data = array(
            'obj_id' => $item['obj_id'],
            'obj_name' => $item['obj_name'],
            'obj_type' => $item['type'],
            'operation' => $item['operation'],
            'op_id' => $opinfo['op_id'],
            'op_name' => $opinfo['op_name'],
            'operate_time' => $operateTime ? $operateTime : time(),
            'memo' => $memo,
            'ip' => $ip,
        );
        app::get('ome')->model('operation_log')->save($data);
        return $data['log_id'];
    }
    /**
     * 获得日志类型
     */
    public static function getType() {
        $type = array(
            'import@ome' => '导入',
            'export@ome' => '导出',
            'orders@ome' => '订单',
            'reship@ome' => '售后',
            'refund_apply@ome' => '退款',
            'payments@ome' => '支付',
            'branch@ome' => '仓库',
            'delivery@ome' => '发货单',
            'goods@ome' => '商品',
            'order@invoice' => '发票',
            'allocation@taoguanallocate' => '调拨单',
            'account@pam' => '登陆',
            'region_mapping@miele' => '地区映射',
            
            'basic_material_storage_life@material' => '调整保质期',
        );
        return $type;
    }
    /**
     * 获得类型对应关系列表
     * @param String $type 操作类型
     */
    public function getTypeMap($type = '') {
        $typeList = array(
            'import@ome' => array('import@ome'),
            'export@ome' => array('export@ome'),
            'orders@ome' => array('orders@ome'),
            'reship@ome' => array('return_product@ome', 'reship@ome'),
            'refund_apply@ome' => array('refund_apply@ome'),
            'payments@ome' => array('payments@ome'),
            'branch@ome' => array('branch@ome', 'branch_pos@ome'),
            'delivery@ome' => array('delivery@ome'),
            'goods@ome' => array('goods@ome'),
            'order@invoice' => array('order@invoice'),
            'allocation@taoguanallocate' => array('allocation@taoguanallocate'),
            'account@pam' => array('account@pam'),
            'region_mapping@miele' => array('region_mapping@miele'),
            
            'basic_material_storage_life@material' => array('basic_material_storage_life@material'),
        );
        if ($type) {
            $list = isset($typeList[$type]) ? $typeList[$type] : $typeList;
        }
        else {
            $list = $typeList;
        }
        $map = array();
        foreach ($list as $v) {
            if (is_array($v)) {
                foreach ($v as $v1) {
                    if ($v1) {
                        $map[] = $v1;
                    }
                }
            }
            elseif (is_string($v) && $v) {
                $map[] = $v;
            }
        }
        return $map;
    }
    
}
?>