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

$db['platform_aftersale_items'] = array(
    'columns' => array(
        'plat_aftersale_item_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'label' => 'ID',
        ),
         'plat_aftersale_id'=> array(
            'type'     => 'int unsigned',
            'required' => true,
            'label'    => '平台售后单号id',
        ),
        'plat_aftersale_obj_id'=>array(
             'type'     => 'int unsigned',
            'required' => true,
            'label'    => '平台售后单号obj_id',

        ),
        'shop_goods_bn'             => array(
          'type' => 'varchar(40)',
          'editable' => false,
          'is_title' => true,
          'comment' => '销售物料编码'
        ),
        'bn'             => array(
          'type' => 'varchar(40)',
          'editable' => false,
          'is_title' => true,
          'comment' => '基础物料编码'
        ),
        'name'             => array(
            'type' => 'varchar(200)',
            'editable' => false,
            'comment' => '基础物料名称',
        ),
        'oid'             => array(
            'type'     => 'varchar(50)',
            'default'  => 0,
            'editable' => false,
            'label'    => '子订单号',
        ),
        
        'num'             => array(
            'type'     => 'number',
            'label'    => '数量',
        ),
        'price'             => array(
             'type'     => 'money',
             'label'    => '单价',
        ),
        'product_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'comment' => '基础物料ID,关联material_basic_material.bm_id',
        ),
        'erp_num'             => array(
            'type'     => 'number',
            'label'    => '数量',
        ),
        'erp_price'             => array(
             'type'     => 'money',
             'label'    => '单价',
        ),
        'return_type'             => array(
             'type'     => 'varchar(20)',
             'label'    => '类型',
        ),
        'refunded'             => array(
             'type'     => 'money',
             'label'    => '退款金额',
        ),
        'betc_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'label' => '贸易公司ID',
            'editable' => false,
        ),
        'plat_obj_id'    => array(
            'type'  => 'int unsigned',
             'label'    => '平台子订单号id',
        ),
        'plat_item_id'=>array(
            'type'  => 'int unsigned',
             'label'    => '平台子订单号itemid',
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
        'erp_obj_id'             => array(
            'type' => 'int unsigned',
        ),
        'sync_status'=>array(

            'type' => array(
                '0' => '未转换',
                '1' => '转换成功',
                '2' => '转换失败',
               
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
        'at_time' => array(
            'type' => 'time',
            'label' => '创建时间',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'up_time' => array(
            'type' => 'time',
            'label' => '更新时间',
            'in_list' => true,
            'default_in_list' => true,
        ),
    ),
    'index' => array (
        
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
    'comment' => 'platform_aftersale_items',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);