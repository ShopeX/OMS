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

class ome_ctl_admin_order extends desktop_controller{
    var $name = "订单中心";
    var $workground = "order_center";
    var $order_type = 'all';

    function _views(){
        if($this->order_type == 'abnormal'){
           $sub_menu = $this->_viewsAbnormal();
        }elseif($this->order_type == 'assigned'){
           $sub_menu = $this->_views_assigned();
        }elseif($this->order_type == 'notassigned'){
           $sub_menu = $this->_views_notassigned();
        }elseif($this->order_type == 'unmyown'){
           $sub_menu = $this->_views_unmyown();
        }elseif($this->order_type == 'myown'){
           $sub_menu = $this->_views_myown();
        }elseif($this->order_type == 'ourgroup'){
           $sub_menu = $this->_views_ourgroup();
        } elseif ($this->order_type == 'buffer') {
            $sub_menu = $this->_view_buffer();
        }elseif ($this->order_type == 'active') {
            $sub_menu = $this->_view_active();
        }else{
           //$sub_menu = $this->_viewsAll(); //去掉历史订单上面的tab
        }
        return $sub_menu;
    }

    function _viewsAll(){
        $mdl_order = $this->app->model('orders');
        $base_filter = array('disabled'=>'false','is_fail'=>'false');
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>array('disabled'=>'false','is_fail'=>'false', 'process_status|noequal'=>'is_retrial'),'optional'=>false),
            1 => array('label'=>app::get('base')->_('货到付款'),'filter'=>array('is_cod'=>'true','status' => 'active', 'process_status|noequal'=>'is_retrial'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('待支付'),'filter'=>array('pay_status' => array('0','3'),'status' => 'active', 'process_status|noequal'=>'is_retrial'),'optional'=>false),
            3 => array('label'=>app::get('base')->_('已支付'),'filter'=>array('pay_status' => 1,'status' => 'active', 'process_status|noequal'=>'is_retrial'),'optional'=>false),
            4 => array(
                'label'=>app::get('base')->_('待处理'),
                'filter'=>array(
                    'abnormal'=>'false',
                    'order_confirm_filter'=>'group_id > 0',
                    'process_status'=>array('unconfirmed','confirmed','splitting'),
                    'status'=>'active'),
                'optional'=>false),
            5 => array(
                'label'=>app::get('base')->_('已处理'),
                'filter'=>array('abnormal'=>'false','order_confirm_filter'=>'group_id > 0','process_status'=>array('splited','remain_cancel'),'status' => 'active'),
                'optional'=>false),
            6 => array('label'=>app::get('base')->_('待发货'),'filter'=>array('ship_status' =>array('0','2'),'status' => 'active', 'process_status|noequal'=>'is_retrial'),'optional'=>false),
            7 => array('label'=>app::get('base')->_('已发货'),'filter'=>array('ship_status' =>'1','status' => 'active', 'process_status|noequal'=>'is_retrial'),'optional'=>false),
            8 => array('label'=>app::get('base')->_('取消'),'filter'=>array('process_status' => 'cancel'),'optional'=>false),
            9 => array('label'=>app::get('base')->_('暂停'),'filter'=>array('pause' => 'true','status' => 'active', 'process_status|noequal'=>'is_retrial'),'optional'=>false),
        );
        $i=0;
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }

            //$v['filter']['archive'] = '0';

            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdl_order->count($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$i++;
        }
        return $sub_menu;
    }

    function _view_active(){
        $mdl_order = $this->app->model('orders');
        $base_filter = array('disabled'=>'false','is_fail'=>'false','archive'=>0);

        //指定显示订单类型
        $base_filter['order_type|in'] = kernel::single('ome_order_func')->get_normal_order_type();

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }

        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>$base_filter,'optional'=>false),
            1 => array('label'=>app::get('base')->_('货到付款'),'filter'=>array('is_cod'=>'true','status' => 'active'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('待支付'),'filter'=>array('pay_status' => array('0','3'),'status' => 'active'),'optional'=>false),
            3 => array('label'=>app::get('base')->_('已支付'),'filter'=>array('pay_status' => 1,'status' => 'active'),'optional'=>false),
            4 => array(
                'label'=>app::get('base')->_('待处理'),
                'filter'=>array(
                    'abnormal'=>'false',
                    'order_confirm_filter'=>'(group_id > 0 or op_id > 0)',
                    'process_status'=>array('unconfirmed','confirmed','splitting'),
                    'status'=>'active'),
                'optional'=>false),
            5 => array(
                'label'=>app::get('base')->_('已处理'),
                'filter'=>array('abnormal'=>'false','order_confirm_filter'=>'group_id > 0','process_status'=>array('splited','remain_cancel'),'status' => 'active'),
                'optional'=>false),
            6 => array('label'=>app::get('base')->_('待发货'),'filter'=>array('ship_status' =>array('0','2'),'status' => 'active'),'optional'=>false),
            7 => array('label'=>app::get('base')->_('已发货'),'filter'=>array('ship_status' =>'1','status' => 'active'),'optional'=>false),
            8 => array('label'=>app::get('base')->_('取消'),'filter'=>array('process_status' => 'cancel'),'optional'=>false),
            9 => array('label'=>app::get('base')->_('暂停'),'filter'=>array('pause' => 'true'),'optional'=>false),
        );
        $i=0;
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);

                //过滤复审、跨境申报类型订单
                if(empty($v['filter']['process_status']))
                {
                    $v['filter']['process_status|notin']    = array('is_declare', 'is_retrial');
                }
            }

            //$v['filter']['archive'] = '0';

            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$i++;
        }
        return $sub_menu;
    }

    /*
     * 已分派订单标签
     */
    function _views_assigned(){
        $mdl_order = $this->app->model('orders');
        $base_filter = array(
            'assigned' => 'assigned',
            'abnormal'=>'false',
            'is_fail'=>'false',
            'process_status|noequal'=>'cancel',
            'is_auto' => 'false',
        );

        //指定显示订单类型
        $base_filter['order_type|in'] = kernel::single('ome_order_func')->get_normal_order_type();

        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>$base_filter,'optional'=>false),
        );
        $groupsObj = $this->app->model("groups");
        $groups = $groupsObj->getList('*');
        foreach($groups as $group){
            $sub_menu[] = array(
                'label'=>$group['name'],
                'filter'=>array('group_id'=>$group['group_id']),
                'optional'=>false
            );
        }
        $i=0;
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }

            $v['filter']['archive'] = '0';

            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&flt=assigned&view='.$i++;
        }
        return $sub_menu;
    }

    /*
     * 未分派订单标签
     */
    function _views_notassigned(){
        $mdl_order = $this->app->model('orders');
        $base_filter = array(
            'assigned' => 'notassigned',
            'abnormal'=>'false',
            'is_fail'=>'false',
            'ship_status'=>array('0', '2'),//部分发货也显示
            'process_status|noequal'=>'cancel',
            'is_auto' => 'false',
        );

        //指定显示订单类型
        $base_filter['order_type|in'] = kernel::single('ome_order_func')->get_normal_order_type();

        $sub_menu[0] = array('label' => app::get('base')->_('全部'), 'filter' => $base_filter, 'optional' => false);
        /*$filterAttr = kernel::single('omeauto_auto_combine')->getErrorFlags();
        foreach ($filterAttr as $code => $tilte) {
            $filter = $base_filter;
            if (!empty($filter['order_confirm_filter']))
                $filter['order_confirm_filter'] .= " AND (auto_status & {$code} = {$code}) ";
            else
                $filter['order_confirm_filter'] = "(auto_status & {$code} = {$code})";
            $sub_menu[$code] = array('label' => app::get('base')->_($tilte), 'filter' => $filter, 'optional ' => false);
        }*/
        $sub_menu['989898'] = array('label' => app::get('base')->_('货到付款'), 'filter' => array_merge(array('is_cod' => 'true', 'status' => 'active'), $base_filter), 'optional' => false);

        //加入Tab栏目
        $sub_menu['989900'] = array('label' => app::get('base')->_('价格异常待处理订单'), 'filter' => array_merge(array('process_status' => 'is_retrial'), $base_filter), 'optional' => false);

        foreach ($sub_menu as $k => $v) {

            $v['filter']['archive'] = '0';

            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&flt=notassigned&view=' . $k;
        }

        return $sub_menu;
    }

    /**
     * 缓存区订单标签
     */
    function _view_buffer() {

        $mdl_order = $this->app->model('orders');

        $base_filter = $this->getBaseFilter('buffer');

        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>$base_filter,'optional'=>false),
            1 => array('label'=>app::get('base')->_('货到付款'),'filter'=>array('is_cod'=>'true'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('待支付'),'filter'=>array('pay_status' => array('0','3')),'optional'=>false),
            3 => array('label'=>app::get('base')->_('已支付'),'filter'=>array('pay_status' => 1),'optional'=>false),
        );
        $i=0;
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }

            $v['filter']['archive'] = '0';

            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&flt=buffer&view=' . $i++;
        }
        return $sub_menu;
    }

    /*
     * 待处理订单标签
     */
    function _views_unmyown(){
        $mdl_order = $this->app->model('orders');
        $base_filter = array(
            'assigned'=>'assigned',
            'abnormal'=>'false',
            'is_fail' => 'false',
            'status' => 'active',
            'is_not_combine' => 0, //是否允许审核
        );

        //指定显示订单类型
        $base_filter['order_type|in'] = kernel::single('ome_order_func')->get_normal_order_type();

        $base_filter['process_status'] = array('unconfirmed', 'confirmed', 'splitting');

        $base_filter['op_id'] = kernel::single('desktop_user')->get_id();

        // 超级管理员
        if(kernel::single('desktop_user')->is_super()){
            if(isset($base_filter['op_id'])){
                unset($base_filter['op_id']);
            }

            if (isset($base_filter['group_id'])) {
                unset($base_filter['group_id']);
            }
        }

        $sub_menu = array(
            0 => array('label' => app::get('base')->_('全部'), 'filter' => $base_filter, 'optional' => false),
        );
        $sub_menu['888888'] = array('label' => app::get('base')->_('预售'), 'filter' => array_merge($base_filter, array('order_type' => 'presale')), 'optional' => false);
        $base_filter['pause'] = 'false';

        // 关了统计数量
        if ('1' != app::get('ome')->getConf('desktop.finder.tab')) {
            // 做批量处理时，不做统计
            //if ($this->noViewCount !== true)
                //if(!kernel::single('ome_view_helper')->isIndependentRds()) {
                    $autoStatusRow = kernel::database()->select('SELECT auto_status,count(*) AS _count FROM sdb_ome_orders WHERE '.$mdl_order->_filter($base_filter).' AND is_cod="false" AND pause="false" AND auto_status>0 GROUP BY auto_status');
                //}

            foreach (kernel::single('omeauto_auto_combine')->getErrorFlags() as $code => $tilte) {
                $filter                         = $base_filter;
                $filter['is_cod']               = 'false';
                $filter['pause']                = 'false';
                $filter['order_confirm_filter'] = $filter['order_confirm_filter'] ? " AND (auto_status & {$code} = {$code}) " : "(auto_status & {$code} = {$code})";

                if (isset($autoStatusRow)) {
                    $addon = 0;
                    foreach ($autoStatusRow as $s) {
                        if (($s['auto_status'] & $code) == $code) $addon += $s['_count'];
                    }
                }

                $sub_menu[$code] = array('label' => $tilte, 'filter' => $filter, 'optional ' => false, 'addon'=>$addon);
            }
        } else {
            foreach (kernel::single('omeauto_auto_combine')->getErrorFlags() as $code => $tilte) {
                $filter                         = $base_filter;
                $filter['is_cod']               = 'false';
                $filter['pause']                = 'false';
                $filter['order_confirm_filter'] = $filter['order_confirm_filter'] ? " AND (auto_status & {$code} = {$code}) " : "(auto_status & {$code} = {$code})";

                $sub_menu[$code] = array('label' => $tilte, 'filter' => $filter, 'optional ' => false);
            }
        }
        $sub_menu['989898'] = array('label' => app::get('base')->_('货到付款'), 'filter' => array_merge(array('is_cod' => 'true'), $base_filter), 'optional' => false);
        $sub_menu['989899'] = array('label' => app::get('base')->_('暂停'), 'filter' => array_merge($base_filter, array('pause' => 'true')), 'optional' => false);

        foreach($sub_menu as $k=>$v){
            //if (!IS_NULL($v['filter'])) {
            //    $v['filter'] = array_merge($v['filter'], $base_filter);
            //}
            $v['filter']['archive'] = '0';
            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;

            $sub_menu[$k]['addon'] = isset($v['addon']) ? $v['addon'] : $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&flt=unmyown&view=' . $k;
        }
        return $sub_menu;
    }

    /*
     * 已处理订单标签
     */

    function _views_myown(){
        $mdl_order = $this->app->model('orders');
        $base_filter = array(
            'assigned'=>'assigned',
            'abnormal'=>'false',
            'is_fail'=>'false',);

        //指定显示订单类型
        $base_filter['order_type|in'] = kernel::single('ome_order_func')->get_normal_order_type();

        $base_filter['op_id'] = kernel::single('desktop_user')->get_id();

        // 超级管理员
        if(kernel::single('desktop_user')->is_super()){
            if(isset($base_filter['op_id'])){
                unset($base_filter['op_id']);
            }
        }
//        $base_filter['order_confirm_filter'] = "(is_cod='true' OR pay_status in ('1','4','5'))";

        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>array('process_status'=>array('splited','remain_cancel','cancel')),'optional'=>false),
            1 => array('label'=>app::get('base')->_('余单撤销'),'filter'=>array('process_status' =>'remain_cancel'),'optional'=>false),
            2 => array(
                'label'=>app::get('base')->_('部分发货'),
                'filter'=>array('ship_status' =>'2','process_status'=>'splited'),
                'optional'=>false),
            3 => array('label'=>app::get('base')->_('已发货'),'filter'=>array('ship_status' =>'1','process_status'=>'splited'),'optional'=>false),
            4 => array('label'=>app::get('base')->_('部分退货'),'filter'=>array('ship_status' =>'3','process_status'=>'splited'),'optional'=>false),
            5 => array(
                'label'=>app::get('base')->_('已退货'),
                'filter'=>array('ship_status' =>'4','process_status'=>'splited'),
                'optional'=>false),
            6 => array(
                'label'=>app::get('base')->_('暂停'),
                'filter'=>array('pause' => 'true','process_status'=>'splited'),
                'optional'=>false),
        );
        $i=0;
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }

            $v['filter']['archive'] = '0';

            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&flt=myown&view='.$i++;
        }
        return $sub_menu;
    }

    /*
     * 本组订单标签
     */
    function _views_ourgroup(){
        $mdl_order = $this->app->model('orders');
        $base_filter = array(
            'assigned'=>'assigned',
            'abnormal'=>'false',
            'is_fail'=>'false',
            'process_status' => array('unconfirmed', 'confirmed', 'splitting', 'splited', 'remain_cancel'),
        );

        //指定显示订单类型
        $base_filter['order_type|in'] = kernel::single('ome_order_func')->get_normal_order_type();

        $groupObj = $this->app->model("groups");
        $group_id = array();
        $op_id = kernel::single('desktop_user')->get_id();
        $op_group = $groupObj->get_group($op_id);
        if($op_group && is_array($op_group)){
            foreach($op_group as $v){
                $group_id[] = $v['group_id'];
            }
        }
        $base_filter['group_id'] = $group_id;
        // 超级管理员
        if(kernel::single('desktop_user')->is_super()){
            if(isset($base_filter['group_id'])){
                unset($base_filter['group_id']);
            }
        }

        $sub_menu = array(
            0 => array(
                'label'=>app::get('base')->_('全部'),
                'filter'=>array(),
                'optional'=>false
            ),
            1 => array(
                'label'=>app::get('base')->_('待认领'),
                'filter' => array('order_confirm_filter' => "(op_id is null OR op_id=0) AND process_status IN('unconfirmed', 'confirmed', 'splitting')"),
                'optional'=>false
            ),
        );
        $i=0;
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($base_filter,$v['filter']);
            }

            $v['filter']['archive'] = '0';

            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&flt=ourgroup&view='.$i++;
        }
        return $sub_menu;
    }

    function _viewsAbnormal(){
        $mdl_order = $this->app->model('orders');
        $abnormal_type_list = app::get('ome')->model('abnormal_type')->getList('*',array('disabled'=>'false', 'type_id|noequal'=>998));//跨境申报

        $sub_menu = array();
        $sub_menu[] = array('label' => app::get('base')->_('全部'), 'filter' => array('abnormal' => 'true', 'is_fail' => 'false'), 'optional' => false);
        foreach($abnormal_type_list as $abnormal_type){
            $sub_menu[] = array('label'=>$abnormal_type['type_name'],'filter'=>array('abnormal_type_id'=>$abnormal_type['type_id'],'abnormal'=>'true','is_fail'=>'false'),'optional'=>false);
        }

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();

        foreach($sub_menu as $k=>$v){
            if($organization_permissions){
                $v['filter']['org_id'] = $organization_permissions;
            }

            $v['filter']['archive'] = '0';
            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdl_order->countAbnormal($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl=admin_order&act=abnormal&view='.$k;
        }

        return $sub_menu;
    }

    function index(){
        $op_id = kernel::single('desktop_user')->get_id();
        $this->title = '订单查看';
        // $base_filter = array('disabled'=>'false', 'order_confirm_filter' => "(is_fail='false' OR (is_fail='true' AND status!='active'))");
        $base_filter = [];

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;

            //post
            if($_POST['org_id']){
                if(in_array($_POST['org_id'],$base_filter['org_id'] )){
                    $base_filter['org_id'] = $_POST['org_id'];
                }else{
                    $base_filter['org_id'] = -1;
                }
            }
        }

        if($_GET['ship_status']&&$_GET['status']){
            $base_filter['ship_status'] = $_GET['ship_status'];
            $base_filter['status'] = $_GET['status'];
        }

        $params = array(
            'title'=>$this->title,
            'actions' => array(
                    array('label'=>app::get('ome')->_('批量设置备注'),'submit'=>"index.php?app=ome&ctl=admin_order&act=BatchUpMemo",'target'=>'dialog::{width:690,height:200,title:\'批量设置备注\'}"'),
                    array(
                        'label'=>app::get('ome')->_('批量修改标记'),
                        'submit'=>"index.php?app=ome&ctl=admin_order&act=batchEditLabel",
                        'target'=>'dialog::{width:700,height:500,title:\'批量修改订单标记\'}"'
                    ),
                    array(
                        'label'  => sprintf('订单导入(新)'),
                        'href'   => sprintf('%s&act=displayImportV2&p[0]=%s', $this->url, 'order'),
                        'target' => sprintf('dialog::{width:760,height:300,title:\'%s\'}','订单导入'),
                    ),
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>true,
            'use_buildin_filter'=>true,
            'use_view_tab'=>true,
            'finder_aliasname' => 'order_view'.$op_id,
            'finder_cols' => 'order_bn,shop_id,total_amount,column_print_status,process_status,is_cod,pay_status,ship_status,payment,shipping,logi_id,logi_no,createtime,paytime,mark_type',
            'base_filter' => $base_filter,
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
       );

        $user = kernel::single('desktop_user');
        if($user->has_permission('order_export')){
            $params['use_buildin_export'] = true;
        }

        if ($servicelist = kernel::servicelist('ome.service.order.index.action_bar'))
        foreach ($servicelist as $object => $instance){
            if (method_exists($instance, 'getActionBar')){
                $actionBars = $instance->getActionBar();
                foreach($actionBars as $actionBar){
                    $params['actions'][] = $actionBar;
                }
            }
        }
       $this->finder('ome_mdl_orders',$params);
    }

    function active(){
        $op_id = kernel::single('desktop_user')->get_id();
        $this->title = '订单查看';
        $this->order_type = 'active';

        $sub_menu=$this->_view_active();
        // 从view挪出来，不然保存的筛选条件会有问题
        foreach ($sub_menu as $key => $value) {
            if($_GET['view']==$key){
                $base_filter= $value['filter'];
            }
        }

        //$base_filter = array('disabled' => 'false', 'is_fail' => 'false', 'order_confirm_filter' => '( op_id IS NOT NULL OR group_id IS NOT NULL)');
        //$base_filter = array('disabled'=>'false','is_fail'=>'false','archive'=>0,'filter_sql'=>"( process_status != 'cancel')");

        //$base_filter['archive'] ='0';

        $params = array(
            'title'=>$this->title,
            'actions' => array(
                    array(
                        'label' => '批量操作',
                        'group' => array(
                            /*
                            array(
                                'label'=>app::get('ome')->_('批量设置为跨境订单'),
                                'submit'=>'index.php?app=ome&ctl=admin_order&act=BatchDeclare',
                                'target'=>'dialog::{width:500,height:170,title:\'批量设置为跨境订单\'}"'
                            ),*/
                            array(
                                'label'=>app::get('ome')->_('批量设置备注'),
                                'submit'=>'index.php?app=ome&ctl=admin_order&act=BatchUpMemo',
                                'target'=>'dialog::{width:690,height:200,title:\'批量设置备注\'}"'
                            ),
                            array(
                                'label'=>app::get('ome')->_('批量修改标记'),
                                'submit'=>"index.php?app=ome&ctl=admin_order&act=batchEditLabel",
                                'target'=>'dialog::{width:700,height:500,title:\'批量修改订单标记\'}"'
                            ),
                            array(
                                'label'=>app::get('ome')->_('批量取消'),
                                'submit'=>'index.php?app=ome&ctl=admin_batch_order&act=batchCancel',
                                'target'=>'dialog::{width:690,height:400,title:\'批量取消\'}"'
                            ),
                        ),
                    ),
                    array(
                        'label'  => sprintf('订单导入(新)'),
                        'href'   => sprintf('%s&act=displayImportV2&p[0]=%s', $this->url, 'order'),
                        'target' => sprintf('dialog::{width:760,height:300,title:\'%s\'}','订单导入'),
                    ),
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>true,
            'use_buildin_filter'=>true,
            'use_view_tab'=>true,
            'finder_aliasname' => 'order_view'.$op_id,
            'finder_cols' => 'order_bn,shop_id,total_amount,column_print_status,process_status,is_cod,pay_status,ship_status,payment,shipping,logi_id,logi_no,createtime,paytime,mark_type',
            'base_filter' => $base_filter,
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
       );

        $user = kernel::single('desktop_user');
        if($user->has_permission('order_export')){
            $params['use_buildin_export'] = true;
        }
        if($user->has_permission('order_add')){
            $addOrder = array(
                'label'=>app::get('ome')->_('新建订单'),
                'href'=>"index.php?app=ome&ctl=admin_order&act=addNormalOrder",
            );
             array_unshift($params['actions'],$addOrder);
        }

        if ($servicelist = kernel::servicelist('ome.service.order.index.action_bar'))
        foreach ($servicelist as $object => $instance){
            if (method_exists($instance, 'getActionBar')){
                $actionBars = $instance->getActionBar();
                foreach($actionBars as $actionBar){
                    $params['actions'][] = $actionBar;
                }
            }
        }

        if ($params['use_buildin_export'] == true && $servicelist = kernel::servicelist('ietask.service.actionbar')) {
            foreach ($servicelist as $object => $instance){
                if (method_exists($instance, 'getOrders')){
                    $actionBars = $instance->getOrders();
                    foreach($actionBars as $actionBar){
                        $params['actions'][] = $actionBar;
                    }
                }
            }
        }
        $this->finder('ome_mdl_orders',$params);
    }

    function exportTemplate(){

        $oObj = $this->app->model('orders');
        $title1 = $oObj->io_title('order');
        $title2 = $oObj->io_title('obj');
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel([$title1, [],$title2], '订单单导入模板', 'xls');
    }

    function exportTemplateV2(){
        $fileName = "订单导入模板.xlsx";
        $title = app::get('ome')->model('orders')->exportTemplateV2('ome_order_importV2');
        kernel::single('omecsv_phpoffice')->export($fileName, [0 => $title]);
    }

    function dispatch(){

        $this->title = '订单调度';
        $op_id = kernel::single('desktop_user')->get_id();
        switch ($_GET['flt']) {
            case 'assigned':
                $this->order_type = 'assigned';
                $this->base_filter = array(
                    'assigned' => 'assigned',
                    'abnormal'=>'false',
                    'is_fail'=>'false',
                    'process_status|noequal'=>'cancel',
                    'is_auto' => 'false',
                );
                $this->action = array(
                    array(
                        'label' => '回收到未分派',
                         'submit' => 'index.php?app=ome&ctl=admin_order&act=order_recover&action=recover',
                         'target' => 'dialog::{width:400,height:200,title:\'回收到未分派\'}'
                         ),
                );
                $this->title = '已分派的订单';
                $finder_aliasname = "order_dispatch_assigned";
                $finder_cols = "order_bn,shop_id,member_id,ship_name,ship_area,total_amount,op_id,group_id,process_status,is_cod,pay_status,ship_status,createtime,paytime,dispatch_time";
                break;
            case 'notassigned':
                $this->order_type = 'notassigned';
                $this->base_filter = array(
                    'assigned' => 'notassigned',
                    'abnormal'=>'false',
                    'ship_status'=>array('0', '2'),//部分发货也显示
                    'is_fail'=>'false',
                    'process_status|noequal'=>'cancel',
                    'is_auto' => 'false',
                );
                if ($_GET['view']) {
                    $flag = $_GET['view'];
                    switch ($flag) {
                        case 999:
                            $this->base_filter['auto_status'] = '0';
                            break;
                        case 989898:
                            $this->base_filter['is_cod'] = 'true';
                            break;
                        case 989900:
                            $this->base_filter['process_status'] = 'is_retrial';//加入Tab栏目
                            break;
                        default:

                            if (!empty($this->base_filter['order_confirm_filter'])){
                                $this->base_filter['order_confirm_filter'] .= sprintf(" AND (sdb_ome_orders.auto_status & %s = %s) ", $flag, $flag);
                            }elseif($flag<999000){
                                $this->base_filter['order_confirm_filter'] = sprintf(" (sdb_ome_orders.auto_status & %s = %s) ", $flag, $flag);
                            }
                            break;
                    }
                }

                $this->title = '未分派的订单';
                $this->action = array(
                    array('label' => '获取订单', 'href' => 'index.php?app=ome&ctl=admin_order_auto&act=index', 'target' => 'dialog::{width:1000,height:550,title:\'获取订单\'}'),
                    array('label' => '订单分派', 'submit' => 'index.php?app=ome&ctl=admin_order&act=dispatchDailog&flt=notassigned', 'target' => 'dialog::{width:600,height:300,title:\'订单分派\'}'),
                );
                // 如果开启了自动审单，就不放开获取订单按钮
                if ('true' == app::get('ome')->getConf('ome.order.is_auto_combine')) {
                    unset($this->action[0]);
                }

                $finder_aliasname = "order_dispatch_notassigned";
                $finder_cols = "order_bn,shop_id,column_fail_status,member_id,ship_name,ship_area,total_amount,is_cod,pay_status,ship_status,column_deff_time,createtime,paytime";
                break;
            case 'buffer':
                $this->order_type = 'buffer';
                $this->base_filter = $this->getBaseFilter('buffer');
                
                $this->action = array(
                    array('label' => '订单分派', 'submit' => 'index.php?app=ome&ctl=admin_order&act=dispatchDailog&flt=buffer', 'target' => 'dialog::{width:600,height:300,title:\'订单分派\'}'),
                );
                
                //brush特殊订单
                if(kernel::single('desktop_user')->has_permission('order_to_brush')) {
                    array_push($this->action, array('label'=>'设为特殊订单', 'submit'=>'index.php?app=brush&ctl=admin_orders&act=brush', 'confirm'=>'确认要设置为特殊订单么？'));
                }

                $this->title = '订单暂存区';
                $finder_aliasname = "order_dispatch_buffer";
                $finder_cols = "column_confirm,order_bn,shop_id,member_id,ship_name,ship_area,total_amount,is_cod,pay_status,ship_status,column_deff_time,createtime,paytime";
                break;
        }

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $this->base_filter['org_id'] = $organization_permissions;
        }
        
        $this->base_filter['archive'] ='0';
        
        //跨境申报
        //$this->base_filter['process_status|noequal'] = 'is_declare';
        
        $this->finder('ome_mdl_orders',array(
            'title' => $this->title,
            'actions' => $this->action,
            'base_filter' => $this->base_filter,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'finder_aliasname' => $finder_aliasname.$op_id,
            'finder_cols'=>$finder_cols,
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        ));
    }

    function confirm(){
        //current op_id
        $op_id = kernel::single('desktop_user')->get_id();

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $this->base_filter['org_id'] = $organization_permissions;
        }

        if ($_GET['flt'] == 'unmyown'){
            $this->order_type = 'unmyown';
            $this->title = '订单确认 - 我的待处理订单';
            $this->base_filter['op_id'] = $op_id;
            $this->base_filter['assigned'] = 'assigned';
            $this->base_filter['abnormal'] = "false";
            $this->base_filter['is_fail'] = 'false';
            $this->base_filter['status'] = 'active';
            $this->base_filter['custom_process_status'] = array('unconfirmed', 'confirmed', 'splitting');

            if ($_GET['view']) {
                $flag = $_GET['view'];
                switch ($flag) {
                    case 989899:
                        $this->base_filter['pause'] = 'true';
                        break;
                    case 989898:
                        $this->base_filter['pause'] = 'false';
                        $this->base_filter['is_cod'] = 'true';
                        break;
                    default:
                        $this->base_filter['pause'] = 'false';
                        if($flag>=999000) break;
                        if (isset($this->base_filter['order_confirm_filter'])) {
                            $this->base_filter['order_confirm_filter'] .= sprintf(" AND (sdb_ome_orders.auto_status & %s = %s)", $flag, $flag);
                        } else {
                            $this->base_filter['order_confirm_filter'] = sprintf("(sdb_ome_orders.auto_status & %s = %s)", $flag, $flag);
                        }
                        break;
                }
            }
            
            //订单退回
            $isgoback = kernel::single('desktop_user')->has_permission('order_goback');
            if($isgoback){
                $this->action = array(
                    array(
                        'label' => '退回未分派',
                        'submit' => 'index.php?ctl=admin_order&app=ome&act=order_goback',
                        'target' => 'dialog::{width:400,height:400,title:\'退回未分派\'}'
                    ),
                    array(
                        'label' => '退回暂存区',
                        'submit' => 'index.php?ctl=admin_order&app=ome&act=order_buffer',
                        'confirm' => '您确认将选择的待处理订单退回到"订单暂存区"吗？退回到"订单暂存区"后，需要您通过"未分派订单"栏目的"获取订单"功能重新获取！',
                    ),
                    array(
                        'label'=>'获取指定的店铺订单',
                        'icon'=>'download.gif',
                        'href'=>'index.php?app=ome&ctl=admin_order&act=getShopOrder',
                        'target'=>'dialog::{width:400,height:170,title:\'获取指定的店铺订单\'}'
                    ),
                    array(
                       'label'=>'批量操作',
                       'group'=>array(
                           array(
                               'label'=>app::get('ome')->_('批量设置备注'),
                               'submit'=>"index.php?app=ome&ctl=admin_order&act=BatchUpMemo",
                               'target'=>'dialog::{width:690,height:200,title:\'批量设置备注\'}"'
                           ),

                           array(
                               'label'=>app::get('ome')->_('批量审单'),
                               'submit'=>'index.php?app=ome&ctl=admin_batch_order&act=batchConfirm&fltId='.$_GET['fltId'],
                               'target'=>'dialog::{width:690,height:400,title:\'批量审单\'}"'
                           ),
                            array(
                                'label'=>app::get('ome')->_('批量取消'),
                                'submit'=>'index.php?app=ome&ctl=admin_batch_order&act=batchCancel',
                                'target'=>'dialog::{width:690,height:400,title:\'批量取消\'}"'
                            ),
                            array(
                                'label' => '批量暂停',
                                'submit'=>'index.php?app=ome&ctl=admin_batch_order&act=batchDialog&p[0]=dopause&p[1]=unmyown&p[2]='.$_GET['view'],
                                'target'=>'dialog::{width:690,height:200,title:\'批量暂停\'}'
                                ),
                            array(
                                'label' => '批量恢复',
                                'submit'=>'index.php?app=ome&ctl=admin_batch_order&act=batchDialog&p[0]=renew&p[1]=unmyown&p[2]='.$_GET['view'],'target'=>'dialog::{width:690,height:200,title:\'批量恢复\'}'
                                ),
                            array(
                                'label' => '批量异常',
                                'submit'=>'index.php?app=ome&ctl=admin_batch_order&act=batchDialog&p[0]=doabnormal&p[1]=unmyown&p[2]='.$_GET['view'],
                                'target'=>'dialog::{width:690,height:400,title:\'批量设置异常\'}"'
                                ),
                            array(
                                'label'=>app::get('ome')->_('批量设置为开发票'),
                                'submit'=>"index.php?app=ome&ctl=admin_batch_order&act=BatchTax&type=unmyown&is_tax=no",
                                'target'=>'dialog::{width:520,height:100,title:\'批量设置为开发票\'}'
                                ),
                            array(
                                'label'=>app::get('ome')->_('批量设置为不开票'),
                                'submit'=>"index.php?app=ome&ctl=admin_batch_order&act=BatchTax&type=unmyown&is_tax=yes",
                                'target'=>'dialog::{width:520,height:100,title:\'批量设置为不开票\'}'
                                ),
                            'batch_update'=> array(
                               'label'=>app::get('ome')->_('批量编辑'),
                               'submit'=>"index.php?app=ome&ctl=admin_order&act=BatchUpdateOrder&type=active",
                               'target'=>'dialog::{width:800,height:500,title:\'批量编辑\'}"'
                            ),
                            array(
                                'label'=>app::get('ome')->_('批量修改标记'),
                                'submit'=>"index.php?app=ome&ctl=admin_order&act=batchEditLabel&type=active",
                                'target'=>'dialog::{width:700,height:500,title:\'批量修改订单标记\'}"'
                            ),
                       )
                   ),
                    array('label'=>'重新路由', 'submit' => 'index.php?app=ome&ctl=admin_order_lack&act=routerAgain', 'target' => 'dialog::{width:600,height:250,title:\'重新路由\'}'),
                    array('label' => '设置定时审单', 'submit' => 'index.php?app=ome&ctl=admin_order_auto&act=timingConfirm', 'target' => 'dialog::{width:400,height:150,title:\'设置定时审单\'}'),
                );
                
                foreach ($this->action as $k => $v_action) {
                    if (isset($v_action['group'])) {
                        //批量编辑权限控制
                        if (!kernel::single('desktop_user')->has_permission('batch_update_order')) {
                            unset($this->action[$k]['group']['batch_update']);
                        }
                    }
                }
                //重新获取CRM赠品
                if($_GET['view'] == omeauto_auto_const::__CRMGIFT_CODE){
                    $this->action[] = array(
                            'label' => '重新获取CRM赠品',
                            'submit' => 'index.php?app=ome&ctl=admin_order&act=doRequestCRM',
                            'confirm' => '您确认重新获取CRM赠品嘛？',
                    );
                }
            }
            
            // 超级管理员
            if(kernel::single('desktop_user')->is_super()){
                if(isset($this->base_filter['op_id']))
                    unset($this->base_filter['op_id']);

                if(isset($this->base_filter['group_id']))
                    unset($this->base_filter['group_id']);
            }
            
            $export = false;
            $user = kernel::single('desktop_user');
            if($user->has_permission('order_export')){
                $export = true;
            }
            
            //archive
            $this->base_filter['archive'] = 0;
            
            //是否允许审核
            $this->base_filter['is_not_combine'] = 0;
            
            $this->finder('ome_mdl_orders',array(
                'title'=>$this->title,
                'actions'=>$this->action,
                'base_filter' => $this->base_filter,
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>$export,
                'use_buildin_import'=>false,
                'use_buildin_filter'=>true,
                'orderBy' => 'paytime,createtime',
                'finder_aliasname' => 'order_confirm_unmyown'.$op_id,
                'finder_cols' => '_func_0,column_confirm,column_fail_status,order_bn,column_custom_add,column_customer_add,shop_id,member_id,ship_name,ship_area,total_amount,op_id,group_id,process_status,is_cod,pay_status,ship_status,column_deff_time,createtime,paytime,dispatch_time',
                'object_method' => [
                    'count'   => 'finder_count',
                    'getlist' => 'finder_getList',
                ],
            ));
        }elseif($_GET['flt'] == 'myown'){
            $this->order_type = 'myown';
            $this->title = '订单确认 - 我的已处理订单';
            $this->base_filter['op_id'] = $op_id;
            $this->base_filter['assigned'] = 'assigned';
            $this->base_filter['abnormal'] = "false";
            $this->base_filter['is_fail'] = 'false';
//            $this->base_filter['order_confirm_filter'] = "(is_cod='true' OR pay_status in ('1','4','5'))";

            if(!isset($_GET['view'])){
                $this->base_filter['process_status'] = array('splited','remain_cancel','cancel');
            }

            if(kernel::single('desktop_user')->is_super()){
                if(isset($this->base_filter['op_id']))
                    unset($this->base_filter['op_id']);
            }

            $this->base_filter['archive'] = '0';

            $this->finder('ome_mdl_orders',array(
                'title'=>$this->title,
                'actions'=>$this->action,
                'base_filter' => $this->base_filter,
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_filter'=>true,
                // 'orderBy' => 'createtime desc',
                'finder_aliasname' => 'order_confirm_myown'.$op_id,
                'finder_cols' => '_func_0,column_confirm,order_bn,shop_id,member_id,column_print_status,ship_name,ship_area,total_amount,op_id,group_id,process_status,is_cod,pay_status,ship_status,logi_id,logi_no,createtime,paytime,dispatch_time',
                'object_method' => [
                    'count'   => 'finder_count',
                    'getlist' => 'finder_getList',
                ],
            ));
        }elseif($_GET['flt'] == 'ourgroup'){
            $this->order_type = 'ourgroup';
            $this->title = '订单确认 - 本组的订单';
            $group_id = array();
            $oGroup = $this->app->model("groups");
            $op_group = $oGroup->get_group(kernel::single('desktop_user')->get_id());
            if($op_group && is_array($op_group)){
                foreach($op_group as $v){
                    $group_id[] = $v['group_id'];
                }
            }
            $this->base_filter = array('group_id'=>$group_id);
            $this->base_filter['assigned'] = 'assigned';
            $this->base_filter['abnormal'] = "false";
            $this->base_filter['is_fail'] = 'false';
            $this->base_filter['process_status'] = array('unconfirmed','confirmed','splitting','splited','remain_cancel');
            //高级筛选过滤的确认状态
            if(isset($_POST['process_status']) && ($_POST['process_status']!='cancel')){
                $this->base_filter['process_status'] = $_POST['process_status'];
            }
            if(kernel::single('desktop_user')->is_super()){
                if(isset($this->base_filter['group_id']))
                    unset($this->base_filter['group_id']);
            }
            $this->base_filter['archive'] ='0';

            if ($_GET['view'] && $_GET['view']==1) {
                $this->action = array(
                    array(
                            'label' => '批量领取',
                            'submit' => 'index.php?app=ome&ctl=admin_order&act=batchClaim',
                            'confirm' => '您确认领取以下订单吗？',
                        )
                );
            }

            $this->finder('ome_mdl_orders',array(
                'title'=>$this->title,
                'actions'=>$this->action,
                'base_filter' => $this->base_filter,
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_filter'=>true,
                // 'orderBy' => 'createtime desc',
                'finder_aliasname' => 'order_confirm_ourgroup'.$op_id,
                'finder_cols' => '_func_0,column_confirm,order_bn,shop_id,member_id,ship_name,ship_area,total_amount,op_id,group_id,process_status,is_cod,pay_status,ship_status,createtime,paytime,dispatch_time',
                'object_method' => [
                    'count'   => 'finder_count',
                    'getlist' => 'finder_getList',
                ]
            ));
        }
    }

    /**
     * 设置DlyCorp
     * @return mixed 返回操作结果
     */
    public function setDlyCorp(){
        $ids = $_POST['order_id'];
        if(empty($ids)){
            $this->end(false, '请选择订单');
        }

        $combineObj = new omeauto_auto_combine();

        $orderGroup = $combineObj->getOrderGroup($ids);

        $dlyCrop = app::get('ome')->model('dly_corp')->getList('corp_id, name, type, is_cod, weight', array('disabled' => 'false'), 0, -1, 'weight DESC');

        $this->pagedata['orderNum'] = count($ids);
        $this->pagedata['dlyCorp'] = $dlyCrop;
        $this->pagedata['orderGroup'] = json_encode($orderGroup);
        $this->display('admin/order/set_dly.html');
    }

    /**
     * batchExamineSingle
     * @return mixed 返回值
     */
    public function batchExamineSingle() {
        $params = $this->_parseAjaxParams($_POST['ajaxParams']);
        $dlyType = strtoupper($_POST['dlyType']);

        if(empty($params)) {
            echo json_encode(array('flag'=>false, 'type'=>'combine', 'msg'=>'没有要操作的订单！', 'order'=>''));
            return;
        }

        if(empty($dlyType)) {
            echo json_encode(array('flag'=>false, 'type'=>'combine', 'msg'=>'没有正确选择快递公司！', 'order'=>''));
            return;
        }

        $orderObj = app::get('ome')->model('orders');
        $order = $orderObj->dump(array('order_id'=>$params[0]['orders'][0]));

        $orderAuto = new omeauto_auto_combine();
        $combineOrder = $orderAuto->fetchCombineOrder($order);
        if(count($combineOrder)>1) {
            echo json_encode(array('flag'=>false, 'type'=>'combine', 'order'=>$order));
            return;
        }

        $orderAuto->setAutoValid(true);

        if($dlyType == 'SYSTEM') {
            $result = $orderAuto->process($params);
        } else {
            $orderAuto->setPlugins(array('pay', 'flag', 'member', 'ordermulti', 'examine'));
            $corp = $this->getCorp($dlyType);

            $itemList = $orderAuto->getItemList();
            foreach ($itemList as $key=>$item) {
                $item->setDlyCorp($corp);
            }

            $result = $orderAuto->process($params);
        }

        if(empty($result)) {
            echo json_encode(array('flag'=>false, 'type'=>'combine', 'order'=>$order));
            return;
        }

        echo json_encode(array('flag'=>true, 'type'=>'combine', 'order'=>$order));
    }

    /**
     * batchClaim
     * @return mixed 返回值
     */
    public function batchClaim() {
        $this->begin('index.php?app=ome&ctl=admin_order&act=confirm&flt=ourgroup&view=1');
        $orderIds = $admin = array();

        // 数据验证
        $orderIds = $_POST['order_id'];
        if (empty($orderIds)) {
            $this->end(false, app::get('ome')->_('请选择要操作的数据项。'));
            return;
        }
        if (is_array($orderIds)) {
            foreach ($orderIds as $value) {
                if (!is_numeric($value)) {
                    $this->end(false, app::get('ome')->_('数据类型不正确。'));
                    return;
                }
            }
        } else {
            if (!is_numeric($orderIds)) {
                $this->end(false, app::get('ome')->_('数据类型不正确。'));
                return;
            }
        }

        $admin['account_id'] = $_SESSION['account']['shopadmin'];
        if ($admin['account_id'] <= 0) {
            $this->end(false, app::get('ome')->_('账户ID不能为空。'));
            return;
        }

        // 数据处理
        if(is_array($orderIds) && !empty($orderIds)){
            $orderObj = $this->app->model("orders");
            if ($orderObj->update(array('op_id' => $admin['account_id']), array('order_id' => $orderIds))){
                $userObj = app::get('desktop')->model('users');
                $operationLogObj = app::get('ome')->model('operation_log');

                $userInfo = $userObj->dump(intval($admin['account_id']));
                $memo = '订单被'.$userInfo['name'].'领取';

                $operationLogObj->batch_write_log('order_dispatch@ome',array('order_id' => $orderIds),$memo,time());
                $this->end(true, app::get('ome')->_('批量领取操作成功。'));
            }else{
                $this->end(false, app::get('ome')->_('批量领取操作失败。'));
            }
        }else{
            $this->end(false, app::get('ome')->_('批量领取操作失败。'));
        }

        unset($order, $admin);
        return;
    }

    /**
     * claim
     * @return mixed 返回值
     */
    public function claim() {
        $this->begin('index.php?app=ome&ctl=admin_order&act=confirm&flt=ourgroup&view=1');
        $order = $admin = array();

        // 数据验证
        $order['id'] = $_GET['order_id'];
        if (empty($order['id'])) {
            $this->end(false, app::get('ome')->_('请选择要操作的数据项。'));
            return;
        }
        if (is_array($order['id'])) {
            foreach ($order['id'] as $value) {
                if (!is_numeric($value)) {
                    $this->end(false, app::get('ome')->_('数据类型不正确。'));
                    return;
                }
            }
        } else {
            if (!is_numeric($order['id'])) {
                $this->end(false, app::get('ome')->_('数据类型不正确。'));
                return;
            }
        }

        $admin['account_id'] = $_SESSION['account']['shopadmin'];
        if ($admin['account_id'] <= 0) {
            $this->end(false, app::get('ome')->_('账户ID不能为空。'));
            return;
        }

        $orderObj = $this->app->model("orders");
        $result = $orderObj->db->select(sprintf("SELECT count(*) as _count FROM `%s` WHERE order_id IN ('%s') AND (op_id IS NULL OR op_id = 0 OR op_id = '')", $orderObj->table_name(1), implode("','", $order['id'])));
        if (intval($result[0]['_count']) !== 1) {
            $this->end(false, app::get('ome')->_('订单已被领取。'));
            return;
        }

        $combineobj = kernel::single('omeauto_auto_combine');
        $orderInfo = $orderObj->dump($order['id'][0]);
        $orderIds = array();
        $combineOrders = $combineobj->fetchCombineOrder($orderInfo);
        foreach ($combineOrders as $comOrder) {
            if($comOrder['group_id']>0 && $comOrder['op_id']==0){
                $orderIds[] = $comOrder['order_id'];
            }
        }
        unset($combineOrders);

        // 数据处理
        if(is_array($orderIds) && !empty($orderIds)){
            if ($orderObj->update(array('op_id' => $admin['account_id']), array('order_id' => $orderIds))){
                $userObj = app::get('desktop')->model('users');
                $operationLogObj = app::get('ome')->model('operation_log');

                $userInfo = $userObj->dump(intval($admin['account_id']));
                $memo = '订单被'.$userInfo['name'].'领取';

                $operationLogObj->batch_write_log('order_dispatch@ome',array('order_id' => $orderIds),$memo,time());
                $this->end(true, app::get('ome')->_('领取操作成功。'));
            }else{
                $this->end(false, app::get('ome')->_('领取操作失败。'));
            }
        }else{
            $this->end(false, app::get('ome')->_('领取操作失败。'));
        }

        unset($order, $admin);
        return;
    }

    function count_dispatch($data=''){
        if ($_POST){
            $start  = $_POST['start'];
            $end    = $_POST['end'];
            $group_id = $_POST['group'];
            $op_id  = $_POST['operator'];

            $where = '';
            if ($op_id != ''){
                $where .= " AND o.op_id = $op_id ";
            }
            if ($group_id != ''){
                $where .= " AND o.group_id = $group_id ";
            }
            if ($start != '' && $end != ''){
                $s = strtotime($start. ' 00:00:00');
                $e = strtotime($end. ' 23:59:59');
                $where .= " AND (o.dt_begin >= $s AND o.dt_begin <= $e) ";
            }
        }else {
            if ($data){
                if ($data == 'today'){
                    $day_s = strtotime(date('Y-m-d'). ' 00:00:00');
                    $day_e = strtotime(date('Y-m-d'). ' 23:59:59');
                    $where = " AND (o.dt_begin >= $day_s AND o.dt_begin <= $day_e) ";
                }elseif ($data == 'twodays'){
                    $day_s = strtotime('-2 day  00:00:00');
                    $day_e = strtotime(date('Y-m-d'). ' 23:59:59');
                    $where = " AND (o.dt_begin >= $day_s AND o.dt_begin <= $day_e) ";
                }
            }else {
                $where = ' AND 1';
            }
        }
        $oObj = $this->app->model('orders');

        //all
        $all = $oObj->get_all($where);

        //group
        $group = $oObj->get_group($where);

        //operator
        $operator = $oObj->get_operator($where);
        $groups = $this->app->model('groups')->getList('group_id,name',array('g_type'=>'confirm'),0,-1);
        $ops = $oObj->get_confirm_ops();

        $this->pagedata['groups'] = $groups;
        $this->pagedata['ops'] = $ops;
        $this->pagedata['all'] = $all;
        $this->pagedata['group'] = $group;
        $this->pagedata['operator'] = $operator;
        $this->display('admin/order/count_order.html');
    }

    function dispatching(){
        $combineobj = kernel::single('omeauto_auto_combine');
        $orderObj = $this->app->model("orders");
        $orders = $orderObj->getList('order_id,group_id,op_id', array('order_id' => $_POST['order_id']));

        $orderIds = array();
        foreach ($orders as $order) {
            if ($order['group_id'] == 0 && $order['op_id'] == 0) {
                $orderIds[$order['order_id']] = $orderIds['order_bn'];
            }

            /*
            $combineOrders = $combineobj->fetchCombineOrder($order);
            foreach ($combineOrders as $comOrder) {
                if($comOrder['group_id']==0 && $comOrder['op_id']==0){
                    $orderIds[$comOrder['order_id']] = $comOrder['order_bn'];
                }
            }
            unset($combineOrders);
            */
        }

        $this->pagedata['orderIds'] = $orderIds;
        if (isset($_POST['isSelectedAll'])&&($_POST['isSelectedAll']=='_ALL_')) {
            $this->pagedata['isSelectedAll']     = urlencode(json_encode($_POST));
        }
        $oGroup = $this->app->model('groups');
        $groups = $oGroup->getList('group_id,name',array('g_type'=>'confirm'));
        $this->pagedata['groups'] = $groups;
        $this->display("admin/order/dispatching.html");
    }

    function dispatchSingle($orderId){
        $orderObj = $this->app->model("orders");
        $order = $orderObj->dump($orderId);
        $orderIds[$order['order_id']] = $order['order_bn'];
        $this->pagedata['orderIds'] = $orderIds;

        $oGroup = $this->app->model('groups');
        $groups = $oGroup->getList('group_id,name',array('g_type'=>'confirm'));
        $this->pagedata['groups'] = $groups;
        $this->pagedata['single'] = 1;
        $this->display("admin/order/dispatching.html");
    }

    function do_dispatch(){
        $order_ids = array();
        $filter = array();
        $data['group_id']      = $_POST['new_group_id']?intval($_POST['new_group_id']):0;
        $data['op_id']         = $_POST['new_op_id']?intval($_POST['new_op_id']):0;
        $data['dt_begin']      = time();
        $data['dispatch_time'] = time();

        $orderObj = $this->app->model("orders");
        $preProcessLib = new ome_preprocess_entrance();
        //是从暂存取拉出来的订单做相应的预处理
        if($_POST['single'] == 1){
            //$preProcessLib = new ome_preprocess_entrance();
            $preProcessLib->process($_POST['order_id'][0],$msg);
            // //淘宝全链路 已客审 // 移到omeauto_auto_group_item里面
            // kernel::single('ome_event_trigger_shop_order')->order_message_produce($_POST['order_id'][0],1);

            $orderInfo = $orderObj->dump($_POST['order_id'],'auto_status,abnormal_status');
            if($orderInfo){
                if(($orderInfo['abnormal_status'] & ome_preprocess_const::__HASGIFT_CODE) == ome_preprocess_const::__HASGIFT_CODE){
                    if($orderInfo['auto_status'] == 0){
                        $data['auto_status'] = omeauto_auto_const::__PMTGIFT_CODE;
                    }elseif( ($orderInfo['auto_status'] & omeauto_auto_const::__PMTGIFT_CODE) != omeauto_auto_const::__PMTGIFT_CODE){
                        $data['auto_status'] = $orderInfo['auto_status'] | omeauto_auto_const::__PMTGIFT_CODE;
                    }
                }

                //获取crm基本配置
                $crm_cfg = app::get('crm')->getConf('crm.setting.cfg');
                //检测crm是否开启
                if(!empty($crm_cfg)){
                    $tb_auto_status = $data['auto_status'];
                    if(($orderInfo['abnormal_status'] & ome_preprocess_const::__HASCRMGIFT_CODE) == ome_preprocess_const::__HASCRMGIFT_CODE){
                        if($tb_auto_status == 0){
                            $data['auto_status'] = omeauto_auto_const::__CRMGIFT_CODE;
                        }elseif( ($tb_auto_status & ome_preprocess_const::__HASCRMGIFT_CODE) != ome_preprocess_const::__HASCRMGIFT_CODE){
                            $data['auto_status'] = $tb_auto_status | ome_preprocess_const::__HASCRMGIFT_CODE;
                        }
                    }
                }
            }


            //超卖
            $orderObjectObj = $this->app->model("order_objects");
            $res = $orderObjectObj->getList('order_id',array('order_id'=>$_POST['order_id'],'is_oversold'=>1),0,-1);
            if($res){
                if($orderInfo){
                    if($orderInfo['auto_status'] == 0){
                        $data['auto_status'] = omeauto_auto_const::__OVERSOLD_CODE;
                    }elseif( ($orderInfo['auto_status'] & omeauto_auto_const::__OVERSOLD_CODE) != omeauto_auto_const::__OVERSOLD_CODE){
                        $data['auto_status'] = $orderInfo['auto_status'] | omeauto_auto_const::__OVERSOLD_CODE;
                    }
                }
            }
        }else{
            //$crm_cfg = app::get('crm')->getConf('crm.setting.cfg');
            //检测crm是否开启,只有开启crm应用时，才执行以下代码
            //if(!empty($crm_cfg)){
                //获取所有的预处理订单
                $arr_order_id = array();
                if(isset($_POST['order_id'])&&is_array($_POST['order_id'])&&(count($_POST['order_id'])>0)) {
                    $arr_order_id = $_POST['order_id'];
                }elseif(isset($_POST['isSelectedAll'])&&$_POST['isSelectedAll']){//全选
                    $params = json_decode(urldecode($_POST['isSelectedAll']),true);
                    if(isset($params['isSelectedAll'])&&($params['isSelectedAll']=='_ALL_')){
                        if(!empty($params['flt'])) {
                            $tmpParams = $this->getBaseFilter(trim($params['flt']));
                            $params = array_merge($tmpParams, $params);
                        } else {
                            $params['filter_sql'] = '(group_id is null or group_id=0)';
                        }
                        unset($params['app']);
                        unset($params['ctl']);
                        unset($params['act']);
                        unset($params['flt']);
                        unset($params['_finder']);
                        $filter = $params;
                        $orderObj->filter_use_like = true;
                        $_order_id = $orderObj->getList('order_id',$filter);
                        foreach($_order_id as $_id){
                            $arr_order_id[] = $_id['order_id'];
                        }
                    }
                }
                //批量分派处理
                foreach($arr_order_id as $order_id){
                    $preProcessLib->process($order_id,$msg);
                    //淘宝全链路 已客审
                    kernel::single('ome_event_trigger_shop_order')->order_message_produce($order_id,'check');
                    $orderInfo = $orderObj->dump($order_id,'auto_status,abnormal_status');
                    if($orderInfo){
                            if(($orderInfo['abnormal_status'] & ome_preprocess_const::__HASCRMGIFT_CODE) == ome_preprocess_const::__HASCRMGIFT_CODE){
                                if($orderInfo['auto_status'] == 0){
                                    $_data['auto_status'] = omeauto_auto_const::__CRMGIFT_CODE;
                                }elseif( ($orderInfo['auto_status'] & ome_preprocess_const::__HASCRMGIFT_CODE) != ome_preprocess_const::__HASCRMGIFT_CODE){
                                    $_data['auto_status'] = $orderInfo['auto_status'] | ome_preprocess_const::__HASCRMGIFT_CODE;
                                }
                                $orderObj->update($_data,array('order_id'=>$order_id));
                                unset($_data);
                            }
                    }
                }
            //}
        }

        //分派过滤条件
        if (isset($_POST['order_id'])&&is_array($_POST['order_id'])&&(count($_POST['order_id'])>0)) {
            $filter['order_id']       = $_POST['order_id'];
            $filter['filter_sql']    = '(group_id is null or group_id=0)';
            $order_ids = array('_ALL_');
        }elseif(isset($_POST['isSelectedAll'])&&$_POST['isSelectedAll']){//全选
            $params = json_decode(urldecode($_POST['isSelectedAll']),true);
            if(isset($params['isSelectedAll'])&&($params['isSelectedAll']=='_ALL_')){
                if(!empty($params['flt'])) {
                    $tmpParams = $this->getBaseFilter(trim($params['flt']));
                    $params = array_merge($tmpParams, $params);
                } else {
                    $params['filter_sql']    = '(group_id is null or group_id=0)';
                }
                unset($params['app']);
                unset($params['ctl']);
                unset($params['act']);
                unset($params['flt']);
                unset($params['_finder']);
                $filter = $params;
                $order_ids = array('_ALL_');
            }
        }
        if(!empty($filter)&&(isset($filter['order_id'])||isset($filter['isSelectedAll']))){
            $filter['process_status'] = array('unconfirmed', 'confirmed', 'splitting', 'is_retrial');//加入is_retrial
            $orderObj->filter_use_like = true;
            $orderObj->dispatch($data,$filter,$order_ids);
        }
        //echo "<script>$$('.dialog').getLast().retrieve('instance').close();</script>";
    }

    function do_cancel($order_id) {
        $oOrder = $this->app->model('orders');
        $orderdata = $oOrder->dump($order_id);
        if ($_POST) {
            $memo = "订单被取消 ".$_POST['memo'];
            $mod = 'sync';
            $oShop = $this->app->model('shop');
            $c2c_shop_list = ome_shop_type::shop_list();
            $shop_detail = $oShop->dump(array('shop_id'=>$orderdata['shop_id']),'node_id,node_type');
            if(!$shop_detail['node_id'] || in_array($shop_detail['node_type'],$c2c_shop_list) || $orderdata['source'] == 'local'){
                $mod = 'async';
            }

            $sync_rs = $oOrder->cancel($order_id,$memo,true,$mod, false);
            if($sync_rs['rsp'] == 'success')
            {
                //取消订单作废相应的发票记录
                if(app::get('invoice')->is_installed()){

                    kernel::single('invoice_process')->cancel(array('order_id'=>$order_id));
                }

                echo "<script>alert('订单取消成功');</script>";
            }else{
                echo "<script>alert('订单取消失败,原因是:".$sync_rs['msg']."');</script>";
            }
            echo "<script>window.finderGroup[$(document.body).getElement('input[name^=_finder\[finder_id\]]').value].refresh();$$('.dialog').getLast().retrieve('instance').close();</script>";
        }
        $this->pagedata['order'] = $orderdata;
        $this->display("admin/order/detail_cancel.html");
    }

    function do_confirm($order_id, $oId = null) {
        $orderTypeLib = kernel::single('ome_order_bool_type');

        if (isset($_GET['find_id'])) {
            $finder_id = $_GET['find_id'];
        } else {
            $finder_id = $_GET['finder_id'];
        }
        $psRow = app::get('ome')->model('order_platformsplit')->db_dump(['order_id'=>$order_id], 'id');
        if($psRow) {
            $_GOTO = 'index.php?app=ome&ctl=admin_order_platformsplit&act=do_confirm&p[0]='.$order_id.'&finder_id='.$finder_id;
            echo "<script>location ='$_GOTO'</script>";exit;
        }
        $oOrder = $this->app->model("orders");

        //finder
        $ini_filter = $_GET['filter'];
        $finder_filter = unserialize(base64_decode($ini_filter));

        $filter = app::get('ome')->model('orders')->_filter($finder_filter);

        if ($order_id == 'up' || $order_id == 'next') {
            if ($order_id == 'up')
                $order_id = $oOrder->getOrderUpNext($oId, $filter, '<');
            else
                $order_id = $oOrder->getOrderUpNext($oId, $filter, '>');

            if (empty($order_id)) {
                header("content-type:text/html; charset=utf-8");
                echo "<script>alert('当前条件下的订单多已处理完成！！！');opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();</script>";
                exit;
            } else {
                $order_id = $order_id['order_id'];
            }
        }

        $order = $oOrder->dump($order_id);
        if(empty($order['consignee']['addr'])) {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('当前订单缺少收货人地址！！！');opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();</script>";
            exit;
        }
        
        //是否允许审核
        if($order['is_not_combine'] > 0){
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('订单已被标记：不允许审核，请检查!');
            opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();
            </script>";
            exit;
        }
        
        //定制订单类型,未推送给莫凡,不允许手工审核订单
        if($order['order_type'] == 'custom' && $order['is_delivery'] == 'N'){
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('定制订单需要推送莫凡成功后，才允许手工审核订单!');opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();</script>";
            exit;
        }
        
        $orderExtend = app::get('ome')->model('order_extend')->db_dump(['order_id'=>$order_id]);
        $orderExtend['extend_field'] && $orderExtend['extend_field'] = json_decode($orderExtend['extend_field'], 1);

        $this->pagedata['is_splited'] = $order['process_status'] == 'splitting' ? 'false' : 'true';
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $user_id = $opInfo['op_id'];
        $is_supp = kernel::single('desktop_user')->is_super();

        if ($order['shipping']['is_cod'] == 'false' && $order['pay_status'] == '3') {
            $order['confirm_flag'] = 0;
            //判断是否是预售
            if($order['order_type'] == 'presale' && $order['step_trade_status'] == 'FRONT_PAID_FINAL_NOPAID' && kernel::single('ome_order_func')->checkPresaleOrder()){
                $order['confirm_flag'] = 1;
            }else{
                header("content-type:text/html; charset=utf-8");
                echo "<script>alert('请完成付款后，再进行确认');opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();</script>";
                exit;
            }
            
        } else {
            if ($order['op_id'] == '' && !$is_supp) {
                $oo['order_id'] = $order_id;
                $oo['op_id'] = $user_id;
                $oOrder->save($oo);
            }
        }

        //判断订单编辑同步状态
        $oOrder_sync = app::get('ome')->model('order_sync_status');
        $sync_status = $oOrder_sync->getList('order_id,type,sync_status',array('order_id'=>$order_id),0,1);
        if ($sync_status[0]['sync_status'] == '1' && $order['source'] == 'matrix'){
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('订单编辑同步失败,无法确认生成发货单!');window.close();</script>";
            exit;
        }

        $oMember = $this->app->model("members");
        $member = $oMember->dump($order['member_id']);
        //当订单类型是taobao时,获取shop_member_id
        if ($order['shop_type']=='taobao') {
            $member['shop_member_id'] = $member['account']['uname'];
            $member['wang_wang_html'] = kernel::single('ome_order_func')->getWangWangHtml(['nick'=>$member['shop_member_id'], 'encryptuid'=>$member['buyer_open_uid']]);
        }

        $object_alias = $oOrder->getOrderObjectAlias($order_id);

        if (!preg_match("/^mainland:/", $order['consignee']['area'])) {
            $region = '';
            $newregion = '';
            foreach (explode("/", $order['consignee']['area']) as $k => $v) {
                $region.=$v . ' ';
            }
        } else {
            $newregion = $order['consignee']['area'];
        }

        //是否启动拆单
        $orderSplitLib   = kernel::single('ome_order_split');
        $split_seting    = $orderSplitLib->get_delivery_seting();
        $split_model     = $split_seting ? 2 : 0;//自由拆单方式split_model=2
        
        $this->pagedata['split_model']  = $split_model;

        //获取当前订单的上下条订单
        $this->pagedata['filter'] = urlencode($ini_filter);
        
        // 是否支持手工合单（On：开启，Off：关闭）
        $is_manual_merge = app::get('ome')->getConf('ome.combine.manual.merge');
        if($is_manual_merge){
            $order['is_manual_merge'] = $is_manual_merge;
        }else{
            $order['is_manual_merge'] = '';
        }
        
        //获取相关订单，并输入内容
        $combineObj = kernel::single('omeauto_auto_combine');
        $combineOrders = $combineObj->fetchCombineOrder($order);
        
        // order
        $order = $oOrder->dump($order_id);
        // 处理加密
        $order['is_encrypt']  = kernel::single('ome_security_router',$order['shop_type'])->show_encrypt($order,'order');
        $member['is_encrypt'] = kernel::single('ome_security_router',$order['shop_type'])->show_encrypt($member,'member');

        // 送货上门
        $order['isCPUP']     = kernel::single('ome_order_bool_type')->isCPUP($order['order_bool_type']);
        $order['isSHSM']     = kernel::single('ome_bill_label_shsm')->isTinyPieces($order['order_id']);

        $orderEx = app::get('ome')->model('order_extend')->db_dump($order['order_id']);
        $order['cpup_service'] = $orderEx['cpup_service'] ? explode(',',$orderEx['cpup_service']) : [];
        $order['promise_service'] = $orderEx['promise_service'];

        //扩展信息
        $es_time = $orderEx['es_time'];
        $extend_field = json_decode($orderEx['extend_field'], true);

        //物流推荐
        $recLogi = kernel::single('ome_event_trigger_shop_logistics')->getRecommend($order);
        if($recLogi['logistics_code']) {
            $this->pagedata['recLogi'] = implode('、', $recLogi['logistics_name']);
        }

        //地址html转化
        $order['consignee']['addr'] = str_replace(array("\r\n","\r","\n","'","\""), '',  htmlspecialchars($order['consignee']['addr']));
        
        //shipping_name字段处理：根据corp.php的corp_default方法显示正确名称
        if (isset($order['shipping']['shipping_name'])) {
            $corpModel = app::get('ome')->model('dly_corp');
            
            // 尝试从不同配送模式中查找配送方式名称
            $shippingNameMap = $corpModel->corp_default('instatnt'); // 先查找同城配送
            if(isset($shippingNameMap[$order['shipping']['shipping_name']])){
                $order['shipping']['shipping_name'] = $shippingNameMap[$order['shipping']['shipping_name']]['name'];
            } else {
                $shippingNameMap = $corpModel->corp_default('seller'); // 再查找商家配送
                if(isset($shippingNameMap[$order['shipping']['shipping_name']])){
                    $order['shipping']['shipping_name'] = $shippingNameMap[$order['shipping']['shipping_name']]['name'];
                }
            }
        }
        
        $orderIdx = $order['order_combine_idx'];
        $orderHash = $order['order_combine_hash'];

        //翱象订单
        $isAoxiang = false;
        if(in_array($order['shop_type'], array('taobao', 'tmall')) && $order['order_bool_type']){
            //是否翱象订单
            $isAoxiang = $orderTypeLib->isAoxiang($order['order_bool_type']);
        }
        $this->pagedata['isAoxiang'] = ($isAoxiang ? 'true' : 'false');

        //翱象建议的物流公司
        if($isAoxiang){
            $axOrderLib = kernel::single('dchain_order');
            $error_msg = '';
            $aoxLogiList = $axOrderLib->getRecommendLogis($order['order_id'], $error_msg);

            $tempData = array();
            if($aoxLogiList['biz_delivery_codes']){
                $tempData[] = '买家推荐物流：'. implode('、', $aoxLogiList['biz_delivery_codes']);
            }

            if($aoxLogiList['black_delivery_cps']){
                $tempData[] = '买家不推荐物流：'. implode('、', $aoxLogiList['black_delivery_cps']);
            }
            $recommendLogiStr = implode('，', $tempData);
            $this->pagedata['recommendLogiStr'] = $recommendLogiStr;
        }
        
        $flag_edit = 'true';
        foreach ($combineOrders as $k=>$combineOrder)
        {
            //过滤全额退款订单
            if($combineOrder['pay_status'] == '5'){
                unset($combineOrders[$k]);
                continue;
            }

            $combineOrders[$k]['mark_text'] = strip_tags(htmlspecialchars($combineOrder['mark_text']));
            $combineOrders[$k]['custom_mark'] = strip_tags(htmlspecialchars($combineOrder['custom_mark']));
            
            //shipping_name字段处理：根据corp.php的corp_default方法显示正确名称
            if (isset($combineOrder['shipping']['shipping_name'])) {
                $corpModel = app::get('ome')->model('dly_corp');
                
                // 尝试从不同配送模式中查找配送方式名称
                $shippingNameMap = $corpModel->corp_default('instatnt'); // 先查找同城配送
                if(isset($shippingNameMap[$combineOrder['shipping']['shipping_name']])){
                    $combineOrders[$k]['shipping']['shipping_name'] = $shippingNameMap[$combineOrder['shipping']['shipping_name']]['name'];
                } else {
                    $shippingNameMap = $corpModel->corp_default('seller'); // 再查找商家配送
                    if(isset($shippingNameMap[$combineOrder['shipping']['shipping_name']])){
                        $combineOrders[$k]['shipping']['shipping_name'] = $shippingNameMap[$combineOrder['shipping']['shipping_name']]['name'];
                    }
                }
            }

            if($combineOrder['isCombine'] == true){
                $isCombinIds[] = $combineOrder['order_id'];
            }
            $combinIds[] = $combineOrder['order_id'];

            if ($order_add_service = kernel::service('service.order.'.$combineOrder['shop_type'])){
                if (method_exists($order_add_service, 'is_edit_view')){
                    $order_add_service->is_edit_view($combineOrder, $flag_edit);
                }
            }

            //自有体系_部分拆分或部分发货的订单不允许编辑
            if($combineOrder['process_status'] == 'splitting' || $combineOrder['ship_status'] == '2'){
                $flag_edit    = in_array($combineOrder['shop_type'], array('ecos.b2c', 'ecos.dzg', 'shopex_b2b','shopex_b2c')) ? 'false' : $flag_edit;
            }

            $combineOrders[$k]['flag_edit'] = $flag_edit;

            // 如果是唯品会jitx订单，检测是否有重点检测的数据
            if ($combineOrder['shop_type'] == 'vop') {
                $obCheckItemsMdl = app::get('ome')->model('order_objects_check_items');
                $check_items     = $obCheckItemsMdl->getList('*', ['order_id'=>$combineOrder['order_id']]);
                $check_items     = array_column($check_items, null, 'bn');

                $mdl = app::get('purchase')->model('pick_bill_check_items');
                foreach ($check_items as $cik => $civ) {
                    $check_items[$cik]['delete'] = 'false';
                    if ($mdl->order_label[$civ['order_label']]) {
                        $check_items[$cik]['order_label'] = $mdl->order_label[$civ['order_label']];
                    }
                }

                foreach ($combineOrder['items'] as $ik => $iv) {
                    foreach ($iv as $kk => $vv) {
                        if ($vv['delete'] == 'true' && $check_items[$vv['bn']]) {
                            $check_items[$vv['bn']]['delete'] = 'true';
                        }
                    }
                }
                $combineOrders[$k]['check_items'] = array_values($check_items);
                if ($check_items) {
                    $this->pagedata['check_items'] = true;
                }
            }
        }

        $order_sort_type = '';
        $auto_branch_id = '';
        if ($order['shop_type']=='taobao' && ($order['order_bool_type'] & ome_order_bool_type::__CNAUTO_CODE)){
            $order_sort_type = 'cnauto';
            $auto_branch_id =  app::get('ome')->getConf('shop.cnauto.set.'.$order['shop_id']);

        }

        $this->pagedata['order_sort_type'] = $order_sort_type;
        $this->pagedata['auto_branch_id'] = $auto_branch_id;

        //O2O全渠道订单
        if(app::get('o2o')->is_installed() && ($order['omnichannel'] == 1)){
            //加载o2o门店模板
            $this->pagedata['use_o2o']    = true;

            //部分拆分or部分发货的全渠道订单不支持门店发货
            if($order['process_status'] == 'splitting' || $order['ship_status'] == '2'){
                $this->pagedata['use_o2o']    = false;
            }

            //根据订单扩展表上的门店编码查找到门店信息
            $o2o_order    = kernel::single("o2o_store")->getOrderIdByStore($order_id);

            //收货人手机号,验证没有手机号会提示错误
            $o2o_order['isMobile']  = kernel::single('ome_func')->isMobile($order['consignee']['mobile']) ? 'true' : 'false';

            $this->pagedata['o2o_order']  = $o2o_order;

            //o2o门店仓对应库存
            $combineOrders    = kernel::single("o2o_branch_product")->getItemBnBranchStore($combineOrders);
        }

        $this->pagedata['combineOrders'] = $combineOrders;
        $this->pagedata['jsOrders'] = json_encode($combineOrders);

        if (empty($this->pagedata['combineOrders'])) {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('该订单已处理完成');opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();</script>";
            exit;
        }

        $order['mark_text'] = unserialize($order['mark_text']);
        $order['custom_mark'] = unserialize($order['custom_mark']);

        //下单时间离当前的时间差
        if ($order['shipping']['is_cod'] == 'true') {
            $difftime = kernel::single('ome_func')->toTimeDiff(time(), $order['createtime']);
        }else{
            $difftime = kernel::single('ome_func')->toTimeDiff(time(), $order['paytime']);
        }

        $order['difftime'] = $difftime['d'] . '天' . $difftime['h'] . '小时' . $difftime['m'] . '分';
        $this->pagedata['assign_express_code'] = '';
        //指定快递发货
        $assign_express = array();
        $assign_express = kernel::single('ome_order_func')->get_assign_express($order_id);
        if($assign_express){
            $this->pagedata['assign_express_name'] = $assign_express['name'];
            $this->pagedata['assign_express_code'] = $assign_express['assign_express_code'];
        }

        // 匹配(快递)物流公司
        if($assign_express){
            $this->pagedata['defaultExpress'] = array('yes'=>$assign_express['assign_express_code']); //指定快递
        }else{
            $this->pagedata['defaultExpress'] = $this->getDefaultParseCorp($order);
        }

        $branch_id = $this->getDefaultBranch($isCombinIds);

        $branch_list = $oOrder->getBranchByOrder($combinIds);

        // 需要检测是否已经参加O2O的门店，如果有，需要将门店仓加入到仓库列表中
        // 检测o2o_store表的is_o2o=1是否有数据，有的话，根据O2O_store表的branch_id再去ome_branch表获取b_type=2的数据
        $branchObj = app::get('ome')->model('branch');
        $o2oStoreObj = app::get('o2o')->model('store');
        $o2oStores = $o2oStoreObj->getList('branch_id', array('is_o2o' => '1'));
        if (!empty($o2oStores)) {
            $o2oBranchIds = array_column($o2oStores, 'branch_id');
            if (!empty($o2oBranchIds)) {
                // 收集 combineOrders 中每个订单的 has_store_branch，求交集确保所有订单都有库存
                $hasStoreBranchIds = array();
                if (!empty($combineOrders) && is_array($combineOrders)) {
                    $firstOrder = true;
                    foreach ($combineOrders as $coOrder) {
                        if (!empty($coOrder['has_store_branch'])) {
                            $currentOrderBranches = array_map('intval', (array)$coOrder['has_store_branch']);
                            if ($firstOrder) {
                                // 第一个订单：直接使用其门店仓列表作为初始交集
                                $hasStoreBranchIds = $currentOrderBranches;
                                $firstOrder = false;
                            } else {
                                // 后续订单：与当前交集求交集
                                $hasStoreBranchIds = array_intersect($hasStoreBranchIds, $currentOrderBranches);
                            }
                        } else {
                            // 如果某个订单没有门店仓库存，则交集为空
                            $hasStoreBranchIds = array();
                            break;
                        }
                    }
                    $hasStoreBranchIds = array_values($hasStoreBranchIds);
                }
                // 仅保留同时存在于所有订单 has_store_branch 的门店仓
                $o2oBranchIds = array_values(array_intersect($o2oBranchIds, $hasStoreBranchIds));
                
                if (!empty($o2oBranchIds)) {
                    // 使用门店仓覆盖区域检测工具类，过滤出可用的门店仓
                    $ship_area = $order['consignee']['area'];
                    $availableO2OBranchIds = kernel::single('ome_store_branch_coverage')->getAvailableBranches($o2oBranchIds, $ship_area);
                    
                    if (!empty($availableO2OBranchIds)) {
                        $storeBranches = $branchObj->getList('*', array(
                            'branch_id' => $availableO2OBranchIds,
                            'b_type' => '2',
                            'disabled' => 'false',
                            'is_deliv_branch' => 'true',
                            'check_permission' => 'false',
                            'b_status' => '1', // 门店仓状态为启用
                        ));
                        if (!empty($storeBranches)) {
                            // 将可用的门店仓添加到仓库列表中
                            foreach ($storeBranches as $storeBranch) {
                                $branch_list[] = $storeBranch;
                            }
                        }
                    }
                }
            }
        }
        
        if ($branch_id[$orderHash]){
            $selected_branch_id = $branch_id[$orderHash];

            $branchObj = app::get('ome')->model('branch');
            $recomm_branch = $branchObj->db->selectrow("select branch_id,name, b_type FROM sdb_ome_branch WHERE branch_id='".$selected_branch_id."'");

            //订单是全渠道类型并且没有指定门店仓发货,优先选择线上仓库
            if($branch_list && ($recomm_branch['b_type'] == 2) && empty($o2o_order['branch_id'])){
                //根据仓库获取指定的物流
                $branch_corp_lib = kernel::single("ome_branch_corp");
                foreach ($branch_list as $b_key => $b_val)
                {
                    $corp_ids = $branch_corp_lib->getCorpIdsByBranchId(array($b_val['branch_id']));
                    if(empty($corp_ids)){
                        continue;
                    }
                    $sql = "SELECT corp_id FROM sdb_ome_dly_corp WHERE corp_id IN(" . implode(",", $corp_ids) . ") AND d_type=1 AND disabled='false'";
                    $temp_corp = $branchObj->db->selectrow($sql);
                    if($temp_corp){
                        $recomm_branch    = $b_val;
                        break;
                    }
                }

                $selected_branch_id = ($recomm_branch['branch_id'] ? $recomm_branch['branch_id'] : $branch_list[0]['branch_id']);
                $this->pagedata['recommend_branch'] = $recomm_branch;
            }else{
                $this->pagedata['recommend_branch'] =$recomm_branch;
            }
        }else{
            $selected_branch_id = $branch_list[0]['branch_id'];
        }

        //平台指定仓库发货
        if($order['is_assign_store'] == 'true'){
            $orderLib = kernel::single('ome_order');
            $error_msg = '';
            $assignBranch = $orderLib->getOrderAssignBranch($order_id, $error_msg);
            if($assignBranch){
                $branch_list = $assignBranch;

                $tmpBranch = array_shift($assignBranch); //弹出第一个仓库
                $selected_branch_id = $tmpBranch['branch_id'];
            }
        }

        $this->pagedata['selected_branch_id'] = $selected_branch_id;

        // todo maxiaochen 得物品牌直发多仓发货只显示得物发货仓列表
        if (strtolower($order['shop_type']) == 'dewu' && kernel::single('ome_order_bool_type')->isDWBrand($order['order_bool_type']) && $orderExtend['extend_field']['performance_type'] == '3') {

            $oAddress = app::get('ome')->model('return_address');
            $dewuBrandList = $oAddress->getList('distinct branch_bn', ['shop_type'=>'dewu']);
            $dewuBrandList = array_column($dewuBrandList, 'branch_bn');

            foreach ($branch_list as $k => $v) {
                if (!in_array($v['branch_bn'], $dewuBrandList)) {
                    unset($branch_list[$k]);
                }
            }
        }
        // 拆分仓库列表：电商大仓列仍平铺展示；门店仓改为下拉（页面通过 has_store_branch 判断是否展示）
        $warehouse_branch_list = array();
        $store_branch_list = array();
        foreach ((array)$branch_list as $__b) {
            if (isset($__b['b_type']) && $__b['b_type'] == '2') {
                $store_branch_list[] = $__b;
            } else {
                $warehouse_branch_list[] = $__b;
            }
        }
        $this->pagedata['branch_list'] = $warehouse_branch_list; // 原模板循环使用
        $this->pagedata['branch_list_warehouses'] = $warehouse_branch_list; // 新模板兼容字段
        $this->pagedata['store_branch_list'] = $store_branch_list;
        $this->pagedata['has_store_branch'] = empty($store_branch_list) ? 'false' : 'true';
        // 默认门店：优先使用已选仓（若为门店），否则退回门店列表第一项
        $default_store_branch_id = 0;
        if (!empty($store_branch_list)) {
            $storeBranchIds = array();
            foreach ($store_branch_list as $__sb) { $storeBranchIds[] = (int)$__sb['branch_id']; }
            if (in_array((int)$selected_branch_id, $storeBranchIds, true)) {
                $default_store_branch_id = (int)$selected_branch_id;
            } else {
                $default_store_branch_id = (int)$store_branch_list[0]['branch_id'];
            }
        }
        $this->pagedata['default_store_branch_id'] = $default_store_branch_id;
        $this->pagedata['jsStoreBranches'] = json_encode($store_branch_list);

        $orderWeight = array();
        foreach ($combinIds as $combin_order_id) {
            $orderWeight[$combin_order_id] = $this->app->model('orders')->getOrderWeight($combin_order_id);
        }

        $weight = 0;
        foreach ($isCombinIds as $oweight) {
            if($orderWeight[$oweight]==0){
                $weight = 0;
                break;
            }else{
                $weight+=$orderWeight[$oweight];
            }
        }

        //判断jitx
        if(kernel::single('ome_order_bool_type')->isJITX($order['order_bool_type'])){
            if(!$order['logi_no']){
                $orderExtend = app::get('ome')->model('order_extend')->db_dump(array('order_id'=>$order['order_id']),'platform_logi_no');
                $order['logi_no'] = $orderExtend['platform_logi_no'];
            }
        }else{
            unset($order['logi_no']);
        }

        // 京东集运订单配送信息禁止编辑
        if ($order['shop_type'] == '360buy') {
            $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', 'SOMS_GNJY');
            if ($jyInfo) {
                $order['is_jy'] = true;
            }
        }
    
        //预估发货快递白名单
        
        if (isset($orderExtend['white_delivery_cps']) && is_string($orderExtend['white_delivery_cps'])) {
            $logiCodes = json_decode($orderExtend['white_delivery_cps'], true);
            if (is_array($logiCodes) && !empty($logiCodes)) {
                $this->pagedata['assign_express_code'] = implode('、', array_filter($logiCodes));
            }
        }

        //收货地址判断是否包含手机
        $combine_conf = app::get('ome')->getConf('ome.combine.addressconf');
        $this->pagedata['combine_addressconf_mobile'] = strval($combine_conf['mobile']);
        $shopObj = $this->app->model("shop");
        $shopInfo = $shopObj->dump($order['shop_id'],'name,shop_type');
        $this->pagedata['shopInfo'] = $shopInfo;
        $orderStatus = $combineObj->getStatus($order);
        $this->pagedata['orderStatus'] = $orderStatus;
        $this->pagedata['region'] = $region;
        $this->pagedata['newregion'] = $newregion;
        $this->pagedata['order_id'] = $order_id;
        $this->pagedata['object_alias'] = $object_alias;
        $this->pagedata['member'] = $member;
        $this->pagedata['order'] = $order;
        $this->pagedata['curorder'] = $order;
        $this->pagedata['weight'] = $weight;
        $this->pagedata['orderWeight'] = json_encode($orderWeight);
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['finder_id'] = $finder_id;

        //到不到是否开启
        $arrive_conf = app::get('ome')->getConf('ome.logi.arrived');
        $this->pagedata['arrive_conf'] = intval($arrive_conf);

        //获取订单的退款、退换货记录
        if(in_array($order['pay_status'], array('4', '5', '6', '7'))){
            $splitReshipList = $orderSplitLib->getReshipByOrderId($order_id);

            //退换货记录
            if($splitReshipList['reship_list']){
                $this->pagedata['reship_list'] = $splitReshipList['reship_list'];
            }

            //退款记录
            if($splitReshipList['refund_apply']){
                $this->pagedata['refund_apply'] = $splitReshipList['refund_apply'];
            }
        }

        //是否走拆单流程
        if($split_model){
            $orderItemMdl = app::get('ome')->model('order_items');
            $orderItemList = $orderItemMdl->getList('*', array('order_id'=>$order['order_id']));
            
            //是否存在福袋基础物料
            $is_luckybag_flag = false;
            foreach ($orderItemList as $itemKey => $itemVal)
            {
                //check
                if($itemVal['delete'] == 'true'){
                    continue;
                }
                
                //item_type
                if($itemVal['item_type'] == 'lkb'){
                    $is_luckybag_flag = true;
                }
            }
            
            //货到付款禁止拆单
            if($order['shipping']['is_cod'] == 'true' || ($order['shop_type'] == 'taobao' && $order['order_source'] == 'maochao') || $is_luckybag_flag){
                $this->pagedata['split_model']  = 0;
                $this->pagedata['order_is_cod'] = 'true';
                $this->singlepage("admin/order/confirm.html");
                exit;
            }

            $error_msg     = '';
            $splitOrder    = $orderSplitLib->checkOrderConfirm($order, $combineOrders, $error_msg);
            if($splitOrder['rsp'] == 'fail'){
                //捆绑商品中有删除的货品
                if($error_msg){
                    header("content-type:text/html; charset=utf-8");
                    echo "<script>alert('". $error_msg ."');opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();</script>";
                    exit;
                }

                //有多个重复的商品货号相同
                if($splitOrder['repeat_product']){
                    $this->pagedata['repeat_product']  = implode(',', $splitOrder['repeat_product']);
                }

                $this->pagedata['split_model']  = 0;
                $this->singlepage("admin/order/confirm.html");
                exit;
            }

            //格式化后的应处理订单
            $this->pagedata['combineOrders']    = $combineOrders;
            $this->pagedata['jsOrders']         = json_encode($combineOrders);

            //获取订单已拆分的发货单信息
            $splitDeliveryList    = $orderSplitLib->getDeliveryByOrderId($order_id, $branch_list);
            if($splitDeliveryList){
                $this->pagedata['order_dlylist']    = $splitDeliveryList;
            }

            // 拆单页面不支持门店仓选择，屏蔽门店仓相关功能
            $this->pagedata['has_store_branch'] = 'false';
            $this->pagedata['store_branch_list'] = array();
            $this->pagedata['default_store_branch_id'] = 0;
            $this->pagedata['jsStoreBranches'] = '[]';

            //拆单模板页
            $this->singlepage("admin/order/confirm_split.html");
        }else{
            //普通审单模板页(开启拆单后,会加载上面的拆单模板页)
            $this->singlepage("admin/order/confirm.html");
        }
    }

    /**
     * 获取DefaultBranch
     * @param mixed $orderIds ID
     * @param mixed $addr addr
     * @return mixed 返回结果
     */
    public function getDefaultBranch($orderIds,$addr=''){
        $combineObj = kernel::single('omeauto_auto_combine');
        $branchPlugObj = new omeauto_auto_plugin_branch();
        $combinGroup = $combineObj->getOrderGroup($orderIds);
        foreach ($combinGroup as $key => $value) {
            $tmp = explode('||', $key);
            $groups[] = array('idx' => $tmp[1], 'hash' => $tmp[0], 'orders' => explode(',', $value['orders']));
        }

        $itemObjects = $combineObj->getItemObject($groups);
        $branch_id = array();
        foreach ($itemObjects as $key => $item) {
            $confirmRoles = '';
            $branchPlugObj->process($item,$confirmRoles);
            $branch_ids = $item->getBranchId();
            foreach ($branch_ids as $k => $v) {
                list($tmpTid, ) = explode('-', $k);
                if(!isset($tid)) {
                    $tid = $tmpTid;
                }
                if($tid != $tmpTid) {
                    unset($branch_ids[$k]);
                }
            }
            $item->setBranchId($branch_ids);
            $branch_id[$key] = kernel::single('omeauto_branch_choose')->getSelectBid($tid,$item);
        }

        return $branch_id;
    }

    /**
     * ajaxGetDefaultBranch
     * @return mixed 返回值
     */
    public function ajaxGetDefaultBranch(){
        $orderIds = json_decode($_POST['orders']);

        $combineObj = kernel::single('omeauto_auto_combine');
        $branchPlugObj = new omeauto_auto_plugin_branch();
        $splitStoreObj = new omeauto_split_storemax();
        $sysAppointObj = new omeauto_split_branchappoint();

        $groups = [];
        $groups[] = array('idx' => '1', 'hash' => '1', 'orders' => $orderIds);

        $itemObjects = $combineObj->getItemObject($groups);
        $branchIdCropId = array();
        $allBranchId = array();
        $storeCode = '';
        $order_bool_type = '';
        foreach ($itemObjects as $key => $item) {
            $orders = $item->getOrders();
            foreach ($orders as $order => $orderVal) {
                $order_bool_type = $orderVal['order_bool_type'];
                foreach ($orderVal['objects'] as $object => $objVal)
                {
                    if ($objVal['store_code']) {
                        $storeCode = $objVal['store_code'];
                        unset($orders[$order]['objects'][$object]['store_code']);
                    }
                }
            }
            $item = new omeauto_auto_group_item($orders);

            $branchPlugObj->process($item);
            $allBranchId = $item->getBranchId();

            // 按就全优选仓
            $splitStoreObj->splitOrder($item,[]);

            $branch_id = $item->getBranchId();
            foreach ($item->getBranchIdCorpId() as $bid => $cid) {
                $branchIdCropId[$bid] = $cid;
            }
        }

        foreach($branch_id as $value){

            $branchObj = app::get('ome')->model('branch');
            if (!$storeCode) {
                $branch = $branchObj->db->selectrow("SELECT branch_id,name FROM sdb_ome_branch WHERE branch_id='".$value."'");
            }else{
                $branch = $branchObj->db->selectrow("SELECT branch_id,name FROM sdb_ome_branch WHERE branch_bn='".$storeCode."'");
                if (!$branch) {
                    $branch = $branchObj->db->selectrow("SELECT branch_id,name FROM sdb_ome_branch WHERE branch_id='".$value."'");
                }


            }
            if(kernel::single('ome_order_bool_type')->isJDLVMI($order_bool_type) && $storeCode){

                $branch_relation = app::get('ome')->model('branch_relation')->db_dump(array (
                    'relation_branch_bn' => $storeCode,
                    'type' => 'jdlvmi',
                ));
                 $branch = $branchObj->db->selectrow("SELECT branch_id,name FROM sdb_ome_branch WHERE branch_id='".$branch_relation['branch_id']."'");
            }
            if(is_array($branch) && $branch['branch_id']>0){
                break;
            }else{
                $branch = array('branch_id'=>0,'name'=>'');
            }
        }


        $branch['all_branch_id'] = $allBranchId;
        $branch['corp_id'] = $branchIdCropId[$branch['branch_id']];
        echo json_encode($branch);
    }

    /**
     * 非拆单页面获取默认仓库 - 避免重复调用ajaxGetDefaultBranch
     * 逻辑与ajaxGetDefaultBranch一致，包含库存检查逻辑，确保返回的all_branch_id只包含有库存的仓库
     */
    public function getDefaultBranchForNormal(){
        $orderIds = json_decode($_POST['orders']);

        $combineObj = kernel::single('omeauto_auto_combine');
        $branchPlugObj = new omeauto_auto_plugin_branch();
        $splitStoreObj = new omeauto_split_storemax();

        $groups = [];
        $groups[] = array('idx' => '1', 'hash' => '1', 'orders' => $orderIds);

        $itemObjects = $combineObj->getItemObject($groups);
        $branchIdCropId = array();
        $allBranchId = array();
        $storeCode = '';
        $order_bool_type = '';
        $branch_id = [];
        
        foreach ($itemObjects as $key => $item) {
            $orders = $item->getOrders();
            foreach ($orders as $order => $orderVal) {
                $order_bool_type = $orderVal['order_bool_type'];
                foreach ($orderVal['objects'] as $object => $objVal)
                {
                    if ($objVal['store_code']) {
                        $storeCode = $objVal['store_code'];
                        unset($orders[$order]['objects'][$object]['store_code']);
                    }
                }
            }
            $item = new omeauto_auto_group_item($orders);

            $branchPlugObj->process($item);
            $allBranchId = $item->getBranchId();

            // 按就全优选仓
            $splitStoreObj->splitOrder($item,[]);

            // 使用与getDefaultBranch相同的选择逻辑
            $branch_ids = $item->getBranchId();
            $item->setBranchId($branch_ids);
            foreach ($allBranchId as $k => $v) {
                list($tmpTid, ) = explode('-', $k);
                if(!isset($tid)) {
                    $tid = $tmpTid;
                }
                if($tid != $tmpTid) {
                    unset($allBranchId[$k]);
                }
            }
            $branch_id[$key] = kernel::single('omeauto_branch_choose')->getSelectBid($tid, $item);
            
            foreach ($item->getBranchIdCorpId() as $bid => $cid) {
                $branchIdCropId[$bid] = $cid;
            }
        }

        foreach($branch_id as $value){

            $branchObj = app::get('ome')->model('branch');
            if (!$storeCode) {
                $branch = $branchObj->db->selectrow("SELECT branch_id,name FROM sdb_ome_branch WHERE branch_id='".$value."'");
            }else{
                $branch = $branchObj->db->selectrow("SELECT branch_id,name FROM sdb_ome_branch WHERE branch_bn='".$storeCode."'");
                if (!$branch) {
                    $branch = $branchObj->db->selectrow("SELECT branch_id,name FROM sdb_ome_branch WHERE branch_id='".$value."'");
                }


            }
            if(kernel::single('ome_order_bool_type')->isJDLVMI($order_bool_type) && $storeCode){

                $branch_relation = app::get('ome')->model('branch_relation')->db_dump(array (
                    'relation_branch_bn' => $storeCode,
                    'type' => 'jdlvmi',
                ));
                 $branch = $branchObj->db->selectrow("SELECT branch_id,name FROM sdb_ome_branch WHERE branch_id='".$branch_relation['branch_id']."'");
            }
            if(is_array($branch) && $branch['branch_id']>0){
                break;
            }else{
                $branch = array('branch_id'=>0,'name'=>'');
            }
        }


        $branch['all_branch_id'] = $allBranchId;
        $branch['corp_id'] = $branchIdCropId[$branch['branch_id']];
        echo json_encode($branch);
    }

    private function getDefaultParseCorp($order) {
        $defaultExpress = array();
        $defaultExpressType = array(
            'yes' => [],
            'no' => [],
        );
        if($order['order_type'] == 'vopczc') {
            $defaultExpressType['yes'] = 'HXPJBEST';
            return $defaultExpressType;
        }
        is_string($order['mark_text']) && $order['mark_text'] = unserialize($order['mark_text']);
        is_string($order['custom_mark']) && $order['custom_mark'] = unserialize($order['custom_mark']);

        $parseEC = new ome_parse_ec_parseEC();

        if (!empty($order['custom_mark'][0]['op_content'])) {
            $parseEC->setContent($order['custom_mark'][0]['op_content']);
            $defaultExpress = $parseEC->parse();
        }

        if (!empty($order['mark_text'][0]['op_content'])) {
            $parseEC->setContent($order['mark_text'][0]['op_content']);
            $md = $parseEC->parse();

            // 以客服为主
            if (!empty($md['yes'])) {
                $defaultExpress['yes'] = $md['yes'];
                $defaultExpress['no'] = $md['no'];
            }

            if (!empty($md['no'])) {
                $defaultExpress['no'] = $md['no'];
            }
        }

        if (is_array($defaultExpress)) {
            foreach ($defaultExpress as $yesOrNo => $express) {
                if (is_array($express)) {
                    foreach ($express as $ec) {
                        foreach ($ec as $eci) {
                            $defaultExpressType[$yesOrNo][] = $eci['type'];
                        }
                    }
                }
            }
        }

        //过滤掉重叠项
        foreach ($defaultExpressType['yes'] as $k => $yType) {
            if (in_array($yType, $defaultExpressType['no'])) {
                unset($defaultExpressType['yes'][$k]);
            }
        }

        if (isset($defaultExpressType['yes'])) {
            if (empty($defaultExpressType['yes'][0])) {
                $defaultExpressType['yes'] = '';
            } else {
                $defaultExpressType['yes'] = $defaultExpressType['yes'][0];
            }
        }

        return $defaultExpressType;
    }

    /**
     * 确认及通生成发货单
     * 
     * @param void
     * @return void
     */
    function finish_combine()
    {
        $this->begin("index.php?app=ome&ctl=admin_order&act=do_confirm&p[0]=" . $_POST['order_id']);

        $orderMdl = $this->app->model('orders');

        $act = $_POST['do_action'];
        if ($act == 4 || $act == 5) {
            //订单暂停或恢复
            if (empty($_POST['order_id'])) {
                $this->end(false, '没有要操作的订单！');
            }

            if ($act == 4) {
                //订单暂停
                $rs = $orderMdl->pauseOrder($_POST['order_id'], false, '');
                if ($rs['rsp'] == 'succ') {
                    $this->end(true, '订单暂停成功');
                } else {
                    $this->end(true, '订单暂停失败');
                }
            } else {
                //订单恢复
                if ($orderMdl->renewOrder($_POST['order_id'])) {
                    $this->end(true, '订单恢复成功');
                } else {
                    $this->end(true, '订单恢复失败');
                }
            }
        } else {
            //检查
            $orders = $_POST['orderIds'];
            $consignee = $_POST['consignee'];
            $logiId = $_POST['logi_id'];
            $consignee['memo'] = $_POST['delivery_remark'];

            //拆单配置
            $orderSplitLib    = kernel::single('ome_order_split');
            $split_seting     = $orderSplitLib->get_delivery_seting();

            //[拆单]审核的SKU和数量
            $splitting_product = $_POST['left_nums'];

            //[拆单]多个重复捆绑商品不支持拆单
            if($_POST['is_repeat_product'] == 'true')
            {
                $split_seting    = '';
                $splitting_product = '';
            }

            if (empty($orders)) {
                $this->end(false, '你没有选择要操作的订单！');
            }

            if (empty($logiId)) {
                $this->end(false, '请选择快递公司！');
            }

            if (empty($consignee)) {
                $this->end(false, '没有配送地址信息！');
            }

            //检查仓库和物流公司属性是否一致
            $branchObj    = app::get('ome')->model('branch');
            $corpObj      = app::get('ome')->model('dly_corp');

            $branch_id    = $_POST['branch_id'];
            $temp_branch  = $branchObj->db->selectrow("SELECT * FROM sdb_ome_branch WHERE branch_id=". $branch_id);

            // 判断是否为自有仓，用ome_branch表的owner判断，全峰会有问题
            $isSelfwms = true;
            $channelAdapter = app::get('channel')->model('adapter')->db_dump(['channel_id'=>$temp_branch['wms_id']]);
            if ($channelAdapter['adapter'] != 'selfwms') {
                $isSelfwms = false;
            }

            $corpInfo = $corpObj->dump(array('corp_id'=>$logiId), '*');
            // 门店 O2O 豁免：门店仓(b_type=2)且该门店参与O2O时，跳过 b_type 与 d_type 的一致性校验
            $skipTypeCheck = false;
            if ($temp_branch['b_type'] == '2' && app::get('o2o')->is_installed()) {
                $o2oStoreObj = app::get('o2o')->model('store');
                $o2oStore = $o2oStoreObj->getList('store_id', array('branch_id' => $branch_id, 'is_o2o' => '1'), 0, 1);
                $skipTypeCheck = !empty($o2oStore);
            }
            if(!$skipTypeCheck && $temp_branch['b_type'] != $corpInfo['d_type'])
            {
                $this->end(false, '选择的快递公司与发货仓库不匹配，请重新选择！');
            }

            //订单列表
            $orderList = $orderMdl->getList('*', array('order_id'=>$orders));

            //orderInfo
            $orderRow  = $orderMdl->plain_to_sdf($orderList[0]);//consigner 格式化地址参数
            $diff_consignee = array_diff_assoc((array)$_POST['consignee'], $orderRow['consignee']);
            if (!empty($diff_consignee)) {
                $extend_data['extend_status'] = 'consignee_modified';
                //记录地址发生变更的扩展
                $this->app->model('order_extend')->update($extend_data, ['order_id' => $orders]);
            }



            //[拆单]多个重复的货品需要检查库存
            $error_msg = '';
            if($split_seting && $splitting_product){
                $checkStore = $orderSplitLib->check_branch_stoce($splitting_product, $branch_id, $error_msg);
                if(!$checkStore){
                    $this->end(false, $error_msg);
                }
            }

            //o2o门店发货
            if($_POST['is_select_o2o'] == 'true')
            {
                if(empty($_POST['o2o_store_id']))
                {
                    $this->end(false, '请选择门店！');
                }

                //验证收货人手机号否则收不到提货短信
                $is_mobile    = kernel::single('ome_func')->isMobile($consignee['mobile']);
                if(!$is_mobile)
                {
                    $this->end(false, '收货人手机号不正确，请正确填写后再提交！');
                }

                //o2o门店仓库
                $isStoreBranch    = kernel::single('ome_branch')->isStoreBranch($_POST['branch_id']);
                if(!$isStoreBranch)
                {
                    $this->end(false, '门店或仓库不存在！');
                }

                //门店交付订单_不允许部分拆分
                $orderInfo    = $orderMdl->getList('order_bn', array('order_id'=>$orders, 'process_status'=>'splitting'));
                if($orderInfo)
                {
                    $split_order_bn    = array();
                    foreach ($orderInfo as $key => $val)
                    {
                        $split_order_bn[]    = $val['order_bn'];
                    }
                    $this->end(false, '交付门店的订单不能是部分拆分状态（'. implode(',', $split_order_bn) .'）');
                }

                //[门店自提]不支持拆单功能
                $split_seting = '';
                $splitting_product = '';
            }elseif(($is_instatnt || $is_seller) && !$skipTypeCheck){ // 门店 O2O 豁免：$skipTypeCheck=true 时不进入同城/商家配送校验
                //同城配送&&商家配送,检查合并的订单
                $orderList = $orderMdl->getList('order_id,order_bn,shop_type,shipping', array('order_id'=>$orders));

                if($is_instatnt){
                    //同城配送
                    foreach ($orderList as $orderKey => $orderVal)
                    {
                        /***
                        if($orderVal['shipping'] != 'INSTATNT'){
                            $error_msg = '订单号：'. $orderVal['order_bn'] .'不是同城配送类型,不能合并发货!';
                            $this->end(false, $error_msg);
                        }
                        ***/

                        if(!in_array($orderVal['shop_type'], array('taobao', 'tmall'))){
                            $error_msg = '订单号：'. $orderVal['order_bn'] .'不是淘系订单,不能使用同城配送!';
                            $this->end(false, $error_msg);
                        }
                    }
                }else{
                    //商家配送
                    foreach ($orderList as $orderKey => $orderVal)
                    {
                        /***
                        if($orderVal['shipping'] != 'SELLER'){
                            $error_msg = '订单号：'. $orderVal['order_bn'] .'不是商家配送类型,不能合并发货!';
                            $this->end(false, $error_msg);
                        }
                        ***/

                        if(!in_array($orderVal['shop_type'], array('taobao', 'tmall'))){
                            $error_msg = '订单号：'. $orderVal['order_bn'] .'不是淘系订单,不能使用商家配送!';
                            $this->end(false, $error_msg);
                        }
                    }
                }
            }

            //[拆单]开始拆单后,必须有拆分的货品sku和数量
            if (empty($splitting_product) && $split_seting)
            {
                $this->end(false,'没有可审核的商品或者可用库存不足');
            }

            $combineObj = kernel::single('omeauto_auto_combine');
            switch ($act) {
                case 1:
                case 2:
                    // 此方法不存在，目前没有2状态
                    $result = $combineObj->confirm($orders, $consignee);
                    break;
                case 3:
                    if($_POST['has_pro_gifts'] == 1){
                        if(count($orders) == 1){
                            $tmp_orderIds = $orders[0];
                        }else{
                            $tmp_orderIds = implode(",", $orders);
                        }
                        //异常状态有多种的时候直接异或可能导致无异常的订单会叠加异常状态to do
                        $orderMdl->db->exec("update sdb_ome_orders set abnormal_status = (abnormal_status ^ ".ome_preprocess_const::__HASGIFT_CODE.") where (abnormal_status & ".ome_preprocess_const::__HASGIFT_CODE." = ".ome_preprocess_const::__HASGIFT_CODE.") and order_id in(".$tmp_orderIds.")");
                    }

                    $consignee['branch_id'] = $_POST['branch_id'];
                    //菜鸟智选物流，会生成面单号,这个面单号需要保存到发货单上
                    $consignee['waybillCode'] = $_POST['waybill_number'];

                    if (trim($_POST['logi_no'])){
                        $consignee['waybillCode'] = $_POST['logi_no'];
                    }

                    // todo maxiaochen 得物品牌直发的多仓发货订单 检测快递公司和发货仓
                    $oAddress       = app::get('ome')->model('return_address');
                    $dewuBrandList  = $oAddress->getList('distinct branch_bn', ['shop_type'=>'dewu']);
                    $dewuBrandList  = array_column($dewuBrandList, 'branch_bn');
                    $dewu_corp_list = kernel::single('logisticsmanager_waybill_dewu')->logistics();
                    unset($dewu_corp_list['VIRTUAL']); // 品牌直发不能用虚拟发货，虚拟发货是急速现货用的
                    $dewu_channel  = app::get('logisticsmanager')->model('channel')->getList('channel_id', ['channel_type'=>'dewu', 'logistics_code|noequal'=>'VIRTUAL']);

                    $oOrder         = $this->app->model('orders');
                    $oExtendModel   = $this->app->model('order_extend');
                    $orderListTmp   = [];
                    foreach ($orders as $orderId) {
                        $oldOrder= $oOrder->dump($orderId,"*",array("order_objects"=>array("*",array("order_items"=>array('*')))));
                        $orderExt = $oExtendModel->db_dump(array('order_id'=>$orderId),'cpup_service,extend_field');
                        $orderExt['extend_field'] && $orderExt['extend_field'] = json_decode($orderExt['extend_field'], 1);

                        $orderListTmp[$orderId]['oldOrder'] = $oldOrder;
                        $orderListTmp[$orderId]['orderExt'] = $orderExt;

                        if ($oldOrder['shop_type'] == 'dewu' && kernel::single('ome_order_bool_type')->isDWBrand($oldOrder['order_bool_type'])) {
                            // 由于没有实际单据测试，所以审单的时候拦截，暂不支持多仓
                            if ($orderExt['extend_field']['performance_type'] == '3'){
                                $this->end(false, '品牌直发暂不支持多仓发货的履约模式');
                            }

                            if ($orderExt['extend_field']['performance_type'] == '3' && !in_array($temp_branch['branch_bn'], $dewuBrandList)) {
                                $this->end(false, '得物品牌直发多仓发货订单的发货仓不能选'.$temp_branch['branch_bn']);
                            }
                            if (!in_array($corpInfo['type'], array_keys($dewu_corp_list))) {
                                $dewu_corp_list_str = '';
                                foreach ($dewu_corp_list as $d_k => $d_v) {
                                    $dewu_corp_list_str .= $d_v.'['.$d_k.'];';
                                }
                                $this->end(false, '得物品牌直发物流公司只能用'.$dewu_corp_list_str);
                            }
                            if ($orderExt['extend_field']['performance_type'] != '2' && !in_array($corpInfo['channel_id'], array_column($dewu_channel, 'channel_id'))) {
                                $this->end(false, '品牌直发物流公司的单号来源只能用得物类型');
                            }
                        }

                        // 抖音中转订单的物流单号来源只能用抖音
                        if ($oldOrder['shop_type'] == 'luban' && $isSelfwms) {
                            $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($orderId, 'order', 'XJJY');
                            if ($jyInfo) {
                                $jd_channel = app::get('logisticsmanager')->model('channel')->getList("channel_id",array('status'=>'true','channel_type'=>['douyin']));
                                if (!in_array($corpInfo['channel_id'], array_column($jd_channel, 'channel_id'))) {
                                    $this->end(false, '自有仓发货抖音中转订单的物流单号来源只能用抖音');
                                }

                            }
                        }

                        // 京东集运，必须使用京东无界电子面单，否则回写会失败
                        if ($oldOrder['shop_type'] == '360buy' && $isSelfwms) {
                            $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($orderId, 'order', 'SOMS_GNJY');
                            if ($jyInfo) {
                                $jd_channel = app::get('logisticsmanager')->model('channel')->getList("channel_id",array('status'=>'true','channel_type'=>['360buy','jdalpha']));
                                if (!in_array($corpInfo['channel_id'], array_column($jd_channel, 'channel_id'))) {
                                    $this->end(false, '自有仓发货京东集运订单的物流单号来源只能用京东');
                                }
                            }
                        }

                    }

                    //[拆单]$splitting_product是拆分的商品列表
                    $errmsg = '';
                    $result = $combineObj->mkDelivery($orders, $consignee, $logiId, $splitting_product, $errmsg, array());
                    if (!$result) {

                        $this->end(false, $errmsg?$errmsg:'有订单状态发生变化无法完成此操作');
                    }

                    //变更退货地址至订单里
                    $oOperation_log = $this->app->model('operation_log');

                    $oOrder_items = $this->app->model('order_items');
                    foreach ($orders as $orderId) {
                        $oldOrder= $orderMdl->dump($orderId,"*",array("order_objects"=>array("*",array("order_items"=>array('*')))));
                        $consignee_diff = [];
                        if(is_array($_POST['consignee'])) {
                            $consignee_diff = array_diff_assoc($_POST['consignee'],$oldOrder['consignee']);
                        }

                        if ($consignee_diff) {
                            foreach ($consignee_diff as $v) {
                                if(strpos($v, '@hash')) {

                                    $this->end(false, '收货人信息有乱码，请编辑过');
                                }
                            }
                            //修改订单地址
                            $new_order['order_id']   = $orderId;
                            $new_order['consignee'] = $consignee_diff;
                            $orderMdl->save($new_order);
                            $oOperation_log->write_log('order_edit@ome',$orderId,"修改订单收货地址");
                            $log_id = $oOperation_log->getList('log_id',array('operation'=>'order_edit@ome','obj_id'=>$orderId),0,1,'log_id DESC');
                            $log_id = $log_id[0]['log_id'];

                            $orderMdl->write_log_detail($log_id,$oldOrder);
                            //更新收货地址
                            kernel::single('ome_service_order')->update_shippinginfo($orderId);

                        }
                    }

                    if (!$result) {

                        $this->end(false, '有订单状态发生变化无法完成此操作');
                    }
                    // //全链路 已财审 // 移到omeauto_auto_group_item里面
                    // kernel::single('ome_event_trigger_shop_order')->order_message_produce($orders,array('2','3','4'));
                    break;
                default:
                    $this->end(false, '不正确的ACTION！');
                    break;
            }
        }
        $msg    = '订单处理成功';

        /*------------------------------------------------------ */
        //-- [拆单]单个订单"部分拆分"状态，跳转到拆分页面
        /*------------------------------------------------------ */
        if(count($_POST['orderIds']) == 1)
        {
            $order_id       = intval($_POST['orderIds'][0]);
            $orderSplitLib    = kernel::single('ome_order_split');
            $split_seting     = $orderSplitLib->get_delivery_seting();

            //开启拆单
            if($split_seting)
            {
                $oOrder = $this->app->model('orders');
                $oRow   = $oOrder->getlist('process_status', array('order_id'=>$order_id), 0, 1);

                //[拆单]订单部分拆分
                if($oRow[0]['process_status'] == 'splitting')
                {
                    $msg    = '订单部分拆分成功';
                }
            }
        }

        $this->end(true, $msg);
    }

    /**
     * 对待处理订单退回到暂存区
     */
    function order_buffer(){
        $this->begin("index.php?app=ome&ctl=admin_order&act=confirm&flt=unmyown");
        $filter = $data = array();
        $data['group_id'] = null;
        $data['op_id'] = null;
        $data['process_status'] = 'unconfirmed';
        $data['confirm'] = 'N';
        $data['pause'] = 'false';
        if (is_array($_POST['order_id']) && count($_POST['order_id'])>0) {
            $filter['order_id'] = $_POST['order_id'];
            $filter['archive'] = 0;
            $filter['assigned'] = 'assigned';
            $filter['abnormal'] = 'false';
            $filter['is_fail'] = 'false';
            $filter['status'] = 'active';
            $filter['process_status'] = array('unconfirmed','confirmed');//禁止splitting部分拆分订单退回暂存区
        }elseif(isset($_POST['isSelectedAll'])&&$_POST['isSelectedAll']=='_ALL_'){//全选待处理订单
            $sub_menu = $this->_views_unmyown();
            if(isset($_POST['view'])){
                if(isset($sub_menu[$_POST['view']])){
                    $filter = $sub_menu[$_POST['view']]['filter'];
                    unset($_POST['view']);
                }
            }
            $filter = array_merge($filter,$_POST);
            unset($filter['app']);
            unset($filter['ctl']);
            unset($filter['act']);
            unset($filter['flt']);
            unset($filter['_finder']);
            $filter['archive'] = 0;
            $filter['assigned'] = 'assigned';
            $filter['abnormal'] = 'false';
            $filter['is_fail'] = 'false';
            $filter['status'] = 'active';
            if(!kernel::single('desktop_user')->is_super()){
                $filter['op_id'] = kernel::single('desktop_user')->get_id();
            }
            $filter['process_status'] = array('unconfirmed','confirmed');//禁止splitting部分拆分订单退回暂存区
        }
        if(is_array($filter)&&count($filter)>0){
            $orderObj = $this->app->model("orders");
            $logObj = $this->app->model('operation_log');
            
            $logObj->batch_write_log('order_dispatch@ome',$filter,'订单退回到暂存区',time());
            $orderObj->filter_use_like = true;
            $orderObj->update($data,$filter);
        }
        $this->end(true, '订单处理成功');
    }

    /**
     * @对已经分派且没有被审核的订单进行收回操作
     * @access public
     * @param void
     * @return void
     */
    function order_recover(){
        //因为订单回收没有权限限定，所以单独调用order_goback来进行
        $this->order_goback();
    }
    /**
     * @对已经分派且没有被审核的订单进行退回/收回操作
     * @access public
     * @param void
     * @return void
     */
    function order_goback(){
        $order_id = $_POST['order_id'];
        if(empty($order_id)){
            //解决选定全部时,没有获取到数据的bug
            if($_POST['isSelectedAll'] == '_ALL_'){
                $base_filter['op_id'] = kernel::single('desktop_user')->get_id();
                $base_filter['assigned'] = 'assigned';
                $base_filter['abnormal'] = "false";
                $base_filter['is_fail'] = 'false';
                $base_filter['status'] = 'active';
                $base_filter['process_status'] = array('unconfirmed', 'confirmed', 'splitting');
                $base_filter['archive'] = 0;
                // 超级管理员
                if(kernel::single('desktop_user')->is_super()){
                    if(isset($base_filter['op_id']))
                        unset($base_filter['op_id']);

                    if(isset($base_filter['group_id']))
                        unset($base_filter['group_id']);
                }
                $_order_id = $this->app->model('orders')->getList('order_id',$base_filter);
                foreach($_order_id as $v){
                    $order_id[] = $v['order_id'];
                }
            }
        }

        if(is_array($order_id) && $order_id)
        {
            //过滤已经通过审核[拆单_部分拆分订单可重新分派]
            $filter = array('order_id|in'=>$order_id, 'process_status'=>array('unconfirmed', 'confirmed', 'splitting'));
            $order_info = $this->app->model('orders')->getList('order_bn,order_id, confirm, process_status, ship_status, pay_status',$filter);

            if($order_info[0]['ship_status'] == '3')
            {
                $this->pagedata['notice'] = '部分退货订单，不能退回未分派';
                $this->pagedata['error'] = true;
            }
            elseif($order_info[0]['process_status'] == 'splitting' && $order_info[0]['pay_status'] == '4')
            {
                $this->pagedata['notice'] = '部分退款订单，不能退回未分派';
                $this->pagedata['error'] = true;
            }
            elseif(($order_info[0]['confirm'] == 'N' && $order_info[0]['process_status'] == 'unconfirmed') || ($order_info[0]['process_status'] == 'splitting'))
            {
                //未拆分或者部分拆分
            }
            elseif($order_info[0]['confirm'] == 'Y' && $order_info[0]['process_status'] == 'confirmed' && $order_info[0]['ship_status'] == '0')
            {
                //已确认-未拆分-未发货
            }
            else
            {
                $this->pagedata['notice'] = '所选订单已经通过审核，没有符合操作的订单';
                $this->pagedata['error'] = true;
            }
            //回收和退回判断
            if(isset($_GET['action']) && $_GET['action']=='recover'){
                 $this->pagedata['action'] = 'recover';
                 $this->pagedata['action_des'] = '回收';
            }else{
                 $this->pagedata['action_des'] = '退回';
            }
            $this->pagedata['order_info'] = $order_info;
        }else{
            $this->pagedata['notice'] = '没有符合操作的订单';
            $this->pagedata['error'] = true;
        }

        $this->display('admin/order/order_goback.html');
    }

    function do_order_goback(){

        $this->begin("");
        //获取操作的权限
        $permissionOjb = kernel::single('desktop_user');
        if($_POST['doaction']=='recover'){
            $act = '收回';
            $has_permission = $permissionOjb->has_permission('order_dispatch');
        }else{
            $act = '退回';
            $has_permission = $permissionOjb->has_permission('order_goback');
        }

        if(!$has_permission){
            $msg = '无权操作：'.$act;
            $this->end(false,$msg);
        }
        //必填信息验证
        $order_id = $_POST['order_id'];
        $remark   = $_POST['remark'];

        if(!is_array($order_id) || !$order_id){
            $this->end(false,'缺少订单号，请重试');
        }
        //$strlen = iconv_strlen($remark);
        if(!$remark){
            $this->end(false,'订单'.$act.'原因不能为空');
        }

        //过滤通过审核的订单[拆单_部分拆分订单可重新分派]
        $objOrder = $this->app->model('orders');
        $filter = array('order_id|in'=>$order_id, 'process_status'=>array('unconfirmed', 'splitting','confirmed'));

        $order_info = $objOrder->getList('order_id, confirm, process_status, ship_status, pay_status',$filter);

        //执行退回操作
        $data = array(
                'group_id' => 0,
                'op_id'    => 0,
                'dispatch_time' =>NULL,
                );
        foreach($order_info as $row)
        {
            //逐个判断订单(排除部分发货、部分退款、部分退货)
            if($row['ship_status'] == '3')
            {
                $this->end(false,'部分退货订单，不能退回未分派'.$act);
            }
            elseif($row['process_status'] == 'splitting' && $row['pay_status'] == '4')
            {
                $this->end(false,'部分退款订单，不能退回未分派'.$act);
            }
            elseif(($row['confirm'] == 'N' && $row['process_status'] == 'unconfirmed') || ($row['process_status'] == 'splitting'))
            {

            }
            elseif($row['confirm'] == 'Y' && $row['process_status'] == 'confirmed')
            {

            }
            else
            {
                $this->end(false,'订单已经审核不能'.$act);
            }

            //[拆单]部分拆分订单可重新分派
            if(($row['confirm'] == 'N' && $row['process_status'] == 'unconfirmed') ||  ($row['confirm'] == 'Y' && $row['process_status'] == 'confirmed'))
            {
                $filter = array('order_id'=>$row['order_id'],'confirm'=>array('N','Y'),'process_status'=>array('unconfirmed','confirmed'));
                $objOrder->goback($data,$filter,$remark,$act);
            }
            elseif($row['process_status'] == 'splitting' && $row['ship_status'] != '3')
            {
                $filter = array('order_id'=>$row['order_id'],'process_status'=>'splitting');
                $objOrder->goback($data,$filter,$remark,$act);
            }

            unset($filter);
        }
        $this->end(true,'订单'.$act.'成功');
    }

    function finish_confirm(){
        $oOrder = $this->app->model("orders");
        $this->begin("index.php?app=ome&ctl=admin_order&act=do_confirm&p[0]=".$_POST['order_id']);

        //判断订单编辑同步状态
        $oOrder_sync = app::get('ome')->model('order_sync_status');
        $sync_status = $oOrder_sync->getList('order_id,type,sync_status',array('order_id'=>$_POST['order_id']),0,1);
        if ($sync_status[0]['sync_status'] == '1'){
            $this->end(false, '订单编辑同步失败,无法确认生成发货单');
        }
        $region = $_POST['consignee'];
        list($package,$region_name,$region_id) = explode(':',$region['area']);
        if (!$region_id){
            $is_area = false;
            //非本地标准地区转换
            $area = $region['area'];
            $regionLib = kernel::single('eccommon_regions');
            $regionLib->region_validate($area);
            $is_correct_area = $regionLib->is_correct_region($area);
            if ($is_correct_area == true){
                 $is_area = true;
                 //更新地区字段
                 $order_update = array(
                   'order_id' => $_POST['order_id'],
                   'consignee' => array(
                       'area' => $area
                   ),
                 );
                 $oOrder->save($order_update);
            }
        }else{
            $is_area = true;
        }

        $action = explode("-",$_POST['do_action']);
        if(in_array(1,$action)){
            $order = $oOrder->dump($_POST['order_id'],'pause');
            if ($order['pause'] == 'true'){
                $this->end(false, '请先恢复订单' );
            }
            //订单确认
            if ($is_area == false){
                $this->end(false,'收货地区与系统不匹配，请编辑订单进行修改！');
            }
            $ret = $oOrder->confirm($_POST['order_id']);
            if(!$ret){
                $this->end(false,'该订单已不需要确认');
                return false;
            }
        }
        if(in_array(2,$action)){
            $order = $oOrder->dump($_POST['order_id'],'pause');
            if ($order['pause'] == 'true'){
                $this->end(false, '请先恢复订单' );
            }
            if ($order['process_status'] == 'cancel'){
                $this->end(false, '订单已取消，无法生成发货单' );
            }
            if ($is_area == false){
                $this->end(false,'收货地区与系统不匹配，请编辑订单进行修改！');
            }

            $_postdelivery = json_decode(urldecode($_POST['order_items']),true);
            $products = $_postdelivery['products'];
            $branch_id = $_postdelivery['branch_id'];
            $deliverys = array();
            $dlys = array();
            if ($products){
                foreach ($products as $pk=>$pv){
                    $item_id = $pv['itemid'];
                    $pv['item_id'] = $pv['itemid'];
                    $pv['order_id'] = $_POST['order_id'];
                    $dlys[$branch_id]['delivery_items'][$item_id] = $pv;
                    unset($pv['order_id'],$pv['itemid'],$pv['item_id']);
                    $deliverys[$branch_id]['delivery_items'][$item_id] = $pv;
                }
            }
            $deliverys[$branch_id]['branch_id'] = $branch_id;
            $deliverys[$branch_id]['logi_id'] = $_postdelivery['logi_id'];
            $_POST['delivery'] = $deliverys;
            unset($_postdelivery, $products, $deliverys, $_POST['order_items']);

            $pro_id = array();
            if($_POST['delivery']){
                $new_delivery = $_POST['delivery'];
                foreach($_POST['delivery'] as $branch_id=>$delivery){
                    if (empty($delivery['logi_id'])){
                        $this->end(false, '请选择物流公司');
                    }
                    $new_delivery_items = array();
                    if ($delivery['delivery_items']){
                        foreach($delivery['delivery_items'] as $item){
                            if ($new_delivery_items[$item['product_id']]){
                                $item['number'] += $new_delivery_items[$item['product_id']]['number'];
                                $new_delivery_items[$item['product_id']] = $item;
                            }else{
                                $new_delivery_items[$item['product_id']] = $item;
                            }
                        }

                        if(count($new_delivery_items) == 0){
                            unset($new_delivery[$branch_id]);
                        }else{
                            $new_delivery[$branch_id]['order_items'] = $dlys[$branch_id]['delivery_items'];
                            $new_delivery[$branch_id]['delivery_items'] = $new_delivery_items;
                            $new_delivery[$branch_id]['consignee'] = $_POST['consignee'];
                        }
                        $pro_id[$item['product_id']] += $new_delivery_items[$item['product_id']]['number'];
                    }
                }
            }
            $product = array();
            $name = array();
            $item_list = $oOrder->getItemBranchStore($_POST['order_id']);
            if ($item_list)
            foreach ($item_list as $il){
                if ($il)
                foreach ($il as $var){
                    if ($var)
                    foreach ($var['order_items'] as $v){
                        $name[$v['product_id']] = $v['name'];
                        $product[$v['product_id']] += $v['left_nums'];
                    }
                }
            }
            if ($product){
                foreach ($product as $id => $number){
                    if ($number < $pro_id[$id]){
                        $this->end(false, $name[$id].'：此商品已拆分完');
                        return ;
                    }
                }
            }
            //订单拆分，产生发货单
            $oOrder->mkDelivery($_POST['order_id'],$new_delivery);
            $item_list = $oOrder->getItemBranchStore($_POST['order_id']);
            if ($item_list)
            foreach ($item_list as $il){
                if ($il)
                foreach ($il as $var){
                    if ($var)
                    foreach ($var['order_items'] as $v){
                        if ($v['left_nums'] >0){
                            $this->end(true, '订单拆分成功');
                        }
                    }
                }
            }
            $this->end(true, '订单拆分完成');
        }
        if(in_array(4,$action)){
            //订单暂停
            $rs = $oOrder->pauseOrder($_POST['order_id'], false, '');
            if ($rs['rsp'] == 'succ'){
                $this->end(true, '订单暂停成功' );
            }else {
                $this->end(true, '订单暂停失败' );
            }
        }
        if(in_array(5,$action)){
            //订单恢复
            if ($oOrder->renewOrder($_POST['order_id'])){
                $this->end(true, '订单恢复成功');
            }else {
                $this->end(true, '订单恢复失败' );
            }
        }
        $this->end(true, '订单处理成功');
    }

    function abnormal(){
        $op_id = kernel::single('desktop_user')->get_id();

        //过滤复审、跨境申报类型订单
        $base_filter = array('abnormal'=>'true','is_fail'=>'false','archive'=>0);

        //action
        $actions = array(
                array(
                        'label' => '批量处理异常',
                        'submit'=>'index.php?app=ome&ctl=admin_batch_order&act=batchDialog&p[0]=dispose_abnormal&p[2]='.$_GET['view'],
                        'target'=>'dialog::{width:690,height:400,title:\'批量处理异常\'}"'
                ),
        );

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }

        $this->order_type = 'abnormal';
        $this->finder('ome_mdl_orders',array(
           'title'=>'异常订单',
           'base_filter'=>$base_filter,
           'use_buildin_new_dialog' => false,
           'use_buildin_set_tag'=>false,
           'use_buildin_recycle'=>false,
           'use_buildin_export'=>true,
           'use_buildin_import'=>false,
           'use_buildin_filter'=>true,
           'finder_aliasname' => 'order_abnormal'.$op_id,
           'use_view_tab'=>true,
           'object_method'=>array('count'=>'countAbnormal','getlist'=>'getlistAbnormal'),
           'actions' => $actions,
        ));
    }

    function do_abnormal($order_id){
        $oAbnormal = $this->app->model('abnormal');
        $oOrder = $this->app->model('orders');
        $ordersdetail = $oOrder->dump(array('order_id'=>$order_id),"op_id,group_id");

        //组织分派所需的参数
        $this->pagedata['op_id'] = $ordersdetail['op_id'];
        $this->pagedata['group_id'] = $ordersdetail['group_id'];
        $this->pagedata['dt_begin'] = strtotime(date('Y-m-d',time()));
        $this->pagedata['dispatch_time'] = strtotime(date('Y-m-d',time()));

        if($_POST){
            $flt = $_POST['flt'];
            $origin_act = $_POST['origin_act']!='' ? $_POST['origin_act'] : 'confirm';
            if ($flt){
                //$this->begin("index.php?app=ome&ctl=admin_order&act=".$origin_act."&flt=".$flt);

            }else{
                //$this->begin("index.php?app=ome&ctl=admin_order&act=".$origin_act);
            }
            $abnormal_data = $_POST['abnormal'];
            $oOrder->set_abnormal($abnormal_data);

            $rs = $oOrder->cancel_delivery($order_id);//取消发货单
           if ($rs['rsp'] == 'fail') {

                echo "<script>alert('订单异常取消发货单失败,原因是:".$rs['msg']."');</script>";

            }else{
                echo "<script>alert('设置异常成功');</script>";
            }
            echo "<script>$$('.dialog').getLast().retrieve('instance').close();window.finderGroup[$(document.body).getElement('input[name^=_finder\[finder_id\]]').value].refresh();</script>";

        }

        $abnormal = $oAbnormal->getList("*",array("order_id"=>$order_id));

        $oAbnormal_type = $this->app->model('abnormal_type');

        $abnormal_type = $oAbnormal_type->getList("*");

        $abnormal[0]['abnormal_memo'] = unserialize($abnormal[0]['abnormal_memo']);
        $this->pagedata['abnormal'] = $abnormal[0];
        $this->pagedata['abnormal_type'] = $abnormal_type;
        $this->pagedata['order_id'] = $order_id;
        $this->pagedata['set_abnormal'] = true;
        $this->pagedata['flt'] = $_GET['flt'];
        $this->pagedata['origin_act'] = $_GET['origin_act'];
        $this->display("admin/order/detail_abnormal.html");
    }

    //状态冲突
    function conflict(){
        $this->finder('ome_mdl_orders',array(
           'title'=>'状态冲突',
           'base_filter'=>array('pay_status'=>'5','ship_status'=>'1','is_fail'=>'false'),
           'use_buildin_new_dialog' => false,
           'use_buildin_set_tag'=>false,
           'use_buildin_recycle'=>false,
           'use_buildin_export'=>false,
           'use_buildin_import'=>false,
           'use_buildin_filter'=>true,
        ));
    }

    function do_export() {
        $selected = $_POST['order_id'];
        $oOrder = $this->app->model('orders');
        $isSelected = $_POST['isSelectedAll'];
        //如果是选择了全部
        if ($isSelected == '_ALL_') {

            $order_ids = $oOrder->getOrderId();

        } else {
            if ($selected)
                foreach ($selected as $order_id) {
                $temp_data = $oOrder->order_detail($order_id);
                $order_info = array();
                $order_info['order_id'] = $temp_data['order_id'];

                $export_data[] = $order_info;
            }

        }
    }

    function get_printable_orders($param) {
        $validator  = $this->app->model('validate');
        if (!$validator->valid()) {
            return "validate failed";
        }
        if (!$param['time_from'] && !$param['time_to']) {
            return array();
        }
        $payed = isset($param['payed'])?$param['payed']:1;
        $to_print = isset($param['to_print'])?$param['to_print']:1;
        $time_from = isset($param['time_from'])?$param['time_from']:0;
        $time_to = isset($param['time_to'])?$param['time_to']:time();
        $page = isset($param['page'])?$param['page']:1;
        $limit = isset($param['limit'])?$param['limit']:1;

        $oOrder = $this->app->model('orders');
        $sql = 'SELECT `order_id` from sdb_ome_orders WHERE 1';
        if ($payed) {
            $sql .= ' and `pay_status`=1';
        }
        if ($to_print) {
            $sql .= ' and `print_finish` = \'true\'';
        }
        if ($time_from) {
            $sql .= " and `createtime`> '$time_from'";
        }
        if ($time_to) {
            $sql .= " and `createtime`< '$time_to'";
        }
        $sql .= " limit ".($page-1)*$limit.','.$limit;
        $order_ids = kernel::database()->db->select($sql);
        $return = array();
        foreach ($order_ids as $orderinfo) {
            $return[] = $oOrder->dump($orderinfo['order_id']);
        }
        //记录日志
        return $return;
    }

     /*
    * 查看售后服务对应日志记录
    */

    function show_aftersale_log($return_id){
        $opObj = $this->app->model('operation_log');
        $log = $opObj->read_log(array('obj_id'=>$return_id,'obj_type'=>'return_product@ome'));

        $this->pagedata['log'] = $log;
        $this->display("admin/order/aftersale_log.html");
    }

    /*
     * 追加备注 append_memo
     */

    function append_memo(){

        $Orders = $this->app->model('orders');
        $orders['order_id'] = $_POST['order']['order_id'];
        if ($_POST['oldmemo']){
            $oldmemo = $_POST['oldmemo'].'<br/>';
        }
        $memo  = $oldmemo.$_POST['order']['mark_text'].'  &nbsp;&nbsp;('.date('Y-m-d H:i:s',time()).' by '.kernel::single('desktop_user')->get_name().')';
        $orders['mark_text'] = $memo;
        $Orders->save($orders);
        echo $memo;
    }

    function view_edit($order_id){

        $oOrder = $this->app->model('orders');
        $order = $oOrder->dump($order_id);
        $branch_list = $oOrder->getBranchByOrder(array($order_id));

        //增加复审订单process_status判断
        if($order['process_status'] == 'is_retrial' && $order['pause'] == 'false'){
            $edit_order['order_id']  = $order_id;
            $edit_order['pause']     = 'true';
            $oOrder->save($edit_order);
            
            //pause
            $order['pause']    = 'true';
        }elseif($order['process_status'] == 'is_retrial'){
            //判断"复审订单" 并且是 "待复审状态"，将不允许编辑提交
            $oRetrial       = app::get('ome')->model('order_retrial');
            $retrial_row    = $oRetrial->getList('*', array('order_id'=>$order_id, 'status'=>'0'), 0, 1);
            if(!empty($retrial_row))
            {
                header("content-type:text/html; charset=utf-8");
                echo "<script>alert('订单号：".$order['order_bn']." 待复审中，请先审核!');window.close();</script>";
                exit;
            }
        }
        if($order['shop_type'] == 'taobao' && $order['order_source'] == 'maochao') {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('订单号：".$order['order_bn']." 属于猫超国际订单，不支持编辑!');window.close();</script>";
            exit;
        }

        if ($order['pause'] == 'false'){
            exit('请先暂停订单');
        }
        if ($order['process_status'] == 'cancel'){
            exit('订单已取消，无法再编辑订单');
        }
        
        //[拆单]部分拆分订单,获取发货单及订单已拆分数量
        $orderSplitLib    = kernel::single('ome_order_split');
        $split_seting     = $orderSplitLib->get_delivery_seting();

        if($split_seting && ($order['process_status'] == 'splitting' || $order['ship_status'] == '2')){
            //仓库列表
            $sql           = "SELECT branch_id, name FROM sdb_ome_branch";
            $temp_data     = kernel::database()->select($sql);
            foreach ($temp_data as $key => $val)
            {
                $dly_branch[$val['branch_id']] = $val['name'];
            }

            //发货单据列表
            $delivery_list  = $delivery_ids = array();
            $sql    = "SELECT d.delivery_id, d.delivery_bn, d.parent_id, d.logi_no, d.logi_name, d.branch_id, d.status, d.is_bind, d.create_time 
                        FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id) 
                        WHERE dord.order_id='".$order_id."' AND d.is_bind='false' AND d.disabled='false' AND d.status NOT IN('failed','cancel','back','return_back')";

            $delivery_list  = kernel::database()->select($sql);
            $status_text    = array ('succ' => '已发货','failed' => '发货失败','cancel' => '已取消','progress' => '等待配货',
                                    'timeout' => '超时','ready' => '等待配货','stop' => '暂停','back' => '打回');
            foreach($delivery_list as $k => $v)
            {
                $delivery_list[$k]['branch_name']   = $dly_branch[$v['branch_id']];
                $delivery_list[$k]['status']        = $status_text[$v['status']];
                $delivery_list[$k]['create_time']   = date('Y-m-d H:i:s', $v['create_time']);
                $delivery_ids[]     = $v['delivery_id'];
            }
            $this->pagedata['delivery_list']    = $delivery_list;
        }

        $item_list = $oOrder->getItemBranchStore($order_id);

        $combineobj = kernel::single('omeauto_auto_combine');
        $combineOrders = $combineobj->fetchCombineOrder($order);

        if(!preg_match("/^mainland:/", $order['consignee']['area'])){
            $region='';
            $newregion='';
            foreach(explode("/",$order['consignee']['area']) as $k=>$v){
                $region.=$v.' ';
            }
        }else{
            $newregion = $order['consignee']['area'];
        }

        $this->pagedata['region'] = $region;
        $this->pagedata['newregion'] = $newregion;
        $this->pagedata['order_id'] = $order_id;
        $order['custom_mark'] = unserialize($order['custom_mark']);
        if ($order['custom_mark'])
        foreach ($order['custom_mark'] as $k=>$v){
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $order['custom_mark'][$k]['op_time'] = $v['op_time'];
            }
        }
        $order['mark_text'] = unserialize($order['mark_text']);
        if ($order['mark_text'])
        foreach ($order['mark_text'] as $k=>$v){
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $order['mark_text'][$k]['op_time'] = $v['op_time'];
            }
        }

        $flag = false;

        //订单代销人会员信息
        $oSellagent = app::get('ome')->model('order_selling_agent');
        $sellagent_detail = $oSellagent->dump(array('order_id'=>$order_id));
        if (!empty($sellagent_detail)){
            $this->pagedata['sellagent'] = $sellagent_detail;
        }
        //发货人信息
        $order_consigner = false;
        if ($order['consigner']){
            foreach ($order['consigner'] as $shipper){
                if (!empty($shipper)){
                    $order_consigner = true;
                    break;
                }
            }
        }
        $oShop = app::get('ome')->model('shop');
        $shop_detail = $oShop->dump(array('shop_id'=>$order['shop_id']));
        $b2b_shop_list = ome_shop_type::b2b_shop_list();
        if (in_array($shop_detail['node_type'], $b2b_shop_list)){
            $this->pagedata['b2b'] = true;
        }

        //购买人信息
        $memberObj = app::get('ome')->model('members');
        $members_detail = $memberObj->dump($order['member_id']);

        // 处理加密
        $order['is_encrypt']  = kernel::single('ome_security_router',$order['shop_type'])->show_encrypt($order,'order');

        $this->pagedata['order'] = $order;
        $this->pagedata['member'] = $members_detail;
        $pmt_orders = $oOrder->getPmtorder($order_id);
        ome_order_func::order_sdf_extend($item_list);
        $obj_config = array();
        if ($servicelist = kernel::servicelist('ome.service.order.edit'))
        foreach ($servicelist as $obj =>$instance){
            if (method_exists($instance,'config_list')){
                $tmp_conf = $instance->config_list();
                $obj_config = array_merge($obj_config,empty($tmp_conf)?array():$tmp_conf);
            }
        }
        // 检查是否存在oid有值且不为-1的行明细
        $has_oid_limit = false;
        foreach ($item_list as $obj => $idata){
            foreach ($idata as $obj_id => $ordObj_list)
            {
                // 检查oid限制
                if (!empty($ordObj_list['oid']) && $ordObj_list['oid'] != '-1') {
                    $has_oid_limit = true;
                    break 2; // 跳出两层循环
                }
            }
        }
        $this->pagedata['has_oid_limit'] = $has_oid_limit;

        foreach ($item_list as $obj => $idata){

            //[拆单]计算订单商品已拆分数量
            foreach ($idata as $obj_id => $ordObj_list)
            {

                if($ordObj_list['obj_type'] == 'pkg' || $ordObj_list['obj_type'] == 'giftpackage')
                {
                    $idata[$obj_id]['make_nums']   = intval($ordObj_list['quantity'] - $ordObj_list['left_nums']);
                }

                foreach ($ordObj_list['order_items'] as $item_id => $ordItem_list)
                {
                    //调整后都以obj层编辑订单,普通商品obj与item相同 xiayuanjun
                    $idata[$obj_id]['make_nums'] = intval($ordItem_list['nums'] - $ordItem_list['left_nums']);
                    $idata[$obj_id]['order_items'][$item_id]['make_nums']   = intval($ordItem_list['nums'] - $ordItem_list['left_nums']);
                }
                $idata[$obj_id]['pmt_order_price'] = $ordObj_list['part_mjz_discount']>0 ? $ordObj_list['part_mjz_discount']:$pmt_orders[$ordObj_list['obj_id']][$ordObj_list['bn']]['apportion_pmt'];
            }

            if (isset($obj_config[$obj])){
                $obj_config[$obj]['load'] = true;
                $obj_config[$obj]['objs'] = $idata;
            }else {
                $obj_config[$obj] = $obj_config['goods'];
                $obj_config[$obj]['load']   = true;
                $obj_config[$obj]['is_add'] = false;
                $obj_config[$obj]['objs']   = $idata;
            }
        }
        
        $conf_list = array();
        if ($obj_config)
        foreach ($obj_config as $name => $conf){
            if ($conf['load']==true) {
                $conf_list[$name] = $conf;
                continue;
            }else if($conf['is_add']==true){
                $conf_list[$name] = $conf;
                $conf_list[$name]['load'] = true;
            }
        }



        $is_super = kernel::single('desktop_user')->is_super();
        //如果不是超级管理员不走这一步
        if(!$is_super){
            //获取网站操作人员id
            $get_id = kernel::single('desktop_user')->get_id();
            //根据操作人员id，获取所的角色
            $role = app::get('desktop')->model('hasrole')->getList('role_id',array('user_id'=>$get_id));
            $role_obj = app::get('desktop')->model('roles');
            $this->pagedata['order_confirm'] = false;
            foreach($role as $v){
                $workgroud = $role_obj->dump(array('role_id'=>$v),'workground');
                $workgroud = unserialize($workgroud['workground']);;
                //检测角色中是否包含审单权限
                if(array_search('order_confirm', $workgroud) !== false){
                    $this->pagedata['order_confirm'] = true;
                    break;
                }
            }
        }else{
            $this->pagedata['order_confirm'] = true;
        }

        //ecshop没有捆绑商品
        if($shop_detail['node_type'] == 'ecshop_b2c'){
            unset($conf_list['pkg']);
        }

        //ksort($conf_list);
        $this->pagedata['conf_list'] = $conf_list;
        $this->pagedata['item_list_log'] = base64_encode(serialize($item_list));
        $this->pagedata['item_list'] = $item_list;
        $this->pagedata['branch_list'] = $branch_list;
        $this->pagedata['combineOrders'] = $combineOrders;

        $tbgiftOrderItemsObj = app::get('ome')->model('tbgift_order_items');
        $tmp_tbgifts = $tbgiftOrderItemsObj->getList('*',array('order_id'=>$order_id),0,-1);
        $this->pagedata['tbgifts'] = $tmp_tbgifts;

        //是否开启复审及复审规则
        $setting_retrial    = $this->get_setting_retrial();
        $this->pagedata['retrial_order']    = $setting_retrial['is_retrial'];

        //[拆单]部分拆分订单,调用单独编辑模板
        if(!empty($delivery_list)){
            $this->singlepage("admin/order/order_edit_split.html");
        }else{
            $this->singlepage("admin/order/order_edit.html");
        }
    }

    /**
     * 余单撤消
     */
    function remain_order_cancel(){

        if ($_POST['remain_order_cancel'] == 'do'){

            $order_id = intval($_POST['order_id']);
            $this->begin("index.php?app=ome&ctl=admin_order&act=do_confirm&p[0]=".$order_id);

            $reback_price = $_POST['refund_money'];//退款金额
            $revock_price = $_POST['revock_price'];//撤销商品总额
            $result = kernel::single('ome_order_order')->order_revoke($order_id,$reback_price,$revock_price);
            if ($result != true || (is_array($result) && $result['result']==false)){
                $result = false;
                $msg = '失败';
                if (is_array($result) && isset($result['msg'])) {
                    $msg = $result['msg'];
                }
            }else{
                $result = true;
                $msg = '成功';
                
                // orderIds
                $orderIds = [$order_id];
                
                // 订单发货完成后，service扩展触发其他服务
                foreach(kernel::servicelist('ome.service.order.finish.after') as $service) {
                    if(method_exists($service, 'after_order_finish')) {
                        $service->after_order_finish($orderIds);
                    }
                }
            }
            
            $this->end($result, app::get('base')->_("余单撤消".$msg));
        }
    }

    /*
     * 显示余单撤消确认页面
     * @param string $order_id 订单号
     * @return 确认页面
     */

    function remain_order_cancel_confirm(){

       $order_id = intval($_GET['order_id']);
       $oOrder = $this->app->model("orders");
       $oRefund = $this->app->model("refunds");
       $order = $oOrder->dump($order_id, '*');
       $order['pmt_order'] = floatval($order['pmt_order']);

       //是否有订单优惠
       if($order['pmt_order']){
           $orderInfo = app::get('ome')->model('orders')->dump($order_id,"order_id",array("order_objects"=>array("*",array("order_items"=>array("*")))));
           $diff_price = kernel::single('ome_order_order')->get_cancel_diff_money($orderInfo);
       }else{
           $diff_price = kernel::single('ome_order_func')->order_items_diff_money($order_id);
       }

        if ($order['process_status'] == 'remain_canlel')
            die('未发货商品总额为0,无法再次撤销！');

        /*[拆单]开启余单撤消金额为0可以申请退款
        if (!$diff_price)
            die('未发货商品总额为0,无法再次撤销！');
        */

       $order['diff_price'] = $diff_price;
       if ($order['payed'] > $diff_price){
           $refund_money = $diff_price;
       }else{
           $refund_money = $order['payed'];
       }
       //已退款金额
       $refunds = $oRefund->getList('money', array('order_id'=>$order_id), 0, -1);
       $refunded = '0';
       if ($refunds){
           foreach ($refunds as $refund_val){
               $refunded += $refund_val['money'];
           }
       }
       $order['refunded'] = $refunded;
       //商品明细
       $item_list = $oOrder->getItemBranchStore($order_id);
       ome_order_func::order_sdf_extend($item_list);
       $this->pagedata['item_list'] = $item_list;
        $pmt_order_money = kernel::single('ome_order_func')->order_diff_pmtmoney($order_id);

        //$order['refund_money'] = $refund_money-$pmt_order_money;
        $order['refund_money'] = $refund_money; //已减去订单优惠分摊

        $order['pmt_order'] = $order['pmt_order']-$pmt_order_money;
       $this->pagedata['order'] = $order;

       /*------------------------------------------------------ */
       //-- [拆单]获取未发货的发货单记录
       /*------------------------------------------------------ */
       $oDelivery       = app::get('ome')->model('delivery');
       $delivery_ids    = $oDelivery->getDeliverIdByOrderId($order_id);

       //[未发货]发货单详情
       if(!empty($delivery_ids))
       {
           $cols        = 'delivery_id, delivery_bn, is_cod, logi_id, logi_no, status, branch_id, 
                                 stock_status, deliv_status, expre_status, verify, process, logi_name';
           $filter      = array('delivery_id'=>$delivery_ids, 'process'=>'false');
           $dly_data    = $oDelivery->getList($cols, $filter, 0, -1);

           $status_text = array ('succ' => '已发货','failed' => '发货失败','cancel' => '已取消','progress' => '等待配货',
                            'timeout' => '超时','ready' => '等待配货','stop' => '暂停','back' => '打回');
           $status_type = array('true'=>'是', 'false'=>'否');

           $delivery_list   = array();
           foreach ($dly_data as $key => $val)
           {
               $val['status']      = $status_text[$val['status']];//发货状态
               $val['is_cod']      = $status_type[$val['is_cod']];
               $val['verify']      = $status_type[$val['verify']];

               $delivery_list[]     = $val;
           }

           if(!empty($delivery_list))
           {
               $this->pagedata['delivery_list']  = $delivery_list;
               $this->pagedata['delivery_flag']  = 'true';
           }
       }

       /*------------------------------------------------------ */
       //-- [拆单]退款&&退换货记录
       /*------------------------------------------------------ */
       if(in_array($order['pay_status'], array('4', '5', '6', '7'))){
           $orderItemObj   = app::get('ome')->model('order_items');
           $oReship        = app::get('ome')->model('reship');
           $oRefund_apply  = app::get('ome')->model('refund_apply');

           //退换货记录
           $status_text    = $oReship->is_check;

           $sql   = "SELECT r.reship_bn, r.status, r.is_check, r.tmoney, r.return_id, i.*
                     FROM sdb_ome_reship as r left join sdb_ome_reship_items as i on r.reship_id=i.reship_id 
                     WHERE r.order_id='".$order_id."' AND r.return_type in('return', 'change') AND r.is_check!='5'";
           $reship_list    = kernel::database()->select($sql);
           if($reship_list){
               $temp_bn  = array();
               foreach ($reship_list as $key => $val)
               {
                   $val['return_type_name']    = ($val['return_type'] == 'return' ? '退货' : '换货');
                   $val['type_name']           = $status_text[$val['is_check']];
                   $val['addon']          = '-';//规格

                   //存储货号查询规格
                   $temp_bn[]        = $val['product_id'];

                   $reship_list[$key]  = $val;
               }

               $temp_items = array();
               $temp_addon = $orderItemObj->getList('product_id, addon', array('order_id'=>$order_id, 'product_id'=>$temp_bn));
               foreach ($temp_addon as $key => $val)
               {
                   if($val['addon']){
                       $temp_items[$val['product_id']] = ome_order_func::format_order_items_addon($val['addon']);;
                   }
               }

               if($temp_addon){
                   foreach ($reship_list as $key => $val)
                   {
                        $product_id = $val['product_id'];

                        if($temp_items[$product_id]){
                            $val['addon']       = $temp_items[$product_id];
                        }
                        $reship_list[$key]      = $val;
                    }
                }
                unset($temp_bn, $temp_addon, $temp_items);
            }
            $this->pagedata['reship_list'] = $reship_list;

           //退款记录
           $refund_apply   = $oRefund_apply->getList('*', array('order_id'=>$order_id, 'disabled'=>'false'));
           if($refund_apply){
               foreach($refund_apply as $k=>$v){
                   $refund_apply[$k]['status_text'] = ome_refund_func::refund_apply_status_name($v['status']);
               }
           }
           $this->pagedata['refund_apply'] = $refund_apply;
           $this->pagedata['is_cancel']    = true;
       }

       $this->singlepage('admin/order/remain_order_cancel.html');
    }

    /*
     * 余单撤消退款
     * @param string $order_id 订单号
     * @param string $refund_money 退款金额
     * @return 退款窗口
     */

    function remain_order_cancel_refund($order_id='',$refund_money='0'){

        $objOrder = $this->app->model('orders');
        if ($_POST){
            $orderdata   = $objOrder->dump($_POST['order_id'], "*", array("order_objects"=>array("*",array("order_items"=>array('*')))));
            if (!kernel::single('ome_order_order')->getPgkDeliveryStatus($orderdata)) {
                $this->splash('error', $this->url, '该订单存在组合商品部分发货无法撤销余单');
            }
            $this->begin('');
            if($_POST['refund_money'] > $orderdata['payed'] ){
                $this->end(false,'退款金额不能大于剩余金额');
            }else{
                //创建退款单
                $_POST['source']          = 'local';
                $_POST['refund_refer']    = '0';
                $is_update_order          = true;//是否更新订单付款状态
                $error_msg = '';
                $return = kernel::single('ome_refund_apply')->createRefundApply($_POST, $is_update_order, $error_msg);
                if(!$return)
                {
                    $this->end(false, $error_msg);
                }

                $this->end(true, $return['msg']);
            }
        }else{
            $order = $objOrder->order_detail($order_id);
            $addon['from'] = 'remain_order_cancel';
            $result = kernel::single('ome_refund_apply')->show_refund_html($order_id, '', $refund_money, $addon);
            if ($result['result'] == true){
                return $result;
            }else{
                exit($result['msg']);
            }
        }
    }

    /**
     * 编辑地址
     * 
     * @return void
     * @author
     * */
    public function view_edit_consignee($order_id)
    {
        $orderMdl = app::get('ome')->model('orders');

        $order = $orderMdl->dump($order_id);
        $order['is_encrypt']  = kernel::single('ome_security_router',$order['shop_type'])->show_encrypt($order,'order');
        $order['consignee']['name'] = '*';

        // 京东集运订单配送信息禁止编辑
        if ($order['shop_type'] == '360buy') {
            $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', 'SOMS_GNJY');
            if ($jyInfo) {
                $order['is_jy'] = true;
            }
        }

        $this->pagedata['order'] = $order;
        $this->display('admin/order/edit/consignee_edit.html');
    }

    function finish_edit(){
        $dlyObj         = app::get('ome')->model('delivery');

        //[拆单]部分拆分订单,获取发货单及订单已拆分数量
        $orderSplitLib    = kernel::single('ome_order_split');
        $split_seting     = $orderSplitLib->get_delivery_seting();
        $oOrder = $this->app->model("orders");
        $oShop  = $this->app->model('shop');

        $order_id       = $_POST['order_id'];
        $order          = $oOrder->dump($order_id);
        $shop_detail    = $oShop->dump(array('shop_id'=>$order['shop_id']), 'node_type');
        $node_type      = $shop_detail['node_type'];
        $source = $order['source'];

        $is_cost_shipping_chaning = false;

        //订单复审_原始订单信息
        $old_order      = $order;

        //判断"复审订单" 并且是 "待复审状态"，将不允许编辑提交
        if($order['process_status'] == 'is_retrial'){
           $oRetrial    = app::get('ome')->model('order_retrial');
           $retrial_row = $oRetrial->getList('*', array('order_id'=>$order_id, 'status'=>'0'), 0, 1);
           if(!empty($retrial_row)){
               header("content-type:text/html; charset=utf-8");
               echo "<script>alert('订单号：".$order['order_bn']." 待复审中，请先审核!');window.close();</script>";
               exit;
           }
        }
        
        $psRow = app::get('ome')->model('order_platformsplit')->db_dump(['order_id'=>$order_id], 'id');
        if($psRow) {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('订单号：".$order['order_bn']." 已进行京东拆，不能编辑!');window.close();</script>";
            exit;
        }
        
        if($_POST['cost_shipping'] == 0){}

        //检测编辑前后，配送费用是否发生了改变
        if( $order['shipping']['cost_shipping'] != $_POST['cost_shipping'] ){
            if($_POST['cost_shipping'] !=='0'){
                //验证配送费用是否是正数
                $re = kernel::single('ome_goods_product')->valiPositive($_POST['cost_shipping']);
                if($re == false){
                    //再次排除类似0.0这种特殊数据
                    if(!preg_match('/^0\.[0]{1,}$/',$_POST['cost_shipping'],$arr)){
                        $this->begin('index.php?app=ome&ctl=admin_order&act=index');
                        $this->end(false, '请录入大于等于0的数值');
                    }
                }
            }
            $is_cost_shipping_chaning = true;
            $order['shipping']['cost_shipping'] = $_POST['cost_shipping'];
        }

        //B2B检测是否允许编辑该订单
        $b2b_shop = ome_shop_type::b2b_shop_list();
        if (in_array($node_type, $b2b_shop)){
            $allow_edit = true;
            if ($allow_edit_service = kernel::service('ome.order.edit')){
                $error = '';
                if(method_exists($allow_edit_service, 'is_allow_edit')){
                    $order_edit_info = array();
                    $order_edit_info['bn'] = $_POST['bn_list'];
                    $order_edit_info['shop_id'] = $_POST['shop_id'];
                    $allow_edit = $allow_edit_service->is_allow_edit($order_edit_info, $error);
                }
            }
            
            if (!$allow_edit){
                $this->begin('');
                if (empty($error))
                    $error = '保存失败';
                $this->end(false, $error);
            }
        }
        
        //操作
        if ($_POST['do_action'] != 0){
            $this->begin('');
            
            $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
            $oOrderItm      = $this->app->model("order_items");
            $oOrderObj      = $this->app->model("order_objects");
            $oOperation_log = $this->app->model('operation_log');
            $obj_orders_extend = $this->app->model('order_extend');

            $post = $_POST;
            $post['order_bn'] = $order['order_bn'];
            if($post['order']['consignee']) {
                foreach ($post['order']['consignee'] as $v) {
                    if(strpos($v, '@hash')) {
                        $this->end(false, '收货人信息有乱码，请编辑过');
                    }
                }
            }
            if ($post['do_action'] != 2){
                if ($order['pause'] == 'false'){
                    $this->end(false, "请先暂停订单");
                }
            }
            list($rs, $rsData) = kernel::single('material_basic_material_stock_freeze')->deleteOrderBranchFreeze([$order_id]);
            if(!$rs) {
                $error_msg = '错误：'.$rsData['msg'];
                $this->end(false, $error_msg);
            }
            $is_address_change  = false;//地址是否变更
            $is_order_change    = false;//是否需要修改
            $is_goods_modify    = false;//是否编辑过商品
            $is_consigner_change = false; //收货人信息变更

            //收货人信息
            $consignee = array_diff_assoc((array)$post['order']['consignee'],$order['consignee']);

            //发货人信息
            $consigner = array_diff_assoc((array)$post['order']['consigner'],(array)$order['consigner']);
            if (!empty($consigner)){
                $is_consigner_change = true;
            }

            if (!empty($consignee)){
                $is_address_change = true;
                $extend_data['order_id'] = $order_id;
                $extend_data['extend_status'] = 'consignee_modified';
                //记录地址发生变更的扩展
                $obj_orders_extend->save($extend_data);
            }

            $goods      = $post['goods'];
            $pkg        = $post['pkg'];
            $gift       = $post['gift'];
            $giftpackage    = $post['giftpackage'];
            $lkb = $post['lkb'];
            $pko = $post['pko'];

            $objtype = $post['objtype'];

            if (empty($goods) && empty($pkg) && empty($gift) && empty($giftpackage) && empty($lkb) && empty($pko)){
                $this->end(false, "订单不能没有商品");
            }

            $new_order = $new_hash = $post['order'];

            //订单明细上的商品是否被修改
            if ($objtype && is_array($objtype)){
                $is_order_change = kernel::single('ome_order_edit')->is_edit_order_item($objtype, $post);
                if($is_order_change){
                    $is_goods_modify = true;
                }
            }else {
                $this->end(false, "订单不能没有商品");
            }

            //是否编辑过商品
            //修改订单折扣金额
            if (strval($order['discount']) != strval($post['discount'])){
                $is_order_change = true;
            }

            //修改订单优惠金额
            if (strval($order['pmt_order']) != strval($post['pmt_order'])){
                $is_order_change = true;
            }

            //打回已存在的发货单(只打回未发货的发货单)
            if ($is_order_change == true || $is_address_change == true || $is_consigner_change == true || $is_cost_shipping_chaning==true){
                $rs    = $oOrder->rebackDeliveryByOrderId($order_id, true, '');
                if(!$rs) {

                    $this->end(false, '发货单撤销失败，不能编辑');
                }
            }

            //处理订单编辑时提交的数据
            $rs = kernel::single("ome_order_edit")->process_order_objtype($objtype,$post);
            if($rs['is_order_change']) {
                $is_order_change = true;
            }

            if($is_order_change == true) {
                kernel::single('ome_order')->edit_divide_pay($rs, $post);
            }

            $obj    = $rs['obj'];
            $new    = $rs['new'];
            $total  = $rs['total'];
            $pmt_goods = $rs['total_pmt_goods'];
            $settlement_amount = $rs['settlement_amount'];
            $actually_amount = $rs['actually_amount'];

            //新添加的基础物料
            $chk_item_ids = array();
            $chk_bm_ids = array();

            //$is_order_change = $rs['is_order_change'];
            $is_goods_modify = $rs['is_goods_modify'];

            if ($is_order_change == true || $is_address_change == true || $is_consigner_change == true || $is_cost_shipping_chaning==true){

                //[拆单]打回发货单后_重载订单确认状态
                if($split_seting){
                    $temp_order          = $oOrder->dump($order_id);
                    $order['process_status']    = $temp_order['process_status'];
                    $order['ship_status']        = $temp_order['ship_status'];

                    unset($temp_order);
                }

                $objMath    = kernel::single('eccommon_math');
                $pro_total  = $order['cost_item'];
                
                $discount   = strval($post['discount']);
                $pmt_order_price   = $post['pmt_order'];

                $new_order['pmt_order']      = $pmt_order_price;
                $new_order['order_id']      = $order_id;
                $new_order['cost_item']     = $total;
                $new_order['pmt_goods']     = $pmt_goods;
                $new_order['shipping']['cost_shipping'] = $order['shipping']['cost_shipping'];
                $new_order['total_amount']  = $objMath->number_plus(array($total, $post['cost_shipping'], $order['shipping']['cost_protect'],$order['cost_tax'],$order['payinfo']['cost_payment']));
                $new_order['total_amount']  = $objMath->number_minus(array($new_order['total_amount'],$pmt_goods,$pmt_order_price));
                $new_order['total_amount']  = $objMath->number_plus(array($new_order['total_amount'],$discount));
                $new_order['discount']      = $discount;
                $new_order['cur_amount'] = $new_order['total_amount'];
                $new_order['settlement_amount'] = $settlement_amount + $post['cost_shipping'];
                $new_order['actually_amount'] = $actually_amount + $post['cost_shipping'];

                if ($new_order['total_amount'] < 0){

                    $this->end(false, "订单折扣金额输入有误");
                }
                if ($consignee)
                    $new_order['consignee'] = $consignee;
                if ($consigner)
                    $new_order['consigner'] = $consigner;

                if ($is_goods_modify == true){
                    $new_order['is_modify'] = 'true';
                }
                $new_order['old_amount']     = $order['total_amount'];
                $new_order['confirm']        = 'N';
                $new_order['process_status'] = 'unconfirmed';
                $new_order['pause']          = 'false';

                //[拆单]部分拆分订单后确认状态设定
                if(!empty($split_seting) && $order['process_status'] == 'splitting')
                {
                    $get_delivery   = $dlyObj->getDeliverIdByOrderId($order_id);//获取已发货的发货单
                    if(!empty($get_delivery))
                    {
                        $new_order['process_status'] = 'splitting';
                        $old_order['process_status'] = $new_order['process_status'];//复审时保存状态
                    }
                }

                $oOperation_log->write_log('order_edit@ome',$_POST['order_id'],"订单修改并恢复");

                //将未修改以前的数据存储以便查询
                if($is_address_change ==true || $is_goods_modify == true || $is_order_change == true||$is_cost_shipping_chaning==true){
                    $log_id = $oOperation_log->getList('log_id',array('operation'=>'order_edit@ome','obj_id'=>$_POST['order_id']),0,1,'log_id DESC');
                    $log_id = $log_id[0]['log_id'];
                    $_POST['item_list'] = unserialize(base64_decode($_POST['item_list']));
                    $this->app->model('orders')->write_log_detail($log_id,$_POST);
                }

                /* 获取订单复审配置 */
                $oRetrial   = app::get('ome')->model('order_retrial');

                //[修改前]订单商品明细列表
                $order_item_list    = $oOrder->getItemList($order_id, true);

                $old_order['is_goods_modify']   = $is_goods_modify;
                $old_order['is_order_change']   = $is_order_change;
                $old_order['is_address_change'] = $is_address_change;
                $old_order['is_consigner_change'] = $is_consigner_change;

                $old_order['item_list']     = $order_item_list;
                $old_order['kefu_remarks']  = addslashes($_POST['kefu_remarks']);

                $retrial_id = $oRetrial->add_retrial($old_order);

                //[设置 ]订单复审_process_status状态，并设为异常订单
                if($retrial_id){
                    $new_order['process_status']    = 'is_retrial';//复审状态
                    $new_order['abnormal']          = 'true';
                    $new_order['pause']             = 'true';//订单暂停
                }

                //本地订单编辑更新hash值
                $orderLib = kernel::single('ome_order');

                if($new_hash){
                    $hashParams = array_merge($order, $new_hash);
                }else{
                    $hashParams = $order;
                }

                $orderExt = $obj_orders_extend->db_dump(array('order_id'=>$order_id),'cpup_service,extend_field');
                if ($orderExt) {
                    $hashParams['cpup_service'] = $orderExt['cpup_service'];
                    if ($orderExt['extend_field']) {
                        $hashParams['extend_field'] = json_decode($orderExt['extend_field'], 1);
                    }
                }
                $orderObjectList = $oOrderObj->getList('*',array('order_id'=>$order_id));
                if ($orderObjectList) {
                    $hashParams['order_objects'] = $orderObjectList;
                }
                $combieHashIdxInfo = $orderLib->genOrderCombieHashIdx($hashParams);
                if($combieHashIdxInfo && $new_order['consignee']){
                    $new_order['order_combine_hash'] = $combieHashIdxInfo['combine_hash'];
                    $new_order['order_combine_idx'] = $combieHashIdxInfo['combine_idx'];
                }
                
                //  原订单更新位置
                kernel::single('console_map_order')->getLocation($order_id);

                //货到付款订单,需要编辑下应收金额
                if($order['shipping']['is_cod'] == 'true' && $order['source'] != 'matrix'){
                    $oObj_orextend = $this->app->model("order_extend");
                    $code_data = array('order_id'=>$order_id,'receivable'=>$new_order['total_amount']);
                    $oObj_orextend->save($code_data);
                }

                if ($is_order_change == true){
                    //更新order_objects,order_items
                    foreach ($obj as $k => $o){
                        $tmp = array();
                        $tmp = $o['items'];
                        unset($o['items']);

                        $oOrderObj->save($o);
                        foreach ($tmp as $oo){

                            $chk_item_ids[] = $oo['item_id'];

                            $oOrderItm->save($oo);
                        }
                    }

                    if ($new)
                    foreach ($new as $ao){
                        //新增新的object
                        $tmp = array();
                        $tmp = $ao['items'];
                        unset($ao['items']);

                        $oOrderObj->save($ao);
                        foreach ($tmp as $aoo){
                            //新增新的item
                            $aoo['obj_id'] = $ao['obj_id'];

                            $product_id = $aoo['product_id'];

                            $chk_bm_ids[] = $product_id;

                            $product_info = $basicMaterialExtObj->dump(array('bm_id'=>$product_id), 'specifications');
                            
                            $oOrderItm->save($aoo);
                        }
                    }

                    //判断基础物料门店是否供货，供货的标记订单为全渠道订单
                    if(app::get('o2o')->is_installed()){

                        //获取items上的product_id
                        if($chk_item_ids){
                            $tempItemsList = $oOrderItm->getList('product_id', array('item_id'=>$chk_item_ids, 'delete'=>'false'));
                            foreach ($tempItemsList as $tempKey => $tempVal){
                                $chk_bm_ids[] = $tempVal['product_id'];
                            }
                        }

                        if($chk_bm_ids)
                        {
                            $basicMaterialLib = kernel::single('material_basic_material');
                            $is_omnichannel = $basicMaterialLib->isOmnichannelOrder($chk_bm_ids);
                            if($is_omnichannel){
                                $omnichannel = 1; //是全渠道订单
                            }else{
                                $omnichannel = 2; //非全渠道订单
                            }

                            $oOrder->update(array('omnichannel'=>$omnichannel), array('order_id'=>$order_id));
                        }
                    }
                }
                // 查询订单产品明细并更新订单
                $objectInof  = app::get('ome')->model("order_objects")->getList('name,quantity as num', array('order_id' => $order_id,'delete'=>'false'));
                if($objectInof){
                    $new_order['title']=json_encode($objectInof);
                }

                //编辑订单后，重新计算订单拆分状态
                $new_order['process_status'] = app::get('ome')->model('order_items')->getProcessStatus($order_id);
                //更新order
                $oOrder->save($new_order);
                app::get('invoice')->model('order_front')->update(['op_edit'=>'1'], ['source_id'=>$order_id, 'source'=>'b2c']);
                //重新计算订单支付状态(货到付款订单不进行支付状态的变更)
                if ($order['shipping']['is_cod'] != 'true') {
                    kernel::single('ome_order_func')->update_order_pay_status($order_id, true, __CLASS__.'::'.__FUNCTION__);
                }

                //修改交易收货人信息 API
                if ($is_address_change == true){
                    if ($service_order = kernel::servicelist('service.order')){
                        foreach($service_order as $object=>$instance){
                           if(method_exists($instance, 'update_shippinginfo')){
                              $instance->update_shippinginfo($order_id);
                           }
                        }
                    }
                }
                
                //订单编辑API
                if ($is_order_change == true){
                    if ($service_order = kernel::servicelist('service.order')){
                        foreach($service_order as $object=>$instance){
                           if(method_exists($instance, 'update_order')){
                               //ecshop本地订单不需要同步到前端
                               if($node_type =='ecshop_b2c' && $source !='matrix' ){
                                   continue;
                               }
                               $rs = $instance->update_order($order_id);
                               //ecshop的订单是直连
                               if($node_type == 'ecshop_b2c'){
                                   if($rs['rsp'] != 'succ'){

                                       $this->end(false, $rs['err_msg']);
                                   }
                               }
                           }
                        }
                    }
                }
                //订单恢复状态同步
                if ($service_order = kernel::servicelist('service.order')){
                    foreach($service_order as $object=>$instance){
                        if(method_exists($instance, 'update_order_pause_status')){
                           $instance->update_order_pause_status($order_id, 'false');
                        }
                    }
                }

                /* 复审订单_库存冻结 */
                if($retrial_id){
                    //编辑保存后订单商品明细列表
                    $order_item_list    = $oOrder->getItemList($order_id, true);

                    $record    = $oRetrial->record_stock_freeze($order_item_list, $retrial_id, false);//保存冻结库存记录
                }
            }else{
                //恢复order
                $oOrder->renewOrder($order_id);
            }

            $shopex_list = ome_shop_type::shopex_shop_type();
            $final_total_amount = isset($new_order['total_amount']) ? $new_order['total_amount'] : $post['total_amount'];

            if( ( $order['source'] == 'local' || in_array($order['shop_type'], $shopex_list) ) && (bccomp('0.000', $final_total_amount,3) == 0) && $order['shipping']['is_cod'] != 'true' ){ //0元订单是否需要财审.货到付款0远都需要才审

                kernel::single('ome_order_order')->order_pay_confirm($order['shop_id'],$order_id,$post['total_amount']);

            }
            
            //[翱象]地址变更后,重新获取配送建议
            if($is_address_change && in_array($order['shop_type'], array('taobao', 'tmall'))){
                $orderTypeLib = kernel::single('ome_order_bool_type');
                $aoxiangLib = kernel::single('dchain_aoxiang');

                //是否翱象订单或者店铺已签约翱象
                $isAoxiang = false;
                if($orderTypeLib->isAoxiang($order['order_bool_type'])) {
                    $isAoxiang = true;
                }else{
                    $isAoxiang = $aoxiangLib->isSignedShop($order['shop_id'], $order['shop_type']);
                }

                //重新获取配送建议
                if($isAoxiang){
                    //订单信息
                    $orderInfo = $oOrder->dump(array('order_id'=>$order_id), '*');

                    //查询建议快递
                    $error_msg = '';
                    $axResult = $aoxiangLib->triggerOrderLogi($orderInfo, $error_msg);
                    $log_msg = ($axResult ? '查询翱象建议快递成功' : '查询翱象建议快递失败：'. $error_msg);

                    //log
                    $oOperation_log->write_log('order_modify@ome', $order_id, $log_msg);
                }
            }
            
            // [大家电订单]商品被修改、收货人地址被修改，订单需要重新预约时间
            if($is_address_change){
                kernel::single('ome_order_reservation')->againReservation($order_id, 'address_change');
            }elseif($is_goods_modify){
                kernel::single('ome_order_reservation')->againReservation($order_id, 'goods_change');
            }
            
            // 增加service调用，便于扩展finish_edit后的处理逻辑
            foreach (kernel::servicelist('ome.service.order.finishedit.after') as $service) {
                if (method_exists($service, 'afterFinishEdit')) {
                    $service->afterFinishEdit($order_id);
                }
            }

            $this->end(true, "完成");
        }else {
            //判断，校验
            $this->begin('');

            $oOrder     = $this->app->model("orders");
            
            $order_id   = $_POST['order_id'];
            $order      = $oOrder->dump($order_id);

            if ($order['pause'] == 'false'){
                $this->end(false, '请先暂停订单');
            }
            $post       = $_POST;

            //[拆单]部分发货 OR 部分退货订单编辑_强制至少保留一个未发货商品
            if($order['ship_status'] == '2' || $order['ship_status'] == '3'){
                $sql    = "SELECT order_id, item_id, obj_id, item_type FROM sdb_ome_order_items
                           WHERE order_id='".$order_id."' AND `delete`='false' AND nums = sendnum";
                $send_items    = kernel::database()->select($sql);

                //过滤已发货的货品
                if($send_items){
                    foreach ($send_items as $key => $val)
                    {
                        $get_item_type    = ($val['item_type'] == 'product' ? 'goods' : $val['item_type']);
                        $get_obj_id       = $val['obj_id'];
                        $get_item_id      = $val['item_id'];

                        unset($post[$get_item_type]['obj'][$get_obj_id]);

                        //goods
                        unset($post[$get_item_type]['num'][$get_item_id], $post[$get_item_type]['price'][$get_item_id]);

                        //pkg
                        if($get_item_type == 'pkg')
                        {
                            unset($post[$get_item_type]['num'][$get_obj_id], $post[$get_item_type]['price'][$get_obj_id]);
                            unset($post[$get_item_type]['inum'][$get_obj_id], $post[$get_item_type]['iprice'][$get_obj_id]);
                        }
                    }

                    unset($get_item_type, $get_obj_id, $get_item_id);
                }
            }

            $goods      = $post['goods'];
            $pkg        = $post['pkg'];
            $gift       = $post['gift'];
            $giftpackage    = $post['giftpackage'];
            $lkb = $post['lkb'];
            $pko = $post['pko'];
            $objtype    = $post['objtype'];
            if (empty($goods) && empty($pkg) && empty($gift) && empty($giftpackage) && empty($lkb) && empty($pko)){
                $this->end(false, "订单不能没有商品");
            }

            //检查输入金额最多只能为2位小数
            $pmt_price = $post['pmt_order'];
            $discount = $post['discount'];
            $cost_shipping = $post['cost_shipping'];

            $tempPrice = bcmul($pmt_price, 1, 2);
            if($tempPrice != $pmt_price){
                $this->end(false, '订单优惠金额：最多只能输入2位小数!');
            }

            $tempPrice = bcmul($discount, 1, 2);
            if($tempPrice != $discount){
                $this->end(false, '订单折扣：最多只能输入2位小数!');
            }

            $tempPrice = bcmul($cost_shipping, 1, 2);
            if($tempPrice != $cost_shipping){
                $this->end(false, '配送费用：最多只能输入2位小数!');
            }

            //check
            if ($objtype && is_array($objtype)){
                //是否有数据提交
                $rs = kernel::single("ome_order_edit")->is_null($objtype,$post);
                if ($rs == true)
                    $this->end(false, "订单不能没有商品");

                //校验数据正确性(新增商品金额最多只能为2位小数)
                $rs = kernel::single("ome_order_edit")->valid_order_objtype($objtype,$post);
                if ($rs !== true && $rs['flag'] == false){
                    $this->end(false, $rs['msg']);
                }
            }else {
                $this->end(false, "订单不能没有商品");
            }
            $this->end(true, '验证完成');
        }
    }

    function getProducts()
    {
        $basicMaterialSelect     = kernel::single('material_basic_select');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        $basicMaterialStockObj   = app::get('material')->model('basic_material_stock');
        $basicMStockFreezeLib    = kernel::single('material_basic_material_stock_freeze');

        $pro_id = $_POST['product_id'];

        if (is_array($pro_id)){
            $filter['bm_id'] = $pro_id;
        }

        //选定全部
        if(is_array($filter['bm_id'][0]) && $filter['bm_id'][0]['_ALL_'])
        {
            if (isset($_POST['filter']['advance']) && $_POST['filter']['advance'])
            {
                $arr_filters    = explode(',', $_POST['filter']['advance']);
                foreach ($arr_filters as $obj_filter)
                {
                    $arr    = explode('=', $obj_filter);
                    $filter[$arr[0] . '|has']    = $arr[1];
                }
                unset($_POST['filter']['advance']);
            }
        }

        if($_GET['bn'])
        {
            $filter = array(
               'material_bn|head'=>$_GET['bn']
           );
        }

        if($_GET['name']){
            $filter = array(
               'material_name|head'=>$_GET['name']
           );
        }
        if($_GET['barcode'])
        {
            $filter = array(
                    'code|head'=>$_GET['barcode']
            );
    
            $code_list = [];
            $bm_ids    = $basicMaterialBarcode->getBmidListByFilter($filter, $code_list);
            $filter['bm_id']    = $bm_ids;
        }

        $data    = $basicMaterialSelect->getlist_ext('bm_id, material_bn, material_name, visibled, retail_price, specifications', $filter);

        $list = array();
        if ($data)
        foreach ($data as $v)
        {
            $v['type'] = 'goods';

            //查询关联的条形码
            $v['barcode']    = $basicMaterialBarcode->getBarcodeById($v['product_id']);

            //库存（各仓库 的库存总和）
            $get_store    = $basicMaterialStockObj->dump($v['product_id'], 'store,store_freeze');

            //根据基础物料ID获取对应的冻结库存
            $get_store['store_freeze']    = $basicMStockFreezeLib->getMaterialStockFreeze($v['product_id']);

            $v['store_minus_freeze']   = $get_store['store'] - $get_store['store_freeze'];

            $list[] = $v;
        }

        echo "window.autocompleter_json=".json_encode($list);
    }

    //自动规则设置-活动订单-获取销售物料列表中选中的bn
    function getSalesMaterialBn(){
        $salesMaterialObj = app::get('material')->model('sales_material');
        if(!$_POST['product_id']){
            return false;
        }
        $data = $salesMaterialObj->getList('sales_material_bn', array("sm_id"=>$_POST['product_id']));
        if($data){
            echo "window.autocompleter_json=".json_encode($data);
        }
    }

    function findProduct(){
        // 商品隐藏
        $filter = array();
        if (!isset($_POST['visibility'])) {
            $filter['visibled'] = 1;
        }elseif(empty($_POST['visibility'])){
            unset($_POST['visibility']);
        }

        $params = array(
            'title'                  => '基础物料列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_view_tab'           => false,
            'use_buildin_filter'     => true,
            'base_filter'            => $filter,
        );
        $this->finder('material_mdl_basic_material', $params);
    }

    function do_check($order_id, $newtotal, $total){

        $orefapply = $this->app->model('orders');
        $order = $orefapply->order_detail($order_id);
        $is_cod = $_GET['is_cod'];
        $pay_status = intval($_GET['pay_status']);
        $newtotal = strval($newtotal);
        $total = strval($total);
        $payed = $order['payed'];
        $refund_money = $order['refund_money'];
        $payed = $payed+$refund_money;
        //是否开启复审及复审规则
        $setting_retrial    = $this->get_setting_retrial();
        $is_retrial         = 'false';//判断显示退款单
        if($setting_retrial['is_retrial'] == 'true' && $_GET['is_retrial'] == 'true')
        {
            $is_retrial    = 'true';
        }
        $this->pagedata['is_retrial']   = $is_retrial;
        $this->pagedata['is_refund']    = ($payed > $newtotal ? 'true' : 'false');

        $is_change = $payed != $newtotal ? 1 : 0;
        if ($payed > $newtotal && $is_retrial != 'true'){
            //$refund_money = $payed - $newtotal;

            $refund_money = bcsub($payed, $newtotal, 2);
            $refund_money_2 = bcsub($payed, $newtotal, 3);
            if(bccomp($refund_money, $refund_money_2, 3) !== 0){
                exit('请检查：退款差额最多只能为2位小数(现在退款金额为：'. $refund_money_2 .')');
            }

            if ($order['pause'] == 'false'){
                exit("请先暂停订单");
            }

            $addon['from'] = 'order_edit';
            $result = kernel::single('ome_refund_apply')->show_refund_html($order_id, '', $refund_money, $addon);
            if ($result['result'] == true){
                return $result;
            }else{
                exit($result['msg']);
            }
        }else{

            /*------------------------------------------------------ */
            //-- [编辑订单]价格监控
            /*------------------------------------------------------ */
            $product_ids   = trim($_GET['product_ids']);
            $product_ids   = explode(',', $product_ids);

            //是复审订单，查询原始订单的总金额
            if($order['process_status'] == 'is_retrial')
            {
                $oSnapshot  = app::get('ome')->model('order_retrial_snapshot');
                $order_old  = $oSnapshot->getList('tid, retrial_id, order_detail', array('order_id'=>$order_id), 0, 1);
                if(!empty($order_old))
                {
                    $order_old  = $order_old[0];
                    $order_old  = unserialize($order_old['order_detail']);
                    $total      = strval($order_old['total_amount']);
                }
            }

            //价格监控
            if(!empty($product_ids))
            {
                $product_list     = array();
                foreach ($product_ids as $key => $val)
                {
                    $temp        = explode('_', $val);
                    $temp_id     = intval($temp[0]);

                    $product_list['ids'][$temp_id]  = $temp_id;
                    $product_list['nums'][$temp_id] = intval($temp[1]);
                }
                unset($product_ids, $temp);

                $oRetrial         = app::get('ome')->model('order_retrial');
                $price_monitor    = $oRetrial->get_product_monitor($product_list, floatval($newtotal));

                $this->pagedata['price_monitor'] = $price_monitor;
            }

            //差额[现订单金额-原订单金额]
            $diff_money        = round(floatval($newtotal - $total), 3);
            $this->pagedata['diff_money']  = $diff_money;

            $this->pagedata['is_cod'] = $is_cod;
            $this->pagedata['is_change'] = $is_change;
            $this->pagedata['change_value'] = round($newtotal-$payed, 3);//禁用abs()函数,有负数退款存在
            $this->pagedata['newtotal'] = $newtotal;
            $this->pagedata['total'] = $total;
            $this->pagedata['payed'] = $payed;

            $this->display("admin/order/order_edit_check.html");
        }
    }

    /**
     * 编辑订单金额变化添加退款单
     */
    function do_refund(){

        $objOrder = app::get('ome')->model('orders');
        $this->begin("index.php?app=ome&ctl=admin_order&act=do_refund&p[0]=".$_POST['order_id']);
        if($_POST){
            $orderdata = $objOrder->order_detail($_POST['order_id']);

            //允许"复审订单"生成退款单
            if ($orderdata['pause'] == 'false' && $orderdata['process_status'] != 'is_retrial')
            {
                $this->end(false, '请先暂停订单');
            }

            //创建退款申请单
            $_POST['source']          = 'local';
            $_POST['refund_refer']    = '0';
            $is_update_order          = true;//是否更新订单付款状态
            $error_msg = '';
            $return = kernel::single('ome_refund_apply')->createRefundApply($_POST, $is_update_order, $error_msg);
            if(!$return)
            {
                $this->end(false, $error_msg);
            }

            $this->end(true, $return['msg']);
        }
    }

    function addOrder(){
        if ($_POST){
            $this->begin("index.php?app=ome&ctl=admin_order&act=addOrder");
            if (!$_POST['type']){
                $this->end(false,'请选择订单类型');
            }
            $brObj = $this->app->model('branch');
            $branch_list = $brObj->getBranchByUser();
            /*if (count($branch_list) == 0){
                $this->end(false, '管理员未关联仓库，请先关联仓库');
            }elseif (count($branch_list) > 1){
                $this->end(false, '管理员已关联多个仓库，无法操作');
            }*/
            $type = $_POST['type'];
            if ($type == 'normal'){
                $this->addNormalOrder();
            }else {
                $this->addSaleOrder();
            }
        }else
            $this->page("admin/order/order_choice.html");
    }

    function addNormalOrder(){
        $shopObj = $this->app->model("shop");
        $filter = array('s_type'=>1, 'delivery_mode|notin'=>'shopyjdf');
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $filter['org_id'] = $organization_permissions;
        }
        $shopData = $shopObj->getList('shop_id,name,shop_type',$filter, 0, -1);
        $this->pagedata['title'] = '新建订单';
        $this->pagedata['shopData'] = $shopData;
        $this->pagedata['creatime'] = date("Y-m-d",time());
        $this->page("admin/order/add_normal_order.html");
    }

    function doAddNormalOrder(){
        $this->begin("index.php?app=ome&ctl=admin_order&act=addNormalOrder");
        define('FRST_TRIGGER_OBJECT_TYPE','订单：手工新建订单');
        define('FRST_TRIGGER_ACTION_TYPE','ome_ctl_admin_order：doAddNormalOrder');
        
        $oObj = $this->app->model("orders");
        $logObj = app::get('ome')->model('operation_log');
        
        $salesMLib = kernel::single('material_sales_material');
        $lib_ome_order = kernel::single('ome_order');
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        $tostr = array();
        $post = $_POST;
        $post['consignee']['r_time']    = '任意日期 任意时间段';
        $post['consignee']['area']      = $post['address_id'];

        $post['member_id'] = $post['id'];
        if (!$post['member_id']){
            $this->end(false, '请选择会员');
        }

        if (!$post['cost_shipping']){
            $post['cost_shipping'] = 0;
        }

        if (!$post['discount']){
            $post['discount'] = 0;
        }

        $consignee = $post['consignee'];
        if ($consignee){
            if (!$consignee['name']){
                $this->end(false, '请填写收件人');
            }
            if (!$consignee['area']){
                $this->end(false, '请填写配送三级区域');
            }
            if (!$consignee['addr']){
                $this->end(false, '请填写配送地址');
            }
            if (!$consignee['mobile'] && !$consignee['telephone']){
                $this->end(false, '收件人手机和固定电话必须填写一项');
            }
        }else {
            $this->end(false, '请填写配送地址信息');
        }
        
        //补发订单
        if(in_array($post['order']['order_type'], array('bufa'))){
            //不需要验证收货人信息，允许复制原订单上加密的收货人信息

            // 需要验证补发原因
            if(empty($post['order']['bufa_reason'])){
                $this->end(false, '请填写补发原因');
            } elseif (!empty($post['order']['bufa_reason']) && mb_strlen($post['order']['bufa_reason']) > 200){
                $this->end(false, '补发原因过长，填写的字数请保持在200字以内');
            }
        }else{
            if (false !== strpos($consignee['addr'],'*') || false !== strpos($consignee['mobile'],'*')) {
                $this->end(false, '收货信息不允许带"*"，请确认后再提交');
            }
        }

        $ship = $_POST['address_id'];

        //检测是不是货到付款
        if($post['is_cod'] == 'true' || $post['is_cod'] == 'false'){
            $is_code = $post['is_cod'];
        }

        $shipping = array();
        if ($ship){
            $shipping = array(
                'shipping_name' => '快递',
                'cost_shipping' => $post['cost_shipping'],
                'is_protect' => 'false',
                'cost_protect' => 0,
                'is_cod' => $is_code?$is_code:'false'
            );
        }else {
            $this->end(false, '请选择物流信息');
        }

        $num = $_POST['num'];
        $price = $_POST['price'];
        if (!$num){
            $this->end(false, '请选择商品');
        }

        foreach ($num as $key => $v){
            if ($v < 1 || $v > 499999){
                $this->end(false, '数量必须大于1且小于499999');
            }
        }

        if (!$price){
            $this->end(false, '请选择商品');
        }

        foreach ($price as $v){
            if ($v < 0){
                $this->end(false, '请填写正确的价格');
            }
        }

        $iorder = $post['order'];
        $iorder['consignee'] = $consignee;
        $iorder['shipping'] = $shipping;

        if($post['shop_id']){
            $shop = explode('*',$post['shop_id']);
            $iorder['shop_id'] = $shop[0];
            $iorder['shop_type'] = $shop[1];
        }else{
            $this->end(false, '请选择来源店铺！');
        }
        
        //shop_bn
        $shopObj = app::get("ome")->model('shop');
        $shopInfo = $shopObj->dump(array('shop_id'=>$iorder['shop_id']), 'shop_bn');
        $shop_bn = $shopInfo['shop_bn'];
        
        $lucky_falg = false;
        foreach ($num as $k => $i){
            //销售物料购买数量
            $obj_number = $i;
            
            $salesMInfo = $salesMLib->getSalesMById($iorder['shop_id'],$k);
            if($salesMInfo){
                if($salesMInfo['sales_material_type'] == 5){ //多选一
                    $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'], $obj_number,$iorder['shop_id']);
                }elseif($salesMInfo['sales_material_type'] == 7){
                    //福袋组合
                    $luckybagParams = $salesMInfo;
                    $luckybagParams['sale_material_nums'] = $obj_number;
                    $luckybagParams['shop_bn'] = $shop_bn;
                    
                    $fdResult = $fudaiLib->process($luckybagParams);
                    if($fdResult['rsp'] == 'succ'){
                        $basicMInfos = $fdResult['data'];
                    }else{
                        //标记福袋分配错误信息
                        $this->end(false, '销售物料编码：'. $salesMInfo['sales_material_bn'] .'获取福袋组合失败：'. $fdResult['error_msg'] .'!');
                    }
                    
                    $lucky_falg = true;
                    
                    //unset
                    unset($luckybagParams, $fdResult);
                }else{
                    //获取绑定的基础物料
                    $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                }
                
                //如果是促销类销售物料
                if($salesMInfo['sales_material_type'] == 2){ //促销
                    $obj_type = $item_type = 'pkg';
                    $obj_sale_price = $price[$k]*$obj_number;
                    //item层关联基础物料平摊销售价
                    $salesMLib->calProSaleMPriceByRate($obj_sale_price, $basicMInfos);
                    $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                }elseif($salesMInfo['sales_material_type'] == 5){ //多选一
                    $obj_type = $item_type = 'pko';
                    foreach($basicMInfos as &$var_basic_info){
                        $var_basic_info["price"] = $price[$k];
                        $var_basic_info["sale_price"] = $price[$k];
                    }
                    unset($var_basic_info);
                    $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                }elseif($salesMInfo['sales_material_type'] == 7){
                    //福袋组合
                    $obj_type = 'lkb';
                    $item_type = 'lkb';
                    
                    //福袋销售物料内的基础物料已经分配好正确的购买数量
                    $lkb_obj_quantity = 1;
                    
                    //格式化order_items
                    $return_arr_info = $lib_ome_order->format_order_items_data($item_type, $lkb_obj_quantity, $basicMInfos);
                }else{
                    $sales_material_type = material_sales_material::$sales_material_type;
                    $obj_type = $sales_material_type[$salesMInfo['sales_material_type']]['type'];
                    $obj_type = $obj_type ? $obj_type : 'goods';
                    $item_type = ($obj_type == 'goods') ? 'product' : $obj_type;
                    if($obj_type == 'gift'){
                        $price[$k] = 0.00;
                    }
                    foreach($basicMInfos as &$var_basic_info){
                        $var_basic_info["price"] = $price[$k];
                        $var_basic_info["sale_price"] = $price[$k];
                    }
                    unset($var_basic_info);
                    $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                }

                $iorder['order_objects'][] = array(
                    'obj_type' => $obj_type,
                    'obj_alias' => $obj_type,
                    'goods_id' => $salesMInfo['sm_id'],
                    'bn' => $salesMInfo['sales_material_bn'],
                    'name' => $salesMInfo['sales_material_name'],
                    'price' => $price[$k],
                    'sale_price'=>$price[$k]*$obj_number,
                    'amount' => $price[$k]*$obj_number,
                    'quantity' => $obj_number,
                    'order_items' => $return_arr_info["order_items"],
                );
                $item_cost += $price[$k]*$obj_number;
                $tostr[]=array("name"=>$salesMInfo['sales_material_name'],"num"=>$obj_number);
            }
        }
        
        if(empty($iorder['order_objects'])) {
            $this->end(false, '选择的商品不是该店铺的！');
        }

        if ($post['customer_memo']){
            $c_memo =  htmlspecialchars($post['customer_memo']);
            $c_memo = array('op_name'=>kernel::single('desktop_user')->get_name(), 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$c_memo);
            $tmp[]  = $c_memo;
            $iorder['custom_mark']  = serialize($tmp);
            $tmp = null;
        }

        if ($post['order_memo']){
            $o_memo =  htmlspecialchars($post['order_memo']);
            $o_memo = array('op_name'=>kernel::single('desktop_user')->get_name(), 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$o_memo);
            $tmp[]  = $o_memo;
            $iorder['mark_text']    = serialize($tmp);
            $tmp = null;
        }

        $mathLib = kernel::single('eccommon_math');

        $iorder['member_id']    = $post['member_id'];
        $iorder['weight']       = $return_arr_info["weight"];
        $iorder['title']        = $tostr ? json_encode($tostr):'';
        $iorder['itemnum']      = count($iorder['order_objects']);
        $iorder['createtime']   = time();
        $iorder['cost_item']    = $item_cost;
        $iorder['currency']     = 'CNY';
        $iorder['pmt_order']    = $post['pmt_order'];
        $iorder['discount']     = $post['discount'];
        
        //关联订单号
        $iorder['relate_order_bn'] = trim($post['relate_order_bn']) ? trim($post['relate_order_bn']) : trim($post['order_bn']);
        
        $iorder['total_amount'] = $mathLib->number_plus(array($item_cost,$post['cost_shipping'],$post['discount']));
        $iorder['total_amount'] = $mathLib->number_minus(array($iorder['total_amount'],$post['pmt_order']));

        $iorder['is_delivery']  = 'Y';
        $iorder['source']  = 'local';//订单来源标识，local为本地新建订单
        $iorder['createway'] = 'local';
        //新建订单时，要开票的
        if($post['is_tax'] == 'true'){
            $iorder['is_tax'] = $post['is_tax'];
            $iorder['tax_title'] = $post['tax_title'];
            $iorder['invoice_kind'] = $post['invoice_kind'];
        }else{
            //选择不要开票的
            //前端店铺下tab发票配置页 前端店铺下单全局发票设置 是否一定要生成发票记录
            $taxconf = app::get('ome')->getConf('shop.tax.config.'.$iorder['shop_id']);
            if($taxconf['config'] == '1'){
                //强制开票开关 开启
                $iorder['is_tax'] = "true";
                $iorder['tax_title'] = $taxconf['title'];
                $iorder['invoice_kind'] = $post['invoice_kind'];
            }
        }

        if ($iorder['total_amount'] < 0)
            $this->end(false, '订单金额不能小于0');

        $iorder['platform_order_bn'] = $iorder['order_bn'] = $oObj->gen_id();


        // 如果有关联订单号，需要使用关联订单号的平台单号
        if ($iorder['relate_order_bn'] && $post['order']['order_type'] == 'bufa') {
            $relateOrder = $oObj->dump(['order_bn' => $iorder['relate_order_bn']], 'platform_order_bn');
            if ($relateOrder) {
                $iorder['platform_order_bn'] = $relateOrder['platform_order_bn'];
            }
        }

        //设置订单失败时间
        $iorder['order_limit_time'] = time() + 60*(app::get('ome')->getConf('ome.order.failtime'));

        $oObj->create_order($iorder);

        if(in_array($iorder['order_type'], array('presale','bufa')) && $_POST['presale_payed']) {
            if($_POST['presale_payed'] > $iorder['total_amount']) {
                $this->end(false, '已付金额不能大于订单金额');
            }
            $op_info = kernel::single('ome_func')->getDesktopUser();

            $pay_time = time();

            $paymentObj = app::get('ome')->model('payments');
            $sdf = array(
                'payment_bn' => $paymentObj->gen_id(),
                'shop_id' => $iorder['shop_id'],
                'order_id' => $iorder['order_id'],
                'currency' => 'CNY',
                'money' => $_POST['presale_payed'],
                'paycost' => '0',
                'cur_money' => '0',
                'pay_type' => 'online',
                't_begin' => $pay_time,
                'download_time' => $pay_time,
                't_end' => $pay_time,
                'status' => 'succ',
                'memo'=>'预售订单自动生成支付单',
                'op_id'=> $op_info['op_id'],
            );
            $paymentObj->create_payments($sdf);
        }
        //货到付款类型订单，增加应收金额
        if($is_code == 'true'){
            $oObj_orextend = $this->app->model("order_extend");
            $code_data = array('order_id'=>$iorder['order_id'],'receivable'=>$iorder['total_amount'],'sellermemberid'=>$iorder['member_id']);
            $oObj_orextend->save($code_data);

        }

        //新增收货地址
        $address_data = array(
            'member_id'     =>  $iorder['member_id'],
            'ship_name'     =>  $iorder['consignee']['name'],
            'ship_area'     =>  $iorder['consignee']['area'],
            'ship_addr'     =>  $iorder['consignee']['addr'],
            'ship_mobile'   =>  $iorder['consignee']['mobile'],
            'ship_tel'      =>  $iorder['consignee']['telephone'],
            'ship_zip'      =>  $iorder['consignee']['zip'],
            'ship_email'    =>  $iorder['consignee']['email'],
        );
        app::get('ome')->model('member_address')->create_address($address_data);

        //补发订单复制原订单收件人敏感数据
        if(in_array($iorder['order_type'], array('bufa'))){
            $error_msg = '';
            $bufaResult = $lib_ome_order->createBufaOrderEncrypt($iorder, $error_msg);
            if(!$bufaResult && $error_msg){
                //logs
                $log_msg = '复制原平台订单收件人敏感数据失败：'. $error_msg;
                $logObj->write_log('order_modify@ome', $iorder['order_id'], $log_msg);
            }
        }
        
        //福袋日志记录
        if($lucky_falg){
            $luckyBagLib = kernel::single('ome_order_luckybag');
            $luckyBagLib->saveLuckyBagUseLogs($iorder);
        }
        
        //[SAP创建]调用service进行SAP创建
        foreach(kernel::servicelist('ome.service.order.create.after') as $object)
        {
            if(method_exists($object, 'after_create')){
                $object->after_create($iorder);
            }
        }
        
        $this->end(true, '创建成功','index.php?app=ome&ctl=admin_order&act=createOrderResult&order_bn='.$iorder['order_bn']);
    }

    /**
     * 创建OrderResult
     * @return mixed 返回值
     */
    public function createOrderResult()
    {
        $this->pagedata['order_bn'] = $_GET['order_bn'];
        $this->display('admin/order/create_order_result.html');
    }
    function getMemberAddress($mem_id=0){
        if (!$mem_id){
            $mem_id = $_REQUEST['member_id'];
        }

        $helperLib = kernel::single('ome_view_helper2');
        $addressObj = $this->app->model("member_address");
        $list = $addressObj->getList('*',array('member_id'=>$mem_id),0,10, 'address_id desc');
        if ($list){
            $address = array();
            foreach ($list as $v){

                $string = array(
                    'area'      =>  $v['ship_area'],
                    'addr'      =>  $v['ship_addr'],
                    'name'      =>  $v['ship_name'],
                    'zip'       =>  $v['ship_zip'],
                    'mobile'    =>  $v['ship_mobile'],
                    'telephone' =>  $v['ship_tel'],
                    'email'     =>  $v['ship_email'],
                );

                //简写密文收货信息
                if (kernel::single('ome_security_router', 'taobao')->is_encrypt(array('ship_name'=>$v['ship_name']), 'order')) {
                    //格式化密文
                    $string['simple_name'] = $helperLib->modifier_ciphertext($v['ship_name'], 'order', 'ship_name');
                    $string['simple_mobile'] = $helperLib->modifier_ciphertext($v['ship_mobile'], 'order', 'ship_mobile');
                    $string['simple_telephone'] = $helperLib->modifier_ciphertext($v['ship_tel'], 'order', 'ship_tel');
                    $string['simple_address'] = $helperLib->modifier_ciphertext($v['ship_addr'], 'order', 'ship_addr');
                }else{
                    //复制原收货信息
                    $string['simple_name'] = $v['ship_name'];
                    $string['simple_mobile'] = $v['ship_mobile'];
                    $string['simple_telephone'] = $v['ship_tel'];
                    $string['simple_address'] = $v['ship_addr'];
                }

                //area
                list(, $regionInfo, $region_id) = explode(':', $v['ship_area']);
                $string['simple_region'] = str_replace('/', '-', $regionInfo);

                $md5 = md5(serialize($string));
                $tmp = explode(':',$string['area']);
                $string['id'] = $tmp[2];
                $address[$md5] = array_map('trim', $string);
                if(count($address)>=10){
                    break;
                }
            }
            sort($address);

            echo json_encode($address);
        }
    }


    function getMembers()
    {
        $helperLib = kernel::single('ome_view_helper2');

        $filter = array();

        if ($_POST['mobile']) $filter['mobile'] = trim($_POST['mobile']);
        if ($_POST['uname']) $filter['uname|head'] = trim($_POST['uname']);
        if ($_POST['uname_right']) $filter['uname'] = trim($_POST['uname_right']);
        if ($_POST['member_id']) $filter['member_id'] = trim($_POST['member_id']);

        if ($filter) {
            $data = $this->app->model('members')->getList('member_id,uname,area,mobile,email,sex',$filter);

            foreach ((array) $data as $k => $v){
                $data[$k]['sex'] = $v['sex']=='male' ? '男' : '女';

                if ($_POST['uname'] && 0 !== strpos(strtolower($v['uname']),strtolower($_POST['uname']) )) {
                    unset($data[$k]);
                }

                //简写密文会员信息
                if (kernel::single('ome_security_router', 'taobao')->is_encrypt(array('ship_mobile'=>$v['mobile']), 'order')) {
                    //格式化密文
                    $data[$k]['simple_uname'] = $helperLib->modifier_ciphertext($v['uname'], 'order', 'ship_name');
                    $data[$k]['simple_mobile'] = $helperLib->modifier_ciphertext($v['mobile'], 'order', 'ship_mobile');
                }else{
                    $data[$k]['simple_uname'] = $v['uname'];
                    $data[$k]['simple_mobile'] = $v['mobile'];
                }
            }

            ksort($data);
        }

        if ($data){
            echo "window.autocompleter_json=".json_encode(array_values($data));exit;
        }
        echo "";
    }


    function getMembers_old(){
        $mbObj = $this->app->model('members');
        $shop_ids = explode('*',$_POST['shop_id']);
        $shop_id = $shop_ids[0];
        $shop_type = $shop_ids[1];
        $shopex_shop_type = ome_shop_type::shopex_shop_type();
        $filter = array();
        if(in_array($shop_type,$shopex_shop_type)){
                $filter['shop_id'] = $shop_id;
        }
        if($_POST['mobile']){
            $filter['mobile'] = $_POST['mobile'];

            $data = $mbObj->get_member($filter,'mobile');
        }elseif ($_POST['uname']){
            $filter['uname'] = $_POST['uname'];
            $data = $mbObj->get_member($filter,'uname');
        }elseif ($_POST['member_id']){
            $filter['member_id'] = $_POST['member_id'];

            $data = $mbObj->getList('member_id,uname,area,mobile,email,sex',$filter,0,-1);
        }

        if ($data)
        foreach ($data as $k => $v){
            $data[$k]['sex'] = $v['sex']=='male' ? '男' : '女';
            $data[$k] = array_map('trim', $v);
        }

        if ($data){
            echo "window.autocompleter_json=".json_encode($data);
            exit;
        }
        echo "";
    }

    function getCorpArea(){
        $region = $_POST['region'];
        $dcaObj = $this->app->model('dly_corp_area');
        $dcObj = $this->app->model('dly_corp');
        if ($region){
            $tmp = explode(':',$region);
            $region_id = $tmp[2];
            $data = $dcaObj->getCorpByRegionId($region_id);
            if (!$data)
                $data = $dcObj->getList('corp_id,name', '', 0, -1);
            echo json_encode($data);
        }else {
            $data = $dcObj->getList('corp_id,name','',0,-1);
            echo json_encode($data);
        }
    }

    function addNewAddress(){
        if (isset($_GET['area'])){
            $this->pagedata['region'] = $_GET['area'];
        }
        $this->display("admin/order/add_new_address.html");
    }

    function getConsingee(){
        $string = $_POST['consignee'];
        if ($string['area']){
            $region = explode(':', $string['area']);
            if (!$region[2]){
                return false;
            }
        }else {
            return false;
        }
        $string['id'] = $region[2];
        echo json_encode($string);
    }

    function getCorps()
    {
        $orderObj    = app::get('ome')->model('orders');

        $branch_id = $_POST['branch_id'];
        $area = $_POST['area'];
        $weight = $_POST['weight'];
        $shop_type = $_POST['shop_type'];
        $shop_id = $_POST['shop_id'];

        //同城配送&&商家配送
        $is_instatnt = false; //同城配送
        $is_seller = false; //商家配送
        $outCityCorps = array();
        $OfflineCorps = array();
        $shipping_type = '';

        $jyInfo = []; // 集运

        //订单信息
        $order_id = $_POST['order_id'];//全渠道订单加载o2o门店物流公司
        if($order_id){
            $orderRow = $orderObj->dump(array('order_id'=>$order_id), '*');

            //[兼容]部分拆分or部分发货的全渠道订单不显示门店物流公司
            if($orderRow['process_status'] == 'splitting' || $orderRow['ship_status'] == '2'){
                $orderRow["omnichannel"] = '2';
            }

            //[小时达]小时达订单允许查询
            $billLabelObj = kernel::single('ome_bill_label');
            $xiaoshiInfo = $billLabelObj->isXiaoshiDa($orderRow['order_id']);

            /*
            // 在提交(finish_combine)的时候判断
            if ($shop_type == '360buy') {
                // 京东集运，必须使用京东无界电子面单，否则回写会失败
                $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', 'SOMS_GNJY');
            } elseif ($shop_type == 'luban') {
                // 抖音中转订单的物流单号来源只能用抖音
                $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($orderRow['order_id'], 'order', 'XJJY');
            }
            */
        }

        //全渠道订单获取配置主店铺shop_id
        if($orderRow["omnichannel"] == "1" && app::get('tbo2o')->is_installed() ){
            $tbo2o_shop = kernel::single('tbo2o_common')->getTbo2oShopInfo();
            $tbo2o_shop_id = $tbo2o_shop["shop_id"];
        }

        //电子面单来源类型
        $channelObj = app::get("logisticsmanager")->model('channel');
        $rows = $channelObj->getList("channel_id,channel_type",array('status'=>'true'));
        $channelType = array();
        foreach($rows as $val) {
            $channelType[$val['channel_id']] = $val['channel_type'];
            unset($val);
        }

        unset($rows);

        $oBranch =app::get('ome')->model("branch");
        $exrecommend_available = kernel::single('channel_func')->check_exrecommend_available();//判断仓库可用
        $channel_type = $oBranch->getChannelBybranchID($branch_id);
        // 是否门店仓（b_type = 2）
        $branchRow = $oBranch->dump(array('branch_id' => $branch_id), 'b_type');
        $isStoreBranch = ($branchRow && $branchRow['b_type'] == '2');
        // 是否参与O2O门店
        $isO2OStore = false;
        if ($isStoreBranch && app::get('o2o')->is_installed()) {
            $o2oStoreObj = app::get('o2o')->model('store');
            $o2oStore = $o2oStoreObj->getList('store_id', array('branch_id' => $branch_id, 'is_o2o' => '1'), 0, 1);
            $isO2OStore = !empty($o2oStore);
        }
        
        if($exrecommend_available && in_array($channel_type,array('selfwms'))){
            $_order_ids = json_decode($_POST['order_info'],true);
            if(!empty($_order_ids)){
                $order_data['main_order_bn'] = $_POST['order_bn'];//主单
                $order_data['main_ship_area'] = $_POST['area'];//主单收货地址
                $order_data['combine_order_ids'] = $_order_ids;//本次合单情况的所有订单

                //使用物流推荐(超时时间设置为2秒)
                $waybill_number = '';
                $rows = $oBranch->get_exrecommend($branch_id,$area,$weight,$shop_type,$shop_id, $order_data,$waybill_number);
            }else{
                $rows = false;
            }
        }

        //如果智选物流功能不可用，则使用ERP原有的智选；如果开启了智选物流，但是，又没有获取到智选物流数据（可能是超时原因没获取到），仍然继续使用ERP原有的智选
        if(empty($exrecommend_available) || ($exrecommend_available && empty($rows))){
            $waybill_number = '';
            $rows = $oBranch->get_corpbyarea($branch_id,$area,$weight,$shop_type,$shop_id,$order_id,$waybill_number);
        }

        //翱象订单
        $isAoxiang = false;
        if(in_array($orderRow['shop_type'], array('taobao', 'tmall')) && $orderRow['order_bool_type']){
            //是否翱象订单
            $orderTypeLib = kernel::single('ome_order_bool_type');
            $isAoxiang = $orderTypeLib->isAoxiang($orderRow['order_bool_type']);
        }

        //翱象建议的物流公司
        $biz_delivery_codes = array();
        $black_delivery_cps = array();
        if($isAoxiang){
            $axOrderLib = kernel::single('dchain_order');
            $error_msg = '';
            $aoxLogiList = $axOrderLib->getRecommendLogis($orderRow['order_id'], $error_msg);

            //建议的物流公司
            if($aoxLogiList['biz_delivery_codes']){
                $biz_delivery_codes = $aoxLogiList['biz_delivery_codes'];
            }

            //黑名单的物流公司
            if($aoxLogiList['black_delivery_cps']){
                $black_delivery_cps = $aoxLogiList['black_delivery_cps'];
            }
        }

        //获取店铺信息
        $shopObj = app::get("ome")->model('shop');
        $shopInfo = $shopObj->dump(array('shop_id' => $shop_id), 'shop_type,addon');

        //过滤掉不适用此店铺的快递公司
        $corpList = array();
        
        // 小时达平台运力时，只保留指定的物流公司
        $xiaoshiPlatformShipping = null;
        if($order_id && $xiaoshiInfo['is_xiaoshi_da'] && $xiaoshiInfo['is_platform_delivery'] && $orderRow['shipping']){
            $xiaoshiPlatformShipping = $orderRow['shipping'];
        }
        
        foreach($rows as $k=>$v)
        {
            // 小时达平台运力时，只保留指定的物流公司
            if($xiaoshiPlatformShipping && $v['type'] != $xiaoshiPlatformShipping){
                continue;
            }
            
            if($v['tmpl_type']=='electron' && $channelType[$v['channel_id']]=='wlb' && $v['shop_id']!=$shop_id) {
                continue;
            }

            if($shop_type == 'paipai' && !empty($v['shop_id']) && $v['shop_id']!=$shop_id) {
                continue;
            }

            //不是全渠道订单隐藏o2o门店物流公司
            if(($orderRow['omnichannel'] != '1') && ($v['d_type'] == 2))
            {
                continue;
            }

            //是全渠道订单 订单的前端店铺是全渠道配置的主店铺 过滤掉门店自提 阿里不认
            if($orderRow["omnichannel"] == "1" && $orderRow["shop_id"] == $tbo2o_shop_id && $v["type"] == "o2o_pickup"){
                continue;
            }

            //同城配送(只有淘宝、天猫店铺支持)
            if($v['corp_model'] == 'instatnt'){
                $outCityCorps[] = $v;

                continue;
            }

            //商家配送(线下配送)
            if($v['corp_model'] == 'seller'){
                // 仅当门店仓且参与O2O时，保留在可选列表；否则归于线下配送，不参与本次选择
                if(!($isStoreBranch && $isO2OStore)){
                    $OfflineCorps[] = $v;
                    continue;
                }
            }

            /*
            // 在提交(finish_combine)的时候判断
            // 京东集运订单必须使用京东无界发货，否则发货回写会失败
            if ($shop_type == '360buy' && $jyInfo && !in_array($channelType[$v['channel_id']], ['360buy','jdalpha'])  ) {
                continue;
            }

            // 抖音中转订单的物流单号来源只能用抖音
            if ($shop_type == 'luban' && $jyInfo && !in_array($channelType[$v['channel_id']], ['douyin'])  ) {
                continue;
            }
            */

            //建议文字描述
            if(in_array($v['type'], $biz_delivery_codes)){
                $v['name'] = $v['name'] . '（推荐）';
            }elseif(in_array($v['type'], $black_delivery_cps)){
                $v['name'] = $v['name'] . '（异常）';
            }

            $corpList[] = $v;
        }

        //同城配送
        if($is_instatnt){
            if($outCityCorps){
                $corpList = array_merge($outCityCorps, $corpList);
            }
        }

        //商家配送
        if($is_seller){
            if($OfflineCorps){
                $corpList = array_merge($OfflineCorps, $corpList);
            }
        }

        if($waybill_number){
            $data['corpList'] = $corpList;
            $data['waybill_number'] = $waybill_number;
            echo json_encode( $data);
        }else{
            echo json_encode( $corpList);
        }
    }

   function do_confirm_delivery_info_edit($order_id){
       if($order_id){
           $oOrder = $this->app->model("orders");
           $order = $oOrder->dump($order_id);
           $this->pagedata['order'] = $order;
           $this->display('admin/order/confirm/delivery_info_edit.html');
       }
   }

   function do_confirm_delivery_info($order_id){
       if($order_id){
           $oOrder = $this->app->model("orders");
           $order = $oOrder->dump($order_id);
           $order['mark_text'] = unserialize($order['mark_text']);
           $order['custom_mark'] = unserialize($order['custom_mark']);
           $this->pagedata['order'] = $order;
           $this->display('admin/order/confirm/delivery_info.html');
       }
   }

   function cancelOrder(){

   }

    //订单快照
    function show_operation(){

        $log_id  = $_GET['log_id'];
        $order_id = $_GET['order_id'];
        $ooObj = $this->app->model('orders');

        $operation_history = $ooObj->read_log_detail($order_id,$log_id);
        $region_detail = $operation_history['order_detail'];
        //兼容 上个版本的 订单快照
        if(isset($region_detail['item_list'])){

            $this->pagedata['operation_history'] = $operation_history;
            $this->pagedata['operation_detail'] = $region_detail;
            if(!preg_match("/^mainland:/", $region_detail['log_area'])){
                $region='';
                $newregion='';
                foreach(explode("/",$region_detail['log_area']) as $k=>$v){
                    $region.=$v.' ';
                }
            }else{
                $newregion = $region_detail['log_area'];
            }
            $this->pagedata['region'] = $region;
            $this->pagedata['newregion'] = $newregion;
            $this->singlepage('admin/order/operations_order_old.html');
        }else{

            $this->pagedata['operation_detail'] = $region_detail;
            $this->singlepage('admin/order/operations_order.html');
        }
    }
   /**
    * 获取订单编辑方式
    * @access public
    * @param number $order_id 订单ID
    * @return json
    */
   function update_type($order_id){
       $return = array('rsp'=>'fail','msg'=>'','data'=>'');
       $rs = kernel::single('ome_order')->update_iframe($order_id,$is_request=false);

       $return['rsp'] = $rs['rsp'];
       $return['msg'] = $rs['msg'];
       if (!is_array($rs['data'])){
           $rs['data'] = ['edit_type' => 'local'];
       }
       if (!isset($rs['data']['edit_type'])){
           $rs['data']['edit_type'] = 'local';
       }
       $return['data'] = $rs['data'];
       echo json_encode($return);
       exit;
   }


   /**
    * 订单编辑页面
    * @access public
    * @param number $order_id 订单ID
    * @return json
    */
   function update_iframe($order_id){
        $oOrder = $this->app->model('orders');
        $order = $oOrder->getRow($order_id);
        $rs = array('rsp'=>'success','msg'=>'');

        if ($order['pause'] == 'false'){
           $rs['rsp'] = 'fail';
           $rs['msg'] = '请先暂停订单';
        }

        if($order['process_status'] == 'splited'){
            //打回已存在的发货单
            $result    = $oOrder->rebackDeliveryByOrderId($order_id, false, '');
            if($result){
                $new_order['order_id']      = $order_id;
                $new_order['old_amount']     = $order['total_amount'];
                $new_order['confirm']        = 'N';
                $new_order['process_status'] = 'unconfirmed';
                $new_order['pause']          = 'false';
                //更新order
                $oOrder->save($new_order);
            }else {
                $rs['rsp'] = 'fail';
                $rs['msg'] = '发货单撤销失败，不能编辑';
            }
        }

         //增加不能编辑状态的判断
        $result = $oOrder->not_allow_edit($order_id);
        if($result['res'] == 'false'){
            $rs['rsp'] = 'fail';
            $rs['msg'] = $result['msg'];
        }

        //存在未处理的退款申请
        $refund_applyObj = app::get('ome')->model('refund_apply');
        $refund_apply_filter = array('order_id'=>$order_id,'status'=>array('0','1','2','6'));
        $refund_apply_detail = $refund_applyObj->dump($refund_apply_filter, 'apply_id');
        if ($refund_apply_detail){
            $rs['rsp'] = 'fail';
            $rs['msg'] = '退款申请中的订单不允许编辑,请先处理退款申请!';
        }

        if ($rs['rsp'] == 'success'){
            $sh_base_url = kernel::base_url(1);
            $finder_id = $_GET['finder_id'];
            $rs['url'] = $sh_base_url.'/index.php?app=ome&ctl=admin_order&act=update_iframe_api&p[0]='.$order_id.'&p[1]='.$finder_id;
        }

        $this->pagedata['rs'] = $rs;
        $this->pagedata['order_id'] = $order_id;
        $this->singlepage('admin/order/update_iframe.html');
   }


   /**
    * 订单编辑接口
    * @access public
    * @param number $order_id 订单ID
    * @param String $finder_id FINDER_id
    * @return json
    */
   function update_iframe_api($order_id,$finder_id=''){
       $sh_base_url = kernel::base_url(1);
       $notify_url = $sh_base_url.'/index.php?app=ome&ctl=admin_order&act=update_iframe&p[0]='.$order_id.'&finder_id='.$finder_id;
       $ext['notify_url'] = $notify_url;
       $rs = kernel::single('ome_order')->update_iframe($order_id,$queue=true,$ext);
       return $rs;
   }


   /**
    * 更新订单同步状态
    * @access public
    * @param number $order_id 订单ID
    * @param String $sync_status 编辑同步状态
    * @return json
    */
   function set_sync_status($order_id,$sync_status=''){
       $rs = array('rsp'=>'fail','msg'=>'');
       if (in_array($sync_status,array('fail','success'))){
           $orderSync = kernel::single('ome_order');
           if ($orderSync->set_sync_status($order_id,$sync_status)){
               $rs['rsp'] = 'success';
           }
       }
       die(json_encode($rs));
   }

   /**
    * 关闭订单编辑页面后所做操作
    * @access public
    * @param number $order_id 订单ID
    * @param String $is_operator 是否记录操作日志
    * @return bool
    */
   function update_iframe_after($order_id,$is_operator='1'){
       $rs = array('rsp'=>'fail','msg'=>'');
       if (empty($order_id)) die(json_encode($rs));

       //更新订单暂停状态为恢复
       $oOrder = $this->app->model('orders');
       $oOrder->renewOrder($order_id);

       if ($is_operator == '1'){
           //记录操作日志
           $oOperation_log = $this->app->model('operation_log');
           $oOperation_log->write_log('order_edit@ome',$order_id,"订单编辑");
       }

       $rs['rsp'] = 'success';
       die(json_encode($rs));
    }

    /**
     * 获取店铺订单信息
     * 
     * @return void
     * */
    function getShopOrder(){
       $Oshop = $this->app->model('shop');
       $shop = $Oshop->getList('name,shop_id,node_type,node_id,business_type as order_type');

       $shops = array();
       $config = ome_shop_type::get_shoporder_config();
       $allshops = array_keys($config);
       $default_shop = '';
       foreach ($shop as $k=>$v) {
            if(!empty($v['node_id']) && in_array($v['node_type'],$allshops) && ($config[$v['node_type']]=='on')){
                $shops[$v['shop_id']] = array('name'=>$v['name'],'node_type'=>$v['node_type'],'shop_id'=>$v['shop_id']);
                $shops[$v['shop_id']]['order_type'] = ($v['order_type'] == 'zx') ? 'direct' : 'agent';

            }
       }

       $this->pagedata['shops'] = $shops;

       $this->display('admin/order/getshoporder.html');
    }

    function fetchCombineDelivery(){
        $order_id = intval($_POST['order_id']);
        $branch_id = intval($_POST['branch_id']);
        $logi_id = intval($_POST['logi_id']);
        $orderIds = ($_POST['order_ids'] ? explode(',', $_POST['order_ids']) : array());
        $orderIds = array_filter($orderIds); //合单多个订单ID

        //check
        if(empty($order_id)){
            exit();
        }

        //订单信息
        $orderObj = app::get('ome')->model('orders');
        $orderInfo = $orderObj->dump(array('order_id'=>$order_id), '*');
        if(empty($orderInfo)){
            exit();
        }

        //翱象订单
        $isAoxiang = false;
        if(in_array($orderInfo['shop_type'], array('taobao', 'tmall')) && $orderInfo['order_bool_type']){
            //是否翱象订单
            $orderTypeLib = kernel::single('ome_order_bool_type');
            $isAoxiang = $orderTypeLib->isAoxiang($orderInfo['order_bool_type']);
        }

        //[翱象订单]检查审单条件
        if($isAoxiang){
            $branchObj = app::get('ome')->model('branch');
            $corpObj = app::get('ome')->model('dly_corp');

            $axOrderLib = kernel::single('dchain_order');

            //branch
            $branchInfo = $branchObj->db->selectrow("SELECT * FROM sdb_ome_branch WHERE branch_id=". $branch_id);

            //检查仓库和物流公司属性是否一致
            $corpInfo = $corpObj->dump(array('corp_id'=>$logi_id), '*');

            //审单规则
            $axResult = $axOrderLib->combineOrder($orderInfo, $branchInfo, $corpInfo, $orderIds);
            if($axResult['rsp'] == 'fail' && $axResult['error_msg']){
                $result = array('rsp'=>'fail', 'check_type'=>'aox', 'error_msg'=>$axResult['error_msg']);

                echo json_encode($result);
                exit();
            }
        }

        //合单
        $combine_delivery = $this->app->model('delivery')->fetchCombineDelivery($order_id);

        if($combine_delivery){
            echo json_encode($combine_delivery);
        }
    }

    function combineOrderNotify(){
        $order_id = intval($_GET['order_id']);
        $combine_delivery = $this->app->model('delivery')->fetchCombineDelivery($order_id);

        $this->pagedata['combine_delivery'] = $combine_delivery;
        $this->page('admin/order/order_combinenotify.html');
    }

    /**
     * 暂停订单
     * @
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    function pause_order($order_id = 0)
    {
        $oOrders = app::get('ome')->model('orders');
        if ($_POST) {

            $order_id = $_POST['order_id'];
            $rs = $oOrders->pauseOrder($order_id, false, '');
            $finder_id = $_POST['finder_id'];
            if ($rs['rsp'] == 'fail') {

              //[拆单]暂停发货单失败_提醒消息
              if($rs['is_split'] == 'true')
              {
                  $this->pagedata['order_id'] = $order_id;
                  $this->pagedata['message'] = $rs['msg'];
                  $this->display('admin/order/order_pause_showmsg.html');
                  exit;
              }
              else
              {
                echo "<script>alert('订单暂停失败,原因是:".$rs['msg']."');</script>";
              }
            }else{
                echo "<script>alert('订单暂停成功');</script>";
            }
            echo "<script>$$('.dialog').getLast().retrieve('instance').close();window.finderGroup[$(document.body).getElement('input[name^=_finder\[finder_id\]]').value].refresh();</script>";
        }
        $this->pagedata['order_id'] = $order_id;

        $orders = $oOrders->dump($order_id,'process_status');
        $this->pagedata['orders'] = $orders;
        unset($orders);
        unset($order_id);
        $this->display('admin/order/order_pausenotify.html');
    }

    /**
     * downloadPrintSite
     * @return mixed 返回值
     */
    public function downloadPrintSite() {
        $product_type = isset($_GET['product_type']) ? trim($_GET['product_type']) : 'tp';
        $url = 'http://update.tg.taoex.com/tg.php';
        $http = kernel::single('base_httpclient');
        $secrect = '67C70BDFAF354401D9D2192377D09DC0';
        $params = array(
            'app_key' => 'taoguan',
            'product_type' => $product_type,
            'timestamp' => time(),
            'format' => 'json'
        );
        $sign = strtoupper(md5($this->assemble($params).$secrect));
        $params['sign'] = $sign;
        $result = $http->post($url, $params);
        echo $result;exit;
    }


    /**
     * errorReportPrintSite
     * @return mixed 返回值
     */
    public function errorReportPrintSite() {
        $product_type = isset($_GET['product_type']) ? trim($_GET['product_type']) : 'tp';
        $base_host = kernel::single('base_request')->get_host();
        $url = 'http://update.tg.taoex.com/error_report.php';
        $http = kernel::single('base_httpclient');
        $secrect = '67C70BDFAF354401D9D2192377D09DC0';
        $params = array(
            'app_key' => 'taoguan',
            'product_type' => $product_type,
            'timestamp' => time(),
            'format' => 'json',
            'errmsg' => $_POST['errmsg'],
            'domain' => $base_host,
        );
        $sign = strtoupper(md5($this->assemble($params).$secrect));
        $params['sign'] = $sign;
        $result = $http->post($url, $params);

        echo $result;exit;
    }

    /**
     * 下载控件
     */
    public function diagLoadPrintSite() {
        $this->page('admin/delivery/controllertmpl/diag_load_print_site.html');
    }


    protected function assemble($params) {
        if(!is_array($params)) {
            return null;
        }
        ksort($params, SORT_STRING);
        $sign = '';
        foreach($params as $pk => $pv) {
            if (is_null($pv)) {
                continue;
            }
            if (is_bool($pv)) {
                $pv = ($pv) ? 1 : 0;
            }
            $sign .= $pk . (is_array($pv) ? $this->assemble($pv) : $pv);
        }
        return $sign;
    }

    /*------------------------------------------------------ */
    //-- 我的异常订单
    /*------------------------------------------------------ */
    function retrial()
    {
        $op_id = kernel::single('desktop_user')->get_id();
        $this->title = '我的异常订单';
        //$this->base_filter['op_id'] = $op_id;
        $this->base_filter['abnormal'] = 'true';
        $this->base_filter['process_status'] = 'is_retrial';

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $this->base_filter['org_id'] = $organization_permissions;
        }

        //超级管理员
        if(kernel::single('desktop_user')->is_super())
        {
            if(isset($this->base_filter['op_id']))
            unset($this->base_filter['op_id']);

            if(isset($this->base_filter['group_id']))
            unset($this->base_filter['group_id']);
        }

        $params     = array(
            'title' => $this->title,
            'base_filter' => $this->base_filter,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            // 'orderBy' => 'createtime desc',
            'finder_aliasname' => 'order_retrial'.$op_id,
            'finder_cols' => 'column_abnormal_status, column_mark_text, mark_text, abnormal_status, process_status, column_confirm,order_bn,shop_id,member_id,total_amount,is_cod,pay_status,ship_status,createtime,paytime',
        );

        $this->finder('ome_mdl_orders', $params);
    }
    /*------------------------------------------------------ */
    //-- 回滚"我的异常订单"
    /*------------------------------------------------------ */
    function retrial_rollback($order_id)
    {
        header("cache-control:no-store,no-cache,must-revalidate");

        //复审订单详情
        $oRetrial  = app::get('ome')->model('order_retrial');
        $row       = $oRetrial->getList('*', array('order_id'=>$order_id), 0, 1, 'dateline DESC');
        $row       = $row[0];
        $this->pagedata['row']      = $row;

        //订单和订单快照信息&&价格监控
        $datalist   = $oRetrial->contrast_order($order_id, $row['id']);

        $this->pagedata['order_profit']         = $datalist['order_profit'];
        $this->pagedata['old_price_monitor']    = $datalist['old_price_monitor'];
        $this->pagedata['new_price_monitor']    = $datalist['new_price_monitor'];
        $this->pagedata['monitor_flag']         = $datalist['monitor_flag'];
        $this->pagedata['setting_is_monitor']   = $datalist['setting_is_monitor'];

        $this->pagedata['order_old']    = $datalist['order_old'];
        $this->pagedata['order_new']    = $datalist['order_new'];

        //回滚订单模板标识
        $this->pagedata['rollback']    = true;

        $this->singlepage('admin/order/retrial_normal.html');
    }
    /*------------------------------------------------------ */
    //-- 获取订单复审配置及复审规则
    /*------------------------------------------------------ */
    function get_setting_retrial()
    {
        $is_retrial        = app::get('ome')->getConf('ome.order.is_retrial');
        $setting_retrial   = app::get('ome')->getConf('ome.order.retrial');

        if($setting_retrial['product'] != '1' && $setting_retrial['order'] != '1' && $setting_retrial['delivery'] != '1')
        {
            $is_retrial     = 'false';
        }
        $setting_retrial['is_retrial']  = $is_retrial;

        if($is_retrial == 'false')
        {
            unset($setting_retrial);
        }

        return $setting_retrial;
    }

    //批量设置备注
    function BatchUpMemo(){
        $this->_request = kernel::single('base_component_request');
        $order_info = $this->_request->get_post();
        //不支持全部备注
        if($order_info['isSelectedAll'] == '_ALL_'){
            echo '暂不支持全部备注!';exit;
        }
        if(empty($order_info['order_id'])){
            echo '请选择订单!';exit;
        }
        //统计批量支付订单数量
        $this->pagedata['order_id'] = serialize($order_info['order_id']);
        $this->display('admin/order/batch_update_memo.html');
    }
    //批量设置备注
    function doBatchUpMemo(){
        $this->begin("index.php?app=ome&ctl=admin_order&act=index");
        $all_order_id = $_POST['order_id'];
        if(!empty($all_order_id)){
            $arr_order_id = unserialize($all_order_id);
        }
        if(empty($all_order_id)){
            $this->end(false,'提交数据有误!');
        }
        $oOrders = $this->app->model('orders');
        $oOperation_log = app::get('ome')->model('operation_log');
        foreach($arr_order_id as $key=>$order_id){
            $plainData = $memo = array();

            $order_info = $oOrders->dump(array('order_id'=>$order_id), 'mark_text,mark_type');
            $oldmem = unserialize($order_info['mark_text']);
            $op_name = kernel::single('desktop_user')->get_name();
            if ($oldmem){
                foreach($oldmem as $k=>$v){
                    $memo[] = $v;
                }
            }
            $newmemo =  htmlspecialchars($_POST['mark_text']);
            $newmemo = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i:s',time()), 'op_content'=>$newmemo);
            $memo[] = $newmemo;

            $plainData['order_id'] = $order_id;
            $plainData['mark_text'] = serialize($memo);
            $plainData['mark_type'] = $order_info['mark_type'];
            $oOrders->save($plainData);

            //写操作日志
            $memo = "批量修改订单备注";
            //订单留言 API
             foreach(kernel::servicelist('service.order') as $object=>$instance){
                if(method_exists($instance, 'update_memo')){
                    $instance->update_memo($order_id, $newmemo);
                }
            }
            $oOperation_log->write_log('order_modify@ome',$order_id,$memo);
        }
        $this->end(true, app::get('base')->_('修改成功'));
     }

    /**
     * [拆单]计算物流费
     * 
     * @return void
     * @author chenping
     * */
    public function calFreightCost($shipping_area,$logi_id,$weight)
    {
        if (false !== strpos($shipping_area,'mainland')) list($area_prefix,$area_chs,$area_id) = explode(':',$shipping_area);

        $cost_freight = app::get('ome')->model('delivery')->getDeliveryFreight($area_id,$logi_id,$weight);

        echo $cost_freight;exit;
    }
    //重新强制获取CRM赠品数据
    public function doRequestCRM(){
        $orders = $_POST['order_id'];
        if(empty($order_id)){
            if($_POST['isSelectedAll'] == '_ALL_'){
                $base_filter['op_id'] = kernel::single('desktop_user')->get_id();
                $base_filter['assigned'] = 'assigned';
                $base_filter['abnormal'] = "false";
                $base_filter['is_fail'] = 'false';
                $base_filter['status'] = 'active';
                $base_filter['process_status'] = array('unconfirmed', 'confirmed', 'splitting');
                $base_filter['archive'] = 0;
                $base_filter['pause'] = 'false';
                $base_filter['order_confirm_filter'] = '(sdb_ome_orders.auto_status & '.omeauto_auto_const::__CRMGIFT_CODE.'='.omeauto_auto_const::__CRMGIFT_CODE.')';
                //超级管理员
                if(kernel::single('desktop_user')->is_super()){
                    if(isset($base_filter['op_id']))
                        unset($base_filter['op_id']);

                    if(isset($base_filter['group_id']))
                        unset($base_filter['group_id']);
                }
                $_order_id = $this->app->model('orders')->getList('order_id',$base_filter);
                foreach($_order_id as $v){
                    $orders[] = $v['order_id'];
                }
            }
        }
       $obj_crm = kernel::single('ome_preprocess_crm');
        $this->begin("index.php?app=ome&ctl=admin_order&act=confirm&flt=unmyown");
        if(empty($orders)){
            $this->end(false, app::get('base')->_('请选择单据'));
        }
        foreach($orders as $order_id){
           //重新获取CRM赠品
           $msg = '';
           $obj_crm->process($order_id,$msg,'doRequestCRM');
        }
        $this->end(true, app::get('base')->_('处理完成'));
    }

    //华强宝物流查询的路由
    function delviery_hqepay()
    {
        $corpMdl = app::get('ome')->model('dly_corp');

        $logi_no = $_POST['logi_no'];
        $order_bn = $_POST['order_bn'];
        $logi_id = $_POST['logi_id'];

        //物流公司信息
        $corpInfo = $corpMdl->dump(array('corp_id'=>$logi_id), '*');

        //物流渠道类型
        $channel_type = kernel::single('logisticsmanager_service_waybill')->getChannelType($logi_id);

        $traceObj = kernel::single('ome_hqepay');
        if(in_array($channel_type,array('unionpay'))){
            $traceObj = kernel::single('ome_unionpay');
        }elseif(in_array($corpInfo['corp_model'], array('instatnt', 'seller'))){
            //同城配--商家配送
            $traceObj = kernel::single('ome_logistics_seller');
        }

        //物流轨迹信息
        $delivery_html = $traceObj->detail_delivery($logi_no,$order_bn);

        echo json_encode($delivery_html);
    }

    /**
     * 批量跨境订单
     */
    function BatchDeclare()
    {
        $this->_request    = kernel::single('base_component_request');
        $order_info        = $this->_request->get_post();

        if(empty($order_info['order_id']))
        {
            echo '请选择订单!';exit;
        }

        //统计批量支付订单数量
        $this->pagedata['order_ids'] = serialize($order_info['order_id']);
        $this->display('admin/order/batch_update_declare.html');
    }

    /**
     * 批量设置为跨境订单
     */
    function doBatchDeclare()
    {
        $this->begin("index.php?app=ome&ctl=admin_order&act=active");

        $order_ids       = array();
        if(!empty($_POST['order_ids']))
        {
            $order_ids    = unserialize($_POST['order_ids']);
        }
        if(empty($order_ids))
        {
            $this->end(false,'提交数据有误!');
        }
        if(count($order_ids) > 100)
        {
            $this->end(false,'最多一次批量新建100个跨境订单!');
        }

        $this->end(false, '执行失败...');
    }

    /**
     * 销售物料列表弹窗数据获取方法
     * 
     * @param Void
     * @return String
     */
    function findSalesMaterial(){
        //已绑定的销售物料才可选择
        $base_filter = array('is_bind'=>1);

        if($_GET['shop_id']){
            $shop = explode('*',$_GET['shop_id']);
            $base_filter['shop_id'] = array($shop[0], '_ALL_');
        }

        if($_GET['type']){
            $base_filter['sales_material_type'] = $_GET['type'];
        }

        $params = array(
            'title'=>'销售物料列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'base_filter' => $base_filter,
        );
        $this->finder('material_mdl_sales_material', $params);
    }


    function getSalesMaterialByAddNormalOrder(){
        // $pro_id = $_POST['product_id'];

        // if (is_array($pro_id)){
        //     $filter['sm_id'] = $pro_id;
        // }

        $sm_id = $_POST['sm_id'];
        $sales_material_bn = $_GET['bn'];
        $sales_material_name = $_GET['name'];
        $basic_material_barcode = $_GET['barcode'];

        if (is_array($sm_id)){
            if ($sm_id[0] == "_ALL_"){
                $filter = '';
            }else {
                $filter['sm_id'] = $sm_id;
            }
        }

        if($sales_material_bn){
           $filter = array(
               'sales_material_bn|head'=>$sales_material_bn
           );
        }

        if($sales_material_name){
            $filter = array(
               'sales_material_name|head'=>$sales_material_name
           );
        }

        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        if($basic_material_barcode){

            $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');

            $filter = array(
               'code|head'=>$basic_material_barcode
            );
    
            $code_list = [];
            $bm_ids = $basicMaterialBarcode->getBmidListByFilter($filter, $code_list);
            $_tmp = $salesBasicMaterialObj->getList('*',array('bm_id|in'=>$bm_ids),0,-1);

            $filter = $_code_list = array();
            foreach ($_tmp as $_k => $_v) {
                $filter['sm_id|in'][] = $_v['sm_id'];

                if (isset($code_list[$_v['bm_id']]) && $code_list[$_v['bm_id']]) {
                    $_code_list[$_v['sm_id']] = $code_list[$_v['bm_id']];
                }
            }

        }

        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        $salesMStockLib = kernel::single('material_sales_material_stock');
        $basicMaterialObj = app::get('material')->model('basic_material');

        // 支持 is_bind 参数过滤
        if (isset($_GET['from']) && $_GET['from'] == 'return_reshipping') {
            $filter['sales_material_type'] = ['1', '2', '3'];
        }

        $filter['use_like'] = 1;
        $data = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type',$filter,0,-1,' sm_id ASC');

        if (!empty($data)){
            foreach ($data as $k => &$item){
                $store = $salesMStockLib->getSalesMStockById($item['sm_id']);
                $ExtInfo = $salesMaterialExtObj->dump($item['sm_id'],'retail_price');
                // $promoItems = $salesBasicMaterialObj->getList('*',array('sm_id'=>$item['sm_id']),0,-1);
                // if($promoItems){
                //     foreach($promoItems as $pk => &$promoItem){
                //         $tmp_item = $basicMaterialObj->getList('material_name,material_bn',array('bm_id'=>$promoItem['bm_id']),0,1);
                //         $promoItem = array_merge($promoItem , $tmp_item[0]);
                //     }
                //     $item['items'] = $promoItems;
                // }

                $item['store'] = $store;
                $item['num'] = 1;
                $item['price'] = $ExtInfo['retail_price'];
                if($item["sales_material_type"] == 4){ //手工新建订单 福袋类型不能修改售价price
                    $item["tpl_price_readonly"] = "readonly";
                }
                $item['product_id'] = $item['sm_id'];
                $item['bn'] = $item['sales_material_bn'];
                $item['name'] = $item['sales_material_name'];
                $item['barcode'] = (string)$_code_list[$item['sm_id']];
                $rows[] = $item;
            }
        }
         // 如果$filter['sm_id']有值，$rows根据它排序
         if (isset($filter['sm_id']) && !empty($filter['sm_id']) && is_array($filter['sm_id']) && !empty($rows)) {
             // 构建sm_id到排序索引的映射
             $sm_id_order = array_flip($filter['sm_id']);
             usort($rows, function($a, $b) use ($sm_id_order) {
                 $a_id = $a['product_id'];
                 $b_id = $b['product_id'];
                 $a_order = isset($sm_id_order[$a_id]) ? $sm_id_order[$a_id] : PHP_INT_MAX;
                 $b_order = isset($sm_id_order[$b_id]) ? $sm_id_order[$b_id] : PHP_INT_MAX;
                 return $b_order - $a_order;
             });
         }
         echo "window.autocompleter_json=".json_encode($rows);
    }

    /**
     * 销售物料列表弹窗选中物料信息查询方法
     * 
     * @param Int $bm_id
     * @return String
     */
    function getSalesMaterial(){
        $sm_id = $_POST['sm_id'];
        $sales_material_bn= $_GET['sales_material_bn'];
        $sales_material_name= $_GET['sales_material_name'];
        if (is_array($sm_id)){
            if ($sm_id[0] == "_ALL_"){
                $filter = '';
            }else {
                $filter['sm_id'] = $sm_id;
            }
        }

        if($sales_material_bn){
           $filter = array(
               'sales_material_bn'=>$sales_material_bn
           );
        }

        if($sales_material_name){
            $filter = array(
               'sales_material_name'=>$sales_material_name
           );
        }

        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        $salesMStockLib = kernel::single('material_sales_material_stock');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialObj = app::get('material')->model('basic_material');

        $filter['use_like'] = 1;
        $data = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type',$filter,0,-1);

        if (!empty($data)){
            foreach ($data as $k => &$item){
                $store = $salesMStockLib->getSalesMStockById($item['sm_id']);
                $ExtInfo = $salesMaterialExtObj->dump($item['sm_id'],'retail_price');
                $promoItems = $salesBasicMaterialObj->getList('*',array('sm_id'=>$item['sm_id']),0,-1);
                if($promoItems){
                    foreach($promoItems as $pk => &$promoItem){
                        $tmp_item = $basicMaterialObj->getList('material_name,material_bn',array('bm_id'=>$promoItem['bm_id']),0,1);
                        $promoItem = array_merge($promoItem , $tmp_item[0]);
                    }
                    $item['items'] = $promoItems;
                }

                $item['store'] = $store;
                $item['num'] = 1;
                $item['price'] = $ExtInfo['retail_price'];
                if($item["sales_material_type"] == 4){ //手工新建订单 福袋类型不能修改售价price
                    $item["tpl_price_readonly"] = "readonly";
                }
                $item['product_id'] = $item['sm_id'];
                $rows[] = $item;
            }
        }
        echo json_encode($rows);
    }

    /**
     * Ajax获取o2o门店店铺
     * 
     * @return Json
     */
    public function ajax_o2o_store_list()
    {
        $shop_name  = $_POST['shop_name'];
        $corp_id    = intval($_POST['logi_id']);
        $region_id  = intval($_POST['region_id']);
        $order_id = intval($_POST['order_id']);

        //地区级数
        $depth      = intval($_POST['depth']) - 1;
        $depth      = ($depth < 1 ? 1 : $depth);

        if((empty($region_id) && empty($shop_name)) || empty($corp_id))
        {
            echo json_encode(array('res'=>'error'));
            exit;
        }
        elseif($region_id && $depth > 5)
        {
            echo json_encode(array('res'=>'error'));//现只支持五级地区
            exit;
        }

        //地区级数
        $regionsObj    = app::get('eccommon')->model('regions');
        //$regionsInfo    = $regionsObj->dump(array('region_id'=>$region_id), 'region_grade');
        //$depth           = $regionsInfo['region_grade'];

        //物流公司
        $corpTypeLib    = kernel::single('o2o_corp_type');
        $corp_type      = $corpTypeLib->get_corp_type($corp_id);

        //搜索条件
        $storeObj      = app::get('o2o')->model('store');
        $where         = '';

        if($corp_type['o2o_pickup'])
        {
            $where    .= ' AND a.self_pick=1 ';
        }
        elseif($corp_type['o2o_ship'])
        {
            $where    .= ' AND a.distribution=1 ';
        }
        $where    .= ' AND a.status=1';

        //判断是否安装了阿里全渠道app
        if(app::get('tbo2o')->is_installed()){
            //判断订单是否是全渠道的
            $mdlO2oOrders = app::get('ome')->model('orders');
            $rs_order = $mdlO2oOrders->dump(array("order_id"=>$order_id),"shop_id,omnichannel");
            $tbo2o_shop = kernel::single('tbo2o_common')->getTbo2oShopInfo();
            if($rs_order["omnichannel"] == "1" && $rs_order["shop_id"] != $tbo2o_shop["shop_id"]){
                //非阿里全渠道店铺的订单 该订单是支持门店履约的情况下 显示的门店信息不能有全渠道的门店
                $arr_o2o_server = kernel::single('tbo2o_common')->getTbo2oServerInfo();
                if ($arr_o2o_server["server_id"]){
                    $where .= ' AND a.server_id<>'.$arr_o2o_server["server_id"];
                }
            }
        }

        //Select
        $flag     = false;
        $limit    = 100;//显示条数
        $sql      = '';
        $c_sql    = '';
        if($shop_name)
        {
            $sql    = 'SELECT {field} FROM sdb_o2o_store AS a WHERE a.name LIKE "%'. $shop_name .'%"'. $where;
            $flag   = true;
        }
        else
        {
            $region_field    = 'region_' . $depth;
            $sql    = 'SELECT {field} FROM sdb_o2o_store AS a LEFT JOIN sdb_o2o_store_regions AS b ON a.store_id=b.store_id ';
            $sql    .= 'WHERE b.'. $region_field .'='. $region_id . $where;

            //大范围查询时_默认显示100条数据
            if($depth < 3)
            {
                $flag   = true;
            }
        }

        //Count
        $c_sql    = str_replace('{field}', 'count(*) AS num', $sql);
        $count    = $storeObj->db->selectrow($c_sql);
        if(empty($count['num']))
        {
            echo json_encode(array('res'=>'empty'));
            exit;
        }

        //List
        $data       = array();
        $region_ids = array();
        $sql        = str_replace('{field}', 'a.store_id, a.name, a.area', $sql);
        $sql        .= ($flag ? ' LIMIT ' . $limit : '');

        $shopList   = $storeObj->db->select($sql);

        foreach ($shopList as $key => $val)
        {
            $area      = explode(':', $val['area']);
            $region_id = $area[2];

            $data[]    = array('store_id'=>$val['store_id'], 'store_name'=>$val['name'], 'region_id'=>$region_id);
            $region_ids[$region_id]    = $region_id;
        }

        //[格式化]门店对应地区
        $o2o_region    = kernel::single('o2o_store_regions');
        $regionList    = $o2o_region->getRegionByName($region_ids);

        foreach ($data as $key => $val)
        {
            $region_id    = $val['region_id'];

            if($regionList[$region_id])
            {
                $val['store_name']    = $val['store_name'] . '（'. $regionList[$region_id] .'）';
            }
            unset($val['region_id']);

            $data[$key]    = $val;
        }

        //[格式化]查询数据大于100条
        if($flag && ($count['num'] > $limit))
        {
            $data[]    = array('store_id'=>'', 'store_name'=>'==只显示 '. $limit .' 条记录,共有 '. $count['num'] .' 条记录==');
        }

        echo json_encode(array('res'=>'succ', 'store_list'=>$data));
        exit;
    }

    /**
     * Ajax获取订单对应仓库信息
     * 
     * @return Json
     */
    public function ajax_o2o_order_by_branch()
    {
        $order_id    = $_POST['order_id'];
        $store_id    = $_POST['store_id'];
        if(empty($order_id) || empty($store_id))
        {
            echo json_encode(array('res'=>'error'));
            exit;
        }

        $oOrderObj      = app::get('ome')->model('orders');
        $oBranchObj     = app::get('ome')->model("branch");
        $storeObj       = app::get('o2o')->model('store');

        $store_info     = $storeObj->dump(array('store_id'=>$store_id), 'branch_id, is_ctrl_store');
        if(empty($store_info['branch_id']))
        {
            echo json_encode(array('res'=>'empty'));
            exit;
        }

        $branchInfo    = $oBranchObj->db->selectrow('SELECT branch_id, branch_bn, name FROM sdb_ome_branch WHERE branch_id='.$store_info['branch_id']);
        //门店是否管控库存的标记
        $branchInfo['is_ctrl_store'] = ($store_info['is_ctrl_store'] == 1) ? true : false;

        //全局性配置门店是否管控供货关系
        $supply_relation = app::get('o2o')->getConf('o2o.ctrl.supply.relation');
        $branchInfo['is_ctrl_supply_relation'] = ($supply_relation == 'true') ? true :false;

        echo json_encode(array('res'=>'succ', 'branchinfo'=>$branchInfo));
        exit;
    }

    /**
     * Ajax最大程度的获取o2o门店店铺
     * 
     * @return Json
     */
    public function ajax_region_by_o2o_store()
    {
        $region_id  = intval($_POST['region_id']);
        $corp_id    = intval($_POST['logi_id']);
        $order_id = intval($_POST['order_id']);

        if(empty($region_id) || empty($corp_id) || !$order_id)
        {
            echo json_encode(array('res'=>'error'));
            exit;
        }

        //物流公司
        $corpTypeLib    = kernel::single('o2o_corp_type');
        $corp_type      = $corpTypeLib->get_corp_type($corp_id);

        //地区级数
        $regionsObj    = app::get('eccommon')->model('regions');
        $regionsInfo   = $regionsObj->dump(array('region_id'=>$region_id), 'p_region_id, region_grade');
        $depth         = $regionsInfo['region_grade'];
        $p_region_id   = $regionsInfo['p_region_id'];

        if(empty($regionsInfo))
        {
            echo json_encode(array('res'=>'no_district'));
            exit;
        }

        //条件
        $storeObj      = app::get('o2o')->model('store');
        $where         = '';

        if($corp_type['o2o_pickup'])
        {
            $where    .= ' AND a.self_pick=1 ';
        }
        elseif($corp_type['o2o_ship'])
        {
            $where    .= ' AND a.distribution=1 ';
        }
        $where    .= ' AND a.status=1';

        //判断是否安装了阿里全渠道app
        if(app::get('tbo2o')->is_installed()){
            //判断订单是否是全渠道的
            $mdlO2oOrders = app::get('ome')->model('orders');
            $rs_order = $mdlO2oOrders->dump(array("order_id"=>$order_id),"shop_id,omnichannel");
            $tbo2o_shop = kernel::single('tbo2o_common')->getTbo2oShopInfo();
            if($rs_order["omnichannel"] == "1" && $rs_order["shop_id"] != $tbo2o_shop["shop_id"]){
                //非阿里全渠道店铺的订单 该订单是支持门店履约的情况下 显示的门店信息不能有全渠道的门店
                $arr_o2o_server = kernel::single('tbo2o_common')->getTbo2oServerInfo();
                if ($arr_o2o_server["server_id"]){
                    $where .= ' AND a.server_id<>'.$arr_o2o_server["server_id"];
                }
            }
        }

        //循环地区查询
        $flag     = false;
        $limit    = 100;//显示条数
        $sql      = '';
        $c_sql    = '';

        while ($depth > 0)
        {
            $region_field    = 'region_' . $depth;
            $sql    = 'SELECT {field} FROM sdb_o2o_store AS a LEFT JOIN sdb_o2o_store_regions AS b ON a.store_id=b.store_id ';
            $sql    .= 'WHERE b.'. $region_field .'='. $region_id . $where;

            //大范围查询时_默认显示100条数据
            $c_sql    = str_replace('{field}', 'count(*) AS num', $sql);
            if($depth < 3)
            {
                $flag   = true;
            }

            $sql        = str_replace('{field}', 'a.store_id, a.name, a.area', $sql);
            $sql        .= ($flag ? ' LIMIT ' . $limit : '');
            $shopList   = $storeObj->db->select($sql);
            if($shopList)
            {
                break;
            }

            $depth--;

            //上一级地区
            $regionsInfo   = $regionsObj->dump(array('region_id'=>$p_region_id), 'region_id, p_region_id');
            $region_id     = $regionsInfo['region_id'];
            $p_region_id   = $regionsInfo['p_region_id'];
        }

        //Count
        $count    = $storeObj->db->selectrow($c_sql);
        if(empty($count['num']))
        {
            echo json_encode(array('res'=>'empty'));
            exit;
        }

        //List
        $data          = array();
        $region_ids    = array();
        foreach ($shopList as $key => $val)
        {
            $area      = explode(':', $val['area']);
            $region_id = $area[2];

            $data[]    = array('store_id'=>$val['store_id'], 'store_name'=>$val['name'], 'region_id'=>$region_id);
            $region_ids[$region_id]    = $region_id;
        }

        //[格式化]门店对应地区
        $o2o_region    = kernel::single('o2o_store_regions');
        $regionList    = $o2o_region->getRegionByName($region_ids);

        foreach ($data as $key => $val)
        {
            $region_id    = $val['region_id'];

            if($regionList[$region_id])
            {
                $val['store_name']    = $val['store_name'] . '（'. $regionList[$region_id] .'）';
            }
            unset($val['region_id']);

            $data[$key]    = $val;
        }

        //[格式化]查询数据大于100条
        if($flag && ($count['num'] > $limit))
        {
            $data[]    = array('store_id'=>'', 'store_name'=>'==只显示 '. $limit .' 条记录,共有 '. $count['num'] .' 条记录==');
        }

        echo json_encode(array('res'=>'succ', 'store_list'=>$data));
        exit;
    }

    /**
     * Ajax获取门店关联的地区
     * 
     * @return Json
     */
    public function ajax_store_regions()
    {
        $area      = $_POST['area'];
        $name      = ($_POST['name'] ? $_POST['name'] : 'store_consignee_area');
        $params    = array('id'=>$name, 'name'=>$name, 'value'=>$area, 'vtype' => 'area');

        //加载门店地区数据
        $regionLib    = kernel::single('o2o_view_input');
        $html         = $regionLib->input_region($params);

        echo($html);
        exit;
    }

    /**
     * [JS调用]门店区域下拉列表
     * 
     * @return Json
     */
    function selO2oRegion()
    {
        $path    = $_GET['path'];
        $depth   = $_GET['depth'];

        $local    = kernel::single('o2o_regions_select');
        $ret      = $local->get_area_select($path, array('depth'=>$depth));
        if($ret){
            echo '&nbsp;-&nbsp;'.$ret;exit;
        }else{
            echo '';exit;
        }
    }

    /**
     * 销售物料列表弹窗选中物料信息查询方法
     * @param Int $bm_id
     * @return String
     */
    function getSalesMaterialgroup()
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        $salesMStockLib = kernel::single('material_sales_material_stock');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        $sm_id = $_POST['sm_id'];
        $shop_id = $_POST['shop_id']; //目前多选一用
        $sales_material_bn= $_GET['sales_material_bn'];
        $sales_material_name= $_GET['sales_material_name'];
        
        //shop_bn
        $shopObj = app::get("ome")->model('shop');
        $shopInfo = $shopObj->dump(array('shop_id'=>$shop_id), 'shop_bn');
        $shop_bn = $shopInfo['shop_bn'];
        
        if (is_array($sm_id)){
            if ($sm_id[0] == "_ALL_"){
                $filter = '';
            }else {
                $filter['sm_id'] = $sm_id;
            }
        }

        if($sales_material_bn){
            $filter = array(
                'sales_material_bn'=>$sales_material_bn
            );
        }

        if($sales_material_name){
            $filter = array(
                'sales_material_name'=>$sales_material_name
            );
        }
        
        //sales_material
        $filter['use_like'] = 1;
        $data = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type',$filter,0,-1);

        if (!empty($data)){
            foreach ($data as $k => $item){
                $ExtInfo = $salesMaterialExtObj->dump($item['sm_id'],'retail_price');
                //$store = $salesMStockLib->getSalesMStockById($item['sm_id']);
                $store = 100;
                $salesMLib = kernel::single('material_sales_material');
                
                if($item['sales_material_type'] == 5){ //多选一
                    $basicMInfos = $salesMLib->get_order_pickone_bminfo($item['sm_id'],"1",$shop_id);
                    $basic_info_arr_pickone = array();
                    foreach($basicMInfos as $basicMInfo){
                        $basic_info_arr_pickone[] = array(
                                "sm_id" => $item['sm_id'],
                                "bm_id" => $basicMInfo['bm_id'],
                                "number" => $basicMInfo['number'],
                                "material_name" => $basicMInfo['material_name'],
                                "material_bn" => $basicMInfo['material_bn'],
                                "price" => $ExtInfo['retail_price'],
                        );
                    }
                    $item['items'] = $basic_info_arr_pickone;
                }elseif($item['sales_material_type'] == 7){
                    //福袋组合
                    $item['sale_material_nums'] = 1;
                    $item['shop_bn'] = $shop_bn;
                    
                    $fdResult = $fudaiLib->process($item);
                    if($fdResult['rsp'] == 'succ' && $fdResult['data']){
                        $basic_info_arr = array();
                        foreach($fdResult['data'] as $basicMInfo)
                        {
                            $basic_info_arr[] = array(
                                'sm_id' => $item['sm_id'],
                                'bm_id' => $basicMInfo['bm_id'],
                                'number' => $basicMInfo['number'],
                                'material_name' => $basicMInfo['material_name'],
                                'material_bn' => $basicMInfo['material_bn'],
                                'price' => $basicMInfo['price'],
                                'luckybag_id' => $basicMInfo['combine_id'], //福袋组合ID
                            );
                        }
                        
                        $item['items'] = $basic_info_arr;
                    }
                    
                }else{
                    $promoItems = $salesBasicMaterialObj->getList('*',array('sm_id'=>$item['sm_id']),0,-1);
                    if($promoItems){
                        foreach($promoItems as $pk => &$promoItem){
                            $tmp_item = $basicMaterialObj->getList('material_name,material_bn',array('bm_id'=>$promoItem['bm_id']),0,1);
                            $promoItem = array_merge($promoItem , $tmp_item[0]);
                        }
                        $item['items'] = $promoItems;
                    }
                }
                $item['store'] = $store;
                $item['num'] = 1;
                $item['price'] = $ExtInfo['retail_price'];
                $rows[$item['sales_material_type']][] = $item;
            }
        }

        echo json_encode($rows);

    }

    /**
     * Ajax通过门店编号自动选择门店
     * 
     * @return Json
     */
    public function ajax_autoload_select_store()
    {
        $store_bn    = $_POST['store_bn'];
        $corp_id     = intval($_POST['logi_id']);
        if(empty($store_bn) || empty($corp_id)){
            echo json_encode(array('res'=>'error'));
            exit;
        }

        //物流公司类型(门店自提OR配送)
        $corpTypeLib    = kernel::single('o2o_corp_type');
        $corp_type      = $corpTypeLib->get_corp_type($corp_id);

        //门店信息
        $o2oStoreObj    = app::get('o2o')->model('store');
        $storeRow       = $o2oStoreObj->dump(array('store_bn'=>$store_bn), 'store_id, area');
        if(empty($store_bn)){
            echo json_encode(array('res'=>'no_store'));
            exit;
        }

        list($temp_package, $temp_region_name, $region_id)    = explode(':', $storeRow['area']);

        //地区级数
        $regionsObj    = app::get('eccommon')->model('regions');
        $regionsInfo   = $regionsObj->dump(array('region_id'=>$region_id), 'p_region_id, region_grade');
        if(empty($regionsInfo)){
            echo json_encode(array('res'=>'no_district'));
            exit;
        }

        //获取地区关联的所有门店
        $where = '';
        if($corp_type['o2o_pickup']){
            $where    .= ' AND a.self_pick=1 ';
        }elseif($corp_type['o2o_ship']){
            $where    .= ' AND a.distribution=1 ';
        }
        $where    .= ' AND a.status=1';

        $region_grade    = 'region_' . $regionsInfo['region_grade'];

        $sql    = 'SELECT a.store_id, a.name AS store_name FROM sdb_o2o_store AS a LEFT JOIN sdb_o2o_store_regions AS b ON a.store_id=b.store_id ';
        $sql    .= 'WHERE b.'. $region_grade .'='. $region_id . $where;
        $data   = $o2oStoreObj->db->select($sql);

        if(empty($data))
        {
            echo json_encode(array('res'=>'empty'));
            exit;
        }

        echo json_encode(array('res'=>'succ', 'store_list'=>$data, 'default_store_id'=>$storeRow['store_id']));
        exit;
    }

    private function getBaseFilter($type){
        switch($type) {
            case 'assigned':
                $return = array(
                    'assigned' => 'assigned',
                    'abnormal'=>'false',
                    'is_fail'=>'false',
                    'process_status'=>array('unconfirmed', 'confirmed', 'splitting', 'splited'),
                    'is_auto' => 'false',
                );
                break;
            case 'notassigned':
                $return = array(
                    'assigned' => 'notassigned',
                    'abnormal'=>'false',
                    'ship_status'=>array('0', '2', '3'),//部分发货也显示
                    'is_fail'=>'false',
                    'process_status'=>array('unconfirmed', 'confirmed', 'splitting'),
                    'is_auto' => 'false',
                );
                break;
            case 'buffer':
                $return = array(
                    'assigned' => 'buffer',//加入SQl判断
                    'abnormal' => 'false',
                    'ship_status' => '0',
                    'is_fail' => 'false',
                    'process_status' => array('unconfirmed','is_retrial'),//加入SQl判断
                    'order_type|in' => kernel::single('ome_order_func')->get_normal_order_type(),
                    'status' => 'active',
                    'is_auto' => 'false',
                    'is_not_combine' => 0, //是否允许审核
                    'order_confirm_filter' => '( op_id IS NULL AND group_id IS NULL)',
                    //'createtime|lthan' => time()-10,#避免全选分派和自动审单并发导致赠品被拆分
                );
                break;
            default :
                $return = array();
                break;
        }

        if($type){
            //check shop permission
            $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
            if($organization_permissions){
                $return['org_id'] = $organization_permissions;
            }
        }
        return $return;
    }


    function getOrder(){

        $orderId = trim($_POST['order_id']);
        $orderBn = trim($_POST['order_bn']);

        if(!$orderId && !$orderBn){
            echo "";exit;
        }

        $basicMaterialStockObj   = app::get('material')->model('basic_material_stock');
        $basicMStockFreezeLib    = kernel::single('material_basic_material_stock_freeze');

        $filter = array();
        if($orderId){
            $filter['order_id'] = $orderId;
        }
        if($orderBn){
            $filter['order_bn'] = $orderBn;
        }
        $subsdf = array();
        if ($_POST['has_goods']){
            $subsdf = array('order_objects'=>array('*',array('order_items'=>array('*'))));
        }
        $ret = array();
        $ordersModel = $this->app->model("orders");
        $ret['order_info'] = $ordersModel->dump($filter,'*',$subsdf);

        list(,,$region_id) = explode(':',$ret['order_info']['consignee']['area']);
        $ret['order_info']['consignee']['id'] = $region_id;

        // 开票
        if ($ret['order_info']) {
            $ret['order_info']['invoice'] = app::get('ome')->model('order_invoice')->db_dump(array('order_id'=>$ret['order_info']['order_id']));

            if ($ret['order_info']['invoice']['tax_title']) $ret['order_info']['tax_title'] = $ret['order_info']['invoice']['tax_title'];
        }


        if($ret['order_info'] && $ret['order_info']['order_objects'] && is_array($ret['order_info']['order_objects'])){
            $productsModel = $this->app->model('products');
            $product_id_arr = array();
            foreach($ret['order_info']['order_objects'] as $o => $obj){
                foreach($obj['order_items'] as $i => $item){
                    $ret['order_info']['order_objects'][$o]['order_items'][$i]['sm_id'] = $item['product_id'];
                    $product_id_arr[] = array('product_id' => $item['product_id']);
                }
            }

            foreach($ret['order_info']['order_objects'] as $o => $obj){
                foreach($obj['order_items'] as $i => $item){
                    //库存（各仓库 的库存总和）
                    $get_store    = $basicMaterialStockObj->dump($item['product_id'], 'store,store_freeze');

                    //根据基础物料ID获取对应的冻结库存
                    $get_store['store_freeze']    = $basicMStockFreezeLib->getMaterialStockFreeze($item['product_id']);

                    $leftStore = $get_store['store'] - $get_store['store_freeze'];
                    $ret['order_info']['order_objects'][$o]['order_items'][$i]['store_minus_freeze'] = $leftStore > 0 ? $leftStore : 0;
                }
            }
        }

        echo json_encode($ret);
    }
    /**
     * 加密字段显示明文
     * 
     * @return void
     * @author
     * */
    public function showSensitiveData($order_id, $fieldType='')
    {
        $order = app::get('ome')->model('orders')->db_dump(array('order_id'=>$order_id), '*');

        //补发订单类型,使用原平台订单信息进行解密
        if(in_array($order['order_type'], array('bufa')) && $order['relate_order_bn']){
            $order = app::get('ome')->model('orders')->db_dump(array('order_bn'=>$order['relate_order_bn']), '*');
        }

        if (!$order) {
            $order = app::get('archive')->model('orders')->db_dump($order_id,'*');
        }

        if ($order['member_id']) {
            $member = app::get('ome')->model('members')->db_dump($order['member_id'],'uname');

            $order['uname'] = $member['uname'];
        }
        
        // 报备外呼主叫号码组
        //@todo：700虚拟号拨打前必须上报主叫号码组，号码组之外的号码拨打不通
        if(in_array($_GET['source'], ['reservation']) && in_array($order['shop_type'], ['taobao','360buy'])){
            $secretResult = kernel::single('ome_order_reservation')->getOrderSecretMobiles($order);
            if($secretResult['rsp'] == 'succ' && $secretResult['data']){
                $result = kernel::single('erpapi_router_request')->set('shop', $order['shop_id'])->order_bindSecretMobiles($secretResult['data']);
                if($result['rsp'] == 'succ'){
                    //操作人
                    $opinfo = kernel::single('ome_func')->get_system();
                    
                    //log
                    app::get('ome')->model('operation_log')->write_log('order_confirm@ome', $order_id, '报备外呼主叫号码组，成功！', time(), $opinfo);
                }
            }
        }
        
        // 页面加密处理
        $order['encrypt_body'] = kernel::single('ome_security_router',$order['shop_type'])->get_encrypt_body($order, 'order', $fieldType);
        
        $this->splash('success',null,null,'redirect',$order);
    }

        /**
     * showSensitiveInvoice
     * @param mixed $order_id ID
     * @return mixed 返回值
     */
    public function showSensitiveInvoice($order_id)
    {
        $order = app::get('ome')->model('orders')->db_dump(array('order_id'=>$order_id,'order_type'=>'_ALL_'), 'order_id,ship_name,ship_tel,ship_mobile,ship_addr,shop_id,shop_type,order_bn,member_id');

        // 页面加密处理
        $order['encrypt_body'] = kernel::single('ome_security_router',$order['shop_type'])->get_encrypt_body($order, 'order');

        // 推送日志
        // kernel::single('base_hchsafe')->order_log(array('operation'=>'查看订单收货人信息','tradeIds'=>array($order['order_bn'])));

        $this->splash('success',null,null,'redirect',$order);
    }

    //批量编辑订单
    /**
     * BatchUpdateOrder
     * @return mixed 返回值
     */
    public function BatchUpdateOrder()
    {
        $ordersModel = app::get('ome')->model('orders');

        if (empty($_POST['order_id']) || $_POST['isSelectedAll']=='_ALL_'){
            die('暂不支持全选');
        }

        $filter = array('order_id'=>$_POST['order_id']);
        $tempList = $ordersModel->getList('order_id,process_status', $filter);

        $order_id = array();
        foreach($tempList as $val)
        {
            //check
            if(!in_array($val['process_status'], array('unconfirmed','confirmed','splitting'))){
                continue;
            }

            $order_id[] = $val['order_id'];
        }

        if (empty($order_id)){
            die('没有可操作的订单');
        }

        //参考第一个订单
        $order = $ordersModel->dump($order_id[0],'*',array('order_objects'=>array('*',array('order_items'=>array('*')))));
        $orderObjectObj = app::get('ome')->model('order_objects');
        foreach ($order['order_objects'] as $okey => &$object) {
            if ($object['delete'] == 'true') {
                unset($order['order_objects'][$okey]);
                continue;
            }

            $objOrderId = $orderObjectObj->getList('distinct order_id',['order_id'=>$order_id,'goods_id'=>$object['goods_id']]);
            if(count($objOrderId) != count($order_id)) {
                unset($order['order_objects'][$okey]);
                continue;
            }
            foreach ($object['order_items'] as $ikey => &$item)
            {
                $item['addon'] = ome_order_func::format_order_items_addon($item['addon']);
            }

        }
        $this->pagedata['order'] = $order;

        $this->pagedata['GroupList']   = json_encode($order_id);
        $this->pagedata['custom_html'] = $this->fetch('admin/order/batch/updateorder.html');
        $this->pagedata['request_url'] = 'index.php?app=ome&ctl=admin_order&act=doBatchUpdateOrder';

        //调用desktop公用进度条
        parent::dialog_batch();
    }

    /**
     * doBatchUpdateOrder
     * @return mixed 返回值
     */
    public function doBatchUpdateOrder()
    {
        $ordersModel = app::get('ome')->model('orders');
        $orderItemModel = app::get('ome')->model('order_items');
        
        $replace = (array)$_POST['replace']; //删除
        $replace = array_filter($replace);

        $change = (array)$_POST['change']; //新增
        $change = array_filter($change);

        $order_ids = explode(',', $_POST['primary_id']);
        $order_ids = array_filter($order_ids);

        if (empty($replace) && empty($change)) {
            echo 'Error: 订单无需修改';exit;
        }

        if (empty($order_ids)){
            echo 'Error: 请先选择订单';exit;
        }

        //result
        $retArr = array(
                'itotal'  => count($order_ids),
                'isucc'   => 0,
                'ifail'   => 0,
                'err_msg' => array(),
        );

        //data
        $params = array(
                'replace' => $replace, //删除
                'change' => $change, //新增
                'change_number' => $_POST['change_number'], //新增数量
                'delAll' => $_POST['delAll'],
        );

        //foreach
        foreach($order_ids as $order_id)
        {
            //订单信息
            $orderInfo = $ordersModel->dump($order_id, 'order_bn,pause,process_status');
            if(!in_array($orderInfo['process_status'], array('unconfirmed','confirmed','splitting'))){
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('订单号[%s]状态不允许编辑', $orderInfo['order_bn']);

                continue;
            }

            //开启事务
            kernel::database()->beginTransaction();

            //执行编辑任务
            $err_msg = '';
            $result = $orderItemModel->changeOrderItem($order_id, $params, $err_msg);
            if(!$result){
                $retArr['ifail']++;
                $retArr['err_msg'][] = 'ERROR:'.$err_msg;
                //回滚事务
                kernel::database()->rollBack();

                continue;
            }

            //提交事务
            kernel::database()->commit();
            
            //成功
            $retArr['isucc']++;
        }

        echo json_encode($retArr),'ok.';
        exit;
    }

    /**
     * dispatchDailog
     * @param mixed $order_id ID
     * @return mixed 返回值
     */
    public function dispatchDailog($order_id = NULL)
    {
        @ini_set('memory_limit','256M');
        $oGroup = $this->app->model('groups');
        $filter = array('g_type'=>'confirm');
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $filter['org_id'] = $organization_permissions;
        }
        $groups = $oGroup->getList('group_id,name',$filter);
        $this->pagedata['groups'] = $groups;
        $_POST['archive'] ='0';
        $_POST['process_status|noequal'] = 'is_declare'; //跨境申报
        $_POST = array_merge($_POST, $this->getBaseFilter($_GET['flt']));
        $orderObj = app::get('ome')->model('orders');
        $orderList = $orderObj->getList('order_id', $_POST, 0, 10000);
        $_POST['order_id'] = array_column($orderList, 'order_id');
        if ($_POST['isSelectedAll'] == '_ALL_') {
            unset($_POST['isSelectedAll']);
        }

        $pageData =[
            'billName' => '订单',
            'maxProcessNum' => 200,
            'queueNum' => 5,
            'close' => true,
            'custom_html' => $this->fetch('admin/order/dispatch.html'),
            'request_url' => 'index.php?app=ome&ctl=admin_order&act=ajaxDispatch'
        ];
        parent::selectToPageRequest($orderObj, $pageData);
    }

    /**
     * ajaxDispatch
     * @return mixed 返回值
     */
    public function ajaxDispatch()
     {
         $order_id = explode(';', $_POST['ajaxParams']);

         $retArr = array(
             'total'  => count($order_id),
             'succ'   => 0,
             'fail'   => 0,
             'fail_msg' => array(),
         );
         if (!$_POST['new_group_id']) { 
            $retArr['fail'] = $retArr['total'];
            $retArr['fail_msg'][] = ['msg'=>'请选择分组'];
            echo json_encode($retArr);exit;
        }
         
         $orderMdl = app::get('ome')->model('orders');

         $orders = $orderMdl->getList('order_id,order_bn', ['order_id'=>$order_id]);

         foreach ($orders as $order) {
             list($rs,$msg) = kernel::single('ome_order')->dispatch($order['order_id'], $_POST['new_group_id'], $_POST['new_op_id']);

             if ($rs) {
                 $retArr['succ']++;
             } else {
                 $retArr['fail']++;

                 $retArr['fail_msg'] = ['msg'=>sprintf('[%s]%s', $order['order_bn'], $msg)];
             }
         }

         echo json_encode($retArr);exit;
    }

    /**
     * 批量修改订单标记
     */
    public function batchEditLabel()
    {
        if (empty($_POST['order_id']) || $_POST['isSelectedAll']=='_ALL_'){
            die('暂不支持全选');
        }

        $orderObj = app::get('ome')->model('orders');
        $labelObj = app::get('omeauto')->model('order_labels');

        //选择的订单
        $filter = array('order_id'=>$_POST['order_id']);
        $dataList = $orderObj->getList('order_id', $filter);
        if(empty($dataList)){
            die('没有可操作的订单');
        }

        $order_ids = array();
        foreach($dataList as $key => $val){
            $order_ids[] = $val['order_id'];
        }

        //所有标签
        $labelList = array();
        $filter = [
            'filter_sql' => '(source<>"system" OR source IS NULL)',
        ];
        $dataList = $labelObj->getList('label_id,label_code,label_name,label_color', $filter);
        if($dataList){
            $line_i = 0;
            foreach ($dataList as $key => $val)
            {
                $label_id = $val['label_id'];

                $line_i++;

                $val['line_i'] = $line_i;
                $labelList[$label_id] = $val;
            }
        }

        $this->pagedata['order_ids'] = json_encode($order_ids);
        $this->pagedata['labelList'] = $labelList;

        $this->pagedata['GroupList']   = json_encode($order_ids);
        $this->pagedata['custom_html'] = $this->fetch('admin/order/batch/label.html');
        $this->pagedata['request_url'] = 'index.php?app=ome&ctl=admin_order&act=doBatchEditLabel';

        //调用desktop公用进度条
        parent::dialog_batch();
    }

    /**
     * doBatchEditLabel
     * @return mixed 返回值
     */
    public function doBatchEditLabel()
    {
        $ordLabelObj = app::get('ome')->model('bill_label');
        $labelObj = app::get('omeauto')->model('order_labels');
        $operLogObj = app::get('ome')->model('operation_log');

        //订单ID
        $order_ids = explode(',', $_POST['primary_id']);
        $order_ids = array_filter($order_ids);

        if (empty($order_ids)){
            echo 'Error: 请先选择订单';
            exit;
        }

        //操作类型
        $oper_action = $_POST['select_oper_action'];

        if (empty($oper_action)) {
            echo 'Error: 没有选择操作方式';
            exit;
        }

        //选择的标签
        $labelIds = explode(',', $_POST['select_labels']);
        $labelIds = array_filter($labelIds);

        if (empty($labelIds)) {
            echo 'Error: 没有选择标签';
            exit;
        }

        if (count($labelIds) > 10) {
            echo 'Error: 一次最多可选择10个标签';
            exit;
        }

        //选择的标签
        $dataList = $labelObj->getList('label_id,label_name', array('label_id'=>$labelIds));
        if (empty($dataList)) {
            echo 'Error: 没有可用的标签';
            exit;
        }

        $labelList = array();
        foreach ($dataList as $key => $val)
        {
            $label_id = $val['label_id'];
            $labelList[$label_id] = $val;
        }

        //操作人
        $opinfo = kernel::single('ome_func')->get_system();

        //result
        $retArr = array(
                'itotal' => count($order_ids),
                'isucc' => 0,
                'ifail' => 0,
                'err_msg' => array(),
        );

        $billLabelLib = kernel::single('ome_bill_label');
        //操作
        if($oper_action == 'delete'){
            //删除标签
            $tags = array();
            foreach ($labelList as $key => $val)
            {
                $tags[] = $val['label_name'];
            }

            foreach($order_ids as $order_id)
            {
                $del_label_ids = array();
                foreach ($labelIds as $laKey => $label_id)
                {
                    $del_label_ids[$label_id] = $label_id;
                }

                $error_msg = '';
                $billLabelLib->delLabelFromBillId($order_id, $del_label_ids, 'order', $error_msg);

                //log
                $logMsg = sprintf('人工批量清除标签,标签为：%s', implode(',', $tags));
                $operLogObj->write_log('order_confirm@ome', $order_id, $logMsg, time(), $opinfo);
            }

            $retArr['isucc'] = count($order_ids);

        }elseif($oper_action == 'add'){
            //新增标签
            $dataList = array();
            foreach($order_ids as $order_id)
            {
                foreach ($labelIds as $laKey => $label_id)
                {
                    //check
                    $isCheck = $ordLabelObj->dump(array('bill_type'=>'order', 'bill_id'=>$order_id, 'label_id'=>$label_id), 'bill_id');
                    if($isCheck){
                        continue; //标记已存在,则跳过
                    }

                    $dataList[$order_id][$label_id] = array(
                            'label_id' => $label_id,
                            'label_name' => $labelList[$label_id]['label_name'],
                    );
                }
            }

            if (empty($dataList)) {
                echo 'Error: 订单已经存在选择的标签,无需重复操作';
                exit;
            }

            foreach ($dataList as $order_id => $items)
            {
                $tags = array();
                foreach ($items as $label_id => $val)
                {
                    // $saveData = array(
                    //         'bill_type'     =>  'order',
                    //         'bill_id'       =>  $order_id,
                    //         'label_id'      =>  $label_id,
                    //         'label_name'    =>  $val['label_name'],
                    //         'create_time'   =>  time(),
                    // );

                    // $ordLabelObj->insert($saveData);
                    $err = '';
                    $billLabelLib->markBillLabel($order_id, $label_id, '', 'order', $err);

                    $tags[] = $val['label_name'];
                }

                //log
                $logMsg = sprintf('人工批量添加标签,标签为：%s', implode(',', $tags));
                $operLogObj->write_log('order_confirm@ome', $order_id, $logMsg, time(), $opinfo);

                //成功
                $retArr['isucc']++;
            }
        }

        echo json_encode($retArr),'ok.';
        exit;
    }

    /**
     * 弹窗获取发货单信息
     * @param $delivery_id
     * @author db
     * @date 2023-07-28 3:08 下午
     */
    public function getDelivery($delivery_id)
    {
        $actions = array();
        $params  = array(
            'title'               => '订单查看',
            'use_buildin_set_tag' => false,
            'use_buildin_filter'  => false,
            'use_buildin_export'  => false,
            'use_buildin_import'  => false,
            'use_buildin_recycle' => false,
            'use_buildin_setcol'  => false,
            'base_filter'         => ['delivery_id' => $delivery_id],
            //'alertpage_finder'=>true,
            'actions'             => $actions,
            'finder_aliasname'    => 'oms_order_delivery',
            //'finder_cols'         => '',
        );
        $this->finder('ome_mdl_delivery', $params);
    }

    /**
     * 审核订单时提示建议信息
     * 
     * @return void
     */
    public function reconfirmOrder()
    {
        $order_id = intval($_GET['order_id']);
        $branch_id = intval($_GET['branch_id']);
        $logi_id = intval($_GET['logi_id']);
        $orderIds = ($_GET['order_ids'] ? explode(',', $_GET['order_ids']) : array());
        $orderIds = array_filter($orderIds); //合单多个订单ID

        $error_msg = '没有报错信息...';

        //订单信息
        $orderObj = app::get('ome')->model('orders');
        $orderInfo = $orderObj->dump(array('order_id'=>$order_id), '*');

        //翱象订单
        $isAoxiang = false;
        if(in_array($orderInfo['shop_type'], array('taobao', 'tmall')) && $orderInfo['order_bool_type']){
            //是否翱象订单
            $orderTypeLib = kernel::single('ome_order_bool_type');
            $isAoxiang = $orderTypeLib->isAoxiang($orderInfo['order_bool_type']);
        }

        //[翱象订单]检查审单条件
        if($isAoxiang){
            $branchObj = app::get('ome')->model('branch');
            $corpObj = app::get('ome')->model('dly_corp');

            $axOrderLib = kernel::single('dchain_order');

            //branch
            $branchInfo = $branchObj->db->selectrow("SELECT * FROM sdb_ome_branch WHERE branch_id=". $branch_id);

            //检查仓库和物流公司属性是否一致
            $corpInfo = $corpObj->dump(array('corp_id'=>$logi_id), '*');

            //审单规则
            $axResult = $axOrderLib->combineOrder($orderInfo, $branchInfo, $corpInfo, $orderIds);
            if($axResult['rsp'] == 'fail' && $axResult['error_msg']){
                $error_msg = $axResult['error_msg'];
            }
        }

        $this->pagedata['error_msg'] = $error_msg;

        $this->display('admin/order/order_check_msg.html');
    }
    
    /**
     * Ajax获取订单收货人解密数据
     *
     * @param $order_id
     * @param $fieldType
     * @return void
     */
    public function ajaxShowSensitiveData($order_id)
    {
        $search = array('&nbsp;','"','&quot;',"\r\n","\r","\n",',');
        
        // order
        $orderInfo = app::get('ome')->model('orders')->db_dump(array('order_id'=>$order_id), '*');
        
        // 补发订单类型,使用原平台订单信息进行解密
        if(in_array($orderInfo['order_type'], array('bufa')) && $orderInfo['relate_order_bn']){
            $orderInfo = app::get('ome')->model('orders')->db_dump(array('order_bn'=>$orderInfo['relate_order_bn']), '*');
        }
        
        // 归档订单
        if (empty($orderInfo)) {
            $orderInfo = app::get('archive')->model('orders')->db_dump($order_id,'*');
        }
        
        if ($orderInfo['member_id']) {
            $member = app::get('ome')->model('members')->db_dump($orderInfo['member_id'],'uname');
            $orderInfo['uname'] = $member['uname'];
        }
        
        // 获取订单解密信息
        //$orderInfo = app::get('ome')->model('orders')->dump(array('order_id'=>$order_id), '*');
        //kernel::single('ome_order')->getEncryptOriginData($orderInfo);
        
        // area
        $temp = explode(':', $orderInfo['ship_area']);
        $area = $temp[1];
        $area = str_replace('/', '-', $area);
        
        // 解密收货人信息
        $encryptData = kernel::single('ome_security_router', $orderInfo['shop_type'])->decrypt($orderInfo, 'order');
        
        // telephone
        $telephone = '';
        if($encryptData['ship_mobile']){
            $telephone = str_replace($search, '', $encryptData['ship_mobile']);
        }elseif($encryptData['ship_tel']){
            $telephone = str_replace($search, '', $encryptData['ship_tel']);
        }
        
        // 收货人明文信息
        $shipInfo = [
            'ship_name' => str_replace($search, '', $encryptData['ship_name']),
            'ship_mobile' => $telephone,
            'area' => $area,
            'ship_addr' => str_replace($search, '', $encryptData['ship_addr']),
        ];
        
        $result = [
            'rsp' => 'succ',
            'error_msg' => '',
            'order_id' => $order_id,
            'order_bn' => $orderInfo['order_bn'],
            'ship_info' => implode('，', $shipInfo), //订单明细手工复制收货人解密信息
        ];
        
        // merge
        $result = array_merge($result, $shipInfo);
        
        echo json_encode($result);
        exit;
    }
}
