<?php

class goldentra_finder_stock
{
    var $detail_process = '处理信息';
    var $detail_operation_log = '操作日志';

    public function detail_process($stock_id)
    {
        $row = app::get('goldentra')->model('stock')->dump(
            $stock_id,
            'process_status,process_msg,process_time,qty,oms_qty,diff_qty,snapshot_date'
        );
        $render = app::get('goldentra')->render();
        $render->pagedata['row'] = $row;
        return $render->fetch('admin/stock/detail_process.html');
    }

    public function detail_operation_log($stock_id)
    {
        $render = app::get('goldentra')->render();
        $logs = app::get('ome')->model('operation_log')->read_log(
            array(
                'obj_id' => $stock_id,
                'obj_type' => goldentra_stock_operation_log::OBJ_TYPE,
            ),
            0,
            -1
        );
        foreach ($logs as $k => $log) {
            $logs[$k]['operate_time'] = date('Y-m-d H:i:s', $log['operate_time']);
        }
        $render->pagedata['logs'] = $logs;
        return $render->fetch('admin/stock/detail_operation_log.html');
    }
}
