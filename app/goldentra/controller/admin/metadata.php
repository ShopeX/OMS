<?php

class goldentra_ctl_admin_metadata extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        $params = array(
            'title' => '数据字典',
            'finder_cols' => 'code,name,unique_key,column_items,at_time,up_time',
            'base_filter' => array(),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
            'orderBy' => 'metadata_id DESC',
        );

        $this->finder('goldentra_mdl_metadata', $params);
    }

    /**
     * 弹窗：字典值明细列表
     */
    public function show($metadata_id = null)
    {
        if (empty($metadata_id) && !empty($_GET['p'][0])) {
            $metadata_id = intval($_GET['p'][0]);
        }
        $metadata_id = intval($metadata_id);
        if ($metadata_id <= 0) {
            echo '无效的字典 ID';
            return;
        }

        $header = app::get('goldentra')->model('metadata')->dump($metadata_id, 'code,name,unique_key');
        $title = '字典值明细';
        if ($header) {
            $title .= '（' . $header['code'] . ' / ' . $header['name'] . '）';
        }

        $this->finder('goldentra_mdl_metadata_item', array(
            'title' => $title,
            'finder_cols' => 'unique_key,column_ext,at_time',
            'base_filter' => array('metadata_id' => $metadata_id),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
            'orderBy' => 'item_id ASC',
        ));
    }
}
