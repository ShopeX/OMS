<?php

/**
 * 库存同步字段字典（OpenAPI V1.3 小写 snake_case）
 */
class goldentra_stock_fields
{
    public static $ext_fields = array(
        'ext_1', 'ext_2', 'ext_3', 'ext_4', 'ext_5',
        'ext_6', 'ext_7', 'ext_8', 'ext_9', 'ext_10',
        'ext_11', 'ext_12', 'ext_13', 'ext_14', 'ext_15',
        'ext_16', 'ext_17', 'ext_18', 'ext_19', 'ext_20',
    );

    public static $csv_columns = array(
        0 => 'branch_bn',
        1 => 'unique_code',
        2 => 'qty',
        3 => 'type',
        4 => 'snapshot_time',
    );

    public static $csv_required = array(
        'branch_bn',
        'unique_code',
        'qty',
        'type',
        'snapshot_time',
    );

    /** OpenAPI 明细必填 */
    public static $line_required = array(
        'branch_bn',
        'plant',
        'unique_code',
        'qty',
        'type',
        'snapshot_time',
    );

    public static $stock_types = array('increment', 'full');

    public static $descriptions = array(
        'branch_bn' => '库位（SAP 库位编码，落库保留）',
        'plant' => '工厂/仓库编码；匹配 OMS 仓库时优先用 plant，找不到再用 plant_库位',
        'unique_code' => '商品唯一码，对应 serial_number',
        'qty' => '数量；计价类 SAP 传克重（如 4.36），OMS 归一化为 >0→1、≤0→0',
        'raw_qty' => 'SAP 原始数量，按接口/FTP 文件原值保存',
        'raw_unit' => 'SAP 原始单位，按接口/FTP 文件原值保存',
        'type' => '库存类型：increment 增量 / full 全量',
        'snapshot_time' => '库存时间 YYYY-MM-DD HH:MM:SS',
        'unit' => '单位，如克',
        'sync_batch_no' => '推送批次号（OpenAPI 顶层，审计用）',
    );

    public static function normalizeLine(array $line)
    {
        return array(
            'branch_bn' => goldentra_api_normalize::pickString($line, 'branch_bn'),
            'plant' => goldentra_api_normalize::pickString($line, 'plant'),
            'unique_code' => goldentra_api_normalize::pickString($line, 'unique_code'),
            'qty' => goldentra_api_normalize::pickString($line, 'qty'),
            'type' => goldentra_api_normalize::pickString($line, 'type'),
            'snapshot_time' => goldentra_api_normalize::pickString($line, 'snapshot_time'),
            'unit' => goldentra_api_normalize::pickString($line, 'unit'),
        );
    }

    public static function buildExtField($raw)
    {
        $known = array_merge(self::$line_required, array('unit', 'sync_batch_no'));
        return goldentra_api_normalize::buildExtField($raw, $known, self::$ext_fields);
    }
}
