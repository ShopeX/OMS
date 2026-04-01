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

$db['order_objects'] = array(
    'columns' => array(
        'obj_id'            => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'editable' => false,
            'comment' => '自增主键ID'
        ),
        'order_id'          => array(
            'type'     => 'table:orders@ome',
            'required' => true,
            'default'  => '0',
            'editable' => false,
            'comment' => '订单ID,关联ome_orders.order_id'
        ),
        'obj_type'          => array(
            'type'     => 'varchar(50)',
            'default'  => '',
            'required' => true,
            'editable' => false,
            'comment' => '订单对象类型,可选值:goods(商品),pkg(捆绑商品),gift(赠品),giftpackage(礼包),lkb(福袋),pko(多选一)',
        ),
        'obj_alias'         => array(
            'type'     => 'varchar(255)',
            'editable' => false,
            'comment' => '订单对象别名',
        ),
        'shop_goods_id'     => array(
            'type'     => 'varchar(50)',
            'required' => true,
            'default'  => 0,
            'editable' => false,
            'comment' => '平台商品ID'
        ),
        'goods_id'          => array(
            'type'     => 'int unsigned',
            'required' => true,
            'default'  => 0,
            'editable' => false,
            'comment' => '销售物料ID,关联material_sales_material.sm_id'
        ),
        'bn'                => array(
            'type'     => 'varchar(40)',
            'editable' => false,
            'is_title' => true,
            'comment' => '销售物料编码',
        ),
        'name'              => array(
            'type'     => 'varchar(200)',
            'editable' => false,
            'comment' => '平台商品名称',
        ),
        'price'             => array(
            'type'     => 'money',
            'default'  => '0',
            'required' => true,
            'editable' => false,
            'comment' => '子单零售价',
        ),
        'amount'            => array(
            'type'     => 'money',
            'default'  => '0',
            'required' => true,
            'editable' => false,
            'comment' => '子单零售小价, 公式: price * quantity'
        ),
        'quantity'          => array(
            'type'     => 'number',
            'default'  => 1,
            'required' => true,
            'editable' => false,
            'comment'  => '子单购买数量',
        ),
        'weight'            => array(
            'type'     => 'money',
            'editable' => false,
            'comment' => '子单重量,单位:g',
        ),
        'score'             => array(
            'type'     => 'number',
            'editable' => false,
            'comment' => '子单积分',
        ),
        'pmt_price'         => array(
            'type'     => 'money',
            'default'  => '0',
            'editable' => false,
            'comment' => '子单优惠小计',
        ),
        'sale_price'        => array(
            'type'     => 'money',
            'default'  => '0',
            'editable' => false,
            'comment' => '子单销售价,公式:amount - pmt_price'
        ),
        'oid'               => array(
            'type'     => 'varchar(50)',
            'default'  => 0,
            'editable' => false,
            'label'    => '子订单号',
        ),
        'is_oversold'       => array(
            'type'    => 'tinyint(1)',
            'default' => 0,
            'comment' => '是否超卖,0:否,1:是',
        ),
        'promotion_id'      => array(
            'type'     => 'varchar(32)',
            'editable' => false,
            'comment' => '平台优惠ID'
        ),
        'divide_order_fee'  => array(
            'type'     => 'money',
            'editable' => false,
            'label'    => '分摊之后的实付金额',
            'comment' => '子单实付,公式: sale_price-part_mjz_discount',
        ),
        'part_mjz_discount' => array(
            'type'     => 'money',
            'editable' => false,
            'label'    => '优惠分摊',
            'comment' => '子单优惠分摊,按价格贡献比分摊pmt_order'
        ),
        'platform_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '平台承担金额（不包含支付优惠）',
        ),
        'settlement_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '结算金额',//客户实付 + 平台支付总额
        ),
        'actually_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '客户实付',// 已支付金额 减去平台支付优惠，加平台支付总额
        ),
        'platform_pay_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '支付优惠金额',
        ),
        'refund_money' => array (
          'type' => 'money',
          'editable' => false,
          'label' => '退款金额',
          'default'=>0,
          'comment' => '子单退款金额',
        ), 
        'store_code'        => array(
            'type'     => 'varchar(64)',
            'default'  => '',
            'editable' => false,
            'label'    => '预选仓库编码',
        ),
        'is_wms_gift'       => array(
            'type'            => 'bool',
            'default'         => 'false',
            'label'           => '是否WMS赠品',
            'editable'        => false,
            'filtertype'      => 'normal',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'estimate_con_time' => array(
            'type'     => 'int unsigned',
            'required' => true,
            'default'  => 0,
            'editable' => false,
            'label'    => '预售发货时间',
        ),
        'ship_status'       => array(
            'type'    => array(
                0 => '未发货',
                1 => '已发货',
                2 => '部分发货',
                3 => '部分退货',
                4 => '已退货',
            ),
            'default' => '0',
            'label'   => '子订单状态',
            'comment' => '子订单状态,0:未发货,1:已发货,2:部分发货,3:部分退货,4:已退货',
        ),
        'presale_status'    => array(
            'type'    => array(
                0 => '非预售',
                1 => '预售',
            ),
            'default' => '0',
            'label'   => '预售状态',
            'comment' => '预售状态,0:非预售,1:预售',
        ),
        'author_id' => array(
            'type' => 'varchar(30)',
            'label' => '活动主播ID',
            'editable' => true,
        ),
        'author_name' => array(
            'type' => 'varchar(50)',
            'label' => '活动主播名',
            'editable' => true,
        ),
        'is_sh_ship'        => array(
            'type'    => 'bool',
            'label'   => '是否屏蔽发货',
            'default' => 'false',
            'comment' => '是否屏蔽发货,false:否,true:是',
        ),
        's_type'            => array(
            'type'    => 'varchar(50)',
            'label'   => '销售类型',
            'default' => 'zx',
        ),
        'pay_status'        => array(
            'type'    => array(
                0 => '未支付',
                1 => '已支付',
                2 => '处理中',
                3 => '部分付款',
                4 => '部分退款',
                5 => '全额退款',
                6 => '退款申请中',
                7 => '退款中',
                8 => '支付中',
            ),
            'default' => '0',
            'label'   => '子订单支付状态',
            'comment' => '子订单支付状态,0:未支付,1:已支付,2:处理中,3:部分付款,4:部分退款,5:全额退款,6:退款申请中,7:退款中,8:支付中',
        ),
        'object_bool_type' => array(
            'type' => 'bigint(20)',
            'label' => '商品明细标识',
            'editable' => false,
            'default' => '0',
            'comment' => '商品明细标识,用于存放子单各种标记'
        ),
        'promised_collect_time' => array(
            'type' => 'time',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '承诺/最晚揽收时间',
        ),
        'promise_outbound_time' => array(
            'type' => 'time',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '承诺/最晚出库时间',
        ),
        'biz_sd_type' => array(
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '建议仓类型',
        ),
        'biz_delivery_type' => array(
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '择配建议类型',
        ),
        'obj_line_no' => array(
            'type' => 'int(10)',
            'default' => 0,
            'editable' => false,
            'label' => '行号',
            'in_list' => false,
            'default_in_list' => false,
        ),
        'delete'            => array(
            'type'     => 'bool',
            'default'  => 'false',
            'editable' => false,
            'comment' => '删除状态,可选值: false(否),true(是)',
        ),
        'main_oid' => array(
            'type' => 'varchar(512)',
            'editable' => false,
            'label' => '赠品关联子单号',
        ),
        'sku_uuid' => array(
            'type' => 'varchar(255)',
            'editable' => false,
            'label' => '商品行唯一标识',
        ),
        'addon' => array(
            'type'     => 'longtext',
            'editable' => false,
            'label'    => '扩展字段',
            'comment'  => '扩展字段',
        ),
    ),
    'index'   => array(
        'idx_bn'  => array('columns' => array('bn')),
        'idx_oid' => array('columns' => array('oid')),
        'idx_goods_id' => array('columns' => array('goods_id')),
        'idx_name'  => array('columns' => array('name')),
    ),
    'comment' => '订单子单表,用于存储平台的订单购买行明细',
    'charset' => 'utf8mb4',
    'engine'  => 'innodb',
    'version' => '$Rev: 40912 $',
);
