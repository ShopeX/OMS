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
 * 订单标记规则
 *
 * @author wangbiao@shopex.cn
 * @version $Id: Z
 */
class omeauto_ctl_order_labelrule extends omeauto_controller
{
    var $workground = 'setting_tools';
    
    function index()
    {
        $base_filter = array();
        $actions = array();
        
        $actions[] = array(
            'label' => '新建',
            'href' => 'index.php?app=omeauto&ctl=order_labelrule&act=add',
            'target' => 'dialog::{width:730,height:550,title:\'新建标记规则\'}',
        );
        
        $actions[] = array(
                'label' => '删除',
                'confirm' => '你确定要删除此条规则吗？',
                'submit'=>'index.php?app=omeauto&ctl=order_labelrule&act=del_rule',
                'target'=>'refresh'
        );
        
        $params = array(
                'title' => '订单标记规则',
                'actions' => $actions,
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag' => false,
                'use_buildin_recycle' => false,
                'use_buildin_export' => false,
                'use_buildin_import' => false,
                'use_buildin_filter' => false,
                'use_view_tab' => false,
                'base_filter' => $base_filter,
        );
        $this->finder('omeauto_mdl_order_labelrule', $params);
    }
    
    function add()
    {
        //运营组织
        $operationOrgObj = app::get('ome')->model('operation_organization');
        
        $filter = array();
        $orgs = $operationOrgObj->getList('*', $filter, 0, -1);
        $this->pagedata['orgs'] = $orgs;
        
        $this->pagedata['data'] = array();
        
        $this->page('order/label/create_rule.html');
    }
    
    function edit($id)
    {
        $ruleObj = app::get('omeauto')->model('order_labelrule');
        
        //运营组织
        $operationOrgObj = app::get('ome')->model('operation_organization');
        
        $filter = array();
        $orgs = $operationOrgObj->getList('*', $filter, 0, -1);
        $this->pagedata['orgs'] = $orgs;
        
        //规则信息
        $ruleInfo = $ruleObj->dump(array('id'=>$id), '*');
        if(empty($ruleInfo)){
            die('规则信息不存在');
        }
        
        //标签信息
        $select_label = array();
        if($ruleInfo['select_label']){
            $select_label = json_decode($ruleInfo['select_label'], true);
        }
        
        //规则
        if($ruleInfo['config']){
            $tempData = array();
            foreach ($ruleInfo['config'] as $key => $row)
            {
                $tempData[$key] = array('json'=>$row, 'attr'=>json_decode($row, true));
            }
            
            $ruleInfo['config'] = $tempData;
        }
        
        $this->pagedata['data'] = $ruleInfo;
        $this->pagedata['selectLabel'] = $select_label;
        
        $this->page('order/label/create_rule.html');
    }
    
    /**
     * 保存
     * @return mixed 返回操作结果
     */

    public function save()
    {
        $ruleObj = app::get('omeauto')->model('order_labelrule');
        $labelObj = app::get('omeauto')->model('order_labels');
        
        $result = array('res'=>'fail', 'error_msg'=>'');
        
        $data = array(
                'id' => intval($_REQUEST['id']),
                'org_id' => intval($_REQUEST['org_id']),
                'name' => trim($_POST['name']),
                'memo' => trim($_POST['memo']),
                'config' => explode('|||', $_POST['roles']),
                'label_id' => intval($_POST['label_id']),
                'weight' => intval($_POST['weight']),
                'create_time' => time(),
                'last_modified' => time(),
        );
        
        //check
        if($data['id']){
            $ruleInfo = $ruleObj->dump(array('id'=>$data['id']), '*');
            if(empty($ruleInfo)){
                unset($data['id']);
            }
        }else{
            unset($data['id']);
        }
        
        if(empty($data['name'])){
            $result['error_msg'] = '请填写类型名称!';
            echo json_encode($result);
            exit;
        }
        
        if(empty($data['config'])){
            $result['error_msg'] = '请选择归类规则!';
            echo json_encode($result);
            exit;
        }
        
        if(empty($data['label_id'])){
            $result['error_msg'] = '请先选择所属标签!';
            echo json_encode($result);
            exit;
        }
        
        //标签信息
        $labelInfo = $labelObj->dump(array('label_id'=>$data['label_id']), 'label_id,label_code,label_name,label_color');
        if(empty($labelInfo)){
            $result['error_msg'] = '选择的标签不存在!';
            echo json_encode($result);
            exit;
        }
        
        //标签(现只支持一个)
        $label_id = $labelInfo['label_id'];
        
        $labelData = array();
        $labelData[$label_id] = $labelInfo;
        
        $data['select_label'] = json_encode($labelData);
        
        //规则名称不允许重复
        if(empty($data['id'])){
            $filter = array('name'=>$data['name']);
            $ruleInfo = $ruleObj->dump($filter, 'id');
            if($ruleInfo){
                $result['error_msg'] = '规则名称不能重复,请检查!';
                echo json_encode($result);
                exit;
            }
        }
        
        //save
        $isSave = $ruleObj->save($data);
        if(!$isSave){
            $result['error_msg'] = '保存失败!';
            echo json_encode($result);
            exit;
        }
        
        $result['res'] = 'succ';
        
        echo json_encode($result);
        exit;
    }
    
