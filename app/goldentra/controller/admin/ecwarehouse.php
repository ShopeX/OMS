<?php

class goldentra_ctl_admin_ecwarehouse extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        $this->finder('goldentra_mdl_ecwarehouse', array(
            'title' => '门店/仓库电商仓配置',
            'actions' => array(
                array(
                    'label' => '新建',
                    'href' => 'index.php?app=goldentra&ctl=admin_ecwarehouse&act=add&finder_id=' . (isset($_GET['finder_id']) ? $_GET['finder_id'] : ''),
                    'target' => 'dialog::{width:560,height:380,title:\'新建电商仓配置\'}',
                ),
            ),
            'finder_cols' => 'column_edit,owner_type,store_id,branch_id,owner_bn,owner_name,is_ec,disabled,up_time',
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => true,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'orderBy' => 'id DESC',
        ));
    }

    public function add()
    {
        $this->pagedata['row'] = array(
            'owner_type' => 'branch',
            'is_ec' => 'true',
            'disabled' => 'false',
        );
        $this->page('admin/ecwarehouse/edit.html');
    }

    public function edit($id = null)
    {
        $id = intval($id ? $id : (isset($_GET['p'][0]) ? $_GET['p'][0] : 0));
        $row = $id > 0 ? app::get('goldentra')->model('ecwarehouse')->dump($id, '*') : array();
        if (!$row) {
            echo '记录不存在';
            return;
        }
        $this->pagedata['row'] = $row;
        $this->page('admin/ecwarehouse/edit.html');
    }

    public function save()
    {
        $this->begin('index.php?app=goldentra&ctl=admin_ecwarehouse&act=index');

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $owner_type = (!empty($_POST['owner_type']) && $_POST['owner_type'] === 'store') ? 'store' : 'branch';
        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        $is_ec = (!empty($_POST['is_ec']) && $_POST['is_ec'] === 'true') ? 'true' : 'false';
        $disabled = (!empty($_POST['disabled']) && $_POST['disabled'] === 'true') ? 'true' : 'false';
        $memo = trim(isset($_POST['memo']) ? $_POST['memo'] : '');

        $owner_bn = '';
        $owner_name = '';

        if ($owner_type === 'store') {
            if ($store_id <= 0) {
                $this->end(false, '请选择门店');
            }
            $store = app::get('o2o')->model('store')->db_dump(
                array('store_id' => $store_id),
                'store_id,store_bn,name'
            );
            if (empty($store['store_id'])) {
                $this->end(false, '门店不存在');
            }
            $branch_id = 0;
            $owner_bn = !empty($store['store_bn']) ? $store['store_bn'] : (string) $store_id;
            $owner_name = !empty($store['name']) ? $store['name'] : '';
        } else {
            if ($branch_id <= 0) {
                $this->end(false, '请选择仓库');
            }
            $branch = app::get('ome')->model('branch')->db_dump(
                array('branch_id' => $branch_id),
                'branch_id,branch_bn,name,b_type'
            );
            if (empty($branch['branch_id'])) {
                $this->end(false, '仓库不存在');
            }
            if (intval($branch['b_type']) !== 1) {
                $this->end(false, '只能选择线上仓');
            }
            $store_id = 0;
            $owner_bn = !empty($branch['branch_bn']) ? $branch['branch_bn'] : (string) $branch_id;
            $owner_name = !empty($branch['name']) ? $branch['name'] : '';
        }

        $mdl = app::get('goldentra')->model('ecwarehouse');
        $exist = $mdl->db_dump(
            array(
                'owner_type' => $owner_type,
                'store_id' => $store_id,
                'branch_id' => $branch_id,
            ),
            'id'
        );
        if ($exist && intval($exist['id']) !== $id) {
            $this->end(false, '该门店/仓库已存在电商仓配置');
        }

        $now = time();
        $data = array(
            'owner_type' => $owner_type,
            'store_id' => $store_id,
            'branch_id' => $branch_id,
            'owner_bn' => $owner_bn,
            'owner_name' => $owner_name,
            'is_ec' => $is_ec,
            'disabled' => $disabled,
            'memo' => $memo,
            'up_time' => $now,
        );
        if ($id > 0) {
            $data['id'] = $id;
        } else {
            $data['at_time'] = $now;
        }

        if (!$mdl->save($data)) {
            $this->end(false, '保存失败');
        }
        $this->end(true, '保存成功');
    }
}
