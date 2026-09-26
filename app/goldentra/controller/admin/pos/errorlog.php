<?php

class goldentra_ctl_admin_pos_errorlog extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        $params = array(
            'title' => 'POS主档信息接收错误日志',
            'actions' => array(),
            'base_filter' => array(),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
            'orderBy' =>'log_id DESC'
        );
        $this->finder('goldentra_mdl_mapping_errorlog', $params);
    }
}
