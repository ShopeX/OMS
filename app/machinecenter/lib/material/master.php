<?php

/**
 * 机器中心主档字段桥接（解耦 machinecenter / goldentra）
 *
 * 通过 servicelist(machinecenter.service.material.master) 获取扩展；
 * 未实现 service 时无扩展列 / 筛选项。
 */
class machinecenter_material_master
{
    const SERVICE_LIST = 'machinecenter.service.material.master';

    public static function extraCols()
    {
        return material_basic_material_master::extraCols(self::SERVICE_LIST);
    }

    public static function extendFilterColumns()
    {
        return material_basic_material_master::extendFilterColumns(self::SERVICE_LIST);
    }

    public static function searchOptions()
    {
        return material_basic_material_master::searchOptions(self::SERVICE_LIST);
    }

    public static function fillListRows($rows)
    {
        return material_basic_material_master::fillListRows($rows, self::SERVICE_LIST);
    }

    public static function fillListRowsWithIdField($rows, $idField = 'bm_id')
    {
        return material_basic_material_master::fillListRowsWithIdField($rows, $idField, self::SERVICE_LIST);
    }

    public static function applyFilter(&$filter, array &$where, $tableAlias = null, $idColumn = 'bm_id')
    {
        material_basic_material_master::applyFilter($filter, $where, $tableAlias, $idColumn, self::SERVICE_LIST);
    }

    public static function appendMasterWhere(&$filter, &$sqlWhere, $tableAlias = null, $idColumn = 'bm_id')
    {
        material_basic_material_master::appendMasterWhere($filter, $sqlWhere, $tableAlias, $idColumn, self::SERVICE_LIST);
    }

    public static function mergeFinderCols($baseCols, $insertAfter = 'material_bn')
    {
        return material_basic_material_master::mergeFinderCols($baseCols, $insertAfter, self::SERVICE_LIST);
    }
}
