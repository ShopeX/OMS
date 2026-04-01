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
 * 导出白名单
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

class ome_export_whitelist
{

    static public function allowed_lists($source=''){
        $data_source = array(
            'ome_mdl_orders' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'order_id', 'structure'=>'multi'),
            'sales_mdl_sales' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'sale_id', 'structure'=>'single'),
            'ome_mdl_goods' => array('cansplit'=>0, 'splitnums'=>200),
            'iostock_mdl_iostocksearch' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'iostock_id', 'structure'=>'single'),
            'omedlyexport_mdl_ome_delivery' => array('cansplit'=>1, 'splitnums'=>100, 'primary_key' => 'delivery_id', 'structure'=>'spec'),
            'wms_mdl_inventory' => array('cansplit'=>0, 'splitnums'=>200),
            'omeanalysts_mdl_ome_goodsale' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'item_id', 'structure'=>'single'),
            'omeanalysts_mdl_ome_products' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'omeanalysts_mdl_ome_sales' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'omeanalysts_mdl_ome_aftersale' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'item_id', 'structure'=>'single'),
            'omeanalysts_mdl_ome_shop' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'omeanalysts_mdl_ome_income' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'omeanalysts_mdl_ome_cod' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'delivery_id', 'structure'=>'single'),
            'omeanalysts_mdl_ome_branchdelivery' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'tgstockcost_mdl_costselect' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'tgstockcost_mdl_branch_product' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'omeanalysts_mdl_ome_goodsrank' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'omeanalysts_mdl_ome_storeStatus' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'omeanalysts_mdl_ome_delivery' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'finance_mdl_bill' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'bill_id', 'structure'=>'single'),
            'finance_mdl_bill_order' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'finance_mdl_ar' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'ar_id', 'structure'=>'single'),
            'finance_mdl_ar_statistics' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'finance_mdl_analysis_bills' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'finance_mdl_analysis_book_bills' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'finance_mdl_monthly_report_items'=> array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'id', 'structure'=>'single'),
            'financebase_mdl_base'=> array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'id', 'structure'=>'single'),
            'financebase_mdl_expenses_rule'=> array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'rule_id', 'structure'=>'single'),
            'console_mdl_branch_product' => array('cansplit'=>0, 'splitnums'=>200),
            'wms_mdl_branch_product' => array('cansplit'=>0, 'splitnums'=>200),
            'drm_mdl_distributor_product_sku' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'inventorydepth_mdl_shop_frame' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'ome_mdl_reship' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'reship_id', 'structure'=>'single'),
            'invoice_mdl_order' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'id', 'structure'=>'single'),
            'wms_mdl_delivery'=>array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'delivery_id', 'structure'=>'spec'),
            'wms_mdl_delivery_outerlogi'=>array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'delivery_id', 'structure'=>'spec'),
            'taoguaniostockorder_mdl_iso'=>array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'iso_id', 'structure'=>'multi'),
            'ome_mdl_reship_refuse' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'reship_id', 'structure'=>'single'),
            'console_mdl_inventory_apply' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'inventory_apply_id', 'structure'=>'multi'),
            'console_mdl_interface_iostocksearchs' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'console_mdl_adjust' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'id', 'structure'=>'multi'),
            'ome_mdl_payments' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'payment_id', 'structure'=>'single'),
            'ome_mdl_analysis_stocknsale' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'ome_mdl_analysis_productnsale' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'archive_mdl_orders' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'order_id', 'structure'=>'multi'),
            'o2o_mdl_product_store' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'id', 'structure'=>'single'),
            'o2o_mdl_inventory' => array('cansplit'=>0, 'splitnums'=>200),
            'o2o_mdl_store_daliy' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'sd_id', 'structure'=>'single'),
            'ome_mdl_branch_product' => array('cansplit'=>0, 'splitnums'=>200),
            'material_mdl_basic_material' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'bm_id', 'structure'=>'single'),
            'console_mdl_basic_material'=>array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'material_mdl_sales_material' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'sm_id', 'structure'=>'single'),
            'console_mdl_pick_stockout_bills' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'stockout_id', 'structure'=>'multi'),
            'purchase_mdl_po' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'po_id', 'structure'=>'multi'),
            'console_mdl_stockdump' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'stockdump_id', 'structure'=>'multi'),
            'ome_mdl_return_iostock'=>array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'item_id', 'structure'=>'single'),
            'ome_mdl_gift_logs'=>array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'id', 'structure'=>'single'),
            'inventorydepth_mdl_shop_adjustment' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'ome_mdl_product_serial_history' => array('cansplit'=>1,'splitnums'=>200,'primary_key' => 'history_id','structure'=>'single'),
            'wms_mdl_product_serial' => array('cansplit'=>1,'splitnums'=>200,'primary_key' => 'serial_id','structure'=>'single'),
            'sales_mdl_aftersale' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'aftersale_id', 'structure'=>'single'),
            'console_mdl_basic_material_stock_artificial_freeze'=>array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'bmsaf_id', 'structure'=>'single'),
            'presale_mdl_orders' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'order_id', 'structure'=>'multi'),
            'financebase_mdl_expenses_split' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'id', 'structure'=>'single'),
            'financebase_mdl_expenses_unsplit' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'id', 'structure'=>'single'),
            'financebase_mdl_cainiao' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'id', 'structure'=>'single'),
            'sales_mdl_delivery_order' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'delivery_id', 'structure'=>'multi'),
            'sales_mdl_delivery_order_item' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'id', 'structure'=>'single'),
            'omeanalysts_mdl_ome_orderDiscounts' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'inventorydepth_mdl_shop_mapping' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'material_mdl_basic_material_channel' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'console_mdl_delivery_package' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'package_id', 'structure'=>'single'),
            'console_mdl_delivery_bill' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'log_id', 'structure'=>'single'),
            'ome_mdl_goods_type' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'purchase_mdl_supplier' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'crm_mdl_gift_rule_logs' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'brush_mdl_delivery' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'delivery_id', 'structure'=>'single'),
            'omeanalysts_mdl_sales_goods' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'omeanalysts_mdl_sales_products' => array('cansplit'=>1, 'splitnums'=>200, 'structure'=>'single'),
            'console_mdl_vopbill_amount'=>array('cansplit'=>1, 'splitnums'=>500, 'primary_key' => 'id', 'structure'=>'single'),
            'console_mdl_vopbill_discount'=>array('cansplit'=>1, 'splitnums'=>500, 'primary_key' => 'id', 'structure'=>'single'),
            'console_mdl_difference_receiving_inventory'=>array('cansplit'=>1, 'splitnums'=>500, 'primary_key' => 'diff_id', 'structure'=>'single'),
            'ome_mdl_return_product' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'return_id', 'structure'=>'spec'),
            'ome_mdl_order_fail' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'order_id', 'structure'=>'single'),
            'desktop_mdl_users' => array('cansplit'=>0, 'splitnums'=>200),
            'invoice_mdl_order_golden3CancelExport' => array('cansplit' => 1, 'splitnums' => 200, 'primary_key' => 'id', 'structure' => 'single'),
            'ome_mdl_shop' => array('cansplit' => 1, 'splitnums' => 200, 'primary_key' => 'shop_id', 'structure' => 'single'),
            'material_mdl_fukubukuro_combine' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'combine_id', 'structure'=>'single'),
            'vop_mdl_po' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'po_id', 'structure'=>'single'),
            'ediws_mdl_refundinfo' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'refundinfo_id', 'structure'=>'single'),
            'dealer_mdl_goods_price' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key'=>'id', 'structure'=>'single'),
            'o2o_mdl_branch_products' => array('cansplit'=>0, 'splitnums'=>200),
            'o2o_mdl_store' => array('cansplit'=>1, 'splitnums'=>200, 'primary_key' => 'store_id', 'structure'=>'single'),
            'tongyioil_mdl_sales' => array(
                'cansplit' => 1,           // 支持数据分片
                'splitnums' => 200,        // 每片200条数据
                'primary_key' => 'id',     // 主键字段
                'structure' => 'multi'     // 多层结构：主表+明细表
            ),
            'tongyioil_mdl_aftersale' => array(
                'cansplit' => 1,
                'splitnums' => 200,
                'primary_key' => 'id',
                'structure' => 'multi'
            ),
            'tongyioil_mdl_bill' => array(
                'cansplit' => 1,
                'splitnums' => 200,
                'primary_key' => 'id',
                'structure' => 'multi'
            ),
        );
        // 增加service调用，允许通过service扩展导出白名单
        $service_list = kernel::servicelist('ome.service.export.whitelist.allowed.after');
        if ($service_list) {
            foreach ($service_list as $service) {
                if (method_exists($service, 'extend_whitelist')) {
                    $extend = $service->extend_whitelist();
                    if (is_array($extend)) {
                        $data_source = array_merge($data_source, $extend);
                    }
                }
            }
        }
        if(!empty($source)){
            return isset($data_source[$source]) ? $data_source[$source] : '';
        }else{
            return $data_source;
        }
    }
}
