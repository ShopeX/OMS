<?php

/**
 * 基础物料主档字段 Service 桥接（解耦 material / goldentra）
 *
 * material 侧不感知具体字段名，所有列定义由 service 返回；
 * 未注册 / 未启用 service 时返回空，业务侧仅保留基础列。
 */
class material_basic_material_master
{
    /** 与 goldentra_service_basic_material::FILTER_PREFIX 保持一致 */
    const FILTER_PREFIX = 'gt_filter_';

    const DEFAULT_SERVICE_LIST = 'material.service.basic.master';

    private static $_services = array();

    /**
     * @param string|null $serviceList servicelist id，默认基础物料主档
     */
    public static function service($serviceList = null)
    {
        if ($serviceList === null || $serviceList === '') {
            $serviceList = self::DEFAULT_SERVICE_LIST;
        }

        if (!array_key_exists($serviceList, self::$_services)) {
            self::$_services[$serviceList] = null;
            foreach (kernel::servicelist($serviceList) as $instance) {
                if (method_exists($instance, 'isEnabled') && $instance->isEnabled()) {
                    self::$_services[$serviceList] = $instance;
                    break;
                }
            }
        }

        return self::$_services[$serviceList];
    }

    public static function extraCols($serviceList = null)
    {
        $service = self::service($serviceList);
        if (!$service || !method_exists($service, 'getExtraCols')) {
            return array();
        }

        return $service->getExtraCols();
    }

    public static function extendFilterColumns($serviceList = null)
    {
        $service = self::service($serviceList);
        if (!$service || !method_exists($service, 'getExtendFilterColumns')) {
            return array();
        }

        return $service->getExtendFilterColumns();
    }

    /**
     * 列表顶部快捷搜索项（由 service 提供；未实现则空）
     */
    public static function searchOptions($serviceList = null)
    {
        $service = self::service($serviceList);
        if (!$service || !method_exists($service, 'getSearchOptions')) {
            return array();
        }

        return $service->getSearchOptions();
    }

    public static function fillListRows($rows, $serviceList = null)
    {
        if (!is_array($rows) || empty($rows)) {
            return $rows;
        }

        $service = self::service($serviceList);
        if (!$service || !method_exists($service, 'fillListRows')) {
            return $rows;
        }

        return $service->fillListRows($rows);
    }

    public static function applyFilter(&$filter, array &$where, $tableAlias = null, $idColumn = 'bm_id', $serviceList = null)
    {
        if (!is_array($filter) || !self::hasGtFilterParams($filter)) {
            return;
        }

        $service = self::service($serviceList);
        if (!$service || !method_exists($service, 'applyFilter')) {
            return;
        }

        $service->applyFilter($filter, $where, $tableAlias, $idColumn);
    }

    /**
     * 是否包含主档扩展筛选项（gt_filter_*），无则业务 getList 不进入 service
     */
    protected static function hasGtFilterParams(array $filter)
    {
        $prefix = self::FILTER_PREFIX;
        $prefixLen = strlen($prefix);
        foreach ($filter as $key => $val) {
            if (strncmp((string) $key, $prefix, $prefixLen) === 0 && $val !== '' && $val !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * 兼容 product_id / bn 行数据的主档填充
     */
    public static function fillListRowsWithIdField($rows, $idField = 'product_id', $serviceList = null)
    {
        if (!is_array($rows) || empty($rows)) {
            return $rows;
        }

        static $bnBmCache = array();

        foreach ($rows as $k => $row) {
            if (!empty($row['bm_id'])) {
                continue;
            }

            $idValue = null;
            if (!empty($row[$idField])) {
                $idValue = $row[$idField];
            } else {
                $suffix = '_' . $idField;
                $suffixLen = strlen($suffix);
                foreach ($row as $field => $val) {
                    if ($val === '' || $val === null) {
                        continue;
                    }
                    if ($field === $idField || substr($field, -$suffixLen) === $suffix) {
                        $idValue = $val;
                        break;
                    }
                }
            }

            if ($idValue !== null && $idValue !== '') {
                $rows[$k]['bm_id'] = intval($idValue);
                $rows[$k][$idField] = $idValue;
                continue;
            }

            if (!empty($row['bn'])) {
                $bn = trim((string) $row['bn']);
                if ($bn !== '') {
                    if (!array_key_exists($bn, $bnBmCache)) {
                        $material = app::get('material')->model('basic_material')->dump(
                            array('material_bn' => $bn),
                            'bm_id'
                        );
                        $bnBmCache[$bn] = $material ? intval($material['bm_id']) : 0;
                    }
                    if ($bnBmCache[$bn] > 0) {
                        $rows[$k]['bm_id'] = $bnBmCache[$bn];
                        $rows[$k][$idField] = $bnBmCache[$bn];
                    }
                }
            }
        }

        return self::fillListRows($rows, $serviceList);
    }

    public static function appendMasterWhere(&$filter, &$sqlWhere, $tableAlias = null, $idColumn = 'bm_id', $serviceList = null)
    {
        if (!is_array($filter) || !self::hasGtFilterParams($filter)) {
            return;
        }

        $where = array();
        self::applyFilter($filter, $where, $tableAlias, $idColumn, $serviceList);
        if (empty($where)) {
            return;
        }

        foreach ($where as $item) {
            $sqlWhere .= ' AND ' . trim($item);
        }
    }

    /**
     * 将 service 返回的主档列键合并进 finder_cols
     * 未实现 / 未启用 service 时原样返回 $baseCols
     *
     * @param string      $baseCols    业务自身列
     * @param string      $insertAfter 插入位置锚点列
     * @param string|null $serviceList servicelist id
     * @return string
     */
    public static function mergeFinderCols($baseCols, $insertAfter = 'material_bn', $serviceList = null)
    {
        $service = self::service($serviceList);
        if (!$service || !method_exists($service, 'getFinderColKeys')) {
            return $baseCols;
        }

        $masterCols = $service->getFinderColKeys();
        if (empty($masterCols)) {
            return $baseCols;
        }

        $parts = array_map('trim', explode(',', $baseCols));
        $pos = array_search($insertAfter, $parts, true);
        if ($pos === false) {
            return $baseCols . ',' . implode(',', $masterCols);
        }

        array_splice($parts, $pos + 1, 0, $masterCols);

        return implode(',', $parts);
    }
}
