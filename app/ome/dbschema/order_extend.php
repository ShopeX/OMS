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
$db['order_extend'] = array(
    'columns' => array(
        'order_id'             => array(
            'type'     => 'table:orders@ome',
            'required' => true,
            'default'  => 0,
            'editable' => false,
            'pkey'     => true,
            'comment'  => '订单号',
        ),
        'receivable'           => array(
            'type'     => 'money',
            'default'  => '0',
            'label'    => '应收费用',
            'editable' => false,
        ),
        'sellermemberid'       => array(
            'type'  => 'varchar(255)',
            'label' => '卖家会员登录名',
        ),
        'extend_status'        => array(
            'type'     => 'varchar(30)',
            'default'  => '0',
            'comment'  => '订单扩展状态(比如收货人信息发生变更)',
            'editable' => false,
        ),
        'bool_extendstatus'    => array(
            'type'     => 'bigint(20)',
            'default'  => '0',
            'comment'  => '订单扩展是与否二进制状态',
            'editable' => false,
        ),
        'presale_auto_paid'    => array(
            'type'     => 'money',
            'default'  => '0',
            'label'    => '预售补全尾款金额',
            'editable' => false,
        ),
        'presale_pay_status'   => array(
            'type'     => 'tinyint unsigned',
            'default'  => 0,
            'editable' => false,
        ),
        'store_dly_type'       => array(
            'type'     => 'tinyint(1)',
            'default'  => '0',
            'comment'  => '门店发货模式',
            'editable' => false,
        ),
        'store_bn'             => array(
            'type'     => 'varchar(20)',
            'comment'  => '门店编码',
            'editable' => false,
        ),
        'store_process_status' => array(
            'type'     => 'tinyint(1)',
            'default'  => '0',
            'comment'  => '门店处理状态',
            'editable' => false,
        ),
        'orig_reship_id'       => array(
            'type'     => 'int',
            'default'  => 0,
            'editable' => false,
            'label'    => '原始换货单号ID',
        ),
        'push_time'            => array(
            'type'    => 'time',
            'label'   => '推单时间',
            'default' => '0',
        ),
        'assign_express_code'  => array(
            'type'            => 'varchar(20)',
            'editable'        => false,
            'label'           => '指定快递编码',
            'width'           => 100,
            'in_list'         => false,
            'default_in_list' => true,
        ),
        'platform_logi_no'     => array(
            'type'  => 'varchar(255)',
            'label' => '平台运单号',
        ),
        'o2o_store_bn'         => array(
            'type'     => 'varchar(20)',
            'comment'  => '履约门店编码',
            'editable' => false,
        ),
        'o2o_store_name'       => array(
            'type'     => 'varchar(20)',
            'comment'  => '履约门店名称',
            'editable' => false,
        ),
        'router_num'           => array(
            'type'     => 'number',
            'default'  => 0,
            'required' => true,
            'editable' => false,
        ),
        'location'             => array(
            'type'     => 'varchar(64)',
            'label'    => '坐标',
            'editable' => true,
        ),
        'store_info'           => array(
            'type'     => 'text',
            'label'    => '平台扩展信息',
            'editable' => false,
        ),
        'cert_id'              => array(
            'type'     => 'varchar(25)',
            'default'  => '',
            'label'    => '身份证信息',
            'comment'  => '身份证信息',
            'editable' => false,
        ),
        'cpup_service'         => array(
            'type'    => 'varchar(200)',
            'label'   => '物流升级服务',
            'default' => '0',
        ),
        'cn_service'           => array(
            'type'    => 'varchar(32)',
            'label'   => '菜鸟服务类型',
            'default' => '0',
        ),
        'promise_service'      => array(
            'type'    => 'varchar(255)',
            'label'   => '物流服务标签',
            'default' => '',
        ),
        'collect_time'         => array(
            'type'    => 'time',
            'label'   => '揽收时间',
            'default' => '0',
        ),
        'es_time'              => array(
            'type'    => 'int',
            'label'   => '物运时间',
            'default' => '0',
        ),
        'latest_delivery_time' => array(
            'type'    => 'time',
            'label'   => '最晚发货时间',
            'default' => '0',
        ),
        'extend_field' => array(
            'type'    => 'text',
            'label'   => '扩展字段',
            'default' => '0',
        ),
        'promised_collect_time' => array(
            'type'    => 'time',
            'label'   => '承诺最晚揽收时间',
            'default' => '0',
        ),
        'promised_sign_time' => array(
            'type'    => 'time',
            'label'   => '承诺最晚送达时间',
            'default' => '0',
        ),
        'plan_sign_time' => array(
            'type' => 'time',
            'label' => '计划送达时间',
            'default' => '0',
            'required' => false,
            'editable' => false,
        ),
        'biz_delivery_code' => array(
            'type'  => 'varchar(255)',
            'label' => '建议快递名单',
            'required' => false,
            'editable' => false,
        ),
        'white_delivery_cps' => array(
            'type'  => 'varchar(255)',
            'label' => '快递白名单',
            'required' => false,
            'editable' => false,
        ),
        'black_delivery_cps' => array(
            'type'  => 'varchar(255)',
            'label' => '快递黑名单',
            'required' => false,
            'editable' => false,
        ),
        'is_xsdbc' => array(
            'type'     => 'tinyint(1)',
            'default'  => '0',
            'comment'  => '是否闪购订单',
            'editable' => false,
        ),
    ),
    'index' => array(
        'idx_orig_reship_id' =>array('columns'=>array('orig_reship_id')),
        'idx_store_process_status' =>array('columns'=>array('store_process_status')),
        'idx_latest_delivery_time' =>array('columns'=>array('latest_delivery_time')),
    ),
    'engine'  => 'innodb',
    'charset' => 'utf8mb4',
    'version' => '$Rev: 40912 $',
    'comment' => '订单扩展表',
);
