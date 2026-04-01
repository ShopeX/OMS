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

class ome_finder_extend_filter_orders{
    function get_extend_colums(){
        $pay_type = ome_payment_type::pay_type();
        $cfgObj = app::get('ome')->model('payment_cfg');
        $payments = $cfgObj->getList('*');
        $pay_bn = array();
        foreach($payments as $payment){
            $pay_bn[$payment['pay_bn']] = $payment['custom_name'];
        }
        $order_bool_list =kernel::single('ome_order_bool_type')->getOrder_type_list();
        $shopName = array_column(app::get('ome')->model('shop')->getList('name,shop_id'),'name','shop_id');
        
        //物流服务标签列表
        $axOrderLib = kernel::single('dchain_order');
        $promise_services = $axOrderLib->getPromiseServiceList();
    
        //运营组织
        $orgList = app::get('ome')->model('operation_organization')->getList('org_id,name,code');
        $orgData = [];
        foreach($orgList as $info) {
            $orgData[$info['org_id']] = $info['name'];
        }
        
        //dbschema
        $db['orders']=array (
            'columns' => array (
                'shop_id' => array(
                    'type'          => $shopName,
                    'label'         => '来源店铺',
                    'width'         => 100,
                    'editable'      => false,
                    'in_list'       => true,
                    'filtertype'    => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                ),
                'ship_area'          => array(
                    'type'          => 'varchar(255)',
                    'label'         => '收货地区',
                    'width'         => 170,
                    'editable'      => false,
                    'filtertype'    => 'textarea',
                    'filterdefault' => true,
                    'sdfpath'       => 'consignee/area',
                    'in_list'       => true,
                ),
                'has_area'          => array(
                    'type'          => 'bool',
                    'default'       => 'true',
                    'editable'      => false,
                    'in_list'       => true,
                    'label'         => '是否包含收货地区',
                    'width'         => 60,
                    'filtertype'    => 'yes',
                    'filterdefault' => true,
                ),
                'ship_status' => array (
                    'type' => array (
                        0 => '未发货',
                        1 => '已发货',
                        2 => '部分发货',
                        3 => '部分退货',
                        4 => '已退货'
                    ),
                    'default' => '0',
                    'required' => true,
                    'label' => '发货状态',
                    'width' => 75,
                    'editable' => false,
                    'filtertype' => 'yes',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => false,
                ),
                'shipping' =>
                array (
                        'type' => 'varchar(100)',
                        'label' => '配送方式',
                        'width' => 75,
                        'editable' => false,
                        'filtertype' => 'yes',
                        'filterdefault' => true,
                        'in_list' => true,
                ),
                'is_cod' =>
                array (
                        'type' => 'bool',
                        'required' => true,
                        'default' => 'false',
                        'editable' => false,
                        'label' => '货到付款',
                        'in_list' => true,
                        'width' => 60,
                        'filtertype' => 'yes',
                        'filterdefault' => true,
                ),
                'ship_tel_mobile' => array (
                    'type' => 'varchar(30)',
                    'label' => '收货人联系电话',
                    'comment' => '收货人联系电话',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'in_list' => true,
                ),
                'pay_type' => array (
                    'type' => $pay_type,
                    'label' => '支付类型',
                    'width' => 65,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => false,
                ),
                'pay_bn' => array (
                    'type' => $pay_bn,
                    'label' => '支付方式',
                    'width' => 65,
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => false,
                ),
                'member_uname' => array (
                  'type' => 'varchar(50)',
                  'label' => '会员用户名',
                  'width' => 75,
                  'editable' => false,
                  'filtertype' => 'normal',
                  'filterdefault' => 'true',
                  'in_list' => true,
                  'default_in_list' => true,
                ),
                'product_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '基础物料编码',
                    'width' => 85,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'product_barcode' => array (
                    'type' => 'varchar(32)',
                    'label' => '条形码',
                    'width' => 110,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'sales_material_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '销售物料编码',
                    'width' => 85,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'sales_material_name' => array (
                    'type' => 'varchar(200)',
                    'label' => '销售物料名称',
                    'width' => 120,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'is_tax_no' => array(
                      'type' =>
                      array (
                        0 => '否',
                        1 => '是',
                      ),
                    'label' => '是否录入发票号',
                    'width' => 100,
                    'filtertype' => 'yes',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),

                'logi_status'=>
                array (
                  'type' => array(
                      '0' => '无',
                      '1' => '已揽收',
                      '2' => '在途中',
                      '3' =>'已签收',
                      '4' =>'退件/问题件',
                      '5' =>'待取件',
                      '6' =>'待派件',
                  ),
                  'default' => '0',
                  'label' => '物流跟踪状态',
                  'editable' => false,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'searchtype' => 'nequal',
                    'in_list' => true,
                    'default_in_list' => true,
                ),

                'order_source' => array(
                  'type' => ome_mdl_orders::$order_source,
                  'label' => '来源渠道',
                  'filtertype' => 'yes',
                  'filterdefault' => true,
                  'in_list' => true,
                  'default_in_list' => true,
                ),
                'paytime' => array(
                    'type'  => 'time',
                    'label' => '付款时间',
                    'width' => 130,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'tax_company' => array(
                    'type'  => 'varchar(255)',
                    'label' => '发票抬头',
                    'width' => 100,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'mark_text' =>array (
                    'type' => 'longtext',
                    'label' => '商家备注',
                    'width' => 100,
                    'editable' => false,
                    'in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'default_in_list'=>true
                ),
                'is_mark_text'             => array(
                    'type'          => 'bool',
                    'default'       => true,
                    'editable'      => false,
                    'in_list'       => true,
                    'label'         => '是否有商家备注',
                    'width'         => 60,
                    'filtertype'    => 'yes',
                    'filterdefault' => true,
                ),
                'custom_mark' =>array (
                    'type' => 'longtext',
                    'label' => '客户备注',
                    'width' => 100,
                    'editable' => false,
                    'in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'default_in_list'=>true
                ),
                'is_custom_mark'             => array(
                    'type'          => 'bool',
                    'default'       => true,
                    'editable'      => false,
                    'in_list'       => true,
                    'label'         => '是否有客户备注',
                    'width'         => 60,
                    'filtertype'    => 'yes',
                    'filterdefault' => true,
                ),
                'is_relate_order_bn' => array(
                      'type' =>
                      array (
                        0 => '否',
                        1 => '是',
                      ),
                    'label' => '是否录入关联订单号',
                    'width' => 100,
                    'filtertype' => 'yes',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'order_bool_type'=>array(
                    'type'  =>  $order_bool_list,
                    'label' => '交易类型',
                    'filtertype' => 'yes',
                    'filterdefault' => true,
                    'editable' => false,
                ),
                'order_label' => array (
                    'type' => 'table:order_labels@omeauto',
                    'label' => '订单标记',
                    'width' => 120,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'promise_service' => array(
                    'type' => $promise_services,
                    'label' => '物流服务标签',
                    'width' => 100,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => false,
                    'default_in_list' => false,
                ),
                'org_id'             => array(
                    'type'            => $orgData,
                    'label'           => '运营组织',
                    'editable'        => false,
                    'width'           => 60,
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'in_list'         => true,
                    'default_in_list' => true,
                ),
                'source_status'      => array(
                    'type'            => array(
                        'TRADE_NO_CREATE_PAY'      => '没有创建支付宝交易',
                        'WAIT_BUYER_PAY'           => '等待买家付款',
                        'PAID_DEALING'             => '已支付处理中',
                        'CLEAR_CUSTOMS'            => '清关中',
                        'WAIT_SELLER_SEND_GOODS'   => '等待卖家发货,即:买家已付款',
                        'SELLER_READY_GOODS'       => '卖家备货中',
                        'SELLER_CONSIGNED_PART'    => '卖家部分发货',
                        'WAIT_BUYER_CONFIRM_GOODS' => '等待买家确认收货,即:卖家已发货',
                        'TRADE_BUYER_SIGNED'       => '买家已签收,货到付款专用',
                        'TRADE_FINISHED'           => '交易成功',
                        'TRADE_CLOSED'             => '交易取消',
                        'TRADE_CLOSED_BY_TAOBAO'   => '付款以前，卖家或买家主动关闭交易',
                        'PAY_PENDING'              => '国际信用卡支付付款确认中',
                        'WAIT_PRE_AUTH_CONFIRM'    => '0元购合约中',
                        'PAID_FORBID_CONSIGN'      => '拼团中订单、POP暂停或者发货强管控的订单，已付款但禁止发货',
                        'WAIT_SEND_CODE'           => '等待发码（LOC订单特有状态）',
                        'DELIVERY_RETURN'          => '配送退货',
                        'UN_KNOWN'                 => '未知 请联系运营',
                        'TRADE_RETURNING'          => '退换货申请',
                    ),
                    'label'           => '平台状态',
                    'editable'        => false,
                    'width'           => 120,
                    'in_list'         => true,
                    'default_in_list' => false,
                    'filtertype' => 'yes',
                    'filterdefault' => true,
                ),
                'latest_delivery_time' => array(
                    'type'  => 'time',
                    'label' => '最晚发货时间',
                    'width' => 130,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'seller_code' => array(
                    'type'  => 'varchar(50)',
                    'label' => '销售人员编码',
                    'width' => 120,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'seller_name' => array(
                    'type'  => 'varchar(50)',
                    'label' => '销售人员名称',
                    'width' => 160,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
            )
        );
        #只有财务那边的才用这个签收
        if($_GET['ctl'] != 'admin_finance'){
            unset($db['orders']['columns']['logi_status']);
        }
        return $db;
    }
}

