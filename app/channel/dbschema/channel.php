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

$db['channel'] = array(
    'columns' => array(
        'channel_id'           => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'editable' => false,
        ),
        'channel_bn'           => array(
            'type'            => 'varchar(100)',
            'required'        => true,
            'label'           => '应用编号',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            // 'is_title'        => true,
            'width'           => '120',
        ),
        'channel_name'         => array(
            'type'            => 'varchar(255)',
            'required'        => true,
            'label'           => '应用名称',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'is_title'        => true,
            'width'           => '120',
        ),
        'channel_type'         => array(
            'required'        => false,
            'label'           => '应用类型',
            'in_list'         => true,
            'default_in_list' => true,
            'width'           => '70',
            'type'            => array(
                'crm'      => 'CRM',
                'wms'      => 'WMS',
                'shop'     => 'SHOP',
                'wwgenius' => '旺旺精灵',
                'ipay'     => 'IPAY',
                'kuaidi'   => '快递',
                // 'kdn'      => '快递鸟',
                'dchain'   => '外部erp(优仓)',
                'smart'    => '报价系统',
                'cloudprint'=> '云打印',
                'ticket'   => '工单',
                'qimen'    => '奇门',
                'sms'      => '短信',
            ),
            'width' => '120',
        ),
        'config'               => array(
            'type'     => 'text',
            'editable' => false,
        ),
        'crop_config'          => array(
            'type'     => 'serialize',
            'editable' => false,
        ),
        'last_download_time'   => array(
            'type'            => 'time',
            'editable'        => false,
            'label'           => '上次下载订单时间(终端)',
            'in_list'         => false,
            'default_in_list' => true,
        ),
        'last_upload_time'     => array(
            'type'            => 'time',
            'editable'        => false,
            'label'           => '上次上传订单时间(ome)',
            'in_list'         => false,
            'default_in_list' => true,
        ),
        'active'               => array(
            'type'            => 'bool',
            'required'        => true,
            'default'         => 'false',
            // 'in_list'         => true,
            // 'default_in_list' => true,
            'editable'        => false,
            'label'           => '激活',
        ),
        'disabled'             => array(
            'type'     => 'bool',
            'required' => true,
            'default'  => 'false',
            'editable' => false,
        ),
        'last_store_sync_time' => array(
            'type'            => 'time',
            'editable'        => false,
            'label'           => '上次库存同步时间',
            'in_list'         => false,
            'default_in_list' => true,
        ),
        'area'                 => array(
            'type'     => 'varchar(255)',
            'editable' => false,
        ),
        'zip'                  => array(
            'type'     => 'varchar(20)',
            'editable' => false,
        ),
        'addr'                 => array(
            'type'     => 'varchar(255)',
            'editable' => false,
        ),
        'default_sender'       => array(
            'type'     => 'varchar(255)',
            'editable' => false,
        ),
        'mobile'               => array(
            'type'     => 'varchar(30)',
            'editable' => false,
        ),
        'tel'                  => array(
            'type'     => 'varchar(30)',
            'editable' => false,
        ),
        'filter_bn'            => array(
            'type'     => 'bool',
            'required' => true,
            'default'  => 'false',
            'editable' => false,
        ),
        'bn_regular'           => array(
            'type'     => 'varchar(255)',
            'editable' => false,
        ),
        'express_remark'       => array(
            'type'     => 'text',
            'editable' => false,
        ),
        'delivery_template'    => array(
            'type'     => 'varchar(255)',
            'editable' => false,
        ),
        'order_bland_template' => array(
            'type'     => 'varchar(255)',
            'editable' => false,
        ),
        'node_id'              => array(
            'type'            => 'varchar(32)',
            'editable'        => false,
            'label'           => '节点',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            // 'is_title'        => true,
            'width'           => '120',
        ),
        'node_type'            => array(
            'type'            => 'varchar(32)',
            'editable'        => false,
            'label'           => '节点类型',
            'in_list'         => true,
            'default_in_list' => true,
        ),
        
        'secret_key'           => array(
            'type'     => 'varchar(100)',
            'editable' => false,
            'label'    => 'secret_key',
        ),
        'app_key'           => array(
            'type'     => 'varchar(30)',
            'editable' => false,
            'label'    => 'app_key',
        ),
        'memo'                 => array(
            'type'     => 'varchar(255)',
            'editable' => false,
        ),
        'api_version'          => array(
            'type'     => 'char(6)',
            'editable' => false,
        ),
        'addon'                => array(
            'type'     => 'serialize',
            'editable' => false,
        ),
        'shipper'              => array(
            'type'     => 'varchar(32)',
            'editable' => false,
            'label'    => '货主',
            'editable' => false,
            'width'    => '120',
        ),
        'matrix_url' => array (
            'type' => 'varchar(255)',
            'label' => '矩阵请求地址',
            'comment' => '可单独为某个店铺指定矩阵请求地址',
            'in_list' => true,
          ),
          'channel_adapter' => array(
            'type' => 'varchar(255)',
            'label' => '应用对接适配器',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'is_title' => true,
            'width' => '120',
        ),
        'biz_type'             => array(
            'type'            => 'varchar(32)',
            'required'        => false,
            'default'         => '',
            'editable'        => false,
            'label'           => '业务类型',
            'in_list'         => true,
            'default_in_list' => false,
        ),
    ),
    'index'   => array(
        'ind_node_id' => array(
            'columns' => array(
                0 => 'node_id',
            ),
        ),
    ),
    'comment' => '渠道表',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
