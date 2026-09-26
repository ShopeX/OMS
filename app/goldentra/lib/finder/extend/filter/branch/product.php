<?php

/**
 * 仓库库存高级筛选：主档字段（由 goldentra service 提供列定义）
 *
 * 不设置 panel_id，仅出现在 Finder 高级筛选，不显示在列表上方筛选条。
 */
class goldentra_finder_extend_filter_branch_product
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
            'branch_product' => array(
                'columns' => $columns,
            ),
        );
    }
}
