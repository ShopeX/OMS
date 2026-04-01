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

class console_ctl_admin_delivery_back extends desktop_controller {

    var $name = "发货单追回列表";
    var $workground = "console_center";


    /**
     * 
     * 发货单列表
     */
    function index(){
        $user = kernel::single('desktop_user');
        $actions[] = array(
            'label'  => '导出',
            'submit' => 'index.php?app=omedlyexport&ctl=ome_delivery&act=index&action=export&status=return_back',
            'target' => 'dialog::{width:600,height:300,title:\'导出\'}'
        );
       $base_filter = array(
           'parent_id' => 0,
           'status' => array('return_back'),
           'type' => 'normal',
           'disabled' => 'false',
           'pause' => 'false',
           'sync|bthan' => 0,
        );
        $base_filter = array_merge($base_filter,$_GET);

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }

        $params = array(
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_import'=>false,
            'use_buildin_export'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab'=>true,
            'actions' => $actions,
            'title'=>'发货单',
            'base_filter' => $base_filter,
        );

        $this->finder('console_mdl_delivery', $params);
    }

    function cancel_list()
    {
        $actions = array();
       
        $base_filter = array(
            'parent_id' => 0,
            'status' => array('cancel','back'),
            'type' => 'normal',
            'disabled' => 'false',
            'pause' => 'false',
            'sync|bthan' => 0,
        );
        $base_filter = array_merge($base_filter,$_GET);

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }

        $actions[] =  array(
            'label'=>'导出',
            'submit'=>'index.php?app=omedlyexport&ctl=ome_delivery&act=index&action=export&status=cancel',
            'target'=>'dialog::{width:600,height:300,title:\'导出\'}'
        );

        $params = array(
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_import'=>false,
            'use_buildin_export'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab'=>true,
            'actions' => $actions,
            'title'=>'取消发货单',
            'base_filter' => $base_filter,
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        );

        
        $this->finder('console_mdl_delivery', $params);
    }
   
}
