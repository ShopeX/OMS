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
 * OMS普通商品推送给翱象系统
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 2023.03.08
 */
class dchain_ctl_admin_aoxiang_product extends desktop_controller
{
    var $title = '普通商品列表';
    var $workground = 'channel_center';
    
    private $_mdl = null; //model类
    private $_aoxiangLib = null; //Lib类
    private $_axProductLib = null; //Lib类
    
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    public function __construct($app)
    {
        parent::__construct($app);

        $this->_mdl = app::get('dchain')->model('aoxiang_product');

        $this->_aoxiangLib = kernel::single('dchain_aoxiang');
        $this->_axProductLib = kernel::single('dchain_product');

        $this->_primary_id = 'pid';
        $this->_primary_bn = 'product_bn';
    }
    
    public function index()
    {
        $actions = array();
        $base_filter = array('product_type'=>'normal');
        $finder_id = $_REQUEST['_finder']['finder_id'] ? $_REQUEST['_finder']['finder_id'] : substr(md5($_SERVER['QUERY_STRING']), 5, 6);
        $shop_id = $_GET['shop_id'];
        
        //action
        if($shop_id){
            $actions[] = array(
                'label' => '批量同步',
                'submit' => $this->url .'&act=batchSync&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:600,height:200,title:'批量对勾选的单据同步给翱象系统'}",
            );
            
            $actions[] = array(
                'label' => '批量关系同步',
                'submit' => $this->url .'&act=batchMappingSync&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:600,height:200,title:'批量对勾选的单据同步商品关系给翱象系统'}",
            );
            
            $actions[] = array(
                'label' => '批量解除关系',
                'submit' => $this->url .'&act=batchDeleteMapping&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:600,height:200,title:'批量对勾选的单据进行解除同步关系'}",
            );
            
            $actions[] = array(
                'label' => '批量删除',
                'submit' => $this->url .'&act=batchDelete&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:600,height:200,title:'批量对勾选的单据进行删除同步'}",
            );
            
            $actions[] = array(
                    'label' => '批量操作',
                    'group' => array(
                            array(
                                'label' => '一键同步商品',
                                'href' => $this->url .'&act=plusSync&view='. $_GET['view'] .'&p[0]='. $shop_id .'&finder_id='.$_GET['finder_id'],
                                'target' => "dialog::{width:600,height:350,title:'一键同步商品给翱象'}",
                            ),
                            array(
                                'label' => '一键同步商品关系',
                                'href' => $this->url .'&act=plusMapping&view='. $_GET['view'] .'&p[0]='. $shop_id .'&finder_id='.$_GET['finder_id'],
                                'target' => "dialog::{width:600,height:350,title:'一键同步商品关系给翱象'}",
                            ),
                            array(
                               'label' => '一键分配平台商品',
                               'href' => $this->url .'&act=batchApportion&view='. $_GET['view'] .'&p[0]='. $shop_id .'&finder_id='.$_GET['finder_id'],
                               'target' => "dialog::{width:600,height:350,title:'一键分配平台下载的店铺商品'}",
                            ),
                            array(
                                'label' => '强制解除关系',
                                'submit' => $this->url .'&act=batchCoerceMapping&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                                'target' => "dialog::{width:600,height:200,title:'批量对勾选的单据进行强制解除同步关系'}",
                            ),
                    ),
            );
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
        
        $this->finder('dchain_mdl_aoxiang_product', $params);
    }
    
    public function _views()
    {
        $base_filter = array('product_type'=>'normal');
        
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
        $id = $_GET['id'];
        $view = intval($_GET['view']);
        $shop_id = trim($_GET['shop_id']);
        $finder_id = $_REQUEST['finder_id'];
        $url = $this->url .'&act=index&shop_id='. $shop_id .'&view='. $view .'&finder_id='. $finder_id;
        
        //row
        $rowInfo = $this->_mdl->dump(array('pid'=>$id), '*');
        if(empty($rowInfo)){
            $this->splash('error', $url, '没有普通商品关联信息');
        }

        if(!in_array($rowInfo['sync_status'], array('none', 'fail', 'running'))){
            $this->splash('error', $url, '普通商品已经同步成功,不能重复同步');
        }

        $dataList = array($rowInfo);
        
        //同步
        $operation = 'manual'; //手工操作标记
        $result = $this->_axProductLib->syncNormalProduct($dataList, $operation);
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
        
        $ids = $_POST['pid'];
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
        
        $this->pagedata['request_url'] = $this->url .'&act=ajaxSync&view='.$_REQUEST['view'].'&finder_id='.$_REQUEST['finder_id'];
        
        //调用desktop公用进度条(第4个参数是增量传incr,否则默认一直为0)
        parent::dialog_batch('dchain_mdl_aoxiang_product', false, 50, 'incr');
    }
    
    /**
     * ajax批量同步
     */
    public function ajaxSync()
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
        
        $filter['product_type'] = 'normal';
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到数据';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //list
        foreach ($dataList as $key => $row)
        {
            if(!in_array($row['sync_status'], array('none', 'fail', 'running'))){
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = '单据：'. $row['product_bn'] .'已经同步成功,不允许重复获取';
                
                //unset
                unset($dataList[$key]);
                
                continue;
            }
        }

        //check
        if(empty($dataList)){
            echo 'Error: 没有需要同步的单据列表';
            exit;
        }
        
        //支持批量同步(每次最多50条)
        $operation = 'manual'; //手工操作标记
        $result = $this->_axProductLib->syncNormalProduct($dataList, $operation);
        if($result['rsp'] == 'succ'){
            //succ
            $retArr['isucc'] += count($dataList);
        }else{
            //fail
            $retArr['ifail'] += count($dataList);
            
            $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
            $retArr['err_msg'][] = '本次'. count($dataList) .'条单据,同步失败：'. $error_msg;
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 批量对勾选的单据删除并取消绑定关系
     */
    public function batchDelete()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $ids = $_POST['pid'];
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
        
        $this->pagedata['request_url'] = $this->url .'&act=ajaxDelSync&view='.$_REQUEST['view'].'&finder_id='.$_REQUEST['finder_id'];
        
        //调用desktop公用进度条(第4个参数是增量传incr,否则默认一直为0)
        parent::dialog_batch('dchain_mdl_aoxiang_product', false, 50, 'incr');
    }
    
    /**
     * ajax批量删除并取消同步关系
     */
    public function ajaxDelSync()
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
        
        $filter['mapping_status'] = array('none', 'fail', 'running');
        $filter['product_type'] = 'normal';
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到可删除的商品(请先解除商品关系)';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //check
        if(empty($dataList)){
            echo 'Error: 没有需要同步的单据';
            exit;
        }
        
        //支持批量同步(每次最多50条)
        $operation = 'manual'; //手工操作标记
        $result = $this->_axProductLib->deleteProduct($dataList, $operation);
        if($result['rsp'] == 'succ'){
            //succ
            $retArr['isucc'] += count($dataList);
        }else{
            //fail
            $retArr['ifail'] += count($dataList);
            
            $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
            $retArr['err_msg'][] = '本次'. count($dataList) .'条单据,删除失败：'. $error_msg;
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 单个关系同步
     *
     * @return array
     */
    public function single_mapping_sync()
    {
        $id = $_GET['id'];
        $view = intval($_GET['view']);
        $shop_id = trim($_GET['shop_id']);
        $finder_id = $_REQUEST['finder_id'];
        $url = $this->url .'&act=index&shop_id='. $shop_id .'&view='. $view .'&finder_id='. $finder_id;
        
        //row
        $rowInfo = $this->_mdl->dump(array('pid'=>$id, 'product_type'=>'normal'), '*');
        if(empty($rowInfo)){
            $this->splash('error', $url, '没有普通商品关联信息');
        }
        
        if(!in_array($rowInfo['sync_status'], array('succ')) || in_array($rowInfo['mapping_status'], array('succ'))){
            $this->splash('error', $url, '普通商品同步状态,不允许同步商品关系,请检查!');
        }
        
        if(empty($rowInfo['shop_iid']) || $rowInfo['sync_status']=='lack'){
            //update
            $updateData = array('mapping_status'=>'invalid', 'last_modified'=>time());
            $this->_mdl->update($updateData, array('pid'=>$id));

            $this->splash('error', $url, '普通商品没有关联平台店铺的商品ID,不能同步!');
        }
        
        $dataList = array($rowInfo);
        
        //同步
        $operation = 'manual'; //手工操作标记
        $result = $this->_axProductLib->syncMappingProduct($dataList, $operation);
        if($result['rsp'] != 'succ'){
            $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
            $this->splash('error', $url, '映射关系失败：'. $error_msg);
        }
        
        $this->splash('success', $url);
    }
    
    /**
     * 批量对勾选的单据进行同步商品关系
     */
    public function batchMappingSync()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $ids = $_POST['pid'];
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
        
        $this->pagedata['request_url'] = $this->url .'&act=ajaxMappingSync&view='.$_REQUEST['view'].'&finder_id='.$_REQUEST['finder_id'];
        
        //调用desktop公用进度条(第4个参数是增量传incr,否则默认一直为0)
        parent::dialog_batch('dchain_mdl_aoxiang_product', false, 50, 'incr');
    }
    
    /**
     * ajax批量同步
     */
    public function ajaxMappingSync()
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
        
        $filter['product_type'] = 'normal';
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到可操作的数据';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //list
        $invalidList = array();
        foreach ($dataList as $key => $row)
        {
            $product_bn = $row['product_bn'];

            if(!in_array($row['sync_status'], array('succ')) || in_array($row['mapping_status'], array('succ'))){
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = '单据：'. $row['product_bn'] .'同步状态,不允许同步商品关系,请检查!';
                
                //unset
                unset($dataList[$key]);
                
                continue;
            }
            
            if(empty($row['shop_iid']) || $row['sync_status']=='lack'){
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = '单据：'. $row['product_bn'] .'普通商品没有关联平台店铺的商品ID,不能同步';
                
                //unset
                unset($dataList[$key]);

                //无效的商品关系
                $invalidList[$product_bn] = $product_bn;

                continue;
            }
        }

        //更新无效的商品关系
        if($invalidList){
            //update
            $updateData = array('mapping_status'=>'invalid', 'last_modified'=>time());
            $this->_mdl->update($updateData, array('product_bn'=>$invalidList));
        }

        //check
        if(empty($dataList)){
            echo 'Error: 没有需要同步的数据';
            exit;
        }

        //支持批量同步(每次最多50条)
        $operation = 'manual'; //手工操作标记
        $result = $this->_axProductLib->syncMappingProduct($dataList, $operation);
        if($result['rsp'] == 'succ'){
            //succ
            $retArr['isucc'] += count($dataList);
        }else{
            //fail
            $retArr['ifail'] += count($dataList);
            
            $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
            $retArr['err_msg'][] = '本次'. count($dataList) .'条单据,映射关系失败：'. $error_msg;
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 批量对勾选的单据删除同步关系
     */
    public function batchDeleteMapping()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $ids = $_POST['pid'];
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
        
        $this->pagedata['request_url'] = $this->url .'&act=ajaxDelMappingSync&view='.$_REQUEST['view'].'&finder_id='.$_REQUEST['finder_id'];
        
        //调用desktop公用进度条(第4个参数是增量传incr,否则默认一直为0)
        parent::dialog_batch('dchain_mdl_aoxiang_product', false, 50, 'incr');
    }
    
    /**
     * ajax批量删除并取消同步关系
     */
    public function ajaxDelMappingSync()
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
    
        $filter['mapping_status'] = array('succ', 'splitting');
        $filter['product_type'] = 'normal';
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到可删除的数据';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //check
        if(empty($dataList)){
            echo 'Error: 没有需要同步的单据';
            exit;
        }
        
        //支持批量同步(每次最多50条)
        $operation = 'manual'; //手工操作标记
        $result = $this->_axProductLib->deleteProductMapping($dataList, $operation);
        if($result['rsp'] == 'succ'){
            //succ
            $retArr['isucc'] += count($dataList);
        }else{
            //fail
            $retArr['ifail'] += count($dataList);
            
            $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
            $retArr['err_msg'][] = '本次'. count($dataList) .'条单据,解除关系失败：'. $error_msg;
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 批量对勾选的单据删除同步关系
     */
    public function batchCoerceMapping()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $ids = $_POST['pid'];
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
        
        $this->pagedata['request_url'] = $this->url .'&act=ajaxCoerceMapping&view='.$_REQUEST['view'].'&finder_id='.$_REQUEST['finder_id'];
        
        //调用desktop公用进度条(第4个参数是增量传incr,否则默认一直为0)
        parent::dialog_batch('dchain_mdl_aoxiang_product', false, 50, 'incr');
    }
    
    /**
     * ajax批量删除并取消同步关系
     */
    public function ajaxCoerceMapping()
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
        
        $filter['mapping_status'] = array('succ', 'splitting');
        $filter['product_type'] = 'normal';
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到可删除的数据';
            exit;
        }
        
