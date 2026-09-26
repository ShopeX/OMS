<?php

class machinecenter_finder_material_sn_delivery_op
{
    var $column_branch_name = '仓库名称';
    var $column_branch_name_width = '120';

    function column_branch_name($row)
    {
        static $branchMap = array();
        $branchId = $row['branch_id'];
        if (!$branchId) {
            return '';
        }
        if (!isset($branchMap[$branchId])) {
            $branch = app::get('ome')->model('branch')->db_dump($branchId, 'name');
            $branchMap[$branchId] = $branch ? $branch['name'] : '';
        }
        return $branchMap[$branchId];
    }
}
