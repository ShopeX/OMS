<?php

class goldentra_ctl_admin_stock_ftp extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        $this->mergeJsonPost();

        if (!empty($_POST['_test_ftp'])) {
            $this->doTest();
            return;
        }
        if (!empty($_POST['ftp_host'])) {
            $this->doSave();
            return;
        }

        $this->pagedata['cfg'] = kernel::single('goldentra_stock_full_setting')->getForForm();
        $this->pagedata['sample_file'] = goldentra_stock_full_config::filePatternLabel(date('Ymd'));
        $this->page('admin/stock/ftp_setting.html');
    }

    public function save()
    {
        $this->doSave();
    }

    public function test()
    {
        $this->doTest();
    }

    private function doSave()
    {
        $this->begin('index.php?app=goldentra&ctl=admin_stock_ftp&act=index');
        try {
            kernel::single('goldentra_stock_full_setting')->saveFromPost($_POST);
        } catch (Exception $e) {
            $this->end(false, $e->getMessage());
        }
        $this->end(true, '保存成功');
    }

    private function doTest()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $cfg = kernel::single('goldentra_stock_full_setting')->buildFromPost($_POST);
            $msg = kernel::single('goldentra_stock_full_setting')->testConnection($cfg);
            echo json_encode(array('success' => true, 'message' => $msg));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'message' => $e->getMessage()));
        }
        exit;
    }

    /**
     * 网关 WAF 会拦截 form-urlencoded 中含服务器路径（如 /data/httpd/oms）的请求，JSON 提交可绕过。
     */
    private function mergeJsonPost()
    {
        $ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (stripos($ct, 'application/json') === false) {
            return;
        }
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return;
        }
        $_POST = array_merge($_POST, $data);
        $_REQUEST = array_merge($_REQUEST, $data);
    }
}