        $mids = array_column($dataList, 'pid');
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //check
        if(empty($dataList)){
            echo 'Error: 没有需要同步的单据';
            exit;
        }
        
        //支持批量同步(每次最多50条)
        $operation = 'manual'; //手工操作标记
        $result = $this->_axProductLib->deleteProductMapping($dataList, $operation);
        if($result['rsp'] == 'succ'){
            //succ
            $retArr['isucc'] += count($dataList);
        }else{
            //fail
            $retArr['isucc'] += count($dataList);
            
            //$retArr['ifail'] += count($dataList);
            //$error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
            //$retArr['err_msg'][] = '本次'. count($dataList) .'条单据,强制删除关系失败：'. $error_msg;
        }
        
        //update
        $updateData = array('mapping_status'=>'none', 'last_modified'=>time());
        $this->_mdl->update($updateData, array('pid'=>$mids));
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 一键分配平台下载的店铺商品
     * @param $shop_id
     * @return void
     */
    public function batchApportion($shop_id)
    {
        $shopMdl = app::get('ome')->model('shop');

        //shop_id
        if(empty($shop_id)){
            die('无效的操作');
        }
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')!';
            die($error_msg);
        }
        
        if($shopInfo['aoxiang_signed'] != '1'){
            $error_msg = '店铺没有签约翱象(shop_id：'. $shop_id .')!';
            die($error_msg);
        }
        
