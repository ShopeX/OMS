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
$db['delivery']=array (
  'columns' =>
  array (
    'delivery_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'extra' => 'auto_increment',
      'comment' => '自增主键ID'
    ),
    'idx_split' =>
    array(
        'type' => 'bigint',
        'required' => true,
        'label' => '订单内容',
        'comment' => '订单明细整型索引',
        'editable' => false,
        'width' => 160,
        'in_list' => false,
        'default_in_list' => false,
        'default' => 0,
    ),
    'skuNum' =>
    array(
        'type' => 'number',
        'required' => true,
        'label' => '商品种类',
        'comment' => '发货单SKU总数',
        'editable' => false,
        'in_list' => false,
        'default' => 0,
    ),
    'itemNum' =>
    array(
        'type' => 'number',
        'required' => true,
        'label' => '商品总数量',
        'comment' => '发货单SKU行数',
        'editable' => false,
        'in_list' => false,
        'default' => 0,
    ),
    'bnsContent' =>
    array(
        'type' => 'text',
        'required' => true,
        'label' => '具体订单内容',
        'comment' => '发货商品明细',
        'editable' => false,
        'in_list' => false,
        'default' => '',
    ),
    'delivery_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '发货单号',
      'comment' => '发货单号',
      'editable' => false,
      'width' =>140,
      'searchtype' => 'nequal',
      'filtertype' => 'yes',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'is_title' => true,
    ),
    'delivery_group' =>
    array (
      'type' => 'table:order_type@omeauto',
      'label' => '发货单分组',
      'comment' => '发货单分组',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
    ),
    'sms_group' =>
    array (
      'type' => 'table:order_type@omeauto',
      'label' => '短信发送分组',
      'comment' => '短信发送分组',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
    ),
    'member_id' =>
    array (
      'type' => 'table:members@ome',
      'label' => '会员用户名',
      'comment' => '会员ID,关系ome_members.member_id',
      'editable' => false,
      'width' =>180,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'is_protect' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'label' => '是否保价',
      'comment' => '保价状态,可选值:true(是),false(否)',
      'editable' => false,
      'in_list' => true,
    ),
    'cost_protect' =>
    array (
       'type' => 'money',
      'default' => '0',
      'label' => '保价费用',
      'required' => false,
      'editable' => false,
    ),
    'is_cod' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'label' => '是否货到付款',
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
      'comment'=>'是否货到付款,可选值:true(是),false(否)',
    ),
    'delivery' =>
    array (
      'type' => 'varchar(20)',
      'label' => '配送方式',
      'comment' => '配送方式(货到付款、EMS...)',
      'editable' => false,
      'in_list' => false,
      'width' =>65,
      'default_in_list' => false,
      'is_title' => true,
    ),
    'logi_id' =>
    array (
      'type' => 'table:dly_corp@ome',
      'comment' => '物流公司ID',
      'editable' => false,
      'label' => '物流公司',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'logi_name' =>
    array (
      'type' => 'varchar(100)',
      'label' => '物流公司',
      'comment' => '物流公司名称',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'logi_no' =>
    array (
      'type' => 'varchar(50)',
      'label' => '物流单号',
      'comment' => '物流单号',
      'editable' => false,
      'width' =>110,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'nequal',
    ),
    'logi_number' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 1,
      'editable' => false,
      'label' => '包裹总数',
      'comment' => '物流包裹总数',
    ),
    'delivery_logi_number' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'label' => '已发货包裹数',
      'comment' => '已发货物流包裹数',
    ),
    'ship_name' =>
    array (
      'type' => 'varchar(255)',
      'label' => '收货人',
      'comment' => '收货人姓名',
      'editable' => false,
      'searchtype' => 'tequal',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>180,
      'in_list' => true,
      'default_in_list' => true,
      'sdfpath' => 'consignee/name',
    ),
    'ship_area' =>
    array (
      'type' => 'region',
      'label' => '收货地区',
      'comment' => '收货人地区',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>130,
      'in_list' => true,
      'default_in_list' => true,
      'sdfpath' => 'consignee/area',
    ),
    'ship_province' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'sdfpath' => 'consignee/province',
      'comment' => '收货人省份'
    ),
    'ship_city' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'sdfpath' => 'consignee/city',
      'comment' => '收货人城市'
    ),
    'ship_district' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'sdfpath' => 'consignee/district',
      'comment' => '收货人地区'
    ),
    'ship_town' =>
    array (
        'type' => 'varchar(50)',
        'editable' => false,
        'sdfpath' => 'consignee/town',
        'comment' => '收货人镇'
    ),
    'ship_village' =>
    array (
        'type' => 'varchar(50)',
        'editable' => false,
        'sdfpath' => 'consignee/village',
        'comment' => '收货人村',
    ),
    'ship_addr' =>
    array (
      'type' => 'text',
      'label' => '收货地址',
      'comment' => '收货人地址',
      'editable' => false,
      'filtertype' => 'normal',
      'width' =>180,
      'in_list' => true,
      'sdfpath' => 'consignee/addr',
    ),
    'ship_zip' =>
    array (
      'type' => 'varchar(20)',
      'label' => '收货邮编',
      'comment' => '收货人邮编',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => false,
      'sdfpath' => 'consignee/zip',
    ),
    'ship_tel' =>
    array (
      'type' => 'varchar(200)',
      'label' => '收货人电话',
      'comment' => '收货人电话',
      'editable' => false,
      'in_list' => true,
      'sdfpath' => 'consignee/telephone',
      'width' =>180,
    ),
    'ship_mobile' =>
    array (
      'type' => 'varchar(200)',
      'label' => '收货人手机',
      'comment' => '收货人手机',
      'editable' => false,
      'in_list' => true,
      'sdfpath' => 'consignee/mobile',
      'width' =>180,
    ),
    'ship_email' =>
    array (
      'type' => 'varchar(150)',
      'label' => '收货人Email',
      'comment' => '收货人Email',
      'editable' => false,
      'in_list' => true,
      'sdfpath' => 'consignee/email',
    ),
    'create_time' =>
    array (
      'type' => 'time',
      'label' => '单据创建时间',
      'comment' => '单据生成时间',
      'editable' => false,
      'filtertype' => 'time',
      'filterdefault' => true,
      'in_list' => true,
    ),
    'status' => array (
      'type' => array (
        'succ' => '已发货',
        'failed' => '发货失败',
        'cancel' => '已取消',
        'progress' => '等待配货',
        'timeout' => '超时',
        'ready' => '待处理',
        'stop' => '暂停',
        'back' => '打回',
        'return_back' => '退回',
      ),
      'default' => 'ready',
      'width' => 150,
      'required' => true,
      'editable' => false,
      'label' => '发货状态',
      'comment' => '发货状态,可选值:succ(已发货),failed(发货失败),cancel(已取消),progress(等待配货),timeout(超时),ready(待处理),stop(暂停),back(打回),return_back(退回)',
    ),
    'memo' =>
    array (
      'type' => 'longtext',
      'label' => '备注',
      'comment' => '备注',
      'editable' => false,
      'in_list' => true,
    ),
    'disabled' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'editable' => false,
      'label' => '无效',
      'in_list' => true,
      'comment' => '删除状态,可选值:true(是),false(否)'
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'editable' => false,
      'label' => '仓库',
      'width' => 110,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'panel_id' => 'delivery_finder_top',
      'comment' => '仓库ID,关联ome_branch.branch_id',
    ),
    'stock_status' =>
    array (
      'type' => 'bool',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'required' => true,
      'editable' => false,
      'width' => 75,
      'default' => 'false',
      'comment' => '配货单是否打印',
      'label' => '配货单打印',
    ),
    'deliv_status' =>
    array (
      'type' => 'bool',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'required' => true,
      'editable' => false,
      'width' => 75,
      'default' => 'false',
      'comment' => '商品清单是否打印',
      'label' => '发货单打印',
    ),
    'expre_status' =>
    array (
      'type' => 'bool',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'required' => true,
      'width' => 75,
      'editable' => false,
      'default' => 'false',
      'comment' => '快递单是否打印',
      'label' => '快递单打印',
    ),
    'verify' =>
    array (
      'type' => 'bool',
      'required' => true,
      'editable' => false,
      'default' => 'false',
      'comment' => '是否校验',
    ),
    'process' =>
    array (
      'type' => 'bool',
      'required' => true,
      'editable' => false,
      'default' => 'false',
      'comment' => '是否发货',
    ),
    'net_weight' =>
    array (
      'type' => 'money',
      'editable' => false,
      'comment' => '商品重量',
    ),
    'weight' =>
    array (
      'type' => 'money',
      'editable' => false,
      'comment' => '包裹重量',
    ),
    'last_modified' =>
    array (
      'label' => '最后更新时间',
      'type' => 'last_modify',
      'editable' => false,
      'in_list' => true,
    ),
    'delivery_time' =>
    array (
      'type' => 'time',
      'label' => '发货时间',
      'comment' => '发货时间',
      'editable' => false,
      'filtertype' => 'time',
      'filterdefault' => true,
      'in_list' => true,
    ),
    'delivery_cost_expect' =>
    array (
      'type' => 'money',
      'default' => '0',
      'editable' => false,
      'comment' => '预计物流费用(包裹重量计算的费用)',
    ),
    'delivery_cost_actual' =>
    array (
      'type' => 'money',
      'editable' => false,
      'comment' => '实际物流费用(物流公司提供费用)',
    ),
    'parent_id' =>
    array (
      'type' => 'bigint unsigned',
      'editable' => false,
      'default' => 0,
      'comment' => '关联的父级发货单ID',
    ),
    'bind_key' =>
    array (
      'type' => 'varchar(32)',
      'editable' => false,
      'comment' => '拼接店铺仓库收货地址会员到付的MD5字段',
    ),
    'type' =>
    array (
      'type' =>
      array (
        'normal' => '普通发货单',
        'reject' => '拒绝退货的发货单',
        'vopczc' => '唯品会仓中仓发货单',
      ),
      'default' => 'normal',
      'editable' => false,
      'comment' => '发货单类型,可选值:normal(普通发货单),reject(拒绝退货的发货单),vopczc(唯品会仓中仓发货单)',
    ),
    'is_bind' =>
    array (
      'type' => 'bool',
      'required' => true,
      'editable' => false,
      'default' => 'false',
      'comment' => '是否发货单子单,可选值:true(是),false(否)'
    ),
    'shop_id' =>
    array (
      'type' => 'table:shop@ome',
      'label' => '来源店铺',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'filtertype' => 'normal',
    ),
   'order_createtime' =>
    array (
      'type' => 'time',
      'label' => '成单时间',
      'width' => 130,
      'editable' => false,
      'filtertype' => 'time',
      'filterdefault' => true,
      'in_list' => false,
    ),
    'pause' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => 'false',
      'comment' => '是否暂停,可选值:true(是),false(否)',
    ),
    'ship_time' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'sdfpath' => 'consignee/r_time',
      'label' => '要求到货时间',
      'comment' => '要求到货时间',
      'in_list' => true,
    ),
    'op_id' =>
    array (
      'type' => 'table:account@pam',
      'editable' => false,
      'required' => true,
      'comment' => '操作员ID,关联pam_account.account_id',
    ),
    'op_name' =>
    array (
      'type' => 'varchar(30)',
      'editable' => false,
      'comment' => '操作人姓名',
    ),
    'deli_cfg' => array(
        'type' => 'varchar(20)',
        'default' => '',
        'editable' => false,
        'required' => true,
        'comment' => '打印配置项',
    ),
    'print_status' =>
    array (
      'type' => 'tinyint(1)',
      'default' => 0,
      'width' => 150,
      'required' => true,
      'comment' => '打印状态',
    ),
    'sync' =>
    array (
      'type' => 'int unsigned',
      'default' => 0,
      'label' => '同步状态',
      'editable' => false,
    ),
    'sync_send_succ_times' =>
    array (
      'type' => 'tinyint',
      'default' => 0,
      'label' => '同步推送成功次数',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'sync_status' =>
    array (
      'type' => array(
          '0' => '无',
          '1' => '发起中',
          '2' => '推送失败',
          '3' =>'推送成功',
          '4' =>'取消失败',
          '5' =>'取消成功',
          '6' =>'不自动推送',
          '9'=>'查询失败',
          '10'=>'查询成功',
          '11' => '通知发货失败',
          '12' => '通知发货成功',
      ),
      'default' => '0',
      'width' => 130,
      'required' => true,
      'label' => '同步状态',
      'filtertype' => 'yes',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'comment' => 'WMS状态,可选值:0(无),1(发起中),2(推送失败),3(推送成功),4(取消失败),5(取消成功),6(不自动推送),9(查询失败),10(查询成功),11(通知发货失败),12(通知发货成功)',
    ),
    'sync_code' => array (
        'type' => 'varchar(32)',
        'label' => '同步WMS错误码',
        'editable' => false,
        'filtertype' => 'normal',
        'filterdefault' => true,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'sync_msg' =>
    array (
      'type' => 'text',
      'label' => '同步失败原因',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'logi_status' => array (
      'type' => array(
          '0' => '无',
          '1' => '已揽收',
          '2' => '在途中',
          '3' => '已签收',
          '4' => '退件/问题件',
          '5' => '待取件',
          '6' => '待派件',
      ),
      'default' => '0',
      'label' => '物流跟踪状态',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'nequal',
      'comment' => '物流跟踪状态,可选值: 0(无),1(已揽收),2(在途中),3(已签收),4(退件/问题件),5(待取件),6(待派件)',
    ),
    'embrace_time' =>
    array (
      'type' => 'time',
      'label' => '快件揽收时间',
      'width' => 130,
      'editable' => false,
      'filtertype' => 'time',
      'in_list' => true,
    ),
    'sign_time' =>
    array (
      'type' => 'time',
      'label' => '客户签收时间',
      'width' => 130,
      'editable' => false,
      'filtertype' => 'time',
      'in_list' => true,
    ),
    'problem_time' =>
    array (
      'type' => 'time',
      'label' => '问题件时间',
      'width' => 130,
      'editable' => false,
      'filtertype' => 'time',
    ),
    'order_type' =>
    array (
      'type' =>
      array (
        'normal' => '普通',
        'presale' => '预售',
      ),
      'default' => 'normal',
      'label' => '订单类型',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'comment' => '订单类型,可选值:normal(普通),presale(预售)',
    ),
    'bool_status' =>
      array(
          'type' => 'int unsigned',
          'label' => '发货单二进制状态',
          'editable' => false,
          'default' => '0',
      ),
    'bool_type'=>
    array(
        'type' => 'int unsigned',
        'label' => '发货单标识',
        'editable' => false,
        'default' => '0',
    ),
    'platform_order_bn' =>
    array (
      'type' => 'varchar(32)',
    
      'label' => '平台订单号',
  
      'width' => 140,
      'searchtype' => 'nequal',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'org_id' =>
    array (
      // 'type' => 'table:operation_organization@ome',
      'type' => 'number',
      'label' => '运营组织',
      'editable' => false,
      'width' => 60,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'comment' => '运营组织,关联pam_operation_organization.organization_id',
    ),
    'shop_type' =>
    array (
      'type' => 'varchar(50)',
      'label' => '店铺类型',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
    ),
    'shipping_type' =>
    array (
      'type' => 'char(3)',
      'default' => '0',
      'label' => '配送方式',
      'editable' => false,
      'width' =>110,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'original_delivery_bn' =>
    array (
      'type' => 'varchar(30)',
      'default' => '0',
      'label' => '京东订单号',
      'editable' => false,
      'width' => 110,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'is_wms_gift' => array(
        'type' => 'bool',
        'default' => 'false',
        'label' => '是否WMS赠品',
        'editable' => false,
        'filtertype' => 'normal',
        'in_list' => true,
        'default_in_list' => false,
    ),
    'wms_channel_id' => array (
        'type' => 'varchar(30)',
        'label' => '渠道ID',
        'editable' => true,
        'filtertype' => 'normal',
        'filterdefault' => true,
        'in_list' => true,
        'default_in_list' => false,
    ),
    'cpup_service'         => array(
        'type'    => 'varchar(200)',
        'label'   => '物流升级服务',
        'default' => '0',
    ),
    'promised_collect_time' =>
        array (
            'type' => 'time',
            'label' => '承诺最晚揽收时间',
            'comment' => '承诺最晚揽收时间',
            'width' => 130,
            'editable' => false,
            'filtertype' => 'time',
            'in_list' => true,
        ),
    'promised_sign_time' =>
        array (
            'type' => 'time',
            'label' => '承诺最晚送达时间',
            'comment' => '承诺最晚送达时间',
            'width' => 130,
            'editable' => false,
            'filtertype' => 'time',
            'in_list' => true,
        ),
    'promise_outbound_time' => array (
          'type' => 'time',
          'label' => '承诺最晚出库时间',
          'comment' => '承诺最晚出库时间',
          'width' => 130,
          'editable' => false,
          'in_list' => true,
          'default_in_list' => false,
    ),
    'plan_sign_time' => array (
          'type' => 'time',
          'label' => '承诺计划送达时间',
          'comment' => '承诺计划送达时间',
          'width' => 130,
          'editable' => false,
          'in_list' => true,
          'default_in_list' => false,
    ),
    'cpup_addon' =>
        array (
            'type' => 'serialize',
            'label'   => '物流升级服务扩展',
            'editable' => false,
        ),
    'wms_status' => array (
        'type' => array (
            '0' => '无',
            '5' => '异常',
            '10' => '仓库接单',
            '15' => '打印',
            '20' => '拣货',
            '25' => '复核',
            '30' => '打包',
            '40' => '确认出库',
        ),
        'default' => '10',
        'width' => 110,
        'editable' => false,
        'label' => '仓库作业状态',
        'in_list' => true,
        'default_in_list' => false,
        'comment' => '仓库作业状态,可选值:0(无),5(异常),10(仓库接单),15(打印),20(拣货),25(复核),30(打包),40(确认出库)',
    ),
    'promise_service' => array(
        'type' => 'varchar(255)',
        'label' => '物流服务标签',
        'default' => '',
        'in_list' => false,
        'default_in_list' => false,
    ),
    'delivery_order_number' =>
        array (
            'type' => 'varchar(32)',
            'default' => '',
            'label' => '送货单号',
            'width' => 130,
            'in_list' => true,
            'default_in_list' => true,
            'is_title' => true,
        ),
    'wms_msg'    => array(
        'type'            => 'text',
        'label'           => 'WMS操作内容',
        'default_in_list' => true,
        'in_list'         => true,
    ),
    'is_sync' =>
        array (
            'type' => 'bool',
            'required' => true,
            'default' => 'false',
            'editable' => 'false',//(协同版)
        ),
    'has_checked' =>
        array (
            'type' => 'intbool',
            'default' => '0',
            'label' => '是否经过重复验证',//(协同版)
        ),
      'betc_id' => array(
          'type' => 'int unsigned',
          'default' => 0,
          'editable' => false,
          'label' => '贸易公司ID',
          'in_list' => true,
          'default_in_list' => false,
      ),
      'cos_id' => array(
          'type' => 'int unsigned',
          'default' => 0,
          'editable' => false,
          'label' => '组织架构ID',
          'in_list' => true,
          'default_in_list' => false,
      ),
      'logistics_status'=>array(
          'type' => 'varchar(30)',
          'default' => '0',
          'label' => '物流状态',
          'editable' => false,
          'width' => 110,
          'comment' => '仓库作业状态,可选值:0(无),100(已揽收),200(运输中),201(派送中),499(已签收)',
      ),
      
  ),
  'index' =>
  array (
    'ind_delivery_bn' =>
    array (
      'columns' =>
      array (
        0 => 'delivery_bn',
      ),
      'prefix' => 'unique',
    ),
    'ind_logi_no' =>
    array (
      'columns' =>
      array (
        0 => 'logi_no',
        1 => 'original_delivery_bn',
      ),
      'prefix' => 'unique',
    ),
    'ind_stock_status' =>
    array (
      'columns' =>
      array (
        0 => 'stock_status',
      ),
    ),
    'ind_deliv_status' =>
    array (
      'columns' =>
      array (
        0 => 'deliv_status',
      ),
    ),
    'ind_expre_status' =>
    array (
      'columns' =>
      array (
        0 => 'expre_status',
      ),
    ),
    'ind_verify' =>
    array (
      'columns' =>
      array (
        0 => 'verify',
      ),
    ),
    'ind_process' =>
    array(
        'columns' =>
        array(
            0 => 'process',
        ),
    ),
    // 'ind_type' =>
    // array(
    //     'columns' =>
    //     array(
    //         0 => 'type',
    //     ),
    // ),
    'ind_bind_key' =>
    array(
        'columns' =>
        array(
            0 => 'bind_key',
        ),
    ),
    'ind_order_createtime' =>
    array(
        'columns' =>
        array(
            0 => 'order_createtime',
        ),
    ),
    'ind_delivery_time' =>
    array(
        'columns' =>
        array(
            0 => 'delivery_time',
        ),
    ),
    'ind_sync_status' =>
    array(
        'columns' =>
        array(
            0 => 'sync_status',
        ),
    ),
    'ind_sync' =>
    array(
        'columns' =>
        array(
            0 => 'sync',
        ),
    ),
    'ind_bool_status' =>
    array(
        'columns' =>
        array(
            0 => 'bool_status',
        ),
    ),
    'ind_status_parent_id_process' =>
    array(
        'columns' =>
            array(
                0 => 'status',
                1 => 'parent_id',
                2 => 'process',
            ),
    ),
    'ind_delivery_order_number' =>
        array(
            'columns' =>
                array(
                    0 => 'delivery_order_number',
                ),
        ),
    'ind_platform_order_bn' => array(
        'columns' => array(
            0 => 'platform_order_bn',
        ),
    ),
    'ind_idx_split' => array(
        'columns' => array(
            0 => 'idx_split',
        ),
    ),
    'ind_parentid_status_type_disabled_pause_sync' => array(
        'columns' => array(
            0 => 'parent_id',
            1 => 'status',
            2 => 'type',
            3 => 'disabled',
            4 => 'pause',
            5 => 'sync',
        ),
    ),
    'idx_parentid_status_logistatus_dtime' => array(
        'columns' => array(
            0 => 'parent_id',
            1 => 'status',
            2 => 'logi_status',
            3 => 'delivery_time',
        ),
    ),
    'idx_org_parent_cod_proc_dtime_dcostact_lnumb' => array(
        'columns' => array(
            'org_id',
            'parent_id',
            'is_cod',
            'process',
            'delivery_time',
            'delivery_cost_actual',
            'logi_number',
        ),
    ),
    'idx_create_time' => array(
        'columns' => array(
            0 => 'create_time',
        ),
    ),
    'ind_betc_id' => array(
          'columns' => array(
              0 => 'betc_id',
          ),
    ),
    'ind_cos_id' => array(
          'columns' => array(
              0 => 'cos_id',
          ),
    ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev: 41996',
  'comment' => '发货单表,用于存储仓库发货信息,与订单是多对多关系',
  'charset' => 'utf8mb4',
);
