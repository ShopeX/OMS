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
 * 分销订单列表
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.24
 */
class dealer_ctl_admin_orders extends desktop_controller
{
    var $name = "代发订单";
    var $workground = "order_center";
    
    private $_businessLib = null;
    
    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */

    public function __construct($app)
    {
        parent::__construct($app);
        
        //lib
        $this->_businessLib = kernel::single('dealer_business');
    }
    
    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $this->title = $this->name;
        $base_filter = $this->getFilters();
        $actions = array();
        
        $op_id = kernel::single('desktop_user')->get_id();
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        switch ($_GET['view'])
        {
            case '0':
            case '1':
                $actions[] = array('label'=>'推送SMART', 'submit'=>$this->url.'&act=batchPushSmart', 'target'=>'dialog::{width:600,height:230,title:\'批量推送SMART\'}');
                break;
        }
        
        //params
        $params = array(
            'title' => $this->title,
            'actions' => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab'=>true,
            'finder_aliasname' => 'order_view'.$op_id,
            'finder_cols' => 'order_bn,shop_id,total_amount,column_print_status,process_status,is_cod,pay_status,ship_status,payment,shipping,logi_id,logi_no,createtime,paytime,mark_type',
            'base_filter' => $base_filter,
        );
        
        //是否允许导出
        $user = kernel::single('desktop_user');
        if($user->has_permission('order_export')){
            $params['use_buildin_export'] = true;
        }
        
        $this->finder('dealer_mdl_orders', $params);
    }
    
    /**
     * _views
     * @return mixed 返回值
     */
    public function _views()
    {
        $orderMdl = app::get('ome')->model('orders');
        
        //filter
        $base_filter = $this->getFilters();
        
        //menu
        $sub_menu = array(
            0 => array(
                'label'=>app::get('base')->_('待处理'),
                'filter'=>array('process_status'=>array('unconfirmed','confirmed','splitting'),'status'=>'active'),
                'optional'=>false,
            ),
            1 => array('label'=>app::get('base')->_('待记账'), 'filter'=>array('pay_status'=>array('0'), 'status'=>'active'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('全部'),'filter'=>$base_filter,'optional'=>false),
            3 => array('label'=>app::get('base')->_('待发货'),'filter'=>array('ship_status'=>array('0','2'),'status'=>'active'),'optional'=>false),
            4 => array('label'=>app::get('base')->_('已发货'),'filter'=>array('ship_status'=>'1','status'=>'active'),'optional'=>false),
            5 => array('label'=>app::get('base')->_('暂停'),'filter'=>array('pause'=>'true'),'optional'=>false),
        );
        
        $i = 0;
        foreach($sub_menu as $k => $v)
        {
            if (isset($v['filter'])){
                $v['filter'] = array_merge($base_filter, $v['filter']);
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $orderMdl->viewcount($v['filter']);
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
    
    /**
     * 请求SMART
     * 
     * @return void
     */
    public function batchPushSmart()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        //post
        $orderIds = $_POST['order_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($orderIds)){
            die('请选择需要操作的订单!');
        }
        
        if(count($orderIds) > 500){
            die('每次最多只能选择500条!');
        }
        
        $this->pagedata['GroupList'] = json_encode($orderIds);
        $this->pagedata['request_url'] = $this->url .'&act=ajaxSyncSmart';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch('dealer_mdl_orders', false, 50, 'incr');
    }
    
    /**
     * Ajax请求SMART
     * 
     * @return string
     */
    public function ajaxSyncSmart()
    {
        $orderMdl = app::get('ome')->model('orders');
        $orderLib = kernel::single('dealer_platform_orders');
        
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取订单
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择订单';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data
        $dataList = $orderMdl->getList('order_id,order_bn,pay_status,process_status', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到订单';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //list
        foreach ($dataList as $key => $val)
        {
            $order_bn = $val['order_bn'];
            
            //check
            if($val['pay_status'] != '0'){
                //fail
                $retArr['err_msg'][] = '订单号：'. $order_bn .'不是未支付状态(已经获取SMART价格)';
                
                $retArr['ifail'] += 1;
            }
            
            if($val['process_status'] != 'unconfirmed'){
                //fail
                $retArr['err_msg'][] = '订单号：'. $order_bn .'确认状态不支持处理';
                
                $retArr['ifail'] += 1;
            }
            
            //request
            $result = $orderLib->requestSmart($order_bn);
            if($result['rsp'] != 'succ'){
                //fail
                $retArr['err_msg'][] = $result['error_msg'];
                
                $retArr['ifail'] += 1;
            }else{
                //succ
                $retArr['isucc'] += 1;
            }
        }
        
        echo json_encode($retArr),'ok.';
        exit;
    }
}
