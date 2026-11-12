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


/* TODO: Add code here */
class desktop_finder_roles{
    var $column_control = '角色操作';
    var $column_workground = '权限';
    protected $vue_mode = false;

    function __construct($app){
        $this->app= $app;
        $this->obj_roles = kernel::single('desktop_roles');
    }

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function column_control($row){
        $is_user_roles_edit = kernel::single('desktop_user')->has_permission('user_roles_edit');
        if(!$is_user_roles_edit){
           return false;
        }

        // Vue mode: return structured array for dynamic row actions
        if ($this->vue_mode) {
            return [
                ['key' => 'edit', 'label' => '编辑', 'action' => 'edit'],
            ];
        }

        // Legacy HTML mode
        $render = $this->app->render();
        $render->pagedata['role_id'] = $row['role_id'];
        return $render->fetch('users/href.html');
      }
    
    function detail_indo($param_id){
        $render = $this->app->render();
        $opctl = $this->app->model('roles');
        $menus = $this->app->model('menus');
        $sdf_roles = $opctl->dump($param_id);
        $render->pagedata['roles'] = $sdf_roles;
        $workground = unserialize($sdf_roles['workground']);

        $menu_rows = $menus->getList('*',array('menu_type' => 'menu'));
        $permission_rows = $menus->getList('*',array('menu_type' => 'permission'));
        $workgrounds = $menus->getList('*',array('menu_type'=>'workground','disabled'=>'false','display'=>'true'));
        $widgets = $menus->getList('*',array('menu_type'=>'widgets'));
        $adminpanel_rows = $menus->getList('*',array('menu_type' => 'adminpanel'));

        $permission_index = array();
        foreach((array)$permission_rows as $permission_row){
            $permission_index[$permission_row['permission']] = $permission_row;
        }

        $menu_permissions = array();
        $workground_permissions = array();
        $menu_workground = array();
        foreach((array)$menu_rows as $menu_row){
            $permission = $menu_row['permission'];
            $menu_permissions[$permission] = 1;
            if(!isset($workground_permissions[$menu_row['workground']])){
                $workground_permissions[$menu_row['workground']] = array();
            }
            if(!isset($workground_permissions[$menu_row['workground']][$permission])){
                $workground_permissions[$menu_row['workground']][$permission] = 1;
            }
            if(in_array($permission,(array)$workground)){
                $menu_workground[$menu_row['workground']] = 1;
            }
        }

        foreach($workgrounds as $key => $workground_row){
            $permissions = array();
            if(isset($workground_permissions[$workground_row['workground']])){
                foreach($workground_permissions[$workground_row['workground']] as $permission => $unused){
                    if(!isset($permission_index[$permission])){
                        continue;
                    }
                    $permission_row = $permission_index[$permission];
                    $permission_row['checked'] = in_array($permission,(array)$workground) ? 1 : 0;
                    $permission_row['role_workground'] = $workground_row['workground'];
                    $permissions[] = $permission_row;
                }
            }
            $workgrounds[$key]['permissions'] = $permissions;
            if(isset($menu_workground[$workground_row['workground']])){
                $workgrounds[$key]['checked'] = 1;
            }
        }

        $__widgets = array();
        foreach((array)$widgets as $widget){
            if(in_array($widget['addon'],(array)$workground)){
                $__widgets[] = $widget;
            }
        }

        $adminpanel_permissions = array();
        foreach((array)$adminpanel_rows as $adminpanel_row){
            $adminpanel_permissions[$adminpanel_row['permission']] = 1;
        }

        $adminpanels = array();
        $others = array();
        $flg = 0;
        $othersflg = 0;
        foreach((array)$permission_rows as $permission_row){
            $permission = $permission_row['permission'];
            if($permission_row['disabled'] == 'false' && !isset($menu_permissions[$permission]) && isset($adminpanel_permissions[$permission])){
                $permission_row['checked'] = in_array($permission,(array)$workground) ? 1 : 0;
                if($permission_row['checked']){
                    $flg = 1;
                }
                $adminpanels[] = $permission_row;
                continue;
            }
            if($permission_row['disabled'] == 'false' && $permission_row['display'] == 'true' && !isset($menu_permissions[$permission]) && !isset($adminpanel_permissions[$permission])){
                $permission_row['checked'] = in_array($permission,(array)$workground) ? 1 : 0;
                if($permission_row['checked']){
                    $othersflg = 1;
                }
                $others[] = $permission_row;
            }
        }

        $render->pagedata['workgrounds'] = $workgrounds;
        $render->pagedata['adminpanels'] = $adminpanels;
        $render->pagedata['widgets'] = $__widgets;
        $render->pagedata['others'] = $others;
        $render->pagedata['othersflg'] = $othersflg;
        $render->pagedata['flg'] = $flg;

        if ($this->vue_mode) {
            return [
                'items' => [
                    'role_id' => (int) ($param_id ?? 0),
                    'role_name' => $sdf_roles['role_name'] ?? '',
                    'workground' => $workground,
                    'data_authority' => unserialize($sdf_roles['data_authority']) ?: [],
                ],
                'workgrounds' => $render->pagedata['workgrounds'],
                'widgets' => $render->pagedata['widgets'],
                'adminpanels' => $render->pagedata['adminpanels'],
                'others' => $render->pagedata['others'],
                'flg' => $flg,
                'othersflg' => $othersflg,
            ];
        }

        echo $render->fetch('users/users_roles.html');
    }
}
?>
