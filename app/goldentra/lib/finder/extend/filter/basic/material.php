<?php

/**
 * 基础物料高级筛选：主档字段（由 goldentra service 提供列定义）
 */
class goldentra_finder_extend_filter_basic_material
{
    public function get_extend_colums()
    {
        $service = kernel::single('goldentra_service_basic_material');
        if (!$service->isEnabled()) {
            return array();
        }

        $columns = $service->getExtendFilterColumns();
        if (empty($columns)) {
            return array();
        }

        return array(
            'basic_material' => array(
                'columns' => $columns,
            ),
        );
    }
}
