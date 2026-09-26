<?php
/**
 * 销售物料-基础物料扩展列（sdb_material_basic_material_props）统一获取
 * 供 sales add 与 basic getMaterial 共用，保证组合表格下拉选项与普通一致
 */
class material_sales_extended_cols
{
    /**
     * 扩展列 + service 合并：取扩展列后调 service 取 params，按 col_key 合并 options/col_type
     * @return array 带 options 的 extendedCols
     */
    public function getExtendedColsWithParams()
    {
        $extendedCols = $this->_getBmPropsExtendedCols();
        $params = array();
        try {
            if ($service = kernel::servicelist('material.service.salesmaterial.props.params.format')) {
                foreach ($service as $object => $instance) {
                    if ($instance && method_exists($instance, 'format_params')) {
                        $params = $instance->format_params($extendedCols);
                        if (!is_array($params)) {
                            $params = array();
                        }
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $params = array();
        }
       
        foreach ($extendedCols as &$col) {
            $colKey = isset($col['col_key']) ? $col['col_key'] : '';
            if ($colKey === '') {
                continue;
            }
            foreach ($params as $p) {
                if (isset($p['col_key']) && $p['col_key'] === $colKey && !empty($p['options']) && is_array($p['options'])) {
                    $col['options'] = $p['options'];
                    if (isset($p['col_type']) && strtolower((string) $p['col_type']) === 'select') {
                        $col['col_type'] = 'select';
                    }
                    break;
                }
            }
        }
        unset($col);
        return $extendedCols;
    }

    private function _getBmPropsExtendedCols()
    {
        $errLevel = error_reporting(E_ALL & ~E_DEPRECATED);
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
                    if (!empty($row['filter_data'])) {
                        $decoded = json_decode($row['filter_data'], true);
                        $row['filter_data'] = is_array($decoded) ? $decoded : null;
                    } else {
                        $row['filter_data'] = null;
                    }
                }
                unset($row);
                return $list;
            }
        } catch (\Throwable $e) {
        } finally {
            error_reporting($errLevel);
        }
        // 未配置自定义扩展列时不兜底，避免无条件展示调色号
        return array();
    }
}
