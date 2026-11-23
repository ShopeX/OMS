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
 * 唯品会平台已经成交的销售单列表(只包括状态正常的销售单)
 *
 * @author wangbiao@shopex.cn
 * @version 2025.03.21
 */
class console_ctl_admin_inventory_orders extends desktop_controller
{
    var $title = '销售订单';
    var $workground = 'console_purchasecenter';
    
    private $_appName = 'console';
    private $_mdl = null; //model类
    protected $_jitOrderLib = null;
    
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get($this->_appName)->model('inventory_orders');
        
        $this->_jitOrderLib = kernel::single('console_inventory_orders');
        
        //primary_id
        $this->_primary_id = 'id';
        
        //primary_bn
        $this->_primary_bn = 'order_sn';
    }
    
    public function index()
    {
        $actions = array();
        
        //filter
        $base_filter = array();
        
        //button
        $buttonList = array();
        
        //pullOrders
        $buttonList['pullOrders'] = array(
            'label' => '拉取成交销售单',
            'href' => $this->url .'&act=batchPull&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
            'target' => "dialog::{width:550,height:350,title:'拉取平台已经成交的销售单(只能拉取一天内的数据)'}",
        );
        
        $buttonList['pullCanncelOrders'] = array(
            'label' => '拉取已取消销售单',
            'href' => $this->url .'&act=batchCanncelPull&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
            'target' => "dialog::{width:550,height:350,title:'拉取平台成交后已取消的销售单(只能拉取一天内的数据)'}",
        );
        
        //dispose
        $buttonList['dispose'] = array(
            'label' => '批量处理',
            'submit' => $this->url.'&act=batchDispose',
            'target' => 'dialog::{width:600,height:230,title:\'批量处理订单\'}'
        );
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        switch ($_GET['view'])
        {
            case '0':
                $actions[] = $buttonList['pullOrders'];
                
                $actions[] = $buttonList['pullCanncelOrders'];
                break;
            case '1':
            case '2':
            case '3':
                $actions[] = $buttonList['dispose'];
                
                break;
            default:
                //---
        }
        
        //导出权限
        $use_buildin_export = false;
        
        //params
        $orderby = 'id DESC';
        $params = array(
            'title' => $this->title,
            'base_filter' => $base_filter,
            'actions'=> $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => $use_buildin_export,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => $orderby,
        );
        
        $this->finder('console_mdl_inventory_orders', $params);
    }
    
    public function _views()
    {
        //filter
        $base_filter = array();
        
        //menu
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'), 'filter'=>$base_filter, 'optional'=>false),
            1 => array('label'=>app::get('base')->_('未处理'), 'filter'=>array('dispose_status'=>array('none')), 'optional'=>false),
            2 => array('label'=>app::get('base')->_('处理中'), 'filter'=>array('dispose_status'=>array('running')), 'optional'=>false),
            3 => array('label'=>app::get('base')->_('处理失败'), 'filter'=>array('dispose_status'=>array('fail')), 'optional'=>false),
        );
        
        foreach($sub_menu as $k => $v)
        {
            if (isset($v['filter'])){
                $v['filter'] = array_merge($base_filter, $v['filter']);
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['href'] = 'index.php?app='. $_GET['app'] .'&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$k;
            
            //第一个TAB菜单没有数据时显示全部
            if($k == 0){
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->count($v['filter']);
                if($sub_menu[$k]['addon'] == 0){
                    unset($sub_menu[$k]);
                }
            }else{
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->viewcount($v['filter']);
            }
        }
        
        return $sub_menu;
    }
    
    /**
     * 拉取平台已经成交的销售单
     */
    public function batchPull()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        //shop
        $shopObj = app::get('ome')->model('shop');
        $sql = "SELECT shop_id,shop_bn,name AS shop_name,shop_type,node_id FROM sdb_ome_shop WHERE shop_type ='vop' AND node_id IS NOT NULL AND node_id != ''";
        $shopList = $shopObj->db->select($sql);
        $this->pagedata['shopList'] = $shopList;
        
        //开始时间(默认为昨天)
        $start_time = date('Y-m-d', time());
        $this->pagedata['start_time'] = $start_time;
        
        //help帮助信息
        $this->pagedata['help_msg'] = '*温馨提醒：最多只能拉取近三个月内的T-1日数据。结束时间是今天零点的时间。';
        
        //店铺编码
        $this->pagedata['selectListName'] = '店铺编码';
        
        //post url
        $this->pagedata['postUrl'] = $this->url .'&act=ajaxPullOrder';
        
        //check
        if(empty($shopList)){
            die('没有绑定唯品会店铺');
        }
        
        $this->display('admin/vop/download_datalist.html');
    }
    
    /**
     * ajax拉取平台已经成交的销售单
     */
    public function ajaxPullOrder()
    {
        $shopObj = app::get('ome')->model('shop');
        
        //check
        if(empty($_POST['shop_bn'])){
            $retArr['err_msg'] = array('请先选择店铺编码');
            echo json_encode($retArr);
            exit;
        }
        
        if(empty($_POST['startTime'])){
            $retArr['err_msg'] = array('请先选择开始时间');
            echo json_encode($retArr);
            exit;
        }
        
        $startTime = strtotime($_POST['startTime'].' 00:00:00');
        
        //check
        $checkTime = strtotime(date('Y-m-d', time()).' 00:00:00') - 86400 * 7;
        if($startTime < $checkTime){
            $retArr['err_msg'] = array('只支持拉取7天以内的数据');
            echo json_encode($retArr);
            exit;
        }
        
        //page
        $nextPage = isset($_POST['nextPage']) && $_POST['nextPage'] > 1 ? $_POST['nextPage'] : 1;
        
        //setting
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'total' => 0,
            'next_page' => 0,
            'err_msg' => array(),
        );
        
        //shop
        $sql = "SELECT shop_id,shop_bn,name,shop_type,node_id FROM sdb_ome_shop WHERE shop_type ='vop' AND shop_bn='". $_POST['shop_bn'] ."' AND node_id IS NOT NULL AND node_id != ''";
        $shopInfo = $shopObj->db->selectrow($sql);
        if(empty($shopInfo)){
            $retArr['err_msg'] = array('唯品会店铺不符合,无法拉取数据');
            echo json_encode($retArr);
            exit;
        }
        $shop_id = $shopInfo['shop_id'];
        
        //params
        $params = array(
            'shop_id' => $shop_id,
            'start_time' => $startTime,
            'end_time' => strtotime(date('Y-m-d', $startTime).' 23:59:59'),
            'page' => $nextPage,
        );
        
        //reuqest
        $result = $this->_jitOrderLib->getInventoryOccupiedOrders($params);
        if($result['rsp'] != 'succ'){
            $error_msg = $result['error_msg'];
            $retArr['err_msg'] = array($error_msg);
        }else{
            if(isset($result['data']) && $result['data']){
                //继续拉取下一页
                if($result['data']['has_next']){
                    $nextPage++;
                }else{
                    $nextPage = 0;
                }
                
                $retArr['total'] = $result['data']['total_num']; //数据总记录数
                $retArr['itotal'] += $result['data']['current_num']; //本次拉取记录数
                $retArr['isucc'] += $result['data']['current_succ_num']; //处理成功记录数
                $retArr['ifail'] += $result['data']['current_fail_num']; //处理失败记录数
                $retArr['next_page'] = $nextPage; //下一页页码(如果为0则无需拉取)
            }else{
                $retArr['itotal'] += $result['current_num']; //本次拉取记录数
                $retArr['isucc'] += $result['current_succ_num']; //处理成功记录数
                $retArr['ifail'] += $result['current_fail_num']; //处理失败记录数
                $retArr['total'] = $result['total_num']; //数据总记录数
            }
        }
        
        echo json_encode($retArr);
        exit;
    }
    
    /**
     * 拉取平台成交后已取消的销售单
     */
    public function batchCanncelPull()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        //shop
        $shopObj = app::get('ome')->model('shop');
        $sql = "SELECT shop_id,shop_bn,name AS shop_name,shop_type,node_id FROM sdb_ome_shop WHERE shop_type ='vop' AND node_id IS NOT NULL AND node_id != ''";
        $shopList = $shopObj->db->select($sql);
        $this->pagedata['shopList'] = $shopList;
        
        //开始时间(默认为昨天)
        $start_time = date('Y-m-d', time());
        $this->pagedata['start_time'] = $start_time;
        
        //help帮助信息
        $this->pagedata['help_msg'] = '*温馨提醒：最多只能拉取近三个月内的T-1日数据。结束时间是今天零点的时间。';
        
        //店铺编码
        $this->pagedata['selectListName'] = '店铺编码';
        
        //post url
        $this->pagedata['postUrl'] = $this->url .'&act=ajaxCanncelOrder';
        
        //check
        if(empty($shopList)){
            die('没有绑定唯品会店铺');
        }
        
        $this->display('admin/vop/download_datalist.html');
    }
    
    /**
     * ajax拉取平台已经成交的销售单
     */
    public function ajaxCanncelOrder()
    {
        $shopObj = app::get('ome')->model('shop');
        
        //check
        if(empty($_POST['shop_bn'])){
            $retArr['err_msg'] = array('请先选择店铺编码');
            echo json_encode($retArr);
            exit;
        }
        
        if(empty($_POST['startTime'])){
            $retArr['err_msg'] = array('请先选择开始时间');
            echo json_encode($retArr);
            exit;
        }
        
        $startTime = strtotime($_POST['startTime'].' 00:00:00');
        
        //check
        $checkTime = strtotime(date('Y-m-d', time()).' 00:00:00') - 86400 * 7;
        if($startTime < $checkTime){
            $retArr['err_msg'] = array('只支持拉取7天以内的数据');
            echo json_encode($retArr);
            exit;
        }
        
        //page
        $nextPage = isset($_POST['nextPage']) && $_POST['nextPage'] > 1 ? $_POST['nextPage'] : 1;
        
        //setting
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'total' => 0,
            'next_page' => 0,
            'err_msg' => array(),
        );
        
        //shop
        $sql = "SELECT shop_id,shop_bn,name,shop_type,node_id FROM sdb_ome_shop WHERE shop_type ='vop' AND shop_bn='". $_POST['shop_bn'] ."' AND node_id IS NOT NULL AND node_id != ''";
        $shopInfo = $shopObj->db->selectrow($sql);
        if(empty($shopInfo)){
            $retArr['err_msg'] = array('唯品会店铺不符合,无法拉取数据');
            echo json_encode($retArr);
            exit;
        }
        $shop_id = $shopInfo['shop_id'];
        
        //params
        $params = array(
            'shop_id' => $shop_id,
            'start_time' => $startTime,
            'end_time' => strtotime(date('Y-m-d', $startTime).' 23:59:59'),
            'page' => $nextPage,
        );
        
        //reuqest
        $result = $this->_jitOrderLib->getInventoryCancelledOrders($params);
        if($result['rsp'] != 'succ'){
            $error_msg = $result['error_msg'];
            $retArr['err_msg'] = array($error_msg);
        }else{
            if(isset($result['data']) && $result['data']){
                //继续拉取下一页
                if($result['data']['has_next']){
                    $nextPage++;
                }else{
                    $nextPage = 0;
                }
                
                $retArr['total'] = $result['data']['total_num']; //数据总记录数
                $retArr['itotal'] += $result['data']['current_num']; //本次拉取记录数
                $retArr['isucc'] += $result['data']['current_succ_num']; //处理成功记录数
                $retArr['ifail'] += $result['data']['current_fail_num']; //处理失败记录数
                $retArr['next_page'] = $nextPage; //下一页页码(如果为0则无需拉取)
            }else{
                $retArr['itotal'] += $result['current_num']; //本次拉取记录数
                $retArr['isucc'] += $result['current_succ_num']; //处理成功记录数
                $retArr['ifail'] += $result['current_fail_num']; //处理失败记录数
                $retArr['total'] = $result['total_num']; //数据总记录数
            }
        }
        
        echo json_encode($retArr);
        exit;
    }
    
    /**
     * 批量处理订单
     *
     * @return void
     */
    public function batchDispose()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        //post
        $ids = $_POST[$this->_primary_id];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($ids)){
            die('请选择需要操作的订单!');
        }
        
        if(count($ids) > 500){
            die('每次最多只能选择500条!');
        }
        
        //data
        $filter = [$this->_primary_id=>$ids, 'dispose_status'=>['none','running','fail']];
        $dataList = $this->_mdl->getList('id,order_sn,dispose_status', $filter, 0, -1);
        if(empty($dataList)){
            die('没有可操作的订单，请检查处理状态!');
        }
        
        $this->pagedata['GroupList'] = json_encode($ids);
        $this->pagedata['request_url'] = $this->url .'&act=ajaxDispose';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch($this->_appName . '_mdl_inventory_orders', false, 50, 'incr');
    }
    
    public function ajaxDispose()
    {
        $orderMdl = app::get('ome')->model('orders');
        $invOrderItemMdl = app::get('console')->model('inventory_order_items');
        
        //setting
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取查询条件
        parse_str($_POST['primary_id'], $postdata);
        
        //check
        if(empty($postdata['f'])) {
            echo 'Error: 请选择需要操作的订单!';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        //check
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data
        $orderList = $this->_mdl->getList('id,order_sn,shop_id,dispose_status', $filter, $offset, $limit);
        if(empty($orderList)){
            echo 'Error: 没有获取到订单';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($orderList);
        
        //id
        $ids = array_column($orderList, 'id');
        
        //list
        foreach ($orderList as $orderKey => $orderInfo)
        {
            $id = $orderInfo['id'];
            $order_sn = $orderInfo['order_sn'];
            
            //check
            if(in_array($orderInfo['dispose_status'], array('finish', 'needless'))){
                //fail
                $retArr['err_msg'][] = '订单：'. $order_sn .'，操作失败：状态不允许处理('. $orderInfo['dispose_status'] .')';
                $retArr['ifail'] += 1;
                
                continue;
            }
            
            //exec
            $paramsFilter = array('order_sn'=>$order_sn);
            $result = $this->_jitOrderLib->disposeInventoryOrders($paramsFilter);
            if($result['rsp'] != 'succ'){
                //fail
                $retArr['err_msg'][] = $result['error_msg'];
                $retArr['ifail'] += 1;
            }else{
                //succ
                $retArr['isucc'] += 1;
            }
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
}