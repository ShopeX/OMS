<?php

class goldentra_ctl_admin_zahlart extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        $this->finder('goldentra_mdl_zahlart', array(
            'title' => '店铺支付方式编码',
            'actions' => array(
                array(
                    'label' => '新建',
                    'href' => 'index.php?app=goldentra&ctl=admin_zahlart&act=add&finder_id=' . (isset($_GET['finder_id']) ? $_GET['finder_id'] : ''),
                    'target' => 'dialog::{width:520,height:360,title:\'新建支付方式配置\'}',
                ),
            ),
            'finder_cols' => 'column_edit,shop_id,shop_bn,shop_name,zahlart,zahlart_name,disabled,up_time',
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
            'disabled' => 'false',
            'zahlart' => 'ZP39',
        );
        $this->page('admin/zahlart/edit.html');
    }

    public function edit($id = null)
    {
        $id = intval($id ? $id : (isset($_GET['p'][0]) ? $_GET['p'][0] : 0));
        $row = $id > 0 ? app::get('goldentra')->model('zahlart')->dump($id, '*') : array();
        if (!$row) {
            echo '记录不存在';
            return;
        }
        $this->pagedata['row'] = $row;
        $this->page('admin/zahlart/edit.html');
    }

    public function save()
    {
        $this->begin('index.php?app=goldentra&ctl=admin_zahlart&act=index');

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $shop_id = trim(isset($_POST['shop_id']) ? $_POST['shop_id'] : '');
        $zahlart = strtoupper(trim(isset($_POST['zahlart']) ? $_POST['zahlart'] : ''));
        $zahlart_name = trim(isset($_POST['zahlart_name']) ? $_POST['zahlart_name'] : '');
        $memo = trim(isset($_POST['memo']) ? $_POST['memo'] : '');
        $disabled = (!empty($_POST['disabled']) && $_POST['disabled'] === 'true') ? 'true' : 'false';

        if ($shop_id === '') {
            $this->end(false, '请选择店铺');
        }
        if ($zahlart === '' || strlen($zahlart) > 4) {
            $this->end(false, '支付方式编码 ZAHLART 必填且不超过4位');
        }

        $shop = app::get('ome')->model('shop')->db_dump(array('shop_id' => $shop_id), 'shop_id,shop_bn,name');
        if (empty($shop['shop_id'])) {
            $this->end(false, '店铺不存在');
        }

        $mdl = app::get('goldentra')->model('zahlart');
        $exist = $mdl->db_dump(array('shop_id' => $shop_id), 'id');
        if ($exist && intval($exist['id']) !== $id) {
            $this->end(false, '该店铺已存在支付方式配置');
        }

        $now = time();
        $data = array(
            'shop_id' => $shop_id,
            'shop_bn' => !empty($shop['shop_bn']) ? $shop['shop_bn'] : $shop_id,
            'shop_name' => !empty($shop['name']) ? $shop['name'] : '',
            'zahlart' => $zahlart,
            'zahlart_name' => $zahlart_name,
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
