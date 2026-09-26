<?php

class goldentra_ctl_admin_invest_goldprice extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        $this->finder('goldentra_mdl_invest_goldprice', array(
            'title' => '投资金价',
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'use_buildin_recycle' => false,
        ));
    }
}
