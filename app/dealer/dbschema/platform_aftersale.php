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

$db['platform_aftersale'] = array(
    'columns' => array(
        'plat_aftersale_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'label' => 'ID',
        ),
        
        'plat_aftersale_bn'=>array(
            'type'            => 'varchar(32)',
           
            'label'           => '退货记录流水号',
            'comment'         => '退货记录流水号',
            'editable'        => false,
            'searchtype'      => 'has',
            'filtertype' => 'normal',
            'in_list'         => true,
            'default_in_list' => true,
            'order' => 2,
        ),
        'plat_order_id'=>array(
            'type' => 'int unsigned',
           
        ),
        'plat_order_bn' =>  array(
            'type'            => 'varchar(32)',
            'label'           => '平台订单号',
            'comment'         => '平台订单号',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 3,
        ),
        'erp_order_bn'             => array(
            'type'     => 'varchar(50)',
            'default'  => 0,
            'editable' => false,
            'label'    => 'ome订单号',
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'erp_order_id'             => array(
            'type' => 'int unsigned',
        ),
        'shop_id'       =>  array(
            'type'       => 'table:shop@ome',
            'label'      => '来源店铺',
            'width'      => 160,
            'editable'   => false,
            'in_list'    => true,
            'filtertype' => 'normal',
            'order' => 4,
        ),
        'shop_type'     =>array(
            'type' => 'varchar(32)',
            'label'      => '店铺类型',
            'in_list'         => true,
            'default_in_list' => true,
            'order' => 5,
        ),
        'return_type'   =>array(
             'type'            => 'varchar(25)',
             'label'           => '售后类型',
             'in_list'         => true,
            'default_in_list' => true,
            'order' => 6,
        ),
        'status'    =>array(

            'type'            => 'varchar(25)',
            'label'           => '售后状态',
            'in_list'         => true,
            'default_in_list' => true,
            'order' => 6,

        ),
        'refund_apply_money'=>array(

            'type'            => 'money',
            'label'           => '申请退款金额',
            'in_list'         => true,
            'default_in_list' => true,
            'order' => 7,
        ),
        'refundmoney'=>array(
            'type'            => 'money',
            'label'           => '退款金额',
            'in_list'         => true,
            'default_in_list' => true,
            'order' => 8,
        ),
        'return_logi_no'     => array(
            'type'            => 'varchar(50)',
            'label'           => '退回物流单号',
            'comment'         => '退回物流单号',
            'editable'        => false,
            'searchtype'      => 'has',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'width'           => 130,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'reason'             => array(
            'type'            => 'text',
            'editable'        => false,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '申请原因',
        ),
        'outer_lastmodify'   => array(
            'label'    => '前端店铺最后更新时间',
            'type'     => 'time',
            'width'    => 130,
            'editable' => false,
        ),
        'add_time'=>array( 
            'type' => 'time',
            'label' => '创建时间',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 25,
        ),
        'at_time' => array(
            'type' => 'time',
            'label' => '新增时间',

            'in_list' => true,
            'default_in_list' => true,
             'order' => 26,
        ),
        'up_time' => array(
            'type' => 'time',
            'label' => '更新时间',
        
            'in_list' => true,
            'default_in_list' => true,
             'order' => 27,
        ),
        'betc_id' => array(
            'type' => 'int unsigned',
            'default'  => 0,
            'editable' => false,
            'label' => '贸易公司ID',
        ),
        'cos_id' => array(
            'type' => 'int unsigned',
            'default'  => 0,
            'editable' => false,
            'label' => '组织架构ID',
        ),
        'sync_status'=>array(

            'type' => array(
                '0' => '未转换',
                '1' => '转换成功',
                '2' => '转换失败',
                '3' => '无需转换',
            ),
            'default' => '0',
            'editable' => false,
            'label' => '转换状态',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
        ),
        'sync_msg'=>array(

            'type'=> 'varchar(200)',
            
            'editable' => false,
            'label' => '转换原因',
        
            'in_list' => true,
            'default_in_list' => true,
        ),
    ),
    'index' => array (
        
        'ind_plat_aftersale_bn' => array(
            'columns' => array(
                'plat_aftersale_bn',
            ),
        ),
        'ind_at_time' => array(
            'columns' => array(
                'at_time',
            ),
        ),
        'ind_up_time' => array(
            'columns' => array(
                'up_time',
            ),
        ),
    ),
    'comment' => 'aftersales',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);