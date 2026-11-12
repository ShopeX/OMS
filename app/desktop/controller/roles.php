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

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

class desktop_ctl_roles extends desktop_controller{
    
    var $workground = 'desktop_ctl_system';    
    var $obj_roles;
    private desktop_capability_roles $capability;
    
    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct($app)
    {
        parent::__construct($app);
        $this->obj_roles = kernel::single('desktop_roles');
        $this->capability = new desktop_capability_roles();
        header("cache-control: no-store, no-cache, must-revalidate");
    }
    
    #[RequirePermission('users')]
    function index(){
        $params = array(
            'title'=>app::get('desktop')->_('角色'),
            'actions'=>array(
                array('label'=>app::get('desktop')->_('新建角色'),'href'=>'index.php?ctl=roles&act=addnew','target'=>'dialog::{width:720,height:500,title:\''.app::get('desktop')->_('新建角色').'\'}'),
            )
        );
        $is_user_roles_add = kernel::single('desktop_user')->has_permission('user_roles_add');
        if(!$is_user_roles_add){
            unset($params['actions']);
        }
        $this->finder('desktop_mdl_roles',$params);
    }

    #[RequirePermission('user_roles_add')]
    function addnew(){

        // 老的权限加载逻辑（注释掉，保留作为参考）
        /*
        $permissions = kernel::single('desktop_roles')->get_roles();

        $this->pagedata['widgets']     = $permissions['widgets'];
        $this->pagedata['workgrounds'] = $permissions['workgrounds'];
        $this->pagedata['adminpanels'] = $permissions['adminpanels'];
        $this->pagedata['others']      = $permissions['others'];
        */

        // 新的权限加载逻辑：使用与编辑角色相同的权限加载逻辑，保持一致性
        $menus = $this->app->model('menus');
        
        // 工作组权限
        $workgrounds = app::get('desktop')->model('menus')->getList('*',array('menu_type'=>'workground','disabled'=>'false','display'=>'true'));
        foreach($workgrounds as $k => $v){
            $workgrounds[$k]['permissions'] = $this->obj_roles->get_permission_per($v['menu_id'], array());
        }

        // 挂件权限
        $widgets = app::get('desktop')->model('menus')->getList('*',array('menu_type'=>'widgets'));
        foreach($widgets as $key=>$widget){
            $widgets[$key]['checked'] = false;
        }

        // 控制面板权限
        $adminpanels = $this->obj_roles->get_adminpanel(0, array());
        
        // 其他权限
        $others = $this->obj_roles->get_others(array());

        $this->pagedata['widgets']     = $widgets;
        $this->pagedata['workgrounds'] = $workgrounds;
        $this->pagedata['adminpanels'] = $adminpanels;
        $this->pagedata['others']      = $others;

        $this->page('users/add_roles.html');
    }
    #[RequirePermission('user_roles_add')]
    function save()
    {
        $this->begin();
        try {
            $this->capability->create($_POST);
            $this->end(true, '保存成功');
        } catch (Throwable $exception) {
            $this->end(false, $exception->getMessage());
        }
    }
    #[RequirePermission('user_roles_edit')]
    function edit($roles_id){
        $param_id = $roles_id;
        if($_POST){
            $this->begin();
            try {
                $roleId = (int) ($_POST['role_id'] ?? $param_id);
                $this->capability->update($roleId, $_POST);
                $this->end(true, '保存成功');
            } catch (Throwable $exception) {
                $this->end(false, $exception->getMessage());
            }
        }else{
            $opctl = $this->app->model('roles');
            $menus = $this->app->model('menus');
            $sdf_roles = $opctl->dump($param_id);
            $this->pagedata['roles'] = $sdf_roles;
            $workground = !empty($sdf_roles['workground']) ? unserialize((string)$sdf_roles['workground']) : [];
            foreach((array)$workground as $v){
                $menuname = $menus->getList('*',array('menu_type' =>'menu','permission' => $v));
                foreach($menuname as $val){
                    $menu_workground[] = $val['workground'];
                }
            }
            $menu_workground = array_unique((array)$menu_workground);

            $workgrounds = app::get('desktop')->model('menus')->getList('*',array('menu_type'=>'workground','disabled'=>'false','display'=>'true'));
            foreach($workgrounds as $k => $v){
                $workgrounds[$k]['permissions'] = $this->obj_roles->get_permission_per($v['menu_id'],$workground);
                if(in_array($v['workground'],(array)$menu_workground)){
                    $workgrounds[$k]['checked'] = 1;
                }
            }

            $widgets = app::get('desktop')->model('menus')->getList('*',array('menu_type'=>'widgets'));
            foreach($widgets as $key=>$widget){
                if(in_array($widget['addon'],$workground)){
                    $widgets[$key]['checked'] = true;
                }
            }

            //数据权限
            $data_authority = !empty($sdf_roles['data_authority']) ? unserialize((string)$sdf_roles['data_authority']) : [];
            if($data_authority){
                foreach($data_authority as $k => $v){
                    if($v){
                        $data_authorities[$v]['checked'] = 1;
                    }
                }
            }

            $this->pagedata['widgets'] = $widgets;
            $this->pagedata['workgrounds'] = $workgrounds;
            $this->pagedata['dataauthorities'] = $data_authorities;
            $this->pagedata['adminpanels'] = $this->obj_roles->get_adminpanel($param_id,$workground);
            $this->pagedata['others'] = $this->obj_roles->get_others($workground);
            $this->page('users/edit_roles.html');
        }
    }
}
