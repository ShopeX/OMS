<?php

/**
 * ============================
 * @Author:   yaokangming
 * @describe: 销售人员管理
 * ============================
 */
class material_ctl_admin_seller extends desktop_controller {
    public function index() {
        $actions = array();
        $actions[] = array(
                'label'  => '新增',
                'href'   => $this->url.'&act=add',
                'target' => 'dialog::{width:450,height:200,title:\'新增\'}',
        );
        $actions[] = array(
                'label'  => '导入',
                'href'   => $this->url.'&act=execlImportDailog&p[0]=seller',
                'target' => 'dialog::{width:500,height:300,title:\'导入\'}',
        );
        $params = array(
                'title'=>'销售人员',
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>false,
                'use_buildin_export'=>false,
                'use_buildin_recycle'=>false,
                'actions'=>$actions,
                'orderBy'=>'id desc',
        );
        
        $this->finder('material_mdl_seller', $params);
    }

    public function add() {
        $this->display('admin/seller/add.html');
    }

    public function edit($id) {
        $seller = app::get('material')->model('seller')->db_dump(['id'=>$id]);
        $this->pagedata['data'] = $seller;
        $this->display('admin/seller/add.html');
    }

    public function oplog($id) {
        $opObj  = app::get('ome')->model('operation_log_snapshoot');
        $opRow = $opObj->db_dump(['log_id'=>$id]);
        if(!$opRow) {
            header('Content-Type: text/html; charset=utf-8');
            echo '没有快照';exit;
        }
        $memo = $opRow['snapshoot'];
        $this->pagedata['data'] = json_decode($memo, true);
        $this->pagedata['readonly'] = true;
        $this->display('admin/seller/add.html');
    }

    public function save() { 
        $seller = app::get('material')->model('seller');
        $data = [];
        $data['seller_code'] = $_POST['seller_code'];
        $data['seller_name'] = $_POST['seller_name'];
        $data['disabled'] = $_POST['disabled'];
        if(empty($data['seller_code']) || empty($data['seller_name'])) {
            $this->splash('error', '', '销售人员编码和销售人员名称不能为空');
        }
        $id = $_POST['id'];
        $filter = [];
        $filter['seller_code'] = $data['seller_code'];
        if($id) {
            $filter['id|noequal'] = $id;
        }
        if($seller->db_dump($filter)) {
            $this->splash('error', '', '销售人员编码已存在');
        }
        if($id) {
            $rs = $seller->update($data, ['id'=>$id]);
            if(is_bool($rs)) {
                //$this->splash('error', '', '修改销售人员信息失败');
            }
            $msg = '修改销售人员信息';
        } else {
            $seller->insert($data);
            if(!$data['id']) {
                $this->splash('error', '', '添加销售人员信息失败');
            }
            $id =  $data['id'];
            $msg = '添加销售人员信息';
        }
        $omeLogMdl = app::get('ome')->model('operation_log');
        $log_id    = $omeLogMdl->write_log('seller@material', $id, $msg.'查看快照');
        if ($log_id && $data) {
            $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
            $snapshoot = json_encode($data, JSON_UNESCAPED_UNICODE);
            $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
            $shootMdl->insert($tmp);
        }
        $this->splash('success', $this->url, '保存成功');
    }
}