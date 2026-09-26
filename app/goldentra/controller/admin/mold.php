<?php

class goldentra_ctl_admin_mold extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        $actions = array();
        if ($_GET['singleselect'] != 1) {
            $actions[] = array(
                'label' => '新建',
                'href' => 'index.php?app=goldentra&ctl=admin_mold&act=add',
                'target' => 'dialog::{width:600,height:300,title:\'新建\'}',
            );
            $actions[] = array(
                'label' => '导入模号',
                'icon' => 'upload.gif',
                'href' => 'index.php?app=goldentra&ctl=admin_import&act=progress_import&import_app=goldentra&import_ctl=admin_mold&import_act=importMold&import_tmpl_name=导入模号&import_tmpl_act=exportTemplateOfMold',
                'target' => 'dialog::{width:600,height:300,title:\'导入模号\'}',
            );
        }

        $params = array(
            'title' => '模号列表',
            'actions' => $actions,
            'base_filter' => array(),
            'finder_cols' => 'column_edit,mold_code,create_time',
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
        );

        $this->finder('goldentra_mdl_mold', $params);
    }

    public function add($m_id = null)
    {
        $moldObj = app::get('goldentra')->model('mold');
        $this->pagedata['mold_detail'] = $m_id ? $moldObj->dump($m_id, '*') : array();
        $this->page('admin/mold/add.html');
    }

    public function save()
    {
        $this->begin('index.php?app=goldentra&ctl=admin_mold&act=index');

        $moldObj = app::get('goldentra')->model('mold');
        $mold_code = trim($_POST['mold_code']);

        if (!$mold_code) {
            $this->end(false, '模号不能为空');
        }

        if (!$_POST['m_id']) {
            $mold_detail = $moldObj->dump(array('mold_code' => $mold_code), 'm_id');
            if ($mold_detail) {
                $this->end(false, $mold_code . '已存在');
            }
        }

        if ($_POST['m_id']) {
            $mold_detail = $moldObj->dump(array('mold_code' => $mold_code), 'm_id');
            if ($mold_detail && $mold_detail['m_id'] != $_POST['m_id']) {
                $this->end(false, $mold_code . '已存在');
            }
        }

        $data = array(
            'mold_code' => $mold_code,
        );
        if (!$_POST['m_id']) {
            $data['create_time'] = time();
        } else {
            $data['m_id'] = $_POST['m_id'];
        }

        $moldObj->save($data);
        $this->end(true, '操作成功');
    }

    public function getMoldByCode()
    {
        $m_id = $_POST['m_id'];
        if ($m_id) {
            $moldObj = app::get('goldentra')->model('mold');
            $data = $moldObj->dump(array('m_id' => $m_id), 'm_id,mold_code');
            if ($data) {
                echo "{id:'" . $data['m_id'] . "',name:'" . $data['mold_code'] . "'}";
                exit;
            }
        }
    }

    public function exportTemplateOfMold()
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=导入模号模板-' . date('Ymd') . '.csv');
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');

        $titles = array('*:模号');
        array_walk($titles, function (&$title) {
            $title = kernel::single('base_charset')->utf2local($title);
        });
        echo '"' . implode('","', $titles) . '"';
        exit;
    }

    public function importMold()
    {
        $row = str_getcsv($_POST['content']);
        $mold_code = isset($row[0]) ? trim($row[0]) : '';

        if (!$mold_code) {
            echo json_encode(array('error' => '模号不能为空')) . 'ok.';
            exit;
        }

        $moldObj = app::get('goldentra')->model('mold');
        $exists = $moldObj->dump(array('mold_code' => $mold_code), 'm_id');
        if ($exists) {
            echo json_encode(array('error' => $mold_code . '已存在')) . 'ok.';
            exit;
        }

        $moldObj->save(array(
            'mold_code' => $mold_code,
            'create_time' => time(),
        ));
        echo json_encode(array('success' => '成功')) . 'ok.';
        exit;
    }
}
