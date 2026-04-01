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

$db['branch']=array (
  'columns' =>
  array (
    'branch_id' =>
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'wms_id' =>
    array (
      'type' => 'number',
      'editable' => false,
    ),
    'branch_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '仓库编号',
      'searchtype' => 'nequal',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'storage_code'=>array (
      'type' => 'varchar(32)',
      'required' => true,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '库内存放点编号',
    ),
    'warehouse_code'        => array(
        'type'            => 'varchar(64)',
        'label'           => 'WAREHOUSE CODE',
        'default_in_list' => true,
        'in_list'         => true,
    ),
    'name' =>
    array (
      'type' => 'varchar(200)',
      'required' => true,
      'editable' => false,
      'is_title' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'width' => 130,
      'label' => '仓库名称',
    ),
    'parent_id' =>
    array (
      'type' => 'number',
      'default' => 0,
      'label' => '关联主仓',
      'editable' => false,
    ),
    'type' =>
    array (
      'type' => 'varchar(50)',
      'in_list' => true,
      'default_in_list' => true,
      'width' => 130,
      'label' => '仓库类型',
      'required' => true,
      'default' => 'main',
    ),
    'store_id'             => array(
      'type'    => 'table:store@o2o',
      'default' => 0,
    ),
    'disabled' =>
    array (
      'type' => 'bool',
      'required' => true,
      'editable' => false,
      'default' => 'false',
    ),
    'area' =>
    array (
      'type' => 'region',
      'label' => '收货地区',
      'width' => 170,
      'editable' => false,
      'in_list' => true,
      'default' => '',
    ),
    'address' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'label' => '联系人地址',
      'in_list' => true,
    ),
    'zip' =>
    array (
      'type' => 'varchar(20)',
      'editable' => false,
      'label' => '联系人邮编',
      'in_list' => true,
    ),
    'phone' =>
    array (
      'type' => 'varchar(100)',
      'editable' => false,
      'label' => '联系人电话',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'uname' =>
    array (
      'type' => 'varchar(100)',
      'editable' => false,
      'label' => '联系人姓名',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'mobile' =>
    array (
      'type' => 'varchar(100)',
      'editable' => false,
      'label' => '联系人手机',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'email' =>
    array (
      'type' => 'text',
      'editable' => false,
      'label' => '仓库邮箱',
      'in_list' => true,
      'default_in_list' => false,
    ),
    'sex' =>
    array (
      'type' =>
      array (
        'male' => '男',
        'female' => '女',
      ),
      'default' => 'male',
      'editable' => false,
      'label' => '性别',
      'in_list' => true,
    ),
    'memo' =>
    array (
      'type' => 'text',
      'editable' => false,
      'in_list' => true,
      'label' => '备注',
    ),
    'stock_threshold' =>
    array (
      'type' => 'number',
      'editable' => false,
      'default' => 1,
    ),
    'stock_safe_type' =>
    array (
      'type' => array(
            'supplier' => '供应商补货水平',
            'branch' => '仓库设置',
        ),
      'editable' => false,
      'label' => '安全库存计算类型',
      'default' => 'branch',
    ),
    'stock_safe_day' =>
    array (
      'type' => 'mediumint unsigned',
      'editable' => false,
      'default' => 7,
    ),
    'stock_safe_time' =>
    array (
      'type' => 'mediumint unsigned',
      'editable' => false,
      'default' => 0,
    ),
    'attr' =>
    array (
      'type' => array(
            'true' => '线上',
            'false' => '线下',
       ),
      'editable' => false,
      'default' => 'true',
      'label' => '仓库属性'
    ),
    'online' =>
    array (
      'type' => array(
            'true' => '电子商务仓',
            'false' => '传统业务仓',
       ),
      'editable' => false,
      'default' => 'true',
      'label' => '仓库类型'
    ),
    'weight' =>
    array (
      'type' => 'number',
      'editable' => false,
      'in_list' => true,
      'default' => 0,
      'label' => '权重',
    ),
    'defaulted' =>
    array (
      'type' => 'bool',
      'required' => true,
      'editable' => false,
      'default' => 'false',
    ),
    'area_conf' =>
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'is_deliv_branch' => array(
        'type' => array(
            'true' => '发货仓库',
            'false' => '备货仓库',
        ),
        'label' => '发货属性',
        'in_list' => true,
        'default_in_list' => true,
        'default' => 'true',
    ),
    'bind_conf' =>
    array (
      'type' => 'longtext',
      'editable' => false,
      'label' => '发货仓绑定配置',
    ),
    'owner' => array(
      'type' => array(
        '1' => '自建仓库',
        '2' => '第三方仓库',
        '3'=>'平台自发仓库',
      ),
      'label' => '仓库归属',
      'default' => '1',
      'in_list' => false,
      'default_in_list' => false,
      'filtertype' => true,
    ),
    'is_declare' => array (
        'type' => 'bool',
        'label' => '跨境申报仓库',
        'required' => false,
        'default' => 'false',
        'in_list' => false,
        'default_in_list' => false,
    ),
    'b_type' =>
    array (
      'type' => 'tinyint(1)',
      'editable' => false,
      'label' => '线上/线下业务区分标记',
      'default' => 1
    ),
    'b_status' =>
    array (
      'type' => 'tinyint(1)',
      'editable' => false,
      'label' => '仓库是否启用',
      'default' => 2
    ),
    'logistics_limit_time' =>
    array (
      'type' => 'mediumint unsigned',
      'editable' => false,
      'default' => 4,
    ),
    'start_work' => array (
        'type' => 'char(4)',
        'label' => '开始工作时间',
        'required' => false,
        'default' => '0000',
        'in_list' => false,
        'default_in_list' => false,
    ),
    'end_work' => array (
        'type' => 'char(4)',
        'label' => '结束工作时间',
        'required' => false,
        'default' => '0000',
        'in_list' => false,
        'default_in_list' => false,
    ),
    'ability' => array (
        'type' => 'number',
        'label' => '接单能力',
        'required' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
  'cutoff_time' =>
      array (
          'type' => 'char(4)',
          'label' => '截单时间',
          'comment' => '截单时间',
          'editable' => true,
      ),
      'latest_delivery_time' => array (
          'type' => 'char(4)',
          'label' => '最晚发货时间',
          'comment' => '最晚发货时间',
          'editable' => true,
      ),
      'location' =>
      array (
          'type' => 'varchar(64)',
          'label' => '坐标',
          'editable' => true,
      ),
    'latitude' =>
      array (
          'type' => 'varchar(100)',
          'label' => '纬度',
          'editable' => true,
          'in_list' => true,
          'default_in_list' => false,
      ),
    'longitude' =>
      array (
          'type' => 'varchar(100)',
          'label' => '经度',
          'editable' => true,
          'in_list' => true,
          'default_in_list' => false,
      ),
    'entity_branch_id' =>
        array (
            'type' => 'table:entity_branch_product@ome',
            'default' => '0',
            'label' => '实体仓ID',
            'comment' => '实体仓ID',
        ),
    'platform' =>
        array (
            'type' => 'varchar(30)',
            'label' => '所属平台',
            'comment' => '所属平台',
        ),
    'owner_code' => array(
        'type'            => 'varchar(100)',
        'editable'        => false,
        'label'           => '货主编码',
        'in_list'         => true,
        'default_in_list' => true,
    ),
    'is_ctrl_store' => array(
        'type'     => 'tinyint(1)',
        'editable' => false,
        'label'    => '是否管控库存',
        'default'  => 1,
        'comment'  => '管控库存(1=是，2=否)',
    ),
    'is_negative_store' => array(
        'type'     => 'tinyint(1)',
        'editable' => false,
        'label'    => '允许负库存',
        'default'  => 2,
        'comment'  => '允许负库存(1=是，2=否)',
    ),
    'at_time'           => [
        'type'    => 'TIMESTAMP',
        'label'   => '创建时间',
        'default' => 'CURRENT_TIMESTAMP',
        'width'           => 130,
        'in_list' => true,
    ],
    'up_time'           => [
        'type'    => 'TIMESTAMP',
        'label'   => '更新时间',
        'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'width'           => 130,
        'in_list' => true,
    ],
  ),

  'index' =>
  array (
    'ind_branch_bn' =>
    array (
        'columns' =>
        array (
          0 => 'branch_bn',
        ),
        'prefix' => 'unique',
    ),
    'ind_b_type' =>
    array (
        'columns' =>
        array (
            0 => 'b_type',
        ),
    ),
  ),
  'comment' => '发货点',
  'engine' => 'innodb',
  'version' => '$Rev: 51996',
);