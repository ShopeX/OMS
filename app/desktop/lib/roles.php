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


class desktop_roles{

    function __construct($app)
    {
        $this->app=$app;
        $this->model = $app->model('roles');    
    }
    
     //根据工作组获得所有下面的permission
    function get_permission_per($menu_id,$wg)
    { 
        $menus = $this->app->model('menus');
        $sdf = $menus->dump($menu_id);
        $workground = $sdf['workground'];
        $aMenus = $menus->getList('*',array('menu_type' => 'menu','workground' => $workground));
        
        $aTmp = array(
            'menu_group' => array(),
            'permission' => array(),
        );
        $menu_group = array();
        foreach($aMenus as $val )
        {
            $aTmp['menu_group'][] = $val['menu_group'];
            $aTmp['permission'][] = $val['permission'];
        }
        $aMenus = array_unique((array)$aTmp['permission']); //所有的permissions
        $permissions = array();

        foreach($aMenus as $val)
        {
            
            $sdf = $menus->dump(array('menu_type' => 'permission','permission' => $val));
            if($sdf){
                if(in_array($sdf['permission'], (array)$wg)){
                    $sdf['checked'] = 1;
                }
                else{
                    $sdf['checked'] = 0;
                }
                $sdf['role_workground'] = $workground;

                $permissions[] = $sdf;
            }
        }
        return $permissions;
    }
    
    //获取控制面板的permissions
    function get_adminpanel($role_id,$wg,&$flg=0)
    {
        $menus = $this->app->model('menus');
        $aPer = $menus->getList('*',array('menu_type' => 'permission','disabled' => 'false'));
        $adminpanel_per = array();
        foreach((array)$aPer as $val)
        {
            $aData = $menus->dump(array('menu_type' => 'menu','permission' => $val['permission']));
            $__aData = $menus->dump(array('menu_type' => 'adminpanel','permission' => $val['permission']));  
            if(!$aData && $__aData){
                if(in_array($val['permission'],(array)$wg)){
                    $val['checked'] = 1;
                    $flg = 1;
                }
                else{
                    $val['checked'] = 0;
                }
                $adminpanel_per[] = $val;
            }
        }
        return $adminpanel_per;
    }
    
    ////获取其他的permissions
    function get_others($wg,&$othersflg=0)
    {
        $menu = app::get('desktop')->model('menus');
        $aData = array();
        $arr_per = $menu->getList('*',array('menu_type'=>'permission','disabled'=>'false','display'=>'true'));
        #print_r($arr_per);exit;
        foreach((array)$arr_per as $key => $val)
        {
            $arr_menu = $menu->getList('menu_id',array('menu_type' => 'menu','permission' => $val['permission']));
            $__arr_menu = $menu->getList('menu_id',array('menu_type' => 'adminpanel','permission' => $val['permission']));
            if($arr_menu || $__arr_menu)
            {
                continue;
            } 
            else
            {
                if(in_array($val['permission'],(array)$wg))
                {
                    $val['checked'] = 1;
                    $othersflg = 1;
                }
                else
                {
                    $val['checked'] = 0;    
                }
                $aData[] = $val;
            }
        }
        return $aData;
    }

    public function get_roles($owner_permissions = array())
    {
       $menuModel = $this->app->model('menus');

        // 工作组权限
        $f_permissions = array();
        $permissions = $menuModel->getList('*',array('menu_type' => 'permission'));
        foreach ($permissions as $key => $value) {
            $value['checked'] = in_array($value['permission'],$owner_permissions) ? 1 : 0;

            $f_permissions[$value['permission']] = $value;
        }

        $wg_permissions = array();
        $menus = $menuModel->getList('*',array('menu_type' => 'menu'));
        foreach ($menus as $key => $value) {
            if ($f_permissions[$value['permission']]) {
                $wg_permissions[$value['workground']][$value['permission']] = $f_permissions[$value['permission']];
                $wg_permissions[$value['workground']][$value['permission']]['role_workground'] = $value['workground'];
                unset($f_permissions[$value['permission']]);
            }
        }

        $workgrounds_permissions = array();
        $workgrounds = $menuModel->getList('*',array('menu_type'=>'workground','disabled'=>'false','display'=>'true'));
        foreach ($workgrounds as $key => $value) {

            $value['permissions'] = $wg_permissions[$value['workground']];

            $workgrounds_permissions[] = $value;
        }

        // 挂件权限
        $widgets = $menuModel->getList('*',array('menu_type'=>'widgets'));

        $widgets_permissions = array();
        foreach($widgets as $key=>$value){
            $value['checked'] = in_array($value['addon'],$owner_permissions) ? 1 : 0;

            $widgets_permissions[] = $value;
        }

        // 控制面板权限
        $adminpanels = $menuModel->getList('*',array('menu_type' => 'adminpanel')); 

        $adminpanels_permissions = array();
        foreach($adminpanels as $key=>$value){
            if ($f_permissions[$value['permission']]) {
                $adminpanels_permissions[$value['permission']] = $f_permissions[$value['permission']];
                $adminpanels_permissions[$value['permission']]['checked'] = in_array($value['permission'],$owner_permissions) ? 1 : 0;

                unset($f_permissions[$value['permission']]);
            }

        }

        // 其他权限
        $others_permissions = array();
        foreach ($f_permissions as $key => $value) {
            if ($value['disabled'] == 'false' && $value['display'] == 'true') {

                $value['checked'] = in_array($value['permission'],$owner_permissions) ? 1 : 0;
                $others_permissions[] = $value;
            }
        }

        return array('widgets'=>$widgets_permissions,'workgrounds'=>$workgrounds_permissions,'adminpanels'=>$adminpanels_permissions,'others'=>$others_permissions);
    }

