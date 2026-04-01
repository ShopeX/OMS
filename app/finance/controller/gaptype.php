<?php

/**
 * ============================
 * @Author:   yaokangming
 * @describe: 
 * ============================
 */
class finance_ctl_gaptype extends desktop_controller {

    public function index() {
        $actions = array();
        $actions[] = array(
            'label'  => '新增',
            'href'   => $this->url.'&act=add',
            'target' => 'dialog::{width:600,height:300,title:\'新增\'}',
        );
        $actions[] = array(
            'label'  => '删除',
            'submit'   => $this->url.'&act=delete',
            'confirm' => '确定要删除吗？',
            'target' => 'refresh',
        );
        $params = array(
            'title'=>'差异类型管理',
            'use_buildin_set_tag'=>false,
            'use_buildin_filter'=>true,
            'use_buildin_export'=>false,
            'use_buildin_recycle'=>false,
            'actions'=>$actions,
            'base_filter'=>['disabled'=>'false'],
            'orderBy'=>'id desc',
        );
        $this->finder('finance_mdl_gaptype', $params);
    }

    /**
     * 新增差异类型
     */
    public function add() {
        $this->display('gaptype/form.html');
    }

    /**
     * 编辑差异类型
     */
    public function edit($id) {
        $mdl = app::get('finance')->model('gaptype');
        $row = $mdl->db_dump(['id'=>$id]);
        $this->pagedata['data'] = $row;
        $this->display('gaptype/form.html');
    }

    /**
     * 保存差异类型
     */
    public function save() {
        $data = $_POST;
        $mdl = app::get('finance')->model('gaptype');
        if(empty($data['gap_name'])){
            $this->splash('error', null, '名称不能为空');
        }
        // 判断 gap_name 是否重复
        $filter = [
            'gap_name' => $data['gap_name'],
            'disabled' => 'false',
        ];
        // 如果是编辑，排除自身
        if (isset($data['id']) && $data['id']) {
            $filter['id|notin'] = [$data['id']];
        }
        $count = $mdl->count($filter);
        if ($count > 0) {
            $this->splash('error', null, '类型名称已存在，请勿重复添加');
        }
        if(isset($data['id']) && $data['id']){
            $rs = $mdl->update($data, ['id'=>$data['id']]);
            if(is_bool($rs)){
                $this->splash('error', null, '保存失败');
            }
        }else{
            $rs = $mdl->insert($data);
            if(is_bool($rs)){
                $this->splash('error', null, '保存失败');
            }
        }
        $this->splash('success', $this->url, '保存成功');
    }

    /**
     * 删除差异类型
     */
    public function delete() {
        $mdl = app::get('finance')->model('gaptype');
        $row = $mdl->db_dump(['id'=>$_POST['id']]);
        if(!$row){
            $this->splash('error', null, '未找到该差异类型');
        }
        $mdl->update(['disabled'=>'true'], ['id'=>$_POST['id']]);
        $this->splash('success', $this->url, '删除成功');
    }

}