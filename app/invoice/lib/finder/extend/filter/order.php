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


class invoice_finder_extend_filter_order{
    
    function get_extend_colums(){
        // 线上店铺列表
        $filter = array(
            's_type' => '1',
        );
        $shopList = app::get('ome')->model('shop')->getList("name,shop_id", $filter);
        
        // format
        $shopName = array_column($shopList, 'name','shop_id');

        $db = array(
            'order' => array(
                'columns' => array(
                    'shop_id' => array(
                        'type'          => $shopName,
                        'label'         => '来源店铺',
                        'width'         => 100,
                        'editable'      => false,
                        'in_list'       => true,
                        'filtertype'    => 'fuzzy_search_multiple',
                        'filterdefault' => true,
                    ),
                )
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
            $db['order']['columns']['org_id|in'] = array('type' => $orgRoles, 'label' => '运营组织', 'editable' => false, 'width' => 60, 'filtertype' => 'normal', 'filterdefault' => true, 'in_list' => true, 'default_in_list' => true);
        }
        return $db;        
    }
    
}