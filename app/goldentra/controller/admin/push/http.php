<?php

class goldentra_ctl_admin_push_http extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        if (!empty($_POST['base_url']) || isset($_POST['enabled'])) {
            $this->doSave();
            return;
        }
        $this->pagedata['cfg'] = kernel::single('goldentra_push_setting')->getForForm();
        $this->pagedata['sd003_url'] = kernel::single('goldentra_push_setting')->resolveUrl('SD003');
        $this->page('admin/push/http_setting.html');
    }

    public function save()
    {
        $this->doSave();
    }

    private function doSave()
    {
        $this->begin('index.php?app=goldentra&ctl=admin_push_http&act=index');
        try {
            kernel::single('goldentra_push_setting')->save($_POST);
        } catch (Exception $e) {
            $this->end(false, $e->getMessage());
        }
        $this->end(true, '保存成功');
    }
}
