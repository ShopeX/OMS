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


$db['shop_skus'] = array(
    'columns' => array(
        'id' => array(
            'type' => 'varchar(32)',
            'required' => true,
            'pkey' => true,
            'label' => 'ID',
            'comment' => ''
        ),
        'request' => array(
            'type' => 'bool',
            'label' => app::get('inventorydepth')->_('回写库存'),
            'default' => 'true',
            'filterdefault'   => true,
            'filtertype'      => true,
        ),
        'shop_id' => array(
            'type'          => 'table:shop@ome',
            'required'      => true,
            'label'         => '前端店铺',
            'filterdefault' => true,
            'filtertype'    => 'fuzzy_search',
            'panel_id'      => 'skus_finder_top',
        ),
        'shop_bn' => array(
            'type'            => 'bn',
            'label'           => app::get('inventorydepth')->_('店铺编码'),
            'required'        => true,
            'filterdefault'   => true,
            'filtertype'      => true,
            'order'           => 10,
        ),
        'cos_id' => array(
            'type' => 'varchar(255)',
            'default' => 0,
            'editable' => false,
            'label' => '组织架构ID',
            'in_list' => false,
            'default_in_list' => false,
        ),
        'shop_bn_crc32' => array(
            'type'     => 'bigint(20)',
            'required' => true,
            'default'  => 0,
        ),      
        'shop_name' => array(
            'type'            => 'varchar(255)',
            'label'           => app::get('inventorydepth')->_('店铺名称'),
            'required'        => true,
            'filterdefault'   => true,
            'filtertype'      => true,
            'order'           => 20,  
        ),
        'shop_type' => array(
            'type'     => 'varchar(50)',
            'label'    => app::get('inventorydepth')->_('店铺类型'),
            'required' => true,
            'default'  => '',
            'hidden'   => true,
        ),
        'shop_sku_id'   => array(
            'type'            => 'varchar(50)',
            'required'        => false,
            'label'           => app::get('inventorydepth')->_('平台SKU ID'),
            'in_list' => true,
            'default_in_list' => true,
            'order'           => 30,
            'filterdefault'   => true,
            'filtertype'      => 'textarea',
        ),
        'shop_iid' => array(
            'type'            => 'varchar(50)',
            'required'        => false,
            'label'           => app::get('inventorydepth')->_('平台商品ID'),
            'order'           => 40,
            'in_list' => true,
            'default_in_list' => true,
            'filterdefault'   => true,
            'filtertype'      => 'textarea',
        ),
        'simple'         => array(
            'type' => 'bool',
            'label' => app::get('inventorydepth')->_('简单商品'),
            'default' => 'false',
        ),
        'shop_product_bn' => array(
            'type'            => 'varchar(200)',
            'required'        => false,
            'label'           => app::get('inventorydepth')->_('店铺货号'),
            'default'         => '',
            'in_list' => true,
            'default_in_list' => true,
            'filterdefault'   => true,
            'filtertype'      => 'textarea',
            'order'           => 60,
            'searchtype' => 'has', 
        ),
        'shop_product_bn_crc32' => array(
            'type' => 'bigint(20)',
            'required' => true,
            'default' => 0,
        ),
        'shop_properties' => array(
            'type' => ' longtext',
            'label' => app::get('inventorydepth')->_('店铺货品属性值'),
            'default' => '',
        ),
        'shop_properties_name' => array(
            'type' => 'longtext',
            'label' => app::get('inventorydepth')->_('店铺货品属性'),
            'default' => '',
            'in_list' => true,
        ),
        'shop_title' => array(
            'type' => 'varchar(80)',
            'label' => app::get('inventorydepth')->_('店铺货品名称'),
            'default' => '',
            'filtertype' => 'normal',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 70,
            'searchtype' => 'nequal'
        ),
        'shop_price' => array(
            'type'            => 'money',
            'label'           => app::get('inventorydepth')->_('销售价'),
            'default'         => 0,
            'in_list'         => true,
        ),
        'shop_barcode' => array(
            'type' => 'varchar(200)',
            'label' => app::get('inventorydepth')->_('条形码'),
            'default' => '',
            'filtertype' => 'normal',
            'in_list' => true,
            'default_in_list' => false,
        ),
        'release_stock' => array(
            'type'            => 'int(10) unsigned',
            'required'        => false,
            'default'         => 0,
            'label'           => app::get('inventorydepth')->_('发布库存'),
            'in_list'         => false,
            'default_in_list' => false,
            'comment'         => '',
            'hidden'          => true,
            'order' => 100
        ),
        'shop_stock' => array(
            'type'            => 'int(10) unsigned',
            'required'        => false,
            'default'         => 0,
            'label'           => app::get('inventorydepth')->_('店铺库存'),
            'in_list'         => false,
            'default_in_list' => false,
            'comment'         => '',
            'order' => 80,
        ),
        'operator' => array(
            'type'     => 'varchar(100)',
            'required' => false,
            'label'    => app::get('inventorydepth')->_('发布操作人'),
            'comment'  => ''
        ),
        'operator_ip' => array(
            'type'     => 'ipaddr',
            'required' => false,
            'label'    => app::get('inventorydepth')->_('发布操作人IP'),
            'comment'  => ''
        ),
        'update_time' => array(
            'type'     => 'last_modify',
            'required' => false,
            'label'    => app::get('inventorydepth')->_('最后更新时间'),
            'comment'  => '',
            'in_list' => true,
        ),
        'download_time' => array(
            'type' => 'time',
            'label' => app::get('inventorydepth')->_('同步时间'),
            'in_list' => true,
            'default' => 0,
        ),
        'mapping' => array(
            'type' => 'intbool',
            'label' => app::get('inventorydepth')->_('已对映上本地货品'),
            'default' => '0',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'bind' => array(
            'type' => array(
                0 => '普通',
                1 => '组合',
                2 => '多选一',
            ),
            'label' => app::get('inventorydepth')->_('商品类型'),
            'default' => '0',
            'filtertype' => 'normal',
        ),
        'sales_material_type' => array(
            'type' => array(
                0 => '默认',
                1 => '普通',
                2 => '组合',
                3 => '赠品',
                5 => '多选一',
                6 => '礼盒',
                7 => '福袋组合',
            ),
            'label' => app::get('inventorydepth')->_('销售物料类型'),
            'width' => 100,
            'editable' => false,
            'default' => 1,
            'in_list' => true,
            'default_in_list' => true,
            'filterdefault' => true,
            'filtertype' => 'normal',
        ),
        'addon' => array(
            'type' => 'serialize',
            'default' => '',
        ),
        'release_status' => array(
            'type' => array (
                'running' => '运行中',
                'success' => '成功',
                'fail'    => '失败',
                'sending' => '发起中',
                'sleep'   => '未发布',
            ),
            'label'           => app::get('inventorydepth')->_('发布状态'),
            'default'         => 'sleep',
            'order' => 110
        ),
        'sync_map' => array (
            'type'=> array (
                0 => '未映射',
                1 => '映射失败',
                2 => '映射成功',
                3 => '解除映射失败',
                4 => '解除映射成功',
            ),
            'default'         => '0',
            'required'        => true,
            'label'           => '关系映射',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'editable'        => false,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'outer_createtime' => array(
            'type' => 'time',
            'label' => '货品创建时间',
            'default' => 0,
            'in_list' => true,
            'filtertype' => 'time',
        ),
        'outer_lastmodify' => array(
            'type' => 'time',
            'label' => '货品更新时间',
            'default' => 0,
            'in_list' => true,
            'filtertype' => 'time',
        ),
        'op_name'       => array(
            'type'     => 'varchar(30)',
            'editable' => false,
        ),
        'bidding_type' => array(
                'type' => 'tinyint',
                'label' => '出价类型',
                'comment' => '出价类型',
                'default' => 0,
                'default_in_list' => false,
                'in_list' => false,
                'order' => 99,
        ),
        'bidding_no' => array(
                'type' => 'varchar(30)',
                'label' => '出价编号',
                'comment' => '出价编号',
                'filterdefault' => true,
                'filtertype' => 'normal',
                'default_in_list' => false,
                'in_list' => true,
                'order' => 98,
        ),
        'stock_model' => array(
            'type' => 'varchar(30)',
            'label' => '库存模式',
            'filterdefault' => true,
            'filtertype' => 'normal',
            'default_in_list' => false,
            'in_list' => true,
        ),
        'at_time'           => [
            'type'    => 'TIMESTAMP',
            'label'   => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'width'   => 120,
            'in_list' => true,
            // 'order'   => 11,
        ],
        'up_time'           => [
            'type'    => 'TIMESTAMP',
            'label'   => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'   => 120,
            'in_list' => true,
            // 'order'   => 11,
        ],
    ),
    'index' => array(
        'idx_shop_bn_crc32' => array(
            'columns' => array('shop_bn_crc32'),
        ),
        'idx_shop_pbn_crc32' => array(
            'columns' => array('shop_product_bn_crc32'),
        ),
        'idx_shop_iid' => array(
            'columns' => array('shop_iid'),
        ),
       
        'idx_shop_sku_id' => array(
            'columns' => array('shop_sku_id'),
        ),
        'shop_product_bn' => array(
            'columns' => array('shop_product_bn'),
        ),
        'ind_outer_createtime' => array(
            'columns' => array('outer_createtime'),
        ),
        'ind_outer_lastmodify' => array(
            'columns' => array('outer_lastmodify'),
        ),
        'ind_bidding_no' => array(
            'columns' => array('bidding_no'),
        ),
        'ind_up_time' => array(
            'columns' => array('up_time'),
        ),
        'ind_shop_product_bn' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'shop_product_bn',
            ),
        ),
        'ind_shop_createtime' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'outer_createtime',
            ),
        ),
        'ind_shop_smaterial_type' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'sales_material_type',
            ),
        ),
    ),
    'comment' => '店铺货品明细',
    'engine' => 'innodb',
    'version' => '$Rev: $'
);
