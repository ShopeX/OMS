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
 * OMS物流公司推送给翱象系统
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 2023.01.05
 */
class dchain_ctl_admin_aoxiang_logistics extends desktop_controller
{
    var $title = '物流公司列表';
    var $workground = 'channel_center';
    
    private $_mdl = null; //model类
    private $_aoxiangLib = null; //Lib类
    
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get('dchain')->model('aoxiang_logistics');
        $this->_aoxiangLib = kernel::single('dchain_aoxiang');
        
        $this->_primary_id = 'lid';
        $this->_primary_bn = 'logi_code';
    }
    
    public function index()
    {
        $actions = array();
        $base_filter = array();
        $finder_id = $_REQUEST['_finder']['finder_id'] ? $_REQUEST['_finder']['finder_id'] : substr(md5($_SERVER['QUERY_STRING']), 5, 6);
        $shop_id = $_GET['shop_id'];
        
        //action
        if($shop_id){
            $logi_url = 'index.php?app=desktop&act=alertpages&goto='. urlencode($this->url .'&act=findLogistics&shop_id='. $shop_id);
            
            $actions[0] = array(
                'label' => '物流公司分配',
                'onclick' => <<<JS
                javascript:Ex_Loader('modedialog',function(){new finderDialog('{$logi_url}',{
                    params:{url:'{$this->url}&act=handwork_allot',name:'corp_id[]', postdata:'shop_id=$shop_id'},
                    onCallback:function(rs){ MessageBox.success('分配成功'); window.finderGroup['{$finder_id}'].refresh(); }
                });});
JS
            );
            
            $actions[1] = array(
                'label' => '批量同步物流公司',
                'submit' => $this->url .'&act=batchSync&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:600,height:200,title:'批量对勾选的单据同步给翱象系统'}",
            );
            
            $actions[2] = array(
                'label' => '批量删除物流公司',
                'submit' => $this->url .'&act=batchDelete&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:600,height:200,title:'批量对勾选的单据进行删除同步'}",
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
        
        $this->finder('dchain_mdl_aoxiang_logistics', $params);
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
     * 选择物流公司列表
     */
    public function findLogistics()
    {
        $this->view_source = 'dialog';
        
        $shop_id = $_GET['shop_id'];
        
        //filter
        $base_filter = array(
            'disabled' => 'false',
        );
        
        $params = array(
            'title' => '物流公司列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'base_filter' => $base_filter,
            'orderBy' => 'corp_id DESC',
        );
        
        $this->finder('ome_mdl_dly_corp', $params);
    }
    
    public function handwork_allot()
    {
        $corpIds = $_POST['corp_id'];
        $shop_id = $_POST['shop_id'];
        
        //check
        if(empty($corpIds)){
            $this->splash('error', null, '请先选择物流公司');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->splash('error', null, '不支持全选');
        }
        
        if(empty($shop_id)){
            $this->splash('error', null, '无效操作,没有关联的店铺');
        }
        
        //添加映射
        $error_msg = '';
        $result = $this->_aoxiangLib->addAoxiangLogistics($corpIds, $shop_id, $error_msg);
        if(!$result){
            $this->splash('error', null, $error_msg);
        }
        
        $this->splash();
    }
    
    /**
     * 单个同步
     *
     * @return array
     */
    public function single_sync()
    {
        $id = intval($_GET['id']);
        $view = intval($_GET['view']);
        $shop_id = trim($_GET['shop_id']);
        $finder_id = $_REQUEST['finder_id'];
        $url = $this->url .'&act=index&shop_id='. $shop_id .'&view='. $view .'&finder_id='. $finder_id;
        
        //row
        $rowInfo = $this->_mdl->dump(array('lid'=>$id), '*');
        if(empty($rowInfo)){
            $this->splash('error', $url, '没有物流公司关联信息');
        }
        
        $dataList = array($rowInfo);
        
        //同步
        $operation = 'manual'; //手工操作标记
        $result = $this->_aoxiangLib->syncLogistics($dataList, $operation);
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
        
        $ids = $_POST['lid'];
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
        parent::dialog_batch('dchain_mdl_aoxiang_logistics', false, 50, 'incr');
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
            if($row['sync_status'] == 'succ'){
                //error
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = '单据：'. $row['logi_code'] .'已经同步成功,不允许重复获取';
                
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
        $result = $this->_aoxiangLib->syncLogistics($dataList, $operation);
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
        
        $ids = $_POST['lid'];
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
        parent::dialog_batch('dchain_mdl_aoxiang_logistics', false, 50, 'incr');
    }
    
    /**
     * ajax批量删除并解绑同步关系
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
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到数据';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //支持批量同步(每次最多50条)
        $operation = 'manual'; //手工操作标记
        $result = $this->_aoxiangLib->deleteLogistics($dataList, $operation);
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
}