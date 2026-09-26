<?php

/**
 * @deprecated 确认收货已并入销售/退货上传同表，菜单已下线；保留控制器避免历史链接报错
 */
class goldentra_ctl_admin_pushreceipt extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        header('Location: index.php?app=goldentra&ctl=admin_pushsales&act=index');
        exit;
    }

    public function retry()
    {
        $this->begin('index.php?app=goldentra&ctl=admin_pushsales&act=index');
        $this->end(false, '确认收货上传菜单已下线，请到「销售/退货上传(发货+确认收货)」处理');
    }
}
