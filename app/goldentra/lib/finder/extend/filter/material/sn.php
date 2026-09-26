<?php

/**
 * 机器中心 SN 列表高级筛选：主档 service + SN props service
 */
class goldentra_finder_extend_filter_material_sn
{
    public function get_extend_colums()
    {
        $columns = array_merge(
            machinecenter_material_master::extendFilterColumns(),
            machinecenter_material_sn_props::extendFilterColumns()
        );
        if (empty($columns)) {
            return array();
        }

        return array(
            'material_sn' => array(
                'columns' => $columns,
            ),
        );
    }
}
