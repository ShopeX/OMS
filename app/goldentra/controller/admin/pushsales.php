<?php

class goldentra_ctl_admin_pushsales extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function _views()
    {
        $mdl = app::get('goldentra')->model('sap_push_sales');
        $sub_menu = array(
            0 => array('label' => app::get('base')->_('全部'), 'filter' => array(), 'optional' => false),
            1 => array('label' => app::get('base')->_('发货待上传'), 'filter' => array('sync_status' => 'pending'), 'optional' => false),
            2 => array('label' => app::get('base')->_('发货失败'), 'filter' => array('sync_status' => 'fail'), 'optional' => false),
            3 => array('label' => app::get('base')->_('发货成功'), 'filter' => array('sync_status' => 'succ'), 'optional' => false),
            4 => array('label' => app::get('base')->_('确认收货待上传'), 'filter' => array('sync_status2' => 'pending'), 'optional' => false),
            5 => array('label' => app::get('base')->_('确认收货失败'), 'filter' => array('sync_status2' => 'fail'), 'optional' => false),
            6 => array('label' => app::get('base')->_('销售'), 'filter' => array('zsalestype' => '01'), 'optional' => true),
            7 => array('label' => app::get('base')->_('退货'), 'filter' => array('zsalestype' => '02'), 'optional' => true),
        );
        $i = 0;
        foreach ($sub_menu as $k => $v) {
            $filter = $v['filter'] ? $v['filter'] : array();
            $sub_menu[$k]['filter'] = $filter ?: null;
            $sub_menu[$k]['addon'] = $mdl->viewcount($filter);
            $sub_menu[$k]['href'] = 'index.php?app=goldentra&ctl=admin_pushsales&act=index&view=' . $i++;
        }
        return $sub_menu;
    }

    public function index()
    {
        $this->finder('goldentra_mdl_sap_push_sales', array(
            'title' => 'SAP销售/退货上传(发货SD003+确认收货SD004)',
            'use_view_tab' => true,
            'actions' => array(
                array(
                    'label' => '重新上传',
                    'submit' => 'index.php?app=goldentra&ctl=admin_pushsales&act=retry',
                    'target' => 'refresh',
                    'confirm' => '确认将选中失败/待上传记录重新置为待上传？',
                ),
            ),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'orderBy' => 'push_id DESC',
        ));
    }

    public function retry()
    {
        $view = isset($_GET['view']) ? intval($_GET['view']) : 0;
        $this->begin('index.php?app=goldentra&ctl=admin_pushsales&act=index&view=' . $view);
        if (!empty($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') {
            $this->end(false, '不支持全选');
        }
        $ids = isset($_POST['push_id']) ? (array) $_POST['push_id'] : array();
        $result = kernel::single('goldentra_push')->retryByIds('sap_push_sales', $ids);
        $msg = sprintf('重试：成功 %d，跳过/失败 %d', $result['ok'], $result['fail']);
        if (!empty($result['fail_msgs'])) {
            $msg .= '。' . implode('；', array_slice($result['fail_msgs'], 0, 5));
        }
        $this->end($result['ok'] > 0 || $result['fail'] === 0, $msg);
    }
}
