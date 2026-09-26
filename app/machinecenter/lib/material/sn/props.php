<?php

/**
 * 机器中心 SN props 扩展桥接（解耦 machinecenter / goldentra）
 *
 * servicelist: machinecenter.service.material.sn.props
 */
class machinecenter_material_sn_props
{
    const SERVICE_LIST = 'machinecenter.service.material.sn.props';
    const FILTER_PREFIX = 'sn_prop_filter_';

    public static function extraCols()
    {
        return material_basic_material_master::extraCols(self::SERVICE_LIST);
    }

    public static function fillListRows($rows)
    {
        return material_basic_material_master::fillListRows($rows, self::SERVICE_LIST);
    }

    public static function mergeFinderCols($baseCols, $insertAfter = 'column_sn_label')
    {
        return material_basic_material_master::mergeFinderCols($baseCols, $insertAfter, self::SERVICE_LIST);
    }

    public static function extendFilterColumns()
    {
        return material_basic_material_master::extendFilterColumns(self::SERVICE_LIST);
    }

    public static function searchOptions()
    {
        return material_basic_material_master::searchOptions(self::SERVICE_LIST);
    }

    public static function applyFilter(&$filter, array &$where, $tableAlias = null, $idColumn = 'id')
    {
        $service = material_basic_material_master::service(self::SERVICE_LIST);
        if (!$service || !method_exists($service, 'applyFilter')) {
            return;
        }
        // 不能走 material_basic_material_master::applyFilter（其前置只认 gt_filter_）
        $service->applyFilter($filter, $where, $tableAlias, $idColumn);
    }

    /**
     * 将 SN props 筛选拼进 SQL where 字符串
     *
     * @param array $filter
     * @param string $sqlWhere
     * @param string|null $tableAlias
     * @param string $idColumn
     */
    public static function appendSnPropsWhere(&$filter, &$sqlWhere, $tableAlias = null, $idColumn = 'id')
    {
        if (!is_array($filter) || !self::hasSnPropFilterParams($filter)) {
            return;
        }

        $where = array();
        self::applyFilter($filter, $where, $tableAlias, $idColumn);
        if (empty($where)) {
            return;
        }

        foreach ($where as $item) {
            $sqlWhere .= ' AND ' . trim($item);
        }
    }

    /**
     * @param array $filter
     * @return bool
     */
    protected static function hasSnPropFilterParams(array $filter)
    {
        $service = material_basic_material_master::service(self::SERVICE_LIST);
        if ($service && method_exists($service, 'hasExtendFilter')) {
            return $service->hasExtendFilter($filter);
        }
        $prefix = self::FILTER_PREFIX;
        $prefixLen = strlen($prefix);
        foreach ($filter as $key => $val) {
            $k = (string) $key;
            $base = (($p = strpos($k, '|')) !== false) ? substr($k, 0, $p) : $k;
            if (strncmp($base, $prefix, $prefixLen) === 0 && $val !== '' && $val !== null) {
                return true;
            }
        }
        return false;
    }
}
