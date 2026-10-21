<?php
/**
 * 销售物料-基础物料扩展列（sdb_material_basic_material_props）统一获取
 * 供 sales add 与 basic getMaterial 共用，保证组合表格下拉选项与普通一致
 */
class material_sales_extended_cols
{
    /**
     * 扩展列：读 sdb_desktop_customcols（tbl_name=sdb_material_basic_material_props），合并 filter_data 中的 options
     * @return array 带 options 的 extendedCols；未配置任何扩展列时返回空数组
     */
    public function getExtendedColsWithParams()
    {
        $extendedCols = $this->_getBmPropsExtendedCols();
        if (empty($extendedCols)) {
            return array();
        }

        foreach ($extendedCols as &$col) {
            $colKey = isset($col['col_key']) ? $col['col_key'] : '';
            if ($colKey === '') {
                continue;
            }
            if (empty($col['options']) || !is_array($col['options'])) {
                $col['options'] = array();
            }
            // 下拉选项与显隐条件均来自 sdb_desktop_customcols.filter_data（JSON），与基础物料 customcols 一致
            if (!empty($col['filter_data']) && is_array($col['filter_data'])) {
                if (!empty($col['filter_data']['options']) && is_array($col['filter_data']['options'])) {
                    $col['options'] = $col['filter_data']['options'];
                }
                if (isset($col['filter_data']['col_type']) && strtolower((string) $col['filter_data']['col_type']) === 'select'
                    && !empty($col['options'])) {
                    $col['col_type'] = 'select';
                }
            }
            if (strtolower((string) ($col['col_type'] ?? '')) === 'select'
                && (empty($col['options']) || !is_array($col['options']))) {
                $col['col_type'] = 'input';
                $col['options'] = array();
            }
        }
        unset($col);
        return $extendedCols;
    }

    private function _getBmPropsExtendedCols()
    {
       
        try {
            $customcolsMdl = app::get('desktop')->model('customcols');
            $list = $customcolsMdl->getList('col_name,col_key,col_type,filter_data', array(
                'tbl_name' => 'sdb_material_basic_material_props',
                'disabled'  => 'false',
            ), 0, -1, 'p_order ASC, id ASC');
            if (!empty($list)) {
                foreach ($list as &$row) {
                    if (empty($row['col_type'])) {
                        $row['col_type'] = 'input';
                    }
                    $row['options'] = array();
                    if (!empty($row['filter_data'])) {
                        $decoded = json_decode($row['filter_data'], true);
                        $row['filter_data'] = is_array($decoded) ? $decoded : null;
                        if (is_array($decoded) && !empty($decoded['options']) && is_array($decoded['options'])) {
                            $row['options'] = $decoded['options'];
                        }
                    } else {
                        $row['filter_data'] = null;
                    }
                    if (strtolower((string) $row['col_type']) === 'select'
                        && (empty($row['options']) || !is_array($row['options']))) {
                        $row['col_type'] = 'input';
                        $row['options'] = array();
                    }
                }
                unset($row);
                return $list;
            }
        } catch (\Throwable $e) {
        } finally {
           
        }
        // 未配置自定义扩展列时不兜底，避免无条件展示调色号
        return array();
    }
}
