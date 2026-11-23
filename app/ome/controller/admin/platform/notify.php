<?php
/**
 * 平台通知详情页（只读展示）
 */
class ome_ctl_admin_platform_notify extends desktop_controller
{
    var $name = '平台通知';
    var $workground = 'setting_tools';

    /**
     * 平台通知详情
     */
    public function index()
    {
        $data = kernel::single('ome_shop_platform_luban_product')->getDetailData();
        $this->pagedata['data'] = $data;
        $this->pagedata['back_url'] = 'index.php?ctl=dashboard&act=index';
        $this->page('admin/platform/notify.html');
    }
}
