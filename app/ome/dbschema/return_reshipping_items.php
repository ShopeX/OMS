<?php
$db['return_reshipping_items'] = array(
    'columns' => array(
        'item_id' => array(
            'type' => 'bigint unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'comment' => '商品明细主键ID，自增',
        ),
        'reshipping_id' => array(
            'type' => 'bigint unsigned',
            'required' => true,
            'editable' => false,
            'comment' => '补寄申请单ID（外键）',
        ),
        'oid' => array(
            'type' => 'varchar(50)',
            'editable' => false,
            'comment' => '平台子订单号(order_objects.oid)',
        ),
        'order_item_id' => array(
            'type' => 'int unsigned',
            'editable' => false,
            'default' => 0,
            'comment' => '原订单明细ID(sdb_ome_order_items.item_id)',
        ),
        'sku_uuid' => array(
            'type' => 'varchar(255)',
            'editable' => false,
            'comment' => '商品行唯一标识',
        ),
        'goods_id' => array(
            'type' => 'bigint unsigned',
            'editable' => false,
            'comment' => '销售物料ID',
        ),
        'bn' => array(
            'type' => 'varchar(50)',
            'editable' => false,
            'comment' => '销售物料编码',
        ),
        'nums' => array(
            'type' => 'int unsigned',
            'editable' => false,
            'comment' => '数量',
        ),
        'price' => array(
            'type' => 'money',
            'editable' => false,
            'comment' => '价格',
        ),
      
        'pmt_price' =>
        array(
          'type' => 'money',
          'default' => '0',
        'editable' => false,
        'comment' => '订单行明细优惠小计',
        ),
        'sale_price' =>
        array(
          'type' => 'money',
          'default' => '0',
            'editable' => false,
            'comment' => '订单行明细销售小计,公式: amount-pmt_price',
        ),
        'amount' =>
        array(
          'type' => 'money',
          'editable' => false,
          'comment' => '订单行明细零售小计,公式: price * nums',
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
        'at_time' => array(
            'type' => 'TIMESTAMP',
            'default' => 'CURRENT_TIMESTAMP',
            'editable' => false,
            'comment' => '创建时间',
        ),
        'up_time' => array(
            'type' => 'TIMESTAMP',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'editable' => false,
            'comment' => '更新时间',
        ),
    ),
    'index' => array(
        'ind_reshipping_id' => array(
            'columns' => array(
                0 => 'reshipping_id',
            ),
        ),
        'ind_order_item_id' => array(
            'columns' => array(
                0 => 'order_item_id',
            ),
        ),
    ),
    'comment' => '补寄申请单商品表，存储补寄申请单的商品明细信息（可选，补寄申请单详情中暂无商品信息）',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
    'charset' => 'utf8mb4',
);

