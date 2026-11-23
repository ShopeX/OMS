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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
class desktop_init{

    public $export_temps = array(
        array('extmp_name'=>'标准模板-全部', 'type'=>'ome_mdl_orders', 'content'=>'is_auto,column_print_status,pay_status,relate_order_bn,order_bn,createway,mark_type,column_fail_status,column_customer_add,column_custom_add,process_status,ship_status,consigner_name,is_modify,ship_addr,member_id,shipping,op_id,shop_id,logi_no,tax_no,consigner_zip,abnormal,cost_item,is_cod,payment,ship_zip,createtime,paytime,last_modified,modifytime,download_time,column_deff_time,outer_lastmodify,column_tax_no,consigner_area,consigner_addr,ship_tel,pause,payed,cost_tax,consigner_mobile,shop_type,ship_mobile,ship_area,logi_id,group_id,dispatch_time,is_tax,total_amount,cost_freight,ship_name,self_delivery,order_source,column_abnormal_type_name,column_tax_company', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omedlyexport_mdl_ome_delivery', 'content'=>'custom_mark,mark_text,tax_no,branch_id,ship_area,shop_id,delivery_time,ship_addr,ship_zip,ship_tel,delivery_bn,ship_name,ship_mobile,logi_name,order_bn,member_id,logi_no,ident,freight', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'ome_mdl_goods', 'content'=>'bn,unit,barcode,visibility,name,brand_id,brief,weight,column_picurl,type_id,goods_type,serial_number,price,cost', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'iostock_mdl_iostocksearch', 'content'=>'bn,column_name,branch_id,inventory_cost,now_num,unit_cost,balance_nums,now_unit_cost,type_id,now_inventory_cost,operator,column_nums,iostock_price,memo,oper,column_supplier,original_bn,column_branch_id,create_time,iostock_bn,column_brand_name', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'sales_mdl_sales', 'content'=>'member_id,branch_id,shop_id,discount,additional_costs,delivery_cost,delivery_cost_actual,sale_amount,sale_time,ship_time,sale_bn,order_check_id,total_amount,cost_freight,payment,order_create_time,paytime,is_tax,order_check_time,column_order_id,archive', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_goodsale', 'content'=>'order_bn,branch_id,goods_name,product_bn,brand_name,type_name,goods_specinfo,buycount,sale_amount,createtime,shop_id,obj_type', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-出入库数量统计', 'type'=>'iostock_mdl_iostocksearch', 'content'=>'bn,column_name,branch_id,now_num,balance_nums,type_id,column_nums,iostock_price,column_supplier,original_bn,column_branch_id,create_time,iostock_bn,column_brand_name', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-发货单明细', 'type'=>'omedlyexport_mdl_ome_delivery', 'content'=>'branch_id,ship_area,shop_id,delivery_time,ship_addr,ship_zip,ship_tel,delivery_bn,ship_name,ship_mobile,logi_name,logi_no,ident,freight', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-发货明细', 'type'=>'ome_mdl_orders', 'content'=>'pay_status,relate_order_bn,order_bn,ship_status,ship_addr,member_id,logi_no,ship_tel,ship_mobile,ship_area,logi_id,ship_name', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-商品销售明细', 'type'=>'omeanalysts_mdl_ome_goodsale', 'content'=>'order_bn,goods_name,product_bn,buycount,sale_amount,createtime,shop_id', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-订单明细', 'type'=>'ome_mdl_orders', 'content'=>'pay_status,relate_order_bn,order_bn,column_customer_add,column_custom_add,ship_status,ship_addr,member_id,shop_id,logi_no,tax_no,cost_item,payment,ship_zip,createtime,paytime,ship_tel,payed,cost_tax,ship_mobile,ship_area,logi_id,is_tax,total_amount,cost_freight,ship_name,order_source,column_tax_company', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-销售单明细', 'type'=>'sales_mdl_sales', 'content'=>'member_id,branch_id,shop_id,discount,additional_costs,sale_amount,sale_time,ship_time,sale_bn,total_amount,cost_freight,payment,order_create_time,paytime,is_tax,column_order_id', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_products', 'content'=>'type_id,brand,goods_bn,bn,name,goods_specinfo,sale_price,sale_num,sale_amount,day_amount,day_num,reship_num,reship_ratio,reship_total_amount,agv_cost_amount,cost_amount,agv_gross_sales,gross_sales,gross_sales_rate,column_obj_type', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_sales', 'content'=>'shop_id,order_id,sale_bn,products_type,product_nums,total_amount,goods_sales_price,discount,cost_freight,sale_amount,cost_amount,delivery_cost_actual,gross_sales,gross_sales_rate,order_create_time,paytime,ship_time,branch_id,additional_costs,payment,member_id,logi_no,ship_area', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_aftersale', 'content'=>'shop_id,order_id,reship_id,aftersale_time,return_type,problem_name,goods_type,brand_name,goods_bn,product_name,goods_specinfo,product_bn,aftersale_num,saleprice,return_price,apply_money,refundmoney,refund_apply_id,refundtime,branch_id,sale_cost', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_shop', 'content'=>'shop_name,time,sale_order,sale_num,sale_amount,aftersale_order,aftersale_num,aftersale_amount,total_amount', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-销售额统计', 'type'=>'omeanalysts_mdl_ome_products', 'content'=>'type_id,brand,goods_bn,bn,name,goods_specinfo,sale_price,sale_num,sale_amount,day_amount,day_num,column_obj_type', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-退货统计', 'type'=>'omeanalysts_mdl_ome_products', 'content'=>'type_id,brand,goods_bn,bn,name,goods_specinfo,reship_num,reship_ratio,reship_total_amount,column_obj_type', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-销售额统计', 'type'=>'omeanalysts_mdl_ome_sales', 'content'=>'shop_id,order_id,sale_bn,products_type,product_nums,total_amount,goods_sales_price,discount,cost_freight,sale_amount,order_create_time,paytime,additional_costs,payment,member_id', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-退货货品明细', 'type'=>'omeanalysts_mdl_ome_aftersale', 'content'=>'shop_id,reship_id,aftersale_time,return_type,problem_name,product_name,product_bn,aftersale_num,return_price,apply_money,refundmoney,refund_apply_id,refundtime,branch_id', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_delivery', 'content'=>'branch_id,shop_id,balance,delivery_time,logi_name,cost_protect,ship_tel,ship_name,delivery_cost_actual,weight,ship_area,ship_mobile,ship_addr,is_cod,total_amount,logi_no,delivery_bn,order_bn,cost_freight', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_cod', 'content'=>'branch_id,shop_id,receivables,balance,delivery_time,logi_name,cost_protect,ship_tel,ship_mobile,delivery_cost_actual,weight,ship_addr,ship_name,is_cod,ship_area,logi_no,total_amount,delivery_bn,order_bn', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_branchdelivery', 'content'=>'branch_name,goods_type,brand_name,goods_bn,product_bn,product_name,goods_specinfo,sale_num,aftersale_num,shop_id,total_nums', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'tgstockcost_mdl_branch_product', 'content'=>'branch_id,type_name,brand_name,goods_bn,product_bn,product_name,spec_info,start_nums,in_nums,out_nums,sale_out_nums,store', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_goodsrank', 'content'=>'rownum,type_id,name,bn,sale_num,sale_amount,reship_num,reship_ratio,gross_sales,gross_sales_rate', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_storeStatus', 'content'=>'branch_name,brand_id,bn,name,turnover_rate,sale_store,sale_day', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'omeanalysts_mdl_ome_income', 'content'=>'bill_time,paymethod,shop_id,bill_amount,bill_type,order_id,bill_id', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-运费汇总', 'type'=>'omeanalysts_mdl_ome_delivery', 'content'=>'branch_id,balance,delivery_time,logi_name,cost_protect,ship_name,delivery_cost_actual,weight,ship_area,logi_no,order_bn,cost_freight', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-订单汇总', 'type'=>'omeanalysts_mdl_ome_cod', 'content'=>'shop_id,balance,delivery_time,logi_name,ship_tel,ship_mobile,ship_name,is_cod,ship_area,logi_no,total_amount,delivery_bn', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-应收货款汇总', 'type'=>'omeanalysts_mdl_ome_cod', 'content'=>'branch_id,shop_id,receivables,balance,delivery_time,logi_name,cost_protect,delivery_cost_actual,ship_name,is_cod,ship_area,logi_no,total_amount,delivery_bn', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-退货数量汇总', 'type'=>'omeanalysts_mdl_ome_branchdelivery', 'content'=>'branch_name,goods_type,brand_name,goods_bn,product_bn,product_name,goods_specinfo,aftersale_num,shop_id', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-结存商品成本汇总', 'type'=>'tgstockcost_mdl_branch_product', 'content'=>'branch_id,type_name,brand_name,goods_bn,product_bn,product_name,spec_info,store,unit_cost,inventory_cost', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-商品销售汇总', 'type'=>'omeanalysts_mdl_ome_goodsrank', 'content'=>'rownum,type_id,name,bn,sale_num,sale_amount', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'finance_mdl_bill_order', 'content'=>'order_bn,channel_name,column_trade,column_plat,column_branch,column_delivery,column_other,column_total', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'finance_mdl_ar_statistics', 'content'=>'ar_bn,channel_name,trade_time,member,type,order_bn,relate_order_bn,money,charge_time,column_items_nums,column_fee_money', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'finance_mdl_analysis_bills', 'content'=>'shop_id,fee_item_id,tid,oid,biz_time,total_amount,amount,pay_time,obj_alipay_mail,book_time,status', 'need_detail'=>1),
        array('extmp_name'=>'标准模板-全部', 'type'=>'finance_mdl_analysis_book_bills', 'content'=>'shop_id,shop_type,fee_item_id,bid,journal_type,amount,book_time,gmt_create', 'need_detail'=>1),
    );

    //初始化系统导出的标准模板
    public function addDefaultExportStandardTemplate(){

        $extempObj = app::get('desktop')->model('export_template');
        foreach($this->export_temps as $export_temp){
            $data = array(
                'et_name' => $export_temp['extmp_name'],
                'et_type' => $export_temp['type'],
                'et_filter' => serialize(array('fields'=>$export_temp['content'],'need_detail'=>$export_temp['need_detail']))
            );

            $extempObj->save($data);
            unset($data);
        }
    }
}
