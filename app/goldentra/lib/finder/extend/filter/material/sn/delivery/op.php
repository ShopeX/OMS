<?php

/**
 * 门店发货唯一码记录高级筛选：由 machinecenter.service.material.master 提供
 */
class goldentra_finder_extend_filter_material_sn_delivery_op
{
    public function get_extend_colums()
    {
        $columns = machinecenter_material_master::extendFilterColumns();
        if (empty($columns)) {
            return array();
        }

        return array(
            'material_sn_delivery_op' => array(
                'columns' => $columns,
            ),
        );
    }
}
