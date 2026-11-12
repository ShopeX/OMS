<?php
/**
 * 店铺收支明细匹配失败通知配置
 */
class financebase_ctl_admin_shop_settlement_match_notify extends desktop_controller
{
    public function index()
    {
        // 与 Vue api 共用同一 capability（读取/校验/归一化/持久化集中一处，避免两处漂移）
        $data = kernel::single('financebase_capability_shop_settlement_match_notify')->loadPageData();

        $this->pagedata['config']                 = $data['config'];
        $this->pagedata['today_status']           = $data['today_status'];
        $this->pagedata['configured_hour_passed'] = $data['configured_hour_passed'];
        $this->pagedata['monitor_config']         = $data['monitor_config'];
        $this->pagedata['monitor_links']          = $data['monitor_links'];
        $this->page('admin/shop/settlement/match/notify.html');
    }

    public function save()
    {
        $this->begin();

        try {
            $message = kernel::single('financebase_capability_shop_settlement_match_notify')
                ->saveConfig((array) $_POST);
        } catch (InvalidArgumentException $exception) {
            $this->end(false, $exception->getMessage());
            return;
        }

        $this->end(true, $message);
    }
}
