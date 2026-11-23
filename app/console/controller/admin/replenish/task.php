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
 * 门店自动补货任务
 * 
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_ctl_admin_replenish_task extends desktop_controller
{
    public function index()
    {
        $base_filter = array();
        
        //action
        $actions = array();
        $actions[] = array(
                'label' => '新建正价补货任务',
                'href' => $this->url.'&act=add&store_mode=normal',
                'target' => 'dialog::{width:600,height:500,title:\'新建正价补货任务\'}',
        );
        
        $actions[] = array(
                'label' => '新建折扣补货任务',
                'href' => $this->url.'&act=add&store_mode=discount',
                'target' => 'dialog::{width:600,height:500,title:\'新建折扣补货任务\'}',
        );
        
        $actions[] = array(
                'label' => '删除任务',
                'confirm' => '你确定要删除此条任务吗？',
                'submit' => $this->url.'&act=deltask',
                'target' => 'refresh',
        );
        
        //params
        $params = array(
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag' => false,
                'use_buildin_recycle' => false,
                'use_buildin_import' => false,
                'use_buildin_export' => false,
                'use_buildin_filter' => true,
                'use_view_tab' => true,
                'actions' => $actions,
                'title' => '补货任务',
                'base_filter' => $base_filter,
                'orderBy' => 'task_id DESC',
        );
        
        $this->finder('console_mdl_replenish_task', $params);
    }
    
    public function add()
    {
        $reTaskObj = app::get('console')->model('replenish_task');
        $storeObj = app::get('o2o')->model('store');
        
        $store_mode = $_GET['store_mode'];
        if(empty($store_mode)){
            die('无效的操作,请检查');
        }

        //门店销售类型
        $storeType = array('normal'=>'正价店铺', 'discount'=>'折扣店铺');
        
        foreach ($storeType as $key => $val)
        {
            if($key != $store_mode){
                unset($storeType[$key]);
            }
        }
        
        //门店列表
        $filter = array('store_mode'=>$store_mode);
        $storeList = $storeObj->getList('store_id,store_bn,name', $filter, 0, 500);
        
        //补货任务
        $reTaskInfo = array(
                'multipl_num' => 1,
                'store_ids' => '',
        );
        
        //已选择门店列表
        $select_store_ids = array();
        
        //调拨仓库
        $sql = "SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE type='main' AND b_type=1";
        $tempList = $storeObj->db->select($sql);
        if(empty($tempList)){
            die('没有获取到调拨仓库');
        }
        
        $branchList = array_column($tempList, null, 'branch_id');
        
        $this->pagedata['branchList'] = $branchList;
        $this->pagedata['select_store_ids'] = $select_store_ids;
        $this->pagedata['storeList'] = $storeList;
        $this->pagedata['storeType'] = $storeType;
        $this->pagedata['data'] = $reTaskInfo;
        
        $this->page('admin/replenish/task_add.html');
    }
    
    public function edit($task_id)
    {
        $reTaskObj = app::get('console')->model('replenish_task');
        $storeObj = app::get('o2o')->model('store');
        
        //门店类型
        $storeType = array('normal'=>'正价店铺', 'discount'=>'折扣店铺');
        
        //补货任务
        $reTaskInfo = $reTaskObj->dump(array('task_id'=>$task_id), '*');
        $store_type = $reTaskInfo['store_type'];
        
        //check
        if($reTaskInfo['task_status'] !== '0'){
            die('补货任务不允许编辑,不是[待确认]状态');
        }
        
        //门店列表
        $filter = array('store_mode'=>$store_type);
        $storeList = $storeObj->getList('store_id,store_bn,name', $filter, 0, 100);
        
        //过滤门店类型
        foreach ($storeType as $key => $val)
        {
            if($store_type != $key){
                unset($storeType[$key]);
            }
        }
        
        //店铺
        $select_store_ids = array();
        if($reTaskInfo['store_ids'] && $reTaskInfo['store_ids'] != '_ALL_'){
            $select_store_ids = json_decode($reTaskInfo['store_ids']);
        }
        
        //商品
        $bm_ids = array();
        if($reTaskInfo['bm_ids'] && $reTaskInfo['bm_ids'] != '_ALL_'){
            $bm_ids = json_decode($reTaskInfo['bm_ids']);
        }
        
        //调拨仓库
        $sql = "SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE type='main' AND b_type=1";
        $tempList = $storeObj->db->select($sql);
        if(empty($tempList)){
            die('没有获取到调拨仓库');
        }
        
        $branchList = array_column($tempList,null, 'branch_id');
        
        $this->pagedata['branchList'] = $branchList;
        $this->pagedata['select_store_ids'] = $select_store_ids;
        $this->pagedata['bm_ids'] = $bm_ids;
        $this->pagedata['storeList'] = $storeList;
        $this->pagedata['storeType'] = $storeType;
        $this->pagedata['data'] = $reTaskInfo;
        
        $domid = 'hand-selected-product';
        $count = count($bm_ids);
        $sign = '基础物料';
        $func =  'product_selected_show';
        $this->pagedata['bcreplacehtml'] = <<<EOF
<div id='{$domid}'>已选择了{$count}{$sign} &nbsp;<a href='javascript:void(0);' onclick='{$func}();'>查看选中{$sign}</a></div>
EOF;
        
        $this->page('admin/replenish/task_add.html');
    }
    
    public function save()
    {
        $this->begin($this->url.'&act=index');
        
        $reTaskObj = app::get('console')->model('replenish_task');
        
        //params
        $data = array(
                'task_id' => intval($_POST['task_id']),
                'task_bn' => date('YmdHis'),
                'task_name' => $_POST['task_name'],
                'store_type' => $_POST['store_mode'],
                'start_time' => $_POST['start_time'],
                'end_time' => $_POST['end_time'],
                'multipl_num' => $_POST['multipl_num'],
                'store_ids' => $_POST['store_ids'],
                'bm_ids' => $_POST['bm_id'],
                'out_branch_id' => intval($_POST['out_branch_id']),
        );
        
        //check
        if(empty($data['task_name'])){
            $this->end(false, '请填写补货任务名称');
        }
        
        if(empty($data['store_ids'])){
            $this->end(false, '请选择店铺范围');
        }
        
        if(empty($data['start_time']) || empty($data['end_time'])){
            $this->end(false, '请填写销售统计日期');
        }
        
        $start_time = strtotime($data['start_time'] . ' 00:00:00');
        $end_time = strtotime($data['end_time'] . ' 23:59:59');
        $today = strtotime(date('Y-m-d', time()).' 00:00:00');
        
        if(empty($start_time) || empty($end_time)){
            $this->end(false, '销售统计日期填写错误');
        }
        
        if($end_time <= $start_time){
            $this->end(false, '销售结束时间必须大于开始时间');
        }
        
        if($end_time >= $today){
            $this->end(false, '销售结束时间必须早于今天');
        }
        
        $diff_time = $end_time - $start_time;
        $day_31_time = (60 * 60 * 24 * 31);
        if($diff_time > $day_31_time){
            $this->end(false, '销售时间范围不允许超过31天');
        }
        
        $temp = explode('.', $data['multipl_num']);
        if(empty($temp[0])){
            $this->end(false, '无效的销售补货系数');
        }
        
        if($data['multipl_num'] < 1 || $data['multipl_num'] > 3){
            $this->end(false, '销售补货系数不能小于1，并且不能大于3');
        }
        
        if(empty($data['out_branch_id'])){
            $this->end(false, '请选择调拨仓库');
        }
        
        //检查是否有任务未完成
        if($data['task_id']){
            $sql = "SELECT task_id FROM sdb_console_replenish_task WHERE task_id != ". intval($data['task_id']) ." AND task_status IN('0','1','2','3','4') AND store_type=". $reTaskObj->db->quote($data['store_type']);
        }else{
            $sql = "SELECT task_id FROM sdb_console_replenish_task WHERE task_status IN('0','1','2','3','4') AND store_type=". $reTaskObj->db->quote($data['store_type']);
        }
        
        $checkInfo = $reTaskObj->db->selectrow($sql);
        if($checkInfo){
            $this->end(false, '存在未完成的任务,不能创建补货任务');
        }
        
        //format
        if($temp[1]){
            $data['multipl_num'] = round($data['multipl_num'], 2);
        }else{
            $data['multipl_num'] = intval($data['multipl_num']);
        }
        
        $data['start_time'] = $start_time;
        $data['end_time'] = $end_time;
        
        if($data['store_ids'][0] == '_ALL_'){
            $data['store_ids'] = '_ALL_';
        }else{
            $data['store_ids'] = json_encode($data['store_ids']);
        }
        
        if($data['bm_ids'][0] == '_ALL_'){
            $data['bm_ids'] = '';
        }elseif($data['bm_ids']){
            $data['bm_ids'] = json_encode($data['bm_ids']);
        }
        
        //save
        if($data['task_id']){
            unset($data['task_bn']);
            
            //补货任务
            $reTaskInfo = $reTaskObj->dump(array('task_id'=>$data['task_id']), '*');
            if($reTaskInfo['task_status'] !== '0'){
                $this->end(false, '补货任务不允许编辑,不是[待确认]状态');
            }
            
            $data['last_modified'] = time();
            
            //update
            $result = $reTaskObj->update($data, array('task_id'=>$data['task_id']));
            if(!$result){
                $this->end(false, '更新失败');
            }
        }else{
            $data['create_time'] = time();
            $data['last_modified'] = time();
            
            //save
            $result = $reTaskObj->insert($data);
            if(!$result){
                $this->end(false, '保存失败');
            }
        }
        
        $this->end(true, '保存成功');
    }
    
    /**
     * 确认补货任务
     */
    public function confirm($task_id)
    {
        $reTaskObj = app::get('console')->model('replenish_task');
        $storeObj = app::get('o2o')->model('store');
        
        //门店类型
        $storeType = array('normal'=>'正价店铺', 'discount'=>'折扣店铺');
        
        //补货任务
        $reTaskInfo = $reTaskObj->dump(array('task_id'=>$task_id), '*');
        if($reTaskInfo['task_status'] !== '0'){
            die('补货任务不允许确认任务,不是[待确认]状态');
        }
        
        $this->pagedata['data'] = $reTaskInfo;
        
        $this->page('admin/replenish/task_confirm.html');
    }
    
    public function doConfirm()
    {
        $this->begin($this->url.'&act=index');
        
        $reTaskObj = app::get('console')->model('replenish_task');
        
        $task_id = intval($_POST['task_id']);
        
        //补货任务
        $reTaskInfo = $reTaskObj->dump(array('task_id'=>$task_id), '*');
        if(empty($reTaskInfo)){
            $this->end(false, '补货任务不存在');
        }
        
        if(empty($reTaskInfo['out_branch_id'])){
            $this->end(false, '调拨仓库不能为空');
        }
        
        if($reTaskInfo['task_status'] !== '0'){
            $this->end(false, '补货任务不是[待确认]状态');
        }
        
        //update
        $reTaskObj->update(array('task_status'=>'1'), array('task_id'=>$task_id));
        
        //queue队列执行任务
        $queueObj = app::get('base')->model('queue');
        $sdf = array('task_id'=>$reTaskInfo['task_id'], 'task_bn'=>$reTaskInfo['task_bn']);
        $queueData = array(
                'queue_title' => '门店补货任务['. $reTaskInfo['task_bn'] .']生成补货建议单',
                'start_time' => time(),
                'params' => array(
                    'sdfdata' => $sdf,
                    'app' => 'console',
                    'mdl' => 'replenish_task',
                ),
                'worker' => 'console_replenish.createReplenish',
        );
        $queueObj->save($queueData);
        
        $this->end(true, '确认成功');
    }
    
    /**
     * 删除任务
     */
    public function deltask()
    {
        $this->begin($this->url.'&act=index');
        
        $reTaskObj = app::get('console')->model('replenish_task');
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false, '不支持全选');
        }
        
        $ids = $_POST['task_id'];
        if(empty($ids)){
            $this->end(false, '请选择要删除的任务');
        }
        
        $dataList = $reTaskObj->getList('task_id,task_bn,task_status', array('task_id'=>$ids));
        
        if(empty($dataList)){
            $this->end(false, '没有可删除的任务');
        }
        
        foreach ($dataList as $key => $val)
        {
            $task_id = $val['task_id'];
            
            if(!in_array($val['task_status'], array('0', '1'))){
                $this->end(false, '删除失败：只能删除未生成补货单的任务');
            }
            
            $reTaskObj->db->exec("DELETE FROM sdb_console_replenish_task WHERE task_id=".$task_id);
        }
        
        $this->end(true, '删除任务成功!');
    }
    
    /**
     * 确认生成补货单
     */
    public function dispose($sug_id)
    {
        $storeObj = app::get('o2o')->model('store');
        //调拨仓库
        $sql = "SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE type='main' AND b_type=1";
        $tempList = $storeObj->db->select($sql);
        if(empty($tempList)){
            die('没有获取到调拨仓库');
        }
        
        $branchList = array_column($tempList, null, 'branch_id');
        
        $this->pagedata['branchList'] = $branchList;
        unset($branchList);

        $suggestObj = app::get('console')->model('replenish_suggest');
        $suggests = $suggestObj->dump(array('sug_id'=>$sug_id), '*');
        $this->pagedata['suggests'] = $suggests;

        
        $this->page('admin/replenish/task_dispose.html');
    }
    
    public function doDispose($sug_id)
    {
        $suggestObj = app::get('console')->model('replenish_suggest');
        

        $redirect_url = 'index.php?app=console&ctl=admin_replenish_suggest&act=index';
        //补货任务
        $reTaskInfo = $suggestObj->dump(array('sug_id'=>$sug_id), '*');
        if(empty($reTaskInfo)){
           
            $this->splash('error', $redirect_url, '补货任务不存在');
        }
        
        if(empty($reTaskInfo['out_branch_id'])){
            $this->splash('error', $redirect_url, '出货仓库不可为空');
            
        }
        
        if($_POST['out_branch_id']){
            $bid = intval($_POST['out_branch_id']);
            $sql = "SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE branch_id=".$bid;

            $branchs = $suggestObj->db->selectrow($sql);
            $suggestObj->update(array('out_branch_id'=>$bid,'out_branch_bn'=>$branchs['branch_bn']), array('sug_id'=>$sug_id));
        }

        
        //审核
        
        $replenishLib = kernel::single('console_replenish');
        list($rs, $rsData)=$replenishLib->confirmReplenish($reTaskInfo);

        if(!$rs) {
            $this->splash('error', $redirect_url, $rsData['msg']);
            
        }else{
            $this->splash('success', $redirect_url, '确认成功');
           
        }
    }
    
    /**
     * 获取补货建议单明细
     */
    public function getSuggestItems($task_id)
    {
        if(empty($task_id)){
            return '';
        }
        
        $suggestObj = app::get('console')->model('replenish_suggest');
        
        //补货建议单明细
        $dataList = $suggestObj->getList('*', array('task_id'=>$task_id));
        if(empty($dataList)){
            return '';
        }
        
        //dbschema
        $schema = $suggestObj->get_schema();
        
        //单据状态
        $statusList = $schema['columns']['sug_status']['type'];
        
        //format
        foreach ($dataList as $key => $val)
        {
            $sug_status = $val['sug_status'];
            $dataList[$key]['sug_status'] = $statusList[$sug_status];
            
            $dataList[$key]['create_time'] = date('Y-m-d H:i:s', $val['create_time']);
            $dataList[$key]['last_modified'] = date('Y-m-d H:i:s', $val['last_modified']);
        }
        
        echo(json_encode($dataList));
    }
}
