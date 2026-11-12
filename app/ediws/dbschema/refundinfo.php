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

$db['refundinfo'] = array(
    'columns' => array(
        'refundinfo_id'                => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => '主键id',
            'editable' => false,
        ),
        'refundid'=>array(
            'type'            => 'varchar(150)',
            'label'           => '退货单号',
             'in_list'         => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'searchtype' => 'nequal',
            'order'           => 20,
        ),

         'outno'=>array(
            'type'            => 'varchar(150)',
            'label'           => '出库单号',
             'in_list'         => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'searchtype' => 'nequal',
            'order'           => 30,
        ),
        'amount'=>array(
            'type'      => 'decimal(20,3)',
            'label'     => '价格',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 40,
        ),
        'warecount'        => array(
            'type'            => 'number',
            'label'           => '商品总数量',
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 50,
        ),
         'applydatetime'       => array(
            'type'            => 'time',
            'label'           => '申请日期',
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 100,
            'filtertype'    => 'time',
            'filterdefault' => true,
            'in_list'       => true,
            'order'           => 60,
        ),
        'providername'=>array(
            'type'            => 'varchar(50)',
            'label'           => '供应商名称',
           
           
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 70,
        ),
        'financestatus'=>array(
            'type'            => array(
               
                '-100' => '财务驳回',
                '100'=>'待推送财务',
                '200'=>'待财务结算',
                '300'=>'财务已结算',
                '400'=>'无需结算',
                '0'=>'未结算',
                '1'=>'已结算',
            ),
            'label'           => '财务状态',
            'default'         => '1',
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,

            'order'           => 80,
        ),
        'orgname'=>array(
            'type'            => 'varchar(50)',
            'label'           => '机构',
          
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 90,

        ),
        'return_sn'         => array(
            'type'            => 'varchar(255)',
            'label'           => '退供单号',
            'is_title'        => true,
            'searchtype' => 'nequal',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 100,
        ),
        'outtypedesc'=>array(
            'type'            => 'varchar(50)',
            'label'           => '出库类型描述',
      
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 110,

        ),
        'storeid'=>array(
            'type'            => 'varchar(50)',
            'label'           => '备件库库房',
          
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 120,
        ),
        'outtype'=>array(
            'type'            => 'varchar(20)',
            'label'           => '出库类型',
      
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 130,
        ),
        'orgid'=>array(
            'type'            => 'number',
            'label'           => '备件库机构Id',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 140,
        ),
        'paytype'=>array(
            'type'            => 'varchar(20)',
            'label'           => '支付方式',
      
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,

        ),
        
        'statusname'=>array(
             'type'            => 'varchar(50)',
             'label'           => '审核状态名称',
        ),
        'salespin'=>array(

             'type'            => 'varchar(50)',
             'label'           => '采销员',

        ),
        'storename'=>array(
            'type'            => 'varchar(50)',
            'label'           => '备件库库房名称',
          
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
        ),

       

    
        'contacts'=>array(

            'type'            => 'varchar(50)',
            'label'           => '联系人',
          
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
        ),
       
        
        'create_time'       => array(
            'type'            => 'time',
            'label'           => '创建时间',
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 100,
             'filtertype'    => 'time',
            'filterdefault' => true,
            'in_list'       => true,
            
        ),
        'last_modified'=>array(
            'type' => 'last_modify',
            'editable' => false,
            'in_list' => true,
            'label'=>'最后更新时间',
            'default_in_list' => true,
             'filtertype'    => 'time',
            'filterdefault' => true,
            'in_list'       => true,
        ),
        'bill_status' => array(
            'type' => array(
                '0' => '未读取',
                '1' => '待读取',
                '2' => '已读取',
                '3' => '读取成功',
                '4' => '读取失败',
                '5' => '无需读取',
                '6'=>'暂缓',
            ),

            'default' => '0',
            'label' => '单据状态',
            'in_list'         => true,
            'default_in_list' => true,
            'editable' => false,
        ),
        'bill_status_msg' => array(
            'type' => 'text',
            'label' => '单据失败原因',
            'in_list'         => true,
            'default_in_list' => true,
            'editable' => false,
        ),

        'iostock_status' => array(
            'type' => array(
                '0' => '未入库',
                '1' => '已入库',
               
            ),

            'default' => '0',
            'label' => '入库状态',
            'in_list'         => true,
            'default_in_list' => true,
            'editable' => false,
        ),
        'iostock_time' => array(
            'type'            => 'time',
            'label'           => '入库时间',
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'order'           => 100,
            'filtertype'    => 'time',
            'filterdefault' => true,
            'in_list'       => true,
        ),
        
        
        'sync_status' => array(
            'type' => array(
                '0' => '未同步',
                '1' => '同步中',
                '2' => '已同步',
                '3' => '推送失败',
                '4' =>'无需推送',
            ),

            'default' => '0',
            'label' => '同步状态',
            'in_list'         => true,
            'default_in_list' => true,
            'editable' => false,
        ),
        'sync_msg' => array(
            'type' => 'text',

            'label' => '同步返回原因',
            'in_list'         => true,
            'default_in_list' => true,
            'editable' => false,
        ),
    ),
    'index'   => array(
        'ind_outno' => array('columns' => array('outno')),
        'ind_refundinfo_id'=> array('columns' => array('refundinfo_id')),
       
    ),
    'comment' => '售后退货',
    'engine'  => 'innodb',
    'version' => '$Rev: 40654 $',
);
