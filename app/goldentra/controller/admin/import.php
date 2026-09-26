<?php

class goldentra_ctl_admin_import extends desktop_controller
{
    /**
     * 进度条逐行导入（CSV 按行 POST 到目标 app/ctl/act）
     */
    public function progress_import()
    {
        $_GET = utils::htmlspecialchars_array($_GET);
        $get = kernel::single('base_component_request')->get_get();
        $render = kernel::single('desktop_controller');

        $importApp = $get['import_app'];
        $importCtl = $get['import_ctl'];
        $importAct = $get['import_act'];
        $importMdl = isset($get['import_mdl']) ? $get['import_mdl'] : '';
        $importFilter = isset($get['import_filter']) ? $get['import_filter'] : '';
        $importTmplName = $get['import_tmpl_name'];
        $importTmplAct = isset($get['import_tmpl_act']) ? $get['import_tmpl_act'] : '';

        if (!$importTmplAct) {
            $render->pagedata['tmpl_url'] = sprintf(
                'index.php?app=goldentra&ctl=admin_import&act=progress_import_tmplate&import_app=%s&import_tmpl_name=%s&import_tmpl_mdl=%s',
                $importApp,
                urlencode($importTmplName),
                $importMdl
            );
        } else {
            $render->pagedata['tmpl_url'] = sprintf(
                'index.php?app=%s&ctl=%s&act=%s',
                $importApp,
                $importCtl,
                $importTmplAct
            );
        }

        $render->pagedata['import_app'] = $importApp;
        $render->pagedata['import_ctl'] = $importCtl;
        $render->pagedata['import_act'] = $importAct;
        $render->pagedata['import_mdl'] = $importMdl;
        $render->pagedata['import_filter'] = $importFilter;
        $render->pagedata['import_tmpl_name'] = $importTmplName;

        if (isset($_GET['_finder']['finder_id'])) {
            $render->pagedata['finder_id'] = $_GET['_finder']['finder_id'];
        } elseif (isset($_GET['finder_id'])) {
            $render->pagedata['finder_id'] = $_GET['finder_id'];
        } else {
            $render->pagedata['finder_id'] = '';
        }

        $render->display('common/progress_import.html', 'goldentra');
    }

    /**
     * 进度条导入模版下载（依赖 model io_title）
     */
    public function progress_import_tmplate()
    {
        $get = kernel::single('base_component_request')->get_get();
        $importApp = $get['import_app'];
        $importMdl = $get['import_tmpl_mdl'];
        $importTmplName = $get['import_tmpl_name'] . date('Y-m-d') . '.csv';

        if (empty($importApp) || empty($importMdl)) {
            die('参数传递错误');
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $importTmplName);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');

        $mdl = app::get($importApp)->model($importMdl);
        if (!method_exists($mdl, 'io_title')) {
            die('mdl:' . $importMdl . ' not exists io_title method');
        }

        $ioTitle = $mdl->io_title('csv');
        $title = array();
        foreach ($ioTitle as $v) {
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        echo '"' . implode('","', $title) . '"';
        echo "\n\n";
        exit;
    }
}