    /**
     * 暂停与启用
     * 
     * @param int $oid
     * @param string $status
     */
    function setStatus($id, $status)
    {
        if ($status == 'true') {
            $disabled = 'false';
        } else {
            $disabled = 'true';
        }
        
        kernel::database()->query("UPDATE sdb_omeauto_order_labelrule SET disabled='{$disabled}' where id={$id}");
        
        echo "<script>parent.MessageBox.success('设置规则状态成功！！');parent.finderGroup['{$_GET['finder_id']}'].refresh();</script>";
        exit;
    }
    
    /**
     * 添加_label
     * @return mixed 返回值
     */
    public function add_label()
    {
        $ruleObj = app::get('omeauto')->model('order_labelrule');
        $labelObj = app::get('omeauto')->model('order_labels');
        
        $ruleInfo = array();
        $labelItems = array();
        $rule_id = $_REQUEST['rule_id'];
        if($rule_id){
            $ruleInfo = $ruleObj->dump(array('id'=>$rule_id), '*');
            if($ruleInfo){
                $dataList = json_decode($ruleInfo['select_label'], true);
                foreach ((array)$dataList as $key => $label_id)
                {
                    $labelItems[$label_id] = $label_id;
                }
            }
        }
        
        //所有标签
        $labelList = array();
        $dataList = $labelObj->getList('label_id,label_code,label_name,label_color', array());
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
        
        $this->pagedata['data'] = $ruleInfo;
        $this->pagedata['labelList'] = $labelList;
        
        $this->page('order/label/select_label.html');
    }
    
    /**
     * 选择归类规则
     */
    public function addrole()
    {
        if (!empty($_REQUEST['role'])) {
            $role = json_decode($_REQUEST['role'], true);
        } else {
            $role = array();
        }
        
        $this->pagedata['uid'] = $_REQUEST['uid'];
        $this->pagedata['role'] = base64_encode($_REQUEST['role']);
        $this->pagedata['org_id'] = $_REQUEST['org_id'];
        $this->pagedata['init'] = $role;
        
        $this->page('order/label/addrole.html');
    }
    
    /**
     * 选择标签
     */
    public function createLabel()
    {
        $labelObj = app::get('omeauto')->model('order_labels');
        
        $result = array('res'=>'fail', 'error_msg'=>'');
        
        $label_id = $_POST['label_id'];
        
        if(empty($result)){
            $result['error_msg'] = '无效的操作.';
            echo json_encode($result);
            exit;
        }
        
        //标签信息
        $labelInfo = $labelObj->dump(array('label_id'=>$label_id), '*');
        if(empty($labelInfo)){
            $result['error_msg'] = '选择的标签,不存在.';
            echo json_encode($result);
            exit;
        }
        
        $result['res'] = 'succ';
        $result['labelInfo'] = $labelInfo;
        
        echo json_encode($result);
        exit;
    }
    
    /**
     * 添加新标签
     */
    public function addLabel()
    {
        $this->pagedata['data'] = array();
        
        $this->page('order/label/rule_label_add.html');
    }
    
    /**
     * 保存新标签
     */
    public function saveLabel()
    {
        $labelObj = app::get('omeauto')->model('order_labels');
        
        $labelLib = kernel::single('omeauto_order_label');
        
        $url = "index.php?app=omeauto&ctl=order_labels&act=index";
        $result = array('res'=>'fail', 'error_msg'=>'');
        
        //标记信息
        $data = array(
                'label_id' => intval($_POST['label_id']),
                'label_code' => trim($_POST['label_code']),
                'label_name' => trim($_POST['label_name']),
                'label_color' => trim($_POST['label_color']),
                'create_time' => time(),
                'last_modified' => time(),
        );
        
        //check
        $error_msg = '';
        $isCheck = $labelLib->check_label_params($data, $error_msg);
        if(!$isCheck){
            $result['error_msg'] = $error_msg;
            echo json_encode($result);
            exit;
        }
        
        //save
        $isSave = $labelObj->save($data);
        if(!$isSave){
            $result['error_msg'] = '保存失败';
            echo json_encode($result);
            exit;
        }
        
        $result['labelInfo'] = $data;
        $result['res'] = 'succ';
        
        echo json_encode($result);
        exit;
    }
    
    /**
     * 删除订单标记规则
     */
    public function del_rule()
    {
        $this->begin('index.php?app=omeauto&ctl=order_labelrule&act=index');
        
        $ruleObj = app::get('omeauto')->model('order_labelrule');
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false,'不支持全选');
        }
        
        if(empty($_POST['id'])){
            $this->end(false, '没有可删除的规则');
        }
        
        foreach($_POST['id'] as $id)
        {
            //启用状态下,不可删除
            $ruleInfo = $ruleObj->dump(array('id'=>$id), 'id,name,disabled');
            if($ruleInfo['disabled'] != 'true'){
                $this->end(false, $ruleInfo['name'] .' 是启用状态,不能删除');
            }
            
            //del
            $ruleObj->db->exec("DELETE FROM sdb_omeauto_order_labelrule WHERE id=".$id);
        }
        
        $this->end(true, '已经删除完成!');
    }
}