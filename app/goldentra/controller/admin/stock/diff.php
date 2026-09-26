<?php

class goldentra_ctl_admin_stock_diff extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function _views()
    {
        $mdl = app::get('goldentra')->model('stock_diff');
        $sub_menu = array(
            0 => array(
                'label' => app::get('base')->_('库存差异'),
                'filter' => array('diff_type' => 'qty_mismatch'),
                'optional' => false,
            ),
            1 => array(
                'label' => app::get('base')->_('SAP没有OMS有'),
                'filter' => array('diff_type' => 'oms_only'),
                'optional' => false,
            ),
            2 => array(
                'label' => app::get('base')->_('SAP有OMS没有'),
                'filter' => array('diff_type' => 'sap_only'),
                'optional' => false,
            ),
            3 => array(
                'label' => app::get('base')->_('全部'),
                'filter' => array(),
                'optional' => false,
            ),
        );

        $i = 0;
        foreach ($sub_menu as $k => $v) {
            $filter = $v['filter'] ? $v['filter'] : array();
            $sub_menu[$k]['filter'] = $filter ?: null;
            $sub_menu[$k]['addon'] = $mdl->count($filter);
            $sub_menu[$k]['href'] = 'index.php?app=goldentra&ctl=admin_stock_diff&act=index&view=' . $i++;
        }

        return $sub_menu;
    }

    public function index()
    {
        $this->finder('goldentra_mdl_stock_diff', array(
            'title' => '电商仓库存差异',
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'orderBy' => 'diff_id DESC',
            'finder_cols' => 'branch_name,branch_bn,unique_code,bn,mold_code,sap_qty,oms_qty,diff_qty,diff_type,sync_batch_no,snapshot_date,at_time',
        ));
    }
}