    /**
     * syncPermissionQueue
     * @return mixed 返回值
     */
    public function syncPermissionQueue()
    {
        $oidc = app::get('ome')->getConf('pam.passport.oidc.enable');
        if($oidc != 'true') {
            return ;
        }
        $oidcInfo = app::get('ome')->getConf('pam.passport.oidc.info');
        if(!$oidcInfo['syncpermission']) {
            return ;
        }
        $oQueue    = app::get('base')->model('queue');
        $queueData = array(
            'queue_title' => '同步权限',
            'start_time'  => time(),
            'params'      => array(
                
            ),
            'worker'      => 'desktop_roles.syncPermission',
        );
        $oQueue->save($queueData);

        $oQueue->flush();
    }

    /**
     * syncPermission
     * @param mixed $cursor_id ID
     * @param mixed $params 参数
     * @param mixed $errormsg errormsg
     * @return mixed 返回值
     */
    public function syncPermission($cursor_id, $params, $errormsg)
    {
        $data = $this->get_roles();
        $sdf = [];
        if($data['widgets']) {
            $sdf[] = ['menu_id'=>'widgets','menu_title'=>'挂件','permissions'=>$data['widgets']];
        }
        if($data['workgrounds']) {
            $sdf[] = ['menu_id'=>'workgrounds','menu_title'=>'工作组','permissions'=>$data['workgrounds']];
        }
        if($data['adminpanels']) {
            $sdf[] = ['menu_id'=>'adminpanels','menu_title'=>'控制面板','permissions'=>$data['adminpanels']];
        }
        $data['others'][] = ['menu_id'=>'customer_sensitive_info','menu_title'=>'客户敏感信息','permission'=>'customer_sensitive_info'];
        $data['others'][] = ['menu_id'=>'purchase_price','menu_title'=>'采购价','permission'=>'purchase_price'];
        $data['others'][] = ['menu_id'=>'cost_price','menu_title'=>'成本价','permission'=>'cost_price'];
        $data['others'][] = ['menu_id'=>'sale_price','menu_title'=>'销售价','permission'=>'sale_price'];
        if($data['others']) {
            $sdf[] = ['menu_id'=>'others','menu_title'=>'其他','permissions'=>$data['others']];
        }
        $operationOrg = app::get('ome')->model('operation_organization')->getList('org_id,name',['status'=>'1']);
        if($operationOrg) {
            $org = [];
            foreach ($operationOrg as $v) {
                $org[] = ['menu_id'=>'operation_organization-'.$v['org_id'],'menu_title'=>$v['name'],'permission'=>$v['org_id']];
            }
            $sdf[] = ['menu_id'=>'operation_organization','menu_title'=>'运营组织','permissions'=>$org];
        }
        $branch = app::get('ome')->model('branch')->getList('branch_id,name',['b_type'=>'1']);
        if($branch) {
            $b = [];
            foreach ($branch as $v) {
                $b[] = ['menu_id'=>'branch-'.$v['branch_id'],'menu_title'=>$v['name'],'permission'=>$v['branch_id']];
            }
            $sdf[] = ['menu_id'=>'branch','menu_title'=>'授权仓库','permissions'=>$b];
        }
        kernel::single('erpapi_router_request')->set('account','account')->user_syncPermission($sdf);
        return false;
    }
}