        //开始时间(默认为三天前)
        $start_time = date('Y-m-d', strtotime('-3 day'));

        $this->pagedata['start_time'] = $start_time;
        $this->pagedata['shopList'] = array($shopInfo);

        $this->display('aoxiang/batch_apportion_product.html');
    }

    /**
     * Ajax一键分配商品
     * @return void
     */
    public function ajaxApportion()
    {
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'total' => 0,
            'err_msg' => array(),
        );

        //page
        $page = isset($_POST['nextPage']) && $_POST['nextPage'] > 1 ? $_POST['nextPage'] : 1;

        //channel_id
        $shop_id = $_POST['shop_id'];

        //check
        if (empty($shop_id)) {
            $retArr['err_msg'] = array('没有选择店铺');
            echo json_encode($retArr);
            exit;
        }
        
        //params
        $params = array(
            'shop_id' => $shop_id,
            'start_time' => trim($_POST['startTime']), //开始时间(年-月-日)
        );
        
        //Apportion
        $result = $this->batchApportionProduct($params, $page);
        if ($result['rsp'] == 'succ') {
            $retArr['itotal'] += $result['succ']; //已处理的
            $retArr['ifail'] += $result['fail']; //失败的
            $retArr['total'] = $result['all']; //总数
            
            //next
            $retArr['next_page'] = $result['next_page'];
        } else {
            $retArr['err_msg'] = array($result['err_msg']);
        }
        
        echo json_encode($retArr);
        exit;
    }
    
    /**
     * 手工分配普通商品给翱象队列任务
     *
     * @param array $params
     * @param int $page
     */
    public function batchApportionProduct($params, $page)
    {
        $skuMdl = app::get('inventorydepth')->model('shop_skus');
        $shopMdl = app::get('ome')->model('shop');
        $queueMdl = app::get('base')->model('queue');
        
        //setting
        $limit = 500;
        $result = array('rsp'=>'fail', 'succ'=>0, 'fail'=>0, 'all'=>0, 'err_msg'=>'');
        
        //wms_id
        $shop_id = $params['shop_id'];
        if(empty($shop_id)){
            $result['err_msg'] = '没有店铺ID无效操作';
            $result['next_page'] = 0;
            return $result;
        }
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), 'shop_id,shop_bn,aoxiang_signed');
        if($shopInfo['aoxiang_signed'] != '1'){
            $result['err_msg'] = '店铺没有签约翱象(shop_id：'. $shop_id .')!';
            $result['next_page'] = 0;
            return $result;
        }
        $shop_bn = $shopInfo['shop_bn'];
        
        //创建时间(年-月-日)
        $start_time = strtotime($params['start_time'].' 00:00:00');
        
        //filter
        $filter = array('shop_id'=>$shop_id, 'outer_createtime|than'=>$start_time);
        
        //分页处理
        $offset = ($page - 1) * $limit;
        
        //count
        $totalCount = $skuMdl->count($filter);
        if(empty($totalCount)){
            $result['next_page'] = 0;
            return $result;
        }
        
        //check
        if($totalCount > 100000){
            $result['err_msg'] = '超过了10万条数据，无法手工执行';
            $result['next_page'] = 0;
            return $result;
        }
        
        $result['all'] = $totalCount;
        
        //list
        $dataList = $skuMdl->getList('id,shop_product_bn,bind', $filter, $offset, $limit, 'outer_createtime ASC');
        if(empty($dataList)){
            $result['next_page'] = 0;
            return $result;
        }

        //setting
        $result['rsp'] = 'succ';
        $result['next_page'] = $page + 1;

        //list
        $normalList = array();
        $combineList = array();
        foreach ($dataList as $rowKey => $rowVal)
        {
            $shop_product_bn = $rowVal['shop_product_bn'];
            $is_bind = $rowVal['bind'];
            
            //check
            if(empty($shop_product_bn)){
                continue;
            }
            
            //type
            if($is_bind == '1'){
                $combineList[$shop_product_bn] = 1;
            }else{
                $normalList[$shop_product_bn] = 1;
            }

            //succ
            $result['succ']++;
        }
        
        //普通商品
        if($normalList){
            //sdfdata
            $sdfData = array(
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'product_bns' => array_keys($normalList),
                'task_page' => $page,
            );
            
            //手工分配普通商品给翱象队列任务
            $queueData = array(
                'queue_title' => '手工分配普通商品给翱象队列任务'. $page,
                'start_time' => time(),
                'params' => array(
                    'sdfdata' => $sdfData,
                    'app' => 'dchain',
                    'mdl' => 'aoxiang_product',
                ),
                'worker'=> 'dchain_inventorydepth.autoDispatchNormalProduct',
            );
            $queueMdl->save($queueData);
        }
        
        //组合商品
        if($combineList){
            //sdfdata
            $sdfData = array(
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'product_bns' => array_keys($combineList),
                'task_page' => $page,
            );
            
            //手工分配组合商品给翱象队列任务
            $queueData = array(
                'queue_title' => '手工分配组合商品给翱象队列任务'. $page,
                'start_time' => time(),
                'params' => array(
                    'sdfdata' => $sdfData,
                    'app' => 'dchain',
                    'mdl' => 'aoxiang_product',
                ),
                'worker'=> 'dchain_inventorydepth.autoDispatchCombineProduct',
            );
            $queueMdl->save($queueData);
        }
        
        //sleep
        sleep(1);
        
        return $result;
    }

    /**
     * 一键同步商品给翱象
     * @param $shop_id
     * @return void
     */
    public function plusSync($shop_id)
    {
        $shopMdl = app::get('ome')->model('shop');

        //shop_id
        if(empty($shop_id)){
            die('无效的操作');
        }

        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')!';
            die($error_msg);
        }

        if($shopInfo['aoxiang_signed'] != '1'){
            $error_msg = '店铺没有签约翱象(shop_id：'. $shop_id .')!';
            die($error_msg);
        }

        //syncStatus
        $syncStatus = array(
            'none' => array('type'=>'none', 'name'=>'未同步'),
            'running' => array('type'=>'running', 'name'=>'同步中'),
        );

        //开始时间(默认为一周前)
        $start_time = date('Y-m-d', strtotime('-7 day'));

        $this->pagedata['start_time'] = $start_time;
        $this->pagedata['shopList'] = array($shopInfo);
        $this->pagedata['syncStatus'] = $syncStatus;

        $this->display('aoxiang/batch_plus_sync_product.html');
    }

    /**
     * Ajax一键同步商品
     * @return void
     */
    public function ajaxPlusSync()
    {
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'total' => 0,
            'err_msg' => array(),
        );

        //page
        $page = isset($_POST['nextPage']) && $_POST['nextPage'] > 1 ? $_POST['nextPage'] : 1;

        //channel_id
        $shop_id = $_POST['shop_id'];
        $sync_status = $_POST['sync_status'];

        //check
        if (empty($shop_id)) {
            $retArr['err_msg'] = array('没有选择店铺');
            echo json_encode($retArr);
            exit;
        }

        if (empty($sync_status)) {
            $retArr['err_msg'] = array('没有选择同步状态');
            echo json_encode($retArr);
            exit;
        }

        //params
        $params = array(
            'shop_id' => $shop_id,
            'sync_status' => $sync_status,
            'start_time' => trim($_POST['startTime']), //开始时间(年-月-日)
        );

        //Apportion
        $result = $this->plusSyncProduct($params, $page);
        if ($result['rsp'] == 'succ') {
            $retArr['itotal'] += $result['succ']; //已处理的
            $retArr['ifail'] += $result['fail']; //失败的
            $retArr['total'] = $result['all']; //总数

            //next
            $retArr['next_page'] = $result['next_page'];
        } else {
            $retArr['err_msg'] = array($result['err_msg']);
        }

        echo json_encode($retArr);
        exit;
    }
    
    
    
    
    
    
    
    /**
     * 手工指定普通商品同步翱象队列任务
     *
     * @param array $params
     * @param int $page
     */
    public function plusSyncProduct($params, $page)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $queueMdl = app::get('base')->model('queue');

        //setting
        $limit = 500;
        $result = array('rsp'=>'fail', 'succ'=>0, 'fail'=>0, 'all'=>0, 'err_msg'=>'');

        //wms_id
        $shop_id = $params['shop_id'];
        if(empty($shop_id)){
            $result['err_msg'] = '没有店铺ID无效操作';
            $result['next_page'] = 0;
            return $result;
        }

        //同步状态
        $sync_status = $params['sync_status'];

        //创建时间(年-月-日)
        //$start_time = strtotime($params['start_time'].' 00:00:00');

        //filter
        $filter = array('shop_id'=>$shop_id, 'sync_status'=>$sync_status, 'product_type'=>'normal');

        //分页处理
        $offset = ($page - 1) * $limit;

        //count
        $totalCount = $axProductMdl->count($filter);
        if(empty($totalCount)){
            $result['next_page'] = 0;
            return $result;
        }

        $result['all'] = $totalCount;

        //list
        $dataList = $axProductMdl->getList('pid,product_bn', $filter, $offset, $limit, 'create_time ASC');
        if(empty($dataList)){
            $result['next_page'] = 0;
            return $result;
        }

        //setting
        $result['rsp'] = 'succ';
        $result['next_page'] = $page + 1;

        //succ
        $result['succ'] += count($dataList);

        //product_bn
        $product_bns = array_column($dataList, 'product_bn');

        //sdfData
        $sdfData = array(
            'shop_id' => $shop_id,
            'product_bns' => $product_bns,
            'task_page' => $page,
        );
        
        //手工指定普通商品同步翱象队列任务
        $queueData = array(
            'queue_title' => '手工指定普通商品同步翱象任务'. $page,
            'start_time' => time(),
            'params' => array(
                'sdfdata' => $sdfData,
                'app' => 'dchain',
                'mdl' => 'aoxiang_product',
            ),
            'worker'=> 'dchain_product.assignNormalProduct',
        );
        
        $queueMdl->save($queueData);
        
        //sleep
        sleep(2);

        return $result;
    }

    /**
     * 一键同步商品关系给翱象
     * @param $shop_id
     * @return void
     */
    public function plusMapping($shop_id)
    {
        $shopMdl = app::get('ome')->model('shop');

        //shop_id
        if(empty($shop_id)){
            die('无效的操作');
        }

        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')!';
            die($error_msg);
        }

        if($shopInfo['aoxiang_signed'] != '1'){
            $error_msg = '店铺没有签约翱象(shop_id：'. $shop_id .')!';
            die($error_msg);
        }

        //统一更新为无效的商品
        $upate_sql = "UPDATE sdb_dchain_aoxiang_product SET mapping_status='invalid' WHERE shop_id='". $shop_id ."' AND sync_status='lack'";
        $shopMdl->db->exec($upate_sql);

        //统一更新shop_iid的商品为
        $upate_sql = "UPDATE sdb_dchain_aoxiang_product SET mapping_status='invalid' WHERE shop_id='". $shop_id ."' AND sync_status='succ' AND mapping_status='none' AND shop_iid is null";
        $shopMdl->db->exec($upate_sql);

        //统一更新shop_iid的商品为
        $upate_sql = "UPDATE sdb_dchain_aoxiang_skus SET mapping_status='invalid' WHERE shop_id='". $shop_id ."' AND mapping_status='none' AND shop_iid is null";
        $shopMdl->db->exec($upate_sql);

        //syncStatus
        $syncStatus = array(
            'none' => array('type'=>'none', 'name'=>'未关联'),
            'running' => array('type'=>'running', 'name'=>'关联中'),
        );

        //开始时间(默认为一周前)
        $start_time = date('Y-m-d', strtotime('-7 day'));

        $this->pagedata['start_time'] = $start_time;
        $this->pagedata['shopList'] = array($shopInfo);
        $this->pagedata['syncStatus'] = $syncStatus;

        $this->display('aoxiang/batch_plus_mapping_product.html');
    }

    /**
     * Ajax一键分配普通商品
     *
     * @return void
     */
    public function ajaxPlusMapping()
    {
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'total' => 0,
            'err_msg' => array(),
        );

        //page
        $page = isset($_POST['nextPage']) && $_POST['nextPage'] > 1 ? $_POST['nextPage'] : 1;

        //channel_id
        $shop_id = $_POST['shop_id'];
        $sync_status = $_POST['sync_status'];

        //check
        if (empty($shop_id)) {
            $retArr['err_msg'] = array('没有选择店铺');
            echo json_encode($retArr);
            exit;
        }

        if (empty($sync_status)) {
            $retArr['err_msg'] = array('没有选择商品关系状态');
            echo json_encode($retArr);
            exit;
        }

        //params
        $params = array(
            'shop_id' => $shop_id,
            'sync_status' => $sync_status,
            'start_time' => trim($_POST['startTime']), //开始时间(年-月-日)
        );

        //Apportion
        $result = $this->plusMappingProduct($params, $page);
        if ($result['rsp'] == 'succ') {
            $retArr['itotal'] += $result['succ']; //已处理的
            $retArr['ifail'] += $result['fail']; //失败的
            $retArr['total'] = $result['all']; //总数

            //next
            $retArr['next_page'] = $result['next_page'];
        } else {
            $retArr['err_msg'] = array($result['err_msg']);
        }

        echo json_encode($retArr);
        exit;
    }

    /**
     * 手工指定普通商品同步商品关系给翱象队列任务
     *
     * @param array $params
     * @param int $page
     */
    public function plusMappingProduct($params, $page)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $queueMdl = app::get('base')->model('queue');

        //setting
        $limit = 500;
        $result = array('rsp'=>'fail', 'succ'=>0, 'fail'=>0, 'all'=>0, 'err_msg'=>'');

        //wms_id
        $shop_id = $params['shop_id'];
        if(empty($shop_id)){
            $result['err_msg'] = '没有店铺ID无效操作';
            $result['next_page'] = 0;
            return $result;
        }

        //商品关系状态
        $mapping_status = $params['sync_status'];

        //创建时间(年-月-日)
        //$start_time = strtotime($params['start_time'].' 00:00:00');

        //filter
        $filter = array('shop_id'=>$shop_id, 'sync_status'=>'succ', 'mapping_status'=>$mapping_status);

        //分页处理
        $offset = ($page - 1) * $limit;

        //count
        $totalCount = $axProductMdl->count($filter);
        if(empty($totalCount)){
            $result['next_page'] = 0;
            return $result;
        }

        $result['all'] = $totalCount;

        //list
        $dataList = $axProductMdl->getList('pid,product_bn', $filter, $offset, $limit, 'create_time ASC');
        if(empty($dataList)){
            $result['next_page'] = 0;
            return $result;
        }

        //setting
        $result['rsp'] = 'succ';
        $result['next_page'] = $page + 1;

        //succ
        $result['succ'] += count($dataList);

        //product_bn
        $product_bns = array_column($dataList, 'product_bn');

        //queue data
        $queueSdf = array(
            'shop_id' => $shop_id,
            'product_bns' => $product_bns,
            'task_page' => $page,
        );

        //指定普通商品同步关系任务
        $queueData = array(
            'queue_title' => '指定普通商品同步关系任务'.$page,
            'start_time' => time(),
            'params' => array(
                'sdfdata' => $queueSdf,
                'app' => 'dchain',
                'mdl' => 'aoxiang_product',
            ),
            'worker'=> 'dchain_product.assignMappingProduct',
        );
        $queueMdl->save($queueData);

        //sleep
        sleep(2);

        return $result;
    }
}