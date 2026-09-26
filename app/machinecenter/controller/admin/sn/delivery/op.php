<?php

class machinecenter_ctl_admin_sn_delivery_op extends desktop_controller
{
    public $name       = '门店发货唯一码记录';
    public $workground = 'machine_center';

    public function index()
    {
        $params = array(
            'title'                 => '门店发货唯一码记录',
            'use_buildin_set_tag'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => true,
            'use_buildin_importxls' => false,
            'use_buildin_recycle'   => false,
            'actions'               => array(),
            'base_filter'           => array('source' => 'wap'),
            'finder_cols'           => material_basic_material_master::mergeFinderCols(
                'delivery_bn,order_bn,column_branch_name,store_bn,store_name,material_bn,sn,op_name,op_no,consign_time,source',
                'material_bn',
                machinecenter_material_master::SERVICE_LIST
            ),
            'orderBy'               => 'consign_time desc',
        );

        $this->finder('machinecenter_mdl_material_sn_delivery_op', $params);
    }
}
