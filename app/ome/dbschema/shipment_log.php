<?php
/**
 * 前端回写日志
 *
 * @author hzjsq@msn.com
 * @version 0.1b
 */
$db['shipment_log'] = array(
    'columns' =>
    array(
        'log_id' =>
        array(
            'type' => 'varchar(32)',
            'required' => true,
            'pkey' => true,
            //'extra' => 'auto_increment',
            'editable' => false,
        ),
        'shopId' =>
        array(
            'type' => 'varchar(32)',
            'required' => true,
            'editable' => false,
            'default_in_list' => true,
            'in_list' => true,
            'label' => '店铺编号',
            'comment' => '店铺编号',
        ),
        'ownerId' => 
        array (
            'type' => 'table:users@desktop',
            'label' => '操作人',
            'editable' => false,
        ),
        'orderBn' =>
        array(
            'type' => 'varchar(64)',
            'required' => true,
            'editable' => false,
            'default_in_list' => true,
            'in_list' => true,
            'label' => '订单号',
            'comment' => '订单号',
        ),
        'deliveryCode' =>
        array(
            'type' => 'varchar(32)',
            'required' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
             'label' => '快递单号',
            'comment' => '快递单号',
        ),
        'deliveryCropCode' =>
        array(
            'type' => 'varchar(20)',
            'required' => true,
            'editable' => false,
        ),
        'deliveryCropName' =>
        array(
            'type' => 'varchar(32)',
            'required' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
             'label' => '快递公司名称',
            'comment' => '快递公司名称',
        ),
        'oid_list' =>
        array(
            'type' => 'varchar(255)',
            //'required' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
             'label' => '子单号',
            'comment' => '子单号',
        ),
        'receiveTime' =>
        array(
            'type' => 'time',
            'required' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'label' => '指令接收时间',
            'comment' => '指令接收时间',
        ),
        'updateTime' =>
        array(
            'type' => 'time',
            'required' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'label' => '状态更新时间',
            'comment' => '状态更新时间',
        ),
        'status' =>
        array(
            'type' => 'varchar(12)',
            'required' => true,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'label' => '当前状态',
            'comment' => '更新结果',
        ),
        'message' =>
        array(
            'type' => 'long',
            'required' => true,
            'editable' => false,
            'in_list' => false,
            'default_in_list' => false,
            'label' => '调用信息',
            'comment' => '调用信息',
        ),
    ),
    'index' => array (
        'ind_ship_status' => array (
            'columns' => array (
                0 => 'status',
            ),
        ),
        'ind_status_updateTime' => array (
            'columns' => array (
                0 => 'status',
                1 => 'updateTime',
            ),
        ),
        'ind_status_receiveTime' => array (
            'columns' => array (
                0 => 'status',
                1 => 'receiveTime',
            ),
        ),
        'ind_shop_id'=>array(
            'columns' => array (
                0 => 'shopId',
            ),
        ),
        'ind_orderBn'=>array(
            'columns' => array (
                0 => 'orderBn',
            ),
        ),
        'ind_deliveryCode'=>array(
            'columns' => array (
                'deliveryCode',
                'status',
            ),
        ),
    ),
    'comment' => '前端回写日志',
    'engine' => 'innodb',    'version' => '$Rev:  $',
);
