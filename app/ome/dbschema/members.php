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

$db['members'] = array(
    'columns' => array(
        'member_id'     => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => 'ID',
            'width'    => 110,
            'editable' => false,
        ),
        'uname_md5'         => array(
            'type'          => 'varchar(32)',
            'label'         => 'md5用户名',
            'width'         => 100,
            'editable'      => false,
            'in_list'       => false,
            'default_in_list' => false,
        ),
        'uname'         => array(
            'type'            => 'varchar(255)',
            'label'           => '用户名',
            'sdfpath'         => 'account/uname',
            'is_title'        => true,
            'width'           => 160,
            'required'        => 1,
            'searchtype'      => 'head',
            'editable'        => false,
            'filtertype'      => 'normal',
            'filterdefault'   => 'true',
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'name'          => array(
            'type'            => 'varchar(255)',
            'label'           => '姓名',
            'width'           => 160,
            'sdfpath'         => 'contact/name',
            'editable'        => true,
            'filtertype'      => 'normal',
            'filterdefault'   => 'true',
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'shop_id'       => array(
            'type'          => 'varchar(32)',
            'label'         => '来源店铺',
            'width'         => 100,
            'editable'      => false,
            'in_list'       => true,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ),
        'shop_type'     => array(
            'type'          => 'varchar(50)',
            'label'         => '所属平台',
            'width'         => 85,
            'editable'      => false,
            'default'       => 'other',
            'in_list'       => true,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ),
        'buyer_open_uid' => array (
          'type' => 'varchar(255)',
          'label' => '买家open_uid',
          'default' => '',
          'editable' => false,
        ),
        'area'          => array(
            'label'           => '地区',
            'width'           => 160,
            'type'            => 'region',
            'sdfpath'         => 'contact/area',
            'editable'        => false,
            'filtertype'      => 'yes',
            'filterdefault'   => 'true',
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'addr'          => array(
            'type'            => 'text',
            'label'           => '地址',
            'sdfpath'         => 'contact/addr',
            'width'           => 200,
            'editable'        => true,
            'filtertype'      => 'normal',
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'mobile'        => array(
            'type'            => 'varchar(255)',
            'label'           => '手机',
            'width'           => 175,
            'sdfpath'         => 'contact/phone/mobile',
            'editable'        => true,
            'filtertype'      => 'normal',
            'filterdefault'   => 'true',
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'tel'           => array(
            'type'            => 'varchar(255)',
            'label'           => '固定电话',
            'width'           => 170,
            'sdfpath'         => 'contact/phone/telephone',
            'editable'        => true,
            'filtertype'      => 'normal',
            'filterdefault'   => 'true',
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'email'         => array(
            'type'            => 'varchar(255)',
            'label'           => 'EMAIL',
            'width'           => 110,
            'sdfpath'         => 'contact/email',
            'searchtype'      => 'has',
            'editable'        => true,
            'filtertype'      => 'normal',
            'filterdefault'   => 'true',
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'zip'           => array(
            'type'       => 'varchar(20)',
            'label'      => '邮编',
            'width'      => 110,
            'sdfpath'    => 'contact/zipcode',
            'editable'   => true,
            'filtertype' => 'normal',
            'in_list'    => true,
        ),
        'order_num'     => array(
            'type'     => 'number',
            'default'  => 0,
            'label'    => '订单数',
            'width'    => 110,
            'editable' => false,
            'hidden'   => true,
            'in_list'  => true,
        ),
        'sex'           => array(
            'type'     => array(
                'female' => '女',
                'male'   => '男',
            ),
            'default'  => 'female',
            'required' => true,
            'label'    => '性别',
            'width'    => 30,
        ),
        'addon'         => array(
            'type'     => 'longtext',
            'editable' => false,
        ),

        'regtime'       => array(
            'label'         => '注册时间',
            'width'         => 75,
            'type'          => 'time',
            'editable'      => false,
            'filtertype'    => 'number',
            'filterdefault' => 'true',
        ),
        'disabled'      => array(
            'type'     => 'bool',
            'default'  => 'false',
            'editable' => false,
        ),
        'remark'        => array(
            'label' => '备注',
            'type'  => 'text',
            'width' => 75,
        ),
        'last_modified' => array(
            'label'    => '最后更新时间',
            'type'     => 'last_modify',
            'width'    => 130,
            'editable' => false,
            'in_list'  => true,
        ),
        'source'        => array(
            'type'     => 'varchar(30)',
            'editable' => false,
            'label'    => '来源',
            'width'    => 100,
            'in_list'  => true,
            'default'  => 'local',
            'comment'  => 'local:本地,sap:SAP,openapi:openapi',
        ),
        'title'             => array(
            'type'            => 'varchar(150)',
            'label'           => '发票抬头',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'head',
            'filtertype'      => 'normal',
            'filterdefault'   => false,
            'order'           => 25,
            'width'           => 200,
        ),
        'ship_tax'          => array(
            'type'            => 'varchar(255)',
            'label'           => '客户税号',
            'in_list'         => true,
            'default_in_list' => false,
        ),
    ),
    'index'   => array(
        'ind_email'       => array(
            'columns' => array(
                0 => 'email',
            ),
        ),
        'ind_buyer_open_uid' =>
        array (
            'columns' =>
            array (
              0 => 'buyer_open_uid',
            ),
        ),
        'ind_regtime'     => array(
            'columns' => array(
                0 => 'regtime',
            ),
        ),
        'ind_disabled'    => array(
            'columns' => array(
                0 => 'disabled',
            ),
        ),
        'uni_user' =>
        array (
          'columns' =>
          array (
            0 => 'uname',
            1 => 'buyer_open_uid',
            2 => 'shop_type',
          ),
        ),
        'ind_member_shopid' => array(
            'columns' => array(
                0 => 'uname_md5',
                1 => 'shop_id',
            ),
            'prefix' => 'unique',
        ),
    ),
    'comment' => '会员表',
    'charset' => 'utf8mb4',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
