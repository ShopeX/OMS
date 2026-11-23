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
 * OMS发货单推送给翱象系统
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 2023.03.04
 */
class dchain_ctl_admin_aoxiang_delivery extends desktop_controller
{
    var $title = '发货单列表';
    var $workground = 'channel_center';
    
    private $_mdl = null; //model类
    private $_aoxiangLib = null; //Lib类
    private $_deliveryLib = null; //Lib类
    
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get('dchain')->model('aoxiang_delivery');
        $this->_aoxiangLib = kernel::single('dchain_aoxiang');
        $this->_deliveryLib = kernel::single('dchain_delivery');
        
        $this->_primary_id = 'did';
        $this->_primary_bn = 'delivery_bn';
    }
    
    public function index()
    {
        $actions = array();
        $base_filter = array();
        $finder_id = $_REQUEST['_finder']['finder_id'] ? $_REQUEST['_finder']['finder_id'] : substr(md5($_SERVER['QUERY_STRING']), 5, 6);
        $shop_id = $_GET['shop_id'];
        
        //action
        if($shop_id){
            $actions[0] = array(
                'label' => '批量同步发货单',
                'submit' => $this->url .'&act=batchSync&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:600,height:200,title:'批量对勾选的单据同步给翱象系统'}",
            );

            /***
            $actions[1] = array(
                'label' => '批量取消发货单',
                'submit' => $this->url .'&act=batchCancel&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:600,height:200,title:'批量对勾选的单据进行请求取消'}",
            );
            ***/
        }
        
        //params
        $orderby = $this->_primary_id .' DESC';
        $params = array(
            'title' => $this->title,
            'base_filter' => $base_filter,
            'actions'=> $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => $orderby,
        );
        
        $this->finder('dchain_mdl_aoxiang_delivery', $params);
    }
    
    public function _views()
    {
        $base_filter = array();
        $sub_menu = array(
            array('label'=>'全部', 'filter'=>$base_filter, 'optional'=>false),
        );
        
        $shopList = $this->_aoxiangLib->getSignedShops();
        foreach ((array)$shopList as $key => $val)
        {
            $filter = $base_filter;
            $filter['shop_id'] = $val['shop_id'];
            
            $sub_menu[] = array('label'=>$val['name'], 'filter'=>$filter, 'optional'=>false, 'shop_id'=>$val['shop_id']);
        }
        
        //menu
        foreach($sub_menu as $k => $v)
        {
            if (!is_null($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            
            $viewcount = $this->_mdl->count($v['filter']);
            $sub_menu[$k]['addon'] = ($viewcount >= 1 ? $viewcount : '_FILTER_POINT_');
            
            $sub_menu[$k]['href'] = $this->url .'&act='.$_GET['act'].'&flt='.$_GET['flt'].'&view='.$k . '&shop_id='. $v['shop_id'];
        }
        
        return $sub_menu;
    }

    /**
     * 单个同步
     *
     * @return array
     */
    public function single_sync()
    {
        $deliveryObj = app::get('ome')->model('delivery');

        $id = intval($_GET['id']);
        $view = intval($_GET['view']);
        $shop_id = trim($_GET['shop_id']);
        $finder_id = $_REQUEST['finder_id'];
        $url = $this->url .'&act=index&shop_id='. $shop_id .'&view='. $view .'&finder_id='. $finder_id;
        
        //row
        $rowInfo = $this->_mdl->dump(array($this->_primary_id=>$id), '*');
        if(empty($rowInfo)){
            $this->splash('error', $url, '没有发货单关联信息');
        }

        $delivery_id = $rowInfo['delivery_id'];

        //deliveryInfo
        $deliveryList = $deliveryObj->getList('*', array('delivery_id'=>$delivery_id));
        $deliveryInfo = $deliveryList[0];

        //同步
        $operation = 'manual'; //手工操作标记
        $result = $this->_deliveryLib->syncDelivery($deliveryInfo, $operation);
        if($result['rsp'] != 'succ'){
            $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
            $this->splash('error', $url, '同步失败：'. $error_msg);
        }
        
        $this->splash('success', $url);
    }
    
    /**
     * 批量对勾选的单据进行同步
     */
    public function batchSync()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $ids = $_POST[$this->_primary_id];
        if(empty($ids)){
            die('请选择需要操作的记录!');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(count($ids) > 50){
            die('每次最多只能选择50条!');
        }
        
        $this->pagedata['GroupList'] = json_encode($ids);
        
        $this->pagedata['request_url'] = $this->url .'&act=ajaxSync&view='.$_REQUEST['view'].'&finder_id='.$_REQUEST['finder_id'];
        
        //调用desktop公用进度条(第4个参数是增量传incr,否则默认一直为0)
        parent::dialog_batch('dchain_mdl_aoxiang_delivery', false, 50, 'incr');
    }
    
    /**
     * ajax批量同步
     */
    public function ajaxSync()
    {
        $deliveryObj = app::get('ome')->model('delivery');

        //ret
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取单据
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择操作的单据';
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
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到数据';
            exit;
        }

        //count
        $retArr['itotal'] = count($dataList);

        //delivery_ids
        $delivery_ids = array_column($dataList, 'delivery_id');

        //delivery
        $deliveryList = $deliveryObj->getList('*', array('delivery_id'=>$delivery_ids));
        $deliveryList = array_column($deliveryList, null, 'delivery_id');

        //list
        $operation = 'manual'; //手工操作标记
        foreach ($dataList as $key => $row)
        {
            $delivery_id = $row['delivery_id'];

            //check
            if(!in_array($row['sync_status'], array('none', 'fail'))){
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = '单据：'. $row['delivery_bn'] .'已经同步成功,不允许重复获取';
                
                //unset
                unset($dataList[$key]);
                
                continue;
            }

            //deliveryInfo
            $deliveryInfo = $deliveryList[$delivery_id];

            //request
            $result = $this->_deliveryLib->syncDelivery($deliveryInfo, $operation);
            if($result['rsp'] == 'succ'){
                //succ
                $retArr['isucc'] += 1;
            }else{
                //fail
                $retArr['ifail'] += 1;

                $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
                $retArr['err_msg'][] = '发货单号：'. $row['delivery_bn'] .'条单据,同步失败：'. $error_msg;
            }
        }

        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 批量对勾选的单据取消
     */
    public function batchCancel()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);

        $ids = $_POST[$this->_primary_id];
        if(empty($ids)){
            die('请选择需要操作的记录!');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(count($ids) > 500){
            die('每次最多只能选择500条!');
        }
        
        $this->pagedata['GroupList'] = json_encode($ids);
        
        $this->pagedata['request_url'] = $this->url .'&act=ajaxCancelSync&view='.$_REQUEST['view'].'&finder_id='.$_REQUEST['finder_id'];
        
        //调用desktop公用进度条(第4个参数是增量传incr,否则默认一直为0)
        parent::dialog_batch('dchain_mdl_aoxiang_delivery', false, 50, 'incr');
    }
    
    /**
     * ajax批量删除并解绑同步关系
     */
    public function ajaxCancelSync()
    {
        //ret
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取单据
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择操作的单据';
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
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到数据';
            exit;
        }

        //count
        $retArr['itotal'] = count($dataList);

        //list
        $operation = 'manual'; //手工操作标记
        foreach ($dataList as $key => $row)
        {
            if(!in_array($row['sync_status'], array('succ', 'cancel_fail'))){
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = '单据：'. $row['delivery_bn'] .'状态,不允许请求取消';

                //unset
                unset($dataList[$key]);

                continue;
            }

            //request
            $result = $this->_deliveryLib->syncCancelDelivery($row, $operation);
            if($result['rsp'] == 'succ'){
                //succ
                $retArr['isucc'] += 1;
            }else{
                //fail
                $retArr['ifail'] += 1;

                $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
                $retArr['err_msg'][] = '发货单号：'. $row['delivery_bn'] .'条单据,请求取消失败：'. $error_msg;
            }
        }

        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
}