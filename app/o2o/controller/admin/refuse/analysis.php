<?php

class o2o_ctl_admin_refuse_analysis extends desktop_controller
{
    public $name       = '门店拒单列表';
    public $workground = 'o2o_center';

    public function index()
    {
        $params = array(
            'title'               => '门店拒单列表',
            'base_filter'         => array(),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter'  => true,
            'use_buildin_export'  => true,
            'use_buildin_import'  => false,
            'orderBy'             => 'sra_id DESC',
        );

        $this->finder('o2o_mdl_store_refuse_analysis', $params);
    }
}
