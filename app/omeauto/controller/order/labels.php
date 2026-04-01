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
 * 订单标签管理
 *
 * @author wangbiao@shopex.cn
 * @version $Id: Z
 */
class omeauto_ctl_order_labels extends omeauto_controller
{
    var $title = '订单标签管理';
    var $workground = 'setting_tools';
    
    private $_appName = 'omeauto';
    private $_mdl = null; //model类
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get($this->_appName)->model('order_labels');
        
        //primary_id
        $this->_primary_id = 'label_id';
        
        //primary_bn
        $this->_primary_bn = 'label_code';
    }
    
    public function index()
    {
        $base_filter = array();
        $actions = array();
        
        $actions[] = array(
            'label' => '新建',
            'href' => 'index.php?app=omeauto&ctl=order_labels&act=add',
            'target' => 'dialog::{width:500,height:300,title:\'新建订单标签\'}',
        );
        
        $actions[] = array(
                'label' => '删除',
                'confirm' => '你确定要删除此条记录吗？',
                'submit'=>'index.php?app=omeauto&ctl=order_labels&act=del_label',
                'target'=>'refresh'
        );
        
        $params = array(
                'title' => $this->title,
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
        $this->finder('omeauto_mdl_order_labels', $params);
    }
    
    public function add()
    {
        $this->pagedata['data'] = array();
        
        $this->page('order/label/label_add.html');
    }
    
    public function edit($label_id)
    {
        //标记信息
        $labelInfo = $this->_mdl->dump(array('label_id'=>$label_id), '*');
        
        $this->pagedata['data'] = $labelInfo;
        
        $this->page('order/label/label_add.html');
    }
    
    /**
     * 保存
     * @return mixed 返回操作结果
     */

    public function save()
    {
        $labelLib = kernel::single('omeauto_order_label');
        
        $url = "index.php?app=omeauto&ctl=order_labels&act=index";
        
        //标记信息
        $data = array(
            'label_id' => intval($_POST['label_id']),
            'label_code' => trim($_POST['label_code']),
            'label_name' => trim($_POST['label_name']),
            'label_color' => trim($_POST['label_color']),
            'source' => kernel::single('desktop_user')->get_login_name(),
            'create_time' => time(),
            'last_modified' => time(),
        );
        
        //check
        $error_msg = '';
        $isCheck = $labelLib->check_label_params($data, $error_msg);
        if(!$isCheck){
            $this->splash('error', $url, $error_msg);
        }
        
        //save
        $return = $this->_mdl->save($data);
        if(!$return){
            $this->splash("error", $url, '保存失败');
        }
        
        $this->splash("success", $url, '标记保存成功');
    }
    
    /**
     * 删除标签
     */
    public function del_label()
    {
        $this->begin('index.php?app=omeauto&ctl=order_labels&act=index');
        
        $orderLabelObj = app::get('ome')->model('bill_label');
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false,'不支持全选');
        }
        
        if(empty($_POST['label_id'])){
            $this->end(false, '没有可删除的记录');
        }
        
        // list
        $labelList = $this->_mdl->getList('*', array('label_id'=>$_POST['label_id']));
        $labelList = array_column($labelList, null, 'label_id');
        
        // delete label
        foreach($_POST['label_id'] as $label_id)
        {
            //判断是否已经有订单使用
            $labelInfo = $orderLabelObj->dump(array('label_id'=>$label_id), 'bill_type,bill_id,label_name');
            if($labelInfo){
                $this->end(false, $labelInfo['label_name'] .'已经被订单使用,不能删除');
            }
            
            // 系统标签不能删除
            if(isset($labelList[$label_id]) && $labelList[$label_id]['source'] == 'system'){
                $this->end(false, $labelList[$label_id]['label_name'] .'：是系统标签,不能删除');
            }
            
            //del
            $this->_mdl->db->exec("DELETE FROM sdb_omeauto_order_labels WHERE label_id=".$label_id);
        }
        
        $this->end(true, '已经删除完成!');
    }
}