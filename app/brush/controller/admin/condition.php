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

class brush_ctl_admin_condition extends desktop_controller{

    function index(){
        $params = array(
            'title'=>'特殊订单条件',
            'actions' => array(
                    array('label'=>'添加条件','href'=>"index.php?app=brush&ctl=admin_condition&act=add",'target'=>'_blank'),
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>true,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab'=>false,
       );
        $this->finder('brush_mdl_farm',$params);
    
    }
    #添加条件
    function add(){
        $this->_action(null);
    }

    function _action($farm_id=null){
        $shop_model = app::get('ome')->model('shop');
        $shop_list = $shop_model->getList("shop_id,name",array(),0,-1,'name ASC');
        $this->pagedata['shop_list'] = $shop_list;
        if($farm_id!=null){
            $shop_row = $this->app->model('farm')->dump(intval($farm_id));
            $shop_row['arrShopId'] = explode(',', $shop_row['shop_ids']);
            unset($shop_row['shop_ids']);
            $this->pagedata['shop_row'] = $shop_row;
        }
        $arrMarkType = kernel::single('ome_order_func')->order_mark_type();
        $this->pagedata['arrMarkType'] = $arrMarkType;
        $this->singlepage('admin/condition/condition_add.html');
    }
    #编辑
    function edit(){
        $this->_action(intval($_GET['farm_id']));
    }
    #保存
    function save(){
        $this->begin('index.php?app=brush&ctl=admin_condition&act=index');
        $farm_obj = $this->app->model('farm');
        if($_POST['farm_name']==''){
            $this->end(false,app::get('brush')->_('规则名称不能为空'));
        }
        if(!isset($_POST['farm_id'])|| $_POST['farm_id']==''){
            $check_name=$this->_check_farmname($_POST['farm_name']);
            if($check_name)$this->end(false,app::get('brush')->_('规则名称已经存在'));
        }
        if(empty($_POST['shop_id'])&& $_POST['user_name']=='' && $_POST['product_bn']=='' && $_POST['custom_mark']=='' && $_POST['condition']=='' && $_POST['mark_type'] == '' && $_POST['mark_text']=='' && $_POST['ship_addr']=='' && $_POST['ship_mobile']==''){
            $this->end(false,app::get('brush')->_('请必须填写一项数据'));
        }
        if(!$_POST['money']) {
            $_POST['money'] = 0;
        }
        $_POST['shop_ids'] =  $_POST['shop_id'] ? implode(',', $_POST['shop_id']) : '';
        unset($_POST['shop_id']);
        if($_POST['farm_id'] != ''){
            $_POST['uptime'] = intval(time());
            $log_memo = $farm_obj->dump($_POST['farm_id'],'*');
            $log_memo = serialize($log_memo);
            $log_operation = 'brush_farm_modify@brush';
        }else{
            $_POST['createtime'] = intval(time());
            $log_memo = '新增条件规则';
            $log_operation = 'brush_farm_add@brush';
        }
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $_POST['op_id'] = $opInfo['op_id'];
        $_POST['op_name'] = $opInfo['op_name'];
        // 如果 condition 是空字符串，移除该字段，让数据库使用默认值或 NULL
        if (isset($_POST['condition']) && $_POST['condition'] === '') {
            unset($_POST['condition']);
        }
        $flag = $farm_obj->save($_POST);
        if($flag){
            $opObj  = app::get('ome')->model('operation_log');
            $ret = $opObj->write_log($log_operation, $_POST['farm_id'], $log_memo);
            !$ret && $this->end(false, '保存失败');
            $this->end(true,app::get('brush')->_('保存成功'));
        }else{
            $this->end(false,app::get('brush')->_('保存失败'));
        }
    }

    /**
     * show_history
     * @param mixed $log_id ID
     * @return mixed 返回值
     */
    public function show_history($log_id) {
        $logObj = app::get('ome')->model('operation_log');
        $log = $logObj->dump($log_id,'memo');
        $arrFarm = unserialize($log['memo']);
        $shopIds = explode(',', $arrFarm['shop_ids']);
        $shopData = app::get('ome')->model('shop')->getList('name', array('shop_id'=>$shopIds));
        $shopName = array();
        foreach($shopData as $shop) {
            $shopName[] = $shop['name'];
        }
        if($arrFarm['mark_type'] != '') {
            $markTypeUrl = kernel::single('ome_order_func')->order_mark_type($arrFarm['mark_type']);
            $markType = "<img src='" . $markTypeUrl . "' width='20'height='20'>";
        } else {
            $markType = '不设旗标';
        }
        $this->pagedata['arrFarm'] = $arrFarm;
        $this->pagedata['shopName'] = implode(',', $shopName);
        $this->pagedata['markType'] = $markType;
        $this->singlepage('admin/farm/detail/history_log.html');
    }

    private function _check_farmname($farm_name){
        $farm_obj = $this->app->model('farm');
        $check_name = $farm_obj->getList('farm_name',array('farm_name'=>$farm_name));
        return $check_name ? true:false;
    }
}
