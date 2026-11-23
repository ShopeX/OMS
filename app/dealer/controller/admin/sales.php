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

/**
 * 分销销售单列表
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.28
 */
class dealer_ctl_admin_sales extends desktop_controller
{
    var $name = '代发销售单';
    var $workground = "order_center";
    
    private $_businessLib = null;
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        //lib
        $this->_businessLib = kernel::single('dealer_business');
    }
    
    public function index()
    {
        $this->title = $this->name;
        $base_filter = $this->getFilters();
        $actions = array();
        
        //导出权限
        $is_export = kernel::single('desktop_user')->has_permission('bill_export');
        
        //params
        $params = array(
            'title' => $this->title,
            'actions' => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => $is_export,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'use_view_tab' => true,
            'orderBy' => 'sale_time desc',
            'base_filter' => $base_filter,
        );
        $this->finder('dealer_mdl_sales', $params);
    }
    
    public function _views()
    {
        $salesObj = app::get('sales')->model('sales');
        
        //filter
        $base_filter = $this->getFilters();
        
        //menu
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'), 'filter'=>array(), 'optional'=>false),
            //1 => array('label'=>app::get('base')->_('金额异常'), 'filter'=>array('check'=>'true'), 'optional' => false),
        );
        
        $i = 0;
        foreach($sub_menu as $k => $v)
        {
            if (isset($v['filter'])){
                $v['filter'] = array_merge($base_filter, $v['filter']);
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = 'showtab'; //$salesObj->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app='. $_GET['app'] .'&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$i++;
        }
        
        return $sub_menu;
    }
    
    /**
     * 公共filter条件
     *
     * @return array
     */
    public function getFilters()
    {
        $base_filter = array();
        
//        //check shop permission
//        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
//        if($organization_permissions){
//            $base_filter['org_id'] = $organization_permissions;
//        }
        
        //获取操作人员的企业组织架构ID权限
        $cosData = $this->_businessLib->getOperationCosIds();
        if($cosData[1]){
            $base_filter['cos_id'] = $cosData[1];
        }else{
            $base_filter['cos_id|than'] = 0; //组织权限
            //$base_filter['betc_id|than'] = 0; //贸易公司ID
        }
        
        return $base_filter;
    }
}
