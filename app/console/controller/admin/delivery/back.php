<?php
class console_ctl_admin_delivery_back extends desktop_controller
{
    var $name = "发货单追回列表";
    var $workground = "console_center";
    
    /**
     * 追回发货单列表
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
            'title' => '追回发货单',
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
