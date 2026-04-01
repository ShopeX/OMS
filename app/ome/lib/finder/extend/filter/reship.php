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

class ome_finder_extend_filter_reship
{
    function get_extend_colums()
    {
        //WMS异常错误码
        $abnormalObj = app::get('wmsmgr')->model('abnormal_code');
        $tempList = $abnormalObj->getList('abnormal_id,abnormal_code,abnormal_name', array('abnormal_type'=>'return'), 0, 100);
        $error_codes = array();
        if($tempList){
            foreach ($tempList as $key => $val)
            {
                $abnormal_code = $val['abnormal_code'];
                $error_codes[$abnormal_code] = $abnormal_code.'('.$val['abnormal_name'].')';
            }
        }
        
        //退货单异常标识
        $abnormal_status_options =kernel::single('ome_constants_reship_abnormal')->getOptions();
        
        unset($tempList);
        
        switch($_GET['ctl']){
            case 'admin_return_rchange':
                $type = array(
                    'return' => '退货',
                    'change' => '换货',
                  );
            break;
            case 'admin_delivery_refuse':
                $type = array(
                    'refuse' => '拒收退货',
                  );
            break;
            default:
                $type = array(
                    'return' => '退货',
                    'change' => '换货',
                    'refuse' => '拒收退货',
                  );
            break;           
        }
        
        //平台售后状态
        $reshipLib = kernel::single('ome_reship');
        $platformStatus = $reshipLib->get_platform_status();
        
        //店铺列表
        $shopNames = array_column(app::get('ome')->model('shop')->getList('name,shop_id'),'name','shop_id');
        
        //dbschema
        $db['reship']=array (
            'columns' => array (
                'order_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '订单号',
                    'width' => 130,
                    'filtertype' => 'textarea',
                    'searchtype' => 'nequal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'return_type' => array (
                  'type' => $type,
                  'default' => 'return',
                  'required' => true,
                  'comment' => '退换货类型',
                  'editable' => false,
                  'label' => '退换货类型',
                  'width' =>65,
                  'in_list' => true,
                  'default_in_list' => true,
                  'filtertype' => true,
                  'filterdefault' => true,
                ),
                'sync_code' => array (
                    'type' => $error_codes,
                    'label' => '同步WMS错误码',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => false,
                    'default_in_list' => false,
                ),
                'abnormal_status' => array(
                    'type' => $abnormal_status_options,
                    'label' => '异常标识',
                    'filtertype' => 'yes',
                    'filterdefault' => true,
                    'editable' => false,
                ),
                'shop_id' => array(
                    'type'          => $shopNames,
                    'label'         => '来源店铺',
                    'width'         => 100,
                    'editable'      => false,
                    'in_list'       => true,
                    'filtertype'    => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                ),
                'platform_status' => array(
                    'type' => $platformStatus,
                    'editable' => false,
                    'label' => '平台售后状态',
                    'default' => '',
                    'in_list'  => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'flag_type_in' =>
                    array(
                        'type' => array(
                            ome_reship_const::__REBACK_DELIVERY => '追回入库',
                            ome_reship_const::__REBACK_FAIL => '追回入库失败',
                            ome_reship_const::__ISCOD_ORDER => '货到付款',
                            ome_reship_const::__NEW_EXCHANGE_REPAIR => '新换货单',
                            ome_reship_const::__ZERO_INTERCEPT => '零秒退拦截',
                            ome_reship_const::__LANJIE_RUKU => '拦截入库',
                            ome_reship_const::__RESHIP_DIFF => '差异入库',
                            ome_reship_const::__RETURN_LOGI_DUP => '物流重复',
                        ),
                        'label' => '标识',
                        'editable' => false,
                        'in_list' => false,
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'default_in_list' => false
                    ),
                'flag_type_text' => array(
                    'type' => array(
                        'ydt' => '原单退',
                        'kt' => '客退',
                    ),
                    'label' => '售后类型',
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'product_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '基础物料编码',
                    'width' => 85,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
            )
        );
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $orgRoles = array();
            $orgRolesObj = app::get('ome')->model('operation_organization');
            $orgRolesList = $orgRolesObj->getList('org_id,name', array('org_id' => $organization_permissions), 0, -1);
            if($orgRolesList){
                foreach($orgRolesList as $orgRole){
                    $orgRoles[$orgRole['org_id']] = $orgRole['name'];
                }
            }
            $db['reship']['columns']['org_id|in'] = array('type' => $orgRoles, 'label' => '运营组织', 'editable' => false, 'width' => 60, 'filtertype' => 'normal', 'filterdefault' => true, 'in_list' => true, 'default_in_list' => true);
        }
        return $db;
    }
}
