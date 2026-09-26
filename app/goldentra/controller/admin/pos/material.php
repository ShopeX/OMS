<?php

class goldentra_ctl_admin_pos_material extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        $params = array(
            'title' => 'POS主档信息',
            'actions' => array(),
            'base_filter' => array(),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
        );
        $this->finder('goldentra_mdl_pos_material', $params);
    }
}