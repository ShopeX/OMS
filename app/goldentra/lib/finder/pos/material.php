<?php

class goldentra_finder_pos_material
{
    var $detail_log = "操作日志";

    function detail_log($id)
    {
        $render = app::get('goldentra')->render();
        $oOperation_log = app::get('ome')->model('operation_log');
        $data = app::get('goldentra')->model('pos_material')->dump(array('id' => $id), 'unique_code');
        $logdata = $oOperation_log->read_log(array('obj_type' => 'pos_material@goldentra', 'obj_id' => $data['unique_code']), 0, -1);
        foreach ($logdata as $k => $v) {
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
        }
        $render->pagedata['log'] = $logdata;
        return $render->fetch('admin/pos/material/operation_log.html');
    }
}